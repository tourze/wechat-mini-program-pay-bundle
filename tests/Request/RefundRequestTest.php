<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Tests\Request;

use HttpClientBundle\Test\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WechatMiniProgramPayBundle\Request\RefundRequest;

/**
 * @internal
 */
#[CoversClass(RefundRequest::class)]
final class RefundRequestTest extends RequestTestCase
{
    private function createValidRequest(): RefundRequest
    {
        $request = new RefundRequest();
        $request->setAppId('wx1234567890abcdef');
        $request->setMchId('1234567890');
        $request->setNonceStr('5K8264ILTKCH16CQ2502SI8ZNMTM67VS');
        $request->setOutTradeNo('OUT_' . time());
        $request->setOutRefundNo('REFUND_' . time());
        $request->setTotalFee(100);
        $request->setRefundFee(50);
        $request->setSign('C380BEC2BFD727A4B6845133519F3AD6');

        return $request;
    }

    #[Test]
    public function testGetRequestPath(): void
    {
        $request = new RefundRequest();
        $this->assertEquals('/secapi/pay/refund', $request->getRequestPath());
    }

    #[Test]
    public function testGetBaseUri(): void
    {
        $request = new RefundRequest();
        $this->assertEquals('https://api.mch.weixin.qq.com', $request->getBaseUri());
    }

    #[Test]
    public function testGetSetAppId(): void
    {
        $request = new RefundRequest();
        $appId = 'wx1234567890abcdef';

        $request->setAppId($appId);
        $this->assertEquals($appId, $request->getAppId());
    }

    #[Test]
    public function testGetSetMchId(): void
    {
        $request = new RefundRequest();
        $mchId = '1234567890';

        $request->setMchId($mchId);
        $this->assertEquals($mchId, $request->getMchId());
    }

    #[Test]
    public function testGetSetNonceStr(): void
    {
        $request = new RefundRequest();
        $nonceStr = '5K8264ILTKCH16CQ2502SI8ZNMTM67VS';

        $request->setNonceStr($nonceStr);
        $this->assertEquals($nonceStr, $request->getNonceStr());
    }

    #[Test]
    public function testGetSetSign(): void
    {
        $request = new RefundRequest();
        $sign = 'C380BEC2BFD727A4B6845133519F3AD6';

        $request->setSign($sign);
        $this->assertEquals($sign, $request->getSign());
    }

    #[Test]
    public function testGetSetOutTradeNo(): void
    {
        $request = new RefundRequest();
        $outTradeNo = 'OUT_' . time();

        $request->setOutTradeNo($outTradeNo);
        $this->assertEquals($outTradeNo, $request->getOutTradeNo());
    }

    #[Test]
    public function testGetSetOutRefundNo(): void
    {
        $request = new RefundRequest();
        $outRefundNo = 'REFUND_' . time();

        $request->setOutRefundNo($outRefundNo);
        $this->assertEquals($outRefundNo, $request->getOutRefundNo());
    }

    #[Test]
    public function testGetSetTotalFee(): void
    {
        $request = new RefundRequest();
        $totalFee = 100;

        $request->setTotalFee($totalFee);
        $this->assertEquals($totalFee, $request->getTotalFee());
    }

    #[Test]
    public function testGetSetRefundFee(): void
    {
        $request = new RefundRequest();
        $refundFee = 50;

        $request->setRefundFee($refundFee);
        $this->assertEquals($refundFee, $request->getRefundFee());
    }

    #[Test]
    public function testGetSetTransactionId(): void
    {
        $request = new RefundRequest();
        $transactionId = '4200000000000000001';

        $request->setTransactionId($transactionId);
        $this->assertEquals($transactionId, $request->getTransactionId());

        $request->setTransactionId(null);
        $this->assertNull($request->getTransactionId());
    }

    #[Test]
    public function testGetSetRefundDesc(): void
    {
        $request = new RefundRequest();
        $refundDesc = '商品质量问题';

        $request->setRefundDesc($refundDesc);
        $this->assertEquals($refundDesc, $request->getRefundDesc());

        $request->setRefundDesc(null);
        $this->assertNull($request->getRefundDesc());
    }

