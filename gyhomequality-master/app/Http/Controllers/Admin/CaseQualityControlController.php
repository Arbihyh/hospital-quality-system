<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\BLLB292;
use App\Model\CaseQuality;
use App\Model\CaseRule;
use App\Model\Department;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BL01_NEW;
use App\Model\EMR_BL_BLSY;
use App\Model\Error;
use App\Model\ErrorRule;
use App\Model\PACS;
use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Model\PatientInfoTargetTemporary;
use App\Model\PatientScore;
use App\Model\Staff;
use App\Model\Yzb;
use App\Model\ZY_HCMX;
use App\Services\CaseService;
use App\Services\ElasticsearchService;
use App\Services\MedicalRecordService;
use App\Services\QualityService;
use App\Services\TargetService;
use App\Services\ToolsService;
use Illuminate\Http\Request;

class CaseQualityControlController extends Controller
{

    /**
     * 获取病历质控列表
     * @param Request $request
     * @return array
     */
    public function getBlZkList(Request $request)
    {
        $BLZT = $request->post('BLZT','');      // 病历状态
        $level = $request->post('level','');    // 整改级别
        $ZKYS = $request->post('ZKYS','');      // 质控医师
        $BLDJ = $request->post('BLDJ','');      // 病历等级
        $AAA28 = $request->post('AAA28','');    // 住院号
        $AAA01 = $request->post('AAA01','');    // 姓名
        $AAC11N = $request->post('AAC11N','');  // 出院科室
        $AAB01_START = $request->post('AAB01_START','');    // 入院开始时间
        $AAB01_END = $request->post('AAB01_END','');        // 入院结束时间
        $AAC01_START = $request->post('AAC01_START','');    // 出院开始时间
        $AAC01_END = $request->post('AAC01_END','');        // 出院结束时间
        $page = $request->post('page',1);
        $pageSize = $request->post('page_size',10);

        $must = [];
        if (!empty($AAA28)) {
            $must[] = ['term' => ['AAA28' => $AAA28]];
        }
        if (!empty($AAA01)) {
            $must[] = ['match_phrase' => ['AAA01' => $AAA01]];
        }
        if (!empty($AAC11N)) {
            $must[] = ['match_phrase' => ['AAC11N' => $AAC11N]];
        }
        // 入院时间
        if ($AAB01_START && $AAB01_END) {
            $AAB01_START = date('Y-m-d H:i:s', $AAB01_START);
            $AAB01_END = date('Y-m-d H:i:s', $AAB01_END);
            $must[] = ['range' => ['AAB01' => ['gte' => $AAB01_START,'lte' => $AAB01_END]]];
        } elseif ($AAB01_START) {
            $AAB01_START = date('Y-m-d H:i:s', $AAB01_START);
            $must[] = ['range' => ['AAB01' => ['gte' => $AAB01_START]]];
        } elseif ($AAB01_END) {
            $AAB01_END = date('Y-m-d H:i:s', $AAB01_END);
            $must[] = ['range' => ['AAB01' => ['lte' => $AAB01_END]]];
        }
        // 出院时间
        if ($AAC01_START && $AAC01_END) {
            $AAC01_START = date('Y-m-d H:i:s', $AAC01_START);
            $AAC01_END = date('Y-m-d H:i:s', $AAC01_END);
            $must[] = ['range' => ['AAC01' => ['gte' => $AAC01_START,'lte' => $AAC01_END]]];
        } elseif ($AAC01_START) {
            $AAC01_START = date('Y-m-d H:i:s', $AAC01_START);
            $must[] = ['range' => ['AAC01' => ['gte' => $AAC01_START]]];
        } elseif ($AAC01_END) {
            $AAC01_END = date('Y-m-d H:i:s', $AAC01_END);
            $must[] = ['range' => ['AAC01' => ['lte' => $AAC01_END]]];
        } else {
            $must[] = ['range' => ['AAC01' => ['gte' => '2021-01-01 00:00:00']]];
        }

        // 病历状态
        if ($BLZT === 1) {
            $must[] = ['term' => ['score' => 100]];
        } elseif ($BLZT === 0) {
            $must[] = ['range' => ['score' => ['lt' => 100]]];
        }

        // 整改级别
        $cqMust = [];
        if (!empty($level)) {
            $ruleIdList = CaseRule::query()->where('level','=',$level)->pluck('id')->toArray();
            if (!empty($ruleIdList)) {
                $cqMust['should'] = [];
                foreach ($ruleIdList as $value) {
                    $cqMust['should'][] = ['term' => ['rule_id' => $value]];
                }
                $cqMust['minimum_should_match'] = 1;
            }
        }
        // 质控医师
        if (!empty($ZKYS)) {
            $cqMust['must'][] = ['match_phrase' => ['case_quality.ZKR' => $ZKYS]];
        }
        if (!empty($cqMust)) {
            $must[] = [
                'nested' => [
                    'path' => 'case_quality',
                    'query' => [
                        'bool' => $cqMust
                    ]
                ]
            ];
        }

        // 病历等级
        if ($BLDJ === 1) {
            $must[] = ['range' => ['score' => ['gte' => 90,'lte' => 100]]];
        } elseif ($BLDJ === 2) {
            $must[] = ['range' => ['score' => ['gte' => 80,'lt' => 90]]];
        } elseif ($BLDJ === 3) {
            $must[] = ['range' => ['score' => ['gte' => 70,'lt' => 80]]];
        }

        // 查询数据
        $blZkService = new ElasticsearchService('bl_zk_2023');
        $params = $blZkService->clearMust()
            ->queryByMustBatch($must)
//            ->orderBy('score','asc')
            ->paginate($page,$pageSize)
            ->trackTotalHits()
            ->getParams();
        $restful = app('es')->search($params);
        $data = $blZkService->getDataByEs($restful);
        $count = !empty($data[1]) ? $data[1] : 0;

        $list = [];
        if (!empty($data[0])) {
            foreach ($data[0] as $value) {
                // 病案首页分数
                $home_minus_points = PatientScore::query()->where('ZYH','=',$value['ZYH'])->value('score');
                $home_minus_points = !empty($home_minus_points) ? $home_minus_points : 100;

                // 事中分数
                $szfs = CaseQuality::query()
                    ->leftJoin('case_rule','case_quality.rule_id','=','case_rule.id')
                    ->where('case_quality.JZHM','=',$value['ZYH'])
                    ->sum('case_rule.score');
                $szfs = !empty($szfs) ? 100-$szfs : 100;

                // 终末分数
                $zmfs = CaseQuality::query()
                    ->leftJoin('case_rule','case_quality.rule_id','=','case_rule.id')
                    ->where('case_quality.JZHM','=',$value['ZYH'])
                    ->sum('case_rule.score');
                $zmfs = !empty($zmfs) ? 100-$zmfs : 100;

//                $BLZT = $value['score']===100 ? '已改' : '未改';
                $BLZT = '已改';
                $list[] = [
                    'ZYH' => $value['ZYH'],
                    'AAA28' => $value['AAA28'],
                    'AAA01' => $value['AAA01'],
                    'CH' => $value['CH'] ?? '',
                    'AAA29' => $value['AAA29'],
                    'AAC11N' => $value['AAC11N'],
                    'JSR' => '',
                    'AAB01' => $value['AAB01'],
                    'AAC01' => $value['AAC01'],
                    'ZKY' => '',
                    'ZGQR' => '',
                    'BLZT' => $BLZT,
                    'sum_minus_points' => $szfs,
                    'zmfs' => $zmfs,
                    'home_minus_points' => $home_minus_points,//$value['home_minus_points'] ?? 0,
                    'ZKKS' => '',
                ];
            }
        }

        return ToolsService::returnAdmin(0, ['list'=>$list,'count'=>$count]);
    }

