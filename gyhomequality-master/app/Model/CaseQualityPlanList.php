<?php


namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class CaseQualityPlanList extends Model
{
    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'case_quality_plan_list';

    /**
     * 指示是否自动维护时间戳
     *
     * @var bool
     */
    public $timestamps = false;
}
