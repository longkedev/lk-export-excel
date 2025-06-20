<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Formula;

/**
 * Excel公式引擎
 * 
 * 负责公式的解析、计算和执行
 * 设计目标：
 * - 支持主要Excel函数
 * - 公式缓存和优化
 * - 循环引用检测
 * - 高性能计算
 */
class FormulaEngine
{
    // 支持的运算符
    public const OPERATORS = [
        '+' => 1,  // 加法
        '-' => 1,  // 减法
        '*' => 2,  // 乘法
        '/' => 2,  // 除法
        '%' => 2,  // 取模
        '^' => 3,  // 幂运算
        '=' => 0,  // 等于
        '<>' => 0, // 不等于
        '<' => 0,  // 小于
        '>' => 0,  // 大于
        '<=' => 0, // 小于等于
        '>=' => 0, // 大于等于
        '&' => 1,  // 字符串连接
    ];

    // 内置函数
    private array $functions = [];
    
    // 公式缓存
    private array $formulaCache = [];
    
    // 计算结果缓存
    private array $resultCache = [];
    
    // 循环引用检测栈
    private array $calculationStack = [];
    
    // 工作表数据引用
    private $cellResolver = null;
    
    private int $maxCacheSize;
    private bool $enableCache;

    public function __construct(int $maxCacheSize = 10000, bool $enableCache = true)
    {
        $this->maxCacheSize = $maxCacheSize;
        $this->enableCache = $enableCache;
        $this->initializeBuiltinFunctions();
    }

    /**
     * 初始化内置函数
     */
    private function initializeBuiltinFunctions(): void
    {
        // 数学函数
        $this->functions['SUM'] = [$this, 'functionSum'];
        $this->functions['AVERAGE'] = [$this, 'functionAverage'];
        $this->functions['MIN'] = [$this, 'functionMin'];
        $this->functions['MAX'] = [$this, 'functionMax'];
        $this->functions['COUNT'] = [$this, 'functionCount'];
        $this->functions['ABS'] = [$this, 'functionAbs'];
        $this->functions['ROUND'] = [$this, 'functionRound'];
        $this->functions['POWER'] = [$this, 'functionPower'];
        $this->functions['SQRT'] = [$this, 'functionSqrt'];
        
        // 逻辑函数
        $this->functions['IF'] = [$this, 'functionIf'];
        $this->functions['AND'] = [$this, 'functionAnd'];
        $this->functions['OR'] = [$this, 'functionOr'];
        $this->functions['NOT'] = [$this, 'functionNot'];
        
        // 文本函数
        $this->functions['LEN'] = [$this, 'functionLen'];
        $this->functions['LEFT'] = [$this, 'functionLeft'];
        $this->functions['RIGHT'] = [$this, 'functionRight'];
        $this->functions['UPPER'] = [$this, 'functionUpper'];
        $this->functions['LOWER'] = [$this, 'functionLower'];
        $this->functions['CONCATENATE'] = [$this, 'functionConcatenate'];
        
        // 日期时间函数
        $this->functions['NOW'] = [$this, 'functionNow'];
        $this->functions['TODAY'] = [$this, 'functionToday'];
        $this->functions['YEAR'] = [$this, 'functionYear'];
        $this->functions['MONTH'] = [$this, 'functionMonth'];
        $this->functions['DAY'] = [$this, 'functionDay'];
    }

    /**
     * 设置单元格值解析器
     */
    public function setCellResolver(callable $resolver): self
    {
        $this->cellResolver = $resolver;
        return $this;
    }

    /**
     * 解析并计算公式
     * 
     * @param string $formula 公式字符串（不包含等号）
     * @param string $cellAddress 当前单元格地址（用于循环引用检测）
     * @return mixed 计算结果
     */
    public function calculate(string $formula, string $cellAddress = ''): mixed
    {
        // 检查循环引用
        if ($cellAddress && in_array($cellAddress, $this->calculationStack)) {
            throw new \RuntimeException("检测到循环引用: " . implode(' -> ', $this->calculationStack) . ' -> ' . $cellAddress);
        }

        // 添加到计算栈
        if ($cellAddress) {
            $this->calculationStack[] = $cellAddress;
        }

        try {
            // 检查缓存
            $cacheKey = md5($formula . '|' . serialize($this->calculationStack));
            if ($this->enableCache && isset($this->resultCache[$cacheKey])) {
                return $this->resultCache[$cacheKey];
            }

            // 解析并计算公式
            $result = $this->parseAndEvaluate($formula);

            // 缓存结果
            if ($this->enableCache) {
                $this->cacheResult($cacheKey, $result);
            }

            return $result;
        } finally {
            // 从计算栈中移除
            if ($cellAddress) {
                array_pop($this->calculationStack);
            }
        }
    }

