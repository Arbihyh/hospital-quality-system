<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class ErrorV2 extends Model
{
    // 病案首页质控结果(事中)
    protected $table = 'error_v2';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
