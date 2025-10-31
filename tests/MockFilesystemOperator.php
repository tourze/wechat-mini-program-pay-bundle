<?php

namespace WechatMiniProgramPayBundle\Tests;

use League\Flysystem\DirectoryListing;
use League\Flysystem\FilesystemOperator;
use WechatMiniProgramPayBundle\Tests\Exception\MockNotSupportedException;

class MockFilesystemOperator implements FilesystemOperator
{
    public function fileExists(string $location): bool
    {
        return false;
    }

    public function has(string $location): bool
    {
        return false;
    }

    public function read(string $location): string
    {
        return '';
    }

    /**
     * @return resource
     */
    public function readStream(string $location)
    {
        $stream = fopen('php://memory', 'r');
        if (false === $stream) {
            throw new MockNotSupportedException('Failed to open stream');
        }

        return $stream;
    }

    /** @phpstan-ignore-next-line missingType.iterableValue */
    public function write(string $location, string $contents, array $config = []): void
    {
    }

    /**
     * @param mixed $contents
     * @phpstan-ignore-next-line missingType.iterableValue
     */
    public function writeStream(string $location, $contents, array $config = []): void
    {
    }

    public function delete(string $location): void
    {
    }

    public function deleteDirectory(string $location): void
    {
    }

    /** @phpstan-ignore-next-line missingType.iterableValue */
    public function createDirectory(string $location, array $config = []): void
    {
    }

    public function listContents(string $location, bool $deep = false): DirectoryListing
    {
        return new DirectoryListing([]);
    }

    /** @phpstan-ignore-next-line missingType.iterableValue */
    public function move(string $source, string $destination, array $config = []): void
    {
    }

    /** @phpstan-ignore-next-line missingType.iterableValue */
    public function copy(string $source, string $destination, array $config = []): void
    {
    }

    public function lastModified(string $location): int
    {
        return 0;
    }

    public function fileSize(string $location): int
    {
        return 0;
    }

    public function mimeType(string $location): string
    {
        return '';
    }

    public function visibility(string $location): string
    {
        return '';
    }

    public function setVisibility(string $location, string $visibility): void
    {
    }

    public function directoryExists(string $location): bool
    {
        return false;
    }
}
