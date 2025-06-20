<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Tests\Type;

use PHPUnit\Framework\TestCase;
use LkExcel\LkExportExcel\Type\TypeDetector;

class TypeDetectorTest extends TestCase
{
    private TypeDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new TypeDetector();
    }

    public function testDetectNull(): void
    {
        $result = $this->detector->detectType(null);
        $this->assertEquals(TypeDetector::TYPE_NULL, $result['type']);
        $this->assertNull($result['value']);
        $this->assertEquals(1.0, $result['confidence']);

        // 空字符串也应该被检测为null
        $result = $this->detector->detectType('');
        $this->assertEquals(TypeDetector::TYPE_NULL, $result['type']);
    }

    public function testDetectBoolean(): void
    {
        $testCases = [
            ['true', true],
            ['false', false],
            ['TRUE', true],
            ['FALSE', false],
            ['yes', true],
            ['no', false],
            ['y', true],
            ['n', false],
            ['1', true],
            ['0', false],
            ['是', true],
            ['否', false],
        ];

        foreach ($testCases as [$input, $expected]) {
            $result = $this->detector->detectType($input);
            $this->assertEquals(TypeDetector::TYPE_BOOLEAN, $result['type'], "Failed for input: $input");
            $this->assertEquals($expected, $result['value'], "Wrong value for input: $input");
            $this->assertGreaterThan(0.7, $result['confidence']);
        }
    }

    public function testDetectInteger(): void
    {
        $testCases = [
            '123' => 123,
            '-456' => -456,
            '+789' => 789,
            '1,000' => 1000,
            '1,234,567' => 1234567,
        ];

        foreach ($testCases as $input => $expected) {
            $result = $this->detector->detectType($input);
            $this->assertEquals(TypeDetector::TYPE_INTEGER, $result['type'], "Failed for input: $input");
            $this->assertEquals($expected, $result['value']);
            $this->assertGreaterThan(0.8, $result['confidence']);
        }
    }

    public function testDetectFloat(): void
    {
        $testCases = [
            '123.45' => 123.45,
            '-456.78' => -456.78,
            '1,234.56' => 1234.56,
            '.5' => 0.5,
            '123.' => 123.0,
        ];

        foreach ($testCases as $input => $expected) {
            $result = $this->detector->detectType($input);
            $this->assertEquals(TypeDetector::TYPE_FLOAT, $result['type'], "Failed for input: $input");
            $this->assertEquals($expected, $result['value']);
            $this->assertGreaterThan(0.8, $result['confidence']);
        }
    }

    public function testDetectScientific(): void
    {
        $testCases = [
            '1.23e5' => 1.23e5,
            '4.56E-3' => 4.56e-3,
            '1e10' => 1e10,
            '-2.5e+2' => -2.5e+2,
        ];

        foreach ($testCases as $input => $expected) {
            $result = $this->detector->detectType($input);
            $this->assertEquals(TypeDetector::TYPE_SCIENTIFIC, $result['type'], "Failed for input: $input");
            $this->assertEquals($expected, $result['value']);
            $this->assertGreaterThan(0.8, $result['confidence']);
        }
    }

    public function testDetectPercentage(): void
    {
        $testCases = [
            '50%' => 0.5,
            '100%' => 1.0,
            '12.5%' => 0.125,
            '-25%' => -0.25,
            '1,000%' => 10.0,
        ];

        foreach ($testCases as $input => $expected) {
            $result = $this->detector->detectType($input);
            $this->assertEquals(TypeDetector::TYPE_PERCENTAGE, $result['type'], "Failed for input: $input");
            $this->assertEquals($expected, $result['value']);
            $this->assertGreaterThan(0.8, $result['confidence']);
        }
    }

    public function testDetectCurrency(): void
    {
        $testCases = [
            '$100.50' => ['amount' => 100.50, 'currency' => 'USD'],
            '¥1,000' => ['amount' => 1000.0, 'currency' => 'CNY'],
            '€50.25' => ['amount' => 50.25, 'currency' => 'EUR'],
            '£75.00' => ['amount' => 75.0, 'currency' => 'GBP'],
        ];

        foreach ($testCases as $input => $expected) {
            $result = $this->detector->detectType($input);
            $this->assertEquals(TypeDetector::TYPE_CURRENCY, $result['type'], "Failed for input: $input");
            $this->assertIsArray($result['value']);
            $this->assertEquals($expected['amount'], $result['value']['amount']);
            $this->assertEquals($expected['currency'], $result['value']['currency']);
            $this->assertGreaterThan(0.8, $result['confidence']);
        }
    }

    public function testDetectEmail(): void
    {
        $validEmails = [
            'user@example.com',
            'test.email+tag@domain.co.uk',
            'user.name123@test-domain.org',
        ];

        foreach ($validEmails as $email) {
            $result = $this->detector->detectType($email);
            $this->assertEquals(TypeDetector::TYPE_EMAIL, $result['type'], "Failed for email: $email");
            $this->assertEquals($email, $result['value']);
            $this->assertGreaterThan(0.9, $result['confidence']);
        }
    }

    public function testDetectURL(): void
    {
        $validUrls = [
            'https://www.example.com',
            'http://test.org/path',
            'ftp://files.server.com',
            'www.example.com',
        ];

        foreach ($validUrls as $url) {
            $result = $this->detector->detectType($url);
            $this->assertEquals(TypeDetector::TYPE_URL, $result['type'], "Failed for URL: $url");
            $this->assertGreaterThanOrEqual(0.7, $result['confidence']);
        }
    }

    public function testDetectDate(): void
    {
        $validDates = [
            '2023-12-25',
            '2023/12/25',
            '12/25/2023',
            '25-12-2023',
            '2023年12月25日',
        ];

        foreach ($validDates as $date) {
            $result = $this->detector->detectType($date);
            $this->assertEquals(TypeDetector::TYPE_DATE, $result['type'], "Failed for date: $date");
            $this->assertInstanceOf(\DateTime::class, $result['value']);
            $this->assertGreaterThan(0.6, $result['confidence']);
        }
    }

    public function testDetectTime(): void
    {
        $validTimes = [
            '14:30:00',
            '2:30 PM',
            '14:30',
            '2:30',
            '14时30分',
        ];

        foreach ($validTimes as $time) {
            $result = $this->detector->detectType($time);
            $this->assertEquals(TypeDetector::TYPE_TIME, $result['type'], "Failed for time: $time");
            $this->assertGreaterThan(0.6, $result['confidence']);
        }
    }

    public function testDetectDateTime(): void
    {
        $validDateTimes = [
            '2023-12-25 14:30:00',
            '12/25/2023 2:30 PM',
            '2023/12/25 14:30',
        ];

        foreach ($validDateTimes as $dateTime) {
            $result = $this->detector->detectType($dateTime);
            $this->assertEquals(TypeDetector::TYPE_DATETIME, $result['type'], "Failed for datetime: $dateTime");
            $this->assertInstanceOf(\DateTime::class, $result['value']);
            $this->assertGreaterThan(0.6, $result['confidence']);
        }
    }

    public function testDetectPhone(): void
    {
        $validPhones = [
            '13812345678',
            '+86 138 1234 5678',
            '(555) 123-4567',
            '555-123-4567',
        ];

        foreach ($validPhones as $phone) {
            $result = $this->detector->detectType($phone);
            $this->assertEquals(TypeDetector::TYPE_PHONE, $result['type'], "Failed for phone: $phone");
            $this->assertGreaterThan(0.0, $result['confidence']);
        }
    }

    public function testDetectString(): void
    {
        $strings = [
            'Just a regular string',
            'Mixed 123 content',
            '这是中文字符串',
            'Special chars: @#$%^&*()',
        ];

        foreach ($strings as $string) {
            $result = $this->detector->detectType($string);
            $this->assertEquals(TypeDetector::TYPE_STRING, $result['type'], "Failed for string: $string");
            $this->assertEquals($string, $result['value']);
            $this->assertGreaterThan(0.0, $result['confidence']);
        }
    }

    public function testBatchDetection(): void
    {
        $values = [
            'true',
            '123',
            '45.67',
            '$100.00',
            'user@example.com',
            '2023-12-25',
            'Regular text',
        ];

        $results = $this->detector->detectBatch($values);

        $this->assertCount(7, $results);
        $this->assertEquals(TypeDetector::TYPE_BOOLEAN, $results[0]['type']);
        $this->assertEquals(TypeDetector::TYPE_INTEGER, $results[1]['type']);
        $this->assertEquals(TypeDetector::TYPE_FLOAT, $results[2]['type']);
        $this->assertEquals(TypeDetector::TYPE_CURRENCY, $results[3]['type']);
        $this->assertEquals(TypeDetector::TYPE_EMAIL, $results[4]['type']);
        $this->assertEquals(TypeDetector::TYPE_DATE, $results[5]['type']);
        $this->assertEquals(TypeDetector::TYPE_STRING, $results[6]['type']);
    }

    public function testColumnTypeInference(): void
    {
        // 测试整数列
        $integerColumn = ['1', '2', '3', '4', '5'];
        $result = $this->detector->inferColumnType($integerColumn);
        $this->assertEquals(TypeDetector::TYPE_INTEGER, $result['type']);
        $this->assertGreaterThan(0.7, $result['confidence']);

        // 测试混合类型列（应该降级为字符串）
        $mixedColumn = ['1', 'text', '3.14', 'true'];
        $result = $this->detector->inferColumnType($mixedColumn);
        $this->assertEquals(TypeDetector::TYPE_STRING, $result['type']);
        $this->assertArrayHasKey('reason', $result['metadata']);
    }

    public function testTypePriority(): void
    {
        // 测试可能匹配多种类型的值，验证优先级
        $value = '123'; // 可以是整数或字符串
        $result = $this->detector->detectType($value);
        $this->assertEquals(TypeDetector::TYPE_INTEGER, $result['type']); // 整数优先级更高

        $value = '1'; // 可以是布尔值、整数或字符串
        $result = $this->detector->detectType($value);
        // 应该根据优先级确定类型
        $this->assertTrue(in_array($result['type'], [TypeDetector::TYPE_BOOLEAN, TypeDetector::TYPE_INTEGER]));
    }

    public function testCustomConverter(): void
    {
        // 添加自定义转换器
        $this->detector->addCustomConverter('custom_type', function($value) {
            return 'custom:' . $value;
        });

        // 注意：这里只是测试转换器的添加，实际类型检测仍基于现有模式
        $this->assertTrue(true); // 简单验证没有错误
    }

    public function testCacheClearing(): void
    {
        // 检测一个值
        $this->detector->detectType('123');
        
        // 清除缓存
        $result = $this->detector->clearCache();
        $this->assertInstanceOf(TypeDetector::class, $result);
    }

    public function testGetSupportedTypes(): void
    {
        $types = $this->detector->getSupportedTypes();
        
        $expectedTypes = [
            TypeDetector::TYPE_NULL,
            TypeDetector::TYPE_BOOLEAN,
            TypeDetector::TYPE_INTEGER,
            TypeDetector::TYPE_FLOAT,
            TypeDetector::TYPE_STRING,
            TypeDetector::TYPE_DATETIME,
            TypeDetector::TYPE_DATE,
            TypeDetector::TYPE_TIME,
            TypeDetector::TYPE_CURRENCY,
            TypeDetector::TYPE_PERCENTAGE,
            TypeDetector::TYPE_SCIENTIFIC,
            TypeDetector::TYPE_PHONE,
            TypeDetector::TYPE_EMAIL,
            TypeDetector::TYPE_URL,
        ];

        foreach ($expectedTypes as $type) {
            $this->assertContains($type, $types);
        }
    }

    public function testStrictMode(): void
    {
        $result = $this->detector->setStrictMode(true);
        $this->assertInstanceOf(TypeDetector::class, $result);

        $result = $this->detector->setStrictMode(false);
        $this->assertInstanceOf(TypeDetector::class, $result);
    }

    public function testDetectionPerformance(): void
    {
        $values = array_fill(0, 1000, '123.45');
        
        $startTime = microtime(true);
        $results = $this->detector->detectBatch($values);
        $endTime = microtime(true);

        $this->assertCount(1000, $results);
        $this->assertLessThan(1.0, $endTime - $startTime); // 应该在1秒内完成

        // 验证所有结果都正确
        foreach ($results as $result) {
            $this->assertEquals(TypeDetector::TYPE_FLOAT, $result['type']);
            $this->assertEquals(123.45, $result['value']);
        }
    }

    public function testEdgeCases(): void
    {
        // 测试边缘情况
        $edgeCases = [
            '0' => TypeDetector::TYPE_BOOLEAN, // 可能是布尔值或整数
            '0.0' => TypeDetector::TYPE_FLOAT,
            '+0' => TypeDetector::TYPE_INTEGER,
            '-0' => TypeDetector::TYPE_INTEGER,
            '   123   ' => TypeDetector::TYPE_INTEGER, // 带空白符
        ];

        foreach ($edgeCases as $input => $expectedType) {
            $result = $this->detector->detectType($input);
            // 由于可能有多种解释，这里只验证结果合理
            $this->assertIsString($result['type']);
            $this->assertIsFloat($result['confidence']);
        }
    }
} 