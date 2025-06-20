<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use LkExcel\LkExportExcel\Api\Writer;

/**
 * 流式数据库导出测试
 * 测试Writer类的真正导出能力
 */

echo "🚀 开始流式数据库导出测试...\n";

try {
    // 连接数据库
    $dbPath = __DIR__ . '/../tests/fixtures/test_database.sqlite';
    $pdo = new PDO("sqlite:{$dbPath}", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✅ SQLite数据库连接成功: {$dbPath}\n";
    
    // 确保输出目录存在
    $outputDir = __DIR__ . '/../output';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    // 测试不同数据量
    $testCases = [
        ['limit' => 1000, 'name' => '1K'],
        ['limit' => 10000, 'name' => '10K'],
        ['limit' => 50000, 'name' => '50K'],
        ['limit' => 100000, 'name' => '100K'],
    ];
    
    foreach ($testCases as $testCase) {
        echo "\n=== 测试 {$testCase['name']} 数据导出 ===\n";
        
        $limit = $testCase['limit'];
        $outputFile = $outputDir . '/stream_export_' . strtolower($testCase['name']) . '.csv';
        
        $startTime = microtime(true);
        $memoryStart = memory_get_usage(true);
        
        // 创建Writer
        $writer = new Writer([
            'memory_limit' => 128 * 1024 * 1024, // 128MB
            'buffer_size' => 5000,  // 5000行缓冲区
            'enable_monitoring' => true
        ]);
        
        // 开始流式写入
        $writer->startWrite($outputFile);
        
        // 写入表头
        $headers = ['id', 'test_id', 'name', 'email', 'phone', 'company', 'position', 'salary'];
        $writer->writeHeaders($headers);
        
        echo "📊 开始流式导出 {$limit} 条数据...\n";
        
        // 流式查询和写入
        $stmt = $pdo->prepare("
            SELECT id, test_id, name, email, phone, company, position, salary 
            FROM performance_test_100k 
            ORDER BY id 
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $processed = 0;
        $batchSize = 5000;
        $nextReport = $batchSize;
        
        // 逐行读取和写入
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $writer->writeRow($row);
            $processed++;
            
            // 定期报告进度
            if ($processed >= $nextReport) {
                $currentMemory = memory_get_usage(true);
                $memoryIncrease = $currentMemory - $memoryStart;
                echo "  📈 已处理 {$processed} 行，内存使用 " . 
                     round($currentMemory / 1024 / 1024, 2) . " MB (+=" . 
                     round($memoryIncrease / 1024 / 1024, 2) . " MB)\n";
                $nextReport += $batchSize;
            }
        }
        
        // 完成写入
        $result = $writer->finishWrite();
        
        $totalTime = microtime(true) - $startTime;
        $memoryEnd = memory_get_usage(true);
        $stats = $writer->getStats();
        
        echo "✅ 导出完成！\n";
        echo "📊 统计信息:\n";
        echo "  - 处理行数: {$processed}\n";
        echo "  - 总耗时: " . round($totalTime, 2) . " 秒\n";
        echo "  - 平均速度: " . round($processed / $totalTime, 2) . " 行/秒\n";
        echo "  - 写入字节数: " . formatBytes($stats['bytes_written']) . "\n";
        echo "  - 缓冲区刷新: {$stats['buffer_flushes']} 次\n";
        echo "  - 内存峰值: " . formatBytes($stats['memory_peak']) . "\n";
        echo "  - 文件大小: " . formatBytes(filesize($outputFile)) . "\n";
        
        $stmt->closeCursor();
        unset($writer);
        
        // 强制垃圾回收
        gc_collect_cycles();
    }
    
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

echo "\n🏁 所有测试完成\n"; 