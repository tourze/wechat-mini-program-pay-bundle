<?php

namespace WechatMiniProgramPayBundle\Tests\Integration\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WechatMiniProgramBundle\Entity\Account;
use WechatMiniProgramPayBundle\Controller\PayCallbackController;
use WechatPayBundle\Entity\Merchant;
use WechatPayBundle\Entity\PayOrder;
use WechatPayBundle\Enum\PayOrderStatus;

class PayCallbackControllerTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private LoggerInterface|MockObject $logger;
    private EventDispatcherInterface|MockObject $eventDispatcher;
    private PayCallbackController $controller;
    private Account $account;
    private PayOrder $payOrder;
    private Merchant $merchant;

    public function testPayCallbackReturnsEmptyResponseForAlreadySuccessfulOrder(): void
    {
        // 设置支付订单状态为成功
        $this->payOrder->setStatus(PayOrderStatus::SUCCESS);

        // 创建请求
        $request = new Request();

        // 调用控制器
        $response = $this->controller->__invoke($this->account, $this->payOrder, $request);

        // 断言
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('', $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @group skip
     */
    public function testPayCallbackWithInvalidSignature(): void
    {
        // 这个测试需要更复杂的设置，包括模拟微信的签名等
        $this->markTestSkipped('需要模拟复杂的微信签名验证');
    }
    
    protected function setUp(): void
    {
        // 创建mock对象
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        // 创建controller
        $this->controller = new PayCallbackController(
            $this->entityManager,
            $this->logger,
            $this->eventDispatcher
        );

        // 创建测试所需的实体
        $this->merchant = new Merchant();
        $this->merchant->setMchId('test_mch_id');
        $this->merchant->setApiKey('test_api_key');

        $this->account = new Account();
        $this->account->setAppId('test_app_id');

        $this->payOrder = new PayOrder();
        $this->payOrder->setAppId('test_app_id');
        $this->payOrder->setMerchant($this->merchant);
        $this->payOrder->setStatus(PayOrderStatus::INIT);
    }
}