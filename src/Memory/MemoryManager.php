<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Memory;

use LkExcel\LkExportExcel\Performance\ProgressReporter;

/**
 * 内存管理器
 * 
 * 负责监控和管理内存使用，确保流式处理时内存使用恒定
 * 核心功能：
 * - 内存使用监控
 * - 自动垃圾回收
 * - 对象池管理
 * - 内存泄漏检测
 * - 与ProgressReporter集成
 */
class MemoryManager
{
    // 内存限制（字节）
    private int $memoryLimit;
    
    // 内存警告阈值（默认80%）
    private float $warningThreshold = 0.8;
    
    // 内存清理阈值（默认90%）
    private float $cleanupThreshold = 0.9;
    
    // 对象池
    private array $objectPools = [];
    
    // 内存使用历史（用于泄漏检测）
    private array $memoryHistory = [];
    
    // 最大历史记录数
    private int $maxHistorySize = 100;
    
    // 垃圾回收计数器
    private int $gcCount = 0;
    
    // 上次检查时间
    private int $lastCheckTime = 0;
    
    // 检查间隔（秒）
    private int $checkInterval = 1;
    
    // ProgressReporter集成
    private ?ProgressReporter $progressReporter = null;
    private bool $silentMode = false;

    public function __construct(int $memoryLimit = 0) // 0表示自动检测
    {
        // 🚀 智能内存限制：根据系统可用内存自动调节
        if ($memoryLimit === 0) {
            $this->memoryLimit = $this->calculateOptimalMemoryLimit();
        } else {
            $this->memoryLimit = $memoryLimit;
        }
        
        $this->lastCheckTime = time();
        
        // 检测是否在测试环境
        $this->silentMode = defined('PHPUNIT_COMPOSER_INSTALL') || 
                           (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'test');
        
        // 初始化对象池
        $this->initializeObjectPools();
    }
    
    /**
     * 计算最优内存限制
     * 基于系统可用内存和PHP配置自动调节
     */
    private function calculateOptimalMemoryLimit(): int
    {
        // 获取PHP内存限制
        $phpMemoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        // 🚀 智能内存调优：根据需要自动提高PHP内存限制
        $recommendedLimit = $this->getRecommendedMemoryLimit();
        
        // 如果当前PHP限制小于推荐值，尝试自动提高
        if ($phpMemoryLimit !== -1 && $phpMemoryLimit < $recommendedLimit) {
            $this->autoAdjustPHPMemoryLimit($recommendedLimit);
            $phpMemoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        }
        
        // 如果PHP内存限制是-1（无限制），使用推荐值
        if ($phpMemoryLimit === -1) {
            return $recommendedLimit;
        }
        
        // 使用PHP内存限制的80%作为我们的工作内存
        $workingMemory = (int)($phpMemoryLimit * 0.8);
        
        // 确保最小32MB
        return max(32 * 1024 * 1024, $workingMemory);
    }
    
    /**
     * 获取推荐的内存限制（基于数据量估算）
     */
    private function getRecommendedMemoryLimit(): int
    {
        // 基础内存需求：128MB
        $baseMemory = 128 * 1024 * 1024;
        
        // 如果检测到大数据量，增加内存需求
        // 这里可以根据文件大小、行数等进行估算
        $currentUsage = memory_get_usage(true);
        
        if ($currentUsage > 50 * 1024 * 1024) {
            // 当前使用超过50MB，可能是大数据处理，推荐512MB
            return 512 * 1024 * 1024;
        }
        
        return $baseMemory;
    }
    
    /**
     * 自动调整PHP内存限制
     */
    private function autoAdjustPHPMemoryLimit(int $targetLimit): void
    {
        $targetLimitMB = ceil($targetLimit / 1024 / 1024);
        
        // 尝试设置新的内存限制
        $oldLimit = ini_get('memory_limit');
        $newLimit = $targetLimitMB . 'M';
        
        if (ini_set('memory_limit', $newLimit) !== false) {
            if (!$this->silentMode) {
                echo "🚀 自动调优: PHP内存限制已从 {$oldLimit} 提高到 {$newLimit}\n";
            }
        } else {
            if (!$this->silentMode) {
                echo "⚠️  无法自动调整内存限制，请手动增加 memory_limit\n";
            }
        }
    }
    
