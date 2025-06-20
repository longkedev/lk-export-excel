<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel;

use LkExcel\LkExportExcel\Api\Reader;
use LkExcel\LkExportExcel\Api\Writer;
use LkExcel\LkExportExcel\Api\Converter;

/**
 * lkExportExcel - 新一代PHP Excel处理库
 * 
 * 主入口类，提供统一的API接口
 * 设计原则：简洁、高性能、低内存
 */
class LkExportExcel
{
    /**
     * 全局配置
     */
    private static array $config = [
        'memory_limit' => 67108864,        // 64MB默认内存限制
        'chunk_size' => 1000,              // 默认分块大小
        'buffer_size' => 8192,             // 默认缓冲区大小
        'temp_dir' => '/tmp/lkexportexcel', // 临时目录
        'cache_enabled' => true,           // 是否启用缓存
    ];

    /**
     * 配置系统参数
     * 
     * @param array $config 配置数组
     */
    public static function config(array $config): void
    {
        self::$config = array_merge(self::$config, $config);
    }

    /**
     * 获取配置值
     * 
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function getConfig(string $key, mixed $default = null): mixed
    {
        return self::$config[$key] ?? $default;
    }

    /**
     * 读取Excel文件
     * 
     * @param string $path 文件路径
     * @return Reader
     */
    public static function read(string $path): Reader
    {
        return new Reader($path, self::$config);
    }

    /**
     * 创建Excel写入器
     * 
     * @return Writer
     */
    public static function write(): Writer
    {
        return new Writer(self::$config);
    }

    /**
     * 文件格式转换
     * 
     * @param string $from 源文件路径
     * @param string $to 目标文件路径
     * @return Converter
     */
    public static function convert(string $from, string $to): Converter
    {
        return new Converter($from, $to, self::$config);
    }

    /**
     * 获取版本信息
     * 
     * @return string
     */
    public static function version(): string
    {
        return '1.0.0-alpha';
    }

    /**
     * 获取系统信息
     * 
     * @return array
     */
    public static function systemInfo(): array
    {
        return [
            'version' => self::version(),
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'extensions' => [
                'zip' => extension_loaded('zip'),
                'xml' => extension_loaded('xml'),
                'mbstring' => extension_loaded('mbstring'),
                'zlib' => extension_loaded('zlib'),
            ],
        ];
    }
} 