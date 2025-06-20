<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Api;

use LkExcel\LkExportExcel\Memory\MemoryManager;
use LkExcel\LkExportExcel\Performance\PerformanceMonitor;
use LkExcel\LkExportExcel\Style\StyleManager;
use LkExcel\LkExportExcel\Memory\ObjectPool;

/**
 * Excel文件写入器 - 流式版本
 * 
 * 核心特性：
 * 1. 真正的流式写入 - 数据不在内存累积
 * 2. 即写即输出 - 每行数据立即写入文件
 * 3. 内存恒定 - 无论数据量多大，内存使用稳定
 * 4. 缓冲区管理 - 使用小缓冲区提高性能
 */
class Writer implements WriterInterface
{
    private array $config;
    private ?string $currentOutputFile = null;
    private $fileHandle = null;
    private string $currentFormat = 'csv';
    private int $currentRow = 0;
    private bool $headerWritten = false;
    
    // 缓冲区配置
    private array $buffer = [];
    private int $bufferSize;
    private int $bufferCount = 0;
    
    // 集成的优化组件
    private MemoryManager $memoryManager;
    private PerformanceMonitor $monitor;
    private StyleManager $styleManager;
    private ObjectPool $objectPool;
    
    // 统计信息
    private array $stats = [
        'rows_written' => 0,
        'cells_written' => 0,
        'bytes_written' => 0,
        'buffer_flushes' => 0,
        'memory_peak' => 0,
        'write_time' => 0
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            // 'memory_limit' => 移除硬编码，使用自动检测
            'enable_monitoring' => true,
            'buffer_size' => 1000, // 缓冲区大小：1000行
            'temp_dir' => sys_get_temp_dir(),
        ], $config);
        
        $this->bufferSize = $this->config['buffer_size'];
        $this->initializeComponents();
    }

    /**
     * 初始化优化组件
     */
    private function initializeComponents(): void
    {
        // 🚀 使用自动内存限制检测，除非明确指定
        $memoryLimit = $this->config['memory_limit'] ?? 0; // 0表示自动检测
        $this->memoryManager = new MemoryManager($memoryLimit);
        $this->monitor = new PerformanceMonitor($this->config['enable_monitoring']);
        $this->styleManager = new StyleManager();
        $this->objectPool = new ObjectPool(50);
    }

    /**
     * 开始写入到文件
     */
    public function startWrite(string $filename): static
    {
        $this->currentOutputFile = $filename;
        $this->currentFormat = $this->detectFormat($filename);
        $this->openFileHandle();
        $this->headerWritten = false;
        $this->currentRow = 0;
        $this->resetStats();
        
        return $this;
    }

    /**
     * 检测文件格式
     */
    private function detectFormat(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return match ($extension) {
            'csv' => 'csv',
            'xlsx' => 'xlsx',
            'json' => 'json',
            default => 'csv'
        };
    }

    /**
     * 打开文件句柄
     */
    private function openFileHandle(): void
    {
        if ($this->fileHandle) {
            fclose($this->fileHandle);
        }
        
        $this->fileHandle = fopen($this->currentOutputFile, 'w');
        
        if ($this->fileHandle === false) {
            throw new \RuntimeException("无法打开文件进行写入: {$this->currentOutputFile}");
        }
        
        // 设置缓冲区
        if (stream_set_write_buffer($this->fileHandle, 8192) !== 0) {
            // 缓冲区设置失败，继续执行
        }
    }

    /**
     * 写入表头
     */
    public function writeHeaders(array $headers, ?array $styles = null): static
    {
        if ($this->headerWritten) {
            return $this;
        }
        
        $this->monitor->startTimer('write_headers');
        
        try {
            $this->writeRowToBuffer($headers);
            $this->headerWritten = true;
            
        } finally {
            $this->monitor->endTimer('write_headers');
        }
        
        return $this;
    }

    /**
     * 写入数据行
     */
    public function writeRow(array $data, ?array $styles = null): static
    {
        $this->monitor->startTimer('write_row');
        
        try {
            // 内存检查
            $this->memoryManager->checkMemoryUsage();
            
            // 🚀 对于CSV格式，直接写入不使用缓冲区
            if ($this->currentFormat === 'csv') {
                $this->writeCSVRow($data);
                fflush($this->fileHandle); // 立即刷新到磁盘
            } else {
                // 其他格式仍使用缓冲区
                $this->writeRowToBuffer($data);
            }
            
            // 更新统计
            $this->stats['rows_written']++;
            $this->stats['cells_written'] += count($data);
            $this->currentRow++;
            
        } finally {
            $this->monitor->endTimer('write_row');
        }
        
        return $this;
    }

    /**
     * 写入行到缓冲区
     */
    private function writeRowToBuffer(array $data): void
    {
        $this->buffer[] = $data;
        $this->bufferCount++;
        $this->currentRow++;
        
        // 缓冲区满了就刷新
        if ($this->bufferCount >= $this->bufferSize) {
            $this->flushBuffer();
        }
    }

    /**
     * 刷新缓冲区到文件
     */
    private function flushBuffer(): void
    {
        if (empty($this->buffer) || !$this->fileHandle) {
            return;
        }
        
        $this->monitor->startTimer('flush_buffer');
        
        try {
            $bytesWritten = 0;
            
            foreach ($this->buffer as $row) {
                switch ($this->currentFormat) {
                    case 'csv':
                        $bytesWritten += $this->writeCSVRow($row);
                        break;
                    case 'json':
                        $bytesWritten += $this->writeJSONRow($row);
                        break;
                    default:
                        $bytesWritten += $this->writeCSVRow($row);
                }
            }
            
            // 强制刷新文件系统缓冲区
            fflush($this->fileHandle);
            
            // 更新统计
            $this->stats['buffer_flushes']++;
            $this->stats['bytes_written'] += $bytesWritten;
            $this->stats['memory_peak'] = max($this->stats['memory_peak'], memory_get_usage(true));
            
            // 清空缓冲区
            $this->buffer = [];
            $this->bufferCount = 0;
            
            // 释放内存
            $this->objectPool->clearAll();
            
        } finally {
            $this->monitor->endTimer('flush_buffer');
        }
    }

    /**
     * 写入CSV行
     */
    private function writeCSVRow(array $row): int
    {
        $oldPosition = ftell($this->fileHandle);
        fputcsv($this->fileHandle, $row);
        $newPosition = ftell($this->fileHandle);
        
        return $newPosition - $oldPosition;
    }

    /**
     * 写入JSON行（JSONL格式）
     */
    private function writeJSONRow(array $row): int
    {
        $json = json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
        $bytesWritten = fwrite($this->fileHandle, $json);
        
        return $bytesWritten ?: 0;
    }

    /**
     * 写入多行数据
     */
    public function writeRows(array $rows, ?array $styles = null): static
    {
        foreach ($rows as $row) {
            $this->writeRow($row);
        }
        
        return $this;
    }

    /**
     * 从数据源写入
     */
    public function fromDataSource(iterable $dataSource, ?callable $mapper = null): static
    {
        foreach ($dataSource as $item) {
            $row = $mapper ? $mapper($item) : $item;
            $this->writeRow($row);
        }
        
        return $this;
    }

    /**
     * 从数据库查询结果流式导出（真正的流式处理）
     * 
     * @param \PDO $pdo 数据库连接
     * @param string $sql SQL查询语句
     * @param array $params 查询参数
     * @param bool $includeHeaders 是否包含表头
     * @return static
     */
    public function fromDatabase(\PDO $pdo, string $sql, array $params = [], bool $includeHeaders = true): static
    {
        $this->monitor->startTimer('database_export');
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $headerWritten = false;
        $rowCount = 0;
        $startTime = microtime(true);
        
        // 智能进度更新间隔（内部自动调优）
        $progressUpdateInterval = 1000;
        
        echo "      🚀 开始流式导出 ({$this->currentFormat})...\n";
        echo "      🔄 导出进度: [" . str_repeat('░', 30) . "] 准备中...";
        
        // 🚀 真正的流式处理：一行数据库 → 一行文件
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            // 写入表头（仅第一次）
            if (!$headerWritten && $includeHeaders) {
                $this->writeHeaders(array_keys($row));
                $headerWritten = true;
            }
            
            // 立即写入这一行（内存中不积累数据）
            $this->writeRow($row);
            $rowCount++;
            
            // 内部自动优化：定期显示进度和内存管理
            if ($rowCount % $progressUpdateInterval === 0) {
                // 内存检查和清理
                $this->memoryManager->checkMemoryUsage();
                
                // 显示进度
                $currentTime = microtime(true);
                $elapsedTime = $currentTime - $startTime;
                $speed = $elapsedTime > 0 ? $rowCount / $elapsedTime : 0;
                $progressBar = $this->createSimpleProgressBar($rowCount, 30);
                
                echo "\r      🚀 导出进度: {$progressBar} " . 
                     number_format($rowCount) . "+ 条 " .
                     "(" . number_format($speed, 0) . "条/秒)";
            }
            
            // 释放行数据
            unset($row);
        }
        
        echo "\r      ✅ 导出完成: " . number_format($rowCount) . " 条记录" . str_repeat(' ', 20) . "\n";
        
        // 更新统计信息
        $this->stats['rows_written'] = $rowCount;
        $this->monitor->endTimer('database_export');
        
        return $this;
    }

    /**
     * 创建简单进度条（内部使用）
     */
    private function createSimpleProgressBar(int $current, int $width = 30): string
    {
        // 动态进度条，显示处理中状态
        $filled = (int)(($current / 1000) % $width);
        $bar = str_repeat('█', $filled) . '▶' . str_repeat('░', $width - $filled - 1);
        return "[$bar]";
    }

    /**
     * 流式写入
     */
    public function streamWrite(\Generator $dataGenerator, ?array $options = null): static
    {
        foreach ($dataGenerator as $data) {
            if (is_array($data)) {
                $this->writeRow($data);
            }
        }
        
        return $this;
    }

    /**
     * 完成写入并关闭文件
     */
    public function finishWrite(): bool
    {
        $this->monitor->startTimer('finish_write');
        
        try {
            // 刷新剩余缓冲区
            $this->flushBuffer();
            
            // 写入文件尾部（如果需要）
            $this->writeFileFooter();
            
            // 关闭文件句柄
            if ($this->fileHandle) {
                fclose($this->fileHandle);
                $this->fileHandle = null;
            }
            
            return true;
            
        } finally {
            $this->monitor->endTimer('finish_write');
        }
    }

    /**
     * 写入文件尾部
     */
    private function writeFileFooter(): void
    {
        if ($this->currentFormat === 'json' && $this->fileHandle) {
            // JSON数组格式可能需要尾部
        }
    }

    /**
     * 保存文件（兼容旧API）
     */
    public function save(string $filename, ?array $options = null): bool
    {
        if ($this->currentOutputFile === null) {
            // 如果没有开始写入，先开始
            $this->startWrite($filename);
        }
        
        return $this->finishWrite();
    }

    /**
     * 快速保存（用于简单场景）
     */
    public function quickSave(string $filename, array $data, ?array $headers = null): bool
    {
        $this->startWrite($filename);
        
        if ($headers) {
            $this->writeHeaders($headers);
        }
        
        foreach ($data as $row) {
            $this->writeRow($row);
        }
        
        return $this->finishWrite();
    }

    /**
     * 获取当前行号
     */
    public function getCurrentRow(): int
    {
        return $this->currentRow;
    }

    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        $this->updateStats();
        return $this->stats;
    }

    /**
     * 更新统计信息
     */
    private function updateStats(): void
    {
        $this->stats['memory_peak'] = max($this->stats['memory_peak'], memory_get_usage(true));
        
        $writeMetrics = $this->monitor->getMetrics('write_row');
        $this->stats['write_time'] = $writeMetrics['duration'] ?? 0;
    }

    /**
     * 重置统计信息
     */
    private function resetStats(): void
    {
        $this->stats = [
            'rows_written' => 0,
            'cells_written' => 0,
            'bytes_written' => 0,
            'buffer_flushes' => 0,
            'memory_peak' => memory_get_usage(true),
            'write_time' => 0
        ];
    }

    /**
     * 关闭写入器并清理资源
     */
    public function close(): void
    {
        if ($this->fileHandle) {
            fclose($this->fileHandle);
            $this->fileHandle = null;
        }
        
        $this->buffer = [];
        $this->bufferCount = 0;
        $this->objectPool->clearAll();
    }

    /**
     * 析构函数
     */
    public function __destruct()
    {
        $this->close();
    }

    // ===========================================
    // 以下方法保持兼容性，但在流式模式下功能有限
    // ===========================================

    public function createSheet(string $name): static
    {
        // 流式模式下，单个文件单个工作表
        return $this;
    }

    public function sheet(string|int $sheet): static
    {
        // 流式模式下忽略工作表切换
        return $this;
    }

    public function setCell(string $cell, mixed $value, ?array $style = null): static
    {
        // 流式模式下不支持随机访问
        throw new \BadMethodCallException('流式模式下不支持setCell操作');
    }

    public function setColumnWidth(string|int $column, float $width): static
    {
        // 流式模式下不支持样式设置
        return $this;
    }

    public function setRowHeight(int $row, float $height): static
    {
        // 流式模式下不支持样式设置
        return $this;
    }

    public function mergeCells(string $range): static
    {
        // 流式模式下不支持合并单元格
        return $this;
    }

    public function setStyle(string $range, array $style): static
    {
        // 流式模式下不支持样式设置
        return $this;
    }

    public function setFont(string $range, array $font): static
    {
        return $this;
    }

    public function setFill(string $range, array $fill): static
    {
        return $this;
    }

    public function setBorder(string $range, array $border): static
    {
        return $this;
    }

    public function setNumberFormat(string $range, string $format): static
    {
        return $this;
    }

    public function insertImage(string $cell, string $imagePath, ?array $options = null): static
    {
        return $this;
    }

    public function insertChart(string $cell, array $chartConfig): static
    {
        return $this;
    }

    public function addDataValidation(string $range, array $validation): static
    {
        return $this;
    }

    public function addConditionalFormatting(string $range, array $condition): static
    {
        return $this;
    }

    public function protect(?string $password = null, ?array $options = null): static
    {
        return $this;
    }

    public function setPrintOptions(array $options): static
    {
        return $this;
    }

    public function setPageSetup(array $pageSetup): static
    {
        return $this;
    }

    public function setFormula(string $cell, string $formula): static
    {
        return $this;
    }

    public function getCurrentColumn(): int
    {
        return 1;
    }

    public function getSheetCount(): int
    {
        return 1;
    }

    public function download(string $filename, ?array $headers = null): void
    {
        throw new \BadMethodCallException('流式模式下不支持直接下载，请先完成写入再处理文件');
    }

    public function getContent(): string
    {
        throw new \BadMethodCallException('流式模式下不支持获取全部内容');
    }

    public function getTempFile(): ?string
    {
        return $this->currentOutputFile;
    }
} 