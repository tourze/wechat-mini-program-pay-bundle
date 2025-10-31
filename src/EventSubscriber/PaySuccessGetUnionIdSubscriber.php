<?php

namespace WechatMiniProgramPayBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tourze\Symfony\AopAsyncBundle\Attribute\Async;
use Tourze\WechatMiniProgramUserContracts\UserLoaderInterface;
use WechatMiniProgramAuthBundle\Service\UserService;
use WechatMiniProgramBundle\Service\Client;
use WechatMiniProgramPayBundle\Event\PayCallbackSuccessEvent;
use WechatMiniProgramPayBundle\Request\GetPaidUnionIdRequest;

#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'wechat_mini_program_pay')]
class PaySuccessGetUnionIdSubscriber
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly UserLoaderInterface $userLoader,
        private readonly UserService $userService,
        private readonly Client $client,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Async]
    #[AsEventListener]
    public function onPayCallbackSuccess(PayCallbackSuccessEvent $event): void
    {
        $openId = $event->getPayOrder()->getOpenId();
        if (null === $openId) {
            return;
        }

        $user = $this->userLoader->loadUserByOpenId($openId);
        if (null !== $user?->getUnionId()) {
            return;
        }

        if (null === $user) {
            $user = $this->userService->createUser($event->getAccount(), $openId);
        }

        $request = new GetPaidUnionIdRequest();
        $request->setAccount($event->getAccount());
        $request->setOpenId($openId);
        try {
            $response = $this->client->request($request);
        } catch (\Throwable $exception) {
            $this->logger->error('支付后获取 Unionid 失败', [
                'request' => $request,
                'exception' => $exception,
            ]);

            return;
        }

        // 验证响应数据结构
        if (!is_array($response) || !array_key_exists('unionid', $response)) {
            $this->logger->error('获取 Unionid 响应数据格式错误', [
                'response' => $response,
                'openId' => $openId,
            ]);

            return;
        }

        $unionId = $response['unionid'];
        if (!is_string($unionId) || '' === $unionId) {
            $this->logger->error('Unionid 数据无效', [
                'unionid' => $unionId,
                'openId' => $openId,
            ]);

            return;
        }

        if (method_exists($user, 'setUnionId')) {
            $user->setUnionId($unionId);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } else {
            $this->logger->warning('User object does not have setUnionId method', [
                'user_class' => get_class($user),
                'unionid' => $unionId,
            ]);
        }
    }
}
