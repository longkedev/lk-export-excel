<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Tests\Performance;

use PHPUnit\Framework\TestCase;
use LkExcel\LkExportExcel\Performance\PerformanceMonitor;

class PerformanceMonitorTest extends TestCase
{
    private PerformanceMonitor $monitor;

    protected function setUp(): void
    {
        $this->monitor = new PerformanceMonitor(true);
    }

    public function testConstructorSetsEnabledState(): void
    {
        $enabledMonitor = new PerformanceMonitor(true);
        $this->assertTrue($enabledMonitor->isEnabled());

        $disabledMonitor = new PerformanceMonitor(false);
        $this->assertFalse($disabledMonitor->isEnabled());
    }

    public function testStartAndEndTimer(): void
    {
        $this->monitor->startTimer('test_operation');
        
        // 模拟一些工作
        usleep(10000); // 10ms
        
        $duration = $this->monitor->endTimer('test_operation');
        
        $this->assertIsFloat($duration);
        $this->assertGreaterThan(0, $duration);
        $this->assertLessThan(1.0, $duration); // 应该小于1秒
    }

    public function testMeasureFunction(): void
    {
        $result = $this->monitor->measure('test_callback', function() {
            usleep(5000); // 5ms
            return 'test_result';
        });
        
        $this->assertEquals('test_result', $result);
        
        $metrics = $this->monitor->getMetrics('test_callback');
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('duration', $metrics);
        $this->assertArrayHasKey('memory_used', $metrics);
        $this->assertGreaterThan(0, $metrics['duration']);
    }

    public function testMemorySnapshotCapture(): void
    {
        $this->monitor->captureMemorySnapshot('test_snapshot');
        
        $snapshots = $this->monitor->getMemorySnapshots();
        $this->assertArrayHasKey('test_snapshot', $snapshots);
        $this->assertArrayHasKey('current', $snapshots['test_snapshot']);
        $this->assertArrayHasKey('peak', $snapshots['test_snapshot']);
        $this->assertArrayHasKey('timestamp', $snapshots['test_snapshot']);
    }

    public function testGetMemoryUsage(): void
    {
        $memoryUsage = $this->monitor->getMemoryUsage();
        
        $this->assertIsArray($memoryUsage);
        $this->assertArrayHasKey('current', $memoryUsage);
        $this->assertArrayHasKey('peak', $memoryUsage);
        $this->assertArrayHasKey('current_formatted', $memoryUsage);
        $this->assertArrayHasKey('peak_formatted', $memoryUsage);
        
        $this->assertIsInt($memoryUsage['current']);
        $this->assertIsInt($memoryUsage['peak']);
        $this->assertIsString($memoryUsage['current_formatted']);
        $this->assertIsString($memoryUsage['peak_formatted']);
    }

    public function testGenerateReport(): void
    {
        $this->monitor->measure('test_operation_1', function() {
            usleep(1000);
            return true;
        });
        
        $this->monitor->measure('test_operation_2', function() {
            usleep(2000);
            return true;
        });
        
        $report = $this->monitor->generateReport();
        
        $this->assertIsArray($report);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('operations', $report);
        $this->assertArrayHasKey('memory_snapshots', $report);
        
        $this->assertEquals(2, $report['summary']['total_operations']);
        $this->assertArrayHasKey('test_operation_1', $report['operations']);
        $this->assertArrayHasKey('test_operation_2', $report['operations']);
    }

    public function testCompareMetrics(): void
    {
        // 创建两个操作进行比较
        $this->monitor->measure('baseline_operation', function() {
            usleep(20000); // 20ms
            $data = array_fill(0, 1000, 'test');
            return $data;
        });
        
        $this->monitor->measure('optimized_operation', function() {
            usleep(10000); // 10ms
            $data = array_fill(0, 500, 'test');
            return $data;
        });
        
        $comparison = $this->monitor->compareMetrics('baseline_operation', 'optimized_operation');
        
        $this->assertIsArray($comparison);
        $this->assertArrayHasKey('duration_improvement', $comparison);
        $this->assertArrayHasKey('memory_improvement', $comparison);
        $this->assertArrayHasKey('peak_memory_improvement', $comparison);
        
        // 检查改进百分比格式
        $this->assertMatchesRegularExpression('/^[+-]?\d+(\.\d+)?%$/', $comparison['duration_improvement']);
    }

