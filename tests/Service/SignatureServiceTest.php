<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WechatMiniProgramPayBundle\Service\SignatureService;

/**
 * SignatureService 测试.
 *
 * @internal
 */
#[CoversClass(SignatureService::class)]
#[RunTestsInSeparateProcesses]
final class SignatureServiceTest extends AbstractIntegrationTestCase
{
    private SignatureService $signatureService;

    protected function onSetUp(): void
    {
        $this->signatureService = self::getService(SignatureService::class);
    }

    #[Test]
    public function testGenerateSignatureWithMD5(): void
    {
        $parameters = [
            'appid' => 'wxd930ea5d5a258f4f',
            'body' => 'test',
            'mch_id' => '10000100',
            'nonce_str' => 'ibuaiVcKdpRxkhJA',
        ];
        $key = '192006250b4c09247ec02edce69f6a2d';

        $signature = $this->signatureService->generateSignature($parameters, $key, SignatureService::SIGN_TYPE_MD5);

        // 验证签名格式
        $this->assertMatchesRegularExpression('/^[A-F0-9]{32}$/', $signature);
        $this->assertEquals('9C5719D2CE48B8875101722D3A792434', $signature);
    }

    #[Test]
    public function testGenerateSignatureWithHmacSha256(): void
    {
        $parameters = [
            'appid' => 'wxd930ea5d5a258f4f',
            'body' => 'test',
            'mch_id' => '10000100',
            'nonce_str' => 'ibuaiVcKdpRxkhJA',
        ];
        $key = '192006250b4c09247ec02edce69f6a2d';

        $signature = $this->signatureService->generateSignature($parameters, $key, SignatureService::SIGN_TYPE_HMAC_SHA256);

        // 验证签名格式
        $this->assertMatchesRegularExpression('/^[A-F0-9]{64}$/', $signature);
        $this->assertEquals('B8F8CF02C8F8ED7AA3E829CDFB1C57AD5F0EF73408F726F48297962CE491B555', $signature);
    }

    #[Test]
    public function testGenerateSignatureFiltersEmptyValues(): void
    {
        $parameters = [
            'appid' => 'wxd930ea5d5a258f4f',
            'body' => 'test',
            'mch_id' => '10000100',
            'nonce_str' => 'ibuaiVcKdpRxkhJA',
            'empty_value' => '',
            'null_value' => null,
            'sign' => 'should_be_ignored',
        ];
        $key = '192006250b4c09247ec02edce69f6a2d';

        $signature = $this->signatureService->generateSignature($parameters, $key);

        // 应该与不包含空值和sign字段的参数生成相同签名
        $cleanParameters = [
            'appid' => 'wxd930ea5d5a258f4f',
            'body' => 'test',
            'mch_id' => '10000100',
            'nonce_str' => 'ibuaiVcKdpRxkhJA',
        ];
        $expectedSignature = $this->signatureService->generateSignature($cleanParameters, $key);

        $this->assertEquals($expectedSignature, $signature);
    }

    #[Test]
    public function testGenerateSignatureWithComplexDataTypes(): void
    {
        $parameters = [
            'appid' => 'wxd930ea5d5a258f4f',
            'bool_true' => true,
            'bool_false' => false,
            'array_value' => ['test' => 'value'],
            'numeric_value' => 123,
            'float_value' => 12.34,
        ];
        $key = '192006250b4c09247ec02edce69f6a2d';

        $signature = $this->signatureService->generateSignature($parameters, $key);

        $this->assertMatchesRegularExpression('/^[A-F0-9]{32}$/', $signature);

        // 验证布尔值转换
        $debugInfo = $this->signatureService->debugSignature($parameters, $key);
        $this->assertIsArray($debugInfo);
        $this->assertArrayHasKey('filtered_parameters', $debugInfo);
        $filteredParams = $debugInfo['filtered_parameters'];
        $this->assertIsArray($filteredParams);
        $this->assertArrayHasKey('bool_true', $filteredParams);
        $this->assertArrayHasKey('bool_false', $filteredParams);
        $this->assertArrayHasKey('array_value', $filteredParams);
        $this->assertEquals('Y', $filteredParams['bool_true']);
        $this->assertEquals('N', $filteredParams['bool_false']);
        $this->assertEquals('{"test":"value"}', $filteredParams['array_value']);
    }

    #[Test]
    public function testVerifySignatureSuccess(): void
    {
        $parameters = [
            'appid' => 'wxd930ea5d5a258f4f',
            'body' => 'test',
            'mch_id' => '10000100',
            'nonce_str' => 'ibuaiVcKdpRxkhJA',
            'sign' => '9C5719D2CE48B8875101722D3A792434',
        ];
        $key = '192006250b4c09247ec02edce69f6a2d';

        $isValid = $this->signatureService->verifySignature($parameters, $key);

        $this->assertTrue($isValid);
    }

