<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class RuleWordMap extends Model
{
    protected $table = 'rule_word_map';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function getInfo($name = '')
    {
        // 添加接受所有信息的管理员ID
        $wordMap = RuleWordMap::query()->where('name', $name)->get()->toArray();
        $white = !empty($wordMap[0]) ? $wordMap[0]['keyword'] : '';
        $white = str_replace('，', ',', $white);
        $white = explode(',', $white);

        return $white ?: [];
    }

    public static function getFirstById($id)
    {
        // 添加接受所有信息的管理员ID
        $wordMap = RuleWordMap::query()->where('id', $id)->first();
        if (empty($wordMap)) {
            return [];
        }
        $wordMap = $wordMap->toArray();
        if (strpos($wordMap['keyword'], ",") === false) {
            return $wordMap;
        }

        $white = !empty($wordMap) ? $wordMap['keyword'] : '';
        $white = str_replace('，', ',', $white);
        $white = explode(',', $white);

        return $white ?: [];
    }

    public static function getArrayById($id = 0)
    {
        // 添加接受所有信息的管理员ID
        $wordMap = RuleWordMap::query()->where('id', $id)->first();
        if (empty($wordMap)) {
            throw new \Exception('id: ' . $id.' rule_word_map信息缺失');
        }
        $wordMap = $wordMap->toArray();
        $white = !empty($wordMap) ? $wordMap['keyword'] : '';
        $white = str_replace('，', ',', $white);
        $white = explode(',', $white);
        return $white ?: [];
    }
}
