<?php

declare(strict_types=1);

/**
 * 数据库连接诊断工具
 * 用于排查服务器数据库连接问题
 */

echo "🔍 数据库连接诊断工具\n";
echo "===================\n\n";

// 数据库配置
$config = [
    'host' => '192.168.20.66',
    'port' => '3306', 
    'dbname' => 'exportexcle',
    'username' => 'exportexcle',
    'password' => 'bmZfKedRftDTMjKL',
    'charset' => 'utf8mb4'
];

echo "📋 连接配置信息:\n";
echo "- 主机: {$config['host']}\n";
echo "- 端口: {$config['port']}\n";
echo "- 数据库: {$config['dbname']}\n";
echo "- 用户名: {$config['username']}\n";
echo "- 字符集: {$config['charset']}\n\n";

// 1. 网络连通性测试
echo "🌐 第一步：网络连通性测试\n";
echo "------------------------\n";

// Ping测试
echo "📡 Ping测试...\n";
$pingResult = shell_exec("ping -c 3 {$config['host']} 2>&1");
if (strpos($pingResult, '3 packets transmitted, 3 received') !== false || 
    strpos($pingResult, '3 packets transmitted, 3 packets received') !== false) {
    echo "✅ Ping测试成功\n";
} else {
    echo "❌ Ping测试失败\n";
    echo "输出: " . trim($pingResult) . "\n";
}

// 端口连通性测试
echo "\n🔌 端口连通性测试...\n";
$socketTest = @fsockopen($config['host'], (int)$config['port'], $errno, $errstr, 5);
if ($socketTest) {
    echo "✅ 端口 {$config['port']} 连通\n";
    fclose($socketTest);
} else {
    echo "❌ 端口 {$config['port']} 连接失败\n";
    echo "错误码: {$errno}, 错误信息: {$errstr}\n";
}

echo "\n";

// 2. PHP环境检查
echo "🐘 第二步：PHP环境检查\n";
echo "--------------------\n";

// PDO扩展检查
if (extension_loaded('pdo')) {
    echo "✅ PDO 扩展已加载\n";
} else {
    echo "❌ PDO 扩展未加载\n";
}

// PDO MySQL驱动检查
if (extension_loaded('pdo_mysql')) {
    echo "✅ PDO MySQL 驱动已加载\n";
} else {
    echo "❌ PDO MySQL 驱动未加载\n";
}

// 可用的PDO驱动
$availableDrivers = PDO::getAvailableDrivers();
echo "📦 可用PDO驱动: " . implode(', ', $availableDrivers) . "\n";

echo "\n";

// 3. DNS解析测试
echo "🔍 第三步：DNS解析测试\n";
echo "--------------------\n";

$ip = gethostbyname($config['host']);
if ($ip === $config['host']) {
    echo "⚠️ DNS解析失败，将直接使用IP地址\n";
} else {
    echo "✅ DNS解析成功: {$config['host']} -> {$ip}\n";
}

echo "\n";

// 4. 数据库连接测试
echo "🔗 第四步：数据库连接测试\n";
echo "------------------------\n";

// 基础连接测试
echo "📝 测试1: 基础连接...\n";
$dsn = "mysql:host={$config['host']};port={$config['port']};charset={$config['charset']}";

try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']}"
    ]);
    echo "✅ 基础连接成功\n";
    
    // 服务器信息
    $serverInfo = $pdo->getAttribute(PDO::ATTR_SERVER_INFO);
    $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    echo "📊 服务器版本: {$serverVersion}\n";
    echo "📊 服务器信息: {$serverInfo}\n";
    
} catch (PDOException $e) {
    echo "❌ 基础连接失败\n";
    echo "错误信息: " . $e->getMessage() . "\n";
    echo "错误代码: " . $e->getCode() . "\n";
    
    // 分析常见错误
    $errorMsg = $e->getMessage();
    if (strpos($errorMsg, 'Connection refused') !== false) {
        echo "💡 建议: MySQL服务可能未启动或端口不正确\n";
    } elseif (strpos($errorMsg, 'Access denied') !== false) {
        echo "💡 建议: 用户名或密码不正确\n";
    } elseif (strpos($errorMsg, 'timeout') !== false) {
        echo "💡 建议: 网络超时，检查防火墙设置\n";
    } elseif (strpos($errorMsg, 'Unknown database') !== false) {
        echo "💡 建议: 数据库不存在\n";
    }
    
    exit(1);
}