    /**
     * 获取病历下质控结果列表
     * @param Request $request
     * @return array
     */
    public function getCaseQualityList(Request $request)
    {
        $ZYH = $request->post('ZYH','');
        $page = $request->post('page',1);
        $pageSize = $request->post('page_size',10);

        if (empty($ZYH)) {
            return ToolsService::returnAdmin(1, '',  '参数错误');
        }

        $caseQualityService = new ElasticsearchService('case_quality_2023');
        $must = [
            ['term' => ['JZHM' => $ZYH]]
        ];
        $params = $caseQualityService->clearMust()
            ->queryByMustBatch($must)
            ->paginate($page,$pageSize)
            ->trackTotalHits()
            ->getParams();
        $restful = app('es')->search($params);
        $data = $caseQualityService->getDataByEs($restful);
        $list = !empty($data[0]) ? $data[0] : [];
        $count = !empty($data[1]) ? $data[1] : 0;

        return ToolsService::returnAdmin(0, ['list'=>$list,'count'=>$count]);
    }

    /**
     * 获取病历菜单
     * @param Request $request
     * @return array
     */
    public function getBlMenuList(Request $request)
    {
        $ZYH = $request->post('id','');
        if (empty($ZYH)) {
            return ToolsService::returnAdmin(1, [], '参数错误');
        }

        $esService = new ElasticsearchService('quality2');
        $must = [
            "term" => [
                'MED_REC_ID' => $ZYH
            ]
        ];
        $params = $esService->queryByMust($must)->getParams();
        $res = app('es')->search($params);
        $res = $esService->getDataByEs($res);
        if (empty($res[1])) {
            return ToolsService::returnAdmin(1, [], '参数错误');
        }
        $info = !empty($res[0]) ? $res[0][0] : [];
        $bllb = array_keys($info['EMR_BL_BL01']);

        // 79儿童营养风险评估
        // 104 外周血管活性药物
        $config = [
            ['name' => '出院记录', 'bllb' => 1],
            ['name' => '入院记录', 'bllb' => 292],
            ['name' => '病程记录', 'bllb' => 294],
            ['name' => '手术', 'bllb' => 303],
            ['name' => '报告单', 'bllb' => 2000002], // no
            ['name' => '病历讨论记录', 'bllb' => 43],
            ['name' => '授权同意类', 'bllb' => 329],
            ['name' => '报告结果类', 'bllb' => 67], // no
            ['name' => '影像报告', 'bllb' => 78],// no
            ['name' => '住院病历类', 'bllb' => 14], // no
            ['name' => '准分子中心门急诊病历', 'bllb' => 2000177], // no
            ['name' => '评估评分表类', 'bllb' => 79],
            ['name' => '护理记录', 'bllb' => 2000049], // no
            ['name' => '护理病历', 'bllb' => 2000048], // no
            ['name' => '护理病程', 'bllb' => 2000146], // no
            ['name' => '门急诊病历', 'bllb' => 2000], // no
            ['name' => '门急诊病程', 'bllb' => 2005], // no
            ['name' => '门诊知情同意书', 'bllb' => 2007], // no
            ['name' => '重危报告类', 'bllb' => 2000185], // no
            ['name' => '死亡记录类', 'bllb' => 288],
            ['name' => '急诊留观病历', 'bllb' => 2004], // no
            ['name' => '化疗记录类', 'bllb' => 83], // no
            ['name' => '检查申请类', 'bllb' => 66], // no
            ['name' => '24小时内记录类', 'bllb' => 18],
            ['name' => '医患沟通类', 'bllb' => 34],
            ['name' => '医疗常用表格', 'bllb' => 87],
        ];


        $esService = new ElasticsearchService('bl01_202303');
        $mustBl[] = [
            "term" => [
                'JZHM' => $ZYH
            ]
        ];
        $mustBl[] = [
            "terms" => [
                'BLLB' => [294, 303]
            ]
        ];
        $params = $esService->queryByMustBatch($mustBl)->paginate(1, 10000)->orderBy('ZXSJ', 'asc')->getParams();
        $res = app('es')->search($params);
        $res = $esService->getDataByEs($res);
        $bl01Data = empty($res[0]) ? [] : $res[0];

        $targetService = new TargetService();
        foreach ($config as $k => $v) {
            if (!in_array($v['bllb'], $bllb)) {
                unset($config[$k]);
            }
            if (in_array($v['bllb'], [294, 303])) {
                // 获取病程记录、手术二级菜单
                $bllbList = $targetService->getSurgeryMenu($bl01Data, $v['bllb']);
                if (!empty($bllbList)) {
                    $config[$k]['count'] = count($bllbList);
                    $config[$k]['list'] = $bllbList;
                }
            } elseif ($v['bllb'] == 2000002) {
                // 获取报告单二级菜单
                $pacsMenuList = $targetService->getPacsMenu($info);
                if (!empty($pacsMenuList)) {
                    $config[$k] = ['name' => '报告单', 'bllb' => 2000002, 'list' => $pacsMenuList];
                    $config[$k]['count'] = count($pacsMenuList);
                }
            } elseif(in_array($v['bllb'],[329, 34, 288, 87])) {
                $bllbList = $targetService->getTwoMenu($ZYH,$v['bllb']);
                if (!empty($bllbList)) {
                    $config[$k]['count'] = count($bllbList);
                    $config[$k]['list'] = $bllbList;
                }
            } elseif (!empty($config[$k]['name'])) {
                $config[$k]['count'] = 1;

                if (in_array($v['bllb'],[1,18,292])) {
                    $config[$k]['blbh'] = EMR_BL_BL01::query()
                        ->where('JZHM','=',$ZYH)
                        ->where('BLLB','=',$v['bllb'])
                        ->where('BLZT','!=',9)
                        ->value('BLBH');
                }
            }
        }

        if ($info['YZB']) {
            $config[] = ['name' => '医嘱', 'bllb' => 49, 'count' => 2];
        }

        return ToolsService::returnAdmin(0, $config);
    }

