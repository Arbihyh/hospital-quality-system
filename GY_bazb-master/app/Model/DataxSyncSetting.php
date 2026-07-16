<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class DataxSyncSetting extends Model
{
    protected $table = 'datax_sync_setting';


    /**
     * 根据ID获取指定sql
     * @param int $id
     * @return mixed
     */
    public static function getByIdSql(int $id){
        return self::query()->where(compact('id'))->value('sql_content');
    }

    /**
     * 根据表名称&&生成sql时的数量获取
     * @param string $table_name
     * @param int $table_num
     * @return mixed
     */
    public static function getByNameSql(string $table_name, int $table_num = 0){
        return self::query()->where(compact('table_name','table_num'))->value('sql_content');
    }
}
