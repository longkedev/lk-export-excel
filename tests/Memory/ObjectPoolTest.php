<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Tests\Memory;

use PHPUnit\Framework\TestCase;
use LkExcel\LkExportExcel\Memory\ObjectPool;

class ObjectPoolTest extends TestCase
{
    private ObjectPool $pool;

    protected function setUp(): void
    {
        $this->pool = new ObjectPool();
    }

    public function testRegisterFactory(): void
    {
        $result = $this->pool->registerFactory('array', function() {
            return [];
        }, 20);
        
        $this->assertInstanceOf(ObjectPool::class, $result);
        
        // 验证工厂已注册（通过尝试获取对象）
        $object = $this->pool->get('array');
        $this->assertIsArray($object);
    }

    public function testGetFromEmptyPool(): void
    {
        $this->pool->registerFactory('string', function() {
            return 'test_string';
        });
        
        $object = $this->pool->get('string');
        $this->assertEquals('test_string', $object);
    }

    public function testGetFromUnregisteredType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("对象类型 'unregistered' 未注册");
        
        $this->pool->get('unregistered');
    }

    public function testReturnAndReuseObject(): void
    {
        $this->pool->registerFactory('stdclass', function() {
            $obj = new \stdClass();
            $obj->id = uniqid();
            return $obj;
        });
        
        // 获取对象
        $object1 = $this->pool->get('stdclass');
        $originalId = $object1->id;
        
        // 归还对象
        $returned = $this->pool->return('stdclass', $object1);
        $this->assertTrue($returned);
        
        // 再次获取应该是同一个对象
        $object2 = $this->pool->get('stdclass');
        $this->assertSame($object1, $object2);
        $this->assertEquals($originalId, $object2->id);
    }

    public function testReturnToUnregisteredType(): void
    {
        $result = $this->pool->return('unregistered', new \stdClass());
        $this->assertFalse($result);
    }

    public function testPoolMaxSize(): void
    {
        $maxSize = 2;
        $this->pool->registerFactory('limited', function() {
            return new \stdClass();
        }, $maxSize);
        
        // 创建并归还多个对象
        $objects = [];
        for ($i = 0; $i < $maxSize + 2; $i++) {
            $objects[] = $this->pool->get('limited');
        }
        
        // 归还所有对象，但只能存储maxSize个
        $returnCount = 0;
        foreach ($objects as $obj) {
            if ($this->pool->return('limited', $obj)) {
                $returnCount++;
            }
        }
        
        $this->assertEquals($maxSize, $returnCount);
    }

    public function testWarmup(): void
    {
        $this->pool->registerFactory('warmup_test', function() {
            return ['created' => true];
        }, 10);
        
        $result = $this->pool->warmup('warmup_test', 5);
        $this->assertInstanceOf(ObjectPool::class, $result);
        
        $stats = $this->pool->getStatistics();
        $this->assertEquals(5, $stats['pools']['warmup_test']['created_count']);
        $this->assertEquals(5, $stats['pools']['warmup_test']['current_size']);
    }

    public function testWarmupUnregisteredType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("对象类型 'unregistered' 未注册");
        
        $this->pool->warmup('unregistered', 5);
    }

    public function testClear(): void
    {
        $this->pool->registerFactory('clear_test', function() {
            return 'test';
        });
        
        // 预热池
        $this->pool->warmup('clear_test', 3);
        
        $stats = $this->pool->getStatistics();
        $this->assertEquals(3, $stats['pools']['clear_test']['current_size']);
        
        // 清空池
        $result = $this->pool->clear('clear_test');
        $this->assertInstanceOf(ObjectPool::class, $result);
        
        $stats = $this->pool->getStatistics();
        $this->assertEquals(0, $stats['pools']['clear_test']['current_size']);
    }

    public function testClearAll(): void
    {
        $this->pool->registerFactory('type1', function() { return 'a'; });
        $this->pool->registerFactory('type2', function() { return 'b'; });
        
        $this->pool->warmup('type1', 2);
        $this->pool->warmup('type2', 3);
        
        $result = $this->pool->clearAll();
        $this->assertInstanceOf(ObjectPool::class, $result);
        
        $stats = $this->pool->getStatistics();
        $this->assertEquals(0, $stats['pools']['type1']['current_size']);
        $this->assertEquals(0, $stats['pools']['type2']['current_size']);
    }

    public function testStatistics(): void
    {
        $this->pool->registerFactory('stats_test', function() {
            return new \stdClass();
        }, 5);
        
        // 执行一些操作
        $obj1 = $this->pool->get('stats_test'); // miss
        $obj2 = $this->pool->get('stats_test'); // miss
        $this->pool->return('stats_test', $obj1);
        $obj3 = $this->pool->get('stats_test'); // hit
        
        $stats = $this->pool->getStatistics();
        
        $this->assertIsArray($stats);
        $this->assertEquals(1, $stats['total_pools']);
        $this->assertArrayHasKey('pools', $stats);
        $this->assertArrayHasKey('stats_test', $stats['pools']);
        
        $poolStats = $stats['pools']['stats_test'];
        $this->assertEquals(2, $poolStats['created_count']);
        $this->assertEquals(1, $poolStats['reused_count']);
        $this->assertEquals(1, $poolStats['returned_count']);
        $this->assertEquals(1, $poolStats['hits']);
        $this->assertEquals(2, $poolStats['misses']);
        $this->assertEqualsWithDelta(1/3, $poolStats['hit_rate'], 0.01); // 1 hit out of 3 operations
    }

    public function testStatusReport(): void
    {
        $this->pool->registerFactory('report_test', function() {
            return 'test';
        });
        
        $this->pool->warmup('report_test', 2);
        
        $report = $this->pool->getStatusReport();
        
        $this->assertIsString($report);
        $this->assertStringContainsString('对象池状态报告', $report);
        $this->assertStringContainsString('report_test', $report);
        $this->assertStringContainsString('总池数: 1', $report);
    }

    public function testSetDefaultMaxSize(): void
    {
        $result = $this->pool->setDefaultMaxSize(100);
        $this->assertInstanceOf(ObjectPool::class, $result);
        
        $this->pool->registerFactory('default_size_test', function() {
            return 'test';
        });
        
        $stats = $this->pool->getStatistics();
        $this->assertEquals(100, $stats['pools']['default_size_test']['max_size']);
    }

    public function testSetCleanupInterval(): void
    {
        $result = $this->pool->setCleanupInterval(200);
        $this->assertInstanceOf(ObjectPool::class, $result);
    }

    public function testResizePool(): void
    {
        $this->pool->registerFactory('resize_test', function() {
            return 'test';
        }, 10);
        
        $this->pool->warmup('resize_test', 8);
        
        // 缩小池
        $result = $this->pool->resizePool('resize_test', 5);
        $this->assertInstanceOf(ObjectPool::class, $result);
        
        $stats = $this->pool->getStatistics();
        $this->assertEquals(5, $stats['pools']['resize_test']['max_size']);
        $this->assertEquals(5, $stats['pools']['resize_test']['current_size']); // 对象被清理
    }

    public function testResizeUnregisteredPool(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("对象类型 'unregistered' 未注册");
        
        $this->pool->resizePool('unregistered', 10);
    }

    public function testGetMemoryUsage(): void
    {
        $this->pool->registerFactory('memory_test', function() {
            return 'test_string';
        });
        
        $this->pool->warmup('memory_test', 3);
        
        $memoryUsage = $this->pool->getMemoryUsage();
        
        $this->assertIsArray($memoryUsage);
        $this->assertArrayHasKey('total_objects', $memoryUsage);
        $this->assertArrayHasKey('estimated_memory_bytes', $memoryUsage);
        $this->assertArrayHasKey('estimated_memory_formatted', $memoryUsage);
        
        $this->assertEquals(3, $memoryUsage['total_objects']);
        $this->assertGreaterThan(0, $memoryUsage['estimated_memory_bytes']);
    }

    public function testObjectReset(): void
    {
        // 创建一个带reset方法的测试类
        $testClass = new class {
            public $data = 'original';
            public function reset() {
                $this->data = 'reset';
            }
        };
        
        $this->pool->registerFactory('resettable', function() use ($testClass) {
            return clone $testClass;
        });
        
        $obj = $this->pool->get('resettable');
        $obj->data = 'modified';
        
        $this->pool->return('resettable', $obj);
        $newObj = $this->pool->get('resettable');
        
        // 对象应该被重置
        $this->assertEquals('reset', $newObj->data);
    }

    public function testHitRateCalculation(): void
    {
        $this->pool->registerFactory('hit_rate_test', function() {
            return new \stdClass();
        });
        
        // 第一次获取 - miss
        $obj1 = $this->pool->get('hit_rate_test');
        $this->pool->return('hit_rate_test', $obj1);
        
        // 第二次获取 - hit
        $obj2 = $this->pool->get('hit_rate_test');
        
        // 第三次获取 - miss (创建新对象)
        $obj3 = $this->pool->get('hit_rate_test');
        
        $stats = $this->pool->getStatistics();
        $poolStats = $stats['pools']['hit_rate_test'];
        
        $this->assertEquals(1, $poolStats['hits']);
        $this->assertEquals(2, $poolStats['misses']);
        $this->assertEqualsWithDelta(1/3, $poolStats['hit_rate'], 0.01);
    }

    public function testCleanupAfterOperations(): void
    {
        $this->pool->setCleanupInterval(5); // 每5次操作清理一次
        
        $this->pool->registerFactory('cleanup_test', function() {
            return ['data' => uniqid()];
        }, 10);
        
        // 执行足够的操作触发清理
        for ($i = 0; $i < 10; $i++) {
            $obj = $this->pool->get('cleanup_test');
            if ($i < 5) {
                $this->pool->return('cleanup_test', $obj);
            }
        }
        
        // 应该有一些对象在池中
        $stats = $this->pool->getStatistics();
        $this->assertLessThanOrEqual(10, $stats['pools']['cleanup_test']['current_size']);
    }
} 