<?php

namespace WechatMiniProgramPayBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use WechatMiniProgramPayBundle\Entity\PaymentNotifyMessage;

/**
 * @internal
 */
#[CoversClass(PaymentNotifyMessage::class)]
final class PaymentNotifyMessageTest extends AbstractEntityTestCase
{
    protected function createEntity(): PaymentNotifyMessage
    {
        return new PaymentNotifyMessage();
    }

    /**
     * @return iterable<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        return [
            ['createTime', new \DateTimeImmutable()],
        ];
    }

    public function testGetSetId(): void
    {
        // ID 是自动生成的，初始值应为 null
        $entity = $this->createEntity();
        $this->assertNull($entity->getId());
    }

    public function testGetSetRawData(): void
    {
        $rawData = '{"test_key":"test_value"}';
        $entity = $this->createEntity();
        $entity->setRawData($rawData);
        $this->assertEquals($rawData, $entity->getRawData());
    }

    public function testGetSetCreateTime(): void
    {
        $createTime = new \DateTimeImmutable();
        $entity = $this->createEntity();
        $entity->setCreateTime($createTime);
        $this->assertSame($createTime, $entity->getCreateTime());
    }
}
