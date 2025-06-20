<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Api;

use LkExcel\LkExportExcel\Core\StreamParser;
use LkExcel\LkExportExcel\Memory\MemoryManager;
use LkExcel\LkExportExcel\Performance\PerformanceMonitor;
use LkExcel\LkExportExcel\Format\FormatDetector;
use LkExcel\LkExportExcel\Type\TypeDetector;

/**
 * Excel文件读取器
 * 
 * 提供链式调用的流畅API，集成所有Phase 1-3的优化组件
 * 支持流式处理，内存使用恒定，高性能解析
 */
class Reader implements ReaderInterface
{
    private string $filePath;
    private array $config;
    private ?string $selectedSheet = null;
    private ?string $selectedRange = null;
    private array $filters = [];
    private array $transformers = [];
    private array $columnMapping = [];
    private bool $hasHeaders = false;
    private int $limit = 0;
    private int $offset = 0;
    private int $startRow = 1;
    private int $startCol = 1;
    
    // 集成的优化组件
    private StreamParser $parser;
    private MemoryManager $memoryManager;
    private PerformanceMonitor $monitor;
    private FormatDetector $formatDetector;
    private TypeDetector $typeDetector;
    
    // 统计信息
    private array $stats = [
        'rows_read' => 0,
        'memory_used' => 0,
        'parse_time' => 0,
        'filter_time' => 0,
        'transform_time' => 0
    ];

    public function __construct(string $path, array $config = [])
    {
        $this->filePath = $path;
        $this->config = $config;
        
        // 验证文件存在
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("文件不存在: {$path}");
        }
        
