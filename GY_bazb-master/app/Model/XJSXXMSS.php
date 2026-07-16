<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * 新技术新项目手术实施表
 */
class XJSXXMSS extends Model
{
    protected $table = 'XJSXXMSS';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}