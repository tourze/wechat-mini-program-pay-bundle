<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Service;

use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;

/**
 * 微信支付签名生成服务
 *
 * 实现微信支付V2版本的签名算法，支持MD5和HMAC-SHA256
 *
 * @see https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=4_3
 */
class SignatureService
{
    public const SIGN_TYPE_MD5 = 'MD5';
    public const SIGN_TYPE_HMAC_SHA256 = 'HMAC-SHA256';

    /**
     * 生成签名.
     *
     * @param array<string, mixed> $parameters 参数数组
     * @param string               $key        商户密钥
     * @param string               $signType   签名类型
     *
     * @return string 签名字符串
     */
    public function generateSignature(array $parameters, string $key, string $signType = self::SIGN_TYPE_MD5): string
    {
        if ('' === $key) {
            throw new PaymentConfigurationException('商户密钥不能为空');
        }

        // 过滤空值和签名字段
        $filteredParams = $this->filterParameters($parameters);

        if (0 === count($filteredParams)) {
            throw new PaymentConfigurationException('签名参数不能为空');
        }

        // 按键名ASCII码排序
        ksort($filteredParams);

        // 拼接参数字符串
        $queryString = $this->buildQueryString($filteredParams);

        // 拼接商户密钥
        $stringSignTemp = $queryString . '&key=' . $key;

        // 根据签名类型生成签名
        return match ($signType) {
            self::SIGN_TYPE_MD5 => strtoupper(md5($stringSignTemp)),
            self::SIGN_TYPE_HMAC_SHA256 => strtoupper(hash_hmac('sha256', $stringSignTemp, $key)),
            default => throw new PaymentConfigurationException("不支持的签名类型: {$signType}"),
        };
    }

    /**
     * 验证签名.
     *
     * @param array<string, mixed> $parameters 参数数组
     * @param string               $key        商户密钥
     * @param string               $signType   签名类型
     *
     * @return bool 验证结果
     */
    public function verifySignature(array $parameters, string $key, string $signType = self::SIGN_TYPE_MD5): bool
    {
        if (!isset($parameters['sign'])) {
            return false;
        }

        $sign = $parameters['sign'];
        $receivedSign = is_string($sign) ? $sign : '';
        $calculatedSign = $this->generateSignature($parameters, $key, $signType);

        return hash_equals($calculatedSign, strtoupper($receivedSign));
    }

    /**
     * 过滤参数.
     *
     * 移除空值、null值和sign字段
     *
     * @param array<string, mixed> $parameters
     *
     * @return array<string, string>
     */
    private function filterParameters(array $parameters): array
    {
        $filtered = [];

        foreach ($parameters as $key => $value) {
            if ($this->shouldSkipParameter($key, $value)) {
                continue;
            }

            $filtered[$key] = $this->convertValueToString($value);
        }

        return $filtered;
    }

    private function shouldSkipParameter(string $key, mixed $value): bool
    {
        return null === $value || '' === $value || 'sign' === $key;
    }

    private function convertValueToString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Y' : 'N';
        }

        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);

            return false !== $encoded ? $encoded : '';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * 构建查询字符串.
     *
     * @param array<string, string> $parameters
     */
    private function buildQueryString(array $parameters): string
    {
        $parts = [];

        foreach ($parameters as $key => $value) {
            $parts[] = $key . '=' . $value;
        }

        return implode('&', $parts);
    }

    /**
     * 生成随机字符串（用于nonce_str）.
     *
     * @param int $length 长度
     *
     * @return string 随机字符串
     */
    public function generateNonceStr(int $length = 32): string
    {
        if ($length <= 0) {
            throw new PaymentConfigurationException('随机字符串长度必须大于0');
        }

        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        $charsLength = strlen($chars);

        for ($i = 0; $i < $length; ++$i) {
            $str .= $chars[random_int(0, $charsLength - 1)];
        }

        return $str;
    }

    /**
     * 检查签名类型是否支持
     *
     * @param string $signType 签名类型
     *
     * @return bool 是否支持
     */
    public function isSignTypeSupported(string $signType): bool
    {
        return in_array($signType, [self::SIGN_TYPE_MD5, self::SIGN_TYPE_HMAC_SHA256], true);
    }

    /**
     * 获取所有支持的签名类型.
     *
     * @return array<string> 支持的签名类型列表
     */
    public function getSupportedSignTypes(): array
    {
        return [self::SIGN_TYPE_MD5, self::SIGN_TYPE_HMAC_SHA256];
    }

    /**
     * 为微信支付参数添加签名.
     *
     * 这是一个便捷方法，会自动生成nonce_str和sign字段
     *
     * @param array<string, mixed> $parameters 参数数组
     * @param string               $key        商户密钥
     * @param string               $signType   签名类型
     *
     * @return array<string, mixed> 添加签名后的参数数组
     */
    public function signParameters(array $parameters, string $key, string $signType = self::SIGN_TYPE_MD5): array
    {
        // 如果没有nonce_str，自动生成
        $nonceStr = $parameters['nonce_str'] ?? '';
        if (!is_string($nonceStr) || '' === $nonceStr) {
            $parameters['nonce_str'] = $this->generateNonceStr();
        }

        // 设置签名类型
        if (self::SIGN_TYPE_MD5 !== $signType) {
            $parameters['sign_type'] = $signType;
        }

        // 生成签名
        $parameters['sign'] = $this->generateSignature($parameters, $key, $signType);

        return $parameters;
    }

    /**
     * 调试签名过程.
     *
     * 返回签名计算的中间步骤，用于调试
     *
     * @param array<string, mixed> $parameters 参数数组
     * @param string               $key        商户密钥
     * @param string               $signType   签名类型
     *
     * @return array<string, mixed> 调试信息
     */
    public function debugSignature(array $parameters, string $key, string $signType = self::SIGN_TYPE_MD5): array
    {
        // 过滤参数
        $filteredParams = $this->filterParameters($parameters);

        // 排序
        ksort($filteredParams);

        // 构建查询字符串
        $queryString = $this->buildQueryString($filteredParams);

        // 拼接密钥
        $stringSignTemp = $queryString . '&key=' . $key;

        // 生成签名
        $signature = match ($signType) {
            self::SIGN_TYPE_MD5 => strtoupper(md5($stringSignTemp)),
            self::SIGN_TYPE_HMAC_SHA256 => strtoupper(hash_hmac('sha256', $stringSignTemp, $key)),
            default => '',
        };

        return [
            'original_parameters' => $parameters,
            'filtered_parameters' => $filteredParams,
            'sorted_parameters' => $filteredParams,
            'query_string' => $queryString,
            'string_sign_temp' => $stringSignTemp,
            'signature' => $signature,
            'sign_type' => $signType,
        ];
    }
}
