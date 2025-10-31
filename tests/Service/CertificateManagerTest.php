<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WechatMiniProgramPayBundle\Service\CertificateManager;
use WechatPayBundle\Entity\Merchant;

/**
 * CertificateManager 测试.
 *
 * @internal
 */
#[CoversClass(CertificateManager::class)]
#[RunTestsInSeparateProcesses]
final class CertificateManagerTest extends AbstractIntegrationTestCase
{
    private CertificateManager $certificateManager;

    protected function onSetUp(): void
    {
        $this->certificateManager = self::getService(CertificateManager::class);
    }

    #[Test]
    public function testGetCertPathWithMerchantCert(): void
    {
        $certContent = "-----BEGIN CERTIFICATE-----\nMIID...test...\n-----END CERTIFICATE-----";
        $merchant = $this->createMerchantWithCert($certContent, null);

        $certPath = $this->certificateManager->getCertPath($merchant);

        $this->assertStringContainsString('wechat_cert_', $certPath);
        $this->assertFileExists($certPath);
        $this->assertEquals($certContent, file_get_contents($certPath));

        // 清理临时文件
        if (file_exists($certPath)) {
            unlink($certPath);
        }
    }

    #[Test]
    public function testGetKeyPathWithMerchantKey(): void
    {
        $keyContent = "-----BEGIN PRIVATE KEY-----\nMIIE...test...\n-----END PRIVATE KEY-----";
        $merchant = $this->createMerchantWithCert(null, $keyContent);

        $keyPath = $this->certificateManager->getKeyPath($merchant);

        $this->assertStringContainsString('wechat_key_', $keyPath);
        $this->assertFileExists($keyPath);
        $this->assertEquals($keyContent, file_get_contents($keyPath));

        // 清理临时文件
        if (file_exists($keyPath)) {
            unlink($keyPath);
        }
    }

    #[Test]
    public function testGetCertPathWithExistingFilePath(): void
    {
        // 创建临时文件模拟已存在的证书文件
        $tempFile = tempnam(sys_get_temp_dir(), 'test_cert_');
        $certContent = 'test cert content';
        file_put_contents($tempFile, $certContent);

        $merchant = $this->createMerchantWithCert($tempFile, null);

        $certPath = $this->certificateManager->getCertPath($merchant);

        $this->assertEquals($tempFile, $certPath);

        // 清理临时文件
        unlink($tempFile);
    }

    #[Test]
    public function testGetKeyPathWithExistingFilePath(): void
    {
        // 创建临时文件模拟已存在的密钥文件
        $tempFile = tempnam(sys_get_temp_dir(), 'test_key_');
        $keyContent = 'test key content';
        file_put_contents($tempFile, $keyContent);

        $merchant = $this->createMerchantWithCert(null, $tempFile);

        $keyPath = $this->certificateManager->getKeyPath($merchant);

        $this->assertEquals($tempFile, $keyPath);

        // 清理临时文件
        unlink($tempFile);
    }

    #[Test]
    public function testGetCertPathWithEmptyCert(): void
    {
        $merchant = $this->createMerchantWithCert('', null);

        $certPath = $this->certificateManager->getCertPath($merchant);

        $this->assertEquals('', $certPath);
    }

    #[Test]
    public function testGetKeyPathWithEmptyKey(): void
    {
        $merchant = $this->createMerchantWithCert(null, '');

        $keyPath = $this->certificateManager->getKeyPath($merchant);

        $this->assertEquals('', $keyPath);
    }

    #[Test]
    public function testGetCertPathFromEnvironmentVariable(): void
    {
        $certContent = "-----BEGIN CERTIFICATE-----\nMIID...env...\n-----END CERTIFICATE-----";
        $_ENV['WECHAT_PAY_CERT'] = $certContent;

        $merchant = $this->createMerchantWithCert(null, null);

        $certPath = $this->certificateManager->getCertPath($merchant);

        $this->assertStringContainsString('wechat_cert_', $certPath);
        $this->assertFileExists($certPath);
        $this->assertEquals($certContent, file_get_contents($certPath));

        // 清理
        unset($_ENV['WECHAT_PAY_CERT']);
        if (file_exists($certPath)) {
            unlink($certPath);
        }
    }

    #[Test]
    public function testGetKeyPathFromEnvironmentVariable(): void
    {
        $keyContent = "-----BEGIN PRIVATE KEY-----\nMIIE...env...\n-----END PRIVATE KEY-----";
        $_ENV['WECHAT_PAY_KEY'] = $keyContent;

        $merchant = $this->createMerchantWithCert(null, null);

        $keyPath = $this->certificateManager->getKeyPath($merchant);

        $this->assertStringContainsString('wechat_key_', $keyPath);
        $this->assertFileExists($keyPath);
        $this->assertEquals($keyContent, file_get_contents($keyPath));

        // 清理
        unset($_ENV['WECHAT_PAY_KEY']);
        if (file_exists($keyPath)) {
            unlink($keyPath);
        }
    }

