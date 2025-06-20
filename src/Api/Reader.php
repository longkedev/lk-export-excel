<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Api;

use LkExcel\LkExportExcel\Core\StreamParser;
use LkExcel\LkExportExcel\Memory\MemoryManager;
use LkExcel\LkExportExcel\Performance\PerformanceMonitor;
use LkExcel\LkExportExcel\Performance\ProgressReporter;
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
    private ?ProgressReporter $progressReporter = null;
    
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
        // 🚀 使用自动内存限制检测，除非明确指定
        $memoryLimit = $this->config['memory_limit'] ?? 0; // 0表示自动检测
        $this->memoryManager = new MemoryManager($memoryLimit);
        $this->monitor = new PerformanceMonitor($this->config['enable_monitoring'] ?? true);
        $this->formatDetector = new FormatDetector();
        $this->typeDetector = new TypeDetector($this->config['strict_mode'] ?? false);
    }
    
    /**
     * 设置ProgressReporter（可选）
     */
    public function setProgressReporter(?ProgressReporter $progressReporter): static
    {
        $this->progressReporter = $progressReporter;
        
        // 将ProgressReporter传递给MemoryManager
        if ($this->progressReporter) {
            $this->memoryManager->setProgressReporter($this->progressReporter);
        }
        
        return $this;
    }

    /**
     * 选择工作表
     */
    public function sheet(string|int $sheet): static
    {
        $this->selectedSheet = (string)$sheet;
        
        // 验证工作表是否存在
        $availableSheets = $this->getSheets();
        
        if (is_int($sheet)) {
            // 按索引选择
            if ($sheet < 0 || $sheet >= count($availableSheets)) {
                throw new \InvalidArgumentException("工作表索引 {$sheet} 不存在，可用范围：0-" . (count($availableSheets) - 1));
            }
            $this->selectedSheet = $availableSheets[$sheet];
        } else {
            // 按名称选择
            if (!in_array($sheet, $availableSheets)) {
                $available = implode(', ', $availableSheets);
                throw new \InvalidArgumentException("工作表 '{$sheet}' 不存在，可用工作表：{$available}");
            }
        }
        
        return $this;
    }

    /**
     * 选择单元格范围
     */
    public function range(string $range): static
    {
        // 验证范围格式
        if (!$this->isValidRange($range)) {
            throw new \InvalidArgumentException("无效的范围格式: {$range}，正确格式如：A1:C10");
        }
        
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
        // 检测文件格式
        $formatInfo = $this->formatDetector->detect($this->filePath);
        
        if (strtolower($formatInfo['format']) !== 'xlsx') {
            return ['Sheet1']; // CSV等格式只有一个工作表
        }
        
        // 使用StreamParser获取真实的工作表信息
        try {
            $zip = new \ZipArchive();
            if ($zip->open($this->filePath) !== true) {
                return ['Sheet1'];
            }
            
            $workbookXml = $zip->getFromName('xl/workbook.xml');
            $zip->close();
            
            if ($workbookXml === false) {
                return ['Sheet1'];
            }
            
            return $this->extractSheetNames($workbookXml);
            
        } catch (\Exception $e) {
            // 降级处理，返回默认工作表
            return ['Sheet1'];
        }
    }

    /**
     * 从工作簿XML中提取工作表名称
     * 
     * @param string $workbookXml 工作簿XML内容
     * @return array 工作表名称列表
     */
    private function extractSheetNames(string $workbookXml): array
    {
        $reader = new \XMLReader();
        $reader->xml($workbookXml);
        
        $sheets = [];
        
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'sheet') {
                $name = $reader->getAttribute('name');
                $state = $reader->getAttribute('state');
                
                // 只返回可见工作表（非隐藏）
                if ($state !== 'hidden') {
                    $sheets[] = $name ?: ('Sheet' . (count($sheets) + 1));
                }
            }
        }
        
        $reader->close();
        return empty($sheets) ? ['Sheet1'] : $sheets;
    }

    /**
     * 获取所有工作表详细信息（包括隐藏状态）
     * 
     * @return array 工作表详细信息
     */
    public function getSheetsInfo(): array
    {
        // 检测文件格式
        $formatInfo = $this->formatDetector->detect($this->filePath);
        
        if (strtolower($formatInfo['format']) !== 'xlsx') {
            return [
                [
                    'name' => 'Sheet1',
                    'index' => 0,
                    'visible' => true,
                    'active' => true
                ]
            ];
        }
        
        try {
            $zip = new \ZipArchive();
            if ($zip->open($this->filePath) !== true) {
                return [['name' => 'Sheet1', 'index' => 0, 'visible' => true, 'active' => true]];
            }
            
            $workbookXml = $zip->getFromName('xl/workbook.xml');
            $zip->close();
            
            if ($workbookXml === false) {
                return [['name' => 'Sheet1', 'index' => 0, 'visible' => true, 'active' => true]];
            }
            
            return $this->extractSheetsInfo($workbookXml);
            
        } catch (\Exception $e) {
            return [['name' => 'Sheet1', 'index' => 0, 'visible' => true, 'active' => true]];
        }
    }

    /**
     * 从工作簿XML中提取详细的工作表信息
     * 
     * @param string $workbookXml 工作簿XML内容
     * @return array 工作表详细信息列表
     */
    private function extractSheetsInfo(string $workbookXml): array
    {
        $reader = new \XMLReader();
        $reader->xml($workbookXml);
        
        $sheets = [];
        $index = 0;
        
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'sheet') {
                $name = $reader->getAttribute('name') ?: ("Sheet" . ($index + 1));
                $state = $reader->getAttribute('state');
                $sheetId = $reader->getAttribute('sheetId');
                
                $sheets[] = [
                    'name' => $name,
                    'index' => $index,
                    'sheetId' => $sheetId ?: (string)($index + 1),
                    'visible' => $state !== 'hidden',
                    'active' => $index === 0 // 第一个工作表默认为活动状态
                ];
                
                $index++;
            }
        }
        
        $reader->close();
        return empty($sheets) ? [['name' => 'Sheet1', 'index' => 0, 'visible' => true, 'active' => true]] : $sheets;
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
            
            // 支持多种CSV格式的检测结果
            if (in_array(strtolower($formatInfo['format']), ['csv', 'text/csv', 'text/plain'])) {
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
     * 解析CSV文件（增强版，智能优化）
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
            
            // 根据预估数据量自动调整检查频率
            $estimatedRows = $this->estimateRowCount();
            $memoryCheckInterval = $this->calculateOptimalCheckInterval($estimatedRows);
            
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
                
                // 智能内存检查 - 根据数据量调整频率
                if ($rowIndex % $memoryCheckInterval === 0) {
                    if (!$this->memoryManager->checkMemoryUsage()) {
                        $this->memoryManager->forceGarbageCollection();
                    }
                }
                
                // 应用类型检测（大数据量时跳过以节省内存）
                $typedData = $estimatedRows > 100000 ? 
                    $data : // 大数据量时跳过类型检测
                    $this->typeDetector->detectRowTypes($data);
                
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
                
                // 清理变量
                unset($typedData, $transformedData);
            }
            
        } finally {
            fclose($handle);
        }
    }

    /**
     * 解析XLSX文件（增强版，支持工作表选择和范围读取）
     */
    private function parseXLSX(): \Generator
    {
        $rowIndex = 0;
        $processedRows = 0;
        
        // 根据预估数据量自动调整检查频率
        $estimatedRows = $this->estimateRowCount();
        $memoryCheckInterval = $this->calculateOptimalCheckInterval($estimatedRows);
        

        
        // 如果指定了工作表，需要传递给StreamParser
        $parseOptions = [];
        if ($this->selectedSheet !== null) {
            $parseOptions['target_sheet'] = $this->selectedSheet;
        }
        
        // 设置解析选项到StreamParser
        $this->parser->setOptions($parseOptions);
        
        foreach ($this->parser->parseXlsx($this->filePath) as $row) {
            $rowIndex++;
            
            // 智能内存检查 - 根据数据量调整频率
            if ($rowIndex % $memoryCheckInterval === 0) {
                if (!$this->memoryManager->checkMemoryUsage()) {
                    $this->memoryManager->forceGarbageCollection();
                }
                
                // 只有在大数据量时才额外检查内存
                if ($estimatedRows > 500000 && memory_get_usage(true) > 80 * 1024 * 1024) {
                    gc_collect_cycles();
                }
            }
            
            // 🚀 使用ProgressReporter进行进度管理
            if ($this->progressReporter && $processedRows > 0) {
                $this->progressReporter->update($processedRows);
            }
            
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
            
            // 范围检查 - 新增功能
            if (!$this->isRowInRange($rowIndex, $row)) {
                continue;
            }
            
            // 裁剪行数据到指定范围 - 新增功能
            $rangeFilteredRow = $this->cropRowToRange($row);
            
            // 应用类型检测（大数据量时可选）
            $typedData = $estimatedRows > 100000 ? 
                $rangeFilteredRow : // 大数据量时跳过类型检测
                $this->typeDetector->detectRowTypes($rangeFilteredRow);
            
            // 应用过滤器
            if (!$this->applyFilters($typedData, $rowIndex)) {
                // 清理不需要的数据
                unset($typedData, $rangeFilteredRow);
                continue;
            }
            
            // 应用转换器
            $transformedData = $this->applyTransformers($typedData, $rowIndex);
            
            // 清理中间变量
            unset($typedData, $rangeFilteredRow);
            
            yield $transformedData;
            $processedRows++;
            $this->stats['rows_read']++;
            
            // 清理yield的数据
            unset($transformedData);
        }
    }

    /**
     * 估算文件行数（基于文件大小）
     */
    private function estimateRowCount(): int
    {
        $fileSize = filesize($this->filePath);
        $extension = strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION));
        
        if ($extension === 'csv') {
            // CSV: 估算每行约100字节
            return (int)($fileSize / 100);
        } elseif ($extension === 'xlsx') {
            // XLSX: 压缩格式，估算比例约1:10
            return (int)($fileSize / 50);
        }
        
        return 10000; // 默认值
    }
    
    /**
     * 计算最优的内存检查间隔
     */
    private function calculateOptimalCheckInterval(int $estimatedRows): int
    {
        if ($estimatedRows < 1000) {
            return 50;          // 小数据：每50行检查
        } elseif ($estimatedRows < 10000) {
            return 100;         // 中等数据：每100行检查
        } elseif ($estimatedRows < 100000) {
            return 500;         // 大数据：每500行检查
        } elseif ($estimatedRows < 1000000) {
            return 2000;        // 很大数据：每2000行检查
        } else {
            return 5000;        // 超大数据：每5000行检查
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
     * 批量处理数据 - 内存优化版本
     */
    public function chunk(int $size, callable $callback): static
    {
        
        $batch = [];
        $batchIndex = 0;
        
        foreach ($this->toIterator() as $row) {
            $batch[] = $row;
            
            if (count($batch) >= $size) {
                $callback($batch, $batchIndex);
                
                // 立即清理批次数据并强制垃圾回收
                $batch = [];
                unset($batch);
                $batch = [];
                $batchIndex++;
                
                // 简化：只记录内存使用，不做无效清理
                $currentMemory = memory_get_usage(true);
                if ($batchIndex % 50 === 0) {
                    echo "📊 批次 {$batchIndex}: 内存 " . round($currentMemory / 1024 / 1024, 2) . " MB\n";
                }
            }
        }
        
        // 处理最后一批数据
        if (!empty($batch)) {
            $callback($batch, $batchIndex);
            unset($batch); // 立即释放
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
        $this->stats['memory_used'] = memory_get_usage(true);
        $metrics = $this->monitor->getMetrics('read_operation');
        $this->stats['parse_time'] = $metrics['duration'] ?? 0;
    }

    /**
     * 执行深度内存清理和恢复策略
     * 
     * @param int $batchIndex 当前批次索引
     */
    private function performDeepMemoryCleanup(int $batchIndex): void
    {
        echo "🔴 批次 {$batchIndex}: 开始深度内存清理...\n";
        
        $beforeMemory = memory_get_usage(true);
        
        // 步骤1：强制垃圾回收（多轮）
        for ($i = 0; $i < 3; $i++) {
            $collected = gc_collect_cycles();
            echo "  第" . ($i + 1) . "轮垃圾回收：回收了 {$collected} 个对象\n";
            
            if ($collected === 0 && $i >= 1) {
                break; // 连续无回收，停止
            }
        }
        
        // 步骤2：暂停等待系统稳定
        echo "  暂停2秒等待内存稳定...\n";
        sleep(2);
        
        // 步骤3：检查恢复效果
        $afterMemory = memory_get_usage(true);
        $saved = $beforeMemory - $afterMemory;
        
        echo "🟢 深度清理完成：释放了 " . round($saved / 1024 / 1024, 2) . " MB\n";
        echo "  清理前：" . round($beforeMemory / 1024 / 1024, 2) . " MB\n";
        echo "  清理后：" . round($afterMemory / 1024 / 1024, 2) . " MB\n";
        echo "  继续处理下一批次...\n\n";
    }

    /**
     * 验证范围格式是否有效
     * 
     * @param string $range 范围字符串
     * @return bool 是否有效
     */
    private function isValidRange(string $range): bool
    {
        // 支持的格式：A1:C10, B2:E15, 等
        return (bool)preg_match('/^[A-Z]+\d+:[A-Z]+\d+$/', $range);
    }

    /**
     * 解析范围字符串
     * 
     * @param string $range 范围字符串，如 "A1:C10"
     * @return array 解析后的范围信息
     */
    private function parseRange(string $range): array
    {
        if (!preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $range, $matches)) {
            throw new \InvalidArgumentException("无效的范围格式: {$range}");
        }
        
        return [
            'start_col' => $this->columnLetterToIndex($matches[1]),
            'start_row' => (int)$matches[2],
            'end_col' => $this->columnLetterToIndex($matches[3]),
            'end_row' => (int)$matches[4],
            'start_col_letter' => $matches[1],
            'end_col_letter' => $matches[3],
        ];
    }

    /**
     * 检查行是否在指定范围内
     * 
     * @param int $rowIndex 行索引（1-based）
     * @param array $row 行数据
     * @return bool 是否在范围内
     */
    private function isRowInRange(int $rowIndex, array $row): bool
    {
        if ($this->selectedRange === null) {
            return true;
        }
        
        $rangeInfo = $this->parseRange($this->selectedRange);
        
        // 检查行是否在范围内
        if ($rowIndex < $rangeInfo['start_row'] || $rowIndex > $rangeInfo['end_row']) {
            return false;
        }
        
        return true;
    }

    /**
     * 裁剪行数据到指定范围
     * 
     * @param array $row 原始行数据
     * @return array 裁剪后的行数据
     */
    private function cropRowToRange(array $row): array
    {
        if ($this->selectedRange === null) {
            return $row;
        }
        
        $rangeInfo = $this->parseRange($this->selectedRange);
        
        // 计算需要的列数
        $colCount = $rangeInfo['end_col'] - $rangeInfo['start_col'] + 1;
        
        // 裁剪列范围，确保数组有足够的元素
        $result = [];
        for ($i = 0; $i < $colCount; $i++) {
            $colIndex = $rangeInfo['start_col'] + $i;
            $result[] = $row[$colIndex] ?? null;
        }
        
        return $result;
    }
} 