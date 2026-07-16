<?php

namespace App\Services;

use App\Model\DiseaseDiagnosisCode;
use App\Model\CaseQuality;
use App\Model\PatientInfo;
use App\Model\V_JMGS_TESTRESULT;
use App\Model\V_JMGS_YMresult;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Model\Pacs;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;

/**
 * 病例分析
 */
class PacsService
{
    const ID = 'ZZ18953';

    public static function getPacsDetail($JZLSH='', $ExamType='', $AAB01, $AAC0107)
    {


        $data = PACS::query()->whereRaw("JZLSH='$JZLSH' and ExamType='$ExamType'  and JYSJ>'$AAB01' and JYSJ<'$AAC0107'")->get()->toArray();
        if (!$data) {
            return [];
        }
        return $data;
    }

    public static function getPacsPlatform($PatientID = 0)
    {

        $data = PACS::query()->where('PatientID', '=', $PatientID)->get()->toArray();


        if (!$data) {
            return [];
        } else {
			$type = array();
			foreach($data as $key=>$val) {
				$type[] = array('name'=>$val['JCMC'], 'ExamType'=>$val['ExamType'], 'id'=>$val['StudyUid']);
			}
        }

        return $type;
    }

    /**
     * 获取报告单相关数据（使用 ES 查询）
     * @param $type
     * @param $ZYH
     * @param $AAA28
     * @param $AAB01
     * @param $AAC01
     * @return array
     */
    public function getPacsData($type,$ZYH,$AAA28='',$AAB01='',$AAC01='')
    {
        if (!$AAA28 || !$AAB01 || !$AAC01) {
            $patientInfo = PatientInfo::query()
                ->where('MED_REC_ID','=',$ZYH)
                ->first(['AAA28','AAB01','AAC01']);
            if (!$patientInfo) {
                return [];
            }

            $AAA28 = $patientInfo->AAA28;
            $AAB01 = $patientInfo->AAB01;
            $AAC01 = $patientInfo->AAC01;
        }

        if ($type == 5) {
            // 获取检验报告单
            return $this->getInspectPacsData($ZYH,$AAB01,$AAC01);
        }

        $typeData = [
            '1' => ['07','08'], //病理诊断报告
            '2' => ['06'],      //超声诊断报告
            '3' => ['01','02','03','04','05','09','11'],    //影像诊断报告单
            '4' => ['10'],      //心电图诊断报告
            '6' => ['08'],      //内窥镜检查报告
        ];
        if (empty($typeData[$type])) {
            return [];
        }

        // 优先使用 Elasticsearch 查询（性能更好）
        try {
            $should = [];
            foreach ($typeData[$type] as $value) {
                $should[] = ['term' => ['ExamType' => $value]];
            }

            $pacsService = new ElasticsearchService('pacs');
            $must = [
                ["term" => ["ZYH" => $ZYH]]
            ];
            $params = $pacsService->clearMust()
                ->queryByMustBatch($must)
                ->queryByShouldBatch($should)
                ->minimumShouldMatch()
                ->orderBy('KDSJ','asc')
                ->paginate(1,1000)
                ->getParams();
            $restful = app('es')->search($params);
            $pacsData = $pacsService->getDataByEs($restful);
            $data = !empty($pacsData[0]) ? $pacsData[0] : [];
        } catch (\Exception $e) {
            // ES 查询失败，降级使用 MySQL 查询
            Log::warning('Pacs ES查询失败，降级使用MySQL: ' . $e->getMessage());
            $data = PACS::query()
                ->where('ZYH','=',$AAA28)
                ->whereIn('ExamType', $typeData[$type])
                //->whereBetween('KDSJ',[$AAB01,$AAC01])
                ->orderBy('KDSJ','asc')
                ->limit(1000)
                ->get()->toArray();
        }

        if (empty($data)) {
            return [];
        }

        // 病例图文、超声诊断、影像诊断、心电图诊断报告单数据
        return $this->getPacsTypeData($data,$type);
    }

