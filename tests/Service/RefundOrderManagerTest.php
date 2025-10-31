<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WechatMiniProgramPayBundle\Response\RefundResponse;
use WechatMiniProgramPayBundle\Service\RefundOrderManager;
use WechatPayBundle\Entity\Merchant;
use WechatPayBundle\Entity\PayOrder;
use WechatPayBundle\Entity\RefundOrder;
use WechatPayBundle\Enum\PayOrderStatus;

/**
 * RefundOrderManager 测试.
 *
 * @internal
 */
#[CoversClass(RefundOrderManager::class)]
#[RunTestsInSeparateProcesses]
final class RefundOrderManagerTest extends AbstractIntegrationTestCase
{
    private RefundOrderManager $refundOrderManager;

    protected function onSetUp(): void
    {
        $this->refundOrderManager = self::getService(RefundOrderManager::class);
    }

    #[Test]
    public function testCreateRefundOrderWithSuccessResponse(): void
    {
        $payOrder = $this->createPayOrder();
        $response = $this->createSuccessRefundResponse();
        $params = $this->createValidRefundParams();

        $refundOrder = $this->refundOrderManager->create($payOrder, $response, $params);

        $this->assertInstanceOf(RefundOrder::class, $refundOrder);
        $this->assertEquals($payOrder, $refundOrder->getPayOrder());
        $this->assertEquals($payOrder->getAppId(), $refundOrder->getAppId());
        $this->assertEquals($params['refundFee'], $refundOrder->getMoney());
        $this->assertEquals($params['refundDesc'], $refundOrder->getReason());
        $this->assertEquals($params['notifyUrl'], $refundOrder->getNotifyUrl());
        $this->assertEquals($response->getRefundId(), $refundOrder->getRefundId());
        $this->assertEquals('SUCCESS', $refundOrder->getStatus());
        $this->assertNotNull($refundOrder->getRequestJson());
        $this->assertNotNull($refundOrder->getResponseJson());
    }

    #[Test]
    public function testCreateRefundOrderWithFailedResponse(): void
    {
        $payOrder = $this->createPayOrder();
        $response = $this->createFailedRefundResponse();
        $params = $this->createValidRefundParams();

        $refundOrder = $this->refundOrderManager->create($payOrder, $response, $params);

        $this->assertInstanceOf(RefundOrder::class, $refundOrder);
        $this->assertEquals('FAILED', $refundOrder->getStatus());
        $this->assertNull($refundOrder->getRefundId());
    }

    #[Test]
    public function testCreateRefundOrderWithMissingRefundFee(): void
    {
        $payOrder = $this->createPayOrder();
        $response = $this->createSuccessRefundResponse();
        $params = $this->createValidRefundParams();
        unset($params['refundFee']); // 移除必需参数

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('退款金额参数缺失');

        $this->refundOrderManager->create($payOrder, $response, $params);
    }

    #[Test]
    public function testCreateRefundOrderWithMissingRequiredParams(): void
    {
        $payOrder = $this->createPayOrder();
        $response = $this->createSuccessRefundResponse();
        $params = [
            'refundFee' => 1000,
            // 缺少 outRefundNo, totalFee
        ];

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('请求数据参数缺失');

        $this->refundOrderManager->create($payOrder, $response, $params);
    }

    #[Test]
    public function testCreateRefundOrderWithEmptyOptionalParams(): void
    {
        $payOrder = $this->createPayOrder();
        $response = $this->createSuccessRefundResponse();
        $params = [
            'outRefundNo' => 'REFUND123456',
            'totalFee' => 2000,
            'refundFee' => 1000,
            'refundDesc' => '',  // 空字符串应该被忽略
            'notifyUrl' => '',   // 空字符串应该被忽略
        ];

        $refundOrder = $this->refundOrderManager->create($payOrder, $response, $params);

        $this->assertInstanceOf(RefundOrder::class, $refundOrder);
        $this->assertNull($refundOrder->getReason());
        // RefundOrderListener会自动生成notifyUrl，所以不应该为null
        $this->assertNotNull($refundOrder->getNotifyUrl());
        $this->assertStringContainsString('wechat-payment/mini-program/refund', $refundOrder->getNotifyUrl());
    }

