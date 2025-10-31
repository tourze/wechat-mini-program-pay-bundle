<?php

namespace WechatMiniProgramPayBundle\Event;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;
use WechatMiniProgramBundle\Entity\Account;
use WechatPayBundle\Entity\PayOrder;

class PayCallbackSuccessEvent extends Event
{
    private PayOrder $payOrder;

    private Account $account;

    /** @var array<string, mixed> */
    private array $decryptData = [];

    private ?Response $response = null;

    public function getPayOrder(): PayOrder
    {
        return $this->payOrder;
    }

    public function setPayOrder(PayOrder $payOrder): void
    {
        $this->payOrder = $payOrder;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): void
    {
        $this->account = $account;
    }

    /** @return array<string, mixed> */
    public function getDecryptData(): array
    {
        return $this->decryptData;
    }

    /** @param array<string, mixed> $decryptData */
    public function setDecryptData(array $decryptData): void
    {
        $this->decryptData = $decryptData;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function setResponse(?Response $response): void
    {
        $this->response = $response;
    }
}
