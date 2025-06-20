<?php

declare(strict_types=1);

/**
 * Phase 4: 生态集成 - API完整演示
 * 
 * 展示lkExportExcel完整的API功能
 * 集成了所有Phase 1-3的优化组件
 */

require_once __DIR__ . '/../vendor/autoload.php';

use LkExcel\LkExportExcel\LkExportExcel;
use LkExcel\LkExportExcel\Api\Reader;
use LkExcel\LkExportExcel\Api\Writer;

echo "=== Phase 4: lkExportExcel 完整API演示 ===\n\n";

// 创建示例数据
$demoData = [
    ['姓名', '年龄', '职位', '薪资', '入职日期'],
    ['张三', 28, '开发工程师', '¥8,500.00', '2023-01-15'],
    ['李四', 32, '产品经理', '¥12,000.00', '2022-08-20'],
    ['王五', 25, '设计师', '¥7,200.00', '2023-03-10'],
    ['赵六', 35, '技术总监', '¥18,000.00', '2021-05-01'],
    ['钱七', 29, '测试工程师', '¥6,800.00', '2022-12-05'],
];

$csvFile = __DIR__ . '/demo_data.csv';
$jsonFile = __DIR__ . '/demo_output.json';

// 创建CSV文件用于演示
$handle = fopen($csvFile, 'w');
foreach ($demoData as $row) {
    fputcsv($handle, $row);
}
fclose($handle);

echo "1. 创建演示数据文件: {$csvFile}\n";

