<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;
use WechatMiniProgramPayBundle\Service\AdminMenu;

/**
 * 管理菜单服务测试.
 *
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
final class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    private AdminMenu $adminMenu;

    protected function onSetUp(): void
    {
        $this->adminMenu = self::getService(AdminMenu::class);
    }

    public function testServiceImplementsMenuProviderInterface(): void
    {
        $this->assertInstanceOf(MenuProviderInterface::class, $this->adminMenu);
    }

    public function testServiceCanBeConstructed(): void
    {
        $this->assertInstanceOf(AdminMenu::class, $this->adminMenu);
    }

    public function testServiceIsCallable(): void
    {
        $this->assertIsCallable($this->adminMenu);
    }
}
