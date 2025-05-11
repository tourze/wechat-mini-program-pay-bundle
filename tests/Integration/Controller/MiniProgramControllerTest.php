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
use WechatMiniProgramPayBundle\Controller\MiniProgramController;
use WechatPayBundle\Entity\Merchant;
use WechatPayBundle\Entity\PayOrder;
use WechatPayBundle\Entity\RefundOrder;
use WechatPayBundle\Enum\PayOrderStatus;
use WechatPayBundle\Repository\PayOrderRepository;
use WechatPayBundle\Repository\RefundOrderRepository;

class MiniProgramControllerTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private LoggerInterface|MockObject $logger;
    private EventDispatcherInterface|MockObject $eventDispatcher;
    private PayOrderRepository|MockObject $payOrderRepository;
    private RefundOrderRepository|MockObject $refundOrderRepository;
    private MiniProgramController $controller;
    private Account $account;
    private PayOrder $payOrder;
    private RefundOrder $refundOrder;
    private Merchant $merchant;

    protected function setUp(): void
    {
        // 创建mock对象
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->payOrderRepository = $this->createMock(PayOrderRepository::class);
        $this->refundOrderRepository = $this->createMock(RefundOrderRepository::class);
        
        // 创建controller
        $this->controller = new MiniProgramController($this->entityManager);
        
        // 创建测试所需的实体
        $this->merchant = new Merchant();
        $this->merchant->setMchId('test_mch_id');
        $this->merchant->setApiKey('test_api_key');
        
        $this->account = new Account();
        $this->account->setAppId('test_app_id');
        
        $this->payOrder = new PayOrder();
        $this->payOrder->setAppId('test_app_id');
        $this->payOrder->setTradeNo('test_trader_no');
        $this->payOrder->setStatus(PayOrderStatus::INIT);
        $this->payOrder->setMerchant($this->merchant);
        
        $this->refundOrder = new RefundOrder();
        $this->refundOrder->setPayOrder($this->payOrder);
        $this->refundOrder->setAppId('test_app_id');
    }

    /**
     * 测试当appId不匹配时的异常抛出
     */
    public function testPayCallback_WithAppIdMismatch(): void
    {
        // 改变AppId使其不匹配
        $this->payOrder->setAppId('different_app_id');
        
        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        
        $request = new Request();
        
        $this->controller->payCallback(
            $this->account,
            $this->payOrder,
            $this->logger,
            $this->eventDispatcher,
            $this->payOrderRepository,
            $request
        );
    }
    
    /**
     * 测试当订单已成功时的处理
     */
    public function testPayCallback_WhenAlreadySuccess(): void
    {
        // 设置订单状态为已成功
        $this->payOrder->setStatus(PayOrderStatus::SUCCESS);
        
        $request = new Request();
        
        $response = $this->controller->payCallback(
            $this->account,
            $this->payOrder,
            $this->logger,
            $this->eventDispatcher,
            $this->payOrderRepository,
            $request
        );
        
        // 断言结果
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('', $response->getContent());
    }
    
    /**
     * 测试退款回调时appId不匹配的情况
     */
    public function testRefundCallback_WithAppIdMismatch(): void
    {
        // 改变AppId使其不匹配
        $this->payOrder->setAppId('different_app_id');
        
        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        
        $request = new Request();
        
        $this->controller->refundCallback(
            $this->account,
            $this->refundOrder,
            $this->logger,
            $this->refundOrderRepository,
            $request
        );
    }
} 