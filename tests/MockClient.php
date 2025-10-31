<?php

namespace WechatMiniProgramPayBundle\Tests;

use HttpClientBundle\Request\RequestInterface;
use Tourze\WechatMiniProgramAppIDContracts\MiniProgramInterface;

/**
 * 简单的微信小程序客户端 Mock 实现
 * 使用组合模式实现接口兼容性.
 */
final class MockClient
{
    /**
     * @return array<string, mixed>
     */
    public function request(RequestInterface $request): array
    {
        // 返回模拟的 unionid 响应
        return ['unionid' => 'mock-union-id'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getAccountAccessToken(MiniProgramInterface $account, bool $refresh = false): array
    {
        return [
            'access_token' => 'mock_access_token',
            'expires_in' => 3600,
            'start_time' => time(),
        ];
    }
}
