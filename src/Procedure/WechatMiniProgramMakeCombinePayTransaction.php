<?php

namespace WechatMiniProgramPayBundle\Procedure;

use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\JsonRPCLogBundle\Attribute\Log;
use WechatMiniProgramBundle\Entity\Account;
use WechatMiniProgramBundle\Repository\AccountRepository;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;
use WechatPayBundle\Entity\Merchant;
use WechatPayBundle\Entity\PayOrder;
use WechatPayBundle\Enum\PayOrderStatus;
use WechatPayBundle\Repository\MerchantRepository;
use WechatPayBundle\Repository\PayOrderRepository;
use WechatPayBundle\Service\WechatPayBuilder;
use Yiisoft\Json\Json;

/**
 * TODO 微信小程序合单支付.
 *
 * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter5_1_4.shtml
 */
#[IsGranted(attribute: 'IS_AUTHENTICATED_FULLY')]
#[MethodDoc(summary: '微信小程序合单支付')]
#[MethodExpose(method: 'WechatMiniProgramMakeCombinePayTransaction')]
#[MethodTag(name: '微信支付')]
#[Log]
#[WithMonologChannel(channel: 'procedure')]
class WechatMiniProgramMakeCombinePayTransaction extends LockableProcedure
{
    /**
     * @var string 当前小程序的AppID
     */
    #[Assert\NotNull]
    public string $appId;

    /**
     * @var array<string> 要去支付的子订单
     */
    public array $payOrderIds = [];

    public function __construct(
        private readonly ?AccountRepository $accountRepository,
        private readonly WechatPayBuilder $payBuilder,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PayOrderRepository $payOrderRepository,
        private readonly MerchantRepository $merchantRepository,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $account = $this->getAccount();
        $subOrders = $this->getSubOrders();
        $merchant = $this->getMerchant();
        $payOrder = $this->createPayOrder($account, $merchant, $subOrders);
        $response = $this->makePaymentRequest($payOrder, $subOrders, $merchant);
        $this->saveOrdersToDatabase($payOrder, $subOrders);

        return $this->generatePaymentParams($account, $merchant, $response);
    }

    private function getAccount(): Account
    {
        if (null === $this->accountRepository) {
            throw new ApiException('找不到微信小程序服务，请联系管理员');
        }
        $account = $this->accountRepository->findOneBy(['appId' => $this->appId]);
        if (null === $account) {
            throw new ApiException('找不到小程序');
        }

        return $account;
    }

    /**
     * @return array<PayOrder>
     */
    private function getSubOrders(): array
    {
        if ([] === $this->payOrderIds) {
            throw new ApiException('找不到子订单信息');
        }

        $subOrders = [];
        foreach ($this->payOrderIds as $payOrderId) {
            $entity = $this->payOrderRepository->find($payOrderId);
            if (null === $entity) {
                throw new ApiException("找不到子订单[{$payOrderId}]");
            }
            $subOrders[] = $entity;
        }

        return $subOrders;
    }

    private function getMerchant(): Merchant
    {
        $merchant = $this->merchantRepository->findOneBy([], ['id' => 'DESC']);
        if (null === $merchant) {
            throw new ApiException('找不到支付配置');
        }

        return $merchant;
    }