    /**
     * 获取检查报告单数据（使用 ES 查询）
     * @param $ZYH
     * @param $AAB01
     * @param $AAC01
     * @return array
     */
    protected function getInspectPacsData($ZYH,$AAB01,$AAC01)
    {
        $data = [];

        // 使用 Elasticsearch 查询模本1（药敏结果）
        try {
            $vjyService = new ElasticsearchService('v_jmgs_ymresult_2023');
            $must = [
                ["term" => ["ZYH" => $ZYH]],
                ['range' => ['BGSJ' => ['gte' => $AAB01, 'lte' => $AAC01]]]
            ];
            $params = $vjyService->clearMust()
                ->queryByMustBatch($must)
                ->paginate(1,1000)
                ->getParams();
            $restful = app('es')->search($params);
            $vjyData = $vjyService->getDataByEs($restful);
            $ymResultData = !empty($vjyData[0]) ? $vjyData[0] : [];
        } catch (\Exception $e) {
            // ES 查询失败，降级使用 MySQL
            Log::warning('检验报告模本1 ES查询失败，降级使用MySQL: ' . $e->getMessage());
            $ymResultData = V_JMGS_YMresult::query()
                ->where('ZYH','=', $ZYH)
                ->whereBetween('BGSJ',[$AAB01,$AAC01])
                ->limit(1000)
                ->get()
                ->toArray();
        }
        
        if (!empty($ymResultData)) {
            // 按 TXM 分组
            $groupedByTxm = [];
            foreach ($ymResultData as $item) {
                $txm = $item['TXM'];
                if (!isset($groupedByTxm[$txm])) {
                    $groupedByTxm[$txm] = [];
                }
                $groupedByTxm[$txm][] = $item;
            }

            // 处理每个 TXM 组
            foreach ($groupedByTxm as $txm => $txmData) {
                $data1Jcxm = [];
                $data1 = null;
                
                foreach ($txmData as $txmInfo) {
                    if ($data1 === null) {
                        $data1 = $this->getJcBgdPublicData($ZYH, 1, $txmInfo);
                    }

                    // 检查报告单模本1项目格式
                    $data1Jcxm[] = [
                        'PYJG' => $txmInfo['PYJG'], //培养结果
                        'XJMC' => $txmInfo['XJMC'], //细菌名称
                        'XJSL' => '', //细菌数量
                        'YMMC' => $txmInfo['YMMC'], //药敏名称
                        'YMJG' => $txmInfo['YMJG'], //药敏结果
                        'YMBW' => $txmInfo['YMBW'], //药敏部位
                    ];
                }
                
                if ($data1 !== null) {
                    $data1['JCXM'] = $data1Jcxm;
                    $data[] = $data1;
                }
            }
        }

        // 使用 Elasticsearch 查询模本2（检验结果）
        try {
            $vjtService = new ElasticsearchService('v_jmgs_testresult_2023');
            $must = [
                ["term" => ["ZYH" => $ZYH]],
                ['range' => ['BGSJ' => ['gte' => $AAB01, 'lte' => $AAC01]]]
            ];
            $params = $vjtService->clearMust()
                ->queryByMustBatch($must)
                ->paginate(1,1000)
                ->getParams();
            $restful = app('es')->search($params);
            $vjtData = $vjtService->getDataByEs($restful);
            $testResultData = !empty($vjtData[0]) ? $vjtData[0] : [];
        } catch (\Exception $e) {
            // ES 查询失败，降级使用 MySQL
            Log::warning('检验报告模本2 ES查询失败，降级使用MySQL: ' . $e->getMessage());
            $testResultData = V_JMGS_TESTRESULT::query()
                ->where('ZYH','=', $ZYH)
                ->whereBetween('BGSJ',[$AAB01,$AAC01])
                ->limit(1000)
                ->get()
                ->toArray();
        }
        
        if (!empty($testResultData)) {
            // 按 TXM 分组
            $groupedByTxm = [];
            foreach ($testResultData as $item) {
                $txm = $item['TXM'];
                if (!isset($groupedByTxm[$txm])) {
                    $groupedByTxm[$txm] = [];
                }
                $groupedByTxm[$txm][] = $item;
            }

            // 处理每个 TXM 组
            foreach ($groupedByTxm as $txm => $txmData) {
                $data2Jcxm = [];
                $data2 = null;
                
                foreach ($txmData as $txmInfo) {
                    if ($data2 === null) {
                        $data2 = $this->getJcBgdPublicData($ZYH, 2, $txmInfo);
                    }

                    // 检查报告单模本2项目格式
                    $data2Jcxm[] = [
                        'JYXM'  =>  $txmInfo['JYXM'],   //中文名称
                        'YW'    =>  $txmInfo['YW'],     //英文
                        'JG'    =>  $txmInfo['JG'],     //结果
                        'TS'    =>  $txmInfo['TS'],     //提示
                        'CKFW'  =>  $txmInfo['CKFW'],   //参考范围
                        'DW'    =>  $txmInfo['DW'],     //单位
                    ];
                }
                
                if ($data2 !== null) {
                    $data2['JCXM'] = $data2Jcxm;
                    $data[] = $data2;
                }
            }
        }

        return $data;
    }