    #[Test]
    public function testCreateRefundOrderWithValidPayOrder(): void
    {
        // 测试正常流程：创建一个有效的PayOrder
        $payOrder = $this->createPayOrder();
        $response = $this->createSuccessRefundResponse();
        $params = $this->createValidRefundParams();

        $refundOrder = $this->refundOrderManager->create($payOrder, $response, $params);

        $this->assertInstanceOf(RefundOrder::class, $refundOrder);
        $this->assertEquals($payOrder->getAppId(), $refundOrder->getAppId());
        $this->assertEquals($payOrder, $refundOrder->getPayOrder());
        $this->assertEquals($params['refundFee'], $refundOrder->getMoney());
        $this->assertEquals($params['refundDesc'], $refundOrder->getReason());
        $this->assertNotNull($refundOrder->getNotifyUrl()); // RefundOrderListener会生成
    }

    /**
     * @param array<string, mixed> $params
     */
    #[Test]
    #[DataProvider('provideRefundParamsTypes')]
    public function testCreateRefundOrderWithDifferentParamTypes(array $params, int $expectedMoney): void
    {
        $payOrder = $this->createPayOrder();
        $response = $this->createSuccessRefundResponse();

        $refundOrder = $this->refundOrderManager->create($payOrder, $response, $params);

        $this->assertEquals($expectedMoney, $refundOrder->getMoney());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, int}>
     */
    public static function provideRefundParamsTypes(): iterable
    {
        yield 'integer refundFee' => [
            [
                'outRefundNo' => 'REFUND123',
                'totalFee' => 2000,
                'refundFee' => 1000,
            ],
            1000,
        ];

        yield 'string refundFee' => [
            [
                'outRefundNo' => 'REFUND123',
                'totalFee' => 2000,
                'refundFee' => '1500',
            ],
            1500,
        ];

        yield 'float refundFee converted to int' => [
            [
                'outRefundNo' => 'REFUND123',
                'totalFee' => 2000,
                'refundFee' => 999.99, // 转换为 int
            ],
            999,
        ];
    }

