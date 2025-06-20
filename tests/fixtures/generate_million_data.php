<?php

declare(strict_types=1);

/**
 * 专门生成100万条数据的脚本
 * 用于测试大数据量的导入导出性能
 */

require_once __DIR__ . '/database_setup.php';

try {
    echo "🚀 开始生成100万条测试数据\n";
    echo "===========================\n\n";
    
    $setup = new DatabaseSetup();
    
    // 先清空所有表
    echo "📝 清空现有测试数据...\n";
    $setup->truncateAllTables();
    
    echo "\n🎯 开始生成100万条测试数据...\n";
    echo "预计需要5-10分钟，请耐心等待...\n\n";
    
    $startTime = microtime(true);
    $startMemory = memory_get_usage(true);
    
    // 使用新的公共方法生成百万级别数据
    $setup->generateMillionTestData();
    
    $totalTime = microtime(true) - $startTime;
    $endMemory = memory_get_usage(true);
    $peakMemory = memory_get_peak_usage(true);
    
    // 最终统计
    echo "🎉 100万条数据生成完成！\n";
    echo "========================\n\n";
    
    echo "📊 生成统计:\n";
    echo "  - 总记录数: " . number_format($totalRows) . " 条\n";
    echo "  - 总耗时: " . formatTime($totalTime) . "\n";
    echo "  - 平均速度: " . number_format($totalRows / $totalTime, 0) . " 条/秒\n";
    echo "  - 内存峰值: " . formatBytes($peakMemory) . "\n";
    echo "  - 当前内存: " . formatBytes($endMemory) . "\n\n";
    
    // 验证数据
    echo "🔍 数据验证:\n";
    $stats = $setup->getTableStats();
    foreach ($stats as $table => $count) {
        echo "  - {$table}: " . number_format($count) . " 条记录\n";
    }
    
    echo "\n✅ 数据库现在可用于大数据量测试了！\n\n";
    
    echo "💡 接下来可以运行:\n";
    echo "  - php examples/database_export_test.php (数据库导出测试)\n";
    echo "  - php examples/optimized_million_test.php (优化测试)\n";
    echo "  - php examples/million_import_test.php (导入测试)\n";
    
} catch (Exception $e) {
    echo "\n❌ 错误: " . $e->getMessage() . "\n";
    echo "📍 位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    if ($e->getPrevious()) {
        echo "📄 原始错误: " . $e->getPrevious()->getMessage() . "\n";
    }
    
    exit(1);
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
    return round($bytes, 1) . ' ' . $units[$pow];
}

/**
 * 格式化时间
 */
function formatTime(float $seconds): string
{
    if ($seconds < 60) {
        return round($seconds, 1) . ' 秒';
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return $minutes . ' 分 ' . round($secs, 1) . ' 秒';
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . ' 小时 ' . $minutes . ' 分钟';
    }
} 