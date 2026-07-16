<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * 门诊反馈模型
 */
class MZFK extends Model
{
    protected $table = 'MZFK';

    protected $fillable = [
        'status',
        'rule_id',
        'blbh',
        'jzsj',
        'ks',
        'ys',
        'mzh',
        'xm',
        'xb',
        'nl',
        'fssj',
        'fsr',
    ];

    public $timestamps = false;
}
