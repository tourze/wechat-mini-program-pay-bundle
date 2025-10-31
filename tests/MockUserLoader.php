<?php

namespace WechatMiniProgramPayBundle\Tests;

use Tourze\WechatMiniProgramAppIDContracts\MiniProgramInterface;
use Tourze\WechatMiniProgramUserContracts\UserInterface;
use Tourze\WechatMiniProgramUserContracts\UserLoaderInterface;

/**
 * Mock UserLoader 实现，避免数据库访问.
 */
class MockUserLoader implements UserLoaderInterface
{
    public function loadUserByOpenId(string $openId): ?UserInterface
    {
        // 返回 null，表示用户不存在
        return null;
    }

    public function loadUserByUnionId(string $unionId): ?UserInterface
    {
        // 返回 null，表示用户不存在
        return null;
    }

    public function createUser(MiniProgramInterface $miniProgram, string $openId, ?string $unionId = null): UserInterface
    {
        return new MockUser($miniProgram, $openId, $unionId);
    }
}
