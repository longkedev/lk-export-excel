<?php

declare(strict_types=1);

/**
 * Phase 4: 生态集成 - 简化演示
 * 
 * 专注展示lkExportExcel的核心API功能
 */

require_once __DIR__ . '/../vendor/autoload.php';

use LkExcel\LkExportExcel\LkExportExcel;

echo "=== Phase 4: lkExportExcel API演示 ===\n\n";

try {
    // === 演示1: 写入功能 ===
    echo "=== 演示1: 基础写入功能 ===\n";
    
    $writer = LkExportExcel::write();
    
    // 写入表头
    $writer->writeHeaders(['员工ID', '姓名', '部门', '薪资'], [
        'font' => ['bold' => true],
        'fill' => ['color' => '#E6E6FA']
    ]);
    
    // 写入数据
    $employeeData = [
        [1001, '张三', '技术部', 8500],
        [1002, '李四', '产品部', 9200],
        [1003, '王五', '设计部', 7800],
        [1004, '赵六', '运营部', 6800],
        [1005, '钱七', '市场部', 7200],
    ];
    
    $writer->writeRows($employeeData);
    
    // 设置样式
    $writer
        ->setColumnWidth('B', 12.0)  // 姓名列宽度
        ->setFont('A1:D1', ['size' => 12, 'bold' => true])
        ->setBorder('A1:D6', ['style' => 'thin']);
    
    $outputFile = __DIR__ . '/demo_output.json';
    $saved = $writer->save($outputFile);
    echo "写入文件: " . ($saved ? '成功' : '失败') . "\n";
    
    if ($saved && file_exists($outputFile)) {
        echo "输出文件大小: " . filesize($outputFile) . " 字节\n";
    }

    // === 演示2: 多工作表功能 ===
    echo "\n=== 演示2: 多工作表功能 ===\n";
    
    $writer2 = LkExportExcel::write();
    
    // 创建多个工作表
    $writer2->createSheet('员工信息')
            ->createSheet('部门统计')
            ->sheet('员工信息');
    
    // 第一个工作表 - 员工信息
    $writer2->writeHeaders(['姓名', '年龄', '部门'])
            ->writeRows([
                ['张三', 28, '技术部'],
                ['李四', 32, '产品部'],
                ['王五', 25, '设计部'],
            ]);
    
    // 切换到第二个工作表 - 部门统计
    $writer2->sheet('部门统计')
            ->writeHeaders(['部门', '人数', '平均年龄'])
            ->writeRows([
                ['技术部', 15, 29],
                ['产品部', 8, 31],
                ['设计部', 6, 26],
            ]);
    
    echo "工作表数量: " . $writer2->getSheetCount() . "\n";
    echo "当前行号: " . $writer2->getCurrentRow() . "\n";

    // === 演示3: 流式写入 ===
    echo "\n=== 演示3: 流式写入 ===\n";
    
    $writer3 = LkExportExcel::write();
    
    // 使用生成器进行流式写入
    $dataGenerator = function() {
        for ($i = 1; $i <= 1000; $i++) {
            yield ["用户{$i}", rand(18, 65), "部门" . ($i % 5 + 1), rand(5000, 15000)];
        }
    };
    
    $writer3->writeHeaders(['用户名', '年龄', '部门', '薪资'])
            ->streamWrite($dataGenerator());
    
    echo "流式写入 1000 条记录完成\n";

    // === 演示4: 样式和格式 ===
    echo "\n=== 演示4: 样式和格式功能 ===\n";
    
    $writer4 = LkExportExcel::write();
    
    // 创建带样式的表格
    $writer4->writeHeaders(['产品名称', '价格', '库存', '状态']);
    
    // 写入数据并设置不同样式
    $products = [
        ['iPhone 15', '¥6,999.00', 50, '有货'],
        ['MacBook Pro', '¥12,999.00', 25, '有货'],
        ['iPad Air', '¥4,299.00', 0, '缺货'],
        ['Apple Watch', '¥2,499.00', 100, '有货'],
    ];
    
    foreach ($products as $index => $product) {
        $row = $index + 2; // 从第2行开始（第1行是表头）
        $writer4->writeRow($product);
        
        // 根据库存状态设置不同颜色
        if ($product[2] == 0) { // 缺货
            $writer4->setFill("A{$row}:D{$row}", ['color' => '#FFE6E6']);
        } elseif ($product[2] < 30) { // 库存不足
            $writer4->setFill("A{$row}:D{$row}", ['color' => '#FFF2CC']);
        }
    }
    
    // 设置表头样式
    $writer4->setFont('A1:D1', ['bold' => true, 'color' => '#FFFFFF'])
            ->setFill('A1:D1', ['color' => '#4F81BD'])
            ->setBorder('A1:D5', ['style' => 'medium']);
    
    echo "样式设置完成\n";

    // === 演示5: 性能统计 ===
    echo "\n=== 演示5: 性能统计 ===\n";
    
    $stats = $writer->getStats();
    echo "第一个写入器统计:\n";
    echo "- 写入行数: {$stats['rows_written']}\n";
    echo "- 写入单元格: {$stats['cells_written']}\n";
    echo "- 创建工作表: {$stats['sheets_created']}\n";
    echo "- 内存使用: " . formatBytes($stats['memory_used']) . "\n";
    
    $stats3 = $writer3->getStats();
    echo "\n流式写入器统计:\n";
    echo "- 写入行数: {$stats3['rows_written']}\n";
    echo "- 写入单元格: {$stats3['cells_written']}\n";
    echo "- 内存使用: " . formatBytes($stats3['memory_used']) . "\n";

    // === 演示6: 高级功能 ===
    echo "\n=== 演示6: 高级功能 ===\n";
    
    $writer5 = LkExportExcel::write();
    
    // 设置单元格和公式
    $writer5->setCell('A1', '商品')
            ->setCell('B1', '单价')
            ->setCell('C1', '数量')
            ->setCell('D1', '总计')
            ->setCell('A2', '苹果')
            ->setCell('B2', 5.50)
            ->setCell('C2', 10)
            ->setFormula('D2', '=B2*C2')  // 设置公式
            ->setCell('A3', '香蕉')
            ->setCell('B3', 3.20)
            ->setCell('C3', 15)
            ->setFormula('D3', '=B3*C3');
    
    // 合并单元格
    $writer5->mergeCells('A5:D5')
            ->setCell('A5', '数据汇总表');
    
    // 设置列宽和行高
    $writer5->setColumnWidth('A', 10.0)
            ->setColumnWidth('D', 12.0)
            ->setRowHeight(1, 25.0)
            ->setRowHeight(5, 30.0);
    
    echo "高级功能设置完成\n";

    // === 演示7: 批量数据处理 ===
    echo "\n=== 演示7: 批量数据处理 ===\n";
    
    $writer6 = LkExportExcel::write();
    
    // 模拟从不同数据源写入
    $dataSources = [
        'users' => [
            ['用户1', 'user1@example.com', '2023-01-15'],
            ['用户2', 'user2@example.com', '2023-02-20'],
            ['用户3', 'user3@example.com', '2023-03-10'],
        ],
        'orders' => [
            ['订单1', '¥299.00', '已完成'],
            ['订单2', '¥156.50', '处理中'],
            ['订单3', '¥89.90', '已取消'],
        ]
    ];
    
    $batchCount = 0;
    foreach ($dataSources as $sourceName => $data) {
        $batchCount++;
        echo "  处理数据源 '{$sourceName}': " . count($data) . " 条记录\n";
        
        // 可以根据不同数据源应用不同的处理逻辑
        $mapper = function($item, $index) use ($sourceName) {
            // 为每条记录添加来源标识
            return array_merge($item, ["来源: {$sourceName}"]);
        };
        
        $writer6->fromDataSource($data, $mapper);
    }
    
    echo "批量处理完成，共处理 {$batchCount} 个数据源\n";

    echo "\n=== 演示完成 ===\n";
    echo "Phase 4 核心API功能演示完毕！\n";
    echo "\n主要特性展示:\n";
    echo "✓ 链式调用API - 流畅的编程体验\n";
    echo "✓ 多工作表支持 - 复杂文档结构\n";
    echo "✓ 流式写入 - 处理大量数据\n";
    echo "✓ 丰富样式系统 - 美观的格式设置\n";
    echo "✓ 性能监控 - 实时统计信息\n";
    echo "✓ 高级功能 - 公式、合并等\n";
    echo "✓ 批量处理 - 多数据源集成\n";

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
} finally {
    // 清理演示文件
    $filesToClean = [
        __DIR__ . '/demo_output.json',
    ];
    
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