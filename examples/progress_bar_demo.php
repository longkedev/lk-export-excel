<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use LkExcel\LkExportExcel\Api\Writer;
use LkExcel\LkExportExcel\Api\Reader;
use LkExcel\LkExportExcel\Performance\ProgressReporter;

/**
 * 新进度条系统演示
 * 
 * 功能展示：
 * 1. 漂亮的终端进度条
 * 2. 实时内存监控
 * 3. 速度和ETA计算
 * 4. 单屏显示，不刷屏
 */

echo "🚀 lkExportExcel 进度条系统演示\n";
echo "================================\n\n";

try {
    // 确保输出目录存在
    $outputDir = __DIR__ . '/../output';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    $csvFile = $outputDir . '/progress_demo.csv';
    
    // ===============================
    // 演示1：导出10万条数据
    // ===============================
    
    echo "💾 演示1：导出10万条数据\n\n";
    
    // 创建进度报告器（终端模式）
    $progressReporter = ProgressReporter::createTerminal([
        'update_interval' => 0.5, // 0.5秒更新一次
        'progress_bar_width' => 60,
        'enable_colors' => true,
    ]);
    
    // 创建Writer
    $writer = new Writer([
        'memory_limit' => 128 * 1024 * 1024, // 128MB
        'buffer_size' => 5000,  // 5千行缓冲区
        'enable_monitoring' => true,
        'progress_reporter' => $progressReporter, // 注入进度报告器
    ]);
    
    $totalRows = 100000;
    
    // 开始任务
    $progressReporter->start('导出CSV数据', $totalRows, [
        '文件格式' => 'CSV',
        '缓冲大小' => '5,000行',
        '目标文件' => basename($csvFile)
    ]);
    
    // 开始写入
    $writer->startWrite($csvFile);
    
    // 写入表头
    $headers = ['id', 'name', 'email', 'phone', 'address', 'created_date'];
    $writer->writeHeaders($headers);
    
    // 生成并写入数据
    for ($i = 1; $i <= $totalRows; $i++) {
        $row = [
            $i,
            'User' . $i,
            'user' . $i . '@demo.com',
            '138' . sprintf('%08d', $i),
            '北京市朝阳区地址' . $i . '号',
            date('Y-m-d H:i:s', time() - rand(0, 365*24*3600))
        ];
        
        $writer->writeRow($row);
        
        // 更新进度（每1000行更新一次）
        if ($i % 1000 === 0) {
                         $progressReporter->update($i, [
                 '当前批次' => number_format($i),
                 '已处理' => number_format($i) . ' / ' . number_format($totalRows),
                 '处理状态' => '正在写入'
             ]);
        }
    }
    
    // 完成导出
    $writer->finishWrite();
    $exportStats = $writer->getStats();
    
         $progressReporter->finish([
         '文件大小' => formatBytes(filesize($csvFile)),
         '总行数' => number_format($totalRows),
         '状态' => '导出完成'
     ]);
    
    unset($writer);
    
    echo "\n按Enter继续导入演示...";
    readline();
    
    // ===============================
    // 演示2：导入10万条数据
    // ===============================
    
    echo "\n📥 演示2：导入10万条数据\n\n";
    
    // 重新创建进度报告器
    $progressReporter = ProgressReporter::createTerminal([
        'update_interval' => 0.3,
        'progress_bar_width' => 60,
        'enable_colors' => true,
    ]);
    
    // 开始导入任务
    $progressReporter->start('导入CSV数据', $totalRows, [
        '文件格式' => 'CSV',
        '处理模式' => '分段读取',
        '源文件' => basename($csvFile)
    ]);
    
    // 创建Reader
    $reader = new Reader($csvFile);
    
    $importedRows = 0;
    $chunkSize = 2000; // 每次处理2000行
    
    // 分块处理
    $reader->chunk($chunkSize, function($batch) use (&$importedRows, $progressReporter, $totalRows) {
        $importedRows += count($batch);
        
        // 模拟一些处理时间
        usleep(50000); // 50毫秒
        
        // 更新进度
        $progressReporter->update($importedRows, [
            '当前批次大小' => number_format(count($batch)),
            '已导入' => number_format($importedRows) . ' / ' . number_format($totalRows),
            '数据处理' => '验证+转换+存储'
        ]);
    });
    
    // 完成导入
    $progressReporter->finish([
        '总导入行数' => number_format($importedRows),
        '处理模式' => '分段处理',
        '数据验证' => '100% 通过'
    ]);
    
    unset($reader);
    
    echo "\n按Enter继续API模式演示...";
    readline();
    
    // ===============================
    // 演示3：API模式（静默处理）
    // ===============================
    
    echo "\n🌐 演示3：API模式（静默处理）\n\n";
    
    // 创建API模式的进度报告器
    $apiProgress = ProgressReporter::createApi();
    
    // 模拟API处理
    $apiProgress->start('API处理数据', 50000);
    
    for ($i = 1; $i <= 50000; $i++) {
        // 模拟处理
        if ($i % 100 === 0) {
            usleep(1000); // 1毫秒
        }
        
        // 更新进度（每5000行）
        if ($i % 5000 === 0) {
            $apiProgress->update($i, [
                'batch' => ceil($i / 5000),
                'memory_usage' => memory_get_usage(true),
                'processing_rate' => 'normal'
            ]);
            
            // 输出API状态（模拟Web端调用）
            $state = $apiProgress->getState();
            echo sprintf("API状态: %.1f%% 完成，速度: %.0f 行/秒，内存: %s\n",
                $state['percentage'],
                $state['speed'],
                formatBytes($state['memory_usage'])
            );
        }
    }
    
    $apiProgress->finish([
        'status' => 'success',
        'total_processed' => 50000
    ]);
    
    $finalState = $apiProgress->getState();
    echo "\nAPI最终状态: " . json_encode($finalState, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    // ===============================
    // 演示总结
    // ===============================
    
    echo "\n🎯 进度条系统特性总结\n";
    echo "============================\n";
    echo "✅ 漂亮的终端进度条显示\n";
    echo "✅ 实时内存使用监控\n";
    echo "✅ 智能速度和ETA计算\n";
    echo "✅ 单屏显示，避免刷屏\n";
    echo "✅ 内存警告只显示一次\n";
    echo "✅ 支持API模式获取状态\n";
    echo "✅ 彩色输出和状态图标\n";
    echo "✅ 可配置更新频率\n\n";
    
    echo "🔧 使用方式：\n";
    echo "- 终端模式：ProgressReporter::createTerminal()\n";
    echo "- API模式：ProgressReporter::createApi()\n";
    echo "- 静默模式：ProgressReporter::createSilent()\n\n";
    
    // 清理
    echo "🧹 清理演示文件...\n";
    if (file_exists($csvFile)) {
        unlink($csvFile);
        echo "✅ 已删除: " . basename($csvFile) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ 演示失败: " . $e->getMessage() . "\n";
    echo "📍 错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
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

echo "\n🏁 进度条系统演示完成！\n"; 