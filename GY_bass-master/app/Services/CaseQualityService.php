<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CaseQualityService
{

    /**
     * 获取病例数量
     *
     * @param $queryCond
     * @return int
     */
    public static function getCaseTotal($queryCond)
    {
        // 获取病例数量
        $sql = 'SELECT count(1) as nums from patient_info as pi left join (select t2.JZHM,t2.is_defect from (SELECT MAX(BLBH) as BLBH FROM `EMR_BL_BL01` GROUP BY JZHM) as t1 left join EMR_BL_BL01 as t2 on t1.BLBH=t2.BLBH) as t3 on pi.MED_REC_ID=t3.JZHM';
        $whereCase = ' where pi.AAC01 > "'.$queryCond->start_time.'" and pi.AAC01 < "'.$queryCond->end_time.' 23:59:59"';
        $caseTotal = DB::selectOne($sql . $whereCase)->nums;

        DebugItemsService::getInstance()->putDebugItem('病例数量', $sql . $whereCase);

        return $caseTotal;
    }

    /**
     * 获取缺陷病案数
     *
     * @param $queryCond
     * @return int
     */
    public static function getDefectCaseTotal($queryCond)
    {
        // 获取病例数量
        $sql = 'SELECT count(1) as nums from patient_info as pi inner join (select t2.JZHM,t2.is_defect from (SELECT MAX(BLBH) as BLBH FROM `EMR_BL_BL01` where is_defect=1 GROUP BY JZHM) as t1 left join EMR_BL_BL01 as t2 on t1.BLBH=t2.BLBH) as t3 on pi.MED_REC_ID=t3.JZHM';
        $whereCase = ' where pi.AAC01 > "'.$queryCond->start_time.'" and pi.AAC01 < "'.$queryCond->end_time.' 23:59:59"';
        $defectCaseTotal = DB::selectOne($sql . $whereCase)->nums;

        DebugItemsService::getInstance()->putDebugItem('缺陷病案数', $sql . $whereCase);

        return $defectCaseTotal;
    }


    /**
     * 获取科室的病例数量
     *
     * @param $queryCond
     * @return int
     */
    public static function getDepartmentCases($queryCond)
    {
        // 获取病例数量
        $sql = 'SELECT AAC11N,count(1) as nums from patient_info as pi left join (select t2.JZHM,t2.is_defect from (SELECT MAX(BLBH) as BLBH FROM `EMR_BL_BL01` GROUP BY JZHM) as t1 left join EMR_BL_BL01 as t2 on t1.BLBH=t2.BLBH) as t3 on pi.MED_REC_ID=t3.JZHM';
        $whereCase = ' where pi.AAC01 > "'.$queryCond->start_time.'" and pi.AAC01 < "'.$queryCond->end_time.' 23:59:59"';
        $others = ' group by AAC11N ';
        $cases = DB::select($sql . $whereCase . $others);

        DebugItemsService::getInstance()->putDebugItem('科室的病例数量', $sql . $whereCase . $others);

        return $cases;
    }

    /**
     * 获取科室的缺陷病案数
     *
     * @param $queryCond
     * @return int
     */
    public static function getDepartmentDefectCases($queryCond, $limit = 0)
    {
        // 获取病例数量
        $sql = 'SELECT AAC11N,count(1) as nums from patient_info as pi inner join (select t2.JZHM,t2.is_defect from (SELECT MAX(BLBH) as BLBH FROM `EMR_BL_BL01` where is_defect=1 GROUP BY JZHM) as t1 left join EMR_BL_BL01 as t2 on t1.BLBH=t2.BLBH) as t3 on pi.MED_REC_ID=t3.JZHM';
        $whereCase = ' where pi.AAC01 > "'.$queryCond->start_time.'" and pi.AAC01 < "'.$queryCond->end_time.' 23:59:59"';
        $others = ' group by AAC11N  order by nums asc';
        if ($limit > 0) {
            $others .= ' LIMIT ' . $limit;
        }
        $cases = DB::select($sql . $whereCase . $others);

        DebugItemsService::getInstance()->putDebugItem('科室的缺陷病案数', $sql . $whereCase . $others);

        return $cases;
    }

}