    /**
     * 获取病历详情
     * @param Request $request
     * @return array
     */
    public function getBl01Info(Request $request)
    {
        $BLBH = $request->post('blbh','');

        if (empty($BLBH)) {
            return ToolsService::returnAdmin(1, [], '参数错误');
        }

        $data = EMR_BL_BL01::query()
            ->join('EMR_BL_BLXG', 'EMR_BL_BLXG.BLBH', '=', 'EMR_BL_BL01.BLBH')
            ->where('EMR_BL_BL01.BLBH', '=',$BLBH)
            ->groupBy('EMR_BL_BLXG.BLBH')
            ->orderBy('EMR_BL_BLXG.XGSJ')
            ->first(['EMR_BL_BL01.BLBH','EMR_BL_BL01.JZHM','EMR_BL_BL01.BLMC','BLLB','MBLB','CJSJ','ZXSJ','WCSJ','SXYS','HJNR'])->toArray();
        if ($data) {
            $patientInfo = PatientInfo::query()
                ->where('MED_REC_ID', '=', $data['JZHM'])
                ->first();
            $brxm = !empty($patientInfo) ? $patientInfo->AAA01 : '';

            $data['title'] = $data['BLMC'];
            if ($data['WCSJ'] == '0000-00-00 00:00:00') {
                $data['WCSJ'] = '';
            }
            // 数据脱敏
            $desensitizeData = desensitize($brxm, 1);
            $data['HJNR'] = str_replace($brxm, $desensitizeData, $data['HJNR']);

            // 查询签名医生
            $SYYS = EMR_BL_BLSY::query()->where('BLBH','=',$data['BLBH'])->pluck('SYYS')->toArray();
            $doctorList = '';
            if ($SYYS) {
                $staff = Staff::query()->whereIn('code',$SYYS)->get(['name','ygjb_text'])->toArray();
                if ($staff) {
                    foreach ($staff as $val) {
                        $doctorName[] = $val['name'].'（'.$val['ygjb_text'].'）';
                    }
                    $doctorList = implode('，',$doctorName);
                }
            }
            $data['doctor_name'] = $doctorList;
        }

        return ToolsService::returnAdmin(0, $data);
    }

