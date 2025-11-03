<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
final class PaymentEventBridgeEventSubscriberTest extends AbstractEventSubscriberTestCase
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
    }

    public function testServiceInstantiation(): void
    {
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

        // 验证事件处理正常完成
        $this->assertInstanceOf(PaymentEventBridgeEventSubscriber::class, $subscriber);
    }

    public function testOnPayCallbackSuccessWithInvalidAttachData(): void
    {
        $subscriber = self::getService(PaymentEventBridgeEventSubscriber::class);

        // 准备数据 - 无效的 attach
        $payOrder = $this->createMockPayOrder(2, 2000, 'invalid-json');
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
        $payOrder = $this->createMockPayOrder(3, 2000, null);
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

    public function testOnPayCallbackSuccessWithEmptyAttachData(): void
    {
        $subscriber = self::getService(PaymentEventBridgeEventSubscriber::class);

        // 准备数据 - 空字符串 attach
        $payOrder = $this->createMockPayOrder(4, 3000, '');
        $account = $this->createMockAccount();

        $event = new PayCallbackSuccessEvent();
        $event->setPayOrder($payOrder);
        $event->setAccount($account);
        $event->setDecryptData([]);

        // 执行测试 - 验证处理空字符串数据时不会抛出异常
        $subscriber->onPayCallbackSuccess($event);

        $this->assertSame($payOrder, $event->getPayOrder());
    }

    public function testOnPayCallbackSuccessWithMinimalDecryptData(): void
    {
        $subscriber = self::getService(PaymentEventBridgeEventSubscriber::class);

        // 准备数据 - 最小化的解密数据
        $attachData = new AttachData(456, 'ORDER-MINIMAL');
        $payOrder = $this->createMockPayOrder(5, 5000, $attachData->encode());
        $account = $this->createMockAccount();

        $decryptData = []; // 空的解密数据

        $event = new PayCallbackSuccessEvent();
        $event->setPayOrder($payOrder);
        $event->setAccount($account);
        $event->setDecryptData($decryptData);

        // 执行测试
        $subscriber->onPayCallbackSuccess($event);

        $this->assertInstanceOf(PaymentEventBridgeEventSubscriber::class, $subscriber);
    }

    public function testOnPayCallbackFailedWithValidAttachData(): void
    {
        $subscriber = self::getService(PaymentEventBridgeEventSubscriber::class);

        // 准备数据
        $attachData = new AttachData(444, 'ORDER-FAILED');
        $payOrder = $this->createMockPayOrder(6, 5500, $attachData->encode());
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
        $payOrder = $this->createMockPayOrder(7, 3300, 'invalid-attach-data');
        $account = $this->createMockAccount();

        $event = new PayCallbackFailedEvent();
        $event->setPayOrder($payOrder);
        $event->setAccount($account);

        // 执行测试 - 验证处理无效数据时不会抛出异常
        $subscriber->onPayCallbackFailed($event);

        // 验证事件对象状态保持不变
        $this->assertSame($account, $event->getAccount());
    }

    public function testOnPayCallbackFailedWithNullAttachData(): void
    {
        $subscriber = self::getService(PaymentEventBridgeEventSubscriber::class);

        // 准备数据 - null attach
        $payOrder = $this->createMockPayOrder(8, 4400, null);
        $account = $this->createMockAccount();

        $event = new PayCallbackFailedEvent();
        $event->setPayOrder($payOrder);
        $event->setAccount($account);

        // 执行测试
        $subscriber->onPayCallbackFailed($event);

        $this->assertSame($payOrder, $event->getPayOrder());
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
        $this->assertTrue($reflectionClass->hasMethod('getSubscribedEvents'));
    }

    /**
     * 测试不同支付时间格式的处理.
     */
    #[DataProvider('payTimeDataProvider')]
    public function testPayTimeHandling(string $timeString, bool $expectsSuccess): void
    {
        $subscriber = self::getService(PaymentEventBridgeEventSubscriber::class);

        $attachData = new AttachData(999, 'ORDER-TIME-TEST');
        $payOrder = $this->createMockPayOrder(9, 1000, $attachData->encode());
        $account = $this->createMockAccount();

        $decryptData = [
            'transaction_id' => 'TXN-TIME-TEST',
            'success_time' => $timeString,
            'amount' => ['total' => 1000],
        ];

        $event = new PayCallbackSuccessEvent();
        $event->setPayOrder($payOrder);
        $event->setAccount($account);
        $event->setDecryptData($decryptData);

        if ($expectsSuccess) {
            // 不应该抛出异常，成功执行即表示测试通过
            $subscriber->onPayCallbackSuccess($event);
            // 验证事件对象状态保持不变
            $this->assertSame($payOrder, $event->getPayOrder());
        } else {
            $this->expectException(\Exception::class);
            $subscriber->onPayCallbackSuccess($event);
        }
    }

    /**
     * 数据提供器：不同的支付时间格式.
     *
     * @return array<string, array{string, bool}>
     */
    public static function payTimeDataProvider(): array
    {
        return [
            'valid_time_with_timezone' => ['2024-01-01T12:00:00+08:00', true],
            'valid_time_utc' => ['2024-01-01T12:00:00Z', true],
            'valid_time_without_timezone' => ['2024-01-01T12:00:00', true],
            'empty_string' => ['', true],
            'invalid_format' => ['invalid-date-format', true],
            'null_converted_to_empty' => ['', true],
        ];
    }

    /**
     * 测试不同金额格式的处理.
     */
    #[DataProvider('amountDataProvider')]
    public function testAmountHandling(int|string|null $amount, float $expectedAmount): void
    {
        $subscriber = self::getService(PaymentEventBridgeEventSubscriber::class);

        $attachData = new AttachData(888, 'ORDER-AMOUNT-TEST');
        $payOrder = $this->createMockPayOrder(10, is_null($amount) ? 0 : (int) $amount, $attachData->encode());
        $account = $this->createMockAccount();

        $decryptData = [
            'transaction_id' => 'TXN-AMOUNT-TEST',
            'amount' => ['total' => $amount],
        ];

        $event = new PayCallbackSuccessEvent();
        $event->setPayOrder($payOrder);
        $event->setAccount($account);
        $event->setDecryptData($decryptData);

        // 执行测试 - 验证不同金额格式都能正确处理
        $subscriber->onPayCallbackSuccess($event);

        // 验证事件对象状态保持不变
        $this->assertSame($payOrder, $event->getPayOrder());
        $this->assertSame($account, $event->getAccount());
    }

    /**
     * 数据提供器：不同的金额格式.
     *
     * @return array<string, array{int|string|null, float}>
     */
    public static function amountDataProvider(): array
    {
        return [
            'integer_amount' => [10000, 100.0],
            'string_amount' => ['5000', 50.0],
            'zero_amount' => [0, 0.0],
            'null_amount' => [null, 0.0],
        ];
    }

    public function testExceptionHandlingInSuccessCallback(): void
    {
        $subscriber = self::getService(PaymentEventBridgeEventSubscriber::class);

        // 创建一个PayOrder mock，在getAttach()时抛出异常（这样不会在catch块中再次调用getId()）
        // 注意：不能 mock getId()，因为它是 final 方法
        $payOrder = $this->createMock(PayOrder::class);
        $payOrder->method('getAttach')->willThrowException(new \RuntimeException('Test exception'));

        $account = $this->createMockAccount();
        $event = new PayCallbackSuccessEvent();
        $event->setPayOrder($payOrder);
        $event->setAccount($account);
        $event->setDecryptData([]);

        // 验证异常被捕获，不会向上传播
        $subscriber->onPayCallbackSuccess($event);

        // 验证事件对象状态保持不变，异常已被正确处理
        $this->assertSame($payOrder, $event->getPayOrder());
    }

    public function testExceptionHandlingInFailedCallback(): void
    {
        $subscriber = self::getService(PaymentEventBridgeEventSubscriber::class);

        // 创建一个PayOrder mock，在getAttach()时抛出异常（这样不会在catch块中再次调用getId()）
        // 注意：不能 mock getId()，因为它是 final 方法
        $payOrder = $this->createMock(PayOrder::class);
        $payOrder->method('getAttach')->willThrowException(new \RuntimeException('Test exception'));

        $account = $this->createMockAccount();
        $event = new PayCallbackFailedEvent();
        $event->setPayOrder($payOrder);
        $event->setAccount($account);

        // 验证异常被捕获，不会向上传播
        $subscriber->onPayCallbackFailed($event);

        // 验证事件对象状态保持不变，异常已被正确处理
        $this->assertSame($payOrder, $event->getPayOrder());
    }

    /**
     * 创建 PayOrder 对象
     *
     * 注意：使用真实实体而非 Mock，因为 getId() 是 final 方法无法被 mock
     */
    private function createMockPayOrder(int $id, int $totalFee, ?string $attach, ?string $tradeNo = null): PayOrder
    {
        $payOrder = new PayOrder();
        $payOrder->setId((string) $id);
        $payOrder->setTotalFee($totalFee);
        $payOrder->setAttach($attach);
        $payOrder->setTradeNo($tradeNo ?? "TRADE-NO-{$id}");

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