    #[Test]
    public function testRequestJsonContainsAllRequiredFields(): void
    {
        $payOrder = $this->createPayOrder();
        $response = $this->createSuccessRefundResponse();
        $params = [
            'outRefundNo' => 'REFUND123456',
            'totalFee' => 2000,
            'refundFee' => 1000,
            'outTradeNo' => 'ORDER123456',
            'transactionId' => 'WX123456',
            'refundDesc' => '用户申请退款',
            'refundAccount' => 'REFUND_SOURCE_RECHARGE_FUNDS',
            'notifyUrl' => 'https://example.com/notify',
        ];

        $refundOrder = $this->refundOrderManager->create($payOrder, $response, $params);

        $requestData = json_decode($refundOrder->getRequestJson() ?? '', true);
        $this->assertIsArray($requestData);

        // 验证所有字段都存在
        $expectedFields = [
            'out_trade_no',
            'transaction_id',
            'out_refund_no',
            'total_fee',
            'refund_fee',
            'refund_desc',
            'refund_account',
            'notify_url',
        ];

        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $requestData);
        }

        $this->assertEquals('ORDER123456', $requestData['out_trade_no']);
        $this->assertEquals('WX123456', $requestData['transaction_id']);
        $this->assertEquals('REFUND123456', $requestData['out_refund_no']);
        $this->assertEquals(2000, $requestData['total_fee']);
        $this->assertEquals(1000, $requestData['refund_fee']);
        $this->assertEquals('用户申请退款', $requestData['refund_desc']);
        $this->assertEquals('REFUND_SOURCE_RECHARGE_FUNDS', $requestData['refund_account']);
        $this->assertEquals('https://example.com/notify', $requestData['notify_url']);
    }

    #[Test]
    public function testSuccessResponseJsonEncoding(): void
    {
        $payOrder = $this->createPayOrder();
        $response = $this->createSuccessRefundResponse();
        $params = $this->createValidRefundParams();

        $refundOrder = $this->refundOrderManager->create($payOrder, $response, $params);

        $responseJson = $refundOrder->getResponseJson();
        $this->assertNotNull($responseJson);

        $responseData = json_decode($responseJson, true);
        $this->assertIsArray($responseData);

        // 验证成功响应包含预期数据
        $this->assertEquals($response->getData(), $responseData);
    }

    #[Test]
    public function testFailedResponseJsonEncoding(): void
    {
        $payOrder = $this->createPayOrder();
        $response = $this->createFailedRefundResponse();
        $params = $this->createValidRefundParams();

        $refundOrder = $this->refundOrderManager->create($payOrder, $response, $params);

        $responseJson = $refundOrder->getResponseJson();
        $this->assertNotNull($responseJson);

        $responseData = json_decode($responseJson, true);
        $this->assertIsArray($responseData);

        $this->assertEquals($response->getErrorCode(), $responseData['error_code']);
        $this->assertEquals($response->getErrorMessage(), $responseData['error_message']);
    }

    private function createPayOrder(): PayOrder
    {
        $merchant = new Merchant();
        $merchant->setMchId('test_merchant');
        $merchant->setApiKey('test_key');
        $merchant->setCertSerial('test_serial');
        $this->persistAndFlush($merchant);

        $payOrder = new PayOrder();
        $payOrder->setAppId('wx123456789');
        $payOrder->setMerchant($merchant);
        $payOrder->setMchId($merchant->getMchId()); // 设置必需的mchId字段
        $payOrder->setTotalFee(2000);
        $payOrder->setTradeNo('TEST_ORDER_' . uniqid());
        $payOrder->setDescription('Test Order Description');
        $payOrder->setTradeType('JSAPI'); // 设置必需的trade_type字段
        $payOrder->setBody('Test Product'); // 设置必需的body字段
        $payOrder->setStatus(PayOrderStatus::INIT); // 设置必需的status字段
        // 预设notifyUrl以避免PayOrderListener自动生成路由时的参数问题
        $payOrder->setNotifyUrl('https://example.com/wechat-pay-notify');
        $this->persistAndFlush($payOrder);

        return $payOrder;
    }

    private function createSuccessRefundResponse(): RefundResponse
    {
        // 模拟成功的退款响应
        $xml = '<?xml version="1.0" encoding="utf-8"?>
<xml>
    <return_code><![CDATA[SUCCESS]]></return_code>
    <return_msg><![CDATA[OK]]></return_msg>
    <result_code><![CDATA[SUCCESS]]></result_code>
    <refund_id><![CDATA[50000000001201511110000]]></refund_id>
    <out_refund_no><![CDATA[REFUND123456]]></out_refund_no>
    <refund_fee>1000</refund_fee>
    <settlement_refund_fee>1000</settlement_refund_fee>
    <total_fee>2000</total_fee>
    <settlement_total_fee>2000</settlement_total_fee>
    <fee_type><![CDATA[CNY]]></fee_type>
    <cash_fee>2000</cash_fee>
    <cash_fee_type><![CDATA[CNY]]></cash_fee_type>
    <cash_refund_fee>1000</cash_refund_fee>
    <coupon_refund_fee>0</coupon_refund_fee>
    <coupon_refund_count>0</coupon_refund_count>
</xml>';

        return RefundResponse::fromXml($xml);
    }

    private function createFailedRefundResponse(): RefundResponse
    {
        // 模拟失败的退款响应
        $xml = '<?xml version="1.0" encoding="utf-8"?>
<xml>
    <return_code><![CDATA[SUCCESS]]></return_code>
    <return_msg><![CDATA[OK]]></return_msg>
    <result_code><![CDATA[FAIL]]></result_code>
    <err_code><![CDATA[ORDERNOTEXIST]]></err_code>
    <err_code_des><![CDATA[此交易订单号不存在]]></err_code_des>
</xml>';

        return RefundResponse::fromXml($xml);
    }

    /**
     * @return array<string, mixed>
     */
    private function createValidRefundParams(): array
    {
        return [
            'outRefundNo' => 'REFUND123456',
            'totalFee' => 2000,
            'refundFee' => 1000,
            'refundDesc' => '用户申请退款',
            'notifyUrl' => 'https://example.com/notify',
            'outTradeNo' => 'ORDER123456',
            'transactionId' => 'WX123456',
        ];
    }
}