    /**
     * 病案首页
     * @param Request $request
     * @return array
     */
    public function getHomeData(Request $request)
    {
        $id = $request->post('id');

        if(empty($id)){
            return ToolsService::returnAdmin(1, [], '病案号不可以为空！');
        }
        $data = MedicalRecordService::getData($id);
        if(!$data){
            return ToolsService::returnAdmin(1, [], '没有数据！');
        }

        $data = ToolsService::codeTransformationInfo(['AAA23C','ABF02C','AEB02C','AEI01C','AAA26C','AAA02C','AAA06C','AAA08C','AAA18C','AAB06C','AEM01C','AEM03C','AEG01C','AEG02C','AAA05C','AAB02C','AAC02C'],$data);
        if(count($data['operation'])){
            foreach($data['operation'] as $k=>$v){
                if(null != config('dictionaries.INCISION_GRADE_ID.'.$v['INCISION_GRADE_ID'])){
                    $data['operation'][$k]['INCISION_GRADE_ID'] = config('dictionaries.INCISION_GRADE_ID.'.$v['INCISION_GRADE_ID']);
                }
                if(null != config('dictionaries.HOCUS_WAY_ID.'.$v['HOCUS_WAY_ID'])){
                    $data['operation'][$k]['HOCUS_WAY_ID'] = config('dictionaries.HOCUS_WAY_ID.'.$v['HOCUS_WAY_ID']);
                }
            }
        }
        if(count($data['diagnosis'])){
            foreach($data['diagnosis'] as $k=>$v){
                if(null != config('dictionaries.IN_STATUS.'.$v['RYQK'])){
                    $data['diagnosis'][$k]['RYQK'] = config('dictionaries.IN_STATUS.'.$v['RYQK']);
                }
            }
        }
        if(null != config('dictionaries.ABAS02.'.$data['AAC03'])){
            $data['AAC03'] = config('dictionaries.ABAS02.'.$data['AAC03']);
        }
        $data['RJSS'] = '';
        if(isset($data['operation'][0]['RJSS'])){
            $data['RJSS'] = $data['operation'][0]['RJSS'];
        }
        $data['AAA03'] = substr($data['AAA03'],0,10);
        if(strpos($data['AAB01'],'T')){
            $data['AAB01'] = str_replace('T',' ',$data['AAB01']);
        }
        if(strpos($data['AAC01'],'T')){
            $data['AAC01'] = str_replace('T',' ',$data['AAC01']);
        }
        $data['AEB02C'] = ($data['AEB02C'] ?? '无').','.($data['AEB01'] ?? '');
        if (!empty($data['AEE01_CODE'])){
            $data['AEE01_CODE'] = QualityService::getStaffInfo($data['AEE01_CODE'],'base_code');
        }
        if (!empty($data['AEE02_CODE'])){
            $data['AEE02_CODE'] = QualityService::getStaffInfo($data['AEE02_CODE'],'base_code');
        }
        if (!empty($data['AEE03_CODE'])){
            $data['AEE03_CODE'] = QualityService::getStaffInfo($data['AEE03_CODE'],'base_code');
        }
        if (!empty($data['AEE04_CODE'])){
            $data['AEE04_CODE'] = QualityService::getStaffInfo($data['AEE04_CODE'],'base_code');
        }
        if (!empty($data['ZRHSBM'])){
            $data['ZRHSBM'] = QualityService::getStaffInfo($data['ZRHSBM'],'base_code');
        }

        // 查询医生签名、创建时间、修改时间、完成时间
        $data['CJSJ'] = '';$data['ZXSJ'] = '';$data['WCSJ'] = '';$data['doctor_name'] = '';
        $bl01NewInfo = EMR_BL_BL01_NEW::query()
            ->where('JZHM','=',$id)
            ->where('BLLB','=',2000001)
            ->where('BLZT','!=',9)
            ->first();
        if ($bl01NewInfo) {
            $data['CJSJ'] = $bl01NewInfo->CJSJ;
            $data['ZXSJ'] = $bl01NewInfo->ZXSJ;
            $data['WCSJ'] = $bl01NewInfo->WCSJ;

            $SYYS = EMR_BL_BLSY::query()->where('BLBH','=',$bl01NewInfo->BLBH)->pluck('SYYS')->toArray();
            if ($SYYS) {
                $staffList = Staff::query()->whereIn('code',$SYYS)->get(['name','ygjb_text'])->toArray();
                $nameList = [];
                foreach ($staffList as $staffInfo) {
                    $nameList[] = $staffInfo['name']."（".$staffInfo['ygjb_text']."）";
                }
                $data['doctor_name'] = implode('、', $nameList);
            }
        }

        $data = dataDesensitize($data); // 数据脱敏
        return ToolsService::returnAdmin(0, $data);
    }

