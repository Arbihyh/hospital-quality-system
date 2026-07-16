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
use App\Model\PatientInfo;

class Xgzb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zb:xgzb {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '心梗指标 - 数据处理';

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
        $this->info('心梗指标 - 开始处理');

        $page = (int) $this->argument('page') ?: 1;
        $this->xgzbDataHandle($page);

        $this->info('心梗指标 - 处理完毕');
    }

    protected function xgzbDataHandle($page)
    {
        $conf = config('confAdmin');
        $endTime = date('Y-m-d H:i:s', time());
        while (true) {
            // 所有符合条件的病例信息
            $zbbmArr = array_keys($this->xgArr());
            $data = PatientInfo::query()
                ->select([
                    'main_diagnosis.AAA28',
                    'EMR_BL_BL01.analysis_case',
                    'EMR_BL_BL01.JZHM',
                    'main_diagnosis.ICD10_ID1',
                    'main_diagnosis.ICD10_NAME',
                    'EMR_BL_BL01.BLBH'
                ])
                ->join('EMR_BL_BL01', 'EMR_BL_BL01.JZHM', '=', 'patient_info.MED_REC_ID')
                ->join('main_diagnosis', 'main_diagnosis.AAA28', '=', 'EMR_BL_BL01.JZHM')
                ->where('EMR_BL_BL01.BLLB', 292)
                ->where('patient_info.AAC01', '>=', $conf['zb_start_time'])
                ->where('patient_info.AAC01', '<=', $endTime)
                ->where(function ($query) {
                    $query->where('main_diagnosis.ICD10_ID1', 'like', '%i21.0%')
                        ->orWhere('main_diagnosis.ICD10_ID1', 'like', 'I21.1%')
                        ->orWhere('main_diagnosis.ICD10_ID1', 'like', 'I21.2%')
                        ->orWhere('main_diagnosis.ICD10_ID1', 'like', 'I21.3%');
                    //->orWhere('main_diagnosis.ICD10_ID1', 'like', 'I21.9%');
                })
                ->paginate(500, '*', 'page', $page)
                ->toArray();
            if ($page == 1) {
                echo '数据总条数：' . $data['total'] . PHP_EOL . '总页数：' . $data['last_page'] . PHP_EOL;
            } elseif ($page > $data['last_page']) {
                // 如果当前页码大于最大页码则结束
                break;
            }

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
                $numerator = 0;
                $sickTime = '';
                $sickSTime = '';
                $sssj = '';
                $ssbh = '';
                $ssmc = '';
                if (empty($analysisCase)) {
                    continue;
                }

                // 获取入院时间，兼容两种可能的键名
                $hospitalizeTime = '';
                /* if (isset($analysisCase['入院时间:'])) {
                    $hospitalizeTime = trim($analysisCase['入院时间:']['value']);
                } elseif (isset($analysisCase['入院日期:'])) {
                    $hospitalizeTime = trim($analysisCase['入院日期:']['value']);
                } */
                $hospitalizeTime = $analysisCase['RYSJ'] ?? '';

                // 检查是否成功获取到入院时间
                if (empty($hospitalizeTime)) {
                    echo "警告: 病历 {$value['JZHM']} 未找到入院时间\n";
                    //continue;
                }

                // 获取主诉
                $zhusu = '';
                /* if (isset($analysisCase['主诉:'])) {
                    $zhusu = trim($analysisCase['主诉:']['value']);
                } elseif (isset($analysisCase['主诉:'])) {
                    $zhusu = trim($analysisCase['主诉:']['value']);
                } */
                $zhusu = $analysisCase['ZHS'] ?? '';
                if (!isset($zhusu)) {
                    echo "警告: 病历 {$value['JZHM']} 未找到主诉\n";
                    //continue;
                }


                $xgzbDescribe = '主诊【' . $value['ICD10_ID1'] . ' ' . $value['ICD10_NAME'] . '】 症状【】 发病时间【】入院时间【' . $hospitalizeTime . '】主诉内容【' . $zhusu . '】';

                if ($symptomArr = $this->zzOrTz($zhusu)) {
                    $numerator = 1;
                    //处理 症状 发病时间
                    /** @var PszbService $pszbService */
                    $pszbService = app(PszbService::class);
                    list($sickTime, $symptom, $sickSTime) = $pszbService->handleData($zhusu, $symptomArr, $hospitalizeTime);

                    $xgzbDescribe = '主诊【' . $value['ICD10_ID1'] . ' ' . $value['ICD10_NAME'] . '】 症状【' . $symptom . '】 发病时间【' . $sickTime . '】入院时间【' . $hospitalizeTime . '】主诉内容【' . $zhusu . '】发病时间-时【' . $sickSTime . '】';

                    //处理手术
                    //有手术名称 没有手术时间 $numerator 制为否
                    list($ssmc, $ssbh, $sssj) = $this->handleSsData($ZYH);
                    if ($ssbh && $ssmc) {
                        $xgzbDescribe .= '手术名称【' . $ssbh . ' ' . $ssmc . '】';
                        if (!$sssj) {
                            $numerator = 0;
                        } else {
                            $xgzbDescribe .= '手术时间【' . $sssj . '】';
                        }
                    }
                }

                // 记录
                $saveData = ['denominator_xgzb' => 1, 'numerator_xgzb' => $numerator, 'xgzb_describe' => $xgzbDescribe, 'XG_BLBH' => $value['BLBH']];
                PatientInfoTarget::query()->updateOrInsert(['ZYH' => $ZYH], $saveData);
                Pszb::query()->updateOrInsert(['BLBH' => $value['BLBH'], 'type' => 61], ['fbsj' => $sickTime, 'fbsj_s' => $sickSTime, 'sssj' => $sssj, 'ICD10_ID1' => $value['ICD10_ID1'], 'ICD10_NAME' => $value['ICD10_NAME'], 'ICD9_ID1' => $ssbh, 'ICD9_NAME' => $ssmc, 'zhusu' => $zhusu]);
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
                    $query->orWhere('ICD9_ID1', 'like', '%36.06%');
                    $query->orWhere('ICD9_ID1', 'like', '%36.07%');
                    $query->orWhere('ICD9_ID1', 'like', '%00.66%');
                })
                ->orderBy('OPE_DATE')
                ->first();

            if ($res) {
                $res = $res->toArray();
            }

            $res1 = SecondaryOperation::query()
                ->where('AAA28', $bl['JZHM'])
                ->where(function ($query) {
                    $query->orWhere('ICD9_ID1', 'like', '%36.06%');
                    $query->orWhere('ICD9_ID1', 'like', '%36.07%');
                    $query->orWhere('ICD9_ID1', 'like', '%00.66%');
                })
                ->orderBy('OPE_DATE')
                ->first();
            if ($res1) {
                $res1 = $res1->toArray();
            }

            if (!$res && !$res1) {
                return ['', '', ''];
            } elseif (!$res && $res1) {
                $data[$key]['ssmc'] = $res1['ICD9_NAME'];
                $data[$key]['ssbh'] = $res1['ICD9_ID1'];
            } else {
                $data[$key]['ssmc'] = $res['ICD9_NAME'];
                $data[$key]['ssbh'] = $res['ICD9_ID1'];
            }

            $SSKSSJ = Bllb303::query()->where('BLBH', $bl['BLBH'])
                ->where(function ($query) {
                    $query->orWhere('SSMC', 'like', '%球囊%');
                    $query->orWhere('SSMC', 'like', '%成形%');
                    $query->orWhere('SSMC', 'like', '%置入%');
                    $query->orWhere('SSMC', 'like', '%PCI%');
                    $query->orWhere('SSMC', 'like', '%植入%');
                    $query->orWhere('SSMC', 'like', '%造影%');
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
            '喘憋',
            '胸痛',
            '胸闷',
            '心悸',
            '憋气',
            '心前区疼痛',
            '后背胀痛',
            '心前区不适',
            '腹痛',
            '胸部不适',
            '后背疼痛',
            '后背痛',
            '胸部疼痛'
        ];

        /** @var PszbService $pszbService */
        $pszbService = app(PszbService::class);
        return $pszbService->matchZz($arr, $str);
    }

    //心梗疾病
    public function xgArr(): array
    {
        return [
            'I21.004' => '急性广泛前壁心肌梗死',
            'I21.003' => '急性前间壁心肌梗死',
            'I21.002' => '急性前侧壁心肌梗死',
            'I21.001' => '急性前壁心肌梗死',
            'I21.000x003' => '急性透壁广泛前壁心肌梗塞',
            'I21.000x002' => '急性透壁前侧壁心肌梗塞',
            'I21.000x001' => '急性透壁前间壁心肌梗塞',
            'I21.000' => '前壁急性透壁性心肌梗死',
            'I21.303' => '冠状动脉介入治疗术后心肌梗死',
            'I21.302' => '冠状动脉旁路术后心肌梗死',
            'I21.300x011' => '急性透壁心肌梗塞',
            'I21.300x008' => '支架内血栓相关性心肌梗死',
            'I21.300x007' => '冠状动脉介入术相关性心肌梗死',
            'I21.300x006' => '冠状动脉旁路移植手术相关性心肌梗死',
            'I21.300x005' => '围手术期心肌梗死',
            'I21.300x004' => '急性ST段抬高型心肌梗死',
            'I21.300x003' => '手术后心肌梗死',
            'I21.300' => '急性透壁性心肌梗死',
            'I21.213' => '急性多壁心肌梗死',
            'I21.901' => '冠状动脉破裂',
            'I21.900x017' => '亚急性心肌梗死',
            'I21.900x016' => '急性室壁心肌梗死',
            'I21.900x014' => '急性膈面心肌梗死',
            'I21.900x013' => '急性膈面(下壁)心肌梗死',
            'I21.900x012' => '急性前膈心肌梗死',
            'I21.900x011' => '心肌梗死  ',
            'I21.900x001' => '非冠心病性心肌梗死',
            'I21.900' => '急性心肌梗死',
        ];
    }
}
