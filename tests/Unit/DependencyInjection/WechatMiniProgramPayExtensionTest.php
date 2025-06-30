<?php

namespace WechatMiniProgramPayBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use WechatMiniProgramPayBundle\DependencyInjection\WechatMiniProgramPayExtension;

class WechatMiniProgramPayExtensionTest extends TestCase
{
    public function testLoad(): void
    {
        $container = new ContainerBuilder();
        $extension = new WechatMiniProgramPayExtension();
        
        $extension->load([], $container);
        
        $this->assertTrue($container->hasDefinition('WechatMiniProgramPayBundle\Service\AttributeControllerLoader'));
    }
}