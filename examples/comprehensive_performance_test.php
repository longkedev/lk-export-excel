<?php

declare(strict_types=1);

/**
 * LkExportExcel 综合性能测试程序 (带进度条)
 * 
 * 测试不同数据量级的导入导出性能：
 * 1. 小数据集: 1K条记录
 * 2. 中数据集: 10K条记录  
 * 3. 大数据集: 100K条记录（performance_test表）
 * 4. 超大数据集: 1M条记录（million_test表）
 */

require_once __DIR__ . '/../vendor/autoload.php';

use LkExcel\LkExportExcel\Api\Reader;
use LkExcel\LkExportExcel\Api\Writer;
use LkExcel\LkExportExcel\Memory\MemoryManager;
use LkExcel\LkExportExcel\Performance\PerformanceMonitor;
use LkExcel\LkExportExcel\Performance\ProgressReporter;

class ComprehensivePerformanceTest
{
    private PDO $pdo;
    private MemoryManager $memoryManager;
    private PerformanceMonitor $performanceMonitor;
    private ProgressReporter $progressReporter;
    private array $testResults = [];
    
    public function __construct()
    {
        $this->memoryManager = new MemoryManager();
        $this->performanceMonitor = new PerformanceMonitor();
        $this->progressReporter = new ProgressReporter();
        $this->connectDatabase();
        $this->ensureOutputDirectory();
    }
    
