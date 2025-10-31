<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Service;

use Tourze\PaymentContracts\Enum\PaymentType;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;

/**
 * 支付类型映射器.
 *
 * 负责将PaymentType枚举映射到微信支付的trade_type，
 * 并提供各种支付类型所需的特殊参数配置
 */
class TradeTypeMapper
{
    // 微信支付支持的交易类型
    public const TRADE_TYPE_JSAPI = 'JSAPI';
    public const TRADE_TYPE_NATIVE = 'NATIVE';
    public const TRADE_TYPE_APP = 'APP';
    public const TRADE_TYPE_H5 = 'H5';

    /**
     * 将PaymentType映射为微信支付的trade_type.
     *
     * @param PaymentType $paymentType 支付类型
     *
     * @return string 微信支付的trade_type
     */
    public function mapToTradeType(PaymentType $paymentType): string
    {
        return match ($paymentType) {
            PaymentType::WECHAT_MINI_PROGRAM => self::TRADE_TYPE_JSAPI,
            PaymentType::WECHAT_OFFICIAL_ACCOUNT => self::TRADE_TYPE_JSAPI,
            PaymentType::WECHAT_JSAPI => self::TRADE_TYPE_JSAPI,
            PaymentType::WECHAT_APP => self::TRADE_TYPE_APP,
            PaymentType::LEGACY_WECHAT_PAY => self::TRADE_TYPE_JSAPI,
            default => throw new PaymentConfigurationException("不支持的支付类型: {$paymentType->value}"),
        };
    }

    /**
     * 获取指定trade_type需要的必填参数.
     *
     * @param string $tradeType 交易类型
     *
     * @return array<string> 必填参数列表
     */
    public function getRequiredParameters(string $tradeType): array
    {
        return match ($tradeType) {
            self::TRADE_TYPE_JSAPI => ['openid'],
            self::TRADE_TYPE_NATIVE => ['product_id'],
            self::TRADE_TYPE_H5 => ['scene_info'],
            self::TRADE_TYPE_APP => [],
            default => throw new PaymentConfigurationException("不支持的交易类型: {$tradeType}"),
        };
    }

    /**
     * 验证支付类型和参数的兼容性.
     *
     * @param PaymentType          $paymentType 支付类型
     * @param array<string, mixed> $parameters  参数
     *
     * @return array<string> 缺失的必填参数列表
     */
    public function validateParameters(PaymentType $paymentType, array $parameters): array
    {
        $tradeType = $this->mapToTradeType($paymentType);
        $requiredParams = $this->getRequiredParameters($tradeType);

        $missingParams = [];
        foreach ($requiredParams as $param) {
            $paramValue = $parameters[$param] ?? null;
            if (null === $paramValue || '' === $paramValue) {
                $missingParams[] = $param;
            }
        }

        return $missingParams;
    }

    /**
     * 获取H5支付的默认场景信息模板
     *
     * @param string      $type     场景类型 (IOS, Android, Wap)
     * @param string      $appName  应用名称
     * @param string|null $bundleId iOS Bundle ID 或 Android Package Name
     *
     * @return array<string, mixed> 场景信息
     */
    public function getH5SceneInfo(string $type, string $appName, ?string $bundleId = null): array
    {
        $sceneInfo = [
            'h5_info' => [
                'type' => $type,
                'app_name' => $appName,
            ],
        ];

        if (null !== $bundleId) {
            if ('IOS' === $type) {
                $sceneInfo['h5_info']['bundle_id'] = $bundleId;
            } elseif ('Android' === $type) {
                $sceneInfo['h5_info']['package_name'] = $bundleId;
            }
        }

        return $sceneInfo;
    }

    /**
     * 获取Wap支付的场景信息.
     *
     * @param string $wapUrl  WAP网站URL
     * @param string $wapName WAP网站名
     *
     * @return array<string, mixed> 场景信息
     */
    public function getWapSceneInfo(string $wapUrl, string $wapName): array
    {
        return [
            'h5_info' => [
                'type' => 'Wap',
                'wap_url' => $wapUrl,
                'wap_name' => $wapName,
            ],
        ];
    }

    /**
     * 检查支付类型是否支持
     *
     * @param PaymentType $paymentType 支付类型
     *
     * @return bool 是否支持
     */
    public function isPaymentTypeSupported(PaymentType $paymentType): bool
    {
        try {
            $this->mapToTradeType($paymentType);

            return true;
        } catch (PaymentConfigurationException) {
            return false;
        }
    }

