<?php

namespace App\Console\Commands\Target;

use App\Model\Bllb292;
use App\Model\Bllb303;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\MainOperation;
use App\Model\PatientInfoTarget;
use App\Model\Pszb;
use App\Model\SecondaryOperation;
use App\Services\PszbService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class Ngzb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zb:ngzb {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '脑梗指标 - 数据处理';

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
        $this->info('脑梗指标 - 开始处理');

        $page = (int)$this->argument('page') ?: 1;
        $this->ngzbDataHandle($page);

        $this->info('脑梗指标 - 处理完毕');
    }

    protected function ngzbDataHandle($page)
    {
        $conf = config('confAdmin');
        $endTime = date('Y-m-d H:i:s', time());
        while (true) {
            // 所有符合条件的病例信息
            $zbbmArr = array_keys($this->ngArr());
            $data = EMR_BL_BL01::query()
                ->leftJoin('main_diagnosis', 'main_diagnosis.AAA28', '=', 'EMR_BL_BL01.JZHM')
                ->leftJoin('patient_info', 'patient_info.MED_REC_ID', '=', 'EMR_BL_BL01.JZHM')
                ->where('EMR_BL_BL01.BLLB', 292)
                ->whereBetween('patient_info.AAC01', [$conf['zb_start_time'], $endTime])
                ->where('main_diagnosis.ICD10_ID1', 'like', 'I63%')
                ->paginate(500, ['main_diagnosis.AAA28', 'EMR_BL_BL01.analysis_case', 'EMR_BL_BL01.JZHM', 'main_diagnosis.ICD10_ID1', 'main_diagnosis.ICD10_NAME', 'EMR_BL_BL01.BLBH'], 'page', $page)
                ->toArray();
            if ($page == 1) {
                echo '数据总条数：' . $data['total'] . PHP_EOL . '总页数：' . $data['last_page'] . PHP_EOL;
            } elseif ($page > $data['last_page']) {
                // 如果当前页码大于最大页码则结束
                break;
            }
            echo $page . PHP_EOL;
            $page++;

            foreach ($data['data'] as $value) {
                /* $analysisCase = json_decode($value['analysis_case'], true);
                if (!$analysisCase) {
                    continue;
                } */
                //$analysisCase = array_column($analysisCase, null, 'title');
                $analysisCase = Bllb292::query()->where('ZYH', $value['JZHM'])->get()->toArray();
                $analysisCase = $analysisCase[0] ?? [];
                $ZYH = $value['JZHM'];
                echo $ZYH . PHP_EOL;
                $sickTime = '';
                $sickSTime = '';
                $sssj = '';
                $ssbh = '';
                $ssmc = '';
                $numerator = 0;
                //$zhusu = trim($analysisCase['主诉:']['value']);
                //$hospitalizeTime = trim($analysisCase['入院时间:']['value']);
                $zhusu = $analysisCase['ZHS'] ?? '';
                $hospitalizeTime = $analysisCase['RYSJ'] ?? '';
                $ngzbDescribe = '主诊【' . $value['ICD10_ID1'] . ' ' . $value['ICD10_NAME'] . '】 症状【】 发病时间【】入院时间【' . $hospitalizeTime . '】主诉内容【' . $zhusu . '】';

                if ($symptomArr = $this->zzOrTz($zhusu)) {
                    $numerator = 1;
                    //处理 症状 发病时间
                    /** @var PszbService $pszbService */
                    $pszbService = app(PszbService::class);
                    list($sickTime, $symptom, $sickSTime) = $pszbService->handleData($zhusu, $symptomArr, $hospitalizeTime);

                    $ngzbDescribe = '主诊【' . $value['ICD10_ID1'] . ' ' . $value['ICD10_NAME'] . '】 症状【' . $symptom . '】 发病时间【' . $sickTime . '】入院时间【' . $hospitalizeTime . '】主诉内容【' . $zhusu . '】发病时间-时【' . $sickSTime . '】';

                    //处理手术
                    list($ssmc, $ssbh, $sssj) = $this->handleSsData($ZYH);
                    //有手术名称 没有手术时间 $numerator 制为否
                    if ($ssbh && $ssmc) {
                        $ngzbDescribe .= '手术名称【' . $ssbh . ' ' . $ssmc . '】';
                        if (!$sssj) {
                            $numerator = 0;
                        } else {
                            $ngzbDescribe .= '手术时间【' . $sssj . '】';
                        }
                    }
                }
                // 记录
                $saveData = ['denominator_ngzb' => 1, 'numerator_ngzb' => $numerator, 'ngzb_describe' => $ngzbDescribe, 'NG_BLBH' => $value['BLBH']];
                PatientInfoTarget::query()->updateOrInsert(['ZYH' => $ZYH], $saveData);
                Pszb::query()->updateOrInsert(['BLBH' => $value['BLBH'], 'type' => 60], ['fbsj' => $sickTime, 'fbsj_s' => $sickSTime, 'sssj' => $sssj, 'ICD10_ID1' => $value['ICD10_ID1'], 'ICD10_NAME' => $value['ICD10_NAME'], 'ICD9_ID1' => $ssbh, 'ICD9_NAME' => $ssmc, 'zhusu' => $zhusu]);
            }
        }

        return true;
    }

    public function handleSsData($ZYH)
    {
        $data = [];

        $bls = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->where(function ($query) {
                $query->orWhereIn('MBLB', [306, 74]);
                $query->orWhere(function ($q) {
                    $q->where('BLLB', 303);
                    $q->where('BLMC', 'like', '%手术记录%');
                });
            })->where('BLZT', '!=', 9)->select(['JZHM', 'BLBH'])->get()->toArray();

        if (!$bls) {
            return ['', '', ''];
        }

        foreach ($bls as $key => $bl) {
            //获取手术名称 以及 手术编号
            $res = MainOperation::query()
                ->where('AAA28', $bl['JZHM'])
                ->where(function ($query) {
                    $query->orWhere('ICD9_ID1', 'like', '%39.74%');
                    $query->orWhere('ICD9_ID1', 'like', '%00.61%');
                    $query->orWhere('ICD9_ID1', 'like', '%00.62%');
                    $query->orWhere('ICD9_ID1', 'like', '%00.63%');
                    $query->orWhere('ICD9_ID1', 'like', '%00.64%');
                    $query->orWhere('ICD9_ID1', 'like', '%00.65%');
                })
                ->orderBy('OPE_DATE')
                ->first();

            if ($res) {
                $res = $res->toArray();
            }
            $res1 = SecondaryOperation::query()
                ->where('AAA28', $bl['JZHM'])
                ->where(function ($query) {
                    $query->orWhere('ICD9_ID1', 'like', '%39.74%');
                    $query->orWhere('ICD9_ID1', 'like', '%00.61%');
                    $query->orWhere('ICD9_ID1', 'like', '%00.62%');
                    $query->orWhere('ICD9_ID1', 'like', '%00.63%');
                    $query->orWhere('ICD9_ID1', 'like', '%00.64%');
                    $query->orWhere('ICD9_ID1', 'like', '%00.65%');
                })
                ->orderBy('OPE_DATE')
                ->first();

            if ($res1) {
                $res1 = $res1->toArray();
            }

            if (!$res && !$res1) {
                return ['', '', '',];
            } elseif (!$res && $res1) {
                $data[$key]['ssmc'] = $res1['ICD9_NAME'];
                $data[$key]['ssbh'] = $res1['ICD9_ID1'];
            } else {
                $data[$key]['ssmc'] = $res['ICD9_NAME'];
                $data[$key]['ssbh'] = $res['ICD9_ID1'];
            }

            $SSKSSJ = Bllb303::query()->where('BLBH', $bl['BLBH'])
                ->where(function ($query) {
                    $query->orWhere('SSMC', 'like', '%成形%');
                    $query->orWhere('SSMC', 'like', '%植入%');
                    $query->orWhere('SSMC', 'like', '%球囊%');
                    $query->orWhere('SSMC', 'like', '%取栓%');
                    $query->orWhere('SSMC', 'like', '%去除%');
                    $query->orWhere('SSMC', 'like', '%置入%');
                })->orderBy('SSKSSJ')->pluck('SSKSSJ')->first();

            $data[$key]['sssj'] = '';
            if ($SSKSSJ) {
                $data[$key]['sssj'] = $SSKSSJ;
            }
        }

        array_multisort(array_column($data, 'sssj'), SORT_ASC, $data);
        $data[0]['sssj'] = $data[0]['sssj'] ?: (Arr::get($data, 1) ? Arr::get($data, 1)['sssj'] : '');
        return array_values(Arr::first($data));
    }

    //症状或体征
    public function zzOrTz($str)
    {
        $arr = [
            '言语笨拙',
            '肢体不灵',
            '言语不清',
            '意识不清',
            '视物模糊',
            '肢体活动不灵',
            '言语含糊',
            '肢体麻木',
            '肢体抽搐',
            '嗜睡',
            '上肢无力',
            '下肢无力',
            '言语障碍',
            '言语不利',
            '言语障碍',
            '言语功能障碍',
            '上肢麻木',
            '下肢麻木',
            '抽搐',
            '意识丧失',
            '肢体无力',
            '行走不能',
            '反应迟钝',
            '肢体活动障碍',
            '言语不能',
            '偏身麻木',
            '不自主活动',
            '不言语',
            '站立不稳',
            '面部麻木',
            '言语混乱',
            '手脚麻木',
            '手无力',
            '手麻木',
            '肢体活动不利',
            '手指麻木',
            '发作性头晕',
            '意识障碍',
            '口角歪斜',
            '意识恍惚',
            '头痛',
            '感觉异常',
            '活动不灵',
            '言语欠流利',
            '无力',
            '走路不稳',
            '头晕',
            '乏力',
            '视物',
            '困难',
            '记忆力减退',
            [
                '头晕',
                '恶心',
            ],
            [
                '头晕',
                '视野缺损',
            ],
            [
                '麻木',
                '手',
            ],
            [
                '麻木',
                '口'
            ],
            '活动笨拙',
            '活动不能',
            '欠流利',
            '走路左偏',
            '麻木',
            '表达障碍',
            '行走不稳',
            '活动受限',
            '运动障碍',
            '不能行走',
            '麻胀',
            '意识模糊',
            '走路右偏',
            '行为异常',
            '言语欠清',
            '言语理解障碍',
            '不自主运动',
            '手麻',
            '活动欠灵活',
            '持物不稳',
            '不能言语',
            '活动障碍'
        ];

        /** @var PszbService $pszbService */
        $pszbService = app(PszbService::class);
        return $pszbService->matchZz($arr, $str);
    }

    //脑梗疾病
    public function ngArr(): array
    {
        return [
            'I63.908' => '创伤性脑梗死',
            'I63.907' => '丘脑梗死',
            'I63.906' => '基底节脑梗死',
            'I63.905' => '多发性脑梗死',
            'I63.904' => '小脑梗死',
            'I63.903' => '出血性脑梗死',
            'I63.902' => '大面积脑梗死',
            'I63.901' => '脑干梗死',
            'I63.900x007' => '分水岭脑梗死[边缘带脑梗死]',
            'I63.900' => '脑梗死',
            'I63.802' => '动脉硬化性脑软化',
            'I63.801' => '腔隙性脑梗死',
            'I63.800' => '脑梗死，其他的',
            'I63.600' => '大脑静脉血栓形成引起的脑梗死，非生脓性',
            'I63.502' => '大脑动脉闭塞脑梗死',
            'I63.501' => '大脑动脉狭窄脑梗死',
            'I63.500x003' => '脑动脉未特指的闭塞或狭窄引起的脑梗死',
            'I63.500x002' => '丘脑穿支动脉梗死',
            'I63.500' => '大脑动脉的闭塞或狭窄引起的脑梗死',
            'I63.402' => '脑栓塞',
            'I63.401' => '栓塞性偏瘫',
            'I63.400' => '大脑动脉栓塞引起的脑梗死',
            'I63.302' => '血栓性偏瘫',
            'I63.301' => '血栓形成性脑软化',
            'I63.300' => '大脑动脉血栓形成引起的脑梗死',
            'I63.208' => '椎动脉狭窄脑梗死',
            'I63.207' => '椎动脉闭塞脑梗死',
            'I63.206' => '基底动脉狭窄脑梗死',
            'I63.205' => '基底动脉闭塞脑梗死',
            'I63.204' => '颈动脉闭塞脑梗死',
            'I63.203' => '颈动脉狭窄脑梗死',
            'I63.202' => '颈总动脉狭窄脑梗死',
            'I63.201' => '颈内动脉狭窄脑梗死',
            'I63.200x001' => '入脑前动脉未特指的闭塞或狭窄引起的脑梗死',
            'I63.200' => '入脑前动脉的闭塞或狭窄引起的脑梗死',
            'I63.103' => '椎动脉栓塞脑梗死',
            'I63.102' => '颈动脉栓塞脑梗死',
            'I63.101' => '基底动脉栓塞脑梗死',
            'I63.100' => '入脑前动脉栓塞引起的脑梗死',
            'I63.003' => '椎动脉血栓形成脑梗死',
            'I63.002' => '颈动脉血栓形成脑梗死',
            'I63.001' => '基底动脉血栓形成脑梗死',
            'I63.000' => '入脑前动脉血栓形成引起的脑梗死'
        ];
    }
}
