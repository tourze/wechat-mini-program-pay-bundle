<?php

namespace WechatMiniProgramPayBundle\Controller;

use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WechatMiniProgramBundle\Entity\Account;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;
use WechatPayBundle\Entity\Merchant;
use WechatPayBundle\Entity\PayOrder;
use WechatPayBundle\Entity\RefundOrder;

#[WithMonologChannel(channel: 'wechat_mini_program_pay')]
final class RefundCallbackController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/wechat-payment/mini-program/refund/{appId}/{refundNo}', name: 'wechat_mini_program_refund_callback', methods: ['POST'])]
    public function __invoke(Account $account, RefundOrder $refundOrder, Request $request): Response
    {
        $this->validateAccountAndRefundOrder($account, $refundOrder);

        $headers = $this->extractWechatHeaders($request);
        $this->validateRequiredHeaders($headers);

        $payOrder = $this->getPayOrderFromRefund($refundOrder);
        $merchant = $this->getMerchantFromPayOrder($payOrder);

        if ($this->verifyRefundSignature($headers, $request->getContent(), $merchant)) {
            return $this->handleRefundSuccess($refundOrder, $headers, $request->getContent(), $merchant);
        }

        return $this->handleRefundFailure();
    }

    private function validateAccountAndRefundOrder(Account $account, RefundOrder $refundOrder): void
    {
        if (null !== $refundOrder->getPayOrder() && $refundOrder->getPayOrder()->getAppId() !== $account->getAppId()) {
            throw $this->createAccessDeniedException('appId不匹配');
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function extractWechatHeaders(Request $request): array
    {
        return [
            'signature' => $request->headers->get('Wechatpay-Signature'),
            'timestamp' => $request->headers->get('Wechatpay-Timestamp'),
            'serial' => $request->headers->get('Wechatpay-Serial'),
            'nonce' => $request->headers->get('Wechatpay-Nonce'),
        ];
    }

    /**
     * @param array<string, string|null> $headers
     */
    private function validateRequiredHeaders(array $headers): void
    {
        if (null === $headers['signature'] || null === $headers['timestamp'] || null === $headers['nonce']) {
            throw $this->createAccessDeniedException('缺少必要的微信支付签名头');
        }
    }

    private function getPayOrderFromRefund(RefundOrder $refundOrder): PayOrder
    {
        $payOrder = $refundOrder->getPayOrder();
        if (null === $payOrder) {
            throw $this->createNotFoundException('支付订单不存在');
        }

        return $payOrder;
    }

    private function getMerchantFromPayOrder(PayOrder $payOrder): Merchant
    {
        $merchant = $payOrder->getMerchant();
        if (null === $merchant) {
            throw $this->createNotFoundException('商户信息不存在');
        }

        return $merchant;
    }

    /**
     * @param array<string, string|null> $headers
     */
    private function verifyRefundSignature(array $headers, string $body, Merchant $merchant): bool
    {
        $platformPublicKeyInstance = Rsa::from($merchant->getMchId(), Rsa::KEY_TYPE_PUBLIC);

        $timeOffsetStatus = $this->isTimestampValid($headers['timestamp']);

        $timestamp = $headers['timestamp'] ?? '';
        $nonce = $headers['nonce'] ?? '';
        $signature = $headers['signature'] ?? '';

        $verifiedStatus = Rsa::verify(
            Formatter::joinedByLineFeed($timestamp, $nonce, $body),
            $signature,
            $platformPublicKeyInstance
        );

        return $timeOffsetStatus && $verifiedStatus;
    }

    private function isTimestampValid(?string $timestamp): bool
    {
        if (null === $timestamp) {
            return false;
        }

        return 300 >= abs(Formatter::timestamp() - (int) $timestamp);
    }

    /**
     * @param array<string, string|null> $headers
     */
    private function handleRefundSuccess(RefundOrder $refundOrder, array $headers, string $body, Merchant $merchant): Response
    {
        $apiv3Key = $merchant->getApiKey();
        if (null === $apiv3Key || '' === $apiv3Key) {
            throw new PaymentConfigurationException('商户密钥不能为空');
        }

        /** @var array<string, mixed> $bodyArray */
        $bodyArray = json_decode($body, true) ?? [];
        $resource = $bodyArray['resource'] ?? [];
        assert(is_array($resource));

        $ciphertext = $resource['ciphertext'] ?? '';
        $nonce = $resource['nonce'] ?? '';
        $associatedData = $resource['associated_data'] ?? '';
        assert(is_string($ciphertext));
        assert(is_string($nonce));
        assert(is_string($associatedData));

        $decryptedData = AesGcm::decrypt($ciphertext, $apiv3Key, $nonce, $associatedData);

        $refundOrder->setCallbackResponse($decryptedData);

        /** @var array<string, mixed> $resourceArray */
        $resourceArray = json_decode($decryptedData, true) ?? [];
        $this->logger->info('退款结果通知回调结果', ['data' => $resourceArray]);

        $this->updateRefundOrderFromCallback($refundOrder, $resourceArray);

        return new Response('');
    }

    /**
     * @param array<string, mixed> $resourceArray
     */
    private function updateRefundOrderFromCallback(RefundOrder $refundOrder, array $resourceArray): void
    {
        $refundStatus = $resourceArray['refund_status'] ?? '';
        $refundId = $resourceArray['refund_id'] ?? '';
        $userReceivedAccount = $resourceArray['user_received_account'] ?? '';

        assert(is_string($refundStatus));
        assert(is_string($refundId));
        assert(is_string($userReceivedAccount));

        $refundOrder->setStatus($refundStatus);
        $refundOrder->setRefundId($refundId);
        $refundOrder->setUserReceiveAccount($userReceivedAccount);

        $successTime = $resourceArray['success_time'] ?? null;
        if (is_string($successTime) && '' !== $successTime) {
            $refundOrder->setSuccessTime(CarbonImmutable::parse($successTime));
        }

        $this->entityManager->persist($refundOrder);
        $this->entityManager->flush();
    }

    private function handleRefundFailure(): Response
    {
        return $this->json([
            'code' => 'FAIL',
            'message' => '签名验证失败',
        ]);
    }
}
