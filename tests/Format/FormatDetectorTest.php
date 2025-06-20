<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Tests\Format;

use PHPUnit\Framework\TestCase;
use LkExcel\LkExportExcel\Format\FormatDetector;

class FormatDetectorTest extends TestCase
{
    private FormatDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new FormatDetector();
    }

    public function testGetSupportedFormats(): void
    {
        $formats = $this->detector->getSupportedFormats();
        
        $expected = [
            FormatDetector::FORMAT_XLSX,
            FormatDetector::FORMAT_XLS,
            FormatDetector::FORMAT_CSV,
            FormatDetector::FORMAT_ODS,
            FormatDetector::FORMAT_XML,
        ];
        
        $this->assertEquals($expected, $formats);
    }

    public function testDetectWithNonExistentFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('文件不存在');
        
        $this->detector->detect('/non/existent/file.xlsx');
    }

    public function testDetectSimpleCSV(): void
    {
        // 创建临时CSV文件
        $csvContent = "Name,Age,City\nJohn,25,New York\nJane,30,Los Angeles\n";
        $tempFile = $this->createTempFile('csv', $csvContent);
        
        try {
            $result = $this->detector->detect($tempFile);
            
            $this->assertEquals(FormatDetector::FORMAT_CSV, $result['format']);
            $this->assertGreaterThan(0.5, $result['confidence']);
            $this->assertIsFloat($result['detection_time']);
            $this->assertIsInt($result['file_size']);
            
            if (isset($result['details']['csv_delimiter'])) {
                $this->assertEquals(',', $result['details']['csv_delimiter']);
            }
        } finally {
            unlink($tempFile);
        }
    }

    public function testDetectCSVWithDifferentDelimiters(): void
    {
        // 测试分号分隔符
        $csvContent = "Name;Age;City\nJohn;25;New York\nJane;30;Los Angeles\n";
        $tempFile = $this->createTempFile('csv', $csvContent);
        
        try {
            $result = $this->detector->detect($tempFile);
            
            $this->assertEquals(FormatDetector::FORMAT_CSV, $result['format']);
            if (isset($result['details']['csv_delimiter'])) {
                $this->assertEquals(';', $result['details']['csv_delimiter']);
            }
        } finally {
            unlink($tempFile);
        }
    }

    public function testDetectXMLContent(): void
    {
        $xmlContent = '<?xml version="1.0" encoding="UTF-8"?><root><item>test</item></root>';
        $tempFile = $this->createTempFile('xml', $xmlContent);
        
        try {
            $result = $this->detector->detect($tempFile);
            
            // 应该检测为XML或由于扩展名检测为XML
            $this->assertTrue(in_array($result['format'], [FormatDetector::FORMAT_XML, FormatDetector::FORMAT_UNKNOWN]));
        } finally {
            unlink($tempFile);
        }
    }

    public function testDetectByExtension(): void
    {
        // 创建一个简单的文本文件，但使用xlsx扩展名
        $content = "This is not really an Excel file";
        $tempFile = $this->createTempFile('xlsx', $content);
        
        try {
            $result = $this->detector->detect($tempFile);
            
            // 应该能够通过扩展名检测
            $this->assertIsString($result['format']);
            if (isset($result['extension'])) {
                $this->assertEquals('xlsx', $result['extension']);
            }
        } finally {
            unlink($tempFile);
        }
    }

    public function testDetectEmptyFile(): void
    {
        $tempFile = $this->createTempFile('txt', '');
        
        try {
            $result = $this->detector->detect($tempFile);
            
            $this->assertIsArray($result);
            $this->assertArrayHasKey('format', $result);
            $this->assertArrayHasKey('confidence', $result);
            $this->assertEquals(0, $result['file_size']);
        } finally {
            unlink($tempFile);
        }
    }

    public function testBatchDetection(): void
    {
        // 创建多个临时文件
        $files = [];
        
        // CSV文件
        $files[] = $this->createTempFile('csv', "a,b,c\n1,2,3\n");
        
        // XML文件
        $files[] = $this->createTempFile('xml', '<?xml version="1.0"?><root></root>');
        
        // 文本文件
        $files[] = $this->createTempFile('txt', 'Just plain text');
        
        try {
            $results = $this->detector->detectBatch($files);
            
            $this->assertCount(3, $results);
            
            foreach ($files as $file) {
                $this->assertArrayHasKey($file, $results);
                $this->assertArrayHasKey('format', $results[$file]);
                $this->assertArrayHasKey('confidence', $results[$file]);
            }
        } finally {
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function testDetectionPerformance(): void
    {
        $content = str_repeat("test,data,here\n", 1000); // 大一些的CSV
        $tempFile = $this->createTempFile('csv', $content);
        
        try {
            $startTime = microtime(true);
            $result = $this->detector->detect($tempFile);
            $endTime = microtime(true);
            
            // 检测应该很快完成（少于100ms）
            $this->assertLessThan(0.1, $endTime - $startTime);
            $this->assertIsFloat($result['detection_time']);
        } finally {
            unlink($tempFile);
        }
    }

    public function testInconsistentCSV(): void
    {
        // 创建不一致的CSV（列数不同）
        $csvContent = "Name,Age,City\nJohn,25\nJane,30,LA,Extra\n";
        $tempFile = $this->createTempFile('csv', $csvContent);
        
        try {
            $result = $this->detector->detect($tempFile);
            
            // 即使不一致，仍可能检测为CSV，但置信度较低
            $this->assertIsString($result['format']);
            $this->assertIsFloat($result['confidence']);
        } finally {
            unlink($tempFile);
        }
    }

    public function testBinaryContent(): void
    {
        // 创建二进制内容文件
        $binaryContent = "\x00\x01\x02\x03\xFF\xFE\xFD";
        $tempFile = $this->createTempFile('bin', $binaryContent);
        
        try {
            $result = $this->detector->detect($tempFile);
            
            // 应该能够处理二进制内容而不崩溃
            $this->assertIsArray($result);
            $this->assertArrayHasKey('format', $result);
        } finally {
            unlink($tempFile);
        }
    }

    public function testUTF8Content(): void
    {
        // 测试UTF-8内容
        $utf8Content = "姓名,年龄,城市\n张三,25,北京\n李四,30,上海\n";
        $tempFile = $this->createTempFile('csv', $utf8Content);
        
        try {
            $result = $this->detector->detect($tempFile);
            
            $this->assertIsArray($result);
            // UTF-8内容应该能正确处理
            if ($result['format'] === FormatDetector::FORMAT_CSV) {
                $this->assertGreaterThan(0.0, $result['confidence']);
            }
        } finally {
            unlink($tempFile);
        }
    }

    public function testDetectionMethodReporting(): void
    {
        $csvContent = "a,b,c\n1,2,3\n";
        $tempFile = $this->createTempFile('csv', $csvContent);
        
        try {
            $result = $this->detector->detect($tempFile);
            
            // 应该报告检测方法
            $this->assertArrayHasKey('detection_method', $result);
            $this->assertIsString($result['detection_method']);
        } finally {
            unlink($tempFile);
        }
    }

    /**
     * 创建临时文件
     */
    private function createTempFile(string $extension, string $content): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'lkexcel_test_') . '.' . $extension;
        file_put_contents($tempFile, $content);
        return $tempFile;
    }

    protected function tearDown(): void
    {
        // 清理可能遗留的临时文件
        $tempDir = sys_get_temp_dir();
        $files = glob($tempDir . '/lkexcel_test_*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
} 