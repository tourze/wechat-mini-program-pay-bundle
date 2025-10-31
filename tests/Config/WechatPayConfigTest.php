<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Tests\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WechatMiniProgramPayBundle\Config\WechatPayConfig;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;

/**
 * @internal
 */
#[CoversClass(WechatPayConfig::class)]
final class WechatPayConfigTest extends TestCase
{
    private WechatPayConfig $config;

    protected function setUp(): void
    {
        $this->config = new WechatPayConfig();

        // 清理环境变量，避免测试相互影响
        $this->clearEnvironmentVariables();
    }

    private function clearEnvironmentVariables(): void
    {
        $envVars = [
            'WECHAT_PAY_APP_ID',
            'WECHAT_PAY_MCH_ID',
            'WECHAT_PAY_KEY',
            'WECHAT_PAY_SIGN_TYPE',
            'WECHAT_PAY_FEE_TYPE',
            'WECHAT_PAY_CERT_PATH',
            'WECHAT_PAY_KEY_PATH',
            'WECHAT_PAY_CA_CERT_PATH',
            'WECHAT_PAY_BASE_URL',
            'WECHAT_PAY_TIMEOUT',
            'WECHAT_PAY_CONNECT_TIMEOUT',
            'WECHAT_PAY_RETRY_TIMES',
            'WECHAT_PAY_DEBUG',
            'WECHAT_PAY_LOG_PATH',
        ];

        foreach ($envVars as $var) {
            unset($_ENV[$var]);
        }
    }

    #[Test]
    public function fromEnvironmentWithoutEnvironmentVariablesCreatesConfigWithDefaults(): void
    {
        $config = WechatPayConfig::fromEnvironment();

        $this->assertSame('', $config->getAppId());
        $this->assertSame('', $config->getMchId());
        $this->assertSame('', $config->getKey());
        $this->assertSame('MD5', $config->getSignType());
        $this->assertSame('CNY', $config->getFeeType());
        $this->assertNull($config->getCertPath());
        $this->assertNull($config->getKeyPath());
        $this->assertNull($config->getCaCertPath());
        $this->assertSame('https://api.mch.weixin.qq.com', $config->getBaseUrl());
        $this->assertSame(30, $config->getTimeout());
        $this->assertSame(10, $config->getConnectTimeout());
        $this->assertSame(3, $config->getRetryTimes());
        $this->assertFalse($config->isDebug());
        $this->assertNull($config->getLogPath());
    }

    #[Test]
    public function fromEnvironmentWithEnvironmentVariablesCreatesConfigFromEnv(): void
    {
        $_ENV['WECHAT_PAY_APP_ID'] = 'test_app_id';
        $_ENV['WECHAT_PAY_MCH_ID'] = 'test_mch_id';
        $_ENV['WECHAT_PAY_KEY'] = 'test_key';
        $_ENV['WECHAT_PAY_SIGN_TYPE'] = 'HMAC-SHA256';
        $_ENV['WECHAT_PAY_FEE_TYPE'] = 'CNY';
        $_ENV['WECHAT_PAY_CERT_PATH'] = '/path/to/cert.pem';
        $_ENV['WECHAT_PAY_KEY_PATH'] = '/path/to/key.pem';
        $_ENV['WECHAT_PAY_CA_CERT_PATH'] = '/path/to/ca.pem';
        $_ENV['WECHAT_PAY_BASE_URL'] = 'https://custom.api.com';
        $_ENV['WECHAT_PAY_TIMEOUT'] = '60';
        $_ENV['WECHAT_PAY_CONNECT_TIMEOUT'] = '15';
        $_ENV['WECHAT_PAY_RETRY_TIMES'] = '5';
        $_ENV['WECHAT_PAY_DEBUG'] = 'true';
        $_ENV['WECHAT_PAY_LOG_PATH'] = '/path/to/logs';

        $config = WechatPayConfig::fromEnvironment();

        $this->assertSame('test_app_id', $config->getAppId());
        $this->assertSame('test_mch_id', $config->getMchId());
        $this->assertSame('test_key', $config->getKey());
        $this->assertSame('HMAC-SHA256', $config->getSignType());
        $this->assertSame('CNY', $config->getFeeType());
        $this->assertSame('/path/to/cert.pem', $config->getCertPath());
        $this->assertSame('/path/to/key.pem', $config->getKeyPath());
        $this->assertSame('/path/to/ca.pem', $config->getCaCertPath());
        $this->assertSame('https://custom.api.com', $config->getBaseUrl());
        $this->assertSame(60, $config->getTimeout());
        $this->assertSame(15, $config->getConnectTimeout());
        $this->assertSame(5, $config->getRetryTimes());
        $this->assertTrue($config->isDebug());
        $this->assertSame('/path/to/logs', $config->getLogPath());
    }

