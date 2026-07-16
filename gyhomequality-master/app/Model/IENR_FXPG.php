<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * 风险评估汇总表Model
 * 
 * @property string $PGXH 评估序号
 * @property string $ZYH 住院就诊流水号码
 * @property string $PGDH 评估单号
 * @property string $PGDMC 评估单名称
 * @property string $PGSJ 评估时间
 * @property string $PGGH 评估工号
 * @property float $PGZF 评估总分
 * @property string $CDMS 存档描述
 * @property string $PGNR 评估内容
 * @property string $YZMS 因子描述
 * @property float $PFFZ 评分分值
 * @property string $FZMS 分值描述
 */
class IENR_FXPG extends Model
{
    /**
     * 关联的数据表
     *
     * @var string
     */
    protected $table = 'IENR_FXPG';

    /**
     * 主键字段
     *
     * @var string
     */
    protected $primaryKey = 'PGXH';

    /**
     * 主键类型
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * 主键是否自增
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * 是否使用时间戳
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
        'PGXH',
        'ZYH',
        'PGDH',
        'PGDMC',
        'PGSJ',
        'PGGH',
        'PGZF',
        'CDMS',
        'PGNR',
        'YZMS',
        'PFFZ',
        'FZMS',
        'CREATE_TIME',
        'UPDATE_TIME',
        'DATA_STATUS',
    ];

    /**
     * 序列化日期格式
     *
     * @param DateTimeInterface $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
