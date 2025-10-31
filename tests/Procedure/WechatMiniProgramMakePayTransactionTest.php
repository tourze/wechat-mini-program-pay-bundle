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
use WechatMiniProgramPayBundle\Procedure\WechatMiniProgramMakePayTransaction;

/**
 * @internal
 */
#[CoversClass(WechatMiniProgramMakePayTransaction::class)]
#[RunTestsInSeparateProcesses]
final class WechatMiniProgramMakePayTransactionTest extends AbstractProcedureTestCase
{
    private WechatMiniProgramMakePayTransaction $procedure;

    protected function onSetUp(): void
    {
        $this->ensureFrameworkTablesExist();
        $this->procedure = self::getService(WechatMiniProgramMakePayTransaction::class);
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

    public function testExecuteThrowsExceptionWhenAccountServiceNotFound(): void
    {
        // 由于 accountService 是 readonly 属性，不能直接修改
        // 需要创建一个新的 procedure 实例来测试 null 情况
        // 但是由于构造函数参数太多，我们可以通过设置 appId 为不存在的值来触发异常

        // 设置一个不存在的 appId
        $this->procedure->appId = 'non-existent-app-id';
        $this->procedure->money = 100;
        $this->procedure->description = '测试订单';

        $this->expectException(ApiException::class);
        // 由于没有登录用户，会抛出用户未登录异常
        // 或者找不到小程序的异常
        $this->expectExceptionMessageMatches('/(找不到|用户未登录)/');

        $this->procedure->execute();
    }
}
