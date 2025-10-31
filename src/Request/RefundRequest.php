<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Request;

use WechatMiniProgramBundle\Request\WithAccountRequest;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;

/**
 * 微信支付退款请求
 *
 * 支持微信支付V2版本的退款接口，用于申请退款操作
 *
 * @see https://pay.weixin.qq.com/doc/v2/merchant/4011941262
 */
class RefundRequest extends WithAccountRequest
{
    // 必填参数
    private string $appId = '';

    private string $mchId = '';

    private string $nonceStr = '';

    private string $sign = '';

    private string $outTradeNo = '';

    private string $outRefundNo = '';

    private int $totalFee = 0;

    private int $refundFee = 0;

    // 可选参数
    private ?string $transactionId = null;

    private ?string $refundDesc = null;

    private ?string $refundAccount = null;

    private ?string $notifyUrl = null;

    private ?string $deviceInfo = null;

    private ?string $signType = 'MD5';   // 默认MD5签名

    private ?string $certPath = null;

    private ?string $keyPath = null;

    public function getRequestPath(): string
    {
        return '/secapi/pay/refund';
    }

    public function getBaseUri(): string
    {
        return 'https://api.mch.weixin.qq.com';
    }

    /** @return array<string, mixed>|null */
    public function getRequestOptions(): ?array
    {
        $this->validate();

        $xmlData = $this->toXml();

        $options = [
            'headers' => [
                'Content-Type' => 'application/xml; charset=utf-8',
                'User-Agent' => 'WechatMiniProgramPayBundle/1.0',
            ],
            'body' => $xmlData,
        ];

        // 添加证书支持（退款接口需要双向证书认证）
        $certPath = $this->getCertPath();
        $keyPath = $this->getKeyPath();

        if (null !== $certPath && '' !== $certPath && null !== $keyPath && '' !== $keyPath) {
            $options['local_cert'] = $certPath;
            $options['local_pk'] = $keyPath;
        }

        return $options;
    }

    /**
     * 验证必填参数.
     */
    public function validate(): void
    {
        $this->validateRequiredFields();
        $this->validateTradeNumbers();
        $this->validateAmounts();
        $this->validateFieldLengths();
    }

    private function validateRequiredFields(): void
    {
        $requiredFields = [
            'appId' => $this->appId,
            'mchId' => $this->mchId,
            'nonceStr' => $this->nonceStr,
            'outRefundNo' => $this->outRefundNo,
            'totalFee' => $this->totalFee,
            'refundFee' => $this->refundFee,
        ];

        foreach ($requiredFields as $field => $value) {
            if ('' === $value || 0 === $value) {
                throw new PaymentConfigurationException("必填参数 {$field} 不能为空");
            }
        }
    }

    private function validateTradeNumbers(): void
    {
        if ((null === $this->transactionId || '' === $this->transactionId) && '' === $this->outTradeNo) {
            throw new PaymentConfigurationException('微信交易号和商户订单号不能同时为空');
        }
    }

    private function validateAmounts(): void
    {
        if ($this->totalFee <= 0) {
            throw new PaymentConfigurationException('订单总金额必须大于0');
        }

        if ($this->refundFee <= 0) {
            throw new PaymentConfigurationException('退款金额必须大于0');
        }

        if ($this->refundFee > $this->totalFee) {
            throw new PaymentConfigurationException('退款金额不能大于订单总金额');
        }
    }

    private function validateFieldLengths(): void
    {
        if (strlen($this->outRefundNo) > 64) {
            throw new PaymentConfigurationException('商户退款单号长度不能超过64位');
        }

        if ('' !== $this->outTradeNo && strlen($this->outTradeNo) > 32) {
            throw new PaymentConfigurationException('商户订单号长度不能超过32位');
        }
    }

    /**
     * 转换为XML格式.
     */
    public function toXml(): string
    {
        $data = $this->toArray();

        $xml = '<xml>';
        foreach ($data as $key => $value) {
            $xmlNode = $this->buildXmlNode($key, $value);
            if (null !== $xmlNode) {
                $xml .= $xmlNode;
            }
        }
        $xml .= '</xml>';

        return $xml;
    }

