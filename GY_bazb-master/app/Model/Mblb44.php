<?php


namespace App\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * 疑难病例讨论记录
 *
 * @property int $id
 * @property string $BLBH 病历编号
 * @property string $ZYH 住院号
 * @property string $TLSJ 讨论时间
 * @property string $TLDD 讨论地点
 * @property string $CJTLRY 参加讨论人员
 * @property string $ZCR 主持人
 * @property string $TLJL 讨论结论
 * @property string $MQZD 目前诊断
 * @property string $TLMD 讨论目的
 */
class Mblb44 extends Model
{
    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'mblb44';

    /**
     * 主键
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * 是否自动维护时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * 可批量赋值的属性
     *
     * @var array
     */
    protected $fillable = [
        'BLBH',
        'ZYH',
        'TLSJ',
        'TLDD',
        'CJTLRY',
        'ZCR',
        'TLJL',
        'MQZD',
        'TLMD',
    ];
}

