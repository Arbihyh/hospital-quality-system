<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SqlServerDiagnostic extends Command
{
    protected $signature = 'command:sqlserver-diagnostic {host=192.192.192.92} {--user=lis} {--password=winning} {--database=BAGL}';
    protected $description = 'SQL Server 连接诊断工具';

    public function handle()
    {
        $host = $this->argument('host');
        $username = $this->option('user');
        $password = $this->option('password');
        $database = $this->option('database');

        $this->info("=== SQL Server 连接诊断工具 ===");
        $this->info("目标服务器: {$host}");
        $this->info("数据库: {$database}");
        $this->info("用户名: {$username}");

        // 1. 检查 PHP SQL Server 扩展
        $this->checkPhpExtensions();

        // 2. 测试网络连接
        $this->testNetworkConnection($host);

        // 3. 测试不同的连接配置
        $this->testConnectionConfigurations($host, $username, $password, $database);

        // 4. 提供故障排除建议
        $this->provideTroubleshootingTips();
    }

    private function checkPhpExtensions()
    {
        $this->info("\n=== 检查 PHP 扩展 ===");

        if (extension_loaded('sqlsrv')) {
            $this->info("✓ sqlsrv 扩展已加载");
        } else {
            $this->error("✗ sqlsrv 扩展未加载");
        }

        if (extension_loaded('pdo_sqlsrv')) {
            $this->info("✓ pdo_sqlsrv 扩展已加载");
        } else {
            $this->error("✗ pdo_sqlsrv 扩展未加载");
        }

        if (function_exists('sqlsrv_connect')) {
            $this->info("✓ sqlsrv_connect 函数可用");
        } else {
            $this->error("✗ sqlsrv_connect 函数不可用");
        }
    }

    private function testNetworkConnection($host)
    {
        $this->info("\n=== 测试网络连接 ===");

        // 测试 1433 端口
        $ports = [1433, 1434]; // 1433 是默认端口，1434 是 SQL Server Browser

        foreach ($ports as $port) {
            $connection = @fsockopen($host, $port, $errno, $errstr, 5);
            if ($connection) {
                $this->info("✓ 端口 {$port} 可达");
                fclose($connection);
            } else {
                $this->error("✗ 端口 {$port} 不可达 (错误: {$errno} - {$errstr})");
            }
        }
    }

    private function testConnectionConfigurations($host, $username, $password, $database)
    {
        $this->info("\n=== 测试连接配置 ===");

        if (!function_exists('sqlsrv_connect')) {
            $this->error("sqlsrv_connect 函数不可用，跳过连接测试");
            return;
        }

        $configurations = [
            [
                'name' => 'SQL Server 2012 兼容 (无加密)',
                'options' => [
                    'Database' => $database,
                    'UID' => $username,
                    'PWD' => $password,
                    'LoginTimeout' => 30,
                    'Encrypt' => 0,
                    'TrustServerCertificate' => 0
                ]
            ],
            [
                'name' => 'SQL Server 2012 兼容 (信任证书)',
                'options' => [
                    'Database' => $database,
                    'UID' => $username,
                    'PWD' => $password,
                    'LoginTimeout' => 30,
                    'Encrypt' => 0,
                    'TrustServerCertificate' => 1
                ]
            ],
            [
                'name' => 'SQL Server 2016+ (强制加密)',
                'options' => [
                    'Database' => $database,
                    'UID' => $username,
                    'PWD' => $password,
                    'LoginTimeout' => 30,
                    'Encrypt' => 1,
                    'TrustServerCertificate' => 1
                ]
            ],
            [
                'name' => '最小配置',
                'options' => [
                    'Database' => $database,
                    'UID' => $username,
                    'PWD' => $password
                ]
            ],
            [
                'name' => 'Windows 身份验证',
                'options' => [
                    'Database' => $database,
                    'LoginTimeout' => 30
                ]
            ]
        ];

        foreach ($configurations as $config) {
            $this->info("\n测试配置: {$config['name']}");

            $connection = @sqlsrv_connect($host, $config['options']);

            if ($connection) {
                $this->info("✓ 连接成功！");

                // 测试简单查询
                $stmt = sqlsrv_query($connection, "SELECT @@VERSION as version");
                if ($stmt) {
                    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                    $this->info("  SQL Server 版本: " . substr($row['version'], 0, 100) . "...");
                    sqlsrv_free_stmt($stmt);
                }

                sqlsrv_close($connection);
                return; // 找到可用配置就停止
            } else {
                $this->error("✗ 连接失败");
                $errors = sqlsrv_errors();
                if (is_array($errors)) {
                    foreach ($errors as $error) {
                        $this->error("  SQLSTATE: {$error['SQLSTATE']}, 错误码: {$error['code']}");
                        $this->error("  错误信息: {$error['message']}");
                    }
                }
            }
        }
    }

    private function provideTroubleshootingTips()
    {
        $this->info("\n=== 故障排除建议 ===");

        $tips = [
            "1. SQL Server 配置检查:",
            "   - 确保 SQL Server 服务正在运行",
            "   - 启用 TCP/IP 协议 (SQL Server Configuration Manager)",
            "   - 确保 SQL Server Browser 服务正在运行",
            "   - 检查 SQL Server 是否配置为监听 1433 端口",
            "",
            "2. 网络和防火墙:",
            "   - 检查防火墙是否允许 1433 和 1434 端口",
            "   - 确保网络连接正常",
            "   - 尝试从同一网络的其他机器连接",
            "",
            "3. SQL Server 2012 特殊注意事项:",
            "   - SQL Server 2012 默认不启用远程连接",
            "   - 可能需要禁用加密 (Encrypt=0)",
            "   - 检查 SQL Server 身份验证模式",
            "",
            "4. 身份验证:",
            "   - 确认用户名和密码正确",
            "   - 检查用户是否有连接权限",
            "   - 尝试使用 Windows 身份验证",
            "",
            "5. 版本兼容性:",
            "   - SQL Server 2012 可能需要较旧的驱动程序",
            "   - 考虑更新 SQL Server 驱动程序",
            "   - 检查 PHP 版本兼容性"
        ];

        foreach ($tips as $tip) {
            $this->info($tip);
        }
    }
}
