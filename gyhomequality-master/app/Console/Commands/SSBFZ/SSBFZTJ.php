<?php

namespace App\Console\Commands\SSBFZ;

use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\OtherDiagnosis;
use App\Model\PatientInfo;
use App\Model\SecondaryOperation;
use App\Model\Setting;
use App\Model\SsbfzStatistics;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SSBFZTJ extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tj:ssbfz {startTime?} {endTime?} {tjKey?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '手术并发症相关统计';

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
        $startTime = $this->argument('startTime') ?: '';
        $endTime = $this->argument('endTime') ?: '';
        $tjKey = $this->argument('tjKey') ?: '';

        if (!empty($tjKey) && !isset($list[$tjKey])) {
            $this->error('无效的参数');
        } else {
            if (!empty($startTime) && !empty($endTime)) {
                $fieldName = 'AAC01';
                $startTime = $startTime.' 00:00:00';
                $endTime = $endTime.' 23:59:59';
            } else {
                $fieldName = 'created_at';
                $startTime = Carbon::parse()->addDay(-90)->toDateString().' 00:00:00';
                $endTime = Carbon::parse()->addDay(-1)->toDateString().' 23:59:59';
            }

            $list = SsbfzStatistics::$list;

            $page = 1;
            while (true) {
                $data = PatientInfo::query()
                    ->whereBetween($fieldName,[$startTime,$endTime])
                    ->orderBy('id')
                    ->paginate(500, ['AAA28','MED_REC_ID','AAC01','AAC11N'],'page', $page)
                    ->toArray();
                if (empty($data['data'])) {
                    break;
                }
                echo $page.PHP_EOL;
                $page++;

                foreach ($data['data'] as $info) {
                    $ZYH = $info['MED_REC_ID'];

                    echo $ZYH.PHP_EOL;

                    $month = !empty($info['AAC01']) ? date("n", strtotime($info['AAC01'])) : '';
                    // 获取手术信息
                    $zyssData = MainOperation::query()
                        ->where('AAA28','=',$ZYH)
                        ->where('ICD9_ID1','!=','')
                        ->get()->toArray();
                    $qtssData = SecondaryOperation::query()
                        ->where('AAA28','=',$ZYH)
                        ->where('ICD9_ID1','!=','')
                        ->get()->toArray();
                    $ssData = array_merge($zyssData,$qtssData);
                    if (empty($ssData)) {
                        $saveData = [
                            'AAA28' => $info['AAA28'],
                            'AAC01' => $info['AAC01'],
                            'month' => $month,
                            'AAC11N' => $info['AAC11N'],
                            'updated_at' => date('Y-m-d H:i:s',time())
                        ];
                        SsbfzStatistics::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
                        continue;
                    }

                    // 获取诊断信息
                    $zyzdData = MainDiagnosis::query()
                        ->where('RYQK','=','无')
                        ->where('AAA28','=',$ZYH)
                        ->get()->toArray();
                    $qtzdData = OtherDiagnosis::query()
                        ->where('RYQK','=','无')
                        ->where('AAA28','=',$ZYH)
                        ->get()->toArray();
                    $zdList = array_merge($zyzdData,$qtzdData);
                    if (empty($zdList)) {
                        $saveData = [
                            'AAA28' => $info['AAA28'],
                            'AAC01' => $info['AAC01'],
                            'month' => $month,
                            'AAC11N' => $info['AAC11N'],
                            'updated_at' => date('Y-m-d H:i:s',time())
                        ];
                        SsbfzStatistics::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
                        continue;
                    }

                    if (!empty($tjKey) && isset($list[$tjKey])) {
                        // 单条执行
                        $v = $this->bfztj($zdList,$list[$tjKey]);
                        $saveData = [
                            'AAA28' => $info['AAA28'],
                            'AAC01' => $info['AAC01'],
                            $tjKey => $v,
                            'month' => $month,
                            'AAC11N' => $info['AAC11N'],
                            'updated_at' => date('Y-m-d H:i:s',time())
                        ];
                        SsbfzStatistics::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
                    } else {
                        // 执行所有
                        foreach ($list as $field => $ICD10_ID1_LIST) {
                            $v = $this->bfztj($zdList,$ICD10_ID1_LIST);
                            $saveData = [
                                'AAA28' => $info['AAA28'],
                                'AAC01' => $info['AAC01'],
                                $field => $v,
                                'month' => $month,
                                'AAC11N' => $info['AAC11N'],
                                'updated_at' => date('Y-m-d H:i:s',time())
                            ];
                            SsbfzStatistics::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
                        }
                    }
                }
            }

            $this->info('手术并发症统计完毕');
        }
    }

    /**
     * @param $data
     * @param $ICD10_ID1_LIST
     * @return int
     */
    public function bfztj($data, $ICD10_ID1_LIST)
    {
        $value = 0;
        foreach ($data as $val) {
            if ($value) {
                break;
            }
            foreach ($ICD10_ID1_LIST as $v) {
                if (stripos($val['ICD10_ID1'],$v) !== false) {
                    $value = 1;
                    break;
                }
            }
        }

        return $value;
    }


}
