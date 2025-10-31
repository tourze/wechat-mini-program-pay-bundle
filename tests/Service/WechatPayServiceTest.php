<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WechatMiniProgramPayBundle\Service\WechatPayService;

/**
 * @internal
 */
#[CoversClass(WechatPayService::class)]
#[RunTestsInSeparateProcesses]
class WechatPayServiceTest extends AbstractIntegrationTestCase
{
    private WechatPayService $service;

    protected function onSetUp(): void
    {
        $this->service = self::getService(WechatPayService::class);
    }

    public function testGetSupportedPaymentType(): void
    {
        $result = $this->service->getSupportedPaymentType();
        $this->assertSame('wechat_pay', $result);
    }

    public function testGetWechatPayParamsWithMissingConfiguration(): void
    {
        $this->expectException(PaymentConfigurationException::class);

        // 测试缺少必要配置时的异常处理
        $this->service->getWechatPayParams('ORDER123', 100.0, []);
    }

    /**
     * @param array<string, mixed> $params
     */
    #[DataProvider('provideValidatePaymentParamsData')]
    public function testValidatePaymentParams(array $params, bool $expected): void
    {
        $result = $this->service->validatePaymentParams($params);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, mixed>
     */
    public static function provideValidatePaymentParamsData(): array
    {
        return [
            'Valid params' => [
                ['orderNumber' => 'ORDER123', 'amount' => 100.0],
                true,
            ],
            'Missing orderNumber' => [
                ['amount' => 100.0],
                false,
            ],
            'Missing amount' => [
                ['orderNumber' => 'ORDER123'],
                false,
            ],
            'Invalid orderNumber type' => [
                ['orderNumber' => 123, 'amount' => 100.0],
                false,
            ],
            'Invalid amount type' => [
                ['orderNumber' => 'ORDER123', 'amount' => 'invalid'],
                false,
            ],
            'Zero amount' => [
                ['orderNumber' => 'ORDER123', 'amount' => 0],
                false,
            ],
            'Negative amount' => [
                ['orderNumber' => 'ORDER123', 'amount' => -10.0],
                false,
            ],
            'Empty array' => [
                [],
                false,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    #[DataProvider('provideGetPaymentParamsData')]
    public function testGetPaymentParams(array $params, bool $shouldSucceed): void
    {
        if (!$shouldSucceed) {
            $this->expectException(PaymentConfigurationException::class);
        }

        $result = $this->service->getPaymentParams($params);

        if ($shouldSucceed) {
            $this->assertIsArray($result);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function provideGetPaymentParamsData(): array
    {
        return [
            'Missing orderNumber' => [
                [
                    'amount' => 100.0,
                    'extraParams' => [],
                ],
                false,
            ],
            'Missing amount' => [
                [
                    'orderNumber' => 'ORDER123',
                    'extraParams' => [],
                ],
                false,
            ],
        ];
    }
}