    #[Test]
    public function testGetSetRefundAccount(): void
    {
        $request = new RefundRequest();
        $refundAccount = 'REFUND_SOURCE_UNSETTLED_FUNDS';

        $request->setRefundAccount($refundAccount);
        $this->assertEquals($refundAccount, $request->getRefundAccount());

        $request->setRefundAccount(null);
        $this->assertNull($request->getRefundAccount());
    }

    #[Test]
    public function testGetSetNotifyUrl(): void
    {
        $request = new RefundRequest();
        $notifyUrl = 'https://example.com/refund/notify';

        $request->setNotifyUrl($notifyUrl);
        $this->assertEquals($notifyUrl, $request->getNotifyUrl());

        $request->setNotifyUrl(null);
        $this->assertNull($request->getNotifyUrl());
    }

    #[Test]
    public function testGetSetDeviceInfo(): void
    {
        $request = new RefundRequest();
        $deviceInfo = 'WEB';

        $request->setDeviceInfo($deviceInfo);
        $this->assertEquals($deviceInfo, $request->getDeviceInfo());

        $request->setDeviceInfo(null);
        $this->assertNull($request->getDeviceInfo());
    }

    #[Test]
    public function testGetSetSignType(): void
    {
        $request = new RefundRequest();

        $this->assertEquals('MD5', $request->getSignType());

        $request->setSignType('HMAC-SHA256');
        $this->assertEquals('HMAC-SHA256', $request->getSignType());

        $request->setSignType(null);
        $this->assertNull($request->getSignType());
    }

    #[Test]
    public function testGetSetCertPath(): void
    {
        $request = new RefundRequest();
        $certPath = '/path/to/cert.pem';

        $request->setCertPath($certPath);
        $this->assertEquals($certPath, $request->getCertPath());

        $request->setCertPath(null);
        $this->assertNull($request->getCertPath());
    }

    #[Test]
    public function testGetSetKeyPath(): void
    {
        $request = new RefundRequest();
        $keyPath = '/path/to/key.pem';

        $request->setKeyPath($keyPath);
        $this->assertEquals($keyPath, $request->getKeyPath());

        $request->setKeyPath(null);
        $this->assertNull($request->getKeyPath());
    }

    #[Test]
    public function testValidateSuccess(): void
    {
        $request = $this->createValidRequest();

        $this->expectNotToPerformAssertions();
        $request->validate();
    }

    #[Test]
    #[DataProvider('requiredFieldsProvider')]
    public function testValidateRequiredFieldsThrowsException(string $field): void
    {
        $request = $this->createValidRequest();

        $reflection = new \ReflectionClass($request);
        $property = $reflection->getProperty($field);
        $property->setAccessible(true);

        if (in_array($field, ['totalFee', 'refundFee'], true)) {
            $property->setValue($request, 0);
        } else {
            $property->setValue($request, '');
        }

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage("必填参数 {$field} 不能为空");
        $request->validate();
    }

    /**
     * @return array<int, list<string>>
     */
    public static function requiredFieldsProvider(): array
    {
        return [
            ['appId'],
            ['mchId'],
            ['nonceStr'],
            ['outRefundNo'],
            ['totalFee'],
            ['refundFee'],
        ];
    }

    #[Test]
    public function testValidateTradeNumbersEmptyBothThrowsException(): void
    {
        $request = $this->createValidRequest();
        $request->setOutTradeNo('');
        $request->setTransactionId(null);

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('微信交易号和商户订单号不能同时为空');
        $request->validate();
    }

    #[Test]
    public function testValidateZeroTotalFeeThrowsException(): void
    {
        $request = $this->createValidRequest();
        $request->setTotalFee(0);

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('必填参数 totalFee 不能为空');
        $request->validate();
    }

    #[Test]
    public function testValidateNegativeTotalFeeThrowsException(): void
    {
        $request = $this->createValidRequest();
        $request->setTotalFee(-100);

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('订单总金额必须大于0');
        $request->validate();
    }

    #[Test]
    public function testValidateZeroRefundFeeThrowsException(): void
    {
        $request = $this->createValidRequest();
        $request->setRefundFee(0);

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('必填参数 refundFee 不能为空');
        $request->validate();
    }

    #[Test]
    public function testValidateNegativeRefundFeeThrowsException(): void
    {
        $request = $this->createValidRequest();
        $request->setRefundFee(-50);

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('退款金额必须大于0');
        $request->validate();
    }

