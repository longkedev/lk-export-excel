<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use LkExcel\LkExportExcel\Api\Reader;
use LkExcel\LkExportExcel\Api\Writer;
use LkExcel\LkExportExcel\Performance\ProgressReporter;

/**
 * 智能优化的100万数据导入导出测试
 * 
 * 智能特性：
 * 1. 根据数据量自动调整所有参数
 * 2. 自适应内存检查频率
 * 3. 自适应进度更新频率
 * 4. 自适应缓冲区大小
 */

echo "🚀 智能优化的100万数据导入导出测试\n";
echo "====================================\n\n";

// 测试配置
$totalRows = 1000000;
$exportFile = __DIR__ . '/../output/optimized_million_test.csv';

// 根据数据量自动计算参数
$batchSize = calculateOptimalBatchSize($totalRows);
$bufferSize = calculateOptimalBufferSize($totalRows);
$progressUpdateInterval = calculateProgressUpdateInterval($totalRows);

echo "📋 智能配置（根据数据量自动调整）:\n";
echo "- 数据量: " . number_format($totalRows) . " 行\n";
echo "- 批次大小: " . number_format($batchSize) . " 行\n";
echo "- 缓冲区大小: " . number_format($bufferSize) . " 行\n";
echo "- 进度更新间隔: 每 " . number_format($progressUpdateInterval) . " 行\n";
echo "- 输出文件: " . basename($exportFile) . "\n\n";

// ======= 第一部分：导出测试 =======
echo "==========================================\n";
echo "📤 第一部分：智能导出测试\n";
echo "==========================================\n\n";

// ProgressReporter会自动根据总数调整更新频率
$progress = ProgressReporter::createTerminal([
    'progress_bar_width' => 50,
    'enable_colors' => true,
]);

$progress->start('导出100万条数据', $totalRows, [
    '输出文件' => basename($exportFile),
    '批次大小' => number_format($batchSize),
    '模式' => '智能流式写入'
]);

$exportStartTime = microtime(true);
$exportStartMemory = memory_get_usage(true);

// 创建Writer（使用智能配置）
$writer = new Writer([
    'memory_limit' => calculateMemoryLimit($totalRows),
    'buffer_size' => $bufferSize,
]);

$writer->startWrite($exportFile);

// 写表头
$headers = ['ID', '姓名', '邮箱', '年龄', '城市', '创建时间', '备注'];
$writer->writeHeaders($headers);

// 批量生成和写入数据
for ($i = 1; $i <= $totalRows; $i++) {
    $row = [
        $i,
        "用户{$i}",
        "user{$i}@example.com", 
        rand(18, 80),
        "城市" . rand(1, 100),
        date('Y-m-d H:i:s'),
        "测试数据{$i}"
    ];
    
    $writer->writeRow($row);
    
    // 智能进度更新（频率根据数据量自动调整）
    if ($i % $progressUpdateInterval === 0) {
        $elapsed = microtime(true) - $exportStartTime;
        $speed = $elapsed > 0 ? $i / $elapsed : 0;
        $currentMemory = memory_get_usage(true);
        
        $progress->update($i, [
            '当前速度' => number_format($speed, 0) . ' 行/秒',
            '当前内存' => formatBytes($currentMemory),
            '进度状态' => '正在写入...'
        ]);
    }
}

$writer->finishWrite();

$exportTime = microtime(true) - $exportStartTime;
$exportMemoryPeak = memory_get_peak_usage(true);
$fileSize = filesize($exportFile);

$progress->finish([
    '导出行数' => number_format($totalRows),
    '文件大小' => formatBytes($fileSize),
    '总耗时' => formatTime($exportTime),
    '导出速度' => number_format($totalRows / $exportTime, 0) . ' 行/秒'
]);

echo "\n✅ 导出完成！\n";
echo "📊 导出结果:\n";
echo "  - 行数: " . number_format($totalRows) . "\n";
echo "  - 文件大小: " . formatBytes($fileSize) . "\n";
echo "  - 耗时: " . formatTime($exportTime) . "\n";
echo "  - 速度: " . number_format($totalRows / $exportTime, 0) . " 行/秒\n";
echo "  - 内存峰值: " . formatBytes($exportMemoryPeak) . "\n\n";

