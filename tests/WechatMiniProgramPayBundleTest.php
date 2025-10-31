<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;
use WechatMiniProgramPayBundle\WechatMiniProgramPayBundle;

/**
 * @internal
 */
#[CoversClass(WechatMiniProgramPayBundle::class)]
#[RunTestsInSeparateProcesses]
final class WechatMiniProgramPayBundleTest extends AbstractBundleTestCase
{
}
