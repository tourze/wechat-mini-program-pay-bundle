<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * 创建缺失的数据库表.
 *
 * 用于解决测试环境中缺少关联表的问题
 */
#[When(env: 'test')]
class TestSchemaFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        if (!$manager instanceof EntityManagerInterface) {
            return;
        }

        $connection = $manager->getConnection();
        $schemaManager = $connection->createSchemaManager();

        // 检查 biz_user 表是否存在
        if (!$schemaManager->tablesExist(['biz_user'])) {
            $table = new Table('biz_user');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('email', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('password', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('username', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('roles', 'json', ['notnull' => false]);
            $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint(
                UnqualifiedName::unquoted('pk_biz_user'),
                [UnqualifiedName::unquoted('id')],
                false
            ));

            $schemaManager->createTable($table);
        }

        // 检查 biz_role 表是否存在
        if (!$schemaManager->tablesExist(['biz_role'])) {
            $table = new Table('biz_role');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('code', 'string', ['length' => 255, 'notnull' => false]);
            $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint(
                UnqualifiedName::unquoted('pk_biz_role'),
                [UnqualifiedName::unquoted('id')],
                false
            ));

            $schemaManager->createTable($table);
        }

        // 检查 biz_user_biz_role 表是否存在
        if (!$schemaManager->tablesExist(['biz_user_biz_role'])) {
            $table = new Table('biz_user_biz_role');
            $table->addColumn('biz_user_id', 'integer');
            $table->addColumn('biz_role_id', 'integer');
            $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint(
                UnqualifiedName::unquoted('pk_biz_user_biz_role'),
                [
                    UnqualifiedName::unquoted('biz_user_id'),
                    UnqualifiedName::unquoted('biz_role_id'),
                ],
                false
            ));

            $schemaManager->createTable($table);
        }

        // 检查 wechat_mini_program_user 表是否存在
        if (!$schemaManager->tablesExist(['wechat_mini_program_user'])) {
            $table = new Table('wechat_mini_program_user');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('open_id', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('union_id', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('avatar_url', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('wechat_mini_program_id', 'integer', ['notnull' => false]);
            $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint(
                UnqualifiedName::unquoted('pk_wechat_mini_program_user'),
                [UnqualifiedName::unquoted('id')],
                false
            ));

            $schemaManager->createTable($table);
        }

        // 检查 files 表是否存在，如果不存在则创建基本结构
        if (!$schemaManager->tablesExist(['files'])) {
            $table = new Table('files');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('path', 'string', ['length' => 500, 'notnull' => false]);
            $table->addColumn('size', 'integer', ['notnull' => false]);
            $table->addColumn('md5hash', 'string', ['length' => 32, 'notnull' => false]);
            $table->addColumn('created_at', 'datetime', ['notnull' => false]);
            $table->addColumn('updated_at', 'datetime', ['notnull' => false]);
            $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint(
                UnqualifiedName::unquoted('pk_files'),
                [UnqualifiedName::unquoted('id')],
                false
            ));
            $table->addIndex(['md5hash'], 'files_idx_md5hash');

            $schemaManager->createTable($table);
        }
    }

    public static function getGroups(): array
    {
        return ['schema', 'test'];
    }

    public function getOrder(): int
    {
        return 1; // 最高优先级，最先执行
    }
}
