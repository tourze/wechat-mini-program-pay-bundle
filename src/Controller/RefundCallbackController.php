<?php

namespace WechatMiniProgramPayBundle\Controller;

use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WechatMiniProgramBundle\Entity\Account;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;
use WechatPayBundle\Entity\RefundOrder;
use WechatPayBundle\Repository\RefundOrderRepository;

class RefundCallbackController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly RefundOrderRepository $refundOrderRepository,
    ) {
    }

    #[Route(path: '/wechat-payment/mini-program/refund/{appId}/{refundNo}', name: 'wechat_mini_program_refund_callback', methods: ['POST'])]
    public function __invoke(Account $account, RefundOrder $refundOrder, Request $request): Response
    {
        // TODO 加锁，最好实现注解来做这个锁
        if (null !== $refundOrder->getPayOrder() && $refundOrder->getPayOrder()->getAppId() !== $account->getAppId()) {
            throw $this->createAccessDeniedException('appId不匹配');
        }

        // TODO 解密，获得结果，分发事件
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
        // 请根据实际情况获取，例如: file_get_contents('php://input');
        $apiv3Key = $refundOrder->getPayOrder()->getMerchant()->getApiKey();
        // 在商户平台上设置的APIv3密钥
        // 根据通知的平台证书序列号，查询本地平台证书文件，
        // 假定为 `/path/to/wechatpay/inWechatpaySerial.pem`
        $platformPublicKeyInstance = Rsa::from($refundOrder->getPayOrder()->getMerchant()->getMchId(), Rsa::KEY_TYPE_PUBLIC);
        // 检查通知时间偏移量，允许5分钟之内的偏移
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int) $inWechatpayTimestamp);
        $verifiedStatus = Rsa::verify(
            // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        );
        if ($timeOffsetStatus && (bool) $verifiedStatus) {
            // 转换通知的JSON文本消息为PHP Array数组
            $inBodyArray = (array) json_decode((string) $inBody, true, 512, JSON_THROW_ON_ERROR);
            // 使用PHP7的数据解构语法，从Array中解构并赋值变量
            [
                'resource' => [
                    'ciphertext' => $ciphertext,
                    'nonce' => $nonce,
                    'associated_data' => $aad,
                ],
            ] = $inBodyArray;
            // 加密文本消息解密
            $inBodyResource = AesGcm::decrypt($ciphertext, $apiv3Key, $nonce, $aad);
            $refundOrder->setCallbackResponse($inBodyResource); // 存储起来吧
            // 把解密后的文本转换为PHP Array数组
            $inBodyResourceArray = (array) json_decode((string) $inBodyResource, true, 512, JSON_THROW_ON_ERROR);
            $this->logger->info('退款结果通知回调结果', $inBodyResourceArray);

            // 成功
            $refundOrder->setStatus($inBodyResourceArray['refund_status']);
            $refundOrder->setRefundId($inBodyResourceArray['refund_id']);
            $refundOrder->setUserReceiveAccount($inBodyResourceArray['user_received_account']);
            $refundOrder->setSuccessTime(CarbonImmutable::parse($inBodyResourceArray['success_time']));
            $this->entityManager->persist($refundOrder);
            $this->entityManager->flush();

            $r = new Response();
            $r->setContent('');

            return $r;
        }

        // 失败
        return $this->json([
            'code' => 'FAIL',
            'message' => '签名验证失败',
        ]);
    }
}