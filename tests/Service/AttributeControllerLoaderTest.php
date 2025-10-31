<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\RouteCollection;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use WechatMiniProgramPayBundle\Service\AttributeControllerLoader;

/**
 * @internal
 */
#[CoversClass(AttributeControllerLoader::class)]
#[RunTestsInSeparateProcesses]
final class AttributeControllerLoaderTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 测试初始化
    }

    public function testServiceCanBeInstantiated(): void
    {
        // 验证服务可以从容器中获取并正确实例化
        $loader = self::getService(AttributeControllerLoader::class);
        $this->assertInstanceOf(AttributeControllerLoader::class, $loader);
    }

    public function testAutoloadReturnsRouteCollection(): void
    {
        // 获取服务实例
        $loader = self::getService(AttributeControllerLoader::class);

        // 调用 autoload 方法并验证返回类型
        $result = $loader->autoload();
        $this->assertInstanceOf(RouteCollection::class, $result);
    }

    public function testLoadMethodExists(): void
    {
        // 测试 load 方法
        $loader = self::getService(AttributeControllerLoader::class);

        // 调用 load 方法并验证返回类型
        $result = $loader->load('test-resource');
        $this->assertInstanceOf(RouteCollection::class, $result);

        // 验证方法可见性
        $reflection = new \ReflectionMethod(AttributeControllerLoader::class, 'load');
        $this->assertTrue($reflection->isPublic());
    }

    public function testSupportsMethodExists(): void
    {
        // 测试 supports 方法
        $loader = self::getService(AttributeControllerLoader::class);

        // 调用 supports 方法并验证返回类型
        $result = $loader->supports('test-resource');
        $this->assertIsBool($result);

        // 验证方法可见性
        $reflection = new \ReflectionMethod(AttributeControllerLoader::class, 'supports');
        $this->assertTrue($reflection->isPublic());
    }

    public function testServiceMethodExists(): void
    {
        // 验证服务有正确的方法
        $loader = self::getService(AttributeControllerLoader::class);
        $this->assertTrue(method_exists($loader, 'autoload'));

        // 验证方法可见性
        $reflection = new \ReflectionMethod(AttributeControllerLoader::class, 'autoload');
        $this->assertTrue($reflection->isPublic());
    }
}
