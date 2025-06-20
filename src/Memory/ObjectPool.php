<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Memory;

/**
 * 高性能对象池
 * 
 * 用于重用频繁创建的对象，减少内存分配和垃圾回收开销
 * 设计目标：
 * - 快速对象获取和归还
 * - 自动池容量管理
 * - 对象生命周期管理
 * - 内存效率优化
 */
class ObjectPool
{
    private array $pools = [];
    private array $factories = [];
    private array $statistics = [];
    private int $defaultMaxSize = 50;
    private int $cleanupInterval = 100; // 每100次操作清理一次
    private int $operationCount = 0;

    /**
     * 注册对象工厂
     */
    public function registerFactory(string $type, callable $factory, int $maxSize = null): self
    {
        $this->factories[$type] = $factory;
        $this->pools[$type] = [
            'objects' => [],
            'max_size' => $maxSize ?? $this->defaultMaxSize,
            'created_count' => 0,
            'reused_count' => 0,
            'returned_count' => 0
        ];
        $this->statistics[$type] = [
            'hits' => 0,
            'misses' => 0,
            'hit_rate' => 0.0
        ];
        
        return $this;
    }

    /**
     * 获取对象（优先从池中获取，否则创建新对象）
     */
    public function get(string $type): mixed
    {
        $this->operationCount++;
        
        if (!isset($this->pools[$type])) {
            throw new \InvalidArgumentException("对象类型 '$type' 未注册");
        }

        $pool = &$this->pools[$type];
        
        // 从池中获取对象
        if (!empty($pool['objects'])) {
            $object = array_pop($pool['objects']);
            $pool['reused_count']++;
            $this->statistics[$type]['hits']++;
            $this->updateHitRate($type);
            return $object;
        }

        // 池中没有对象，创建新对象
        if (!isset($this->factories[$type])) {
            throw new \InvalidArgumentException("对象类型 '$type' 没有注册工厂");
        }

        $object = ($this->factories[$type])();
        $pool['created_count']++;
        $this->statistics[$type]['misses']++;
        $this->updateHitRate($type);

        // 定期清理池
        if ($this->operationCount % $this->cleanupInterval === 0) {
            $this->performCleanup();
        }

        return $object;
    }

    /**
     * 归还对象到池中
     */
    public function return(string $type, mixed $object): bool
    {
        if (!isset($this->pools[$type])) {
            return false;
        }

        $pool = &$this->pools[$type];

        // 检查池是否已满
        if (count($pool['objects']) >= $pool['max_size']) {
            return false; // 池已满，丢弃对象
        }

        // 重置对象状态
        $this->resetObject($object);
        
        $pool['objects'][] = $object;
        $pool['returned_count']++;
        
        return true;
    }

    /**
     * 预热对象池（预先创建指定数量的对象）
     */
    public function warmup(string $type, int $count): self
    {
        if (!isset($this->pools[$type])) {
            throw new \InvalidArgumentException("对象类型 '$type' 未注册");
        }

        $pool = &$this->pools[$type];
        $maxCount = min($count, $pool['max_size']);

        for ($i = count($pool['objects']); $i < $maxCount; $i++) {
            $object = ($this->factories[$type])();
            $this->resetObject($object);
            $pool['objects'][] = $object;
            $pool['created_count']++;
        }

        return $this;
    }

    /**
     * 清空指定类型的对象池
     */
    public function clear(string $type): self
    {
        if (isset($this->pools[$type])) {
            $this->pools[$type]['objects'] = [];
        }
        return $this;
    }

    /**
     * 清空所有对象池
     */
    public function clearAll(): self
    {
        foreach ($this->pools as $type => $pool) {
            $this->pools[$type]['objects'] = [];
        }
        return $this;
    }

    /**
     * 获取池统计信息
     */
    public function getStatistics(): array
    {
        $totalStats = [
            'total_pools' => count($this->pools),
            'total_objects' => 0,
            'total_created' => 0,
            'total_reused' => 0,
            'total_returned' => 0,
            'overall_hit_rate' => 0.0,
            'pools' => []
        ];

        $totalHits = 0;
        $totalOperations = 0;

        foreach ($this->pools as $type => $pool) {
            $poolSize = count($pool['objects']);
            $totalStats['total_objects'] += $poolSize;
            $totalStats['total_created'] += $pool['created_count'];
            $totalStats['total_reused'] += $pool['reused_count'];
            $totalStats['total_returned'] += $pool['returned_count'];

            $hits = $this->statistics[$type]['hits'];
            $misses = $this->statistics[$type]['misses'];
            $operations = $hits + $misses;
            
            $totalHits += $hits;
            $totalOperations += $operations;

            $totalStats['pools'][$type] = [
                'current_size' => $poolSize,
                'max_size' => $pool['max_size'],
                'usage_rate' => $pool['max_size'] > 0 ? $poolSize / $pool['max_size'] : 0,
                'created_count' => $pool['created_count'],
                'reused_count' => $pool['reused_count'],
                'returned_count' => $pool['returned_count'],
                'hits' => $hits,
                'misses' => $misses,
                'hit_rate' => $this->statistics[$type]['hit_rate'],
                'efficiency' => $pool['created_count'] > 0 ? $pool['reused_count'] / $pool['created_count'] : 0
            ];
        }

        $totalStats['overall_hit_rate'] = $totalOperations > 0 ? $totalHits / $totalOperations : 0;

        return $totalStats;
    }

