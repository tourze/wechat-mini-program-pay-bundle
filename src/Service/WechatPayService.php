<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tourze\PaymentContracts\Enum\PaymentType;
use Tourze\PaymentContracts\Interface\PaymentGatewayInterface;
use WechatMiniProgramBundle\Entity\Account;
use WechatMiniProgramBundle\Service\AccountService;
use WechatMiniProgramBundle\Service\PayClient;
use WechatMiniProgramPayBundle\Config\WechatPayConfig;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WechatMiniProgramPayBundle\Request\UnifiedOrderRequest;
use WechatMiniProgramPayBundle\Response\UnifiedOrderResponse;
use WechatPayBundle\Entity\Merchant;
use WechatPayBundle\Entity\PayOrder;
use WechatPayBundle\Enum\PayOrderStatus;
use WechatPayBundle\Repository\MerchantRepository;
use WechatPayBundle\Repository\PayOrderRepository;

/**
 * 微信支付服务
 *
 * 处理微信支付相关业务逻辑
 */
#[WithMonologChannel(channel: 'wechat_mini_program_pay')]
class WechatPayService implements PaymentGatewayInterface
{
    public function __construct(
        private readonly ?AccountService $accountService,
        private readonly MerchantRepository $merchantRepository,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
        private readonly SignatureService $signatureService,
        private readonly TradeTypeMapper $tradeTypeMapper,
        private readonly PayClient $client,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PayOrderRepository $payOrderRepository,
    ) {
    }

    /**
     * 获取微信支付参数.
     *
     * @param array<string, mixed> $extraParams
     *
     * @return array<string, mixed>
     */
    public function getWechatPayParams(string $orderNumber, float $amount, array $extraParams = []): array
    {
        try {
            // 获取小程序账户
            $appId = $extraParams['appId'] ?? '';
            if (!is_string($appId)) {
                throw new PaymentConfigurationException('AppId必须是字符串');
            }
            $account = $this->getAccount($appId);

            // 获取商户配置
            $mchId = $extraParams['mchId'] ?? '';
            if (!is_string($mchId)) {
                throw new PaymentConfigurationException('MchId必须是字符串');
            }
            $merchant = $this->getMerchant($mchId);

            // 使用新的统一下单API
            $paymentType = $extraParams['paymentType'] ?? PaymentType::WECHAT_MINI_PROGRAM;
            if (!$paymentType instanceof PaymentType) {
                $paymentType = PaymentType::WECHAT_MINI_PROGRAM;
            }

            return $this->unifiedOrder($account, $merchant, $orderNumber, $amount, $paymentType, $extraParams);
        } catch (\Exception $e) {
            $this->logger->error('获取微信支付参数失败', [
                'orderNumber' => $orderNumber,
                'amount' => $amount,
                'extraParams' => $extraParams,
                'error' => $e->getMessage(),
            ]);
            throw new PaymentConfigurationException('获取微信支付参数失败');
        }
    }

    private function getAccount(string $appId): Account
    {
        if (null === $this->accountService) {
            throw new PaymentConfigurationException('找不到微信小程序服务，请联系管理员');
        }

        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            // 创建一个简单的 Request 对象用于非 HTTP 上下文
            $request = new Request();
        }

        $account = $this->accountService->detectAccountFromRequest($request, $appId);
        if (null === $account) {
            throw new PaymentConfigurationException('找不到小程序账户');
        }

