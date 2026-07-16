<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class RuleFormula extends Model
{
    protected $table = 'rule_formula';

    // 如果不需要时间戳字段，可以禁用它们
    public $timestamps = false;

    // 允许批量赋值的字段
    protected $fillable = ['formula'];
}
