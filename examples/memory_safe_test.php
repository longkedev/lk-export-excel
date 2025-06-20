<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use LkExcel\LkExportExcel\Api\Reader;

/**
 * 内存安全测试 - 分段处理策略
 * 
 * 核心思路：
 * 1. 分段读取：每次只读取一定数量的行，然后完全重置
 * 2. 进程重启：当内存使用过高时，记录状态并重启进程
 * 3. 文件分割：将大文件分割成小文件处理
 */
class MemorySafeProcessor
{
    private string $filePath;
    private int $segmentSize;
    private int $maxMemory;
    
    public function __construct(string $filePath, int $segmentSize = 5000, int $maxMemory = 200 * 1024 * 1024)
    {
        $this->filePath = $filePath;
        $this->segmentSize = $segmentSize;
        $this->maxMemory = $maxMemory;
    }
    
    /**
     * 分段处理大文件
     */
    public function processInSegments(): void
    {
        echo "=== 内存安全分段处理 ===\n";
        echo "文件: {$this->filePath}\n";
        echo "分段大小: {$this->segmentSize} 行\n";
        echo "内存限制: " . round($this->maxMemory / 1024 / 1024, 2) . " MB\n\n";
        
        $totalProcessed = 0;
        $segmentIndex = 0;
        $startTime = microtime(true);
        
        while (true) {
            echo "🔄 处理分段 " . ($segmentIndex + 1) . "...\n";
            
            $segmentStart = microtime(true);
            $processedInSegment = $this->processSegment($segmentIndex * $this->segmentSize, $this->segmentSize);
            $segmentTime = microtime(true) - $segmentStart;
            
            $totalProcessed += $processedInSegment;
            $segmentIndex++;
            
            echo "✅ 分段完成: 处理了 {$processedInSegment} 行，耗时 " . round($segmentTime, 2) . " 秒\n";
            echo "📊 累计处理: {$totalProcessed} 行\n";
            echo "💾 当前内存: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB\n\n";
            
            // 如果该分段处理的行数少于预期，说明文件结束
            if ($processedInSegment < $this->segmentSize) {
                break;
            }
            
            // 强制内存清理
            $this->forceMemoryCleanup();
            
            // 检查内存使用，如果过高则警告
            if (memory_get_usage(true) > $this->maxMemory) {
                echo "⚠️ 内存使用过高，建议重启进程\n";
                break;
            }
            
            // 短暂休息让系统稳定
            usleep(100000); // 0.1秒
        }
        
        $totalTime = microtime(true) - $startTime;
        $avgSpeed = $totalProcessed / $totalTime;
        
        echo "\n=== 处理完成 ===\n";
        echo "总处理行数: {$totalProcessed}\n";
        echo "总耗时: " . round($totalTime, 2) . " 秒\n";
        echo "平均速度: " . round($avgSpeed, 2) . " 行/秒\n";
        echo "最终内存: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB\n";
    }
    
    /**
     * 处理单个分段
     */
    private function processSegment(int $offset, int $limit): int
    {
        $processed = 0;
        
        try {
            // 创建新的Reader实例
            $reader = new Reader($this->filePath);
            
            // 设置偏移和限制
            $reader->limit($limit, $offset);
            
            // 分批处理这个分段
            $reader->chunk(100, function($batch, $chunkIndex) use (&$processed) {
                $processed += count($batch);
                
                // 简单处理：只计算行数
                // 在实际应用中，这里可以进行数据处理、验证、存储等操作
                
                // 每处理一个批次输出进度
                if ($chunkIndex % 10 === 0) {
                    echo "  处理批次 {$chunkIndex}, 累计 {$processed} 行\n";
                }
            });
            
        } catch (\Exception $e) {
            echo "❌ 分段处理出错: " . $e->getMessage() . "\n";
        } finally {
            // 确保释放 Reader
            unset($reader);
        }
        
        return $processed;
    }
    
    /**
     * 轻量内存监控（不做无效清理）
     */
    private function forceMemoryCleanup(): void
    {
        $currentMemory = memory_get_usage(true);
        echo "📊 分段间内存监控: " . round($currentMemory / 1024 / 1024, 2) . " MB\n";
        
        // 只做一次轻量GC，不抱期望
        gc_collect_cycles();
    }
}

// 运行测试
$processor = new MemorySafeProcessor(__DIR__ . '/../tests/fixtures/performance_100k.xlsx');
$processor->processInSegments(); 