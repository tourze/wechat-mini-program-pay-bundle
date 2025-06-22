<?php

namespace WechatMiniProgramPayBundle\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Attribute\Route;
use WechatMiniProgramBundle\Entity\Account;
use WechatPayBundle\Entity\PayOrder;
use WechatPayBundle\Repository\PayOrderRepository;

/**
 * 合单支付回调
 *
 * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter5_1_4.shtml
 */
class CombinePayCallbackController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly PayOrderRepository $payOrderRepository,
    ) {
    }

    #[Route(path: '/wechat-payment/mini-program/combine-pay/{appId}', name: 'wechat_mini_program_combine_pay_callback', methods: ['POST'])]
    public function __invoke(Account $account, PayOrder $order): never
    {
        throw new \LogicException('合单支付回调待实现');
    }
}