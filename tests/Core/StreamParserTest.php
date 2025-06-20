<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Tests\Core;

use PHPUnit\Framework\TestCase;
use LkExcel\LkExportExcel\Core\StreamParser;

/**
 * StreamParser 单元测试
 */
class StreamParserTest extends TestCase
{
    private StreamParser $parser;

    protected function setUp(): void
    {
        $this->parser = new StreamParser();
    }

    public function testConstructor(): void
    {
        $parser = new StreamParser(4096);
        $this->assertEquals('idle', $parser->getState());
    }

    public function testSetOptions(): void
    {
        $options = [
            'skip_empty_rows' => true,
            'memory_threshold' => 100 * 1024 * 1024,
        ];
        
        $result = $this->parser->setOptions($options);
        $this->assertInstanceOf(StreamParser::class, $result);
    }

    public function testParseSharedStrings(): void
    {
        $xmlContent = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
                <si><t>测试字符串1</t></si>
                <si><t>测试字符串2</t></si>
                <si><t>测试字符串3</t></si>
            </sst>';
        
        $result = $this->parser->parseSharedStrings($xmlContent);
        
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals('测试字符串1', $result[0]);
        $this->assertEquals('测试字符串2', $result[1]);
        $this->assertEquals('测试字符串3', $result[2]);
    }

    public function testParseWorksheetStream(): void
    {
        $xmlContent = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
                <sheetData>
                    <row r="1">
                        <c r="A1" t="inlineStr"><is><t>姓名</t></is></c>
                        <c r="B1" t="inlineStr"><is><t>年龄</t></is></c>
                    </row>
                    <row r="2">
                        <c r="A2" t="inlineStr"><is><t>张三</t></is></c>
                        <c r="B2"><v>25</v></c>
                    </row>
                </sheetData>
            </worksheet>';
        
        $rows = [];
        foreach ($this->parser->parseWorksheetStream($xmlContent) as $row) {
            $rows[] = $row;
        }
        
        $this->assertCount(2, $rows);
        // TODO: 验证具体的行数据内容
    }

    public function testStateManagement(): void
    {
        $this->assertEquals('idle', $this->parser->getState());
        
        $this->parser->pause();
        $this->assertEquals('paused', $this->parser->getState());
        
        $this->parser->resume();
        $this->assertEquals('parsing', $this->parser->getState());
    }

    public function testInvalidFileHandling(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $generator = $this->parser->parseXlsx('nonexistent.xlsx');
        // 需要实际调用生成器来触发异常
        iterator_to_array($generator);
    }

    /**
     * 测试工作簿关系解析
     */
    public function testParseWorkbookRelationships(): void
    {
        // 创建临时ZIP文件来测试关系解析
        $tempFile = tempnam(sys_get_temp_dir(), 'test_relations_');
        $zip = new \ZipArchive();
        $zip->open($tempFile, \ZipArchive::CREATE);
        
        // 添加工作簿关系文件
        $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
                <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
                <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
                <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
            </Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $relsXml);
        $zip->close();
        
        // 使用反射访问私有方法
        $reflection = new \ReflectionClass($this->parser);
        $method = $reflection->getMethod('parseWorkbookRelationships');
        $method->setAccessible(true);
        
        $testZip = new \ZipArchive();
        $testZip->open($tempFile);
        $result = $method->invoke($this->parser, $testZip);
        $testZip->close();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('rId1', $result);
        $this->assertEquals('worksheets/sheet1.xml', $result['rId1']['target']);
        $this->assertArrayHasKey('rId2', $result);
        $this->assertEquals('worksheets/sheet2.xml', $result['rId2']['target']);
        
