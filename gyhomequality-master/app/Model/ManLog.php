<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * 运营日志模型
 */
class ManLog extends Model
{
    protected $table = 'man_logs';

    // 表中 created_at 为 varchar，且无 updated_at，关闭时间戳
    public $timestamps = false;

    protected $fillable = [
        'name',
        'realname',
        'loginip',
        'content',
        'created_at',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}


