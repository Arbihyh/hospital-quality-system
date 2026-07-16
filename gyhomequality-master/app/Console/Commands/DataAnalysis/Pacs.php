<?php

namespace App\Console\Commands\DataAnalysis;

use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Model\Setting;
use App\Model\Staff;
use App\Services\ElasticsearchService;
use App\Services\PacsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class Pacs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:pacs {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '报告单格式化数据处理';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('报告单格式化数据处理 - 开始');

        $setName = 'gsh_pacs_ymresult_testresult';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        if ($lastId) {
            $lastId = date('Y-m-d', strtotime($lastId));
        } else {
            $lastId = Carbon::parse()->addDay(-1)->toDateString();
        }

        $startTime = $lastId.' 00:00:00';
        $endTime = date("Y-m-d H:i:s");


        $page = (int)$this->argument('page') ?: 1;

        // 1：病理诊断报告，2：超声诊断报告，3：影像诊断报告单，4：心电图诊断报告，5：检验报告单，6：內窥镜检查报告
        $typeList = [1,2,3,4,5,6];

        while (true) {
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
//                ->whereBetween('AAC01',['2022-01-01 00:00:00','2023-12-31 23:59:59'])
                ->whereBetween('AAC01',[$startTime,$endTime])
                ->paginate(200, ['id','AAA28','MED_REC_ID','AAB01','AAC01','AAA04'],'page', $page)
                ->toArray();

            if ($page == 1) {
                echo '数据总条数：'.$data['total'].PHP_EOL.'总页数：'.$data['last_page'].PHP_EOL;
            } elseif ($page > $data['last_page']) {
                // 如果当前页码大于最大页码则结束
                break;
            }
            echo $page.PHP_EOL;
            $page++;

            foreach ($data['data'] as $value) {
                $newLastId = $value['AAC01'];
                echo $newLastId.PHP_EOL;

                $ZYH = $value['MED_REC_ID'];
                $AAA28 = $value['AAA28'];
                $AAB01 = $value['AAB01'];
                $AAC01 = $value['AAC01'];
                $AAA04 = $value['AAA04'];

                if (empty($AAB01) || empty($AAC01)) {
                    continue;
                }

                $insertData = [];
                foreach ($typeList as $type) {
                    // 报告单格式化
                    $pacsInfo = $this->getPacsData($type,$ZYH,$AAA28,$AAB01,$AAC01,$AAA04);
                    if ($pacsInfo) {
                        $insertData[$type] = $pacsInfo;
                    }
                }

                if ($insertData) {
                    PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],['pacs_content'=>json_encode($insertData, JSON_UNESCAPED_UNICODE)]);
                }
            }
        }

        if (!empty($newLastId)) {
            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $newLastId]);
        }

        $this->info('报告单格式化数据处理 - 结束');
    }

    public function getPacsData($type, $ZYH, $AAA28, $AAB01, $AAC01, $AAA04)
    {
        if ($type == 5) {
            // 获取检验报告单输
            return $this->getInspectPacsData($ZYH, $AAA28, $AAB01, $AAC01, $AAA04);
        }

        $typeData = [
            '1' => ['07'],      // 病理诊断报告
            '2' => ['06'],      // 超声诊断报告
            '3' => ['01','02','03','04','05','09','11'],    // 影像诊断报告单
            '4' => ['10'],      // 心电图诊断报告
            '6' => ['08'],      // 內窥镜检查报告
        ];
        if (empty($typeData[$type])) {
            return [];
        }

        $should = [];
        foreach ($typeData[$type] as $value) {
            $should[] = ['term' => ['ExamType' => $value]];
        }

        // 获取报告单
        $pacsService = new ElasticsearchService('pacs');
        $must = [
            ["term" => ["JZLSH" => $AAA28]],
            ['range' => ['KDSJ' => ['gte' => $AAB01, 'lte' => $AAC01]]]
        ];
        $params = $pacsService->clearMust()
            ->queryByMustBatch($must)
            ->queryByShouldBatch($should)
            ->minimumShouldMatch()
            ->orderBy('KDSJ','asc')
            ->paginate(1,10000)
            ->getParams();
        $restful = app('es')->search($params);
        $pacsData = $pacsService->getDataByEs($restful);
        $data = $pacsData[0];
        if (empty($data)) {
            return [];
        }

        // 数据处理
        return $this->getPacsTypeData($data,$type,$AAA04);
    }

    protected function getInspectPacsData($ZYH,$AAB01,$AAC01,$AAA04)
    {
        $vjyService = new ElasticsearchService('v_jmgs_ymresult_2023');
        $vjtService = new ElasticsearchService('v_jmgs_testresult_2023');

        $data = [];

        // 查询TXM（模本1）
        $must = [
            ["term" => ["ZYH" => $ZYH]],
//            ['range' => ['BGSJ' => ['gte' => $AAB01,'lte' => $AAC01]]]
        ];
        $params = $vjyService->clearMust()
            ->queryByMustBatch($must)
            ->paginate(1,10000)
            ->getParams();
        $restful = app('es')->search($params);
        $vjyData = $vjyService->getDataByEs($restful);
        $txmList = [];
        if (!empty($vjyData[0])) {
            foreach ($vjyData[0] as $value) {
                if (!in_array($value['TXM'],$txmList)) {
                    $txmList[] = $value['TXM'];
                }
            }
        }
        if (!empty($txmList)) {
            foreach ($txmList as $txm) {
                $must = [
                    ["term" => ["TXM" => $txm]],
//                    ['range' => ['BGSJ' => ['gte' => $AAB01,'lte' => $AAC01]]]
                ];
                $params = $vjyService->clearMust()
                    ->queryByMustBatch($must)
                    ->paginate(1,1000)
                    ->getParams();
                $restful = app('es')->search($params);
                $vjyData = $vjyService->getDataByEs($restful);
                $txmData = !empty($vjyData[0]) ? $vjyData[0] : [];
//                $txmData = [];
//                $YMMC_LIST = [];
//                foreach ($txmList as $val) {
//                    if (!in_array($val['YMMC'],$YMMC_LIST)) {
//                        $YMMC_LIST[] = $val['YMMC'];
//                        $txmData[] = $val;
//                    }
//                }

                $status = true;
                $data1Jcxm = [];
                foreach ($txmData as $txmInfo) {
                    if ($status) {
                        $data1 = $this->getJcBgdPublicData($ZYH,1,$txmInfo);
                        $status = false;
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
                $data1['JCXM'] = $data1Jcxm;
                $data[] = $data1;
            }
        }

        // 查询TXM（模本2）
        $must = [
            ["term" => ["ZYH" => $ZYH]],
//            ['range' => ['BGSJ' => ['gte' => $AAB01,'lte' => $AAC01]]]
        ];
        $params = $vjtService->clearMust()
            ->queryByMustBatch($must)
            ->paginate(1,10000)
            ->getParams();
        $restful = app('es')->search($params);
        $vjtData = $vjtService->getDataByEs($restful);
        $txmList = [];
        if (!empty($vjtData[0])) {
            foreach ($vjtData[0] as $value) {
                if (!in_array($value['TXM'],$txmList)) {
                    $txmList[] = $value['TXM'];
                }
            }
        }

        if (!empty($txmList)) {
            foreach ($txmList as $txm) {
                $must = [
                    ["term" => ["TXM" => $txm]],
//                    ['range' => ['BGSJ' => ['gte' => $AAB01,'lte' => $AAC01]]]
                ];
                $params = $vjtService->clearMust()
                    ->queryByMustBatch($must)
                    ->paginate(1,10000)
                    ->getParams();
                $restful = app('es')->search($params);
                $vjtData = $vjtService->getDataByEs($restful);
                $txmData = !empty($vjtData[0]) ? $vjtData[0] : [];
//                $txmData = [];
//                $JYXM_LIST = [];
//                foreach ($txmList as $val) {
//                    if (!in_array($val['JYXM'],$JYXM_LIST)) {
//                        $JYXM_LIST[] = $val['JYXM'];
//                        $txmData[] = $val;
//                    }
//                }
                $status = true;
                $data2Jcxm = [];
                foreach ($txmData as $txmInfo) {
                    if ($status) {
                        $data2 = $this->getJcBgdPublicData($ZYH,2,$txmInfo);
                        $status = false;
                    }

                    // 检查报告单模本2项目格式
                    $data2Jcxm[] = [
                        'JYXM'  =>  $txmInfo['JYXM'],   //中文名称
                        'YW'    =>  $txmInfo['YW'],     //英文
                        'JG'  =>  $txmInfo['JG'],       //结果
                        'TS'    =>  $txmInfo['TS'],     //提示
                        'CKFW'  =>  $txmInfo['CKFW'],   //参考范围
                        'DW'    =>  $txmInfo['DW'],     //单位
                    ];
                }
                $data2['JCXM'] = $data2Jcxm;
                $data[] = $data2;
            }
        }

        return $data;
    }

    /**
     * 获取报告单数据
     * @param $data
     * @param $type
     * @param $AAA04
     * @return array
     */
    protected function getPacsTypeData($data,$type,$AAA04)
    {
        $returnData = [];
        if ($type == 1) {
            // 病理诊断报告
            foreach ($data as $key => $val) {
                $JCBW = explode("\n", trim($val['JCBW']));
                $YXBX = explode("\n", trim($val['YXBX']));
                $YXZD = explode("\n", trim($val['YXZD']));
                $returnData[$key] = [
                    'type'      => $type,
                    'JYSJ'      => $val['JYSJ'],    //检查时间
                    'BGSJ'      => $val['BGSJ'],    //报告时间
                    'BRXM'      => $val['BRXM'],    //姓名
                    'BRXB'      => $val['BRXB'],    //性别
                    'BRNL'      => $AAA04,          //年龄
                    'SQKSMC'    => $val['SQKSMC'],  //科室
                    'JZLSH'     => $val['JZLSH'],   //住院号
                    'JCBGJGMC'  => $val['JCBGJGMC'],//送检标本科别
                    'JCBW'      => $JCBW,           //标本部位
                    'StudyUid'  => $val['StudyUid'],//病理号
                    'YXBX'      => $YXBX,           //大体描述
                    'YXZD'      => $YXZD,           //病理诊断
                    'BGRXM'     => $val['BGRXM'],   //报告医师
                    'SHRXM'     => $val['SHRXM'],   //审核医师
                    'KDSJ'      => $val['KDSJ'],    //开单时间
                    'JCKSMC'    => $val['JCKSMC'],  //检查科室
                    'ExamType'  => $val['ExamType'],//类型
                ];
            }
        } elseif($type == 2) {
            // 超声诊断报告
            foreach ($data as $key => $val) {
                $YXBX = explode("\n", trim($val['YXBX']));
                $YXZD = explode("\n", trim($val['YXZD']));
                $returnData[$key] = [
                    'type'      => $type,
                    'JYSJ'      => $val['JYSJ'],    //检查时间
                    'BGSJ'      => $val['BGSJ'],    //报告时间
                    'StudyUid'  => $val['StudyUid'],//检查号
                    'JZLSH'     => $val['JZLSH'],   //住院号
                    'BRXM'      => $val['BRXM'],    //姓名
                    'BRXB'      => $val['BRXB'],    //性别
                    'BRNL'      => $AAA04,          //年龄
                    'SQKSMC'    => $val['SQKSMC'],  //科室
                    'YXBX'      => $YXBX,           //超声所见
                    'YXZD'      => $YXZD,           //超声提示
                    'JCYS'      => $val['JCYS'],    //检查医师
                    'SHRXM'     => $val['SHRXM'],   //审核人姓名
                    'LRY'       => '',              //录入员
                    'HZYS'      => '',              //会诊医师
                    'KDSJ'      => $val['KDSJ'],    //开单时间
                    'JCKSMC'    => $val['JCKSMC'],  //检查科室
                    'ExamType'  => $val['ExamType'],//类型
                ];
            }
        } elseif ($type == 3) {
            // 影像诊断报告单
            foreach ($data as $key => $val) {
                $YXBX = explode("\n", trim($val['YXBX']));
                $YXZD = explode("\n", trim($val['YXZD']));
                $returnData[$key] = [
                    'type'      => $type,
                    'JYSJ'      => $val['JYSJ'],    //检查时间
                    'BGSJ'      => $val['BGSJ'],    //报告时间
                    'BRXM'      => $val['BRXM'],    //姓名
                    'BRXB'      => $val['BRXB'],    //性别
                    'BRNL'      => $AAA04,          //年龄
                    'PatientID' => $val['PatientID'],//影像号
                    'SQKSMC'    => $val['SQKSMC'],  //科室
                    'JZLSH'     => $val['JZLSH'],   //住院号
                    'CH'        => '',              //床号
                    'StudyUid'  => $val['StudyUid'],//检查号
                    'JCMC'      => $val['JCMC'],    //检查项目
                    'YXBX'      => $YXBX,           //影像学表现
                    'YXZD'      => $YXZD,           //影像学诊断
                    'BGRXM'     => $val['BGRXM'],   //影响医师
                    'SHRXM'     => $val['SHRXM'],   //审核医师
                    'KDSJ'      => $val['KDSJ'],    //开单时间
                    'JCKSMC'    => $val['JCKSMC'],  //检查科室
                    'ExamType'  => $val['ExamType'],//类型
                ];
            }
        } elseif ($type == 4) {
            // 心电图诊断报告
            foreach ($data as $key => $val) {
                $YXZD = explode("\n", trim($val['YXZD']));
                $returnData[$key] = [
                    'type'      => $type,
                    'JYSJ'      => $val['JYSJ'],    //检查时间
                    'BGSJ'      => $val['BGSJ'],    //报告时间
                    'BRXM'      => $val['BRXM'],    //姓名
                    'BRXB'      => $val['BRXB'],    //性别
                    'BRNL'      => $AAA04,          //年龄
                    'SQKSMC'    => $val['SQKSMC'],  //科室
                    'JZLSH'     => $val['JZLSH'],   //住院号
                    'StudyUid'  => $val['StudyUid'],//检查号
                    'JCMC'      => $val['JCMC'],    //检查名称
                    'YXZD'      => $YXZD,           //心电提示
                    'BGRXM'     => $val['BGRXM'],   //报告人姓名
                    'SHRXM'     => $val['SHRXM'],   //审核人姓名
                    'KDSJ'      => $val['KDSJ'],    //开单时间
                    'JCKSMC'    => $val['JCKSMC'],  //检查科室
                    'ExamType'  => $val['ExamType'],//类型
                ];
            }
        } elseif ($type == 6) {
            // 內窥镜检查报告
            foreach ($data as $key => $val) {
                $JCBW = explode("\n", trim($val['JCBW']));
                $YXBX = explode("\n", trim($val['YXBX']));
                $YXZD = explode("\n", trim($val['YXZD']));
                $returnData[$key] = [
                    'type'      => $type,
                    'JYSJ'      => $val['JYSJ'],    //检查时间
                    'BGSJ'      => $val['BGSJ'],    //报告时间
                    'BRXM'      => $val['BRXM'],    //姓名
                    'BRXB'      => $val['BRXB'],    //性别
                    'BRNL'      => $AAA04,          //年龄
                    'SQKSMC'    => $val['SQKSMC'],  //科室
                    'JZLSH'     => $val['JZLSH'],   //住院号
                    'StudyUid'  => $val['StudyUid'],//检查号
                    'JCMC'      => $val['JCMC'],    //检查名称
                    'JCBW'      => $JCBW,           //检查部位
                    'YXBX'      => $YXBX,           //内径所见
                    'YXZD'      => $YXZD,           //内径诊断
                    'BGRXM'     => $val['BGRXM'],   //报告医师
                    'SHRXM'     => $val['SHRXM'],   //审核医师
                    'KDSJ'      => $val['KDSJ'],    //开单时间
                    'JCKSMC'    => $val['JCKSMC'],  //检查科室
                    'ExamType'  => $val['ExamType'],//类型
                ];
            }
        }

        foreach ($returnData as &$v) {
            // 姓名脱敏
            if (!empty($v['BRXM'])) {
                $v['BRXM'] = desensitize($v['BRXM'], 1, 0, '*');
            }
        }

        return $returnData;
    }

    protected function getJcBgdPublicData($ZYH,$templateType,$txmInfo)
    {
        $JYY = !empty($txmInfo['JYY']) ? Staff::query()->where('code','=',$txmInfo['JYY'])->value('name') : '';
        $SHY = !empty($txmInfo['SHY']) ? Staff::query()->where('code','=',$txmInfo['SHY'])->value('name') : '';


        $txmInfo['XM'] = desensitize($txmInfo['XM'], 1, 0, '*');
        return [
            'type'  => 5,
            'template_type' => $templateType,       //模板
            'TXM'   => $txmInfo['TXM'],     //条形码
            'NO'    => $txmInfo['NO'],      //NO
            'XM'    => $txmInfo['XM'],      //姓名
            'XB'    => $txmInfo['XB'],      //性别
            'NL'    => $txmInfo['NL'],      //年龄
            'CH'    => $txmInfo['CH'],      //床号
            'YBLX'  => $txmInfo['YBLX'],    //样本类型
            'YBZT'  => $txmInfo['YBZT'],    //样本状态
            'ZYH'   => $txmInfo['AAA28'],   //住院号
            'BQ'    => $txmInfo['BQ'],      //病区
            'LCZD'  => $txmInfo['LCZD'],    //临床诊断
            'SJYS'  => $txmInfo['SJYS'],    //送检医生
            'JYY'   => $JYY,                //检验员
            'SHY'   => $SHY,                //审核员
            'CJSJ'  => $txmInfo['CJSJ'],    //采集时间
            'JSSJ'  => $txmInfo['JSSJ'],    //接收时间
            'BGSJ'  => $txmInfo['BGSJ'],    //报告时间
            'AAA28' => $txmInfo['AAA28'],   //住院号
        ];
    }

}
