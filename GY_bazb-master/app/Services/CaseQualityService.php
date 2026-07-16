<?php

namespace App\Services;

use App\Model\CaseQualityV2;
use App\Model\PatientInfo;
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
        $whereCase = ' where pi.AAC01 > "' . $queryCond->start_time . '" and pi.AAC01 < "' . $queryCond->end_time . ' 23:59:59"';
        $caseTotal = DB::selectOne($sql . $whereCase)->nums;

        DebugItemsService::getInstance()->putDebugItem('病例数量', $sql . $whereCase);

        return $caseTotal;
    }

    /**
     * 获取病例数量
     *
     * @param $queryCond
     * @return int
     */
    public static function getCaseTotalByEs($queryCond)
    {
        $query = PatientInfo::query()
            ->leftJoin('ZY_BRRY as brry', 'patient_info.MED_REC_ID', '=', 'brry.ZYH')
            ->when($queryCond->AAA28, function ($query) use ($queryCond) {
                $query->where('brry.AAA28', $queryCond->AAA28);
            })
            ->when($queryCond->brks, function ($query) use ($queryCond) {
                $query->whereIn('brry.BRKS', is_array($queryCond->brks) ? $queryCond->brks : [$queryCond->brks]);
            })
            ->when(is_array($queryCond->dep_ids) && !empty($queryCond->dep_ids), function ($query) use ($queryCond) {
                $query->whereIn('brry.BRKS', $queryCond->dep_ids);
            })
            ->when($queryCond->start_time, function ($query) use ($queryCond) {
                $query->where('patient_info.AAB01', '>=', $queryCond->start_time);
            })
            ->when($queryCond->end_time, function ($query) use ($queryCond) {
                $query->where('patient_info.AAB01', '<=', $queryCond->end_time);
            });
        if ($queryCond->status == 2) {
            $query->where('patient_info.in_hospital', 1)->where('patient_info.AAC01', '=', '');
        } else if ($queryCond->status == 3) {
            $query->where('patient_info.AAC01', '>', date("Y-m-d 00:00:00"));
        }else if ($queryCond->status == 4) {
            $query->where('patient_info.in_hospital', 2);
        }  else {
            $query = $query->where(function ($query) {
                //$query->where('patient_info.in_hospital', 1)->where('patient_info.AAC01', '=', '');或者aac01大于当天00:00:00
                $query->where('patient_info.in_hospital', 1)->where('patient_info.AAC01', '=', '')->orWhere('patient_info.AAC01', '>', date("Y-m-d 00:00:00"));
                //$query->where('patient_info.in_hospital', 1)->orWhere('patient_info.AAC01', '>', date("Y-m-d 00:00:00"));
            });
        }
        $count = $query->count();
        return $count;
    }

    /**
     * 获取缺陷病案数
     *
     * @param $queryCond
     * @return int
     */
    public static function getDefectCaseTotal($queryCond)
    {
        $query = PatientInfo::query()
            ->leftJoin('ZY_BRRY as brry', 'patient_info.MED_REC_ID', '=', 'brry.ZYH')
            ->where("patient_info.score", "<", 100)
            ->when($queryCond->AAA28, function ($query) use ($queryCond) {
                $query->where('brry.AAA28', $queryCond->AAA28);
            })
            ->when($queryCond->brks, function ($query) use ($queryCond) {
                $query->whereIn('brry.BRKS', is_array($queryCond->brks) ? $queryCond->brks : [$queryCond->brks]);
            })
            ->when(is_array($queryCond->dep_ids) && !empty($queryCond->dep_ids), function ($query) use ($queryCond) {
                $query->whereIn('brry.BRKS', $queryCond->dep_ids);
            })
            ->when($queryCond->start_time, function ($query) use ($queryCond) {
                $query->where('patient_info.AAB01', '>=', $queryCond->start_time);
            })
            ->when($queryCond->end_time, function ($query) use ($queryCond) {
                $query->where('patient_info.AAB01', '<=', $queryCond->end_time);
            });
        if ($queryCond->status == 2) {
            $query->where('patient_info.in_hospital', 1);
        } else if ($queryCond->status == 3) {
            $query->where('patient_info.AAC01', '>', date("Y-m-d 00:00:00"));
        } else if ($queryCond->status == 4) {
            $query->where('patient_info.in_hospital', 2);
        } else {
            $query = $query->where(function ($query) {
                $query->where('patient_info.in_hospital', 1)->orWhere('patient_info.AAC01', '>', date("Y-m-d 00:00:00"));
            });
        }
        $count = $query->count();
        return $count;
    }


    /**
     * 获取科室的病例数量
     *
     * @param $queryCond
     * @return int
     */
    public static function getDepartmentCases($queryCond)
    {

        $res = CaseQualityV2::query()
            ->when(is_array($queryCond->dep_id) && !empty($queryCond->dep_id), function ($query) use ($queryCond) {
                $query->whereHas('brry', function ($db) use ($queryCond) {
                    $db->whereIn('BRKS', is_array($queryCond->dep_id) ? $queryCond->dep_id : [$queryCond->dep_id]);
                });
            })
            ->where('created_at', '>', $queryCond->start_time . ' 00:00:00')
            ->where('created_at', '<', $queryCond->end_time . ' 23:59:59')
            ->get(['JZHM'])->toArray();
        $jzhm = array_column($res, 'JZHM');

        $piService = new ElasticsearchService('patient_info');
        $must = [
            'terms' => [
                "MED_REC_ID" => $jzhm
            ]
        ];
        $aggs = [
            'aac11n' => [
                'terms' => [
                    "field" => 'AAC11N',
                    "size" => 10,
                ]
            ]
        ];
        $params = $piService->clearMust()->queryByMust($must)->aggs($aggs)->paginate(1, 0)->getParams();
        $mzRes = app('es')->search($params);
        $mzRes = $piService->getDataByEs($mzRes);
        if (empty($mzRes[2]) || empty($mzRes[2]['aac11n']) || empty($mzRes[2]['aac11n']['buckets'])) {
            return [];
        }

        return $mzRes[2]['aac11n']['buckets'];
    }

    /**
     * 获取科室的缺陷病案数
     *
     * @param $queryCond
     * @return int
     */
    public static function getDepartmentDefectCases($queryCond, $limit = 0)
    {
        $res = CaseQualityV2::query()
            ->when(is_array($queryCond->dep_id) && !empty($queryCond->dep_id), function ($query) use ($queryCond) {
                $query->whereHas('brry', function ($db) use ($queryCond) {
                    $db->whereIn('BRKS', is_array($queryCond->dep_id) ? $queryCond->dep_id : [$queryCond->dep_id]);
                });
            })
            ->where('created_at', '>', $queryCond->start_time . ' 00:00:00')
            ->where('created_at', '<', $queryCond->end_time . ' 23:59:59')
            ->get(['JZHM'])->toArray();
        $jzhm = array_column($res, 'JZHM');

        $piService = new ElasticsearchService('patient_info');
        $must = [
            [
                'terms' => [
                    "MED_REC_ID" => $jzhm
                ]
            ],
            [
                'term' => [
                    "is_defect_v2" => 1
                ]
            ]
        ];
        $aggs = [
            'aac11n' => [
                'terms' => [
                    "field" => 'AAC11N',
                    "size" => 2000,
                ]
            ]
        ];
        $params = $piService->clearMust()->queryByMustBatch($must)->paginate(1, 0)->aggs($aggs)->getParams();
        $mzRes = app('es')->search($params);
        $mzRes = $piService->getDataByEs($mzRes);
        if (empty($mzRes[2]) || empty($mzRes[2]['aac11n']) || empty($mzRes[2]['aac11n']['buckets'])) {
            return [];
        }
        return $mzRes[2]['aac11n']['buckets'];
        // 获取病例数量
        //        $sql = 'SELECT AAC11N,count(1) as nums from patient_info as pi inner join (select t2.JZHM,t2.is_defect from (SELECT MAX(BLBH) as BLBH FROM `EMR_BL_BL01` where is_defect=1 GROUP BY JZHM) as t1 left join EMR_BL_BL01 as t2 on t1.BLBH=t2.BLBH) as t3 on pi.MED_REC_ID=t3.JZHM';
        //        $whereCase = ' where pi.AAC01 > "' . $queryCond->start_time . '" and pi.AAC01 < "' . $queryCond->end_time . ' 23:59:59"';
        //        $others = ' group by AAC11N  order by nums asc';
        //        if ($limit > 0) {
        //            $others .= ' LIMIT ' . $limit;
        //        }
        //        $cases = DB::select($sql . $whereCase . $others);
        //
        //        DebugItemsService::getInstance()->putDebugItem('科室的缺陷病案数', $sql . $whereCase . $others);
        //
        //        return $cases;
    }
}
