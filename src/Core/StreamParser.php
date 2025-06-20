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
    
    // 共享字符串表（仅在内存中保存当前批次）
    private array $sharedStrings = [];
    
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

            // 首先解析共享字符串表（分批处理避免内存爆炸）
            $this->parseSharedStringsStream($zip);

            // 获取工作表列表
            $workbook = $zip->getFromName('xl/workbook.xml');
            if ($workbook === false) {
                throw new \RuntimeException('无法读取工作簿信息');
            }

            $worksheets = $this->extractWorksheetsInfo($workbook);
            
            // 解析第一个工作表（后续版本支持多工作表）
            if (!empty($worksheets)) {
                $firstSheet = reset($worksheets);
                $sheetData = $zip->getFromName("xl/worksheets/{$firstSheet['filename']}");
                
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
                            if ($this->currentRow % 1000 === 0) {
                                if (memory_get_usage(true) > $this->options['memory_threshold']) {
                                    gc_collect_cycles();
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
    private function extractWorksheetsInfo(string $workbookXml): array
    {
        $reader = new \XMLReader();
        $reader->xml($workbookXml);
        
        $worksheets = [];
        
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'sheet') {
                $worksheets[] = [
                    'name' => $reader->getAttribute('name') ?? '',
                    'sheetId' => $reader->getAttribute('sheetId') ?? '',
                    'rId' => $reader->getAttribute('r:id') ?? '',
                    'filename' => 'sheet' . ($reader->getAttribute('sheetId') ?? '1') . '.xml'
                ];
            }
        }
        
        $reader->close();
        return $worksheets;
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
     * 清理资源
     */
    private function cleanup(): void
    {
        $this->sharedStrings = [];
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