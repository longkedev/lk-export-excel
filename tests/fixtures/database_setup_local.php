<?php

declare(strict_types=1);

/**
 * 本地SQLite数据库表结构创建和测试数据生成
 * 用于导入导出性能测试（本地版本）
 */

class LocalDatabaseSetup
{
    private PDO $pdo;
    private string $dbPath;

    public function __construct(string $dbPath = null)
    {
        $this->dbPath = $dbPath ?: __DIR__ . '/test_database.sqlite';
        $this->connectDatabase();
    }

    /**
     * 连接SQLite数据库
     */
    private function connectDatabase(): void
    {
        try {
            // 如果数据库文件不存在，SQLite会自动创建
            $this->pdo = new PDO("sqlite:{$this->dbPath}", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            
            // 启用外键约束
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            
            echo "SQLite数据库连接成功: {$this->dbPath}\n";
        } catch (PDOException $e) {
            throw new RuntimeException("数据库连接失败: " . $e->getMessage());
        }
    }

    /**
     * 创建所有测试表
     */
    public function createAllTables(): void
    {
        echo "开始创建数据库表...\n";
        
        $this->createUsersTable();
        $this->createOrdersTable();
        $this->createProductsTable();
        $this->createSalesTable();
        $this->createEmployeesTable();
        $this->createPerformanceTestTable();
        
        echo "所有表创建完成！\n";
    }

    /**
     * 生成所有测试数据
     */
    public function generateAllTestData(): void
    {
        echo "开始生成测试数据...\n";
        
        $this->generateUsersData(1000);        // 1千用户
        $this->generateProductsData(500);      // 500种产品
        $this->generateEmployeesData(200);     // 200个员工
        $this->generateOrdersData(5000);       // 5千订单
        $this->generateSalesData(10000);       // 1万销售记录
        
        // 性能测试数据
        $this->generatePerformanceData(10000);   // 1万条记录
        $this->generatePerformanceData(100000);  // 10万条记录（分表存储）
        
        echo "所有测试数据生成完成！\n";
    }

    /**
     * 创建用户表
     */
    private function createUsersTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            phone TEXT,
            gender TEXT CHECK(gender IN ('male', 'female', 'other')) DEFAULT 'other',
            birthday DATE,
            city TEXT,
            address TEXT,
            avatar_url TEXT,
            status TEXT CHECK(status IN ('active', 'inactive', 'banned')) DEFAULT 'active',
            points INTEGER DEFAULT 0,
            balance REAL DEFAULT 0.00,
            register_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login_time DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $this->pdo->exec($sql);
        
        // 创建索引
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_status ON users(status)");
        
        echo "用户表 (users) 创建完成\n";
    }

    /**
     * 创建订单表
     */
    private function createOrdersTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_no TEXT NOT NULL UNIQUE,
            user_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            quantity INTEGER NOT NULL DEFAULT 1,
            unit_price REAL NOT NULL,
            total_amount REAL NOT NULL,
            discount_amount REAL DEFAULT 0.00,
            final_amount REAL NOT NULL,
            status TEXT CHECK(status IN ('pending', 'paid', 'shipped', 'completed', 'cancelled')) DEFAULT 'pending',
            payment_method TEXT CHECK(payment_method IN ('alipay', 'wechat', 'card', 'cash')) DEFAULT 'alipay',
            shipping_address TEXT,
            remark TEXT,
            order_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            payment_time DATETIME,
            shipping_time DATETIME,
            completed_time DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $this->pdo->exec($sql);
        
        // 创建索引
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_order_no ON orders(order_no)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_user_id ON orders(user_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status)");
        