    protected function getJcBgdPublicData($ZYH,$templateType,$txmInfo)
    {
        return [
            'type'  => 5,
            'template_type' => $templateType,       //模板
            'TXM'   => $txmInfo['TXM'], //条形码
            'NO'    => $txmInfo['NO'],  //NO
            'XM'    => $txmInfo['XM'],  //姓名
            'XB'    => $txmInfo['XB'],  //性别
            'NL'    => $txmInfo['NL'],  //年龄
            'CH'    => $txmInfo['CH'],  //床号
            'YBLX'  => $txmInfo['YBLX'],    //样本类型
            'YBZT'  => $txmInfo['YBZT'],    //样本状态
            'ZYH'   => $ZYH,                //住院号
            'BQ'    => $txmInfo['BQ'],      //病区
            'LCZD'  => $txmInfo['LCZD'],    //临床诊断
            'SJYS'  => $txmInfo['SJYS'],    //送检医生
            'JYY'   => $txmInfo['JYY'],     //检验员
            'SHY'   => $txmInfo['SHY'],     //审核员
            'CJSJ'  => $txmInfo['CJSJ'],    //采集时间
            'JSSJ'  => $txmInfo['JSSJ'],    //接收时间
            'BGSJ'  => $txmInfo['BGSJ'],    //报告时间
        ];
    }