    public function testDisabledMonitor(): void
    {
        $disabledMonitor = new PerformanceMonitor(false);
        
        $disabledMonitor->startTimer('test');
        $duration = $disabledMonitor->endTimer('test');
        
        $this->assertEquals(0.0, $duration);
        $this->assertEmpty($disabledMonitor->getMetrics());
    }

    public function testSetEnabled(): void
    {
        $this->monitor->setEnabled(false);
        $this->assertFalse($this->monitor->isEnabled());
        
        $this->monitor->setEnabled(true);
        $this->assertTrue($this->monitor->isEnabled());
    }

    public function testReset(): void
    {
        $this->monitor->measure('test_operation', function() {
            return true;
        });
        
        $this->monitor->captureMemorySnapshot('test_snapshot');
        
        // 确认有数据
        $this->assertNotEmpty($this->monitor->getMetrics());
        $this->assertNotEmpty($this->monitor->getMemorySnapshots());
        
        // 重置
        $this->monitor->reset();
        
        // 确认数据被清空（除了initial快照）
        $this->assertEmpty($this->monitor->getMetrics());
        $snapshots = $this->monitor->getMemorySnapshots();
        $this->assertArrayHasKey('initial', $snapshots); // 重置后会重新创建initial快照
        $this->assertArrayNotHasKey('test_snapshot', $snapshots);
    }

    public function testEndTimerWithoutStart(): void
    {
        $duration = $this->monitor->endTimer('non_existent_timer');
        $this->assertEquals(0.0, $duration);
    }

    public function testGetNonExistentMetric(): void
    {
        $metrics = $this->monitor->getMetrics('non_existent');
        $this->assertEmpty($metrics);
    }

    public function testCompareNonExistentMetrics(): void
    {
        $comparison = $this->monitor->compareMetrics('non_existent_1', 'non_existent_2');
        $this->assertArrayHasKey('error', $comparison);
        $this->assertEquals('One or both metrics not found', $comparison['error']);
    }

    public function testMemoryIntensiveOperation(): void
    {
        $result = $this->monitor->measure('memory_intensive', function() {
            $data = [];
            for ($i = 0; $i < 10000; $i++) {
                $data[] = str_repeat('x', 100);
            }
            return count($data);
        });
        
        $this->assertEquals(10000, $result);
        
        $metrics = $this->monitor->getMetrics('memory_intensive');
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('memory_used', $metrics);
        $this->assertArrayHasKey('duration', $metrics);
        // 内存使用可能为0（由于垃圾回收），但应该是非负数
        $this->assertGreaterThanOrEqual(0, $metrics['memory_used']);
        $this->assertGreaterThan(0, $metrics['duration']);
    }

    public function testPerformanceRatingCalculation(): void
    {
        // 测试快速低内存操作
        $this->monitor->measure('excellent_operation', function() {
            return 'fast';
        });
        
        // 测试慢速高内存操作
        $this->monitor->measure('poor_operation', function() {
            usleep(100000); // 100ms
            $data = array_fill(0, 100000, str_repeat('x', 1000));
            return count($data);
        });
        
        $report = $this->monitor->generateReport();
        
        $excellentRating = $report['operations']['excellent_operation']['performance_rating'];
        $poorRating = $report['operations']['poor_operation']['performance_rating'];
        
        $this->assertContains($excellentRating, ['Excellent', 'Good', 'Fair', 'Poor']);
        $this->assertContains($poorRating, ['Excellent', 'Good', 'Fair', 'Poor']);
    }

    public function testConcurrentTimers(): void
    {
        $this->monitor->startTimer('timer1');
        $this->monitor->startTimer('timer2');
        
        usleep(5000);
        $duration1 = $this->monitor->endTimer('timer1');
        
        usleep(5000);
        $duration2 = $this->monitor->endTimer('timer2');
        
        $this->assertGreaterThan(0, $duration1);
        $this->assertGreaterThan(0, $duration2);
        $this->assertGreaterThan($duration1, $duration2); // timer2 运行时间更长
    }
} 