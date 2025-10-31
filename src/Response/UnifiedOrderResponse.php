<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Response;

use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WechatMiniProgramPayBundle\Service\SignatureService;
use WechatMiniProgramPayBundle\Utils\XmlUtils;

/**
 * 微信支付统一下单响应处理器.
 *
 * 处理微信支付V2版本的统一下单API响应，支持：
 * - XML响应解析
 * - 签名验证
 * - 错误码映射
 * - 支付参数提取
 */
class UnifiedOrderResponse
{
    private const SUCCESS_RETURN_CODE = 'SUCCESS';
    private const SUCCESS_RESULT_CODE = 'SUCCESS';

    // 微信支付错误码映射
    private const ERROR_CODE_MAPPING = [
        'NOAUTH' => '商户未开通此接口权限',
        'NOTENOUGH' => '余额不足',
        'ORDERPAID' => '商户订单已支付',
        'ORDERCLOSED' => '订单已关闭',
        'SYSTEMERROR' => '系统错误',
        'APPID_NOT_EXIST' => 'APPID不存在',
        'MCHID_NOT_EXIST' => '商户号不存在',
        'APPID_MCHID_NOT_MATCH' => 'appid和mch_id不匹配',
        'LACK_PARAMS' => '缺少参数',
        'OUT_TRADE_NO_USED' => '商户订单号重复',
        'SIGNERROR' => '签名错误',
        'XML_FORMAT_ERROR' => 'XML格式错误',
        'REQUIRE_POST_METHOD' => '请使用post方法',
        'POST_DATA_EMPTY' => 'post数据为空',
        'NOT_UTF8' => '编码格式错误',
    ];

    /** @var array<string, mixed> */
    private array $data = [];

    private bool $isSuccess = false;

    private string $errorCode = '';

    private string $errorMessage = '';

    private string $prepayId = '';

    private string $codeUrl = '';

    private string $mwebUrl = '';

    /**
     * 从XML字符串构造响应对象
     */
    public static function fromXml(string $xml): self
    {
        $response = new self();
        $response->parseXml($xml);

        return $response;
    }

    /**
     * 解析XML响应.
     */
    private function parseXml(string $xml): void
    {
        if ('' === $xml) {
            throw new PaymentConfigurationException('响应内容为空');
        }

        try {
            $this->data = XmlUtils::xmlToArray($xml);
        } catch (PaymentConfigurationException $e) {
            throw new PaymentConfigurationException('响应XML解析失败: ' . $e->getMessage());
        }

        $this->processResponse();
    }

    /**
     * 处理响应数据.
     */
    private function processResponse(): void
    {
        // 检查返回状态
        $returnCode = $this->getField('return_code');
        if (self::SUCCESS_RETURN_CODE !== $returnCode) {
            $this->isSuccess = false;
            $this->errorCode = $returnCode;
            $this->errorMessage = $this->getField('return_msg', '通信失败');

            return;
        }

        // 检查业务结果
        $resultCode = $this->getField('result_code');
        if (self::SUCCESS_RESULT_CODE !== $resultCode) {
            $this->isSuccess = false;
            $this->errorCode = $this->getField('err_code', 'UNKNOWN_ERROR');
            $errCodeDes = $this->getField('err_code_des');
            $this->errorMessage = $this->mapErrorMessage($this->errorCode, '' !== $errCodeDes ? $errCodeDes : null);

            return;
        }

        // 成功响应，提取关键字段
        $this->isSuccess = true;
        $this->prepayId = $this->getField('prepay_id', '');
        $this->codeUrl = $this->getField('code_url', '');
        $this->mwebUrl = $this->getField('mweb_url', '');
    }

    /**
     * 验证响应签名.
     */
    public function verifySignature(string $key, string $signType = SignatureService::SIGN_TYPE_MD5): bool
    {
        if (!$this->isSuccess) {
            // 失败响应可能没有签名
            return true;
        }

        if (!isset($this->data['sign'])) {
            return false;
        }

        $signatureService = new SignatureService();

        return $signatureService->verifySignature($this->data, $key, $signType);
    }

    /**
     * 获取字段值
     */
    private function getField(string $key, string $default = ''): string
    {
        if (!isset($this->data[$key])) {
            return $default;
        }

        $value = $this->data[$key];

        return is_string($value) ? $value : $default;
    }

    /**
     * 映射错误信息.
     */
    private function mapErrorMessage(string $errorCode, ?string $originalMessage = null): string
    {
        $mappedMessage = self::ERROR_CODE_MAPPING[$errorCode] ?? '未知错误';

        if (null !== $originalMessage && '' !== $originalMessage && $mappedMessage !== $originalMessage) {
            return $mappedMessage . '(' . $originalMessage . ')';
        }

        return $mappedMessage;
    }