    #[Test]
    public function testValidateRefundFeeExceedsTotalFeeThrowsException(): void
    {
        $request = $this->createValidRequest();
        $request->setTotalFee(100);
        $request->setRefundFee(150);

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('退款金额不能大于订单总金额');
        $request->validate();
    }

    #[Test]
    public function testValidateLongOutRefundNoThrowsException(): void
    {
        $request = $this->createValidRequest();
        $request->setOutRefundNo(str_repeat('a', 65));

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('商户退款单号长度不能超过64位');
        $request->validate();
    }

    #[Test]
    public function testValidateLongOutTradeNoThrowsException(): void
    {
        $request = $this->createValidRequest();
        $request->setOutTradeNo(str_repeat('a', 33));

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('商户订单号长度不能超过32位');
        $request->validate();
    }

    #[Test]
    public function testToArray(): void
    {
        $request = $this->createValidRequest();
        $request->setTransactionId('4200000000000000001');
        $request->setRefundDesc('商品质量问题');
        $request->setRefundAccount('REFUND_SOURCE_UNSETTLED_FUNDS');
        $request->setNotifyUrl('https://example.com/refund/notify');
        $request->setDeviceInfo('WEB');

        $array = $request->toArray();

        $this->assertEquals('wx1234567890abcdef', $array['appid']);
        $this->assertEquals('1234567890', $array['mch_id']);
        $this->assertEquals('5K8264ILTKCH16CQ2502SI8ZNMTM67VS', $array['nonce_str']);
        $this->assertEquals('4200000000000000001', $array['transaction_id']);
        $this->assertEquals(100, $array['total_fee']);
        $this->assertEquals(50, $array['refund_fee']);
        $this->assertEquals('商品质量问题', $array['refund_desc']);
        $this->assertEquals('REFUND_SOURCE_UNSETTLED_FUNDS', $array['refund_account']);
        $this->assertEquals('https://example.com/refund/notify', $array['notify_url']);
        $this->assertEquals('WEB', $array['device_info']);
        $this->assertEquals('MD5', $array['sign_type']);

        $this->assertArrayNotHasKey('cert_path', $array);
        $this->assertArrayNotHasKey('key_path', $array);
    }

    #[Test]
    public function testToXml(): void
    {
        $request = $this->createValidRequest();
        $request->setRefundDesc('商品质量问题');
        $request->setDeviceInfo('WEB');

        $xml = $request->toXml();

        $this->assertStringStartsWith('<xml>', $xml);
        $this->assertStringEndsWith('</xml>', $xml);
        $this->assertStringContainsString('<appid><![CDATA[wx1234567890abcdef]]></appid>', $xml);
        $this->assertStringContainsString('<mch_id><![CDATA[1234567890]]></mch_id>', $xml);
        $this->assertStringContainsString('<nonce_str><![CDATA[5K8264ILTKCH16CQ2502SI8ZNMTM67VS]]></nonce_str>', $xml);
        $this->assertStringContainsString('<total_fee><![CDATA[100]]></total_fee>', $xml);
        $this->assertStringContainsString('<refund_fee><![CDATA[50]]></refund_fee>', $xml);
        $this->assertStringContainsString('<refund_desc><![CDATA[商品质量问题]]></refund_desc>', $xml);
        $this->assertStringContainsString('<device_info><![CDATA[WEB]]></device_info>', $xml);
        $this->assertStringContainsString('<sign><![CDATA[C380BEC2BFD727A4B6845133519F3AD6]]></sign>', $xml);
    }

    #[Test]
    public function testGetRequestOptions(): void
    {
        $request = $this->createValidRequest();

        $options = $request->getRequestOptions();

        $this->assertIsArray($options);
        $this->assertArrayHasKey('headers', $options);
        $this->assertArrayHasKey('body', $options);

        $headers = $options['headers'];
        $this->assertIsArray($headers);
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayHasKey('User-Agent', $headers);
        $this->assertEquals('application/xml; charset=utf-8', $headers['Content-Type']);
        $this->assertEquals('WechatMiniProgramPayBundle/1.0', $headers['User-Agent']);

        $this->assertIsString($options['body']);
        $this->assertStringStartsWith('<xml>', $options['body']);
        $this->assertStringEndsWith('</xml>', $options['body']);
    }

