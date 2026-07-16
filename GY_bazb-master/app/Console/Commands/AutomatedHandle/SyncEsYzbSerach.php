<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncEsYzbSerach extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:yz_serach {startTime?} {endTime?}';

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
        $setName = 'es_index_yz_serach_2023';
        $index = 'yz_serach_2023';

        $startTime = $this->argument('startTime') ?: '';
        $endTime = $this->argument('endTime') ?: '';
        if ($startTime && $endTime) {
            $fieldName = 'a.AAC01';
            $startTime = $startTime.' 00:00:00';
            $endTime = $endTime.' 23:59:59';
        } else {
            $fieldName = 'a.created_at';
            $startTime = Carbon::parse()->addDay(-5)->toDateString().' 00:00:00';
            $endTime = Carbon::parse()->addDay(-1)->toDateString().' 23:59:59';
        }

        $page = 1;
        while (true) {
            // 所有符合条件的病例信息
            $field = ['a.MED_REC_ID','a.AAA28','a.MED_REC_ID as ZYH','a.AAA01','a.AAA02C','a.AAA03','a.AAA04','a.AAA05C','a.AAA40','a.AAA42','a.AEN01','a.AAA06C','a.AAA07','a.AAA08C','a.AEM01C','a.AAB01','a.AAC01','a.AAC11N','a.AAC04','a.ADA01','a.ADA0101','a.AAA29','a.AAB06C','a.ABC01N','a.ICD9_NAME','a.ORG_STATE','a.AAA26C','a.ATTEND_GRP_CODE','a.ATTEND_GRP_NAME','a.F_D','a.J','a.coder_id','a.score','a.is_error','a.ABG01N','a.ABG01C','a.status','a.created_at','a.AAC01','a.source','a.level','b.ICD10_NAME as MD_ICD10_NAME','b.ICD10_ID1 as MD_ICD10_ID1','b.RYQK as MD_RYQK','c.ABF01N as PMI_ABF01N','c.ABF01C as PMI_ABF01C','d.ICD9_NAME as MO_ICD9_NAME','d.ICD9_ID1 as MO_ICD9_ID1','d.OPE_LEVEL as MO_OPE_LEVEL','d.SSPB as MO_SSPB','d.OPE_TYPE as MO_OPE_TYPE','c.ABA01N as PMI_ABA01N','c.ABA01C as PMI_ABA01C','d.RJSS as MO_RJSS'];
            $data = DB::table('patient_info as a')
                ->leftJoin('main_diagnosis as b','a.MED_REC_ID','=','b.AAA28')
                ->leftJoin('patient_medical_info as c','a.MED_REC_ID','=','c.AAA28')
                ->leftJoin('main_operation as d','a.MED_REC_ID','=','d.AAA28')
                ->where('a.hospital_name','=',config('confAdmin.hospital_name'))
                ->whereBetween($fieldName,[$startTime,$endTime])
                ->paginate(100, $field, 'page', $page)
                ->toArray();
            if (empty($data['data'])) {
                // 如果当前页码大于最大页码则结束
                break;
            }
            $page++;

            foreach ($data['data'] as $value) {
                $ZYH = $value->MED_REC_ID;
                echo $ZYH.PHP_EOL;

                // 医嘱数据查询
                $yzbData = DB::select("SELECT ZYH,JSON_ARRAYAGG(JSON_OBJECT('ZYH',ZYH,'YZMC',YZMC,'KZKS',KZKS,'YZQX',YZQX,'YYSX',YYSX,'XMLB',XMLB,'BRKS',BRKS,'KZSJ',date_format(KZSJ,'%Y-%m-%d %H:%i:%s'))) as yzb FROM yzb where ZYH=".$ZYH." group by ZYH");
                $yzb = !empty($yzbData) ? json_decode($yzbData[0]->yzb, true) : [];

                // 其它诊断
                $odData = DB::select("SELECT AAA28,JSON_ARRAYAGG(JSON_OBJECT('AAA28',AAA28,'ICD10_NAME',ICD10_NAME,'ICD10_ID1',ICD10_ID1,'RYQK',RYQK)) as other_diagnosis FROM other_diagnosis where AAA28=".$ZYH." group by AAA28");
                $other_diagnosis = !empty($odData) ? json_decode($odData[0]->other_diagnosis) : [];

                // 其它手术
                $sobData = DB::select("SELECT AAA28,JSON_ARRAYAGG(JSON_OBJECT('AAA28',AAA28,'ICD9_NAME',ICD9_NAME,'ICD9_ID1',ICD9_ID1,'OPE_LEVEL',OPE_LEVEL,'SSPB',SSPB,'OPE_TYPE',OPE_TYPE,'RJSS',RJSS)) as secondary_operation FROM secondary_operation where AAA28=".$ZYH." group by AAA28");
                $secondary_operation = !empty($sobData) ? json_decode($sobData[0]->secondary_operation) : [];

                // 要同步到Es的数据
                $es_params = [];
                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $ZYH]];
                $es_params['body'][] = ['doc' => [
                    'hospital_name' => config('confAdmin.hospital_name'),
                    "AAA28" => $value->AAA28,
                    "ZYH" => $value->ZYH,
                    "AAA01" => $value->AAA01,
                    "AAA02C" => $value->AAA02C,
                    "AAA03" => $value->AAA03,
                    "AAA04" => $value->AAA04,
                    "AAA05C" => $value->AAA05C,
                    "AAA40" => $value->AAA40,
                    "AAA42" => $value->AAA42,
                    "AEN01" => $value->AEN01,
                    "AAA06C" => $value->AAA06C,
                    "AAA07" => $value->AAA07,
                    "AAA08C" => $value->AAA08C,
                    "AEM01C" => $value->AEM01C,
                    "AAB01" => $value->AAB01,
                    "AAC01" => $value->AAC01,
                    "AAC11N" => $value->AAC11N,
                    "AAC04" => $value->AAC04,
                    "ADA01" => $value->ADA01,
                    "ADA0101" => $value->ADA0101,
                    "AAA29" => $value->AAA29,
                    "AAB06C" => $value->AAB06C,
                    "ABC01N" => $value->ABC01N,
                    "ICD9_NAME" => $value->ICD9_NAME,
                    "ORG_STATE" => $value->ORG_STATE,
                    "AAA26C" => $value->AAA26C,
                    "ATTEND_GRP_CODE" => $value->ATTEND_GRP_CODE,
                    "ATTEND_GRP_NAME" => $value->ATTEND_GRP_NAME,
                    "F_D" => $value->F_D,
                    "J" => $value->J,
                    "coder_id" => $value->coder_id,
                    "score" => $value->score,
                    "is_error" => $value->is_error,
                    "ABG01N" => $value->ABG01N,
                    "ABG01C" => $value->ABG01C,
                    "status" => $value->status,
                    "created_at" => $value->created_at,
                    "AAC01" => $value->AAC01,
                    "source" => $value->source,
                    "level" => $value->level,
                    "MD_ICD10_NAME" => $value->MD_ICD10_NAME,
                    "MD_ICD10_ID1" => $value->MD_ICD10_ID1,
                    "MD_RYQK" => $value->MD_RYQK,
                    "PMI_ABF01N" => $value->PMI_ABF01N,
                    "PMI_ABF01C" => $value->PMI_ABF01C,
                    "MO_ICD9_NAME" => $value->MO_ICD9_NAME,
                    "MO_ICD9_ID1" => $value->MO_ICD9_ID1,
                    "MO_OPE_LEVEL" => $value->MO_OPE_LEVEL,
                    "MO_SSPB" => $value->MO_SSPB,
                    "MO_OPE_TYPE" => $value->MO_OPE_TYPE,
                    "PMI_ABA01N" => $value->PMI_ABA01N,
                    "PMI_ABA01C" => $value->PMI_ABA01C,
                    "MO_RJSS" => $value->MO_RJSS,
                    "yzb" => $yzb,
                    "other_diagnosis" => $other_diagnosis,
                    "secondary_operation" => $secondary_operation,
                ], 'doc_as_upsert' => true];

                app('es')->bulk($es_params);
            }

            Setting::query()->where('name', '=', $setName)->update(['content' => date('Y-m-d',strtotime($endTime))]);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
