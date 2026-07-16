<?php

namespace App\Services\DataxSync;

use App\Model\DataxSyncSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DataxSyncService
{

    const ORACLE = 'ORACLE';

    const SQL_SERVER = 'SQL_SERVER';

    //数据库类型
    protected $database_type = 'ORACLE';

    //用户名
    protected $user_name = 'C##YW_BAZK';

    //密码
    protected $pass_word = 'Yw#bazk123';

    //链接地址
    protected $server_ip = '10.32.82.69:1521/ods';

    //数据库名称
    protected $databases = 'quality';

    //编码
    protected $character = 'UTF8';

    //实例类
    protected static $instance;

    //链接
    protected $connect;

    //语句
    protected $sql;

    /**
     * @var bool
     */
    protected static $state = true;

    /**
     * @var bool
     */
    protected static $error_msg = 'success';

    public function __construct()
    {
        $this->connect = $this->getConnect();
    }


    private function getConnect()
    {
        $connect = false;
        $config = config('database.connections.oracle');
        try {
            switch ($this->database_type) {
                case self::SQL_SERVER:
                    $connect = sqlsrv_connect($this->server_ip,["Database" => $this->databases, "Uid" => $this->user_name, "PWD" => $this->pass_word]);
                    //$connect = oci_connect($config['username'], $config['password'], $config['host'].':'.$config['port'].'/'.$config['database'], $this->character);
                    if (!$connect)
                        Log::info(__CLASS__ . '链接SQL_SERVER数据库报错::', ['msg' => json_encode(sqlsrv_errors())]);
                    var_dump($connect);
                    break;

                case self::ORACLE:
                    $connect = oci_connect($config['username'], $config['password'], $config['host'].':'.$config['port'].'/'.$config['database'], $this->character);
                    if (!$connect)
                        Log::info(__CLASS__ . '链接ODS数据库报错::', ['msg' => htmlentities(data_get(oci_error(), 'message'))]);
                    break;
                default:
                    //$connect = oci_connect($this->user_name, $this->pass_word, $this->server_ip, $this->character);
                    $connect = oci_connect($config['username'], $config['password'], $config['host'].':'.$config['port'].'/'.$config['database'], $this->character);
                    if (!$connect)
                        Log::info(__CLASS__ . '链接ODS数据库报错::', ['msg' => htmlentities(data_get(oci_error(), 'message'))]);
                    break;
            }
        } catch (\Exception $exception) {
            Log::info(__CLASS__ . '链接数据库报错::' . $exception->getMessage());
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
                sqlsrv_close($this->connect);
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
        $this->sql = strtoupper(DataxSyncSetting::getByNameSql($name, $num));
        return $this;
    }

    /**
     * 根据ID获取指定sql
     * @param int $id
     * @return self
     */
    public function setByIdSql(int $id): self
    {
        $this->sql = strtoupper(DataxSyncSetting::getByIdSql($id));
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
     * ORACLE
     * @return array|false
     */
    public function getResult()
    {
        if (!$this->connect || !$this->sql)
            return false;
        //var_dump($this->connect);
        //var_dump($this->sql);

        $result = [];
        try {
            $data = oci_parse($this->connect, $this->sql);

            oci_execute($data, OCI_DEFAULT);

            while ($row = oci_fetch_assoc($data)) {
                $result[] = $row;
            }
            oci_free_statement($data);

        } catch (\Exception $exception) {
            Log::error(__CLASS__ . "ODS查询失败,请检查链接或查询语句::" . $exception->getMessage());
            return false;
        }

        return $result;
    }

    /**
     * SQL_SERVER
     * @return array|false
     */
    public function getSqlServerResult()
    {
        if (!$this->connect || !$this->sql)
            return false;

        $result = [];
        try {
            $data = sqlsrv_query($this->connect, $this->sql);

            if ($data == false)
                throw new \Exception(json_encode(sqlsrv_errors()), 1001);

            while (($row = sqlsrv_fetch_array($data, SQLSRV_FETCH_ASSOC))) {
                $result[] = $row;
            }

            sqlsrv_free_stmt($data);
        } catch (\Exception $exception) {
            Log::error(__CLASS__ . "ODS查询失败,请检查链接或查询语句::" . $exception->getMessage());
            return false;
        }

        return $result;
    }

}
