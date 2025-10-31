<?php

namespace WechatMiniProgramPayBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use WechatMiniProgramPayBundle\Entity\PaymentNotifyMessage;

/**
 * @extends ServiceEntityRepository<PaymentNotifyMessage>
 */
#[AsRepository(entityClass: PaymentNotifyMessage::class)]
class PaymentNotifyMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentNotifyMessage::class);
    }

    public function save(PaymentNotifyMessage $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PaymentNotifyMessage $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 计算仓库中的所有实体数量.
     */
    public function count(array $criteria = []): int
    {
        return parent::count($criteria);
    }
}
