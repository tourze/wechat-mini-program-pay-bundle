<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Tests\Request;

use HttpClientBundle\Test\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tourze\PaymentContracts\Enum\PaymentType;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WechatMiniProgramPayBundle\Request\UnifiedOrderRequest;

/**
 * @internal
 */
#[CoversClass(UnifiedOrderRequest::class)]
final class UnifiedOrderRequestTest extends RequestTestCase
{
    private function createValidRequest(): UnifiedOrderRequest
    {
        $request = new UnifiedOrderRequest();
        $request->setAppId('wx1234567890abcdef');
        $request->setMchId('1234567890');
        $request->setNonceStr('5K8264ILTKCH16CQ2502SI8ZNMTM67VS');
        $request->setBody('测试商品');
        $request->setOutTradeNo('OUT_' . time());
        $request->setTotalFee(100);
        $request->setSpbillCreateIp('127.0.0.1');
        $request->setNotifyUrl('https://example.com/notify');
        $request->setTradeType('JSAPI');
        $request->setOpenId('openid123');

        return $request;
    }

    #[Test]
    public function testGetRequestPath(): void
    {
        $request = new UnifiedOrderRequest();
        $this->assertEquals('/pay/unifiedorder', $request->getRequestPath());
    }

    #[Test]
    public function testGetBaseUri(): void
    {
        $request = new UnifiedOrderRequest();
        $this->assertEquals('https://api.mch.weixin.qq.com', $request->getBaseUri());
    }

    #[Test]
    public function testGetSetAppId(): void
    {
        $request = new UnifiedOrderRequest();
        $appId = 'wx1234567890abcdef';

        $request->setAppId($appId);
        $this->assertEquals($appId, $request->getAppId());
    }

    #[Test]
    public function testGetSetMchId(): void
    {
        $request = new UnifiedOrderRequest();
        $mchId = '1234567890';

        $request->setMchId($mchId);
        $this->assertEquals($mchId, $request->getMchId());
    }

    #[Test]
    public function testGetSetNonceStr(): void
    {
        $request = new UnifiedOrderRequest();
        $nonceStr = '5K8264ILTKCH16CQ2502SI8ZNMTM67VS';

        $request->setNonceStr($nonceStr);
        $this->assertEquals($nonceStr, $request->getNonceStr());
    }

    #[Test]
    public function testGetSetSign(): void
    {
        $request = new UnifiedOrderRequest();
        $sign = 'C380BEC2BFD727A4B6845133519F3AD6';

        $request->setSign($sign);
        $this->assertEquals($sign, $request->getSign());
    }

    #[Test]
    public function testGetSetBody(): void
    {
        $request = new UnifiedOrderRequest();
        $body = '测试商品';

        $request->setBody($body);
        $this->assertEquals($body, $request->getBody());
    }

    #[Test]
    public function testGetSetOutTradeNo(): void
    {
        $request = new UnifiedOrderRequest();
        $outTradeNo = 'OUT_' . time();

        $request->setOutTradeNo($outTradeNo);
        $this->assertEquals($outTradeNo, $request->getOutTradeNo());
    }

    #[Test]
    public function testGetSetTotalFee(): void
    {
        $request = new UnifiedOrderRequest();
        $totalFee = 100;

        $request->setTotalFee($totalFee);
        $this->assertEquals($totalFee, $request->getTotalFee());
    }

    #[Test]
    public function testGetSetSpbillCreateIp(): void
    {
        $request = new UnifiedOrderRequest();
        $ip = '127.0.0.1';

        $request->setSpbillCreateIp($ip);
        $this->assertEquals($ip, $request->getSpbillCreateIp());
    }

    #[Test]
    public function testGetSetNotifyUrl(): void
    {
        $request = new UnifiedOrderRequest();
        $notifyUrl = 'https://example.com/notify';

        $request->setNotifyUrl($notifyUrl);
        $this->assertEquals($notifyUrl, $request->getNotifyUrl());
    }

    #[Test]
    public function testGetSetTradeType(): void
    {
        $request = new UnifiedOrderRequest();
        $tradeType = 'JSAPI';

        $request->setTradeType($tradeType);
        $this->assertEquals($tradeType, $request->getTradeType());
    }

    #[Test]
    public function testGetSetOpenId(): void
    {
        $request = new UnifiedOrderRequest();
        $openId = 'openid123';

        $request->setOpenId($openId);
        $this->assertEquals($openId, $request->getOpenId());

        // 测试设置为null
        $request->setOpenId(null);
        $this->assertNull($request->getOpenId());
    }

