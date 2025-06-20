<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Tests\Api;

use PHPUnit\Framework\TestCase;
use LkExcel\LkExportExcel\Api\Reader;

/**
 * Reader 集成测试
 */
class ReaderTest extends TestCase
{
    private string $csvTestFile;
    private string $xlsxTestFile;

    protected function setUp(): void
    {
        // 创建临时CSV测试文件
        $this->csvTestFile = tempnam(sys_get_temp_dir(), 'test_csv_');
        file_put_contents($this->csvTestFile, "姓名,年龄,城市\n张三,25,北京\n李四,30,上海\n王五,28,深圳");
        
        // 创建专门的测试XLSX文件
        $this->xlsxTestFile = $this->createTestXlsxFile();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->csvTestFile)) {
            unlink($this->csvTestFile);
        }
        if (file_exists($this->xlsxTestFile)) {
            unlink($this->xlsxTestFile);
        }
    }

    /**
     * 为测试创建专门的XLSX文件
     */
    private function createTestXlsxFile(): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_xlsx_');
        $zip = new \ZipArchive();
        $zip->open($tempFile, \ZipArchive::CREATE);
        
        // 1. 创建[Content_Types].xml
        $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypesXml);
        
        // 2. 创建_rels/.rels
        $mainRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
        $zip->addFromString('_rels/.rels', $mainRelsXml);
        
        // 3. 创建工作簿文件
        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="销售数据" sheetId="1" r:id="rId1"/>
        <sheet name="统计报告" sheetId="2" r:id="rId2"/>
        <sheet name="隐藏表" sheetId="3" r:id="rId3" state="hidden"/>
    </sheets>
</workbook>';
        $zip->addFromString('xl/workbook.xml', $workbookXml);
        
        // 4. 创建工作簿关系文件
        $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>
    <Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
        
        // 5. 创建共享字符串文件
        $sharedStringsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="6" uniqueCount="6">
    <si><t>产品名称</t></si>
    <si><t>销售数量</t></si>
    <si><t>单价</t></si>
    <si><t>笔记本电脑</t></si>
    <si><t>智能手机</t></si>
    <si><t>平板电脑</t></si>
</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
        
        // 6. 创建工作表1：销售数据
        $sheet1Xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>
        <row r="1">
            <c r="A1" t="s"><v>0</v></c>
            <c r="B1" t="s"><v>1</v></c>
            <c r="C1" t="s"><v>2</v></c>
        </row>
        <row r="2">
            <c r="A2" t="s"><v>3</v></c>
            <c r="B2"><v>10</v></c>
            <c r="C2"><v>5000</v></c>
        </row>
        <row r="3">
            <c r="A3" t="s"><v>4</v></c>
            <c r="B3"><v>5</v></c>
            <c r="C3"><v>3000</v></c>
        </row>
        <row r="4">
            <c r="A4" t="s"><v>5</v></c>
            <c r="B4"><v>8</v></c>
            <c r="C4"><v>2500</v></c>
        </row>
    </sheetData>
</worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet1Xml);
        
        // 7. 创建工作表2：统计报告（简单数据）
        $sheet2Xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>
        <row r="1">
            <c r="A1" t="inlineStr"><is><t>总计</t></is></c>
            <c r="B1"><v>23</v></c>
        </row>
        <row r="2">
            <c r="A2" t="inlineStr"><is><t>平均</t></is></c>
            <c r="B2"><v>7.67</v></c>
        </row>
    </sheetData>
</worksheet>';
        $zip->addFromString('xl/worksheets/sheet2.xml', $sheet2Xml);
        
        // 8. 创建工作表3：隐藏表
        $sheet3Xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>
        <row r="1">
            <c r="A1" t="inlineStr"><is><t>机密数据</t></is></c>
        </row>
    </sheetData>
