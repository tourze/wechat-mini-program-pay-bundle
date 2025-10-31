<?php

namespace WechatMiniProgramPayBundle\Controller;

use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\PaymentContracts\Enum\PaymentType;
use Tourze\PaymentContracts\Event\PaymentSuccessEvent;
use Tourze\XML\XML;
use WechatPayBundle\Entity\Merchant;
use WechatPayBundle\Entity\PayOrder;
use WechatPayBundle\Enum\PayOrderStatus;
use WechatPayBundle\Repository\MerchantRepository;
use WechatPayBundle\Repository\PayOrderRepository;
use Yiisoft\Json\Json;

#[WithMonologChannel(channel: 'wechat_mini_program_pay')]
final class UnifiedOrderController extends AbstractController
{
    /**
     * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_5_5.shtml
     */
    #[Route(path: '/wechat-payment/mini-program/pay-v2/{tradeNo}', name: 'wechat_app_unified_order_pay_callback_v2', methods: ['POST'])]
    public function __invoke(
        string $tradeNo,
        LoggerInterface $logger,
        EventDispatcherInterface $eventDispatcher,
        PayOrderRepository $payOrderRepository,
        MerchantRepository $merchantRepository,
        LockFactory $lockFactory,
        EntityManagerInterface $entityManager,
        Request $request,
    ): Response {
        $lock = $lockFactory->createLock("wechat-app-pay-success-{$tradeNo}");
        if (!$lock->acquire()) {
            $logger->error("获取锁失败:{$tradeNo}");

            return new Response(XML::build([
                'return_code' => 'FAIL',
                'return_msg' => '通知过于频繁',
            ]));
        }
        try {
            /** @var PayOrder|null $payOrder */
            $payOrder = $payOrderRepository->findOneBy(['tradeNo' => $tradeNo]);
            if (null === $payOrder) {
                return new Response(XML::build([
                    'return_code' => 'FAIL',
                    'return_msg' => '订单不存在',
                ]));
            }
            if (PayOrderStatus::SUCCESS === $payOrder->getStatus()) {
                return new Response(XML::build([
                    'return_code' => 'SUCCESS',
                    'return_msg' => '订单已处理',
                ]));
            }
            $body = $request->getContent();
            $logger->info('支付回调', [
                'data' => $body,
            ]);
            $attributes = XML::parse($body);
            $logger->info('格式化数据', [
                'xml' => $attributes,
            ]);

            // 将回调信息存起来
            $payOrder->setCallbackResponse(Json::encode($attributes));
            $payOrder->setCallbackTime(CarbonImmutable::now());
            if (isset($attributes['transaction_id'])) {
                $transactionId = $attributes['transaction_id'];
                assert(is_string($transactionId));
                $payOrder->setTransactionId($transactionId);
            }
            $entityManager->persist($payOrder);
            $entityManager->flush();
            /** @var Merchant|null $merchant */
            $merchant = $merchantRepository->findOneBy([
                'mchId' => $attributes['mch_id'],
            ]);
            if (null === $merchant) {
                return new Response(XML::build([
                    'return_code' => 'FAIL',
                    'return_msg' => '商户号错误',
                ]));
            }
            $sign = $attributes['sign'];
            unset($attributes['sign']);
            // 进行签名
            $pemKey = $merchant->getApiKey();
            if (null === $pemKey) {
                return new Response(XML::build([
                    'return_code' => 'FAIL',
                    'return_msg' => '商户私钥缺失',
                ]));
            }
            $currentSign = $this->generateSign($attributes, $pemKey);

            if ($sign !== $currentSign) {
                return new Response(XML::build([
                    'return_code' => 'FAIL',
                    'return_msg' => '签名验证失败',
                ]));
            }

            // 只要签名过了就算通知成功了，至于事件下面的逻辑，各自需要处理好
            $payOrder->setStatus(PayOrderStatus::SUCCESS);
            $entityManager->persist($payOrder);
            $entityManager->flush();
            $tradeType = $payOrder->getTradeType();
            $payTime = new \DateTimeImmutable(CarbonImmutable::now()->format('Y-m-d H:i:s'));
            $transactionIdForEvent = $attributes['transaction_id'] ?? '';
            assert(is_string($transactionIdForEvent));
            $paymentSuccessEvent = new PaymentSuccessEvent(
                paymentType: PaymentType::WECHAT_MINI_PROGRAM,
                orderNumber: $payOrder->getTradeNo() ?? '',
                orderId: 0,
                transactionId: $transactionIdForEvent,
                amount: (float) (($payOrder->getTotalFee() ?? 0) / 100),
                payTime: $payTime,
                rawData: $attributes
            );
            $eventDispatcher->dispatch($paymentSuccessEvent);
        } catch (\Throwable $exception) {
            $logger->error("处理微信app支付回调事件失败:{$tradeNo}", [
                'error' => $exception,
            ]);
        } finally {
            $lock->release();
        }

        return new Response(XML::build([
            'return_code' => 'SUCCESS',
            'return_msg' => 'ok',
        ]));
    }

    /**
     * 生成签名.
     *
     * @param array<string, mixed> $attributes
     */
    public function generateSign(array $attributes, string $key, string $encryptMethod = 'md5'): string
    {
        ksort($attributes);

        $attributes['key'] = $key;

        $queryString = urldecode(http_build_query($attributes));

        return match ($encryptMethod) {
            'md5' => strtoupper(md5($queryString)),
            'sha1' => strtoupper(sha1($queryString)),
            default => strtoupper(hash($encryptMethod, $queryString)),
        };
    }
}
