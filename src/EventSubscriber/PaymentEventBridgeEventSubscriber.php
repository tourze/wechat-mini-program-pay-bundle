<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\EventSubscriber;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\PaymentContracts\Enum\PaymentType;
use Tourze\PaymentContracts\Event\PaymentFailedEvent;
use Tourze\PaymentContracts\Event\PaymentSuccessEvent;
use Tourze\PaymentContracts\ValueObject\AttachData;
use WechatMiniProgramPayBundle\Event\PayCallbackFailedEvent;
use WechatMiniProgramPayBundle\Event\PayCallbackSuccessEvent;
use WechatPayBundle\Entity\PayOrder;

/**
 * 支付事件桥接器.
 *
 * 监听微信支付特定事件，转换并发布通用支付事件，实现解耦
 */
#[WithMonologChannel(channel: 'wechat_mini_program_pay')]
class PaymentEventBridgeEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PayCallbackSuccessEvent::class => 'onPayCallbackSuccess',
            PayCallbackFailedEvent::class => 'onPayCallbackFailed',
        ];
    }

    /**
     * 处理支付成功回调.
     */
    public function onPayCallbackSuccess(PayCallbackSuccessEvent $event): void
    {
        $payOrder = $event->getPayOrder();
        $decryptData = $event->getDecryptData();

        try {
            // 解析订单信息
            $attachData = $this->parseAttachData($payOrder->getAttach());
            if (null === $attachData) {
                $this->logger->warning('无法解析支付订单的 attach 数据', [
                    'pay_order_id' => $payOrder->getId(),
                    'attach' => $payOrder->getAttach(),
                ]);

                return;
            }

            // 提取支付信息
            $payTime = $this->extractPayTime($decryptData);
            $amount = $this->extractAmount($decryptData, $payOrder);
            $transactionId = $this->extractTransactionId($decryptData);

            // 发布通用支付成功事件
            $paymentSuccessEvent = new PaymentSuccessEvent(
                paymentType: $this->getPaymentType(),
                orderNumber: '' !== $attachData->getOrderSn() ? $attachData->getOrderSn() : ($payOrder->getTradeNo() ?? ''),
                orderId: $attachData->getOrderId(),
                transactionId: $transactionId,
                amount: $amount,
                payTime: $payTime,
                rawData: $decryptData
            );

            $this->eventDispatcher->dispatch($paymentSuccessEvent);

            $this->logger->info('发布通用支付成功事件', [
                'order_id' => $attachData->getOrderId(),
                'order_number' => '' !== $attachData->getOrderSn() ? $attachData->getOrderSn() : ($payOrder->getTradeNo() ?? ''),
                'transaction_id' => $transactionId,
                'amount' => $amount,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('处理支付成功回调时发生错误', [
                'pay_order_id' => $payOrder->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 提取支付时间
     *
     * @param array<string, mixed> $decryptData
     */
    private function extractPayTime(array $decryptData): \DateTimeInterface
    {
        $successTime = $decryptData['success_time'] ?? '';

        return $this->parsePayTime(is_string($successTime) ? $successTime : '');
    }

    /**
     * 提取支付金额
     *
     * @param array<string, mixed> $decryptData
     */
    private function extractAmount(array $decryptData, PayOrder $payOrder): float
    {
        $amountData = is_array($decryptData['amount'] ?? null) ? $decryptData['amount'] : null;
        $totalAmount = null !== $amountData && isset($amountData['total']) ? $amountData['total'] : $payOrder->getTotalFee();

        // 确保totalAmount是int|string|null类型
        if (null !== $totalAmount && !is_int($totalAmount) && !is_string($totalAmount)) {
            $totalAmount = $payOrder->getTotalFee();
        }

        return $this->parseAmount($totalAmount);
    }

    /**
     * 提取交易ID
     *
     * @param array<string, mixed> $decryptData
     */
    private function extractTransactionId(array $decryptData): string
    {
        $transactionId = $decryptData['transaction_id'] ?? '';

        return is_string($transactionId) ? $transactionId : '';
    }

    /**
     * 处理支付失败回调.
     */
    public function onPayCallbackFailed(PayCallbackFailedEvent $event): void
    {
        $payOrder = $event->getPayOrder();

        try {
            // 解析订单信息
            $attachData = $this->parseAttachData($payOrder->getAttach());
            if (null === $attachData) {
                $this->logger->warning('无法解析支付订单的 attach 数据', [
                    'pay_order_id' => $payOrder->getId(),
                    'attach' => $payOrder->getAttach(),
                ]);

                return;
            }

            // 发布通用支付失败事件
            $paymentFailedEvent = new PaymentFailedEvent(
                paymentType: $this->getPaymentType(),
                orderNumber: '' !== $attachData->getOrderSn() ? $attachData->getOrderSn() : ($payOrder->getTradeNo() ?? ''),
                orderId: $attachData->getOrderId(),
                failReason: '微信支付回调验证失败',
                rawData: []
            );

            $this->eventDispatcher->dispatch($paymentFailedEvent);

            $this->logger->info('发布通用支付失败事件', [
                'order_id' => $attachData->getOrderId(),
                'order_number' => '' !== $attachData->getOrderSn() ? $attachData->getOrderSn() : ($payOrder->getTradeNo() ?? ''),
                'fail_reason' => '微信支付回调验证失败',
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('处理支付失败回调时发生错误', [
                'pay_order_id' => $payOrder->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 解析附加数据.
     */
    private function parseAttachData(?string $attach): ?AttachData
    {
        if (null === $attach || '' === $attach) {
            return null;
        }

        return AttachData::parse($attach);
    }

    /**
     * 解析支付时间.
     */
    private function parsePayTime(string $payTimeString): \DateTimeInterface
    {
        if ('' === $payTimeString) {
            return new \DateTimeImmutable();
        }

        try {
            // 微信支付时间格式：2024-01-01T12:00:00+08:00
            return new \DateTimeImmutable($payTimeString);
        } catch (\Exception) {
            return new \DateTimeImmutable();
        }
    }

    /**
     * 解析支付金额（分转元）.
     */
    private function parseAmount(int|string|null $totalFee): float
    {
        if (null === $totalFee) {
            return 0.0;
        }

        $fee = is_string($totalFee) ? (int) $totalFee : $totalFee;

        return $fee / 100.0;
    }

    /**
     * 获取当前支付类型.
     */
    private function getPaymentType(): PaymentType
    {
        return PaymentType::WECHAT_MINI_PROGRAM;
    }
}
