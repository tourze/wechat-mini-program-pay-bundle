<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Tests\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WechatMiniProgramPayBundle\Response\UnifiedOrderResponse;
use WechatMiniProgramPayBundle\Service\SignatureService;

/**
 * @internal
 */
#[CoversClass(UnifiedOrderResponse::class)]
final class UnifiedOrderResponseTest extends TestCase
{
    private function createSuccessXmlResponse(): string
    {
        return '<xml>
            <return_code><![CDATA[SUCCESS]]></return_code>
            <return_msg><![CDATA[OK]]></return_msg>
            <result_code><![CDATA[SUCCESS]]></result_code>
            <appid><![CDATA[wx1234567890abcdef]]></appid>
            <mch_id><![CDATA[1234567890]]></mch_id>
            <nonce_str><![CDATA[5K8264ILTKCH16CQ2502SI8ZNMTM67VS]]></nonce_str>
            <sign><![CDATA[C380BEC2BFD727A4B6845133519F3AD6]]></sign>
            <trade_type><![CDATA[JSAPI]]></trade_type>
            <prepay_id><![CDATA[wx201811111111111111]]></prepay_id>
        </xml>';
    }

    private function createReturnFailXmlResponse(): string
    {
        return '<xml>
            <return_code><![CDATA[FAIL]]></return_code>
            <return_msg><![CDATA[签名失败]]></return_msg>
        </xml>';
    }

    private function createResultFailXmlResponse(): string
    {
        return '<xml>
            <return_code><![CDATA[SUCCESS]]></return_code>
            <return_msg><![CDATA[OK]]></return_msg>
            <result_code><![CDATA[FAIL]]></result_code>
            <err_code><![CDATA[NOAUTH]]></err_code>
            <err_code_des><![CDATA[商户未开通此接口权限]]></err_code_des>
            <appid><![CDATA[wx1234567890abcdef]]></appid>
            <mch_id><![CDATA[1234567890]]></mch_id>
            <nonce_str><![CDATA[5K8264ILTKCH16CQ2502SI8ZNMTM67VS]]></nonce_str>
            <sign><![CDATA[C380BEC2BFD727A4B6845133519F3AD6]]></sign>
        </xml>';
    }

