<?php

namespace WechatMiniProgramPayBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use DoctrineEnhanceBundle\Repository\CommonRepositoryAware;
use WechatMiniProgramPayBundle\Entity\PaymentNotifyMessage;

/**
 * @method PaymentNotifyMessage|null find($id, $lockMode = null, $lockVersion = null)
 * @method PaymentNotifyMessage|null findOneBy(array $criteria, array $orderBy = null)
 * @method PaymentNotifyMessage[]    findAll()
 * @method PaymentNotifyMessage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaymentNotifyMessageRepository extends ServiceEntityRepository
{
    use CommonRepositoryAware;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentNotifyMessage::class);
    }
}
