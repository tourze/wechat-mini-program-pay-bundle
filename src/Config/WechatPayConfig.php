<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Config;

use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;

/**
 * 微信支付配置类.
 *
 * 管理微信支付V2版本的配置参数，支持：
 * - 基础配置：AppID、商户号、密钥等
 * - 证书配置：API证书路径和密码
 * - 网络配置：超时时间、重试次数等
 * - 业务配置：签名类型、货币类型等
 */
class WechatPayConfig
{
    private string $appId = '';

    private string $mchId = '';

    private string $key = '';

    private string $signType = 'MD5';

    private string $feeType = 'CNY';

    private ?string $certPath = null;

    private ?string $keyPath = null;

    private ?string $caCertPath = null;

    private string $baseUrl = 'https://api.mch.weixin.qq.com';

    private int $timeout = 30;

    private int $connectTimeout = 10;

    private int $retryTimes = 3;

    private bool $debug = false;

    private ?string $logPath = null;

    /** @var array<string, string> */
    private array $defaultHeaders = [
        'User-Agent' => 'WechatMiniProgramPayBundle/1.0',
        'Content-Type' => 'application/xml; charset=utf-8',
    ];

    /**
     * 从环境变量和默认值构造配置.
     */
    public static function fromEnvironment(): self
    {
        $config = new self();

        $config->appId = self::getStringEnv('WECHAT_PAY_APP_ID', '');
        $config->mchId = self::getStringEnv('WECHAT_PAY_MCH_ID', '');
        $config->key = self::getStringEnv('WECHAT_PAY_KEY', '');
        $config->signType = self::getStringEnv('WECHAT_PAY_SIGN_TYPE', 'MD5');
        $config->feeType = self::getStringEnv('WECHAT_PAY_FEE_TYPE', 'CNY');
        $config->certPath = self::getNullableStringEnv('WECHAT_PAY_CERT_PATH');
        $config->keyPath = self::getNullableStringEnv('WECHAT_PAY_KEY_PATH');
        $config->caCertPath = self::getNullableStringEnv('WECHAT_PAY_CA_CERT_PATH');
        $config->baseUrl = self::getStringEnv('WECHAT_PAY_BASE_URL', 'https://api.mch.weixin.qq.com');
        $config->timeout = self::getIntEnv('WECHAT_PAY_TIMEOUT', 30);
        $config->connectTimeout = self::getIntEnv('WECHAT_PAY_CONNECT_TIMEOUT', 10);
        $config->retryTimes = self::getIntEnv('WECHAT_PAY_RETRY_TIMES', 3);

        $config->debug = filter_var($_ENV['WECHAT_PAY_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $logPath = $_ENV['WECHAT_PAY_LOG_PATH'] ?? null;
        $config->logPath = is_string($logPath) ? $logPath : null;

        return $config;
    }

    /**
     * 从数组构造配置.
     */
    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $instance = new self();

        $instance->appId = self::getStringFromArray($config, 'app_id', '');
        $instance->mchId = self::getStringFromArray($config, 'mch_id', '');
        $instance->key = self::getStringFromArray($config, 'key', '');
        $instance->signType = self::getStringFromArray($config, 'sign_type', 'MD5');
        $instance->feeType = self::getStringFromArray($config, 'fee_type', 'CNY');
        $instance->certPath = self::getNullableStringFromArray($config, 'cert_path');
        $instance->keyPath = self::getNullableStringFromArray($config, 'key_path');
        $instance->caCertPath = self::getNullableStringFromArray($config, 'ca_cert_path');
        $instance->baseUrl = self::getStringFromArray($config, 'base_url', 'https://api.mch.weixin.qq.com');
        $instance->timeout = self::getIntFromArray($config, 'timeout', 30);
        $instance->connectTimeout = self::getIntFromArray($config, 'connect_timeout', 10);
        $instance->retryTimes = self::getIntFromArray($config, 'retry_times', 3);
        $instance->debug = self::getBoolFromArray($config, 'debug', false);
        $instance->logPath = self::getNullableStringFromArray($config, 'log_path');

        return $instance;
    }

    /**
     * 验证配置完整性.
     */
    public function validate(): void
    {
        $this->validateRequiredFields();
        $this->validateSignType();
        $this->validateFeeType();
        $this->validateTimeoutSettings();
        $this->validateRetryTimes();
        $this->validateCertificateFiles();
    }

    /**
     * 验证必填字段.
     */
    private function validateRequiredFields(): void
    {
        $requiredFields = [
            'appId' => $this->appId,
            'mchId' => $this->mchId,
            'key' => $this->key,
        ];

        foreach ($requiredFields as $field => $value) {
            if ('' === $value) {
                throw new PaymentConfigurationException("必填配置项 {$field} 不能为空");
            }
        }
    }

    /**
     * 验证签名类型.
     */
    private function validateSignType(): void
    {
        if (!in_array($this->signType, ['MD5', 'HMAC-SHA256'], true)) {
            throw new PaymentConfigurationException("不支持的签名类型: {$this->signType}");
        }
    }

    /**
     * 验证货币类型.
     */
    private function validateFeeType(): void
    {
        if (!in_array($this->feeType, ['CNY'], true)) {
            throw new PaymentConfigurationException("不支持的货币类型: {$this->feeType}");
        }
    }

    /**
     * 验证超时配置.
     */
    private function validateTimeoutSettings(): void
    {
        if ($this->timeout <= 0 || $this->connectTimeout <= 0) {
            throw new PaymentConfigurationException('超时时间必须大于0');
        }
    }

    /**
     * 验证重试次数.
     */
    private function validateRetryTimes(): void
    {
        if ($this->retryTimes < 0) {
            throw new PaymentConfigurationException('重试次数不能为负数');
        }
    }

    /**
     * 验证证书文件.
     */
    private function validateCertificateFiles(): void
    {
        $this->validateCertificateFile($this->certPath, '证书文件');
        $this->validateCertificateFile($this->keyPath, '密钥文件');
        $this->validateCertificateFile($this->caCertPath, 'CA证书文件');
    }

    /**
     * 验证单个证书文件.
     */
    private function validateCertificateFile(?string $filePath, string $fileType): void
    {
        if (null !== $filePath && !file_exists($filePath)) {
            throw new PaymentConfigurationException("{$fileType}不存在: {$filePath}");
        }
    }

    // Getters and Setters

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function setAppId(string $appId): void
    {
        $this->appId = $appId;
    }

    public function getMchId(): string
    {
        return $this->mchId;
    }

    public function setMchId(string $mchId): void
    {
        $this->mchId = $mchId;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function getSignType(): string
    {
        return $this->signType;
    }

    public function setSignType(string $signType): void
    {
        if (!in_array($signType, ['MD5', 'HMAC-SHA256'], true)) {
            throw new PaymentConfigurationException("不支持的签名类型: {$signType}");
        }
        $this->signType = $signType;
    }

    public function getFeeType(): string
    {
        return $this->feeType;
    }

    public function setFeeType(string $feeType): void
    {
        $this->feeType = $feeType;
    }

    public function getCertPath(): ?string
    {
        return $this->certPath;
    }

    public function setCertPath(?string $certPath): void
    {
        $this->certPath = $certPath;
    }

    public function getKeyPath(): ?string
    {
        return $this->keyPath;
    }

    public function setKeyPath(?string $keyPath): void
    {
        $this->keyPath = $keyPath;
    }

    public function getCaCertPath(): ?string
    {
        return $this->caCertPath;
    }

    public function setCaCertPath(?string $caCertPath): void
    {
        $this->caCertPath = $caCertPath;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setTimeout(int $timeout): void
    {
        if ($timeout <= 0) {
            throw new PaymentConfigurationException('超时时间必须大于0');
        }
        $this->timeout = $timeout;
    }

    public function getConnectTimeout(): int
    {
        return $this->connectTimeout;
    }

    public function setConnectTimeout(int $connectTimeout): void
    {
        if ($connectTimeout <= 0) {
            throw new PaymentConfigurationException('连接超时时间必须大于0');
        }
        $this->connectTimeout = $connectTimeout;
    }

    public function getRetryTimes(): int
    {
        return $this->retryTimes;
    }

    public function setRetryTimes(int $retryTimes): void
    {
        if ($retryTimes < 0) {
            throw new PaymentConfigurationException('重试次数不能为负数');
        }
        $this->retryTimes = $retryTimes;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    public function getLogPath(): ?string
    {
        return $this->logPath;
    }

    public function setLogPath(?string $logPath): void
    {
        $this->logPath = $logPath;
    }

    /**
     * @return array<string, string>
     */
    public function getDefaultHeaders(): array
    {
        return $this->defaultHeaders;
    }

    /**
     * @param array<string, string> $defaultHeaders
     */
    public function setDefaultHeaders(array $defaultHeaders): void
    {
        $this->defaultHeaders = $defaultHeaders;
    }

    public function addDefaultHeader(string $name, string $value): void
    {
        $this->defaultHeaders[$name] = $value;
    }

    /**
     * 是否需要证书认证
     */
    public function needsCertificate(): bool
    {
        return null !== $this->certPath && '' !== $this->certPath && null !== $this->keyPath && '' !== $this->keyPath;
    }

    /**
     * 获取HTTP客户端配置.
     */
    /**
     * @return array<string, mixed>
     */
    public function getHttpClientConfig(): array
    {
        $config = [
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'headers' => $this->defaultHeaders,
        ];

        if ($this->needsCertificate()) {
            $config['cert'] = $this->certPath;
            $config['ssl_key'] = $this->keyPath;
        }

        if (null !== $this->caCertPath) {
            $config['verify'] = $this->caCertPath;
        }

        return $config;
    }

    /**
     * 转换为数组.
     */
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'app_id' => $this->appId,
            'mch_id' => $this->mchId,
            'key' => $this->key,
            'sign_type' => $this->signType,
            'fee_type' => $this->feeType,
            'cert_path' => $this->certPath,
            'key_path' => $this->keyPath,
            'ca_cert_path' => $this->caCertPath,
            'base_url' => $this->baseUrl,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'retry_times' => $this->retryTimes,
            'debug' => $this->debug,
            'log_path' => $this->logPath,
        ];
    }

    /**
     * 获取调试信息（隐藏敏感信息）.
     */
    /**
     * @return array<string, mixed>
     */
    public function getDebugInfo(): array
    {
        return [
            'app_id' => $this->appId,
            'mch_id' => $this->mchId,
            'key' => '' !== $this->key ? '***' : '',
            'sign_type' => $this->signType,
            'fee_type' => $this->feeType,
            'cert_path' => $this->certPath,
            'key_path' => $this->keyPath,
            'ca_cert_path' => $this->caCertPath,
            'base_url' => $this->baseUrl,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'retry_times' => $this->retryTimes,
            'debug' => $this->debug,
            'log_path' => $this->logPath,
            'needs_certificate' => $this->needsCertificate(),
        ];
    }

    /**
     * 克隆配置（用于不同环境）.
     */
    public function clone(): self
    {
        return clone $this;
    }

    /**
     * 合并配置.
     */
    /**
     * @param array<string, mixed> $config
     */
    public function merge(array $config): self
    {
        $merged = $this->clone();

        foreach ($config as $key => $value) {
            $this->applyConfigValue($merged, $key, $value);
        }

        return $merged;
    }

    /**
     * 应用单个配置值
     *
     * @param mixed $value
     */
    private function applyConfigValue(self $config, string $key, $value): void
    {
        $this->applyStringConfig($config, $key, $value)
        || $this->applyNullableStringConfig($config, $key, $value)
        || $this->applyIntegerConfig($config, $key, $value)
        || $this->applyBooleanConfig($config, $key, $value);
    }

    /**
     * 应用字符串类型配置.
     *
     * @param mixed $value
     */
    private function applyStringConfig(self $config, string $key, $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        match ($key) {
            'app_id' => $config->setAppId($value),
            'mch_id' => $config->setMchId($value),
            'key' => $config->setKey($value),
            'sign_type' => $config->setSignType($value),
            'fee_type' => $config->setFeeType($value),
            'base_url' => $config->setBaseUrl($value),
            default => null,
        };

        return true;
    }

    /**
     * 应用可空字符串类型配置.
     *
     * @param mixed $value
     */
    private function applyNullableStringConfig(self $config, string $key, $value): bool
    {
        if (!is_string($value) && null !== $value) {
            return false;
        }

        match ($key) {
            'cert_path' => $config->setCertPath($value),
            'key_path' => $config->setKeyPath($value),
            'ca_cert_path' => $config->setCaCertPath($value),
            'log_path' => $config->setLogPath($value),
            default => null,
        };

        return true;
    }

    /**
     * 应用整数类型配置.
     *
     * @param mixed $value
     */
    private function applyIntegerConfig(self $config, string $key, $value): bool
    {
        if (!is_int($value)) {
            return false;
        }

        match ($key) {
            'timeout' => $config->setTimeout($value),
            'connect_timeout' => $config->setConnectTimeout($value),
            'retry_times' => $config->setRetryTimes($value),
            default => null,
        };

        return true;
    }

    /**
     * 应用布尔类型配置.
     *
     * @param mixed $value
     */
    private function applyBooleanConfig(self $config, string $key, $value): bool
    {
        if (!is_bool($value)) {
            return false;
        }

        match ($key) {
            'debug' => $config->setDebug($value),
            default => null,
        };

        return true;
    }

    /**
     * 检查配置是否支持指定功能.
     */
    public function supportsFeature(string $feature): bool
    {
        return match ($feature) {
            'certificate_auth' => $this->needsCertificate(),
            'hmac_sha256' => 'HMAC-SHA256' === $this->signType,
            'debug_logging' => $this->debug && null !== $this->logPath && '' !== $this->logPath,
            'retry' => $this->retryTimes > 0,
            default => false,
        };
    }

    /**
     * 获取完整的API URL.
     */
    public function getApiUrl(string $path): string
    {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    /**
     * 获取字符串环境变量.
     */
    private static function getStringEnv(string $key, string $default): string
    {
        $value = $_ENV[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    /**
     * 获取可为空的字符串环境变量.
     */
    private static function getNullableStringEnv(string $key): ?string
    {
        $value = $_ENV[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * 获取整数环境变量.
     */
    private static function getIntEnv(string $key, int $default): int
    {
        $value = $_ENV[$key] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * 从数组获取字符串值
     *
     * @param array<string, mixed> $array
     */
    private static function getStringFromArray(array $array, string $key, string $default): string
    {
        $value = $array[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    /**
     * 从数组获取可为空的字符串值
     *
     * @param array<string, mixed> $array
     */
    private static function getNullableStringFromArray(array $array, string $key): ?string
    {
        $value = $array[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * 从数组获取整数值
     *
     * @param array<string, mixed> $array
     */
    private static function getIntFromArray(array $array, string $key, int $default): int
    {
        $value = $array[$key] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * 从数组获取布尔值
     *
     * @param array<string, mixed> $array
     */
    private static function getBoolFromArray(array $array, string $key, bool $default): bool
    {
        $value = $array[$key] ?? $default;

        return is_bool($value) ? $value : $default;
    }
}