try {
    // === 演示1: 基础读取功能 ===
    echo "\n=== 演示1: 基础读取功能 ===\n";
    
    $reader = LkExportExcel::read($csvFile);
    
    // 获取文件信息
    $info = $reader->info();
    echo "文件信息:\n";
    echo "- 路径: {$info['path']}\n";
    echo "- 大小: {$info['size_formatted']}\n";
    echo "- 格式: {$info['format']}\n";
    echo "- 置信度: {$info['confidence']}\n";
    echo "- 可读: " . ($info['readable'] ? '是' : '否') . "\n";

    // === 演示2: 链式调用API ===
    echo "\n=== 演示2: 链式调用API ===\n";
    
    // 使用链式调用读取数据
    $data = $reader
        ->headers(true)  // 包含表头
        ->limit(3, 1)    // 跳过表头，读取3行
        ->filter(function($row, $index) {
            // 过滤年龄大于26的记录
            return isset($row[1]) && (int)$row[1] > 26;
        })
        ->transform(function($row, $index) {
            // 转换数据格式
            if (isset($row[3])) {
                // 清理薪资格式
                $row[3] = str_replace(['¥', ','], '', $row[3]);
            }
            return $row;
        })
        ->toArray();
        
    echo "过滤和转换后的数据:\n";
    foreach ($data as $index => $row) {
        echo "  行{$index}: " . implode(' | ', $row) . "\n";
    }

    // === 演示3: 高级读取功能 ===
    echo "\n=== 演示3: 高级读取功能 ===\n";
    
    // 重置reader
    $reader->reset();
    
    // 获取单元格和列数据
    echo "单元格A1内容: " . ($reader->cell('A1') ?? 'null') . "\n";
    echo "第一行数据: " . implode(' | ', $reader->first() ?? []) . "\n";
    
    // 获取特定列数据
    $nameColumn = $reader->reset()->column(0); // 第一列（姓名）
    echo "姓名列数据: " . implode(', ', array_slice($nameColumn, 1)) . "\n";
    
    // 分块处理
    echo "\n分块处理数据:\n";
    $reader->reset()->chunk(2, function($batch, $batchIndex) {
        echo "  批次{$batchIndex}: " . count($batch) . "行数据\n";
    });

    // === 演示4: 流式处理 ===
    echo "\n=== 演示4: 流式处理 ===\n";
    
    echo "流式读取数据:\n";
    $rowCount = 0;
    foreach ($reader->reset()->stream() as $index => $row) {
        if ($rowCount++ < 3) { // 只显示前3行
            echo "  流{$index}: " . implode(' | ', $row) . "\n";
        }
    }

    // === 演示5: 写入功能 ===
    echo "\n=== 演示5: 写入功能 ===\n";
    
    $writer = LkExportExcel::write();
    
    // 写入表头（带样式）
    $writer->writeHeaders(['员工ID', '姓名', '部门', '评分'], [
        'font' => ['bold' => true],
        'fill' => ['color' => '#E6E6FA']
    ]);
    
    // 写入数据
    $employeeData = [
        [1001, '张三', '技术部', 95],
        [1002, '李四', '产品部', 88],
        [1003, '王五', '设计部', 92],
    ];
    
    foreach ($employeeData as $row) {
        $writer->writeRow($row);
    }
    
    // 设置样式
    $writer->setColumnWidth('B', 15.0)  // 姓名列宽度
           ->setFont('A1:D1', ['size' => 12, 'bold' => true])
           ->setBorder('A1:D4', ['style' => 'thin']);
    
    // 保存文件
    $saved = $writer->save($jsonFile);
    echo "写入文件: " . ($saved ? '成功' : '失败') . "\n";
    
    if ($saved && file_exists($jsonFile)) {
        echo "输出文件大小: " . filesize($jsonFile) . " 字节\n";
    }

    // === 演示6: 高级写入功能 ===
    echo "\n=== 演示6: 高级写入功能 ===\n";
    
    $writer2 = LkExportExcel::write();
    
    // 创建多个工作表
    $writer2->createSheet('员工信息')
            ->createSheet('部门统计')
            ->sheet('员工信息');
    
    // 从数据源写入
    $dataSource = [
        ['name' => '员工A', 'age' => 25, 'salary' => 8000],
        ['name' => '员工B', 'age' => 30, 'salary' => 9500],
        ['name' => '员工C', 'age' => 28, 'salary' => 8800],
    ];
    
    $writer2->fromDataSource($dataSource, function($item, $index) {
        return [$item['name'], $item['age'], $item['salary']];
    });
    
    // 流式写入生成器数据
    $dataGenerator = function() {
        for ($i = 1; $i <= 5; $i++) {
            yield ["流式数据{$i}", rand(20, 40), rand(5000, 15000)];
        }
    };
    
    $writer2->streamWrite($dataGenerator());
    
    echo "工作表数量: " . $writer2->getSheetCount() . "\n";
    echo "当前行号: " . $writer2->getCurrentRow() . "\n";

    // === 演示7: 性能统计 ===
    echo "\n=== 演示7: 性能统计 ===\n";
    
    $readerStats = $reader->getStats();
    echo "读取器统计:\n";
    echo "- 读取行数: {$readerStats['rows_read']}\n";
    echo "- 解析时间: " . number_format($readerStats['parse_time'] * 1000, 2) . "ms\n";
    echo "- 内存使用: " . formatBytes($readerStats['memory_used']) . "\n";
    
    $writerStats = $writer->getStats();
    echo "\n写入器统计:\n";
    echo "- 写入行数: {$writerStats['rows_written']}\n";
    echo "- 写入单元格: {$writerStats['cells_written']}\n";
    echo "- 创建工作表: {$writerStats['sheets_created']}\n";
    echo "- 写入时间: " . number_format($writerStats['write_time'] * 1000, 2) . "ms\n";

    // === 演示8: 转换功能 ===
    echo "\n=== 演示8: 转换功能 ===\n";
    
    // 读取CSV并转换为不同格式
    $convertData = LkExportExcel::read($csvFile)->toArray();
    echo "转换数据行数: " . count($convertData) . "\n";
    
    // 重新写入为JSON格式
    $jsonOutputFile = str_replace('.json', '_converted.json', $jsonFile);
    $convertWriter = LkExportExcel::write();
    $convertWriter->writeRows($convertData);
    $converted = $convertWriter->save($jsonOutputFile);
    echo "格式转换: " . ($converted ? '成功' : '失败') . "\n";

    // === 演示9: 批量操作 ===
    echo "\n=== 演示9: 批量操作 ===\n";
    
    // 批量处理多个数据块
    $batchData = array_chunk($demoData, 2);
    echo "批量数据块数: " . count($batchData) . "\n";
    
    foreach ($batchData as $index => $batch) {
        echo "  批次{$index}: " . count($batch) . "行\n";
    }

    // === 演示10: 内存和性能监控 ===
    echo "\n=== 演示10: 内存和性能监控 ===\n";
    
    // 获取详细的性能报告
    $performanceReport = $readerStats['monitor_report'] ?? [];
    if (!empty($performanceReport)) {
        echo "性能监控报告:\n";
        foreach ($performanceReport as $operation => $metrics) {
            if (is_array($metrics) && isset($metrics['call_count'])) {
                echo "- {$operation}: 调用{$metrics['call_count']}次, ";
                echo "总时间" . number_format(($metrics['total_duration'] ?? 0) * 1000, 2) . "ms\n";
            }
        }
    }
    
    // 内存统计
    $memoryStats = $readerStats['memory_stats'] ?? [];
    if (!empty($memoryStats)) {
        echo "\n内存统计:\n";
        echo "- 当前使用: " . formatBytes($memoryStats['current_usage'] ?? 0) . "\n";
        echo "- 峰值使用: " . formatBytes($memoryStats['peak_usage'] ?? 0) . "\n";
        echo "- 垃圾回收: " . ($memoryStats['gc_runs'] ?? 0) . "次\n";
    }

    echo "\n=== 演示完成 ===\n";
    echo "Phase 4 API功能全部演示完毕！\n";
    echo "lkExportExcel 现在提供了:\n";
    echo "✓ 统一的链式调用API\n";
    echo "✓ 高性能流式处理\n";
    echo "✓ 智能类型检测和转换\n";
    echo "✓ 完善的内存管理\n";
    echo "✓ 详细的性能监控\n";
    echo "✓ 丰富的样式和格式支持\n";
    echo "✓ 框架集成能力\n";

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
} finally {
    // 清理演示文件
    $filesToClean = [$csvFile, $jsonFile];
    $convertedFile = str_replace('.json', '_converted.json', $jsonFile);
    if (file_exists($convertedFile)) {
        $filesToClean[] = $convertedFile;
    }
    
    foreach ($filesToClean as $file) {
        if (file_exists($file)) {
            unlink($file);
            echo "清理文件: " . basename($file) . "\n";
        }
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