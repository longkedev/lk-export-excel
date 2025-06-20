<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Format;

/**
 * 智能格式检测器
 * 
 * 多层级格式检测策略：
 * - 文件头魔数检测（最可靠）
 * - MIME类型分析
 * - 文件扩展名验证
 * - 内容结构检测
 * - 混合格式处理
 */
class FormatDetector
{
    // 支持的格式
    public const FORMAT_XLSX = 'xlsx';
    public const FORMAT_XLS = 'xls';
    public const FORMAT_CSV = 'csv';
    public const FORMAT_ODS = 'ods';
    public const FORMAT_XML = 'xml';
    public const FORMAT_UNKNOWN = 'unknown';

    // 文件魔数签名
    private array $magicNumbers = [
        // XLSX/DOCX/PPTX (Office Open XML) - ZIP archive
        self::FORMAT_XLSX => [
            ['PK', 0], // ZIP signature
            ['xl/', null, 'zip_content'], // Must contain xl/ folder
        ],
        
        // XLS (Microsoft Excel 97-2003)
        self::FORMAT_XLS => [
            ["\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1", 0], // OLE2 signature
        ],
        
        // ODS (OpenDocument Spreadsheet)
        self::FORMAT_ODS => [
            ['PK', 0], // ZIP signature
            ['mimetype', null, 'zip_content'], // Must contain mimetype file
            ['application/vnd.oasis.opendocument.spreadsheet', null, 'mime_content'],
        ],
    ];

