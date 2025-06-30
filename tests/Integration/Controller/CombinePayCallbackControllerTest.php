<?php

namespace WechatMiniProgramPayBundle\Tests\Integration\Controller;

use PHPUnit\Framework\TestCase;
use WechatMiniProgramPayBundle\Controller\CombinePayCallbackController;
use WechatMiniProgramPayBundle\Exception\PaymentNotImplementedException;

class CombinePayCallbackControllerTest extends TestCase
{
    public function testControllerThrowsException(): void
    {
        $controller = new CombinePayCallbackController();
        
        $account = $this->createMock(\WechatMiniProgramBundle\Entity\Account::class);
        $payOrder = $this->createMock(\WechatPayBundle\Entity\PayOrder::class);
        
        $this->expectException(PaymentNotImplementedException::class);
        $this->expectExceptionMessage('合单支付回调待实现');
        
        $controller->__invoke($account, $payOrder);
    }
}