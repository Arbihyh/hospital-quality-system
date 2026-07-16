<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\Department;
use App\Model\PatientInfo;
use App\Model\PatientHospitalInfo;
use App\Services\CsvService;
use App\Services\ElasticsearchService;
use App\Services\ToolsService;
use App\Services\YzbService;
use Illuminate\Http\Request;

class YzController extends Controller
{
    public function getYzSerachWhere()
    {
        $yzService = new YzbService();
        $data = $yzService->getYzSerachSelectData();

        return ToolsService::returnData(200, $data, $msg ?? '');
    }

    /**
     * 医嘱搜索
     * @param Request $request
     * @return array
     */
    public function getYzSerach(Request $request)
    {
        $AAC01_start = $request->post('AAC01_start', '');    // 出院开始时间
        $AAC01_end = $request->post('AAC01_end', '');        // 出院结束时间
        $AAC04_start = $request->post('AAC04_start', '');    // 住院天数开始
        $AAC04_end = $request->post('AAC04_end', '');        // 住院天数结束
		$AAA04_start = $request->post('AAA04_start', []);    // type 1-年龄 0-天数
        $AAA04_end = $request->post('AAA04_end', []);        // type 1-年龄 0-天数
        $AAA29_start = $request->post('AAA29_start', '');    // 住院次数开始
        $AAA29_end = $request->post('AAA29_end', '');        // 住院次数结束
        $KZSJ_start = $request->post('KZSJ_start', '');      // 开嘱开始时间
        $KZSJ_end = $request->post('KZSJ_end', '');          // 开嘱开始时间
        $field = $request->post('field', []);
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $page = checkPageStart($page, $pageSize);

        // 查询条件处理
        $must = [];
        $should = [];
        $notMust = [];

        // 出院时间
        if ($AAC01_start && $AAC01_end) {
            $AAC01_start = date('Y-m-d', strtotime($AAC01_start)).' 00:00:00';
            $AAC01_end = date('Y-m-d', strtotime($AAC01_end)).' 23:59:59';
            $must[] = ['range' => ['AAC01' => ['gte' => $AAC01_start, 'lte' => $AAC01_end]]];
        } elseif ($AAC01_start) {
            $AAC01_start = date('Y-m-d', strtotime($AAC01_start)).' 00:00:00';
            $must[] = ['range' => ['AAC01' => ['gte' => $AAC01_start]]];
        } elseif ($AAC01_end) {
            $AAC01_end = date('Y-m-d', strtotime($AAC01_end)).' 23:59:59';
            $must[] = ['range' => ['AAC01' => ['lte' => $AAC01_end]]];
        } else {
            $must[] = ['range' => ['AAC01' => ['gte' => "2010-01-01 00:00:00", 'lte' => "2099-12-31 23:59:59"]]];
        }
        // 住院天数开始
        if ($AAC04_start && $AAC04_end) {
            $must[] = ['range' => ['AAC04' => ['gte' => $AAC04_start, 'lte' => $AAC04_end]]];
        } elseif ($AAC04_start) {
            $must[] = ['range' => ['AAC04' => ['gte' => $AAC04_start]]];
        } elseif ($AAC04_end) {
            $must[] = ['range' => ['AAC04' => ['lte' => $AAC04_end]]];
        }
        // 年龄 or 天数
        if ($AAA04_start && $AAA04_end) {
            $key = $AAA04_start['type']==1 ? 'AAA04' : 'AAA40';
            $must[] = ['range' => [$key => ['gte' => $AAA04_start['value'], 'lte' => $AAA04_end['value']]]];
        } elseif ($AAA04_start) {
            $key = $AAA04_start['type']==1 ? 'AAA04' : 'AAA40';
            $must[] = ['range' => [$key => ['gte' => $AAA04_start['value']]]];
        } elseif ($AAA04_end) {
            $key = $AAA04_end['type']==1 ? 'AAA04' : 'AAA40';
            $must[] = ['range' => [$key => ['lte' => $AAA04_end['value']]]];
        }
        // 住院次数
        if ($AAA29_start && $AAA29_end) {
            $must[] = ['range' => ['AAA29' => ['gte' => $AAA29_start, 'lte' => $AAA29_end]]];
        } elseif ($AAA29_start) {
            $must[] = ['range' => ['AAA29' => ['gte' => $AAA29_start]]];
        } elseif ($AAA29_end) {
            $must[] = ['range' => ['AAA29' => ['lte' => $AAA29_end]]];
        }
        // 开嘱时间
        $yzb2023Must = [];
        $yzbMust = [];
        if ($KZSJ_start && $KZSJ_end) {
            $KZSJ_start = date('Y-m-d', strtotime($KZSJ_start)).' 00:00:00';
            $KZSJ_end = date('Y-m-d', strtotime($KZSJ_end)).' 23:59:59';
            $yzbMust[] = ['range' => ['yzb.KZSJ' => ['gte' => $KZSJ_start, 'lte' => $KZSJ_end]]];
            $yzb2023Must[] = ['range' => ['KZSJ' => ['gte' => $KZSJ_start, 'lte' => $KZSJ_end]]];
        } elseif ($KZSJ_start) {
            $KZSJ_start = date('Y-m-d', strtotime($KZSJ_start)).' 00:00:00';
            $yzbMust[] = ['range' => ['yzb.KZSJ' => ['gte' => $KZSJ_start]]];
            $yzb2023Must[] = ['range' => ['KZSJ' => ['gte' => $KZSJ_start]]];
        } elseif ($KZSJ_end) {
            $KZSJ_end = date('Y-m-d', strtotime($KZSJ_end)).' 23:59:59';
            $yzbMust[] = ['range' => ['yzb.KZSJ' => ['lte' => $KZSJ_end]]];
            $yzb2023Must[] = ['range' => ['KZSJ' => ['lte' => $KZSJ_end]]];
        }

        // 其他诊断条件
        $odMust = []; $odMotMust = []; $odShould = [];
        // 其他手术条件
        $soMust = []; $soMotMust = []; $soShould = [];
        // 医嘱条件
        $yzbNotMust = []; $yzbShould = []; $yzb2023Should = []; $yzb2023NotMust = [];

        // 主信息搜索字段
        $patientKey = ['AAA01','AAA28','AEM01C','AAB06C','MD_ICD10_NAME','MD_ICD10_ID1','MD_RYQK','PMI_ABF01N','PMI_ABF01C','MO_ICD9_NAME','MO_ICD9_ID1','MO_OPE_LEVEL','MO_SSPB','MO_OPE_TYPE','PMI_ABA01N','PMI_ABA01C','MO_RJSS','AEE10','AAD01C','AAC11N','AAB02C'];
        // 其他诊断搜索字段
        $otherDiagnosisKey = ['OD_ICD10_NAME','OD_ICD10_ID1','OD_RYQK'];
        // 其他手术搜索字段
        $secondaryOperationKey = ['SO_ICD9_NAME','SO_ICD9_ID1','SO_OPE_LEVEL','SO_SSPB','SO_OPE_TYPE'];
        // 医嘱搜索字段
        $yzbKey = ['YZMC','KZKS','YZQX','YYSX','XMLB','BRKS'];
        // 主要诊断或其它诊断 + 主要手术或其它手术
        $qtKey = ['MO_SO_ICD9_NAME','MO_SO_ICD9_ID1','MO_SO_OPE_LEVEL','MO_SO_SSPB','MO_SO_OPE_TYPE','MO_SO_RJSS'];
        // 需要特殊处理的查询
        $matchPhrasePrefix = ['MD_ICD10_ID1','OD_ICD10_ID1','PMI_ABF01C','MO_ICD9_ID1','SO_ICD9_ID1','MO_SO_ICD9_ID1','PMI_ABA01C'];

        // 全部搜索条件处理
        $yzbService = new YzbService();
        $serachAllKey = $yzbService->getSerachAllKeyValue();
        foreach ($field as $value) {
            if ($value['value'] != 'all' || empty($serachAllKey[$value['key']])) {
                continue;
            }

            $allKeyList = $serachAllKey[$value['key']];
            foreach ($allKeyList as $val) {
                $field[] = [
                    'key' => $value['key'],
                    'select_type' => 1,
                    'value' => $val,
                ];
            }
        }


        $yzService = new YzbService();
        $searchWhere = $yzService->getYzSerachSelectData();

        // 聚合搜索条件处理
        foreach ($field as $value) {
            if ($value['value'] == 'all' || empty($value['value'])) {
                continue;
            }

            // 判断是不是编码，编码用match_phrase_prefix查询，其它的用match_phrase
            $type = 'match_phrase';
            foreach ($searchWhere as $s) {
                if ($s['key'] == $value['key']) {
                    if ($s['type'] == 'select') {
                        $type = is_array($value['value']) ? 'terms' : 'term';
                    } else {
                        $type = in_array($value['key'], $matchPhrasePrefix) ? 'match_phrase_prefix' : 'match_phrase';
                    }
                    break;
                }
            }

            if (in_array($value['key'], $patientKey)) {
                // 主信息
                if ($value['select_type'] === 1) {
                    $should[] = [$type => [$value['key'] => $value['value']]];
                } else if ($value['select_type'] == 2) {
                    $notMust[] = [$type => [$value['key'] => $value['value']]];
                } else {
                    $must[] = [$type => [$value['key'] => $value['value']]];
                }
            } elseif (in_array($value['key'], $otherDiagnosisKey)) {
                // 其他诊断
                $key = str_replace('OD_','',$value['key']);
                if ($value['select_type'] === 1) {
                    $odShould[] = [$type => ['other_diagnosis.'.$key => $value['value']]];
                } else if ($value['select_type'] == 2) {
                    $odMotMust[] = [$type => ['other_diagnosis.'.$key => $value['value']]];
                } else {
                    $odMust[] = [$type => ['other_diagnosis.'.$key => $value['value']]];
                }
            } elseif (in_array($value['key'], $secondaryOperationKey)) {
                // 其他手术
                $key = str_replace('SO_','',$value['key']);
                if ($value['select_type'] === 1) {
                    $soShould[] = [$type => ['secondary_operation.'.$key => $value['value']]];
                } else if ($value['select_type'] == 2) {
                    $soMotMust[] = [$type => ['secondary_operation.'.$key => $value['value']]];
                } else {
                    $soMust[] = [$type => ['secondary_operation.'.$key => $value['value']]];
                }
            } elseif (in_array($value['key'], $yzbKey)) {
                // 医嘱
                if ($value['select_type'] === 1) {
                    $yzbShould[] = [$type => ['yzb.'.$value['key'] => $value['value']]];
                    $yzb2023Should[] = [$type => [$value['key'] => $value['value']]];
                } else if ($value['select_type'] == 2) {
                    $yzbNotMust[] = [$type => ['yzb.'.$value['key'] => $value['value']]];
                    $yzb2023NotMust[] = [$type => [$value['key'] => $value['value']]];
                } else {
                    $yzbMust[] = [$type => ['yzb.'.$value['key'] => $value['value']]];
                    $yzb2023Must[] = [$type => [$value['key'] => $value['value']]];
                }
            } elseif (in_array($value['key'], $qtKey)) {
                // 主要手术 或 其它手术信息
                // 主要手术
                $key1 = str_replace('SO_','',$value['key']);
                $should[] = [$type => [$key1 => $value['value']]];

                // 其它手术
                $key2 = str_replace('MO_SO_','',$value['key']);
                $mosoShould[] = [$type => ['secondary_operation.'.$key2 => $value['value']]];
            }
        }

        // 其他诊断
        $odSerach = [];
        if ($odMust) {
//            $odSerach['must'] = $odMust;
            $must[] = $this->esWhereHandle('other_diagnosis',['must'=>$odMust]);
        }
        if ($odShould) {
//            $odSerach['should'] = $odShould;
//            $odSerach['minimum_should_match'] = 1;
            $should[] = $this->esWhereHandle('other_diagnosis',['should'=>$odShould]);
        }
        if ($odMotMust) {
//            $odSerach['must_not'] = $odMotMust;
            $notMust[] = $this->esWhereHandle('other_diagnosis',['must_not'=>$odMotMust]);
        }
        if ($odSerach) {
//            $must[] = $this->esWhereHandle('other_diagnosis',$odSerach);
        }

        // 其他手术条件
        $soSerach = [];
        if ($soMust) {
//            $soSerach['must'] = $soMust;
            $must[] = $this->esWhereHandle('secondary_operation',['must'=>$soMust]);
        }
        if ($soShould) {
//            $soSerach['should'] = $soShould;
//            $soSerach['minimum_should_match'] = 1;
            $should[] = $this->esWhereHandle('secondary_operation',['should'=>$soShould]);
        }
        if ($soMotMust) {
//            $soSerach['must_not'] = $soMotMust;
            $notMust[] = $this->esWhereHandle('must_not',['should'=>$soMotMust]);
        }
        if ($soSerach) {
//            $must[] = $this->esWhereHandle('secondary_operation',$soSerach);
        }
        if (!empty($mosoShould)) {
            $should[] = $this->esWhereHandle('secondary_operation',['should'=>$mosoShould]);
        }

        // 医嘱条件
        $yzbSerach = [];
        if ($yzbMust) {
//            $yzbSerach['must'] = $yzbMust;
            $must[] = $this->esWhereHandle('yzb',['must'=>$yzbMust]);
        }
        if ($yzbShould) {
//            $yzbSerach['should'] = $yzbShould;
            $should[] = $this->esWhereHandle('yzb',['should'=>$yzbShould]);;
        }
        if ($yzbNotMust) {
//            $yzbSerach['must_not'] = $yzbNotMust;
            $notMust[] = $this->esWhereHandle('yzb',['must_not'=>$yzbNotMust]);;
        }
        if ($yzbSerach) {
//            $must[] = $this->esWhereHandle('yzb',$yzbSerach);
        }

        // 数据查询
        $returnData = $yzbService->yzSerach($page, $pageSize, $must, $should, $notMust);
        $conut = $returnData[1] ?? 0;

        // &&
        $list = [];
        if (!empty($returnData[0])) {
            foreach ($returnData[0] as $value) {
                $list[] = [
                    'AAA28' => $value['AAA28'] ?? '',
                    'ZYH' => $value['ZYH'] ?? '',
                    'AAB01' => $value['AAB01'] ?? '',
                    'AAC01' => $value['AAC01'] ?? '',
                    'AAC11N' => $value['AAC11N'] ?? '',
                    'AAD01C' => $value['AAD01C'] ?? '',
                    'AAB02C' => $value['AAB02C'] ?? '',
                    'AAC02C' => $value['AAC02C'] ?? '',
                    'YZMC' => '',
                    'YCJL' => '',
                    'SYPC' => '',
                    'BRKS' => '',
                    'KZKS' => '',
                    'YZQX' => '',
                ];
            }
        }

        if ($list) {
            $yzService = new ElasticsearchService('yzb_2023');

            // 获取科室信息
            $depList = Department::query()->pluck('dep_name','dep_id')->toArray();

            // 批量获取所有患者信息，通过AAC02C字段获取科室信息
            $zyhList = array_filter(array_unique(array_column($list, 'ZYH')));
            $patientInfoMap = [];
            $query = PatientInfo::query()
                ->whereIn('patient_info.MED_REC_ID', $zyhList);
            $patientInfoList = $query->get(['patient_info.MED_REC_ID', 'patient_info.AAC02C'])
                ->toArray();
            // 建立映射关系，优先使用ZYH，其次使用AAA28
            foreach ($patientInfoList as $patient) {
                $patientInfoMap[$patient['MED_REC_ID']] = $patient['AAC02C'] ?? null;
            }

            foreach ($list as $key => &$value) {
                $yzb2023MustCopy = $yzb2023Must;
                $yzb2023MustCopy[] = ['term' => ['ZYH' => $value['ZYH']]];
                if (!$yzb2023Should) {
                    $params = $yzService->clearMust()
                        ->queryByMustBatch($yzb2023MustCopy)
                        ->queryByMustNotBatch($yzb2023NotMust)
                        ->paginate(1, 1000)
                        ->trackTotalHits()
                        ->getParams();
                } else {
                    $params = $yzService->clearMust()
                        ->queryByMustBatch($yzb2023MustCopy)
                        ->queryByShouldBatch($yzb2023Should)
                        ->queryByMustNotBatch($yzb2023NotMust)
                        ->paginate(1, 1000)
                        ->minimumShouldMatch(1)
                        ->trackTotalHits()
                        ->getParams();
                }
                $restful = app('es')->search($params);
                $yzbData = $yzService->getDataByEs($restful);
                if ($yzbData[0]) {
                    $value['YZMC'] = $yzbData[0][0]['YZMC'];
                    $value['YCJL'] = '';//$yzbData[0][0]['YCJL'];
                    $value['SYPC'] = '';//$yzbData[0][0]['SYPC'];
                    // 通过patient_info表获取患者信息，通过AAC02C字段获取科室信息
                    $aac02c = $patientInfoMap[$value['ZYH']] ?? null;
                    if ($aac02c) {
                        $value['BRKS'] = $depList[$aac02c] ?? '';
                    } else {
                        $value['BRKS'] = '';
                    }
                    $value['KZKS'] = $depList[strip_tags($yzbData[0][0]['KZKS'])] ?? '';
                    $value['AAC11N'] = $depList[$value['AAC11N']] ?? '';
                    $value['AAB02C'] = $depList[$value['AAB02C']] ?? '';
                    $value['AAC02C'] = $depList[$value['AAC02C']] ?? '';
                    $value['AAD01C'] = $depList[$value['AAD01C']] ?? '';
                    $value['YZQX'] = $yzbData[0][0]['YZQX']==2 ? '临时医嘱' : '长期医嘱';
                }
            }
        }

        return ToolsService::returnData(200, ['total'=>$conut,'list'=>$list], $msg ?? '');
    }

