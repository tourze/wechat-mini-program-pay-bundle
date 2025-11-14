<?php

namespace WechatMiniProgramPayBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use WechatMiniProgramPayBundle\Repository\PaymentNotifyMessageRepository;

#[ORM\Entity(repositoryClass: PaymentNotifyMessageRepository::class)]
#[ORM\Table(name: 'wechat_mini_program_payment_notify_message', options: ['comment' => '支付回调消息'])]
class PaymentNotifyMessage implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => 'ID'])]
    private ?int $id = null;

    #[Assert\Length(max: 65535)]
    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '原始数据'])]
    private ?string $rawData = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    #[IndexColumn]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '创建时间'])]
    private ?\DateTimeImmutable $createTime = null;

    public function setCreateTime(?\DateTimeImmutable $createTime): void
    {
        $this->createTime = $createTime;
    }

    public function getCreateTime(): ?\DateTimeImmutable
    {
        return $this->createTime;
    }

    public function getRawData(): ?string
    {
        return $this->rawData;
    }

    public function setRawData(?string $rawData): void
    {
        $this->rawData = $rawData;
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }
}