    /**
     * 是否成功
     */
    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }

    /**
     * 获取错误码
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * 获取错误信息.
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    /**
     * 获取预支付ID.
     */
    public function getPrepayId(): string
    {
        return $this->prepayId;
    }

    /**
     * 获取二维码链接（NATIVE支付）.
     */
    public function getCodeUrl(): string
    {
        return $this->codeUrl;
    }

    /**
     * 获取H5支付链接.
     */
    public function getMwebUrl(): string
    {
        return $this->mwebUrl;
    }

    /**
     * 获取原始响应数据.
     *
     * @return array<string, mixed>
     */
    public function getRawData(): array
    {
        return $this->data;
    }

    /**
     * 获取应用ID.
     */
    public function getAppId(): string
    {
        return $this->getField('appid');
    }

    /**
     * 获取商户号.
     */
    public function getMchId(): string
    {
        return $this->getField('mch_id');
    }

    /**
     * 获取随机字符串.
     */
    public function getNonceStr(): string
    {
        return $this->getField('nonce_str');
    }

    /**
     * 获取签名.
     */
    public function getSign(): string
    {
        return $this->getField('sign');
    }

    /**
     * 获取交易类型.
     */
    public function getTradeType(): string
    {
        return $this->getField('trade_type');
    }

    /**
     * 生成小程序支付参数.
     */
    /**
     * @return array<string, string>
     */
    public function generateMiniProgramPayParams(string $key, string $signType = SignatureService::SIGN_TYPE_MD5): array
    {
        if (!$this->isSuccess || '' === $this->prepayId) {
            throw new PaymentConfigurationException('响应失败或缺少预支付ID，无法生成小程序支付参数');
        }

        $params = [
            'appId' => $this->getAppId(),
            'timeStamp' => (string) time(),
            'nonceStr' => $this->generateNonceStr(),
            'package' => 'prepay_id=' . $this->prepayId,
            'signType' => $signType,
        ];

        // 生成签名
        $signatureService = new SignatureService();
        $params['paySign'] = $signatureService->generateSignature($params, $key, $signType);

        return $params;
    }

    /**
     * 生成APP支付参数.
     */
    /**
     * @return array<string, string>
     */
    public function generateAppPayParams(string $key, string $signType = SignatureService::SIGN_TYPE_MD5): array
    {
        if (!$this->isSuccess || '' === $this->prepayId) {
            throw new PaymentConfigurationException('响应失败或缺少预支付ID，无法生成APP支付参数');
        }

        $params = [
            'appid' => $this->getAppId(),
            'partnerid' => $this->getMchId(),
            'prepayid' => $this->prepayId,
            'package' => 'Sign=WXPay',
            'noncestr' => $this->generateNonceStr(),
            'timestamp' => (string) time(),
        ];

        // 生成签名
        $signatureService = new SignatureService();
        $params['sign'] = $signatureService->generateSignature($params, $key, $signType);

        return $params;
    }

    /**
     * 生成JSAPI支付参数.
     */
    /**
     * @return array<string, string>
     */
    public function generateJsApiPayParams(string $key, string $signType = SignatureService::SIGN_TYPE_MD5): array
    {
        if (!$this->isSuccess || '' === $this->prepayId) {
            throw new PaymentConfigurationException('响应失败或缺少预支付ID，无法生成JSAPI支付参数');
        }

        $params = [
            'appId' => $this->getAppId(),
            'timeStamp' => (string) time(),
            'nonceStr' => $this->generateNonceStr(),
            'package' => 'prepay_id=' . $this->prepayId,
            'signType' => $signType,
        ];

        // 生成签名
        $signatureService = new SignatureService();
        $params['paySign'] = $signatureService->generateSignature($params, $key, $signType);

        return $params;
    }

    /**
     * 检查是否有特定字段.
     */
    public function hasField(string $key): bool
    {
        return isset($this->data[$key]) && '' !== $this->data[$key];
    }

    /**
     * 获取调试信息.
     */
    /**
     * @return array<string, mixed>
     */
    public function getDebugInfo(): array
    {
        return [
            'success' => $this->isSuccess,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'prepay_id' => $this->prepayId,
            'code_url' => $this->codeUrl,
            'mweb_url' => $this->mwebUrl,
            'raw_data' => $this->data,
        ];
    }

    /**
     * 生成随机字符串.
     */
    private function generateNonceStr(int $length = 32): string
    {
        $signatureService = new SignatureService();

        return $signatureService->generateNonceStr($length);
    }

    /**
     * 转换为JSON字符串.
     */
    public function toJson(): string
    {
        $result = json_encode([
            'success' => $this->isSuccess,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'data' => [
                'prepay_id' => $this->prepayId,
                'code_url' => $this->codeUrl,
                'mweb_url' => $this->mwebUrl,
                'app_id' => $this->getAppId(),
                'mch_id' => $this->getMchId(),
                'trade_type' => $this->getTradeType(),
            ],
        ], JSON_UNESCAPED_UNICODE);

        if (false === $result) {
            throw new PaymentConfigurationException('JSON编码失败');
        }

        return $result;
    }

    /**
     * 检查响应是否包含必需字段.
     */
    /**
     * @param array<string> $requiredFields
     *
     * @return array<string>
     */
    public function validateRequiredFields(array $requiredFields): array
    {
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!$this->hasField($field)) {
                $missingFields[] = $field;
            }
        }

        return $missingFields;
    }

    /**
     * 获取支付类型相关的关键信息.
     */
    /**
     * @return array<string, string>
     */
    public function getPaymentInfo(): array
    {
        $info = [
            'prepay_id' => $this->prepayId,
            'trade_type' => $this->getTradeType(),
        ];

        // 根据交易类型添加特定信息
        switch ($this->getTradeType()) {
            case 'NATIVE':
                $info['code_url'] = $this->codeUrl;
                break;
            case 'H5':
            case 'MWEB':
                $info['mweb_url'] = $this->mwebUrl;
                break;
            case 'JSAPI':
            case 'APP':
                // 这些类型主要使用prepay_id
                break;
        }

        return $info;
    }
}
