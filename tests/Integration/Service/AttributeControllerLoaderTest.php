<?php

namespace WechatMiniProgramPayBundle\Tests\Integration\Service;

use PHPUnit\Framework\TestCase;
use WechatMiniProgramPayBundle\Service\AttributeControllerLoader;

class AttributeControllerLoaderTest extends TestCase
{
    public function testAttributeControllerLoader(): void
    {
        $controllerLoader = $this->createMock(\Symfony\Component\Routing\Loader\AttributeClassLoader::class);
        $controllerLoader->method('load')->willReturn(new \Symfony\Component\Routing\RouteCollection());
        
        $loader = new AttributeControllerLoader($controllerLoader);
        
        $this->assertInstanceOf(AttributeControllerLoader::class, $loader);
        
        $result = $loader->autoload();
        $this->assertInstanceOf(\Symfony\Component\Routing\RouteCollection::class, $result);
    }
}