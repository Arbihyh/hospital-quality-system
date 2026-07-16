<?php

namespace App\Services;

use App\Model\QualityReportStatistics;
use Illuminate\Support\Facades\DB;

/**
 * 质控报告统计数据服务
 * 从统计表中读取预先计算好的统计数据
 */
class QualityReportStatisticsService
{
    /**
     * 获取规则统计数据(按缺陷次数排序)
     *
     * @param string $periodLabel 期间标签
     * @param bool $onlySingleNo 是否只获取单否项
     * @param int $limit 限制数量
     * @return array
     */
    public static function getRuleStatistics($periodLabel, $onlySingleNo = false, $limit = null)
    {
        $query = QualityReportStatistics::where('stat_period', $periodLabel)
            ->where('stat_type', QualityReportStatistics::TYPE_RULE);
        
        if ($onlySingleNo) {
            $query->where('is_single_no', 1);
        }
        
        $query->orderBy('rule_defect_count', 'desc');
        
        if ($limit) {
            $query->limit($limit);
        }
        
        return $query->get();
    }
    
    /**
     * 获取科室统计数据(按缺陷率或病历质量排序)
     *
     * @param string $periodLabel 期间标签
     * @param string $orderBy 排序字段: defect_ratio, grade_a_ratio
     * @param string $direction 排序方向: asc, desc
     * @param int $limit 限制数量
     * @return array
     */
    public static function getDepartmentStatistics($periodLabel, $orderBy = 'defect_ratio', $direction = 'desc', $limit = null)
    {
        $query = QualityReportStatistics::where('stat_period', $periodLabel)
            ->where('stat_type', QualityReportStatistics::TYPE_DEPARTMENT)
            ->orderBy($orderBy, $direction);
        
        if ($limit) {
            $query->limit($limit);
        }
        
        return $query->get();
    }
    
    /**
     * 获取医师统计数据
     *
     * @param string $periodLabel 期间标签
     * @param string $orderBy 排序字段
     * @param string $direction 排序方向
     * @param int $limit 限制数量
     * @return array
     */
    public static function getDoctorStatistics($periodLabel, $orderBy = 'defect_ratio', $direction = 'desc', $limit = null)
    {
        $query = QualityReportStatistics::where('stat_period', $periodLabel)
            ->where('stat_type', QualityReportStatistics::TYPE_DOCTOR)
            ->orderBy($orderBy, $direction);
        
        if ($limit) {
            $query->limit($limit);
        }
        
        return $query->get();
    }
    
    /**
     * 获取指标统计数据
     *
     * @param string $periodLabel 期间标签
     * @param string $dimensionType 维度类型: overall, department, doctor
     * @param string $orderBy 排序字段
     * @param string $direction 排序方向
     * @param int $limit 限制数量
     * @return array
     */
    public static function getIndicatorStatistics($periodLabel, $dimensionType = 'overall', $orderBy = 'indicator_completion', $direction = 'desc', $limit = null)
    {
        $query = QualityReportStatistics::where('stat_period', $periodLabel)
            ->where('stat_type', QualityReportStatistics::TYPE_INDICATOR);
        
        if ($dimensionType !== 'all') {
            $query->where('extra_data', 'like', '%"dimension_type":"' . $dimensionType . '"%');
        }
        
        $query->orderBy($orderBy, $direction);
        
        if ($limit) {
            $query->limit($limit);
        }
        
        return $query->get();
    }
    
