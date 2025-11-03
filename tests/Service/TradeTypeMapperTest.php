<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PaymentContracts\Enum\PaymentType;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WechatMiniProgramPayBundle\Service\TradeTypeMapper;

/**
 * @internal
 */
#[CoversClass(TradeTypeMapper::class)]
#[RunTestsInSeparateProcesses]
class TradeTypeMapperTest extends AbstractIntegrationTestCase
{
    private TradeTypeMapper $mapper;

    protected function onSetUp(): void
    {
        $this->mapper = self::getService(TradeTypeMapper::class);
    }

    public function testMapToTradeTypeForSupportedPaymentTypes(): void
    {
        $this->assertSame(TradeTypeMapper::TRADE_TYPE_JSAPI, $this->mapper->mapToTradeType(PaymentType::WECHAT_MINI_PROGRAM));
        $this->assertSame(TradeTypeMapper::TRADE_TYPE_JSAPI, $this->mapper->mapToTradeType(PaymentType::WECHAT_OFFICIAL_ACCOUNT));
        $this->assertSame(TradeTypeMapper::TRADE_TYPE_JSAPI, $this->mapper->mapToTradeType(PaymentType::WECHAT_JSAPI));
        $this->assertSame(TradeTypeMapper::TRADE_TYPE_APP, $this->mapper->mapToTradeType(PaymentType::WECHAT_APP));
        $this->assertSame(TradeTypeMapper::TRADE_TYPE_JSAPI, $this->mapper->mapToTradeType(PaymentType::LEGACY_WECHAT_PAY));
    }

    public function testMapToTradeTypeForUnsupportedPaymentType(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('不支持的支付类型: alipay');

        $this->mapper->mapToTradeType(PaymentType::LEGACY_ALIPAY);
    }

    public function testGetRequiredParametersForAllTradeTypes(): void
    {
        $this->assertSame(['openid'], $this->mapper->getRequiredParameters(TradeTypeMapper::TRADE_TYPE_JSAPI));
        $this->assertSame(['product_id'], $this->mapper->getRequiredParameters(TradeTypeMapper::TRADE_TYPE_NATIVE));
        $this->assertSame(['scene_info'], $this->mapper->getRequiredParameters(TradeTypeMapper::TRADE_TYPE_H5));
        $this->assertSame([], $this->mapper->getRequiredParameters(TradeTypeMapper::TRADE_TYPE_APP));
    }

