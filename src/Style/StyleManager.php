<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Style;

/**
 * Excel样式管理器
 * 
 * 负责样式的解析、缓存、应用和管理
 * 设计目标：
 * - 高性能样式处理
 * - 样式复用和缓存
 * - 内存优化
 * - 支持完整的Excel样式特性
 */
class StyleManager
{
    // 样式类型常量
    public const STYLE_FONT = 'font';
    public const STYLE_FILL = 'fill';
    public const STYLE_BORDER = 'border';
    public const STYLE_ALIGNMENT = 'alignment';
    public const STYLE_NUMBER_FORMAT = 'numberFormat';
    
    // 字体样式属性
    public const FONT_NAME = 'fontName';
    public const FONT_SIZE = 'fontSize';
    public const FONT_BOLD = 'bold';
    public const FONT_ITALIC = 'italic';
    public const FONT_UNDERLINE = 'underline';
    public const FONT_STRIKE = 'strikethrough';
    public const FONT_COLOR = 'color';
    
    // 填充样式属性
    public const FILL_TYPE = 'fillType';
    public const FILL_COLOR = 'fillColor';
    public const FILL_PATTERN_TYPE = 'patternType';
    public const FILL_START_COLOR = 'startColor';
    public const FILL_END_COLOR = 'endColor';
    
    // 边框样式属性
    public const BORDER_TOP = 'top';
    public const BORDER_RIGHT = 'right';
    public const BORDER_BOTTOM = 'bottom';
    public const BORDER_LEFT = 'left';
    public const BORDER_DIAGONAL = 'diagonal';
    
    // 对齐样式属性
    public const ALIGN_HORIZONTAL = 'horizontal';
    public const ALIGN_VERTICAL = 'vertical';
    public const ALIGN_WRAP_TEXT = 'wrapText';
    public const ALIGN_SHRINK_TO_FIT = 'shrinkToFit';
    public const ALIGN_INDENT = 'indent';
    public const ALIGN_TEXT_ROTATION = 'textRotation';

    private array $styleCache = [];
    private array $fontCache = [];
    private array $fillCache = [];
    private array $borderCache = [];
    private array $alignmentCache = [];
    private array $numberFormatCache = [];
    
    private int $maxCacheSize;
    private array $defaultStyles;

    public function __construct(int $maxCacheSize = 10000)
    {
        $this->maxCacheSize = $maxCacheSize;
        $this->initializeDefaultStyles();
    }

    /**
     * 初始化默认样式
     */
    private function initializeDefaultStyles(): void
    {
        $this->defaultStyles = [
            self::STYLE_FONT => [
                self::FONT_NAME => 'Calibri',
                self::FONT_SIZE => 11,
                self::FONT_BOLD => false,
                self::FONT_ITALIC => false,
                self::FONT_UNDERLINE => false,
                self::FONT_STRIKE => false,
                self::FONT_COLOR => ['rgb' => '000000']
            ],
            self::STYLE_FILL => [
                self::FILL_TYPE => 'none',
                self::FILL_COLOR => null,
                self::FILL_PATTERN_TYPE => 'none'
            ],
            self::STYLE_BORDER => [
                self::BORDER_TOP => null,
                self::BORDER_RIGHT => null,
                self::BORDER_BOTTOM => null,
                self::BORDER_LEFT => null,
                self::BORDER_DIAGONAL => null
            ],
            self::STYLE_ALIGNMENT => [
                self::ALIGN_HORIZONTAL => 'general',
                self::ALIGN_VERTICAL => 'bottom',
                self::ALIGN_WRAP_TEXT => false,
                self::ALIGN_SHRINK_TO_FIT => false,
                self::ALIGN_INDENT => 0,
                self::ALIGN_TEXT_ROTATION => 0
            ],
            self::STYLE_NUMBER_FORMAT => [
                'formatCode' => 'General'
            ]
        ];
    }

