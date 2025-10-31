<?php

namespace WechatMiniProgramPayBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;

/**
 * @internal
 */
#[CoversClass(PaymentConfigurationException::class)]
final class PaymentConfigurationExceptionTest extends AbstractExceptionTestCase
{
    public function testException(): void
    {
        $exception = new PaymentConfigurationException('test message');

        $this->assertSame('test message', $exception->getMessage());
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
