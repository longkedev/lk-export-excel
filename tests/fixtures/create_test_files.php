<?php

declare(strict_types=1);

/**
 * 测试文件生成器
 * 生成各种类型和大小的Excel文件用于测试
 */

require_once __DIR__ . '/../../vendor/autoload.php';

class TestFileGenerator
{
    private string $outputDir;

    public function __construct(string $outputDir = __DIR__)
    {
        $this->outputDir = $outputDir;
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    /**
     * 生成所有测试文件
     */
    public function generateAll(): void
    {
        echo "开始生成测试文件...\n";
        
        $this->generateSmallSimpleFile();
        $this->generateMultiSheetFile();
        $this->generateLargeDataFile();
        $this->generateComplexFormatFile();
        $this->generatePerformanceTestFiles();
        
        echo "所有测试文件生成完成！\n";
    }

    /**
     * 生成小型简单文件
     */
    private function generateSmallSimpleFile(): void
    {
        echo "生成小型简单文件...\n";
        
        $fileName = $this->outputDir . '/small_simple.xlsx';
        $zip = new ZipArchive();
        $zip->open($fileName, ZipArchive::CREATE);

        // 基本结构文件
        $this->addBasicStructure($zip);

        // 简单工作表数据
        $worksheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>
        <row r="1">
            <c r="A1" t="inlineStr"><is><t>姓名</t></is></c>
            <c r="B1" t="inlineStr"><is><t>年龄</t></is></c>
            <c r="C1" t="inlineStr"><is><t>城市</t></is></c>
        </row>
        <row r="2">
            <c r="A2" t="inlineStr"><is><t>张三</t></is></c>
            <c r="B2"><v>25</v></c>
            <c r="C2" t="inlineStr"><is><t>北京</t></is></c>
        </row>
        <row r="3">
            <c r="A3" t="inlineStr"><is><t>李四</t></is></c>
            <c r="B3"><v>30</v></c>
            <c r="C3" t="inlineStr"><is><t>上海</t></is></c>
        </row>
        <row r="4">
            <c r="A4" t="inlineStr"><is><t>王五</t></is></c>
            <c r="B4"><v>28</v></c>
            <c r="C4" t="inlineStr"><is><t>深圳</t></is></c>
        </row>
    </sheetData>
</worksheet>';

        $zip->addFromString('xl/worksheets/sheet1.xml', $worksheetXml);
        $zip->close();
        
        echo "小型简单文件生成完成: {$fileName}\n";
    }

    /**
     * 生成多工作表文件
     */
    private function generateMultiSheetFile(): void
    {
        echo "生成多工作表文件...\n";
        
        $fileName = $this->outputDir . '/multi_sheet.xlsx';
        $zip = new ZipArchive();
        $zip->open($fileName, ZipArchive::CREATE);

        // 基本结构
        $this->addBasicStructure($zip, true);

        // 多工作表工作簿
        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="销售数据" sheetId="1" r:id="rId1"/>
        <sheet name="财务报表" sheetId="2" r:id="rId2"/>
        <sheet name="员工信息" sheetId="3" r:id="rId3"/>
        <sheet name="隐藏工作表" sheetId="4" r:id="rId4" state="hidden"/>
    </sheets>
</workbook>';
        $zip->addFromString('xl/workbook.xml', $workbookXml);

        // 工作簿关系
        $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>
    <Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet4.xml"/>
    <Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);

        // 各个工作表
        $this->addSalesSheet($zip);
        $this->addFinanceSheet($zip);
        $this->addEmployeeSheet($zip);
        $this->addHiddenSheet($zip);

        $zip->close();
        echo "多工作表文件生成完成: {$fileName}\n";
    }