    #[Test]
    public function fromArrayWithValidDataCreatesConfigFromArray(): void
    {
        $data = [
            'app_id' => 'test_app_id',
            'mch_id' => 'test_mch_id',
            'key' => 'test_key',
            'sign_type' => 'HMAC-SHA256',
            'fee_type' => 'CNY',
            'cert_path' => '/path/to/cert.pem',
            'key_path' => '/path/to/key.pem',
            'ca_cert_path' => '/path/to/ca.pem',
            'base_url' => 'https://custom.api.com',
            'timeout' => 60,
            'connect_timeout' => 15,
            'retry_times' => 5,
            'debug' => true,
            'log_path' => '/path/to/logs',
        ];

        $config = WechatPayConfig::fromArray($data);

        $this->assertSame('test_app_id', $config->getAppId());
        $this->assertSame('test_mch_id', $config->getMchId());
        $this->assertSame('test_key', $config->getKey());
        $this->assertSame('HMAC-SHA256', $config->getSignType());
        $this->assertSame('CNY', $config->getFeeType());
        $this->assertSame('/path/to/cert.pem', $config->getCertPath());
        $this->assertSame('/path/to/key.pem', $config->getKeyPath());
        $this->assertSame('/path/to/ca.pem', $config->getCaCertPath());
        $this->assertSame('https://custom.api.com', $config->getBaseUrl());
        $this->assertSame(60, $config->getTimeout());
        $this->assertSame(15, $config->getConnectTimeout());
        $this->assertSame(5, $config->getRetryTimes());
        $this->assertTrue($config->isDebug());
        $this->assertSame('/path/to/logs', $config->getLogPath());
    }

    #[Test]
    public function fromArrayWithEmptyArrayCreatesConfigWithDefaults(): void
    {
        $config = WechatPayConfig::fromArray([]);

        $this->assertSame('', $config->getAppId());
        $this->assertSame('', $config->getMchId());
        $this->assertSame('', $config->getKey());
        $this->assertSame('MD5', $config->getSignType());
        $this->assertSame('CNY', $config->getFeeType());
        $this->assertSame('https://api.mch.weixin.qq.com', $config->getBaseUrl());
        $this->assertSame(30, $config->getTimeout());
        $this->assertSame(10, $config->getConnectTimeout());
        $this->assertSame(3, $config->getRetryTimes());
        $this->assertFalse($config->isDebug());
    }

    #[Test]
    public function testValidateWithValidConfigDoesNotThrowException(): void
    {
        $this->config->setAppId('test_app_id');
        $this->config->setMchId('test_mch_id');
        $this->config->setKey('test_key');
        $this->config->setSignType('MD5');
        $this->config->setFeeType('CNY');
        $this->config->setTimeout(30);
        $this->config->setConnectTimeout(10);
        $this->config->setRetryTimes(3);

        $this->config->validate();

        // 验证配置的有效性：如果没有抛出异常，所有必填字段都应该设置正确
        $this->assertSame('test_app_id', $this->config->getAppId());
        $this->assertSame('test_mch_id', $this->config->getMchId());
        $this->assertSame('test_key', $this->config->getKey());
    }

    #[Test]
    #[DataProvider('invalidRequiredFieldsProvider')]
    public function validateWithMissingRequiredFieldsThrowsException(string $field, string $expectedMessage): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->config->setAppId('appId' === $field ? '' : 'test_app_id');
        $this->config->setMchId('mchId' === $field ? '' : 'test_mch_id');
        $this->config->setKey('key' === $field ? '' : 'test_key');

