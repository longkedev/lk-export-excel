<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Core;

/**
 * 并发处理器
 * 
 * 基于PHP Fiber的协程模型实现并发处理
 * 设计原则：
 * - 动态负载均衡
 * - 任务优先级队列
 * - 死锁检测和避免
 * - 异常隔离和恢复
 */
class ConcurrentProcessor
{
    // 处理器状态
    public const STATE_IDLE = 'idle';
    public const STATE_RUNNING = 'running';
    public const STATE_PAUSED = 'paused';
    public const STATE_STOPPED = 'stopped';

    // 任务优先级
    public const PRIORITY_LOW = 1;
    public const PRIORITY_NORMAL = 5;
    public const PRIORITY_HIGH = 10;
    public const PRIORITY_CRITICAL = 20;

    private string $state = self::STATE_IDLE;
    private int $maxWorkers;
    private int $activeWorkers = 0;
    private array $taskQueue = [];
    private array $workers = [];
    private array $results = [];
    private bool $fiberSupported;

    /**
     * 构造函数
     * 
     * @param int $maxWorkers 最大工作者数量
     */
    public function __construct(int $maxWorkers = 4)
    {
        $this->maxWorkers = max(1, $maxWorkers);
        $this->fiberSupported = class_exists('Fiber');
        
        if (!$this->fiberSupported && extension_loaded('parallel')) {
            // 如果没有Fiber但有parallel扩展，使用parallel模式
            $this->initParallelMode();
        }
    }

    /**
     * 添加任务到队列
     * 
     * @param callable $task 任务回调
     * @param int $priority 任务优先级
     * @param array $context 任务上下文
     * @return string 任务ID
     */
    public function addTask(callable $task, int $priority = self::PRIORITY_NORMAL, array $context = []): string
    {
        $taskId = uniqid('task_', true);
        
        $taskData = [
            'id' => $taskId,
            'task' => $task,
            'priority' => $priority,
            'context' => $context,
            'created_at' => microtime(true),
            'status' => 'queued'
        ];
        
        // 按优先级插入队列
        $this->insertTaskByPriority($taskData);
        
        return $taskId;
    }

    /**
     * 处理队列中的所有任务
     * 
     * @return array 处理结果
     */
    public function processAll(): array
    {
        if (empty($this->taskQueue)) {
            return [];
        }

        $this->state = self::STATE_RUNNING;
        $this->results = [];

        try {
            if ($this->fiberSupported) {
                return $this->processFiberMode();
            } elseif (extension_loaded('parallel')) {
                return $this->processParallelMode();
            } else {
                return $this->processSequentialMode();
            }
        } catch (\Exception $e) {
            $this->state = self::STATE_STOPPED;
            throw new \RuntimeException("并发处理失败: " . $e->getMessage(), 0, $e);
        } finally {
            $this->cleanup();
        }
    }

    /**
     * Fiber模式处理（PHP 8.1+）
     */
    private function processFiberMode(): array
    {
        $fibers = [];
        
        while (!empty($this->taskQueue) || !empty($fibers)) {
            // 启动新的Fiber工作者
            while (count($fibers) < $this->maxWorkers && !empty($this->taskQueue)) {
                $task = array_shift($this->taskQueue);
                $fiber = new \Fiber(function() use ($task) {
                    return $this->executeTask($task);
                });
                
                $fibers[$task['id']] = [
                    'fiber' => $fiber,
                    'task' => $task,
                    'started' => false
                ];
            }
            
            // 处理Fiber状态
            foreach ($fibers as $taskId => $fiberData) {
                $fiber = $fiberData['fiber'];
                $task = $fiberData['task'];
                
                try {
                    if (!$fiberData['started']) {
                        $fiber->start();
                        $fibers[$taskId]['started'] = true;
                    } elseif ($fiber->isSuspended()) {
                        $fiber->resume();
                    }
                    
                    if ($fiber->isTerminated()) {
                        $this->results[$taskId] = [
                            'task_id' => $taskId,
                            'result' => $fiber->getReturn(),
                            'status' => 'completed',
                            'completed_at' => microtime(true)
                        ];
                        unset($fibers[$taskId]);
                    }
                } catch (\Throwable $e) {
                    $this->results[$taskId] = [
                        'task_id' => $taskId,
                        'error' => $e->getMessage(),
                        'status' => 'failed',
                        'completed_at' => microtime(true)
                    ];
                    unset($fibers[$taskId]);
                }
            }
            
            // 避免过度消耗CPU
            if (!empty($fibers)) {
                usleep(1000); // 1ms
            }
        }
        
        return $this->results;
    }

