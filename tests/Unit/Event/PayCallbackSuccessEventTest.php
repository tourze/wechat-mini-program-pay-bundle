<?php

namespace WechatMiniProgramPayBundle\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use WechatMiniProgramBundle\Entity\Account;
use WechatMiniProgramPayBundle\Event\PayCallbackSuccessEvent;
use WechatPayBundle\Entity\PayOrder;

class PayCallbackSuccessEventTest extends TestCase
{
    private PayCallbackSuccessEvent $event;
    private Account $account;
    private PayOrder $payOrder;
    private array $decryptData;

    protected function setUp(): void
    {
        $this->event = new PayCallbackSuccessEvent();
        $this->account = new Account();
        $this->payOrder = new PayOrder();
        $this->decryptData = ['test_key' => 'test_value'];
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

    public function testGetSetDecryptData(): void
    {
        $this->event->setDecryptData($this->decryptData);
        $this->assertSame($this->decryptData, $this->event->getDecryptData());
    }

    public function testGetSetResponse(): void
    {
        $response = new Response('test_content');
        $this->event->setResponse($response);
        $this->assertSame($response, $this->event->getResponse());
    }
}