    /**
     * @param array<PayOrder> $subOrders
     */
    /**
     * @param array<PayOrder> $subOrders
     */
    private function createPayOrder(Account $account, Merchant $merchant, array $subOrders): PayOrder
    {
        $attach = ['wechat-combine' => array_map(fn ($order) => $order->getId(), $subOrders)];

        $payOrder = new PayOrder();
        $payOrder->setMerchant($merchant);
        $payOrder->setStatus(PayOrderStatus::INIT);
        $payOrder->setAppId($account->getAppId());
        $payOrder->setMchId($merchant->getMchId());
        $payOrder->setTradeType('COMBINE');
        $payOrder->setTradeNo(CarbonImmutable::now()->format('YmdHis') . random_int(100000, 999999));
        $payOrder->setAttach(Json::encode($attach));

        $startTime = CarbonImmutable::now();
        $payOrder->setStartTime($startTime);
        $payOrder->setExpireTime($startTime->clone()->addMinutes(15));
        $payOrder->setTotalFee(0);
        $payOrder->setFeeType('');
        $user = $this->security->getUser();
        if (null === $user) {
            throw new ApiException('用户未登录');
        }
        $payOrder->setOpenId($user->getUserIdentifier());
        $payOrder->setNotifyUrl($this->urlGenerator->generate('wechat_mini_program_combine_pay_callback', [
            'appId' => $account->getAppId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL));

        return $payOrder;
    }

    /**
     * @param array<PayOrder> $subOrders
     *
     * @return array<string, mixed>
     */
    private function makePaymentRequest(PayOrder $payOrder, array $subOrders, Merchant $merchant): array
    {
        $requestJson = [
            'combine_appid' => $payOrder->getAppId(),
            'combine_mchid' => $payOrder->getMchId(),
            'combine_out_trade_no' => $payOrder->getTradeNo(),
            'time_expire' => $payOrder->getExpireTime()?->format('Y-m-dTH:i:s+08:00') ?? '',
            'notify_url' => $payOrder->getNotifyUrl(),
            'combine_payer_info' => ['openid' => $payOrder->getOpenId()],
            'scene_info' => ['payer_client_ip' => $this->requestStack->getCurrentRequest()?->getClientIp() ?? '127.0.0.1'],
            'sub_orders' => $this->buildSubOrdersData($subOrders),
        ];

        $payOrder->setRequestJson(Json::encode($requestJson));
        $builder = $this->payBuilder->genBuilder($merchant);

        // 外部系统交互审计日志：记录请求开始时间
        $startTime = microtime(true);
        $this->logger->info('微信合并下单接口请求开始', [
            'request' => $requestJson,
            'merchant_id' => $merchant->getMchId(),
            'trade_no' => $payOrder->getTradeNo(),
        ]);

        try {
            $response = $builder->chain('v3/combine-transactions/jsapi')->post(['json' => $requestJson]);
            $responseContent = $response->getBody()->getContents();
            $payOrder->setResponseJson($responseContent);
            $decodedResponseRaw = Json::decode($responseContent);

            // 确保decodedResponse是数组且键为字符串
            if (!is_array($decodedResponseRaw)) {
                throw new ApiException('响应数据格式错误');
            }

            // 类型断言：确保是 array<string, mixed>
            /** @var array<string, mixed> $decodedResponse */
            $decodedResponse = $decodedResponseRaw;

            // 外部系统交互审计日志：记录成功响应
            $endTime = microtime(true);
            $this->logger->info('微信合并下单接口请求成功', [
                'request' => $requestJson,
                'response' => $decodedResponse,
                'duration_ms' => round(($endTime - $startTime) * 1000, 2),
                'merchant_id' => $merchant->getMchId(),
                'trade_no' => $payOrder->getTradeNo(),
            ]);
        } catch (\Throwable $e) {
            // 外部系统交互审计日志：记录异常
            $endTime = microtime(true);
            $this->logger->error('微信合并下单接口请求失败', [
                'request' => $requestJson,
                'exception' => $e->getMessage(),
                'duration_ms' => round(($endTime - $startTime) * 1000, 2),
                'merchant_id' => $merchant->getMchId(),
                'trade_no' => $payOrder->getTradeNo(),
            ]);
            throw $e;
        }

        if (!isset($decodedResponse['prepay_id'])) {
            $errCode = $decodedResponse['err_code'] ?? '未知错误';
            throw new ApiException(is_string($errCode) ? $errCode : '响应错误');
        }

        return $decodedResponse;
    }

    /**
     * @param array<PayOrder> $subOrders
     *
     * @return array<array<string, mixed>>
     */
    private function buildSubOrdersData(array $subOrders): array
    {
        $subOrdersData = [];
        foreach ($subOrders as $subOrder) {
            $subOrdersData[] = [
                'mchid' => $subOrder->getMchId(),
                'attach' => Json::encode([PayOrder::class => [$subOrder->getId()]]),
                'amount' => [
                    'total_amount' => $subOrder->getTotalFee(),
                    'currency' => $subOrder->getFeeType(),
                ],
            ];
        }

        return $subOrdersData;
    }

    /**
     * @param array<PayOrder> $subOrders
     */
    private function saveOrdersToDatabase(PayOrder $payOrder, array $subOrders): void
    {
        $this->entityManager->wrapInTransaction(function () use ($payOrder, $subOrders): void {
            $this->entityManager->persist($payOrder);
            foreach ($subOrders as $subOrder) {
                $subOrder->setParent($payOrder);
                $this->entityManager->persist($subOrder);
            }
        });
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array<string, mixed>
     */
    private function generatePaymentParams(Account $account, Merchant $merchant, array $response): array
    {
        $merchantPrivateKeyInstance = Rsa::from($merchant->getPemKey());

        // 确保prepay_id是字符串
        $prepayId = $response['prepay_id'] ?? '';
        if (!is_string($prepayId)) {
            throw new ApiException('prepay_id必须是字符串');
        }

        $params = [
            'appId' => $account->getAppId(),
            'timeStamp' => (string) Formatter::timestamp(),
            'nonceStr' => Formatter::nonce(),
            'package' => "prepay_id={$prepayId}",
        ];
        $params += [
            'paySign' => Rsa::sign(Formatter::joinedByLineFeed(...array_values($params)), $merchantPrivateKeyInstance),
            'signType' => 'RSA',
        ];

        return $params;
    }
}