    #[Test]
    public function testGetSetProductId(): void
    {
        $request = new UnifiedOrderRequest();
        $productId = 'product123';

        $request->setProductId($productId);
        $this->assertEquals($productId, $request->getProductId());

        // 测试设置为null
        $request->setProductId(null);
        $this->assertNull($request->getProductId());
    }

    #[Test]
    public function testGetSetSceneInfo(): void
    {
        $request = new UnifiedOrderRequest();
        $sceneInfo = ['h5_info' => ['type' => 'Wap', 'wap_url' => 'https://example.com', 'wap_name' => '商家名称']];

        $request->setSceneInfo($sceneInfo);
        $this->assertEquals($sceneInfo, $request->getSceneInfo());

        // 测试设置为null
        $request->setSceneInfo(null);
        $this->assertNull($request->getSceneInfo());
    }

    #[Test]
    public function testOptionalFields(): void
    {
        $request = new UnifiedOrderRequest();

        // 测试所有可选字段
        $this->assertNull($request->getDeviceInfo());
        $this->assertEquals('MD5', $request->getSignType());
        $this->assertNull($request->getDetail());
        $this->assertNull($request->getAttach());
        $this->assertEquals('CNY', $request->getFeeType());
        $this->assertNull($request->getTimeStart());
        $this->assertNull($request->getTimeExpire());
        $this->assertNull($request->getGoodsTag());
        $this->assertNull($request->getLimitPay());
        $this->assertNull($request->getReceipt());
        $this->assertNull($request->getProfitSharing());

        // 测试设置可选字段
        $request->setDeviceInfo('WEB');
        $request->setSignType('HMAC-SHA256');
        $request->setDetail('商品详情');
        $request->setAttach('附加数据');
        $request->setFeeType('USD');
        $request->setGoodsTag('WXG');
        $request->setLimitPay('no_credit');
        $request->setReceipt('Y');
        $request->setProfitSharing(true);

        $this->assertEquals('WEB', $request->getDeviceInfo());
        $this->assertEquals('HMAC-SHA256', $request->getSignType());
        $this->assertEquals('商品详情', $request->getDetail());
        $this->assertEquals('附加数据', $request->getAttach());
        $this->assertEquals('USD', $request->getFeeType());
        $this->assertEquals('WXG', $request->getGoodsTag());
        $this->assertEquals('no_credit', $request->getLimitPay());
        $this->assertEquals('Y', $request->getReceipt());
        $this->assertTrue($request->getProfitSharing());
    }

    #[Test]
    public function testTimeFields(): void
    {
        $request = new UnifiedOrderRequest();
        $timeStart = new \DateTimeImmutable('2023-01-01 10:00:00');
        $timeExpire = new \DateTimeImmutable('2023-01-01 12:00:00');

        $request->setTimeStart($timeStart);
        $request->setTimeExpire($timeExpire);

        $this->assertSame($timeStart, $request->getTimeStart());
        $this->assertSame($timeExpire, $request->getTimeExpire());
    }

    #[Test]
    public function testValidateSuccess(): void
    {
        $request = $this->createValidRequest();

        // 验证应该成功，不抛出异常
        $this->expectNotToPerformAssertions();
        $request->validate();
    }

    #[Test]
    #[DataProvider('requiredFieldsProvider')]
    public function testValidateRequiredFieldsThrowsException(string $field): void
    {
        $request = $this->createValidRequest();

        // 使用反射设置字段为空值
        $reflection = new \ReflectionClass($request);
        $property = $reflection->getProperty($field);
        $property->setAccessible(true);

        if ('totalFee' === $field) {
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
            ['body'],
            ['outTradeNo'],
            ['totalFee'],
            ['spbillCreateIp'],
            ['notifyUrl'],
            ['tradeType'],
        ];
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
        $this->expectExceptionMessage('订单金额必须大于0');
        $request->validate();
    }

    #[Test]
    public function testValidateLongOutTradeNoThrowsException(): void
    {
        $request = $this->createValidRequest();
        $request->setOutTradeNo(str_repeat('a', 33)); // 超过32位

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('商户订单号长度不能超过32位');
        $request->validate();
    }

    #[Test]
    public function testValidateJSAPIWithoutOpenIdThrowsException(): void
    {
        $request = $this->createValidRequest();
        $request->setTradeType('JSAPI');
        $request->setOpenId(null);

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('JSAPI支付必须提供openId');
        $request->validate();
    }

    #[Test]
    public function testValidateNATIVEWithoutProductIdThrowsException(): void
    {
        $request = $this->createValidRequest();
        $request->setTradeType('NATIVE');
        $request->setProductId(null);

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('NATIVE支付必须提供productId');
        $request->validate();
    }

    #[Test]
    public function testValidateH5WithoutSceneInfoThrowsException(): void
    {
        $request = $this->createValidRequest();
        $request->setTradeType('H5');
        $request->setSceneInfo(null);

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('H5支付必须提供sceneInfo场景信息');
        $request->validate();
    }

