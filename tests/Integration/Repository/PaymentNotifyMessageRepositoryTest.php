<?php

namespace WechatMiniProgramPayBundle\Tests\Integration\Repository;

use PHPUnit\Framework\TestCase;
use WechatMiniProgramPayBundle\Repository\PaymentNotifyMessageRepository;

class PaymentNotifyMessageRepositoryTest extends TestCase
{
    public function testRepository(): void
    {
        $registry = $this->createMock(\Doctrine\Persistence\ManagerRegistry::class);
        
        $repository = new PaymentNotifyMessageRepository($registry);
        
        $this->assertInstanceOf(PaymentNotifyMessageRepository::class, $repository);
    }
}