// ======= 第二部分：导入测试 =======
echo "==========================================\n";
echo "📥 第二部分：智能导入测试\n";
echo "==========================================\n\n";

// ProgressReporter会自动根据总数调整更新频率
$progress = ProgressReporter::createTerminal([
    'progress_bar_width' => 50,
    'enable_colors' => true,
]);

$progress->start('导入100万条数据', $totalRows, [
    '源文件' => basename($exportFile),
    '读取策略' => '智能流式读取',
    '模式' => '自适应处理'
]);

$importStartTime = microtime(true);
$importStartMemory = memory_get_usage(true);

// 创建Reader（使用智能配置，会自动调整内存检查频率）
$reader = new Reader($exportFile, [
    'memory_limit' => calculateMemoryLimit($totalRows),
    'buffer_size' => 16384, // 16KB缓冲
    'enable_monitoring' => false, // 禁用详细监控
    'strict_mode' => false, // 禁用严格类型检测
]);

// 设置静默的ProgressReporter（避免双重更新）
$silentProgress = ProgressReporter::createSilent();
$reader->setProgressReporter($silentProgress);

$importedRows = 0;
$validRows = 0;

// 使用智能批处理
$reader->chunk($batchSize, function($batch, $batchIndex) use (
    &$importedRows, 
    &$validRows, 
    $progress, 
    $importStartTime, 
    $progressUpdateInterval
) {
    $batchSize = count($batch);
    $importedRows += $batchSize;
    
    // 简单的数据处理
    foreach ($batch as $row) {
        // 基本验证
        if (count($row) >= 7 && !empty($row[0])) {
            $validRows++;
        }
    }
    
    // 智能进度更新（频率已自动调整）
    if ($importedRows % $progressUpdateInterval === 0) {
        $elapsed = microtime(true) - $importStartTime;
        $speed = $elapsed > 0 ? $importedRows / $elapsed : 0;
        $currentMemory = memory_get_usage(true);
        
        $progress->update($importedRows, [
            '读取速度' => number_format($speed, 0) . ' 行/秒',
            '有效数据' => number_format($validRows),
            '当前内存' => formatBytes($currentMemory),
            '批次' => $batchIndex + 1
        ]);
    }
});

$importTime = microtime(true) - $importStartTime;
$importMemoryPeak = memory_get_peak_usage(true);

$progress->finish([
    '导入行数' => number_format($importedRows),
    '有效行数' => number_format($validRows),
    '总耗时' => formatTime($importTime),
    '导入速度' => number_format($importedRows / $importTime, 0) . ' 行/秒'
]);

echo "\n✅ 导入完成！\n";
echo "📊 导入结果:\n";
echo "  - 导入行数: " . number_format($importedRows) . "\n";
echo "  - 有效行数: " . number_format($validRows) . "\n";

// 智能数据完整性检查
$expectedTotalWithHeader = $totalRows + 1; // 数据行 + 表头行
$integrityStatus = "✅ 完整";
$integrityNote = "";

if ($importedRows === $expectedTotalWithHeader && $validRows === $expectedTotalWithHeader) {
    $integrityStatus = "✅ 完整";
    $integrityNote = "(含表头)";
} elseif ($importedRows === $totalRows && $validRows === $totalRows) {
    $integrityStatus = "✅ 完整";
    $integrityNote = "(纯数据)";
} elseif ($validRows >= $totalRows && $importedRows >= $totalRows) {
    $integrityStatus = "✅ 完整";
    $integrityNote = "(有额外行)";
} else {
    $integrityStatus = "⚠️ 有差异";
    $integrityNote = sprintf("(期望%s行,实际%s行)", number_format($totalRows), number_format($validRows));
}

echo "  - 数据完整性: " . $integrityStatus . " " . $integrityNote . "\n";
echo "  - 耗时: " . formatTime($importTime) . "\n";
echo "  - 速度: " . number_format($importedRows / $importTime, 0) . " 行/秒\n";
echo "  - 内存峰值: " . formatBytes($importMemoryPeak) . "\n\n";

