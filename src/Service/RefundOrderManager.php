<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Service;

use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WechatMiniProgramPayBundle\Response\RefundResponse;
use WechatPayBundle\Entity\PayOrder;
use WechatPayBundle\Entity\RefundOrder;
use WechatPayBundle\Repository\RefundOrderRepository;

/**
 * 退款订单管理器.
 *
 * 负责创建和管理退款订单记录
 */
final class RefundOrderManager
{
    public function __construct(
        private readonly RefundOrderRepository $refundOrderRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     */
    public function create(PayOrder $payOrder, RefundResponse $response, array $params): RefundOrder
    {
        $refundOrder = $this->createBasicRefundOrder($payOrder, $params);
        $this->setRefundOrderFromResponse($refundOrder, $response);
        $this->setRefundOrderJsonData($refundOrder, $response, $params);

        $this->refundOrderRepository->save($refundOrder);

        return $refundOrder;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function createBasicRefundOrder(PayOrder $payOrder, array $params): RefundOrder
    {
        if (!isset($params['refundFee'])) {
            throw new PaymentConfigurationException('退款金额参数缺失');
        }

        $refundOrder = new RefundOrder();
        $refundOrder->setPayOrder($payOrder);

        $appId = $payOrder->getAppId();
        if (null !== $appId) {
            $refundOrder->setAppId($appId);
        }

        $refundFee = $params['refundFee'];
        if (!is_int($refundFee) && !is_numeric($refundFee)) {
            throw new PaymentConfigurationException('退款金额必须是数字');
        }
        $refundOrder->setMoney((int) $refundFee);

        $this->setOptionalFields($refundOrder, $params);

        return $refundOrder;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function setOptionalFields(RefundOrder $refundOrder, array $params): void
    {
        $refundDesc = $params['refundDesc'] ?? null;
        if (null !== $refundDesc && is_string($refundDesc) && '' !== $refundDesc) {
            $refundOrder->setReason($refundDesc);
        }

        $notifyUrl = $params['notifyUrl'] ?? null;
        if (null !== $notifyUrl && is_string($notifyUrl) && '' !== $notifyUrl) {
            $refundOrder->setNotifyUrl($notifyUrl);
        }
    }

    private function setRefundOrderFromResponse(RefundOrder $refundOrder, RefundResponse $response): void
    {
        if ($response->isSuccess() && '' !== $response->getRefundId()) {
            $refundOrder->setRefundId($response->getRefundId());
        }

        $refundOrder->setStatus($response->isSuccess() ? 'SUCCESS' : 'FAILED');
    }

    /**
     * @param array<string, mixed> $params
     */
    private function setRefundOrderJsonData(RefundOrder $refundOrder, RefundResponse $response, array $params): void
    {
        $this->setRequestJson($refundOrder, $params);
        $this->setResponseJson($refundOrder, $response);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function setRequestJson(RefundOrder $refundOrder, array $params): void
    {
        if (!isset($params['outRefundNo'], $params['totalFee'], $params['refundFee'])) {
            throw new PaymentConfigurationException('请求数据参数缺失');
        }

        $outTradeNo = $params['outTradeNo'] ?? '';
        $transactionId = $params['transactionId'] ?? '';
        $outRefundNo = $params['outRefundNo'];
        $refundDesc = $params['refundDesc'] ?? '';
        $refundAccount = $params['refundAccount'] ?? '';
        $notifyUrl = $params['notifyUrl'] ?? '';

        $requestData = [
            'out_trade_no' => is_string($outTradeNo) ? $outTradeNo : '',
            'transaction_id' => is_string($transactionId) ? $transactionId : '',
            'out_refund_no' => is_string($outRefundNo) ? $outRefundNo : '',
            'total_fee' => $params['totalFee'],
            'refund_fee' => $params['refundFee'],
            'refund_desc' => is_string($refundDesc) ? $refundDesc : '',
            'refund_account' => is_string($refundAccount) ? $refundAccount : '',
            'notify_url' => is_string($notifyUrl) ? $notifyUrl : '',
        ];

        $requestJson = json_encode($requestData, JSON_UNESCAPED_UNICODE);
        $refundOrder->setRequestJson(false !== $requestJson ? $requestJson : null);
    }

    private function setResponseJson(RefundOrder $refundOrder, RefundResponse $response): void
    {
        if ($response->isSuccess()) {
            $responseJson = json_encode($response->getData(), JSON_UNESCAPED_UNICODE);
            $refundOrder->setResponseJson(false !== $responseJson ? $responseJson : null);
        } else {
            $errorData = [
                'error_code' => $response->getErrorCode(),
                'error_message' => $response->getErrorMessage(),
            ];
            $errorJson = json_encode($errorData, JSON_UNESCAPED_UNICODE);
            $refundOrder->setResponseJson(false !== $errorJson ? $errorJson : null);
        }
    }
}
