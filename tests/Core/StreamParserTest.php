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
} 