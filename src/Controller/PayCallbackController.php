<?php

namespace WechatMiniProgramPayBundle\Controller;

use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WechatMiniProgramBundle\Entity\Account;
use WechatMiniProgramPayBundle\Event\PayCallbackFailedEvent;
use WechatMiniProgramPayBundle\Event\PayCallbackSuccessEvent;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;
use WechatPayBundle\Entity\Merchant;
use WechatPayBundle\Entity\PayOrder;
use WechatPayBundle\Enum\PayOrderStatus;

#[WithMonologChannel(channel: 'wechat_mini_program_pay')]
final class PayCallbackController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_5_5.shtml
     */
    #[Route(path: '/wechat-payment/mini-program/pay/{appId}/{tradeNo}', name: 'wechat_mini_program_pay_callback', methods: ['POST'])]
    public function __invoke(
        #[MapEntity(mapping: ['appId' => 'appId'])] Account $account,
        #[MapEntity(mapping: ['tradeNo' => 'tradeNo'])] PayOrder $payOrder,
        Request $request,
    ): Response {
        $this->validateAccountAndOrder($account, $payOrder);

        if (PayOrderStatus::SUCCESS === $payOrder->getStatus()) {
            return $this->createEmptyResponse();
        }

        $headers = $this->extractWechatHeaders($request);
        $this->updateOrderWithCallback($payOrder, $headers, $request->getContent());

        $merchant = $this->getMerchantFromOrder($payOrder);
        $certificates = $this->loadCertificates($merchant);

        $this->validateRequiredHeaders($headers);

        $platformCert = $certificates['platform'];
        if (!is_object($platformCert)) {
            throw new PaymentConfigurationException('平台证书加载失败');
        }

        if ($this->verifySignature($headers, $request->getContent(), $platformCert)) {
            return $this->handleSuccessCallback($account, $payOrder, $headers, $request->getContent(), $merchant->getKey());
        }

        return $this->handleFailedCallback($account, $payOrder);
    }

    private function validateAccountAndOrder(Account $account, PayOrder $payOrder): void
    {
        if ($payOrder->getAppId() !== $account->getAppId()) {
            throw $this->createAccessDeniedException('appId不匹配');
        }
    }

    private function createEmptyResponse(): Response
    {
        return new Response('');
    }

    /**
     * @return array<string, string|null>
     */
    private function extractWechatHeaders(Request $request): array
    {
        $headers = [
            'signature' => $request->headers->get('Wechatpay-Signature'),
            'timestamp' => $request->headers->get('Wechatpay-Timestamp'),
            'serial' => $request->headers->get('Wechatpay-Serial'),
            'nonce' => $request->headers->get('Wechatpay-Nonce'),
        ];

        $this->logger->debug('小程序微信支付成功回调', array_merge($headers, [
            'body' => $request->getContent(),
        ]));

        return $headers;
    }

    /**
     * @param array<string, string|null> $headers
     */
    private function updateOrderWithCallback(PayOrder $payOrder, array $headers, string $body): void
    {
        $payOrder->setCallbackTime(CarbonImmutable::now());
        $payOrder->setResponseData($body);
        $payOrder->setResponseSerial($headers['serial']);
    }

    private function getMerchantFromOrder(PayOrder $payOrder): Merchant
    {
        $merchant = $payOrder->getMerchant();
        if (null === $merchant) {
            throw $this->createNotFoundException('商户信息不存在');
        }

        return $merchant;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadCertificates(Merchant $merchant): array
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        if (!is_string($projectDir)) {
            throw new PaymentConfigurationException('kernel.project_dir 参数必须是字符串');
        }

        $certDir = $projectDir . '/var/cert/wechat_pay/' . $merchant->getMchId();

        return [
            'private' => Rsa::from('file://' . $certDir . '_key.pem', Rsa::KEY_TYPE_PRIVATE),
            'merchant' => Rsa::from('file://' . $certDir . '_cert.pem'),
            'platform' => Rsa::from('file://' . $certDir . '_platform_cert.pem', Rsa::KEY_TYPE_PUBLIC),
        ];
    }

    /**
     * @param array<string, string|null> $headers
     */
    private function validateRequiredHeaders(array $headers): void
    {
        if (
            null === $headers['signature'] || '' === $headers['signature']
            || null === $headers['timestamp'] || '' === $headers['timestamp']
            || null === $headers['nonce'] || '' === $headers['nonce']
        ) {
            throw $this->createAccessDeniedException('缺少必要的微信支付签名头');
        }
    }

    /**
     * @param array<string, string|null> $headers
     */
    private function verifySignature(array $headers, string $body, object $platformCertificate): bool
    {
        /** @var array<string, mixed> $bodyArray */
        $bodyArray = json_decode($body, true) ?? [];

        if ('TRANSACTION.SUCCESS' !== ($bodyArray['event_type'] ?? null)) {
            return false;
        }

        $timestamp = $headers['timestamp'] ?? '';
        $nonce = $headers['nonce'] ?? '';
        $signature = $headers['signature'] ?? '';

        return Rsa::verify(
            Formatter::joinedByLineFeed($timestamp, $nonce, $body),
            $signature,
            $platformCertificate
        );
    }

    /**
     * @param array<string, string|null> $headers
     */
    private function handleSuccessCallback(Account $account, PayOrder $payOrder, array $headers, string $body, ?string $apiv3Key): Response
    {
        if (null === $apiv3Key || '' === $apiv3Key) {
            throw new PaymentConfigurationException('商户密钥不能为空');
        }

        /** @var array<string, mixed> $bodyArray */
        $bodyArray = json_decode($body, true) ?? [];
        $resource = $bodyArray['resource'] ?? [];

        if (!is_array($resource)) {
            throw new PaymentConfigurationException('无效的回调数据格式');
        }

        $ciphertext = $resource['ciphertext'] ?? '';
        $nonce = $resource['nonce'] ?? '';
        $associatedData = $resource['associated_data'] ?? '';

        if (!is_string($ciphertext) || !is_string($nonce) || !is_string($associatedData)) {
            throw new PaymentConfigurationException('回调数据缺少必要的加密字段');
        }

        $decryptedData = AesGcm::decrypt($ciphertext, $apiv3Key, $nonce, $associatedData);

        /** @var array<string, mixed> $resourceArray */
        $resourceArray = json_decode($decryptedData, true) ?? [];

        $payOrder->setStatus(PayOrderStatus::SUCCESS);

        $transactionId = $resourceArray['transaction_id'] ?? '';
        $payOrder->setTransactionId(is_string($transactionId) ? $transactionId : null);

        $successTime = $resourceArray['success_time'] ?? '';
        $payOrder->setSuccessTime(is_string($successTime) ? $successTime : null);
        $this->entityManager->persist($payOrder);
        $this->entityManager->flush();

        $event = new PayCallbackSuccessEvent();
        $event->setAccount($account);
        $event->setPayOrder($payOrder);
        $event->setDecryptData($resourceArray);
        $this->eventDispatcher->dispatch($event);

        return $event->getResponse() ?? $this->createEmptyResponse();
    }

    private function handleFailedCallback(Account $account, PayOrder $payOrder): Response
    {
        $payOrder->setStatus(PayOrderStatus::FAILED);
        $this->entityManager->persist($payOrder);
        $this->entityManager->flush();

        $event = new PayCallbackFailedEvent();
        $event->setAccount($account);
        $event->setPayOrder($payOrder);
        $this->eventDispatcher->dispatch($event);

        if (null !== $event->getResponse()) {
            return $event->getResponse();
        }

        return $this->json([
            'code' => 'FAIL',
            'message' => '签名验证失败',
        ]);
    }
}
