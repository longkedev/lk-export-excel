<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Integration\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * LkExcel Facade
 * 
 * 提供优雅的Laravel风格API访问
 * 
 * @method static \LkExcel\LkExportExcel\Api\Reader read(string $filePath, array $config = [])
 * @method static \LkExcel\LkExportExcel\Api\Writer write(array $config = [])
 * @method static \LkExcel\LkExportExcel\Api\Converter convert(string $fromFormat, string $toFormat)
 * @method static string version()
 * @method static void config(array $config)
 * @method static array getConfig()
 * @method static array benchmark(array $options = [])
 * @method static array getStats()
 * 
 * @see \LkExcel\LkExportExcel\LkExportExcel
 */
class LkExcel extends Facade
{
    /**
     * 获取组件的注册名称
     */
    protected static function getFacadeAccessor(): string
    {
        return 'lkexcel';
    }

    /**
     * 快速读取Excel文件到数组
     * 
     * @param string $filePath 文件路径
     * @param array $options 读取选项
     * @return array
     */
    public static function toArray(string $filePath, array $options = []): array
    {
        return static::read($filePath, $options)->toArray();
    }

    /**
     * 流式读取Excel文件
     * 
     * @param string $filePath 文件路径
     * @param array $options 读取选项
     * @return \Generator
     */
    public static function stream(string $filePath, array $options = []): \Generator
    {
        return static::read($filePath, $options)->toIterator();
    }

    /**
     * 读取Excel文件并分块处理
     * 
     * @param string $filePath 文件路径
     * @param int $chunkSize 分块大小
     * @param callable $callback 处理回调
     * @param array $options 读取选项
     * @return void
     */
    public static function chunk(string $filePath, int $chunkSize, callable $callback, array $options = []): void
    {
        static::read($filePath, $options)->chunk($chunkSize, $callback);
    }

    /**
     * 从数组创建Excel文件
     * 
     * @param array $data 数据数组
     * @param string $filename 保存文件名
     * @param array $options 写入选项
     * @return bool
     */
    public static function fromArray(array $data, string $filename, array $options = []): bool
    {
        $writer = static::write($options);
        
        // 如果数据有表头
        if (isset($options['headers']) && is_array($options['headers'])) {
            $writer->writeHeaders($options['headers']);
        }
        
        $writer->writeRows($data);
        return $writer->save($filename);
    }

    /**
     * 从Collection创建Excel文件
     * 
     * @param \Illuminate\Support\Collection $collection 集合数据
     * @param string $filename 保存文件名
     * @param array $options 写入选项
     * @return bool
     */
    public static function fromCollection($collection, string $filename, array $options = []): bool
    {
        return static::fromArray($collection->toArray(), $filename, $options);
    }

    /**
     * 从Eloquent Model创建Excel文件
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query 查询构建器
     * @param string $filename 保存文件名
     * @param array $options 写入选项
     * @return bool
     */
    public static function fromQuery($query, string $filename, array $options = []): bool
    {
        $writer = static::write($options);
        
        // 获取字段作为表头
        if (!isset($options['headers'])) {
            $model = $query->getModel();
            $fillable = $model->getFillable();
            if (!empty($fillable)) {
                $writer->writeHeaders($fillable);
            }
        } elseif (is_array($options['headers'])) {
            $writer->writeHeaders($options['headers']);
        }
        
        // 分块查询避免内存溢出
        $chunkSize = $options['chunk_size'] ?? 1000;
        $query->chunk($chunkSize, function ($records) use ($writer, $options) {
            foreach ($records as $record) {
                $data = isset($options['columns']) 
                    ? $record->only($options['columns'])
                    : $record->toArray();
                $writer->writeRow(array_values($data));
            }
        });
        
        return $writer->save($filename);
    }

    /**
     * 直接下载Excel文件
     * 
     * @param array $data 数据数组
     * @param string $filename 下载文件名
     * @param array $options 选项
     * @return void
     */
    public static function download(array $data, string $filename, array $options = []): void
    {
        $writer = static::write($options);
        
        if (isset($options['headers']) && is_array($options['headers'])) {
            $writer->writeHeaders($options['headers']);
        }
        
        $writer->writeRows($data);
        $writer->download($filename);
    }

    /**
     * 获取文件信息
     * 
     * @param string $filePath 文件路径
     * @return array
     */
    public static function info(string $filePath): array
    {
        return static::read($filePath)->info();
    }

    /**
     * 验证Excel文件
     * 
     * @param string $filePath 文件路径
     * @param array $rules 验证规则
     * @return array
     */
    public static function validate(string $filePath, array $rules = []): array
    {
        $reader = static::read($filePath);
        $info = $reader->info();
        $errors = [];
        
        // 基础验证
        if (!$info['readable']) {
            $errors[] = '文件不可读';
        }
        
        if (isset($rules['max_size']) && $info['size'] > $rules['max_size']) {
            $errors[] = "文件大小超过限制: {$rules['max_size']} bytes";
        }
        
        if (isset($rules['allowed_formats']) && !in_array($info['format'], $rules['allowed_formats'])) {
            $errors[] = "不支持的文件格式: {$info['format']}";
        }
        
        if (isset($rules['max_rows'])) {
            $rowCount = $reader->count();
            if ($rowCount > $rules['max_rows']) {
                $errors[] = "行数超过限制: {$rowCount} > {$rules['max_rows']}";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'info' => $info
        ];
    }

    /**
     * 转换文件格式
     * 
     * @param string $inputFile 输入文件
     * @param string $outputFile 输出文件
     * @param array $options 转换选项
     * @return bool
     */
    public static function convertFile(string $inputFile, string $outputFile, array $options = []): bool
    {
        $data = static::read($inputFile, $options)->toArray();
        return static::fromArray($data, $outputFile, $options);
    }

    /**
     * 批量处理文件
     * 
     * @param array $files 文件列表
     * @param callable $processor 处理器
     * @param array $options 选项
     * @return array
     */
    public static function batch(array $files, callable $processor, array $options = []): array
    {
        $results = [];
        
        foreach ($files as $file) {
            try {
                $reader = static::read($file, $options);
                $result = $processor($reader, $file);
                $results[$file] = ['success' => true, 'result' => $result];
            } catch (\Exception $e) {
                $results[$file] = ['success' => false, 'error' => $e->getMessage()];
            }
        }
        
        return $results;
    }
} 