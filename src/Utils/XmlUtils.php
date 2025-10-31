<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Utils;

use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;

/**
 * XML处理工具类.
 *
 * 提供安全的XML解析和生成功能，专门用于微信支付的XML处理
 */
class XmlUtils
{
    /**
     * 将数组转换为XML字符串.
     *
     * @param array<string, mixed> $data        数据数组
     * @param string               $rootElement 根元素名称
     *
     * @return string XML字符串
     */
    public static function arrayToXml(array $data, string $rootElement = 'xml'): string
    {
        if (0 === count($data)) {
            return "<{$rootElement}></{$rootElement}>";
        }

        $xml = "<{$rootElement}>";

        foreach ($data as $key => $value) {
            if (null === $value || '' === $value) {
                continue;
            }

            $xml .= self::buildXmlElement($key, $value);
        }

        $xml .= "</{$rootElement}>";

        return $xml;
    }

    /**
     * 将XML字符串转换为数组.
     *
     * @param string $xml XML字符串
     *
     * @return array<string, mixed> 解析后的数组
     */
    public static function xmlToArray(string $xml): array
    {
        if ('' === trim($xml)) {
            return [];
        }

        // 使用安全的XML解析选项，防止XXE攻击
        // 使用内部错误处理
        $useInternalErrors = libxml_use_internal_errors(true);
        try {
            $xmlObject = simplexml_load_string(
                $xml,
                'SimpleXMLElement',
                LIBXML_NOCDATA | LIBXML_NONET
            );

            if (false === $xmlObject) {
                $errors = libxml_get_errors();
                $errorMessages = array_map(fn ($error) => trim($error->message), $errors);
                libxml_clear_errors();

                throw new PaymentConfigurationException('XML解析失败: ' . implode(', ', $errorMessages));
            }

            $result = self::xmlObjectToArray($xmlObject);

            return self::ensureStringKeys($result);
        } finally {
            // 恢复设置
            libxml_use_internal_errors($useInternalErrors);
        }
    }

