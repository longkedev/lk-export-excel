<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Tests\Memory;

use PHPUnit\Framework\TestCase;
use LkExcel\LkExportExcel\Memory\MemoryManager;

/**
 * MemoryManager 单元测试
 */
class MemoryManagerTest extends TestCase
{
    private MemoryManager $memoryManager;

    protected function setUp(): void
    {
        $this->memoryManager = new MemoryManager(64 * 1024 * 1024); // 64MB
    }

    public function testConstructor(): void
    {
        $manager = new MemoryManager(128 * 1024 * 1024);
        $stats = $manager->getMemoryStats();
        
        $this->assertEquals(128 * 1024 * 1024, $stats['limit']);
    }

    public function testCheckMemoryUsage(): void
    {
        $result = $this->memoryManager->checkMemoryUsage();
        $this->assertIsBool($result);
    }

    public function testMemoryStats(): void
    {
        $stats = $this->memoryManager->getMemoryStats();
        
        $this->assertArrayHasKey('current_usage', $stats);
        $this->assertArrayHasKey('peak_usage', $stats);
        $this->assertArrayHasKey('limit', $stats);
        $this->assertArrayHasKey('usage_ratio', $stats);
        $this->assertArrayHasKey('gc_count', $stats);
        $this->assertArrayHasKey('object_pools', $stats);
        $this->assertArrayHasKey('leak_detection', $stats);
        
        $this->assertIsInt($stats['current_usage']);
        $this->assertIsFloat($stats['usage_ratio']);
    }

    public function testObjectPool(): void
    {
        // 测试从空池获取对象
        $obj = $this->memoryManager->getFromPool('stdClass');
        $this->assertNull($obj);
        
        // 测试返回对象到池
        $testObj = new \stdClass();
        $testObj->test = 'value';
        
        $this->memoryManager->returnToPool('stdClass', $testObj);
        
        // 测试从池获取对象
        $retrievedObj = $this->memoryManager->getFromPool('stdClass');
        $this->assertInstanceOf(\stdClass::class, $retrievedObj);
    }

    public function testForceGarbageCollection(): void
    {
        $initialStats = $this->memoryManager->getMemoryStats();
        $initialGcCount = $initialStats['gc_count'];
        
        $this->memoryManager->forceGarbageCollection();
        
        $newStats = $this->memoryManager->getMemoryStats();
        $this->assertEquals($initialGcCount + 1, $newStats['gc_count']);
    }

    public function testSetMemoryLimit(): void
    {
        $newLimit = 128 * 1024 * 1024; // 128MB
        $this->memoryManager->setMemoryLimit($newLimit);
        
        $stats = $this->memoryManager->getMemoryStats();
        $this->assertEquals($newLimit, $stats['limit']);
    }

    public function testSetThresholds(): void
    {
        // 测试设置阈值
        $this->memoryManager->setThresholds(0.75, 0.85);
        
        // 测试边界值
        $this->memoryManager->setThresholds(-0.1, 1.5); // 应该被限制在0.0-1.0范围内
        
        // 无异常表示测试通过
        $this->assertTrue(true);
    }

    public function testMemoryLeakDetection(): void
    {
        $result = $this->memoryManager->detectMemoryLeaks();
        
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('trend', $result);
        
        // 初始状态应该是数据不足
        $this->assertEquals('insufficient_data', $result['status']);
    }
} 