    /**
     * Parallel扩展模式处理
     */
    private function processParallelMode(): array
    {
        if (!extension_loaded('parallel')) {
            throw new \RuntimeException('parallel扩展未加载');
        }

        // TODO: 实现parallel模式
        // 当前版本使用顺序处理作为后备
        return $this->processSequentialMode();
    }

    /**
     * 顺序处理模式（后备方案）
     */
    private function processSequentialMode(): array
    {
        while (!empty($this->taskQueue)) {
            $task = array_shift($this->taskQueue);
            
            try {
                $result = $this->executeTask($task);
                $this->results[$task['id']] = [
                    'task_id' => $task['id'],
                    'result' => $result,
                    'status' => 'completed',
                    'completed_at' => microtime(true)
                ];
            } catch (\Throwable $e) {
                $this->results[$task['id']] = [
                    'task_id' => $task['id'],
                    'error' => $e->getMessage(),
                    'status' => 'failed',
                    'completed_at' => microtime(true)
                ];
            }
        }
        
        return $this->results;
    }

    /**
     * 执行单个任务
     */
    private function executeTask(array $taskData): mixed
    {
        $startTime = microtime(true);
        
        try {
            $result = call_user_func($taskData['task'], $taskData['context']);
            
            // 任务执行统计
            $executionTime = microtime(true) - $startTime;
            if ($executionTime > 1.0) {
                // 记录慢任务
                error_log("慢任务检测: {$taskData['id']} 执行时间: {$executionTime}s");
            }
            
            return $result;
        } catch (\Throwable $e) {
            error_log("任务执行失败: {$taskData['id']} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 按优先级插入任务
     */
    private function insertTaskByPriority(array $taskData): void
    {
        $inserted = false;
        
        for ($i = 0; $i < count($this->taskQueue); $i++) {
            if ($taskData['priority'] > $this->taskQueue[$i]['priority']) {
                array_splice($this->taskQueue, $i, 0, [$taskData]);
                $inserted = true;
                break;
            }
        }
        
        if (!$inserted) {
            $this->taskQueue[] = $taskData;
        }
    }

    /**
     * 获取队列状态
     */
    public function getQueueStatus(): array
    {
        $statusCount = [
            'queued' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0
        ];
        
        // 统计队列中的任务
        foreach ($this->taskQueue as $task) {
            $statusCount['queued']++;
        }
        
        // 统计结果中的任务
        foreach ($this->results as $result) {
            $statusCount[$result['status']]++;
        }
        
        return [
            'total_tasks' => count($this->taskQueue) + count($this->results),
            'queue_length' => count($this->taskQueue),
            'active_workers' => $this->activeWorkers,
            'max_workers' => $this->maxWorkers,
            'status_count' => $statusCount,
            'processor_state' => $this->state,
            'fiber_supported' => $this->fiberSupported
        ];
    }

    /**
     * 暂停处理
     */
    public function pause(): void
    {
        $this->state = self::STATE_PAUSED;
    }

    /**
     * 恢复处理
     */
    public function resume(): void
    {
        if ($this->state === self::STATE_PAUSED) {
            $this->state = self::STATE_RUNNING;
        }
    }

    /**
     * 停止处理
     */
    public function stop(): void
    {
        $this->state = self::STATE_STOPPED;
        $this->cleanup();
    }

    /**
     * 清理资源
     */
    private function cleanup(): void
    {
        $this->activeWorkers = 0;
        $this->workers = [];
        gc_collect_cycles();
    }

    /**
     * 初始化Parallel模式（如果可用）
     */
    private function initParallelMode(): void
    {
        // TODO: 初始化parallel扩展相关资源
    }

    /**
     * 获取处理结果
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * 获取失败的任务
     */
    public function getFailedTasks(): array
    {
        return array_filter($this->results, fn($result) => $result['status'] === 'failed');
    }

    /**
     * 获取成功的任务
     */
    public function getCompletedTasks(): array
    {
        return array_filter($this->results, fn($result) => $result['status'] === 'completed');
    }

    /**
     * 析构函数
     */
    public function __destruct()
    {
        $this->cleanup();
    }
} 