<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Performance;

/**
 * 性能监控器
 * 
 * 负责监控和测量系统性能指标
 * 设计目标：
 * - 内存使用监控
 * - 处理时间测量
 * - 性能瓶颈识别
 * - 基准测试支持
 */
class PerformanceMonitor
{
    private array $metrics = [];
    private array $timers = [];
    private array $memorySnapshots = [];
    private bool $enabled = true;

    public function __construct(bool $enabled = true)
    {
        $this->enabled = $enabled;
        $this->captureInitialState();
    }

    /**
     * 开始计时
     */
    public function startTimer(string $name): self
    {
        if (!$this->enabled) {
            return $this;
        }

        $this->timers[$name] = [
            'start' => hrtime(true),
            'memory_start' => memory_get_usage(true),
            'peak_memory_start' => memory_get_peak_usage(true)
        ];

        return $this;
    }

    /**
     * 结束计时
     */
    public function endTimer(string $name): float
    {
        if (!$this->enabled || !isset($this->timers[$name])) {
            return 0.0;
        }

        $end = hrtime(true);
        $memoryEnd = memory_get_usage(true);
        $peakMemoryEnd = memory_get_peak_usage(true);

        $duration = ($end - $this->timers[$name]['start']) / 1e9; // 转换为秒
        $memoryUsed = $memoryEnd - $this->timers[$name]['memory_start'];
        $peakMemoryUsed = $peakMemoryEnd - $this->timers[$name]['peak_memory_start'];

        $this->metrics[$name] = [
            'duration' => $duration,
            'memory_used' => $memoryUsed,
            'peak_memory_used' => $peakMemoryUsed,
            'end_memory' => $memoryEnd,
            'end_peak_memory' => $peakMemoryEnd,
            'timestamp' => time()
        ];

        unset($this->timers[$name]);

        return $duration;
    }

    /**
     * 测量函数执行性能
     */
    public function measure(string $name, callable $callback): mixed
    {
        $this->startTimer($name);
        $result = $callback();
        $this->endTimer($name);
        return $result;
    }

    /**
     * 捕获内存快照
     */
    public function captureMemorySnapshot(string $label): self
    {
        if (!$this->enabled) {
            return $this;
        }

        $this->memorySnapshots[$label] = [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'timestamp' => microtime(true)
        ];

        return $this;
    }

    /**
     * 获取内存使用情况
     */
    public function getMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'current_formatted' => $this->formatBytes(memory_get_usage(true)),
            'peak_formatted' => $this->formatBytes(memory_get_peak_usage(true))
        ];
    }

    /**
     * 获取性能指标
     */
    public function getMetrics(string $name = null): array
    {
        if ($name !== null) {
            return $this->metrics[$name] ?? [];
        }
        return $this->metrics;
    }

    /**
     * 获取内存快照
     */
    public function getMemorySnapshots(): array
    {
        return $this->memorySnapshots;
    }

    /**
     * 生成性能报告
     */
    public function generateReport(): array
    {
        $report = [
            'summary' => [
                'total_operations' => count($this->metrics),
                'current_memory' => $this->formatBytes(memory_get_usage(true)),
                'peak_memory' => $this->formatBytes(memory_get_peak_usage(true)),
                'report_time' => date('Y-m-d H:i:s')
            ],
            'operations' => [],
            'memory_snapshots' => $this->memorySnapshots
        ];

        foreach ($this->metrics as $name => $metric) {
            $report['operations'][$name] = [
                'duration' => round($metric['duration'], 4) . 's',
                'memory_used' => $this->formatBytes($metric['memory_used']),
                'peak_memory_used' => $this->formatBytes($metric['peak_memory_used']),
                'end_memory' => $this->formatBytes($metric['end_memory']),
                'performance_rating' => $this->calculatePerformanceRating($metric)
            ];
        }

        return $report;
    }

    /**
     * 重置所有指标
     */
    public function reset(): self
    {
        $this->metrics = [];
        $this->timers = [];
        $this->memorySnapshots = [];
        $this->captureInitialState();
        return $this;
    }

    /**
     * 启用/禁用监控
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * 检查是否启用
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * 比较两个性能指标
     */
    public function compareMetrics(string $baseline, string $current): array
    {
        $baselineMetric = $this->metrics[$baseline] ?? null;
        $currentMetric = $this->metrics[$current] ?? null;

        if (!$baselineMetric || !$currentMetric) {
            return ['error' => 'One or both metrics not found'];
        }

        return [
            'duration_improvement' => $this->calculateImprovement(
                $baselineMetric['duration'], 
                $currentMetric['duration']
            ),
            'memory_improvement' => $this->calculateImprovement(
                $baselineMetric['memory_used'], 
                $currentMetric['memory_used']
            ),
            'peak_memory_improvement' => $this->calculateImprovement(
                $baselineMetric['peak_memory_used'], 
                $currentMetric['peak_memory_used']
            )
        ];
    }

    /**
     * 捕获初始状态
     */
    private function captureInitialState(): void
    {
        if ($this->enabled) {
            $this->captureMemorySnapshot('initial');
        }
    }

    /**
     * 格式化字节数
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
     * 计算性能评级
     */
    private function calculatePerformanceRating(array $metric): string
    {
        $duration = $metric['duration'];
        $memoryMB = $metric['memory_used'] / (1024 * 1024);

        // 简单的性能评级算法
        if ($duration < 0.1 && $memoryMB < 10) {
            return 'Excellent';
        } elseif ($duration < 0.5 && $memoryMB < 50) {
            return 'Good';
        } elseif ($duration < 2.0 && $memoryMB < 100) {
            return 'Fair';
        } else {
            return 'Poor';
        }
    }

    /**
     * 计算改进百分比
     */
    private function calculateImprovement(float $baseline, float $current): string
    {
        if ($baseline == 0) {
            return 'N/A';
        }

        $improvement = (($baseline - $current) / $baseline) * 100;
        return ($improvement >= 0 ? '+' : '') . round($improvement, 2) . '%';
    }
} 