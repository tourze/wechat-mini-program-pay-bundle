<?php

namespace WechatMiniProgramPayBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use WechatMiniProgramPayBundle\Exception\PaymentNotImplementedException;

/**
 * @internal
 */
#[CoversClass(PaymentNotImplementedException::class)]
final class PaymentNotImplementedExceptionTest extends AbstractExceptionTestCase
{
    public function testException(): void
    {
        $exception = new PaymentNotImplementedException('Test message');

        $this->assertInstanceOf(PaymentNotImplementedException::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }
}
