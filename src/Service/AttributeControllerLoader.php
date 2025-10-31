<?php

namespace WechatMiniProgramPayBundle\Service;

use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\RouteCollection;
use Tourze\RoutingAutoLoaderBundle\Service\RoutingAutoLoaderInterface;
use WechatMiniProgramPayBundle\Controller\CombinePayCallbackController;
use WechatMiniProgramPayBundle\Controller\PayCallbackController;
use WechatMiniProgramPayBundle\Controller\RefundCallbackController;
use WechatMiniProgramPayBundle\Controller\UnifiedOrderController;

#[AutoconfigureTag(name: 'routing.loader')]
class AttributeControllerLoader extends Loader implements RoutingAutoLoaderInterface
{
    private AttributeRouteControllerLoader $controllerLoader;

    public function __construct()
    {
        parent::__construct();
        $this->controllerLoader = new AttributeRouteControllerLoader();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        return $this->autoload();
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return false;
    }

    public function autoload(): RouteCollection
    {
        $collection = new RouteCollection();

        // 注册所有控制器
        $collection->addCollection($this->controllerLoader->load(PayCallbackController::class));
        $collection->addCollection($this->controllerLoader->load(CombinePayCallbackController::class));
        $collection->addCollection($this->controllerLoader->load(RefundCallbackController::class));
        $collection->addCollection($this->controllerLoader->load(UnifiedOrderController::class));

        return $collection;
    }
}
