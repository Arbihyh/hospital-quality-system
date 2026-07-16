<?php

namespace App\Console\Commands\Target;

use App\Model\Error;
use App\Model\MainOperation;
use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class Zyssbm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zb:zyssbm {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '主要手术编码正确率 - 数据处理';

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
        $this->info('主要手术编码正确率 - 开始处理');

        $page = (int)$this->argument('page') ?: 1;
        $this->zyssbmDataHandle($page);

        $this->info('主要手术编码正确率 - 处理完毕');
    }

    protected function zyssbmDataHandle($page)
    {
        $conf = config('confAdmin');
        $endTime = date('Y-m-d H:i:s', time());
        $bl01NewService = new ElasticsearchService('emr_bl_bl01_new');
        $errorService = new ElasticsearchService('error');
        $emrBlBasysjService = new ElasticsearchService('emr_bl_basysj');
        $mainOperationService = new ElasticsearchService('main_operation');

        while (true) {
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->whereBetween('AAC01',[$conf['zb_start_time'], $endTime])
                ->paginate(500, ['AAA28','MED_REC_ID','AAB01','AAC01','ABC01N'],'page', $page)
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
                $ZYH = $value['MED_REC_ID'];

                // 编码员主要手术编码
                $mainOperationInfo = $this->mainOperation($mainOperationService,$ZYH);
                $ICD9_ID1 = !empty($mainOperationInfo['ICD9_ID1']) ? $mainOperationInfo['ICD9_ID1'] : '';
                $ICD9_NAME = !empty($mainOperationInfo['ICD9_NAME']) ? $mainOperationInfo['ICD9_NAME'] : '';
                if (!$ICD9_NAME) {
                    continue;
                }

                // 质控后主要手术编码
                $errorDesc = $this->errorData($errorService,$ZYH);
                if ($errorDesc) {
                    $numerator = 0;
                    $errorMsg = '编码员主要手术编码【'.$ICD9_NAME.'，'.$ICD9_ID1.'】质控后主要手术编码【'.$errorDesc.'（无效）】';
                } else {
                    $numerator = 1;
                    $errorMsg = '编码员主要手术编码【'.$ICD9_NAME.'，'.$ICD9_ID1.'】质控后主要手术编码【正确】';
                }

                // 记录
                $saveData = ['denominator_zyssbm'=>1,'numerator_zyssbm'=>$numerator,'zyssbm_error'=>$errorMsg];
                PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
            }
        }

        return true;
    }

    protected function errorData($errorService,$ZYH)
    {
        $must = [
            ["term" => ['ZYH' => $ZYH]],
            ["term" => ['error_field' => 'ICD9_NAME']]
        ];
        $params = $errorService->clearMust()->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $errorData = $errorService->getDataByEs($restful);
        $errorDesc = !empty($errorData[0][0]['desc']) ? $errorData[0][0]['desc'] : '';

        return $errorDesc;
    }

    protected function bl01NewData($bl01NewService,$ZYH)
    {
        $must = [
            ["term" => ['JZHM' => $ZYH]],
            ["term" => ['BLLB' => 2000001]]
        ];
        $notMust = [
            ["term" => ['BLZT' => 9]]
        ];
        $params = $bl01NewService->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNotBatch($notMust)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01NewData = $bl01NewService->getDataByEs($restful);
        $blbh = !empty($bl01NewData[0]) ? $bl01NewData[0][0]['BLBH'] : '';

        return $blbh;
    }

    protected function mainOperation($mainOperationService,$ZYH)
    {
        $must = [
            ["term" => ['AAA28' => $ZYH]],
        ];
        $params = $mainOperationService->clearMust()->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $mainOperationData = $mainOperationService->getDataByEs($restful);
        $ICD9_NAME = !empty($mainOperationData[0][0]['ICD9_NAME']) ? $mainOperationData[0][0]['ICD9_NAME'] : '';
        $ICD9_ID1 = !empty($mainOperationData[0][0]['ICD9_ID1']) ? $mainOperationData[0][0]['ICD9_ID1'] : '';
        return ['ICD9_NAME'=>$ICD9_NAME,'ICD9_ID1'=>$ICD9_ID1];
    }

    protected function emrBlBasysjData($emrBlBasysjService,$blbh)
    {
        $must = [
            ["term" => ['BLBH' => $blbh]],
            ["term" => ['XMXH' => 638]]
        ];
        $params = $emrBlBasysjService->clearMust()->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $emrBlBasysjData = $emrBlBasysjService->getDataByEs($restful);
        $xmqzList = [];
        if (!empty($emrBlBasysjData[0])) {
            foreach ($emrBlBasysjData[0] as $val) {
                if (!empty($val['XMQZ'])) {
                    $xmqzList[] = $val['XMQZ'];
                }
            }
        }

        return $xmqzList;
    }


}
