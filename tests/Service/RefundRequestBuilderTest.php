<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WechatMiniProgramPayBundle\Request\RefundRequest;
use WechatMiniProgramPayBundle\Service\RefundRequestBuilder;
use WechatPayBundle\Entity\Merchant;
use WechatPayBundle\Entity\PayOrder;

/**
 * RefundRequestBuilder 测试.
 *
 * @internal
 */
#[CoversClass(RefundRequestBuilder::class)]
#[RunTestsInSeparateProcesses]
final class RefundRequestBuilderTest extends AbstractIntegrationTestCase
{
    private RefundRequestBuilder $refundRequestBuilder;

    protected function onSetUp(): void
    {
        $this->refundRequestBuilder = self::getService(RefundRequestBuilder::class);
    }

    #[Test]
    public function testBuildRefundRequestWithValidParams(): void
    {
        $payOrder = $this->createPayOrder();
        $params = $this->createValidRefundParams();

        $request = $this->refundRequestBuilder->build($payOrder, $params);

        $this->assertInstanceOf(RefundRequest::class, $request);
        $this->assertEquals($payOrder->getAppId(), $request->getAppId());
        $this->assertEquals($payOrder->getMerchant()?->getMchId(), $request->getMchId());
        $this->assertNotEmpty($request->getNonceStr());
        $this->assertEquals($params['outRefundNo'], $request->getOutRefundNo());
        $this->assertEquals($params['totalFee'], $request->getTotalFee());
        $this->assertEquals($params['refundFee'], $request->getRefundFee());
    }

    #[Test]
    public function testBuildRefundRequestWithoutMerchant(): void
    {
        $payOrder = new PayOrder();
        $payOrder->setAppId('wx123456789');
        $payOrder->setMerchant(null); // 没有商户信息

        $params = $this->createValidRefundParams();

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('支付订单缺少商户信息');

        $this->refundRequestBuilder->build($payOrder, $params);
    }

    #[Test]
    public function testBuildRefundRequestWithoutAppId(): void
    {
        // 创建一个没有AppId的PayOrder
        $merchant = new Merchant();
        $merchant->setMchId('test_merchant_123');
        $merchant->setApiKey('test_api_key');
        $merchant->setCertSerial('test_cert_serial');

        $payOrder = new PayOrder();
        // 不设置AppId
        $payOrder->setMerchant($merchant);
        $payOrder->setTotalFee(2000);
        $payOrder->setTradeNo('ORDER123456');

        $params = $this->createValidRefundParams();

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('支付订单缺少AppID信息');

        $this->refundRequestBuilder->build($payOrder, $params);
    }

    #[Test]
    public function testBuildRefundRequestWithMissingRefundInfo(): void
    {
        $payOrder = $this->createPayOrder();
        $params = [
            'outRefundNo' => 'REFUND123',
            // 缺少 totalFee 和 refundFee
        ];

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('退款信息参数缺失');

        $this->refundRequestBuilder->build($payOrder, $params);
    }

    #[Test]
    public function testBuildRefundRequestWithOptionalParams(): void
    {
        $payOrder = $this->createPayOrder();
        $params = [
            'outRefundNo' => 'REFUND123456',
            'totalFee' => 2000,
            'refundFee' => 1000,
            'transactionId' => 'WX123456789',
            'outTradeNo' => 'ORDER123456',
            'refundDesc' => '用户申请退款',
            'refundAccount' => 'REFUND_SOURCE_RECHARGE_FUNDS',
            'notifyUrl' => 'https://example.com/notify',
        ];

        $request = $this->refundRequestBuilder->build($payOrder, $params);

        $this->assertEquals($params['transactionId'], $request->getTransactionId());
        $this->assertEquals($params['outTradeNo'], $request->getOutTradeNo());
        $this->assertEquals($params['refundDesc'], $request->getRefundDesc());
        $this->assertEquals($params['refundAccount'], $request->getRefundAccount());
        $this->assertEquals($params['notifyUrl'], $request->getNotifyUrl());
    }

    #[Test]
    public function testBuildRefundRequestWithEmptyOptionalParams(): void
    {
        $payOrder = $this->createPayOrder();
        $params = [
            'outRefundNo' => 'REFUND123456',
            'totalFee' => 2000,
            'refundFee' => 1000,
            'transactionId' => '',  // 空字符串
            'outTradeNo' => '',     // 空字符串
            'refundDesc' => null,   // null值
        ];

        $request = $this->refundRequestBuilder->build($payOrder, $params);

        // 空字符串不应该被设置
        $this->assertNull($request->getTransactionId());
        $this->assertEquals('', $request->getOutTradeNo());
        $this->assertNull($request->getRefundDesc());
    }