    /**
     * 解析内存限制字符串
     */
    private function parseMemoryLimit(string $memoryLimit): int
    {
        if ($memoryLimit === '-1') {
            return -1;
        }
        
        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int)$memoryLimit;
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value;
        }
    }

    /**
     * 设置ProgressReporter
     */
    public function setProgressReporter(?ProgressReporter $progressReporter): void
    {
        $this->progressReporter = $progressReporter;
    }

    /**
     * 设置静默模式
     */
    public function setSilentMode(bool $silent): void
    {
        $this->silentMode = $silent;
    }

    /**
     * 检查内存使用是否正常
     * 
     * @return bool true表示内存使用正常，false表示需要清理
     */
    public function checkMemoryUsage(): bool
    {
        $currentUsage = memory_get_usage(true);
        $currentTime = time();
        
        // 记录内存使用历史
        if ($currentTime > $this->lastCheckTime + $this->checkInterval) {
            $this->recordMemoryUsage($currentUsage);
            $this->lastCheckTime = $currentTime;
        }
        
        $usageRatio = $currentUsage / $this->memoryLimit;
        
        // 更新ProgressReporter（如果存在）
        if ($this->progressReporter) {
            $this->progressReporter->updateMemory($currentUsage, memory_get_peak_usage(true));
        }
        
        // 如果内存使用率超过90%，立即进行紧急清理
        if ($usageRatio >= 0.9) {
            $this->emergencyCleanup();
            return false;
        }
        
        // 如果超过清理阈值，需要立即清理
        if ($usageRatio >= $this->cleanupThreshold) {
            $this->forceGarbageCollection();
            return false;
        }
        
        // 如果超过警告阈值，记录警告（但不再直接输出）
        if ($usageRatio >= $this->warningThreshold) {
            $this->logMemoryWarning($currentUsage, $usageRatio);
        }
        
        return true;
    }

    /**
     * 强制执行垃圾回收
     */
    public function forceGarbageCollection(): void
    {
        // 清理对象池中的过期对象
        $this->cleanupObjectPools();
        
        // 执行PHP垃圾回收
        $collected = gc_collect_cycles();
        $this->gcCount++;
        
        // 记录垃圾回收效果
        $newUsage = memory_get_usage(true);
        $this->logGarbageCollection($collected, $newUsage);
    }

    /**
     * 获取对象池中的对象
     * 
     * @param string $type 对象类型
     * @return mixed
     */
    public function getFromPool(string $type): mixed
    {
        if (!isset($this->objectPools[$type])) {
            return null;
        }
        
        $pool = &$this->objectPools[$type]['objects'];
        
        if (!empty($pool)) {
            return array_pop($pool);
        }
        
        return null;
    }

    /**
     * 将对象返回到对象池
     * 
     * @param string $type 对象类型
     * @param mixed $object 要返回的对象
     */
    public function returnToPool(string $type, mixed $object): void
    {
        if (!isset($this->objectPools[$type])) {
            return;
        }
        
        $pool = &$this->objectPools[$type];
        
        // 检查池子是否已满
        if (count($pool['objects']) >= $pool['max_size']) {
            return; // 池子已满，丢弃对象
        }
        
        // 内存安全检查 - 添加内存检查以避免分配失败
        if (memory_get_usage(true) > $this->memoryLimit * 0.9) {
            // 接近内存限制时不再向池中添加对象
            return;
        }
        
        // 重置对象状态（如果有重置方法）
        if (method_exists($object, 'reset')) {
            $object->reset();
        }
        
        $pool['objects'][] = $object;
    }

    /**
     * 检测内存泄漏
     * 
     * @return array 泄漏检测结果
     */
    public function detectMemoryLeaks(): array
    {
        if (count($this->memoryHistory) < 10) {
            return ['status' => 'insufficient_data', 'trend' => 'unknown'];
        }
        
        // 分析最近10次的内存使用趋势
        $recent = array_slice($this->memoryHistory, -10);
        $first = reset($recent);
        $last = end($recent);
        
        $growth = $last['usage'] - $first['usage'];
        $growthRate = $growth / $first['usage'];
        
        // 计算平均增长率
        $avgGrowth = 0;
        for ($i = 1; $i < count($recent); $i++) {
            $avgGrowth += ($recent[$i]['usage'] - $recent[$i-1]['usage']);
        }
        $avgGrowth /= (count($recent) - 1);
        
        $result = [
            'status' => 'normal',
            'trend' => 'stable',
            'growth_bytes' => $growth,
            'growth_rate' => $growthRate,
            'avg_growth' => $avgGrowth,
        ];
        
        // 判断是否存在泄漏
        if ($growthRate > 0.1 && $avgGrowth > 1024 * 1024) { // 10%增长且平均增长>1MB
            $result['status'] = 'potential_leak';
            $result['trend'] = 'increasing';
        } elseif ($growthRate > 0.05) {
            $result['status'] = 'warning';
            $result['trend'] = 'slowly_increasing';
        }
        
        return $result;
    }

    /**
     * 获取内存使用统计
     * 
     * @return array
     */
    public function getMemoryStats(): array
    {
        $currentUsage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);
        
        return [
            'current_usage' => $currentUsage,
            'current_usage_mb' => round($currentUsage / 1024 / 1024, 2),
            'current_usage_formatted' => $this->formatBytes($currentUsage),
            'peak_usage' => $peakUsage,
            'peak_usage_mb' => round($peakUsage / 1024 / 1024, 2),
            'peak_usage_formatted' => $this->formatBytes($peakUsage),
            'limit' => $this->memoryLimit,
            'limit_mb' => round($this->memoryLimit / 1024 / 1024, 2),
            'limit_formatted' => $this->formatBytes($this->memoryLimit),
            'usage_ratio' => (float)($currentUsage / $this->memoryLimit),
            'gc_count' => $this->gcCount,
            'object_pools' => $this->getObjectPoolStats(),
            'leak_detection' => $this->detectMemoryLeaks(),
        ];
    }

    /**
     * 获取峰值内存使用量
     * 
     * @return int 峰值内存使用量（字节）
     */
    public function getPeakUsage(): int
    {
        return memory_get_peak_usage(true);
    }

    /**
     * 设置内存限制
     * 
     * @param int $limit 内存限制（字节）
     */
    public function setMemoryLimit(int $limit): void
    {
        $this->memoryLimit = $limit;
    }

    /**
     * 设置警告和清理阈值
     * 
     * @param float $warningThreshold 警告阈值（0.0-1.0）
     * @param float $cleanupThreshold 清理阈值（0.0-1.0）
     */
    public function setThresholds(float $warningThreshold, float $cleanupThreshold): void
    {
        $this->warningThreshold = max(0.0, min(1.0, $warningThreshold));
        $this->cleanupThreshold = max(0.0, min(1.0, $cleanupThreshold));
    }

    /**
     * 初始化对象池
     */
    private function initializeObjectPools(): void
    {
        // 为常用对象类型创建对象池 - 减少池大小以节省内存
        $this->objectPools = [
            'array' => ['objects' => [], 'max_size' => 50],
            'stdClass' => ['objects' => [], 'max_size' => 25],
            'DOMDocument' => ['objects' => [], 'max_size' => 5],
            'XMLReader' => ['objects' => [], 'max_size' => 3],
        ];
    }

    /**
     * 紧急内存清理
     */
    private function emergencyCleanup(): void
    {
        // 清空所有对象池
        foreach ($this->objectPools as $type => &$pool) {
            $pool['objects'] = [];
        }
        
        // 强制执行垃圾回收多次
        for ($i = 0; $i < 3; $i++) {
            gc_collect_cycles();
        }
        
        $this->gcCount += 3;
    }

    /**
     * 清理对象池
     */
    private function cleanupObjectPools(): void
    {
        foreach ($this->objectPools as $type => &$pool) {
            // 保留一半的对象，释放另一半
            $keepCount = intval(count($pool['objects']) * 0.5);
            $pool['objects'] = array_slice($pool['objects'], 0, $keepCount);
        }
    }

    /**
     * 记录内存使用历史
     * 
     * @param int $usage 当前内存使用量
     */
    private function recordMemoryUsage(int $usage): void
    {
        $this->memoryHistory[] = [
            'time' => time(),
            'usage' => $usage,
            'peak' => memory_get_peak_usage(true),
        ];
        
        // 限制历史记录数量
        if (count($this->memoryHistory) > $this->maxHistorySize) {
            $this->memoryHistory = array_slice($this->memoryHistory, -$this->maxHistorySize);
        }
    }

    /**
     * 记录内存警告
     * 
     * @param int $usage 当前内存使用量
     * @param float $ratio 使用比例
     */
    private function logMemoryWarning(int $usage, float $ratio): void
    {
        // 在测试环境或静默模式中禁用内存警告日志输出
        if ($this->silentMode) {
            return;
        }
        
        // 如果有ProgressReporter，通过它处理警告
        if ($this->progressReporter) {
            // ProgressReporter的updateMemory已经处理了警告显示
            return;
        }
        
        // 只有在没有ProgressReporter时才使用传统的error_log
        // 但避免频繁输出，只记录到日志，不输出到屏幕
        error_log(sprintf(
            "lkExportExcel 内存警告: 使用 %s MB (%.1f%% of limit)",
            round($usage / 1024 / 1024, 2),
            $ratio * 100
        ));
    }

    /**
     * 记录垃圾回收信息
     * 
     * @param int $collected 回收的对象数量
     * @param int $newUsage 回收后的内存使用量
     */
    private function logGarbageCollection(int $collected, int $newUsage): void
    {
        // 已禁用垃圾回收日志 - 因为PHP GC对XMLReader/字符串驻留无效
        // 只有分段处理（重建实例）才能真正释放内存
        return;
    }

    /**
     * 获取对象池统计信息
     * 
     * @return array
     */
    private function getObjectPoolStats(): array
    {
        $stats = [];
        foreach ($this->objectPools as $type => $pool) {
            $stats[$type] = [
                'count' => count($pool['objects']),
                'max_size' => $pool['max_size'],
                'utilization' => count($pool['objects']) / $pool['max_size'],
            ];
        }
        return $stats;
    }

    /**
     * 格式化字节数
     * 
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 析构函数 - 清理资源
     */
    public function __destruct()
    {
        $this->objectPools = [];
        $this->memoryHistory = [];
    }
} 