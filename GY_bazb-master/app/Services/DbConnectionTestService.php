<?php

namespace App\Services;

use PDO;
use PDOException;

/**
 * 数据库连接测试服务类
 */
class DbConnectionTestService
{
    /**
     * 测试SQL Server数据库连接
     *
     * @param string $host 数据库主机地址
     * @param string $database 数据库名称
     * @param string $username 用户名
     * @param string $password 密码
     * @param int $port 端口号，默认为1433
     * @param int $timeout 连接超时时间（秒）
     * @param bool $trustServerCertificate 是否信任服务器证书，默认为true
     * @return array 返回测试结果，包含状态和消息
     */
    public function testSqlServerConnection(
        string $host,
        string $database,
        string $username,
        string $password,
        int $port = 1433,
        int $timeout = 5,
        bool $trustServerCertificate = true
    ): array {
        try {
            // 构建连接配置
            $dsn = "sqlsrv:Server=$host,$port;Database=$database;LoginTimeout=$timeout";
            
            // 添加证书信任选项
            if ($trustServerCertificate) {
                $dsn .= ";TrustServerCertificate=1";
            }
            
            // 设置PDO选项
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => $timeout,
                PDO::ATTR_CASE => PDO::CASE_NATURAL,
                PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ];
            
            // 尝试建立连接
            $startTime = microtime(true);
            $connection = new PDO($dsn, $username, $password, $options);
            $endTime = microtime(true);
            $connectionTime = round(($endTime - $startTime) * 1000, 2); // 毫秒
            
            // 尝试执行简单查询以验证连接可用
            $stmt = $connection->query('SELECT @@VERSION AS version');
            $serverInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 关闭连接
            $connection = null;
            
            return [
                'status' => true,
                'message' => '连接成功',
                'connection_time' => $connectionTime,
                'server_info' => $serverInfo['version'] ?? null,
            ];
        } catch (PDOException $e) {
            // 捕获PDO异常并返回错误信息
            return [
                'status' => false,
                'message' => '连接失败: ' . $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        } catch (\Exception $e) {
            // 捕获其他可能的异常
            return [
                'status' => false,
                'message' => '发生未知错误: ' . $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        }
    }
    
    /**
     * 使用原生sqlsrv扩展测试SQL Server数据库连接
     * 
     * @param string $host 数据库主机地址
     * @param string $database 数据库名称
     * @param string $username 用户名
     * @param string $password 密码
     * @param int $port 端口号，默认为1433
     * @param int $timeout 连接超时时间（秒）
     * @param bool $trustServerCertificate 是否信任服务器证书，默认为true
     * @return array 返回测试结果，包含状态和消息
     */
    public function testSqlServerConnectionWithSqlsrv(
        string $host,
        string $database,
        string $username,
        string $password,
        int $port = 1433,
        int $timeout = 5,
        bool $trustServerCertificate = true
    ): array {
        try {
            // 检查sqlsrv扩展是否已安装
            if (!function_exists('sqlsrv_connect')) {
                return [
                    'status' => false,
                    'message' => '连接失败: PHP sqlsrv扩展未安装',
                    'error_code' => 'SQLSRV_NOT_INSTALLED',
                ];
            }
            
            // 构建连接配置
            $serverName = "$host,$port";
            $connectionInfo = [
                "Database" => $database,
                "UID" => $username,
                "PWD" => $password,
                "LoginTimeout" => $timeout,
                "TrustServerCertificate" => $trustServerCertificate ? 1 : 0,
            ];
            
            // 尝试建立连接
            $startTime = microtime(true);
            $connection = sqlsrv_connect($serverName, $connectionInfo);
            $endTime = microtime(true);
            $connectionTime = round(($endTime - $startTime) * 1000, 2); // 毫秒
            
            if ($connection === false) {
                $errors = sqlsrv_errors();
                $errorMessage = '连接失败';
                $errorCode = null;
                
                if (is_array($errors) && count($errors) > 0) {
                    $firstError = $errors[0];
                    $errorMessage .= ': [' . $firstError['code'] . '] ' . $firstError['message'];
                    $errorCode = $firstError['code'];
                }
                
                return [
                    'status' => false,
                    'message' => $errorMessage,
                    'error_code' => $errorCode,
                ];
            }
            
            // 尝试执行简单查询以验证连接可用
            $query = "SELECT @@VERSION AS version";
            $stmt = sqlsrv_query($connection, $query);
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                sqlsrv_close($connection);
                
                return [
                    'status' => false,
                    'message' => '查询失败: ' . ($errors[0]['message'] ?? '未知错误'),
                    'error_code' => $errors[0]['code'] ?? null,
                ];
            }
            
            $serverInfo = null;
            if (sqlsrv_fetch($stmt)) {
                $serverInfo = sqlsrv_get_field($stmt, 0);
            }
            
            // 关闭资源
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($connection);
            
            return [
                'status' => true,
                'message' => '连接成功',
                'connection_time' => $connectionTime,
                'server_info' => $serverInfo,
            ];
        } catch (\Exception $e) {
            // 捕获其他可能的异常
            return [
                'status' => false,
                'message' => '发生未知错误: ' . $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        }
    }
}