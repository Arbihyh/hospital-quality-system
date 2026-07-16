<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class ErrorHomeBmy extends Model
{
    // 病案首页质控结果(编码员)
    protected $table = 'error_home_bmy';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
