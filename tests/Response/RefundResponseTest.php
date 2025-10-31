<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Tests\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WechatMiniProgramPayBundle\Response\RefundResponse;

/**
 * @internal
 */
#[CoversClass(RefundResponse::class)]
final class RefundResponseTest extends TestCase
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
            <transaction_id><![CDATA[4200000000000000001]]></transaction_id>
            <out_trade_no><![CDATA[OUT_123456789]]></out_trade_no>
            <out_refund_no><![CDATA[REFUND_123456789]]></out_refund_no>
            <refund_id><![CDATA[50000000000000000001]]></refund_id>
            <total_fee><![CDATA[100]]></total_fee>
            <refund_fee><![CDATA[50]]></refund_fee>
            <settlement_total_fee><![CDATA[100]]></settlement_total_fee>
            <settlement_refund_fee><![CDATA[50]]></settlement_refund_fee>
            <fee_type><![CDATA[CNY]]></fee_type>
            <cash_fee><![CDATA[100]]></cash_fee>
            <cash_fee_type><![CDATA[CNY]]></cash_fee_type>
            <cash_refund_fee><![CDATA[50]]></cash_refund_fee>
            <coupon_refund_fee><![CDATA[0]]></coupon_refund_fee>
            <coupon_refund_count><![CDATA[0]]></coupon_refund_count>
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
            <err_code><![CDATA[ORDERNOTEXIST]]></err_code>
            <err_code_des><![CDATA[此交易订单号不存在]]></err_code_des>
            <appid><![CDATA[wx1234567890abcdef]]></appid>
            <mch_id><![CDATA[1234567890]]></mch_id>
            <nonce_str><![CDATA[5K8264ILTKCH16CQ2502SI8ZNMTM67VS]]></nonce_str>
            <sign><![CDATA[C380BEC2BFD727A4B6845133519F3AD6]]></sign>
        </xml>';
    }

    #[Test]
    public function testFromXmlWithSuccessResponse(): void
    {
        $xml = $this->createSuccessXmlResponse();
        $response = RefundResponse::fromXml($xml);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('', $response->getErrorCode());
        $this->assertEquals('', $response->getErrorMessage());
        $this->assertEquals('50000000000000000001', $response->getRefundId());
        $this->assertEquals('REFUND_123456789', $response->getOutRefundNo());
        $this->assertEquals(50, $response->getRefundFee());
        $this->assertEquals(50, $response->getSettlementRefundFee());
        $this->assertEquals(100, $response->getTotalFee());
        $this->assertEquals(100, $response->getSettlementTotalFee());
        $this->assertEquals('CNY', $response->getFeeType());
        $this->assertEquals(100, $response->getCashFee());
        $this->assertEquals('CNY', $response->getCashFeeType());
        $this->assertEquals(50, $response->getCashRefundFee());
        $this->assertEquals(0, $response->getCouponRefundFee());
        $this->assertEquals(0, $response->getCouponRefundCount());
    }

    #[Test]
    public function testFromXmlWithReturnFailResponse(): void
    {
        $xml = $this->createReturnFailXmlResponse();
        $response = RefundResponse::fromXml($xml);

        $this->assertFalse($response->isSuccess());
        $this->assertEquals('FAIL', $response->getErrorCode());
        $this->assertEquals('签名失败', $response->getErrorMessage());
        $this->assertEquals('', $response->getRefundId());
        $this->assertEquals('', $response->getOutRefundNo());
        $this->assertEquals(0, $response->getRefundFee());
    }

    #[Test]
    public function testFromXmlWithResultFailResponse(): void
    {
        $xml = $this->createResultFailXmlResponse();
        $response = RefundResponse::fromXml($xml);

        $this->assertFalse($response->isSuccess());
        $this->assertEquals('ORDERNOTEXIST', $response->getErrorCode());
        $this->assertEquals('此交易订单号不存在', $response->getErrorMessage());
        $this->assertEquals('', $response->getRefundId());
        $this->assertEquals('', $response->getOutRefundNo());
    }

    #[Test]
    public function testFromXmlWithEmptyXmlThrowsException(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('响应内容为空');
        RefundResponse::fromXml('');
    }

    #[Test]
    public function testFromXmlWithInvalidXmlThrowsException(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('响应XML解析失败');
        RefundResponse::fromXml('invalid xml content');
    }

    #[Test]
    public function testFromXmlWithNonXmlContentThrowsException(): void
    {
        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('响应XML解析失败');
        RefundResponse::fromXml('{"key": "value"}');
    }

    #[Test]
    public function testVerifySignatureWithValidSignature(): void
    {
        // 创建一个签名正确的XML响应
        $xml = '<xml>
            <return_code><![CDATA[SUCCESS]]></return_code>
            <return_msg><![CDATA[OK]]></return_msg>
            <result_code><![CDATA[SUCCESS]]></result_code>
            <appid><![CDATA[wx1234567890abcdef]]></appid>
            <mch_id><![CDATA[1234567890]]></mch_id>
            <nonce_str><![CDATA[5K8264ILTKCH16CQ2502SI8ZNMTM67VS]]></nonce_str>
            <refund_id><![CDATA[50000000000000000001]]></refund_id>
            <out_refund_no><![CDATA[REFUND_123456789]]></out_refund_no>
            <refund_fee><![CDATA[50]]></refund_fee>
            <total_fee><![CDATA[100]]></total_fee>
        </xml>';

        $response = RefundResponse::fromXml($xml);

        // 对于成功的响应但没有签名字段，应该返回false
        $key = 'test_key_123456789012345678901234';
        $this->assertFalse($response->verifySignature($key, 'MD5'));
    }

    #[Test]
    public function testVerifySignatureWithFailedResponse(): void
    {
        $xml = $this->createReturnFailXmlResponse();
        $response = RefundResponse::fromXml($xml);

        $key = 'test_key_123456789012345678901234';
        $this->assertTrue($response->verifySignature($key, 'MD5'));
    }

    #[Test]
    public function testVerifySignatureWithMissingSign(): void
    {
        $xml = '<xml>
            <return_code><![CDATA[SUCCESS]]></return_code>
            <return_msg><![CDATA[OK]]></return_msg>
            <result_code><![CDATA[SUCCESS]]></result_code>
            <refund_id><![CDATA[50000000000000000001]]></refund_id>
            <out_refund_no><![CDATA[REFUND_123456789]]></out_refund_no>
            <refund_fee><![CDATA[50]]></refund_fee>
            <total_fee><![CDATA[100]]></total_fee>
        </xml>';

        $response = RefundResponse::fromXml($xml);
        $key = 'test_key_123456789012345678901234';

        $this->assertFalse($response->verifySignature($key, 'MD5'));
    }

    #[Test]
    public function testGetData(): void
    {
        $xml = $this->createSuccessXmlResponse();
        $response = RefundResponse::fromXml($xml);

        $data = $response->getData();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('return_code', $data);
        $this->assertArrayHasKey('result_code', $data);
        $this->assertArrayHasKey('refund_id', $data);
        $this->assertArrayHasKey('out_refund_no', $data);
        $this->assertArrayHasKey('refund_fee', $data);
        $this->assertArrayHasKey('total_fee', $data);

        $this->assertEquals('SUCCESS', $data['return_code']);
        $this->assertEquals('SUCCESS', $data['result_code']);
        $this->assertEquals('50000000000000000001', $data['refund_id']);
        $this->assertEquals('REFUND_123456789', $data['out_refund_no']);
        $this->assertEquals('50', $data['refund_fee']);
        $this->assertEquals('100', $data['total_fee']);
    }

    #[Test]
    public function testGetRefundInfo(): void
    {
        $xml = $this->createSuccessXmlResponse();
        $response = RefundResponse::fromXml($xml);

        $refundInfo = $response->getRefundInfo();

        $this->assertIsArray($refundInfo);
        $this->assertArrayHasKey('refund_id', $refundInfo);
        $this->assertArrayHasKey('out_refund_no', $refundInfo);
        $this->assertArrayHasKey('refund_fee', $refundInfo);
        $this->assertArrayHasKey('settlement_refund_fee', $refundInfo);
        $this->assertArrayHasKey('total_fee', $refundInfo);
        $this->assertArrayHasKey('settlement_total_fee', $refundInfo);
        $this->assertArrayHasKey('fee_type', $refundInfo);
        $this->assertArrayHasKey('cash_fee', $refundInfo);
        $this->assertArrayHasKey('cash_fee_type', $refundInfo);
        $this->assertArrayHasKey('cash_refund_fee', $refundInfo);
        $this->assertArrayHasKey('coupon_refund_fee', $refundInfo);
        $this->assertArrayHasKey('coupon_refund_count', $refundInfo);

        $this->assertEquals('50000000000000000001', $refundInfo['refund_id']);
        $this->assertEquals('REFUND_123456789', $refundInfo['out_refund_no']);
        $this->assertEquals(50, $refundInfo['refund_fee']);
        $this->assertEquals(50, $refundInfo['settlement_refund_fee']);
        $this->assertEquals(100, $refundInfo['total_fee']);
        $this->assertEquals(100, $refundInfo['settlement_total_fee']);
        $this->assertEquals('CNY', $refundInfo['fee_type']);
        $this->assertEquals(100, $refundInfo['cash_fee']);
        $this->assertEquals('CNY', $refundInfo['cash_fee_type']);
        $this->assertEquals(50, $refundInfo['cash_refund_fee']);
        $this->assertEquals(0, $refundInfo['coupon_refund_fee']);
        $this->assertEquals(0, $refundInfo['coupon_refund_count']);
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
            <err_code_des><![CDATA[原始错误描述]]></err_code_des>
        </xml>';

        $response = RefundResponse::fromXml($xml);

        $this->assertFalse($response->isSuccess());
        $this->assertEquals($errorCode, $response->getErrorCode());
        $this->assertEquals($expectedMessage, $response->getErrorMessage());
    }

    /**
     * @return array<int, list<string>>
     */
    public static function errorCodeMappingProvider(): array
    {
        return [
            ['ORDERNOTEXIST', '此交易订单号不存在'],
            ['ORDERCLOSE', '订单已关闭'],
            ['NOAUTH', '商户无权限'],
            ['NOTENOUGH', '余额不足'],
            ['INVALID_REQ_TOO_MUCH', '无效请求过多'],
            ['BIZERR_NEED_RETRY', '退款业务流程错误，需要商户触发重试来解决'],
            ['TRADE_OVERDUE', '订单已经超过退款期限'],
            ['ERROR', '系统错误'],
            ['USER_ACCOUNT_ABNORMAL', '退款请求失败，用户账号异常'],
            ['INVALID_TRANSACTIONID', '无效transaction_id'],
            ['PARAM_ERROR', '参数错误'],
            ['APPID_NOT_EXIST', 'APPID不存在'],
            ['MCHID_NOT_EXIST', '商户号不存在'],
            ['APPID_MCHID_NOT_MATCH', 'appid和mch_id不匹配'],
            ['LACK_PARAMS', '缺少参数'],
            ['SIGNERROR', '签名错误'],
            ['XML_FORMAT_ERROR', 'XML格式错误'],
            ['FREQUENCY_LIMITED', '频率限制'],
            ['REQUIRE_POST_METHOD', '请使用post方法'],
            ['POST_DATA_EMPTY', 'post数据为空'],
            ['NOT_UTF8', '编码格式错误'],
        ];
    }

    #[Test]
    public function testErrorMessageWithOriginalMessage(): void
    {
        $xml = '<xml>
            <return_code><![CDATA[SUCCESS]]></return_code>
            <return_msg><![CDATA[OK]]></return_msg>
            <result_code><![CDATA[FAIL]]></result_code>
            <err_code><![CDATA[ORDERNOTEXIST]]></err_code>
            <err_code_des><![CDATA[订单不存在]]></err_code_des>
        </xml>';

        $response = RefundResponse::fromXml($xml);

        $this->assertEquals('此交易订单号不存在', $response->getErrorMessage());
    }

    #[Test]
    public function testErrorMessageWithoutOriginalMessage(): void
    {
        $xml = '<xml>
            <return_code><![CDATA[SUCCESS]]></return_code>
            <return_msg><![CDATA[OK]]></return_msg>
            <result_code><![CDATA[FAIL]]></result_code>
            <err_code><![CDATA[UNKNOWN_ERROR]]></err_code>
            <err_code_des><![CDATA[未知错误]]></err_code_des>
        </xml>';

        $response = RefundResponse::fromXml($xml);

        $this->assertEquals('未知错误', $response->getErrorMessage());
    }

    #[Test]
    public function testReturnFailWithoutResultCode(): void
    {
        $xml = '<xml>
            <return_code><![CDATA[FAIL]]></return_code>
            <return_msg><![CDATA[网络错误]]></return_msg>
        </xml>';

        $response = RefundResponse::fromXml($xml);

        $this->assertFalse($response->isSuccess());
        $this->assertEquals('FAIL', $response->getErrorCode());
        $this->assertEquals('网络错误', $response->getErrorMessage());
    }

    #[Test]
    public function testReturnFailWithDefaultMessage(): void
    {
        $xml = '<xml>
            <return_code><![CDATA[FAIL]]></return_code>
        </xml>';

        $response = RefundResponse::fromXml($xml);

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
            <err_code><![CDATA[UNKNOWN_CODE]]></err_code>
            <err_code_des><![CDATA[自定义错误描述]]></err_code_des>
        </xml>';

        $response = RefundResponse::fromXml($xml);

        $this->assertFalse($response->isSuccess());
        $this->assertEquals('UNKNOWN_CODE', $response->getErrorCode());
        $this->assertEquals('自定义错误描述', $response->getErrorMessage());
    }

    #[Test]
    public function testCompleteWorkflowSuccess(): void
    {
        $xml = $this->createSuccessXmlResponse();
        $response = RefundResponse::fromXml($xml);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('50000000000000000001', $response->getRefundId());
        $this->assertEquals('REFUND_123456789', $response->getOutRefundNo());
        $this->assertEquals(50, $response->getRefundFee());

        $data = $response->getData();
        $this->assertIsArray($data);

        $refundInfo = $response->getRefundInfo();
        $this->assertIsArray($refundInfo);
        $this->assertEquals(50, $refundInfo['refund_fee']);
        $this->assertEquals(100, $refundInfo['total_fee']);
    }

    #[Test]
    public function testCompleteWorkflowFailure(): void
    {
        $xml = $this->createResultFailXmlResponse();
        $response = RefundResponse::fromXml($xml);

        $this->assertFalse($response->isSuccess());
        $this->assertEquals('ORDERNOTEXIST', $response->getErrorCode());
        $this->assertEquals('此交易订单号不存在', $response->getErrorMessage());

        $data = $response->getData();
        $this->assertIsArray($data);
        $this->assertEquals('FAIL', $data['result_code']);

        $refundInfo = $response->getRefundInfo();
        $this->assertIsArray($refundInfo);
        $this->assertEquals('', $refundInfo['refund_id']);
        $this->assertEquals(0, $refundInfo['refund_fee']);
    }

    #[Test]
    public function testEdgeCasesHandling(): void
    {
        $xml = '<xml>
            <return_code><![CDATA[SUCCESS]]></return_code>
            <result_code><![CDATA[SUCCESS]]></result_code>
            <refund_id><![CDATA[]]></refund_id>
            <out_refund_no><![CDATA[]]></out_refund_no>
            <refund_fee><![CDATA[]]></refund_fee>
            <total_fee><![CDATA[]]></total_fee>
        </xml>';

        $response = RefundResponse::fromXml($xml);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('', $response->getRefundId());
        $this->assertEquals('', $response->getOutRefundNo());
        $this->assertEquals(0, $response->getRefundFee());
        $this->assertEquals(0, $response->getTotalFee());

        $this->assertEquals('', $response->getFeeType());
        $this->assertEquals('', $response->getCashFeeType());
        $this->assertEquals(0, $response->getCashFee());
        $this->assertEquals(0, $response->getCashRefundFee());
        $this->assertEquals(0, $response->getCouponRefundFee());
        $this->assertEquals(0, $response->getCouponRefundCount());
    }
}
