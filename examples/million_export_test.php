<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use LkExcel\LkExportExcel\Api\Writer;
use LkExcel\LkExportExcel\Performance\ProgressReporter;

/**
 * 100万条数据导出专项测试
 * 
 * 测试重点：
 * 1. 导出性能和内存控制
 * 2. 进度条实时显示
 * 3. 大数据量处理能力
 */

echo "🚀 100万条数据导出测试\n";
echo "=======================\n\n";

try {
    // 确保输出目录存在
    $outputDir = __DIR__ . '/../output';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    $csvFile = $outputDir . '/million_export_test.csv';
    $totalRows = 1000000;
    
    echo "📋 测试配置:\n";
    echo "- 数据行数: " . number_format($totalRows) . " 行\n";
    echo "- 输出格式: CSV\n";
    echo "- 缓冲策略: 大缓冲区 (20K行)\n";
    echo "- 内存限制: 512MB\n";
    echo "- 进度更新: 每2万行\n\n";
    
    echo "按Enter开始导出测试...";
    readline();
    
    // 创建进度报告器
    $progressReporter = ProgressReporter::createTerminal([
        'update_interval' => 1.0, // 1秒更新一次
        'progress_bar_width' => 60,
        'enable_colors' => true,
        'memory_warning_threshold' => 0.8,
    ]);
    
    // 开始导出任务
    $progressReporter->start('导出100万条数据', $totalRows, [
        '目标文件' => basename($csvFile),
        '数据字段' => '7个字段',
        '预估大小' => '~100MB'
    ]);
    
    $exportStartTime = microtime(true);
    $exportStartMemory = memory_get_usage(true);
    
    // 创建Writer
    $writer = new Writer([
        'memory_limit' => 512 * 1024 * 1024, // 512MB
        'buffer_size' => 20000,  // 2万行缓冲区
        'enable_monitoring' => true
    ]);
    
    // 开始写入
    $writer->startWrite($csvFile);
    
    // 写入表头
    $headers = ['id', 'name', 'email', 'phone', 'address', 'company', 'created_date'];
    $writer->writeHeaders($headers);
    
    // 生成数据并写入
    $updateInterval = 20000; // 每2万行更新一次
    $flushCount = 0;
    
    for ($i = 1; $i <= $totalRows; $i++) {
        // 生成测试数据
        $row = [
            $i,
            'User' . $i,
            'user' . $i . '@example.com',
            '138' . sprintf('%08d', $i % 100000000),
            '北京市朝阳区地址' . $i . '号楼',
            'Company' . ($i % 1000),
            date('Y-m-d H:i:s', time() - rand(0, 365*24*3600))
        ];
        
        $writer->writeRow($row);
        
        // 更新进度
        if ($i % $updateInterval === 0) {
            $currentMemory = memory_get_usage(true);
            $memoryIncrease = $currentMemory - $exportStartMemory;
            $elapsed = microtime(true) - $exportStartTime;
            $speed = $elapsed > 0 ? $i / $elapsed : 0;
            
            $progressReporter->update($i, [
                '当前速度' => number_format($speed, 0) . ' 行/秒',
                '内存增长' => formatBytes($memoryIncrease),
                '已耗时' => formatTime($elapsed),
                '缓冲状态' => '正在写入'
            ]);
        }
    }
    
    // 完成导出
    $writer->finishWrite();
    
    $exportTime = microtime(true) - $exportStartTime;
    $exportMemoryPeak = memory_get_peak_usage(true);
    $fileSize = filesize($csvFile);
    
    $progressReporter->finish([
        '文件大小' => formatBytes($fileSize),
        '总耗时' => formatTime($exportTime),
        '平均速度' => number_format($totalRows / $exportTime, 0) . ' 行/秒',
        '内存峰值' => formatBytes($exportMemoryPeak)
    ]);
    
    unset($writer);
    gc_collect_cycles();
    
    // 导出结果统计
    echo "\n🎯 导出测试结果\n";
    echo "================\n";
    echo "✅ 导出状态: 成功完成\n";
    echo "📊 数据统计:\n";
    echo "  - 导出行数: " . number_format($totalRows) . " 行\n";
    echo "  - 文件大小: " . formatBytes($fileSize) . "\n";
    echo "  - 平均行大小: " . round($fileSize / $totalRows) . " 字节/行\n\n";
    
    echo "⚡ 性能指标:\n";
    echo "  - 总耗时: " . formatTime($exportTime) . "\n";
    echo "  - 导出速度: " . number_format($totalRows / $exportTime, 0) . " 行/秒\n";
    echo "  - 吞吐量: " . formatBytes((int)($fileSize / $exportTime)) . "/秒\n\n";
    
    echo "💾 内存使用:\n";
    echo "  - 内存峰值: " . formatBytes($exportMemoryPeak) . "\n";
    echo "  - 内存增长: " . formatBytes($exportMemoryPeak - $exportStartMemory) . "\n";
    echo "  - 内存效率: " . round($fileSize / $exportMemoryPeak, 2) . "x (文件大小/内存峰值)\n\n";
    
    // 文件验证
    echo "🔍 文件验证:\n";
    $actualLines = 0;
    $handle = fopen($csvFile, 'r');
    while (($line = fgets($handle)) !== false) {
        $actualLines++;
    }
    fclose($handle);
    
    echo "  - 期望行数: " . number_format($totalRows + 1) . " 行 (含表头)\n";
    echo "  - 实际行数: " . number_format($actualLines) . " 行\n";
    echo "  - 验证结果: " . ($actualLines === $totalRows + 1 ? "✅ 通过" : "❌ 失败") . "\n\n";
    
    // 性能评级
    $speed = $totalRows / $exportTime;
    if ($speed > 200000) {
        $grade = "🏆 优秀";
    } elseif ($speed > 150000) {
        $grade = "🥇 良好";  
    } elseif ($speed > 100000) {
        $grade = "🥈 一般";
    } else {
        $grade = "🥉 需优化";
    }
    
    echo "🏅 性能评级: $grade (基于 " . number_format($speed, 0) . " 行/秒)\n\n";
    
    // 和其他系统对比
    echo "📈 性能对比参考:\n";
    echo "  - PhpSpreadsheet: ~10,000-30,000 行/秒 (会内存溢出)\n";
    echo "  - lkExportExcel: " . number_format($speed, 0) . " 行/秒 (内存稳定)\n";
    echo "  - 性能提升: " . round($speed / 20000, 1) . "x 倍\n\n";
    
    echo "📝 测试文件保存位置: " . $csvFile . "\n";
    echo "💡 提示: 该文件可用于后续的导入测试\n\n";
    
} catch (Exception $e) {
    echo "\n❌ 导出测试失败: " . $e->getMessage() . "\n";
    echo "📍 错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    if ($e->getPrevious()) {
        echo "📄 原始错误: " . $e->getPrevious()->getMessage() . "\n";
    }
    
    // 输出调试信息
    echo "\n🔧 调试信息:\n";
    echo "  - 当前内存: " . formatBytes(memory_get_usage(true)) . "\n";
    echo "  - 内存峰值: " . formatBytes(memory_get_peak_usage(true)) . "\n";
    echo "  - 内存限制: " . ini_get('memory_limit') . "\n";
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

echo "�� 100万条数据导出测试完成！\n"; 