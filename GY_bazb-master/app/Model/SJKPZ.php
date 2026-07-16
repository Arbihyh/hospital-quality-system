<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class SJKPZ extends Model
{
    protected $table = 'SJK_PZ';

    // 如果表没有 created_at 和 updated_at 字段，设置为 false
    public $timestamps = false;

}