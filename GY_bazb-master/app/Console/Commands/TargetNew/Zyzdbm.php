<?php

namespace App\Console\Commands\TargetNew;

use App\Model\Error;
use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Model\PatientInfoTargetTemporary;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class Zyzdbm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newZb:zyzdbm {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '主要诊断编码正确率 - 数据处理';

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
        $this->info('主要诊断编码正确率 - 开始处理');

        $page = (int)$this->argument('page') ?: 1;
        $this->zyzdbmDataHandle($page);

        $this->info('主要诊断编码正确率 - 处理完毕');
    }

    protected function zyzdbmDataHandle($page)
    {
        $errorService = new ElasticsearchService('error');
        $mainDiagnosisService = new ElasticsearchService('main_diagnosis');
        $bl01NewService = new ElasticsearchService('emr_bl_bl01_new');

        while (true) {
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->whereBetween('AAC01',['2022-01-01 00:00:00','2023-12-31 23:59:59'])
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

                // 编码员主要诊断
                $mainDiagnosisInfo = $this->mainDiagnosis($mainDiagnosisService,$ZYH);
                $ICD10_ID1 = !empty($mainDiagnosisInfo['ICD10_ID1']) ? $mainDiagnosisInfo['ICD10_ID1'] : '无';
                $ICD10_NAME = !empty($mainDiagnosisInfo['ICD10_NAME']) ? $mainDiagnosisInfo['ICD10_NAME'] : '无';

                // 质控后主要诊断
                $errorDesc = $this->errorData($errorService,$ZYH);
                if ($errorDesc) {
                    $numerator = 0;
                    $errorMsg = '编码员主要诊断编码【'.$ICD10_NAME.'，'.$ICD10_ID1.'】质控后主要诊断编码【'.$errorDesc.'（错误）】';
                } else {
                    $numerator = 1;
                    if ($ICD10_NAME=='无' || $ICD10_ID1=='无') {
                        $numerator = 0;
                    }
                    $errorMsg = '编码员主要诊断编码【'.$ICD10_NAME.'，'.$ICD10_ID1.'】质控后主要诊断编码【正确】';
                }

                // 记录
                $saveData = ['denominator_zyzdbm'=>1,'numerator_zyzdbm'=>$numerator,'zyzdbm_error'=>$errorMsg];
                PatientInfoTargetTemporary::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
            }
        }

        return true;
    }

    public function mainDiagnosis($mainDiagnosisService,$ZYH)
    {
        $must = [
            ["term" => ['ZYH' => $ZYH]]
        ];

        $params = $mainDiagnosisService->clearMust()
            ->queryByMustBatch($must)
            ->getParams();
        $restful = app('es')->search($params);
        $mainDiagnosisData = $mainDiagnosisService->getDataByEs($restful);

        return !empty($mainDiagnosisData[0]) ? $mainDiagnosisData[0][0] : [];
    }

    protected function errorData($errorService,$ZYH)
    {
        $must = [
            ["term" => ['ZYH' => $ZYH]],
            ["term" => ['error_field' => 'ABC01N']]
        ];
        $params = $errorService->clearMust()->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $errorData = $errorService->getDataByEs($restful);
        $errorDesc = !empty($errorData[0][0]['desc']) ? $errorData[0][0]['desc'] : '';

        return $errorDesc;
    }


}
