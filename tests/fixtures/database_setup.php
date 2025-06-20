<?php

declare(strict_types=1);

/**
 * 数据库表结构创建和测试数据生成
 * 用于导入导出性能测试
 */

class DatabaseSetup
{
    private PDO $pdo;
    
    // 数据库配置
    private array $config = [
        'host' => '192.168.20.66',
        'port' => '3306', 
        'dbname' => 'exportexcle',
        'username' => 'exportexcle',
        'password' => 'bmZfKedRftDTMjKL',
        'charset' => 'utf8mb4'
    ];

    public function __construct()
    {
        $this->connectDatabase();
    }

    /**
     * 连接数据库
     */
    private function connectDatabase(): void
    {
        $dsn = "mysql:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['dbname']};charset={$this->config['charset']}";
        
        try {
            $this->pdo = new PDO($dsn, $this->config['username'], $this->config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->config['charset']}"
            ]);
            
            echo "数据库连接成功！\n";
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
        $this->generatePerformanceData(100000);  // 10万条记录
        
        echo "所有测试数据生成完成！\n";
    }

    /**
     * 创建用户表
     */
    private function createUsersTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            phone VARCHAR(20),
            gender ENUM('male', 'female', 'other') DEFAULT 'other',
            birthday DATE,
            city VARCHAR(50),
            address TEXT,
            avatar_url VARCHAR(255),
            status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
            points INT DEFAULT 0,
            balance DECIMAL(10,2) DEFAULT 0.00,
            register_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login_time DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_email (email),
            INDEX idx_status (status),
            INDEX idx_register_time (register_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->pdo->exec($sql);
        echo "用户表 (users) 创建完成\n";
    }

    /**
     * 创建订单表
     */
    private function createOrdersTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS orders (
            id INT PRIMARY KEY AUTO_INCREMENT,
            order_no VARCHAR(32) NOT NULL UNIQUE,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(10,2) NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            discount_amount DECIMAL(10,2) DEFAULT 0.00,
            final_amount DECIMAL(10,2) NOT NULL,
            status ENUM('pending', 'paid', 'shipped', 'completed', 'cancelled') DEFAULT 'pending',
            payment_method ENUM('alipay', 'wechat', 'card', 'cash') DEFAULT 'alipay',
            shipping_address TEXT,
            remark TEXT,
            order_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            payment_time DATETIME,
            shipping_time DATETIME,
            completed_time DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_order_no (order_no),
            INDEX idx_user_id (user_id),
            INDEX idx_product_id (product_id),
            INDEX idx_status (status),
            INDEX idx_order_time (order_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->pdo->exec($sql);
        echo "订单表 (orders) 创建完成\n";
    }

    /**
     * 创建产品表
     */
    private function createProductsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS products (
            id INT PRIMARY KEY AUTO_INCREMENT,
            product_code VARCHAR(32) NOT NULL UNIQUE,
            product_name VARCHAR(100) NOT NULL,
            category VARCHAR(50),
            brand VARCHAR(50),
            model VARCHAR(50),
            specification TEXT,
            unit VARCHAR(20) DEFAULT '件',
            cost_price DECIMAL(10,2),
            selling_price DECIMAL(10,2) NOT NULL,
            market_price DECIMAL(10,2),
            stock_quantity INT DEFAULT 0,
            min_stock INT DEFAULT 0,
            max_stock INT DEFAULT 9999,
            status ENUM('active', 'inactive', 'discontinued') DEFAULT 'active',
            weight DECIMAL(8,3),
            volume DECIMAL(8,3),
            description TEXT,
            images JSON,
            tags JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_product_code (product_code),
            INDEX idx_product_name (product_name),
            INDEX idx_category (category),
            INDEX idx_brand (brand),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->pdo->exec($sql);
        echo "产品表 (products) 创建完成\n";
    }

    /**
     * 创建销售数据表
     */
    private function createSalesTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS sales (
            id INT PRIMARY KEY AUTO_INCREMENT,
            sale_date DATE NOT NULL,
            region VARCHAR(50),
            salesperson VARCHAR(50),
            customer_name VARCHAR(100),
            customer_type ENUM('individual', 'enterprise') DEFAULT 'individual',
            product_id INT,
            product_name VARCHAR(100),
            category VARCHAR(50),
            quantity INT NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL,
            discount_rate DECIMAL(5,4) DEFAULT 0.0000,
            discount_amount DECIMAL(10,2) DEFAULT 0.00,
            subtotal DECIMAL(10,2) NOT NULL,
            tax_rate DECIMAL(5,4) DEFAULT 0.1300,
            tax_amount DECIMAL(10,2) NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            commission_rate DECIMAL(5,4) DEFAULT 0.0500,
            commission_amount DECIMAL(10,2),
            payment_method ENUM('cash', 'card', 'transfer', 'alipay', 'wechat') DEFAULT 'card',
            payment_status ENUM('pending', 'partial', 'completed') DEFAULT 'completed',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_sale_date (sale_date),
            INDEX idx_region (region),
            INDEX idx_salesperson (salesperson),
            INDEX idx_product_id (product_id),
            INDEX idx_category (category),
            INDEX idx_customer_type (customer_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->pdo->exec($sql);
        echo "销售数据表 (sales) 创建完成\n";
    }

    /**
     * 创建员工表
     */
    private function createEmployeesTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS employees (
            id INT PRIMARY KEY AUTO_INCREMENT,
            employee_no VARCHAR(20) NOT NULL UNIQUE,
            name VARCHAR(50) NOT NULL,
            gender ENUM('male', 'female') NOT NULL,
            birth_date DATE,
            id_card VARCHAR(18),
            phone VARCHAR(20),
            email VARCHAR(100),
            department VARCHAR(50),
            position VARCHAR(50),
            level ENUM('junior', 'intermediate', 'senior', 'expert', 'manager') DEFAULT 'junior',
            hire_date DATE NOT NULL,
            salary DECIMAL(10,2),
            bonus DECIMAL(10,2) DEFAULT 0.00,
            social_security DECIMAL(8,2) DEFAULT 0.00,
            address TEXT,
            emergency_contact VARCHAR(50),
            emergency_phone VARCHAR(20),
            status ENUM('active', 'inactive', 'resigned') DEFAULT 'active',
            manager_id INT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_employee_no (employee_no),
            INDEX idx_name (name),
            INDEX idx_department (department),
            INDEX idx_position (position),
            INDEX idx_status (status),
            INDEX idx_hire_date (hire_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->pdo->exec($sql);
        echo "员工表 (employees) 创建完成\n";
    }

    /**
     * 创建性能测试表
     */
    private function createPerformanceTestTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS performance_test (
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
            website VARCHAR(255),
            description TEXT,
            tags JSON,
            metadata JSON,
            score DECIMAL(8,2),
            rating ENUM('A', 'B', 'C', 'D', 'F') DEFAULT 'C',
            is_active BOOLEAN DEFAULT TRUE,
            created_date DATE,
            created_time TIME,
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
        
        $this->pdo->exec($sql);
        echo "性能测试表 (performance_test) 创建完成\n";
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
        
        $batchSize = 1000;
        $batches = ceil($count / $batchSize);
        
        for ($batch = 0; $batch < $batches; $batch++) {
            $batchStart = $batch * $batchSize;
            $batchEnd = min($batchStart + $batchSize, $count);
            
            $sql = "INSERT INTO users (username, email, phone, gender, city, address, status, points, balance, register_time) VALUES ";
            $values = [];
            $params = [];
            
            for ($i = $batchStart; $i < $batchEnd; $i++) {
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
                
                $values[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $params = array_merge($params, [$username, $email, $phone, $gender, $city, $address, $status, $points, $balance, $registerTime]);
            }
            
            $stmt = $this->pdo->prepare($sql . implode(', ', $values));
            $stmt->execute($params);
        }
        
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
        
        $sql = "INSERT INTO products (product_code, product_name, category, brand, unit, cost_price, selling_price, market_price, stock_quantity, status) VALUES ";
        $values = [];
        $params = [];
        
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
            
            $values[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = array_merge($params, [$productCode, $productName, $category, $brand, $unit, $costPrice, $sellingPrice, $marketPrice, $stockQuantity, $status]);
        }
        
        $stmt = $this->pdo->prepare($sql . implode(', ', $values));
        $stmt->execute($params);
        
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
        
        $sql = "INSERT INTO employees (employee_no, name, gender, department, position, level, hire_date, salary, status) VALUES ";
        $values = [];
        $params = [];
        
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
            
            $values[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = array_merge($params, [$employeeNo, $name, $gender, $department, $position, $level, $hireDate, $salary, $status]);
        }
        
        $stmt = $this->pdo->prepare($sql . implode(', ', $values));
        $stmt->execute($params);
        
        echo "员工数据生成完成\n";
    }

    /**
     * 生成订单数据
     */
    private function generateOrdersData(int $count): void
    {
        echo "生成 {$count} 条订单数据...\n";
        
        // 获取用户和产品ID范围
        $userCount = $this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $productCount = $this->pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
        
        if ($userCount == 0 || $productCount == 0) {
            echo "警告：用户或产品数据为空，跳过订单生成\n";
            return;
        }
        
        $statuses = ['pending', 'paid', 'shipped', 'completed', 'cancelled'];
        $paymentMethods = ['alipay', 'wechat', 'card', 'cash'];
        
        $batchSize = 1000;
        $batches = ceil($count / $batchSize);
        
        for ($batch = 0; $batch < $batches; $batch++) {
            $batchStart = $batch * $batchSize;
            $batchEnd = min($batchStart + $batchSize, $count);
            
            $sql = "INSERT INTO orders (order_no, user_id, product_id, quantity, unit_price, total_amount, final_amount, status, payment_method, order_time) VALUES ";
            $values = [];
            $params = [];
            
            for ($i = $batchStart; $i < $batchEnd; $i++) {
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
                
                $values[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $params = array_merge($params, [$orderNo, $userId, $productId, $quantity, $unitPrice, $totalAmount, $finalAmount, $status, $paymentMethod, $orderTime]);
            }
            
            $stmt = $this->pdo->prepare($sql . implode(', ', $values));
            $stmt->execute($params);
        }
        
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
        
        $batchSize = 1000;
        $batches = ceil($count / $batchSize);
        
        for ($batch = 0; $batch < $batches; $batch++) {
            $batchStart = $batch * $batchSize;
            $batchEnd = min($batchStart + $batchSize, $count);
            
            $sql = "INSERT INTO sales (sale_date, region, salesperson, customer_name, customer_type, product_name, category, quantity, unit_price, subtotal, tax_amount, total_amount, payment_method, payment_status) VALUES ";
            $values = [];
            $params = [];
            
            for ($i = $batchStart; $i < $batchEnd; $i++) {
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
                
                $values[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $params = array_merge($params, [$saleDate, $region, $salesperson, $customerName, $customerType, $productName, $category, $quantity, $unitPrice, $subtotal, $taxAmount, $totalAmount, $paymentMethod, $paymentStatus]);
            }
            
            $stmt = $this->pdo->prepare($sql . implode(', ', $values));
            $stmt->execute($params);
        }
        
        echo "销售数据生成完成\n";
    }

    /**
     * 生成性能测试数据
     */
    private function generatePerformanceData(int $count): void
    {
        echo "生成 {$count} 条性能测试数据...\n";
        
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
                
                $values[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $params = array_merge($params, [$testId, $name, $email, $phone, $address, $company, $position, $salary, $age, $gender, $city, $province, $score, $rating, $createdDate]);
            }
            
            $stmt = $this->pdo->prepare($sql . implode(', ', $values));
            $stmt->execute($params);
        }
        
        echo "性能测试数据 ({$count}条) 生成完成\n";
    }

    /**
     * 生成百万级别测试数据
     */
    public function generateMillionTestData(): void
    {
        echo "开始生成百万级别测试数据...\n";
        
        // 基础数据
        $this->generateUsersData(1000);        // 1千用户
        $this->generateProductsData(500);      // 500种产品
        $this->generateEmployeesData(200);     // 200个员工
        $this->generateOrdersData(5000);       // 5千订单
        $this->generateSalesData(10000);       // 1万销售记录
        
        // 分批生成100万条性能测试数据
        $totalRows = 1000000;
        $batchSize = 100000;
        $batches = $totalRows / $batchSize;
        
        echo "开始生成100万条性能测试数据 (分{$batches}批)...\n";
        
        for ($batch = 1; $batch <= $batches; $batch++) {
            echo "批次 {$batch}/{$batches} - 生成 " . number_format($batchSize) . " 条记录...\n";
            $this->generatePerformanceData($batchSize);
            echo "批次 {$batch} 完成\n";
        }
        
        echo "百万级别测试数据生成完成！\n";
    }

    /**
     * 获取表统计信息
     */
    public function getTableStats(): array
    {
        $tables = ['users', 'products', 'employees', 'orders', 'sales', 'performance_test'];
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
        
        $tables = ['orders', 'sales', 'performance_test', 'users', 'products', 'employees'];
        
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        
        foreach ($tables as $table) {
            $this->pdo->exec("TRUNCATE TABLE {$table}");
            echo "表 {$table} 已清空\n";
        }
        
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        
        echo "所有表数据清空完成\n";
    }
}

// 执行脚本
if (php_sapi_name() === 'cli') {
    try {
        $setup = new DatabaseSetup();
        
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
                
            case 'all':
            default:
                $setup->createAllTables();
                $setup->generateAllTestData();
                
                echo "\n最终统计:\n";
                $stats = $setup->getTableStats();
                foreach ($stats as $table => $count) {
                    echo "  {$table}: {$count} 条记录\n";
                }
                break;
        }
        
    } catch (Exception $e) {
        echo "错误: " . $e->getMessage() . "\n";
        exit(1);
    }
} 