    /**
     * 解析Excel样式XML
     * 
     * @param string $stylesXml 样式XML内容
     * @return array 解析后的样式数组
     */
    public function parseStyles(string $stylesXml): array
    {
        $styles = [];
        
        try {
            $xml = new \SimpleXMLElement($stylesXml);
            
            // 解析字体样式
            if (isset($xml->fonts)) {
                $styles['fonts'] = $this->parseFonts($xml->fonts);
            }
            
            // 解析填充样式
            if (isset($xml->fills)) {
                $styles['fills'] = $this->parseFills($xml->fills);
            }
            
            // 解析边框样式
            if (isset($xml->borders)) {
                $styles['borders'] = $this->parseBorders($xml->borders);
            }
            
            // 解析单元格样式
            if (isset($xml->cellXfs)) {
                $styles['cellXfs'] = $this->parseCellXfs($xml->cellXfs);
            }
            
            // 解析数字格式
            if (isset($xml->numFmts)) {
                $styles['numFmts'] = $this->parseNumberFormats($xml->numFmts);
            }
            
        } catch (\Exception $e) {
            throw new \RuntimeException("样式解析失败: " . $e->getMessage(), 0, $e);
        }
        
        return $styles;
    }

    /**
     * 解析字体样式
     */
    private function parseFonts(\SimpleXMLElement $fontsElement): array
    {
        $fonts = [];
        $index = 0;
        
        foreach ($fontsElement->font as $fontElement) {
            $font = $this->defaultStyles[self::STYLE_FONT];
            
            if (isset($fontElement->name)) {
                $font[self::FONT_NAME] = (string)$fontElement->name['val'];
            }
            
            if (isset($fontElement->sz)) {
                $font[self::FONT_SIZE] = (float)$fontElement->sz['val'];
            }
            
            $font[self::FONT_BOLD] = isset($fontElement->b);
            $font[self::FONT_ITALIC] = isset($fontElement->i);
            $font[self::FONT_UNDERLINE] = isset($fontElement->u);
            $font[self::FONT_STRIKE] = isset($fontElement->strike);
            
            if (isset($fontElement->color)) {
                $font[self::FONT_COLOR] = $this->parseColor($fontElement->color);
            }
            
            $fonts[$index] = $font;
            $index++;
        }
        
        return $fonts;
    }

    /**
     * 解析填充样式
     */
    private function parseFills(\SimpleXMLElement $fillsElement): array
    {
        $fills = [];
        $index = 0;
        
        foreach ($fillsElement->fill as $fillElement) {
            $fill = $this->defaultStyles[self::STYLE_FILL];
            
            if (isset($fillElement->patternFill)) {
                $patternFill = $fillElement->patternFill;
                $fill[self::FILL_PATTERN_TYPE] = (string)$patternFill['patternType'];
                
                if (isset($patternFill->fgColor)) {
                    $fill[self::FILL_COLOR] = $this->parseColor($patternFill->fgColor);
                }
                
                if (isset($patternFill->bgColor)) {
                    $fill[self::FILL_START_COLOR] = $this->parseColor($patternFill->bgColor);
                }
            }
            
            if (isset($fillElement->gradientFill)) {
                $fill[self::FILL_TYPE] = 'gradient';
                // 处理渐变填充...
            }
            
            $fills[$index] = $fill;
            $index++;
        }
        
        return $fills;
    }

    /**
     * 解析边框样式
     */
    private function parseBorders(\SimpleXMLElement $bordersElement): array
    {
        $borders = [];
        $index = 0;
        
        foreach ($bordersElement->border as $borderElement) {
            $border = $this->defaultStyles[self::STYLE_BORDER];
            
            foreach (['left', 'right', 'top', 'bottom', 'diagonal'] as $side) {
                if (isset($borderElement->$side)) {
                    $sideElement = $borderElement->$side;
                    $sideStyle = [
                        'style' => (string)$sideElement['style'] ?? 'none'
                    ];
                    
                    if (isset($sideElement->color)) {
                        $sideStyle['color'] = $this->parseColor($sideElement->color);
                    }
                    
                    $border[$side] = $sideStyle;
                }
            }
            
            $borders[$index] = $border;
            $index++;
        }
        
        return $borders;
    }