        echo "订单表 (orders) 创建完成\n";
    }

    /**
     * 创建产品表
     */
    private function createProductsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_code TEXT NOT NULL UNIQUE,
            product_name TEXT NOT NULL,
            category TEXT,
            brand TEXT,
            model TEXT,
            specification TEXT,
            unit TEXT DEFAULT '件',
            cost_price REAL,
            selling_price REAL NOT NULL,
            market_price REAL,
            stock_quantity INTEGER DEFAULT 0,
            min_stock INTEGER DEFAULT 0,
            max_stock INTEGER DEFAULT 9999,
            status TEXT CHECK(status IN ('active', 'inactive', 'discontinued')) DEFAULT 'active',
            weight REAL,
            volume REAL,
            description TEXT,
            images TEXT, -- JSON作为TEXT存储
            tags TEXT,   -- JSON作为TEXT存储
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $this->pdo->exec($sql);
        
        // 创建索引
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_products_code ON products(product_code)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_products_name ON products(product_name)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_products_category ON products(category)");
        
        echo "产品表 (products) 创建完成\n";
    }

    /**
     * 创建销售数据表
     */
    private function createSalesTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS sales (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sale_date DATE NOT NULL,
            region TEXT,
            salesperson TEXT,
            customer_name TEXT,
            customer_type TEXT CHECK(customer_type IN ('individual', 'enterprise')) DEFAULT 'individual',
            product_id INTEGER,
            product_name TEXT,
            category TEXT,
            quantity INTEGER NOT NULL,
            unit_price REAL NOT NULL,
            discount_rate REAL DEFAULT 0.0000,
            discount_amount REAL DEFAULT 0.00,
            subtotal REAL NOT NULL,
            tax_rate REAL DEFAULT 0.1300,
            tax_amount REAL NOT NULL,
            total_amount REAL NOT NULL,
            commission_rate REAL DEFAULT 0.0500,
            commission_amount REAL,
            payment_method TEXT CHECK(payment_method IN ('cash', 'card', 'transfer', 'alipay', 'wechat')) DEFAULT 'card',
            payment_status TEXT CHECK(payment_status IN ('pending', 'partial', 'completed')) DEFAULT 'completed',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $this->pdo->exec($sql);
        
        // 创建索引
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_sales_date ON sales(sale_date)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_sales_region ON sales(region)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_sales_salesperson ON sales(salesperson)");
        
        echo "销售数据表 (sales) 创建完成\n";
    }

    /**
     * 创建员工表
     */
    private function createEmployeesTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS employees (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_no TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            gender TEXT CHECK(gender IN ('male', 'female')) NOT NULL,
            birth_date DATE,
            id_card TEXT,
            phone TEXT,
            email TEXT,
            department TEXT,
            position TEXT,
            level TEXT CHECK(level IN ('junior', 'intermediate', 'senior', 'expert', 'manager')) DEFAULT 'junior',
            hire_date DATE NOT NULL,
            salary REAL,
            bonus REAL DEFAULT 0.00,
            social_security REAL DEFAULT 0.00,
            address TEXT,
            emergency_contact TEXT,
            emergency_phone TEXT,
            status TEXT CHECK(status IN ('active', 'inactive', 'resigned')) DEFAULT 'active',
            manager_id INTEGER,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $this->pdo->exec($sql);
        
        // 创建索引
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_employees_no ON employees(employee_no)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_employees_name ON employees(name)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_employees_department ON employees(department)");
        
        echo "员工表 (employees) 创建完成\n";
    }

    /**
     * 创建性能测试表
     */
    private function createPerformanceTestTable(): void
    {
        // 1万条记录表
        $sql = "CREATE TABLE IF NOT EXISTS performance_test_10k (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            test_id TEXT NOT NULL,
            name TEXT NOT NULL,
            email TEXT,
            phone TEXT,
            address TEXT,
            company TEXT,
            position TEXT,
            salary REAL,
            age INTEGER,
            gender TEXT CHECK(gender IN ('male', 'female', 'other')),
            city TEXT,
            province TEXT,
            country TEXT DEFAULT 'China',
            zipcode TEXT,
            website TEXT,
            description TEXT,
            tags TEXT, -- JSON as TEXT
            metadata TEXT, -- JSON as TEXT
            score REAL,
            rating TEXT CHECK(rating IN ('A', 'B', 'C', 'D', 'F')) DEFAULT 'C',
            is_active INTEGER DEFAULT 1,
            created_date DATE,
            created_time TIME,
            created_datetime DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_datetime DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($sql);
        
        // 10万条记录表
        $sql = "CREATE TABLE IF NOT EXISTS performance_test_100k (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            test_id TEXT NOT NULL,
            name TEXT NOT NULL,
            email TEXT,
            phone TEXT,
            address TEXT,
            company TEXT,
            position TEXT,
            salary REAL,
            age INTEGER,
            gender TEXT CHECK(gender IN ('male', 'female', 'other')),
            city TEXT,
            province TEXT,
            country TEXT DEFAULT 'China',
            zipcode TEXT,
            website TEXT,
            description TEXT,
            tags TEXT,
            metadata TEXT,
            score REAL,
            rating TEXT CHECK(rating IN ('A', 'B', 'C', 'D', 'F')) DEFAULT 'C',
            is_active INTEGER DEFAULT 1,
            created_date DATE,
            created_time TIME,
            created_datetime DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_datetime DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($sql);
        
        // 创建索引
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_perf_10k_test_id ON performance_test_10k(test_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_perf_10k_name ON performance_test_10k(name)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_perf_10k_city ON performance_test_10k(city)");
        
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_perf_100k_test_id ON performance_test_100k(test_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_perf_100k_name ON performance_test_100k(name)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_perf_100k_city ON performance_test_100k(city)");
        
        echo "性能测试表 (performance_test_10k, performance_test_100k) 创建完成\n";
    }

    /**
     * 生成用户数据
     */
    private function generateUsersData(int $count): void
    {
        echo "生成 {$count} 条用户数据...\n";
        
        $cities = ['北京', '上海', '广州', '深圳', '杭州', '成都', '武汉', '西安', '南京', '苏州', '天津', '重庆'];
        $statuses = ['active', 'inactive', 'banned'];
        $genders = ['male', 'female', 'other'];
        
        $this->pdo->beginTransaction();
        
        $sql = "INSERT INTO users (username, email, phone, gender, city, address, status, points, balance, register_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        
        for ($i = 0; $i < $count; $i++) {
            $userId = str_pad((string)($i + 1), 6, '0', STR_PAD_LEFT);
            $username = 'user' . $userId;
            $email = $username . '@test.com';
            $phone = '1' . str_pad((string)rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
            $gender = $genders[array_rand($genders)];
            $city = $cities[array_rand($cities)];
            $address = $city . '市某某区某某街道' . rand(1, 999) . '号';
            $status = $statuses[array_rand($statuses)];
            $points = rand(0, 10000);
            $balance = rand(0, 100000) / 100;
            $registerTime = date('Y-m-d H:i:s', strtotime('-' . rand(1, 1000) . ' days'));
            
            $stmt->execute([$username, $email, $phone, $gender, $city, $address, $status, $points, $balance, $registerTime]);
        }
        
        $this->pdo->commit();
        echo "用户数据生成完成\n";
    }

    /**
     * 生成产品数据
     */
    private function generateProductsData(int $count): void
    {
        echo "生成 {$count} 条产品数据...\n";
        
        $categories = ['电子产品', '服装鞋帽', '家居用品', '食品饮料', '图书文具', '运动户外', '美妆个护', '母婴用品'];
        $brands = ['华为', '苹果', '小米', '三星', '联想', '戴尔', '耐克', '阿迪达斯', '优衣库', '海尔'];
        $units = ['件', '台', '个', '套', '箱', '包', '瓶', '袋'];
        $statuses = ['active', 'inactive', 'discontinued'];
        
        $this->pdo->beginTransaction();
        
        $sql = "INSERT INTO products (product_code, product_name, category, brand, unit, cost_price, selling_price, market_price, stock_quantity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        
        for ($i = 0; $i < $count; $i++) {
            $productCode = 'P' . str_pad((string)($i + 1), 6, '0', STR_PAD_LEFT);
            $category = $categories[array_rand($categories)];
            $brand = $brands[array_rand($brands)];
            $productName = $brand . ' ' . $category . ' ' . chr(65 + ($i % 26)) . '型';
            $unit = $units[array_rand($units)];
            $costPrice = rand(1000, 50000) / 100;
            $sellingPrice = $costPrice * (1 + rand(20, 100) / 100);
            $marketPrice = $sellingPrice * (1 + rand(5, 30) / 100);
            $stockQuantity = rand(0, 1000);
            $status = $statuses[array_rand($statuses)];
            
            $stmt->execute([$productCode, $productName, $category, $brand, $unit, $costPrice, $sellingPrice, $marketPrice, $stockQuantity, $status]);
        }
        
        $this->pdo->commit();
        echo "产品数据生成完成\n";
    }

    /**
     * 生成员工数据
     */
    private function generateEmployeesData(int $count): void
    {
        echo "生成 {$count} 条员工数据...\n";
        
        $departments = ['技术部', '市场部', '销售部', '人事部', '财务部', '运营部', '客服部', '采购部'];
        $positions = ['经理', '主管', '专员', '助理', '总监', '副总', '实习生'];
        $levels = ['junior', 'intermediate', 'senior', 'expert', 'manager'];
        $genders = ['male', 'female'];
        $statuses = ['active', 'inactive', 'resigned'];
        
        $this->pdo->beginTransaction();
        
        $sql = "INSERT INTO employees (employee_no, name, gender, department, position, level, hire_date, salary, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        
        for ($i = 0; $i < $count; $i++) {
            $employeeNo = 'E' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT);
            $name = '员工' . chr(65 + ($i % 26)) . ($i + 1);
            $gender = $genders[array_rand($genders)];
            $department = $departments[array_rand($departments)];
            $position = $positions[array_rand($positions)];
            $level = $levels[array_rand($levels)];
            $hireDate = date('Y-m-d', strtotime('-' . rand(30, 2000) . ' days'));
            $salary = rand(5000, 50000);
            $status = $statuses[array_rand($statuses)];
            
            $stmt->execute([$employeeNo, $name, $gender, $department, $position, $level, $hireDate, $salary, $status]);
        }
        
        $this->pdo->commit();
        echo "员工数据生成完成\n";
    }

    /**
     * 生成订单数据
     */
    private function generateOrdersData(int $count): void
    {
        echo "生成 {$count} 条订单数据...\n";
        
        // 获取用户和产品数量
        $userCount = $this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $productCount = $this->pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
        
        if ($userCount == 0 || $productCount == 0) {
            echo "警告：用户或产品数据为空，跳过订单生成\n";
            return;
        }
        
        $statuses = ['pending', 'paid', 'shipped', 'completed', 'cancelled'];
        $paymentMethods = ['alipay', 'wechat', 'card', 'cash'];
        
        $this->pdo->beginTransaction();
        
        $sql = "INSERT INTO orders (order_no, user_id, product_id, quantity, unit_price, total_amount, final_amount, status, payment_method, order_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        
        for ($i = 0; $i < $count; $i++) {
            $orderNo = 'ORD' . date('Ymd') . str_pad((string)($i + 1), 6, '0', STR_PAD_LEFT);
            $userId = rand(1, $userCount);
            $productId = rand(1, $productCount);
            $quantity = rand(1, 10);
            $unitPrice = rand(1000, 50000) / 100;
            $totalAmount = $quantity * $unitPrice;
            $finalAmount = $totalAmount * (1 - rand(0, 20) / 100);
            $status = $statuses[array_rand($statuses)];
            $paymentMethod = $paymentMethods[array_rand($paymentMethods)];
            $orderTime = date('Y-m-d H:i:s', strtotime('-' . rand(1, 365) . ' days'));
            
            $stmt->execute([$orderNo, $userId, $productId, $quantity, $unitPrice, $totalAmount, $finalAmount, $status, $paymentMethod, $orderTime]);
        }
        
        $this->pdo->commit();
        echo "订单数据生成完成\n";
    }

    /**
     * 生成销售数据
     */
    private function generateSalesData(int $count): void
    {
        echo "生成 {$count} 条销售数据...\n";
        
        $regions = ['华北', '华东', '华南', '华中', '西南', '西北', '东北'];
        $salespeople = ['张三', '李四', '王五', '赵六', '钱七', '孙八', '周九', '吴十'];
        $categories = ['电子产品', '服装鞋帽', '家居用品', '食品饮料'];
        $customerTypes = ['individual', 'enterprise'];
        $paymentMethods = ['cash', 'card', 'transfer', 'alipay', 'wechat'];
        $paymentStatuses = ['pending', 'partial', 'completed'];
        
        $this->pdo->beginTransaction();
        
        $sql = "INSERT INTO sales (sale_date, region, salesperson, customer_name, customer_type, product_name, category, quantity, unit_price, subtotal, tax_amount, total_amount, payment_method, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        
        for ($i = 0; $i < $count; $i++) {
            $saleDate = date('Y-m-d', strtotime('-' . rand(1, 365) . ' days'));
            $region = $regions[array_rand($regions)];
            $salesperson = $salespeople[array_rand($salespeople)];
            $customerName = '客户' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT);
            $customerType = $customerTypes[array_rand($customerTypes)];
            $productName = '产品' . chr(65 + ($i % 26));
            $category = $categories[array_rand($categories)];
            $quantity = rand(1, 100);
            $unitPrice = rand(100, 10000) / 100;
            $subtotal = $quantity * $unitPrice;
            $taxAmount = $subtotal * 0.13;
            $totalAmount = $subtotal + $taxAmount;
            $paymentMethod = $paymentMethods[array_rand($paymentMethods)];
            $paymentStatus = $paymentStatuses[array_rand($paymentStatuses)];
            
            $stmt->execute([$saleDate, $region, $salesperson, $customerName, $customerType, $productName, $category, $quantity, $unitPrice, $subtotal, $taxAmount, $totalAmount, $paymentMethod, $paymentStatus]);
        }
        
        $this->pdo->commit();
        echo "销售数据生成完成\n";
    }

    /**
     * 生成性能测试数据
     */
    private function generatePerformanceData(int $count): void
    {
        $tableName = $count >= 100000 ? 'performance_test_100k' : 'performance_test_10k';
        echo "生成 {$count} 条性能测试数据到表 {$tableName}...\n";
        
        $cities = ['北京', '上海', '广州', '深圳', '杭州', '成都', '武汉', '西安', '南京', '苏州'];
        $provinces = ['北京', '上海', '广东', '浙江', '四川', '湖北', '陕西', '江苏'];
        $genders = ['male', 'female', 'other'];
        $ratings = ['A', 'B', 'C', 'D', 'F'];
        $companies = ['阿里巴巴', '腾讯', '百度', '字节跳动', '美团', '京东', '滴滴', '华为'];
        $positions = ['工程师', '产品经理', '设计师', '运营', '市场', '销售', '客服', '管理'];
        
        $this->pdo->beginTransaction();
        
        $sql = "INSERT INTO {$tableName} (test_id, name, email, phone, address, company, position, salary, age, gender, city, province, score, rating, created_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        
        $batchSize = 1000;
        $processed = 0;
        
        for ($i = 0; $i < $count; $i++) {
            $testId = 'TEST' . str_pad((string)($i + 1), 8, '0', STR_PAD_LEFT);
            $name = '测试用户' . str_pad((string)($i + 1), 6, '0', STR_PAD_LEFT);
            $email = 'test' . ($i + 1) . '@example.com';
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
            
            $stmt->execute([$testId, $name, $email, $phone, $address, $company, $position, $salary, $age, $gender, $city, $province, $score, $rating, $createdDate]);
            
            $processed++;
            if ($processed % $batchSize === 0) {
                echo "已处理 {$processed}/{$count} 条记录\n";
            }
        }
        
        $this->pdo->commit();
        echo "性能测试数据 ({$count}条) 生成完成\n";
    }

    /**
     * 获取表统计信息
     */
    public function getTableStats(): array
    {
        $tables = ['users', 'products', 'employees', 'orders', 'sales', 'performance_test_10k', 'performance_test_100k'];
        $stats = [];
        
        foreach ($tables as $table) {
            $count = $this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            $stats[$table] = $count;
        }
        
        return $stats;
    }

    /**
     * 清空所有表数据
     */
    public function truncateAllTables(): void
    {
        echo "清空所有表数据...\n";
        
        $tables = ['orders', 'sales', 'performance_test_10k', 'performance_test_100k', 'users', 'products', 'employees'];
        
        foreach ($tables as $table) {
            $this->pdo->exec("DELETE FROM {$table}");
            $this->pdo->exec("DELETE FROM sqlite_sequence WHERE name='{$table}'"); // 重置自增ID
            echo "表 {$table} 已清空\n";
        }
        
        echo "所有表数据清空完成\n";
    }
    
    /**
     * 导出数据为CSV（用于测试）
     */
    public function exportTableToCSV(string $tableName, string $outputPath, int $limit = 0): void
    {
        $sql = "SELECT * FROM {$tableName}";
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $this->pdo->query($sql);
        $handle = fopen($outputPath, 'w');
        
        // 写入表头
        if ($row = $stmt->fetch()) {
            fputcsv($handle, array_keys($row));
            fputcsv($handle, array_values($row));
        }
        
        // 写入数据行
        while ($row = $stmt->fetch()) {
            fputcsv($handle, array_values($row));
        }
        
        fclose($handle);
        echo "表 {$tableName} 导出到 {$outputPath} 完成\n";
    }
}

