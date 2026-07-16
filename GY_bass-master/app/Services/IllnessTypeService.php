<?php

namespace App\Services;

use App\Model\IllnessTypeCount;
use App\Model\PatientInfo;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Model\PatientHospitalInfo;

/**
 * 单病种质量
 */
class IllnessTypeService
{

    /**
     * @param array $where
     * @return mixed
     * @throws Exception
     * 单病种质量
     */
    public function getList(array $where = [])
    {
        if (empty($where['data_type'])) {
            throw new Exception("单病种类型不能为空");
        }
        $res = IllnessTypeCount::getList($where);

        return $res;
    }

    /**
     * @throws Exception
     * 定时生成执行相关诊断信息的统计信息
     */
    public static function illness()
    {
        DB::table('illness_type_count')->truncate();
        // 1，急性心肌梗死病种例数，主要诊断是    “急性心肌梗死 ”    的总数
        $illness = [
            1 => ['I21'], // 急性心肌梗死国临2.0中是I21）
            2 => ['I11', 'I13.0', 'I13.2', 'I50'], // 10.2.心力衰竭,（国临2.0中是I11.0、I13.0、I13.2、I50）
            3 => ['J13', 'J14', 'J15', 'J18'],//10.3.肺炎（住院、成人）（国临2.0中是J13、J14、J15、J18）
            4 => ['J13', 'J14', 'J15', 'J18'], // 10.4.肺炎（住院、儿童）（国临2.0中是J13、J14、J15、J18）＜18（不含新生儿及1-12个月婴儿肺炎）
            5 => ['I63'], // 脑梗死，国临2.0中是I63（除外I63.301、I63.302、I63.401、I63.801、I63.802）
            6 => ['81.51', '81.52'],// 10.6.髋关节置换术（国临3.0中是81.51、81.52）
            7 => ['81.54'], // 10.7.膝关节置换术（国临3.0中是81.54）
            8 => ['36.1'], // 10.8.冠状动脉旁路移植术（国临3.0中是36.1）
            9 => ['74.0', '74.1', '74.2'], // 剖宫产（国临3.0中是74.0、74.1、74.2）
            10 => ['J44.0', 'J44.1', 'J44.9'], //慢性阻塞性肺疾病（国临2.0中是J44.0、J44.1、J44.9）
        ];

        $countData = [];
        foreach ($illness as $illnessK => $illnessV) {
            $thisYear = date("Y");
            for ($i = 2020; $i <= $thisYear; $i++) {
                for ($j = 1; $j <= 12; $j++) {
                    // 组装新数据
                    $newData = [
                        'data_type' => $illnessK,
                        'year' => $i,
                        'month' => $j,
                    ];
                    $where['poi.ZB02'] = $i; // 数据年份
                    $where['poi.ZB03'] = $j; // 数据月份
                    $where['phi.ABC01C'] = ['operator' => 'like', 'data' => $illnessV]; // 出院时主要诊断编码

                    // 患者年龄
                    if ($illnessK == 3) {
                        $where['patient_info.AAA04'] = ['operator' => '>=', 'data' => '18'];
                    }
                    if ($illnessK == 4) {
                        $where['patient_info.AAA04'] = ['operator' => '<', 'data' => '18'];
                    }

                    // 初始化所有数据表的查询对象
                    $obj = PatientInfo::query()
                        ->Join("patient_hospital_info as phi", "patient_info.MED_REC_ID", "=", "phi.AAA28")
                        ->Join("patient_other_info as poi", "patient_info.MED_REC_ID", "=", "poi.AAA28");

                    // 脑梗死，国临2.0中是I63（除外I63.301、I63.302、I63.401、I63.801、I63.802）
                    if ($illnessK == 5) {
                        $obj = $obj->whereNotIn('phi.ABC01C', ['I63.301', 'I63.302', 'I63.401', 'I63.801', 'I63.802']);
                    }
                    // 病种例数,例如：急性心肌梗死病种例数
                    $total = self::filter($obj, $where)->count();
                    if (empty($total)) {
                        $newData['total'] = 0;
                        $newData['avg_zy_days'] = 0;
                        $newData['avg_cost'] = 0;
                        $newData['dead_radio'] = 0;
                        $countData[] = $newData;
                        continue;
                    }
                    $newData['total'] = $total;

                    // 平均住院日
                    // patient_info.AAC01出院时间（时）patient_hospital_info.AAB01入院时间（时）
                    $zyTime = 0;
                    $zyTimeQuery = self::filter($obj, $where)->selectRaw('sum(UNIX_TIMESTAMP(patient_info.AAC01)-UNIX_TIMESTAMP(phi.AAB01)) as diff')->get()->toArray();
                    if ($zyTimeQuery) {
                        $zyTime = $zyTimeQuery[0]['diff'];
                    }
                    if (!$total || !$zyTime) {
                        $avgZyDays = 0;
                    } else {
                        $avgZyDays = bcdiv((string)$zyTime, (string)($total * 24), 4); // 住院时间 / 住院人数
                    }
                    $newData['avg_zy_days'] = $avgZyDays;

                    // 次均费用
                    $costTotal = 0;
                    $costQuery = self::filter($obj, $where)->selectRaw('sum(patient_info.ADA01) as cost_total')->get()->toArray();
                    if ($costQuery) {
                        $costTotal = $costQuery[0]['cost_total'];
                    }
                    if (!$total || !$costTotal) {
                        $avgCost = 0;
                    } else {
                        $avgCost = bcdiv((string)$costTotal, (string)$total, 4); // 当月总费用 / 住院人数
                    }
                    $newData['avg_cost'] = $avgCost;

                    // 病死率,离院方式代码=5代表死亡
                    $deadTotal = 0;
                    $deadTotal = self::filter($obj, array_merge($where, ['patient_info.AEM01C' => 5]))->count();
                    if (!$total || !$deadTotal) {
                        $deadRadio = 0;
                    } else {
                        $deadRadio = bcdiv((string)$deadTotal, (string)$total, 4);
                    }
                    $newData['dead_radio'] = $deadRadio;

                    $countData[] = $newData;

                }
            }
        }
        if (empty($countData)) {
            throw new Exception('数据插入失败，没有要插入的数据');
        }
        $countDataRet = array_chunk($countData, 100);
        Db::beginTransaction();
        try {
            foreach ($countDataRet as $item) {
                IllnessTypeCount::add($item);
            }
            Db::commit();
        } catch (\Throwable $ex) {
            Db::rollBack();
            throw new Exception('数据插入失败，' . $ex->getMessage());
        }
    }

