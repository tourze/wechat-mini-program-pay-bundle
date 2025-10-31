<?php

namespace WechatMiniProgramPayBundle\Procedure;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\JsonRPCLogBundle\Attribute\Log;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;
use WechatPayBundle\Repository\PayOrderRepository;

/**
 * 微信小程序获取支付参数.
 *
 * @see https://pay.weixin.qq.com/wiki/doc/apiv3/open/pay/chapter2_8_0.shtml
 */
#[IsGranted(attribute: 'IS_AUTHENTICATED_FULLY')]
#[Log]
#[MethodDoc(summary: '微信小程序获取支付参数')]
#[MethodExpose(method: 'WechatMiniProgramGetPayConfig')]
#[MethodTag(name: '微信支付')]
class WechatMiniProgramGetPayConfig extends LockableProcedure
{
    #[MethodParam(description: '支付单号')]
    public string $payOrderId;

    public function __construct(
        private readonly PayOrderRepository $payOrderRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $payOrder = $this->payOrderRepository->find($this->payOrderId);
        if (null === $payOrder) {
            throw new ApiException('PayOrder not found');
        }

        $merchant = $payOrder->getMerchant();
        if (null === $merchant) {
            throw new ApiException('Merchant not found');
        }

        $prepayId = $payOrder->getPrepayId();
        if (null === $prepayId) {
            throw new ApiException('PrepayId not found');
        }

        // @link https://github.com/wechatpay-apiv3/wechatpay-php#%E7%AD%BE%E5%90%8D
        $merchantPrivateKeyInstance = Rsa::from($merchant->getPemKey());
        $params = [
            'appId' => $payOrder->getAppId(),
            'timeStamp' => (string) Formatter::timestamp(),
            'nonceStr' => Formatter::nonce(),
            'package' => "prepay_id={$prepayId}",
        ];
        $params += [
            'paySign' => Rsa::sign(
                Formatter::joinedByLineFeed(...array_values(array_filter($params, fn ($value) => null !== $value))),
                $merchantPrivateKeyInstance
            ),
            'signType' => 'RSA',
        ];

        return $params;
    }
}