    /**
     * 获取申诉按规则统计数据(用于申诉前五问题)
     *
     * @param string $periodLabel 期间标签
     * @param string $orderBy 排序字段: appeal_rejected(驳回数)
     * @param string $direction 排序方向
     * @param int $limit 限制数量
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAppealByRuleStatistics($periodLabel, $orderBy = 'appeal_rejected', $direction = 'desc', $limit = null)
    {
        $query = QualityReportStatistics::where('stat_period', $periodLabel)
            ->where('stat_type', 'appeal_rule')
            ->orderBy($orderBy, $direction);
        
        if ($limit) {
            $query->limit($limit);
        }
        
        return $query->get();
    }
    
    /**
     * 获取申诉按科室统计数据(用于所有科室申诉情况)
     *
     * @param string $periodLabel 期间标签
     * @param string $orderBy 排序字段: appeal_success_ratio(成功率)
     * @param string $direction 排序方向
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAppealByDepartmentStatistics($periodLabel, $orderBy = 'appeal_success_ratio', $direction = 'desc')
    {
        return QualityReportStatistics::where('stat_period', $periodLabel)
            ->where('stat_type', 'appeal_department')
            ->orderBy($orderBy, $direction)
            ->get();
    }
    
    /**
     * 获取申诉统计数据(已废弃,保留兼容)
     *
     * @param string $periodLabel 期间标签
     * @param bool $byDoctor 是否按医师分组
     * @return array
     */
    public static function getAppealStatistics($periodLabel, $byDoctor = false)
    {
        $query = QualityReportStatistics::where('stat_period', $periodLabel)
            ->where('stat_type', QualityReportStatistics::TYPE_APPEAL);
        
        if ($byDoctor) {
            $query->whereNotNull('dimension_id');
        } else {
            $query->whereNull('dimension_id');
        }
        
        return $query->get();
    }
    
    /**
     * 获取规则按科室分布数据
     *
     * @param string $periodLabel 期间标签
     * @param int|null $ruleId 规则ID
     * @param bool $onlySingleNo 是否只获取单否项
     * @param string $orderBy 排序字段
     * @param string $direction 排序方向
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getRuleDepartmentStatistics($periodLabel, $ruleId = null, $onlySingleNo = false, $orderBy = 'rule_defect_count', $direction = 'desc')
    {
        $query = QualityReportStatistics::where('stat_period', $periodLabel)
            ->where('stat_type', 'rule_department');
        
        if ($onlySingleNo) {
            $query->where('is_single_no', 1);
        }
        
        if ($ruleId !== null) {
            $query->where('extra_data', 'like', '%"rule_id":' . $ruleId . '%');
        }
        
        $query->orderBy($orderBy, $direction);
        
        return $query->get();
    }
    
    /**
     * 获取单否项按科室分布数据
     *
     * @param string $periodLabel 期间标签
     * @param int|null $ruleId 规则ID,不传则返回所有单否项
     * @param int $limit 限制数量
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getSingleNoItemByDepartment($periodLabel, $ruleId = null, $limit = null)
    {
        return self::getRuleDepartmentStatistics($periodLabel, $ruleId, true, 'rule_defect_count', 'desc');
    }
    
    /**
     * 获取所有单否项及其科室分布
     * 返回格式: [规则ID => [科室数据...]]
     *
     * @param string $periodLabel 期间标签
     * @return array
     */
    public static function getAllSingleNoItemWithDepartments($periodLabel)
    {
        $stats = QualityReportStatistics::where('stat_period', $periodLabel)
            ->where('stat_type', 'rule_department')
            ->where('is_single_no', 1)
            ->orderBy('rule_defect_count', 'desc')
            ->get();
        
        $result = [];
        foreach ($stats as $stat) {
            $extraData = json_decode($stat->extra_data, true);
            $ruleId = $extraData['rule_id'] ?? null;
            
            if ($ruleId === null) {
                continue;
            }
            
            if (!isset($result[$ruleId])) {
                $result[$ruleId] = [
                    'rule_name' => $extraData['rule_name'] ?? '',
                    'departments' => []
                ];
            }
            
            $result[$ruleId]['departments'][] = [
                'department_id' => $extraData['department_id'] ?? '',
                'department_name' => $extraData['department_name'] ?? '',
                'defect_count' => $stat->rule_defect_count,
                'case_count' => $stat->rule_case_count,
            ];
        }
        
        return $result;
    }
}
