<?php

namespace WechatMiniProgramPayBundle\Procedure;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\JsonRPCLogBundle\Attribute\Log;
use Tourze\SnowflakeBundle\Service\Snowflake;
use WechatMiniProgramBundle\Entity\Account;
use WechatMiniProgramBundle\Service\AccountService;
use WechatPayBundle\Entity\Merchant;
use WechatPayBundle\Entity\PayOrder;
use WechatPayBundle\Enum\PayOrderStatus;
use WechatPayBundle\Repository\MerchantRepository;

/**
 * 微信小程序获取支付参数.
 *
 * @see https://pay.weixin.qq.com/wiki/doc/apiv3/open/pay/chapter2_8_0.shtml
 */
#[IsGranted(attribute: 'IS_AUTHENTICATED_FULLY')]
#[Log]
#[MethodDoc(summary: '微信小程序获取支付参数')]
#[MethodExpose(method: 'WechatMiniProgramMakePayTransaction')]
#[MethodTag(name: '微信支付')]
#[WithMonologChannel(channel: 'wechat_mini_program_pay')]
class WechatMiniProgramMakePayTransaction extends LockableProcedure
{
    #[MethodParam(description: '当前小程序的AppID')]
    public string $appId = '';

    #[MethodParam(description: '商户ID')]
    public string $mchId = '';

    #[MethodParam(description: '币种')]
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
        private readonly MerchantRepository $merchantRepository,
        private readonly RequestStack $requestStack,
        private readonly Snowflake $snowflake,
        private readonly WechatMiniProgramGetPayConfig $payConfigApi,
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $account = $this->getAccount();
        $merchant = $this->getMerchant();
        $payOrder = $this->createPayOrder($account, $merchant);

        return $this->generatePaymentConfig($payOrder);
    }

    private function getAccount(): Account
    {
        if (null === $this->accountService) {
            throw new ApiException('找不到微信小程序服务，请联系管理员');
        }
        $account = $this->accountService->detectAccountFromRequest($this->requestStack->getMainRequest(), $this->appId);
        if (null === $account) {
            throw new ApiException('找不到小程序');
        }

        return $account;
    }

    private function getMerchant(): Merchant
    {
        if ('' === $this->mchId) {
            $merchant = $this->merchantRepository->findOneBy([], ['id' => 'DESC']);
        } else {
            $merchant = $this->merchantRepository->findOneBy(['mchId' => $this->mchId]);
        }
        if (null === $merchant) {
            throw new ApiException('找不到支付配置');
        }

        return $merchant;
    }

    private function createPayOrder(Account $account, Merchant $merchant): PayOrder
    {
        $payOrder = new PayOrder();
        $payOrder->setMerchant($merchant);
        $payOrder->setStatus(PayOrderStatus::INIT);
        $payOrder->setAppId($account->getAppId());
        $payOrder->setMchId($merchant->getMchId());
        $payOrder->setTradeType('JSAPI');
        $payOrder->setTradeNo("WP{$this->snowflake->id()}");
        $payOrder->setAttach($this->attach);
        $payOrder->setDescription($this->description);
        $payOrder->setTotalFee($this->money);
        $payOrder->setFeeType($this->currency);

        $user = $this->security->getUser();
        if (null === $user) {
            throw new ApiException('用户未登录');
        }
        $payOrder->setOpenId($user->getUserIdentifier());

        $this->entityManager->persist($payOrder);
        $this->entityManager->flush();

        if (null === $payOrder->getPrepayId()) {
            throw new ApiException('找不到预支付交易会话标识');
        }

        return $payOrder;
    }

    /**
     * @return array<string, mixed>
     */
    private function generatePaymentConfig(PayOrder $payOrder): array
    {
        $payOrderId = $payOrder->getId();
        if (null === $payOrderId) {
            throw new ApiException('支付订单ID为空');
        }
        $this->payConfigApi->payOrderId = $payOrderId;

        $startTime = microtime(true);
        $this->logger->info('微信小程序获取支付配置接口请求开始', [
            'pay_order_id' => $payOrder->getId(),
            'app_id' => $payOrder->getAppId(),
            'trade_no' => $payOrder->getTradeNo(),
        ]);

        try {
            $result = $this->payConfigApi->execute();
            $endTime = microtime(true);
            $this->logger->info('微信小程序获取支付配置接口请求成功', [
                'pay_order_id' => $payOrder->getId(),
                'app_id' => $payOrder->getAppId(),
                'trade_no' => $payOrder->getTradeNo(),
                'response_time' => round(($endTime - $startTime) * 1000, 2) . 'ms',
                'response_size' => strlen((string) json_encode($result)) . ' bytes',
            ]);

            return $result;
        } catch (\Throwable $e) {
            $endTime = microtime(true);
            $this->logger->error('微信小程序获取支付配置接口请求失败', [
                'pay_order_id' => $payOrder->getId(),
                'app_id' => $payOrder->getAppId(),
                'trade_no' => $payOrder->getTradeNo(),
                'response_time' => round(($endTime - $startTime) * 1000, 2) . 'ms',
                'error_message' => $e->getMessage(),
                'error_type' => get_class($e),
            ]);
            throw $e;
        }
    }
}