</worksheet>';
        $zip->addFromString('xl/worksheets/sheet3.xml', $sheet3Xml);
        
        $zip->close();
        
        return $tempFile;
    }

    /**
     * 测试Reader构造函数
     */
    public function testConstructor(): void
    {
        $reader = new Reader($this->csvTestFile);
        $this->assertInstanceOf(Reader::class, $reader);
    }

    /**
     * 测试不存在文件的异常处理
     */
    public function testNonExistentFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Reader('nonexistent.xlsx');
    }

    /**
     * 测试CSV文件的getSheets方法
     */
    public function testGetSheetsForCsv(): void
    {
        $reader = new Reader($this->csvTestFile);
        $sheets = $reader->getSheets();
        
        $this->assertIsArray($sheets);
        $this->assertCount(1, $sheets);
        $this->assertEquals('Sheet1', $sheets[0]);
    }

    /**
     * 测试XLSX文件的getSheets方法
     */
    public function testGetSheetsForXlsx(): void
    {
        $reader = new Reader($this->xlsxTestFile);
        $sheets = $reader->getSheets();
        
        $this->assertIsArray($sheets);
        $this->assertGreaterThan(1, count($sheets)); // 恢复：测试文件有多个工作表
        $this->assertContains('销售数据', $sheets);
        $this->assertContains('统计报告', $sheets);
        $this->assertNotContains('隐藏表', $sheets); // 隐藏表不应该在列表中
    }

    /**
     * 测试getSheetsInfo方法
     */
    public function testGetSheetsInfo(): void
    {
        $reader = new Reader($this->xlsxTestFile);
        $sheetsInfo = $reader->getSheetsInfo();
        
        $this->assertIsArray($sheetsInfo);
        $this->assertGreaterThan(1, count($sheetsInfo)); // 恢复：测试文件有多个工作表
        
        // 检查第一个工作表信息
        $firstSheet = $sheetsInfo[0];
        $this->assertArrayHasKey('name', $firstSheet);
        $this->assertArrayHasKey('index', $firstSheet);
        $this->assertArrayHasKey('visible', $firstSheet);
        $this->assertArrayHasKey('active', $firstSheet);
        
        // 验证隐藏表的可见性
        $hiddenSheet = array_filter($sheetsInfo, fn($sheet) => $sheet['name'] === '隐藏表');
        if (!empty($hiddenSheet)) {
            $hiddenSheet = reset($hiddenSheet);
            $this->assertFalse($hiddenSheet['visible']);
        }
    }

    /**
     * 测试按名称选择工作表
     */
    public function testSheetSelectionByName(): void
    {
        $reader = new Reader($this->xlsxTestFile);
        
        // 正常选择
        $result = $reader->sheet('销售数据');
        $this->assertInstanceOf(Reader::class, $result);
        
        // 选择不存在的工作表
        $this->expectException(\InvalidArgumentException::class);
        $reader->sheet('不存在的表');
    }

    /**
     * 测试按索引选择工作表
     */
    public function testSheetSelectionByIndex(): void
    {
        $reader = new Reader($this->xlsxTestFile);
        
        // 正常选择
        $result = $reader->sheet(0);
        $this->assertInstanceOf(Reader::class, $result);
        
        // 选择无效索引
        $this->expectException(\InvalidArgumentException::class);
        $reader->sheet(999);
    }

    /**
     * 测试范围选择功能
     */
    public function testRangeSelection(): void
    {
        $reader = new Reader($this->csvTestFile);
        
        // 正常范围
        $result = $reader->range('A1:C3');
        $this->assertInstanceOf(Reader::class, $result);
        
        // 无效范围格式
        $this->expectException(\InvalidArgumentException::class);
        $reader->range('invalid_range');
    }

    /**
     * 测试范围格式验证
     */
    public function testRangeFormatValidation(): void
    {
        $reader = new Reader($this->csvTestFile);
        
        // 有效格式
        $validRanges = ['A1:C10', 'B2:Z100', 'AA1:BB50'];
        foreach ($validRanges as $range) {
            $result = $reader->range($range);
            $this->assertInstanceOf(Reader::class, $result);
        }
        
        // 无效格式
        $invalidRanges = ['A1-C10', '1A:3C', 'A:C', '1:10'];
        foreach ($invalidRanges as $range) {
            try {
                $reader->range($range);
                $this->fail("应该抛出异常的范围: {$range}");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('无效的范围格式', $e->getMessage());
            }
        }
    }

    /**
     * 测试CSV数据读取
     */
    public function testCsvDataReading(): void
    {
        $reader = new Reader($this->csvTestFile);
        $data = $reader->toArray();
        
        $this->assertIsArray($data);
        $this->assertCount(4, $data); // 修复：包括表头共4行
        
        // 验证第一行数据（表头）
        $this->assertEquals(['姓名', '年龄', '城市'], $data[0]);
        // 验证第二行数据  
        $this->assertEquals(['张三', '25', '北京'], $data[1]);
    }

    /**
     * 测试限制和偏移功能
     */
    public function testLimitAndOffset(): void
    {
        $reader = new Reader($this->csvTestFile);
        
        // 测试限制
        $limitedData = $reader->limit(2)->toArray();
        $this->assertCount(2, $limitedData);
        
        // 重置并测试偏移
        $reader->reset();
        $offsetData = $reader->limit(2, 1)->toArray();
        $this->assertCount(2, $offsetData);
        $this->assertEquals(['张三', '25', '北京'], $offsetData[0]);
    }

    /**
     * 测试过滤器功能
     */
    public function testFilterFunctionality(): void
    {
        $reader = new Reader($this->csvTestFile);
        
        // 添加过滤器：只要包含"北京"的行
        $filteredData = $reader
            ->filter(function($row, $index) {
                return in_array('北京', $row);
            })
            ->toArray();
        
        $this->assertCount(1, $filteredData);
        $this->assertContains('北京', $filteredData[0]);
    }

    /**
     * 测试转换器功能
     */
    public function testTransformerFunctionality(): void
    {
        $reader = new Reader($this->csvTestFile);
        
        // 添加转换器：将所有字符串转为大写
        $transformedData = $reader
            ->transform(function($row, $index) {
                return array_map('strtoupper', $row);
            })
            ->first();
        
        $this->assertContains('姓名', $transformedData);
    }

    /**
     * 测试统计信息
     */
    public function testStatistics(): void
    {
        $reader = new Reader($this->csvTestFile);
        $reader->toArray(); // 触发读取操作
        
        $stats = $reader->getStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('rows_read', $stats);
        $this->assertArrayHasKey('memory_used', $stats);
        $this->assertGreaterThan(0, $stats['rows_read']);
    }

    /**
     * 测试文件信息获取
     */
    public function testFileInfo(): void
    {
        $reader = new Reader($this->csvTestFile);
        $info = $reader->info();
        
        $this->assertIsArray($info);
        $this->assertArrayHasKey('path', $info);
        $this->assertArrayHasKey('size', $info);
        $this->assertArrayHasKey('format', $info);
        $this->assertArrayHasKey('sheets', $info);
        
        $this->assertEquals($this->csvTestFile, $info['path']);
        $this->assertEquals('csv', $info['format']);
    }

    /**
     * 测试链式调用
     */
    public function testMethodChaining(): void
    {
        $reader = new Reader($this->csvTestFile);
        
        $result = $reader
            ->headers(true)
            ->limit(2)
            ->filter(function($row) { return !empty($row); })
            ->transform(function($row) { return $row; });
        
        $this->assertInstanceOf(Reader::class, $result);
        
        $data = $result->toArray();
        $this->assertIsArray($data);
        $this->assertLessThanOrEqual(2, count($data));
    }

    /**
     * 测试重置功能
     */
    public function testReset(): void
    {
        $reader = new Reader($this->csvTestFile);
        
        // 设置一些配置
        $reader->limit(5, 2)->headers(true);
        
        // 重置
        $reader->reset();
        
        // 验证配置被重置
        $data = $reader->toArray();
        $this->assertCount(4, $data); // 修复：应该读取所有4行（包括表头）
    }

    /**
     * 测试迭代器模式
     */
    public function testIteratorPattern(): void
    {
        $reader = new Reader($this->csvTestFile);
        
        $rowCount = 0;
        foreach ($reader->stream() as $row) {
            $this->assertIsArray($row);
            $rowCount++;
        }
        
        $this->assertEquals(4, $rowCount); // 修复：期望4行（包括表头）
    }

    /**
     * 测试批处理功能
     */
    public function testChunkProcessing(): void
    {
        $reader = new Reader($this->csvTestFile);
        
        $processedBatches = 0;
        $reader->chunk(2, function($batch, $batchIndex) use (&$processedBatches) {
            $this->assertIsArray($batch);
            $this->assertLessThanOrEqual(2, count($batch));
            $processedBatches++;
        });
        
        $this->assertGreaterThan(0, $processedBatches);
    }
} 