    /**
     * 获取格式化病例内容
     * @param Request $request
     * @return array
     */
    public function getCasePlatform(Request $request, CaseService $caseService)
    {
        $id = $request->post('id');
        $bllb = $request->post('bllb', 1);

        if (!$id) {
            return ToolsService::returnAdmin(1, [], '请求的参数有误');
        }

        $res = [];
        try {
            $res = $caseService->getCasePlatform($id, $bllb);
            $code = 0;
        } catch (\Exception $e) {
            $code = 1;
            $msg = $e->getMessage();
        }

        return ToolsService::returnAdmin($code, $res, $msg ?? '');
    }

    /**
     * 获取手术格式化数据
     * @param Request $request
     * @return array
     */
    public function getSurgeryData(Request $request)
    {
        $blbh = $request->post('blbh');
        $bllb = 303;
        if (!$blbh) {
            return ToolsService::returnAdmin(1, [], '请求的参数有误');
        }
        $res = [];
        try {
            $caseService = new CaseService();
            $res = $caseService->getSurgeryData($blbh, $bllb);
            $code = 0;
        } catch (\Exception $e) {
            $code = 1;
            $msg = $e->getMessage();
        }
        return ToolsService::returnAdmin($code, $res, $msg ?? '');
    }

    /**
     * 获取病程格式化数据
     * @param Request $request
     * @return array
     */
    public function getBcData(Request $request)
    {
        $blbh = $request->post('blbh');
        if (!$blbh) {
            return ToolsService::returnAdmin(1, [], '请求的参数有误');
        }
        $res = [];
        try {
            $caseService = new CaseService();
            $res = $caseService->getBcData($blbh, 294);
            $code = 0;
        } catch (\Exception $e) {
            $code = 1;
            $msg = $e->getMessage();
        }
        return ToolsService::returnAdmin($code, $res, $msg ?? '');
    }

    /**
     * 获取报告单相关数据
     * @param Request $request
     * @return array
     */
    public function getPacsData(Request $request)
    {
        $type = $request->post('type');     // 检查类型
        $ZYH = $request->post('zyh');       // 病案号

        if (!$ZYH || !$type) {
            return ToolsService::returnAdmin(1, [], '请求的参数有误');
        }

        $pacsContent = PatientInfoTarget::query()
            ->where('ZYH','=',$ZYH)
            ->value('pacs_content');
        $pacsData = json_decode($pacsContent,true);
        $data = [];
        if (isset($pacsData[$type])) {
            $data = $pacsData[$type];

            // 报告类型处理
            foreach ($data as $key => &$val) {
                $ExamType = '';
                if (!empty($val['ExamType'])) {
                    $ExamType = !empty(Pacs::EXAM_TYPE[$val['ExamType']]) ? Pacs::EXAM_TYPE[$val['ExamType']] : '';
                }
                $val['ExamType'] = $ExamType;
            }
        }

        return ToolsService::returnAdmin(0, $data);
    }

