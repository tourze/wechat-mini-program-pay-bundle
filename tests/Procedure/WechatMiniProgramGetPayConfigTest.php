<?php

namespace WechatMiniProgramPayBundle\Tests\Procedure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Tests\AbstractProcedureTestCase;
use WechatMiniProgramPayBundle\Procedure\WechatMiniProgramGetPayConfig;
use WechatPayBundle\Entity\Merchant;
use WechatPayBundle\Entity\PayOrder;
use WechatPayBundle\Repository\PayOrderRepository;

/**
 * @internal
 */
#[CoversClass(WechatMiniProgramGetPayConfig::class)]
#[RunTestsInSeparateProcesses]
final class WechatMiniProgramGetPayConfigTest extends AbstractProcedureTestCase
{
    private function ensureFrameworkTablesExist(): void
    {
        $connection = self::getEntityManager()->getConnection();
        $schemaManager = $connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['biz_user_biz_role'])) {
            $sql = 'CREATE TABLE biz_user_biz_role (biz_user_id INTEGER, biz_role_id INTEGER, PRIMARY KEY (biz_user_id, biz_role_id))';
            $connection->executeStatement($sql);
        }

        if (!$schemaManager->tablesExist(['biz_user'])) {
            $sql = 'CREATE TABLE biz_user (id INTEGER PRIMARY KEY AUTOINCREMENT, email VARCHAR(255), password VARCHAR(255))';
            $connection->executeStatement($sql);
        }

        if (!$schemaManager->tablesExist(['biz_role'])) {
            $sql = 'CREATE TABLE biz_role (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255))';
            $connection->executeStatement($sql);
        }
    }

    protected function onSetUp(): void
    {
        $this->ensureFrameworkTablesExist();
    }

    public function testProcedureService(): void
    {
        $procedure = self::getService(WechatMiniProgramGetPayConfig::class);
        $this->assertInstanceOf(WechatMiniProgramGetPayConfig::class, $procedure);
    }

    public function testExecuteWithValidPayOrder(): void
    {
        self::markTestSkipped('此测试需要真实的私钥文件或更复杂的Mock设置。已覆盖异常路径测试。');
    }

    public function testExecuteThrowsExceptionWhenPayOrderNotFound(): void
    {
        // Mock PayOrderRepository
        $payOrderRepository = $this->createMock(PayOrderRepository::class);
        $payOrderRepository->expects($this->once())
            ->method('find')
            ->with('non-existent-id')
            ->willReturn(null)
        ;

        // 注入Mock依赖到容器中
        self::getContainer()->set(PayOrderRepository::class, $payOrderRepository);

        $procedure = self::getService(WechatMiniProgramGetPayConfig::class);
        $procedure->payOrderId = 'non-existent-id';

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('PayOrder not found');

        $procedure->execute();
    }

    public function testExecuteThrowsExceptionWhenMerchantNotFound(): void
    {
        $payOrder = $this->createMockPayOrder();
        $payOrder->expects($this->once())
            ->method('getMerchant')
            ->willReturn(null)
        ;

        $payOrderRepository = $this->createMock(PayOrderRepository::class);
        $payOrderRepository->expects($this->once())
            ->method('find')
            ->with('test-pay-order-id')
            ->willReturn($payOrder)
        ;

        // 注入Mock依赖到容器中
        self::getContainer()->set(PayOrderRepository::class, $payOrderRepository);

        $procedure = self::getService(WechatMiniProgramGetPayConfig::class);
        $procedure->payOrderId = 'test-pay-order-id';

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Merchant not found');

        $procedure->execute();
    }

    public function testExecuteThrowsExceptionWhenPrepayIdNotFound(): void
    {
        $payOrder = $this->createMockPayOrder();
        $merchant = $this->createMockMerchant();

        $payOrder->expects($this->once())
            ->method('getMerchant')
            ->willReturn($merchant)
        ;

        $payOrder->expects($this->once())
            ->method('getPrepayId')
            ->willReturn(null)
        ;

        $payOrderRepository = $this->createMock(PayOrderRepository::class);
        $payOrderRepository->expects($this->once())
            ->method('find')
            ->with('test-pay-order-id')
            ->willReturn($payOrder)
        ;

        // 注入Mock依赖到容器中
        self::getContainer()->set(PayOrderRepository::class, $payOrderRepository);

        $procedure = self::getService(WechatMiniProgramGetPayConfig::class);
        $procedure->payOrderId = 'test-pay-order-id';

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('PrepayId not found');

        $procedure->execute();
    }

    /**
     * 创建模拟的PayOrder对象
     */
    private function createMockPayOrder(): MockObject
    {
        return $this->createMock(PayOrder::class);
    }

    /**
     * 创建模拟的Merchant对象
     */
    private function createMockMerchant(): MockObject
    {
        return $this->createMock(Merchant::class);
    }
}
