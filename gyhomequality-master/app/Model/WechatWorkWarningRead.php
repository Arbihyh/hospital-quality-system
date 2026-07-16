<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class WechatWorkWarningRead extends Model
{
    protected $table = 'wechat_work_warning_reads';

    protected $fillable = [
        'warning_key',
        'source_log_id',
        'user_id',
        'wechat_userid',
        'read_at',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
