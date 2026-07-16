<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class WechatWorkPushConfig extends Model
{
    protected $table = 'wechat_work_push_configs';

    protected $fillable = [
        'name',
        'enabled',
        'corp_id',
        'secret',
        'agent_id',
        'access_token',
        'api_base_url',
        'detail_url_template',
        'timeout',
        'message_type',
        'callback_url',
        'callback_token',
        'callback_aes_key',
        'repeat_enabled',
        'repeat_type',
        'repeat_interval_minutes',
        'repeat_times',
        'default_userids',
        'default_group_ids',
        'unchanged_skip_enabled',
        'remind_unread_only',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * 获取默认策略配置。
     *
     * @return self|null
     */
    public static function getDefaultConfig()
    {
        return self::query()->orderBy('id')->first();
    }
}
