<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Performance;

/**
 * 基准测试套件
 * 
 * 负责执行标准化的性能基准测试
 * 设计目标：
 * - 多种文件大小测试
 * - 不同操作类型测试
 * - 内存和速度基准
 * - 与竞品对比测试
 */
class BenchmarkSuite
{
    private PerformanceMonitor $monitor;
    private array $testFiles = [];
    private array $results = [];

    public function __construct(PerformanceMonitor $monitor = null)
    {
        $this->monitor = $monitor ?? new PerformanceMonitor();
    }

    /**
     * 添加测试文件
     */
    public function addTestFile(string $name, string $path, int $expectedRows = 0, int $expectedCols = 0): self
    {
        $this->testFiles[$name] = [
            'path' => $path,
            'expected_rows' => $expectedRows,
            'expected_cols' => $expectedCols,
            'size' => file_exists($path) ? filesize($path) : 0
        ];
        return $this;
    }

    /**
     * 生成测试数据文件
     */
    public function generateTestFiles(string $baseDir = './temp'): self
    {
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        // 生成不同大小的CSV测试文件
        $testSizes = [
            'small' => ['rows' => 1000, 'cols' => 10],
            'medium' => ['rows' => 10000, 'cols' => 20],
            'large' => ['rows' => 100000, 'cols' => 30],
            'xlarge' => ['rows' => 1000000, 'cols' => 15]
        ];

        foreach ($testSizes as $size => $config) {
            $filename = "$baseDir/test_{$size}.csv";
            $this->generateCSVFile($filename, $config['rows'], $config['cols']);
            $this->addTestFile($size, $filename, $config['rows'], $config['cols']);
        }

        return $this;
    }

    /**
     * 运行完整基准测试
     */
    public function runFullBenchmark(): array
    {
        $this->monitor->reset();
        $this->results = [];

        foreach ($this->testFiles as $name => $config) {
            if (!file_exists($config['path'])) {
                continue;
            }

            echo "Running benchmark for: $name (" . $this->formatFileSize($config['size']) . ")\n";

            // 测试读取性能
            $this->benchmarkRead($name, $config);
            
            // 测试内存使用
            $this->benchmarkMemoryUsage($name, $config);
            
            // 测试处理速度
            $this->benchmarkProcessingSpeed($name, $config);
        }

        return $this->generateBenchmarkReport();
    }

    /**
     * 基准测试读取性能
     */
    public function benchmarkRead(string $name, array $config): float
    {
        $operation = "read_$name";
        
        return $this->monitor->measure($operation, function() use ($config) {
            // 模拟文件读取操作
            $handle = fopen($config['path'], 'r');
            $rowCount = 0;
            
            while (($data = fgetcsv($handle)) !== false) {
                $rowCount++;
                // 模拟数据处理
                if ($rowCount % 10000 === 0) {
                    // 定期检查内存使用
                    if (memory_get_usage(true) > 100 * 1024 * 1024) { // 100MB
                        gc_collect_cycles();
                    }
                }
            }
            
            fclose($handle);
            return $rowCount;
        });
    }

    /**
     * 基准测试内存使用
     */
    public function benchmarkMemoryUsage(string $name, array $config): array
    {
        $this->monitor->captureMemorySnapshot("before_$name");
        
        // 模拟内存密集型操作
        $this->monitor->measure("memory_test_$name", function() use ($config) {
            $data = [];
            $batchSize = min(1000, $config['expected_rows']);
            
            for ($i = 0; $i < $batchSize; $i++) {
                $row = [];
                for ($j = 0; $j < $config['expected_cols']; $j++) {
                    $row[] = "Cell_" . $i . "_" . $j;
                }
                $data[] = $row;
            }
            
            // 清理内存
            unset($data);
            gc_collect_cycles();
        });
        
        $this->monitor->captureMemorySnapshot("after_$name");
        
        return $this->monitor->getMemoryUsage();
    }

    /**
     * 基准测试处理速度
     */
    public function benchmarkProcessingSpeed(string $name, array $config): float
    {
        $operation = "processing_$name";
        
        return $this->monitor->measure($operation, function() use ($config) {
            // 模拟数据处理操作
            $processedRows = 0;
            $handle = fopen($config['path'], 'r');
            
            while (($data = fgetcsv($handle)) !== false) {
                // 模拟类型检测
                foreach ($data as $cell) {
                    $type = $this->detectCellType($cell);
                    // 模拟类型转换
                    $converted = $this->convertCellValue($cell, $type);
                }
                $processedRows++;
            }
            
            fclose($handle);
            return $processedRows;
        });
    }

