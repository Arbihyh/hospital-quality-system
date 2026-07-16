<?php

// 测试查询 dbo.A_Tupicd9set 表的前10条数据（使用 SqlServerProxyService）
// 使用方法: php test-table-query.php [代理主机] [代理端口] [数据库主机] [数据库名] [用户名] [密码] [表名]

// 引入 Laravel 自动加载
require_once __DIR__ . '/vendor/autoload.php';

// 引入 Laravel 应用
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\SqlServerProxyService;

$proxyHost = $argv[1] ?? '192.192.192.102';
$proxyPort = $argv[2] ?? '8080';
$dbHost = $argv[3] ?? null;
$dbName = $argv[4] ?? null;
$dbUser = $argv[5] ?? null;
$dbPass = $argv[6] ?? null;
$tableName = $argv[7] ?? 'A_Tupicd9set';

echo "=== 测试查询 dbo.{$tableName} 表（使用 SqlServerProxyService）===\n";

// 设置代理服务 URL
$proxyUrl = "http://{$proxyHost}:{$proxyPort}/api/sql";
putenv("SQLSERVER_PROXY_URL={$proxyUrl}");
config(['database.sqlserver_proxy.url' => $proxyUrl]);

echo "代理服务地址: {$proxyUrl}\n";

// 创建 SqlServerProxyService 实例
$proxyService = null;
if ($dbHost && $dbName && $dbUser && $dbPass) {
    // 使用动态数据库配置
    $proxyService = SqlServerProxyService::withDatabase($dbHost, $dbName, $dbUser, $dbPass);
    echo "使用动态数据库配置:\n";
    echo "  数据库: {$dbHost}:1433/{$dbName}\n";
    echo "  用户: {$dbUser}\n";
} else {
    // 使用默认配置
    $proxyService = new SqlServerProxyService();
    echo "使用默认数据库配置\n";
}

echo "\n";

