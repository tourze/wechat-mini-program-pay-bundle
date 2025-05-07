<?php

namespace WechatMiniProgramPayBundle\Procedure;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\JsonRPCLogBundle\Attribute\Log;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;
use WechatPayBundle\Repository\PayOrderRepository;

/**
 * 微信小程序获取支付参数
 *
 * @see https://pay.weixin.qq.com/wiki/doc/apiv3/open/pay/chapter2_8_0.shtml
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Log]
#[MethodExpose('WechatMiniProgramGetPayConfig')]
class WechatMiniProgramGetPayConfig extends LockableProcedure
{
    #[MethodParam('支付单号')]
    public string $payOrderId;

    public function __construct(
        private readonly PayOrderRepository $payOrderRepository,
    ) {
    }

    public function execute(): array
    {
        $payOrder = $this->payOrderRepository->find($this->payOrderId);

        // @link https://github.com/wechatpay-apiv3/wechatpay-php#%E7%AD%BE%E5%90%8D
        $merchantPrivateKeyInstance = Rsa::from($payOrder->getMerchant()->getPemKey());
        $params = [
            'appId' => $payOrder->getAppId(),
            'timeStamp' => (string) Formatter::timestamp(),
            'nonceStr' => Formatter::nonce(),
            'package' => "prepay_id={$payOrder->getPrepayId()}",
        ];
        $params += [
            'paySign' => Rsa::sign(
                Formatter::joinedByLineFeed(...array_values($params)),
                $merchantPrivateKeyInstance
            ),
            'signType' => 'RSA',
        ];

        return $params;
    }
}
