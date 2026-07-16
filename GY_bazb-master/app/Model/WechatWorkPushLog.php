<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class WechatWorkPushLog extends Model
{
    protected $table = 'wechat_work_push_logs';

    protected $fillable = [
        'source_log_id',
        'source_type',
        'warning_key',
        'patient_zyh',
        'patient_no',
        'rule_id',
        'case_quality_id',
        'data_id',
        'quality_type',
        'title',
        'content',
        'quality_content',
        'message_type',
        'task_id',
        'event_key',
        'receiver_type',
        'receiver_id',
        'receiver_name',
        'send_status',
        'send_time',
        'fail_reason',
        'request_body',
        'response_body',
        'read_status',
        'read_at',
        'read_user_id',
        'repeat_batch',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
