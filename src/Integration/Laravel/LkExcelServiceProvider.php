<?php

declare(strict_types=1);

namespace LkExcel\LkExportExcel\Integration\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use LkExcel\LkExportExcel\LkExportExcel;
use LkExcel\LkExportExcel\Core\StreamParser;
use LkExcel\LkExportExcel\Memory\MemoryManager;
use LkExcel\LkExportExcel\Performance\PerformanceMonitor;
use LkExcel\LkExportExcel\Format\FormatDetector;
use LkExcel\LkExportExcel\Type\TypeDetector;
use LkExcel\LkExportExcel\Style\StyleManager;
use LkExcel\LkExportExcel\Formula\FormulaEngine;
use LkExcel\LkExportExcel\Memory\ObjectPool;

/**
 * Laravel服务提供者
 * 
 * 将lkExportExcel集成到Laravel生态系统
 * 提供完整的依赖注入、配置管理和Facade支持
 */
class LkExcelServiceProvider extends ServiceProvider
{
    /**
     * 启动服务
     */
    public function boot(): void
    {
        // 发布配置文件
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/lkexcel.php' => config_path('lkexcel.php'),
            ], 'lkexcel-config');
            
            // 发布视图文件（如果有）
            $this->publishes([
                __DIR__ . '/views' => resource_path('views/vendor/lkexcel'),
            ], 'lkexcel-views');
        }

        // 加载配置文件
        $this->mergeConfigFrom(__DIR__ . '/config/lkexcel.php', 'lkexcel');

        // 注册Artisan命令
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\LkExcelInstallCommand::class,
                Commands\LkExcelBenchmarkCommand::class,
                Commands\LkExcelOptimizeCommand::class,
            ]);
        }

        // 注册中间件
        $this->registerMiddleware();

        // 扩展验证规则
        $this->extendValidator();
    }

    /**
     * 注册服务
     */
    public function register(): void
    {
        // 注册核心服务
        $this->registerCoreServices();
        
        // 注册工厂服务
        $this->registerFactories();
        
        // 注册主服务
        $this->registerMainService();
        
        // 注册别名
        $this->registerAliases();
    }

    /**
     * 注册核心服务
     */
    private function registerCoreServices(): void
    {
        // 流式解析器
        $this->app->singleton(StreamParser::class, function (Application $app) {
            $config = $app['config']['lkexcel.core.stream_parser'];
            return new StreamParser($config['buffer_size'] ?? 8192);
        });

        // 内存管理器
        $this->app->singleton(MemoryManager::class, function (Application $app) {
            $config = $app['config']['lkexcel.memory'];
            return new MemoryManager($config['limit'] ?? 67108864);
        });

        // 性能监控器
        $this->app->singleton(PerformanceMonitor::class, function (Application $app) {
            $config = $app['config']['lkexcel.performance'];
            return new PerformanceMonitor($config['enabled'] ?? true);
        });

        // 格式检测器
        $this->app->singleton(FormatDetector::class, function (Application $app) {
            return new FormatDetector();
        });

        // 类型检测器
        $this->app->singleton(TypeDetector::class, function (Application $app) {
            $config = $app['config']['lkexcel.type_detection'];
            return new TypeDetector($config['strict_mode'] ?? false);
        });

        // 样式管理器
        $this->app->singleton(StyleManager::class, function (Application $app) {
            return new StyleManager();
        });

        // 公式引擎
        $this->app->singleton(FormulaEngine::class, function (Application $app) {
            $config = $app['config']['lkexcel.formula'];
            return new FormulaEngine($config['enable_cache'] ?? true);
        });

        // 对象池
        $this->app->singleton(ObjectPool::class, function (Application $app) {
            $config = $app['config']['lkexcel.object_pool'];
            return new ObjectPool($config['default_size'] ?? 50);
        });
    }

    /**
     * 注册工厂服务
     */
    private function registerFactories(): void
    {
        // 读取器工厂
        $this->app->bind('lkexcel.reader.factory', function (Application $app) {
            return new Factories\ReaderFactory($app);
        });

        // 写入器工厂
        $this->app->bind('lkexcel.writer.factory', function (Application $app) {
            return new Factories\WriterFactory($app);
        });

        // 转换器工厂
        $this->app->bind('lkexcel.converter.factory', function (Application $app) {
            return new Factories\ConverterFactory($app);
        });
    }

    /**
     * 注册主服务
     */
    private function registerMainService(): void
    {
        $this->app->singleton('lkexcel', function (Application $app) {
            $config = $app['config']['lkexcel'];
            return new LkExportExcel($config);
        });

        $this->app->alias('lkexcel', LkExportExcel::class);
    }

    /**
     * 注册别名
     */
    private function registerAliases(): void
    {
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('LkExcel', Facades\LkExcel::class);
    }

    /**
     * 注册中间件
     */
    private function registerMiddleware(): void
    {
        if (method_exists($this->app['router'], 'aliasMiddleware')) {
            $this->app['router']->aliasMiddleware('lkexcel.memory', Middleware\MemoryLimitMiddleware::class);
            $this->app['router']->aliasMiddleware('lkexcel.monitor', Middleware\PerformanceMiddleware::class);
        }
    }

    /**
     * 扩展验证规则
     */
    private function extendValidator(): void
    {
        $this->app['validator']->extend('excel_file', function ($attribute, $value, $parameters, $validator) {
            if (!$value instanceof \Illuminate\Http\UploadedFile) {
                return false;
            }

            $formatDetector = $this->app->make(FormatDetector::class);
            $result = $formatDetector->detect($value->getPathname());
            
            return in_array($result['format'], ['XLSX', 'XLS', 'CSV']);
        });

        $this->app['validator']->extend('excel_size', function ($attribute, $value, $parameters, $validator) {
            if (!$value instanceof \Illuminate\Http\UploadedFile) {
                return false;
            }

            $maxSize = isset($parameters[0]) ? (int)$parameters[0] : 10 * 1024 * 1024; // 默认10MB
            return $value->getSize() <= $maxSize;
        });
    }

    /**
     * 获取提供的服务
     */
    public function provides(): array
    {
        return [
            'lkexcel',
            LkExportExcel::class,
            StreamParser::class,
            MemoryManager::class,
            PerformanceMonitor::class,
            FormatDetector::class,
            TypeDetector::class,
            StyleManager::class,
            FormulaEngine::class,
            ObjectPool::class,
            'lkexcel.reader.factory',
            'lkexcel.writer.factory',
            'lkexcel.converter.factory',
        ];
    }
} 