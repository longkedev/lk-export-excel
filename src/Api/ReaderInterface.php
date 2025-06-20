<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Api;

/**
 * Excel文件读取器接口
 * 
 * 定义统一的文件读取API标准，支持链式调用和流式处理
 * 设计原则：
 * - 易用性：简洁直观的API
 * - 高性能：流式处理，低内存占用
 * - 灵活性：丰富的配置选项
 * - 可扩展：插件式架构
 */
interface ReaderInterface
{
    /**
     * 设置要读取的工作表
     * 
     * @param string|int $sheet 工作表名称或索引
     * @return static
     */
    public function sheet(string|int $sheet): static;

    /**
     * 设置读取范围
     * 
     * @param string $range Excel范围，如 'A1:C100'
     * @return static
     */
    public function range(string $range): static;

    /**
     * 设置起始行和列
     * 
     * @param int $startRow 起始行（1开始）
     * @param int $startCol 起始列（1开始）
     * @return static
     */
    public function start(int $startRow, int $startCol = 1): static;

    /**
     * 设置读取限制
     * 
     * @param int $limit 最大行数
     * @param int $offset 跳过行数
     * @return static
     */
    public function limit(int $limit, int $offset = 0): static;

    /**
     * 设置过滤器
     * 
     * @param callable $filter 过滤函数 function(array $row, int $index): bool
     * @return static
     */
    public function filter(callable $filter): static;

    /**
     * 设置数据转换器
     * 
     * @param callable $transformer 转换函数 function(array $row, int $index): array
     * @return static
     */
    public function transform(callable $transformer): static;

    /**
     * 设置列映射
     * 
     * @param array $mapping 列映射 ['A' => 'name', 'B' => 'age']
     * @return static
     */
    public function columns(array $mapping): static;

    /**
     * 设置是否包含表头
     * 
     * @param bool $hasHeaders 是否有表头
     * @return static
     */
    public function headers(bool $hasHeaders = true): static;

    /**
     * 设置读取选项
     * 
     * @param array $options 配置选项
     * @return static
     */
    public function options(array $options): static;

    /**
     * 获取文件信息
     * 
     * @return array 文件基本信息
     */
    public function info(): array;

    /**
     * 获取工作表列表
     * 
     * @return array 工作表名称列表
     */
    public function getSheets(): array;

    /**
     * 获取行数统计
     * 
     * @return int 总行数
     */
    public function count(): int;

    /**
     * 转换为数组（一次性加载）
     * 
     * @return array 所有数据
     */
    public function toArray(): array;

    /**
     * 转换为迭代器（流式处理）
     * 
     * @return \Generator 数据迭代器
     */
    public function toIterator(): \Generator;

    /**
     * 执行回调处理每一行
     * 
     * @param callable $callback 回调函数 function(array $row, int $index): void
     * @return static
     */
    public function each(callable $callback): static;

    /**
     * 获取第一行数据
     * 
     * @return array|null 第一行数据
     */
    public function first(): ?array;

    /**
     * 获取指定行数据
     * 
     * @param int $row 行号（1开始）
     * @return array|null 行数据
     */
    public function row(int $row): ?array;

    /**
     * 获取指定列数据
     * 
     * @param string|int $column 列标识
     * @return array 列数据
     */
    public function column(string|int $column): array;

    /**
     * 获取单元格值
     * 
     * @param string $cell 单元格地址，如 'A1'
     * @return mixed 单元格值
     */
    public function cell(string $cell): mixed;

    /**
     * 批量处理数据
     * 
     * @param int $size 批次大小
     * @param callable $callback 批处理回调 function(array $batch, int $batchIndex): void
     * @return static
     */
    public function chunk(int $size, callable $callback): static;

    /**
     * 流式读取（内存友好）
     * 
     * @return \Generator 数据生成器
     */
    public function stream(): \Generator;

    /**
     * 获取性能统计
     * 
     * @return array 性能数据
     */
    public function getStats(): array;

    /**
     * 重置所有配置
     * 
     * @return static
     */
    public function reset(): static;
} 