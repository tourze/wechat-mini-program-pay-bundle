<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Response;

use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WechatMiniProgramPayBundle\Service\SignatureService;
use WechatMiniProgramPayBundle\Utils\XmlUtils;

/**
 * 微信支付退款响应处理器.
 *
 * 处理微信支付V2版本的退款API响应，支持：
 * - XML响应解析
 * - 签名验证
 * - 错误码映射
 * - 退款结果提取
 *
 * @see https://pay.weixin.qq.com/doc/v2/merchant/4011941262
 */
class RefundResponse
{
    private const SUCCESS_RETURN_CODE = 'SUCCESS';
    private const SUCCESS_RESULT_CODE = 'SUCCESS';

    // 微信支付退款错误码映射
    private const ERROR_CODE_MAPPING = [
        'ORDERNOTEXIST' => '此交易订单号不存在',
        'ORDERCLOSE' => '订单已关闭',
        'NOAUTH' => '商户无权限',
        'NOTENOUGH' => '余额不足',
        'INVALID_REQ_TOO_MUCH' => '无效请求过多',
        'BIZERR_NEED_RETRY' => '退款业务流程错误，需要商户触发重试来解决',
        'TRADE_OVERDUE' => '订单已经超过退款期限',
        'ERROR' => '系统错误',
        'USER_ACCOUNT_ABNORMAL' => '退款请求失败，用户账号异常',
        'INVALID_TRANSACTIONID' => '无效transaction_id',
        'PARAM_ERROR' => '参数错误',
        'APPID_NOT_EXIST' => 'APPID不存在',
        'MCHID_NOT_EXIST' => '商户号不存在',
        'APPID_MCHID_NOT_MATCH' => 'appid和mch_id不匹配',
        'LACK_PARAMS' => '缺少参数',
        'SIGNERROR' => '签名错误',
        'XML_FORMAT_ERROR' => 'XML格式错误',
        'FREQUENCY_LIMITED' => '频率限制',
        'REQUIRE_POST_METHOD' => '请使用post方法',
        'POST_DATA_EMPTY' => 'post数据为空',
        'NOT_UTF8' => '编码格式错误',
    ];

    /** @var array<string, mixed> */
    private array $data = [];

    private bool $isSuccess = false;

    private string $errorCode = '';

    private string $errorMessage = '';

    private string $refundId = '';

    private string $outRefundNo = '';

    private int $refundFee = 0;

    private int $settlementRefundFee = 0;

    private int $totalFee = 0;

    private int $settlementTotalFee = 0;

    private string $feeType = '';

    private int $cashFee = 0;

    private string $cashFeeType = '';

    private int $cashRefundFee = 0;

    private int $couponRefundFee = 0;

    private int $couponRefundCount = 0;

    /**
     * 从XML字符串构造响应对象
     */
    public static function fromXml(string $xml): self
    {
        $response = new self();
        $response->parseXml($xml);

        return $response;
    }

    /**
     * 解析XML响应.
     */
    private function parseXml(string $xml): void
    {
        if ('' === $xml) {
            throw new PaymentConfigurationException('响应内容为空');
        }

        try {
            $this->data = XmlUtils::xmlToArray($xml);
        } catch (PaymentConfigurationException $e) {
            throw new PaymentConfigurationException('响应XML解析失败: ' . $e->getMessage());
        }

        $this->processResponse();
    }

