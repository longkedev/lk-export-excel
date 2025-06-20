<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Integration\ThinkPHP;

use LkExcel\LkExportExcel\Api\Reader;
use LkExcel\LkExportExcel\Api\Writer;
use LkExcel\LkExportExcel\LkExportExcel;
use LkExcel\LkExportExcel\Performance\PerformanceMonitor;

/**
 * ThinkPHP框架lkExportExcel服务类
 * 
 * 提供ThinkPHP框架的深度集成支持：
 * - 服务注册和管理
 * - 配置管理
 * - Response响应处理
 * - 文件上传处理
 * - 模型数据导出
 * 
 * 使用方法：
 * 1. 在provider.php中注册: LkExcelService::class
 * 2. 在控制器中使用: app('lkexcel.reader', [$filePath])
 * 3. 或使用助手函数: lkexcel()->read($file)
 */
class LkExcelService
{
    /**
     * 配置数组
     */
    private static array $config = [];

    /**
     * 初始化服务
     */
    public static function register(): void
    {
        // 注册到容器
        if (function_exists('app')) {
            // 注册Reader服务
            app()->bind('lkexcel.reader', function($app, $params = []) {
                $filePath = $params[0] ?? '';
                return new Reader($filePath);
            });

            // 注册Writer服务
            app()->bind('lkexcel.writer', function() {
                return new Writer();
            });

            // 注册性能监控器
            app()->bind('lkexcel.monitor', function() {
                return new PerformanceMonitor(
                    self::getConfig('performance_enabled', true)
                );
            });

            // 注册主服务
            app()->bind('lkexcel', function() {
                return new self();
            });
        }

        // 设置默认配置
        self::loadConfig();
    }

    /**
     * 加载配置
     */
    private static function loadConfig(): void
    {
        $configFile = null;
        
        // 尝试加载ThinkPHP配置
        if (function_exists('config')) {
            $userConfig = config('lkexcel', []);
        } else {
            $userConfig = [];
        }

        // 默认配置
        $defaultConfig = [
            'memory_limit' => 64 * 1024 * 1024, // 64MB
            'chunk_size' => 1000,
            'buffer_size' => 8192,
            'performance_enabled' => true,
            'temp_dir' => sys_get_temp_dir() . '/lkexcel',
            'download' => [
                'chunk_size' => 1024 * 8, // 8KB
                'cache_control' => 'max-age=0',
            ],
        ];

        self::$config = array_merge($defaultConfig, $userConfig);

        // 应用配置到lkExportExcel
        LkExportExcel::config(self::$config);

        // 确保临时目录存在
        if (!is_dir(self::$config['temp_dir'])) {
            mkdir(self::$config['temp_dir'], 0755, true);
        }
    }

    /**
     * 获取Reader实例
     */
    public function reader(string $filePath = ''): Reader
    {
        if (function_exists('app')) {
            return app('lkexcel.reader', [$filePath]);
        }
        return new Reader($filePath);
    }

    /**
     * 获取Writer实例
     */
    public function writer(): Writer
    {
        if (function_exists('app')) {
            return app('lkexcel.writer');
        }
        return new Writer();
    }

    /**
     * 获取性能监控器
     */
    public function monitor(): PerformanceMonitor
    {
        if (function_exists('app')) {
            return app('lkexcel.monitor');
        }
        return new PerformanceMonitor(self::getConfig('performance_enabled', true));
    }

    /**
     * 创建Excel下载响应 (ThinkPHP Response)
     */
    public function download($data, string $filename = 'export.xlsx', array $options = []): array
    {
        $writer = $this->writer();
        
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
        
        // 返回ThinkPHP下载响应格式
        return [
            'type' => 'excel',
            'content' => $content,
            'filename' => $filename,
            'headers' => [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => strlen($content),
                'Cache-Control' => self::getConfig('download.cache_control', 'max-age=0'),
            ],
        ];
    }

    /**
     * 从ThinkPHP模型导出
     */
    public function fromModel($model, string $filename = 'export.xlsx', array $options = []): array
    {
        $data = [];
        
        // 处理模型数据
        if (method_exists($model, 'toArray')) {
            $modelData = $model->toArray();
            if (isset($modelData[0])) {
                // 多条记录
                $data = $modelData;
            } else {
                // 单条记录
                $data = [$modelData];
            }
        } elseif (is_array($model)) {
            $data = $model;
        } else {
            throw new \InvalidArgumentException('模型数据格式不支持');
        }

        // 处理字段映射
        if (isset($options['fields'])) {
            $fields = $options['fields'];
            $data = array_map(function($row) use ($fields) {
                $newRow = [];
                foreach ($fields as $key => $label) {
                    $newRow[] = $row[$key] ?? '';
                }
                return $newRow;
            }, $data);
            
            // 设置表头
            $options['headers'] = array_values($fields);
        }

        return $this->download($data, $filename, $options);
    }

    /**
     * 从上传文件读取Excel
     */
    public function readFromUpload(array $file): Reader
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException('无效的上传文件');
        }

        return $this->reader($file['tmp_name']);
    }

    /**
     * 导出为CSV格式
     */
    public function downloadCsv(array $data, string $filename = 'export.csv', array $options = []): array
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
            'type' => 'csv',
            'content' => $content,
            'filename' => $filename,
            'headers' => [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => strlen($content),
            ],
        ];
    }

    /**
     * 批量处理多个文件
     */
    public function batch(array $files, callable $processor): array
    {
        $results = [];
        $monitor = $this->monitor();
        
        foreach ($files as $index => $file) {
            $monitor->startTimer("batch_process_{$index}");
            
            try {
                $reader = $this->reader($file);
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
    public function getPerformanceReport(): array
    {
        return $this->monitor()->generateReport();
    }

    /**
     * 清理临时文件
     */
    public function cleanup(): void
    {
        $tempDir = self::getConfig('temp_dir', sys_get_temp_dir() . '/lkexcel');
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
     * 获取配置值
     */
    public static function getConfig(string $key = null, $default = null)
    {
        if ($key === null) {
            return self::$config;
        }
        
        // 支持点语法
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }

    /**
     * 设置配置值
     */
    public static function setConfig(string $key, $value): void
    {
        // 支持点语法
        $keys = explode('.', $key);
        $config = &self::$config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
    }
}

/**
 * ThinkPHP助手函数
 */
if (!function_exists('lkexcel')) {
    /**
     * 获取lkExcel服务实例
     */
    function lkexcel(): LkExcelService
    {
        if (function_exists('app') && app()->has('lkexcel')) {
            return app('lkexcel');
        }
        return new LkExcelService();
    }
} 