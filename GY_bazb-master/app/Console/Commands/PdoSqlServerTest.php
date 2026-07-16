<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use PDOException;

class PdoSqlServerTest extends Command
{
    protected $signature = 'command:pdo-sqlserver-test {--user=lis} {--password=winning}';
    protected $description = '使用 PDO 测试 SQL Server 连接 (模拟 JDBC 方式)';

    public function handle()
    {
        $username = $this->option('user');
        $password = $this->option('password');
        $host = '192.192.192.92';
        $database = 'BAGL';
        
        $this->info("=== PDO SQL Server 连接测试 ===");
        $this->info("服务器: {$host}");
        $this->info("数据库: {$database}");
        $this->info("用户名: {$username}");
        
        // 检查 PDO SQL Server 扩展
        if (!extension_loaded('pdo_sqlsrv')) {
            $this->error('错误: pdo_sqlsrv 扩展未安装或未启用');
            return 1;
        }
        
        $this->info("✓ pdo_sqlsrv 扩展已加载\n");
        
        // 多种 PDO 连接字符串配置 - 重点测试端口指定
        $connectionConfigs = [
            [
                'name' => 'PDO 完全模拟 DataX Web JDBC',
                'dsn' => "sqlsrv:Server={$host},1433;Database={$database};Encrypt=no;TrustServerCertificate=yes;LoginTimeout=30",
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 30
                ]
            ],
            [
                'name' => 'PDO 明确端口 (逗号分隔)',
                'dsn' => "sqlsrv:Server={$host},1433;Database={$database};Encrypt=0;TrustServerCertificate=1",
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 30
                ]
            ],
            [
                'name' => 'PDO 明确端口 (冒号分隔)',
                'dsn' => "sqlsrv:Server={$host}:1433;Database={$database};Encrypt=no;TrustServerCertificate=yes",
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 30
                ]
            ],
            [
                'name' => 'PDO 端口+实例名格式',
                'dsn' => "sqlsrv:Server={$host}\\SQLEXPRESS,1433;Database={$database};Encrypt=no;TrustServerCertificate=yes",
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]
            ],
            [
                'name' => 'PDO 标准格式 (无端口)',
                'dsn' => "sqlsrv:Server={$host};Database={$database};Encrypt=no;TrustServerCertificate=yes",
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 30
                ]
            ],
            [
                'name' => 'PDO 最小配置 (测试基本连通)',
                'dsn' => "sqlsrv:Server={$host},1433;Database={$database}",
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]
            ]
        ];
        
        foreach ($connectionConfigs as $config) {
            $this->info("测试配置: {$config['name']}");
            $this->info("DSN: {$config['dsn']}");
            
            try {
                $pdo = new PDO(
                    $config['dsn'],
                    $username,
                    $password,
                    $config['options']
                );
                
                $this->info("✓ PDO 连接成功！");
                
                // 测试查询
                $stmt = $pdo->query("SELECT @@VERSION as version, DB_NAME() as current_db, GETDATE() as current_time");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $this->info("  SQL Server 版本: " . substr($result['version'], 0, 60) . "...");
                $this->info("  当前数据库: " . $result['current_db']);
                $this->info("  当前时间: " . $result['current_time']);
                
                // 测试表查询
                try {
                    $stmt = $pdo->query("SELECT TOP 5 * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'");
                    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $this->info("  数据库中的表 (前5个):");
                    foreach ($tables as $table) {
                        $this->info("    - " . $table['TABLE_NAME']);
                    }
                } catch (PDOException $e) {
                    $this->warn("  表查询失败: " . $e->getMessage());
                }
                
                $pdo = null; // 关闭连接
                
                $this->info("\n=== 连接成功！推荐配置 ===");
                $this->info("DSN: {$config['dsn']}");
                $this->info("用户名: {$username}");
                $this->info("密码: [已隐藏]");
                
                return 0;
                
            } catch (PDOException $e) {
                $this->error("✗ PDO 连接失败");
                $this->error("  错误信息: " . $e->getMessage());
                $this->error("  错误码: " . $e->getCode());
            }
            
            $this->info(str_repeat("-", 50));
        }
        
        $this->error("\n所有 PDO 配置都失败了！");
        
        // 提供详细的故障排除信息
        $this->info("\n=== 故障排除建议 ===");
        $this->info("1. 网络连接问题:");
        $this->info("   - 检查服务器 {$host} 是否可达");
        $this->info("   - 检查端口 1433 是否开放");
        $this->info("   - 检查防火墙设置");
        
        $this->info("\n2. SQL Server 配置:");
        $this->info("   - 确保 SQL Server 服务正在运行");
        $this->info("   - 启用 TCP/IP 协议");
        $this->info("   - 检查 SQL Server 身份验证模式");
        $this->info("   - 确保用户 '{$username}' 有连接权限");
        
        $this->info("\n3. 驱动程序问题:");
        $this->info("   - 确保安装了正确版本的 Microsoft ODBC Driver");
        $this->info("   - 考虑降级到 ODBC Driver 17");
        
        $this->info("\n4. 与 DataX Web 对比:");
        $this->info("   - DataX Web 使用 Java JDBC 驱动");
        $this->info("   - PHP 使用 Microsoft ODBC 驱动");
        $this->info("   - 两者对加密和证书的处理方式不同");
        
        return 1;
    }
}
