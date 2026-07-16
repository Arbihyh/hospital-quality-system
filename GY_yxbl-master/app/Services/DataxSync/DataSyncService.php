<?php

namespace App\Services\DataxSync;


use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DataSyncService extends DataxSyncService
{

    protected $database_type = 'ORACLE';

    //ODS 用户名
    protected $user_name = 'C##YW_BAZK';

    //ODS 密码
    protected $pass_word = 'Yw#bazk123';

    //ODS 链接地址
    protected $server_ip = '10.32.82.69:1521/ods';

    //sjkid
    protected $sjkid = '1';

    protected static $instances = [];




    public function __construct($sjkid)
    {
        $this->sjkid = $sjkid;
        parent::__construct($sjkid);
    }

    /**
     * 获取实例类  单例模式
     * @param string $sjkid
     * @return self
     */
    public static function getInstance($sjkid) :self
    {
        if(!isset(self::$instances[$sjkid])){
            self::$instances[$sjkid] = new self($sjkid);
        }
        return self::$instances[$sjkid];
    }

    /**
     * @param $sql
     * @return $this
     */
    public function setSql($sql) :self
    {
        $this->sql = $sql;
        return $this;
    }

    /**
     * 添加等于条件
     * @param string $field
     * @param $value
     * @return $this
     */
    public function setWhere(string $field,$value) :self
    {
        if($this->sql) {
            if (strpos($field, '?') !== false) {
                // $field 中存在 ?，使用参数绑定的形式（简化为直接用str_replace，因为本类为拼sql字符串，不实际参数绑定）
                if (is_array($value)) {
                    // 如果 $value 是数组，按顺序替换每个 ?
                    foreach ($value as $val) {
                        $field = preg_replace('/\?/', "'{$val}'", $field, 1);
                    }
                } else {
                    $field = str_replace('?', "'{$value}'", $field);
                }
                $this->sql .= (stripos($this->sql, 'WHERE') === false ? ' WHERE ' : ' AND ') . $field;
                return $this;
            } else {
                if(is_array($value)){
                    $value = $value[0];
                }
                $this->sql .= (stripos($this->sql, 'WHERE') === false ? ' WHERE ' : ' AND ') . "{$field} = '{$value}'";
                return $this;
            }
        }
        return $this;
    }


    /**
     * 添加时间查询条件
     * @param string $field
     * @param string $start_time
     * @param string $end_time
     * @return $this
     */
    public function setTimeWhere(string $field,string $start_time, string $end_time) :self
    {
        $start_time = Carbon::parse($start_time??NULL)->startOfDay()->toDateTimeString();
        $end_time = Carbon::parse($end_time??NULL)->endOfDay()->toDateTimeString();

        if($this->sql) {
            $prefix_keyword = (stripos($this->sql, 'WHERE') == false) ? ' WHERE' : ' AND';
            $this->sql .= "{$prefix_keyword} {$field} BETWEEN '{$start_time}' AND '{$end_time}'";
        }
        return $this;
    }


    /**
     * 添加时间查询条件
     * @param string $field
     * @param string $start_time
     * @param string $end_time
     * @return $this
     */
    public function setTime(string $field,string $start_time, string $end_time) :self
    {
        if($this->sql) {
            $prefix_keyword = (stripos($this->sql, 'WHERE') == false) ? ' WHERE' : ' AND';
            $this->sql .= "{$prefix_keyword} {$field} BETWEEN {$start_time} AND {$end_time}";
        }
        return $this;
    }

    /**
     * 添加倒序排序
     * @param string $field 排序字段
     * @return $this
     */
    public function setOrderByDesc(string $field) :self 
    {
        if($this->sql) { 
            // 防止SQL注入，只允许字段名包含字母、数字和下划线
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                throw new \InvalidArgumentException('Invalid field name');
            }
            $this->sql .= " ORDER BY {$field} DESC";
        }
        return $this;
    }

    public function getDataSync($table_name,$unique_key){
        try {
            // 统一调用getResult方法，内部会根据数据库类型处理
            $res = $this->getResult();
            if($res){
                collect($res)->map(function($data)use($table_name,$unique_key){
                    $unique_value = data_get($data,$unique_key,'');

                    // 先检查unique_value是否有效
                    if (!$unique_value) {
                        Log::info(__METHOD__ . '数据同步失败::unique_value为空',[$unique_key,$unique_value,$data]);
                        return;
                    }

                    // 验证通过后再从数据中移除unique_key
                    unset($data[$unique_key]);

                    // 处理字段大小写冲突问题
                    // 检查是否存在大写的UPDATED_AT字段，存在则移除，避免与Laravel自动添加的updated_at字段冲突
                    if (array_key_exists('UPDATED_AT', $data)) {
                        // 如果是null值，可以直接移除
                        if ($data['UPDATED_AT'] === null) {
                            unset($data['UPDATED_AT']);
                        }
                        // 如果有值且当前没有小写的updated_at，则使用它的值
                        else if (!array_key_exists('updated_at', $data)) {
                            $data['updated_at'] = $data['UPDATED_AT'];
                            unset($data['UPDATED_AT']);
                        }
                        // 两者都有值，优先使用小写的
                        else {
                            unset($data['UPDATED_AT']);
                        }
                    }

                    // 同样处理CREATED_AT字段
                    if (array_key_exists('CREATED_AT', $data)) {
                        if ($data['CREATED_AT'] === null) {
                            unset($data['CREATED_AT']);
                        } else if (!array_key_exists('created_at', $data)) {
                            $data['created_at'] = $data['CREATED_AT'];
                            unset($data['CREATED_AT']);
                        } else {
                            unset($data['CREATED_AT']);
                        }
                    }

                    // 设置时间戳字段
                    data_set($data,'updated_at',Carbon::now()->toDateTimeString());
                    data_set($data,'created_at',Carbon::now()->toDateTimeString());

                    // 查询是否存在记录
                    $existingRecord = DB::table($table_name)
                        ->where($unique_key, $unique_value)
                        ->first();

                    try {
                        if ($existingRecord) {
                            // 更新记录
                            if (!DB::table($table_name)->where($unique_key, $unique_value)->update($data)) {
                                Log::info(__METHOD__ . '数据更新失败::', [$unique_key, $unique_value, $data]);
                            }
                        } else {
                            // 插入新记录
                            $data[$unique_key] = $unique_value;
                            if (!DB::table($table_name)->insert($data)) {
                                Log::info(__METHOD__ . '数据插入失败::', [$unique_key, $unique_value, $data]);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error(__METHOD__ . '数据处理异常::', [
                            'error' => $e->getMessage(),
                            'unique_key' => $unique_key,
                            'unique_value' => $unique_value,
                            'data' => $data
                        ]);
                    }
                });
            }
        }catch (\Exception $e){
            Log::info(__METHOD__ . "获取数据失败::",[$e->getMessage(),$e->getCode()]);
            return false;
        }
        return $res;
    }

}
