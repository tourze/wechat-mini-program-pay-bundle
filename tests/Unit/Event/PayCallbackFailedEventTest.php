<?php

namespace WechatMiniProgramPayBundle\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use WechatMiniProgramBundle\Entity\Account;
use WechatMiniProgramPayBundle\Event\PayCallbackFailedEvent;
use WechatPayBundle\Entity\PayOrder;

class PayCallbackFailedEventTest extends TestCase
{
    private PayCallbackFailedEvent $event;
    private Account $account;
    private PayOrder $payOrder;

    protected function setUp(): void
    {
        $this->event = new PayCallbackFailedEvent();
        $this->account = new Account();
        $this->payOrder = new PayOrder();
    }

    public function testGetSetAccount(): void
    {
        $this->event->setAccount($this->account);
        $this->assertSame($this->account, $this->event->getAccount());
    }

    public function testGetSetPayOrder(): void
    {
        $this->event->setPayOrder($this->payOrder);
        $this->assertSame($this->payOrder, $this->event->getPayOrder());
    }

    public function testGetSetResponse(): void
    {
        $response = new Response('test_content');
        $this->event->setResponse($response);
        $this->assertSame($response, $this->event->getResponse());
    }
}
