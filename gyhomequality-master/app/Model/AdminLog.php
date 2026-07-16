<?php


namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class AdminLog extends Model
{
    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'admin_log';

    /**
     * 指示是否自动维护时间戳
     *
     * @var bool
     */
    public $timestamps = false;
}
