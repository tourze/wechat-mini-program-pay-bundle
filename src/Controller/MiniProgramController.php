<?php

namespace WechatMiniProgramPayBundle\Controller;

use Carbon\Carbon;
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
use WechatPayBundle\Entity\RefundOrder;
use WechatPayBundle\Enum\PayOrderStatus;
use WechatPayBundle\Repository\PayOrderRepository;
use WechatPayBundle\Repository\RefundOrderRepository;

#[Route(path: '/wechat-payment/mini-program')]
class MiniProgramController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_5_5.shtml
     */
    #[Route(path: '/pay/{appId}/{traderNo}', name: 'wechat_mini_program_pay_callback', methods: ['POST'])]
    public function payCallback(
        Account $account,
        PayOrder $payOrder,
        LoggerInterface $logger,
        EventDispatcherInterface $eventDispatcher,
        PayOrderRepository $payOrderRepository,
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

        $payOrder->setCallbackTime(Carbon::now());
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
        $apiv3Key = $payOrder->getMerchant()->getApiKey();
        // 在商户平台上设置的APIv3密钥
        // 根据通知的平台证书序列号，查询本地平台证书文件，
        // 假定为 `/path/to/wechatpay/inWechatpaySerial.pem`
        $platformPublicKeyInstance = Rsa::from($payOrder->getMerchant()->getMchId(), Rsa::KEY_TYPE_PUBLIC);
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
            $payOrder->setCallbackResponse($inBodyResource); // 存储起来吧
            // 把解密后的文本转换为PHP Array数组
            $inBodyResourceArray = (array) json_decode((string) $inBodyResource, true, 512, JSON_THROW_ON_ERROR);
            // print_r($inBodyResourceArray);// 打印解密后的结果
            $logger->info('微信支付回调解密结果', $inBodyResourceArray);

            // 成功
            $payOrder->setTransactionId($inBodyResourceArray['transaction_id']);
            $payOrder->setTradeType($inBodyResourceArray['trade_type']);
            $payOrder->setTradeState($inBodyResourceArray['trade_state']);
            $payOrder->setStatus(PayOrderStatus::SUCCESS);
            $this->entityManager->persist($payOrder);
            $this->entityManager->flush();

            $e = new PayCallbackSuccessEvent();
            $e->setAccount($account);
            $e->setPayOrder($payOrder);
            $e->setDecryptData($inBodyResourceArray);
            $eventDispatcher->dispatch($e);
            if ($e->getResponse()) {
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
        $eventDispatcher->dispatch($e);
        if ($e->getResponse()) {
            return $e->getResponse();
        }

        return $this->json([
            'code' => 'FAIL',
            'message' => '签名验证失败',
        ]);
    }

    /**
     * 合单支付回调
     *
     * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter5_1_4.shtml
     */
    #[Route(path: '/combine-pay/{appId}', name: 'wechat_mini_program_combine_pay_callback', methods: ['POST'])]
    public function combineCallback(Account $account, PayOrder $order, LoggerInterface $logger, EventDispatcherInterface $eventDispatcher, PayOrderRepository $payOrderRepository): never
    {
        // TODO 解密，获得结果，分发事件
        throw $this->createAccessDeniedException('未实现');
    }

    /**
     * 退款结果通知
     *
     * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_11.shtml
     * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_9.shtml
     */
    #[Route(path: '/refund/{appId}/{refundNo}', name: 'wechat_mini_program_refund_callback', methods: ['POST'])]
    public function refundCallback(Account $account, RefundOrder $refundOrder, LoggerInterface $logger, RefundOrderRepository $refundOrderRepository, Request $request): Response
    {
        // TODO 加锁，最好实现注解来做这个锁
        if ($refundOrder->getPayOrder() && $refundOrder->getPayOrder()->getAppId() !== $account->getAppId()) {
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
            $logger->info('退款结果通知回调结果', $inBodyResourceArray);

            // 成功
            $refundOrder->setStatus($inBodyResourceArray['refund_status']);
            $refundOrder->setRefundId($inBodyResourceArray['refund_id']);
            $refundOrder->setUserReceiveAccount($inBodyResourceArray['user_received_account']);
            $refundOrder->setSuccessTime(Carbon::parse($inBodyResourceArray['success_time']));
            $this->entityManager->persist($refundOrder);
            $this->entityManager->flush();

            $r = new Response();
            $r->setContent('');

            return $r;
        }

        return $this->json([
            'code' => 'FAIL',
            'message' => '签名验证失败',
        ]);
    }
}