    /**
     * 构建XML元素.
     *
     * @param string $key   元素名
     * @param mixed  $value 元素值
     *
     * @return string XML元素字符串
     */
    private static function buildXmlElement(string $key, mixed $value): string
    {
        if (is_array($value)) {
            // 数组类型，转换为JSON字符串并使用CDATA包装
            $jsonValue = json_encode($value, JSON_UNESCAPED_UNICODE);

            return "<{$key}><![CDATA[{$jsonValue}]]></{$key}>";
        }

        if (is_bool($value)) {
            // 布尔类型转换为Y/N
            $boolValue = $value ? 'Y' : 'N';

            return "<{$key}><![CDATA[{$boolValue}]]></{$key}>";
        }

        if (is_object($value)) {
            // 对象类型，尝试转换为字符串
            if (method_exists($value, '__toString')) {
                $stringValue = (string) $value;
            } else {
                $stringValue = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            return "<{$key}><![CDATA[{$stringValue}]]></{$key}>";
        }

        // 标量类型，使用CDATA包装以确保安全
        $stringValue = is_scalar($value) ? (string) $value : '';

        return "<{$key}><![CDATA[{$stringValue}]]></{$key}>";
    }

    /**
     * 将SimpleXMLElement对象转换为数组.
     *
     * @param \SimpleXMLElement $xmlObject XML对象
     *
     * @return mixed 转换后的数据
     */
    private static function xmlObjectToArray(\SimpleXMLElement $xmlObject): mixed
    {
        $result = self::extractAttributes($xmlObject);

        $children = $xmlObject->children();
        if (0 === $children->count()) {
            return self::handleLeafNode($xmlObject, $result);
        }

        return self::processChildElements($children, $result);
    }

    /**
     * 提取XML属性.
     */
    /**
     * @return array<string, string>
     */
    private static function extractAttributes(\SimpleXMLElement $xmlObject): array
    {
        $result = [];
        $attributes = $xmlObject->attributes();

        if (null === $attributes) {
            return $result;
        }

        foreach ($attributes as $name => $value) {
            $result["@{$name}"] = (string) $value;
        }

        return $result;
    }

    /**
     * 处理叶子节点（无子元素）.
     */
    /**
     * @param array<string, mixed> $result
     */
    private static function handleLeafNode(\SimpleXMLElement $xmlObject, array $result): mixed
    {
        $textContent = (string) $xmlObject;

        return 0 === count($result) ? $textContent : array_merge($result, ['text' => $textContent]);
    }

    /**
     * 处理子元素.
     */
    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private static function processChildElements(\SimpleXMLElement $children, array $result): array
    {
        foreach ($children as $name => $child) {
            $childValue = self::xmlObjectToArray($child);
            $result = self::addChildToResult($result, $name, $childValue);
        }

        return $result;
    }

    /**
     * 将子元素添加到结果数组中.
     */
    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private static function addChildToResult(array $result, string $name, mixed $childValue): array
    {
        if (!isset($result[$name])) {
            $result[$name] = $childValue;

            return $result;
        }

        // 重复元素，转换为数组
        if (!is_array($result[$name]) || !isset($result[$name][0])) {
            $result[$name] = [$result[$name]];
        }
        $result[$name][] = $childValue;

        return $result;
    }

    /**
     * 验证XML格式是否正确.
     *
     * @param string $xml XML字符串
     *
     * @return bool 是否有效
     */
    public static function isValidXml(string $xml): bool
    {
        if ('' === trim($xml)) {
            return false;
        }

        $useInternalErrors = libxml_use_internal_errors(true);

        try {
            $result = simplexml_load_string(
                $xml,
                'SimpleXMLElement',
                LIBXML_NONET
            );

            return false !== $result;
        } finally {
            libxml_use_internal_errors($useInternalErrors);
            libxml_clear_errors();
        }
    }

    /**
     * 格式化XML字符串（美化输出）.
     *
     * @param string $xml 原始XML字符串
     *
     * @return string 格式化后的XML字符串
     */
    public static function formatXml(string $xml): string
    {
        if ('' === trim($xml)) {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;
        $dom->preserveWhiteSpace = false;

        // 禁用外部实体加载
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;

        if (!$dom->loadXML($xml, LIBXML_NONET)) {
            throw new PaymentConfigurationException('XML格式化失败：无效的XML格式');
        }

        $formatted = $dom->saveXML();

        return false !== $formatted ? $formatted : $xml;
    }

    /**
     * 提取XML中的特定字段值
     *
     * @param string $xml       XML字符串
     * @param string $fieldName 字段名
     *
     * @return string|null 字段值
     */
    public static function extractFieldValue(string $xml, string $fieldName): ?string
    {
        if ('' === trim($xml) || '' === $fieldName) {
            return null;
        }

        try {
            $data = self::xmlToArray($xml);

            $fieldValue = $data[$fieldName] ?? null;

            return is_scalar($fieldValue) ? (string) $fieldValue : null;
        } catch (PaymentConfigurationException) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function ensureStringKeys(mixed $result): array
    {
        if (!is_array($result)) {
            return [];
        }

        $validated = [];
        foreach ($result as $key => $value) {
            if (is_string($key)) {
                $validated[$key] = $value;
            }
        }

        return $validated;
    }

    /**
     * 检查XML是否包含指定字段.
     *
     * @param string        $xml            XML字符串
     * @param array<string> $requiredFields 必需字段列表
     *
     * @return array<string> 缺失的字段列表
     */
    public static function checkRequiredFields(string $xml, array $requiredFields): array
    {
        if (0 === count($requiredFields)) {
            return [];
        }

        try {
            $data = self::xmlToArray($xml);
            $missingFields = [];

            foreach ($requiredFields as $field) {
                $fieldValue = $data[$field] ?? null;
                if (null === $fieldValue || '' === $fieldValue) {
                    $missingFields[] = $field;
                }
            }

            return $missingFields;
        } catch (PaymentConfigurationException) {
            // XML解析失败，认为所有字段都缺失
            return $requiredFields;
        }
    }

    /**
     * 安全地获取XML中的字段值，支持默认值
     *
     * @param string $xml          XML字符串
     * @param string $fieldName    字段名
     * @param mixed  $defaultValue 默认值
     *
     * @return mixed 字段值或默认值
     */
    public static function getFieldValue(string $xml, string $fieldName, mixed $defaultValue = null): mixed
    {
        try {
            $data = self::xmlToArray($xml);

            return $data[$fieldName] ?? $defaultValue;
        } catch (PaymentConfigurationException) {
            return $defaultValue;
        }
    }
}
