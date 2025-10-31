<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Service;

use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WechatPayBundle\Entity\Merchant;

/**
 * 证书管理服务
 *
 * 负责处理微信支付证书的管理，包括：
 * - 从商户配置获取证书内容
 * - 创建和管理临时证书文件
 * - 证书路径解析
 */
final class CertificateManager
{
    public function getCertPath(Merchant $merchant): string
    {
        $certContent = $this->getCertContent($merchant);

        if ('' === $certContent) {
            return '';
        }

        return $this->processCertificateContent($certContent, 'cert');
    }

    public function getKeyPath(Merchant $merchant): string
    {
        $keyContent = $this->getKeyContent($merchant);

        if ('' === $keyContent) {
            return '';
        }

        return $this->processCertificateContent($keyContent, 'key');
    }

    private function getCertContent(Merchant $merchant): string
    {
        $cert = $merchant->getPemCert();
        if (null !== $cert) {
            return $cert;
        }

        $envCert = $_ENV['WECHAT_PAY_CERT'] ?? null;
        if (null !== $envCert && is_string($envCert)) {
            return $envCert;
        }

        return '';
    }

    private function getKeyContent(Merchant $merchant): string
    {
        $key = $merchant->getPemKey();
        if (null !== $key) {
            return $key;
        }

        $envKey = $_ENV['WECHAT_PAY_KEY'] ?? null;
        if (null !== $envKey && is_string($envKey)) {
            return $envKey;
        }

        return '';
    }

    private function processCertificateContent(string $content, string $type): string
    {
        // 如果是文件路径（不含换行符且文件存在），直接返回
        if (!str_contains($content, "\n") && file_exists($content)) {
            return $content;
        }

        return $this->createTempCertificateFile($content, $type);
    }

    private function createTempCertificateFile(string $content, string $type): string
    {
        $filePath = $this->generateTempFilePath($content, $type);

        if ($this->tempFileExists($filePath, $content)) {
            return $filePath;
        }

        return $this->writeTempFile($filePath, $content, $type);
    }

    private function generateTempFilePath(string $content, string $type): string
    {
        $tempDir = sys_get_temp_dir();
        $contentHash = md5($content);

        return $tempDir . DIRECTORY_SEPARATOR . "wechat_{$type}_{$contentHash}.pem";
    }

    private function tempFileExists(string $filePath, string $content): bool
    {
        return file_exists($filePath) && file_get_contents($filePath) === $content;
    }

    private function writeTempFile(string $filePath, string $content, string $type): string
    {
        if (false === file_put_contents($filePath, $content, LOCK_EX)) {
            throw new PaymentConfigurationException("无法写入{$type}临时文件");
        }

        chmod($filePath, 0o600);

        return $filePath;
    }
}