// 执行脚本
if (php_sapi_name() === 'cli') {
    try {
        $dbPath = __DIR__ . '/test_database.sqlite';
        $setup = new LocalDatabaseSetup($dbPath);
        
        // 检查参数
        $action = $argv[1] ?? 'all';
        
        switch ($action) {
            case 'tables':
                $setup->createAllTables();
                break;
                
            case 'data':
                $setup->generateAllTestData();
                break;
                
            case 'stats':
                $stats = $setup->getTableStats();
                echo "数据库表统计:\n";
                foreach ($stats as $table => $count) {
                    echo "  {$table}: {$count} 条记录\n";
                }
                break;
                
            case 'truncate':
                $setup->truncateAllTables();
                break;
                
            case 'export':
                // 导出测试用CSV
                $setup->exportTableToCSV('performance_test_10k', __DIR__ . '/export_10k.csv');
                $setup->exportTableToCSV('performance_test_100k', __DIR__ . '/export_100k.csv', 1000); // 只导出1000条用于快速测试
                break;
                
            case 'all':
            default:
                $setup->createAllTables();
                $setup->generateAllTestData();
                
                echo "\n最终统计:\n";
                $stats = $setup->getTableStats();
                foreach ($stats as $table => $count) {
                    echo "  {$table}: {$count} 条记录\n";
                }
                
                echo "\n数据库文件: {$dbPath}\n";
                echo "文件大小: " . round(filesize($dbPath) / 1024 / 1024, 2) . " MB\n";
                break;
        }
        
    } catch (Exception $e) {
        echo "错误: " . $e->getMessage() . "\n";
        exit(1);
    }
} 