<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\Setting;
use App\Services\ElasticsearchService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncEsQuality2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:quality2 {startTime?} {endTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $setName = 'es_index_quality2';
        $index = 'quality2';

        $startTime = $this->argument('startTime') ?: '';
        $endTime = $this->argument('endTime') ?: '';
        if ($startTime && $endTime) {
            $fieldName = 'p.AAC01';
            $startTime = $startTime.' 00:00:00';
            $endTime = $endTime.' 23:59:59';
        } else {
            $fieldName = 'p.created_at';
            $startTime = Carbon::parse()->addDay(-5)->toDateString().' 00:00:00';
            $endTime = Carbon::parse()->addDay(-1)->toDateString().' 23:59:59';
        }

        $yzbService = new ElasticsearchService('yzb_2023');
        $feeService = new ElasticsearchService('fee_detailed');
        $pacsService = new ElasticsearchService('pacs');

        $page = 1;
        while (true) {
            $field = ['p.MED_REC_ID','p.AAA28','p.AAC11N','p.AAB01','p.AAC01','p.AAA04','p.AAA40','p.AEM01C','p.AAC04','p.ADA01','p.AAA29','p.AAB06C','p.AAA26C','p.created_at','p.AAC01','md.ICD10_NAME','md.ICD10_ID1'];
            $data = DB::table('patient_info as p')
                ->leftJoin('EMR_BL_BL01 as a','p.MED_REC_ID','=','a.JZHM')
                ->leftJoin('main_diagnosis as md','p.MED_REC_ID','=','md.AAA28')
                ->where('p.hospital_name','=',config('confAdmin.hospital_name'))
                ->whereBetween($fieldName,[$startTime,$endTime])
                ->distinct()
                ->paginate(100, $field, 'page', $page)
                ->toArray();
            if (empty($data['data'])) {
                break;
            }
            $page++;

            foreach ($data['data'] as $value) {
                $ZYH = $value->MED_REC_ID;

                echo $ZYH.PHP_EOL;

                // 病历
                $EMR_BL_BL01Data = DB::select("SELECT DISTINCT a.JZHM,a.EMR_BL_BL01 FROM (select a.JZHM, JSON_OBJECTAGG(a.BLLB, a.HJNR) EMR_BL_BL01 from (select a.JZHM,a.BLLB, CONVERT(b.HJNR USING utf8mb4) HJNR from EMR_BL_BL01 a LEFT JOIN EMR_BL_BLXG b on a.BLBH = b.BLBH WHERE HJNR is not null) a LEFT JOIN patient_info p on a.JZHM = p.MED_REC_ID WHERE p.MED_REC_ID=".$ZYH." GROUP BY a.JZHM) a LEFT JOIN patient_info p on a.JZHM = p.MED_REC_ID");
                $EMR_BL_BL01 = !empty($EMR_BL_BL01Data) ? json_decode($EMR_BL_BL01Data[0]->EMR_BL_BL01, true) : [];

                // 医嘱
//                $yzbData = DB::select("select ZYH,JSON_ARRAYAGG(JSON_OBJECT('YZMC',YZMC)) yzb from (select ZYH,YZMC from yzb GROUP BY ZYH,YZMC) a where ZYH=".$ZYH." GROUP BY ZYH");
//                $YZB = !empty($yzbData) ? $yzbData[0]->yzb : [];

                $must = ['term' => ['ZYH' => $ZYH]];
                $params = $yzbService->clearMust()
                    ->queryByMust($must)
                    ->paginate(1,10000)
                    ->source(['YZMC'])
                    ->getParams();
                $result = app('es')->search($params);
                $yzbData = $yzbService->getDataByEs($result);
                $YZB = !empty($yzbData[0]) ? $yzbData[0] : [];

                // 费用
//                $feeData = DB::select("select AAA28,JSON_ARRAYAGG(JSON_OBJECT('FYMC',FYMC)) fee_detailed from (select AAA28,FYMC from fee_detailed GROUP BY AAA28,FYMC) a where AAA28=".$ZYH." GROUP BY AAA28");
//                $Fee_detailed = !empty($feeData) ? $feeData[0]->fee_detailed : [];
                $must = ['term' => ['MED_REC_ID' => $ZYH]];
                $params = $feeService->clearMust()
                    ->queryByMust($must)
                    ->paginate(1,10000)
                    ->source(['FYMC'])
                    ->getParams();
                $result = app('es')->search($params);
                $feeData = $feeService->getDataByEs($result);
                $Fee_detailed = !empty($feeData[0]) ? $feeData[0] : [];

                // 报告单
//                $pacsData = DB::select("select ZYH,b.MED_REC_ID,JSON_ARRAYAGG(JSON_OBJECT('JCMC',JCMC)) pacs_jcmc_list from PACS a left join patient_info b on a.ZYH=b.MED_REC_ID where JCMC!='' and b.MED_REC_ID=".$ZYH." GROUP BY ZYH");
//                $pacs_jcmc_list = !empty($pacsData) ? $pacsData[0]->pacs_jcmc_list : [];
                $must = [
                    ['term' => ['JZLSH' => $value->AAA28]],
                    ['range' => ['KDSJ' => ['gte' => $value->AAB01,'lte' => $value->AAC01]]]
                ];
                $params = $pacsService->clearMust()
                    ->queryByMustBatch($must)
                    ->source(['JCMC'])
                    ->paginate(1,1000)
                    ->getParams();
                $restful = app('es')->search($params);
                $pacsData = $pacsService->getDataByEs($restful);
                $pacs_jcmc_list = !empty($pacsData[0]) ? $pacsData[0] : [];

                // 要同步到Es的数据
                $es_params = [];
                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $ZYH]];
                $es_params['body'][] = ['doc' => [
                    'hospital_name' => config('confAdmin.hospital_name'),
                    "MED_REC_ID" => $ZYH,
                    "AAA28" => $value->AAA28,
                    "AAC11N" => $value->AAC11N,
                    "AAB01" => $value->AAB01,
                    "AAC01" => $value->AAC01,
                    "AAA04" => $value->AAA04,
                    "AAA40" => $value->AAA40,
                    "AEM01C" => $value->AEM01C,
                    "AAC04" => $value->AAC04,
                    "AAA29" => $value->AAA29,
                    "AAB06C" => $value->AAB06C,
                    "AAA26C" => $value->AAA26C,
                    "created_at" => $value->created_at,
                    "ICD10_NAME" => $value->ICD10_NAME,
                    "ICD10_ID1" => $value->ICD10_ID1,
                    "EMR_BL_BL01" => $EMR_BL_BL01,
                    "YZB" => $YZB,
                    "Fee_detailed" => $Fee_detailed,
                    "pacs_jcmc_list" => $pacs_jcmc_list,
                ], 'doc_as_upsert' => true];
                app('es')->bulk($es_params);
            }

            Setting::query()->where('name', '=', $setName)->update(['content' => date('Y-m-d',strtotime($endTime))]);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
