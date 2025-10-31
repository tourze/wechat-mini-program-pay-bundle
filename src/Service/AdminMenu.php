<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Service;

use Knp\Menu\ItemInterface;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;
use WechatMiniProgramPayBundle\Entity\PaymentNotifyMessage;

/**
 * 微信小程序支付管理菜单服务
 */
readonly class AdminMenu implements MenuProviderInterface
{
    public function __construct(
        private LinkGeneratorInterface $linkGenerator,
    ) {
    }

    public function __invoke(ItemInterface $item): void
    {
        if (null === $item->getChild('支付管理')) {
            $item->addChild('支付管理');
        }

        $paymentMenu = $item->getChild('支付管理');
        if (null === $paymentMenu) {
            return;
        }

        // 微信小程序支付子菜单
        if (null === $paymentMenu->getChild('微信小程序支付')) {
            $paymentMenu->addChild('微信小程序支付');
        }

        $wechatMiniProgramMenu = $paymentMenu->getChild('微信小程序支付');
        if (null === $wechatMiniProgramMenu) {
            return;
        }

        // 支付回调消息管理菜单
        $wechatMiniProgramMenu->addChild('支付回调消息')
            ->setUri($this->linkGenerator->getCurdListPage(PaymentNotifyMessage::class))
            ->setAttribute('icon', 'fas fa-bell')
        ;
    }
}
