<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class PatientInfoTargetTemporary extends Model
{
    // 指标质控结果数据
    protected $table = 'patient_info_target_temporary';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
