<?php

declare(strict_types=1);

/**
 * 创建专门的100万条数据表
 * 不影响现有的performance_test表（11万条）
 */

try {
    echo "🚀 创建100万条数据表\n";
    echo "===================\n\n";
    
    // 连接数据库
    $pdo = new PDO(
        "mysql:host=192.168.20.66;port=3306;dbname=exportexcle;charset=utf8mb4",
        'exportexcle',
        'bmZfKedRftDTMjKL',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "数据库连接成功！\n\n";
    
    // 创建新的100万数据表
    echo "📝 创建 million_test 表...\n";
    $createTableSql = "CREATE TABLE IF NOT EXISTS million_test (
        id INT PRIMARY KEY AUTO_INCREMENT,
        test_id VARCHAR(32) NOT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20),
        address TEXT,
        company VARCHAR(100),
        position VARCHAR(50),
        salary DECIMAL(10,2),
        age INT,
        gender ENUM('male', 'female', 'other'),
        city VARCHAR(50),
        province VARCHAR(50),
        country VARCHAR(50) DEFAULT 'China',
        zipcode VARCHAR(10),
        score DECIMAL(8,2),
        rating ENUM('A', 'B', 'C', 'D', 'F') DEFAULT 'C',
        is_active BOOLEAN DEFAULT TRUE,
        created_date DATE,
        created_datetime DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_datetime DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_test_id (test_id),
        INDEX idx_name (name),
        INDEX idx_email (email),
        INDEX idx_city (city),
        INDEX idx_created_date (created_date),
        INDEX idx_score (score),
        INDEX idx_rating (rating)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($createTableSql);
    echo "✅ million_test 表创建完成\n\n";
    
    // 检查是否已有数据
    $existingCount = $pdo->query("SELECT COUNT(*) FROM million_test")->fetchColumn();
    echo "📊 现有数据: " . number_format($existingCount) . " 条\n";
    
    if ($existingCount >= 1000000) {
        echo "✅ 表中已有足够的数据，无需重新生成\n";
        exit(0);
    }
    
    // 清空表重新生成
    if ($existingCount > 0) {
        echo "🧹 清空现有数据...\n";
        $pdo->exec('TRUNCATE TABLE million_test');
        echo "✅ 数据已清空\n\n";
    }
    
    echo "🎯 开始生成100万条数据...\n";
    echo "预计需要3-5分钟，请耐心等待...\n\n";
    
    $startTime = microtime(true);
    $startMemory = memory_get_usage(true);
    
    // 分批生成100万条数据
    $totalRows = 1000000;
    $batchSize = 50000; // 每批5万条
    $batches = $totalRows / $batchSize;
    
    echo "将分成 {$batches} 批，每批 " . number_format($batchSize) . " 条记录\n\n";
    
    for ($batch = 1; $batch <= $batches; $batch++) {
        $batchStartTime = microtime(true);
        
        echo "📦 批次 {$batch}/{$batches} - 生成 " . number_format($batchSize) . " 条记录...\n";
        
        generateMillionTestData($pdo, $batchSize, ($batch - 1) * $batchSize);
        
        $batchTime = microtime(true) - $batchStartTime;
        $currentMemory = memory_get_usage(true);
        
        echo "   ✅ 批次完成 - 耗时: " . round($batchTime, 1) . "s, ";
        echo "内存: " . formatBytes($currentMemory) . ", ";
        echo "速度: " . number_format($batchSize / $batchTime, 0) . " 条/秒\n";
        
        // 每5批暂停1秒
        if ($batch % 5 === 0) {
            echo "   ⏸️  暂停1秒...\n";
            sleep(1);
        }
        
        echo "\n";
    }
    
    $totalTime = microtime(true) - $startTime;
    $endMemory = memory_get_usage(true);
    $peakMemory = memory_get_peak_usage(true);
    
    // 验证数据
    $finalCount = $pdo->query("SELECT COUNT(*) FROM million_test")->fetchColumn();
    
    // 最终统计
    echo "🎉 100万条数据生成完成！\n";
    echo "========================\n\n";
    
    echo "📊 生成统计:\n";
    echo "  - 目标记录数: " . number_format($totalRows) . " 条\n";
    echo "  - 实际记录数: " . number_format($finalCount) . " 条\n";
    echo "  - 总耗时: " . formatTime($totalTime) . "\n";
    echo "  - 平均速度: " . number_format($totalRows / $totalTime, 0) . " 条/秒\n";
    echo "  - 内存峰值: " . formatBytes($peakMemory) . "\n\n";
    
    echo "🔍 数据库表统计:\n";
    $tables = ['performance_test', 'million_test'];
    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        echo "  - {$table}: " . number_format($count) . " 条记录\n";
    }
    
    echo "\n✅ 现在你有了两个测试数据集：\n";
    echo "  - performance_test (11万条) - 适合中等规模测试\n";
    echo "  - million_test (100万条) - 适合大规模测试\n\n";
    
    echo "💡 接下来可以运行:\n";
    echo "  - php examples/database_export_test.php (数据库导出测试)\n";
    echo "  - php examples/optimized_million_test.php (优化测试)\n";
    
} catch (Exception $e) {
    echo "\n❌ 错误: " . $e->getMessage() . "\n";
    echo "📍 位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

/**
 * 生成100万测试数据
 */
function generateMillionTestData(PDO $pdo, int $count, int $offset): void
{
    $cities = ['北京', '上海', '广州', '深圳', '杭州', '成都', '武汉', '西安', '南京', '苏州', '天津', '重庆', '青岛', '大连', '宁波', '厦门'];
    $provinces = ['北京', '上海', '广东', '浙江', '四川', '湖北', '陕西', '江苏', '天津', '重庆', '山东', '辽宁', '福建'];
    $genders = ['male', 'female', 'other'];
    $ratings = ['A', 'B', 'C', 'D', 'F'];
    $companies = ['阿里巴巴', '腾讯', '百度', '字节跳动', '美团', '京东', '滴滴', '华为', '小米', '蚂蚁集团', '网易', '新浪', '搜狐', '携程', '拼多多'];
    $positions = ['工程师', '产品经理', '设计师', '运营专员', '市场专员', '销售代表', '客服专员', '管理人员', '分析师', '架构师'];
    
    $insertBatchSize = 1000; // 每次插入1000条
    $batches = ceil($count / $insertBatchSize);
    
    for ($batch = 0; $batch < $batches; $batch++) {
        $batchStart = $batch * $insertBatchSize;
        $batchEnd = min($batchStart + $insertBatchSize, $count);
        
        $sql = "INSERT INTO million_test (test_id, name, email, phone, address, company, position, salary, age, gender, city, province, score, rating, created_date) VALUES ";
        $values = [];
        $params = [];
        
        for ($i = $batchStart; $i < $batchEnd; $i++) {
            $actualIndex = $offset + $i + 1;
            $testId = 'MILLION' . str_pad((string)$actualIndex, 8, '0', STR_PAD_LEFT);
            $name = '百万测试用户' . str_pad((string)$actualIndex, 6, '0', STR_PAD_LEFT);
            $email = 'million' . $actualIndex . '@test.com';
            $phone = '1' . str_pad((string)rand(3000000000, 9999999999), 10, '0', STR_PAD_LEFT);
            $city = $cities[array_rand($cities)];
            $province = $provinces[array_rand($provinces)];
            $address = $province . '省' . $city . '市' . chr(65 + ($actualIndex % 26)) . '区第' . rand(1, 999) . '街道' . rand(1, 99) . '号';
            $company = $companies[array_rand($companies)];
            $position = $positions[array_rand($positions)];
            $salary = rand(8000, 80000);
            $age = rand(22, 60);
            $gender = $genders[array_rand($genders)];
            $score = rand(0, 10000) / 100;
            $rating = $ratings[array_rand($ratings)];
            $createdDate = date('Y-m-d', strtotime('-' . rand(1, 1095) . ' days')); // 过去3年内
            
            $values[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = array_merge($params, [
                $testId, $name, $email, $phone, $address, $company, 
                $position, $salary, $age, $gender, $city, $province, 
                $score, $rating, $createdDate
            ]);
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