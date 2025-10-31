<?php

namespace WechatMiniProgramPayBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Response;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;
use WechatMiniProgramBundle\Entity\Account;
use WechatMiniProgramPayBundle\Event\PayCallbackSuccessEvent;
use WechatPayBundle\Entity\PayOrder;

/**
 * @internal
 */
#[CoversClass(PayCallbackSuccessEvent::class)]
final class PayCallbackSuccessEventTest extends AbstractEventTestCase
{
    private PayCallbackSuccessEvent $event;

    /** @var array<string, mixed> */
    private array $decryptData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = new PayCallbackSuccessEvent();
        $this->decryptData = ['test_key' => 'test_value'];
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
