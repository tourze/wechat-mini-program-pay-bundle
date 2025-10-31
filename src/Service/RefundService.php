<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use WechatMiniProgramBundle\Service\PayClient;
use WechatMiniProgramPayBundle\Config\WechatPayConfig;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WechatMiniProgramPayBundle\Request\RefundRequest;
use WechatMiniProgramPayBundle\Response\RefundResponse;
use WechatPayBundle\Entity\Merchant;
use WechatPayBundle\Entity\PayOrder;
use WechatPayBundle\Entity\RefundOrder;
use WechatPayBundle\Repository\PayOrderRepository;

/**
 * 微信支付退款服务
 *
 * 处理微信支付退款相关业务逻辑，包括：
 * - 退款请求创建和发送
 * - 退款响应处理和验证
 * - 退款订单记录管理
 * - 证书配置和安全处理
 */
#[WithMonologChannel(channel: 'wechat_mini_program_pay')]
class RefundService
{
    public function __construct(
        private readonly PayClient $client,
        private readonly SignatureService $signatureService,
        private readonly LoggerInterface $logger,
        private readonly PayOrderRepository $payOrderRepository,
        private readonly RefundRequestBuilder $requestBuilder,
        private readonly RefundOrderManager $orderManager,
    ) {
    }

    /**
     * 申请退款.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function refund(array $params): array
    {
        $this->validateRefundParams($params);

        try {
            return $this->processRefund($params);
        } catch (\Exception $e) {
            $this->logger->error('退款申请失败', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            throw new PaymentConfigurationException('退款申请失败: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function processRefund(array $params): array
    {
        $payOrder = $this->findPayOrderFromParams($params);
        $request = $this->requestBuilder->build($payOrder, $params);
        $response = $this->sendRefundRequest($request, $payOrder);
        $refundOrder = $this->orderManager->create($payOrder, $response, $params);

        return $this->formatRefundResult($response, $refundOrder);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function findPayOrderFromParams(array $params): PayOrder
    {
        $outTradeNoValue = $params['outTradeNo'] ?? '';
        $transactionIdValue = $params['transactionId'] ?? '';

        $outTradeNo = is_string($outTradeNoValue) ? $outTradeNoValue : '';
        $transactionId = is_string($transactionIdValue) ? $transactionIdValue : '';

        return $this->findPayOrder($outTradeNo, $transactionId);
    }

    private function sendRefundRequest(RefundRequest $request, PayOrder $payOrder): RefundResponse
    {
        $merchant = $payOrder->getMerchant();
        if (null === $merchant) {
            throw new PaymentConfigurationException('支付订单缺少商户信息');
        }

        $config = $this->createPayConfig($merchant, $request->getAppId());
        $this->signRefundRequest($request, $config);
        $xmlResponse = $this->executeRefundRequest($request);

        return $this->processRefundResponse($xmlResponse, $config, $request->getOutRefundNo());
    }

    /**
     * @param array<string, mixed> $params
     */
    private function validateRefundParams(array $params): void
    {
        $this->validateRequiredFields($params);
        $this->validateTradeNumbers($params);
        $this->validateAmounts($params);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function validateRequiredFields(array $params): void
    {
        $requiredFields = ['outRefundNo', 'totalFee', 'refundFee'];

        foreach ($requiredFields as $field) {
            if (!isset($params[$field]) || $this->isEmptyValue($params[$field])) {
                throw new PaymentConfigurationException("必填参数 {$field} 不能为空");
            }
        }
    }

    private function isEmptyValue(mixed $value): bool
    {
        return null === $value || '' === $value;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function validateTradeNumbers(array $params): void
    {
        $transactionIdValue = $params['transactionId'] ?? '';
        $outTradeNoValue = $params['outTradeNo'] ?? '';

        $transactionId = is_string($transactionIdValue) ? $transactionIdValue : '';
        $outTradeNo = is_string($outTradeNoValue) ? $outTradeNoValue : '';

        if ('' === $transactionId && '' === $outTradeNo) {
            throw new PaymentConfigurationException('微信交易号和商户订单号不能同时为空');
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function validateAmounts(array $params): void
    {
        if (!isset($params['totalFee']) || !isset($params['refundFee'])) {
            throw new PaymentConfigurationException('金额参数缺失');
        }

        $totalFeeValue = $params['totalFee'];
        $refundFeeValue = $params['refundFee'];

        if (!is_int($totalFeeValue) && !is_numeric($totalFeeValue)) {
            throw new PaymentConfigurationException('订单总金额必须是数字');
        }
        if (!is_int($refundFeeValue) && !is_numeric($refundFeeValue)) {
            throw new PaymentConfigurationException('退款金额必须是数字');
        }

        $totalFee = (int) $totalFeeValue;
        $refundFee = (int) $refundFeeValue;

        if ($totalFee <= 0) {
            throw new PaymentConfigurationException('订单总金额必须大于0');
        }

        if ($refundFee <= 0) {
            throw new PaymentConfigurationException('退款金额必须大于0');
        }

        if ($refundFee > $totalFee) {
            throw new PaymentConfigurationException('退款金额不能大于订单总金额');
        }
    }

    private function findPayOrder(string $outTradeNo, string $transactionId): PayOrder
    {
        $payOrder = ('' !== $transactionId)
            ? $this->payOrderRepository->findOneBy(['transactionId' => $transactionId])
            : $this->payOrderRepository->findOneBy(['tradeNo' => $outTradeNo]);

        if (null === $payOrder) {
            throw new PaymentConfigurationException('找不到对应的支付订单');
        }

        return $payOrder;
    }

    private function signRefundRequest(RefundRequest $request, WechatPayConfig $config): void
    {
        $signedParams = $this->signatureService->signParameters(
            $request->toArray(),
            $config->getKey(),
            $config->getSignType()
        );

        if (!isset($signedParams['sign'])) {
            throw new PaymentConfigurationException('签名生成失败');
        }

        $signValue = $signedParams['sign'];
        if (!is_string($signValue)) {
            throw new PaymentConfigurationException('签名必须是字符串');
        }

        $request->setSign($signValue);
    }

    private function executeRefundRequest(RefundRequest $request): string
    {
        try {
            $xmlResponse = $this->client->request($request);
            if (!is_string($xmlResponse)) {
                throw new PaymentConfigurationException('退款响应格式错误');
            }

            return $xmlResponse;
        } catch (\Throwable $exception) {
            $this->logger->error('退款请求失败', [
                'request' => $request,
                'exception' => $exception,
                'out_refund_no' => $request->getOutRefundNo(),
            ]);
            throw new PaymentConfigurationException('退款请求失败: ' . $exception->getMessage());
        }
    }

    private function processRefundResponse(string $xmlResponse, WechatPayConfig $config, string $outRefundNo): RefundResponse
    {
        $response = RefundResponse::fromXml($xmlResponse);
        $this->validateRefundResponse($response, $config, $outRefundNo);

        return $response;
    }

    private function validateRefundResponse(RefundResponse $response, WechatPayConfig $config, string $outRefundNo): void
    {
        if (!$response->verifySignature($config->getKey(), $config->getSignType())) {
            $this->logger->error('退款响应签名验证失败', ['out_refund_no' => $outRefundNo]);
            throw new PaymentConfigurationException('退款响应签名验证失败');
        }

        if (!$response->isSuccess()) {
            $this->logger->error('退款申请失败', [
                'error' => $response->getErrorMessage(),
                'out_refund_no' => $outRefundNo,
            ]);
            throw new PaymentConfigurationException('退款申请失败: ' . $response->getErrorMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRefundResult(RefundResponse $response, RefundOrder $refundOrder): array
    {
        return [
            'success' => true,
            'refund_id' => $response->getRefundId(),
            'out_refund_no' => $response->getOutRefundNo(),
            'refund_fee' => $response->getRefundFee(),
            'settlement_refund_fee' => $response->getSettlementRefundFee(),
            'total_fee' => $response->getTotalFee(),
            'cash_refund_fee' => $response->getCashRefundFee(),
            'coupon_refund_fee' => $response->getCouponRefundFee(),
            'refund_order_id' => $refundOrder->getId(),
        ];
    }

    private function createPayConfig(Merchant $merchant, string $appId): WechatPayConfig
    {
        return WechatPayConfig::fromArray([
            'app_id' => $appId,
            'mch_id' => $merchant->getMchId(),
            'key' => $merchant->getApiKey(),
            'sign_type' => 'MD5',
        ]);
    }

    /**
     * 生成退款单号.
     */
    public static function generateRefundNo(): string
    {
        return RefundRequest::generateRefundNo();
    }
}
