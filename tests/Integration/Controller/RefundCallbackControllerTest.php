<?php

namespace WechatMiniProgramPayBundle\Tests\Integration\Controller;

use PHPUnit\Framework\TestCase;
use WechatMiniProgramPayBundle\Controller\RefundCallbackController;

class RefundCallbackControllerTest extends TestCase
{
    public function testControllerClass(): void
    {
        $this->assertTrue(class_exists(RefundCallbackController::class));
    }
}