<?php

namespace WechatMiniProgramPayBundle\Tests\Unit\Procedure;

use PHPUnit\Framework\TestCase;
use WechatMiniProgramPayBundle\Procedure\WechatMiniProgramMakeCombinePayTransaction;

class WechatMiniProgramMakeCombinePayTransactionTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(WechatMiniProgramMakeCombinePayTransaction::class));
    }
}