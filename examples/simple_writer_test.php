<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use LkExcel\LkExportExcel\Api\Writer;

/**
 * 简单的Writer功能测试
 * 测试新的流式Writer API基本功能
 */

echo "🚀 开始简单Writer测试...\n";

try {
    // 确保输出目录存在
    $outputDir = __DIR__ . '/../output';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    $outputFile = $outputDir . '/simple_test.csv';
    
    // 创建Writer实例
    echo "📝 创建Writer实例...\n";
    $writer = new Writer([
        'memory_limit' => 128 * 1024 * 1024, // 128MB
        'buffer_size' => 100,  // 小缓冲区测试
        'enable_monitoring' => true
    ]);
    
    // 开始写入
    echo "📂 开始写入文件: {$outputFile}\n";
    $writer->startWrite($outputFile);
    
    // 写入表头
    echo "📋 写入表头...\n";
    $headers = ['ID', '姓名', '邮箱', '电话', '地址'];
    $writer->writeHeaders($headers);
    
    // 写入测试数据
    echo "📊 写入测试数据...\n";
    $testData = [
        [1, '张三', 'zhangsan@test.com', '13800138000', '北京市朝阳区'],
        [2, '李四', 'lisi@test.com', '13800138001', '上海市浦东新区'],
        [3, '王五', 'wangwu@test.com', '13800138002', '广州市天河区'],
        [4, '赵六', 'zhaoliu@test.com', '13800138003', '深圳市南山区'],
        [5, '孙七', 'sunqi@test.com', '13800138004', '杭州市西湖区']
    ];
    
    foreach ($testData as $index => $row) {
        $writer->writeRow($row);
        echo "  ✅ 写入第 " . ($index + 1) . " 行\n";
    }
    
    // 完成写入
    echo "💾 完成写入...\n";
    $result = $writer->finishWrite();
    
    if ($result) {
        echo "✅ 文件写入成功！\n";
        
        // 获取统计信息
        $stats = $writer->getStats();
        echo "\n📈 Writer统计信息:\n";
        echo "  - 写入行数: " . $stats['rows_written'] . "\n";
        echo "  - 写入单元格数: " . $stats['cells_written'] . "\n";
        echo "  - 写入字节数: " . $stats['bytes_written'] . "\n";
        echo "  - 缓冲区刷新次数: " . $stats['buffer_flushes'] . "\n";
        echo "  - 内存峰值: " . round($stats['memory_peak'] / 1024 / 1024, 2) . " MB\n";
        
        // 检查文件
        if (file_exists($outputFile)) {
            $fileSize = filesize($outputFile);
            echo "  - 文件大小: " . round($fileSize / 1024, 2) . " KB\n";
            
            // 显示文件内容前几行
            echo "\n📄 文件内容预览:\n";
            $content = file_get_contents($outputFile);
            $lines = explode("\n", $content);
            foreach (array_slice($lines, 0, 3) as $lineNum => $line) {
                echo "  行" . ($lineNum + 1) . ": " . $line . "\n";
            }
        }
        
    } else {
        echo "❌ 文件写入失败！\n";
    }
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo "📍 错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    if ($e->getPrevious()) {
        echo "📄 原始错误: " . $e->getPrevious()->getMessage() . "\n";
    }
}

echo "\n🏁 测试完成\n"; 