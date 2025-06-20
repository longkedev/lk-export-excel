<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Type;

/**
 * 智能类型检测器
 * 
 * 提供高性能的Excel单元格类型自动推断
 * 设计原则：
 * - 基于正则表达式的快速预检测
 * - 上下文感知的类型判断
 * - 自定义类型转换器支持
 * - 类型约束和验证
 */
class TypeDetector
{
    // 支持的数据类型
    public const TYPE_NULL = 'null';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_FLOAT = 'float';
    public const TYPE_STRING = 'string';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_DATE = 'date';
    public const TYPE_TIME = 'time';
    public const TYPE_CURRENCY = 'currency';
    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_SCIENTIFIC = 'scientific';
    public const TYPE_PHONE = 'phone';
    public const TYPE_EMAIL = 'email';
    public const TYPE_URL = 'url';

    // 类型检测优先级（数字越大优先级越高）
    private array $typePriority = [
        self::TYPE_NULL => 1,
        self::TYPE_SCIENTIFIC => 9,
        self::TYPE_DATETIME => 8,
        self::TYPE_DATE => 8,
        self::TYPE_TIME => 8,
        self::TYPE_CURRENCY => 7,
        self::TYPE_PERCENTAGE => 6,
        self::TYPE_EMAIL => 5,
        self::TYPE_URL => 5,
        self::TYPE_PHONE => 4,
        self::TYPE_BOOLEAN => 3, // 布尔值优先级提高
        self::TYPE_FLOAT => 2,
        self::TYPE_INTEGER => 2,
        self::TYPE_STRING => 0, // 最低优先级，默认类型
    ];

    // 正则表达式模式
    private array $patterns = [
        self::TYPE_BOOLEAN => [
            '/^(?:true|false|yes|no|y|n|是|否|真|假)$/i',
            '/^[01]$/',
        ],
        self::TYPE_INTEGER => [
            '/^[+-]?\d+$/',
            '/^[+-]?\d{1,3}(?:,\d{3})*$/', // 带千分位分隔符
        ],
        self::TYPE_FLOAT => [
            '/^[+-]?\d*\.\d+$/',
            '/^[+-]?\d+\.\d*$/',
            '/^[+-]?\d{1,3}(?:,\d{3})*\.\d+$/', // 带千分位的浮点数
        ],
        self::TYPE_SCIENTIFIC => [
            '/^[+-]?\d*\.?\d+[eE][+-]?\d+$/',
        ],
        self::TYPE_PERCENTAGE => [
            '/^[+-]?\d*\.?\d+%$/',
            '/^[+-]?\d{1,3}(?:,\d{3})*\.?\d*%$/',
        ],
        self::TYPE_CURRENCY => [
            '/^(?:¥|\$|€|£|₹|₽|¢)\s*[+-]?\d{1,3}(?:,\d{3})*(?:\.\d{1,4})?$/u',
            '/^[+-]?\d{1,3}(?:,\d{3})*(?:\.\d{1,4})?\s*(?:¥|\$|€|£|₹|₽|¢)$/u',
            '/^(?:CNY|USD|EUR|GBP|JPY)\s+[+-]?\d+(?:\.\d+)?$/u',
        ],
        self::TYPE_EMAIL => [
            '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
        ],
        self::TYPE_URL => [
            '/^https?:\/\/[^\s]+$/',
            '/^ftp:\/\/[^\s]+$/',
            '/^www\.[^\s]+\.[a-zA-Z]{2,}$/',
        ],
        self::TYPE_PHONE => [
            '/^[\+]?[\d\s\-\(\)]{10,}$/',
            '/^1[3-9]\d{9}$/', // 中国手机号
            '/^\d{3}-\d{3}-\d{4}$/', // 美国电话格式
        ],
        self::TYPE_DATETIME => [
            '/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}\s+\d{1,2}:\d{2}(?::\d{2})?$/',
            '/^\d{1,2}[-\/]\d{1,2}[-\/]\d{4}\s+\d{1,2}:\d{2}(?::\d{2})?(?:\s*[AaPp][Mm])?$/',
        ],
        self::TYPE_DATE => [
            '/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}$/',
            '/^\d{1,2}[-\/]\d{1,2}[-\/]\d{4}$/',
            '/^\d{4}年\d{1,2}月\d{1,2}日$/',
        ],
        self::TYPE_TIME => [
            '/^\d{1,2}:\d{2}(?::\d{2})?(?:\s*[AaPp][Mm])?$/',
            '/^\d{1,2}时\d{1,2}分(?:\d{1,2}秒)?$/',
        ],
    ];

