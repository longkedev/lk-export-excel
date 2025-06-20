<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use LkExcel\LkExportExcel\Api\Converter;

/**
 * Converter API 演示
 * 
 * 展示文件格式转换功能，包括：
 * - XLSX转CSV
 * - XLSX转JSON
 * - 多工作表转换
 * - 大文件转换
 * - 质量检测
 * - 转换日志监控
 */

echo "========================================\n";
echo "LkExportExcel Converter 演示\n";
echo "========================================\n\n";

// 测试文件路径
$testFilesDir = __DIR__ . '/../tests/fixtures';
$outputDir = __DIR__ . '/../output';

// 确保输出目录存在
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

/**
 * 演示1：基础XLSX转CSV
 */
function demo1_basic_xlsx_to_csv(string $testFilesDir, string $outputDir): void
{
    echo "📊 演示1：基础XLSX转CSV转换\n";
    echo "--------------------\n";
    
    try {
        $sourceFile = $testFilesDir . '/small_simple.xlsx';
        $targetFile = $outputDir . '/basic_output.csv';
        
        echo "源文件: $sourceFile\n";
        echo "目标文件: $targetFile\n";
        
        $startTime = microtime(true);
        $converter = new Converter($sourceFile, $targetFile);
        $result = $converter->convert();
        $endTime = microtime(true);
        
        if ($result) {
            echo "✅ 转换成功！\n";
            echo "📁 文件大小: " . formatBytes(filesize($targetFile)) . "\n";
            echo "⏱️  转换时间: " . round($endTime - $startTime, 3) . "秒\n";
            
            // 显示转换日志
            $log = $converter->getConversionLog();
            echo "📋 转换步骤: " . count($log) . "个\n";
            
            // 预览CSV内容
            $csvContent = file_get_contents($targetFile);
            $lines = explode("\n", $csvContent);
            echo "📄 内容预览（前3行）:\n";
            for ($i = 0; $i < min(3, count($lines)); $i++) {
                echo "   " . $lines[$i] . "\n";
            }
        } else {
            echo "❌ 转换失败\n";
        }
        
    } catch (Exception $e) {
        echo "❌ 错误: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

/**
 * 演示2：XLSX转JSON
 */
function demo2_xlsx_to_json(string $testFilesDir, string $outputDir): void
{
    echo "🔄 演示2：XLSX转JSON转换\n";
    echo "--------------------\n";
    
    try {
        $sourceFile = $testFilesDir . '/small_simple.xlsx';
        $targetFile = $outputDir . '/basic_output.json';
        
        echo "源文件: $sourceFile\n";
        echo "目标文件: $targetFile\n";
        
        $startTime = microtime(true);
        $converter = new Converter($sourceFile, $targetFile);
        $result = $converter->convert();
        $endTime = microtime(true);
        
        if ($result) {
            echo "✅ 转换成功！\n";
            echo "📁 文件大小: " . formatBytes(filesize($targetFile)) . "\n";
            echo "⏱️  转换时间: " . round($endTime - $startTime, 3) . "秒\n";
            
            // 验证JSON结构
            $jsonContent = file_get_contents($targetFile);
            $data = json_decode($jsonContent, true);
            
            if ($data !== null) {
                echo "📊 JSON数据结构验证通过\n";
                echo "📄 数据预览:\n";
                echo "   " . substr($jsonContent, 0, 200) . "...\n";
            } else {
                echo "⚠️  JSON格式验证失败\n";
            }
        } else {
            echo "❌ 转换失败\n";
        }
        
    } catch (Exception $e) {
        echo "❌ 错误: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

/**
 * 演示3：多工作表转换
 */
function demo3_multi_sheet_conversion(string $testFilesDir, string $outputDir): void
{
    echo "📑 演示3：多工作表转换\n";
    echo "--------------------\n";
    
    try {
        $sourceFile = $testFilesDir . '/multi_sheet.xlsx';
        $targetFile = $outputDir . '/multi_sheet_output.xlsx';
        
        echo "源文件: $sourceFile\n";
        echo "目标文件: $targetFile\n";
        
        $config = [
            'preserve_formulas' => true,
            'preserve_formatting' => false,
            'quality_check' => true
        ];
        
        $startTime = microtime(true);
        $converter = new Converter($sourceFile, $targetFile, $config);
        $result = $converter->convert();
        $endTime = microtime(true);
        
        if ($result) {
            echo "✅ 转换成功！\n";
            echo "📁 文件大小: " . formatBytes(filesize($targetFile)) . "\n";
            echo "⏱️  转换时间: " . round($endTime - $startTime, 3) . "秒\n";
            
            // 显示详细转换日志
            $log = $converter->getConversionLog();
            echo "📋 详细转换日志:\n";
            foreach ($log as $entry) {
                echo "   [{$entry['timestamp']}] {$entry['message']}\n";
                if (!empty($entry['data'])) {
                    foreach ($entry['data'] as $key => $value) {
                        if (is_scalar($value)) {
                            echo "     - $key: $value\n";
                        }
                    }
                }
            }
        } else {
            echo "❌ 转换失败\n";
        }
        
    } catch (Exception $e) {
        echo "❌ 错误: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

/**
 * 演示4：大文件转换性能测试
 */
function demo4_large_file_conversion(string $testFilesDir, string $outputDir): void
{
    echo "🚀 演示4：大文件转换性能测试\n";
    echo "--------------------\n";
    
    try {
        $sourceFile = $testFilesDir . '/large_data.xlsx';
        $targetFile = $outputDir . '/large_output.csv';
        
        echo "源文件: $sourceFile\n";
        echo "目标文件: $targetFile\n";
        echo "源文件大小: " . formatBytes(filesize($sourceFile)) . "\n";
        
        $config = [
            'chunk_size' => 1000,
            'memory_limit' => 256 * 1024 * 1024, // 256MB
            'quality_check' => true
        ];
        
        echo "配置: 块大小={$config['chunk_size']}, 内存限制=" . formatBytes($config['memory_limit']) . "\n";
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $converter = new Converter($sourceFile, $targetFile, $config);
        $result = $converter->convert();
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        if ($result) {
            echo "✅ 转换成功！\n";
            echo "📁 目标文件大小: " . formatBytes(filesize($targetFile)) . "\n";
            echo "⏱️  转换时间: " . round($endTime - $startTime, 3) . "秒\n";
            echo "💾 内存使用:\n";
            echo "   - 起始: " . formatBytes($startMemory) . "\n";
            echo "   - 结束: " . formatBytes($endMemory) . "\n";
            echo "   - 峰值: " . formatBytes($peakMemory) . "\n";
            echo "   - 增量: " . formatBytes($endMemory - $startMemory) . "\n";
            
            // 性能分析
            $fileSize = filesize($sourceFile);
            $throughput = $fileSize / ($endTime - $startTime);
            echo "📈 性能指标:\n";
            echo "   - 处理速度: " . formatBytes($throughput) . "/秒\n";
            echo "   - 内存效率: " . round($fileSize / $peakMemory, 2) . "x (文件大小/峰值内存)\n";
        } else {
            echo "❌ 转换失败\n";
        }
        
    } catch (Exception $e) {
        echo "❌ 错误: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

/**
 * 演示5：质量检测和错误处理
 */
function demo5_quality_and_error_handling(string $testFilesDir, string $outputDir): void
{
    echo "🔍 演示5：质量检测和错误处理\n";
    echo "--------------------\n";
    
    // 正常文件质量检测
    echo "1. 正常文件质量检测:\n";
    try {
        $sourceFile = $testFilesDir . '/small_simple.xlsx';
        $targetFile = $outputDir . '/quality_test.csv';
        
        $config = [
            'quality_check' => true,
            'chunk_size' => 100
        ];
        
        $converter = new Converter($sourceFile, $targetFile, $config);
        $result = $converter->convert();
        
        if ($result) {
            echo "   ✅ 转换成功，质量检测通过\n";
            
            $log = $converter->getConversionLog();
            foreach ($log as $entry) {
                if (strpos($entry['message'], '质量检测') !== false) {
                    echo "   📊 " . $entry['message'] . "\n";
                    if (isset($entry['data']['data_integrity'])) {
                        echo "   📈 数据完整性: " . ($entry['data']['data_integrity'] ? '通过' : '失败') . "\n";
                    }
                    if (isset($entry['data']['source_rows']) && isset($entry['data']['target_rows'])) {
                        echo "   📋 行数对比: {$entry['data']['source_rows']} → {$entry['data']['target_rows']}\n";
                    }
                }
            }
        }
    } catch (Exception $e) {
        echo "   ❌ 质量检测失败: " . $e->getMessage() . "\n";
    }
    
    // 错误处理测试
    echo "\n2. 错误处理测试:\n";
    
    // 测试不存在的文件
    try {
        $converter = new Converter('/nonexistent/file.xlsx', $outputDir . '/error_test.csv');
        $converter->convert();
        echo "   ❌ 应该抛出异常但没有\n";
    } catch (Exception $e) {
        echo "   ✅ 正确捕获文件不存在错误: " . $e->getMessage() . "\n";
    }
    
    // 测试不支持的格式
    try {
        $converter = new Converter($testFilesDir . '/small_simple.xlsx', $outputDir . '/test.unsupported');
        $result = $converter->convert();
        echo "   ⚠️  不支持的格式，但转换为默认XLSX格式\n";
    } catch (Exception $e) {
        echo "   ✅ 正确处理不支持格式: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

/**
 * 演示6：支持的格式列表
 */
function demo6_supported_formats(): void
{
    echo "📝 演示6：支持的格式列表\n";
    echo "--------------------\n";
    
    $formats = Converter::getSupportedFormats();
    
    echo "支持的源格式:\n";
    foreach ($formats['source'] as $format) {
        echo "   - $format\n";
    }
    
    echo "\n支持的目标格式:\n";
    foreach ($formats['target'] as $format) {
        echo "   - $format\n";
    }
    
    echo "\n支持的转换组合:\n";
    foreach ($formats['source'] as $source) {
        foreach ($formats['target'] as $target) {
            echo "   - $source → $target\n";
        }
    }
    
    echo "\n";
}

/**
 * 格式化字节数
 */
function formatBytes(float|int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

// 执行所有演示
try {
    demo1_basic_xlsx_to_csv($testFilesDir, $outputDir);
    demo2_xlsx_to_json($testFilesDir, $outputDir);
    demo3_multi_sheet_conversion($testFilesDir, $outputDir);
    demo4_large_file_conversion($testFilesDir, $outputDir);
    demo5_quality_and_error_handling($testFilesDir, $outputDir);
    demo6_supported_formats();
    
    echo "========================================\n";
    echo "✅ 所有演示完成！\n";
    echo "📁 输出文件位置: $outputDir\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    echo "❌ 演示执行失败: " . $e->getMessage() . "\n";
    echo "📚 请检查依赖是否正确安装\n";
} 