    /**
     * 获取病历数据
     * @param Request $request
     * @return array
     */
    public function getAllCase(Request $request)
    {
        $bllb = $request->post('bllb', 1);
        $MED_REC_ID = $request->post('MED_REC_ID');

        $data = EMR_BL_BL01::query()
            ->join('EMR_BL_BLXG', 'EMR_BL_BLXG.BLBH', '=', 'EMR_BL_BL01.BLBH')
            ->where('JZHM', $MED_REC_ID)
            ->where('BLLB', $bllb)
            ->groupBy('EMR_BL_BLXG.BLBH')
            ->orderBy('EMR_BL_BLXG.XGSJ')
            ->get(['EMR_BL_BL01.BLBH','MBLB','CJSJ','ZXSJ','WCSJ','SXYS','HJNR'])->toArray();

        $patientInfo = PatientInfo::query()
            ->where('MED_REC_ID', '=', $MED_REC_ID)
            ->get()->toArray();
        $brxm = !empty($patientInfo) ? $patientInfo[0]['AAA01'] : '';

        if ($data) {
            $bllbArray = [329=>'授权同意记录',18=>'24小时记录记录',34=>'医患沟通记录',87=>'医疗常用表格'];
            $mblbArray = [21=>'24小时入院死亡记录',20=>'24小时内入院记录',288=>'死亡记录',290=>'死亡记录',291=>'死亡讨论记录'];
            foreach ($data as &$value) {
                if (array_key_exists($value['MBLB'],$mblbArray)) {
                    $value['title'] = $mblbArray[$value['MBLB']];
                } elseif (array_key_exists($bllb,$bllbArray)) {
                    $value['title'] = $bllbArray[$bllb];
                }
                if ($value['WCSJ'] == '0000-00-00 00:00:00') {
                    $value['WCSJ'] = '';
                }
                // 数据脱敏
                if (!empty(request()->post('is_tm')) && $brxm) {
                    $desensitizeData = desensitize($brxm, 1, 0, $re = '*');
                    $value['HJNR'] = str_replace($brxm, $desensitizeData, $value['HJNR']);
                }

                // 查询签名医生
                $SYYS = EMR_BL_BLSY::query()->where('BLBH','=',$value['BLBH'])->pluck('SYYS')->toArray();
                $doctorList = '';
                if ($SYYS) {
                    $staff = Staff::query()->whereIn('code',$SYYS)->get(['name','ygjb_text'])->toArray();
                    if ($staff) {
                        foreach ($staff as $val) {
                            $doctorName[] = $val['name'].'（'.$val['ygjb_text'].'）';
                        }
                        $doctorList = implode('，',$doctorName);
                    }
                }
                $value['doctor_name'] = $doctorList;
            }
        }

        return ToolsService::returnAdmin(0, $data);
    }

    /**
     * 长期医嘱
     * @param Request $request
     * @return array
     */
    public static function long(Request $request)
    {
        $AAA28 = $request->post('AAA28');
        $page = $request->post('page',1);
        $info = self::getInfo($AAA28);
        if (empty($info)) {
            return ToolsService::returnAdmin(1,[]);
        }
        $list = Yzb::query()
            ->where('ZYH',$AAA28)
            ->where('YZQX',1)
            ->orderBy('KZSJ')
            ->get(['YZMC','KZSJ','TZSJ','KZYS','TZYS','XZJDGH','SYPC','YCJL'])->toArray();
        if (!empty($list)) {
            foreach ($list as &$item) {
                $item['KZYS'] = QualityService::getStaffInfo($item['KZYS'],'name');
                $item['TZYS'] = QualityService::getStaffInfo($item['TZYS'],'name');
                $item['XZJDGH'] = QualityService::getStaffInfo($item['XZJDGH'],'name');
                $item['KSDATE'] = date('Y-m-d',strtotime($item['KZSJ']));
                $item['KSTIME'] = date('H:i:s',strtotime($item['KZSJ']));
                $item['TZDATE'] = date('Y-m-d',strtotime($item['TZSJ']));
                $item['TZTIME'] = date('H:i:s',strtotime($item['TZSJ']));
            }
        }

        if (!empty(request()->post('is_tm'))) {
            // 数据脱敏
            $info['AAA01'] = desensitize($info['AAA01'], 1, 0, '*');
        }

        $data = ['list' => $list, 'info' => $info];
        return ToolsService::returnAdmin(0,$data);
    }

    /**
     * 临时医嘱
     * @param Request $request
     * @return array
     */
    public static function temporary(Request $request)
    {
        $AAA28 = $request->post('AAA28');
        $page = $request->post('page',1);
        $limit = $request->post('limit',20);
        $offset = ($page - 1) * $limit;
        $info = self::getInfo($AAA28);
        if (empty($info)) {
            return ToolsService::returnAdmin(0,[]);
        }
        $list = Yzb::query()
            ->where('ZYH',$AAA28)
            ->where('YZQX',2)
            ->orderBy('XZJDSJ')
            ->get(['YZMC','KZSJ','TZSJ','KZYS','XZJDSJ','XZJDGH','SYPC','YCJL'])->toArray();
        if (!empty($list)) {
            foreach ($list as &$item) {
                $item['KZYS'] = QualityService::getStaffInfo($item['KZYS'],'name');
                $item['XZJDGH'] = QualityService::getStaffInfo($item['XZJDGH'],'name');
                $item['DATE'] = date('Y-m-d',strtotime($item['KZSJ']));
                $item['TIME'] = date('H:i:s',strtotime($item['KZSJ']));
            }
        }

        if (!empty(request()->post('is_tm'))) {
            // 数据脱敏
            $info['AAA01'] = desensitize($info['AAA01'], 1, 0, '*');
        }

        // 获取护士分床时间、死亡时间、出院时间
        $info['HCRQ'] = ZY_HCMX::query()->where('ZYH','=',$AAA28)->where('HCLX','=',0)->value('HCRQ');
        // 查询出院时间
        $info['AAC01'] = Yzb::query()->where('ZYH','=',$AAA28)->where('YDYZLB','=',303)->value('XZJDSJ');
        // 查询死亡时间
        $info['SWSJ'] = Yzb::query()->where('ZYH','=',$AAA28)->where('YDYZLB','=',305)->value('XZJDSJ');

        $data = ['list' => $list, 'info' => $info];
        return ToolsService::returnAdmin(0,$data);
    }