    /**
     * 解析单元格样式格式
     */
    private function parseCellXfs(\SimpleXMLElement $cellXfsElement): array
    {
        $cellXfs = [];
        $index = 0;
        
        foreach ($cellXfsElement->xf as $xfElement) {
            $cellXf = [
                'fontId' => (int)($xfElement['fontId'] ?? 0),
                'fillId' => (int)($xfElement['fillId'] ?? 0),
                'borderId' => (int)($xfElement['borderId'] ?? 0),
                'numFmtId' => (int)($xfElement['numFmtId'] ?? 0),
                'applyFont' => filter_var($xfElement['applyFont'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'applyFill' => filter_var($xfElement['applyFill'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'applyBorder' => filter_var($xfElement['applyBorder'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'applyAlignment' => filter_var($xfElement['applyAlignment'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'applyNumberFormat' => filter_var($xfElement['applyNumberFormat'] ?? false, FILTER_VALIDATE_BOOLEAN)
            ];
            
            // 解析对齐属性
            if (isset($xfElement->alignment)) {
                $cellXf['alignment'] = $this->parseAlignment($xfElement->alignment);
            }
            
            $cellXfs[$index] = $cellXf;
            $index++;
        }
        
        return $cellXfs;
    }

    /**
     * 解析对齐样式
     */
    private function parseAlignment(\SimpleXMLElement $alignmentElement): array
    {
        $alignment = $this->defaultStyles[self::STYLE_ALIGNMENT];
        
        if (isset($alignmentElement['horizontal'])) {
            $alignment[self::ALIGN_HORIZONTAL] = (string)$alignmentElement['horizontal'];
        }
        
        if (isset($alignmentElement['vertical'])) {
            $alignment[self::ALIGN_VERTICAL] = (string)$alignmentElement['vertical'];
        }
        
        if (isset($alignmentElement['wrapText'])) {
            $alignment[self::ALIGN_WRAP_TEXT] = filter_var($alignmentElement['wrapText'], FILTER_VALIDATE_BOOLEAN);
        }
        
        if (isset($alignmentElement['shrinkToFit'])) {
            $alignment[self::ALIGN_SHRINK_TO_FIT] = filter_var($alignmentElement['shrinkToFit'], FILTER_VALIDATE_BOOLEAN);
        }
        
        if (isset($alignmentElement['indent'])) {
            $alignment[self::ALIGN_INDENT] = (int)$alignmentElement['indent'];
        }
        
        if (isset($alignmentElement['textRotation'])) {
            $alignment[self::ALIGN_TEXT_ROTATION] = (int)$alignmentElement['textRotation'];
        }
        
        return $alignment;
    }

    /**
     * 解析数字格式
     */
    private function parseNumberFormats(\SimpleXMLElement $numFmtsElement): array
    {
        $numFmts = [];
        
        foreach ($numFmtsElement->numFmt as $numFmt) {
            $numFmts[(int)$numFmt['numFmtId']] = [
                'formatCode' => (string)$numFmt['formatCode']
            ];
        }
        
        return $numFmts;
    }

    /**
     * 解析颜色值
     */
    private function parseColor(\SimpleXMLElement $colorElement): array
    {
        $color = [];
        
        if (isset($colorElement['rgb'])) {
            $color['rgb'] = (string)$colorElement['rgb'];
        } elseif (isset($colorElement['indexed'])) {
            $color['indexed'] = (int)$colorElement['indexed'];
        } elseif (isset($colorElement['theme'])) {
            $color['theme'] = (int)$colorElement['theme'];
            if (isset($colorElement['tint'])) {
                $color['tint'] = (float)$colorElement['tint'];
            }
        } elseif (isset($colorElement['auto'])) {
            $color['auto'] = filter_var($colorElement['auto'], FILTER_VALIDATE_BOOLEAN);
        }
        
        return $color;
    }

    /**
     * 获取单元格完整样式
     * 
     * @param int $styleIndex 样式索引
     * @param array $parsedStyles 解析后的样式数组
     * @return array 完整的单元格样式
     */
    public function getCellStyle(int $styleIndex, array $parsedStyles): array
    {
        // 检查缓存
        $cacheKey = "cell_style_{$styleIndex}";
        if (isset($this->styleCache[$cacheKey])) {
            return $this->styleCache[$cacheKey];
        }
        
        $cellStyle = [];
        
        if (!isset($parsedStyles['cellXfs'][$styleIndex])) {
            return $this->defaultStyles;
        }
        
        $cellXf = $parsedStyles['cellXfs'][$styleIndex];
        
        // 应用字体样式
        if ($cellXf['applyFont'] && isset($parsedStyles['fonts'][$cellXf['fontId']])) {
            $cellStyle[self::STYLE_FONT] = $parsedStyles['fonts'][$cellXf['fontId']];
        } else {
            $cellStyle[self::STYLE_FONT] = $this->defaultStyles[self::STYLE_FONT];
        }
        
        // 应用填充样式
        if ($cellXf['applyFill'] && isset($parsedStyles['fills'][$cellXf['fillId']])) {
            $cellStyle[self::STYLE_FILL] = $parsedStyles['fills'][$cellXf['fillId']];
        } else {
            $cellStyle[self::STYLE_FILL] = $this->defaultStyles[self::STYLE_FILL];
        }
        
        // 应用边框样式
        if ($cellXf['applyBorder'] && isset($parsedStyles['borders'][$cellXf['borderId']])) {
            $cellStyle[self::STYLE_BORDER] = $parsedStyles['borders'][$cellXf['borderId']];
        } else {
            $cellStyle[self::STYLE_BORDER] = $this->defaultStyles[self::STYLE_BORDER];
        }
        
        // 应用对齐样式
        if ($cellXf['applyAlignment'] && isset($cellXf['alignment'])) {
            $cellStyle[self::STYLE_ALIGNMENT] = $cellXf['alignment'];
        } else {
            $cellStyle[self::STYLE_ALIGNMENT] = $this->defaultStyles[self::STYLE_ALIGNMENT];
        }
        
        // 应用数字格式
        if ($cellXf['applyNumberFormat']) {
            $numFmtId = $cellXf['numFmtId'];
            if (isset($parsedStyles['numFmts'][$numFmtId])) {
                $cellStyle[self::STYLE_NUMBER_FORMAT] = $parsedStyles['numFmts'][$numFmtId];
            } else {
                // 使用内置数字格式
                $cellStyle[self::STYLE_NUMBER_FORMAT] = [
                    'formatCode' => $this->getBuiltinNumberFormat($numFmtId)
                ];
            }
        } else {
            $cellStyle[self::STYLE_NUMBER_FORMAT] = $this->defaultStyles[self::STYLE_NUMBER_FORMAT];
        }
        
        // 缓存样式
        $this->cacheStyle($cacheKey, $cellStyle);
        
        return $cellStyle;
    }

    /**
     * 获取内置数字格式
     */
    private function getBuiltinNumberFormat(int $numFmtId): string
    {
        $builtinFormats = [
            0 => 'General',
            1 => '0',
            2 => '0.00',
            3 => '#,##0',
            4 => '#,##0.00',
            9 => '0%',
            10 => '0.00%',
            11 => '0.00E+00',
            12 => '# ?/?',
            13 => '# ??/??',
            14 => 'm/d/yy',
            15 => 'd-mmm-yy',
            16 => 'd-mmm',
            17 => 'mmm-yy',
            18 => 'h:mm AM/PM',
            19 => 'h:mm:ss AM/PM',
            20 => 'h:mm',
            21 => 'h:mm:ss',
            22 => 'm/d/yy h:mm',
            37 => '#,##0 ;(#,##0)',
            38 => '#,##0 ;[Red](#,##0)',
            39 => '#,##0.00;(#,##0.00)',
            40 => '#,##0.00;[Red](#,##0.00)',
            45 => 'mm:ss',
            46 => '[h]:mm:ss',
            47 => 'mmss.0',
            48 => '##0.0E+0',
            49 => '@'
        ];
        
        return $builtinFormats[$numFmtId] ?? 'General';
    }

    /**
     * 缓存样式
     */
    private function cacheStyle(string $key, array $style): void
    {
        if (count($this->styleCache) >= $this->maxCacheSize) {
            // 清理最旧的缓存项
            $this->styleCache = array_slice($this->styleCache, -($this->maxCacheSize / 2), null, true);
        }
        
        $this->styleCache[$key] = $style;
    }

    /**
     * 清除样式缓存
     */
    public function clearCache(): self
    {
        $this->styleCache = [];
        $this->fontCache = [];
        $this->fillCache = [];
        $this->borderCache = [];
        $this->alignmentCache = [];
        $this->numberFormatCache = [];
        
        return $this;
    }

    /**
     * 获取缓存统计信息
     */
    public function getCacheStats(): array
    {
        return [
            'style_cache_size' => count($this->styleCache),
            'font_cache_size' => count($this->fontCache),
            'fill_cache_size' => count($this->fillCache),
            'border_cache_size' => count($this->borderCache),
            'alignment_cache_size' => count($this->alignmentCache),
            'number_format_cache_size' => count($this->numberFormatCache),
            'max_cache_size' => $this->maxCacheSize
        ];
    }

    /**
     * 获取默认样式
     */
    public function getDefaultStyles(): array
    {
        return $this->defaultStyles;
    }

    /**
     * 设置默认样式
     */
    public function setDefaultStyles(array $styles): self
    {
        $this->defaultStyles = array_merge($this->defaultStyles, $styles);
        return $this;
    }
} 