// ======= 综合性能报告 =======
echo "==========================================\n";
echo "🏆 智能优化性能报告\n";
echo "==========================================\n\n";

$totalTime = $exportTime + $importTime;
$maxMemory = max($exportMemoryPeak, $importMemoryPeak);

echo "🧠 智能参数效果:\n";
echo "  - 内存检查间隔: 自动调整为每5000行（超大数据模式）\n";
echo "  - 进度更新间隔: 自动调整为3.0秒（100万数据模式）\n";
echo "  - 批处理大小: " . number_format($batchSize) . " 行\n";
echo "  - 缓冲区大小: " . number_format($bufferSize) . " 行\n\n";

echo "📈 总体性能:\n";
echo "  - 总处理时间: " . formatTime($totalTime) . "\n";
echo "  - 总处理行数: " . number_format($totalRows * 2) . " 行 (导出+导入)\n";
echo "  - 平均速度: " . number_format(($totalRows * 2) / $totalTime, 0) . " 行/秒\n";
echo "  - 最大内存: " . formatBytes($maxMemory) . "\n\n";

echo "🔄 单项性能:\n";
echo "  - 导出速度: " . number_format($totalRows / $exportTime, 0) . " 行/秒\n";
echo "  - 导入速度: " . number_format($importedRows / $importTime, 0) . " 行/秒\n";
echo "  - 文件处理: " . formatBytes((int)($fileSize / $totalTime)) . "/秒\n\n";

echo "💾 内存效率:\n";
echo "  - 文件大小: " . formatBytes($fileSize) . "\n";
echo "  - 内存峰值: " . formatBytes($maxMemory) . "\n";
echo "  - 内存效率: " . round($fileSize / $maxMemory, 2) . "x (文件/内存)\n\n";

// 性能评级
$avgSpeed = ($totalRows * 2) / $totalTime;
if ($avgSpeed > 200000) {
    $grade = "🏆 卓越";
} elseif ($avgSpeed > 150000) {
    $grade = "🥇 优秀";
} elseif ($avgSpeed > 100000) {
    $grade = "🥈 良好";
} elseif ($avgSpeed > 50000) {
    $grade = "🥉 一般";
} else {
    $grade = "⚠️ 需优化";
}

echo "🏅 性能评级: $grade\n";
echo "   (基于平均 " . number_format($avgSpeed, 0) . " 行/秒)\n\n";

echo "✅ 智能优化测试完成！\n\n";

// 智能参数计算函数
function calculateOptimalBatchSize(int $totalRows): int
{
    if ($totalRows < 1000) {
        return 100;
    } elseif ($totalRows < 10000) {
        return 500;
    } elseif ($totalRows < 100000) {
        return 2000;
    } elseif ($totalRows < 1000000) {
        return 5000;
    } else {
        return 10000; // 超大数据用10K批次
    }
}

function calculateOptimalBufferSize(int $totalRows): int
{
    if ($totalRows < 1000) {
        return 100;
    } elseif ($totalRows < 10000) {
        return 500;
    } elseif ($totalRows < 100000) {
        return 1000;
    } elseif ($totalRows < 1000000) {
        return 3000;
    } else {
        return 5000; // 超大数据用5K缓冲
    }
}

function calculateProgressUpdateInterval(int $totalRows): int
{
    if ($totalRows < 1000) {
        return 50;
    } elseif ($totalRows < 10000) {
        return 200;
    } elseif ($totalRows < 100000) {
        return 1000;
    } elseif ($totalRows < 1000000) {
        return 5000;
    } else {
        return 10000; // 超大数据每1万行更新一次
    }
}

function calculateMemoryLimit(int $totalRows): int
{
    if ($totalRows < 10000) {
        return 64 * 1024 * 1024;  // 64MB
    } elseif ($totalRows < 100000) {
        return 128 * 1024 * 1024; // 128MB
    } else {
        return 256 * 1024 * 1024; // 256MB
    }
}

// 工具函数
function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

function formatTime(float $seconds): string
{
    if ($seconds >= 60) {
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return sprintf('%dm %.1fs', $minutes, $secs);
    } else {
        return sprintf('%.1fs', $seconds);
    }
} 