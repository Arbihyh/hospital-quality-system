<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class RuleConfig extends Model
{
    protected $table = 'rule_config';

    protected $guarded = [];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * @param $id
     * @param false $flag
     * @return array|false|\Illuminate\Database\Eloquent\Builder|Model|object
     */
    public static function getFirstById($id,$flag = false){
        $res = self::query()->where(compact('id'))->first();
        return $res ? ($flag ? $res : $res->getAttributes()) : false;
    }

    /**
     * @param $id
     * @param false $is_array
     * @return false|mixed|string[]
     */
    public static function getValueById($id,$is_array = false){

        $value = self::query()->where(compact('id'))->value('keyword');

        if($is_array) $value = explode(',', str_replace('，', ',', $value));

        return $value;
    }

    /**
     * @param $id
     * @param false $flag
     * @return array|false|\Illuminate\Database\Eloquent\Builder|Model|object
     */
    public static function getFirstByName($name, bool $flag = false){
        $res = self::query()->where(compact('name'))->first();
        return $res ? ($flag ? $res : $res->getAttributes()) : false;
    }

    /**
     * @param $id
     * @param false $is_array
     * @return false|mixed|string[]
     */
    public static function getValueByName($name, bool $is_array = false){

        $value = self::query()->where(compact('name'))->value('keyword');

        if($is_array) $value = explode(',', str_replace('，', ',', $value));

        return $value;
    }

}

