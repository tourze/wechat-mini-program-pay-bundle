<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Service;

use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WechatMiniProgramPayBundle\Request\RefundRequest;
use WechatPayBundle\Entity\Merchant;
use WechatPayBundle\Entity\PayOrder;

/**
 * 退款请求构建器.
 *
 * 负责创建和配置退款请求对象
 */
final class RefundRequestBuilder
{
    public function __construct(
        private readonly SignatureService $signatureService,
        private readonly CertificateManager $certificateManager,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     */
    public function build(PayOrder $payOrder, array $params): RefundRequest
    {
        $merchant = $payOrder->getMerchant();
        if (null === $merchant) {
            throw new PaymentConfigurationException('支付订单缺少商户信息');
        }

        $request = $this->createBaseRequest($payOrder, $merchant);
        $this->setTradeInfo($request, $params);
        $this->setRefundInfo($request, $params);
        $this->setOptionalParams($request, $params);
        $this->setCertificates($request, $merchant);

        return $request;
    }

    private function createBaseRequest(PayOrder $payOrder, Merchant $merchant): RefundRequest
    {
        $appId = $payOrder->getAppId();
        if (null === $appId) {
            throw new PaymentConfigurationException('支付订单缺少AppID信息');
        }

        $request = new RefundRequest();
        $request->setAppId($appId);
        $request->setMchId($merchant->getMchId());
        $request->setNonceStr($this->signatureService->generateNonceStr());

        return $request;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function setTradeInfo(RefundRequest $request, array $params): void
    {
        $transactionIdValue = $params['transactionId'] ?? '';
        $outTradeNoValue = $params['outTradeNo'] ?? '';

        $transactionId = is_string($transactionIdValue) ? $transactionIdValue : '';
        $outTradeNo = is_string($outTradeNoValue) ? $outTradeNoValue : '';

        if ('' !== $transactionId) {
            $request->setTransactionId($transactionId);
        }
        if ('' !== $outTradeNo) {
            $request->setOutTradeNo($outTradeNo);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function setRefundInfo(RefundRequest $request, array $params): void
    {
        if (!isset($params['outRefundNo'], $params['totalFee'], $params['refundFee'])) {
            throw new PaymentConfigurationException('退款信息参数缺失');
        }

        $outRefundNo = $params['outRefundNo'];
        if (!is_string($outRefundNo)) {
            throw new PaymentConfigurationException('退款单号必须是字符串');
        }
        $request->setOutRefundNo($outRefundNo);

        $totalFee = $params['totalFee'];
        if (!is_int($totalFee) && !is_numeric($totalFee)) {
            throw new PaymentConfigurationException('订单总金额必须是数字');
        }
        $request->setTotalFee((int) $totalFee);

        $refundFee = $params['refundFee'];
        if (!is_int($refundFee) && !is_numeric($refundFee)) {
            throw new PaymentConfigurationException('退款金额必须是数字');
        }
        $request->setRefundFee((int) $refundFee);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function setOptionalParams(RefundRequest $request, array $params): void
    {
        $refundDesc = $params['refundDesc'] ?? null;
        if (null !== $refundDesc && is_string($refundDesc)) {
            $request->setRefundDesc($refundDesc);
        }

        $refundAccount = $params['refundAccount'] ?? null;
        if (null !== $refundAccount && is_string($refundAccount)) {
            $request->setRefundAccount($refundAccount);
        }

        $notifyUrl = $params['notifyUrl'] ?? null;
        if (null !== $notifyUrl && is_string($notifyUrl)) {
            $request->setNotifyUrl($notifyUrl);
        }
    }

    private function setCertificates(RefundRequest $request, Merchant $merchant): void
    {
        $certPath = $this->certificateManager->getCertPath($merchant);
        $keyPath = $this->certificateManager->getKeyPath($merchant);

        if ('' !== $certPath && '' !== $keyPath) {
            $request->setCertPath($certPath);
            $request->setKeyPath($keyPath);
        }
    }
}
