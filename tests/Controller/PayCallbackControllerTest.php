<?php

namespace WechatMiniProgramPayBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;
use WechatMiniProgramPayBundle\Controller\PayCallbackController;

/**
 * @internal
 */
#[CoversClass(PayCallbackController::class)]
#[RunTestsInSeparateProcesses]
final class PayCallbackControllerTest extends AbstractWebTestCase
{
    public function testControllerCanBeInstantiated(): void
    {
        $client = self::createClientWithDatabase();
        $container = $client->getContainer();

        $controller = $container->get(PayCallbackController::class);

        $this->assertInstanceOf(PayCallbackController::class, $controller);
    }

    public function testControllerHasInvokeMethod(): void
    {
        $reflection = new \ReflectionClass(PayCallbackController::class);

        $this->assertTrue($reflection->hasMethod('__invoke'));

        $method = $reflection->getMethod('__invoke');
        $this->assertTrue($method->isPublic());
    }

    public function testInvokeMethodHasCorrectParameters(): void
    {
        $reflection = new \ReflectionMethod(PayCallbackController::class, '__invoke');
        $parameters = $reflection->getParameters();

        $this->assertCount(3, $parameters);
        $this->assertEquals('account', $parameters[0]->getName());
        $this->assertEquals('payOrder', $parameters[1]->getName());
        $this->assertEquals('request', $parameters[2]->getName());
    }

    public function testInvokeMethodReturnsResponse(): void
    {
        $reflection = new \ReflectionMethod(PayCallbackController::class, '__invoke');
        $returnType = $reflection->getReturnType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertEquals('Symfony\Component\HttpFoundation\Response', $returnType->getName());
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        // 这个测试验证路由配置是否正确，不需要数据库
        $reflection = new \ReflectionClass(PayCallbackController::class);
        $routeMethod = $reflection->getMethod('__invoke');
        $attributes = $routeMethod->getAttributes(Route::class);

        $this->assertCount(1, $attributes);
        $routeAttribute = $attributes[0]->newInstance();

        // 验证只允许 POST 方法
        $this->assertEquals(['POST'], $routeAttribute->getMethods());
        $this->assertNotContains($method, $routeAttribute->getMethods(), "Method {$method} should not be allowed");
    }
}