    /**
     * 构建XML节点
     */
    private function buildXmlNode(string $key, mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (is_array($value)) {
            return "<{$key}><![CDATA[" . json_encode($value, JSON_UNESCAPED_UNICODE) . "]]></{$key}>";
        }

        if (is_bool($value)) {
            return "<{$key}><![CDATA[" . ($value ? 'Y' : 'N') . "]]></{$key}>";
        }

        // 确保value可以安全转换为字符串
        $stringValue = is_scalar($value) ? (string) $value : '';

        return "<{$key}><![CDATA[{$stringValue}]]></{$key}>";
    }

    /**
     * 转换为数组（用于签名生成）.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'appid' => $this->appId,
            'mch_id' => $this->mchId,
            'device_info' => $this->deviceInfo,
            'nonce_str' => $this->nonceStr,
            'sign_type' => $this->signType,
            'transaction_id' => $this->transactionId,
            'out_trade_no' => $this->outTradeNo,
            'out_refund_no' => $this->outRefundNo,
            'total_fee' => $this->totalFee,
            'refund_fee' => $this->refundFee,
            'refund_desc' => $this->refundDesc,
            'refund_account' => $this->refundAccount,
            'notify_url' => $this->notifyUrl,
            'sign' => $this->getSign(),
        ];

        // 过滤空值
        return array_filter($data, fn ($value) => null !== $value && '' !== $value);
    }

    // Getter and Setter methods

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function setAppId(string $appId): void
    {
        $this->appId = $appId;
    }

    public function getMchId(): string
    {
        return $this->mchId;
    }

    public function setMchId(string $mchId): void
    {
        $this->mchId = $mchId;
    }

    public function getNonceStr(): string
    {
        return $this->nonceStr;
    }

    public function setNonceStr(string $nonceStr): void
    {
        $this->nonceStr = $nonceStr;
    }

    public function getSign(): string
    {
        return $this->sign;
    }

    public function setSign(string $sign): void
    {
        $this->sign = $sign;
    }

    public function getOutTradeNo(): string
    {
        return $this->outTradeNo;
    }

    public function setOutTradeNo(string $outTradeNo): void
    {
        $this->outTradeNo = $outTradeNo;
    }

    public function getOutRefundNo(): string
    {
        return $this->outRefundNo;
    }

    public function setOutRefundNo(string $outRefundNo): void
    {
        $this->outRefundNo = $outRefundNo;
    }

    public function getTotalFee(): int
    {
        return $this->totalFee;
    }

    public function setTotalFee(int $totalFee): void
    {
        $this->totalFee = $totalFee;
    }

    public function getRefundFee(): int
    {
        return $this->refundFee;
    }

    public function setRefundFee(int $refundFee): void
    {
        $this->refundFee = $refundFee;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(?string $transactionId): void
    {
        $this->transactionId = $transactionId;
    }

    public function getRefundDesc(): ?string
    {
        return $this->refundDesc;
    }

    public function setRefundDesc(?string $refundDesc): void
    {
        $this->refundDesc = $refundDesc;
    }

    public function getRefundAccount(): ?string
    {
        return $this->refundAccount;
    }

    public function setRefundAccount(?string $refundAccount): void
    {
        $this->refundAccount = $refundAccount;
    }

    public function getNotifyUrl(): ?string
    {
        return $this->notifyUrl;
    }

    public function setNotifyUrl(?string $notifyUrl): void
    {
        $this->notifyUrl = $notifyUrl;
    }

    public function getDeviceInfo(): ?string
    {
        return $this->deviceInfo;
    }

    public function setDeviceInfo(?string $deviceInfo): void
    {
        $this->deviceInfo = $deviceInfo;
    }

    public function getSignType(): ?string
    {
        return $this->signType;
    }

    public function setSignType(?string $signType): void
    {
        $this->signType = $signType;
    }

    public function getCertPath(): ?string
    {
        return $this->certPath;
    }

    public function setCertPath(?string $certPath): void
    {
        $this->certPath = $certPath;
    }

    public function getKeyPath(): ?string
    {
        return $this->keyPath;
    }

    public function setKeyPath(?string $keyPath): void
    {
        $this->keyPath = $keyPath;
    }

    /**
     * 生成随机字符串.
     */
    public static function generateNonceStr(int $length = 32): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; ++$i) {
            $str .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $str;
    }

    /**
     * 生成退款单号.
     *
     * 格式：REFUND + YYYYMMDDHHMMSS + 6位随机数
     */
    public static function generateRefundNo(): string
    {
        $timestamp = date('YmdHis');
        $randomStr = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return 'REFUND' . $timestamp . $randomStr;
    }
}
