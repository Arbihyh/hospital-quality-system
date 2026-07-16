<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * 质控报告统计汇总表模型
 * 
 * @property int $id 主键ID
 * @property string $stat_period 统计期间
 * @property string $stat_type 统计类型
 * @property string|null $dimension_id 维度ID
 * @property string|null $dimension_name 维度名称
 * @property int $total_cases 病历总数
 * @property int $defect_cases 缺陷病历数
 * @property float $defect_ratio 缺陷占比
 * @property float $avg_defects 平均缺陷数
 * @property int $total_defects 总缺陷数
 * @property int $grade_a_count 甲级病历数
 * @property float $grade_a_ratio 甲级占比
 * @property int $grade_b_count 乙级病历数
 * @property float $grade_b_ratio 乙级占比
 * @property int $grade_c_count 丙级病历数
 * @property float $grade_c_ratio 丙级占比
 * @property float $avg_score 平均分数
 * @property float $max_score 最高分数
 * @property float $min_score 最低分数
 * @property int $rule_defect_count 规则缺陷次数
 * @property int $rule_case_count 涉及规则的病历数
 * @property int $is_single_no 是否单否项
 * @property int $appeal_total 申诉总数
 * @property int $appeal_approved 申诉通过数
 * @property int $appeal_rejected 申诉驳回数
 * @property int $appeal_pending 申诉待处理数
 * @property float $appeal_success_ratio 申诉成功率
 * @property float $indicator_value 指标值
 * @property float $indicator_target 指标目标值
 * @property float $indicator_completion 指标完成率
 * @property string|null $extra_data 扩展数据
 * @property \Carbon\Carbon|null $created_at 创建时间
 * @property \Carbon\Carbon|null $updated_at 更新时间
 */
class QualityReportStatistics extends Model
{
    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'quality_report_statistics';

    /**
     * 可批量赋值的属性
     *
     * @var array
     */
    protected $fillable = [
        'stat_period',
        'stat_type',
        'dimension_id',
        'dimension_name',
        'total_cases',
        'defect_cases',
        'defect_ratio',
        'avg_defects',
        'total_defects',
        'grade_a_count',
        'grade_a_ratio',
        'grade_b_count',
        'grade_b_ratio',
        'grade_c_count',
        'grade_c_ratio',
        'avg_score',
        'max_score',
        'min_score',
        'rule_defect_count',
        'rule_case_count',
        'is_single_no',
        'appeal_total',
        'appeal_approved',
        'appeal_rejected',
        'appeal_pending',
        'appeal_success_ratio',
        'indicator_value',
        'indicator_target',
        'indicator_completion',
        'extra_data',
    ];

    /**
     * 统计类型常量
     */
    const TYPE_OVERALL = 'overall';                 // 总体统计
    const TYPE_DEPARTMENT = 'department';           // 科室维度
    const TYPE_DOCTOR = 'doctor';                  // 医师维度
    const TYPE_RULE = 'rule';                      // 规则维度
    const TYPE_RULE_DEPARTMENT = 'rule_department'; // 规则按科室维度
    const TYPE_INDICATOR = 'indicator';            // 指标维度
    const TYPE_APPEAL = 'appeal';                  // 申诉维度(总体,已废弃)
    const TYPE_APPEAL_RULE = 'appeal_rule';        // 申诉按规则统计
    const TYPE_APPEAL_DEPARTMENT = 'appeal_department'; // 申诉按科室统计

    /**
     * 获取指定期间的总体统计
     *
     * @param string $period 期间标签,如"2025年11月"
     * @return QualityReportStatistics|null
     */
    public static function getOverallStats($period)
    {
        return self::where('stat_period', $period)
            ->where('stat_type', self::TYPE_OVERALL)
            ->first();
    }