    /**
     * @param $obj
     * @param array $where
     * @return mixed
     * 数据过滤
     */
    private static function filter($obj, $where = [])
    {
        if ($where) {
            foreach ($where as $key => $val) {
                if (isset($val['data']) && is_array($val['data']) && count($val['data']) > 1 && $val['operator'] != 'like') {
                    $obj = $obj->whereIn($key, $val['data']);
                } else {
                    $sqlVal = $val['data'] ?: $val;
                    if ($val['operator'] == 'like') {
                        if (is_array($sqlVal)) {
                            $likeRawSql = '(';
                            foreach ($sqlVal as $sk => $sv) {
                                if (count($sqlVal) == $sk + 1) {
                                    $likeRawSql .= $key . ' like "' . $sv . '%"';
                                } else {
                                    $likeRawSql .= $key . ' like "' . $sv . '%" or ';
                                }
                            }
                            $likeRawSql .= ')';
                            $obj = $obj->whereRaw($likeRawSql);
                        } else {
                            $obj = $obj->where($key, 'like', $sqlVal . '%');
                        }

                    } elseif (!empty($val['operator'])) {
                        $obj = $obj->where($key, $val['operator'], $sqlVal);
                    } else {
                        $obj = $obj->where($key, '=', $sqlVal);
                    }

                }
            }
        }
        return $obj;
    }
}
