<?php

namespace WechatMiniProgramPayBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class WechatMiniProgramPayExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }
}
