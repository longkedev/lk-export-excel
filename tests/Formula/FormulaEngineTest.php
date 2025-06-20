<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Tests\Formula;

use LkExcel\LkExportExcel\Formula\FormulaEngine;
use PHPUnit\Framework\TestCase;

class FormulaEngineTest extends TestCase
{
    private FormulaEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new FormulaEngine();
        
        // 设置简单的单元格解析器
        $this->engine->setCellResolver(function ($cellAddress) {
            $cells = [
                'A1' => 10,
                'A2' => 20,
                'A3' => 30,
                'B1' => 'Hello',
                'B2' => 'World',
                'C1' => true,
                'C2' => false,
                'D1' => 3.14,
                'D2' => 2.71
            ];
            
            return $cells[$cellAddress] ?? 0;
        });
    }

    public function testConstructor(): void
    {
        $engine = new FormulaEngine(5000, false);
        $stats = $engine->getCacheStats();
        
        $this->assertEquals(5000, $stats['max_cache_size']);
        $this->assertFalse($stats['cache_enabled']);
    }

    public function testSimpleNumbers(): void
    {
        $this->assertEquals(42, $this->engine->calculate('42'));
        $this->assertEquals(3.14, $this->engine->calculate('3.14'));
        $this->assertEquals(-5, $this->engine->calculate('-5'));
    }

    public function testSimpleStrings(): void
    {
        $this->assertEquals('Hello', $this->engine->calculate('"Hello"'));
        $this->assertEquals('', $this->engine->calculate('""'));
    }

    public function testBooleanValues(): void
    {
        $this->assertTrue($this->engine->calculate('TRUE'));
        $this->assertFalse($this->engine->calculate('FALSE'));
    }

    public function testCellReferences(): void
    {
        $this->assertEquals(10, $this->engine->calculate('A1'));
        $this->assertEquals(20, $this->engine->calculate('A2'));
        $this->assertEquals('Hello', $this->engine->calculate('B1'));
        $this->assertTrue($this->engine->calculate('C1'));
    }

    public function testBasicArithmetic(): void
    {
        $this->assertEquals(7, $this->engine->calculate('3 + 4'));
        $this->assertEquals(-1, $this->engine->calculate('3 - 4'));
        $this->assertEquals(12, $this->engine->calculate('3 * 4'));
        $this->assertEquals(2, $this->engine->calculate('8 / 4'));
        $this->assertEquals(8, $this->engine->calculate('2 ^ 3'));
        $this->assertEquals(1, $this->engine->calculate('5 % 2'));
    }

    public function testArithmeticWithCells(): void
    {
        $this->assertEquals(30, $this->engine->calculate('A1 + A2')); // 10 + 20
        $this->assertEquals(200, $this->engine->calculate('A1 * A2')); // 10 * 20
        $this->assertEquals(2, $this->engine->calculate('A2 / A1')); // 20 / 10
    }

    public function testComparisonOperators(): void
    {
        $this->assertTrue($this->engine->calculate('5 > 3'));
        $this->assertFalse($this->engine->calculate('3 > 5'));
        $this->assertTrue($this->engine->calculate('5 >= 5'));
        $this->assertTrue($this->engine->calculate('3 < 5'));
        $this->assertFalse($this->engine->calculate('5 < 3'));
        $this->assertTrue($this->engine->calculate('3 <= 3'));
        $this->assertTrue($this->engine->calculate('5 = 5'));
        $this->assertTrue($this->engine->calculate('3 <> 5'));
        $this->assertFalse($this->engine->calculate('5 <> 5'));
    }

    public function testStringConcatenation(): void
    {
        $this->assertEquals('HelloWorld', $this->engine->calculate('"Hello" & "World"'));
        $this->assertEquals('Hello10', $this->engine->calculate('"Hello" & 10'));
    }

    public function testSumFunction(): void
    {
        $this->assertEquals(15, $this->engine->calculate('SUM(1, 2, 3, 4, 5)'));
        $this->assertEquals(60, $this->engine->calculate('SUM(A1, A2, A3)')); // 10 + 20 + 30
    }

    public function testAverageFunction(): void
    {
        $this->assertEquals(3, $this->engine->calculate('AVERAGE(1, 2, 3, 4, 5)'));
        $this->assertEquals(20, $this->engine->calculate('AVERAGE(A1, A2, A3)')); // (10 + 20 + 30) / 3
    }

    public function testMinMaxFunctions(): void
    {
        $this->assertEquals(1, $this->engine->calculate('MIN(1, 2, 3, 4, 5)'));
        $this->assertEquals(5, $this->engine->calculate('MAX(1, 2, 3, 4, 5)'));
        $this->assertEquals(10, $this->engine->calculate('MIN(A1, A2, A3)')); // min(10, 20, 30)
        $this->assertEquals(30, $this->engine->calculate('MAX(A1, A2, A3)')); // max(10, 20, 30)
    }

    public function testCountFunction(): void
    {
        $this->assertEquals(5, $this->engine->calculate('COUNT(1, 2, 3, 4, 5)'));
        $this->assertEquals(3, $this->engine->calculate('COUNT(A1, A2, A3)'));
        $this->assertEquals(2, $this->engine->calculate('COUNT(A1, "text", A2)')); // 只计算数字
    }

    public function testMathFunctions(): void
    {
        $this->assertEquals(5, $this->engine->calculate('ABS(-5)'));
        $this->assertEquals(5, $this->engine->calculate('ABS(5)'));
        $this->assertEquals(3.14, $this->engine->calculate('ROUND(3.14159, 2)'));
        $this->assertEquals(8, $this->engine->calculate('POWER(2, 3)'));
        $this->assertEquals(3, $this->engine->calculate('SQRT(9)'));
    }

    public function testLogicalFunctions(): void
    {
        $this->assertTrue($this->engine->calculate('AND(TRUE, TRUE)'));
        $this->assertFalse($this->engine->calculate('AND(TRUE, FALSE)'));
        $this->assertTrue($this->engine->calculate('OR(TRUE, FALSE)'));
        $this->assertFalse($this->engine->calculate('OR(FALSE, FALSE)'));
        $this->assertFalse($this->engine->calculate('NOT(TRUE)'));
        $this->assertTrue($this->engine->calculate('NOT(FALSE)'));
    }

    public function testIfFunction(): void
    {
        $this->assertEquals('Yes', $this->engine->calculate('IF(TRUE, "Yes", "No")'));
        $this->assertEquals('No', $this->engine->calculate('IF(FALSE, "Yes", "No")'));
        $this->assertEquals('Greater', $this->engine->calculate('IF(5 > 3, "Greater", "Lesser")'));
    }

    public function testTextFunctions(): void
    {
        $this->assertEquals(5, $this->engine->calculate('LEN("Hello")'));
        $this->assertEquals('Hel', $this->engine->calculate('LEFT("Hello", 3)'));
        $this->assertEquals('llo', $this->engine->calculate('RIGHT("Hello", 3)'));
        $this->assertEquals('HELLO', $this->engine->calculate('UPPER("hello")'));
        $this->assertEquals('hello', $this->engine->calculate('LOWER("HELLO")'));
        $this->assertEquals('HelloWorld', $this->engine->calculate('CONCATENATE("Hello", "World")'));
    }

    public function testDateTimeFunctions(): void
    {
        $now = $this->engine->calculate('NOW()');
        $this->assertInstanceOf(\DateTime::class, $now);
        
        $today = $this->engine->calculate('TODAY()');
        $this->assertInstanceOf(\DateTime::class, $today);
        
        $now = new \DateTime();
        $this->engine->setCellResolver(function ($cell) use ($now) {
            return $cell === 'E1' ? $now : 0;
        });
        
        $this->assertEquals((int)$now->format('Y'), $this->engine->calculate('YEAR(E1)'));
        $this->assertEquals((int)$now->format('n'), $this->engine->calculate('MONTH(E1)'));
        $this->assertEquals((int)$now->format('j'), $this->engine->calculate('DAY(E1)'));
    }

    public function testComplexFormulas(): void
    {
        // 嵌套函数
        $this->assertEquals(25, $this->engine->calculate('POWER(ABS(-5), 2)'));
        
        // 混合运算
        $this->assertEquals(65, $this->engine->calculate('SUM(A1, A2, A3) + 5')); // (10+20+30) + 5
        
        // 条件运算
        $this->assertEquals(30, $this->engine->calculate('IF(A3 > A2, A3, A2)')); // max(A2, A3)
    }

    public function testParentheses(): void
    {
        $this->assertEquals(14, $this->engine->calculate('2 * (3 + 4)'));
        $this->assertEquals(10, $this->engine->calculate('(2 + 3) * 2'));
        $this->assertEquals(11, $this->engine->calculate('2 + 3 * 3')); // 运算符优先级
    }

    public function testDivisionByZero(): void
    {
        $this->assertEquals(0, $this->engine->calculate('5 / 0'));
        $this->assertEquals(0, $this->engine->calculate('10 % 0'));
    }

    public function testCircularReferenceDetection(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('检测到循环引用');
        
        // 模拟循环引用
        $this->engine->setCellResolver(function ($cell) {
            if ($cell === 'F1') {
                return $this->engine->calculate('F1', 'F1'); // 自引用
            }
            return 0;
        });
        
        $this->engine->calculate('F1', 'F1');
    }

    public function testCaching(): void
    {
        $engine = new FormulaEngine(100, true);
        
        // 首次计算
        $result1 = $engine->calculate('1 + 2 + 3');
        $stats1 = $engine->getCacheStats();
        
        // 相同计算（应该使用缓存）
        $result2 = $engine->calculate('1 + 2 + 3');
        $stats2 = $engine->getCacheStats();
        
        $this->assertEquals($result1, $result2);
        $this->assertEquals(6, $result1);
    }

    public function testClearCache(): void
    {
        $this->engine->calculate('1 + 1');
        $statsBefore = $this->engine->getCacheStats();
        
        $this->engine->clearCache();
        $statsAfter = $this->engine->getCacheStats();
        
        $this->assertEquals(0, $statsAfter['result_cache_size']);
        $this->assertEquals(0, $statsAfter['formula_cache_size']);
    }

    public function testCustomFunction(): void
    {
        $this->engine->addFunction('DOUBLE', function ($args) {
            if (is_array($args)) {
                $value = $args[0] ?? 0;
            } else {
                $value = empty($args) ? 0 : array_pop($args);
            }
            return is_numeric($value) ? $value * 2 : 0;
        });
        
        $this->assertEquals(10, $this->engine->calculate('DOUBLE(5)'));
        $this->assertContains('DOUBLE', $this->engine->getSupportedFunctions());
    }

    public function testGetSupportedFunctions(): void
    {
        $functions = $this->engine->getSupportedFunctions();
        
        $this->assertIsArray($functions);
        $this->assertContains('SUM', $functions);
        $this->assertContains('AVERAGE', $functions);
        $this->assertContains('IF', $functions);
        $this->assertContains('AND', $functions);
        $this->assertContains('LEN', $functions);
        $this->assertContains('NOW', $functions);
    }

    public function testInvalidOperator(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('未知运算符');
        
        // 手动构造一个会触发未知运算符错误的情况
        $reflection = new \ReflectionClass($this->engine);
        $method = $reflection->getMethod('applyOperator');
        $method->setAccessible(true);
        
        $stack = [1, 2];
        $method->invoke($this->engine, '#', $stack); // 使用无效运算符
    }

    public function testInvalidFunction(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('未知函数');
        
        // 手动构造一个会触发未知函数错误的情况
        $reflection = new \ReflectionClass($this->engine);
        $method = $reflection->getMethod('callFunction');
        $method->setAccessible(true);
        
        $stack = [];
        $method->invoke($this->engine, 'UNKNOWN', $stack);
    }

    public function testInsufficientOperands(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('需要两个操作数');
        
        // 手动构造一个会触发操作数不足错误的情况
        $reflection = new \ReflectionClass($this->engine);
        $method = $reflection->getMethod('applyOperator');
        $method->setAccessible(true);
        
        $stack = [1]; // 只有一个操作数
        $method->invoke($this->engine, '+', $stack);
    }

    public function testCacheStats(): void
    {
        $stats = $this->engine->getCacheStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('formula_cache_size', $stats);
        $this->assertArrayHasKey('result_cache_size', $stats);
        $this->assertArrayHasKey('max_cache_size', $stats);
        $this->assertArrayHasKey('cache_enabled', $stats);
        
        $this->assertEquals(10000, $stats['max_cache_size']); // 默认值
        $this->assertTrue($stats['cache_enabled']); // 默认值
    }
} 