    // 货币符号映射
    private array $currencySymbols = [
        '¥' => 'CNY',
        '$' => 'USD',
        '€' => 'EUR',
        '£' => 'GBP',
        '₹' => 'INR',
        '₽' => 'RUB',
        '¢' => 'USD_CENT',
    ];

    // 日期格式模式
    private array $dateFormats = [
        'Y-m-d',
        'Y/m/d',
        'm-d-Y',
        'm/d/Y',
        'd-m-Y',
        'd/m/Y',
        'Y年m月d日',
    ];

    // 时间格式模式
    private array $timeFormats = [
        'H:i:s',
        'H:i',
        'h:i:s A',
        'h:i A',
        'H时i分s秒',
        'H时i分',
    ];

    private bool $strictMode;
    private array $customConverters = [];
    private array $typeCache = [];

    public function __construct(bool $strictMode = false)
    {
        $this->strictMode = $strictMode;
    }

    /**
     * 检测单个值的类型
     * 
     * @param mixed $value 要检测的值
     * @param array $context 上下文信息（列索引、行索引等）
     * @return array 检测结果
     */
    public function detectType(mixed $value, array $context = []): array
    {
        $startTime = microtime(true);
        
        // 基础类型检查
        if ($value === null || $value === '') {
            return $this->createResult(self::TYPE_NULL, null, 1.0, [
                'detection_time' => microtime(true) - $startTime
            ]);
        }

        // 字符串标准化
        $stringValue = is_string($value) ? trim($value) : (string)$value;
        
        // 检查缓存
        $cacheKey = md5($stringValue . serialize($context));
        if (isset($this->typeCache[$cacheKey])) {
            return $this->typeCache[$cacheKey];
        }

        // 类型检测
        $result = $this->performTypeDetection($stringValue, $context);
        $result['detection_time'] = microtime(true) - $startTime;
        
        // 缓存结果
        $this->typeCache[$cacheKey] = $result;
        
        return $result;
    }

