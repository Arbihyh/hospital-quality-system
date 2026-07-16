<?php

namespace App\Services\DataxSync;

use App\Model\DataxSyncSetting;
use App\Model\SjkConn;
use App\Services\SqlServerProxyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DataxSyncService
{
    //oracle
    const ORACLE = 'ORACLE';

    //sql server
    const SQL_SERVER = 'SQL_SERVER';

    //mysql
    const MYSQL = 'MYSQL';

    //数据库类型
    protected $database_type = 'ORACLE';

    //用户名
    protected $user_name;

    //密码
    protected $pass_word;

    //链接地址
    protected $server_ip;

    //数据库名称
    protected $databases;

    //编码
    protected $character = 'UTF8';

    //实例类
    protected static $instance;

    //链接
    protected $connect;

    //语句
    protected $sql;

    //config
    protected $config;

    /**
     * @var bool
     */
    protected static $state = true;

    /**
     * @var bool
     */
    protected static $error_msg = 'success';

    /**
     * 构造函数
     * @param string $sjkId 数据库连接ID
     * @param string $sqlType 数据库类型 (ORACLE|SQL_SERVER)
     */
    public function __construct(string $sjkId)
    {
        // 根据 SJKID 从 SJK_CONN 获取连接信息
        $connInfo = SjkConn::getBySjkId($sjkId);

        if (empty($connInfo)) {
            throw new \Exception("未找到 SJKID: {$sjkId} 对应的连接信息");
        }
        // 根据 SqlType 设置数据库类型
        $sqlType = $connInfo['SJKLX'];
        $this->database_type = strtoupper($sqlType);




        // 设置连接参数
        $this->user_name = $connInfo['username'];
        $this->pass_word = $connInfo['password'];
        $this->server_ip = $connInfo['host'] . ':' . $connInfo['port'];
        $this->databases = $connInfo['database'];
        $this->config = [
            'username' => $connInfo['username'],
            'password' => $connInfo['password'],
            'host' => $connInfo['host'],
            'port' => $connInfo['port'],
            'database' => $connInfo['database'],
        ];
        $this->connect = $this->getConnect();
    }


    private function getConnect()
    {
        $connect = false;
        $config = $this->config;
        try {
            switch ($this->database_type) {
                case self::SQL_SERVER:
                    // 使用SqlServerProxyService创建连接
                    $sqlsrvConfig = [
                        'host' => $config['host'],
                        'port' => $config['port'] ?? '1433',
                        'database' => $config['database'],
                        'username' => $config['username'],
                        'password' => $config['password']
                    ];

                    Log::info('sqlsrvConfig', ['sqlsrvConfig' => $sqlsrvConfig]);
                    $connect = SqlServerProxyService::withConfig($sqlsrvConfig);

                    // 测试SQL Server连接
                    if (!$connect->testConnection()) {
                        Log::error(__CLASS__ . '链接SQL_SERVER数据库失败');
                        throw new \Exception('SQL Server数据库连接失败');
                    }
                    break;

                case self::ORACLE:
                    // 添加5秒超时限制
                    Log::info('链接ODS数据库开始:' . date("Y-m-d H:i:s"));
                    $connect = oci_connect($config['username'], $config['password'], $config['host'] . ':' . $config['port'] . '/' . $config['database'], $this->character);
                    if (!$connect)
                        Log::info(__CLASS__ . '链接ODS数据库报错::', ['msg' => htmlentities(data_get(oci_error(), 'message'))]);
                    Log::info('链接ODS数据库结束:' . date("Y-m-d H:i:s"));
                    break;
                case self::MYSQL:
                    $port = $config['port'] ?? 3306;
                    $charset = strtolower($this->character) === 'utf8' ? 'utf8mb4' : strtolower($this->character);
                    $dsn = "mysql:host={$config['host']};port={$port};dbname={$config['database']};charset={$charset}";
                    try {
                        $connect = new \PDO($dsn, $config['username'], $config['password'], [
                            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                            \PDO::ATTR_EMULATE_PREPARES => false,
                        ]);
                    } catch (\PDOException $e) {
                        Log::info(__CLASS__ . '链接MYSQL数据库报错::', ['msg' => $e->getMessage()]);
                        $connect = false;
                    }
                    break;
                default:
                    //$connect = oci_connect($this->user_name, $this->pass_word, $this->server_ip, $this->character);
                    $connect = oci_connect($config['username'], $config['password'], $config['host'] . ':' . $config['port'] . '/' . $config['database'], $this->character, 5);
                    if (!$connect)
                        Log::info(__CLASS__ . '链接ODS数据库报错::', ['msg' => htmlentities(data_get(oci_error(), 'message'))]);
                    break;
            }
        } catch (\Exception $exception) {
            Log::info(__CLASS__ . '链接数据库报错::' . $exception->getMessage());
            // 连接失败直接返回false
            return false;
        }
        return $connect;
    }

    /**
     * 关闭数据库连接
     * @return bool
     */
    public function closeConnect(): bool
    {
        switch ($this->database_type) {
            case self::SQL_SERVER:
                // SqlServerProxyService 不需要手动关闭连接
                break;

            case self::MYSQL:
                if ($this->connect && $this->connect instanceof \PDO) {
                    $this->connect = null;
                }
                break;

            case self::ORACLE:
            default:
                if (is_resource($this->connect)) {
                    oci_close($this->connect);
                }
                break;
        }
        return true;
    }


    /**
     * 根据表名称&&生成sql时的数量获取
     * @param string $name
     * @param int $num
     * @return self
     *
     */
    public function setByNameSql(string $name, int $num = 0): self
    {
        $this->sql = DataxSyncSetting::getByNameSql($name, $num);
        return $this;
    }

    /**
     * 根据ID获取指定sql
     * @param int $id
     * @return self
     */
    public function setByIdSql(int $id): self
    {
        $this->sql = DataxSyncSetting::getByIdSql($id);
        return $this;
    }

    /**
     * 获取SQL语句
     * @return mixed
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * 设置SQL语句
     * @param string $sql
     * @return $this
     */
    public function setSql($sql)
    {
        $this->sql = $sql;
        return $this;
    }

    /**
     * 根据数据库类型转换SQL语法
     * @param string $sql 原始SQL
     * @return string 转换后的SQL
     */
    private function convertSqlForDatabase($sql)
    {
        if ($this->database_type === self::MYSQL) {
            // 将 SQL Server 的 CONVERT(VARCHAR, column, 120) 转换为 MySQL 的 DATE_FORMAT
            // 格式 120 对应 'YYYY-MM-DD HH:MI:SS'
            $sql = preg_replace_callback(
                '/CONVERT\s*\(\s*VARCHAR\s*,\s*([^,]+)\s*,\s*120\s*\)/i',
                function ($matches) {
                    $column = trim($matches[1]);
                    return "DATE_FORMAT({$column}, '%Y-%m-%d %H:%i:%s')";
                },
                $sql
            );

            // 清理 SELECT 字段列表末尾的多余逗号（在 FROM 之前）
            // 匹配模式：逗号后面可能有空格，然后直接是 FROM
            $sql = preg_replace('/,\s+FROM\s+/i', ' FROM ', $sql);
        }
        return $sql;
    }

    /**
     * 获取查询结果（支持ORACLE、SQL_SERVER和MYSQL）
     * @return array|false
     */
    public function getResult()
    {
        if (!$this->connect || !$this->sql)
            return false;

        // 根据数据库类型转换SQL语法
        $sql = $this->convertSqlForDatabase($this->sql);

        //Log::info('执行sql语句：'.$sql);
        $result = [];
        try {
            switch ($this->database_type) {
                case self::SQL_SERVER:
                    // 使用 SqlServerProxyService 的 query 方法
                    $result = $this->connect->query($sql);
                    if ($result === false) {
                        throw new \Exception('SQL Server查询失败', 1001);
                    }
                    break;

                case self::MYSQL:
                    // MySQL 查询（使用 PDO）
                    $stmt = $this->connect->query($sql);
                    if ($stmt === false) {
                        throw new \Exception('MySQL查询失败', 1001);
                    }
                    $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    break;

                case self::ORACLE:
                    // ORACLE 查询
                    $data = oci_parse($this->connect, $sql);
                    oci_execute($data, OCI_DEFAULT);

                    while ($row = oci_fetch_assoc($data)) {
                        $result[] = $row;
                    }
                    oci_free_statement($data);
                    break;
                default:
                    // ORACLE 查询
                    $data = oci_parse($this->connect, $sql);
                    oci_execute($data, OCI_DEFAULT);

                    while ($row = oci_fetch_assoc($data)) {
                        $result[] = $row;
                    }
                    oci_free_statement($data);
                    break;
            }
        } catch (\Exception $exception) {
            Log::error(__CLASS__ . "数据库查询失败,请检查链接或查询语句::" . $exception->getMessage() . '，sql:' . $sql);
            return false;
        }

        return $result;
    }
}
