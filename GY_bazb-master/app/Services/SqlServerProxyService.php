<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SqlServerProxyService
{
    private $timeout;
    private $dbConfig;

    public function __construct($dbConfig = null)
    {
        $this->timeout = env('SQLSERVER_PROXY_TIMEOUT', 30);
        $this->dbConfig = $dbConfig;
    }

    /**
     * 创建使用指定数据库配置的服务实例
     */
    public static function withDatabase($host, $database, $username, $password, $port = '1433')
    {
        $dbConfig = [
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password
        ];

        return new static($dbConfig);
    }

    /**
     * 创建使用配置数组的服务实例
     */
    public static function withConfig(array $config)
    {
        return new static($config);
    }

    /**
     * 获取代理服务 URL，支持动态更新
     */
    private function getProxyUrl()
    {
        // 优先使用配置，然后是环境变量，最后是默认值
        return config('database.sqlserver_proxy.url')
            ?: env('SQLSERVER_PROXY_URL')
            ?: 'http://sqlserver-proxy:8080/api/sql';
    }

    /**
     * 测试连接
     */
    public function testConnection()
    {
        try {
            $url = $this->getProxyUrl() . '/health';
            $response = Http::timeout($this->timeout)->get($url);
            
            if ($response->successful()) {
                $data = $response->json();
                return $data['status'] === 'UP';
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('SQL Server Proxy 连接测试失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 执行查询
     */
    public function query($sql, $params = [])
    {
        try {
            $url = $this->getProxyUrl() . '/query';
            $requestData = [
                'sql' => $sql,
                'params' => $params
            ];

            // 添加数据库配置（如果有）
            if ($this->dbConfig) {
                $requestData['dbConfig'] = $this->dbConfig;
            }

            $response = Http::timeout($this->timeout)->post($url, $requestData);

            if ($response->successful()) {

                // 检查响应是否过大
                $responseSize = strlen($response->body());
                if ($responseSize > 10 * 1024 * 1024) { // 10MB
                    throw new \Exception("响应过大 ({$responseSize} 字节)，请使用分页查询或添加 LIMIT 子句");
                }
                $resContent = $response->body();
                $data = json_decode($resContent, true);
                if ($data === null) {
                    // 方法1: 将换行符替换为 \n 转义字符
                    $resContent = preg_replace('/\r?\n/', '\\n', $resContent);
                    $resContent = preg_replace('/\t/', '\\n', $resContent);
                    
                    // 方法2: 如果方法1不行，尝试更严格的修复
                    if (json_decode($resContent) === null) {
                        // 移除所有换行符和多余的空格
                        $resContent = preg_replace('/\s+/', ' ', $resContent);
                    }
                    $data = json_decode($resContent, true);
                }

                if($data == null){
                    $testJsonString = $resContent;
                    $testArray = json_decode($testJsonString, true);
                    if ($testArray === null && preg_match('/^\{[^\{]*data"\s*:\s*\[/', $testJsonString)) {
                        // 解析失败，但结构像注释中的情形，可能有转义和嵌套字符串问题
                        // 首先去除多余斜杠（如 \"）
                        $tmp = preg_replace('/\\\\(["\\\\\/bfnrt])/', '$1', $testJsonString);
                        // 再次尝试解析
                        $testArray = json_decode($tmp, true);
                        if ($testArray !== null) {
                            $data = $testArray;
                        }
                    } elseif ($testArray !== null) {
                        $data = $testArray;
                    }
                }

                if($data == null){
                    // 1. 清理可能的转义符（如果需要）
                    $resContent = stripslashes($resContent);
                    //2.如果编码不是 UTF-8，可以转换
                    $resContent = mb_convert_encoding($resContent, 'UTF-8', 'auto');
                    // 3. 解析 JSON
                    // $patientInfo = json_decode($patientInfo, true, 512, JSON_THROW_ON_ERROR); // PHP 7.3+
                    $data = json_decode($resContent, true);

                    // 4. 验证数据结构
                    if (!isset($data['success'], $data['data']) || $data['success'] !== true || empty($data['data'])) {
                        Log::info('query_data_error',['data'=>$resContent]);
                    }
                }

                if ($data && $data['success']) {
                    return $data['data'];
                } else {
                    return $response->body();
                    throw new \Exception($data['error'] ?? 'SQL 执行失败');
                }
            } else {
                throw new \Exception('HTTP Error: ' . $response->status());
            }
        } catch (\Exception $e) {
            Log::error('SQL Server Proxy 查询失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 执行更新操作
     */
    public function update($sql, $params = [])
    {
        try {
            $url = $this->getProxyUrl() . '/update';
            $requestData = [
                'sql' => $sql,
                'params' => $params
            ];

            // 添加数据库配置（如果有）
            if ($this->dbConfig) {
                $requestData['dbConfig'] = $this->dbConfig;
            }

            $response = Http::timeout($this->timeout)->post($url, $requestData);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['success']) {
                    return $data['affectedRows'];
                } else {
                    throw new \Exception($data['error'] ?? 'Unknown error');
                }
            } else {
                throw new \Exception('HTTP Error: ' . $response->status());
            }
        } catch (\Exception $e) {
            Log::error('SQL Server Proxy 更新失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 执行标量查询
     */
    public function scalar($sql, $params = [])
    {
        try {
            $url = $this->getProxyUrl() . '/scalar';
            $requestData = [
                'sql' => $sql,
                'params' => $params
            ];

            // 添加数据库配置（如果有）
            if ($this->dbConfig) {
                $requestData['dbConfig'] = $this->dbConfig;
            }

            $response = Http::timeout($this->timeout)->post($url, $requestData);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['success']) {
                    return $data['value'];
                } else {
                    throw new \Exception($data['error'] ?? 'Unknown error');
                }
            } else {
                throw new \Exception('HTTP Error: ' . $response->status());
            }
        } catch (\Exception $e) {
            Log::error('SQL Server Proxy 标量查询失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取数据库信息
     */
    public function getDatabaseInfo()
    {
        try {
            $url = $this->getProxyUrl() . '/health';
            $response = Http::timeout($this->timeout)->get($url);
            
            if ($response->successful()) {
                $data = $response->json();
                return $data['info'] ?? null;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('获取数据库信息失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 批量执行查询
     */
    public function batchQuery($queries)
    {
        $results = [];
        
        foreach ($queries as $index => $queryData) {
            try {
                $sql = $queryData['sql'];
                $params = $queryData['params'] ?? [];
                
                $results[$index] = [
                    'success' => true,
                    'data' => $this->query($sql, $params)
                ];
            } catch (\Exception $e) {
                $results[$index] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
}