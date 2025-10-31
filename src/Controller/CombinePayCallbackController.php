<?php

namespace WechatMiniProgramPayBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use WechatMiniProgramBundle\Entity\Account;
use WechatMiniProgramPayBundle\Exception\PaymentNotImplementedException;

/**
 * 合单支付回调.
 *
 * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter5_1_4.shtml
 */
final class CombinePayCallbackController extends AbstractController
{
    public function __construct()
    {
    }

    #[Route(path: '/wechat-payment/mini-program/combine-pay/{appId}', name: 'wechat_mini_program_combine_pay_callback', methods: ['POST'])]
    public function __invoke(Account $account): never
    {
        throw new PaymentNotImplementedException('合单支付回调待实现');
    }
}