echo "\n📝 测试2: 指定数据库连接...\n";
$dsnWithDb = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset={$config['charset']}";

try {
    $pdoWithDb = new PDO($dsnWithDb, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']}"
    ]);
    echo "✅ 数据库 '{$config['dbname']}' 连接成功\n";
    
    // 测试权限
    echo "\n📝 测试3: 权限检查...\n";
    
    // 查看表权限
    try {
        $tables = $pdoWithDb->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "✅ 表查看权限正常\n";
        echo "📋 现有表: " . (empty($tables) ? '(无表)' : implode(', ', $tables)) . "\n";
    } catch (PDOException $e) {
        echo "❌ 表查看权限异常: " . $e->getMessage() . "\n";
    }
    
    // 创建表权限
    try {
        $pdoWithDb->exec("CREATE TABLE IF NOT EXISTS connection_test (id INT PRIMARY KEY AUTO_INCREMENT, test_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        echo "✅ 表创建权限正常\n";
        
        // 插入权限
        $pdoWithDb->exec("INSERT INTO connection_test () VALUES ()");
        echo "✅ 数据插入权限正常\n";
        
        // 查询权限
        $result = $pdoWithDb->query("SELECT COUNT(*) FROM connection_test")->fetchColumn();
        echo "✅ 数据查询权限正常 (测试表有 {$result} 条记录)\n";
        
        // 清理测试表
        $pdoWithDb->exec("DROP TABLE connection_test");
        echo "✅ 表删除权限正常\n";
        
    } catch (PDOException $e) {
        echo "❌ 数据操作权限异常: " . $e->getMessage() . "\n";
    }
    
} catch (PDOException $e) {
    echo "❌ 数据库连接失败\n";
    echo "错误信息: " . $e->getMessage() . "\n";
    
    if (strpos($e->getMessage(), 'Unknown database') !== false) {
        echo "💡 数据库 '{$config['dbname']}' 不存在\n";
        echo "💡 建议: 请先创建数据库或检查数据库名称\n";
    }
}

echo "\n";

// 5. 网络配置建议
echo "⚙️ 第五步：网络配置建议\n";
echo "----------------------\n";

echo "🔧 常见解决方案:\n";
echo "1. 检查MySQL服务器状态:\n";
echo "   sudo systemctl status mysql\n";
echo "   sudo systemctl start mysql\n\n";

echo "2. 检查MySQL配置文件 (/etc/mysql/mysql.conf.d/mysqld.cnf):\n";
echo "   bind-address = 0.0.0.0  # 允许外部连接\n";
echo "   port = 3360  # 确认端口正确\n\n";

echo "3. 检查防火墙设置:\n";
echo "   sudo ufw allow 3360\n";
echo "   # 或者\n";
echo "   sudo iptables -A INPUT -p tcp --dport 3360 -j ACCEPT\n\n";

echo "4. 检查MySQL用户权限:\n";
echo "   mysql -u root -p\n";
echo "   > CREATE USER 'exportexcle'@'%' IDENTIFIED BY 'bmZfKedRftDTMjKL';\n";
echo "   > GRANT ALL PRIVILEGES ON exportexcle.* TO 'exportexcle'@'%';\n";
echo "   > FLUSH PRIVILEGES;\n\n";

echo "5. 在服务器上测试本地连接:\n";
echo "   mysql -h 127.0.0.1 -P 3360 -u exportexcle -p exportexcle\n\n";

echo "6. 检查网络路由:\n";
echo "   traceroute {$config['host']}\n";
echo "   telnet {$config['host']} {$config['port']}\n\n";

echo "🎯 诊断完成！\n";
echo "如果问题持续，请检查以上建议项目。\n"; 