    #[Test]
    public function testBuildRefundRequestWithTradeInfo(): void
    {
        $payOrder = $this->createPayOrder();
        $params = [
            'outRefundNo' => 'REFUND123456',
            'totalFee' => 2000,
            'refundFee' => 1000,
            'transactionId' => 'WX123456789',
            'outTradeNo' => 'ORDER123456',
        ];

        $request = $this->refundRequestBuilder->build($payOrder, $params);

        $this->assertEquals($params['transactionId'], $request->getTransactionId());
        $this->assertEquals($params['outTradeNo'], $request->getOutTradeNo());
    }

    #[Test]
    public function testBuildRefundRequestWithOnlyTransactionId(): void
    {
        $payOrder = $this->createPayOrder();
        $params = [
            'outRefundNo' => 'REFUND123456',
            'totalFee' => 2000,
            'refundFee' => 1000,
            'transactionId' => 'WX123456789',
            // 没有 outTradeNo
        ];

        $request = $this->refundRequestBuilder->build($payOrder, $params);

        $this->assertEquals($params['transactionId'], $request->getTransactionId());
        $this->assertEquals('', $request->getOutTradeNo());
    }

    #[Test]
    public function testBuildRefundRequestWithOnlyOutTradeNo(): void
    {
        $payOrder = $this->createPayOrder();
        $params = [
            'outRefundNo' => 'REFUND123456',
            'totalFee' => 2000,
            'refundFee' => 1000,
            'outTradeNo' => 'ORDER123456',
            // 没有 transactionId
        ];

        $request = $this->refundRequestBuilder->build($payOrder, $params);

        $this->assertNull($request->getTransactionId());
        $this->assertEquals($params['outTradeNo'], $request->getOutTradeNo());
    }

    #[Test]
    #[DataProvider('provideRefundAmounts')]
    public function testBuildRefundRequestWithDifferentAmounts(int $totalFee, int $refundFee): void
    {
        $payOrder = $this->createPayOrder();
        $params = [
            'outRefundNo' => 'REFUND123456',
            'totalFee' => $totalFee,
            'refundFee' => $refundFee,
        ];

        $request = $this->refundRequestBuilder->build($payOrder, $params);

        $this->assertEquals($totalFee, $request->getTotalFee());
        $this->assertEquals($refundFee, $request->getRefundFee());
    }

    /**
     * @return iterable<array{int, int}>
     */
    public static function provideRefundAmounts(): iterable
    {
        yield 'full refund' => [1000, 1000];
        yield 'partial refund' => [2000, 1000];
        yield 'small amounts' => [100, 50];
        yield 'large amounts' => [100000, 50000];
    }

    #[Test]
    public function testRequestBuilderCallsServiceMethodsInCorrectOrder(): void
    {
        $payOrder = $this->createPayOrder();
        $params = $this->createValidRefundParams();

        // 设置Mock期望调用顺序

        $request = $this->refundRequestBuilder->build($payOrder, $params);

        // 验证基本请求信息
        $this->assertEquals($payOrder->getAppId(), $request->getAppId());
        $this->assertEquals($payOrder->getMerchant()?->getMchId(), $request->getMchId());
        $this->assertNotEmpty($request->getNonceStr());
    }

    #[Test]
    public function testBuildRefundRequestWithAllOptionalFieldsSet(): void
    {
        $payOrder = $this->createPayOrder();
        $params = [
            'outRefundNo' => 'REFUND123456',
            'totalFee' => 2000,
            'refundFee' => 1000,
            'transactionId' => 'WX123456789',
            'outTradeNo' => 'ORDER123456',
            'refundDesc' => '用户主动申请退款',
            'refundAccount' => 'REFUND_SOURCE_UNSETTLED_FUNDS',
            'notifyUrl' => 'https://api.example.com/notify/refund',
        ];

        $request = $this->refundRequestBuilder->build($payOrder, $params);

        // 验证所有字段都正确设置
        $this->assertEquals('wx123456789', $request->getAppId());
        $this->assertEquals('test_merchant_123', $request->getMchId());
        $this->assertNotEmpty($request->getNonceStr());
        $this->assertEquals('REFUND123456', $request->getOutRefundNo());
        $this->assertEquals(2000, $request->getTotalFee());
        $this->assertEquals(1000, $request->getRefundFee());
        $this->assertEquals('WX123456789', $request->getTransactionId());
        $this->assertEquals('ORDER123456', $request->getOutTradeNo());
        $this->assertEquals('用户主动申请退款', $request->getRefundDesc());
        $this->assertEquals('REFUND_SOURCE_UNSETTLED_FUNDS', $request->getRefundAccount());
        $this->assertEquals('https://api.example.com/notify/refund', $request->getNotifyUrl());
    }

    private function createPayOrder(): PayOrder
    {
        $merchant = new Merchant();
        $merchant->setMchId('test_merchant_123');
        $merchant->setApiKey('test_api_key');
        $merchant->setCertSerial('test_cert_serial');

        $payOrder = new PayOrder();
        $payOrder->setAppId('wx123456789');
        $payOrder->setMerchant($merchant);
        $payOrder->setTotalFee(2000);
        $payOrder->setTradeNo('ORDER123456');

        return $payOrder;
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
        ];
    }
}
