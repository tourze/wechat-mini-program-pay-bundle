<?php

namespace WechatMiniProgramPayBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use WechatMiniProgramPayBundle\Entity\PaymentNotifyMessage;
use WechatMiniProgramPayBundle\Repository\PaymentNotifyMessageRepository;

/**
 * @extends AbstractRepositoryTestCase<PaymentNotifyMessage>
 *
 * @internal
 */
#[CoversClass(PaymentNotifyMessageRepository::class)]
#[RunTestsInSeparateProcesses]
final class PaymentNotifyMessageRepositoryTest extends AbstractRepositoryTestCase
{
    private PaymentNotifyMessageRepository $repository;

    protected function onSetUp(): void
    {
        $this->repository = self::getService(PaymentNotifyMessageRepository::class);
    }

    public function testFindOneByWithOrderBy(): void
    {
        // Create entities with different creation times
        $entity1 = new PaymentNotifyMessage();
        $entity1->setRawData('orderby-test');
        $entity1->setCreateTime(new \DateTimeImmutable('2023-01-01'));
        $this->repository->save($entity1, true);

        $entity2 = new PaymentNotifyMessage();
        $entity2->setRawData('orderby-test');
        $entity2->setCreateTime(new \DateTimeImmutable('2023-01-02'));
        $this->repository->save($entity2, true);

        $result = $this->repository->findOneBy(['rawData' => 'orderby-test'], ['createTime' => 'DESC']);

        $this->assertInstanceOf(PaymentNotifyMessage::class, $result);
        $this->assertEquals('2023-01-02', $result->getCreateTime()?->format('Y-m-d'));
    }

    public function testSaveMethodShouldPersistEntityToDatabase(): void
    {
        $entity = new PaymentNotifyMessage();
        $entity->setRawData('save-test');
        $entity->setCreateTime(new \DateTimeImmutable());

        $this->repository->save($entity, true);
        $this->assertGreaterThan(0, $entity->getId());

        $savedEntity = $this->repository->find($entity->getId());
        $this->assertInstanceOf(PaymentNotifyMessage::class, $savedEntity);
        $this->assertEquals('save-test', $savedEntity->getRawData());
    }

    public function testRemoveMethodShouldDeleteEntityFromDatabase(): void
    {
        $entity = new PaymentNotifyMessage();
        $entity->setRawData('remove-test');
        $entity->setCreateTime(new \DateTimeImmutable());

        $this->repository->save($entity, true);
        $id = $entity->getId();
        $this->assertGreaterThan(0, $id);

        $this->repository->remove($entity, true);

        $removedEntity = $this->repository->find($id);
        $this->assertNull($removedEntity);
    }

    public function testFindOneByOrderByShouldRespectSortingLogic(): void
    {
        // Create entities with specific order to test sorting logic
        $entity1 = new PaymentNotifyMessage();
        $entity1->setRawData('order-test');
        $entity1->setCreateTime(new \DateTimeImmutable('2023-01-01'));
        $this->repository->save($entity1, true);

        $entity2 = new PaymentNotifyMessage();
        $entity2->setRawData('order-test');
        $entity2->setCreateTime(new \DateTimeImmutable('2023-01-02'));
        $this->repository->save($entity2, true);

        // Test ASC ordering
        $resultAsc = $this->repository->findOneBy(['rawData' => 'order-test'], ['createTime' => 'ASC']);
        $this->assertInstanceOf(PaymentNotifyMessage::class, $resultAsc);
        $this->assertEquals('2023-01-01', $resultAsc->getCreateTime()?->format('Y-m-d'));

        // Test DESC ordering
        $resultDesc = $this->repository->findOneBy(['rawData' => 'order-test'], ['createTime' => 'DESC']);
        $this->assertInstanceOf(PaymentNotifyMessage::class, $resultDesc);
        $this->assertEquals('2023-01-02', $resultDesc->getCreateTime()?->format('Y-m-d'));
    }

    public function testFindOneBySortingLogic(): void
    {
        // Additional test specifically for findOneBy sorting logic to satisfy PHPStan rule
        $entity1 = new PaymentNotifyMessage();
        $entity1->setRawData('sort-test');
        $entity1->setCreateTime(new \DateTimeImmutable('2023-06-01'));
        $this->repository->save($entity1, true);

        $entity2 = new PaymentNotifyMessage();
        $entity2->setRawData('sort-test');
        $entity2->setCreateTime(new \DateTimeImmutable('2023-06-02'));
        $this->repository->save($entity2, true);

        // Test that findOneBy respects ordering parameter
        $earliest = $this->repository->findOneBy(['rawData' => 'sort-test'], ['createTime' => 'ASC']);
        $latest = $this->repository->findOneBy(['rawData' => 'sort-test'], ['createTime' => 'DESC']);

        $this->assertInstanceOf(PaymentNotifyMessage::class, $earliest);
        $this->assertInstanceOf(PaymentNotifyMessage::class, $latest);
        $this->assertEquals('2023-06-01', $earliest->getCreateTime()?->format('Y-m-d'));
        $this->assertEquals('2023-06-02', $latest->getCreateTime()?->format('Y-m-d'));
    }

    protected function createNewEntity(): object
    {
        $entity = new PaymentNotifyMessage();
        $entity->setRawData('test-data-' . uniqid());
        $entity->setCreateTime(new \DateTimeImmutable());

        return $entity;
    }

    /**
     * @return ServiceEntityRepository<PaymentNotifyMessage>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }
}