    #[Test]
    public function testToArray(): void
    {
        $request = $this->createValidRequest();
        $request->setDetail('商品详情');
        $request->setAttach('附加数据');
        $request->setProfitSharing(true);

        $array = $request->toArray();

        $this->assertEquals('wx1234567890abcdef', $array['appid']);
        $this->assertEquals('1234567890', $array['mch_id']);
        $this->assertEquals('5K8264ILTKCH16CQ2502SI8ZNMTM67VS', $array['nonce_str']);
        $this->assertEquals('测试商品', $array['body']);
        $this->assertEquals(100, $array['total_fee']);
        $this->assertEquals('127.0.0.1', $array['spbill_create_ip']);
        $this->assertEquals('https://example.com/notify', $array['notify_url']);
        $this->assertEquals('JSAPI', $array['trade_type']);
        $this->assertEquals('openid123', $array['openid']);
        $this->assertEquals('商品详情', $array['detail']);
        $this->assertEquals('附加数据', $array['attach']);
        $this->assertEquals('Y', $array['profit_sharing']);

        // 确保空值被过滤
        $this->assertArrayNotHasKey('device_info', $array);
        $this->assertArrayNotHasKey('time_start', $array);
        $this->assertArrayNotHasKey('time_expire', $array);
    }

    #[Test]
    public function testToArrayWithDateTime(): void
    {
        $request = $this->createValidRequest();
        $timeStart = new \DateTimeImmutable('2023-01-01 10:00:00');
        $timeExpire = new \DateTimeImmutable('2023-01-01 12:00:00');

        $request->setTimeStart($timeStart);
        $request->setTimeExpire($timeExpire);

        $array = $request->toArray();

        $this->assertEquals('20230101100000', $array['time_start']);
        $this->assertEquals('20230101120000', $array['time_expire']);
    }

    #[Test]
    public function testToArrayWithSceneInfo(): void
    {
        $request = $this->createValidRequest();
        $sceneInfo = ['h5_info' => ['type' => 'Wap', 'wap_url' => 'https://example.com']];
        $request->setSceneInfo($sceneInfo);

        $array = $request->toArray();

        $expectedJson = json_encode($sceneInfo, JSON_UNESCAPED_UNICODE);
        $this->assertEquals($expectedJson, $array['scene_info']);
    }

    #[Test]
    public function testToArrayWithProfitSharingFalse(): void
    {
        $request = $this->createValidRequest();
        $request->setProfitSharing(false);

        $array = $request->toArray();

        $this->assertEquals('N', $array['profit_sharing']);
    }

    #[Test]
    public function testToXml(): void
    {
        $request = $this->createValidRequest();
        $request->setDetail('商品详情');
        $request->setAttach('附加数据');

        $xml = $request->toXml();

        $this->assertStringStartsWith('<xml>', $xml);
        $this->assertStringEndsWith('</xml>', $xml);
        $this->assertStringContainsString('<appid><![CDATA[wx1234567890abcdef]]></appid>', $xml);
        $this->assertStringContainsString('<mch_id><![CDATA[1234567890]]></mch_id>', $xml);
        $this->assertStringContainsString('<body><![CDATA[测试商品]]></body>', $xml);
        $this->assertStringContainsString('<total_fee><![CDATA[100]]></total_fee>', $xml);
        $this->assertStringContainsString('<trade_type><![CDATA[JSAPI]]></trade_type>', $xml);
        $this->assertStringContainsString('<openid><![CDATA[openid123]]></openid>', $xml);
        $this->assertStringContainsString('<detail><![CDATA[商品详情]]></detail>', $xml);
        $this->assertStringContainsString('<attach><![CDATA[附加数据]]></attach>', $xml);
    }

    #[Test]
    public function testToXmlWithBooleanValue(): void
    {
        $request = $this->createValidRequest();
        $request->setProfitSharing(true);

        $xml = $request->toXml();

        $this->assertStringContainsString('<profit_sharing><![CDATA[Y]]></profit_sharing>', $xml);
    }

    #[Test]
    public function testToXmlWithArrayValue(): void
    {
        $request = $this->createValidRequest();
        $sceneInfo = ['h5_info' => ['type' => 'Wap']];
        $request->setSceneInfo($sceneInfo);

        $xml = $request->toXml();

        $expectedJson = json_encode($sceneInfo, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString("<scene_info><![CDATA[{$expectedJson}]]></scene_info>", $xml);
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
    public function testGetRequestOptionsWithInvalidDataThrowsException(): void
    {
        $request = new UnifiedOrderRequest(); // 未设置必填字段

        $this->expectException(PaymentConfigurationException::class);
        $request->getRequestOptions();
    }

    #[Test]
    public function testGenerateNonceStr(): void
    {
        $nonceStr = UnifiedOrderRequest::generateNonceStr();

        $this->assertEquals(32, strlen($nonceStr));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $nonceStr);
    }

    #[Test]
    public function testGenerateNonceStrWithCustomLength(): void
    {
        $length = 16;
        $nonceStr = UnifiedOrderRequest::generateNonceStr($length);

        $this->assertEquals($length, strlen($nonceStr));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $nonceStr);
    }

