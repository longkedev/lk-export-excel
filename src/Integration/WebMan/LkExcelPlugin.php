<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Integration\WebMan;

use LkExcel\LkExportExcel\Api\Reader;
use LkExcel\LkExportExcel\Api\Writer;
use LkExcel\LkExportExcel\LkExportExcel;
use LkExcel\LkExportExcel\Performance\PerformanceMonitor;

/**
 * WebMan框架lkExportExcel插件
 * 
 * 提供WebMan框架的深度集成支持：
 * - 服务容器绑定
 * - 配置管理
 * - Response辅助方法
 * - 文件上传处理
 * 
 * 使用方法：
 * 1. 在config/bootstrap.php中调用: LkExcelPlugin::install();
 * 2. 在控制器中使用: $this->excel()->read($file);
 */
class LkExcelPlugin
{
    /**
     * 容器实例
     */
    private static array $instances = [];

    /**
     * 配置数组
     */
    private static array $config = [];

    /**
     * 插件安装
     */
    public static function install(array $config = []): void
    {
        // 设置配置
        self::$config = array_merge([
            'memory_limit' => 64 * 1024 * 1024, // 64MB
            'chunk_size' => 1000,
            'buffer_size' => 8192,
            'performance_enabled' => true,
            'temp_dir' => sys_get_temp_dir() . '/lkexcel',
        ], $config);

        // 应用配置到lkExportExcel
        LkExportExcel::config(self::$config);

        // 确保临时目录存在
        if (!is_dir(self::$config['temp_dir'])) {
            mkdir(self::$config['temp_dir'], 0755, true);
        }
    }

    /**
     * 创建Reader实例
     */
    public static function reader(string $filePath = ''): Reader
    {
        return new Reader($filePath);
    }

    /**
     * 获取Writer实例
     */
    public static function writer(): Writer
    {
        if (!isset(self::$instances['writer'])) {
            self::$instances['writer'] = new Writer();
        }
        return self::$instances['writer'];
    }

    /**
     * 获取性能监控器实例
     */
    public static function monitor(): PerformanceMonitor
    {
        if (!isset(self::$instances['monitor'])) {
            self::$instances['monitor'] = new PerformanceMonitor(
                self::$config['performance_enabled'] ?? true
            );
        }
        return self::$instances['monitor'];
    }

    /**
     * 创建Excel下载响应
     */
    public static function downloadExcel($data, string $filename = 'export.xlsx', array $options = []): array
    {
        $writer = self::writer();
        
        // 处理数据
        if (is_array($data)) {
            // 如果有表头选项
            if (isset($options['headers'])) {
                $writer->writeHeaders($options['headers']);
                // 设置表头样式
                if (isset($options['header_style'])) {
                    $headerRange = 'A1:' . chr(65 + count($options['headers']) - 1) . '1';
                    $writer->setStyle($headerRange, $options['header_style']);
                }
            }
            $writer->writeRows($data);
        } elseif (is_callable($data)) {
            $writer->streamWrite($data());
        } else {
            throw new \InvalidArgumentException('数据必须是数组或可调用对象');
        }

        // 获取内容
        $content = $writer->getContent();
        
        // 返回WebMan响应数组格式
        return [
            'status' => 200,
            'headers' => [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => strlen($content),
                'Cache-Control' => 'max-age=0',
            ],
            'content' => $content,
        ];
    }

    /**
     * 从上传文件读取Excel
     */
    public static function readFromUpload(array $file): Reader
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException('无效的上传文件');
        }

        return self::reader($file['tmp_name']);
    }

    /**
     * 批量处理多个文件
     */
    public static function batch(array $files, callable $processor): array
    {
        $results = [];
        $monitor = self::monitor();
        
        foreach ($files as $index => $file) {
            $monitor->startTimer("batch_process_{$index}");
            
            try {
                $reader = new Reader($file);
                $results[$file] = $processor($reader, $file);
            } catch (\Exception $e) {
                $results[$file] = ['error' => $e->getMessage()];
            }
            
            $monitor->endTimer("batch_process_{$index}");
        }
        
        return $results;
    }

    /**
     * 获取性能报告
     */
    public static function getPerformanceReport(): array
    {
        return self::monitor()->generateReport();
    }

    /**
     * 清理临时文件
     */
    public static function cleanup(): void
    {
        $tempDir = self::$config['temp_dir'] ?? sys_get_temp_dir() . '/lkexcel';
        if (is_dir($tempDir)) {
            $files = glob($tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < time() - 3600) { // 1小时前的文件
                    unlink($file);
                }
            }
        }
    }

    /**
     * 导出为CSV格式
     */
    public static function downloadCsv(array $data, string $filename = 'export.csv', array $options = []): array
    {
        $delimiter = $options['delimiter'] ?? ',';
        $enclosure = $options['enclosure'] ?? '"';
        $escape = $options['escape'] ?? '\\';

        $output = fopen('php://temp', 'r+');
        
        // 写入BOM以支持中文
        if ($options['with_bom'] ?? true) {
            fwrite($output, "\xEF\xBB\xBF");
        }

        // 写入表头
        if (isset($options['headers'])) {
            fputcsv($output, $options['headers'], $delimiter, $enclosure, $escape);
        }

        // 写入数据
        foreach ($data as $row) {
            fputcsv($output, $row, $delimiter, $enclosure, $escape);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return [
            'status' => 200,
            'headers' => [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => strlen($content),
            ],
            'content' => $content,
        ];
    }

    /**
     * 获取配置值
     */
    public static function getConfig(string $key = null, $default = null)
    {
        if ($key === null) {
            return self::$config;
        }
        
        return self::$config[$key] ?? $default;
    }

    /**
     * 设置配置值
     */
    public static function setConfig(string $key, $value): void
    {
        self::$config[$key] = $value;
    }
} 