    /**
     * 获取指定期间的科室统计
     *
     * @param string $period 期间标签
     * @param string|null $deptCode 科室代码,不传则返回所有科室
     * @return \Illuminate\Database\Eloquent\Collection|QualityReportStatistics|null
     */
    public static function getDepartmentStats($period, $deptCode = null)
    {
        $query = self::where('stat_period', $period)
            ->where('stat_type', self::TYPE_DEPARTMENT);
        
        if ($deptCode) {
            return $query->where('dimension_id', $deptCode)->first();
        }
        
        return $query->get();
    }

    /**
     * 获取指定期间的医师统计
     *
     * @param string $period 期间标签
     * @param string|null $doctorCode 医师工号,不传则返回所有医师
     * @return \Illuminate\Database\Eloquent\Collection|QualityReportStatistics|null
     */
    public static function getDoctorStats($period, $doctorCode = null)
    {
        $query = self::where('stat_period', $period)
            ->where('stat_type', self::TYPE_DOCTOR);
        
        if ($doctorCode) {
            return $query->where('dimension_id', $doctorCode)->first();
        }
        
        return $query->get();
    }

    /**
     * 获取指定期间的规则统计
     *
     * @param string $period 期间标签
     * @param int|null $ruleId 规则ID,不传则返回所有规则
     * @param bool $onlySingleNo 是否只返回单否项
     * @return \Illuminate\Database\Eloquent\Collection|QualityReportStatistics|null
     */
    public static function getRuleStats($period, $ruleId = null, $onlySingleNo = false)
    {
        $query = self::where('stat_period', $period)
            ->where('stat_type', self::TYPE_RULE);
        
        if ($onlySingleNo) {
            $query->where('is_single_no', 1);
        }
        
        if ($ruleId) {
            return $query->where('dimension_id', $ruleId)->first();
        }
        
        return $query->get();
    }

    /**
     * 获取指定期间的指标统计
     *
     * @param string $period 期间标签
     * @param int|null $catalogId 指标目录ID,不传则返回所有指标
     * @return \Illuminate\Database\Eloquent\Collection|QualityReportStatistics|null
     */
    public static function getIndicatorStats($period, $catalogId = null)
    {
        $query = self::where('stat_period', $period)
            ->where('stat_type', self::TYPE_INDICATOR);
        
        if ($catalogId) {
            return $query->where('dimension_id', $catalogId)->first();
        }
        
        return $query->get();
    }

    /**
     * 获取指定期间的申诉统计
     *
     * @param string $period 期间标签
     * @param string|null $doctorCode 医师工号,不传则返回总体申诉统计
     * @return \Illuminate\Database\Eloquent\Collection|QualityReportStatistics|null
     */
    public static function getAppealStats($period, $doctorCode = null)
    {
        $query = self::where('stat_period', $period)
            ->where('stat_type', self::TYPE_APPEAL);
        
        if ($doctorCode) {
            return $query->where('dimension_id', $doctorCode)->first();
        } else {
            return $query->whereNull('dimension_id')->first();
        }
    }
    
    /**
     * 获取指定期间的规则按科室统计
     *
     * @param string $period 期间标签
     * @param int|null $ruleId 规则ID,不传则返回所有规则
     * @param string|null $deptId 科室ID,不传则返回所有科室
     * @param bool $onlySingleNo 是否只返回单否项
     * @return \Illuminate\Database\Eloquent\Collection|QualityReportStatistics|null
     */
    public static function getRuleDepartmentStats($period, $ruleId = null, $deptId = null, $onlySingleNo = false)
    {
        $query = self::where('stat_period', $period)
            ->where('stat_type', self::TYPE_RULE_DEPARTMENT);
        
        if ($onlySingleNo) {
            $query->where('is_single_no', 1);
        }
        
        if ($ruleId !== null && $deptId !== null) {
            return $query->where('dimension_id', $ruleId . '_' . $deptId)->first();
        } elseif ($ruleId !== null) {
            $query->where('extra_data', 'like', '%"rule_id":' . $ruleId . '%');
        } elseif ($deptId !== null) {
            $query->where('extra_data', 'like', '%"department_id":"' . $deptId . '"%');
        }
        
        return $query->get();
    }
}
