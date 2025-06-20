<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use LkExcel\LkExportExcel\Performance\ProgressReporter;

/**
 * 简单进度条演示
 * 
 * 展示进度条的核心功能：
 * 1. 漂亮的终端显示
 * 2. 实时统计信息
 * 3. 内存监控
 * 4. 单屏刷新
 */

echo "🚀 lkExportExcel 进度条系统\n";
echo "===========================\n\n";

// 模拟数据处理任务
function simulateDataProcessing(string $taskName, int $totalItems, float $itemProcessTime = 0.001): void
{
    echo "📊 开始任务：{$taskName}\n\n";
    
    // 创建终端进度条
    $progress = ProgressReporter::createTerminal([
        'update_interval' => 0.2, // 200毫秒更新一次
        'progress_bar_width' => 50,
        'enable_colors' => true,
        'memory_warning_threshold' => 0.7,
    ]);
    
    // 开始任务
    $progress->start($taskName, $totalItems, [
        '预计耗时' => formatTime($totalItems * $itemProcessTime),
        '处理模式' => '流式处理',
        '内存模式' => '低内存占用'
    ]);
    
    // 模拟处理过程
    for ($i = 1; $i <= $totalItems; $i++) {
        // 模拟处理时间
        usleep((int)($itemProcessTime * 1000000));
        
        // 模拟内存使用（故意增加一些内存占用）
        if ($i % 1000 === 0) {
            $dummyData = array_fill(0, 100, str_repeat('x', 1000));
            unset($dummyData); // 立即释放
        }
        
        // 更新进度
        $progress->update($i, [
            '当前项目' => "Item_{$i}",
            '批次信息' => '批次 ' . ceil($i / 1000),
            '处理状态' => $i > $totalItems * 0.8 ? '收尾阶段' : '处理中'
        ]);
    }
    
    // 完成任务
    $progress->finish([
        '完成状态' => '✅ 成功',
        '总处理量' => number_format($totalItems),
        '质量检查' => '100% 通过'
    ]);
    
    echo "\n任务完成！按Enter继续...\n";
    readline();
}

// 模拟API状态获取
function demonstrateApiMode(): void
{
    echo "\n🌐 API模式演示\n";
    echo "==============\n";
    
    $apiProgress = ProgressReporter::createApi([
        'update_interval' => 0.1,
    ]);
    
    $totalTasks = 20000;
    $apiProgress->start('API后台处理', $totalTasks);
    
    echo "模拟Web应用调用API获取进度状态：\n\n";
    
    for ($i = 1; $i <= $totalTasks; $i++) {
        usleep(100); // 0.1毫秒处理时间
        
        if ($i % 2000 === 0) {
            $apiProgress->update($i, [
                'batch_id' => ceil($i / 2000),
                'queue_size' => max(0, $totalTasks - $i),
                'status' => 'processing'
            ]);
            
            // 获取状态（模拟API调用）
            $state = $apiProgress->getState();
            
            echo sprintf("[API] 进度: %5.1f%% | 速度: %8.0f/s | 内存: %6s | ETA: %s\n",
                $state['percentage'],
                $state['speed'],
                formatBytes($state['memory_usage']),
                formatTime($state['eta'])
            );
        }
    }
    
    $apiProgress->finish(['result' => 'success']);
    $finalState = $apiProgress->getState();
    
    echo "\n最终API状态：\n";
    echo json_encode([
        'status' => $finalState['status'],
        'percentage' => $finalState['percentage'],
        'total_time' => round($finalState['elapsed_time'], 2),
        'average_speed' => round($finalState['speed'], 0),
        'memory_peak' => formatBytes($finalState['memory_peak'])
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

try {
    // 演示1：小任务
    simulateDataProcessing('处理10K数据项', 10000, 0.0002);
    
    // 演示2：中等任务
    simulateDataProcessing('处理50K数据项', 50000, 0.0001);
    
    // 演示3：大任务
    simulateDataProcessing('处理100K数据项', 100000, 0.00005);
    
    // 演示4：API模式
    demonstrateApiMode();
    
    // 总结
    echo "\n🎯 进度条系统特性\n";
    echo "==================\n";
    echo "✅ 流畅的终端进度条\n";
    echo "✅ 彩色输出和图标\n";
    echo "✅ 实时速度计算\n";
    echo "✅ 智能ETA预估\n";
    echo "✅ 内存使用监控\n";
    echo "✅ 单屏固定显示\n";
    echo "✅ API状态接口\n";
    echo "✅ 可配置更新频率\n";
    echo "✅ 详细状态信息\n\n";
    
    echo "🔧 使用示例：\n";
    echo "```php\n";
    echo "\$progress = ProgressReporter::createTerminal();\n";
    echo "\$progress->start('任务名称', \$totalItems);\n";
    echo "for (\$i = 1; \$i <= \$totalItems; \$i++) {\n";
    echo "    // 处理数据...\n";
    echo "    \$progress->update(\$i);\n";
    echo "}\n";
    echo "\$progress->finish();\n";
    echo "```\n\n";
    
} catch (Exception $e) {
    echo "❌ 演示失败: " . $e->getMessage() . "\n";
    echo "📍 位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

/**
 * 格式化时间
 */
function formatTime(float $seconds): string
{
    if ($seconds < 1) {
        return round($seconds * 1000) . 'ms';
    } elseif ($seconds < 60) {
        return round($seconds, 1) . 's';
    } elseif ($seconds < 3600) {
        return floor($seconds / 60) . 'm ' . round($seconds % 60) . 's';
    } else {
        return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
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
    return round($bytes, 1) . $units[$pow];
}

echo "🏁 进度条演示完成！\n"; 