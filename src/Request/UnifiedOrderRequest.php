<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Request;

use Tourze\PaymentContracts\Enum\PaymentType;
use WechatMiniProgramBundle\Request\WithAccountRequest;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;

/**
 * 微信支付统一下单请求
 *
 * 支持微信支付V2版本的统一下单接口，兼容多种支付类型：
 * - JSAPI：小程序支付、公众号支付
 * - NATIVE：PC网站扫码支付
 * - APP：移动应用支付
 * - H5：手机网站支付
 *
 * @see https://pay.weixin.qq.com/doc/v2/merchant/4011940985
 */
class UnifiedOrderRequest extends WithAccountRequest
{
    // 必填参数
    private string $appId = '';

    private string $mchId = '';

    private string $nonceStr = '';

    private string $sign = '';

    private string $body = '';

    private string $outTradeNo = '';

    private int $totalFee = 0;

    private string $spbillCreateIp = '';

    private string $notifyUrl = '';

    private string $tradeType = '';

    // 条件必填参数
    private ?string $openId = null;      // JSAPI支付必填

    private ?string $productId = null;   // NATIVE支付必填

    /** @var array<string, mixed>|null */
    private ?array $sceneInfo = null;    // H5支付必填

    // 选填参数
    private ?string $deviceInfo = null;

    private ?string $signType = 'MD5';   // 默认MD5签名

    private ?string $detail = null;

    private ?string $attach = null;

    private ?string $feeType = 'CNY';    // 默认人民币

    private ?\DateTimeInterface $timeStart = null;

    private ?\DateTimeInterface $timeExpire = null;

    private ?string $goodsTag = null;

    private ?string $limitPay = null;

    private ?string $receipt = null;

    private ?bool $profitSharing = null;

    public function getRequestPath(): string
    {
        return '/pay/unifiedorder';
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

        return [
            'headers' => [
                'Content-Type' => 'application/xml; charset=utf-8',
                'User-Agent' => 'WechatMiniProgramPayBundle/1.0',
            ],
            'body' => $xmlData,
        ];
    }

    /**
     * 验证必填参数.
     */
    public function validate(): void
    {
        $requiredFields = [
            'appId' => $this->appId,
            'mchId' => $this->mchId,
            'nonceStr' => $this->nonceStr,
            'body' => $this->body,
            'outTradeNo' => $this->outTradeNo,
            'totalFee' => $this->totalFee,
            'spbillCreateIp' => $this->spbillCreateIp,
            'notifyUrl' => $this->notifyUrl,
            'tradeType' => $this->tradeType,
        ];

        foreach ($requiredFields as $field => $value) {
            if ('' === $value || ('totalFee' === $field && 0 === $value)) {
                throw new PaymentConfigurationException("必填参数 {$field} 不能为空");
            }
        }

        // 根据交易类型验证条件必填参数
        $this->validateByTradeType();

        // 验证金额
        if ($this->totalFee <= 0) {
            throw new PaymentConfigurationException('订单金额必须大于0');
        }

        // 验证订单号长度
        if (strlen($this->outTradeNo) > 32) {
            throw new PaymentConfigurationException('商户订单号长度不能超过32位');
        }
    }

    /**
     * 根据交易类型验证条件必填参数.
     */
    private function validateByTradeType(): void
    {
        switch ($this->tradeType) {
            case 'JSAPI':
                if (null === $this->openId || '' === $this->openId) {
                    throw new PaymentConfigurationException('JSAPI支付必须提供openId');
                }
                break;
            case 'NATIVE':
                if (null === $this->productId || '' === $this->productId) {
                    throw new PaymentConfigurationException('NATIVE支付必须提供productId');
                }
                break;
            case 'H5':
                if (null === $this->sceneInfo || [] === $this->sceneInfo) {
                    throw new PaymentConfigurationException('H5支付必须提供sceneInfo场景信息');
                }
                break;
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
            'body' => $this->body,
            'detail' => $this->detail,
            'attach' => $this->attach,
            'out_trade_no' => $this->outTradeNo,
            'fee_type' => $this->feeType,
            'total_fee' => $this->totalFee,
            'spbill_create_ip' => $this->spbillCreateIp,
            'time_start' => $this->timeStart?->format('YmdHis'),
            'time_expire' => $this->timeExpire?->format('YmdHis'),
            'goods_tag' => $this->goodsTag,
            'notify_url' => $this->notifyUrl,
            'trade_type' => $this->tradeType,
            'product_id' => $this->productId,
            'limit_pay' => $this->limitPay,
            'openid' => $this->openId,
            'receipt' => $this->receipt,
            'profit_sharing' => null === $this->profitSharing ? null : ($this->profitSharing ? 'Y' : 'N'),
            'scene_info' => null !== $this->sceneInfo ? json_encode($this->sceneInfo, JSON_UNESCAPED_UNICODE) : null,
            'sign' => $this->getSign(),
        ];

        // 过滤空值
        return array_filter($data, static fn ($value): bool => null !== $value && '' !== $value && false !== $value);
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

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
    }

