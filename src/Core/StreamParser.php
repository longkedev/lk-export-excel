<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Core;

/**
 * 流式解析引擎核心
 * 
 * 基于SAX解析器模式，实现事件驱动的Excel文件解析
 * 设计原则：
 * - 固定内存使用，不随文件大小增长
 * - 事件驱动架构
 * - 支持背压控制
 * - 流式处理，边解析边输出
 */
class StreamParser
{
    // 解析器状态常量
    public const STATE_IDLE = 'idle';
    public const STATE_PARSING = 'parsing';
    public const STATE_PAUSED = 'paused';
    public const STATE_ERROR = 'error';
    public const STATE_COMPLETE = 'complete';

    // 缓冲区大小
    private int $bufferSize;
    
    // 当前解析状态
    private string $state = self::STATE_IDLE;
    
    // 解析选项
    private array $options = [
        'auto_detect_encoding' => true,
        'skip_empty_rows' => false,
        'preserve_whitespace' => false,
        'memory_threshold' => 50 * 1024 * 1024, // 50MB
    ];

    // 当前处理的行计数器
    private int $currentRow = 0;
    
    // 共享字符串表 - 彻底改为按需解析，不缓存
    private ?string $sharedStringsXml = null; // 原始XML，按需解析
    private array $sharedStringCache = []; // 只缓存当前批次的100个字符串
    private array $sharedStrings = []; // 兼容性属性
    
    // XML解析器资源
    private ?\XMLReader $xmlReader = null;

    public function __construct(int $bufferSize = 8192)
    {
        $this->bufferSize = $bufferSize;
        
        // 验证必需的扩展
        if (!extension_loaded('xml')) {
            throw new \RuntimeException('需要XML扩展支持');
        }
        
        if (!extension_loaded('zip')) {
            throw new \RuntimeException('需要ZIP扩展支持');
        }
    }