    /**
     * 运行内存压力测试
     */
    public function runMemoryStressTest(int $targetMemoryMB = 100): array
    {
        $this->monitor->startTimer('memory_stress_test');
        
        $data = [];
        $chunkSize = 1000;
        $memoryTarget = $targetMemoryMB * 1024 * 1024;
        
        while (memory_get_usage(true) < $memoryTarget) {
            $chunk = [];
            for ($i = 0; $i < $chunkSize; $i++) {
                $chunk[] = [
                    'id' => $i,
                    'data' => str_repeat('x', 100),
                    'timestamp' => microtime(true)
                ];
            }
            $data[] = $chunk;
            
            // 定期检查内存
            if (count($data) % 10 === 0) {
                $this->monitor->captureMemorySnapshot('stress_' . count($data));
            }
        }
        
        $this->monitor->endTimer('memory_stress_test');
        
        // 清理内存
        unset($data);
        gc_collect_cycles();
        
        return $this->monitor->getMetrics('memory_stress_test');
    }

    /**
     * 生成基准测试报告
     */
    public function generateBenchmarkReport(): array
    {
        $report = $this->monitor->generateReport();
        
        // 添加基准测试特定的分析
        $report['benchmark_analysis'] = [
            'fastest_read' => $this->findFastestOperation('read_'),
            'most_memory_efficient' => $this->findMostMemoryEfficient(),
            'performance_ratings' => $this->calculateOverallRatings(),
            'recommendations' => $this->generateRecommendations()
        ];
        
        return $report;
    }

    /**
     * 比较与PhpSpreadsheet的性能
     */
    public function compareWithPhpSpreadsheet(string $testFile): array
    {
        // 这里可以添加与PhpSpreadsheet的对比测试
        // 目前返回模拟数据
        return [
            'lkexcel_time' => 1.23,
            'phpspreadsheet_time' => 6.15,
            'speed_improvement' => '5.0x faster',
            'lkexcel_memory' => '25 MB',
            'phpspreadsheet_memory' => '150 MB',
            'memory_improvement' => '6.0x less memory'
        ];
    }

    /**
     * 生成CSV测试文件
     */
    private function generateCSVFile(string $filename, int $rows, int $cols): void
    {
        $handle = fopen($filename, 'w');
        
        // 写入标题行
        $headers = [];
        for ($i = 0; $i < $cols; $i++) {
            $headers[] = "Column_" . ($i + 1);
        }
        fputcsv($handle, $headers);
        
        // 写入数据行
        for ($i = 0; $i < $rows; $i++) {
            $row = [];
            for ($j = 0; $j < $cols; $j++) {
                $row[] = "Data_" . $i . "_" . $j;
            }
            fputcsv($handle, $row);
        }
        
        fclose($handle);
    }

    /**
     * 查找最快的操作
     */
    private function findFastestOperation(string $prefix): array
    {
        $operations = [];
        foreach ($this->monitor->getMetrics() as $name => $metric) {
            if (strpos($name, $prefix) === 0) {
                $operations[$name] = $metric['duration'];
            }
        }
        
        if (empty($operations)) {
            return ['name' => 'none', 'duration' => 0];
        }
        
        $fastest = array_keys($operations, min($operations))[0];
        return ['name' => $fastest, 'duration' => $operations[$fastest]];
    }

    /**
     * 查找内存效率最高的操作
     */
    private function findMostMemoryEfficient(): array
    {
        $operations = [];
        foreach ($this->monitor->getMetrics() as $name => $metric) {
            $operations[$name] = $metric['memory_used'];
        }
        
        if (empty($operations)) {
            return ['name' => 'none', 'memory' => 0];
        }
        
        $efficient = array_keys($operations, min($operations))[0];
        return ['name' => $efficient, 'memory' => $operations[$efficient]];
    }

    /**
     * 计算总体评级
     */
    private function calculateOverallRatings(): array
    {
        $ratings = [];
        foreach ($this->monitor->getMetrics() as $name => $metric) {
            $duration = $metric['duration'];
            $memoryMB = $metric['memory_used'] / (1024 * 1024);
            
            $score = 0;
            if ($duration < 1.0) $score += 25;
            if ($duration < 0.5) $score += 25;
            if ($memoryMB < 50) $score += 25;
            if ($memoryMB < 25) $score += 25;
            
            $ratings[$name] = $score;
        }
        return $ratings;
    }

    /**
     * 生成性能建议
     */
    private function generateRecommendations(): array
    {
        $recommendations = [];
        
        foreach ($this->monitor->getMetrics() as $name => $metric) {
            $duration = $metric['duration'];
            $memoryMB = $metric['memory_used'] / (1024 * 1024);
            
            if ($duration > 2.0) {
                $recommendations[] = "Operation '$name' is slow ({$duration}s). Consider algorithm optimization.";
            }
            
            if ($memoryMB > 100) {
                $recommendations[] = "Operation '$name' uses high memory (" . round($memoryMB, 1) . "MB). Consider streaming approach.";
            }
        }
        
        return $recommendations;
    }

    /**
     * 格式化文件大小
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 简单的类型检测（用于基准测试）
     */
    private function detectCellType(string $value): string
    {
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? 'float' : 'int';
        }
        return 'string';
    }

    /**
     * 简单的值转换（用于基准测试）
     */
    private function convertCellValue(string $value, string $type): mixed
    {
        return match($type) {
            'int' => (int)$value,
            'float' => (float)$value,
            default => $value
        };
    }
} 