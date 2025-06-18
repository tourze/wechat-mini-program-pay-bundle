<?php

namespace WechatMiniProgramPayBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;

class WechatMiniProgramPayBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            \WechatPayBundle\WechatPayBundle::class => ['all' => true],
        ];
    }
}
