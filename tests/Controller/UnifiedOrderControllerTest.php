<?php

namespace WechatMiniProgramPayBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;
use WechatMiniProgramPayBundle\Controller\UnifiedOrderController;

/**
 * @internal
 */
#[CoversClass(UnifiedOrderController::class)]
#[RunTestsInSeparateProcesses]
final class UnifiedOrderControllerTest extends AbstractWebTestCase
{
    public function testControllerCanBeInstantiated(): void
    {
        $client = self::createClientWithDatabase();
        $container = $client->getContainer();

        $controller = $container->get(UnifiedOrderController::class);

        $this->assertInstanceOf(UnifiedOrderController::class, $controller);
    }

    public function testControllerHasInvokeMethod(): void
    {
        $reflection = new \ReflectionClass(UnifiedOrderController::class);

        $this->assertTrue($reflection->hasMethod('__invoke'));

        $method = $reflection->getMethod('__invoke');
        $this->assertTrue($method->isPublic());
    }

    public function testInvokeMethodHasCorrectParameters(): void
    {
        $reflection = new \ReflectionMethod(UnifiedOrderController::class, '__invoke');
        $parameters = $reflection->getParameters();

        $this->assertCount(8, $parameters);
        $this->assertEquals('tradeNo', $parameters[0]->getName());
        $this->assertEquals('logger', $parameters[1]->getName());
        $this->assertEquals('eventDispatcher', $parameters[2]->getName());
        $this->assertEquals('payOrderRepository', $parameters[3]->getName());
        $this->assertEquals('merchantRepository', $parameters[4]->getName());
        $this->assertEquals('lockFactory', $parameters[5]->getName());
        $this->assertEquals('entityManager', $parameters[6]->getName());
        $this->assertEquals('request', $parameters[7]->getName());
    }

    public function testInvokeMethodReturnsResponse(): void
    {
        $reflection = new \ReflectionMethod(UnifiedOrderController::class, '__invoke');
        $returnType = $reflection->getReturnType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertEquals('Symfony\Component\HttpFoundation\Response', $returnType->getName());
    }

    public function testControllerHasGenerateSignMethod(): void
    {
        $reflection = new \ReflectionClass(UnifiedOrderController::class);

        $this->assertTrue($reflection->hasMethod('generateSign'));

        $method = $reflection->getMethod('generateSign');
        $this->assertTrue($method->isPublic());
    }

    public function testGenerateSignMethodHasCorrectParameters(): void
    {
        $reflection = new \ReflectionMethod(UnifiedOrderController::class, 'generateSign');
        $parameters = $reflection->getParameters();

        $this->assertCount(3, $parameters);
        $this->assertEquals('attributes', $parameters[0]->getName());
        $this->assertEquals('key', $parameters[1]->getName());
        $this->assertEquals('encryptMethod', $parameters[2]->getName());
    }

    public function testGenerateSignMethodReturnsString(): void
    {
        $reflection = new \ReflectionMethod(UnifiedOrderController::class, 'generateSign');
        $returnType = $reflection->getReturnType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertEquals('string', $returnType->getName());
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        // 这个测试验证路由配置是否正确，不需要数据库
        $reflection = new \ReflectionClass(UnifiedOrderController::class);
        $routeMethod = $reflection->getMethod('__invoke');
        $attributes = $routeMethod->getAttributes(Route::class);

        $this->assertCount(1, $attributes);
        $routeAttribute = $attributes[0]->newInstance();

        // 验证只允许 POST 方法
        $this->assertEquals(['POST'], $routeAttribute->getMethods());
        $this->assertNotContains($method, $routeAttribute->getMethods(), "Method {$method} should not be allowed");
    }
}
