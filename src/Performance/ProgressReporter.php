<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Performance;

/**
 * 进度报告器
 * 
 * 功能：
 * 1. 终端进度条显示
 * 2. Web API进度接口
 * 3. 实时内存和性能监控
 * 4. 单屏显示，避免刷屏
 */
class ProgressReporter
{
    private array $config;
    private array $state;
    private float $startTime;
    private int $startMemory;
    private float $lastReportTime = 0.0;
    private string $displayMode;
    
    // 进度状态常量
    public const STATUS_WAITING = 'waiting';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ERROR = 'error';
    
    // 显示模式
    public const MODE_TERMINAL = 'terminal';
    public const MODE_API = 'api';
    public const MODE_SILENT = 'silent';
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'display_mode' => self::MODE_TERMINAL,
            'update_interval' => 1.0, // 更新间隔（秒）
            'progress_bar_width' => 50,
            'memory_warning_threshold' => 0.8,
            'enable_colors' => true,
            'show_eta' => true,
            'show_speed' => true,
            'show_memory' => true,
        ], $config);
        
        $this->displayMode = $this->config['display_mode'];
        $this->initState();
    }
    
    /**
     * 初始化状态
     */
    private function initState(): void
    {
        $this->state = [
            'status' => self::STATUS_WAITING,
            'total' => 0,
            'current' => 0,
            'percentage' => 0.0,
            'speed' => 0.0,
            'eta' => 0,
            'memory_usage' => 0,
            'memory_peak' => 0,
            'memory_percentage' => 0.0,
            'operation' => '',
            'details' => '',
            'error_message' => '',
            'start_time' => 0,
            'elapsed_time' => 0,
        ];
    }
    
    /**
     * 开始任务
     */
    public function start(string $operation, int $total, array $details = []): void
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
        
        // 根据数据量自动调整更新间隔
        $this->config['update_interval'] = $this->calculateOptimalUpdateInterval($total);
        
        $this->state = array_merge($this->state, [
            'status' => self::STATUS_RUNNING,
            'operation' => $operation,
            'total' => $total,
            'current' => 0,
            'start_time' => $this->startTime,
            'details' => $details,
        ]);
        
        if ($this->displayMode === self::MODE_TERMINAL) {
            $this->clearScreen();
            $this->showHeader();
        }
    }
    
    /**
     * 根据数据量计算最优更新间隔
     */
    private function calculateOptimalUpdateInterval(int $total): float
    {
        if ($total < 1000) {
            return 0.1;         // 小数据：0.1秒更新
        } elseif ($total < 10000) {
            return 0.5;         // 中等数据：0.5秒更新
        } elseif ($total < 100000) {
            return 1.0;         // 大数据：1秒更新
        } elseif ($total < 1000000) {
            return 2.0;         // 很大数据：2秒更新
        } else {
            return 3.0;         // 超大数据：3秒更新
        }
    }
    
    /**
     * 更新进度
     */
    public function update(int $current, array $details = []): void
    {
        $now = microtime(true);
        
        // 限制更新频率
        if ($now - $this->lastReportTime < $this->config['update_interval']) {
            return;
        }
        
        $this->lastReportTime = $now;
        $this->updateState($current, $details);
        
        if ($this->displayMode === self::MODE_TERMINAL) {
            $this->refreshDisplay();
        }
    }
    
    /**
     * 完成任务
     */
    public function finish(array $summary = []): void
    {
        $this->state['status'] = self::STATUS_COMPLETED;
        $this->state['current'] = $this->state['total'];
        $this->state['percentage'] = 100.0;
        $this->updateState($this->state['current'], $summary);
        
        if ($this->displayMode === self::MODE_TERMINAL) {
            $this->refreshDisplay();
            echo "\n\n";
        }
    }
    
    /**
     * 报告错误
     */
    public function error(string $message): void
    {
        $this->state['status'] = self::STATUS_ERROR;
        $this->state['error_message'] = $message;
        
        if ($this->displayMode === self::MODE_TERMINAL) {
            $this->refreshDisplay();
            echo "\n\n";
        }
    }
    
    /**
     * 获取当前状态（用于API）
     */
    public function getState(): array
    {
        return $this->state;
    }
    
    /**
     * 手动更新内存信息
     */
    public function updateMemory(int $currentUsage, int $peakUsage): void
    {
        $memoryLimit = $this->getMemoryLimit();
        
        $this->state['memory_usage'] = $currentUsage;
        $this->state['memory_peak'] = $peakUsage;
        $this->state['memory_percentage'] = $memoryLimit > 0 ? 
            ($currentUsage / $memoryLimit) * 100 : 0;
        
        // 如果在终端模式且内存使用过高，显示一次性警告
        if ($this->displayMode === self::MODE_TERMINAL && 
            $this->state['memory_percentage'] > $this->config['memory_warning_threshold'] * 100) {
            
            static $warningShown = false;
            if (!$warningShown) {
                // 只显示一次内存警告，不刷屏
                $this->state['details']['memory_warning'] = '⚠️ 内存使用量较高';
                $warningShown = true;
            }
        }
    }
    
    /**
     * 更新内部状态
     */
    private function updateState(int $current, array $details): void
    {
        $now = microtime(true);
        $elapsed = $now - $this->startTime;
        
        $this->state['current'] = $current;
        $this->state['elapsed_time'] = $elapsed;
        $this->state['percentage'] = $this->state['total'] > 0 ? 
            min(100, ($current / $this->state['total']) * 100) : 0;
        
        // 计算速度
        if ($elapsed > 0) {
            $this->state['speed'] = $current / $elapsed;
        }
        
        // 计算ETA
        if ($this->state['speed'] > 0 && $current < $this->state['total']) {
            $remaining = $this->state['total'] - $current;
            $this->state['eta'] = $remaining / $this->state['speed'];
        } else {
            $this->state['eta'] = 0;
        }
        
        // 更新内存信息
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        $memoryLimit = $this->getMemoryLimit();
        
        $this->state['memory_usage'] = $currentMemory;
        $this->state['memory_peak'] = $peakMemory;
        $this->state['memory_percentage'] = $memoryLimit > 0 ? 
            ($currentMemory / $memoryLimit) * 100 : 0;
        
        // 合并详细信息
        if (!empty($details)) {
            $this->state['details'] = array_merge(
                is_array($this->state['details']) ? $this->state['details'] : [],
                $details
            );
        }
    }
    
    /**
     * 清屏
     */
    private function clearScreen(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            system('cls');
        } else {
            system('clear');
        }
    }
    
    /**
     * 显示头部信息
     */
    private function showHeader(): void
    {
        $title = "lkExportExcel - " . $this->state['operation'];
        $separator = str_repeat('=', strlen($title));
        
        echo $this->colorText($separator, 'cyan') . "\n";
        echo $this->colorText($title, 'cyan', true) . "\n";
        echo $this->colorText($separator, 'cyan') . "\n\n";
    }
    
    /**
     * 刷新显示
     */
    private function refreshDisplay(): void
    {
        // 移动到固定位置（不清屏，只更新内容）
        echo "\033[4;1H"; // 移动到第4行第1列
        
        $this->showProgress();
        $this->showStats();
        $this->showMemory();
        $this->showDetails();
        
        // 清除后续行
        echo "\033[K\033[0J";
    }
    
    /**
     * 显示进度条
     */
    private function showProgress(): void
    {
        $width = $this->config['progress_bar_width'];
        $percentage = $this->state['percentage'];
        $filled = (int)round(($percentage / 100) * $width);
        $empty = $width - $filled;
        
        $bar = $this->colorText(str_repeat('█', $filled), 'green') .
               $this->colorText(str_repeat('░', $empty), 'dark_gray');
        
        $statusIcon = $this->getStatusIcon();
        $percentText = sprintf('%6.1f%%', $percentage);
        
        echo sprintf("%s [%s] %s (%d/%d)\n", 
            $statusIcon, $bar, $percentText, 
            $this->state['current'], $this->state['total']
        );
    }
    
    /**
     * 显示统计信息
     */
    private function showStats(): void
    {
        echo "\n";
        
        // 速度信息
        if ($this->config['show_speed']) {
            $speed = $this->formatNumber($this->state['speed']);
            echo "📊 速度: " . $this->colorText($speed . " 行/秒", 'yellow') . "  ";
        }
        
        // 时间信息
        $elapsed = $this->formatTime($this->state['elapsed_time']);
        echo "⏱️  耗时: " . $this->colorText($elapsed, 'blue') . "  ";
        
        // ETA信息
        if ($this->config['show_eta'] && $this->state['eta'] > 0) {
            $eta = $this->formatTime($this->state['eta']);
            echo "⏳ 剩余: " . $this->colorText($eta, 'magenta');
        }
        
        echo "\n";
    }
    
    /**
     * 显示内存信息
     */
    private function showMemory(): void
    {
        if (!$this->config['show_memory']) {
            return;
        }
        
        echo "\n";
        
        $current = $this->formatBytes($this->state['memory_usage']);
        $peak = $this->formatBytes($this->state['memory_peak']);
        $percentage = $this->state['memory_percentage'];
        
        $memoryColor = $percentage > 80 ? 'red' : ($percentage > 60 ? 'yellow' : 'green');
        
        echo "💾 内存: " . $this->colorText($current, $memoryColor) . 
             " (峰值: " . $this->colorText($peak, $memoryColor) . 
             ", " . $this->colorText(sprintf('%.1f%%', $percentage), $memoryColor) . ")\n";
        
        // 内存警告（只显示一次）
        if ($percentage > $this->config['memory_warning_threshold'] * 100) {
            static $warningShown = false;
            if (!$warningShown) {
                echo $this->colorText("⚠️  内存使用较高，建议关注", 'red') . "\n";
                $warningShown = true;
            }
        }
    }
    
    /**
     * 显示详细信息
     */
    private function showDetails(): void
    {
        if (empty($this->state['details'])) {
            return;
        }
        
        echo "\n";
        
        if (is_array($this->state['details'])) {
            foreach ($this->state['details'] as $key => $value) {
                if (is_string($key)) {
                    echo sprintf("📝 %s: %s\n", $key, $value);
                }
            }
        } else {
            echo "📝 " . $this->state['details'] . "\n";
        }
        
        // 错误信息
        if ($this->state['status'] === self::STATUS_ERROR && !empty($this->state['error_message'])) {
            echo "\n" . $this->colorText("❌ 错误: " . $this->state['error_message'], 'red') . "\n";
        }
    }
    
    /**
     * 获取状态图标
     */
    private function getStatusIcon(): string
    {
        switch ($this->state['status']) {
            case self::STATUS_WAITING:
                return '⏸️ ';
            case self::STATUS_RUNNING:
                return '▶️ ';
            case self::STATUS_COMPLETED:
                return '✅';
            case self::STATUS_ERROR:
                return '❌';
            default:
                return '❓';
        }
    }
    
    /**
     * 颜色文本
     */
    private function colorText(string $text, string $color, bool $bold = false): string
    {
        if (!$this->config['enable_colors']) {
            return $text;
        }
        
        $colors = [
            'red' => '31',
            'green' => '32',
            'yellow' => '33',
            'blue' => '34',
            'magenta' => '35',
            'cyan' => '36',
            'white' => '37',
            'dark_gray' => '90',
        ];
        
        $colorCode = $colors[$color] ?? '37';
        $boldCode = $bold ? '1;' : '';
        
        return "\033[{$boldCode}{$colorCode}m{$text}\033[0m";
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
        return round($bytes, 1) . ' ' . $units[$pow];
    }
    
    /**
     * 格式化数字
     */
    private function formatNumber(float $number): string
    {
        if ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return round($number / 1000, 1) . 'K';
        } else {
            return (string)round($number, 1);
        }
    }
    
    /**
     * 格式化时间
     */
    private function formatTime(float $seconds): string
    {
        if ($seconds >= 3600) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $secs = $seconds % 60;
            return sprintf('%dh %dm %.1fs', $hours, $minutes, $secs);
        } elseif ($seconds >= 60) {
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;
            return sprintf('%dm %.1fs', $minutes, $secs);
        } else {
            return sprintf('%.1fs', $seconds);
        }
    }
    
    /**
     * 获取内存限制
     */
    private function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === false || $limit === '-1') {
            return 0; // 无限制
        }
        
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        if (is_numeric($last)) {
            $limitValue = (int)$limit;
        } else {
            $limitValue = (int)substr($limit, 0, -1);
        }
        
        switch ($last) {
            case 'g':
                $limitValue *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $limitValue *= 1024 * 1024;
                break;
            case 'k':
                $limitValue *= 1024;
                break;
        }
        
        return $limitValue;
    }
    
    /**
     * 静态方法：创建终端进度条
     */
    public static function createTerminal(array $config = []): self
    {
        return new self(array_merge($config, ['display_mode' => self::MODE_TERMINAL]));
    }
    
    /**
     * 静态方法：创建API进度器
     */
    public static function createApi(array $config = []): self
    {
        return new self(array_merge($config, ['display_mode' => self::MODE_API]));
    }
    
    /**
     * 静态方法：创建静默模式
     */
    public static function createSilent(): self
    {
        return new self(['display_mode' => self::MODE_SILENT]);
    }
} 