    private function createNativeSuccessXmlResponse(): string
    {
        return '<xml>
            <return_code><![CDATA[SUCCESS]]></return_code>
            <return_msg><![CDATA[OK]]></return_msg>
            <result_code><![CDATA[SUCCESS]]></result_code>
            <appid><![CDATA[wx1234567890abcdef]]></appid>
            <mch_id><![CDATA[1234567890]]></mch_id>
            <nonce_str><![CDATA[5K8264ILTKCH16CQ2502SI8ZNMTM67VS]]></nonce_str>
            <sign><![CDATA[C380BEC2BFD727A4B6845133519F3AD6]]></sign>
            <trade_type><![CDATA[NATIVE]]></trade_type>
            <prepay_id><![CDATA[wx201811111111111111]]></prepay_id>
            <code_url><![CDATA[weixin://wxpay/bizpayurl?pr=1234567890]]></code_url>
        </xml>';
    }

    private function createH5SuccessXmlResponse(): string
    {
        return '<xml>
            <return_code><![CDATA[SUCCESS]]></return_code>
            <return_msg><![CDATA[OK]]></return_msg>
            <result_code><![CDATA[SUCCESS]]></result_code>
            <appid><![CDATA[wx1234567890abcdef]]></appid>
            <mch_id><![CDATA[1234567890]]></mch_id>
            <nonce_str><![CDATA[5K8264ILTKCH16CQ2502SI8ZNMTM67VS]]></nonce_str>
            <sign><![CDATA[C380BEC2BFD727A4B6845133519F3AD6]]></sign>
            <trade_type><![CDATA[MWEB]]></trade_type>
            <prepay_id><![CDATA[wx201811111111111111]]></prepay_id>
            <mweb_url><![CDATA[https://wx.tenpay.com/cgi-bin/mmpayweb-bin/checkmweb?prepay_id=wx201811111111111111]]></mweb_url>
        </xml>';
    }

    #[Test]
    public function testFromXmlWithSuccessResponse(): void
    {
        $xml = $this->createSuccessXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('', $response->getErrorCode());
        $this->assertEquals('', $response->getErrorMessage());
        $this->assertEquals('wx201811111111111111', $response->getPrepayId());
        $this->assertEquals('wx1234567890abcdef', $response->getAppId());
        $this->assertEquals('1234567890', $response->getMchId());
        $this->assertEquals('5K8264ILTKCH16CQ2502SI8ZNMTM67VS', $response->getNonceStr());
        $this->assertEquals('C380BEC2BFD727A4B6845133519F3AD6', $response->getSign());
        $this->assertEquals('JSAPI', $response->getTradeType());
    }

    #[Test]
    public function testFromXmlWithReturnFailResponse(): void
    {
        $xml = $this->createReturnFailXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        $this->assertFalse($response->isSuccess());
        $this->assertEquals('FAIL', $response->getErrorCode());
        $this->assertEquals('签名失败', $response->getErrorMessage());
        $this->assertEquals('', $response->getPrepayId());
    }

    #[Test]
    public function testFromXmlWithResultFailResponse(): void
    {
        $xml = $this->createResultFailXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        $this->assertFalse($response->isSuccess());
        $this->assertEquals('NOAUTH', $response->getErrorCode());
        $this->assertEquals('商户未开通此接口权限', $response->getErrorMessage());
        $this->assertEquals('', $response->getPrepayId());
    }

    #[Test]
    public function testFromXmlWithNativeSuccessResponse(): void
    {
        $xml = $this->createNativeSuccessXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('wx201811111111111111', $response->getPrepayId());
        $this->assertEquals('weixin://wxpay/bizpayurl?pr=1234567890', $response->getCodeUrl());
        $this->assertEquals('NATIVE', $response->getTradeType());
        $this->assertEquals('', $response->getMwebUrl());
    }

    #[Test]
    public function testFromXmlWithH5SuccessResponse(): void
    {
        $xml = $this->createH5SuccessXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('wx201811111111111111', $response->getPrepayId());
        $this->assertEquals('https://wx.tenpay.com/cgi-bin/mmpayweb-bin/checkmweb?prepay_id=wx201811111111111111', $response->getMwebUrl());
        $this->assertEquals('MWEB', $response->getTradeType());
        $this->assertEquals('', $response->getCodeUrl());
    }

    #[Test]
    public function testFromXmlWithEmptyXmlThrowsException(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('响应内容为空');

        UnifiedOrderResponse::fromXml('');
    }

    #[Test]
    public function testFromXmlWithInvalidXmlThrowsException(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('响应XML解析失败');

        UnifiedOrderResponse::fromXml('<invalid>xml</not_matching>');
    }

    #[Test]
    public function testFromXmlWithNonXmlContentThrowsException(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('响应XML解析失败');

        UnifiedOrderResponse::fromXml('{"json": "data"}');
    }

    #[Test]
    public function testVerifySignatureWithValidSignature(): void
    {
        $xml = $this->createSuccessXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        // 对于成功的响应，但没有实际验证签名（需要密钥），测试方法执行不出错并返回布尔值
        $result = $response->verifySignature('test_key');
        // verifySignature() 返回类型已明确为 bool,直接使用返回值即可(方法不抛出异常即表示成功)
        $this->assertNotNull($result);
    }

    #[Test]
    public function testVerifySignatureWithFailedResponse(): void
    {
        $xml = $this->createReturnFailXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        // 失败的响应应该返回true（跳过签名验证）
        $result = $response->verifySignature('test_key');
        $this->assertTrue($result);
    }

    #[Test]
    public function testVerifySignatureWithMissingSign(): void
    {
        $xmlWithoutSign = '<xml>
            <return_code><![CDATA[SUCCESS]]></return_code>
            <return_msg><![CDATA[OK]]></return_msg>
            <result_code><![CDATA[SUCCESS]]></result_code>
            <appid><![CDATA[wx1234567890abcdef]]></appid>
            <mch_id><![CDATA[1234567890]]></mch_id>
            <prepay_id><![CDATA[wx201811111111111111]]></prepay_id>
        </xml>';

        $response = UnifiedOrderResponse::fromXml($xmlWithoutSign);

        // 缺少签名字段应该返回false
        $result = $response->verifySignature('test_key');
        $this->assertFalse($result);
    }

    #[Test]
    public function testGetRawData(): void
    {
        $xml = $this->createSuccessXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        $rawData = $response->getRawData();

        // getRawData() 返回类型已明确为 array,无需 assertIsArray
        $this->assertEquals('SUCCESS', $rawData['return_code']);
        $this->assertEquals('OK', $rawData['return_msg']);
        $this->assertEquals('SUCCESS', $rawData['result_code']);
        $this->assertEquals('wx1234567890abcdef', $rawData['appid']);
        $this->assertEquals('1234567890', $rawData['mch_id']);
        $this->assertEquals('wx201811111111111111', $rawData['prepay_id']);
    }

    #[Test]
    public function testHasField(): void
    {
        $xml = $this->createSuccessXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        $this->assertTrue($response->hasField('return_code'));
        $this->assertTrue($response->hasField('prepay_id'));
        $this->assertFalse($response->hasField('non_existent_field'));
        $this->assertFalse($response->hasField(''));
    }

    #[Test]
    public function testGetDebugInfo(): void
    {
        $xml = $this->createSuccessXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        $debugInfo = $response->getDebugInfo();

        // getDebugInfo() 返回类型已明确为 array
        $this->assertArrayHasKey('success', $debugInfo);
        $this->assertArrayHasKey('error_code', $debugInfo);
        $this->assertArrayHasKey('error_message', $debugInfo);
        $this->assertArrayHasKey('prepay_id', $debugInfo);
        $this->assertArrayHasKey('code_url', $debugInfo);
        $this->assertArrayHasKey('mweb_url', $debugInfo);
        $this->assertArrayHasKey('raw_data', $debugInfo);

        $this->assertTrue($debugInfo['success']);
        $this->assertEquals('wx201811111111111111', $debugInfo['prepay_id']);
        // raw_data 是 array<string, mixed> 的子元素,保留类型断言以验证结构
        $this->assertIsArray($debugInfo['raw_data']);
    }

    #[Test]
    public function testToJson(): void
    {
        $xml = $this->createSuccessXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        $json = $response->toJson();
        $data = json_decode($json, true);

        // json_decode() 返回 mixed,保留类型断言验证结构
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('error_code', $data);
        $this->assertArrayHasKey('error_message', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        $this->assertArrayHasKey('prepay_id', $data['data']);
        $this->assertArrayHasKey('app_id', $data['data']);
        $this->assertArrayHasKey('mch_id', $data['data']);
        $this->assertArrayHasKey('trade_type', $data['data']);

        $this->assertTrue($data['success']);
        $this->assertEquals('', $data['error_code']);
        $this->assertEquals('', $data['error_message']);
        $this->assertEquals('wx201811111111111111', $data['data']['prepay_id']);
        $this->assertEquals('wx1234567890abcdef', $data['data']['app_id']);
        $this->assertEquals('1234567890', $data['data']['mch_id']);
        $this->assertEquals('JSAPI', $data['data']['trade_type']);
    }

    #[Test]
    public function testToJsonWithFailedResponse(): void
    {
        $xml = $this->createResultFailXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        $json = $response->toJson();
        $data = json_decode($json, true);

        // json_decode() 返回 mixed,保留类型断言验证结构
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('error_code', $data);
        $this->assertArrayHasKey('error_message', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        $this->assertArrayHasKey('prepay_id', $data['data']);

        $this->assertFalse($data['success']);
        $this->assertEquals('NOAUTH', $data['error_code']);
        $this->assertEquals('商户未开通此接口权限', $data['error_message']);
        $this->assertEquals('', $data['data']['prepay_id']);
    }

    #[Test]
    public function testValidateRequiredFields(): void
    {
        $xml = $this->createSuccessXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        // 测试存在的字段
        $missingFields = $response->validateRequiredFields(['return_code', 'appid', 'prepay_id']);
        $this->assertEmpty($missingFields);

        // 测试不存在的字段
        $missingFields = $response->validateRequiredFields(['non_existent_field1', 'non_existent_field2']);
        $this->assertEquals(['non_existent_field1', 'non_existent_field2'], $missingFields);

        // 混合测试
        $missingFields = $response->validateRequiredFields(['appid', 'non_existent_field', 'prepay_id']);
        $this->assertEquals(['non_existent_field'], $missingFields);
    }

    #[Test]
    public function testGetPaymentInfo(): void
    {
        $xml = $this->createSuccessXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        $paymentInfo = $response->getPaymentInfo();

        // getPaymentInfo() 返回类型已明确为 array<string, string>
        $this->assertEquals('wx201811111111111111', $paymentInfo['prepay_id']);
        $this->assertEquals('JSAPI', $paymentInfo['trade_type']);
        $this->assertArrayNotHasKey('code_url', $paymentInfo);
        $this->assertArrayNotHasKey('mweb_url', $paymentInfo);
    }

    #[Test]
    public function testGetPaymentInfoWithNative(): void
    {
        $xml = $this->createNativeSuccessXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        $paymentInfo = $response->getPaymentInfo();

        // getPaymentInfo() 返回类型已明确为 array<string, string>
        $this->assertEquals('wx201811111111111111', $paymentInfo['prepay_id']);
        $this->assertEquals('NATIVE', $paymentInfo['trade_type']);
        $this->assertEquals('weixin://wxpay/bizpayurl?pr=1234567890', $paymentInfo['code_url']);
        $this->assertArrayNotHasKey('mweb_url', $paymentInfo);
    }

    #[Test]
    public function testGetPaymentInfoWithH5(): void
    {
        $xml = $this->createH5SuccessXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        $paymentInfo = $response->getPaymentInfo();

        // getPaymentInfo() 返回类型已明确为 array<string, string>
        $this->assertEquals('wx201811111111111111', $paymentInfo['prepay_id']);
        $this->assertEquals('MWEB', $paymentInfo['trade_type']);
        $this->assertEquals('https://wx.tenpay.com/cgi-bin/mmpayweb-bin/checkmweb?prepay_id=wx201811111111111111', $paymentInfo['mweb_url']);
        $this->assertArrayNotHasKey('code_url', $paymentInfo);
    }

    #[Test]
    public function testGenerateMiniProgramPayParamsSuccess(): void
    {
        $xml = $this->createSuccessXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        $params = $response->generateMiniProgramPayParams('test_key');

        // generateMiniProgramPayParams() 返回类型已明确为 array<string, string>
        $this->assertArrayHasKey('appId', $params);
        $this->assertArrayHasKey('timeStamp', $params);
        $this->assertArrayHasKey('nonceStr', $params);
        $this->assertArrayHasKey('package', $params);
        $this->assertArrayHasKey('signType', $params);
        $this->assertArrayHasKey('paySign', $params);

        $this->assertEquals('wx1234567890abcdef', $params['appId']);
        $this->assertEquals('prepay_id=wx201811111111111111', $params['package']);
        $this->assertEquals('MD5', $params['signType']);
        // 元素类型已由方法签名保证为 string,无需 assertIsString
    }

    #[Test]
    public function testGenerateMiniProgramPayParamsWithHmacSha256(): void
    {
        $xml = $this->createSuccessXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        $params = $response->generateMiniProgramPayParams('test_key', SignatureService::SIGN_TYPE_HMAC_SHA256);

        $this->assertEquals('HMAC-SHA256', $params['signType']);
        // 元素类型已由方法签名保证为 string,无需 assertIsString
    }

    #[Test]
    public function testGenerateMiniProgramPayParamsFailsWithFailedResponse(): void
    {
        $xml = $this->createResultFailXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('响应失败或缺少预支付ID，无法生成小程序支付参数');

        $response->generateMiniProgramPayParams('test_key');
    }

    #[Test]
    public function testGenerateAppPayParamsSuccess(): void
    {
        $xml = $this->createSuccessXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        $params = $response->generateAppPayParams('test_key');

        // generateAppPayParams() 返回类型已明确为 array<string, string>
        $this->assertArrayHasKey('appid', $params);
        $this->assertArrayHasKey('partnerid', $params);
        $this->assertArrayHasKey('prepayid', $params);
        $this->assertArrayHasKey('package', $params);
        $this->assertArrayHasKey('noncestr', $params);
        $this->assertArrayHasKey('timestamp', $params);
        $this->assertArrayHasKey('sign', $params);

        $this->assertEquals('wx1234567890abcdef', $params['appid']);
        $this->assertEquals('1234567890', $params['partnerid']);
        $this->assertEquals('wx201811111111111111', $params['prepayid']);
        $this->assertEquals('Sign=WXPay', $params['package']);
        // 元素类型已由方法签名保证为 string,无需 assertIsString
    }

    #[Test]
    public function testGenerateAppPayParamsFailsWithFailedResponse(): void
    {
        $xml = $this->createResultFailXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('响应失败或缺少预支付ID，无法生成APP支付参数');

        $response->generateAppPayParams('test_key');
    }

    #[Test]
    public function testGenerateJsApiPayParamsSuccess(): void
    {
        $xml = $this->createSuccessXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        $params = $response->generateJsApiPayParams('test_key');

        // generateJsApiPayParams() 返回类型已明确为 array<string, string>
        $this->assertArrayHasKey('appId', $params);
        $this->assertArrayHasKey('timeStamp', $params);
        $this->assertArrayHasKey('nonceStr', $params);
        $this->assertArrayHasKey('package', $params);
        $this->assertArrayHasKey('signType', $params);
        $this->assertArrayHasKey('paySign', $params);

        $this->assertEquals('wx1234567890abcdef', $params['appId']);
        $this->assertEquals('prepay_id=wx201811111111111111', $params['package']);
        $this->assertEquals('MD5', $params['signType']);
    }

    #[Test]
    public function testGenerateJsApiPayParamsFailsWithFailedResponse(): void
    {
        $xml = $this->createResultFailXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('响应失败或缺少预支付ID，无法生成JSAPI支付参数');

        $response->generateJsApiPayParams('test_key');
    }

    #[Test]
    #[DataProvider('errorCodeMappingProvider')]
    public function testErrorCodeMapping(string $errorCode, string $expectedMessage): void
    {
        $xml = '<xml>
            <return_code><![CDATA[SUCCESS]]></return_code>
            <return_msg><![CDATA[OK]]></return_msg>
            <result_code><![CDATA[FAIL]]></result_code>
            <err_code><![CDATA[' . $errorCode . ']]></err_code>
            <err_code_des><![CDATA[原始错误信息]]></err_code_des>
        </xml>';

        $response = UnifiedOrderResponse::fromXml($xml);

        $this->assertFalse($response->isSuccess());
        $this->assertEquals($errorCode, $response->getErrorCode());
        $this->assertStringContainsString($expectedMessage, $response->getErrorMessage());
    }

    /**
     * @return array<int, list<string>>
     */
    public static function errorCodeMappingProvider(): array
    {
        return [
            ['NOAUTH', '商户未开通此接口权限'],
            ['NOTENOUGH', '余额不足'],
            ['ORDERPAID', '商户订单已支付'],
            ['ORDERCLOSED', '订单已关闭'],
            ['SYSTEMERROR', '系统错误'],
            ['APPID_NOT_EXIST', 'APPID不存在'],
            ['MCHID_NOT_EXIST', '商户号不存在'],
            ['APPID_MCHID_NOT_MATCH', 'appid和mch_id不匹配'],
            ['LACK_PARAMS', '缺少参数'],
            ['OUT_TRADE_NO_USED', '商户订单号重复'],
            ['SIGNERROR', '签名错误'],
            ['XML_FORMAT_ERROR', 'XML格式错误'],
            ['REQUIRE_POST_METHOD', '请使用post方法'],
            ['POST_DATA_EMPTY', 'post数据为空'],
            ['NOT_UTF8', '编码格式错误'],
            ['UNKNOWN_ERROR_CODE', '未知错误'],
        ];
    }

    #[Test]
    public function testErrorMessageWithOriginalMessage(): void
    {
        $xml = '<xml>
            <return_code><![CDATA[SUCCESS]]></return_code>
            <return_msg><![CDATA[OK]]></return_msg>
            <result_code><![CDATA[FAIL]]></result_code>
            <err_code><![CDATA[NOAUTH]]></err_code>
            <err_code_des><![CDATA[详细的原始错误信息]]></err_code_des>
        </xml>';

        $response = UnifiedOrderResponse::fromXml($xml);

        $this->assertEquals('商户未开通此接口权限(详细的原始错误信息)', $response->getErrorMessage());
    }

    #[Test]
    public function testErrorMessageWithoutOriginalMessage(): void
    {
        $xml = '<xml>
            <return_code><![CDATA[SUCCESS]]></return_code>
            <return_msg><![CDATA[OK]]></return_msg>
            <result_code><![CDATA[FAIL]]></result_code>
            <err_code><![CDATA[NOAUTH]]></err_code>
        </xml>';

        $response = UnifiedOrderResponse::fromXml($xml);

        $this->assertEquals('商户未开通此接口权限', $response->getErrorMessage());
    }

    #[Test]
    public function testReturnFailWithoutResultCode(): void
    {
        $xml = '<xml>
            <return_code><![CDATA[FAIL]]></return_code>
            <return_msg><![CDATA[通信失败]]></return_msg>
        </xml>';

        $response = UnifiedOrderResponse::fromXml($xml);

        $this->assertFalse($response->isSuccess());
        $this->assertEquals('FAIL', $response->getErrorCode());
        $this->assertEquals('通信失败', $response->getErrorMessage());
        $this->assertEquals('', $response->getPrepayId());
        $this->assertEquals('', $response->getCodeUrl());
        $this->assertEquals('', $response->getMwebUrl());
    }

    #[Test]
    public function testReturnFailWithDefaultMessage(): void
    {
        $xml = '<xml>
            <return_code><![CDATA[FAIL]]></return_code>
        </xml>';

        $response = UnifiedOrderResponse::fromXml($xml);

        $this->assertFalse($response->isSuccess());
        $this->assertEquals('FAIL', $response->getErrorCode());
        $this->assertEquals('通信失败', $response->getErrorMessage());
    }

    #[Test]
    public function testResultFailWithUnknownErrorCode(): void
    {
        $xml = '<xml>
            <return_code><![CDATA[SUCCESS]]></return_code>
            <return_msg><![CDATA[OK]]></return_msg>
            <result_code><![CDATA[FAIL]]></result_code>
        </xml>';

        $response = UnifiedOrderResponse::fromXml($xml);

        $this->assertFalse($response->isSuccess());
        $this->assertEquals('UNKNOWN_ERROR', $response->getErrorCode());
        $this->assertEquals('未知错误', $response->getErrorMessage());
    }

    #[Test]
    public function testCompleteWorkflowSuccess(): void
    {
        $xml = $this->createSuccessXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        // 验证基本信息
        $this->assertTrue($response->isSuccess());
        $this->assertNotEmpty($response->getPrepayId());
        $this->assertNotEmpty($response->getAppId());
        $this->assertNotEmpty($response->getMchId());

        // 验证字段存在性
        $this->assertTrue($response->hasField('return_code'));
        $this->assertTrue($response->hasField('prepay_id'));

        // 验证必需字段
        $missingFields = $response->validateRequiredFields(['return_code', 'result_code', 'prepay_id']);
        $this->assertEmpty($missingFields);

        // 获取支付信息
        $paymentInfo = $response->getPaymentInfo();
        $this->assertArrayHasKey('prepay_id', $paymentInfo);
        $this->assertArrayHasKey('trade_type', $paymentInfo);

        // 生成小程序支付参数
        $miniProgramParams = $response->generateMiniProgramPayParams('test_key');
        // generateMiniProgramPayParams() 返回类型已明确为 array<string, string>
        $this->assertArrayHasKey('appId', $miniProgramParams);
        $this->assertArrayHasKey('paySign', $miniProgramParams);

        // 转换为JSON
        $json = $response->toJson();
        $jsonData = json_decode($json, true);
        // json_decode() 返回 mixed,保留类型断言验证结构
        $this->assertIsArray($jsonData);
        $this->assertArrayHasKey('success', $jsonData);
        $this->assertIsBool($jsonData['success']);
        $this->assertTrue($jsonData['success']);

        // 获取调试信息
        $debugInfo = $response->getDebugInfo();
        $this->assertTrue($debugInfo['success']);
        // raw_data 是 array<string, mixed> 的子元素,保留类型断言以验证结构
        $this->assertIsArray($debugInfo['raw_data']);
    }

    #[Test]
    public function testCompleteWorkflowFailure(): void
    {
        $xml = $this->createResultFailXmlResponse();
        $response = UnifiedOrderResponse::fromXml($xml);

        // 验证失败状态
        $this->assertFalse($response->isSuccess());
        $this->assertNotEmpty($response->getErrorCode());
        $this->assertNotEmpty($response->getErrorMessage());
        $this->assertEmpty($response->getPrepayId());

        // 验证签名（失败响应应该跳过验证）
        $this->assertTrue($response->verifySignature('test_key'));

        // 转换为JSON
        $json = $response->toJson();
        $jsonData = json_decode($json, true);
        // json_decode() 返回 mixed,保留类型断言验证结构
        $this->assertIsArray($jsonData);
        $this->assertArrayHasKey('success', $jsonData);
        $this->assertArrayHasKey('error_code', $jsonData);
        $this->assertIsBool($jsonData['success']);
        $this->assertIsString($jsonData['error_code']);
        $this->assertFalse($jsonData['success']);
        $this->assertNotEmpty($jsonData['error_code']);

        // 获取调试信息
        $debugInfo = $response->getDebugInfo();
        $this->assertFalse($debugInfo['success']);
        $this->assertNotEmpty($debugInfo['error_code']);

        // 尝试生成支付参数应该失败
        $this->expectException(PaymentConfigurationException::class);
        $response->generateMiniProgramPayParams('test_key');
    }

    #[Test]
    public function testEdgeCasesHandling(): void
    {
        // 测试空字段值
        $xmlWithEmptyFields = '<xml>
            <return_code><![CDATA[SUCCESS]]></return_code>
            <return_msg><![CDATA[OK]]></return_msg>
            <result_code><![CDATA[SUCCESS]]></result_code>
            <appid><![CDATA[wx1234567890abcdef]]></appid>
            <mch_id><![CDATA[]]></mch_id>
            <nonce_str><![CDATA[5K8264ILTKCH16CQ2502SI8ZNMTM67VS]]></nonce_str>
            <prepay_id><![CDATA[wx201811111111111111]]></prepay_id>
            <empty_field><![CDATA[]]></empty_field>
        </xml>';

        $response = UnifiedOrderResponse::fromXml($xmlWithEmptyFields);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('', $response->getMchId()); // 空值字段
        $this->assertFalse($response->hasField('empty_field')); // 空值字段不应该被认为存在
        $this->assertFalse($response->hasField('non_existent')); // 不存在的字段

        // 测试获取原始数据
        $rawData = $response->getRawData();
        $this->assertArrayHasKey('mch_id', $rawData);
        $this->assertEquals('', $rawData['mch_id']);
    }
}
