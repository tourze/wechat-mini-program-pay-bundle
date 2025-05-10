<?php

namespace WechatMiniProgramPayBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tourze\Symfony\Async\Attribute\Async;
use Tourze\WechatMiniProgramUserContracts\UserLoaderInterface;
use WechatMiniProgramAuthBundle\Entity\User;
use WechatMiniProgramBundle\Service\Client;
use WechatMiniProgramPayBundle\Event\PayCallbackSuccessEvent;
use WechatMiniProgramPayBundle\Request\GetPaidUnionIdRequest;

class PaySuccessGetUnionIdSubscriber
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly UserLoaderInterface $userLoader,
        private readonly Client $client,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Async]
    #[AsEventListener]
    public function onPayCallbackSuccess(PayCallbackSuccessEvent $event): void
    {
        $user = $this->userLoader->loadUserByOpenId($event->getPayOrder()->getOpenId());
        if ($user?->getUnionId()) {
            return;
        }

        if (!$user) {
            $user = new User();
            $user->setAccount($event->getAccount());
            $user->setOpenId($event->getPayOrder()->getOpenId());
        }

        $request = new GetPaidUnionIdRequest();
        $request->setAccount($event->getAccount());
        $request->setOpenId($event->getPayOrder()->getOpenId());
        try {
            $response = $this->client->request($request);
        } catch (\Throwable $exception) {
            $this->logger->error('支付后获取 Unionid 失败', [
                'request' => $request,
                'exception' => $exception,
            ]);

            return;
        }

        $user->setUnionId($response['unionid']);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
