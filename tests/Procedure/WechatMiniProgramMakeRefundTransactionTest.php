<?php

namespace WechatMiniProgramPayBundle\Tests\Procedure;

use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Tests\AbstractProcedureTestCase;
use WechatMiniProgramBundle\Entity\Account;
use WechatMiniProgramPayBundle\Procedure\WechatMiniProgramMakeRefundTransaction;
use WechatPayBundle\Entity\PayOrder;
use WechatPayBundle\Entity\RefundOrder;
use WechatPayBundle\Enum\PayOrderStatus;

/**
 * @internal
 */
#[CoversClass(WechatMiniProgramMakeRefundTransaction::class)]
#[RunTestsInSeparateProcesses]
final class WechatMiniProgramMakeRefundTransactionTest extends AbstractProcedureTestCase
{
    private WechatMiniProgramMakeRefundTransaction $procedure;

    protected function onSetUp(): void
    {
        $this->ensureFrameworkTablesExist();
        $this->procedure = self::getService(WechatMiniProgramMakeRefundTransaction::class);
    }

    private function ensureFrameworkTablesExist(): void
    {
        $connection = self::getEntityManager()->getConnection();
        $schemaManager = $connection->createSchemaManager();

        try {
            if (!$schemaManager->tablesExist(['biz_user_biz_role'])) {
                $table = new Table('biz_user_biz_role');
                $table->addColumn('biz_user_id', 'integer');
                $table->addColumn('biz_role_id', 'integer');
                $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint(
                    new UnqualifiedName(Identifier::unquoted('pk_biz_user_biz_role')),
                    [
                        new UnqualifiedName(Identifier::unquoted('biz_user_id')),
                        new UnqualifiedName(Identifier::unquoted('biz_role_id')),
                    ],
                    false
                ));
                $schemaManager->createTable($table);
            }
        } catch (\Exception $e) {
            // 忽略表已存在或其他非关键错误
        }

        try {
            if (!$schemaManager->tablesExist(['biz_user'])) {
                $table = new Table('biz_user');
                $table->addColumn('id', 'integer', ['autoincrement' => true]);
                $table->addColumn('email', 'string', ['length' => 255, 'notnull' => false]);
                $table->addColumn('password', 'string', ['length' => 255, 'notnull' => false]);
                $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint(
                    new UnqualifiedName(Identifier::unquoted('pk_biz_user')),
                    [new UnqualifiedName(Identifier::unquoted('id'))],
                    false
                ));
                $schemaManager->createTable($table);
            }
        } catch (\Exception $e) {
            // 忽略表已存在或其他非关键错误
        }

        try {
            if (!$schemaManager->tablesExist(['biz_role'])) {
                $table = new Table('biz_role');
                $table->addColumn('id', 'integer', ['autoincrement' => true]);
                $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
                $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint(
                    new UnqualifiedName(Identifier::unquoted('pk_biz_role')),
                    [new UnqualifiedName(Identifier::unquoted('id'))],
                    false
                ));
                $schemaManager->createTable($table);
            }
        } catch (\Exception $e) {
            // 忽略表已存在或其他非关键错误
        }
    }

    public function testExecuteSuccessfully(): void
    {
        // 创建真实的 Account 实体并保存到数据库
        $account = new Account();
        $account->setName('Test Account');
        $account->setAppId('test-app-id');
        $account->setAppSecret('test-app-secret');
        $this->persistAndFlush($account);

        // 创建真实的 PayOrder 实体并保存到数据库
        $payOrder = new PayOrder();
        $payOrder->setAppId('test-app-id');
        $payOrder->setMchId('test-mch-id');
        $payOrder->setTradeType('JSAPI');
        $payOrder->setTradeNo('test-trade-no-' . time());
        $payOrder->setBody('Test Order');
        $payOrder->setFeeType('CNY');
        $payOrder->setTotalFee(100);
        $payOrder->setNotifyUrl('https://example.com/notify');
        $payOrder->setStatus(PayOrderStatus::INIT);
        $this->persistAndFlush($payOrder);

        $this->procedure->appId = 'test-app-id';
        $this->procedure->payOrderId = (int) $payOrder->getId();
        $this->procedure->reason = '测试退款';
        $this->procedure->money = 100;

        $result = $this->procedure->execute();

        $this->assertEquals(['__message' => '申请成功'], $result);

        // 验证退款订单是否被创建
        $em = self::getEntityManager();
        $refundOrders = $em->getRepository(RefundOrder::class)->findBy(['payOrder' => $payOrder]);
        $this->assertCount(1, $refundOrders);
        $this->assertEquals(100, $refundOrders[0]->getMoney());
        $this->assertEquals('测试退款', $refundOrders[0]->getReason());
    }

    public function testExecuteThrowsExceptionWhenAccountNotFound(): void
    {
        // 不在数据库中创建 Account，以触发找不到小程序的异常
        $this->procedure->appId = 'non-existent-app-id';
        $this->procedure->payOrderId = 123;
        $this->procedure->reason = '测试退款';
        $this->procedure->money = 100;

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('找不到小程序');

        $this->procedure->execute();
    }
}