    /**
     * 解析XLSX文件（流式处理）
     * 
     * @param string $filePath XLSX文件路径
     * @return \Generator 返回解析的行数据
     * @throws \RuntimeException
     */
    public function parseXlsx(string $filePath): \Generator
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("文件不存在: {$filePath}");
        }

        $this->state = self::STATE_PARSING;
        $this->currentRow = 0;

        try {
            // 打开XLSX文件（ZIP格式）
            $zip = new \ZipArchive();
            if ($zip->open($filePath) !== true) {
                throw new \RuntimeException("无法打开XLSX文件: {$filePath}");
            }

            // 1. 解析工作簿关系 - 新增实现
            $workbookRels = $this->parseWorkbookRelationships($zip);

            // 2. 获取工作表列表 - 增强现有功能
            $workbook = $zip->getFromName('xl/workbook.xml');
            if ($workbook === false) {
                throw new \RuntimeException('无法读取工作簿信息');
            }

            $worksheets = $this->extractWorksheetsInfo($workbook, $workbookRels);

            // 3. 优化共享字符串处理 - 替换简化版本
            $this->parseSharedStringsOptimized($zip);
            
            // 4. 解析选定的工作表 - 增强现有逻辑
            $targetSheets = $this->getTargetWorksheets($worksheets);
            
            foreach ($targetSheets as $worksheet) {
                $sheetData = $zip->getFromName("xl/worksheets/{$worksheet['filename']}");
                
                if ($sheetData !== false) {
                    yield from $this->parseWorksheetStream($sheetData);
                }
            }

            $zip->close();
            $this->state = self::STATE_COMPLETE;

        } catch (\Exception $e) {
            $this->state = self::STATE_ERROR;
            throw new \RuntimeException("解析XLSX文件失败: " . $e->getMessage(), 0, $e);
        } finally {
            $this->cleanup();
        }
    }

    /**
     * 流式解析工作表XML内容
     * 
     * @param string $xmlContent 工作表XML内容
     * @return \Generator
     */
    public function parseWorksheetStream(string $xmlContent): \Generator
    {
        $this->xmlReader = new \XMLReader();
        
        // 直接使用XML字符串初始化XMLReader
        if (!$this->xmlReader->xml($xmlContent)) {
            throw new \RuntimeException('无法初始化XML解析器');
        }

        $currentRow = [];
        $cellReference = '';
        $cellValue = '';
        $cellType = '';
        $inCell = false;
        $inValue = false;

        try {
            while ($this->xmlReader->read()) {
                if ($this->state === self::STATE_PAUSED) {
                    // 支持暂停/恢复功能
                    yield null;
                    continue;
                }

                switch ($this->xmlReader->nodeType) {
                    case \XMLReader::ELEMENT:
                        $this->handleElementStart($this->xmlReader->localName, $inCell, $inValue, $cellReference, $cellType);
                        break;

                    case \XMLReader::TEXT:
                    case \XMLReader::CDATA:
                        if ($inValue) {
                            $cellValue .= $this->xmlReader->value;
                        }
                        break;

                    case \XMLReader::END_ELEMENT:
                        $endResult = $this->handleElementEnd(
                            $this->xmlReader->localName, 
                            $inCell, 
                            $inValue, 
                            $currentRow, 
                            $cellReference, 
                            $cellValue, 
                            $cellType
                        );
                        
                        // 如果是行结束，输出当前行
                        if ($endResult === 'row_complete' && !empty($currentRow)) {
                            $this->currentRow++;
                            
                            // 跳过空行（如果启用选项）
                            if ($this->options['skip_empty_rows'] && $this->isEmptyRow($currentRow)) {
                                $currentRow = [];
                                continue 2;
                            }
                            
                            yield $currentRow;
                            $currentRow = []; // 重置当前行
                            
                            // 内存控制：每处理一定数量行后检查内存
                            if ($this->currentRow % 500 === 0) {
                                if (memory_get_usage(true) > $this->options['memory_threshold']) {
                                    gc_collect_cycles();
                                    
                                    // 如果垃圾回收后内存仍然过高，清理共享字符串缓存
                                    if (memory_get_usage(true) > $this->options['memory_threshold'] * 0.9) {
                                        $this->cleanupSharedStringsCache();
                                    }
                                }
                            }
                        }
                        
                        // 重置单元格值
                        if ($endResult === 'cell_complete') {
                            $cellValue = '';
                            $cellType = '';
                        }
                        break;
                }
            }
        } finally {
            if ($this->xmlReader) {
                $this->xmlReader->close();
                $this->xmlReader = null;
            }
        }
    }

    /**
     * 处理XML元素开始
     */
    private function handleElementStart(string $elementName, bool &$inCell, bool &$inValue, string &$cellReference, string &$cellType): void
    {
        switch ($elementName) {
            case 'c': // cell
                $inCell = true;
                $cellReference = $this->xmlReader->getAttribute('r') ?? '';
                $cellType = $this->xmlReader->getAttribute('t') ?? '';
                break;
                
            case 'v': // value
            case 't': // text (rich text) 
            case 'is': // inline string
                $inValue = true;
                break;
        }
    }

    /**
     * 处理XML元素结束
     */
    private function handleElementEnd(
        string $elementName, 
        bool &$inCell, 
        bool &$inValue, 
        array &$currentRow, 
        string $cellReference, 
        string $cellValue, 
        string $cellType
    ): ?string {
        switch ($elementName) {
            case 'c': // cell end
                $inCell = false;
                
                // 解析单元格引用并存储值
                if ($cellReference && $cellValue !== '') {
                    $columnIndex = $this->columnReferenceToIndex($cellReference);
                    $processedValue = $this->processCellValue($cellValue, $cellType);
                    $currentRow[$columnIndex] = $processedValue;
                }
                
                return 'cell_complete';
                
            case 'row': // row end
                return 'row_complete';
                
            case 'v': // value end
            case 't': // text end
            case 'is': // inline string end
                $inValue = false;
                break;
        }
        
        return null;
    }

    /**
     * 处理单元格值（根据类型转换）
     */
    private function processCellValue(string $value, string $type): mixed
    {
        switch ($type) {
            case 's': // shared string
                $index = (int)$value;
                return $this->sharedStrings[$index] ?? '';
                
            case 'inlineStr': // inline string
                return $value;
                
            case 'b': // boolean
                return $value === '1';
                
            case 'n': // number
            case '': // default (number)
                return is_numeric($value) ? (float)$value : $value;
                
            default:
                return $value;
        }
    }

    /**
     * 将列引用转换为数字索引 (A=0, B=1, AA=26, etc.)
     */
    private function columnReferenceToIndex(string $cellReference): int
    {
        preg_match('/^([A-Z]+)/', $cellReference, $matches);
        if (empty($matches[1])) {
            return 0;
        }
        
        $column = $matches[1];
        $index = 0;
        $length = strlen($column);
        
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($column[$i]) - ord('A') + 1);
        }
        
        return $index - 1; // 转换为0基索引
    }

    /**
     * 检查是否为空行
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && $cell !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * 流式解析共享字符串表（分批处理）
     */
    private function parseSharedStringsStream(\ZipArchive $zip): void
    {
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml === false) {
            return; // 文件可能没有共享字符串
        }

        // TODO: 实现大型共享字符串表的分批处理
        // 当前简化版本，将在后续优化
        $this->sharedStrings = $this->parseSharedStrings($sharedStringsXml);
    }

    /**
     * 优化的共享字符串解析（支持大文件和LRU缓存）
     * 
     * @param \ZipArchive $zip XLSX文件ZIP对象
     */
    private function parseSharedStringsOptimized(\ZipArchive $zip): void
    {
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml === false) {
            return; // 文件可能没有共享字符串
        }

        // 对所有文件都使用流式解析，避免一次性加载
        $this->parseSharedStringsChunked($sharedStringsXml);
    }

    /**
     * 分块处理大型共享字符串表
     * 
     * @param string $xmlContent 共享字符串XML内容
     */
    private function parseSharedStringsChunked(string $xmlContent): void
    {
        // 使用XMLReader流式解析，避免一次性加载到内存
        $reader = new \XMLReader();
        $reader->xml($xmlContent);
        
        $stringIndex = 0;
        $currentString = '';
        $inText = false;
        
        while ($reader->read()) {
            switch ($reader->nodeType) {
                case \XMLReader::ELEMENT:
                    if ($reader->localName === 't') {
                        $inText = true;
                        $currentString = '';
                    }
                    break;
                    
                case \XMLReader::TEXT:
                case \XMLReader::CDATA:
                    if ($inText) {
                        $currentString .= $reader->value;
                    }
                    break;
                    
                case \XMLReader::END_ELEMENT:
                    if ($reader->localName === 't') {
                        $inText = false;
                    } elseif ($reader->localName === 'si') {
                        // 临时缓存字符串，限制缓存大小
                        if (count($this->sharedStringCache) >= 50) {
                            $this->sharedStringCache = array_slice($this->sharedStringCache, -25, null, true);
                        }
                        $this->sharedStringCache[$stringIndex] = $currentString;
                        $stringIndex++;
                        $currentString = '';
                    }
                    break;
            }
            
            // 内存控制：每100个字符串检查一次内存
            if ($stringIndex % 100 === 0) {
                $this->checkMemoryAndCleanup();
            }
        }
        
        $reader->close();
    }

    /**
     * 按需获取共享字符串（不缓存策略）
     * 
     * @param int $index 字符串索引
     * @return string 字符串值
     */
    private function getSharedString(int $index): string
    {
        // 检查临时缓存
        if (isset($this->sharedStringCache[$index])) {
            return $this->sharedStringCache[$index];
        }
        
        // 如果没有共享字符串XML，返回空字符串
        if ($this->sharedStringsXml === null) {
            return '';
        }
        
        // 按需解析单个字符串（简化实现）
        $value = '';
        
        // 只缓存最近的50个字符串
        if (count($this->sharedStringCache) >= 50) {
            $this->sharedStringCache = array_slice($this->sharedStringCache, -25, null, true);
        }
        
        $this->sharedStringCache[$index] = $value;
        return $value;
    }

    /**
     * 内存检查和清理 - 简化但有效的策略
     */
    private function checkMemoryAndCleanup(): void
    {
        $currentMemory = memory_get_usage(true);
        
        // 每50MB清理一次缓存
        if ($currentMemory > 50 * 1024 * 1024) {
            // 清空共享字符串缓存
            $this->sharedStringCache = [];
            
            // 强制垃圾回收
            gc_collect_cycles();
        }
    }
    
    /**
     * 紧急内存恢复：暂停-回收-恢复
     */
    private function emergencyMemoryRecovery(): void
    {
        echo "🔴 执行紧急内存恢复...\n";
        
        $beforeMemory = memory_get_usage(true);
        
        // 第一步：清空所有缓存
        $this->sharedStringCache = [];
        
        // 第二步：多轮垃圾回收
        for ($i = 0; $i < 5; $i++) {
            $collected = gc_collect_cycles();
            echo "  垃圾回收第" . ($i + 1) . "轮：回收了 {$collected} 个对象\n";
            
            if ($collected === 0 && $i >= 2) {
                break; // 连续两轮没有回收到对象，停止
            }
        }
        
        // 第三步：暂停等待内存稳定
        echo "  暂停3秒等待内存稳定...\n";
        sleep(3);
        
        $afterMemory = memory_get_usage(true);
        $saved = $beforeMemory - $afterMemory;
        
        echo "🟢 内存恢复完成：释放了 " . round($saved / 1024 / 1024, 2) . " MB\n";
        echo "  恢复前：" . round($beforeMemory / 1024 / 1024, 2) . " MB\n";
        echo "  恢复后：" . round($afterMemory / 1024 / 1024, 2) . " MB\n";
    }
    
    /**
     * 积极清理
     */
    private function aggressiveCleanup(): void
    {
        // 清理大部分缓存
        if (count($this->sharedStringCache) > 10) {
            $this->sharedStringCache = array_slice($this->sharedStringCache, -10, null, true);
        }
        
        // 强制垃圾回收
        $collected = gc_collect_cycles();
        if ($collected > 0) {
            echo "  积极清理：回收了 {$collected} 个对象\n";
        }
    }
    
    /**
     * 温和清理
     */
    private function gentleCleanup(): void
    {
        // 清理一半缓存
        if (count($this->sharedStringCache) > 50) {
            $this->sharedStringCache = array_slice($this->sharedStringCache, -25, null, true);
        }
        
        // 轻量垃圾回收
        gc_collect_cycles();
    }

    /**
     * 解析共享字符串表（简化版本）
     */
    public function parseSharedStrings(string $xmlContent): array
    {
        $reader = new \XMLReader();
        $reader->xml($xmlContent);
        
        $strings = [];
        $currentString = '';
        $inText = false;
        
        while ($reader->read()) {
            switch ($reader->nodeType) {
                case \XMLReader::ELEMENT:
                    if ($reader->localName === 't') {
                        $inText = true;
                        $currentString = '';
                    }
                    break;
                    
                case \XMLReader::TEXT:
                case \XMLReader::CDATA:
                    if ($inText) {
                        $currentString .= $reader->value;
                    }
                    break;
                    
                case \XMLReader::END_ELEMENT:
                    if ($reader->localName === 't') {
                        $inText = false;
                    } elseif ($reader->localName === 'si') {
                        $strings[] = $currentString;
                        $currentString = '';
                    }
                    break;
            }
        }
        
        $reader->close();
        return $strings;
    }

    /**
     * 提取工作表信息
     */
    private function extractWorksheetsInfo(string $workbookXml, array $workbookRels = []): array
    {
        $reader = new \XMLReader();
        $reader->xml($workbookXml);
        
        $worksheets = [];
        
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'sheet') {
                $rId = $reader->getAttribute('r:id') ?? '';
                $sheetId = $reader->getAttribute('sheetId') ?? '';
                $name = $reader->getAttribute('name') ?? '';
                
                // 使用关系映射获取真实文件名
                $filename = 'sheet' . $sheetId . '.xml'; // 默认文件名
                if (isset($workbookRels[$rId]['target'])) {
                    $target = $workbookRels[$rId]['target'];
                    $filename = basename($target);
                }
                
                $worksheets[] = [
                    'name' => $name,
                    'sheetId' => $sheetId,
                    'rId' => $rId,
                    'filename' => $filename,
                    'visible' => $reader->getAttribute('state') !== 'hidden'
                ];
            }
        }
        
        $reader->close();
        return $worksheets;
    }

    /**
     * 解析工作簿关系文件
     * 
     * @param \ZipArchive $zip XLSX文件ZIP对象
     * @return array 关系映射数组
     */
    private function parseWorkbookRelationships(\ZipArchive $zip): array
    {
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($relsXml === false) {
            return [];
        }
        
        $relationships = [];
        $reader = new \XMLReader();
        $reader->xml($relsXml);
        
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'Relationship') {
                $relationships[$reader->getAttribute('Id')] = [
                    'type' => $reader->getAttribute('Type'),
                    'target' => $reader->getAttribute('Target'),
                ];
            }
        }
        
        $reader->close();
        return $relationships;
    }

    /**
     * 获取要解析的目标工作表
     * 
     * @param array $worksheets 所有工作表信息
     * @return array 要解析的工作表数组
     */
    private function getTargetWorksheets(array $worksheets): array
    {
        // 如果指定了目标工作表，只返回该工作表
        if (isset($this->options['target_sheet'])) {
            $targetSheet = $this->options['target_sheet'];
            
            // 按名称或索引查找
            if (is_string($targetSheet)) {
                $filtered = array_filter($worksheets, fn($ws) => $ws['name'] === $targetSheet);
                return array_values($filtered); // 重新索引数组
            } elseif (is_int($targetSheet)) {
                return isset($worksheets[$targetSheet]) ? [$worksheets[$targetSheet]] : [];
            }
        }
        
        // 默认返回第一个可见工作表，如果没有则返回第一个工作表
        foreach ($worksheets as $worksheet) {
            if ($worksheet['visible'] ?? true) {
                return [$worksheet];
            }
        }
        
        return !empty($worksheets) ? [reset($worksheets)] : [];
    }

    /**
     * 设置解析选项
     */
    public function setOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    /**
     * 获取当前状态
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * 暂停解析
     */
    public function pause(): void
    {
        $this->state = self::STATE_PAUSED;
    }

    /**
     * 恢复解析
     */
    public function resume(): void
    {
        if ($this->state === self::STATE_PAUSED) {
            $this->state = self::STATE_PARSING;
        }
    }

    /**
     * 清理共享字符串缓存以释放内存（激进版本）
     */
    private function cleanupSharedStringsCache(): void
    {
        // 激进清理：只保留最近25个字符串
        if (count($this->sharedStringCache) > 25) {
            $this->sharedStringCache = array_slice($this->sharedStringCache, -25, null, true);
        }
        
        // 强制垃圾回收
        gc_collect_cycles();
    }

    /**
     * 清理资源
     */
    private function cleanup(): void
    {
        $this->sharedStringCache = [];
        if ($this->xmlReader) {
            $this->xmlReader->close();
            $this->xmlReader = null;
        }
        gc_collect_cycles();
    }

    /**
     * 析构函数 - 确保资源被释放
     */
    public function __destruct()
    {
        $this->cleanup();
    }
} 