<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Dbtest extends Command
{
    protected $signature = 'command:Dbtest {--user= : 数据库用户名} {--password= : 数据库密码}';
    protected $description = '测试SQL Server数据库连接';

    public function handle()
    {
        // 检查 SQL Server 扩展
        if (!extension_loaded('sqlsrv')) {
            $this->error('错误: sqlsrv 扩展未安装或未启用');
            $this->info('请安装 Microsoft SQL Server Driver for PHP');
            return 1;
        }

        // 获取命令行参数，如果没有提供，使用默认值
        $username = $this->option('user') ?: 'lis';
        $password = $this->option('password') ?: 'winning';

        $this->info("正在使用用户: {$username} 连接数据库...");

        // 尝试多种连接配置 - 模拟 JDBC 连接方式
        $connectionConfigs = [
            [
                'name' => '模拟 JDBC 连接方式 (推荐)',
                'options' => [
                    'Database' => 'BAGL',
                    'UID' => $username,
                    'PWD' => $password,
                    'LoginTimeout' => 30,
                    'Encrypt' => 'no',
                    'TrustServerCertificate' => 'yes',
                    'MultipleActiveResultSets' => 'false'
                ]
            ],
            [
                'name' => 'JDBC 风格配置 (数值型)',
                'options' => [
                    'Database' => 'BAGL',
                    'UID' => $username,
                    'PWD' => $password,
                    'LoginTimeout' => 30,
                    'Encrypt' => 0,
                    'TrustServerCertificate' => 1,
                    'MultipleActiveResultSets' => 0
                ]
            ],
            [
                'name' => '强制禁用 SSL (Driver 18 兼容)',
                'options' => [
                    'Database' => 'BAGL',
                    'UID' => $username,
                    'PWD' => $password,
                    'LoginTimeout' => 60,
                    'Encrypt' => 'no',
                    'TrustServerCertificate' => 'yes',
                    'ConnectionPooling' => 0
                ]
            ],
            [
                'name' => '传统连接方式',
                'options' => [
                    'Database' => 'BAGL',
                    'UID' => $username,
                    'PWD' => $password,
                    'LoginTimeout' => 30,
                    'ReturnDatesAsStrings' => 1
                ]
            ],
            [
                'name' => '最简配置测试',
                'options' => [
                    'Database' => 'BAGL',
                    'UID' => $username,
                    'PWD' => $password
                ]
            ]
        ];

        $connection = null;

        foreach ($connectionConfigs as $config) {
            $this->info("\n尝试连接配置: {$config['name']}");
            $connection = sqlsrv_connect('192.192.192.92,1433', $config['options']);

            if ($connection) {
                $this->info("✓ 连接成功！使用配置: {$config['name']}");
                break;
            } else {
                $errors = sqlsrv_errors();
                $this->error("✗ 连接失败: {$config['name']}");
                if (is_array($errors)) {
                    foreach ($errors as $error) {
                        $this->error("  SQLSTATE: {$error['SQLSTATE']}, 错误码: {$error['code']}, 错误信息: {$error['message']}");
                    }
                }
            }
        }
        if ($connection) {
            $this->info("\n=== 开始测试数据库查询 ===");

            // 使用直接查询获取数据
            $sql = "SELECT TOP 10 * FROM v_HDR_OPER_ANAES";
            $this->info("SQL: {$sql}");

            $stmt = sqlsrv_query($connection, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                foreach ($errors as $error) {
                    $this->error("查询失败: SQLSTATE: {$error['SQLSTATE']}, 错误码: {$error['code']}, 错误信息: {$error['message']}");
                }
            } else {
                $this->info("查询成功!");

                // 获取列信息
                $columns = [];
                $columnWidths = [];

                foreach (sqlsrv_field_metadata($stmt) as $fieldMetadata) {
                    $columns[] = $fieldMetadata['Name'];
                    // 初始列宽为列名长度
                    $columnWidths[$fieldMetadata['Name']] = mb_strlen($fieldMetadata['Name']);
                }

                // 获取所有数据，并计算每列的最大宽度
                $rows = [];
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $formattedRow = [];
                    foreach ($row as $key => $value) {
                        if ($value instanceof \DateTime) {
                            $formattedValue = $value->format('Y-m-d H:i:s');
                        } else {
                            $formattedValue = $value === null ? 'NULL' : (string)$value;
                        }

                        $formattedRow[$key] = $formattedValue;
                        // 更新列宽
                        $columnWidths[$key] = max($columnWidths[$key], mb_strlen($formattedValue));
                    }
                    $rows[] = $formattedRow;
                }

                // 打印表头
                $header = '| ';
                $separator = '| ';

                foreach ($columns as $column) {
                    $header .= str_pad($column, $columnWidths[$column]) . ' | ';
                    $separator .= str_repeat('-', $columnWidths[$column]) . ' | ';
                }

                $this->info($separator);
                $this->info($header);
                $this->info($separator);

                // 打印数据行
                foreach ($rows as $row) {
                    $line = '| ';
                    foreach ($columns as $column) {
                        $line .= str_pad($row[$column] ?? '', $columnWidths[$column]) . ' | ';
                    }
                    $this->info($line);
                }

                $this->info($separator);
                $this->info("总共获取 " . count($rows) . " 条记录");
            }

            // 释放资源
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($connection);
        } else {
            $this->error("\n所有连接配置都失败了！");
            $this->info("\n=== 故障排除建议 ===");
            $this->info("1. 检查 SQL Server 2012 是否启用了 TCP/IP 协议");
            $this->info("2. 检查 SQL Server Browser 服务是否运行");
            $this->info("3. 检查防火墙是否阻止了 1433 端口");
            $this->info("4. 检查 SQL Server 是否配置为允许远程连接");
            $this->info("5. 尝试使用 SQL Server Management Studio 从同一台机器连接");
        }
    }
}
