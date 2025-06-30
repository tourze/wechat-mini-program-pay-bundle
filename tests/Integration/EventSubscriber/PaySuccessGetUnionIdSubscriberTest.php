<?php

namespace WechatMiniProgramPayBundle\Tests\Integration\EventSubscriber;

use PHPUnit\Framework\TestCase;
use WechatMiniProgramPayBundle\EventSubscriber\PaySuccessGetUnionIdSubscriber;
use WechatMiniProgramPayBundle\Event\PayCallbackSuccessEvent;

class PaySuccessGetUnionIdSubscriberTest extends TestCase
{
    public function testOnPayCallbackSuccessWithExistingUnionId(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $userLoader = $this->createMock(\Tourze\WechatMiniProgramUserContracts\UserLoaderInterface::class);
        $client = $this->createMock(\WechatMiniProgramBundle\Service\Client::class);
        $entityManager = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        
        $subscriber = new PaySuccessGetUnionIdSubscriber($logger, $userLoader, $client, $entityManager);
        
        $user = $this->createMock(\Tourze\WechatMiniProgramUserContracts\UserInterface::class);
        $user->method('getUnionId')->willReturn('existing-union-id');
        
        $userLoader->method('loadUserByOpenId')->willReturn($user);
        
        $account = $this->createMock(\WechatMiniProgramBundle\Entity\Account::class);
        $payOrder = $this->createMock(\WechatPayBundle\Entity\PayOrder::class);
        $payOrder->method('getOpenId')->willReturn('test-openid');
        
        $event = new PayCallbackSuccessEvent();
        $event->setAccount($account);
        $event->setPayOrder($payOrder);
        
        $client->expects($this->never())->method('request');
        
        $subscriber->onPayCallbackSuccess($event);
    }
}