    /**
     * 执行类型检测
     */
    private function performTypeDetection(string $value, array $context): array
    {
        $candidates = [];
        
        // 遍历所有类型模式
        foreach ($this->patterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    $candidates[] = [
                        'type' => $type,
                        'confidence' => $this->calculateConfidence($type, $value, $context),
                        'pattern' => $pattern
                    ];
                    break; // 匹配到一个即可，避免重复
                }
            }
        }

        // 如果没有匹配，默认为字符串
        if (empty($candidates)) {
            return $this->createResult(self::TYPE_STRING, $value, 1.0);
        }

        // 按优先级和置信度排序
        usort($candidates, function($a, $b) {
            $priorityDiff = $this->typePriority[$b['type']] - $this->typePriority[$a['type']];
            if ($priorityDiff !== 0) {
                return $priorityDiff;
            }
            return $b['confidence'] <=> $a['confidence'];
        });

        $bestCandidate = $candidates[0];
        $convertedValue = $this->convertValue($value, $bestCandidate['type']);
        
        return $this->createResult(
            $bestCandidate['type'],
            $convertedValue,
            $bestCandidate['confidence'],
            [
                'original_value' => $value,
                'pattern_matched' => $bestCandidate['pattern'],
                'candidates_count' => count($candidates)
            ]
        );
    }

    /**
     * 计算类型检测的置信度
     */
    private function calculateConfidence(string $type, string $value, array $context): float
    {
        $baseConfidence = 0.8;
        
        switch ($type) {
            case self::TYPE_BOOLEAN:
                // 完全匹配的布尔值置信度更高
                if (in_array(strtolower($value), ['true', 'false', '1', '0'])) {
                    return 0.95;
                }
                return 0.8;
                
            case self::TYPE_INTEGER:
                // 检查是否在合理范围内
                $intValue = (int)str_replace(',', '', $value);
                if ($intValue > PHP_INT_MAX || $intValue < PHP_INT_MIN) {
                    return 0.6; // 超出范围降低置信度
                }
                return 0.9;
                
            case self::TYPE_FLOAT:
                return 0.85;
                
            case self::TYPE_DATETIME:
                // 尝试实际解析日期时间
                return $this->validateDateTime($value) ? 0.95 : 0.6;
                
            case self::TYPE_DATE:
                return $this->validateDate($value) ? 0.9 : 0.6;
                
            case self::TYPE_TIME:
                return $this->validateTime($value) ? 0.9 : 0.6;
                
            case self::TYPE_EMAIL:
                // 使用PHP内置验证增强置信度
                return filter_var($value, FILTER_VALIDATE_EMAIL) ? 0.95 : 0.7;
                
            case self::TYPE_URL:
                return filter_var($value, FILTER_VALIDATE_URL) ? 0.95 : 0.7;
                
            case self::TYPE_CURRENCY:
                return 0.85;
                
            case self::TYPE_PERCENTAGE:
                return 0.85;
                
            case self::TYPE_SCIENTIFIC:
                return 0.9;
                
            default:
                return $baseConfidence;
        }
    }

    /**
     * 转换值到指定类型
     */
    private function convertValue(string $value, string $type): mixed
    {
        // 检查自定义转换器
        if (isset($this->customConverters[$type])) {
            return call_user_func($this->customConverters[$type], $value);
        }

        switch ($type) {
            case self::TYPE_NULL:
                return null;
                
            case self::TYPE_BOOLEAN:
                return $this->convertToBoolean($value);
                
            case self::TYPE_INTEGER:
                return (int)str_replace(',', '', $value);
                
            case self::TYPE_FLOAT:
                return (float)str_replace(',', '', $value);
                
            case self::TYPE_PERCENTAGE:
                $numericValue = (float)str_replace(['%', ','], '', $value);
                return $numericValue / 100; // 转换为小数
                
            case self::TYPE_CURRENCY:
                return $this->convertToCurrency($value);
                
            case self::TYPE_SCIENTIFIC:
                return (float)$value;
                
            case self::TYPE_DATETIME:
                return $this->convertToDateTime($value);
                
            case self::TYPE_DATE:
                return $this->convertToDate($value);
                
            case self::TYPE_TIME:
                return $this->convertToTime($value);
                
            default:
                return $value; // 保持原值
        }
    }

    /**
     * 转换布尔值
     */
    private function convertToBoolean(string $value): bool
    {
        $lowerValue = strtolower($value);
        return in_array($lowerValue, ['true', 'yes', 'y', '1', '是', '真']);
    }

    /**
     * 转换货币值
     */
    private function convertToCurrency(string $value): array
    {
        // 提取货币符号和数值 - 修复空格处理和可选小数部分
        $pattern = '/^(¥|\$|€|£|₹|₽|¢)\s*([+-]?\d{1,3}(?:,\d{3})*(?:\.\d{1,4})?)|([+-]?\d{1,3}(?:,\d{3})*(?:\.\d{1,4})?)\s*(¥|\$|€|£|₹|₽|¢)$/u';
        if (preg_match($pattern, $value, $matches)) {
            // 前缀符号格式 (¥1,000)
            if (!empty($matches[1]) && !empty($matches[2])) {
                $symbol = $matches[1];
                $amount = (float)str_replace(',', '', $matches[2]);
            }
            // 后缀符号格式 (1,000¥)
            elseif (!empty($matches[3]) && !empty($matches[4])) {
                $symbol = $matches[4];
                $amount = (float)str_replace(',', '', $matches[3]);
            }
            else {
                return ['amount' => 0.0, 'currency' => 'UNKNOWN', 'symbol' => '', 'formatted' => $value];
            }
            
            $currency = $this->currencySymbols[$symbol] ?? 'UNKNOWN';
            
            return [
                'amount' => $amount,
                'currency' => $currency,
                'symbol' => $symbol,
                'formatted' => $value
            ];
        }
        
        return ['amount' => 0.0, 'currency' => 'UNKNOWN', 'symbol' => '', 'formatted' => $value];
    }

    /**
     * 转换日期时间
     */
    private function convertToDateTime(string $value): ?\DateTime
    {
        foreach ($this->dateFormats as $format) {
            foreach ($this->timeFormats as $timeFormat) {
                $fullFormat = $format . ' ' . $timeFormat;
                $dateTime = \DateTime::createFromFormat($fullFormat, $value);
                if ($dateTime && $dateTime->format($fullFormat) === $value) {
                    return $dateTime;
                }
            }
        }
        
        // 尝试strtotime作为后备
        $timestamp = strtotime($value);
        return $timestamp ? new \DateTime("@$timestamp") : null;
    }

    /**
     * 转换日期
     */
    private function convertToDate(string $value): ?\DateTime
    {
        foreach ($this->dateFormats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return $date;
            }
        }
        
        return null;
    }

    /**
     * 转换时间
     */
    private function convertToTime(string $value): ?\DateTime
    {
        foreach ($this->timeFormats as $format) {
            $time = \DateTime::createFromFormat($format, $value);
            if ($time) {
                return $time;
            }
        }
        
        return null;
    }

    /**
     * 验证日期时间格式
     */
    private function validateDateTime(string $value): bool
    {
        return $this->convertToDateTime($value) !== null;
    }

    /**
     * 验证日期格式
     */
    private function validateDate(string $value): bool
    {
        return $this->convertToDate($value) !== null;
    }

    /**
     * 验证时间格式
     */
    private function validateTime(string $value): bool
    {
        return $this->convertToTime($value) !== null;
    }

    /**
     * 创建检测结果
     */
    private function createResult(string $type, mixed $value, float $confidence, array $metadata = []): array
    {
        return [
            'type' => $type,
            'value' => $value,
            'confidence' => $confidence,
            'metadata' => $metadata
        ];
    }

    /**
     * 批量类型检测
     */
    public function detectBatch(array $values, array $context = []): array
    {
        $results = [];
        foreach ($values as $index => $value) {
            $itemContext = array_merge($context, ['index' => $index]);
            $results[$index] = $this->detectType($value, $itemContext);
        }
        return $results;
    }

    /**
     * 列类型推断（基于多个样本）
     */
    public function inferColumnType(array $columnValues, float $threshold = 0.7): array
    {
        if (empty($columnValues)) {
            return $this->createResult(self::TYPE_STRING, null, 0.0);
        }

        $typeCounts = [];
        $totalConfidence = [];
        
        foreach ($columnValues as $value) {
            $result = $this->detectType($value);
            $type = $result['type'];
            $confidence = $result['confidence'];
            
            if (!isset($typeCounts[$type])) {
                $typeCounts[$type] = 0;
                $totalConfidence[$type] = 0.0;
            }
            
            $typeCounts[$type]++;
            $totalConfidence[$type] += $confidence;
        }

        // 计算每种类型的平均置信度和占比
        $typeScores = [];
        $totalSamples = count($columnValues);
        
        foreach ($typeCounts as $type => $count) {
            $ratio = $count / $totalSamples;
            $avgConfidence = $totalConfidence[$type] / $count;
            $score = $ratio * $avgConfidence;
            
            $typeScores[$type] = [
                'type' => $type,
                'count' => $count,
                'ratio' => $ratio,
                'avg_confidence' => $avgConfidence,
                'score' => $score
            ];
        }

        // 按分数排序
        uasort($typeScores, fn($a, $b) => $b['score'] <=> $a['score']);
        
        $bestType = array_key_first($typeScores);
        $bestScore = $typeScores[$bestType];
        
        // 如果最佳类型的占比低于阈值，则降级为混合类型或字符串
        if ($bestScore['ratio'] < $threshold) {
            return $this->createResult(self::TYPE_STRING, null, $bestScore['avg_confidence'], [
                'inferred_from_samples' => $totalSamples,
                'type_distribution' => $typeScores,
                'reason' => 'mixed_types_below_threshold'
            ]);
        }

        return $this->createResult($bestType, null, $bestScore['avg_confidence'], [
            'inferred_from_samples' => $totalSamples,
            'type_distribution' => $typeScores,
            'primary_type_ratio' => $bestScore['ratio']
        ]);
    }

    /**
     * 添加自定义类型转换器
     */
    public function addCustomConverter(string $type, callable $converter): self
    {
        $this->customConverters[$type] = $converter;
        return $this;
    }

    /**
     * 清除类型缓存
     */
    public function clearCache(): self
    {
        $this->typeCache = [];
        return $this;
    }

    /**
     * 获取支持的类型列表
     */
    public function getSupportedTypes(): array
    {
        return array_keys($this->typePriority);
    }

    /**
     * 设置严格模式
     */
    public function setStrictMode(bool $strict): self
    {
        $this->strictMode = $strict;
        return $this;
    }

    /**
     * 检测整行数据的类型
     * 
     * @param array $rowData 行数据
     * @return array 类型检测后的行数据
     */
    public function detectRowTypes(array $rowData): array
    {
        $typedRow = [];
        
        foreach ($rowData as $columnIndex => $value) {
            $context = ['column_index' => $columnIndex];
            $typeResult = $this->detectType($value, $context);
            
            // 使用转换后的值，如果转换失败则保持原值
            $typedRow[$columnIndex] = $typeResult['converted_value'] ?? $value;
        }
        
        return $typedRow;
    }

    /**
     * 获取统计信息
     * 
     * @return array 统计数据
     */
    public function getStats(): array
    {
        return [
            'cache_size' => count($this->typeCache),
            'strict_mode' => $this->strictMode,
            'supported_types' => count($this->getSupportedTypes()),
            'custom_converters' => count($this->customConverters),
            'type_priorities' => $this->typePriority,
        ];
    }
} 