<?php

namespace WechatMiniProgramPayBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use WechatMiniProgramBundle\Entity\Account;
use WechatMiniProgramPayBundle\Exception\PaymentNotImplementedException;
use WechatPayBundle\Entity\PayOrder;

/**
 * 合单支付回调
 *
 * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter5_1_4.shtml
 */
class CombinePayCallbackController extends AbstractController
{
    public function __construct() {
    }

    #[Route(path: '/wechat-payment/mini-program/combine-pay/{appId}', name: 'wechat_mini_program_combine_pay_callback', methods: ['POST'])]
    public function __invoke(Account $account, PayOrder $order): never
    {
        throw new PaymentNotImplementedException('合单支付回调待实现');
    }
}