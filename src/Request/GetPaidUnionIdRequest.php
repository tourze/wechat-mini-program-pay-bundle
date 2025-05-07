<?php

namespace WechatMiniProgramPayBundle\Request;

use WechatMiniProgramBundle\Request\WithAccountRequest;

/**
 * 支付后获取 Unionid
 *
 * 该接口用于在用户支付完成后，获调用本接口前需要用户完成支付，用户支付完成后，取该用户的 UnionId，无需用户授权。本接口支付后的五分钟内有效。
 *
 * @see https://developers.weixin.qq.com/miniprogram/dev/OpenApiDoc/user-info/basic-info/getPaidUnionid.html
 */
class GetPaidUnionIdRequest extends WithAccountRequest
{
    /**
     * @var string 支付用户唯一标识
     */
    private string $openId;

    /**
     * @var string|null 微信支付订单号
     */
    private ?string $transactionId = null;

    /**
     * @var string|null 微信支付分配的商户号，和商户订单号配合使用
     */
    private ?string $mchId = null;

    /**
     * @var string|null 微信支付商户订单号，和商户号配合使用
     */
    private ?string $outTradeNo = null;

    public function getRequestPath(): string
    {
        return '/wxa/getpaidunionid';
    }

    public function getRequestOptions(): ?array
    {
        $json = [
            'openid' => $this->getOpenId(),
        ];
        if (null !== $this->getTransactionId()) {
            $json['transaction_id'] = $this->getTransactionId();
        }
        if (null !== $this->getMchId()) {
            $json['mch_id'] = $this->getMchId();
        }
        if (null !== $this->getOutTradeNo()) {
            $json['out_trade_no'] = $this->getOutTradeNo();
        }

        return [
            'json' => $json,
        ];
    }

    public function getOpenId(): string
    {
        return $this->openId;
    }

    public function setOpenId(string $openId): void
    {
        $this->openId = $openId;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(?string $transactionId): void
    {
        $this->transactionId = $transactionId;
    }

    public function getMchId(): ?string
    {
        return $this->mchId;
    }

    public function setMchId(?string $mchId): void
    {
        $this->mchId = $mchId;
    }

    public function getOutTradeNo(): ?string
    {
        return $this->outTradeNo;
    }

    public function setOutTradeNo(?string $outTradeNo): void
    {
        $this->outTradeNo = $outTradeNo;
    }
}