    #[Test]
    public function testGetRequestOptionsWithCertificates(): void
    {
        $request = $this->createValidRequest();
        $request->setCertPath('/path/to/cert.pem');
        $request->setKeyPath('/path/to/key.pem');

        $options = $request->getRequestOptions();
        $this->assertNotNull($options);

        $this->assertArrayHasKey('local_cert', $options);
        $this->assertArrayHasKey('local_pk', $options);
        $this->assertEquals('/path/to/cert.pem', $options['local_cert']);
        $this->assertEquals('/path/to/key.pem', $options['local_pk']);
    }

    #[Test]
    public function testGetRequestOptionsWithInvalidDataThrowsException(): void
    {
        $request = new RefundRequest();

        $this->expectException(PaymentConfigurationException::class);
        $request->getRequestOptions();
    }

    #[Test]
    public function testGenerateNonceStr(): void
    {
        $nonceStr = RefundRequest::generateNonceStr();

        $this->assertEquals(32, strlen($nonceStr));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $nonceStr);
    }

    #[Test]
    public function testGenerateNonceStrWithCustomLength(): void
    {
        $length = 16;
        $nonceStr = RefundRequest::generateNonceStr($length);

        $this->assertEquals($length, strlen($nonceStr));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $nonceStr);
    }

    #[Test]
    public function testGenerateNonceStrIsUnique(): void
    {
        $nonceStr1 = RefundRequest::generateNonceStr();
        $nonceStr2 = RefundRequest::generateNonceStr();

        $this->assertNotEquals($nonceStr1, $nonceStr2);
    }

    #[Test]
    public function testGenerateRefundNo(): void
    {
        $refundNo = RefundRequest::generateRefundNo();

        $this->assertStringStartsWith('REFUND', $refundNo);
        $this->assertEquals(26, strlen($refundNo));
        $this->assertMatchesRegularExpression('/^REFUND\d{20}$/', $refundNo);
    }

    #[Test]
    public function testGenerateRefundNoIsUnique(): void
    {
        $refundNo1 = RefundRequest::generateRefundNo();
        usleep(1000);
        $refundNo2 = RefundRequest::generateRefundNo();

        $this->assertNotEquals($refundNo1, $refundNo2);
    }

    #[Test]
    public function testCompleteWorkflow(): void
    {
        $request = new RefundRequest();

        $request->setAppId('wx1234567890abcdef');
        $request->setMchId('1234567890');
        $request->setNonceStr(RefundRequest::generateNonceStr());
        $request->setOutTradeNo('OUT_' . time());
        $request->setOutRefundNo(RefundRequest::generateRefundNo());
        $request->setTotalFee(100);
        $request->setRefundFee(50);
        $request->setSign('C380BEC2BFD727A4B6845133519F3AD6');
        $request->setRefundDesc('商品质量问题');
        $request->setNotifyUrl('https://example.com/refund/notify');
        $request->setCertPath('/path/to/cert.pem');
        $request->setKeyPath('/path/to/key.pem');

        $request->validate();

        $options = $request->getRequestOptions();
        $this->assertIsArray($options);

        $array = $request->toArray();
        $this->assertIsArray($array);
        $this->assertArrayHasKey('appid', $array);

        $xml = $request->toXml();
        $this->assertIsString($xml);
        $this->assertStringStartsWith('<xml>', $xml);
    }

    #[Test]
    public function testEdgeCases(): void
    {
        $request = new RefundRequest();

        $request->setTotalFee(1);
        $this->assertEquals(1, $request->getTotalFee());

        $request->setRefundFee(1);
        $this->assertEquals(1, $request->getRefundFee());

        $request->setOutRefundNo(str_repeat('x', 64));
        $this->assertEquals(64, strlen($request->getOutRefundNo()));

        $request->setOutTradeNo(str_repeat('x', 32));
        $this->assertEquals(32, strlen($request->getOutTradeNo()));

        $request->setRefundDesc('退款原因-特殊字符@#$%^&*()');
        $refundDesc = $request->getRefundDesc();
        $this->assertNotNull($refundDesc);
        $this->assertStringContainsString('特殊字符', $refundDesc);

        $request->setNotifyUrl('https://sub.domain.com:8080/path/to/refund?param=value');
        $notifyUrl = $request->getNotifyUrl();
        $this->assertNotNull($notifyUrl);
        $this->assertStringContainsString('https://', $notifyUrl);
    }
}