    /**
     * 解析并求值公式
     */
    private function parseAndEvaluate(string $formula): mixed
    {
        $formula = trim($formula);
        
        // 处理简单值
        if ($this->isSimpleValue($formula)) {
            return $this->parseSimpleValue($formula);
        }

        // 解析表达式
        $tokens = $this->tokenize($formula);
        $rpn = $this->toReversePolishNotation($tokens);
        return $this->evaluateRPN($rpn);
    }

    /**
     * 检查是否为简单值
     */
    private function isSimpleValue(string $value): bool
    {
        // 数字
        if (is_numeric($value)) {
            return true;
        }
        
        // 字符串（被引号包围且中间没有未转义的引号）
        if (preg_match('/^"[^"]*"$/', $value)) {
            return true;
        }
        
        // 布尔值
        if (in_array(strtoupper($value), ['TRUE', 'FALSE'])) {
            return true;
        }
        
        // 单个单元格引用
        if (preg_match('/^[A-Z]+\d+$/', $value)) {
            return true;
        }
        
        return false;
    }

    /**
     * 解析简单值
     */
    private function parseSimpleValue(string $value): mixed
    {
        // 数字
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }
        
        // 字符串
        if (preg_match('/^"(.*)"$/', $value, $matches)) {
            return $matches[1];
        }
        
        // 布尔值
        $upperValue = strtoupper($value);
        if ($upperValue === 'TRUE') {
            return true;
        }
        if ($upperValue === 'FALSE') {
            return false;
        }
        
        // 单元格引用
        if (preg_match('/^[A-Z]+\d+$/', $value)) {
            return $this->resolveCellValue($value);
        }
        
