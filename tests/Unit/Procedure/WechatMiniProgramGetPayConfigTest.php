<?php

namespace WechatMiniProgramPayBundle\Tests\Unit\Procedure;

use PHPUnit\Framework\TestCase;
use WechatMiniProgramPayBundle\Procedure\WechatMiniProgramGetPayConfig;

class WechatMiniProgramGetPayConfigTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(WechatMiniProgramGetPayConfig::class));
    }
}