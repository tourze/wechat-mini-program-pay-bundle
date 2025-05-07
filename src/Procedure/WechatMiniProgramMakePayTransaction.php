<?php

namespace WechatMiniProgramPayBundle\Procedure;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\JsonRPCLogBundle\Attribute\Log;
use Tourze\SnowflakeBundle\Service\Snowflake;
use WechatMiniProgramBundle\Service\AccountService;
use WechatPayBundle\Entity\PayOrder;
use WechatPayBundle\Enum\PayOrderStatus;
use WechatPayBundle\Repository\MerchantRepository;
use WechatPayBundle\Repository\PayOrderRepository;

/**
 * 微信小程序获取支付参数
 *
 * @see https://pay.weixin.qq.com/wiki/doc/apiv3/open/pay/chapter2_8_0.shtml
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Log]
#[MethodExpose('WechatMiniProgramMakePayTransaction')]
class WechatMiniProgramMakePayTransaction extends LockableProcedure
{
    #[MethodParam('当前小程序的AppID')]
    public string $appId = '';

    #[MethodParam('商户ID')]
    public string $mchId = '';

    #[MethodParam('币种')]
    public string $currency = 'CNY';

    /**
     * @var int 在一些比较特别的情境中，需要前端传入价格
     */
    #[Assert\Range(min: 0)]
    public int $money = 0;

    /**
     * @var string 描述
     */
    public string $description = '新订单';

    /**
     * @var string 附加信息
     */
    public string $attach = '';

    public function __construct(
        private readonly ?AccountService $accountService,
        private readonly PayOrderRepository $payOrderRepository,
        private readonly MerchantRepository $merchantRepository,
        private readonly RequestStack $requestStack,
        private readonly Snowflake $snowflake,
        private readonly WechatMiniProgramGetPayConfig $payConfigApi,
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function execute(): array
    {
        if (!$this->accountService) {
            throw new ApiException('找不到微信小程序服务，请联系管理员');
        }
        $account = $this->accountService->detectAccountFromRequest($this->requestStack->getMainRequest(), $this->appId);
        if (!$account) {
            throw new ApiException('找不到小程序');
        }

        // 如果没声明，我们就取第一个支付配置
        if (empty($this->mchId)) {
            $merchant = $this->merchantRepository->findOneBy([], ['id' => 'DESC']);
        } else {
            $merchant = $this->merchantRepository->findOneBy([
                'mchId' => $this->mchId,
            ]);
        }
        if (!$merchant) {
            throw new ApiException('找不到支付配置');
        }

        // 生成支付单
        $payOrder = new PayOrder();
        $payOrder->setMerchant($merchant);
        $payOrder->setStatus(PayOrderStatus::INIT);
        $payOrder->setAppId($account->getAppId());
        $payOrder->setMchId($merchant->getMchId());
        $payOrder->setTradeType('JSAPI');
        $payOrder->setTradeNo("WP{$this->snowflake->id()}");
        $payOrder->setAttach($this->attach);
        $payOrder->setDescription($this->description);

        // 费用情况
        $payOrder->setTotalFee($this->money);
        $payOrder->setFeeType($this->currency);

        // 支付者信息
        $payOrder->setOpenId($this->security->getUser()->getUserIdentifier());

        // 保存支付订单
        $this->entityManager->persist($payOrder);
        $this->entityManager->flush();
        if (!$payOrder->getPrepayId()) {
            throw new ApiException('找不到预支付交易会话标识');
        }

        $this->payConfigApi->payOrderId = $payOrder->getId();

        return $this->payConfigApi->execute();
    }
}
