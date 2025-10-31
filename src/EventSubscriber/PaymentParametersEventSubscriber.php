<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\PaymentContracts\Enum\PaymentType;
use Tourze\PaymentContracts\Event\PaymentParametersRequestedEvent;
use WechatMiniProgramPayBundle\Service\WechatPayService;

/**
 * 微信支付参数事件订阅器.
 *
 * 监听支付参数请求事件，为微信支付提供真实的支付参数
 */
class PaymentParametersEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly WechatPayService $wechatPayService,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PaymentParametersRequestedEvent::class => 'onPaymentParametersRequested',
        ];
    }

    #[AsEventListener(event: PaymentParametersRequestedEvent::class)]
    public function onPaymentParametersRequested(PaymentParametersRequestedEvent $event): void
    {
        $paymentType = PaymentType::fromValue($event->getPaymentType());

        // 检查是否为微信支付类型
        if (null === $paymentType || !$paymentType->isWechatPayment()) {
            return;
        }

        if ($event->hasPaymentParams()) {
            return;
        }

        try {
            $extraParams = [
                'orderId' => $event->getOrderId(),
                'orderState' => $event->getOrderState(),
                'requestTime' => $event->getRequestTime(),
                'appId' => $event->getAppId(),
                'mchId' => $event->getMchId(),
                'description' => $event->getDescription(),
                'attach' => $event->getAttach(),
                'openId' => $event->getOpenId(),
                'notifyUrl' => $event->getNotifyUrl(),
                'paymentType' => $paymentType,
            ];

            // 过滤掉 null 值
            $extraParams = array_filter($extraParams, fn ($value) => null !== $value);

            $paymentParams = $this->wechatPayService->getWechatPayParams(
                orderNumber: $event->getOrderNumber(),
                amount: $event->getAmount(),
                extraParams: $extraParams
            );

            $event->setPaymentParams($paymentParams);
        } catch (\Exception $e) {
            // 记录错误但不中断流程，让默认处理器继续
        }
    }
}
