<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Tests\Api;

use PHPUnit\Framework\TestCase;
use LkExcel\LkExportExcel\Api\Converter;
use LkExcel\LkExportExcel\Format\FormatDetector;
use LkExcel\LkExportExcel\Memory\MemoryManager;

/**
 * Converter类测试
 */
class ConverterTest extends TestCase
{
    private string $testDataDir;
    private string $tempDir;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->testDataDir = __DIR__ . '/../fixtures';
        $this->tempDir = sys_get_temp_dir() . '/lkexcel_test_' . uniqid();
        
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }
    
    protected function tearDown(): void
    {
        // 清理临时文件
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
        parent::tearDown();
    }
    
    public function testConverterConstruction(): void
    {
        $sourceFile = $this->testDataDir . '/small_simple.xlsx';
        $targetFile = $this->tempDir . '/output.csv';
        
        $converter = new Converter($sourceFile, $targetFile);
        
        $this->assertInstanceOf(Converter::class, $converter);
    }
    
    public function testConverterWithCustomConfig(): void
    {
        $sourceFile = $this->testDataDir . '/small_simple.xlsx';
        $targetFile = $this->tempDir . '/output.csv';
        
        $config = [
            'chunk_size' => 500,
            'preserve_formulas' => false,
            'quality_check' => false
        ];
        
        $converter = new Converter($sourceFile, $targetFile, $config);
        
        $this->assertInstanceOf(Converter::class, $converter);
    }
    
    public function testConvertXlsxToCsv(): void
    {
        $sourceFile = $this->testDataDir . '/small_simple.xlsx';
        $targetFile = $this->tempDir . '/output.csv';
        
        $converter = new Converter($sourceFile, $targetFile);
        $result = $converter->convert();
        
        $this->assertTrue($result);
        $this->assertFileExists($targetFile);
        
        // 验证CSV内容
        $csvContent = file_get_contents($targetFile);
        $this->assertNotEmpty($csvContent);
        
        $lines = explode("\n", trim($csvContent));
        $this->assertGreaterThan(0, count($lines));
    }
    
    public function testConvertXlsxToJson(): void
    {
        $sourceFile = $this->testDataDir . '/small_simple.xlsx';
        $targetFile = $this->tempDir . '/output.json';
        
        $converter = new Converter($sourceFile, $targetFile);
        $result = $converter->convert();
        
        $this->assertTrue($result);
        $this->assertFileExists($targetFile);
        
        // 验证JSON内容
        $jsonContent = file_get_contents($targetFile);
        $this->assertNotEmpty($jsonContent);
        
        $data = json_decode($jsonContent, true);
        $this->assertIsArray($data);
    }
    
    public function testConvertMultiSheetXlsx(): void
    {
        $sourceFile = $this->testDataDir . '/multi_sheet.xlsx';
        $targetFile = $this->tempDir . '/multi_output.xlsx';
        
        $converter = new Converter($sourceFile, $targetFile);
        $result = $converter->convert();
        
        $this->assertTrue($result);
        $this->assertFileExists($targetFile);
    }
    
    public function testConvertLargeFile(): void
    {
        $sourceFile = $this->testDataDir . '/large_data.xlsx';
        $targetFile = $this->tempDir . '/large_output.csv';
        
        $config = [
            'chunk_size' => 1000,
            'memory_limit' => 256 * 1024 * 1024 // 256MB
        ];
        
        $converter = new Converter($sourceFile, $targetFile, $config);
        $result = $converter->convert();
        
        $this->assertTrue($result);
        $this->assertFileExists($targetFile);
        
        // 验证文件大小合理
        $fileSize = filesize($targetFile);
        $this->assertGreaterThan(0, $fileSize);
    }
    
    public function testQualityCheck(): void
    {
        $sourceFile = $this->testDataDir . '/small_simple.xlsx';
        $targetFile = $this->tempDir . '/quality_output.csv';
        
        $config = ['quality_check' => true];
        $converter = new Converter($sourceFile, $targetFile, $config);
        $result = $converter->convert();
        
        $this->assertTrue($result);
        
        $log = $converter->getConversionLog();
        $this->assertNotEmpty($log);
        
        // 查找质量检测相关日志
        $qualityLogFound = false;
        foreach ($log as $entry) {
            if (strpos($entry['message'], '质量检测') !== false) {
                $qualityLogFound = true;
                break;
            }
        }
        $this->assertTrue($qualityLogFound, '应该包含质量检测日志');
    }
    
    public function testConversionLog(): void
    {
        $sourceFile = $this->testDataDir . '/small_simple.xlsx';
        $targetFile = $this->tempDir . '/log_output.csv';
        
        $converter = new Converter($sourceFile, $targetFile);
        $result = $converter->convert();
        
        $this->assertTrue($result);
        
        $log = $converter->getConversionLog();
        $this->assertNotEmpty($log);
        
        // 验证日志结构
        $firstEntry = $log[0];
        $this->assertArrayHasKey('timestamp', $firstEntry);
        $this->assertArrayHasKey('message', $firstEntry);
        $this->assertArrayHasKey('data', $firstEntry);
        $this->assertArrayHasKey('memory_usage', $firstEntry);
    }
    
    public function testGetSupportedFormats(): void
    {
        $formats = Converter::getSupportedFormats();
        
        $this->assertIsArray($formats);
        $this->assertArrayHasKey('source', $formats);
        $this->assertArrayHasKey('target', $formats);
        
        $this->assertContains('XLSX', $formats['source']);
        $this->assertContains('CSV', $formats['target']);
        $this->assertContains('JSON', $formats['target']);
    }
    
    public function testConvertNonExistentFile(): void
    {
        $sourceFile = $this->testDataDir . '/nonexistent.xlsx';
        $targetFile = $this->tempDir . '/output.csv';
        
        $converter = new Converter($sourceFile, $targetFile);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/源文件不存在/');
        
        $converter->convert();
    }
    
    public function testConvertEmptyFile(): void
    {
        // 创建空文件
        $sourceFile = $this->tempDir . '/empty.xlsx';
        $targetFile = $this->tempDir . '/output.csv';
        
        file_put_contents($sourceFile, '');
        
        $converter = new Converter($sourceFile, $targetFile);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/源文件为空/');
        
        $converter->convert();
    }
    
    public function testConvertWithMemoryManager(): void
    {
        $sourceFile = $this->testDataDir . '/small_simple.xlsx';
        $targetFile = $this->tempDir . '/memory_output.csv';
        
        $memoryManager = new MemoryManager(32 * 1024 * 1024); // 32MB限制
        $formatDetector = new FormatDetector();
        
        $converter = new Converter(
            $sourceFile, 
            $targetFile, 
            [],
            $formatDetector,
            $memoryManager
        );
        
        $result = $converter->convert();
        $this->assertTrue($result);
        
        // 验证内存使用
        $peakUsage = $memoryManager->getPeakUsage();
        $this->assertGreaterThan(0, $peakUsage);
    }
    
    public function testCsvEscaping(): void
    {
        // 这里可能需要创建包含特殊字符的测试文件
        $sourceFile = $this->testDataDir . '/small_simple.xlsx';
        $targetFile = $this->tempDir . '/csv_escape_output.csv';
        
        $converter = new Converter($sourceFile, $targetFile);
        $result = $converter->convert();
        
        $this->assertTrue($result);
        $this->assertFileExists($targetFile);
        
        $csvContent = file_get_contents($targetFile);
        $this->assertIsString($csvContent);
    }
    
    public function testProgressReporting(): void
    {
        $sourceFile = $this->testDataDir . '/large_data.xlsx';
        $targetFile = $this->tempDir . '/progress_output.csv';
        
        $config = ['chunk_size' => 100]; // 小块大小，更频繁的进度报告
        $converter = new Converter($sourceFile, $targetFile, $config);
        
        $result = $converter->convert();
        $this->assertTrue($result);
        
        $log = $converter->getConversionLog();
        $progressCount = 0;
        
        foreach ($log as $entry) {
            if (strpos($entry['message'], '已处理') !== false) {
                $progressCount++;
            }
        }
        
        // 对于大文件，应该有进度报告
        $this->assertGreaterThan(0, $progressCount);
    }
    
    /**
     * 递归删除目录
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
} 