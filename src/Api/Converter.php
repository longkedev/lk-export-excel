<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Api;

use LkExcel\LkExportExcel\Format\FormatDetector;
use LkExcel\LkExportExcel\Memory\MemoryManager;

/**
 * 文件格式转换器
 */
class Converter
{
    private string $fromPath;
    private string $toPath;
    private array $config;
    private FormatDetector $formatDetector;
    private MemoryManager $memoryManager;
    private array $conversionLog = [];
    
    public function __construct(
        string $from, 
        string $to, 
        array $config = [],
        ?FormatDetector $formatDetector = null,
        ?MemoryManager $memoryManager = null
    ) {
        $this->fromPath = $from;
        $this->toPath = $to;
        $this->config = array_merge([
            'chunk_size' => 1000,
            'preserve_formulas' => true,
            'preserve_formatting' => false,
            'quality_check' => true,
            'memory_limit' => 128 * 1024 * 1024, // 128MB
        ], $config);
        
        $this->formatDetector = $formatDetector ?? new FormatDetector();
        $this->memoryManager = $memoryManager ?? new MemoryManager($this->config['memory_limit']);
    }

    /**
     * 执行转换
     * 
     * @return bool
     * @throws \RuntimeException
     */
    public function convert(): bool
    {
        $startTime = microtime(true);
        $this->logProgress('开始文件转换', [
            'source' => $this->fromPath,
            'target' => $this->toPath
        ]);
        
        try {
            // 1. 验证源文件
            $this->validateSourceFile();
            
            // 2. 检测文件格式
            $sourceFormat = $this->formatDetector->detect($this->fromPath);
            $targetFormat = $this->detectTargetFormat($this->toPath);
            
            $this->logProgress('格式检测完成', [
                'source_format' => $sourceFormat,
                'target_format' => $targetFormat
            ]);
            
            // 3. 检查格式兼容性
            $this->validateFormatCompatibility($sourceFormat, $targetFormat);
            
            // 4. 创建Reader和Writer
            $reader = new Reader($this->fromPath, $this->config);
            $writer = new Writer($this->config);
            
            // 5. 执行转换
            $result = $this->executeConversion($reader, $writer, $sourceFormat, $targetFormat);
            
            // 6. 质量检测
            if ($this->config['quality_check'] && $result) {
                $qualityResult = $this->performQualityCheck($reader, $this->toPath);
                $this->logProgress('质量检测完成', $qualityResult);
            }
            
            $endTime = microtime(true);
            $this->logProgress('转换完成', [
                'duration' => round($endTime - $startTime, 2) . 's',
                'peak_memory' => $this->formatBytes($this->memoryManager->getPeakUsage()),
                'success' => $result
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logProgress('转换失败', ['error' => $e->getMessage()]);
            throw new \RuntimeException("文件转换失败: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * 执行实际转换逻辑
     */
    private function executeConversion(Reader $reader, Writer $writer, array $sourceFormat, string $targetFormat): bool
    {
        $this->logProgress('开始数据转换');
        
        // 获取所有工作表
        $sheets = $reader->getSheets();
        $totalSheets = count($sheets);
        
        foreach ($sheets as $sheetIndex => $sheetName) {
            $this->logProgress("处理工作表: {$sheetName}", [
                'sheet' => $sheetIndex + 1,
                'total' => $totalSheets
            ]);
            
            // 切换到当前工作表
            $reader->sheet($sheetName);
            
            // 如果是多工作表目标格式，创建对应工作表
            if (in_array($targetFormat, ['XLSX', 'XLS']) && $sheetIndex > 0) {
                $writer->createSheet($sheetName);
            } elseif ($sheetIndex === 0) {
                $writer->sheet(0); // 使用默认工作表
            }
            
            // 流式转换数据
            $rowCount = 0;
            foreach ($reader->stream() as $rowIndex => $row) {
                // 数据清理和转换
                $convertedRow = $this->convertRowData($row, $sourceFormat, $targetFormat);
                $writer->writeRow($convertedRow);
                
                $rowCount++;
                
                // 定期检查内存使用和进度报告
                if ($rowCount % $this->config['chunk_size'] === 0) {
                    $this->memoryManager->checkMemoryUsage();
                    $this->logProgress("已处理 {$rowCount} 行数据");
                }
            }
            
            $this->logProgress("工作表 {$sheetName} 处理完成", ['rows' => $rowCount]);
        }
        
        // 保存目标文件
        $saveResult = $writer->save($this->toPath);
        $this->logProgress('文件保存完成', ['success' => $saveResult]);
        
        return $saveResult;
    }
    
    /**
     * 转换行数据
     */
    private function convertRowData(array $row, array $sourceFormat, string $targetFormat): array
    {
        $convertedRow = [];
        
        foreach ($row as $cellIndex => $cellValue) {
            // 根据目标格式进行数据转换
            $convertedValue = match($targetFormat) {
                'CSV' => $this->convertForCsv($cellValue),
                'JSON' => $this->convertForJson($cellValue),
                'XLSX', 'XLS' => $this->convertForExcel($cellValue),
                default => $cellValue
            };
            
            $convertedRow[$cellIndex] = $convertedValue;
        }
        
        return $convertedRow;
    }
    
    /**
     * CSV格式转换
     */
    private function convertForCsv($value): string
    {
        if (is_null($value)) {
            return '';
        }
        
        // 处理日期和数字格式
        if (is_float($value) || is_int($value)) {
            return (string) $value;
        }
        
        // 转义CSV特殊字符
        $value = (string) $value;
        if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        
        return $value;
    }
    
    /**
     * JSON格式转换
     */
    private function convertForJson($value)
    {
        // 保持原始数据类型
        return $value;
    }
    
    /**
     * Excel格式转换
     */
    private function convertForExcel($value)
    {
        // 保持原始数据类型和格式
        return $value;
    }
    
    /**
     * 检测目标文件格式
     */
    private function detectTargetFormat(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        return match($extension) {
            'xlsx' => 'XLSX',
            'xls' => 'XLS', 
            'csv' => 'CSV',
            'json' => 'JSON',
            default => 'XLSX'
        };
    }
    
    /**
     * 验证源文件
     */
    private function validateSourceFile(): void
    {
        if (!file_exists($this->fromPath)) {
            throw new \InvalidArgumentException("源文件不存在: {$this->fromPath}");
        }
        
        if (!is_readable($this->fromPath)) {
            throw new \InvalidArgumentException("源文件不可读: {$this->fromPath}");
        }
        
        if (filesize($this->fromPath) === 0) {
            throw new \InvalidArgumentException("源文件为空: {$this->fromPath}");
        }
    }
    
    /**
     * 验证格式兼容性
     */
    private function validateFormatCompatibility(array $sourceFormat, string $targetFormat): void
    {
        // 检查不兼容的转换
        $incompatibleCombinations = [
            // 暂时没有完全不兼容的组合，但可以添加警告
        ];
        
        // 添加格式转换警告
        $sourceFormatType = strtoupper($sourceFormat['format'] ?? 'UNKNOWN');
        
        if ($sourceFormatType === 'XLSX' && $targetFormat === 'CSV') {
            $this->logProgress('警告: XLSX转CSV可能丢失格式信息和多工作表数据');
        }
        
        if (in_array($sourceFormatType, ['XLSX', 'XLS']) && $targetFormat === 'JSON') {
            $this->logProgress('警告: Excel转JSON将失去工作表结构信息');
        }
    }
    
    /**
     * 执行质量检测
     */
    private function performQualityCheck(Reader $sourceReader, string $targetPath): array
    {
        $qualityResult = [
            'source_sheets' => 0,
            'target_sheets' => 0,
            'source_rows' => 0,
            'target_rows' => 0,
            'data_integrity' => true,
            'warnings' => []
        ];
        
        try {
            // 检查源文件统计
            $sourceSheets = $sourceReader->getSheets();
            $qualityResult['source_sheets'] = count($sourceSheets);
            
            $sourceRowCount = 0;
            foreach ($sourceSheets as $sheetName) {
                $sourceReader->sheet($sheetName);
                foreach ($sourceReader->stream() as $row) {
                    $sourceRowCount++;
                }
            }
            $qualityResult['source_rows'] = $sourceRowCount;
            
            // 检查目标文件
            if (file_exists($targetPath)) {
                $targetReader = new Reader($targetPath);
                $targetSheets = $targetReader->getSheets();
                $qualityResult['target_sheets'] = count($targetSheets);
                
                $targetRowCount = 0;
                foreach ($targetSheets as $sheetName) {
                    $targetReader->sheet($sheetName);
                    foreach ($targetReader->stream() as $row) {
                        $targetRowCount++;
                    }
                }
                $qualityResult['target_rows'] = $targetRowCount;
                
                // 数据完整性检查
                if ($sourceRowCount !== $targetRowCount) {
                    $qualityResult['data_integrity'] = false;
                    $qualityResult['warnings'][] = "行数不匹配: 源文件{$sourceRowCount}行，目标文件{$targetRowCount}行";
                }
            }
            
        } catch (\Exception $e) {
            $qualityResult['warnings'][] = "质量检测部分失败: " . $e->getMessage();
        }
        
        return $qualityResult;
    }
    
    /**
     * 记录转换进度
     */
    private function logProgress(string $message, array $data = []): void
    {
        $this->conversionLog[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message,
            'data' => $data,
            'memory_usage' => $this->formatBytes(memory_get_usage(true))
        ];
    }
    
    /**
     * 格式化字节数
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * 获取转换日志
     */
    public function getConversionLog(): array
    {
        return $this->conversionLog;
    }
    
    /**
     * 获取支持的转换格式
     */
    public static function getSupportedFormats(): array
    {
        return [
            'source' => ['XLSX', 'XLS', 'CSV'],
            'target' => ['XLSX', 'XLS', 'CSV', 'JSON']
        ];
    }
} 