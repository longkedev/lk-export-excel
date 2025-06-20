<?php

declare(strict_types=1);

/**
 * 只添加100万条performance_test数据
 * 不影响现有的用户、产品等数据
 */

require_once __DIR__ . '/database_setup.php';

try {
    echo "🚀 添加100万条性能测试数据\n";
    echo "==========================\n\n";
    
    $setup = new DatabaseSetup();
    
    // 先清空performance_test表
    echo "📝 清空性能测试表...\n";
    $pdo = new PDO(
        "mysql:host=192.168.20.66;port=3306;dbname=exportexcle;charset=utf8mb4",
        'exportexcle',
        'bmZfKedRftDTMjKL',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec('TRUNCATE TABLE performance_test');
    echo "性能测试表已清空\n\n";
    
    echo "🎯 开始生成100万条性能测试数据...\n";
    echo "预计需要3-5分钟，请耐心等待...\n\n";
    
    $startTime = microtime(true);
    $startMemory = memory_get_usage(true);
    
    // 分批生成100万条数据，每批5万条（减少批次大小）
    $totalRows = 1000000;
    $batchSize = 50000;
    $batches = $totalRows / $batchSize;
    
    echo "将分成 {$batches} 批，每批 " . number_format($batchSize) . " 条记录\n\n";
    
    for ($batch = 1; $batch <= $batches; $batch++) {
        $batchStartTime = microtime(true);
        
        echo "📦 批次 {$batch}/{$batches} - 生成 " . number_format($batchSize) . " 条记录...\n";
        
        // 直接生成性能测试数据
        generatePerformanceTestData($pdo, $batchSize, ($batch - 1) * $batchSize);
        
        $batchTime = microtime(true) - $batchStartTime;
        $currentMemory = memory_get_usage(true);
        
        echo "   ✅ 批次完成 - 耗时: " . round($batchTime, 1) . "s, ";
        echo "内存: " . formatBytes($currentMemory) . ", ";
        echo "速度: " . number_format($batchSize / $batchTime, 0) . " 条/秒\n";
        
        // 强制垃圾回收
        gc_collect_cycles();
        
        $afterGcMemory = memory_get_usage(true);
        if ($afterGcMemory < $currentMemory) {
            echo "   🧹 内存回收: " . formatBytes($currentMemory - $afterGcMemory) . "\n";
        }
        
        echo "\n";
        
        // 每5批暂停1秒，避免过度占用服务器资源
        if ($batch % 5 === 0) {
            echo "   ⏸️  暂停1秒...\n\n";
            sleep(1);
        }
    }
    
    $totalTime = microtime(true) - $startTime;
    $endMemory = memory_get_usage(true);
    $peakMemory = memory_get_peak_usage(true);
    
    // 最终统计
    echo "🎉 100万条数据生成完成！\n";
    echo "========================\n\n";
    
    echo "📊 生成统计:\n";
    echo "  - 总记录数: " . number_format($totalRows) . " 条\n";
    echo "  - 总耗时: " . formatTime($totalTime) . "\n";
    echo "  - 平均速度: " . number_format($totalRows / $totalTime, 0) . " 条/秒\n";
    echo "  - 内存峰值: " . formatBytes($peakMemory) . "\n";
    echo "  - 当前内存: " . formatBytes($endMemory) . "\n\n";
    
    // 验证数据
    echo "🔍 数据验证:\n";
    $count = $pdo->query("SELECT COUNT(*) FROM performance_test")->fetchColumn();
    echo "  - performance_test: " . number_format($count) . " 条记录\n\n";
    
    if ($count >= $totalRows) {
        echo "✅ 数据生成成功！现在可以进行大数据量测试了。\n\n";
        
        echo "💡 接下来可以运行:\n";
        echo "  - php examples/database_export_test.php (数据库导出测试)\n";
        echo "  - php examples/optimized_million_test.php (优化测试)\n";
        echo "  - php examples/million_import_test.php (导入测试)\n";
    } else {
        echo "⚠️ 数据生成可能不完整，请检查。\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ 错误: " . $e->getMessage() . "\n";
    echo "📍 位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    if ($e->getPrevious()) {
        echo "📄 原始错误: " . $e->getPrevious()->getMessage() . "\n";
    }
    
    exit(1);
}

/**
 * 直接生成性能测试数据
 */
function generatePerformanceTestData(PDO $pdo, int $count, int $offset): void
{
    $cities = ['北京', '上海', '广州', '深圳', '杭州', '成都', '武汉', '西安', '南京', '苏州'];
    $provinces = ['北京', '上海', '广东', '浙江', '四川', '湖北', '陕西', '江苏'];
    $genders = ['male', 'female', 'other'];
    $ratings = ['A', 'B', 'C', 'D', 'F'];
    $companies = ['阿里巴巴', '腾讯', '百度', '字节跳动', '美团', '京东', '滴滴', '华为'];
    $positions = ['工程师', '产品经理', '设计师', '运营', '市场', '销售', '客服', '管理'];
    
    $batchSize = 1000;
    $batches = ceil($count / $batchSize);
    
    for ($batch = 0; $batch < $batches; $batch++) {
        $batchStart = $batch * $batchSize;
        $batchEnd = min($batchStart + $batchSize, $count);
        
        $sql = "INSERT INTO performance_test (test_id, name, email, phone, address, company, position, salary, age, gender, city, province, score, rating, created_date) VALUES ";
        $values = [];
        $params = [];
        
        for ($i = $batchStart; $i < $batchEnd; $i++) {
            $actualIndex = $offset + $i + 1;
            $testId = 'TEST' . str_pad((string)$actualIndex, 8, '0', STR_PAD_LEFT);
            $name = '测试用户' . str_pad((string)$actualIndex, 6, '0', STR_PAD_LEFT);
            $email = 'test' . $actualIndex . '@example.com';
            $phone = '1' . str_pad((string)rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
            $city = $cities[array_rand($cities)];
            $province = $provinces[array_rand($provinces)];
            $address = $province . '省' . $city . '市某某区某某街道' . rand(1, 999) . '号';
            $company = $companies[array_rand($companies)];
            $position = $positions[array_rand($positions)];
            $salary = rand(5000, 50000);
            $age = rand(22, 65);
            $gender = $genders[array_rand($genders)];
            $score = rand(0, 10000) / 100;
            $rating = $ratings[array_rand($ratings)];
            $createdDate = date('Y-m-d', strtotime('-' . rand(1, 1000) . ' days'));
            
            $values[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = array_merge($params, [$testId, $name, $email, $phone, $address, $company, $position, $salary, $age, $gender, $city, $province, $score, $rating, $createdDate]);
        }
        
        $stmt = $pdo->prepare($sql . implode(', ', $values));
        $stmt->execute($params);
    }
}

/**
 * 格式化字节数
 */
function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 1) . ' ' . $units[$pow];
}

/**
 * 格式化时间
 */
function formatTime(float $seconds): string
{
    if ($seconds < 60) {
        return round($seconds, 1) . ' 秒';
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return $minutes . ' 分 ' . round($secs, 1) . ' 秒';
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . ' 小时 ' . $minutes . ' 分钟';
    }
} 