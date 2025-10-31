<?php

namespace WechatMiniProgramPayBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;
use WechatMiniProgramBundle\Entity\Account;
use WechatMiniProgramPayBundle\Controller\CombinePayCallbackController;
use WechatMiniProgramPayBundle\Exception\PaymentNotImplementedException;

/**
 * @internal
 */
#[CoversClass(CombinePayCallbackController::class)]
#[RunTestsInSeparateProcesses]
final class CombinePayCallbackControllerTest extends AbstractWebTestCase
{
    private CombinePayCallbackController $controller;

    protected function onSetUp(): void
    {
        $this->controller = new CombinePayCallbackController();
    }

    public function testInvokeShouldThrowPaymentNotImplementedException(): void
    {
        $this->expectException(PaymentNotImplementedException::class);
        $this->expectExceptionMessage('合单支付回调待实现');

        $account = new Account();

        $this->controller->__invoke($account);
    }

    public function testControllerMethodExists(): void
    {
        $reflection = new \ReflectionMethod($this->controller, '__invoke');
        $this->assertTrue($reflection->hasReturnType());

        $returnType = $reflection->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertEquals('never', $returnType->getName());
    }

    public function testControllerHasCorrectRoute(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('__invoke');

        $attributes = $method->getAttributes(Route::class);
        $this->assertCount(1, $attributes);

        $routeAttribute = $attributes[0]->newInstance();
        $this->assertEquals('/wechat-payment/mini-program/combine-pay/{appId}', $routeAttribute->getPath());
        $this->assertEquals(['POST'], $routeAttribute->getMethods());
        $this->assertEquals('wechat_mini_program_combine_pay_callback', $routeAttribute->getName());
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        // 这个测试验证路由配置是否正确，不需要数据库
        $reflection = new \ReflectionClass($this->controller);
        $routeMethod = $reflection->getMethod('__invoke');
        $attributes = $routeMethod->getAttributes(Route::class);

        $this->assertCount(1, $attributes);
        $routeAttribute = $attributes[0]->newInstance();

        // 验证只允许 POST 方法
        $this->assertEquals(['POST'], $routeAttribute->getMethods());
        $this->assertNotContains($method, $routeAttribute->getMethods(), "Method {$method} should not be allowed");
    }
}