    public function getOutTradeNo(): string
    {
        return $this->outTradeNo;
    }

    public function setOutTradeNo(string $outTradeNo): void
    {
        $this->outTradeNo = $outTradeNo;
    }

    public function getTotalFee(): int
    {
        return $this->totalFee;
    }

    public function setTotalFee(int $totalFee): void
    {
        $this->totalFee = $totalFee;
    }

    public function getSpbillCreateIp(): string
    {
        return $this->spbillCreateIp;
    }

    public function setSpbillCreateIp(string $spbillCreateIp): void
    {
        $this->spbillCreateIp = $spbillCreateIp;
    }

    public function getNotifyUrl(): string
    {
        return $this->notifyUrl;
    }

    public function setNotifyUrl(string $notifyUrl): void
    {
        $this->notifyUrl = $notifyUrl;
    }

    public function getTradeType(): string
    {
        return $this->tradeType;
    }

    public function setTradeType(string $tradeType): void
    {
        $this->tradeType = $tradeType;
    }

    public function getOpenId(): ?string
    {
        return $this->openId;
    }

    public function setOpenId(?string $openId): void
    {
        $this->openId = $openId;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(?string $productId): void
    {
        $this->productId = $productId;
    }

    /** @return array<string, mixed>|null */
    public function getSceneInfo(): ?array
    {
        return $this->sceneInfo;
    }

    /** @param array<string, mixed>|null $sceneInfo */
    public function setSceneInfo(?array $sceneInfo): void
    {
        $this->sceneInfo = $sceneInfo;
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

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function setDetail(?string $detail): void
    {
        $this->detail = $detail;
    }

    public function getAttach(): ?string
    {
        return $this->attach;
    }

    public function setAttach(?string $attach): void
    {
        $this->attach = $attach;
    }

    public function getFeeType(): ?string
    {
        return $this->feeType;
    }

    public function setFeeType(?string $feeType): void
    {
        $this->feeType = $feeType;
    }

    public function getTimeStart(): ?\DateTimeInterface
    {
        return $this->timeStart;
    }

    public function setTimeStart(?\DateTimeInterface $timeStart): void
    {
        $this->timeStart = $timeStart;
    }

    public function getTimeExpire(): ?\DateTimeInterface
    {
        return $this->timeExpire;
    }

    public function setTimeExpire(?\DateTimeInterface $timeExpire): void
    {
        $this->timeExpire = $timeExpire;
    }

    public function getGoodsTag(): ?string
    {
        return $this->goodsTag;
    }

    public function setGoodsTag(?string $goodsTag): void
    {
        $this->goodsTag = $goodsTag;
    }

    public function getLimitPay(): ?string
    {
        return $this->limitPay;
    }

    public function setLimitPay(?string $limitPay): void
    {
        $this->limitPay = $limitPay;
    }

    public function getReceipt(): ?string
    {
        return $this->receipt;
    }

    public function setReceipt(?string $receipt): void
    {
        $this->receipt = $receipt;
    }

    public function getProfitSharing(): ?bool
    {
        return $this->profitSharing;
    }

    public function setProfitSharing(?bool $profitSharing): void
    {
        $this->profitSharing = $profitSharing;
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
     * 根据PaymentType设置TradeType.
     */
    public function setTradeTypeFromPaymentType(PaymentType $paymentType): void
    {
        $tradeType = match ($paymentType) {
            PaymentType::WECHAT_MINI_PROGRAM => 'JSAPI',
            PaymentType::WECHAT_OFFICIAL_ACCOUNT => 'JSAPI',
            PaymentType::WECHAT_JSAPI => 'JSAPI',
            PaymentType::WECHAT_APP => 'APP',
            PaymentType::LEGACY_WECHAT_PAY => 'JSAPI',
            default => 'JSAPI',
        };

        $this->setTradeType($tradeType);
    }

    /**
     * 设置H5支付场景信息.
     *
     * @param array<string, mixed> $sceneInfo
     */
    public function setH5SceneInfo(array $sceneInfo): void
    {
        $this->setTradeType('H5');
        $this->setSceneInfo($sceneInfo);
    }

    /**
     * 设置NATIVE支付产品ID.
     */
    public function setNativeProductId(string $productId): void
    {
        $this->setTradeType('NATIVE');
        $this->setProductId($productId);
    }
}
