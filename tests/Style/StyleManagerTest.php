<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Tests\Style;

use LkExcel\LkExportExcel\Style\StyleManager;
use PHPUnit\Framework\TestCase;

class StyleManagerTest extends TestCase
{
    private StyleManager $styleManager;

    protected function setUp(): void
    {
        $this->styleManager = new StyleManager();
    }

    public function testConstructor(): void
    {
        $manager = new StyleManager(5000);
        $stats = $manager->getCacheStats();
        
        $this->assertEquals(5000, $stats['max_cache_size']);
        $this->assertEquals(0, $stats['style_cache_size']);
    }

    public function testGetDefaultStyles(): void
    {
        $defaults = $this->styleManager->getDefaultStyles();
        
        $this->assertArrayHasKey(StyleManager::STYLE_FONT, $defaults);
        $this->assertArrayHasKey(StyleManager::STYLE_FILL, $defaults);
        $this->assertArrayHasKey(StyleManager::STYLE_BORDER, $defaults);
        $this->assertArrayHasKey(StyleManager::STYLE_ALIGNMENT, $defaults);
        $this->assertArrayHasKey(StyleManager::STYLE_NUMBER_FORMAT, $defaults);
        
        // 检查默认字体
        $font = $defaults[StyleManager::STYLE_FONT];
        $this->assertEquals('Calibri', $font[StyleManager::FONT_NAME]);
        $this->assertEquals(11, $font[StyleManager::FONT_SIZE]);
        $this->assertFalse($font[StyleManager::FONT_BOLD]);
        $this->assertFalse($font[StyleManager::FONT_ITALIC]);
    }

    public function testSetDefaultStyles(): void
    {
        $customStyles = [
            StyleManager::STYLE_FONT => [
                StyleManager::FONT_NAME => 'Arial',
                StyleManager::FONT_SIZE => 12
            ]
        ];
        
        $this->styleManager->setDefaultStyles($customStyles);
        $defaults = $this->styleManager->getDefaultStyles();
        
        $this->assertEquals('Arial', $defaults[StyleManager::STYLE_FONT][StyleManager::FONT_NAME]);
        $this->assertEquals(12, $defaults[StyleManager::STYLE_FONT][StyleManager::FONT_SIZE]);
        // 其他属性应该保持默认值
        $this->assertEquals(false, $defaults[StyleManager::STYLE_FONT][StyleManager::FONT_BOLD]);
    }

    public function testParseSimpleStylesXml(): void
    {
        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
            <fonts count="2">
                <font>
                    <sz val="11"/>
                    <color theme="1"/>
                    <name val="Calibri"/>
                </font>
                <font>
                    <sz val="12"/>
                    <b/>
                    <color rgb="FF0000"/>
                    <name val="Arial"/>
                </font>
            </fonts>
            <fills count="2">
                <fill>
                    <patternFill patternType="none"/>
                </fill>
                <fill>
                    <patternFill patternType="solid">
                        <fgColor rgb="FFFF00"/>
                    </patternFill>
                </fill>
            </fills>
            <borders count="1">
                <border>
                    <left/>
                    <right/>
                    <top/>
                    <bottom/>
                </border>
            </borders>
            <cellXfs count="2">
                <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
                <xf numFmtId="0" fontId="1" fillId="1" borderId="0" applyFont="1" applyFill="1"/>
            </cellXfs>
        </styleSheet>';

        $parsedStyles = $this->styleManager->parseStyles($stylesXml);

        // 检查字体解析
        $this->assertArrayHasKey('fonts', $parsedStyles);
        $this->assertCount(2, $parsedStyles['fonts']);
        
        $font1 = $parsedStyles['fonts'][0];
        $this->assertEquals('Calibri', $font1[StyleManager::FONT_NAME]);
        $this->assertEquals(11, $font1[StyleManager::FONT_SIZE]);
        $this->assertFalse($font1[StyleManager::FONT_BOLD]);
        
        $font2 = $parsedStyles['fonts'][1];
        $this->assertEquals('Arial', $font2[StyleManager::FONT_NAME]);
        $this->assertEquals(12, $font2[StyleManager::FONT_SIZE]);
        $this->assertTrue($font2[StyleManager::FONT_BOLD]);
        $this->assertEquals('FF0000', $font2[StyleManager::FONT_COLOR]['rgb']);

        // 检查填充解析
        $this->assertArrayHasKey('fills', $parsedStyles);
        $this->assertCount(2, $parsedStyles['fills']);
        
        $fill1 = $parsedStyles['fills'][0];
        $this->assertEquals('none', $fill1[StyleManager::FILL_PATTERN_TYPE]);
        
        $fill2 = $parsedStyles['fills'][1];
        $this->assertEquals('solid', $fill2[StyleManager::FILL_PATTERN_TYPE]);
        $this->assertEquals('FFFF00', $fill2[StyleManager::FILL_COLOR]['rgb']);

        // 检查单元格样式格式
        $this->assertArrayHasKey('cellXfs', $parsedStyles);
        $this->assertCount(2, $parsedStyles['cellXfs']);
        
        $cellXf2 = $parsedStyles['cellXfs'][1];
        $this->assertEquals(1, $cellXf2['fontId']);
        $this->assertEquals(1, $cellXf2['fillId']);
        $this->assertTrue($cellXf2['applyFont']);
        $this->assertTrue($cellXf2['applyFill']);
    }