// 测试1: 健康检查
echo "1. 健康检查...\n";
try {
    if ($proxyService->testConnection()) {
        echo "   ✅ 连接正常\n";

        // 获取数据库信息
        $dbInfo = $proxyService->getDatabaseInfo();
        if ($dbInfo) {
            echo "   📍 数据库状态: " . ($dbInfo['status'] ?? 'Unknown') . "\n";
            if (isset($dbInfo['info'])) {
                echo "   📍 服务器: " . ($dbInfo['info']['server_name'] ?? 'Unknown') . "\n";
                echo "   📍 当前数据库: " . ($dbInfo['info']['current_db'] ?? 'Unknown') . "\n";
                echo "   📍 当前时间: " . ($dbInfo['info']['current_time'] ?? 'Unknown') . "\n";
            }
        }
    } else {
        echo "   ❌ 连接异常\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ 无法连接到代理服务: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// 测试2: 查询表结构
echo "2. 查询表结构...\n";
try {
    $structureResults = $proxyService->query(
        'SELECT TOP 5 COLUMN_NAME, DATA_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
        [$tableName]
    );

    echo "   ✅ 表结构查询成功\n";
    echo "   📋 前5个字段:\n";
    foreach ($structureResults as $column) {
        $nullable = $column['IS_NULLABLE'] === 'YES' ? 'NULL' : 'NOT NULL';
        $length = $column['CHARACTER_MAXIMUM_LENGTH'] ? "({$column['CHARACTER_MAXIMUM_LENGTH']})" : '';
        echo "      - {$column['COLUMN_NAME']}: {$column['DATA_TYPE']}{$length} {$nullable}\n";
    }
} catch (Exception $e) {
    echo "   ❌ 表结构查询失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 测试3: 查询表记录数
echo "3. 查询表记录数...\n";
try {
    $totalCount = $proxyService->scalar("SELECT COUNT(*) FROM dbo.{$tableName}");
    echo "   ✅ 记录数查询成功\n";
    echo "   📊 总记录数: {$totalCount}\n";
} catch (Exception $e) {
    echo "   ❌ 记录数查询失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 测试4: 查询前10条数据
echo "4. 查询前10条数据...\n";
try {
    $dataResults = $proxyService->query("SELECT TOP 10 * FROM dbo.{$tableName}");

    echo "   ✅ 数据查询成功\n";
    echo "   📋 查询到 " . count($dataResults) . " 条记录:\n\n";

    // 显示数据
    foreach ($dataResults as $index => $row) {
        echo "   记录 " . ($index + 1) . ":\n";
        foreach ($row as $column => $value) {
            $displayValue = $value === null ? 'NULL' : $value;
            // 如果值太长，截断显示
            if (is_string($displayValue) && strlen($displayValue) > 50) {
                $displayValue = substr($displayValue, 0, 50) . '...';
            }
            echo "      {$column}: {$displayValue}\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "   ❌ 数据查询失败: " . $e->getMessage() . "\n";
}

// 测试5: 查询字段信息
echo "5. 查询字段信息...\n";
try {
    $keyFieldsResults = $proxyService->query("SELECT TOP 1 * FROM dbo.{$tableName}");

    if (!empty($keyFieldsResults)) {
        echo "   ✅ 字段信息查询成功\n";
        echo "   📋 字段列表:\n";

        $firstRow = $keyFieldsResults[0];
        $columnNames = array_keys($firstRow);
        echo "      表包含 " . count($columnNames) . " 个字段:\n";
        foreach ($columnNames as $columnName) {
            echo "      - {$columnName}\n";
        }
    } else {
        echo "   ⚠️  表为空，无法获取字段信息\n";
    }
} catch (Exception $e) {
    echo "   ❌ 字段信息查询失败: " . $e->getMessage() . "\n";
}

// 测试6: 批量查询示例
echo "\n6. 批量查询测试...\n";
try {
    $queries = [
        ['sql' => "SELECT COUNT(*) as total FROM dbo.{$tableName}", 'params' => []],
        ['sql' => "SELECT COUNT(DISTINCT FFLAG) as flag_types FROM dbo.{$tableName}", 'params' => []],
        ['sql' => "SELECT TOP 1 * FROM dbo.{$tableName} ORDER BY FID DESC", 'params' => []]
    ];

    $batchResults = $proxyService->batchQuery($queries);

    echo "   ✅ 批量查询成功\n";
    foreach ($batchResults as $index => $result) {
        if ($result['success']) {
            echo "   📊 查询 " . ($index + 1) . " 成功: " . json_encode($result['data']) . "\n";
        } else {
            echo "   ❌ 查询 " . ($index + 1) . " 失败: " . $result['error'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ 批量查询失败: " . $e->getMessage() . "\n";
}

echo "\n=== 测试完成 ===\n";

// 显示使用帮助
if (count($argv) == 1) {
    echo "\n📚 使用方法:\n";
    echo "  php test-table-query.php [代理主机] [代理端口] [数据库主机] [数据库名] [用户名] [密码] [表名]\n\n";
    echo "📋 参数说明:\n";
    echo "  代理主机    - Java 代理服务的主机地址 (默认: 192.192.192.102)\n";
    echo "  代理端口    - Java 代理服务的端口 (默认: 8080)\n";
    echo "  数据库主机  - SQL Server 数据库主机 (可选，不指定则使用默认配置)\n";
    echo "  数据库名    - 数据库名称 (可选)\n";
    echo "  用户名      - 数据库用户名 (可选)\n";
    echo "  密码        - 数据库密码 (可选)\n";
    echo "  表名        - 要查询的表名 (默认: A_Tupicd9set)\n\n";
    echo "🚀 示例:\n";
    echo "  # 使用默认配置测试 A_Tupicd9set 表\n";
    echo "  php test-table-query.php\n\n";
    echo "  # 指定代理服务地址\n";
    echo "  php test-table-query.php 192.192.192.102 8080\n\n";
    echo "  # 连接到不同的数据库\n";
    echo "  php test-table-query.php 192.192.192.102 8080 192.192.192.95 OTHER_DB other_user other_pass\n\n";
    echo "  # 查询不同的表\n";
    echo "  php test-table-query.php 192.192.192.102 8080 192.192.192.95 OTHER_DB other_user other_pass users\n\n";
    echo "💡 特性:\n";
    echo "  - 使用 SqlServerProxyService 类进行数据库操作\n";
    echo "  - 支持动态数据库连接（无需重启 Java 服务）\n";
    echo "  - 自动测试连接、表结构、数据查询等功能\n";
    echo "  - 支持批量查询操作\n\n";
}