    // MIME类型映射
    private array $mimeTypes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => self::FORMAT_XLSX,
        'application/vnd.ms-excel' => self::FORMAT_XLS,
        'application/vnd.oasis.opendocument.spreadsheet' => self::FORMAT_ODS,
        'text/csv' => self::FORMAT_CSV,
        'text/plain' => self::FORMAT_CSV,
        'application/csv' => self::FORMAT_CSV,
    ];

    // 文件扩展名映射
    private array $extensions = [
        'xlsx' => self::FORMAT_XLSX,
        'xls' => self::FORMAT_XLS,
        'csv' => self::FORMAT_CSV,
        'ods' => self::FORMAT_ODS,
        'xml' => self::FORMAT_XML,
    ];

    // CSV可能的分隔符
    private array $csvDelimiters = [',', ';', '\t', '|', ':'];

    private int $maxSampleSize;
    private bool $enableDeepScan;

    public function __construct(int $maxSampleSize = 8192, bool $enableDeepScan = true)
    {
        $this->maxSampleSize = $maxSampleSize;
        $this->enableDeepScan = $enableDeepScan;
    }

    /**
     * 检测文件格式
     * 
     * @param string $filePath 文件路径
     * @return array 检测结果
     */
    public function detect(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("文件不存在: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new \InvalidArgumentException("文件不可读: {$filePath}");
        }

        $startTime = microtime(true);
        $results = [
            'format' => self::FORMAT_UNKNOWN,
            'confidence' => 0.0,
            'mime_type' => null,
            'extension' => null,
            'details' => [],
            'detection_time' => 0,
            'file_size' => filesize($filePath),
        ];

        try {
            // 1. 魔数检测（最高优先级）
            $magicResult = $this->detectByMagicNumber($filePath);
            if ($magicResult['confidence'] > 0.8) {
                $results = array_merge($results, $magicResult);
                $results['detection_method'] = 'magic_number';
                $results['detection_time'] = microtime(true) - $startTime;
                return $results;
            }

            // 2. MIME类型检测
            $mimeResult = $this->detectByMimeType($filePath);
            if ($mimeResult['confidence'] > $results['confidence']) {
                $results = array_merge($results, $mimeResult);
                $results['detection_method'] = 'mime_type';
            }

            // 3. 文件扩展名检测
            $extResult = $this->detectByExtension($filePath);
            if ($extResult['confidence'] > $results['confidence']) {
                $results = array_merge($results, $extResult);
                $results['detection_method'] = 'extension';
            }

            // 4. 内容结构深度检测
            if ($this->enableDeepScan && $results['confidence'] < 0.7) {
                $contentResult = $this->detectByContent($filePath);
                if ($contentResult['confidence'] > $results['confidence']) {
                    $results = array_merge($results, $contentResult);
                    $results['detection_method'] = 'content_analysis';
                }
            }

            // 5. 特殊情况：CSV格式检测
            if ($results['format'] === self::FORMAT_UNKNOWN) {
                $csvResult = $this->detectCSV($filePath);
                if ($csvResult['confidence'] > $results['confidence']) {
                    $results = array_merge($results, $csvResult);
                    $results['detection_method'] = 'csv_heuristic';
                }
            }

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
            $results['confidence'] = 0.0;
        }

        $results['detection_time'] = microtime(true) - $startTime;
        return $results;
    }

    /**
     * 通过魔数检测格式
     */
    private function detectByMagicNumber(string $filePath): array
    {
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return ['format' => self::FORMAT_UNKNOWN, 'confidence' => 0.0];
        }

        try {
            $header = fread($handle, $this->maxSampleSize);
            
            foreach ($this->magicNumbers as $format => $signatures) {
                $matches = 0;
                $totalChecks = count($signatures);
                
                foreach ($signatures as $signature) {
                    [$pattern, $offset, $type] = array_pad($signature, 3, null);
                    
                    if ($type === 'zip_content') {
                        // 检查ZIP内容
                        if ($this->checkZipContent($filePath, $pattern)) {
                            $matches++;
                        }
                    } elseif ($type === 'mime_content') {
                        // 检查MIME内容
                        if ($this->checkMimeContent($filePath, $pattern)) {
                            $matches++;
                        }
                    } else {
                        // 普通魔数检查
                        if ($offset === null || $offset === 0) {
                            if (str_starts_with($header, $pattern)) {
                                $matches++;
                            }
                        } else {
                            $checkData = substr($header, $offset, strlen($pattern));
                            if ($checkData === $pattern) {
                                $matches++;
                            }
                        }
                    }
                }
                
                if ($matches > 0) {
                    $confidence = $matches / $totalChecks;
                    return [
                        'format' => $format,
                        'confidence' => $confidence,
                        'details' => [
                            'magic_matches' => $matches,
                            'total_checks' => $totalChecks,
                            'header_sample' => bin2hex(substr($header, 0, 16))
                        ]
                    ];
                }
            }
            
        } finally {
            fclose($handle);
        }

        return ['format' => self::FORMAT_UNKNOWN, 'confidence' => 0.0];
    }

    /**
     * 通过MIME类型检测格式
     */
    private function detectByMimeType(string $filePath): array
    {
        if (!function_exists('mime_content_type')) {
            return ['format' => self::FORMAT_UNKNOWN, 'confidence' => 0.0];
        }

        $mimeType = mime_content_type($filePath);
        if (!$mimeType) {
            return ['format' => self::FORMAT_UNKNOWN, 'confidence' => 0.0];
        }

        $format = $this->mimeTypes[$mimeType] ?? self::FORMAT_UNKNOWN;
        $confidence = $format !== self::FORMAT_UNKNOWN ? 0.7 : 0.0;

        return [
            'format' => $format,
            'confidence' => $confidence,
            'mime_type' => $mimeType,
            'details' => ['detected_mime' => $mimeType]
        ];
    }

    /**
     * 通过文件扩展名检测格式
     */
    private function detectByExtension(string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $format = $this->extensions[$extension] ?? self::FORMAT_UNKNOWN;
        $confidence = $format !== self::FORMAT_UNKNOWN ? 0.5 : 0.0;

        return [
            'format' => $format,
            'confidence' => $confidence,
            'extension' => $extension,
            'details' => ['file_extension' => $extension]
        ];
    }

    /**
     * 通过内容分析检测格式
     */
    private function detectByContent(string $filePath): array
    {
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return ['format' => self::FORMAT_UNKNOWN, 'confidence' => 0.0];
        }

        try {
            $sample = fread($handle, $this->maxSampleSize);
            
            // XML检测
            if ($this->isXMLContent($sample)) {
                return [
                    'format' => self::FORMAT_XML,
                    'confidence' => 0.6,
                    'details' => ['content_type' => 'xml']
                ];
            }

            // 二进制Excel检测（更深入的OLE2分析）
            if ($this->isOLE2Content($sample)) {
                return [
                    'format' => self::FORMAT_XLS,
                    'confidence' => 0.8,
                    'details' => ['content_type' => 'ole2_binary']
                ];
            }

            // ZIP结构检测
            if ($this->isZipContent($sample)) {
                // 进一步检测ZIP内容
                $zipFormat = $this->analyzeZipStructure($filePath);
                if ($zipFormat !== self::FORMAT_UNKNOWN) {
                    return [
                        'format' => $zipFormat,
                        'confidence' => 0.7,
                        'details' => ['content_type' => 'zip_archive']
                    ];
                }
            }

        } finally {
            fclose($handle);
        }

        return ['format' => self::FORMAT_UNKNOWN, 'confidence' => 0.0];
    }

    /**
     * CSV格式检测
     */
    private function detectCSV(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['format' => self::FORMAT_UNKNOWN, 'confidence' => 0.0];
        }

        try {
            $lines = [];
            $lineCount = 0;
            
            // 读取前几行进行分析
            while ($lineCount < 5 && ($line = fgets($handle)) !== false) {
                $lines[] = trim($line);
                $lineCount++;
            }

            if (empty($lines)) {
                return ['format' => self::FORMAT_UNKNOWN, 'confidence' => 0.0];
            }

            // 检测分隔符
            $detectedDelimiter = $this->detectCSVDelimiter($lines);
            if (!$detectedDelimiter) {
                return ['format' => self::FORMAT_UNKNOWN, 'confidence' => 0.0];
            }

            // 验证CSV结构一致性
            $consistency = $this->checkCSVConsistency($lines, $detectedDelimiter);
            
            return [
                'format' => self::FORMAT_CSV,
                'confidence' => $consistency,
                'details' => [
                    'csv_delimiter' => $detectedDelimiter,
                    'sample_lines' => count($lines),
                    'consistency_score' => $consistency
                ]
            ];

        } finally {
            fclose($handle);
        }
    }

    /**
     * 检测CSV分隔符
     */
    private function detectCSVDelimiter(array $lines): ?string
    {
        $delimiterCounts = [];
        
        foreach ($this->csvDelimiters as $delimiter) {
            $actualDelimiter = $delimiter === '\t' ? "\t" : $delimiter;
            $count = 0;
            
            foreach ($lines as $line) {
                $count += substr_count($line, $actualDelimiter);
            }
            
            $delimiterCounts[$delimiter] = $count;
        }

        // 选择出现次数最多且相对稳定的分隔符
        arsort($delimiterCounts);
        $topDelimiter = array_key_first($delimiterCounts);
        
        return $delimiterCounts[$topDelimiter] > 0 ? $topDelimiter : null;
    }

    /**
     * 检查CSV一致性
     */
    private function checkCSVConsistency(array $lines, string $delimiter): float
    {
        if (count($lines) < 2) {
            return 0.0;
        }

        $actualDelimiter = $delimiter === '\t' ? "\t" : $delimiter;
        $columnCounts = [];
        
        foreach ($lines as $line) {
            $columns = str_getcsv($line, $actualDelimiter);
            $columnCounts[] = count($columns);
        }

        // 计算列数的一致性
        $uniqueCounts = array_unique($columnCounts);
        if (count($uniqueCounts) === 1) {
            return 0.9; // 完全一致
        }

        // 计算标准差来评估一致性
        $mean = array_sum($columnCounts) / count($columnCounts);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $columnCounts)) / count($columnCounts);
        $stdDev = sqrt($variance);
        
        // 标准差越小，一致性越高
        return max(0.0, 1.0 - ($stdDev / $mean));
    }

    /**
     * 检查ZIP内容
     */
    private function checkZipContent(string $filePath, string $pattern): bool
    {
        try {
            $zip = new \ZipArchive();
            if ($zip->open($filePath) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    if (str_contains($filename, $pattern)) {
                        $zip->close();
                        return true;
                    }
                }
                $zip->close();
            }
        } catch (\Exception $e) {
            // ZIP解析失败
        }
        
        return false;
    }

    /**
     * 检查MIME内容
     */
    private function checkMimeContent(string $filePath, string $expectedMime): bool
    {
        try {
            $zip = new \ZipArchive();
            if ($zip->open($filePath) === true) {
                $mimeContent = $zip->getFromName('mimetype');
                $zip->close();
                return $mimeContent === $expectedMime;
            }
        } catch (\Exception $e) {
            // ZIP解析失败
        }
        
        return false;
    }

    /**
     * 检查是否为XML内容
     */
    private function isXMLContent(string $content): bool
    {
        $trimmed = ltrim($content);
        return str_starts_with($trimmed, '<?xml') || str_starts_with($trimmed, '<');
    }

    /**
     * 检查是否为OLE2内容
     */
    private function isOLE2Content(string $content): bool
    {
        return str_starts_with($content, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");
    }

    /**
     * 检查是否为ZIP内容
     */
    private function isZipContent(string $content): bool
    {
        return str_starts_with($content, 'PK');
    }

    /**
     * 分析ZIP结构
     */
    private function analyzeZipStructure(string $filePath): string
    {
        try {
            $zip = new \ZipArchive();
            if ($zip->open($filePath) === true) {
                $hasXlFolder = false;
                $hasMimeType = false;
                $mimeContent = '';
                
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    
                    if (str_starts_with($filename, 'xl/')) {
                        $hasXlFolder = true;
                    }
                    
                    if ($filename === 'mimetype') {
                        $hasMimeType = true;
                        $mimeContent = $zip->getFromName('mimetype');
                    }
                }
                
                $zip->close();
                
                // 判断具体格式
                if ($hasXlFolder) {
                    return self::FORMAT_XLSX;
                }
                
                if ($hasMimeType && $mimeContent === 'application/vnd.oasis.opendocument.spreadsheet') {
                    return self::FORMAT_ODS;
                }
            }
        } catch (\Exception $e) {
            // ZIP分析失败
        }
        
        return self::FORMAT_UNKNOWN;
    }

    /**
     * 获取所有支持的格式
     */
    public function getSupportedFormats(): array
    {
        return [
            self::FORMAT_XLSX,
            self::FORMAT_XLS,
            self::FORMAT_CSV,
            self::FORMAT_ODS,
            self::FORMAT_XML,
        ];
    }

    /**
     * 批量检测多个文件
     */
    public function detectBatch(array $filePaths): array
    {
        $results = [];
        
        foreach ($filePaths as $filePath) {
            try {
                $results[$filePath] = $this->detect($filePath);
            } catch (\Exception $e) {
                $results[$filePath] = [
                    'format' => self::FORMAT_UNKNOWN,
                    'confidence' => 0.0,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
} 