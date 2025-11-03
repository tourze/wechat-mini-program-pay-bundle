<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Tests\Utils;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WechatMiniProgramPayBundle\Exception\PaymentConfigurationException;
use WechatMiniProgramPayBundle\Utils\XmlUtils;

/**
 * @internal
 */
#[CoversClass(XmlUtils::class)]
class XmlUtilsTest extends TestCase
{
    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('provideArrayToXmlData')]
    public function testArrayToXml(array $data, string $rootElement, string $expected): void
    {
        $result = XmlUtils::arrayToXml($data, $rootElement);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, mixed>
     */
    public static function provideArrayToXmlData(): array
    {
        return [
            'Empty array with default root' => [
                [],
                'xml',
                '<xml></xml>',
            ],
            'Empty array with custom root' => [
                [],
                'root',
                '<root></root>',
            ],
            'Simple array' => [
                ['key' => 'value'],
                'xml',
                '<xml><key><![CDATA[value]]></key></xml>',
            ],
            'Array with null values skipped' => [
                ['key1' => 'value1', 'key2' => null, 'key3' => 'value3'],
                'xml',
                '<xml><key1><![CDATA[value1]]></key1><key3><![CDATA[value3]]></key3></xml>',
            ],
            'Array with empty string values skipped' => [
                ['key1' => 'value1', 'key2' => '', 'key3' => 'value3'],
                'xml',
                '<xml><key1><![CDATA[value1]]></key1><key3><![CDATA[value3]]></key3></xml>',
            ],
            'Array with boolean values' => [
                ['flag1' => true, 'flag2' => false],
                'xml',
                '<xml><flag1><![CDATA[Y]]></flag1><flag2><![CDATA[N]]></flag2></xml>',
            ],
            'Array with numeric values' => [
                ['amount' => 100, 'price' => 12.34],
                'xml',
                '<xml><amount><![CDATA[100]]></amount><price><![CDATA[12.34]]></price></xml>',
            ],
            'Array with array values (JSON encoded)' => [
                ['items' => ['item1', 'item2']],
                'xml',
                '<xml><items><![CDATA[["item1","item2"]]]></items></xml>',
            ],
            'Array with nested array values' => [
                ['user' => ['name' => 'John', 'age' => 30]],
                'xml',
                '<xml><user><![CDATA[{"name":"John","age":30}]]></user></xml>',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('provideXmlToArrayData')]
    public function testXmlToArray(string $xml, array $expected): void
    {
        $result = XmlUtils::xmlToArray($xml);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, mixed>
     */
    public static function provideXmlToArrayData(): array
    {
        return [
            'Empty XML' => [
                '',
                [],
            ],
            'Simple XML' => [
                '<xml><key><![CDATA[value]]></key></xml>',
                ['key' => 'value'],
            ],
            'XML without CDATA' => [
                '<xml><key>value</key></xml>',
                ['key' => 'value'],
            ],
            'XML with multiple elements' => [
                '<xml><key1><![CDATA[value1]]></key1><key2><![CDATA[value2]]></key2></xml>',
                ['key1' => 'value1', 'key2' => 'value2'],
            ],
            'XML with attributes' => [
                '<xml><item id="123">value</item></xml>',
                ['item' => ['@id' => '123', 'text' => 'value']],
            ],
            'XML with repeated elements' => [
                '<xml><item>value1</item><item>value2</item></xml>',
                ['item' => ['value1', 'value2']],
            ],
            'XML with nested elements' => [
                '<xml><user><name>John</name><age>30</age></user></xml>',
                ['user' => ['name' => 'John', 'age' => '30']],
            ],
            'Empty elements' => [
                '<xml><empty></empty></xml>',
                ['empty' => ''],
            ],
        ];
    }

    public function testXmlToArrayWithInvalidXml(): void
    {
        $invalidXml = '<xml><unclosed>';

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessageMatches('/XML解析失败/');

        XmlUtils::xmlToArray($invalidXml);
    }

    #[DataProvider('provideIsValidXmlData')]
    public function testIsValidXml(string $xml, bool $expected): void
    {
        $result = XmlUtils::isValidXml($xml);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, mixed>
     */
    public static function provideIsValidXmlData(): array
    {
        return [
            'Valid XML' => [
                '<xml><key>value</key></xml>',
                true,
            ],
            'Empty string' => [
                '',
                false,
            ],
            'Whitespace only' => [
                '   ',
                false,
            ],
            'Invalid XML - unclosed tag' => [
                '<xml><unclosed>',
                false,
            ],
            'Invalid XML - malformed' => [
                '<xml><key>value</key',
                false,
            ],
            'XML with attributes' => [
                '<xml><item id="123">value</item></xml>',
                true,
            ],
            'Self-closing tags' => [
                '<xml><item/></xml>',
                true,
            ],
        ];
    }

    #[DataProvider('provideFormatXmlData')]
    public function testFormatXml(string $xml, string $expected): void
    {
        $result = XmlUtils::formatXml($xml);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, mixed>
     */
    public static function provideFormatXmlData(): array
    {
        return [
            'Empty string' => [
                '',
                '',
            ],
            'Simple XML formatting' => [
                '<xml><key>value</key></xml>',
                "<?xml version=\"1.0\"?>\n<xml>\n  <key>value</key>\n</xml>\n",
            ],
            'Compact XML formatting' => [
                '<xml><key1>value1</key1><key2>value2</key2></xml>',
                "<?xml version=\"1.0\"?>\n<xml>\n  <key1>value1</key1>\n  <key2>value2</key2>\n</xml>\n",
            ],
        ];
    }

    public function testFormatXmlWithInvalidXml(): void
    {
        $invalidXml = '<xml><unclosed>';

        $this->expectException(PaymentConfigurationException::class);
        $this->expectExceptionMessage('XML格式化失败：无效的XML格式');

        XmlUtils::formatXml($invalidXml);
    }

    #[DataProvider('provideExtractFieldValueData')]
    public function testExtractFieldValue(string $xml, string $fieldName, ?string $expected): void
    {
        $result = XmlUtils::extractFieldValue($xml, $fieldName);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, mixed>
     */
    public static function provideExtractFieldValueData(): array
    {
        return [
            'Field exists' => [
                '<xml><key>value</key></xml>',
                'key',
                'value',
            ],
            'Field does not exist' => [
                '<xml><key>value</key></xml>',
                'missing',
                null,
            ],
            'Empty XML' => [
                '',
                'key',
                null,
            ],
            'Empty field name' => [
                '<xml><key>value</key></xml>',
                '',
                null,
            ],
            'Numeric field value' => [
                '<xml><amount>100</amount></xml>',
                'amount',
                '100',
            ],
            'Boolean-like field value' => [
                '<xml><flag>true</flag></xml>',
                'flag',
                'true',
            ],
        ];
    }

    /**
     * @param array<string> $requiredFields
     * @param array<string> $expected
     */
    #[DataProvider('provideCheckRequiredFieldsData')]
    public function testCheckRequiredFields(string $xml, array $requiredFields, array $expected): void
    {
        $result = XmlUtils::checkRequiredFields($xml, $requiredFields);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, mixed>
     */
    public static function provideCheckRequiredFieldsData(): array
    {
        return [
            'All fields present' => [
                '<xml><field1>value1</field1><field2>value2</field2></xml>',
                ['field1', 'field2'],
                [],
            ],
            'Some fields missing' => [
                '<xml><field1>value1</field1></xml>',
                ['field1', 'field2', 'field3'],
                ['field2', 'field3'],
            ],
            'Empty values treated as missing' => [
                '<xml><field1>value1</field1><field2></field2></xml>',
                ['field1', 'field2'],
                ['field2'],
            ],
            'No required fields' => [
                '<xml><field1>value1</field1></xml>',
                [],
                [],
            ],
            'Invalid XML' => [
                '<xml><unclosed>',
                ['field1', 'field2'],
                ['field1', 'field2'],
            ],
        ];
    }

    #[DataProvider('provideGetFieldValueData')]
    public function testGetFieldValue(string $xml, string $fieldName, mixed $defaultValue, mixed $expected): void
    {
        $result = XmlUtils::getFieldValue($xml, $fieldName, $defaultValue);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, mixed>
     */
    public static function provideGetFieldValueData(): array
    {
        return [
            'Field exists' => [
                '<xml><key>value</key></xml>',
                'key',
                'default',
                'value',
            ],
            'Field does not exist, returns default' => [
                '<xml><other>value</other></xml>',
                'key',
                'default',
                'default',
            ],
            'Invalid XML, returns default' => [
                '<xml><unclosed>',
                'key',
                'default',
                'default',
            ],
            'Null default value' => [
                '<xml><other>value</other></xml>',
                'key',
                null,
                null,
            ],
            'Array default value' => [
                '<xml><other>value</other></xml>',
                'key',
                ['default', 'array'],
                ['default', 'array'],
            ],
            'Boolean default value' => [
                '<xml><other>value</other></xml>',
                'key',
                true,
                true,
            ],
        ];
    }

    public function testBuildXmlElementWithObject(): void
    {
        // Test with an object that has __toString method
        $object = new class () {
            public function __toString(): string
            {
                return 'object_string_value';
            }
        };

        $result = XmlUtils::arrayToXml(['test' => $object]);
        $this->assertStringContainsString('<test><![CDATA[object_string_value]]></test>', $result);
    }

    public function testBuildXmlElementWithObjectWithoutToString(): void
    {
        // Test with an object that doesn't have __toString method
        $object = new \stdClass();
        $object->property = 'value';

        $result = XmlUtils::arrayToXml(['test' => $object]);
        $this->assertStringContainsString('<test><![CDATA[{"property":"value"}]]></test>', $result);
    }

    public function testXmlObjectToArrayWithComplexXml(): void
    {
        $xml = '
            <xml>
                <user id="123" status="active">
                    <name>John Doe</name>
                    <email>john@example.com</email>
                    <roles>
                        <role>admin</role>
                        <role>user</role>
                    </roles>
                </user>
                <metadata>
                    <created>2023-01-01</created>
                    <updated>2023-12-31</updated>
                </metadata>
            </xml>
        ';

        $result = XmlUtils::xmlToArray($xml);

        $expected = [
            'user' => [
                '@id' => '123',
                '@status' => 'active',
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'roles' => [
                    'role' => ['admin', 'user'],
                ],
            ],
            'metadata' => [
                'created' => '2023-01-01',
                'updated' => '2023-12-31',
            ],
        ];

        $this->assertSame($expected, $result);
    }

    public function testLibxmlErrorHandling(): void
    {
        // Test that libxml errors are properly handled and restored
        $currentSetting = libxml_use_internal_errors(false);

        // This should not affect the internal error handling
        $xml = '<xml><key>value</key></xml>';
        $result = XmlUtils::xmlToArray($xml);

        $this->assertSame(['key' => 'value'], $result);

        // Verify the setting is restored
        $this->assertSame(false, libxml_use_internal_errors($currentSetting));
    }

    public function testLargeXmlHandling(): void
    {
        // Test handling of larger XML structures
        $data = [];
        for ($i = 0; $i < 100; ++$i) {
            $data["key_{$i}"] = "value_{$i}";
        }

        $xml = XmlUtils::arrayToXml($data);
        $result = XmlUtils::xmlToArray($xml);

        $this->assertSame($data, $result);
        $this->assertCount(100, $result);
    }

    public function testSpecialCharactersInXml(): void
    {
        $data = [
            'special_chars' => 'Value with <>&"\'',
            'unicode' => '中文测试',
            'numbers' => '123.45',
        ];

        $xml = XmlUtils::arrayToXml($data);
        $result = XmlUtils::xmlToArray($xml);

        $this->assertSame($data, $result);
    }

    public function testEmptyAndWhitespaceHandling(): void
    {
        $xml = '<xml>   </xml>';
        $result = XmlUtils::xmlToArray($xml);
        $this->assertSame([], $result);

        $xml = '<xml><empty></empty><whitespace>   </whitespace></xml>';
        $result = XmlUtils::xmlToArray($xml);
        $this->assertSame(['empty' => '', 'whitespace' => '   '], $result);
    }
}