    #[Test]
    public function testVerifySignatureFailure(): void
    {
        $parameters = [
            'appid' => 'wxd930ea5d5a258f4f',
            'body' => 'test',
            'mch_id' => '10000100',
            'nonce_str' => 'ibuaiVcKdpRxkhJA',
            'sign' => 'invalid_signature',
        ];
        $key = '192006250b4c09247ec02edce69f6a2d';

        $isValid = $this->signatureService->verifySignature($parameters, $key);

        $this->assertFalse($isValid);
    }

    #[Test]
    public function testVerifySignatureWithoutSignField(): void
    {
        $parameters = [
            'appid' => 'wxd930ea5d5a258f4f',
            'body' => 'test',
            'mch_id' => '10000100',
            'nonce_str' => 'ibuaiVcKdpRxkhJA',
        ];
        $key = '192006250b4c09247ec02edce69f6a2d';

        $isValid = $this->signatureService->verifySignature($parameters, $key);

        $this->assertFalse($isValid);
    }

    #[Test]
    public function testVerifySignatureCaseInsensitive(): void
    {
        $parameters = [
            'appid' => 'wxd930ea5d5a258f4f',
            'body' => 'test',
            'mch_id' => '10000100',
            'nonce_str' => 'ibuaiVcKdpRxkhJA',
            'sign' => '9c5719d2ce48b8875101722d3a792434', // 小写
        ];
        $key = '192006250b4c09247ec02edce69f6a2d';

        $isValid = $this->signatureService->verifySignature($parameters, $key);

        $this->assertTrue($isValid);
    }