    public function testGetCellStyle(): void
    {
        // 创建测试样式数据
        $parsedStyles = [
            'fonts' => [
                0 => [
                    StyleManager::FONT_NAME => 'Calibri',
                    StyleManager::FONT_SIZE => 11,
                    StyleManager::FONT_BOLD => false,
                    StyleManager::FONT_ITALIC => false,
                    StyleManager::FONT_UNDERLINE => false,
                    StyleManager::FONT_STRIKE => false,
                    StyleManager::FONT_COLOR => ['rgb' => '000000']
                ],
                1 => [
                    StyleManager::FONT_NAME => 'Arial',
                    StyleManager::FONT_SIZE => 12,
                    StyleManager::FONT_BOLD => true,
                    StyleManager::FONT_ITALIC => false,
                    StyleManager::FONT_UNDERLINE => false,
                    StyleManager::FONT_STRIKE => false,
                    StyleManager::FONT_COLOR => ['rgb' => 'FF0000']
                ]
            ],
            'fills' => [
                0 => [
                    StyleManager::FILL_TYPE => 'none',
                    StyleManager::FILL_COLOR => null,
                    StyleManager::FILL_PATTERN_TYPE => 'none'
                ],
                1 => [
                    StyleManager::FILL_TYPE => 'none',
                    StyleManager::FILL_COLOR => ['rgb' => 'FFFF00'],
                    StyleManager::FILL_PATTERN_TYPE => 'solid'
                ]
            ],
            'borders' => [
                0 => [
                    StyleManager::BORDER_TOP => null,
                    StyleManager::BORDER_RIGHT => null,
                    StyleManager::BORDER_BOTTOM => null,
                    StyleManager::BORDER_LEFT => null,
                    StyleManager::BORDER_DIAGONAL => null
                ]
            ],
            'cellXfs' => [
                0 => [
                    'fontId' => 0,
                    'fillId' => 0,
                    'borderId' => 0,
                    'numFmtId' => 0,
                    'applyFont' => false,
                    'applyFill' => false,
                    'applyBorder' => false,
                    'applyAlignment' => false,
                    'applyNumberFormat' => false
                ],
                1 => [
                    'fontId' => 1,
                    'fillId' => 1,
                    'borderId' => 0,
                    'numFmtId' => 0,
                    'applyFont' => true,
                    'applyFill' => true,
                    'applyBorder' => false,
                    'applyAlignment' => false,
                    'applyNumberFormat' => false
                ]
            ]
        ];

        // 测试默认样式（索引0）
        $cellStyle0 = $this->styleManager->getCellStyle(0, $parsedStyles);
        $this->assertEquals('Calibri', $cellStyle0[StyleManager::STYLE_FONT][StyleManager::FONT_NAME]);
        $this->assertFalse($cellStyle0[StyleManager::STYLE_FONT][StyleManager::FONT_BOLD]);

        // 测试自定义样式（索引1）
        $cellStyle1 = $this->styleManager->getCellStyle(1, $parsedStyles);
        $this->assertEquals('Arial', $cellStyle1[StyleManager::STYLE_FONT][StyleManager::FONT_NAME]);
        $this->assertTrue($cellStyle1[StyleManager::STYLE_FONT][StyleManager::FONT_BOLD]);
        $this->assertEquals('FF0000', $cellStyle1[StyleManager::STYLE_FONT][StyleManager::FONT_COLOR]['rgb']);
        $this->assertEquals('FFFF00', $cellStyle1[StyleManager::STYLE_FILL][StyleManager::FILL_COLOR]['rgb']);

        // 测试不存在的样式索引，应该返回默认样式
        $cellStyleInvalid = $this->styleManager->getCellStyle(999, $parsedStyles);
        $this->assertEquals($this->styleManager->getDefaultStyles(), $cellStyleInvalid);
    }

