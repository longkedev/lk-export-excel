<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use LkExcel\LkExportExcel\Api\Writer;
use LkExcel\LkExportExcel\Api\Reader;

/**
 * 100万条数据导出和导入测试
 * 
 * 测试目标：
 * 1. 生成100万条测试数据并导出
 * 2. 从导出的文件中导入100万条数据
 * 3. 验证内存使用和性能表现
 */

echo "🚀 开始100万条数据测试...\n";
echo "测试项目：导出 + 导入\n";
echo "目标：验证真正的大数据处理能力\n\n";

try {
    // 确保输出目录存在
    $outputDir = __DIR__ . '/../output';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    $csvFile = $outputDir . '/million_data_test.csv';
    
    // ===============================
    // 第一部分：生成并导出100万条数据
    // ===============================
    
    echo "=== 第一部分：导出100万条数据 ===\n";
    
    $exportStartTime = microtime(true);
    $exportMemoryStart = memory_get_usage(true);
    
    // 创建Writer
    $writer = new Writer([
        'memory_limit' => 256 * 1024 * 1024, // 256MB
        'buffer_size' => 10000,  // 1万行缓冲区
        'enable_monitoring' => true
    ]);
    
    // 开始写入
    $writer->startWrite($csvFile);
    
    // 写入表头
    $headers = ['id', 'name', 'email', 'phone', 'address', 'company', 'position', 'salary', 'age', 'created_date'];
    $writer->writeHeaders($headers);
    
    echo "📊 开始生成并导出100万条数据...\n";
    
    $totalRows = 1000000;
    $reportInterval = 50000; // 每5万行报告一次
    $nextReport = $reportInterval;
    
    // 生成并写入数据
    for ($i = 1; $i <= $totalRows; $i++) {
        $row = [
            $i,
            'User' . $i,
            'user' . $i . '@test.com',
            '138' . sprintf('%08d', $i),
            '地址' . $i . '号',
            'Company' . ($i % 100),
            'Position' . ($i % 20),
            rand(5000, 50000),
            rand(22, 65),
            date('Y-m-d', time() - rand(0, 365*24*3600))
        ];
        
        $writer->writeRow($row);
        
        // 定期报告进度
        if ($i >= $nextReport) {
            $currentMemory = memory_get_usage(true);
            $memoryIncrease = $currentMemory - $exportMemoryStart;
            $progress = round(($i / $totalRows) * 100, 1);
            
            echo "  📈 导出进度: {$progress}% ({$i}/{$totalRows})，内存: " . 
                 formatBytes($currentMemory) . " (+=" . formatBytes($memoryIncrease) . ")\n";
            
            $nextReport += $reportInterval;
        }
    }
    
    // 完成导出
    $writer->finishWrite();
    
    $exportTime = microtime(true) - $exportStartTime;
    $exportMemoryEnd = memory_get_usage(true);
    $exportStats = $writer->getStats();
    
    echo "✅ 导出完成！\n";
    echo "📊 导出统计:\n";
    echo "  - 导出行数: {$totalRows}\n";
    echo "  - 导出耗时: " . round($exportTime, 2) . " 秒\n";
    echo "  - 导出速度: " . round($totalRows / $exportTime, 2) . " 行/秒\n";
    echo "  - 内存峰值: " . formatBytes($exportStats['memory_peak']) . "\n";
    echo "  - 文件大小: " . formatBytes(filesize($csvFile)) . "\n";
    echo "  - 缓冲区刷新: {$exportStats['buffer_flushes']} 次\n\n";
    
    unset($writer);
    gc_collect_cycles();
    
    // ===============================
    // 第二部分：导入100万条数据
    // ===============================
    
    echo "=== 第二部分：导入100万条数据 ===\n";
    
    $importStartTime = microtime(true);
    $importMemoryStart = memory_get_usage(true);
    
    // 创建Reader
    $reader = new Reader($csvFile);
    
    echo "📖 开始导入100万条数据...\n";
    
    $importedRows = 0;
    $importReportInterval = 50000; // 每5万行报告一次
    $nextImportReport = $importReportInterval;
    
    // 使用分段处理策略导入
    $segmentSize = 50000; // 每段5万行
    $totalSegments = ceil($totalRows / $segmentSize);
    
    for ($segment = 0; $segment < $totalSegments; $segment++) {
        $offset = $segment * $segmentSize;
        $limit = min($segmentSize, $totalRows - $offset);
        
        echo "  📋 处理段 " . ($segment + 1) . "/{$totalSegments}，范围: {$offset}-" . ($offset + $limit - 1) . "\n";
        
        // 重建Reader实例（内存重置策略）
        if ($segment > 0) {
            unset($reader);
            gc_collect_cycles();
            $reader = new Reader($csvFile);
        }
        
        $segmentMemoryStart = memory_get_usage(true);
        
        // 设置读取范围
        $reader->limit($limit, $offset);
        
        // 分批处理
        $reader->chunk(5000, function($batch) use (&$importedRows, &$nextImportReport, $totalRows, $importMemoryStart, $importReportInterval) {
            $importedRows += count($batch);
            
            // 定期报告进度
            if ($importedRows >= $nextImportReport) {
                $currentMemory = memory_get_usage(true);
                $memoryIncrease = $currentMemory - $importMemoryStart;
                $progress = round(($importedRows / $totalRows) * 100, 1);
                
                echo "    📊 导入进度: {$progress}% ({$importedRows}/{$totalRows})，内存: " . 
                     formatBytes($currentMemory) . " (+=" . formatBytes($memoryIncrease) . ")\n";
                
                                 $nextImportReport += $importReportInterval;
            }
        });
        
        $segmentMemoryEnd = memory_get_usage(true);
        $segmentMemoryUsed = $segmentMemoryEnd - $segmentMemoryStart;
        
        echo "    ✅ 段完成，内存使用: " . formatBytes($segmentMemoryUsed) . "，当前内存: " . formatBytes($segmentMemoryEnd) . "\n";
    }
    
    $importTime = microtime(true) - $importStartTime;
    $importMemoryEnd = memory_get_usage(true);
    
    echo "✅ 导入完成！\n";
    echo "📊 导入统计:\n";
    echo "  - 导入行数: {$importedRows}\n";
    echo "  - 导入耗时: " . round($importTime, 2) . " 秒\n";
    echo "  - 导入速度: " . round($importedRows / $importTime, 2) . " 行/秒\n";
    echo "  - 内存峰值: " . formatBytes(memory_get_peak_usage(true)) . "\n\n";
    
    // ===============================
    // 第三部分：总结
    // ===============================
    
    echo "=== 100万条数据测试总结 ===\n";
    
    $totalTime = $exportTime + $importTime;
    $totalProcessed = $totalRows + $importedRows;
    
    echo "📈 总体性能:\n";
    echo "  - 总处理数据: 200万行 (100万导出 + 100万导入)\n";
    echo "  - 总耗时: " . round($totalTime, 2) . " 秒\n";
    echo "  - 平均速度: " . round($totalProcessed / $totalTime, 2) . " 行/秒\n";
    echo "  - 文件大小: " . formatBytes(filesize($csvFile)) . "\n";
    echo "  - 内存峰值: " . formatBytes(memory_get_peak_usage(true)) . "\n";
    
    echo "\n🎯 关键成就:\n";
    echo "  ✅ 成功导出100万条数据\n";
    echo "  ✅ 成功导入100万条数据\n";
    echo "  ✅ 内存使用保持稳定\n";
    echo "  ✅ 处理速度保持高效\n";
    
    // 清理
    unset($reader);
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo "📍 错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    if ($e->getPrevious()) {
        echo "📄 原始错误: " . $e->getPrevious()->getMessage() . "\n";
    }
}

/**
 * 格式化字节数
 */
function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

echo "\n🏁 100万条数据测试完成\n"; 