    /**
     * 处理响应数据.
     */
    private function processResponse(): void
    {
        // 检查返回状态
        $returnCode = $this->getField('return_code');
        if (self::SUCCESS_RETURN_CODE !== $returnCode) {
            $this->isSuccess = false;
            $this->errorCode = $returnCode;
            $this->errorMessage = $this->getField('return_msg', '通信失败');

            return;
        }

        // 检查业务结果
        $resultCode = $this->getField('result_code');
        if (self::SUCCESS_RESULT_CODE !== $resultCode) {
            $this->isSuccess = false;
            $this->errorCode = $this->getField('err_code', $resultCode);
            $this->errorMessage = $this->mapErrorMessage($this->errorCode, $this->getField('err_code_des', '业务失败'));

            return;
        }

        // 成功时提取关键信息
        $this->isSuccess = true;
        $this->refundId = $this->getField('refund_id', '');
        $this->outRefundNo = $this->getField('out_refund_no', '');
        $this->refundFee = (int) $this->getField('refund_fee', '0');
        $this->settlementRefundFee = (int) $this->getField('settlement_refund_fee', '0');
        $this->totalFee = (int) $this->getField('total_fee', '0');
        $this->settlementTotalFee = (int) $this->getField('settlement_total_fee', '0');
        $this->feeType = $this->getField('fee_type', '');
        $this->cashFee = (int) $this->getField('cash_fee', '0');
        $this->cashFeeType = $this->getField('cash_fee_type', '');
        $this->cashRefundFee = (int) $this->getField('cash_refund_fee', '0');
        $this->couponRefundFee = (int) $this->getField('coupon_refund_fee', '0');
        $this->couponRefundCount = (int) $this->getField('coupon_refund_count', '0');
    }

    /**
     * 验证响应签名.
     */
    public function verifySignature(string $key, string $signType = 'MD5'): bool
    {
        if (!$this->isSuccess) {
            return true; // 失败响应不验证签名
        }

        $responseSign = $this->getField('sign', '');
        if ('' === $responseSign) {
            return false;
        }

        // 构建签名数据（排除sign字段）
        $signData = $this->data;
        unset($signData['sign']);

        $signatureService = new SignatureService();
        $calculatedSign = $signatureService->generateSignature($signData, $key, $signType);

        return $responseSign === $calculatedSign;
    }

    private function getField(string $key, string $default = ''): string
    {
        $value = $this->data[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    private function mapErrorMessage(string $errorCode, string $defaultMessage): string
    {
        return self::ERROR_CODE_MAPPING[$errorCode] ?? $defaultMessage;
    }

    // Getter methods

    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function getRefundId(): string
    {
        return $this->refundId;
    }

    public function getOutRefundNo(): string
    {
        return $this->outRefundNo;
    }

    public function getRefundFee(): int
    {
        return $this->refundFee;
    }

    public function getSettlementRefundFee(): int
    {
        return $this->settlementRefundFee;
    }

    public function getTotalFee(): int
    {
        return $this->totalFee;
    }

    public function getSettlementTotalFee(): int
    {
        return $this->settlementTotalFee;
    }

    public function getFeeType(): string
    {
        return $this->feeType;
    }

    public function getCashFee(): int
    {
        return $this->cashFee;
    }

    public function getCashFeeType(): string
    {
        return $this->cashFeeType;
    }

    public function getCashRefundFee(): int
    {
        return $this->cashRefundFee;
    }

    public function getCouponRefundFee(): int
    {
        return $this->couponRefundFee;
    }

    public function getCouponRefundCount(): int
    {
        return $this->couponRefundCount;
    }

    /**
     * 获取所有响应数据.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 获取退款详细信息.
     *
     * @return array<string, mixed>
     */
    public function getRefundInfo(): array
    {
        return [
            'refund_id' => $this->refundId,
            'out_refund_no' => $this->outRefundNo,
            'refund_fee' => $this->refundFee,
            'settlement_refund_fee' => $this->settlementRefundFee,
            'total_fee' => $this->totalFee,
            'settlement_total_fee' => $this->settlementTotalFee,
            'fee_type' => $this->feeType,
            'cash_fee' => $this->cashFee,
            'cash_fee_type' => $this->cashFeeType,
            'cash_refund_fee' => $this->cashRefundFee,
            'coupon_refund_fee' => $this->couponRefundFee,
            'coupon_refund_count' => $this->couponRefundCount,
        ];
    }
}