    /**
     * ES嵌套查询格式处理
     * @param $path
     * @param $data
     * @return array[]
     */
    public function esWhereHandle($path,$data)
    {
        $returnData = [
            'nested' => [
                'path' => $path,
                'query' => [
                    'bool' => $data
                ]
            ]
        ];

        return $returnData;
    }

    /**
     * 医嘱搜索导出
     * @param Request $request
     * @return string|null
     */
    public function yzSerachExport(Request $request)
    {
        $AAC01_start = $request->post('AAC01_start','');    // 出院开始时间
        $AAC01_end = $request->post('AAC01_end','');        // 出院结束时间
        $AAC04_start = $request->post('AAC04_start','');    // 住院天数开始
        $AAC04_end = $request->post('AAC04_end','');        // 住院天数结束
        $AAA04_start = $request->post('AAA04_start',[]);    // type 1-年龄 0-天数
        $AAA04_end = $request->post('AAA04_end',[]);        // type 1-年龄 0-天数
        $AAA29_start = $request->post('AAA29_start','');    // 住院次数开始
        $AAA29_end = $request->post('AAA29_end','');        // 住院次数结束
        $KZSJ_start = $request->post('KZSJ_start','');      // 开嘱开始时间
        $KZSJ_end = $request->post('KZSJ_end','');          // 开嘱开始时间
        $field = $request->post('field',[]);

        // 查询条件处理
        $must = [];
        $should = [];
        $notMust = [];

        // 出院时间
        if ($AAC01_start && $AAC01_end) {
            $AAC01_start = date('Y-m-d', strtotime($AAC01_start)).' 00:00:00';
            $AAC01_end = date('Y-m-d', strtotime($AAC01_end)).' 23:59:59';
            $must[] = ['range' => ['AAC01' => ['gte' => $AAC01_start, 'lte' => $AAC01_end]]];
        } elseif ($AAC01_start) {
            $AAC01_start = date('Y-m-d', strtotime($AAC01_start)).' 00:00:00';
            $must[] = ['range' => ['AAC01' => ['gte' => $AAC01_start]]];
        } elseif ($AAC01_end) {
            $AAC01_end = date('Y-m-d', strtotime($AAC01_end)).' 23:59:59';
            $must[] = ['range' => ['AAC01' => ['lte' => $AAC01_end]]];
        } else {
            $must[] = ['range' => ['AAC01' => ['gte' => "2010-01-01 00:00:00", 'lte' => "2099-12-31 23:59:59"]]];
        }
        // 住院天数开始
        if ($AAC04_start && $AAC04_end) {
            $must[] = ['range' => ['AAC04' => ['gte' => $AAC04_start, 'lte' => $AAC04_end]]];
        } elseif ($AAC04_start) {
            $must[] = ['range' => ['AAC04' => ['gte' => $AAC04_start]]];
        } elseif ($AAC04_end) {
            $must[] = ['range' => ['AAC04' => ['lte' => $AAC04_end]]];
        }
        // 年龄 or 天数
        if ($AAA04_start && $AAA04_end) {
            $key = $AAA04_start['type']==1 ? 'AAA04' : 'AAA40';
            $must[] = ['range' => [$key => ['gte' => $AAA04_start['value'], 'lte' => $AAA04_end['value']]]];
        } elseif ($AAA04_start) {
            $key = $AAA04_start['type']==1 ? 'AAA04' : 'AAA40';
            $must[] = ['range' => [$key => ['gte' => $AAA04_start['value']]]];
        } elseif ($AAA04_end) {
            $key = $AAA04_end['type']==1 ? 'AAA04' : 'AAA40';
            $must[] = ['range' => [$key => ['lte' => $AAA04_end['value']]]];
        }
        // 住院次数
        if ($AAA29_start && $AAA29_end) {
            $must[] = ['range' => ['AAA29' => ['gte' => $AAA29_start, 'lte' => $AAA29_end]]];
        } elseif ($AAA29_start) {
            $must[] = ['range' => ['AAA29' => ['gte' => $AAA29_start]]];
        } elseif ($AAA29_end) {
            $must[] = ['range' => ['AAA29' => ['lte' => $AAA29_end]]];
        }
        // 开嘱时间
        $yzb2023Must = [];
        $yzbMust = [];
        if ($KZSJ_start && $KZSJ_end) {
            $KZSJ_start = date('Y-m-d', strtotime($KZSJ_start)).' 00:00:00';
            $KZSJ_end = date('Y-m-d', strtotime($KZSJ_end)).' 23:59:59';
            $yzbMust[] = ['range' => ['yzb.KZSJ' => ['gte' => $KZSJ_start, 'lte' => $KZSJ_end]]];
            $yzb2023Must[] = ['range' => ['KZSJ' => ['gte' => $KZSJ_start, 'lte' => $KZSJ_end]]];
        } elseif ($KZSJ_start) {
            $KZSJ_start = date('Y-m-d', strtotime($KZSJ_start)).' 00:00:00';
            $yzbMust[] = ['range' => ['yzb.KZSJ' => ['gte' => $KZSJ_start]]];
            $yzb2023Must[] = ['range' => ['KZSJ' => ['gte' => $KZSJ_start]]];
        } elseif ($KZSJ_end) {
            $KZSJ_end = date('Y-m-d', strtotime($KZSJ_end)).' 23:59:59';
            $yzbMust[] = ['range' => ['yzb.KZSJ' => ['lte' => $KZSJ_end]]];
            $yzb2023Must[] = ['range' => ['KZSJ' => ['lte' => $KZSJ_end]]];
        }

        // 其他诊断条件
        $odMust = []; $odMotMust = []; $odShould = [];
        // 其他手术条件
        $soMust = []; $soMotMust = []; $soShould = [];
        // 医嘱条件
        $yzbNotMust = []; $yzbShould = []; $yzb2023Should = []; $yzb2023NotMust = [];

        // 主信息搜索字段
        $patientKey = ['AAA01','AAA28','AEM01C','AAB06C','MD_ICD10_NAME','MD_ICD10_ID1','MD_RYQK','PMI_ABF01N','PMI_ABF01C','MO_ICD9_NAME','MO_ICD9_ID1','MO_OPE_LEVEL','MO_SSPB','MO_OPE_TYPE','PMI_ABA01N','PMI_ABA01C','MO_RJSS'];
        // 其他诊断搜索字段
        $otherDiagnosisKey = ['OD_ICD10_NAME','OD_ICD10_ID1','OD_RYQK'];
        // 其他手术搜索字段
        $secondaryOperationKey = ['SO_ICD9_NAME','SO_ICD9_ID1','SO_OPE_LEVEL','SO_SSPB','SO_OPE_TYPE'];
        // 医嘱搜索字段
        $yzbKey = ['YZMC','KZKS','YZQX','YYSX','XMLB','BRKS'];
        // 主要诊断或其它诊断 + 主要手术或其它手术
        $qtKey = ['MO_SO_ICD9_NAME','MO_SO_ICD9_ID1','MO_SO_OPE_LEVEL','MO_SO_SSPB','MO_SO_OPE_TYPE','MO_SO_RJSS'];
        // 需要特殊处理的查询
        $matchPhrasePrefix = ['MD_ICD10_ID1','OD_ICD10_ID1','PMI_ABF01C','MO_ICD9_ID1','SO_ICD9_ID1','MO_SO_ICD9_ID1','PMI_ABA01C'];

        // 聚合搜索条件处理
        if (!is_array($field)) {
            $field = json_decode($field,true);
        }
        foreach ($field as $value) {
            if ($value['value'] == 'all' || empty($value['value'])) {
                continue;
            }

            // 判断是不是编码，编码用match_phrase_prefix查询，其它的用match_phrase
            $type = in_array($value['key'],$matchPhrasePrefix) ? 'match_phrase_prefix' : 'match_phrase';

            if (in_array($value['key'], $patientKey)) {
                // 主信息
                if ($value['select_type'] === 1) {
                    $should[] = [$type => [$value['key'] => $value['value']]];
                } else if ($value['select_type'] == 2) {
                    $notMust[] = [$type => [$value['key'] => $value['value']]];
                } else {
                    $must[] = [$type => [$value['key'] => $value['value']]];
                }
            } elseif (in_array($value['key'], $otherDiagnosisKey)) {
                // 其他诊断
                $key = str_replace('OD_','',$value['key']);
                if ($value['select_type'] === 1) {
                    $odShould[] = [$type => ['other_diagnosis.'.$key => $value['value']]];
                } else if ($value['select_type'] == 2) {
                    $odMotMust[] = [$type => ['other_diagnosis.'.$key => $value['value']]];
                } else {
                    $odMust[] = [$type => ['other_diagnosis.'.$key => $value['value']]];
                }
            } elseif (in_array($value['key'], $secondaryOperationKey)) {
                // 其他手术
                $key = str_replace('SO_','',$value['key']);
                if ($value['select_type'] === 1) {
                    $soShould[] = [$type => ['secondary_operation.'.$key => $value['value']]];
                } else if ($value['select_type'] == 2) {
                    $soMotMust[] = [$type => ['secondary_operation.'.$key => $value['value']]];
                } else {
                    $soMust[] = [$type => ['secondary_operation.'.$key => $value['value']]];
                }
            } elseif (in_array($value['key'], $yzbKey)) {
                // 医嘱
                if ($value['select_type'] === 1) {
                    $yzbShould[] = ['match_phrase' => ['yzb.'.$value['key'] => $value['value']]];
                    $yzb2023Should[] = ['match_phrase' => [$value['key'] => $value['value']]];
                } else if ($value['select_type'] == 2) {
                    $yzbNotMust[] = ['match_phrase' => ['yzb.'.$value['key'] => $value['value']]];
                    $yzb2023NotMust[] = ['match_phrase' => [$value['key'] => $value['value']]];
                } else {
                    $yzbMust[] = ['match_phrase' => ['yzb.'.$value['key'] => $value['value']]];
                    $yzb2023Must[] = ['match_phrase' => [$value['key'] => $value['value']]];
                }
            } elseif (in_array($value['key'], $qtKey)) {
                // 主要手术 或 其它手术信息
                // 主要手术
                $key1 = str_replace('SO_','',$value['key']);
                $should[] = [$type => [$key1 => $value['value']]];

                // 其它手术
                $key2 = str_replace('MO_SO_','',$value['key']);
                $soShould[] = [$type => ['secondary_operation.'.$key2 => $value['value']]];
            }
        }

        // 其他诊断
        $odSerach = [];
        if ($odMust) {
//            $odSerach['must'] = $odMust;
            $must[] = $this->esWhereHandle('other_diagnosis',['must'=>$odMust]);
        }
        if ($odShould) {
//            $odSerach['should'] = $odShould;
//            $odSerach['minimum_should_match'] = 1;
            $should[] = $this->esWhereHandle('other_diagnosis',['should'=>$odShould]);
        }
        if ($odMotMust) {
//            $odSerach['must_not'] = $odMotMust;
            $notMust[] = $this->esWhereHandle('other_diagnosis',['must_not'=>$odMotMust]);
        }
        if ($odSerach) {
//            $must[] = $this->esWhereHandle('other_diagnosis',$odSerach);
        }

        // 其他手术条件
        $soSerach = [];
        if ($soMust) {
//            $soSerach['must'] = $soMust;
            $must[] = $this->esWhereHandle('secondary_operation',['must'=>$soMust]);
        }
        if ($soShould) {
//            $soSerach['should'] = $soShould;
//            $soSerach['minimum_should_match'] = 1;
            $should[] = $this->esWhereHandle('secondary_operation',['should'=>$soShould]);
        }
        if ($soMotMust) {
//            $soSerach['must_not'] = $soMotMust;
            $notMust[] = $this->esWhereHandle('must_not',['should'=>$soMotMust]);
        }
        if ($soSerach) {
//            $must[] = $this->esWhereHandle('secondary_operation',$soSerach);
        }
        if (!empty($mosoShould)) {
            $should[] = $this->esWhereHandle('secondary_operation',['should'=>$mosoShould]);
        }

        // 医嘱条件
        if ($yzbMust) {
//            $yzbSerach['must'] = $yzbMust;
            $must[] = $this->esWhereHandle('yzb',['must'=>$yzbMust]);
        }
        if ($yzbShould) {
//            $yzbSerach['should'] = $yzbShould;
            $should[] = $this->esWhereHandle('yzb',['should'=>$yzbShould]);;
        }
        if ($yzbNotMust) {
//            $yzbSerach['must_not'] = $yzbNotMust;
            $notMust[] = $this->esWhereHandle('yzb',['must_not'=>$yzbNotMust]);;
        }

        // 数据查询
        $yzbService = new YzbService();
        $returnData = $yzbService->yzSerach(1,10000,$must,$should,$notMust);

        // &&
        $list[] = ['病案号', '住院号', '入院时间', '出院时间', '医嘱名称', '一次计量','使用频次','病人科室','开嘱科室','医嘱期效'];
        if (!empty($returnData[0])) {
            foreach ($returnData[0] as $value) {
                $list[] = [
                    'AAA28' => $value['AAA28'],
                    'ZYH' => $value['ZYH'],
                    'AAB01' => $value['AAB01'],
                    'AAC01' => $value['AAC01'],
                    'YZMC' => '',
                    'YCJL' => '',
                    'SYPC' => '',
                    'BRKS' => '',
                    'KZKS' => '',
                    'YZQX' => '',
                ];
            }
        }

        if ($list && ($yzb2023Must || $yzb2023Should)) {
            $yzService = new ElasticsearchService('yzb_2023');

            // 获取科室信息
            $depList = Department::query()->pluck('dep_name','dep_id')->toArray();

            foreach ($list as $key => &$value) {
                if ($key < 1) {
                    continue;
                }
                $yzb2023MustNew = $yzb2023Must;
                $yzb2023MustNew[] = ['term' => ['ZYH' => $value['ZYH']]];
                if (!$yzb2023Should) {
                    $params = $yzService->clearMust()
                        ->queryByMustBatch($yzb2023MustNew)
                        ->queryByMustNotBatch($yzb2023NotMust)
                        ->paginate(1,1)
                        ->trackTotalHits()
                        ->getParams();
                } else {
                    $params = $yzService->clearMust()
                        ->queryByMustBatch($yzb2023MustNew)
                        ->queryByShouldBatch($yzb2023Should)
                        ->queryByMustNotBatch($yzb2023NotMust)
                        ->paginate(1,1)
                        ->minimumShouldMatch(1)
                        ->trackTotalHits()
                        ->getParams();
                }

                $restful = app('es')->search($params);
                $yzbData = $yzService->getDataByEs($restful);
                if ($yzbData[0]) {
                    $value['YZMC'] = $yzbData[0][0]['YZMC'];
                    $value['YCJL'] = '';//$yzbData[0][0]['YCJL'];
                    $value['SYPC'] = '';//$yzbData[0][0]['SYPC'];
                    $value['BRKS'] = $depList[$yzbData[0][0]['BRKS']] ?? '';
                    $value['KZKS'] = $depList[$yzbData[0][0]['KZKS']] ?? '';
                    $value['YZQX'] = $yzbData[0][0]['YZQX']==2 ? '临时医嘱' : '长期医嘱';
                }
            }
        }

        $csv = new CsvService();
        $csv->filename = $csv->charset('医嘱搜索导出', 'UTF-8');

        return $csv->export($list, false);
    }
    

}
