<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class DataSyncKey extends Model
{
    protected $table = 'data_sync_key';
    // 添加允许批量赋值的字段
    protected $fillable = [
        'tablename', 
        'unique_key', 
        'unique_value', 
        'jm_key'
    ];
}
