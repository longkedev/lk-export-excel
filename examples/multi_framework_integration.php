<?php

declare(strict_types=1);

/**
 * lkExportExcel 多框架集成示例
 * 
 * 展示如何在不同PHP框架中使用lkExportExcel
 */

require_once __DIR__ . '/../vendor/autoload.php';

use LkExcel\LkExportExcel\LkExportExcel;

echo "=== lkExportExcel 多框架集成示例 ===\n\n";

// ============================================================
// 1. Laravel框架集成示例
// ============================================================
echo "🟢 Laravel框架集成:\n";
echo "
// 1. 安装服务提供者 (config/app.php)
'providers' => [
    LkExcel\LkExportExcel\Integration\Laravel\LkExcelServiceProvider::class,
],

'aliases' => [
    'LkExcel' => LkExcel\LkExportExcel\Integration\Laravel\Facades\LkExcel::class,
],

// 2. 在控制器中使用
use LkExcel;

class UserController extends Controller 
{
    public function export() 
    {
        // 方式1: 从集合导出
        return LkExcel::fromCollection(
            collect(User::all()), 
            'users.xlsx'
        );
        
        // 方式2: 从查询导出
        return LkExcel::fromQuery(
            User::where('active', true), 
            'active_users.xlsx'
        );
        
        // 方式3: 直接下载
        return LkExcel::download(\$userData, 'report.xlsx');
    }
    
    public function import(Request \$request) 
    {
        \$data = LkExcel::read(\$request->file('excel'))
            ->headers(true)
            ->filter(fn(\$row) => !empty(\$row['email']))
            ->toArray();
            
        foreach (\$data as \$row) {
            User::create(\$row);
        }
    }
}
";

// ============================================================
// 2. WebMan框架集成示例
// ============================================================
echo "\n🔵 WebMan框架集成:\n";
echo "
// 1. 在 config/bootstrap.php 中安装
use LkExcel\LkExportExcel\Integration\WebMan\LkExcelPlugin;

LkExcelPlugin::install([
    'memory_limit' => 64 * 1024 * 1024,
    'temp_dir' => runtime_path('excel_temp'),
]);

// 2. 在控制器中使用
use LkExcel\LkExportExcel\Integration\WebMan\LkExcelPlugin;

class UserController 
{
    public function export(Request \$request): Response
    {
        \$users = // 获取用户数据
        
        // 导出Excel
        \$response = LkExcelPlugin::downloadExcel(\$users, 'users.xlsx', [
            'headers' => ['ID', '姓名', '邮箱', '创建时间'],
            'header_style' => ['font' => ['bold' => true]]
        ]);
        
        return new Response(
            \$response['status'], 
            \$response['headers'], 
            \$response['content']
        );
    }
    
    public function import(Request \$request): Response 
    {
        try {
            \$reader = LkExcelPlugin::readFromUpload(\$request->file('excel'));
            \$data = \$reader->headers(true)->toArray();
            
            // 处理导入数据
            foreach (\$data as \$row) {
                // 保存到数据库
            }
            
            return json(['code' => 0, 'msg' => '导入成功']);
        } catch (Exception \$e) {
            return json(['code' => 1, 'msg' => \$e->getMessage()]);
        }
    }
}
";

// ============================================================
// 3. ThinkPHP框架集成示例
// ============================================================
echo "\n🟡 ThinkPHP框架集成:\n";
echo "
// 1. 在服务提供者中注册 (app/provider.php)
use LkExcel\LkExportExcel\Integration\ThinkPHP\LkExcelService;

return [
    LkExcelService::class,
];

// 或在应用初始化时注册
LkExcelService::register();

// 2. 在控制器中使用
use LkExcel\LkExportExcel\Integration\ThinkPHP\LkExcelService;

class UserController extends BaseController
{
    public function export()
    {
        \$users = User::select()->toArray();
        
        // 使用助手函数
        \$response = lkexcel()->download(\$users, 'users.xlsx', [
            'headers' => ['ID', '姓名', '邮箱'],
            'header_style' => ['font' => ['bold' => true]]
        ]);
        
        return download(\$response['content'], \$response['filename'])
            ->contentType(\$response['headers']['Content-Type']);
    }
    
    public function exportModel()
    {
        \$users = User::field('id,name,email')->select();
        
        // 直接从模型导出
        \$response = lkexcel()->fromModel(\$users, 'users.xlsx', [
            'fields' => [
                'id' => 'ID',
                'name' => '姓名', 
                'email' => '邮箱'
            ]
        ]);
        
        return download(\$response['content'], \$response['filename']);
    }
    
    public function import()
    {
        \$file = request()->file('excel');
        
        try {
            \$reader = lkexcel()->readFromUpload(\$file->getInfo());
            \$data = \$reader->headers(true)->toArray();
            
            \$userModel = new User();
            \$userModel->saveAll(\$data);
            
            return json(['code' => 0, 'msg' => '导入成功']);
        } catch (Exception \$e) {
            return json(['code' => 1, 'msg' => \$e->getMessage()]);
        }
    }
}
";

// ============================================================
// 4. 性能对比演示
// ============================================================
echo "\n📊 性能演示 (生成10000行数据):\n";

// 生成测试数据
$testData = [];
$headers = ['ID', '姓名', '部门', '薪资', '入职日期'];

for ($i = 1; $i <= 10000; $i++) {
    $testData[] = [
        $i,
        "员工{$i}",
        ['技术部', '产品部', '设计部', '运营部'][rand(0, 3)],
        rand(5000, 15000),
        date('Y-m-d', strtotime('-' . rand(1, 1000) . ' days'))
    ];
}

// 测试性能
$startTime = microtime(true);
$startMemory = memory_get_usage(true);

$writer = LkExportExcel::write();
$writer->writeHeaders($headers);
$writer->writeRows($testData);
$content = $writer->getContent();

$endTime = microtime(true);
$endMemory = memory_get_usage(true);

echo "  ✅ 写入10000行数据:\n";
echo "    处理时间: " . round(($endTime - $startTime) * 1000, 2) . " ms\n";
echo "    内存使用: " . round(($endMemory - $startMemory) / 1024 / 1024, 2) . " MB\n";
echo "    文件大小: " . round(strlen($content) / 1024, 2) . " KB\n";

// ============================================================
// 5. 通用功能演示
// ============================================================
echo "\n🛠️ 通用功能演示:\n";

// 批量处理示例
echo "
// 批量处理多个Excel文件
\$files = ['file1.xlsx', 'file2.xlsx', 'file3.xlsx'];

// Laravel
\$results = LkExcel::batch(\$files, function(\$reader, \$file) {
    return \$reader->headers(true)->count();
});

// WebMan  
\$results = LkExcelPlugin::batch(\$files, function(\$reader, \$file) {
    return \$reader->headers(true)->count();
});

// ThinkPHP
\$results = lkexcel()->batch(\$files, function(\$reader, \$file) {
    return \$reader->headers(true)->count();
});
";

// 性能监控示例
echo "
// 获取性能报告
// Laravel
\$report = app('lkexcel.monitor')->generateReport();

// WebMan
\$report = LkExcelPlugin::getPerformanceReport();

// ThinkPHP  
\$report = lkexcel()->getPerformanceReport();

// 输出: ['read_time' => 1.23, 'memory_usage' => 8.5, ...]
";

// 配置管理示例
echo "
// 配置管理
// Laravel: config/lkexcel.php
// WebMan: LkExcelPlugin::install(['memory_limit' => 128MB])
// ThinkPHP: config/lkexcel.php
";

echo "\n=== 集成完成！所有框架都可以享受相同的高性能Excel处理能力 ===\n"; 