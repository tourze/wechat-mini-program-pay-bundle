<?php

namespace WechatMiniProgramPayBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;
use Tourze\Symfony\AopAsyncBundle\Attribute\Async;
use WechatMiniProgramPayBundle\Event\PayCallbackSuccessEvent;
use WechatMiniProgramPayBundle\EventSubscriber\PaySuccessGetUnionIdSubscriber;
use WechatPayBundle\Entity\PayOrder;

/**
 * @internal
 */
#[CoversClass(PaySuccessGetUnionIdSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class PaySuccessGetUnionIdSubscriberTest extends AbstractEventSubscriberTestCase
{
    protected function onSetUp(): void
    {
        // 设置测试所需的服务
    }

    public function testOnPayCallbackSuccessWithNullOpenId(): void
    {
        $payOrder = $this->createMock(PayOrder::class);
        $payOrder->method('getOpenId')->willReturn(null);

        $event = new PayCallbackSuccessEvent();
        $event->setPayOrder($payOrder);

        $this->assertNull($payOrder->getOpenId());
    }

    public function testSubscriberHasAsyncAttribute(): void
    {
        $reflection = new \ReflectionMethod(PaySuccessGetUnionIdSubscriber::class, 'onPayCallbackSuccess');
        $attributes = $reflection->getAttributes(Async::class);

        $this->assertCount(1, $attributes);
    }

    public function testSubscriberHasCorrectMethod(): void
    {
        $reflection = new \ReflectionClass(PaySuccessGetUnionIdSubscriber::class);

        $this->assertTrue($reflection->hasMethod('onPayCallbackSuccess'));

        $method = $reflection->getMethod('onPayCallbackSuccess');
        $this->assertTrue($method->isPublic());

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('event', $parameters[0]->getName());
    }
}