        return $value;
    }

    /**
     * 分词器 - 将公式分解为令牌
     */
    private function tokenize(string $formula): array
    {
        $tokens = [];
        $length = strlen($formula);
        $i = 0;
        
        while ($i < $length) {
            $char = $formula[$i];
            
            // 跳过空白字符
            if (ctype_space($char)) {
                $i++;
                continue;
            }
            
            // 字符串
            if ($char === '"') {
                $string = $char;
                $i++;
                while ($i < $length && $formula[$i] !== '"') {
                    $string .= $formula[$i];
                    $i++;
                }
                if ($i < $length) {
                    $string .= $formula[$i]; // 闭合引号
                    $i++;
                }
                $tokens[] = $string;
                continue;
            }
            
            // 函数或单元格引用
            if (ctype_alpha($char)) {
                $identifier = '';
                while ($i < $length && (ctype_alnum($formula[$i]) || $formula[$i] === '_')) {
                    $identifier .= $formula[$i];
                    $i++;
                }
                $tokens[] = $identifier;
                continue;
            }
            
            // 数字（包括负数）
            if (ctype_digit($char) || $char === '.') {
                $number = '';
                while ($i < $length && (ctype_digit($formula[$i]) || $formula[$i] === '.')) {
                    $number .= $formula[$i];
                    $i++;
                }
                $tokens[] = $number;
                continue;
            }
            
            // 处理负号：如果前面是运算符、左括号、开始或逗号，则为一元负号
            if ($char === '-') {
                $isUnaryMinus = empty($tokens) || 
                    in_array(end($tokens), ['(', ',']) ||
                    isset(self::OPERATORS[end($tokens)]);
                
                if ($isUnaryMinus) {
                    // 检查后面是否是数字
                    if ($i + 1 < $length && (ctype_digit($formula[$i + 1]) || $formula[$i + 1] === '.')) {
                        $number = '-';
                        $i++;
                        while ($i < $length && (ctype_digit($formula[$i]) || $formula[$i] === '.')) {
                            $number .= $formula[$i];
                            $i++;
                        }
                        $tokens[] = $number;
                        continue;
                    }
                }
            }
            
            // 运算符
            if (isset(self::OPERATORS[$char])) {
                // 处理多字符运算符
                if ($char === '<' && $i + 1 < $length) {
                    if ($formula[$i + 1] === '=') {
                        $tokens[] = '<=';
                        $i += 2;
                        continue;
                    }
                    if ($formula[$i + 1] === '>') {
                        $tokens[] = '<>';
                        $i += 2;
                        continue;
                    }
                }
                if ($char === '>' && $i + 1 < $length && $formula[$i + 1] === '=') {
                    $tokens[] = '>=';
                    $i += 2;
                    continue;
                }
                
                $tokens[] = $char;
                $i++;
                continue;
            }
            
            // 括号和分隔符
            if (in_array($char, ['(', ')', ',', ':', ';'])) {
                $tokens[] = $char;
                $i++;
                continue;
            }
            
            // 跳过未识别字符
            $i++;
        }
        
        return $tokens;
    }

    /**
     * 转换为逆波兰表示法
     */
    private function toReversePolishNotation(array $tokens): array
    {
        $output = [];
        $operators = [];
        $argCounts = []; // 跟踪函数参数数量
        
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            
            // 数字或字符串
            if (is_numeric($token) || preg_match('/^".*"$/', $token) || in_array(strtoupper($token), ['TRUE', 'FALSE'])) {
                $output[] = $token;
            }
            // 单元格引用
            elseif (preg_match('/^[A-Z]+\d+$/', $token)) {
                $output[] = $token;
            }
            // 函数
            elseif (isset($this->functions[strtoupper($token)])) {
                $operators[] = strtoupper($token);
                $argCounts[] = 1; // 初始参数计数
            }
            // 左括号
            elseif ($token === '(') {
                $operators[] = $token;
            }
            // 右括号
            elseif ($token === ')') {
                while (!empty($operators) && end($operators) !== '(') {
                    $output[] = array_pop($operators);
                }
                array_pop($operators); // 移除左括号
                
                // 如果栈顶是函数，输出它及其参数数量
                if (!empty($operators) && isset($this->functions[end($operators)])) {
                    $funcName = array_pop($operators);
                    $argCount = array_pop($argCounts);
                    $output[] = $funcName;
                    $output[] = "ARG_COUNT:$argCount"; // 参数数量标记
                }
            }
            // 逗号（函数参数分隔符）
            elseif ($token === ',') {
                while (!empty($operators) && end($operators) !== '(') {
                    $output[] = array_pop($operators);
                }
                // 增加参数计数
                if (!empty($argCounts)) {
                    $argCounts[count($argCounts) - 1]++;
                }
            }
            // 运算符
            elseif (isset(self::OPERATORS[$token])) {
                while (!empty($operators) && 
                       end($operators) !== '(' && 
                       isset(self::OPERATORS[end($operators)]) && 
                       self::OPERATORS[end($operators)] >= self::OPERATORS[$token]) {
                    $output[] = array_pop($operators);
                }
                $operators[] = $token;
            }
        }
        
        // 输出剩余运算符
        while (!empty($operators)) {
            if (!empty($argCounts) && isset($this->functions[end($operators)])) {
                $funcName = array_pop($operators);
                $argCount = array_pop($argCounts);
                $output[] = $funcName;
                $output[] = "ARG_COUNT:$argCount";
            } else {
                $output[] = array_pop($operators);
            }
        }
        
        return $output;
    }

    /**
     * 求值逆波兰表示法
     */
    private function evaluateRPN(array $rpn): mixed
    {
        $stack = [];
        
        for ($i = 0; $i < count($rpn); $i++) {
            $token = $rpn[$i];
            
            // 参数数量标记 - 需要先处理
            if (preg_match('/^ARG_COUNT:(\d+)$/', $token, $matches)) {
                // 前一个应该是函数名
                if ($i > 0) {
                    $funcName = $rpn[$i - 1];
                    $argCount = (int)$matches[1];
                    $result = $this->callFunctionWithArgs($funcName, $stack, $argCount);
                    $stack[] = $result;
                }
                continue; // 跳过这个token
            }
            
            // 数字
            if (is_numeric($token)) {
                $stack[] = strpos($token, '.') !== false ? (float)$token : (int)$token;
            }
            // 字符串
            elseif (preg_match('/^"(.*)"$/', $token, $matches)) {
                $stack[] = $matches[1];
            }
            // 布尔值
            elseif (strtoupper($token) === 'TRUE') {
                $stack[] = true;
            }
            elseif (strtoupper($token) === 'FALSE') {
                $stack[] = false;
            }
            // 单元格引用
            elseif (preg_match('/^[A-Z]+\d+$/', $token)) {
                $stack[] = $this->resolveCellValue($token);
            }
            // 函数（只处理已经在栈中的情况，跳过即将被ARG_COUNT处理的）
            elseif (isset($this->functions[$token])) {
                // 检查下一个token是否是ARG_COUNT
                if ($i + 1 < count($rpn) && preg_match('/^ARG_COUNT:\d+$/', $rpn[$i + 1])) {
                    // 跳过，让ARG_COUNT处理
                    continue;
                } else {
                    // 兼容老方式
                    $result = $this->callFunction($token, $stack);
                    $stack[] = $result;
                }
            }
            // 运算符
            elseif (isset(self::OPERATORS[$token])) {
                $result = $this->applyOperator($token, $stack);
                $stack[] = $result;
            }
        }
        
        return empty($stack) ? 0 : $stack[0];
    }

    /**
     * 应用运算符
     */
    private function applyOperator(string $operator, array &$stack): mixed
    {
        if (count($stack) < 2) {
            throw new \RuntimeException("运算符 '$operator' 需要两个操作数");
        }
        
        $right = array_pop($stack);
        $left = array_pop($stack);
        
        return match ($operator) {
            '+' => $left + $right,
            '-' => $left - $right,
            '*' => $left * $right,
            '/' => $right == 0 ? 0 : $left / $right,
            '%' => $right == 0 ? 0 : $left % $right,
            '^' => pow($left, $right),
            '=' => $left == $right,
            '<>' => $left != $right,
            '<' => $left < $right,
            '>' => $left > $right,
            '<=' => $left <= $right,
            '>=' => $left >= $right,
            '&' => (string)$left . (string)$right,
            default => throw new \RuntimeException("未知运算符: $operator")
        };
    }

    /**
     * 调用函数
     */
    private function callFunction(string $functionName, array &$stack): mixed
    {
        if (!isset($this->functions[$functionName])) {
            throw new \RuntimeException("未知函数: $functionName");
        }
        
        $function = $this->functions[$functionName];
        return call_user_func($function, $stack);
    }

    /**
     * 调用函数（带参数数量）
     */
    private function callFunctionWithArgs(string $functionName, array &$stack, int $argCount): mixed
    {
        if (!isset($this->functions[$functionName])) {
            throw new \RuntimeException("未知函数: $functionName");
        }
        
        // 收集指定数量的参数
        $args = [];
        for ($i = 0; $i < $argCount && !empty($stack); $i++) {
            $args[] = array_pop($stack);
        }
        $args = array_reverse($args);
        
        $function = $this->functions[$functionName];
        return call_user_func($function, $args);
    }

    /**
     * 解析单元格值
     */
    private function resolveCellValue(string $cellAddress): mixed
    {
        if ($this->cellResolver === null) {
            return 0; // 默认返回0
        }
        
        return call_user_func($this->cellResolver, $cellAddress);
    }

    /**
     * 缓存计算结果
     */
    private function cacheResult(string $key, mixed $result): void
    {
        if (count($this->resultCache) >= $this->maxCacheSize) {
            // 清理一半缓存
            $this->resultCache = array_slice($this->resultCache, -($this->maxCacheSize / 2), null, true);
        }
        
        $this->resultCache[$key] = $result;
    }

    /**
     * 清除缓存
     */
    public function clearCache(): self
    {
        $this->formulaCache = [];
        $this->resultCache = [];
        return $this;
    }

    /**
     * 获取缓存统计
     */
    public function getCacheStats(): array
    {
        return [
            'formula_cache_size' => count($this->formulaCache),
            'result_cache_size' => count($this->resultCache),
            'max_cache_size' => $this->maxCacheSize,
            'cache_enabled' => $this->enableCache
        ];
    }

    // === 内置函数实现 ===

    /**
     * SUM函数 - 求和
     */
    private function functionSum($args): mixed
    {
        if (is_array($args)) {
            $values = $args;
        } else {
            // 兼容老方式（从栈收集）
            $values = $this->collectFunctionArgs($args);
        }
        
        $sum = 0;
        foreach ($values as $value) {
            if (is_numeric($value)) {
                $sum += $value;
            }
        }
        return $sum;
    }

    /**
     * AVERAGE函数 - 平均值
     */
    private function functionAverage($args): mixed
    {
        if (is_array($args)) {
            $values = $args;
        } else {
            $values = $this->collectFunctionArgs($args);
        }
        
        $sum = 0;
        $count = 0;
        foreach ($values as $value) {
            if (is_numeric($value)) {
                $sum += $value;
                $count++;
            }
        }
        return $count > 0 ? $sum / $count : 0;
    }

    /**
     * MIN函数 - 最小值
     */
    private function functionMin($args): mixed
    {
        if (is_array($args)) {
            $values = $args;
        } else {
            $values = $this->collectFunctionArgs($args);
        }
        
        $min = null;
        foreach ($values as $value) {
            if (is_numeric($value)) {
                $min = $min === null ? $value : min($min, $value);
            }
        }
        return $min ?? 0;
    }

    /**
     * MAX函数 - 最大值
     */
    private function functionMax($args): mixed
    {
        if (is_array($args)) {
            $values = $args;
        } else {
            $values = $this->collectFunctionArgs($args);
        }
        
        $max = null;
        foreach ($values as $value) {
            if (is_numeric($value)) {
                $max = $max === null ? $value : max($max, $value);
            }
        }
        return $max ?? 0;
    }

    /**
     * COUNT函数 - 计数（只计算数字）
     */
    private function functionCount($args): mixed
    {
        if (is_array($args)) {
            $values = $args;
        } else {
            $values = $this->collectFunctionArgs($args);
        }
        
        $count = 0;
        foreach ($values as $value) {
            if (is_numeric($value)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * ABS函数 - 绝对值
     */
    private function functionAbs($args): mixed
    {
        if (is_array($args)) {
            $value = $args[0] ?? 0;
        } else {
            $value = empty($args) ? 0 : array_pop($args);
        }
        
        return abs($value);
    }

    /**
     * ROUND函数 - 四舍五入
     */
    private function functionRound($args): mixed
    {
        if (is_array($args)) {
            $number = $args[0] ?? 0;
            $precision = $args[1] ?? 0;
        } else {
            if (count($args) < 1) {
                return 0;
            }
            $precision = count($args) >= 2 ? array_pop($args) : 0;
            $number = array_pop($args);
        }
        
        if (!is_numeric($number) || !is_numeric($precision)) {
            return 0;
        }
        
        return round($number, (int)$precision);
    }

    /**
     * POWER函数 - 幂运算
     */
    private function functionPower($args): mixed
    {
        if (is_array($args)) {
            $base = $args[0] ?? 0;
            $exponent = $args[1] ?? 0;
        } else {
            if (count($args) < 2) {
                return 0;
            }
            $exponent = array_pop($args);
            $base = array_pop($args);
        }
        
        if (!is_numeric($base) || !is_numeric($exponent)) {
            return 0;
        }
        
        return pow($base, $exponent);
    }

    /**
     * SQRT函数 - 平方根
     */
    private function functionSqrt($args): mixed
    {
        if (is_array($args)) {
            $value = $args[0] ?? 0;
        } else {
            $value = empty($args) ? 0 : array_pop($args);
        }
        
        if (!is_numeric($value) || $value < 0) {
            return 0;
        }
        
        return sqrt($value);
    }

    /**
     * IF函数 - 条件判断
     */
    private function functionIf($args): mixed
    {
        if (is_array($args)) {
            $condition = $args[0] ?? false;
            $trueValue = $args[1] ?? '';
            $falseValue = $args[2] ?? '';
        } else {
            if (count($args) < 1) {
                return '';
            }
            $falseValue = count($args) >= 3 ? array_pop($args) : '';
            $trueValue = count($args) >= 2 ? array_pop($args) : '';
            $condition = array_pop($args);
        }
        
        return $condition ? $trueValue : $falseValue;
    }

    /**
     * AND函数 - 逻辑与
     */
    private function functionAnd($args): mixed
    {
        if (is_array($args)) {
            $values = $args;
        } else {
            $values = $this->collectFunctionArgs($args);
        }
        
        foreach ($values as $value) {
            if (!$value) {
                return false;
            }
        }
        return true;
    }

    /**
     * OR函数 - 逻辑或
     */
    private function functionOr($args): mixed
    {
        if (is_array($args)) {
            $values = $args;
        } else {
            $values = $this->collectFunctionArgs($args);
        }
        
        foreach ($values as $value) {
            if ($value) {
                return true;
            }
        }
        return false;
    }

    /**
     * NOT函数 - 逻辑非
     */
    private function functionNot($args): mixed
    {
        if (is_array($args)) {
            $value = $args[0] ?? true;
        } else {
            $value = empty($args) ? true : array_pop($args);
        }
        
        return !$value;
    }

    /**
     * LEN函数 - 字符串长度
     */
    private function functionLen($args): mixed
    {
        if (is_array($args)) {
            $value = $args[0] ?? '';
        } else {
            $value = empty($args) ? '' : array_pop($args);
        }
        
        return mb_strlen((string)$value, 'UTF-8');
    }

    /**
     * LEFT函数 - 左边字符串
     */
    private function functionLeft($args): mixed
    {
        if (is_array($args)) {
            $text = $args[0] ?? '';
            $length = $args[1] ?? 0;
        } else {
            if (count($args) < 2) {
                return '';
            }
            $length = array_pop($args);
            $text = array_pop($args);
        }
        
        if (!is_numeric($length)) {
            return '';
        }
        
        return mb_substr((string)$text, 0, (int)$length, 'UTF-8');
    }

    /**
     * RIGHT函数 - 右边字符串
     */
    private function functionRight($args): mixed
    {
        if (is_array($args)) {
            $text = $args[0] ?? '';
            $length = $args[1] ?? 0;
        } else {
            if (count($args) < 2) {
                return '';
            }
            $length = array_pop($args);
            $text = array_pop($args);
        }
        
        if (!is_numeric($length)) {
            return '';
        }
        
        return mb_substr((string)$text, -(int)$length, null, 'UTF-8');
    }

    /**
     * UPPER函数 - 转大写
     */
    private function functionUpper($args): mixed
    {
        if (is_array($args)) {
            $value = $args[0] ?? '';
        } else {
            $value = empty($args) ? '' : array_pop($args);
        }
        
        return mb_strtoupper((string)$value, 'UTF-8');
    }

    /**
     * LOWER函数 - 转小写
     */
    private function functionLower($args): mixed
    {
        if (is_array($args)) {
            $value = $args[0] ?? '';
        } else {
            $value = empty($args) ? '' : array_pop($args);
        }
        
        return mb_strtolower((string)$value, 'UTF-8');
    }

    /**
     * CONCATENATE函数 - 字符串连接
     */
    private function functionConcatenate($args): mixed
    {
        if (is_array($args)) {
            $values = $args;
        } else {
            $values = $this->collectFunctionArgs($args);
        }
        
        return implode('', array_map('strval', $values));
    }

    /**
     * NOW函数 - 当前日期时间（返回DateTime对象）
     */
    private function functionNow($args): mixed
    {
        return new \DateTime();
    }

    /**
     * TODAY函数 - 今天日期（返回DateTime对象）
     */
    private function functionToday($args): mixed
    {
        return new \DateTime('today');
    }

    /**
     * YEAR函数 - 年份
     */
    private function functionYear($args): mixed
    {
        if (is_array($args)) {
            $value = $args[0] ?? null;
        } else {
            $value = empty($args) ? null : array_pop($args);
        }
        
        if ($value instanceof \DateTime) {
            return (int)$value->format('Y');
        }
        
        if (is_numeric($value)) {
            return (int)date('Y', (int)$value);
        }
        
        return (int)date('Y');
    }

    /**
     * MONTH函数 - 月份
     */
    private function functionMonth($args): mixed
    {
        if (is_array($args)) {
            $value = $args[0] ?? null;
        } else {
            $value = empty($args) ? null : array_pop($args);
        }
        
        if ($value instanceof \DateTime) {
            return (int)$value->format('n');
        }
        
        if (is_numeric($value)) {
            return (int)date('n', (int)$value);
        }
        
        return (int)date('n');
    }

    /**
     * DAY函数 - 日
     */
    private function functionDay($args): mixed
    {
        if (is_array($args)) {
            $value = $args[0] ?? null;
        } else {
            $value = empty($args) ? null : array_pop($args);
        }
        
        if ($value instanceof \DateTime) {
            return (int)$value->format('j');
        }
        
        if (is_numeric($value)) {
            return (int)date('j', (int)$value);
        }
        
        return (int)date('j');
    }

    /**
     * 收集函数参数（修改为接收参数数组）
     */
    private function collectFunctionArgs(array $args): array
    {
        return $args;
    }

    /**
     * 添加自定义函数
     */
    public function addFunction(string $name, callable $function): self
    {
        $this->functions[strtoupper($name)] = $function;
        return $this;
    }

    /**
     * 获取支持的函数列表
     */
    public function getSupportedFunctions(): array
    {
        return array_keys($this->functions);
    }
} 