    #[Test]
    public function testGenerateNonceStrIsUnique(): void
    {
        $nonceStr1 = UnifiedOrderRequest::generateNonceStr();
        $nonceStr2 = UnifiedOrderRequest::generateNonceStr();

        $this->assertNotEquals($nonceStr1, $nonceStr2);
    }

    #[Test]
    #[DataProvider('paymentTypeProvider')]
    public function testSetTradeTypeFromPaymentType(PaymentType $paymentType, string $expectedTradeType): void
    {
        $request = new UnifiedOrderRequest();

        $request->setTradeTypeFromPaymentType($paymentType);

        $this->assertEquals($expectedTradeType, $request->getTradeType());
    }

    /**
     * @return array<int, array{PaymentType, string}>
     */
    public static function paymentTypeProvider(): array
    {
        return [
            [PaymentType::WECHAT_MINI_PROGRAM, 'JSAPI'],
            [PaymentType::WECHAT_OFFICIAL_ACCOUNT, 'JSAPI'],
            [PaymentType::WECHAT_JSAPI, 'JSAPI'],
            [PaymentType::WECHAT_APP, 'APP'],
            [PaymentType::LEGACY_WECHAT_PAY, 'JSAPI'],
        ];
    }

    #[Test]
    public function testSetH5SceneInfo(): void
    {
        $request = new UnifiedOrderRequest();
        $sceneInfo = ['h5_info' => ['type' => 'Wap', 'wap_url' => 'https://example.com', 'wap_name' => '商家名称']];

        $request->setH5SceneInfo($sceneInfo);
        $this->assertEquals('H5', $request->getTradeType());
        $this->assertEquals($sceneInfo, $request->getSceneInfo());
    }

    #[Test]
    public function testSetNativeProductId(): void
    {
        $request = new UnifiedOrderRequest();
        $productId = 'product123';

        $request->setNativeProductId($productId);
        $this->assertEquals('NATIVE', $request->getTradeType());
        $this->assertEquals($productId, $request->getProductId());
    }

    #[Test]
    public function testCompleteWorkflow(): void
    {
        $request = new UnifiedOrderRequest();

        // 设置基本必填参数
        $request->setAppId('wx1234567890abcdef');
        $request->setMchId('1234567890');
        $request->setNonceStr(UnifiedOrderRequest::generateNonceStr());
        $request->setBody('测试商品');
        $request->setOutTradeNo('OUT_' . time());
        $request->setTotalFee(100);
        $request->setSpbillCreateIp('192.168.1.1');
        $request->setNotifyUrl('https://example.com/notify');
        $request->setTradeTypeFromPaymentType(PaymentType::WECHAT_MINI_PROGRAM);
        $request->setOpenId('openid123');

        // 设置可选参数
        $request->setDetail('商品详细描述');
        $request->setAttach('订单附加信息');
        $request->setFeeType('CNY');
        $request->setProfitSharing(false);

        // 验证请求
        $request->validate(); // 不应该抛出异常

        // 生成请求选项
        $options = $request->getRequestOptions();
        $this->assertIsArray($options);

        // 转换为数组
        $array = $request->toArray();
        $this->assertIsArray($array);
        $this->assertArrayHasKey('appid', $array);

        // 转换为XML
        $xml = $request->toXml();
        $this->assertIsString($xml);
        $this->assertStringStartsWith('<xml>', $xml);
    }

    #[Test]
    public function testEdgeCases(): void
    {
        $request = new UnifiedOrderRequest();

        // 测试边界值
        $request->setTotalFee(1); // 最小金额
        $this->assertEquals(1, $request->getTotalFee());

        $request->setOutTradeNo(str_repeat('x', 32)); // 最大长度
        $this->assertEquals(32, strlen($request->getOutTradeNo()));

        // 测试特殊字符
        $request->setBody('商品名称-特殊字符@#$%^&*()');
        $this->assertStringContainsString('特殊字符', $request->getBody());

        // 测试URL格式
        $request->setNotifyUrl('https://sub.domain.com:8080/path/to/notify?param=value');
        $this->assertStringContainsString('https://', $request->getNotifyUrl());
    }
}