    public function testGetRequiredParametersForUnsupportedTradeType(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('不支持的交易类型: UNKNOWN');

        $this->mapper->getRequiredParameters('UNKNOWN');
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string>        $expectedMissing
     */
    #[DataProvider('provideValidateParametersData')]
    public function testValidateParameters(PaymentType $paymentType, array $parameters, array $expectedMissing): void
    {
        $result = $this->mapper->validateParameters($paymentType, $parameters);
        $this->assertSame($expectedMissing, $result);
    }

    /**
     * @return array<string, mixed>
     */
    public static function provideValidateParametersData(): array
    {
        return [
            'WECHAT_MINI_PROGRAM with openid' => [
                PaymentType::WECHAT_MINI_PROGRAM,
                ['openid' => 'test_openid'],
                [],
            ],
            'WECHAT_MINI_PROGRAM without openid' => [
                PaymentType::WECHAT_MINI_PROGRAM,
                [],
                ['openid'],
            ],
            'WECHAT_MINI_PROGRAM with empty openid' => [
                PaymentType::WECHAT_MINI_PROGRAM,
                ['openid' => ''],
                ['openid'],
            ],
            'WECHAT_APP without required parameters' => [
                PaymentType::WECHAT_APP,
                [],
                [],
            ],
        ];
    }

    /**
     * @param array<string, string> $expected
     */
    #[DataProvider('provideH5SceneInfoData')]
    public function testGetH5SceneInfo(string $type, string $appName, ?string $bundleId, array $expected): void
    {
        $result = $this->mapper->getH5SceneInfo($type, $appName, $bundleId);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, mixed>
     */
    public static function provideH5SceneInfoData(): array
    {
        return [
            'IOS with bundle_id' => [
                'IOS',
                'TestApp',
                'com.test.app',
                [
                    'h5_info' => [
                        'type' => 'IOS',
                        'app_name' => 'TestApp',
                        'bundle_id' => 'com.test.app',
                    ],
                ],
            ],
            'Android with package_name' => [
                'Android',
                'TestApp',
                'com.test.app',
                [
                    'h5_info' => [
                        'type' => 'Android',
                        'app_name' => 'TestApp',
                        'package_name' => 'com.test.app',
                    ],
                ],
            ],
            'Wap without bundle_id' => [
                'Wap',
                'TestApp',
                null,
                [
                    'h5_info' => [
                        'type' => 'Wap',
                        'app_name' => 'TestApp',
                    ],
                ],
            ],
            'Other type with bundle_id ignored' => [
                'Other',
                'TestApp',
                'com.test.app',
                [
                    'h5_info' => [
                        'type' => 'Other',
                        'app_name' => 'TestApp',
                    ],
                ],
            ],
        ];
    }

    public function testGetWapSceneInfo(): void
    {
        $result = $this->mapper->getWapSceneInfo('https://test.com', 'TestSite');

        $expected = [
            'h5_info' => [
                'type' => 'Wap',
                'wap_url' => 'https://test.com',
                'wap_name' => 'TestSite',
            ],
        ];

        $this->assertSame($expected, $result);
    }

    #[DataProvider('provideIsPaymentTypeSupportedData')]
    public function testIsPaymentTypeSupported(PaymentType $paymentType, bool $expected): void
    {
        $result = $this->mapper->isPaymentTypeSupported($paymentType);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, mixed>
     */
    public static function provideIsPaymentTypeSupportedData(): array
    {
        return [
            'WECHAT_MINI_PROGRAM supported' => [PaymentType::WECHAT_MINI_PROGRAM, true],
            'WECHAT_OFFICIAL_ACCOUNT supported' => [PaymentType::WECHAT_OFFICIAL_ACCOUNT, true],
            'WECHAT_JSAPI supported' => [PaymentType::WECHAT_JSAPI, true],
            'WECHAT_APP supported' => [PaymentType::WECHAT_APP, true],
            'LEGACY_WECHAT_PAY supported' => [PaymentType::LEGACY_WECHAT_PAY, true],
            'LEGACY_ALIPAY not supported' => [PaymentType::LEGACY_ALIPAY, false],
            'BANK_CARD not supported' => [PaymentType::BANK_CARD, false],
        ];
    }

    public function testGetSupportedPaymentTypes(): void
    {
        $result = $this->mapper->getSupportedPaymentTypes();

        $expected = [
            PaymentType::WECHAT_MINI_PROGRAM,
            PaymentType::WECHAT_OFFICIAL_ACCOUNT,
            PaymentType::WECHAT_JSAPI,
            PaymentType::WECHAT_APP,
            PaymentType::LEGACY_WECHAT_PAY,
        ];

        $this->assertSame($expected, $result);
    }

    public function testGetSupportedTradeTypes(): void
    {
        $result = $this->mapper->getSupportedTradeTypes();

        $expected = [
            TradeTypeMapper::TRADE_TYPE_JSAPI,
            TradeTypeMapper::TRADE_TYPE_NATIVE,
            TradeTypeMapper::TRADE_TYPE_APP,
            TradeTypeMapper::TRADE_TYPE_H5,
        ];

        $this->assertSame($expected, $result);
    }

    #[DataProvider('provideGetTradeTypeLabelData')]
    public function testGetTradeTypeLabel(string $tradeType, string $expected): void
    {
        $result = $this->mapper->getTradeTypeLabel($tradeType);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, mixed>
     */
    public static function provideGetTradeTypeLabelData(): array
    {
        return [
            'JSAPI' => [TradeTypeMapper::TRADE_TYPE_JSAPI, 'JSAPI支付(小程序/公众号)'],
            'NATIVE' => [TradeTypeMapper::TRADE_TYPE_NATIVE, 'Native支付(扫码)'],
            'APP' => [TradeTypeMapper::TRADE_TYPE_APP, 'APP支付'],
            'H5' => [TradeTypeMapper::TRADE_TYPE_H5, 'H5支付(手机网站)'],
            'UNKNOWN' => ['UNKNOWN', '未知类型(UNKNOWN)'],
        ];
    }

    #[DataProvider('provideRecommendH5TypeData')]
    public function testRecommendH5Type(string $userAgent, string $expected): void
    {
        $result = $this->mapper->recommendH5Type($userAgent);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, mixed>
     */
    public static function provideRecommendH5TypeData(): array
    {
        return [
            'iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)', 'IOS'],
            'iPad' => ['Mozilla/5.0 (iPad; CPU OS 14_0 like Mac OS X)', 'IOS'],
            'Android phone' => ['Mozilla/5.0 (Linux; Android 10; SM-G975F)', 'Android'],
            'Desktop browser' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Wap'],
            'Empty user agent' => ['', 'Wap'],
            'Mixed case iPhone' => ['Mozilla/5.0 (IPHONE; CPU iPhone OS 14_0 like Mac OS X)', 'IOS'],
            'Mixed case Android' => ['Mozilla/5.0 (Linux; ANDROID 10; SM-G975F)', 'Android'],
        ];
    }

    #[DataProvider('provideIsValidTradeTypeData')]
    public function testIsValidTradeType(string $tradeType, bool $expected): void
    {
        $result = $this->mapper->isValidTradeType($tradeType);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, mixed>
     */
    public static function provideIsValidTradeTypeData(): array
    {
        return [
            'JSAPI valid' => [TradeTypeMapper::TRADE_TYPE_JSAPI, true],
            'NATIVE valid' => [TradeTypeMapper::TRADE_TYPE_NATIVE, true],
            'APP valid' => [TradeTypeMapper::TRADE_TYPE_APP, true],
            'H5 valid' => [TradeTypeMapper::TRADE_TYPE_H5, true],
            'UNKNOWN invalid' => ['UNKNOWN', false],
            'Empty string invalid' => ['', false],
        ];
    }

    public function testGetPaymentTypeInfoForSupportedType(): void
    {
        $result = $this->mapper->getPaymentTypeInfo(PaymentType::WECHAT_MINI_PROGRAM);

        $expected = [
            'payment_type' => 'wechat_mini_program',
            'payment_type_label' => '微信小程序支付',
            'trade_type' => TradeTypeMapper::TRADE_TYPE_JSAPI,
            'trade_type_label' => 'JSAPI支付(小程序/公众号)',
            'required_parameters' => ['openid'],
            'supported' => true,
        ];

        $this->assertSame($expected, $result);
    }

    public function testGetPaymentTypeInfoForUnsupportedType(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('不支持的支付类型: alipay');

        $this->mapper->getPaymentTypeInfo(PaymentType::LEGACY_ALIPAY);
    }

    public function testGetAllPaymentTypeInfo(): void
    {
        $result = $this->mapper->getAllPaymentTypeInfo();

        // getAllPaymentTypeInfo() 返回类型已明确为 array
        $this->assertCount(5, $result); // 5个支持的支付类型

        foreach ($result as $info) {
            $this->assertArrayHasKey('payment_type', $info);
            $this->assertArrayHasKey('payment_type_label', $info);
            $this->assertArrayHasKey('trade_type', $info);
            $this->assertArrayHasKey('trade_type_label', $info);
            $this->assertArrayHasKey('required_parameters', $info);
            $this->assertArrayHasKey('supported', $info);
            $this->assertTrue($info['supported']);
        }
    }

    #[DataProvider('provideRecommendPaymentTypeData')]
    public function testRecommendPaymentType(string $userAgent, PaymentType $expected): void
    {
        $result = $this->mapper->recommendPaymentType($userAgent);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, mixed>
     */
    public static function provideRecommendPaymentTypeData(): array
    {
        return [
            'WeChat browser' => ['Mozilla/5.0 (iPhone; MicroMessenger/8.0.0)', PaymentType::WECHAT_JSAPI],
            'WeChat Android' => ['Mozilla/5.0 (Linux; Android; MicroMessenger)', PaymentType::WECHAT_JSAPI],
            'Mobile browser' => ['Mozilla/5.0 (iPhone; Mobile Safari)', PaymentType::WECHAT_JSAPI],
            'Android mobile' => ['Mozilla/5.0 (Linux; Android 10; Mobile)', PaymentType::WECHAT_JSAPI],
            'Desktop browser' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64)', PaymentType::WECHAT_JSAPI],
            'Empty user agent' => ['', PaymentType::WECHAT_JSAPI],
        ];
    }

    public function testConstants(): void
    {
        $this->assertSame('JSAPI', TradeTypeMapper::TRADE_TYPE_JSAPI);
        $this->assertSame('NATIVE', TradeTypeMapper::TRADE_TYPE_NATIVE);
        $this->assertSame('APP', TradeTypeMapper::TRADE_TYPE_APP);
        $this->assertSame('H5', TradeTypeMapper::TRADE_TYPE_H5);
    }
}
