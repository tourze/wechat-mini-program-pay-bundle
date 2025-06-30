<?php

namespace WechatMiniProgramPayBundle\Tests\Unit\Procedure;

use PHPUnit\Framework\TestCase;
use WechatMiniProgramPayBundle\Procedure\WechatMiniProgramMakeRefundTransaction;

class WechatMiniProgramMakeRefundTransactionTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(WechatMiniProgramMakeRefundTransaction::class));
    }
}