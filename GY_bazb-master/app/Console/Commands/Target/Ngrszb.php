<?php

namespace App\Console\Commands\Target;

use App\Model\EMR_BL_BL01;
use App\Model\PatientInfoTarget;
use App\Model\Pszb;
use App\Model\Yzb;
use Carbon\Carbon;
use Illuminate\Console\Command;

class Ngrszb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zb:ngrszb {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '脑梗溶栓指标 - 数据处理';

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
     * 必须在脑梗指标执行完之后在执行此方法
     *
     * @return int
     */
    public function handle()
    {
        $this->info('脑梗溶栓指标 - 开始处理');

        $page = (int)$this->argument('page') ?: 1;
        $this->ngzbDataHandle($page);

        $this->info('脑梗溶栓指标 - 处理完毕');
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
                ->leftJoin('pszb', 'pszb.BLBH', '=', 'EMR_BL_BL01.BLBH')
                ->where('EMR_BL_BL01.BLLB', 292)
                ->whereBetween('patient_info.AAC01', [$conf['zb_start_time'], $endTime])
                ->whereIn('main_diagnosis.ICD10_ID1', $zbbmArr)
                ->paginate(500, ['main_diagnosis.AAA28', 'EMR_BL_BL01.analysis_case', 'EMR_BL_BL01.JZHM', 'main_diagnosis.ICD10_ID1', 'main_diagnosis.ICD10_NAME','EMR_BL_BL01.BLBH as BL01BLBH', 'pszb.*'], 'page', $page)
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
                $ZYH = $value['JZHM'];
                $yz = Yzb::query()->where('ZYH', $value['JZHM'])->where('YZMC', 'like', '%阿替普酶%')->orderBy('XZJDSJ')->first();

                if ($yz && $yz->XZJDSJ) {
                    $numerator = 0;
                    $rssj = '';
                    $ngnszbDescribe = '主诉内容【' . $value['zhusu'] . '】发病时间【' . $value['fbsj'] . '】溶栓时间【】';
                    //有发病时间 并且发病时间-溶栓时间 <= 4.5小时
                    if ($value['fbsj']) {
                        $rssj = round(Carbon::parse($yz->XZJDSJ)->diffInMinutes(Carbon::parse($value['fbsj'])) / 60, 2);
                        if ($rssj <= 4.5) {
                            $numerator = 1;
                            $ngnszbDescribe = '主诉内容【' . $value['zhusu'] . '】发病时间【' . $value['fbsj'] . '】溶栓时间【' . $rssj . '小时】';
                        }
                    }
                    // 记录
                    $saveData = ['denominator_ngrszb' => 1, 'numerator_ngrszb' => $numerator, 'ngrszb_describe' => $ngnszbDescribe, 'NG_BLBH' => $value['BL01BLBH']];
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => $ZYH], $saveData);
                    Pszb::query()->updateOrInsert(['BLBH' => $value['BL01BLBH'], 'type' => 60], ['rssj' => $rssj, 'yzmc' => $yz['YZMC'], 'XZJDSJ' => date('Y-m-d H:i', strtotime($yz['XZJDSJ']))]);
                }
            }
        }

        return true;
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
