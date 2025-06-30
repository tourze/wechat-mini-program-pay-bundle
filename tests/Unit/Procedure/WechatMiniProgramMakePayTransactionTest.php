<?php

namespace WechatMiniProgramPayBundle\Tests\Unit\Procedure;

use PHPUnit\Framework\TestCase;
use WechatMiniProgramPayBundle\Procedure\WechatMiniProgramMakePayTransaction;

class WechatMiniProgramMakePayTransactionTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(WechatMiniProgramMakePayTransaction::class));
    }
}