    #[Test]
    public function testGenerateNonceStr(): void
    {
        $nonceStr = $this->signatureService->generateNonceStr();

        $this->assertEquals(32, strlen($nonceStr));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $nonceStr);
    }

    #[Test]
    public function testGenerateNonceStrWithCustomLength(): void
    {
        $length = 16;
        $nonceStr = $this->signatureService->generateNonceStr($length);

        $this->assertEquals($length, strlen($nonceStr));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $nonceStr);
    }

    #[Test]
    public function testGenerateNonceStrUnique(): void
    {
        $nonceStr1 = $this->signatureService->generateNonceStr();
        $nonceStr2 = $this->signatureService->generateNonceStr();

        $this->assertNotEquals($nonceStr1, $nonceStr2);
    }

    #[Test]
    public function testIsSignTypeSupported(): void
    {
        $this->assertTrue($this->signatureService->isSignTypeSupported(SignatureService::SIGN_TYPE_MD5));
        $this->assertTrue($this->signatureService->isSignTypeSupported(SignatureService::SIGN_TYPE_HMAC_SHA256));
        $this->assertFalse($this->signatureService->isSignTypeSupported('UNSUPPORTED_TYPE'));
    }

    #[Test]
    public function testGetSupportedSignTypes(): void
    {
        $supportedTypes = $this->signatureService->getSupportedSignTypes();

        $this->assertContains(SignatureService::SIGN_TYPE_MD5, $supportedTypes);
        $this->assertContains(SignatureService::SIGN_TYPE_HMAC_SHA256, $supportedTypes);
        $this->assertCount(2, $supportedTypes);
    }

    #[Test]
    public function testSignParameters(): void
    {
        $parameters = [
            'appid' => 'wxd930ea5d5a258f4f',
            'body' => 'test',
            'mch_id' => '10000100',
        ];
        $key = '192006250b4c09247ec02edce69f6a2d';

        $signedParams = $this->signatureService->signParameters($parameters, $key);

        $this->assertArrayHasKey('nonce_str', $signedParams);
        $this->assertArrayHasKey('sign', $signedParams);
        $nonceStr = $signedParams['nonce_str'];
        $sign = $signedParams['sign'];
        $this->assertIsString($nonceStr);
        $this->assertIsString($sign);
        $this->assertEquals(32, strlen($nonceStr));
        $this->assertMatchesRegularExpression('/^[A-F0-9]{32}$/', $sign);

        // 验证签名是否正确
        $isValid = $this->signatureService->verifySignature($signedParams, $key);
        $this->assertTrue($isValid);
    }

    #[Test]
    public function testSignParametersWithExistingNonceStr(): void
    {
        $parameters = [
            'appid' => 'wxd930ea5d5a258f4f',
            'body' => 'test',
            'mch_id' => '10000100',
            'nonce_str' => 'existing_nonce',
        ];
        $key = '192006250b4c09247ec02edce69f6a2d';

        $signedParams = $this->signatureService->signParameters($parameters, $key);

        $this->assertEquals('existing_nonce', $signedParams['nonce_str']);
        $this->assertArrayHasKey('sign', $signedParams);
    }

    #[Test]
    public function testSignParametersWithHmacSha256(): void
    {
        $parameters = [
            'appid' => 'wxd930ea5d5a258f4f',
            'body' => 'test',
            'mch_id' => '10000100',
        ];
        $key = '192006250b4c09247ec02edce69f6a2d';

        $signedParams = $this->signatureService->signParameters($parameters, $key, SignatureService::SIGN_TYPE_HMAC_SHA256);

        $this->assertArrayHasKey('sign_type', $signedParams);
        $this->assertEquals(SignatureService::SIGN_TYPE_HMAC_SHA256, $signedParams['sign_type']);
        $this->assertArrayHasKey('sign', $signedParams);
        $sign = $signedParams['sign'];
        $this->assertIsString($sign);
        $this->assertMatchesRegularExpression('/^[A-F0-9]{64}$/', $sign);
    }

    #[Test]
    public function testDebugSignature(): void
    {
        $parameters = [
            'appid' => 'wxd930ea5d5a258f4f',
            'body' => 'test',
            'mch_id' => '10000100',
            'nonce_str' => 'ibuaiVcKdpRxkhJA',
            'empty_field' => '',
        ];
        $key = '192006250b4c09247ec02edce69f6a2d';

        $debugInfo = $this->signatureService->debugSignature($parameters, $key);

        $this->assertIsArray($debugInfo);
        $this->assertArrayHasKey('original_parameters', $debugInfo);
        $this->assertArrayHasKey('filtered_parameters', $debugInfo);
        $this->assertArrayHasKey('sorted_parameters', $debugInfo);
        $this->assertArrayHasKey('query_string', $debugInfo);
        $this->assertArrayHasKey('string_sign_temp', $debugInfo);
        $this->assertArrayHasKey('signature', $debugInfo);
        $this->assertArrayHasKey('sign_type', $debugInfo);

        $this->assertEquals($parameters, $debugInfo['original_parameters']);
        $filteredParameters = $debugInfo['filtered_parameters'];
        $this->assertIsArray($filteredParameters);
        $this->assertArrayNotHasKey('empty_field', $filteredParameters);
        $this->assertEquals('appid=wxd930ea5d5a258f4f&body=test&mch_id=10000100&nonce_str=ibuaiVcKdpRxkhJA', $debugInfo['query_string']);
        $this->assertEquals('9C5719D2CE48B8875101722D3A792434', $debugInfo['signature']);
    }

    #[Test]
    #[DataProvider('provideInvalidKeys')]
    public function testGenerateSignatureWithInvalidKey(string $key, string $expectedMessage): void
    {
        $parameters = [
            'appid' => 'wxd930ea5d5a258f4f',
            'body' => 'test',
        ];

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->signatureService->generateSignature($parameters, $key);
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function provideInvalidKeys(): iterable
    {
        yield 'empty key' => ['', '商户密钥不能为空'];
    }

    #[Test]
    public function testGenerateSignatureWithEmptyParameters(): void
    {
        $parameters = [];
        $key = '192006250b4c09247ec02edce69f6a2d';

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('签名参数不能为空');

        $this->signatureService->generateSignature($parameters, $key);
    }

    #[Test]
    public function testGenerateSignatureWithOnlyEmptyParameters(): void
    {
        $parameters = [
            'empty' => '',
            'null' => null,
            'sign' => 'should_be_ignored',
        ];
        $key = '192006250b4c09247ec02edce69f6a2d';

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('签名参数不能为空');

        $this->signatureService->generateSignature($parameters, $key);
    }

    #[Test]
    public function testGenerateSignatureWithUnsupportedSignType(): void
    {
        $parameters = [
            'appid' => 'wxd930ea5d5a258f4f',
            'body' => 'test',
        ];
        $key = '192006250b4c09247ec02edce69f6a2d';

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('不支持的签名类型: UNSUPPORTED');

        $this->signatureService->generateSignature($parameters, $key, 'UNSUPPORTED');
    }

    #[Test]
    public function testGenerateNonceStrWithInvalidLength(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('随机字符串长度必须大于0');

        $this->signatureService->generateNonceStr(0);
    }

    #[Test]
    public function testGenerateNonceStrWithNegativeLength(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('随机字符串长度必须大于0');

        $this->signatureService->generateNonceStr(-1);
    }

    #[Test]
    public function testSignatureConsistency(): void
    {
        $parameters = [
            'appid' => 'wxd930ea5d5a258f4f',
            'body' => 'test',
            'mch_id' => '10000100',
            'nonce_str' => 'ibuaiVcKdpRxkhJA',
        ];
        $key = '192006250b4c09247ec02edce69f6a2d';

        $signature1 = $this->signatureService->generateSignature($parameters, $key);
        $signature2 = $this->signatureService->generateSignature($parameters, $key);

        $this->assertEquals($signature1, $signature2);
    }

    #[Test]
    public function testParameterOrderDoesNotAffectSignature(): void
    {
        $parameters1 = [
            'appid' => 'wxd930ea5d5a258f4f',
            'body' => 'test',
            'mch_id' => '10000100',
            'nonce_str' => 'ibuaiVcKdpRxkhJA',
        ];

        $parameters2 = [
            'nonce_str' => 'ibuaiVcKdpRxkhJA',
            'mch_id' => '10000100',
            'body' => 'test',
            'appid' => 'wxd930ea5d5a258f4f',
        ];

        $key = '192006250b4c09247ec02edce69f6a2d';

        $signature1 = $this->signatureService->generateSignature($parameters1, $key);
        $signature2 = $this->signatureService->generateSignature($parameters2, $key);

        $this->assertEquals($signature1, $signature2);
    }
}