    public static function getInfo($AAA28)
    {
        $info = PatientInfo::query()
            ->join('patient_hospital_info','patient_info.MED_REC_ID','=','patient_hospital_info.AAA28')
            ->where('MED_REC_ID',$AAA28)
            ->first(['patient_info.AAA28','patient_info.AAA01','patient_info.AAA02C','patient_info.AAA04','patient_hospital_info.AAB02C','patient_info.AAC01']);
        $info = !empty($info) ? $info->toArray() : [];
        if (isset($info['AAA02C']) && $info['AAA02C'] == 1) {
            $info['AAA02C'] = '男';
        } else {
            $info['AAA02C'] = '女';
        }
        $conf = config('dictionaries.AAB02C');
        $info['AAB02C'] = $conf[$info['AAB02C']] ?? '';
        return $info;
    }

    /**
     * 获取住院质控规则（人工）
     * @param Request $request
     * @return array
     */
    public function getRule(Request $request)
    {
        // 查询人工质控规则
        $ruleData = CaseRule::query()
            ->where('status','=',1)
            ->where('is_ai','=',0)
            ->get(['id','category','title','notice','score','type','level'])->toArray();
        $returnData['rule'] = $ruleData;

        // 查询质控分类
        $category = array_unique(array_column($ruleData,'category'));
        $category = array_filter($category);
        sort($category);
        $returnData['category'] = $category;

        // 查询质控项目
        $title = array_unique(array_column($ruleData,'title'));
        $title = array_filter($title);
        sort($title);
        $returnData['title'] = $title;

        // 质控类型
        $type = array_unique(array_column($ruleData,'type'));
        $type = array_filter($type);
        sort($type);
        $returnData['type'] = $type;

        // 整改级别
        $returnData['level'] = [['id'=>1,'name'=>'强制'],['id'=>2,'name'=>'建议']];

        // 整改级别
        $returnData['zgjb'] = ['终末质控','事中质控'];

        return ToolsService::returnAdmin(0,$returnData);
    }

    /**
     * 获取病历信息
     * @param Request $request
     * @return array
     */
    public function getBlInfo(Request $request)
    {
        $blbh = $request->post('blbh','');
        if (empty($blbh)) {
            return ToolsService::returnAdmin(1,[],'参数错误');
        }

        $bl01Info = EMR_BL_BL01::query()
            ->join('EMR_BL_BLXG', 'EMR_BL_BLXG.BLBH', '=', 'EMR_BL_BL01.BLBH')
            ->where('EMR_BL_BL01.BLBH', '=',$blbh)
            ->groupBy('EMR_BL_BLXG.BLBH')
            ->orderBy('EMR_BL_BLXG.XGSJ')
            ->first(['EMR_BL_BL01.BLBH','EMR_BL_BL01.JZHM','EMR_BL_BL01.BLMC','BLLB','MBLB','CJSJ','ZXSJ','WCSJ','SXYS','BRKS','HJNR']);
        if (!$bl01Info) {
            return ToolsService::returnAdmin(0,[]);
        }

        // 接收人
        $jsr = Staff::query()->where('code','=',$bl01Info->SXYS)->value('name');
        // 接收科室
        $jsks = Department::query()->where('dep_id','=',$bl01Info->BRKS)->value('dep_name');
        // AAA28
        $patientInfo = PatientInfo::query()->where('MED_REC_ID','=',$bl01Info->JZHM)->first();
        $AAA28 = !empty($patientInfo) ? $patientInfo->AAA28 : '';
        $brxm = !empty($patientInfo) ? $patientInfo->AAA01 : '';
        $doctorList = '';
        $HJNR = '';
        if ($bl01Info) {
            $WCSJ = $bl01Info->WCSJ=='0000-00-00 00:00:00' ? '' : $bl01Info->WCSJ;

            // 数据脱敏
            $desensitizeData = desensitize($brxm, 1);
            $HJNR = str_replace($brxm, $desensitizeData, $bl01Info->HJNR);

            // 查询签名医生
            $SYYS = EMR_BL_BLSY::query()->where('BLBH','=',$blbh)->pluck('SYYS')->toArray();

            if ($SYYS) {
                $staff = Staff::query()->whereIn('code',$SYYS)->get(['name','ygjb_text'])->toArray();
                if ($staff) {
                    foreach ($staff as $val) {
                        $doctorName[] = $val['name'].'（'.$val['ygjb_text'].'）';
                    }
                    $doctorList = implode('，',$doctorName);
                }
            }
        }

        $returnData = [
            'BLBH' => $blbh,
            'AAA28' => $AAA28,
            'ZYH' => $bl01Info->JZHM,
            'JSR' => $jsr,
            'JSKS' => $jsks,
            'title' => $bl01Info->BLMC ?? '',
            'CJSJ' => $bl01Info->CJSJ ?? '',
            'ZXSJ' => $bl01Info->ZXSJ ?? '',
            'WCSJ' => $WCSJ,
            'doctor_name' => $doctorList,
            'BLMC' => $bl01Info->BLMC ?? '',
            'HJNR' => $HJNR,
        ];

        return ToolsService::returnAdmin(0,$returnData);
    }