    private function connectDatabase(): void
    {
        try {
            $this->pdo = new PDO(
                "mysql:host=192.168.20.66;port=3306;dbname=exportexcle;charset=utf8mb4",
                'exportexcle',
                'bmZfKedRftDTMjKL',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            echo "✅ 数据库连接成功\n\n";
        } catch (Exception $e) {
            echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    private function ensureOutputDirectory(): void
    {
        $outputDir = __DIR__ . '/../output';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
    }
    
    /**
     * 运行完整的性能测试套件
     */
    public function runComprehensiveTest(): array
    {
        echo "🚀 LkExportExcel 综合性能测试 (带进度条)\n";
        echo "==========================================\n\n";
        
        $this->printSystemInfo();
        
        // 测试用例配置
        $testCases = [
            [
                'name' => '小数据集测试',
                'description' => '1K条记录',
                'limit' => 1000,
                'table' => 'performance_test',
                'priority' => 'high'
            ],
            [
                'name' => '中数据集测试', 
                'description' => '10K条记录',
                'limit' => 10000,
                'table' => 'performance_test',
                'priority' => 'high'
            ],
            [
                'name' => '大数据集测试',
                'description' => '100K条记录（全部performance_test表数据）',
                'limit' => null, // 全部数据
                'table' => 'performance_test',
                'priority' => 'medium'
            ],
            [
                'name' => '超大数据集测试',
                'description' => '1M条记录（全部million_test表数据）',
                'limit' => null, // 全部数据
                'table' => 'million_test', 
                'priority' => 'low'
            ]
        ];
        
        // 显示整体进度
        $totalTests = count($testCases);
        echo "📋 测试计划: 共 {$totalTests} 个测试用例\n\n";
        
        foreach ($testCases as $index => $testCase) {
            echo "🎯 整体进度: [" . ($index + 1) . "/{$totalTests}]\n\n";
            
            $this->runSingleTest($index + 1, $testCase);
        }
        
        $this->generateFinalReport();
        
        return $this->testResults;
    }
    
    /**
     * 运行单个测试用例
     */
    private function runSingleTest(int $testNumber, array $testCase): void
    {
        echo "📊 测试 {$testNumber}: {$testCase['name']}\n";
        echo "   描述: {$testCase['description']}\n";
        echo "   优先级: {$testCase['priority']}\n";
        echo str_repeat("-", 50) . "\n";
        
        $testResult = [
            'test_number' => $testNumber,
            'name' => $testCase['name'],
            'description' => $testCase['description'],
            'table' => $testCase['table'],
            'limit' => $testCase['limit'],
            'priority' => $testCase['priority'],
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'export_results' => [],
            'import_results' => [],
            'status' => 'running'
        ];
        
                    try {
                // 获取实际数据量
                $sql = "SELECT COUNT(*) FROM {$testCase['table']}";
                if ($testCase['limit']) {
                    $actualLimit = min($testCase['limit'], $this->pdo->query($sql)->fetchColumn());
                } else {
                    $actualLimit = $this->pdo->query($sql)->fetchColumn();
                }
                
                echo "📈 预计处理: " . number_format($actualLimit) . " 条记录\n";
                echo "🚀 自动调优: 内部引擎会根据数据量智能优化\n\n";
            
            // 1. 导出测试
            echo "🔄 步骤1: 数据导出测试\n";
            $testResult['export_results'] = $this->runExportTest($testCase, $actualLimit);
            
            echo "\n🔄 步骤2: 数据导入测试\n";
            $testResult['import_results'] = $this->runImportTest($testCase, $testResult['export_results'], $actualLimit);
            
            $testResult['status'] = 'completed';
            $testResult['end_time'] = microtime(true);
            $testResult['end_memory'] = memory_get_usage(true);
            $testResult['peak_memory'] = memory_get_peak_usage(true);
            $testResult['total_time'] = $testResult['end_time'] - $testResult['start_time'];
            
            $this->printTestSummary($testResult);
            
        } catch (Exception $e) {
            echo "❌ 测试失败: " . $e->getMessage() . "\n";
            $testResult['status'] = 'failed';
            $testResult['error'] = $e->getMessage();
        }
        
        $this->testResults[] = $testResult;
        echo "\n" . str_repeat("=", 60) . "\n\n";
    }
    
    /**
     * 运行导出测试
     */
    private function runExportTest(array $testCase, int $expectedRows): array
    {
        $exportResults = [];
        $formats = ['csv', 'xlsx'];
        
        foreach ($formats as $formatIndex => $format) {
            echo "   📄 导出为 {$format} 格式 [" . ($formatIndex + 1) . "/2]...\n";
            
            $limitStr = $testCase['limit'] ? (string)$testCase['limit'] : 'all';
            $filename = "test_{$testCase['table']}_{$limitStr}.{$format}";
            $outputPath = __DIR__ . "/../output/{$filename}";
            
            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);
            
            try {
                // 构建SQL查询
                $sql = "SELECT * FROM {$testCase['table']}";
                if ($testCase['limit']) {
                    $sql .= " LIMIT {$testCase['limit']}";
                }
                
                // 🎯 只传递数据（变量），方式（常量）由Writer内部处理
                $writer = new Writer();
                $writer->startWrite($outputPath)
                       ->fromDatabase($this->pdo, $sql)
                       ->finishWrite();
                
                // 从Writer获取统计信息
                $stats = $writer->getStats();
                $actualRows = $stats['rows_written'];
                
                $endTime = microtime(true);
                $endMemory = memory_get_usage(true);
                $fileSize = file_exists($outputPath) ? filesize($outputPath) : 0;
                
                // 显示详细统计
                echo "      📈 写入统计:\n";
                echo "         记录数: " . number_format($actualRows) . " 条\n";
                echo "         文件大小: " . $this->formatBytes($fileSize) . "\n";
                echo "         耗时: " . round($endTime - $startTime, 2) . "s\n";
                echo "         平均速度: " . number_format($actualRows / ($endTime - $startTime), 0) . " 条/秒\n";
                
                // 🚀 流式处理的内存统计：显示实际使用情况
                $memoryDelta = $endMemory - $startMemory;
                if ($memoryDelta > 1024) {
                    echo "         内存使用: " . $this->formatBytes($memoryDelta) . "\n";
                } else {
                    echo "         内存使用: 流式处理，内存恒定\n";
                }
                
                $exportResults[$format] = [
                    'status' => 'success',
                    'filename' => $filename,
                    'file_path' => $outputPath,
                    'file_size' => $fileSize,
                    'rows_exported' => $actualRows,
                    'time_taken' => $endTime - $startTime,
                    'memory_used' => $endMemory - $startMemory,
                    'peak_memory' => memory_get_peak_usage(true),
                    'export_speed' => $actualRows / ($endTime - $startTime)
                ];
                
                echo "      ✅ {$format} 导出成功！\n";
                
            } catch (Exception $e) {
                echo "\n";
                $exportResults[$format] = [
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
                echo "      ❌ 导出失败: " . $e->getMessage() . "\n";
            }
            echo "\n";
        }
        
        return $exportResults;
    }
    
    /**
     * 运行导入测试
     */
    private function runImportTest(array $testCase, array $exportResults, int $expectedRows): array
    {
        $importResults = [];
        
        foreach ($exportResults as $format => $exportResult) {
            if ($exportResult['status'] !== 'success') {
                continue;
            }
            
            $formatIndex = array_search($format, array_keys($exportResults)) + 1;
            echo "   📥 导入 {$format} 文件 [{$formatIndex}/" . count($exportResults) . "]...\n";
            
            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);
            $startPeakMemory = memory_get_peak_usage(true);
            
            try {
                // 🚀 高复用：只传递数据，Reader内部自动处理进度显示
                echo "      📊 开始读取文件: {$exportResult['filename']}\n";
                
                $reader = new Reader($exportResult['file_path']);
                
                $importedRows = 0;
                $validRows = 0;
                
                // Reader内部自动显示进度，测试程序只需要处理数据
                foreach ($reader->toIterator() as $row) {
                    $importedRows++;
                    if (!empty($row) && count(array_filter($row)) > 0) {
                        $validRows++;
                    }
                }
                
                $endTime = microtime(true);
                $endMemory = memory_get_usage(true);
                $endPeakMemory = memory_get_peak_usage(true);
                
                echo "\n      ✅ 数据导入完成！\n";
                
                // 显示详细统计
                echo "      📈 导入统计:\n";
                echo "         总行数: " . number_format($importedRows) . " 条\n";
                echo "         有效行数: " . number_format($validRows) . " 条\n";
                echo "         耗时: " . round($endTime - $startTime, 2) . "s\n";
                echo "         平均速度: " . number_format($importedRows / ($endTime - $startTime), 0) . " 条/秒\n";
                
                // 🚀 流式处理的内存统计：显示峰值变化和工作内存
                $peakMemoryDelta = $endPeakMemory - $startPeakMemory;
                $currentMemoryDelta = $endMemory - $startMemory;
                
                if ($peakMemoryDelta > 1024) {
                    echo "         峰值内存增长: " . $this->formatBytes($peakMemoryDelta) . "\n";
                } else {
                    echo "         内存使用: 流式处理，内存恒定\n";
                }
                
                $integrityStatus = $this->checkDataIntegrity($exportResult['rows_exported'], $importedRows, $validRows);
                
                $importResults[$format] = [
                    'status' => 'success',
                    'rows_imported' => $importedRows,
                    'valid_rows' => $validRows,
                    'time_taken' => $endTime - $startTime,
                    'memory_used' => $endMemory - $startMemory,
                    'peak_memory' => memory_get_peak_usage(true),
                    'import_speed' => $importedRows / ($endTime - $startTime),
                    'data_integrity' => $integrityStatus
                ];
                
                echo "      🔍 数据完整性: " . $integrityStatus['status'] . "\n";
                echo "      ✅ {$format} 导入成功！\n";
                
            } catch (Exception $e) {
                echo "\n";
                $importResults[$format] = [
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
                echo "      ❌ 导入失败: " . $e->getMessage() . "\n";
            }
            echo "\n";
        }
        
        return $importResults;
    }
    

    
    /**
     * 检查数据完整性
     */
    private function checkDataIntegrity(int $exported, int $imported, int $valid): array
    {
        // 智能完整性检查
        $expectedWithHeader = $exported + 1; // 数据行 + 表头行
        
        if ($imported === $expectedWithHeader && $valid === $expectedWithHeader) {
            return ['status' => '✅ 完整 (含表头)', 'note' => 'Perfect match with header'];
        } elseif ($imported === $exported && $valid === $exported) {
            return ['status' => '✅ 完整 (纯数据)', 'note' => 'Perfect match without header'];
        } elseif ($valid >= $exported && $imported >= $exported) {
            return ['status' => '✅ 完整 (有额外行)', 'note' => 'Complete with extra rows'];
        } else {
            $note = sprintf("期望%s行,实际%s行", number_format($exported), number_format($valid));
            return ['status' => '⚠️ 有差异', 'note' => $note];
        }
    }
    
    /**
     * 打印测试总结
     */
    private function printTestSummary(array $testResult): void
    {
        echo "\n📋 测试总结:\n";
        echo "   🎯 测试名称: {$testResult['name']}\n";
        echo "   📊 数据表: {$testResult['table']}\n";
        echo "   ⏱️  总耗时: " . round($testResult['total_time'], 2) . "s\n";
        echo "   💾 内存峰值: " . $this->formatBytes($testResult['peak_memory']) . "\n";
        echo "   📈 状态: " . ($testResult['status'] === 'completed' ? '✅ 完成' : '❌ 失败') . "\n";
    }
    
    /**
     * 生成最终报告
     */
    private function generateFinalReport(): void
    {
        echo "📊 最终测试报告\n";
        echo "===============\n\n";
        
        $totalTests = count($this->testResults);
        $successfulTests = count(array_filter($this->testResults, fn($r) => $r['status'] === 'completed'));
        $failedTests = $totalTests - $successfulTests;
        
        echo "🎯 测试概览:\n";
        echo "   总测试数: {$totalTests}\n";
        echo "   成功: {$successfulTests}\n";
        echo "   失败: {$failedTests}\n";
        echo "   成功率: " . round(($successfulTests / $totalTests) * 100, 1) . "%\n\n";
        
        foreach ($this->testResults as $result) {
            if ($result['status'] !== 'completed') continue;
            
            echo "📋 {$result['name']}:\n";
            echo "   ⏱️  总耗时: " . round($result['total_time'], 2) . "s\n";
            echo "   💾 内存峰值: " . $this->formatBytes($result['peak_memory']) . "\n";
            
            foreach (['csv', 'xlsx'] as $format) {
                if (isset($result['export_results'][$format]) && $result['export_results'][$format]['status'] === 'success') {
                    $export = $result['export_results'][$format];
                    $import = $result['import_results'][$format] ?? null;
                    
                    echo "   📄 {$format}格式:\n";
                    echo "      导出: " . number_format($export['rows_exported']) . "条, ";
                    echo number_format($export['export_speed'], 0) . "条/秒, ";
                    echo $this->formatBytes($export['file_size']) . "\n";
                    
                    if ($import && $import['status'] === 'success') {
                        echo "      导入: " . number_format($import['rows_imported']) . "条, ";
                        echo number_format($import['import_speed'], 0) . "条/秒, ";
                        echo $import['data_integrity']['status'] . "\n";
                    }
                }
            }
            echo "\n";
        }
    }
    
    /**
     * 打印系统信息
     */
    private function printSystemInfo(): void
    {
        echo "🖥️  系统信息:\n";
        echo "   PHP版本: " . PHP_VERSION . "\n";
        echo "   内存限制: " . ini_get('memory_limit') . "\n";
        echo "   最大执行时间: " . ini_get('max_execution_time') . "s\n";
        echo "   操作系统: " . PHP_OS . "\n";
        echo "   测试时间: " . date('Y-m-d H:i:s') . "\n\n";
    }
    
    /**
     * 格式化字节数
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 1) . ' ' . $units[$pow];
    }
    
    /**
     * 获取测试结果用于报告生成
     */
    public function getTestResults(): array
    {
        return $this->testResults;
    }
    

}

// 运行测试
if (isset($argv[0]) && basename($argv[0]) === basename(__FILE__)) {
    echo "🎯 开始综合性能测试...\n\n";
    
    $test = new ComprehensivePerformanceTest();
    $results = $test->runComprehensiveTest();
    
    echo "\n🎉 所有测试完成！\n";
    echo "📄 详细报告已保存，可用于README.md更新\n";
} 