    /**
     * 生成大数据文件
     */
    private function generateLargeDataFile(): void
    {
        echo "生成大数据文件...\n";
        
        $fileName = $this->outputDir . '/large_data.xlsx';
        $zip = new ZipArchive();
        $zip->open($fileName, ZipArchive::CREATE);

        $this->addBasicStructure($zip);

        // 生成1万行数据
        $worksheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>';

        // 表头
        $worksheetXml .= '
        <row r="1">
            <c r="A1" t="inlineStr"><is><t>ID</t></is></c>
            <c r="B1" t="inlineStr"><is><t>用户名</t></is></c>
            <c r="C1" t="inlineStr"><is><t>邮箱</t></is></c>
            <c r="D1" t="inlineStr"><is><t>注册时间</t></is></c>
            <c r="E1" t="inlineStr"><is><t>积分</t></is></c>
            <c r="F1" t="inlineStr"><is><t>状态</t></is></c>
        </row>';

        // 生成10000行数据
        $cities = ['北京', '上海', '广州', '深圳', '杭州', '成都', '武汉', '西安', '南京', '苏州'];
        $statuses = ['活跃', '休眠', '禁用'];
        
        for ($i = 2; $i <= 10001; $i++) {
            $userId = $i - 1;
            $username = 'user' . str_pad((string)$userId, 5, '0', STR_PAD_LEFT);
            $email = $username . '@test.com';
            $registerTime = date('Y-m-d H:i:s', strtotime('-' . rand(1, 365) . ' days'));
            $points = rand(0, 10000);
            $status = $statuses[array_rand($statuses)];
            
            $worksheetXml .= "
        <row r=\"{$i}\">
            <c r=\"A{$i}\"><v>{$userId}</v></c>
            <c r=\"B{$i}\" t=\"inlineStr\"><is><t>{$username}</t></is></c>
            <c r=\"C{$i}\" t=\"inlineStr\"><is><t>{$email}</t></is></c>
            <c r=\"D{$i}\" t=\"inlineStr\"><is><t>{$registerTime}</t></is></c>
            <c r=\"E{$i}\"><v>{$points}</v></c>
            <c r=\"F{$i}\" t=\"inlineStr\"><is><t>{$status}</t></is></c>
        </row>";
        }

        $worksheetXml .= '
    </sheetData>
</worksheet>';

        $zip->addFromString('xl/worksheets/sheet1.xml', $worksheetXml);
        $zip->close();
        
        echo "大数据文件生成完成: {$fileName} (10000行数据)\n";
    }

    /**
     * 生成复杂格式文件
     */
    private function generateComplexFormatFile(): void
    {
        echo "生成复杂格式文件...\n";
        
        $fileName = $this->outputDir . '/complex_format.xlsx';
        $zip = new ZipArchive();
        $zip->open($fileName, ZipArchive::CREATE);

        $this->addBasicStructure($zip);

        // 包含公式、格式、样式的复杂工作表
        $worksheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>
        <row r="1">
            <c r="A1" t="inlineStr"><is><t>产品</t></is></c>
            <c r="B1" t="inlineStr"><is><t>数量</t></is></c>
            <c r="C1" t="inlineStr"><is><t>单价</t></is></c>
            <c r="D1" t="inlineStr"><is><t>金额</t></is></c>
            <c r="E1" t="inlineStr"><is><t>税率</t></is></c>
            <c r="F1" t="inlineStr"><is><t>含税金额</t></is></c>
        </row>
        <row r="2">
            <c r="A2" t="inlineStr"><is><t>笔记本电脑</t></is></c>
            <c r="B2"><v>10</v></c>
            <c r="C2"><v>5000</v></c>
            <c r="D2"><f>B2*C2</f><v>50000</v></c>
            <c r="E2"><v>0.13</v></c>
            <c r="F2"><f>D2*(1+E2)</f><v>56500</v></c>
        </row>
        <row r="3">
            <c r="A3" t="inlineStr"><is><t>显示器</t></is></c>
            <c r="B3"><v>20</v></c>
            <c r="C3"><v>1200</v></c>
            <c r="D3"><f>B3*C3</f><v>24000</v></c>
            <c r="E3"><v>0.13</v></c>
            <c r="F3"><f>D3*(1+E3)</f><v>27120</v></c>
        </row>
        <row r="4">
            <c r="A4" t="inlineStr"><is><t>总计</t></is></c>
            <c r="B4"><f>SUM(B2:B3)</f><v>30</v></c>
            <c r="C4" t="inlineStr"><is><t>-</t></is></c>
            <c r="D4"><f>SUM(D2:D3)</f><v>74000</v></c>
            <c r="E4" t="inlineStr"><is><t>-</t></is></c>
            <c r="F4"><f>SUM(F2:F3)</f><v>83620</v></c>
        </row>
    </sheetData>
</worksheet>';

        $zip->addFromString('xl/worksheets/sheet1.xml', $worksheetXml);
        $zip->close();
        
        echo "复杂格式文件生成完成: {$fileName}\n";
    }

