<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WechatMiniProgramPayBundle\Service\RefundService;

/**
 * RefundService 测试.
 *
 * @internal
 */
#[CoversClass(RefundService::class)]
#[RunTestsInSeparateProcesses]
final class RefundServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // This test class doesn't need specific setup
    }

    #[Test]
    public function testGenerateRefundNo(): void
    {
        $refundNo = RefundService::generateRefundNo();

        $this->assertStringStartsWith('REFUND', $refundNo);
        $this->assertEquals(26, strlen($refundNo));
        $this->assertMatchesRegularExpression('/^REFUND\d{20}$/', $refundNo);
    }

    #[Test]
    public function testGenerateRefundNoIsUnique(): void
    {
        $refundNo1 = RefundService::generateRefundNo();
        usleep(1000);
        $refundNo2 = RefundService::generateRefundNo();

        $this->assertNotEquals($refundNo1, $refundNo2);
    }

    #[Test]
    public function testRefundServiceCanBeInstantiated(): void
    {
        // 测试服务是否可以被正确注入
        $service = self::getService(RefundService::class);
        $this->assertInstanceOf(RefundService::class, $service);
    }

    #[Test]
    public function testRefundValidationLogicWithMissingParameters(): void
    {
        // 使用反射测试私有验证方法或直接测试公共方法的参数验证
        $service = self::getService(RefundService::class);

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('必填参数 outRefundNo 不能为空');

        $service->refund([
            'totalFee' => 100,
            'refundFee' => 50,
            'outTradeNo' => 'ORDER123',
        ]);
    }
}