        return $account;
    }

    private function getMerchant(string $mchId): Merchant
    {
        if ('' === $mchId) {
            $merchant = $this->merchantRepository->findOneBy([], ['id' => 'DESC']);
        } else {
            $merchant = $this->merchantRepository->findOneBy(['mchId' => $mchId]);
        }

        if (null === $merchant) {
            throw new PaymentConfigurationException('找不到支付配置');
        }

        return $merchant;
    }

    /**
     * 新的统一下单方法.
     *
     * @param array<string, mixed> $extraParams
     *
     * @return array<string, mixed>
     */
    private function unifiedOrder(
        Account $account,
        Merchant $merchant,
        string $orderNumber,
        float $amount,
        PaymentType $paymentType,
        array $extraParams = [],
    ): array {
        // 创建支付配置
        $config = $this->createPayConfig($merchant, $account);

        if (!isset($extraParams['notifyUrl'])) {
            $notifyUrl = $this->urlGenerator->generate('wechat_app_unified_order_pay_callback_v2', [
                'tradeNo' => $orderNumber,
            ], UrlGeneratorInterface::ABSOLUTE_URL);
            $extraParams['notifyUrl'] = $notifyUrl;
        }
        // 创建统一下单请求
        $request = $this->createUnifiedOrderRequest(
            $config,
            $account,
            $orderNumber,
            $amount,
            $paymentType,
            $extraParams
        );

        // 发送请求并处理响应
        $response = $this->sendUnifiedOrderRequest($request, $config);

        // 创建支付订单记录
        $payOrder = $this->createPayOrderFromResponse($account, $merchant, $orderNumber, $amount, $response, $extraParams);

        // 生成客户端支付参数
        return $this->generateClientPayParams($response, $config, $paymentType);
    }

    private function createPayConfig(Merchant $merchant, Account $account): WechatPayConfig
    {
        return WechatPayConfig::fromArray([
            'app_id' => $account->getAppId(),
            'mch_id' => $merchant->getMchId(),
            'key' => $merchant->getApiKey(),
            'sign_type' => 'MD5',
        ]);
    }

    /**
     * @param array<string, mixed> $extraParams
     */
    private function createUnifiedOrderRequest(
        WechatPayConfig $config,
        Account $account,
        string $orderNumber,
        float $amount,
        PaymentType $paymentType,
        array $extraParams,
    ): UnifiedOrderRequest {
        $request = $this->buildBaseRequest($config, $account, $orderNumber, $amount, $extraParams);
        $this->setPaymentTypeParameters($request, $paymentType);
        $this->setRequiredParameters($request, $paymentType, $extraParams, $orderNumber);
        $this->setOptionalParameters($request, $extraParams);

        return $request;
    }

    /**
     * @param array<string, mixed> $extraParams
     */
    private function buildBaseRequest(
        WechatPayConfig $config,
        Account $account,
        string $orderNumber,
        float $amount,
        array $extraParams,
    ): UnifiedOrderRequest {
        $totalFee = (int) ($amount * 100);

        $request = new UnifiedOrderRequest();
        $request->setAccount($account);
        $request->setAppId($config->getAppId());
        $request->setMchId($config->getMchId());
        $request->setNonceStr($this->signatureService->generateNonceStr());
        $request->setOutTradeNo($orderNumber);
        $request->setTotalFee($totalFee);
        $request->setSpbillCreateIp($this->getClientIp());
        $request->setSignType($config->getSignType());

        $description = $extraParams['description'] ?? '订单支付-' . $orderNumber;
        if (is_string($description)) {
            $request->setBody($description);
        }

        $notifyUrl = $extraParams['notifyUrl'] ?? '';
        if (is_string($notifyUrl)) {
            $request->setNotifyUrl($notifyUrl);
        }

        return $request;
    }

    private function setPaymentTypeParameters(UnifiedOrderRequest $request, PaymentType $paymentType): void
    {
        $request->setTradeTypeFromPaymentType($paymentType);
    }

    /**
     * @param array<string, mixed> $extraParams
     */
    private function setRequiredParameters(
        UnifiedOrderRequest $request,
        PaymentType $paymentType,
        array $extraParams,
        string $orderNumber,
    ): void {
        $tradeType = $this->tradeTypeMapper->mapToTradeType($paymentType);
        $requiredParams = $this->tradeTypeMapper->getRequiredParameters($tradeType);

        foreach ($requiredParams as $param) {
            $this->setRequiredParameter($request, $param, $extraParams, $orderNumber);
        }
    }

    /**
     * @param array<string, mixed> $extraParams
     */
    private function setRequiredParameter(
        UnifiedOrderRequest $request,
        string $param,
        array $extraParams,
        string $orderNumber,
    ): void {
        switch ($param) {
            case 'openid':
                $openId = $extraParams['openId'] ?? '';
                if (is_string($openId)) {
                    $request->setOpenId($openId);
                }
                break;
            case 'product_id':
                $productId = $extraParams['productId'] ?? $orderNumber;
                if (is_string($productId)) {
                    $request->setProductId($productId);
                }
                break;
            case 'scene_info':
                $sceneInfo = $extraParams['sceneInfo'] ?? [];
                if (is_array($sceneInfo)) {
                    $validatedSceneInfo = $this->validateSceneInfo($sceneInfo);
                    $request->setSceneInfo($validatedSceneInfo);
                }
                break;
        }
    }

    /**
     * @param array<string, mixed> $extraParams
     */
    private function setOptionalParameters(UnifiedOrderRequest $request, array $extraParams): void
    {
        if (isset($extraParams['attach']) && is_string($extraParams['attach'])) {
            $request->setAttach($extraParams['attach']);
        }
        if (isset($extraParams['detail']) && is_string($extraParams['detail'])) {
            $request->setDetail($extraParams['detail']);
        }
    }

    /**
     * @param array<mixed, mixed> $sceneInfo
     *
     * @return array<string, mixed>
     */
    private function validateSceneInfo(array $sceneInfo): array
    {
        $validated = [];
        foreach ($sceneInfo as $key => $value) {
            if (is_string($key)) {
                $validated[$key] = $value;
            }
        }

        return $validated;
    }

    /**
     * @param array<mixed, mixed> $extraParams
     *
     * @return array<string, mixed>
     */
    private function validateExtraParams(array $extraParams): array
    {
        $validated = [];
        foreach ($extraParams as $key => $value) {
            if (is_string($key)) {
                $validated[$key] = $value;
            }
        }

        return $validated;
    }

    private function sendUnifiedOrderRequest(UnifiedOrderRequest $request, WechatPayConfig $config): UnifiedOrderResponse
    {
        // 生成签名
        $signedParams = $this->signatureService->signParameters(
            $request->toArray(),
            $config->getKey(),
            $config->getSignType()
        );
        $sign = $signedParams['sign'] ?? '';
        if (is_string($sign)) {
            $request->setSign($sign);
        }

        try {
            $xmlResponse = $this->client->request($request);
        } catch (\Throwable $exception) {
            $this->logger->error('统一下单请求失败', [
                'request' => $request,
                'exception' => $exception,
                'out_trade_no' => $request->getOutTradeNo(),
            ]);
            throw new PaymentConfigurationException('统一下单请求失败: ' . $exception->getMessage());
        }

        if (!is_string($xmlResponse)) {
            throw new PaymentConfigurationException('响应数据格式错误');
        }

        $response = UnifiedOrderResponse::fromXml($xmlResponse);

        // 验证签名
        if (!$response->verifySignature($config->getKey(), $config->getSignType())) {
            $this->logger->error('响应签名验证失败', [
                'out_trade_no' => $request->getOutTradeNo(),
            ]);
            throw new PaymentConfigurationException('响应签名验证失败');
        }

        if (!$response->isSuccess()) {
            $this->logger->error('统一下单失败', [
                'error' => $response->getErrorMessage(),
                'out_trade_no' => $request->getOutTradeNo(),
            ]);
            throw new PaymentConfigurationException('统一下单失败: ' . $response->getErrorMessage());
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $extraParams
     */
    private function createPayOrderFromResponse(
        Account $account,
        Merchant $merchant,
        string $orderNumber,
        float $amount,
        UnifiedOrderResponse $response,
        array $extraParams,
    ): PayOrder {
        $totalFee = (int) ($amount * 100);

        $payOrder = $this->payOrderRepository->findOneBy(['tradeNo' => $orderNumber]);
        if (null === $payOrder) {
            $payOrder = new PayOrder();
            $payOrder->setMerchant($merchant);
        } else {
            // 如果订单已经存在且状态不是 INIT，则不允许重复创建
            if (PayOrderStatus::INIT !== $payOrder->getStatus()) {
                throw new PaymentConfigurationException('支付订单已存在且不可修改');
            }
        }

        $payOrder->setStatus(PayOrderStatus::INIT);
        $payOrder->setBody('');
        $payOrder->setAppId($account->getAppId());
        $payOrder->setMchId($merchant->getMchId());
        $payOrder->setTradeType($response->getTradeType());
        $payOrder->setTradeNo($orderNumber);
        $notifyUrl = $extraParams['notifyUrl'] ?? '';
        if (is_string($notifyUrl)) {
            $payOrder->setNotifyUrl($notifyUrl);
        }

        $attach = $extraParams['attach'] ?? '';
        if (is_string($attach)) {
            $payOrder->setAttach($attach);
        }

        $description = $extraParams['description'] ?? '订单支付-' . $orderNumber;
        if (is_string($description)) {
            $payOrder->setDescription($description);
        }
        $payOrder->setTotalFee($totalFee);
        $payOrder->setFeeType('CNY');
        $payOrder->setPrepayId($response->getPrepayId());

        $openId = $extraParams['openId'] ?? null;
        if (is_string($openId)) {
            $payOrder->setOpenId($openId);
        }

        $this->payOrderRepository->save($payOrder);

        return $payOrder;
    }

    /**
     * @return array<string, mixed>
     */
    private function generateClientPayParams(
        UnifiedOrderResponse $response,
        WechatPayConfig $config,
        PaymentType $paymentType,
    ): array {
        return match ($paymentType) {
            PaymentType::WECHAT_MINI_PROGRAM => $response->generateMiniProgramPayParams(
                $config->getKey(),
                $config->getSignType()
            ),
            PaymentType::WECHAT_APP => $response->generateAppPayParams(
                $config->getKey(),
                $config->getSignType()
            ),
            PaymentType::WECHAT_JSAPI,
            PaymentType::WECHAT_OFFICIAL_ACCOUNT,
            PaymentType::LEGACY_WECHAT_PAY => $response->generateJsApiPayParams(
                $config->getKey(),
                $config->getSignType()
            ),
            default => [
                'prepay_id' => $response->getPrepayId(),
                'code_url' => $response->getCodeUrl(),
                'mweb_url' => $response->getMwebUrl(),
            ],
        };
    }

    private function getClientIp(): string
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            return '127.0.0.1';
        }

        return $request->getClientIp() ?? '127.0.0.1';
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function getPaymentParams(array $params): array
    {
        $orderNumber = $params['orderNumber'] ?? '';
        $amount = $params['amount'] ?? 0;
        $extraParams = $params['extraParams'] ?? [];

        if (!is_string($orderNumber)) {
            throw new PaymentConfigurationException('订单号必须是字符串');
        }

        if (!is_numeric($amount)) {
            throw new PaymentConfigurationException('金额必须是数字');
        }

        if (!is_array($extraParams)) {
            throw new PaymentConfigurationException('额外参数必须是数组');
        }

        $validatedExtraParams = $this->validateExtraParams($extraParams);

        return $this->getWechatPayParams(
            orderNumber: $orderNumber,
            amount: (float) $amount,
            extraParams: $validatedExtraParams
        );
    }

    public function getSupportedPaymentType(): string
    {
        return 'wechat_pay';
    }

    /**
     * @param array<string, mixed> $params
     */
    public function validatePaymentParams(array $params): bool
    {
        return isset($params['orderNumber'], $params['amount'])
            && is_string($params['orderNumber'])
            && is_numeric($params['amount'])
            && $params['amount'] > 0;
    }
}
