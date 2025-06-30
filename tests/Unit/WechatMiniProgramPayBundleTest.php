<?php

namespace WechatMiniProgramPayBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WechatMiniProgramPayBundle\WechatMiniProgramPayBundle;

class WechatMiniProgramPayBundleTest extends TestCase
{
    public function testBundle(): void
    {
        $bundle = new WechatMiniProgramPayBundle();
        
        $this->assertInstanceOf(WechatMiniProgramPayBundle::class, $bundle);
    }
}