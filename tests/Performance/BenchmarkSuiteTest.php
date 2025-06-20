<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Tests\Performance;

use PHPUnit\Framework\TestCase;
use LkExcel\LkExportExcel\Performance\BenchmarkSuite;
use LkExcel\LkExportExcel\Performance\PerformanceMonitor;

class BenchmarkSuiteTest extends TestCase
{
    private BenchmarkSuite $suite;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->suite = new BenchmarkSuite();
        $this->tempDir = sys_get_temp_dir() . '/lkexcel_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // 清理临时文件
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }
    }

    public function testConstructorWithCustomMonitor(): void
    {
        $monitor = new PerformanceMonitor();
        $suite = new BenchmarkSuite($monitor);
        
        $this->assertInstanceOf(BenchmarkSuite::class, $suite);
    }

    public function testAddTestFile(): void
    {
        $testFile = $this->tempDir . '/test.csv';
        file_put_contents($testFile, "col1,col2\nval1,val2\n");
        
        $result = $this->suite->addTestFile('test', $testFile, 1, 2);
        
        $this->assertInstanceOf(BenchmarkSuite::class, $result);
    }

    public function testGenerateTestFiles(): void
    {
        $this->suite->generateTestFiles($this->tempDir);
        
        // 检查生成的文件
        $expectedFiles = ['small', 'medium', 'large', 'xlarge'];
        foreach ($expectedFiles as $size) {
            $filename = $this->tempDir . "/test_{$size}.csv";
            $this->assertFileExists($filename);
            $this->assertGreaterThan(0, filesize($filename));
        }
    }

    public function testBenchmarkRead(): void
    {
        // 创建一个小测试文件
        $testFile = $this->tempDir . '/read_test.csv';
        $this->createTestCSV($testFile, 100, 5);
        
        $config = [
            'path' => $testFile,
            'expected_rows' => 100,
            'expected_cols' => 5,
            'size' => filesize($testFile)
        ];
        
        $duration = $this->suite->benchmarkRead('read_test', $config);
        
        $this->assertIsFloat($duration);
        $this->assertGreaterThan(0, $duration);
    }

    public function testBenchmarkMemoryUsage(): void
    {
        // 创建一个小测试文件
        $testFile = $this->tempDir . '/memory_test.csv';
        $this->createTestCSV($testFile, 50, 3);
        
        $config = [
            'path' => $testFile,
            'expected_rows' => 50,
            'expected_cols' => 3,
            'size' => filesize($testFile)
        ];
        
        $memoryUsage = $this->suite->benchmarkMemoryUsage('memory_test', $config);
        
        $this->assertIsArray($memoryUsage);
        $this->assertArrayHasKey('current', $memoryUsage);
        $this->assertArrayHasKey('peak', $memoryUsage);
    }

    public function testBenchmarkProcessingSpeed(): void
    {
        // 创建一个小测试文件
        $testFile = $this->tempDir . '/processing_test.csv';
        $this->createTestCSV($testFile, 100, 4);
        
        $config = [
            'path' => $testFile,
            'expected_rows' => 100,
            'expected_cols' => 4,
            'size' => filesize($testFile)
        ];
        
        $duration = $this->suite->benchmarkProcessingSpeed('processing_test', $config);
        
        $this->assertIsFloat($duration);
        $this->assertGreaterThan(0, $duration);
    }

    public function testRunMemoryStressTest(): void
    {
        $result = $this->suite->runMemoryStressTest(10); // 10MB目标
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('duration', $result);
        $this->assertArrayHasKey('memory_used', $result);
        $this->assertGreaterThan(0, $result['duration']);
    }

    public function testGenerateBenchmarkReport(): void
    {
        // 添加一个测试文件并运行一个简单的基准测试
        $testFile = $this->tempDir . '/report_test.csv';
        $this->createTestCSV($testFile, 10, 2);
        
        $this->suite->addTestFile('report_test', $testFile, 10, 2);
        
        $config = [
            'path' => $testFile,
            'expected_rows' => 10,
            'expected_cols' => 2,
            'size' => filesize($testFile)
        ];
        
        $this->suite->benchmarkRead('report_test', $config);
        
        $report = $this->suite->generateBenchmarkReport();
        
        $this->assertIsArray($report);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('operations', $report);
        $this->assertArrayHasKey('benchmark_analysis', $report);
        
        $analysis = $report['benchmark_analysis'];
        $this->assertArrayHasKey('fastest_read', $analysis);
        $this->assertArrayHasKey('most_memory_efficient', $analysis);
        $this->assertArrayHasKey('performance_ratings', $analysis);
        $this->assertArrayHasKey('recommendations', $analysis);
    }

    public function testCompareWithPhpSpreadsheet(): void
    {
        $comparison = $this->suite->compareWithPhpSpreadsheet('dummy_file.xlsx');
        
        $this->assertIsArray($comparison);
        $this->assertArrayHasKey('lkexcel_time', $comparison);
        $this->assertArrayHasKey('phpspreadsheet_time', $comparison);
        $this->assertArrayHasKey('speed_improvement', $comparison);
        $this->assertArrayHasKey('memory_improvement', $comparison);
    }

    public function testRunFullBenchmarkWithGeneratedFiles(): void
    {
        // 只生成小文件以避免测试超时
        $this->createTestCSV($this->tempDir . '/test_small.csv', 10, 3);
        $this->suite->addTestFile('small', $this->tempDir . '/test_small.csv', 10, 3);
        
        // 捕获输出
        ob_start();
        $report = $this->suite->runFullBenchmark();
        $output = ob_get_clean();
        
        $this->assertIsArray($report);
        $this->assertStringContainsString('Running benchmark for: small', $output);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('benchmark_analysis', $report);
    }

    public function testBenchmarkWithNonExistentFile(): void
    {
        $this->suite->addTestFile('nonexistent', '/path/that/does/not/exist.csv', 0, 0);
        
        // 应该跳过不存在的文件
        ob_start();
        $report = $this->suite->runFullBenchmark();
        ob_get_clean();
        
        $this->assertIsArray($report);
        // 报告应该是空的或只包含基本结构
        $this->assertEquals(0, $report['summary']['total_operations']);
    }

    /**
     * 创建测试CSV文件的辅助方法
     */
    private function createTestCSV(string $filename, int $rows, int $cols): void
    {
        $handle = fopen($filename, 'w');
        
        // 写入标题行
        $headers = [];
        for ($i = 0; $i < $cols; $i++) {
            $headers[] = "Col" . ($i + 1);
        }
        fputcsv($handle, $headers);
        
        // 写入数据行
        for ($i = 0; $i < $rows; $i++) {
            $row = [];
            for ($j = 0; $j < $cols; $j++) {
                $row[] = "Data{$i}_{$j}";
            }
            fputcsv($handle, $row);
        }
        
        fclose($handle);
    }
} 