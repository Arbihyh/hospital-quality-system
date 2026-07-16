<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class EmrBlStatistics extends Model
{
    protected $table = 'emr_bl_statistics';

    protected $fillable=['BLBH','JZHM','date','denominator','numerator'];

    const FLAG = [1=>'CT/MRI检查记录符合率',2=>'植入物相关记录符合率'];

    // 1:分母 2:分子
    const KEY = [1=>'denominator',2=>'numerator'];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
