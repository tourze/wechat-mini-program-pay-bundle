<?php

namespace WechatMiniProgramPayBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Response;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;
use WechatMiniProgramBundle\Entity\Account;
use WechatMiniProgramPayBundle\Event\PayCallbackFailedEvent;
use WechatPayBundle\Entity\PayOrder;

/**
 * @internal
 */
#[CoversClass(PayCallbackFailedEvent::class)]
final class PayCallbackFailedEventTest extends AbstractEventTestCase
{
    private PayCallbackFailedEvent $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = new PayCallbackFailedEvent();
    }

    public function testGetSetAccount(): void
    {
        $account = $this->createMock(Account::class);
        $this->event->setAccount($account);
        $this->assertSame($account, $this->event->getAccount());
    }

    public function testGetSetPayOrder(): void
    {
        $payOrder = $this->createMock(PayOrder::class);
        $this->event->setPayOrder($payOrder);
        $this->assertSame($payOrder, $this->event->getPayOrder());
    }

    public function testGetSetResponse(): void
    {
        $response = new Response('test_content');
        $this->event->setResponse($response);
        $this->assertSame($response, $this->event->getResponse());
    }
}