    /**
     * 生成性能测试文件
     */
    private function generatePerformanceTestFiles(): void
    {
        echo "生成性能测试文件...\n";
        
        // 生成1万条记录
        $this->generatePerformanceFile('performance_10k.xlsx', 10000);
        
        // 生成10万条记录  
        $this->generatePerformanceFile('performance_100k.xlsx', 100000);
    }

    /**
     * 生成指定行数的性能测试文件
     */
    private function generatePerformanceFile(string $fileName, int $rowCount): void
    {
        $fullPath = $this->outputDir . '/' . $fileName;
        $zip = new ZipArchive();
        $zip->open($fullPath, ZipArchive::CREATE);

        $this->addBasicStructure($zip);

        // 分批写入数据避免内存溢出
        $batchSize = 1000;
        $batches = ceil($rowCount / $batchSize);
        
        $worksheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>';

        // 表头
        $worksheetXml .= '
        <row r="1">
            <c r="A1" t="inlineStr"><is><t>ID</t></is></c>
            <c r="B1" t="inlineStr"><is><t>姓名</t></is></c>
            <c r="C1" t="inlineStr"><is><t>邮箱</t></is></c>
            <c r="D1" t="inlineStr"><is><t>电话</t></is></c>
            <c r="E1" t="inlineStr"><is><t>地址</t></is></c>
            <c r="F1" t="inlineStr"><is><t>创建时间</t></is></c>
            <c r="G1" t="inlineStr"><is><t>余额</t></is></c>
        </row>';

        // 生成数据行
        for ($row = 2; $row <= $rowCount + 1; $row++) {
            $id = $row - 1;
            $name = '用户' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
            $email = 'user' . $id . '@example.com';
            $phone = '1' . str_pad((string)rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
            $address = '北京市朝阳区某某街道' . rand(1, 999) . '号';
            $createTime = date('Y-m-d H:i:s', strtotime('-' . rand(1, 1000) . ' days'));
            $balance = number_format(rand(0, 100000) / 100, 2, '.', '');

            $worksheetXml .= "
        <row r=\"{$row}\">
            <c r=\"A{$row}\"><v>{$id}</v></c>
            <c r=\"B{$row}\" t=\"inlineStr\"><is><t>{$name}</t></is></c>
            <c r=\"C{$row}\" t=\"inlineStr\"><is><t>{$email}</t></is></c>
            <c r=\"D{$row}\" t=\"inlineStr\"><is><t>{$phone}</t></is></c>
            <c r=\"E{$row}\" t=\"inlineStr\"><is><t>{$address}</t></is></c>
            <c r=\"F{$row}\" t=\"inlineStr\"><is><t>{$createTime}</t></is></c>
            <c r=\"G{$row}\"><v>{$balance}</v></c>
        </row>";
        }

        $worksheetXml .= '
    </sheetData>
</worksheet>';

        $zip->addFromString('xl/worksheets/sheet1.xml', $worksheetXml);
        $zip->close();
        
        echo "性能测试文件生成完成: {$fullPath} ({$rowCount}行数据)\n";
    }

    /**
     * 添加基本的XLSX结构文件
     */
    private function addBasicStructure(ZipArchive $zip, bool $multiSheet = false): void
    {
        // Content Types
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    
        if ($multiSheet) {
            $contentTypes .= '
    <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/worksheets/sheet4.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        
        $contentTypes .= '
</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // Main rels
        $mainRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
        $zip->addFromString('_rels/.rels', $mainRels);

        // Workbook (单工作表版本)
        if (!$multiSheet) {
            $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Sheet1" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>';
            $zip->addFromString('xl/workbook.xml', $workbook);

            // Workbook rels
            $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>';
            $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        }
    }

    /**
     * 添加销售数据工作表
     */
    private function addSalesSheet(ZipArchive $zip): void
    {
        $salesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>
        <row r="1">
            <c r="A1" t="inlineStr"><is><t>日期</t></is></c>
            <c r="B1" t="inlineStr"><is><t>产品</t></is></c>
            <c r="C1" t="inlineStr"><is><t>销量</t></is></c>
            <c r="D1" t="inlineStr"><is><t>金额</t></is></c>
        </row>
        <row r="2">
            <c r="A2" t="inlineStr"><is><t>2024-01-01</t></is></c>
            <c r="B2" t="inlineStr"><is><t>笔记本</t></is></c>
            <c r="C2"><v>100</v></c>
            <c r="D2"><v>500000</v></c>
        </row>
        <row r="3">
            <c r="A3" t="inlineStr"><is><t>2024-01-02</t></is></c>
            <c r="B3" t="inlineStr"><is><t>手机</t></is></c>
            <c r="C3"><v>200</v></c>
            <c r="D3"><v>600000</v></c>
        </row>
    </sheetData>
</worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $salesXml);
    }

    /**
     * 添加财务报表工作表
     */
    private function addFinanceSheet(ZipArchive $zip): void
    {
        $financeXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>
        <row r="1">
            <c r="A1" t="inlineStr"><is><t>科目</t></is></c>
            <c r="B1" t="inlineStr"><is><t>收入</t></is></c>
            <c r="C1" t="inlineStr"><is><t>支出</t></is></c>
            <c r="D1" t="inlineStr"><is><t>余额</t></is></c>
        </row>
        <row r="2">
            <c r="A2" t="inlineStr"><is><t>销售收入</t></is></c>
            <c r="B2"><v>1100000</v></c>
            <c r="C2"><v>0</v></c>
            <c r="D2"><v>1100000</v></c>
        </row>
        <row r="3">
            <c r="A3" t="inlineStr"><is><t>运营成本</t></is></c>
            <c r="B3"><v>0</v></c>
            <c r="C3"><v>300000</v></c>
            <c r="D3"><v>-300000</v></c>
        </row>
    </sheetData>
</worksheet>';
        $zip->addFromString('xl/worksheets/sheet2.xml', $financeXml);
    }

    /**
     * 添加员工信息工作表
     */
    private function addEmployeeSheet(ZipArchive $zip): void
    {
        $employeeXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>
        <row r="1">
            <c r="A1" t="inlineStr"><is><t>员工号</t></is></c>
            <c r="B1" t="inlineStr"><is><t>姓名</t></is></c>
            <c r="C1" t="inlineStr"><is><t>部门</t></is></c>
            <c r="D1" t="inlineStr"><is><t>薪资</t></is></c>
        </row>
        <row r="2">
            <c r="A2" t="inlineStr"><is><t>E001</t></is></c>
            <c r="B2" t="inlineStr"><is><t>张三</t></is></c>
            <c r="C2" t="inlineStr"><is><t>技术部</t></is></c>
            <c r="D2"><v>15000</v></c>
        </row>
        <row r="3">
            <c r="A3" t="inlineStr"><is><t>E002</t></is></c>
            <c r="B3" t="inlineStr"><is><t>李四</t></is></c>
            <c r="C3" t="inlineStr"><is><t>市场部</t></is></c>
            <c r="D3"><v>12000</v></c>
        </row>
    </sheetData>
</worksheet>';
        $zip->addFromString('xl/worksheets/sheet3.xml', $employeeXml);
    }

    /**
     * 添加隐藏工作表
     */
    private function addHiddenSheet(ZipArchive $zip): void
    {
        $hiddenXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>
        <row r="1">
            <c r="A1" t="inlineStr"><is><t>机密数据</t></is></c>
            <c r="B1" t="inlineStr"><is><t>仅管理员可见</t></is></c>
        </row>
    </sheetData>
</worksheet>';
        $zip->addFromString('xl/worksheets/sheet4.xml', $hiddenXml);
    }
}

// 执行生成
if (php_sapi_name() === 'cli') {
    $generator = new TestFileGenerator();
    $generator->generateAll();
} 