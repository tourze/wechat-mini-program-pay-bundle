<?php

namespace WechatMiniProgramPayBundle\Controller;

use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WechatMiniProgramBundle\Entity\Account;
use WechatMiniProgramPayBundle\Event\PayCallbackFailedEvent;
use WechatMiniProgramPayBundle\Event\PayCallbackSuccessEvent;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;
use WechatPayBundle\Entity\PayOrder;
use WechatPayBundle\Enum\PayOrderStatus;
use WechatPayBundle\Repository\PayOrderRepository;

class PayCallbackController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly PayOrderRepository $payOrderRepository,
    ) {
    }

    /**
     * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_5_5.shtml
     */
    #[Route(path: '/wechat-payment/mini-program/pay/{appId}/{traderNo}', name: 'wechat_mini_program_pay_callback', methods: ['POST'])]
    public function __invoke(
        Account $account,
        PayOrder $payOrder,
        Request $request,
    ): Response {
        // TODO 加锁，最好实现注解来做这个锁
        if ($payOrder->getAppId() !== $account->getAppId()) {
            throw $this->createAccessDeniedException('appId不匹配');
        }

        if (PayOrderStatus::SUCCESS === $payOrder->getStatus()) {
            // 如果已经成功的话，不重新进行下面的处理
            $r = new Response();
            $r->setContent('');

            return $r;
        }

        // 这是微信V3版本的支付API
        $inWechatpaySignature = $request->headers->get('Wechatpay-Signature');
        // 请根据实际情况获取
        $inWechatpayTimestamp = $request->headers->get('Wechatpay-Timestamp');
        // 请根据实际情况获取
        $inWechatpaySerial = $request->headers->get('Wechatpay-Serial');
        // 请根据实际情况获取
        $inWechatpayNonce = $request->headers->get('Wechatpay-Nonce');
        // 请根据实际情况获取
        $inBody = $request->getContent();
        $this->logger->debug('小程序微信支付成功回调', [
            'wechatpay-signature' => $inWechatpaySignature,
            'wechatpay-timestamp' => $inWechatpayTimestamp,
            'wechatpay-serial' => $inWechatpaySerial,
            'wechatpay-nonce' => $inWechatpayNonce,
            'body' => $inBody,
        ]);

        $payOrder->setCallbackTime(CarbonImmutable::now());
        $payOrder->setResponseData($inBody);
        $payOrder->setResponseSerial($inWechatpaySerial);

        $apiv3Key = $payOrder->getMerchant()->getKey();
        $merchantPrivateKeyFilePath = 'file://' . $this->getParameter('kernel.project_dir') . '/var/cert/wechat_pay/' . $payOrder->getMerchant()->getMchId() . '_key.pem';
        $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);
        $merchantCertificateFilePath = 'file://' . $this->getParameter('kernel.project_dir') . '/var/cert/wechat_pay/' . $payOrder->getMerchant()->getMchId() . '_cert.pem';
        $merchantCertificateInstance = Rsa::from($merchantCertificateFilePath);
        $merchantCertificateSerial = strtoupper(ltrim($merchantCertificateInstance->getSerialNumber(), '0'));
        $platformCertificateFilePath = 'file://' . $this->getParameter('kernel.project_dir') . '/var/cert/wechat_pay/' . $payOrder->getMerchant()->getMchId() . '_platform_cert.pem';
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
        $platformCertificateSerial = strtoupper(ltrim($platformPublicKeyInstance->getSerialNumber(), '0'));

        // 根据通知的平台证书序列号，查询本地平台证书文件，
        // 假定为 `/path/to/wechatpay/inWechatpaySerial.pem`
        $inWechatpaySignature = $inWechatpaySignature;
        $inWechatpayTimestamp = $inWechatpayTimestamp;
        $inWechatpayNonce = $inWechatpayNonce;
        // 构造验签名串
        $inBodyArray = json_decode($inBody, true);
        if ('TRANSACTION.SUCCESS' === $inBodyArray['event_type'] && Rsa::verify(
            // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        )) {
            $inBodyResourceArray = AesGcm::decrypt($inBodyArray['resource']['ciphertext'], $apiv3Key, $inBodyArray['resource']['nonce'], $inBodyArray['resource']['associated_data']);
            $inBodyResourceArray = json_decode($inBodyResourceArray, true);

            // 成功
            $payOrder->setStatus(PayOrderStatus::SUCCESS);
            $payOrder->setTransactionId($inBodyResourceArray['transaction_id'] ?? '');
            $payOrder->setSuccessTime($inBodyResourceArray['success_time'] ?? '');
            $this->entityManager->persist($payOrder);
            $this->entityManager->flush();

            $e = new PayCallbackSuccessEvent();
            $e->setAccount($account);
            $e->setPayOrder($payOrder);
            $e->setDecryptData($inBodyResourceArray);
            $this->eventDispatcher->dispatch($e);
            if (null !== $e->getResponse()) {
                return $e->getResponse();
            }

            $r = new Response();
            $r->setContent('');

            return $r;
        }

        // 失败
        $payOrder->setStatus(PayOrderStatus::FAILED);
        $this->entityManager->persist($payOrder);
        $this->entityManager->flush();
        $e = new PayCallbackFailedEvent();
        $e->setAccount($account);
        $e->setPayOrder($payOrder);
        $this->eventDispatcher->dispatch($e);
        if (null !== $e->getResponse()) {
            return $e->getResponse();
        }

        return $this->json([
            'code' => 'FAIL',
            'message' => '签名验证失败',
        ]);
    }
}