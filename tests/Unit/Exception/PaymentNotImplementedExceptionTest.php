<?php

namespace WechatMiniProgramPayBundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use WechatMiniProgramPayBundle\Exception\PaymentNotImplementedException;

class PaymentNotImplementedExceptionTest extends TestCase
{
    public function testException(): void
    {
        $exception = new PaymentNotImplementedException('Test message');
        
        $this->assertInstanceOf(PaymentNotImplementedException::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }
}