    /**
     * 获取池状态报告
     */
    public function getStatusReport(): string
    {
        $stats = $this->getStatistics();
        $report = "=== 对象池状态报告 ===\n";
        $report .= "总池数: {$stats['total_pools']}\n";
        $report .= "总对象数: {$stats['total_objects']}\n";
        $report .= "总命中率: " . round($stats['overall_hit_rate'] * 100, 2) . "%\n\n";

        foreach ($stats['pools'] as $type => $poolStats) {
            $report .= "池类型: $type\n";
            $report .= "  当前大小: {$poolStats['current_size']} / {$poolStats['max_size']}\n";
            $report .= "  使用率: " . round($poolStats['usage_rate'] * 100, 2) . "%\n";
            $report .= "  命中率: " . round($poolStats['hit_rate'] * 100, 2) . "%\n";
            $report .= "  效率: " . round($poolStats['efficiency'] * 100, 2) . "%\n";
            $report .= "  创建: {$poolStats['created_count']}, 重用: {$poolStats['reused_count']}\n\n";
        }

        return $report;
    }

    /**
     * 设置默认最大池大小
     */
    public function setDefaultMaxSize(int $size): self
    {
        $this->defaultMaxSize = $size;
        return $this;
    }

    /**
     * 设置清理间隔
     */
    public function setCleanupInterval(int $interval): self
    {
        $this->cleanupInterval = $interval;
        return $this;
    }

    /**
     * 调整池大小
     */
    public function resizePool(string $type, int $newMaxSize): self
    {
        if (!isset($this->pools[$type])) {
            throw new \InvalidArgumentException("对象类型 '$type' 未注册");
        }

        $pool = &$this->pools[$type];
        $pool['max_size'] = $newMaxSize;

        // 如果新大小小于当前对象数，清理多余对象
        if (count($pool['objects']) > $newMaxSize) {
            $pool['objects'] = array_slice($pool['objects'], 0, $newMaxSize);
        }

        return $this;
    }

    /**
     * 重置对象状态
     */
    private function resetObject(mixed $object): void
    {
        // 如果是数组类型，直接返回（数组是值类型，不需要重置）
        if (is_array($object)) {
            return;
        }
        
        // 只对对象类型尝试调用重置方法
        if (is_object($object)) {
            if (method_exists($object, 'reset')) {
                $object->reset();
            } elseif (method_exists($object, 'clear')) {
                $object->clear();
            } elseif (method_exists($object, 'initialize')) {
                $object->initialize();
            }
        }
    }

    /**
     * 更新命中率
     */
    private function updateHitRate(string $type): void
    {
        $stats = &$this->statistics[$type];
        $total = $stats['hits'] + $stats['misses'];
        $stats['hit_rate'] = $total > 0 ? $stats['hits'] / $total : 0.0;
    }

    /**
     * 执行池清理
     */
    private function performCleanup(): void
    {
        foreach ($this->pools as $type => $pool) {
            $currentSize = count($pool['objects']);
            $maxSize = $pool['max_size'];
            
            // 如果池使用率过低，清理一些对象以释放内存
            if ($currentSize > $maxSize * 0.8) {
                $targetSize = (int)($maxSize * 0.6);
                $removeCount = $currentSize - $targetSize;
                
                if ($removeCount > 0) {
                    $this->pools[$type]['objects'] = array_slice(
                        $this->pools[$type]['objects'], 
                        0, 
                        $targetSize
                    );
                }
            }
        }
    }

    /**
     * 获取内存使用信息
     */
    public function getMemoryUsage(): array
    {
        $objectCount = 0;
        $estimatedMemory = 0;
        
        foreach ($this->pools as $type => $pool) {
            $poolSize = count($pool['objects']);
            $objectCount += $poolSize;
            
            // 估算内存使用（这是一个粗略的估算）
            if (!empty($pool['objects'])) {
                $sampleObject = $pool['objects'][0];
                $objectMemory = $this->estimateObjectMemory($sampleObject);
                $estimatedMemory += $objectMemory * $poolSize;
            }
        }
        
        return [
            'total_objects' => $objectCount,
            'estimated_memory_bytes' => $estimatedMemory,
            'estimated_memory_formatted' => $this->formatBytes($estimatedMemory)
        ];
    }

    /**
     * 估算对象内存使用
     */
    private function estimateObjectMemory(mixed $object): int
    {
        // 这是一个简化的内存估算
        if (is_string($object)) {
            return strlen($object) + 24; // 字符串开销
        } elseif (is_array($object)) {
            return count($object) * 40 + 56; // 数组开销
        } elseif (is_object($object)) {
            return 80; // 对象基础开销
        }
        
        return 16; // 其他类型默认开销
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