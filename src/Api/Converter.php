<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Api;

/**
 * 文件格式转换器
 */
class Converter
{
    private string $fromPath;
    private string $toPath;
    private array $config;

    public function __construct(string $from, string $to, array $config = [])
    {
        $this->fromPath = $from;
        $this->toPath = $to;
        $this->config = $config;
    }

    /**
     * 执行转换
     * 
     * @return bool
     */
    public function convert(): bool
    {
        // TODO: 实现格式转换逻辑
        return true;
    }
} 