    /**
     * 添加质控结果
     * @param Request $request
     * @return array
     */
    public function addCaseQuality(Request $request)
    {
        $ZYH = $request->post('ZYH','');            // 住院号

        $ZKR = $request->post('ZKR','');            // 质控人
        $ZKKS = $request->post('ZKKS','');          // 质控科室
        $JSR = $request->post('JSR','');            // 接收人
        $JSKS = $request->post('JSKS','');          // 接收科室
        $ZGJB = $request->post('ZGJB','');          // 整改级别
        $ZGQX = $request->post('ZGQX','');          // 整改期限
        $ruleId = $request->post('rule_id','');     // 规则ID
        $category = $request->post('category','');  // 质控分类
        $title = $request->post('title','');        // 质控项目
        $type = $request->post('type','');          // 质控类型
        $notice = $request->post('notice','');      // 错误描述
        $basis = $request->post('basis','');        // 质控内容
        $score = $request->post('score','');        // 扣分
        $level = $request->post('level',2);         // 1-强制 2-建议
        $score = !empty($score) ? $score : 0;

        if (!$ZYH || !$ZKR || !$ZKKS || !$JSR || !$JSKS || !$ZGJB || !$category || !$title || !$notice || !$basis) {
            return ToolsService::returnAdmin(1,[],'参数错误');
        }

        // 人工质控规则
        $type = !empty($type) ? $type : '';
        $saveCaseRuleData = [
            'category' => $category,
            'title' => $title,
            'type' => $type,
            'notice' => $notice,
            'score' => $score,
            'is_ai' => 0,
            'level' => $level,
        ];
        if ($ruleId) {
            // 更新
            CaseRule::query()->where('id','=',$ruleId)->update($saveCaseRuleData);
        } else {
            // 添加
            $ruleId = CaseRule::query()->insertGetId($saveCaseRuleData);
        }

        $caseQualityInfo = CaseQuality::query()
            ->where('rule_id','=',$ruleId)
            ->where('JZHM','=',$ZYH)
            ->first();
        if ($caseQualityInfo) {
            return ToolsService::returnAdmin(1,[],'已质控过该病历，请勿重复质控');
        }

        // 添加质控结果
        $insertData = [
            'rule_id' => $ruleId,
            'code' => 'rule_'.$ruleId,
            'error_field' => $title,
            'JZHM' => $ZYH,
            'basis' => json_encode([[$basis]], 256),
            'is_ai' => 0,
            'ZKR' => $ZKR,
            'ZKKS' => $ZKKS,
            'JSR' => $JSR,
            'JSKS' => $JSKS,
            'ZGJB' => $ZGJB,
        ];
        if (!empty($ZGQX)) {
            $insertData['ZGQX'] = date('Y-m-d', $ZGQX);
        }
        $res = CaseQuality::query()->insert($insertData);
        if ($res) {
            return ToolsService::returnAdmin(0);
        }
        return ToolsService::returnAdmin(1,[],'质控失败');
    }

    /**
     * 获取质控结果
     * @param Request $request
     * @return array
     */
    public function getCaseQuality(Request $request)
    {
        $ZYH = $request->get('ZYH');
        if (!$ZYH) {
            return ToolsService::returnAdmin(1, [], '请求的参数有误');
        }
        $caseQualityData = [];
        try {
            $caseQualityService = new CaseService();
            $caseQualityData = $caseQualityService->getCaseQuality($ZYH);
            $code = 0;
        } catch (\Exception $e) {
            $code = 1;
            $msg = $e->getMessage();
        }
        return ToolsService::returnAdmin($code, $caseQualityData, $msg ?? '');
    }

    /**
     * 质控结果列表（二级列表）
     * @param Request $request
     * @return array|void
     */
    public function caseQualityExamine(Request $request)
    {
        $ZYH = $request->post('ZYH','');

        if (empty($ZYH)) {
            return ToolsService::returnAdmin(1, [], '请求的参数有误');
        }

        $error = Error::query()
            ->where(['ZYH' => $ZYH])
            ->where('status', 0)
            ->select('down', 'desc', 'error_field', 'error_name', 'category', 'error_rule')
            ->get()->toArray();

        if (!empty($error)) {
            $errorRuleData = ErrorRule::query()->where('status','=',0)->get()->toArray();
            $errorRuleData = array_column($errorRuleData, null, 'id');
            foreach ($error as &$errorInfo) {
                $errorInfo['level'] = $errorRuleData[$errorInfo['error_rule']]['level'];
                if ($errorInfo['error_rule'] != 1458) {
                    $errorInfo['desc'] = !empty($errorRuleData[$errorInfo['error_rule']]) ? $errorRuleData[$errorInfo['error_rule']]['desc'] : $errorInfo['desc'];
                }
            }
        }

        return ToolsService::returnAdmin(0, $error);
    }


}
