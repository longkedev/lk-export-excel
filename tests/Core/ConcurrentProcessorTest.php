<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Tests\Core;

use PHPUnit\Framework\TestCase;
use LkExcel\LkExportExcel\Core\ConcurrentProcessor;

class ConcurrentProcessorTest extends TestCase
{
    private ConcurrentProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new ConcurrentProcessor(2); // 最大2个工作者
    }

    public function testAddTask(): void
    {
        $taskId = $this->processor->addTask(
            function($context) { return $context['value'] * 2; },
            ConcurrentProcessor::PRIORITY_NORMAL,
            ['value' => 5]
        );

        $this->assertIsString($taskId);
        $this->assertStringStartsWith('task_', $taskId);
    }

    public function testTaskPriority(): void
    {
        // 添加不同优先级的任务
        $lowId = $this->processor->addTask(
            function() { return 'low'; },
            ConcurrentProcessor::PRIORITY_LOW
        );

        $highId = $this->processor->addTask(
            function() { return 'high'; },
            ConcurrentProcessor::PRIORITY_HIGH
        );

        $criticalId = $this->processor->addTask(
            function() { return 'critical'; },
            ConcurrentProcessor::PRIORITY_CRITICAL
        );

        $results = $this->processor->processAll();

        // 验证高优先级任务先执行
        $taskIds = array_keys($results);
        $this->assertEquals($criticalId, $taskIds[0]);
        $this->assertEquals($highId, $taskIds[1]);
        $this->assertEquals($lowId, $taskIds[2]);
    }

    public function testSimpleTaskExecution(): void
    {
        $this->processor->addTask(function($context) {
            return $context['a'] + $context['b'];
        }, ConcurrentProcessor::PRIORITY_NORMAL, ['a' => 3, 'b' => 7]);

        $results = $this->processor->processAll();

        $this->assertCount(1, $results);
        $result = array_values($results)[0];
        $this->assertEquals('completed', $result['status']);
        $this->assertEquals(10, $result['result']);
    }

    public function testMultipleTasksExecution(): void
    {
        // 添加多个任务
        for ($i = 1; $i <= 5; $i++) {
            $this->processor->addTask(function($context) {
                return $context['number'] * $context['number'];
            }, ConcurrentProcessor::PRIORITY_NORMAL, ['number' => $i]);
        }

        $results = $this->processor->processAll();

        $this->assertCount(5, $results);
        
        // 验证所有任务都完成
        foreach ($results as $result) {
            $this->assertEquals('completed', $result['status']);
            $this->assertIsNumeric($result['result']);
        }
    }

    public function testTaskWithException(): void
    {
        $this->processor->addTask(function() {
            throw new \Exception('测试异常');
        });

        $results = $this->processor->processAll();

        $this->assertCount(1, $results);
        $result = array_values($results)[0];
        $this->assertEquals('failed', $result['status']);
        $this->assertStringContainsString('测试异常', $result['error']);
    }

    public function testQueueStatus(): void
    {
        // 添加一些任务
        $this->processor->addTask(function() { return 1; });
        $this->processor->addTask(function() { return 2; });
        $this->processor->addTask(function() { return 3; });

        $statusBefore = $this->processor->getQueueStatus();
        $this->assertEquals(3, $statusBefore['queue_length']);
        $this->assertEquals(3, $statusBefore['total_tasks']);

        // 处理任务
        $this->processor->processAll();

        $statusAfter = $this->processor->getQueueStatus();
        $this->assertEquals(0, $statusAfter['queue_length']);
        $this->assertEquals(3, $statusAfter['status_count']['completed']);
    }

    public function testGetResults(): void
    {
        $taskId = $this->processor->addTask(function() {
            return 'test result';
        });

        $this->processor->processAll();
        $results = $this->processor->getResults();

        $this->assertArrayHasKey($taskId, $results);
        $this->assertEquals('test result', $results[$taskId]['result']);
    }

    public function testGetCompletedTasks(): void
    {
        $this->processor->addTask(function() { return 'success'; });
        $this->processor->addTask(function() { throw new \Exception('fail'); });

        $this->processor->processAll();

        $completed = $this->processor->getCompletedTasks();
        $failed = $this->processor->getFailedTasks();

        $this->assertCount(1, $completed);
        $this->assertCount(1, $failed);
        $this->assertEquals('completed', array_values($completed)[0]['status']);
        $this->assertEquals('failed', array_values($failed)[0]['status']);
    }

    public function testFiberSupport(): void
    {
        $status = $this->processor->getQueueStatus();
        
        // 检查Fiber支持情况
        if (class_exists('Fiber')) {
            $this->assertTrue($status['fiber_supported']);
        } else {
            $this->assertFalse($status['fiber_supported']);
        }
    }

    public function testAsyncLikeExecution(): void
    {
        // 模拟一些需要时间的任务
        $startTime = microtime(true);
        
        for ($i = 1; $i <= 3; $i++) {
            $this->processor->addTask(function($context) {
                // 模拟一些计算
                $result = 0;
                for ($j = 0; $j < 1000; $j++) {
                    $result += $j * $context['multiplier'];
                }
                return $result;
            }, ConcurrentProcessor::PRIORITY_NORMAL, ['multiplier' => $i]);
        }

        $results = $this->processor->processAll();
        $endTime = microtime(true);

        $this->assertCount(3, $results);
        $this->assertLessThan(1.0, $endTime - $startTime); // 应该在1秒内完成

        // 验证结果正确性
        foreach ($results as $result) {
            $this->assertEquals('completed', $result['status']);
            $this->assertIsNumeric($result['result']);
        }
    }

    public function testEmptyQueue(): void
    {
        $results = $this->processor->processAll();
        $this->assertEmpty($results);
    }

    public function testPauseResumeStop(): void
    {
        $this->processor->pause();
        $status = $this->processor->getQueueStatus();
        $this->assertEquals(ConcurrentProcessor::STATE_PAUSED, $status['processor_state']);

        $this->processor->resume();
        // 注意：实际状态可能还是PAUSED，因为没有真正运行处理循环

        $this->processor->stop();
        $status = $this->processor->getQueueStatus();
        $this->assertEquals(ConcurrentProcessor::STATE_STOPPED, $status['processor_state']);
    }

    protected function tearDown(): void
    {
        // 确保处理器停止
        $this->processor->stop();
    }
} 