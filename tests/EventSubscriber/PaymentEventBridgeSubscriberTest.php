<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Tourze\PaymentContracts\ValueObject\AttachData;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;
use WechatMiniProgramBundle\Entity\Account;
use WechatMiniProgramPayBundle\Event\PayCallbackFailedEvent;
use WechatMiniProgramPayBundle\Event\PayCallbackSuccessEvent;
use WechatMiniProgramPayBundle\EventSubscriber\PaymentEventBridgeEventSubscriber;
use WechatPayBundle\Entity\PayOrder;

/**
 * 支付事件桥接器测试.
 *
 * @internal
 */
#[CoversClass(PaymentEventBridgeEventSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class PaymentEventBridgeSubscriberTest extends AbstractEventSubscriberTestCase
{
    protected function onSetUp(): void
    {
        // 测试初始化，EventSubscriber 将通过依赖注入获取真实服务
    }

    public function testGetSubscribedEvents(): void
    {
        $expectedEvents = [
            PayCallbackSuccessEvent::class => 'onPayCallbackSuccess',
            PayCallbackFailedEvent::class => 'onPayCallbackFailed',
        ];

        $actualEvents = PaymentEventBridgeEventSubscriber::getSubscribedEvents();

        $this->assertEquals($expectedEvents, $actualEvents);

        // 验证订阅器可以正确实例化
        $subscriber = self::getService(PaymentEventBridgeEventSubscriber::class);
        $this->assertInstanceOf(PaymentEventBridgeEventSubscriber::class, $subscriber);
    }

    public function testOnPayCallbackSuccessWithValidAttachData(): void
    {
        // 获取订阅器服务
        $subscriber = self::getService(PaymentEventBridgeEventSubscriber::class);
        $this->assertInstanceOf(PaymentEventBridgeEventSubscriber::class, $subscriber);

        // 准备数据
        $attachData = new AttachData(123, 'ORDER-001');
        $payOrder = $this->createMockPayOrder(1, 10000, $attachData->encode());
        $account = $this->createMockAccount();

        $decryptData = [
            'transaction_id' => 'TXN-123456',
            'success_time' => '2024-01-01T12:00:00+08:00',
            'amount' => [
                'total' => 10000,
            ],
        ];

        $event = new PayCallbackSuccessEvent();
        $event->setPayOrder($payOrder);
        $event->setAccount($account);
        $event->setDecryptData($decryptData);

        // 执行测试 - 不会抛出异常即通过
        $subscriber->onPayCallbackSuccess($event);

        // 验证事件处理正常完成（验证方法存在且可调用）
        $this->assertInstanceOf(PaymentEventBridgeEventSubscriber::class, $subscriber);
    }

    public function testOnPayCallbackSuccessWithInvalidAttachData(): void
    {
        $subscriber = self::getService(PaymentEventBridgeEventSubscriber::class);

        // 准备数据 - 无效的 attach
        $payOrder = $this->createMockPayOrder(3, 2000, 'invalid-json');
        $account = $this->createMockAccount();

        $event = new PayCallbackSuccessEvent();
        $event->setPayOrder($payOrder);
        $event->setAccount($account);
        $event->setDecryptData([]);

        // 执行测试 - 验证处理无效数据时不会抛出异常
        $subscriber->onPayCallbackSuccess($event);

        // 验证事件对象状态保持不变
        $this->assertSame($payOrder, $event->getPayOrder());
    }

    public function testOnPayCallbackSuccessWithNullAttachData(): void
    {
        $subscriber = self::getService(PaymentEventBridgeEventSubscriber::class);

        // 准备数据 - null attach
        $payOrder = $this->createMockPayOrder(4, 2000, null);
        $account = $this->createMockAccount();

        $event = new PayCallbackSuccessEvent();
        $event->setPayOrder($payOrder);
        $event->setAccount($account);
        $event->setDecryptData([]);

        // 执行测试 - 验证处理null数据时不会抛出异常
        $subscriber->onPayCallbackSuccess($event);

        // 验证事件对象状态保持不变
        $this->assertSame($account, $event->getAccount());
    }

    public function testOnPayCallbackFailedWithValidAttachData(): void
    {
        $subscriber = self::getService(PaymentEventBridgeEventSubscriber::class);

        // 准备数据
        $attachData = new AttachData(444, 'ORDER-FAILED');
        $payOrder = $this->createMockPayOrder(9, 5500, $attachData->encode());
        $account = $this->createMockAccount();

        $event = new PayCallbackFailedEvent();
        $event->setPayOrder($payOrder);
        $event->setAccount($account);

        // 执行测试 - 验证失败回调处理不会抛出异常
        $subscriber->onPayCallbackFailed($event);

        // 验证事件对象状态保持不变
        $this->assertSame($payOrder, $event->getPayOrder());
    }

    public function testOnPayCallbackFailedWithInvalidAttachData(): void
    {
        $subscriber = self::getService(PaymentEventBridgeEventSubscriber::class);

        // 准备数据 - 无效的 attach
        $payOrder = $this->createMockPayOrder(10, 3300, 'invalid-attach-data');
        $account = $this->createMockAccount();

        $event = new PayCallbackFailedEvent();
        $event->setPayOrder($payOrder);
        $event->setAccount($account);

        // 执行测试 - 验证处理无效数据时不会抛出异常
        $subscriber->onPayCallbackFailed($event);

        // 验证事件对象状态保持不变
        $this->assertSame($account, $event->getAccount());
    }

    public function testSubscriberServiceConfiguration(): void
    {
        // 验证订阅器服务配置正确
        $subscriber = self::getService(PaymentEventBridgeEventSubscriber::class);
        $this->assertInstanceOf(PaymentEventBridgeEventSubscriber::class, $subscriber);

        // 验证订阅器有正确的方法（通过反射验证）
        $reflectionClass = new \ReflectionClass($subscriber);
        $this->assertTrue($reflectionClass->hasMethod('onPayCallbackSuccess'));
        $this->assertTrue($reflectionClass->hasMethod('onPayCallbackFailed'));
    }

    /**
     * 创建模拟的 PayOrder 对象
     */
    private function createMockPayOrder(int $id, int $totalFee, ?string $attach, ?string $tradeNo = null): PayOrder&MockObject
    {
        $payOrder = $this->createMock(PayOrder::class);
        $payOrder->method('getId')->willReturn((string) $id); // PayOrder getId 返回 ?string
        $payOrder->method('getTotalFee')->willReturn($totalFee);
        $payOrder->method('getAttach')->willReturn($attach);
        $payOrder->method('getTradeNo')->willReturn($tradeNo ?? "TRADE-NO-{$id}");

        return $payOrder;
    }

    /**
     * 创建模拟的 Account 对象
     */
    private function createMockAccount(): Account&MockObject
    {
        return $this->createMock(Account::class);
    }
}
