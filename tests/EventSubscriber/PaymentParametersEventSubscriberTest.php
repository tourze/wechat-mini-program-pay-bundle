<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PaymentContracts\Event\PaymentParametersRequestedEvent;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;
use WechatMiniProgramPayBundle\EventSubscriber\PaymentParametersEventSubscriber;

/**
 * 微信支付参数事件订阅器测试.
 *
 * @internal
 */
#[CoversClass(PaymentParametersEventSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class PaymentParametersEventSubscriberTest extends AbstractEventSubscriberTestCase
{
    protected function onSetUp(): void
    {
        // 测试初始化
    }

    public function testGetSubscribedEvents(): void
    {
        $expectedEvents = [
            PaymentParametersRequestedEvent::class => 'onPaymentParametersRequested',
        ];

        $actualEvents = PaymentParametersEventSubscriber::getSubscribedEvents();

        $this->assertEquals($expectedEvents, $actualEvents);

        // 验证订阅器可以正确实例化
        $subscriber = self::getService(PaymentParametersEventSubscriber::class);
        $this->assertInstanceOf(PaymentParametersEventSubscriber::class, $subscriber);
    }

    public function testOnPaymentParametersRequestedWithWechatMiniProgram(): void
    {
        $subscriber = self::getService(PaymentParametersEventSubscriber::class);

        // 准备测试数据
        $event = new PaymentParametersRequestedEvent(
            paymentType: 'wechat_mini_program',
            amount: 100.0,
            orderNumber: 'ORDER-001',
            orderId: 123,
            orderState: 'pending',
            requestTime: '2024-01-01T12:00:00',
            appId: 'wx123456',
            mchId: 'mch001',
            description: '测试商品',
            attach: '{"order_id": 123}',
            openId: 'openid123',
            notifyUrl: 'https://example.com/notify'
        );

        // 执行测试 - 验证处理微信小程序支付请求不会抛出异常
        $subscriber->onPaymentParametersRequested($event);

        // 验证订阅器实例正常工作
        $this->assertInstanceOf(PaymentParametersEventSubscriber::class, $subscriber);
    }

    public function testOnPaymentParametersRequestedWithNonWechatPayment(): void
    {
        $subscriber = self::getService(PaymentParametersEventSubscriber::class);

        // 准备测试数据 - 非微信支付类型
        $event = new PaymentParametersRequestedEvent(
            paymentType: 'alipay_h5',
            amount: 75.0,
            orderNumber: 'ORDER-003'
        );

        // 执行测试 - 验证处理非微信支付类型不会抛出异常
        $subscriber->onPaymentParametersRequested($event);

        // 事件应该没有设置支付参数
        $this->assertFalse($event->hasPaymentParams());
        $this->assertNull($event->getPaymentParams());
    }

    public function testOnPaymentParametersRequestedWithInvalidPaymentType(): void
    {
        $subscriber = self::getService(PaymentParametersEventSubscriber::class);

        // 准备测试数据 - 无效的支付类型
        $event = new PaymentParametersRequestedEvent(
            paymentType: 'invalid_payment_type',
            amount: 25.0,
            orderNumber: 'ORDER-004'
        );

        // 执行测试 - 验证处理无效支付类型不会抛出异常
        $subscriber->onPaymentParametersRequested($event);

        $this->assertFalse($event->hasPaymentParams());
        $this->assertNull($event->getPaymentParams());
    }

    public function testOnPaymentParametersRequestedWithAlreadySetParams(): void
    {
        $subscriber = self::getService(PaymentParametersEventSubscriber::class);

        // 准备测试数据
        $event = new PaymentParametersRequestedEvent(
            paymentType: 'wechat_mini_program',
            amount: 200.0,
            orderNumber: 'ORDER-005'
        );

        // 预先设置支付参数
        $existingParams = ['existing' => 'params'];
        $event->setPaymentParams($existingParams);

        // 执行测试
        $subscriber->onPaymentParametersRequested($event);

        // 参数应该保持不变
        $this->assertTrue($event->hasPaymentParams());
        $this->assertEquals($existingParams, $event->getPaymentParams());
    }

    public function testOnPaymentParametersRequestedWithEmptyPaymentTypeString(): void
    {
        $subscriber = self::getService(PaymentParametersEventSubscriber::class);

        // 准备测试数据 - 空的支付类型字符串
        $event = new PaymentParametersRequestedEvent(
            paymentType: '',
            amount: 100.0,
            orderNumber: 'ORDER-011'
        );

        // 执行测试
        $subscriber->onPaymentParametersRequested($event);

        $this->assertFalse($event->hasPaymentParams());
        $this->assertNull($event->getPaymentParams());
    }

    public function testSubscriberServiceConfiguration(): void
    {
        // 验证订阅器服务配置正确
        $subscriber = self::getService(PaymentParametersEventSubscriber::class);
        $this->assertInstanceOf(PaymentParametersEventSubscriber::class, $subscriber);

        // 验证订阅器有正确的方法（通过反射验证）
        $reflectionClass = new \ReflectionClass($subscriber);
        $this->assertTrue($reflectionClass->hasMethod('onPaymentParametersRequested'));
    }
}
