<?php

namespace WechatMiniProgramPayBundle\Service;

use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\RouteCollection;
use WechatMiniProgramPayBundle\Controller\CombinePayCallbackController;
use WechatMiniProgramPayBundle\Controller\PayCallbackController;
use WechatMiniProgramPayBundle\Controller\RefundCallbackController;

class AttributeControllerLoader
{
    private AttributeClassLoader $controllerLoader;

    public function __construct(AttributeClassLoader $controllerLoader)
    {
        $this->controllerLoader = $controllerLoader;
    }

    public function autoload(): RouteCollection
    {
        $collection = new RouteCollection();
        
        // 注册所有控制器
        $collection->addCollection($this->controllerLoader->load(PayCallbackController::class));
        $collection->addCollection($this->controllerLoader->load(CombinePayCallbackController::class));
        $collection->addCollection($this->controllerLoader->load(RefundCallbackController::class));
        
        return $collection;
    }
}