    #[Test]
    public function testTempFileCaching(): void
    {
        $certContent = "-----BEGIN CERTIFICATE-----\nMIID...cache...\n-----END CERTIFICATE-----";
        $merchant = $this->createMerchantWithCert($certContent, null);

        // 第一次调用
        $certPath1 = $this->certificateManager->getCertPath($merchant);
        $mtime1 = filemtime($certPath1);

        // 稍等一下再调用，确保如果重新创建文件时间会不同
        usleep(10000);

        // 第二次调用应该返回相同的文件路径
        $certPath2 = $this->certificateManager->getCertPath($merchant);
        $mtime2 = filemtime($certPath2);

        $this->assertEquals($certPath1, $certPath2);
        $this->assertEquals($mtime1, $mtime2); // 文件时间相同说明没有重新创建

        // 清理临时文件
        if (file_exists($certPath1)) {
            unlink($certPath1);
        }
    }

    #[Test]
    public function testWriteTempFileThrowsExceptionOnFailure(): void
    {
        // 创建一个只读目录来模拟写入失败
        $readOnlyDir = sys_get_temp_dir() . '/readonly_test_' . uniqid();
        mkdir($readOnlyDir);
        chmod($readOnlyDir, 0o444); // 只读权限

        $certContent = 'test content';

        // 使用反射来测试私有方法
        $reflection = new \ReflectionClass($this->certificateManager);
        $method = $reflection->getMethod('writeTempFile');
        $method->setAccessible(true);

        $failPath = $readOnlyDir . '/test.pem';

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('无法写入cert临时文件');

        try {
            $method->invoke($this->certificateManager, $failPath, $certContent, 'cert');
        } finally {
            // 清理：恢复权限并删除目录
            chmod($readOnlyDir, 0o755);
            rmdir($readOnlyDir);
        }
    }

    #[Test]
    public function testTempFilePermissions(): void
    {
        $certContent = "-----BEGIN CERTIFICATE-----\nMIID...perms...\n-----END CERTIFICATE-----";
        $merchant = $this->createMerchantWithCert($certContent, null);

        $certPath = $this->certificateManager->getCertPath($merchant);

        // 检查文件权限是否为 600 (仅所有者可读写)
        $permissions = fileperms($certPath) & 0o777;
        $this->assertEquals(0o600, $permissions);

        // 清理临时文件
        if (file_exists($certPath)) {
            unlink($certPath);
        }
    }

    #[Test]
    #[DataProvider('provideCertificateContentTypes')]
    public function testCertificateContentTypes(string $content, string $expectedType): void
    {
        $merchant = $this->createMerchantWithCert($content, null);

        $certPath = $this->certificateManager->getCertPath($merchant);

        if ('' === $content) {
            $this->assertEquals('', $certPath);
        } else {
            $this->assertStringContainsString("wechat_{$expectedType}_", $certPath);
            if (file_exists($certPath)) {
                unlink($certPath);
            }
        }
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function provideCertificateContentTypes(): iterable
    {
        yield 'PEM certificate' => [
            "-----BEGIN CERTIFICATE-----\nMIID...test...\n-----END CERTIFICATE-----",
            'cert',
        ];
        yield 'Empty content' => ['', 'cert'];
        yield 'Multi-line content' => [
            "line1\nline2\nline3",
            'cert',
        ];
    }

    #[Test]
    public function testMerchantCertTakesPrecedenceOverEnvironment(): void
    {
        $merchantCert = "-----BEGIN CERTIFICATE-----\nmerchant cert\n-----END CERTIFICATE-----";
        $envCert = "-----BEGIN CERTIFICATE-----\nenv cert\n-----END CERTIFICATE-----";

        $_ENV['WECHAT_PAY_CERT'] = $envCert;

        $merchant = $this->createMerchantWithCert($merchantCert, null);

        $certPath = $this->certificateManager->getCertPath($merchant);

        $this->assertEquals($merchantCert, file_get_contents($certPath));

        // 清理
        unset($_ENV['WECHAT_PAY_CERT']);
        if (file_exists($certPath)) {
            unlink($certPath);
        }
    }

    #[Test]
    public function testMerchantKeyTakesPrecedenceOverEnvironment(): void
    {
        $merchantKey = "-----BEGIN PRIVATE KEY-----\nmerchant key\n-----END PRIVATE KEY-----";
        $envKey = "-----BEGIN PRIVATE KEY-----\nenv key\n-----END PRIVATE KEY-----";

        $_ENV['WECHAT_PAY_KEY'] = $envKey;

        $merchant = $this->createMerchantWithCert(null, $merchantKey);

        $keyPath = $this->certificateManager->getKeyPath($merchant);

        $this->assertEquals($merchantKey, file_get_contents($keyPath));

        // 清理
        unset($_ENV['WECHAT_PAY_KEY']);
        if (file_exists($keyPath)) {
            unlink($keyPath);
        }
    }

    private function createMerchantWithCert(?string $cert, ?string $key): Merchant
    {
        $merchant = new Merchant();
        $merchant->setMchId('test_mch_id');
        $merchant->setCertSerial('test_serial');
        $merchant->setApiKey('test_api_key');

        if (null !== $cert) {
            $merchant->setPemCert($cert);
        }

        if (null !== $key) {
            $merchant->setPemKey($key);
        }

        return $merchant;
    }
}
