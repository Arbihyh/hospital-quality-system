<?php

namespace App\Console\Commands;

use App\Model\CaseRule;
use App\Model\OMR_BL01;
use App\Model\EMR_BL_BL01;
use App\Model\PatientInfo;
use App\Model\MainOperation;
use App\Services\EsSaveService;
use Illuminate\Console\Command;
use App\Console\Commands\DataFormat\OMR_BL01 as cb;

class Test2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'test2 {startTime?} {endTime?} {zyh?} {rule_id?}';   

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 定时生成执行相关诊断信息的统计信息';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    function handle()
    {
        $startTime = $this->argument('startTime');
        $endTime = $this->argument('endTime');
        $zyh = $this->argument('zyh');
        $rule_id = $this->argument('rule_id');
        if (!$startTime || !$endTime) {
            $this->error("请输入开始时间和结束时间");
            return;
        }
        $this->quality($startTime, $endTime, $zyh, $rule_id);
    }

    public function quality($startTime, $endTime, $zyh, $rule_id)
    {

        //所有添加的质控规则
        $caseRule = CaseRule::query()->where("id", $rule_id)->get()->toArray();
        $caseRule = array_column($caseRule, null, 'id');

        $moduleName = env("APP_NAME", "");
        $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\BanhzkgzService";
        $banhzkgzService = new $className();
        $index = 1;
        while (true) {
            $offset = ($index - 1) * 1000;
            $query = PatientInfo::query();
            if ($zyh) {
                $query = $query->whereIn('MED_REC_ID', $zyh);
            } else {
                $query = $query->whereBetween('AAC01', [$startTime, $endTime]);
            }
            $resData = $query->orderBy('AAC01', 'asc')
                ->offset($offset)
                ->limit(1000)
                ->get(['MED_REC_ID', 'AAC04', 'AAB01', 'AAC01', 'AAA28', 'AAA29'])->toArray();
            if (empty($resData)) {
                break;
            }
            $index++;
            // 数据处理
            foreach ($resData as $info) {
                var_dump($info['MED_REC_ID'].' - '.$info['AAB01'].' - '.$info['AAC01']);
                $banhzkgzService->zkgzHandle($caseRule, $info, 99);
            }
        }
    }
}