    /**
     * 获取病例图文、超声诊断、影像真的、心电图真的报告单数据
     * @param $data
     * @param $type
     * @return array
     */
    protected function getPacsTypeData($data,$type)
    {
        $returnData = [];
        if ($type == 1) {
            // 病历图文报告单
            foreach ($data as $key => $val) {
                $YXBX = explode("\r\n", trim($val['YXBX']));
                $JCBW = explode("\r\n", trim($val['JCBW']));
                $JCBW[] = $val['JCMC'];
                $YXZD = explode("\r\n", trim($val['YXZD']));
                $returnData[$key] = [
                    'type'      => $type,
                    'StudyUid'  => $val['StudyUid'],    //病理号
                    'BRXM'      => $val['BRXM'],    //姓名
                    'BRXB'      => $val['BRXB'],    //性别
                    'BRNL'      => '',              //年龄
                    'ZYH'       => $val['ZYH'],     //住院号
                    'SJYY'      => '',              //送检医院
                    'JCKSMC'    => $val['JCKSMC'],  //科别
                    'JYSJ'      => $val['JYSJ'],    //送检日期
                    'LCZD'      => '',              //临床诊断
                    'SJYS'      => '',              //送检医生
                    'JCBW'      => $JCBW,           //大体描述
                    'YXBX'      => $YXBX,           //镜下所见
                    'YXZD'      => $YXZD,           //病理诊断
                    'JCYS'      => $val['JCYS'],    //诊断医生
                    'SHRXM'     => $val['SHRXM'],   //复诊医生
                    'BGRQ'      => $val['BGRQ'],    //报告日期
                    'BGSJ'      => $val['BGSJ']     //报告时间
                ];
            }
        } elseif($type == 2) {
            // 超声诊断报告
            foreach ($data as $key => $val) {
                $YXBX = explode("\r\n", trim($val['YXBX']));
                $YXZD = explode("\r\n", trim($val['YXZD']));
                $returnData[$key] = [
                    'type'      => $type,
                    'StudyUid'  => $val['StudyUid'],//编号
//                    'JZLSH'     => $val['JZLSH'],   //病人号
                    'ZYH'       => $val['ZYH'],     //病人号
                    'BRXM'      => $val['BRXM'],    //姓名
                    'BRXB'      => $val['BRXB'],    //性别
                    'BRNL'      => '',              //年龄
                    'JCKSMC'    => $val['JCKSMC'],  //科别
                    'YXBX'      => $YXBX,           //超声所见
                    'YXZD'      => $YXZD,           //超声提示
                    'JCYS'      => $val['JCYS'],    //检查医生
                    'SHRXM'     => $val['SHRXM'],   //审核医生
                    'LRY'       => '',              //录入员
                    'HZYS'      => '',              //会诊医师
                    'JYSJ'      => $val['JYSJ'],    //检查时间
                    'BGRQ'      => $val['BGRQ'],    //报告日期
                    'BGSJ'      => $val['BGSJ']     //报告时间
                ];
            }
        } elseif ($type == 3) {
            // 影像诊断报告单
            foreach ($data as $key => $val) {
                $YXBX = explode("\r\n", trim($val['YXBX']));
                $YXZD = explode("\r\n", trim($val['YXZD']));
                $returnData[$key] = [
                    'type'      => $type,
                    'StudyUid'  => $val['StudyUid'],//检查号
                    'JYSJ'      => $val['JYSJ'],    //检查时间
                    'BGRQ'      => $val['BGRQ'],    //报告日期
                    'BRXM'      => $val['BRXM'],    //姓名
                    'BRXB'      => $val['BRXB'],    //性别
                    'BRNL'      => '',              //年龄
                    'PatientID' => $val['PatientID'], //影像号
                    'KESHI'     => '',              //科室
                    'ZYH'       => $val['ZYH'],     //住院号
                    'CH'        => '',              //床号
                    'JZLSH'     => $val['JZLSH'],   //就诊流水号
                    'JCMC'      => $val['JCMC'],    //检查项目
                    'YXBX'      => $YXBX,           //影像学表现
                    'YXZD'      => $YXZD,           //影像学诊断
                    'JCYS'      => $val['JCYS'],    //报告医生
                    'SHRXM'     => $val['SHRXM'],   //审核医生
                    'BGSJ'      => $val['BGSJ']     //报告时间
                ];
            }
        } elseif ($type == 4) {
            //心电图诊断报告
            foreach ($data as $key => $val) {
                $YXZD = explode("\r\n", trim($val['YXZD']));
                $returnData[$key] = [
                    'type'      => $type,
                    'StudyUid'  => $val['StudyUid'],//检查号
                    'BRXM'      => $val['BRXM'],    //姓名
                    'BRXB'      => $val['BRXB'],    //性别
                    'BRNL'      => '',              //年龄
                    'JCKSMC'    => $val['JCKSMC'],  //科室
                    'ZYH'       => $val['ZYH'],     //住院号
                    'CH'        => '',              //床号
                    'XL'        => '',              //心率
                    'JQ'        => '',              //间期
                    'YXZD'      => $YXZD,           //心电提示
                    'PRJQ'      => '',              //PR间期
                    'DZ'        => '',              //电轴
                    'QRSSX'     => '',              //QRS时限
                    'ZF'        => '',              //振幅
                    'JCYS'      => $val['JCYS'],    //检查医生
                    'JYSJ'      => $val['JYSJ'],    //检查时间
                    'BGRQ'      => $val['BGRQ'],    //报告日期
                    'BGSJ'      => $val['BGSJ']     //报告时间
                ];
            }
        } elseif ($type == 6) {
            // 内窥镜检查报告
            foreach ($data as $key => $val) {
                $JCBW = explode("\r\n", trim($val['JCBW']));
                $YXBX = explode("\r\n", trim($val['YXBX']));
                $YXZD = explode("\r\n", trim($val['YXZD']));
                $returnData[$key] = [
                    'type'      => $type,
                    'StudyUid'  => $val['StudyUid'],//检查号
                    'JYSJ'      => $val['JYSJ'],    //检查时间
                    'BGRQ'      => $val['BGRQ'],    //报告日期
                    'BRXM'      => $val['BRXM'],    //姓名
                    'BRXB'      => $val['BRXB'],    //性别
                    'BRNL'      => '',              //年龄
                    'JCKSMC'    => $val['JCKSMC'],  //科室
                    'ZYH'       => $val['ZYH'],     //住院号
                    'JCMC'      => $val['JCMC'],    //检查名称
                    'JCBW'      => $JCBW,           //检查部位
                    'YXBX'      => $YXBX,           //内镜所见
                    'YXZD'      => $YXZD,           //内镜诊断
                    'JCYS'      => $val['JCYS'],    //报告医生
                    'SHRXM'     => $val['SHRXM'],   //审核医生
                    'BGSJ'      => $val['BGSJ']     //报告时间
                ];
            }
        }

        if (!empty(request()->post('is_tm'))) {
            foreach ($returnData as $k => &$v) {
                if(!empty($v['BRXM'])){
                    $v['BRXM'] = desensitize($v['BRXM'], 1, 0, '*');
                }
                if(!empty($v['XM'])){
                    $v['XM'] = desensitize($v['XM'], 1, 0, '*');
                }
            }
        }
        return $returnData;
    }

}

