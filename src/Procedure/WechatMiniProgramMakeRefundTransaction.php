<?php

namespace WechatMiniProgramPayBundle\Procedure;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\JsonRPCLogBundle\Attribute\Log;
use WechatMiniProgramBundle\Repository\AccountRepository;
use WechatPayBundle\Entity\RefundOrder;
use WechatPayBundle\Repository\PayOrderRepository;

/**
 * 微信退款接口.
 *
 * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_9.shtml
 * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_10.shtml
 */
#[MethodDoc(summary: '微信退款接口')]
#[MethodExpose(method: 'WechatMiniProgramMakeRefundTransaction')]
#[MethodTag(name: '微信支付')]
#[Log]
#[IsGranted(attribute: 'IS_AUTHENTICATED_FULLY')]
class WechatMiniProgramMakeRefundTransaction extends LockableProcedure
{
    /**
     * @var string 当前小程序的AppID
     */
    #[Assert\NotNull]
    public string $appId;

    /**
     * @var int 原支付交易单号
     */
    public int $payOrderId;

    /**
     * @var string 退款原因
     */
    public string $reason;

    /**
     * @var int 金额
     */
    public int $money;

    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly PayOrderRepository $payOrderRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $account = $this->accountRepository->findOneBy([
            'appId' => $this->appId,
        ]);
        if (null === $account) {
            throw new ApiException('找不到小程序');
        }

        $payOrder = $this->payOrderRepository->findOneBy([
            'appId' => $account->getAppId(),
            'id' => $this->payOrderId,
        ]);
        if (null === $payOrder) {
            throw new ApiException('找不到原支付单号');
        }

        $refundOrder = new RefundOrder();
        $refundOrder->setAppId($account->getAppId());
        $refundOrder->setPayOrder($payOrder);
        $refundOrder->setReason($this->reason);
        $refundOrder->setMoney($this->money);
        $this->entityManager->persist($refundOrder);
        $this->entityManager->flush();

        return [
            '__message' => '申请成功',
        ];
    }
}