        unlink($tempFile);
    }

    /**
     * 测试增强的工作表信息提取
     */
    public function testExtractWorksheetsInfoWithRelations(): void
    {
        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
                <sheets>
                    <sheet name="数据表1" sheetId="1" r:id="rId1"/>
                    <sheet name="数据表2" sheetId="2" r:id="rId2" state="hidden"/>
                    <sheet name="数据表3" sheetId="3" r:id="rId3"/>
                </sheets>
            </workbook>';
        
        $relations = [
            'rId1' => ['target' => 'worksheets/sheet1.xml'],
            'rId2' => ['target' => 'worksheets/sheet2.xml'],
            'rId3' => ['target' => 'worksheets/sheet3.xml'],
        ];
        
        $reflection = new \ReflectionClass($this->parser);
        $method = $reflection->getMethod('extractWorksheetsInfo');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->parser, $workbookXml, $relations);
        
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        
        // 验证第一个工作表
        $this->assertEquals('数据表1', $result[0]['name']);
        $this->assertEquals('sheet1.xml', $result[0]['filename']);
        $this->assertTrue($result[0]['visible']);
        
        // 验证隐藏工作表
        $this->assertEquals('数据表2', $result[1]['name']);
        $this->assertFalse($result[1]['visible']);
        
        // 验证第三个工作表
        $this->assertEquals('数据表3', $result[2]['name']);
        $this->assertTrue($result[2]['visible']);
    }

    /**
     * 测试目标工作表选择
     */
    public function testGetTargetWorksheets(): void
    {
        $worksheets = [
            ['name' => '数据表1', 'visible' => true],
            ['name' => '数据表2', 'visible' => false],
            ['name' => '数据表3', 'visible' => true],
        ];
        
        $reflection = new \ReflectionClass($this->parser);
        $method = $reflection->getMethod('getTargetWorksheets');
        $method->setAccessible(true);
        
        // 测试默认选择（第一个可见工作表）
        $result = $method->invoke($this->parser, $worksheets);
        $this->assertCount(1, $result);
        $this->assertEquals('数据表1', $result[0]['name']);
        
        // 测试按名称选择
        $this->parser->setOptions(['target_sheet' => '数据表3']);
        $result = $method->invoke($this->parser, $worksheets);
        $this->assertCount(1, $result);
        $this->assertEquals('数据表3', $result[0]['name']);
        
        // 测试按索引选择
        $this->parser->setOptions(['target_sheet' => 1]);
        $result = $method->invoke($this->parser, $worksheets);
        $this->assertCount(1, $result);
        $this->assertEquals('数据表2', $result[0]['name']);
    }

    /**
     * 测试LRU缓存功能
     */
    public function testLRUCaching(): void
    {
        $reflection = new \ReflectionClass($this->parser);
        $method = $reflection->getMethod('addToStringCache');
        $method->setAccessible(true);
        
        // 添加字符串到缓存
        for ($i = 0; $i < 15000; $i++) {
            $method->invoke($this->parser, $i, "测试字符串{$i}");
        }
        
        // 获取共享字符串数组
        $property = $reflection->getProperty('sharedStrings');
        $property->setAccessible(true);
        $sharedStrings = $property->getValue($this->parser);
        
        // 验证LRU清理机制生效
        $this->assertLessThan(15000, count($sharedStrings));
        $this->assertLessThanOrEqual(10000, count($sharedStrings));
    }

    /**
     * 测试内存控制机制
     */
    public function testMemoryControl(): void
    {
        // 设置很低的内存阈值来触发清理
        $this->parser->setOptions(['memory_threshold' => 1024]); // 1KB
        
        $reflection = new \ReflectionClass($this->parser);
        $method = $reflection->getMethod('checkMemoryAndCleanup');
        $method->setAccessible(true);
        
        // 添加大量数据到共享字符串缓存
        $property = $reflection->getProperty('sharedStrings');
        $property->setAccessible(true);
        
        $largeData = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeData[$i] = str_repeat('测试数据', 100);
        }
        $property->setValue($this->parser, $largeData);
        
        // 调用内存检查
        $method->invoke($this->parser);
        
        // 验证缓存被清理
        $cleanedData = $property->getValue($this->parser);
        $this->assertLessThan(1000, count($cleanedData));
    }

    /**
     * 测试分块处理共享字符串
     */
    public function testChunkedStringProcessing(): void
    {
        $xmlContent = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
                <si><t>字符串1</t></si>
                <si><t>字符串2</t></si>
                <si><t>字符串3</t></si>
                <si><t>字符串4</t></si>
                <si><t>字符串5</t></si>
            </sst>';
        
        $reflection = new \ReflectionClass($this->parser);
        $method = $reflection->getMethod('parseSharedStringsChunked');
        $method->setAccessible(true);
        
        // 调用分块处理方法
        $method->invoke($this->parser, $xmlContent);
        
        // 验证共享字符串被正确解析
        $property = $reflection->getProperty('sharedStrings');
        $property->setAccessible(true);
        $sharedStrings = $property->getValue($this->parser);
        
        $this->assertIsArray($sharedStrings);
        $this->assertCount(5, $sharedStrings);
        $this->assertEquals('字符串1', $sharedStrings[0]);
        $this->assertEquals('字符串5', $sharedStrings[4]);
    }

    /**
     * 测试工作表选择功能
     */
    public function testWorksheetSelection(): void
    {
        // 测试按名称选择工作表
        $this->parser->setOptions(['target_sheet' => '特定工作表']);
        
        $reflection = new \ReflectionClass($this->parser);
        $property = $reflection->getProperty('options');
        $property->setAccessible(true);
        $options = $property->getValue($this->parser);
        
        $this->assertEquals('特定工作表', $options['target_sheet']);
        
        // 测试按索引选择工作表
        $this->parser->setOptions(['target_sheet' => 2]);
        $options = $property->getValue($this->parser);
        
        $this->assertEquals(2, $options['target_sheet']);
    }
} 