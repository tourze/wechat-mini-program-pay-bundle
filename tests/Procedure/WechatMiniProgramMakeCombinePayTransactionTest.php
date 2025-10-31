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
use WechatMiniProgramPayBundle\Procedure\WechatMiniProgramMakeCombinePayTransaction;

/**
 * @internal
 */
#[CoversClass(WechatMiniProgramMakeCombinePayTransaction::class)]
#[RunTestsInSeparateProcesses]
final class WechatMiniProgramMakeCombinePayTransactionTest extends AbstractProcedureTestCase
{
    private WechatMiniProgramMakeCombinePayTransaction $procedure;

    protected function onSetUp(): void
    {
        $this->ensureFrameworkTablesExist();
        $this->procedure = self::getService(WechatMiniProgramMakeCombinePayTransaction::class);
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

    public function testExecuteThrowsExceptionWhenAccountNotFound(): void
    {
        // 不在数据库中创建 Account，以触发找不到小程序的异常
        $this->procedure->appId = 'non-existent-app-id';
        $this->procedure->payOrderIds = ['test-order-1'];

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('找不到小程序');

        $this->procedure->execute();
    }

    public function testExecuteThrowsExceptionWhenNoPayOrderIds(): void
    {
        // 创建真实的 Account 实体并保存到数据库
        $account = new Account();
        $account->setName('Test Account');
        $account->setAppId('test-app-id');
        $account->setAppSecret('test-app-secret');
        $this->persistAndFlush($account);

        $this->procedure->appId = 'test-app-id';
        $this->procedure->payOrderIds = []; // 空数组以触发异常

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('找不到子订单信息');

        $this->procedure->execute();
    }
}
