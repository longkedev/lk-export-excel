<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Api;

/**
 * Excel文件写入器接口
 * 
 * 定义统一的文件写入API标准，支持流式写入和大文件处理
 * 设计原则：
 * - 高性能：流式写入，内存恒定
 * - 易用性：链式调用API
 * - 灵活性：丰富的格式设置
 * - 扩展性：支持自定义格式
 */
interface WriterInterface
{
    /**
     * 创建工作表
     * 
     * @param string $name 工作表名称
     * @return static
     */
    public function createSheet(string $name): static;

    /**
     * 设置当前工作表
     * 
     * @param string|int $sheet 工作表名称或索引
     * @return static
     */
    public function sheet(string|int $sheet): static;

    /**
     * 写入数据行
     * 
     * @param array $data 数据行
     * @param array|null $styles 样式配置
     * @return static
     */
    public function writeRow(array $data, ?array $styles = null): static;

    /**
     * 写入多行数据
     * 
     * @param array $rows 多行数据
     * @param array|null $styles 样式配置
     * @return static
     */
    public function writeRows(array $rows, ?array $styles = null): static;

    /**
     * 写入表头
     * 
     * @param array $headers 表头数据
     * @param array|null $styles 表头样式
     * @return static
     */
    public function writeHeaders(array $headers, ?array $styles = null): static;

    /**
     * 设置单元格值
     * 
     * @param string $cell 单元格地址，如 'A1'
     * @param mixed $value 单元格值
     * @param array|null $style 单元格样式
     * @return static
     */
    public function setCell(string $cell, mixed $value, ?array $style = null): static;

    /**
     * 设置列宽
     * 
     * @param string|int $column 列标识
     * @param float $width 列宽
     * @return static
     */
    public function setColumnWidth(string|int $column, float $width): static;

    /**
     * 设置行高
     * 
     * @param int $row 行号
     * @param float $height 行高
     * @return static
     */
    public function setRowHeight(int $row, float $height): static;

    /**
     * 合并单元格
     * 
     * @param string $range 合并范围，如 'A1:C3'
     * @return static
     */
    public function mergeCells(string $range): static;

    /**
     * 设置单元格样式
     * 
     * @param string $range 单元格范围
     * @param array $style 样式配置
     * @return static
     */
    public function setStyle(string $range, array $style): static;

    /**
     * 设置字体样式
     * 
     * @param string $range 单元格范围
     * @param array $font 字体配置
     * @return static
     */
    public function setFont(string $range, array $font): static;

    /**
     * 设置填充样式
     * 
     * @param string $range 单元格范围
     * @param array $fill 填充配置
     * @return static
     */
    public function setFill(string $range, array $fill): static;

    /**
     * 设置边框样式
     * 
     * @param string $range 单元格范围
     * @param array $border 边框配置
     * @return static
     */
    public function setBorder(string $range, array $border): static;

    /**
     * 设置数字格式
     * 
     * @param string $range 单元格范围
     * @param string $format 数字格式代码
     * @return static
     */
    public function setNumberFormat(string $range, string $format): static;

    /**
     * 插入图片
     * 
     * @param string $cell 插入位置
     * @param string $imagePath 图片路径
     * @param array|null $options 图片选项
     * @return static
     */
    public function insertImage(string $cell, string $imagePath, ?array $options = null): static;

    /**
     * 插入图表
     * 
     * @param string $cell 插入位置
     * @param array $chartConfig 图表配置
     * @return static
     */
    public function insertChart(string $cell, array $chartConfig): static;

    /**
     * 添加数据验证
     * 
     * @param string $range 应用范围
     * @param array $validation 验证规则
     * @return static
     */
    public function addDataValidation(string $range, array $validation): static;

    /**
     * 添加条件格式
     * 
     * @param string $range 应用范围
     * @param array $condition 条件配置
     * @return static
     */
    public function addConditionalFormatting(string $range, array $condition): static;

    /**
     * 保护工作表
     * 
     * @param string|null $password 保护密码
     * @param array|null $options 保护选项
     * @return static
     */
    public function protect(?string $password = null, ?array $options = null): static;

    /**
     * 设置打印选项
     * 
     * @param array $options 打印选项
     * @return static
     */
    public function setPrintOptions(array $options): static;

    /**
     * 设置页面设置
     * 
     * @param array $pageSetup 页面设置
     * @return static
     */
    public function setPageSetup(array $pageSetup): static;

    /**
     * 添加公式
     * 
     * @param string $cell 单元格地址
     * @param string $formula 公式
     * @return static
     */
    public function setFormula(string $cell, string $formula): static;

    /**
     * 从数据源写入
     * 
     * @param iterable $dataSource 数据源
     * @param callable|null $mapper 数据映射器
     * @return static
     */
    public function fromDataSource(iterable $dataSource, ?callable $mapper = null): static;

    /**
     * 流式写入大量数据
     * 
     * @param \Generator $dataGenerator 数据生成器
     * @param array|null $options 写入选项
     * @return static
     */
    public function streamWrite(\Generator $dataGenerator, ?array $options = null): static;

    /**
     * 获取当前行号
     * 
     * @return int 当前行号
     */
    public function getCurrentRow(): int;

    /**
     * 获取当前列号
     * 
     * @return int 当前列号
     */
    public function getCurrentColumn(): int;

    /**
     * 获取工作表数量
     * 
     * @return int 工作表数量
     */
    public function getSheetCount(): int;

    /**
     * 获取写入统计
     * 
     * @return array 写入统计信息
     */
    public function getStats(): array;

    /**
     * 保存文件
     * 
     * @param string $filename 文件路径
     * @param array|null $options 保存选项
     * @return bool 是否成功
     */
    public function save(string $filename, ?array $options = null): bool;

    /**
     * 输出到浏览器
     * 
     * @param string $filename 下载文件名
     * @param array|null $headers 额外HTTP头
     * @return void
     */
    public function download(string $filename, ?array $headers = null): void;

    /**
     * 获取文件内容
     * 
     * @return string 文件二进制内容
     */
    public function getContent(): string;

    /**
     * 获取临时文件路径
     * 
     * @return string|null 临时文件路径
     */
    public function getTempFile(): ?string;

    /**
     * 关闭写入器并清理资源
     * 
     * @return void
     */
    public function close(): void;
} 