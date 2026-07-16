<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'setting';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function getInfo($name = '')
    {
        // 添加接受所有信息的管理员ID
        $content = self::query()->where('name', $name)->value("content");
        $content = str_replace('，', ',', $content);
        if(strpos($content, ",") === false) {
            return $content;
        }
        $content = explode(',', $content);

        return $content ?: [];

    }

}