        $this->config->validate();
    }

    /**
     * @return iterable<string, array<int, string>>
     */
    public static function invalidRequiredFieldsProvider(): iterable
    {
        yield 'empty appId' => ['appId', '必填配置项 appId 不能为空'];
        yield 'empty mchId' => ['mchId', '必填配置项 mchId 不能为空'];
        yield 'empty key' => ['key', '必填配置项 key 不能为空'];
    }

    #[Test]
    #[DataProvider('invalidSignTypeProvider')]
    public function validateWithInvalidSignTypeThrowsException(string $signType): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage("不支持的签名类型: {$signType}");

        $this->config->setAppId('test_app_id');
        $this->config->setMchId('test_mch_id');
        $this->config->setKey('test_key');
        $this->config->setSignType($signType);

        $this->config->validate();
    }

    /**
     * @return iterable<string, array<int, string>>
     */
    public static function invalidSignTypeProvider(): iterable
    {
        yield 'SHA1' => ['SHA1'];
        yield 'SHA256' => ['SHA256'];
        yield 'invalid' => ['invalid'];
    }

    #[Test]
    #[DataProvider('invalidFeeTypeProvider')]
    public function validateWithInvalidFeeTypeThrowsException(string $feeType): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage("不支持的货币类型: {$feeType}");

        $this->config->setAppId('test_app_id');
        $this->config->setMchId('test_mch_id');
        $this->config->setKey('test_key');
        $this->config->setFeeType($feeType);

        $this->config->validate();
    }

    /**
     * @return iterable<string, array<int, string>>
     */
    public static function invalidFeeTypeProvider(): iterable
    {
        yield 'USD' => ['USD'];
        yield 'EUR' => ['EUR'];
        yield 'JPY' => ['JPY'];
    }

    #[Test]
    #[DataProvider('invalidTimeoutProvider')]
    public function validateWithInvalidTimeoutThrowsException(int $timeout): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('超时时间必须大于0');

        $this->config->setAppId('test_app_id');
        $this->config->setMchId('test_mch_id');
        $this->config->setKey('test_key');
        $this->config->setTimeout($timeout);

        $this->config->validate();
    }

    /**
     * @return iterable<string, array<int, int>>
     */
    public static function invalidTimeoutProvider(): iterable
    {
        yield 'zero timeout' => [0];
        yield 'negative timeout' => [-1];
    }

    #[Test]
    #[DataProvider('invalidConnectTimeoutProvider')]
    public function validateWithInvalidConnectTimeoutThrowsException(int $connectTimeout): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('超时时间必须大于0');

        $this->config->setAppId('test_app_id');
        $this->config->setMchId('test_mch_id');
        $this->config->setKey('test_key');
        $this->config->setConnectTimeout($connectTimeout);

        $this->config->validate();
    }

    /**
     * @return iterable<string, array<int, int>>
     */
    public static function invalidConnectTimeoutProvider(): iterable
    {
        yield 'zero connect timeout' => [0];
        yield 'negative connect timeout' => [-1];
    }

    #[Test]
    public function validateWithNegativeRetryTimesThrowsException(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('重试次数不能为负数');

        $this->config->setAppId('test_app_id');
        $this->config->setMchId('test_mch_id');
        $this->config->setKey('test_key');
        $this->config->setRetryTimes(-1);

        $this->config->validate();
    }

    #[Test]
    public function validateWithNonExistentCertFileThrowsException(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('证书文件不存在: /non/existent/cert.pem');

        $this->config->setAppId('test_app_id');
        $this->config->setMchId('test_mch_id');
        $this->config->setKey('test_key');
        $this->config->setCertPath('/non/existent/cert.pem');

        $this->config->validate();
    }

    #[Test]
    public function validateWithNonExistentKeyFileThrowsException(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('密钥文件不存在: /non/existent/key.pem');

        $this->config->setAppId('test_app_id');
        $this->config->setMchId('test_mch_id');
        $this->config->setKey('test_key');
        $this->config->setKeyPath('/non/existent/key.pem');

        $this->config->validate();
    }

    #[Test]
    public function validateWithNonExistentCaCertFileThrowsException(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('CA证书文件不存在: /non/existent/ca.pem');

        $this->config->setAppId('test_app_id');
        $this->config->setMchId('test_mch_id');
        $this->config->setKey('test_key');
        $this->config->setCaCertPath('/non/existent/ca.pem');

        $this->config->validate();
    }

    #[Test]
    public function setSignTypeWithValidTypeSetsSignType(): void
    {
        $this->config->setSignType('HMAC-SHA256');
        $this->assertSame('HMAC-SHA256', $this->config->getSignType());
    }

    #[Test]
    public function setSignTypeWithInvalidTypeThrowsException(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('不支持的签名类型: invalid');

        $this->config->setSignType('invalid');
    }

    #[Test]
    public function setTimeoutWithValidTimeoutSetsTimeout(): void
    {
        $this->config->setTimeout(60);
        $this->assertSame(60, $this->config->getTimeout());
    }

    #[Test]
    public function setTimeoutWithInvalidTimeoutThrowsException(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('超时时间必须大于0');

        $this->config->setTimeout(0);
    }

    #[Test]
    public function setConnectTimeoutWithValidTimeoutSetsTimeout(): void
    {
        $this->config->setConnectTimeout(20);
        $this->assertSame(20, $this->config->getConnectTimeout());
    }

    #[Test]
    public function setConnectTimeoutWithInvalidTimeoutThrowsException(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('连接超时时间必须大于0');

        $this->config->setConnectTimeout(-1);
    }

    #[Test]
    public function setRetryTimesWithValidRetryTimesSetsRetryTimes(): void
    {
        $this->config->setRetryTimes(5);
        $this->assertSame(5, $this->config->getRetryTimes());
    }

    #[Test]
    public function setRetryTimesWithNegativeRetryTimesThrowsException(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('重试次数不能为负数');

        $this->config->setRetryTimes(-1);
    }

    #[Test]
    public function setBaseUrlTrimsTrailingSlash(): void
    {
        $this->config->setBaseUrl('https://api.test.com/');
        $this->assertSame('https://api.test.com', $this->config->getBaseUrl());
    }

    #[Test]
    public function getDefaultHeadersReturnsDefaultHeaders(): void
    {
        $expected = [
            'User-Agent' => 'WechatMiniProgramPayBundle/1.0',
            'Content-Type' => 'application/xml; charset=utf-8',
        ];

        $this->assertSame($expected, $this->config->getDefaultHeaders());
    }

    #[Test]
    public function setDefaultHeadersSetsCustomHeaders(): void
    {
        $headers = ['Custom-Header' => 'custom-value'];
        $this->config->setDefaultHeaders($headers);

        $this->assertSame($headers, $this->config->getDefaultHeaders());
    }

    #[Test]
    public function testAddDefaultHeaderAddsHeaderToExisting(): void
    {
        $this->config->addDefaultHeader('Custom-Header', 'custom-value');

        $headers = $this->config->getDefaultHeaders();
        $this->assertSame('custom-value', $headers['Custom-Header']);
    }

    #[Test]
    public function testNeedsCertificateWithBothCertAndKeyPathsReturnsTrue(): void
    {
        $this->config->setCertPath('/path/to/cert.pem');
        $this->config->setKeyPath('/path/to/key.pem');

        $this->assertTrue($this->config->needsCertificate());
    }

    #[Test]
    #[DataProvider('needsCertificateFalseProvider')]
    public function testNeedsCertificateWithMissingPathsReturnsFalse(?string $certPath, ?string $keyPath): void
    {
        $this->config->setCertPath($certPath);
        $this->config->setKeyPath($keyPath);

        $this->assertFalse($this->config->needsCertificate());
    }

    /**
     * @return iterable<string, array<int, string|null>>
     */
    public static function needsCertificateFalseProvider(): iterable
    {
        yield 'no cert path' => [null, '/path/to/key.pem'];
        yield 'no key path' => ['/path/to/cert.pem', null];
        yield 'both null' => [null, null];
        yield 'empty cert path' => ['', '/path/to/key.pem'];
        yield 'empty key path' => ['/path/to/cert.pem', ''];
    }

    #[Test]
    public function getHttpClientConfigWithoutCertificatesReturnsBasicConfig(): void
    {
        $this->config->setTimeout(60);
        $this->config->setConnectTimeout(15);

        $config = $this->config->getHttpClientConfig();

        $this->assertSame(60, $config['timeout']);
        $this->assertSame(15, $config['connect_timeout']);
        $this->assertArrayHasKey('headers', $config);
        $this->assertArrayNotHasKey('cert', $config);
        $this->assertArrayNotHasKey('ssl_key', $config);
        $this->assertArrayNotHasKey('verify', $config);
    }

    #[Test]
    public function getHttpClientConfigWithCertificatesReturnsCertConfig(): void
    {
        $this->config->setCertPath('/path/to/cert.pem');
        $this->config->setKeyPath('/path/to/key.pem');
        $this->config->setCaCertPath('/path/to/ca.pem');

        $config = $this->config->getHttpClientConfig();

        $this->assertSame('/path/to/cert.pem', $config['cert']);
        $this->assertSame('/path/to/key.pem', $config['ssl_key']);
        $this->assertSame('/path/to/ca.pem', $config['verify']);
    }

    #[Test]
    public function testToArrayReturnsAllConfigAsArray(): void
    {
        $this->config->setAppId('test_app_id');
        $this->config->setMchId('test_mch_id');
        $this->config->setKey('test_key');
        $this->config->setSignType('HMAC-SHA256');
        $this->config->setFeeType('CNY');
        $this->config->setCertPath('/path/to/cert.pem');
        $this->config->setKeyPath('/path/to/key.pem');
        $this->config->setCaCertPath('/path/to/ca.pem');
        $this->config->setBaseUrl('https://test.api.com');
        $this->config->setTimeout(60);
        $this->config->setConnectTimeout(15);
        $this->config->setRetryTimes(5);
        $this->config->setDebug(true);
        $this->config->setLogPath('/path/to/logs');

        $expected = [
            'app_id' => 'test_app_id',
            'mch_id' => 'test_mch_id',
            'key' => 'test_key',
            'sign_type' => 'HMAC-SHA256',
            'fee_type' => 'CNY',
            'cert_path' => '/path/to/cert.pem',
            'key_path' => '/path/to/key.pem',
            'ca_cert_path' => '/path/to/ca.pem',
            'base_url' => 'https://test.api.com',
            'timeout' => 60,
            'connect_timeout' => 15,
            'retry_times' => 5,
            'debug' => true,
            'log_path' => '/path/to/logs',
        ];

        $this->assertSame($expected, $this->config->toArray());
    }

    #[Test]
    public function getDebugInfoHidesSensitiveInformation(): void
    {
        $this->config->setAppId('test_app_id');
        $this->config->setMchId('test_mch_id');
        $this->config->setKey('sensitive_key');
        $this->config->setCertPath('/path/to/cert.pem');
        $this->config->setKeyPath('/path/to/key.pem');

        $debugInfo = $this->config->getDebugInfo();

        $this->assertSame('test_app_id', $debugInfo['app_id']);
        $this->assertSame('test_mch_id', $debugInfo['mch_id']);
        $this->assertSame('***', $debugInfo['key']);
        $this->assertTrue($debugInfo['needs_certificate']);
    }

    #[Test]
    public function getDebugInfoWithEmptyKeyShowsEmptyString(): void
    {
        $debugInfo = $this->config->getDebugInfo();

        $this->assertSame('', $debugInfo['key']);
    }

    #[Test]
    public function testCloneCreatesDeepCopy(): void
    {
        $this->config->setAppId('original');
        $this->config->setMchId('original');
        $this->config->setKey('original');

        $cloned = $this->config->clone();
        $cloned->setAppId('modified');

        $this->assertSame('original', $this->config->getAppId());
        $this->assertSame('modified', $cloned->getAppId());
    }

    #[Test]
    public function testMergeWithValidConfigMergesSuccessfully(): void
    {
        $this->config->setAppId('original_app_id');
        $this->config->setMchId('original_mch_id');
        $this->config->setKey('original_key');

        $mergeData = [
            'app_id' => 'merged_app_id',
            'sign_type' => 'HMAC-SHA256',
            'timeout' => 60,
            'unknown_field' => 'should_be_ignored',
        ];

        $merged = $this->config->merge($mergeData);

        // 原对象不应被修改
        $this->assertSame('original_app_id', $this->config->getAppId());
        $this->assertSame('MD5', $this->config->getSignType());
        $this->assertSame(30, $this->config->getTimeout());

        // 合并后的对象应包含新值
        $this->assertSame('merged_app_id', $merged->getAppId());
        $this->assertSame('original_mch_id', $merged->getMchId());
        $this->assertSame('original_key', $merged->getKey());
        $this->assertSame('HMAC-SHA256', $merged->getSignType());
        $this->assertSame(60, $merged->getTimeout());
    }

    #[Test]
    public function testMergeWithInvalidSignTypeThrowsException(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('不支持的签名类型: invalid');

        $this->config->merge(['sign_type' => 'invalid']);
    }

    #[Test]
    public function testMergeWithInvalidTimeoutThrowsException(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('超时时间必须大于0');

        $this->config->merge(['timeout' => 0]);
    }

    #[Test]
    #[DataProvider('supportedFeaturesProvider')]
    public function testSupportsFeatureWithDifferentFeaturesReturnsExpectedResult(
        string $feature,
        callable $configSetup,
        bool $expected,
    ): void {
        $configSetup($this->config);

        $this->assertSame($expected, $this->config->supportsFeature($feature));
    }

    /**
     * @return iterable<string, array<int, mixed>>
     */
    public static function supportedFeaturesProvider(): iterable
    {
        yield 'certificate_auth with certificates' => [
            'certificate_auth',
            function (WechatPayConfig $config) {
                $config->setCertPath('/cert');
                $config->setKeyPath('/key');

                return $config;
            },
            true,
        ];

        yield 'certificate_auth without certificates' => [
            'certificate_auth',
            fn (WechatPayConfig $config) => $config,
            false,
        ];

        yield 'hmac_sha256 with HMAC-SHA256' => [
            'hmac_sha256',
            fn (WechatPayConfig $config) => $config->setSignType('HMAC-SHA256'),
            true,
        ];

        yield 'hmac_sha256 with MD5' => [
            'hmac_sha256',
            fn (WechatPayConfig $config) => $config->setSignType('MD5'),
            false,
        ];

        yield 'debug_logging with debug enabled' => [
            'debug_logging',
            function (WechatPayConfig $config) {
                $config->setDebug(true);
                $config->setLogPath('/logs');

                return $config;
            },
            true,
        ];

        yield 'debug_logging without log path' => [
            'debug_logging',
            fn (WechatPayConfig $config) => $config->setDebug(true),
            false,
        ];

        yield 'retry with positive retry times' => [
            'retry',
            fn (WechatPayConfig $config) => $config->setRetryTimes(3),
            true,
        ];

        yield 'retry with zero retry times' => [
            'retry',
            fn (WechatPayConfig $config) => $config->setRetryTimes(0),
            false,
        ];

        yield 'unsupported feature' => [
            'unsupported_feature',
            fn (WechatPayConfig $config) => $config,
            false,
        ];
    }

    #[Test]
    public function getApiUrlConcatenatesBaseUrlAndPath(): void
    {
        $this->config->setBaseUrl('https://api.test.com');

        $this->assertSame('https://api.test.com/pay/unifiedorder', $this->config->getApiUrl('/pay/unifiedorder'));
        $this->assertSame('https://api.test.com/pay/unifiedorder', $this->config->getApiUrl('pay/unifiedorder'));
    }

    #[Test]
    public function getApiUrlWithTrailingSlashInBaseUrlHandlesCorrectly(): void
    {
        $this->config->setBaseUrl('https://api.test.com/');

        // setBaseUrl 应该自动去掉尾部斜杠
        $this->assertSame('https://api.test.com/pay/unifiedorder', $this->config->getApiUrl('pay/unifiedorder'));
    }
}