        $this->initializeComponents();
    }

    /**
     * 初始化优化组件
     */
    private function initializeComponents(): void
    {
        $this->parser = new StreamParser($this->config['buffer_size'] ?? 8192);
        $this->memoryManager = new MemoryManager($this->config['memory_limit'] ?? 67108864);
        $this->monitor = new PerformanceMonitor($this->config['enable_monitoring'] ?? true);
        $this->formatDetector = new FormatDetector();
        $this->typeDetector = new TypeDetector($this->config['strict_mode'] ?? false);
    }

    /**
     * 选择工作表
     */
    public function sheet(string|int $sheet): static
    {
        $this->selectedSheet = (string)$sheet;
        return $this;
    }

    /**
     * 选择单元格范围
     */
    public function range(string $range): static
    {
        $this->selectedRange = $range;
        return $this;
    }

    /**
     * 设置起始行和列
     */
    public function start(int $startRow, int $startCol = 1): static
    {
        $this->startRow = $startRow;
        $this->startCol = $startCol;
        return $this;
    }

    /**
     * 限制读取数量
     */
    public function limit(int $limit, int $offset = 0): static
    {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    /**
     * 添加数据过滤器
     */
    public function filter(callable $filter): static
    {
        $this->filters[] = $filter;
        return $this;
    }

    /**
     * 添加数据转换器
     */
    public function transform(callable $transformer): static
    {
        $this->transformers[] = $transformer;
        return $this;
    }

    /**
     * 设置列映射
     */
    public function columns(array $mapping): static
    {
        $this->columnMapping = $mapping;
        return $this;
    }

    /**
     * 设置是否包含表头
     */
    public function headers(bool $hasHeaders = true): static
    {
        $this->hasHeaders = $hasHeaders;
        return $this;
    }

    /**
     * 设置读取选项
     */
    public function options(array $options): static
    {
        $this->config = array_merge($this->config, $options);
        return $this;
    }

    /**
     * 获取文件信息
     */
    public function info(): array
    {
        $formatInfo = $this->formatDetector->detect($this->filePath);
        
        return [
            'path' => $this->filePath,
            'size' => filesize($this->filePath),
            'size_formatted' => $this->formatBytes(filesize($this->filePath)),
            'format' => $formatInfo['format'],
            'mime_type' => $formatInfo['mime_type'],
            'confidence' => $formatInfo['confidence'],
            'sheets' => $this->getSheets(),
            'created' => date('Y-m-d H:i:s', filectime($this->filePath)),
            'modified' => date('Y-m-d H:i:s', filemtime($this->filePath)),
            'readable' => is_readable($this->filePath),
        ];
    }

    /**
     * 获取工作表列表
     */
    public function getSheets(): array
    {
        // 简化实现 - 在实际项目中需要解析XLSX结构
        return ['Sheet1']; // 默认工作表
    }

    /**
     * 获取行数统计
     */
    public function count(): int
    {
        $count = 0;
        foreach ($this->toIterator() as $row) {
            $count++;
        }
        return $count;
    }

    /**
     * 转换为数组（一次性加载）
     */
    public function toArray(): array
    {
        return iterator_to_array($this->toIterator());
    }

    /**
     * 转换为迭代器（流式处理）
     */
    public function toIterator(): \Generator
    {
        $this->monitor->startTimer('read_operation');
        
        try {
            $rowIndex = 0;
            $processedRows = 0;
            
            // 检测文件格式并选择合适的解析器
            $formatInfo = $this->formatDetector->detect($this->filePath);
            
            if ($formatInfo['format'] === 'CSV') {
                yield from $this->parseCSV();
            } else {
                yield from $this->parseXLSX();
            }
            
        } finally {
            $this->monitor->endTimer('read_operation');
            $this->updateStats();
        }
    }

    /**
     * 解析CSV文件
     */
    private function parseCSV(): \Generator
    {
        $handle = fopen($this->filePath, 'r');
        
        if ($handle === false) {
            throw new \RuntimeException("无法打开CSV文件: {$this->filePath}");
        }
        
        try {
            $headers = null;
            $rowIndex = 0;
            $processedRows = 0;
            
            // 处理表头
            if ($this->hasHeaders) {
                $headers = fgetcsv($handle);
                if ($headers !== false && !empty($this->columnMapping)) {
                    $headers = $this->applyColumnMapping($headers);
                }
            }
            
            // 跳过偏移行
            for ($i = 0; $i < $this->offset; $i++) {
                if (fgetcsv($handle) === false) break;
            }
            
            while (($data = fgetcsv($handle)) !== false && 
                   ($this->limit === 0 || $processedRows < $this->limit)) {
                
                $rowIndex++;
                
                // 内存检查
                if (!$this->memoryManager->checkMemoryUsage()) {
                    $this->memoryManager->forceGarbageCollection();
                }
                
                // 应用类型检测
                $typedData = $this->typeDetector->detectRowTypes($data);
                
                // 应用过滤器
                if (!$this->applyFilters($typedData, $rowIndex)) {
                    continue;
                }
                
                // 应用转换器
                $transformedData = $this->applyTransformers($typedData, $rowIndex);
                
                // 应用列映射
                if (!empty($this->columnMapping) && $headers) {
                    $transformedData = $this->mapColumns($transformedData, $headers);
                }
                
                yield $transformedData;
                $processedRows++;
                $this->stats['rows_read']++;
            }
            
        } finally {
            fclose($handle);
        }
    }

    /**
     * 解析XLSX文件
     */
    private function parseXLSX(): \Generator
    {
        $rowIndex = 0;
        $processedRows = 0;
        
        foreach ($this->parser->parseXlsx($this->filePath) as $row) {
            $rowIndex++;
            
            // 跳过起始行之前的数据
            if ($rowIndex < $this->startRow) {
                continue;
            }
            
            // 跳过偏移行
            if ($rowIndex - $this->startRow < $this->offset) {
                continue;
            }
            
            // 限制检查
            if ($this->limit > 0 && $processedRows >= $this->limit) {
                break;
            }
            
            // 内存检查
            if (!$this->memoryManager->checkMemoryUsage()) {
                $this->memoryManager->forceGarbageCollection();
            }
            
            // 应用类型检测
            $typedData = $this->typeDetector->detectRowTypes($row);
            
            // 应用过滤器
            if (!$this->applyFilters($typedData, $rowIndex)) {
                continue;
            }
            
            // 应用转换器
            $transformedData = $this->applyTransformers($typedData, $rowIndex);
            
            yield $transformedData;
            $processedRows++;
            $this->stats['rows_read']++;
        }
    }

    /**
     * 执行回调处理每一行
     */
    public function each(callable $callback): static
    {
        foreach ($this->toIterator() as $index => $row) {
            $callback($row, $index);
        }
        return $this;
    }

    /**
     * 获取第一行数据
     */
    public function first(): ?array
    {
        foreach ($this->toIterator() as $row) {
            return $row;
        }
        return null;
    }

    /**
     * 获取指定行数据
     */
    public function row(int $row): ?array
    {
        $currentRow = 0;
        foreach ($this->toIterator() as $data) {
            $currentRow++;
            if ($currentRow === $row) {
                return $data;
            }
        }
        return null;
    }

    /**
     * 获取指定列数据
     */
    public function column(string|int $column): array
    {
        $columnData = [];
        $columnIndex = is_string($column) ? $this->columnLetterToIndex($column) : $column;
        
        foreach ($this->toIterator() as $row) {
            if (isset($row[$columnIndex])) {
                $columnData[] = $row[$columnIndex];
            }
        }
        
        return $columnData;
    }

    /**
     * 获取单元格值
     */
    public function cell(string $cell): mixed
    {
        // 解析单元格地址 (如 A1, B2)
        preg_match('/([A-Z]+)(\d+)/', strtoupper($cell), $matches);
        if (count($matches) !== 3) {
            throw new \InvalidArgumentException("无效的单元格地址: $cell");
        }
        
        $col = $this->columnLetterToIndex($matches[1]);
        $row = (int)$matches[2];
        
        $rowData = $this->row($row);
        return $rowData[$col] ?? null;
    }

    /**
     * 批量处理数据
     */
    public function chunk(int $size, callable $callback): static
    {
        $batch = [];
        $batchIndex = 0;
        
        foreach ($this->toIterator() as $row) {
            $batch[] = $row;
            
            if (count($batch) >= $size) {
                $callback($batch, $batchIndex);
                $batch = [];
                $batchIndex++;
            }
        }
        
        // 处理最后一批数据
        if (!empty($batch)) {
            $callback($batch, $batchIndex);
        }
        
        return $this;
    }

    /**
     * 流式读取（内存友好）
     */
    public function stream(): \Generator
    {
        return $this->toIterator();
    }

    /**
     * 获取性能统计
     */
    public function getStats(): array
    {
        return array_merge($this->stats, [
            'memory_stats' => $this->memoryManager->getMemoryStats(),
            'monitor_report' => $this->monitor->generateReport(),
            'type_stats' => $this->typeDetector->getStats(),
        ]);
    }

    /**
     * 重置所有配置
     */
    public function reset(): static
    {
        $this->selectedSheet = null;
        $this->selectedRange = null;
        $this->filters = [];
        $this->transformers = [];
        $this->columnMapping = [];
        $this->hasHeaders = false;
        $this->limit = 0;
        $this->offset = 0;
        $this->startRow = 1;
        $this->startCol = 1;
        $this->stats = [
            'rows_read' => 0,
            'memory_used' => 0,
            'parse_time' => 0,
            'filter_time' => 0,
            'transform_time' => 0
        ];
        return $this;
    }

    /**
     * 获取文件信息（向后兼容）
     */
    public function getFileInfo(): array
    {
        return $this->info();
    }

    /**
     * 应用过滤器
     */
    private function applyFilters(array $data, int $index): bool
    {
        $this->monitor->startTimer('filter_operation');
        
        try {
            foreach ($this->filters as $filter) {
                if (!$filter($data, $index)) {
                    return false;
                }
            }
            return true;
        } finally {
            $this->monitor->endTimer('filter_operation');
        }
    }

    /**
     * 应用转换器
     */
    private function applyTransformers(array $data, int $index): array
    {
        $this->monitor->startTimer('transform_operation');
        
        try {
            foreach ($this->transformers as $transformer) {
                $data = $transformer($data, $index);
            }
            return $data;
        } finally {
            $this->monitor->endTimer('transform_operation');
        }
    }

    /**
     * 应用列映射
     */
    private function applyColumnMapping(array $headers): array
    {
        $mappedHeaders = [];
        foreach ($headers as $index => $header) {
            $mappedHeaders[$index] = $this->columnMapping[$header] ?? $header;
        }
        return $mappedHeaders;
    }

    /**
     * 映射列数据
     */
    private function mapColumns(array $data, array $headers): array
    {
        $mappedData = [];
        foreach ($data as $index => $value) {
            $headerKey = $headers[$index] ?? $index;
            $mappedData[$headerKey] = $value;
        }
        return $mappedData;
    }

    /**
     * 将列字母转换为索引
     */
    private function columnLetterToIndex(string $column): int
    {
        $column = strtoupper($column);
        $index = 0;
        $length = strlen($column);
        
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($column[$i]) - ord('A') + 1);
        }
        
        return $index - 1; // 转换为0开始的索引
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
     * 更新统计信息
     */
    private function updateStats(): void
    {
        $metrics = $this->monitor->getMetrics('read_operation');
        $this->stats['parse_time'] = $metrics['duration'] ?? 0;
        $this->stats['memory_used'] = memory_get_peak_usage(true);
        
        $filterMetrics = $this->monitor->getMetrics('filter_operation');
        $this->stats['filter_time'] = $filterMetrics['duration'] ?? 0;
        
        $transformMetrics = $this->monitor->getMetrics('transform_operation');
        $this->stats['transform_time'] = $transformMetrics['duration'] ?? 0;
    }
} 