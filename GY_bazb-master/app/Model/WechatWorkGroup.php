<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class WechatWorkGroup extends Model
{
    protected $table = 'wechat_work_groups';

    protected $fillable = [
        'name',
        'send_type',
        'chat_id',
        'agent_id',
        'webhook_key',
        'webhook_url',
        'enabled',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
