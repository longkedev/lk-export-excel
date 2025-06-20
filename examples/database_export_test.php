<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use LkExcel\LkExportExcel\Api\Writer;

/**
 * 数据库导出测试 - 从SQLite导出10万条数据到Excel
 * 
 * 测试核心功能：
 * 1. 大数据量导出性能
 * 2. 内存使用控制
 * 3. 分段写入策略
 * 4. Writer类的内存管理能力
 */
class DatabaseExportTest
{
    private PDO $pdo;
    private string $dbPath;
    private string $outputDir;
    
    public function __construct()
    {
        $this->dbPath = __DIR__ . '/../tests/fixtures/test_database.sqlite';
        $this->outputDir = __DIR__ . '/../output';
        
        // 确保输出目录存在
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
        
        $this->connectDatabase();
    }
    
    /**
     * 连接数据库
     */
    private function connectDatabase(): void
    {
        try {
            $this->pdo = new PDO("sqlite:{$this->dbPath}", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            
            echo "✅ SQLite数据库连接成功: {$this->dbPath}\n";
        } catch (PDOException $e) {
            throw new RuntimeException("❌ 数据库连接失败: " . $e->getMessage());
        }
    }
    
    /**
     * 执行单次导出测试
     */
    private function performExport(int $limit, int $offset): array
    {
        $startTime = microtime(true);
        $outputFile = $this->outputDir . '/database_export_' . ($limit === 100000 ? '100k' : '1k') . '.csv';
        
        echo "🚀 开始导出 {$limit} 条数据到 {$outputFile}...\n";
        
        // 创建Writer并开始写入
        $writer = new Writer([
            'memory_limit' => 128 * 1024 * 1024, // 128MB
            'buffer_size' => 5000,  // 5000行缓冲区
            'enable_monitoring' => true
        ]);
        
        // 开始写入文件
        $writer->startWrite($outputFile);
        
        // 写入表头
        $headers = ['id', 'name', 'email', 'phone', 'address', 'city', 'department', 'position', 'salary', 'hire_date'];
        $writer->writeHeaders($headers);
        echo "✅ 表头写入完成\n";
        
        // 分批处理数据导出
        $batchSize = 5000;
        $processed = 0;
        $memoryStart = memory_get_usage(true);
        
        while ($processed < $limit) {
            $currentLimit = min($batchSize, $limit - $processed);
            $currentOffset = $offset + $processed;
            
            echo "📊 处理批次 " . (intval($processed / $batchSize) + 1) . 
                 "，行范围: {$currentOffset}-" . ($currentOffset + $currentLimit - 1) . "\n";
            
            // 获取当前批次数据
            $stmt = $this->pdo->prepare("
                SELECT id, name, email, phone, address, city, department, position, salary, hire_date 
                FROM performance_test_100k 
                ORDER BY id 
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $currentLimit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $currentOffset, PDO::PARAM_INT);
            $stmt->execute();
            
            $batchStartTime = microtime(true);
            $batchMemoryStart = memory_get_usage(true);
            
            // 逐行写入数据
            $rowCount = 0;
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $writer->writeRow($row);
                $rowCount++;
            }
            
            $batchTime = microtime(true) - $batchStartTime;
            $batchMemoryPeak = memory_get_usage(true);
            $memoryIncrease = $batchMemoryPeak - $batchMemoryStart;
            
            $processed += $rowCount;
            
            echo "   ✅ 批次完成: {$rowCount} 行，耗时 " . round($batchTime, 2) . 
                 " 秒，内存增长 " . $this->formatBytes($memoryIncrease) . 
                 "，当前内存 " . $this->formatBytes($batchMemoryPeak) . "\n";
            
            // 获取Writer统计信息
            $stats = $writer->getStats();
            if ($stats['buffer_flushes'] > 0) {
                echo "   💾 缓冲区已刷新 {$stats['buffer_flushes']} 次，写入 " . 
                     $this->formatBytes($stats['bytes_written']) . "\n";
            }
            
            $stmt->closeCursor();
        }
        
        // 完成写入并关闭文件
        echo "💾 完成写入并保存文件...\n";
        $saveStartTime = microtime(true);
        
        $result = $writer->finishWrite();
        
        $saveTime = microtime(true) - $saveStartTime;
        echo "✅ 文件保存完成，耗时 " . round($saveTime, 2) . " 秒\n";
        
        $totalTime = microtime(true) - $startTime;
        $memoryEnd = memory_get_usage(true);
        $memoryUsed = $memoryEnd - $memoryStart;
        
        // 获取最终统计信息
        $finalStats = $writer->getStats();
        
        if (!$result) {
            throw new \RuntimeException("文件保存失败");
        }
        
        return [
            'limit' => $limit,
            'processed' => $processed,
            'total_time' => $totalTime,
            'memory_used' => $memoryUsed,
            'memory_peak' => $finalStats['memory_peak'],
            'file_size' => file_exists($outputFile) ? filesize($outputFile) : 0,
            'rows_written' => $finalStats['rows_written'],
            'cells_written' => $finalStats['cells_written'],
            'bytes_written' => $finalStats['bytes_written'],
            'buffer_flushes' => $finalStats['buffer_flushes'],
            'output_file' => $outputFile,
            'avg_speed' => $processed > 0 ? round($processed / $totalTime, 2) : 0
        ];
    }
    
    /**
     * 测试导出10万条数据
     */
    public function testExport100k(): void
    {
        echo "\n=== 数据库导出测试：10万条数据 ===\n";
        echo "数据库: {$this->dbPath}\n";
        echo "内存限制: " . ini_get('memory_limit') . "\n";
        echo "开始时间: " . date('Y-m-d H:i:s') . "\n\n";
        
        $result = $this->performExport(100000, 0);
        
        echo "\n=== 导出完成 ===\n";
        echo "总耗时: " . round($result['total_time'], 2) . " 秒\n";
        echo "平均速度: " . $result['avg_speed'] . " 行/秒\n";
        echo "输出文件大小: " . $this->formatBytes($result['file_size']) . "\n";
        echo "写入字节数: " . $this->formatBytes($result['bytes_written']) . "\n";
        echo "缓冲区刷新: " . $result['buffer_flushes'] . " 次\n";
        echo "最终内存: " . $this->formatBytes($result['memory_peak']) . "\n";
    }
    
    /**
     * 测试小批量导出（用于对比）
     */
    public function testExport1k(): void
    {
        echo "\n=== 对比测试：1千条数据导出 ===\n";
        
        $result = $this->performExport(1000, 0);
        
        echo "✅ 1K导出完成，耗时 " . round($result['total_time'], 2) . " 秒\n";
        echo "📊 文件大小: " . $this->formatBytes($result['file_size']) . "\n";
        echo "💾 内存峰值: " . $this->formatBytes($result['memory_peak']) . "\n\n";
    }
    
    /**
     * 格式化字节数
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// 运行测试
try {
    $test = new DatabaseExportTest();
    
    // 先运行小批量测试
    $test->testExport1k();
    
    // 然后运行大批量测试
    $test->testExport100k();
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo "📍 错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
} 