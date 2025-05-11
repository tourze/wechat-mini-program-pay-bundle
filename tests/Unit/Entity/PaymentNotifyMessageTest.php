<?php

namespace WechatMiniProgramPayBundle\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use WechatMiniProgramPayBundle\Entity\PaymentNotifyMessage;

class PaymentNotifyMessageTest extends TestCase
{
    private PaymentNotifyMessage $entity;
    
    protected function setUp(): void
    {
        $this->entity = new PaymentNotifyMessage();
    }
    
    public function testGetSetId(): void
    {
        // ID 是自动生成的，初始值应为 0 或 null
        $this->assertNotNull($this->entity->getId());
    }
    
    public function testGetSetRawData(): void
    {
        $rawData = '{"test_key":"test_value"}';
        $this->entity->setRawData($rawData);
        $this->assertEquals($rawData, $this->entity->getRawData());
    }
    
    public function testGetSetCreateTime(): void
    {
        $createdAt = new \DateTime();
        $this->entity->setCreateTime($createdAt);
        $this->assertSame($createdAt, $this->entity->getCreateTime());
    }
    
    public function testFluentInterface(): void
    {
        // 测试流式接口（返回 $this）
        $this->assertSame($this->entity, $this->entity->setRawData('test'));
        $this->assertSame($this->entity, $this->entity->setCreateTime(new \DateTime()));
    }
} 