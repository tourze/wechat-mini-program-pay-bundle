<?php

namespace WechatMiniProgramPayBundle\Procedure;

use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\JsonRPCLogBundle\Attribute\Log;
use WechatMiniProgramBundle\Repository\AccountRepository;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;
use WechatPayBundle\Entity\Merchant;
use WechatPayBundle\Entity\PayOrder;
use WechatPayBundle\Enum\PayOrderStatus;
use WechatPayBundle\Repository\PayOrderRepository;
use WechatPayBundle\Service\WechatPayBuilder;
use Yiisoft\Json\Json;

/**
 * TODO 微信小程序合单支付
 *
 * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter5_1_4.shtml
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[MethodExpose('WechatMiniProgramMakeCombinePayTransaction')]
#[Log]
#[WithMonologChannel('procedure')]
class WechatMiniProgramMakeCombinePayTransaction extends LockableProcedure
{
    /**
     * @var string 当前小程序的AppID
     */
    #[Assert\NotNull]
    public string $appId;

    /**
     * @var array 要去支付的子订单
     */
    public array $payOrderIds = [];

    public function __construct(
        private readonly ?AccountRepository $accountRepository,
        private readonly WechatPayBuilder $payBuilder,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PayOrderRepository $payOrderRepository,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function execute(): array
    {
        if (!$this->accountRepository) {
            throw new ApiException('找不到微信小程序服务，请联系管理员');
        }
        $account = $this->accountRepository->findOneBy([
            'appId' => $this->appId,
        ]);
        if (!$account) {
            throw new ApiException('找不到小程序');
        }

        // 查找子订单
        if (empty($this->payOrderIds)) {
            throw new ApiException('找不到子订单信息');
        }

        $subOrders = [];
        $attach = ['wechat-combine' => []];
        foreach ($this->payOrderIds as $payOrderId) {
            $entity = $this->payOrderRepository->find($payOrderId);
            if (!$entity) {
                throw new ApiException("找不到子订单[{$payOrderId}]");
            }

            $subOrders[] = $entity;
            $attach['wechat-combine'][] = $entity->getId();
        }

        $attach = Json::encode($attach);

        /** @var Merchant $payConfig */
        $payConfig = $account->getPayConfigs()[0];

        // 生成支付单
        $payOrder = new PayOrder();
        $payOrder->setMerchant($payConfig);
        $payOrder->setStatus(PayOrderStatus::INIT);
        $payOrder->setAppId($account->getAppId());
        $payOrder->setMchId($payConfig->getMchId());
        $payOrder->setTradeType('COMBINE'); // 微信支付实际没这种 tradeType 的，这里我直接挪用
        $payOrder->setTradeNo(Carbon::now()->format('YmdHis') . random_int(100000, 999999));
        $payOrder->setAttach($attach);

        $startTime = Carbon::now();
        $expireTime = $startTime->clone()->addMinutes(15);
        $payOrder->setStartTime($startTime);
        $payOrder->setExpireTime($expireTime);

        // 费用情况。合单支付的情况下，没价格信息的
        $payOrder->setTotalFee(0);
        $payOrder->setFeeType('');

        // 支付者信息
        $payOrder->setOpenId($this->security->getUser()->getUserIdentifier());

        // 回调地址也保存起来吧
        $payOrder->setNotifyUrl($this->urlGenerator->generate('wechat_mini_program_combine_pay_callback', [
            'appId' => $account->getAppId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL));

        $requestJson = [
            'combine_appid' => $account->getAppId(),
            'combine_mchid' => $payConfig->getMchId(),
            'combine_out_trade_no' => $payOrder->getTradeNo(),
            'time_expire' => $payOrder->getExpireTime()->format('Y-m-dTH:i:s+08:00'),
            'notify_url' => $payOrder->getNotifyUrl(),
            'combine_payer_info' => [
                'openid' => $payOrder->getOpenId(),
            ],
            'scene_info' => [
                'payer_client_ip' => $this->requestStack->getCurrentRequest()->getClientIp(),
            ],
            'sub_orders' => [],
        ];
        foreach ($subOrders as $subOrder) {
            $requestJson['sub_orders'][] = [
                'mchid' => $subOrder->getMchId(),
                'attach' => Json::encode([
                    PayOrder::class => [$subOrder->getId()],
                ]),
                'amount' => [
                    'total_amount' => $subOrder->getTotalFee(),
                    'currency' => $subOrder->getFeeType(),
                ],
            ];
        }

        $payOrder->setRequestJson(Json::encode($requestJson));
        $builder = $this->payBuilder->genBuilder($payConfig);
        $response = $builder->chain('v3/combine-transactions/jsapi')->post([
            'json' => $requestJson,
        ]);
        $response = $response->getBody()->getContents();
        $payOrder->setResponseJson($response);
        $response = Json::decode($response);
        $this->logger->info('微信合并下单接口', [
            'request' => $requestJson,
            'response' => $response,
        ]);

        $this->entityManager->wrapInTransaction(function () use ($payOrder, &$subOrders) {
            // 存放父订单
            $this->entityManager->persist($payOrder);
            // 更新子订单
            foreach ($subOrders as $subOrder) {
                $subOrder->setParent($payOrder);
                $this->entityManager->persist($subOrder);
            }
        });

        if (!isset($response['prepay_id'])) {
            throw new ApiException($response['err_code']);
        }

        // @link https://github.com/wechatpay-apiv3/wechatpay-php#%E7%AD%BE%E5%90%8D
        $merchantPrivateKeyInstance = Rsa::from($payConfig->getPemKey());
        $params = [
            'appId' => $account->getAppId(),
            'timeStamp' => (string) Formatter::timestamp(),
            'nonceStr' => Formatter::nonce(),
            'package' => "prepay_id={$response['prepay_id']}",
        ];
        $params += ['paySign' => Rsa::sign(
            Formatter::joinedByLineFeed(...array_values($params)),
            $merchantPrivateKeyInstance
        ), 'signType' => 'RSA'];

        return $params;
    }
}