    /**
     * 获取所有支持的支付类型.
     *
     * @return array<PaymentType> 支持的支付类型列表
     */
    public function getSupportedPaymentTypes(): array
    {
        return [
            PaymentType::WECHAT_MINI_PROGRAM,
            PaymentType::WECHAT_OFFICIAL_ACCOUNT,
            PaymentType::WECHAT_JSAPI,
            PaymentType::WECHAT_APP,
            PaymentType::LEGACY_WECHAT_PAY,
        ];
    }

    /**
     * 获取所有支持的交易类型.
     *
     * @return array<string> 支持的交易类型列表
     */
    public function getSupportedTradeTypes(): array
    {
        return [
            self::TRADE_TYPE_JSAPI,
            self::TRADE_TYPE_NATIVE,
            self::TRADE_TYPE_APP,
            self::TRADE_TYPE_H5,
        ];
    }

    /**
     * 根据交易类型获取支付类型标签.
     *
     * @param string $tradeType 交易类型
     *
     * @return string 支付类型标签
     */
    public function getTradeTypeLabel(string $tradeType): string
    {
        return match ($tradeType) {
            self::TRADE_TYPE_JSAPI => 'JSAPI支付(小程序/公众号)',
            self::TRADE_TYPE_NATIVE => 'Native支付(扫码)',
            self::TRADE_TYPE_APP => 'APP支付',
            self::TRADE_TYPE_H5 => 'H5支付(手机网站)',
            default => "未知类型({$tradeType})",
        };
    }

    /**
     * 根据用户代理字符串推荐H5支付类型.
     *
     * @param string $userAgent 用户代理字符串
     *
     * @return string H5支付类型 (IOS, Android, Wap)
     */
    public function recommendH5Type(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);

        if (str_contains($userAgent, 'iphone') || str_contains($userAgent, 'ipad')) {
            return 'IOS';
        }

        if (str_contains($userAgent, 'android')) {
            return 'Android';
        }

        return 'Wap';
    }

    /**
     * 验证交易类型是否有效.
     *
     * @param string $tradeType 交易类型
     *
     * @return bool 是否有效
     */
    public function isValidTradeType(string $tradeType): bool
    {
        return in_array($tradeType, $this->getSupportedTradeTypes(), true);
    }

    /**
     * 获取支付类型的描述信息.
     *
     * @param PaymentType $paymentType 支付类型
     *
     * @return array<string, mixed> 描述信息
     */
    public function getPaymentTypeInfo(PaymentType $paymentType): array
    {
        if (!$this->isPaymentTypeSupported($paymentType)) {
            throw new PaymentConfigurationException("不支持的支付类型: {$paymentType->value}");
        }

        $tradeType = $this->mapToTradeType($paymentType);
        $requiredParams = $this->getRequiredParameters($tradeType);

        return [
            'payment_type' => $paymentType->value,
            'payment_type_label' => $paymentType->getLabel(),
            'trade_type' => $tradeType,
            'trade_type_label' => $this->getTradeTypeLabel($tradeType),
            'required_parameters' => $requiredParams,
            'supported' => true,
        ];
    }

    /**
     * 获取所有支持的支付类型信息.
     *
     * @return array<array<string, mixed>> 支付类型信息列表
     */
    public function getAllPaymentTypeInfo(): array
    {
        $info = [];

        foreach ($this->getSupportedPaymentTypes() as $paymentType) {
            $info[] = $this->getPaymentTypeInfo($paymentType);
        }

        return $info;
    }

    /**
     * 根据设备类型推荐支付方式.
     *
     * @param string $userAgent 用户代理字符串
     *
     * @return PaymentType 推荐的支付类型
     */
    public function recommendPaymentType(string $userAgent): PaymentType
    {
        $userAgent = strtolower($userAgent);

        // 检查是否是微信内置浏览器
        if (str_contains($userAgent, 'micromessenger')) {
            // 微信小程序或公众号
            return PaymentType::WECHAT_JSAPI;
        }

        // 检查是否是移动设备
        if (str_contains($userAgent, 'mobile')) {
            // 移动设备，推荐H5支付
            return PaymentType::WECHAT_JSAPI; // 可以考虑支持H5支付类型
        }

        // 桌面设备，推荐扫码支付
        return PaymentType::WECHAT_JSAPI;
    }
}