    public function testClearCache(): void
    {
        $parsedStyles = [
            'cellXfs' => [
                0 => [
                    'fontId' => 0,
                    'fillId' => 0,
                    'borderId' => 0,
                    'numFmtId' => 0,
                    'applyFont' => false,
                    'applyFill' => false,
                    'applyBorder' => false,
                    'applyAlignment' => false,
                    'applyNumberFormat' => false
                ]
            ]
        ];

        // 添加一些缓存
        $this->styleManager->getCellStyle(0, $parsedStyles);
        $statsBefore = $this->styleManager->getCacheStats();
        $this->assertGreaterThan(0, $statsBefore['style_cache_size']);

        // 清除缓存
        $this->styleManager->clearCache();
        $statsAfter = $this->styleManager->getCacheStats();
        
        $this->assertEquals(0, $statsAfter['style_cache_size']);
        $this->assertEquals(0, $statsAfter['font_cache_size']);
        $this->assertEquals(0, $statsAfter['fill_cache_size']);
        $this->assertEquals(0, $statsAfter['border_cache_size']);
        $this->assertEquals(0, $statsAfter['alignment_cache_size']);
        $this->assertEquals(0, $statsAfter['number_format_cache_size']);
    }

    public function testCacheStats(): void
    {
        $stats = $this->styleManager->getCacheStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('style_cache_size', $stats);
        $this->assertArrayHasKey('font_cache_size', $stats);
        $this->assertArrayHasKey('fill_cache_size', $stats);
        $this->assertArrayHasKey('border_cache_size', $stats);
        $this->assertArrayHasKey('alignment_cache_size', $stats);
        $this->assertArrayHasKey('number_format_cache_size', $stats);
        $this->assertArrayHasKey('max_cache_size', $stats);
        
        $this->assertEquals(10000, $stats['max_cache_size']); // 默认值
    }

    public function testBuiltinNumberFormats(): void
    {
        $parsedStyles = [
            'cellXfs' => [
                0 => [
                    'fontId' => 0,
                    'fillId' => 0,
                    'borderId' => 0,
                    'numFmtId' => 14, // 内置日期格式
                    'applyFont' => false,
                    'applyFill' => false,
                    'applyBorder' => false,
                    'applyAlignment' => false,
                    'applyNumberFormat' => true
                ],
                1 => [
                    'fontId' => 0,
                    'fillId' => 0,
                    'borderId' => 0,
                    'numFmtId' => 2, // 内置数字格式
                    'applyFont' => false,
                    'applyFill' => false,
                    'applyBorder' => false,
                    'applyAlignment' => false,
                    'applyNumberFormat' => true
                ]
            ]
        ];

        $cellStyle1 = $this->styleManager->getCellStyle(0, $parsedStyles);
        $this->assertEquals('m/d/yy', $cellStyle1[StyleManager::STYLE_NUMBER_FORMAT]['formatCode']);
        
        $cellStyle2 = $this->styleManager->getCellStyle(1, $parsedStyles);
        $this->assertEquals('0.00', $cellStyle2[StyleManager::STYLE_NUMBER_FORMAT]['formatCode']);
    }

    public function testInvalidStylesXml(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('样式解析失败');
        
        $invalidXml = '<?xml version="1.0" encoding="UTF-8"?><invalid><xml>';
        $this->styleManager->parseStyles($invalidXml);
    }
} 