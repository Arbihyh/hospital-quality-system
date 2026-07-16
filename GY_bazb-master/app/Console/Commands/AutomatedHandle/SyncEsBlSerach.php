<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\Bllb1;
use App\Model\Bllb292;
use App\Model\Bllb294_295;
use App\Model\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncEsBlSerach extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:bl_serach {startDate?} {endDate?}';

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
        $setName = 'es_index_bl_serach_2023';
        $index = 'bl_serach_2023';

        $startTime = $this->argument('startDate') ?: '';
        $endTime = $this->argument('endDate') ?: '';
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
            $field = ['a.MED_REC_ID as id','a.id as data_id','a.AAA28','a.MED_REC_ID as ZYH','a.AAA01','a.AAA02C','a.AAA03','a.AAA04','a.AAA05C','a.AAA40','a.AAA42','a.AEN01','a.AAA06C','a.AAA07','a.AAA08C','a.AEM01C','a.AAB01','a.AAC01','a.AAC11N','a.AAC04','a.ADA01','a.ADA0101','a.AAA29','a.AAB06C','a.ABC01N','a.ICD9_NAME','b.ICD10_NAME as MD_ICD10_NAME','b.ICD10_ID1 as MD_ICD10_ID1','c.ICD9_NAME as MO_ICD9_NAME','c.ICD9_ID1 as MO_ICD9_ID1'];
            $data = DB::table('patient_info as a')
                ->leftJoin('main_diagnosis as b','a.MED_REC_ID','=','b.AAA28')
                ->leftJoin('main_operation as c','a.MED_REC_ID','=','c.AAA28')
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
                $ZYH = $value->ZYH;
                echo $ZYH.PHP_EOL;

                // 入院记录
                $ryjlData = Bllb292::query()
                    ->leftJoin('EMR_BL_BLXG','bllb292.BLBH','=','EMR_BL_BLXG.BLBH')
                    ->first(['ZYH','ZHS','XBS','JWS','GRS','HYS','YJJHYS','JZS','TGJC','FZJC','CBZD','ZHUANKE','CBZB_FIRST','HJNR']);

                // 出院记录
                $cyjlData = Bllb1::query()
                    ->leftJoin('EMR_BL_BLXG','bllb1.BLBH','=','EMR_BL_BLXG.BLBH')
                    ->first(['ZYH','RYQK','CBZD','CBZD_FIRST','ZLJG','CYQK','CYZD','CYZD_FIRST','CYYZ','HJNR']);

                // 病程记录-首次病程
                $bcjlScbcData = Bllb294_295::query()
                    ->leftJoin('EMR_BL_BLXG','bllb294_295.BLBH','=','EMR_BL_BLXG.BLBH')
                    ->first(['ZYH','BLTD','CBZD','CBZD_ONE','CBZD_OTHER','ZDYJ','JBZD','JBZDMC','ZLJH','HJNR']);

                // 病程记录-输血病程
                $bcjlSxbcData = DB::select("SELECT ZYH,JSON_ARRAYAGG(JSON_OBJECT('data_id',bllb294_45.id,'ZYH',ZYH,'TIWEN',TIWEN,'JCZB',JCZB,'JCJG',JCJG,'SXKSSJ',SXKSSJ,'SXJSSJ',SXJSSJ,'SZZ',SZZ,'HDZ',HDZ,'HJNR',HJNR)) as bllb294_45 FROM bllb294_45 left join EMR_BL_BLXG on bllb294_45.BLBH=EMR_BL_BLXG.BLBH where ZYH=".$ZYH." group by ZYH");
                $bllb294_45 = !empty($bcjlSxbcData) ? json_decode($bcjlSxbcData[0]->bllb294_45,true) : [];

                // 手术记录
                $ssjlData = DB::select("SELECT ZYH,JSON_ARRAYAGG(JSON_OBJECT('bllb303_id',bllb303.id,'ZYH',ZYH,'SQZD',SQZD,'SZZD',SZZD,'SZZD_ONE',SZZD_ONE,'SSMC',SSMC,'SSMC_ONE',SSMC_ONE,'SSZD',SSZD,'SSZ',SSZ,'ZS',ZS,'CH',CH,'SSRQ',SSRQ,'HJNR',HJNR)) as bllb303 FROM bllb303 left join EMR_BL_BLXG on bllb303.BLBH=EMR_BL_BLXG.BLBH where ZYH=".$ZYH." group by ZYH");
                $bllb303 = !empty($ssjlData) ? json_decode($ssjlData[0]->bllb303,true) : [];

                // 其它诊断
                $odData = DB::select("SELECT AAA28,JSON_ARRAYAGG(JSON_OBJECT('AAA28',AAA28,'ICD10_NAME',ICD10_NAME,'ICD10_ID1',ICD10_ID1,'RYQK',RYQK)) as other_diagnosis FROM other_diagnosis where AAA28=".$ZYH." group by AAA28");
                $other_diagnosis = !empty($odData) ? json_decode($odData[0]->other_diagnosis,true) : [];

                // 其它手术
                $sobData = DB::select("SELECT AAA28,JSON_ARRAYAGG(JSON_OBJECT('AAA28',AAA28,'ICD9_NAME',ICD9_NAME,'ICD9_ID1',ICD9_ID1,'OPE_LEVEL',OPE_LEVEL,'SSPB',SSPB,'OPE_TYPE',OPE_TYPE,'RJSS',RJSS)) as secondary_operation FROM secondary_operation where AAA28=".$ZYH." group by AAA28");
                $secondary_operation = !empty($sobData) ? json_decode($sobData[0]->secondary_operation,true) : [];

                // 病程类
                $bllb294Data = DB::select("SELECT JZHM,JSON_ARRAYAGG(JSON_OBJECT('JZHM',JZHM,'HJNR',HJNR)) as bllb294 FROM EMR_BL_BL01 left join EMR_BL_BLXG on EMR_BL_BL01.BLBH=EMR_BL_BLXG.BLBH where JZHM=".$ZYH." and BLLB=294 and HJNR!='' group by JZHM");
                $bllb294 = !empty($bllb294Data) ? json_decode($bllb294Data[0]->bllb294,true) : [];

                // 手术类
                $ssjlData = DB::select("SELECT JZHM,JSON_ARRAYAGG(JSON_OBJECT('JZHM',JZHM,'HJNR',HJNR)) as ssjl FROM EMR_BL_BL01 left join EMR_BL_BLXG on EMR_BL_BL01.BLBH=EMR_BL_BLXG.BLBH where JZHM=".$ZYH." and BLLB=303 and HJNR!='' group by JZHM");
                $ssjl = !empty($ssjlData) ? json_decode($ssjlData[0]->ssjl,true) : [];

                // 要同步到Es的数据
                $es_params = [];
                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $ZYH]];
                $es_params['body'][] = ['doc' => [
                    'hospital_name' => config('confAdmin.hospital_name'),
                    "data_id" => $value->id,
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
                    "MD_ICD10_NAME" => $value->MD_ICD10_NAME,
                    "MD_ICD10_ID1" => $value->MD_ICD10_ID1,
                    "MO_ICD9_NAME" => $value->MO_ICD9_NAME,
                    "MO_ICD9_ID1" => $value->MO_ICD9_ID1,
                    "RYJL_ZHS" => $ryjlData->RYJL_ZHS ?? '',
                    "RYJL_XBS" => $ryjlData->RYJL_XBS ?? '',
                    "RYJL_JWS" => $ryjlData->RYJL_JWS ?? '',
                    "RYJL_GRS" => $ryjlData->RYJL_GRS ?? '',
                    "RYJL_HYS" => $ryjlData->RYJL_HYS ?? '',
                    "RYJL_YJJHYS" => $ryjlData->RYJL_YJJHYS ?? '',
                    "RYJL_JZS" => $ryjlData->RYJL_JZS ?? '',
                    "RYJL_TGJC" => $ryjlData->RYJL_TGJC ?? '',
                    "RYJL_FZJC" => $ryjlData->RYJL_FZJC ?? '',
                    "RYJL_CBZD" => $ryjlData->RYJL_CBZD ?? '',
                    "RYJL_ZHUANKE" => $ryjlData->RYJL_ZHUANKE ?? '',
                    "RYJL_CBZB_FIRST" => $ryjlData->RYJL_CBZB_FIRST ?? '',
                    "RYJL_HJNR" => $ryjlData->RYJL_HJNR ?? '',
                    "CYJL_RYQK" => $cyjlData->CYJL_RYQK ?? '',
                    "CYJL_CBZD" => $cyjlData->CYJL_CBZD ?? '',
                    "CYJL_CBZD_FIRST" => $cyjlData->CYJL_CBZD_FIRST ?? '',
                    "CYJL_ZLJG" => $cyjlData->CYJL_ZLJG ?? '',
                    "CYJL_CYQK" => $cyjlData->CYJL_CYQK ?? '',
                    "CYJL_CYZD" => $cyjlData->CYJL_CYZD ?? '',
                    "CYJL_CYZD_FIRST" => $cyjlData->CYJL_CYZD_FIRST ?? '',
                    "CYJL_CYYZ" => $cyjlData->CYJL_CYYZ ?? '',
                    "CYJL_HJNR" => $cyjlData->CYJL_HJNR ?? '',
                    "BCJL_SCBC_BLTD" => $bcjlScbcData->BCJL_SCBC_BLTD ?? '',
                    "BCJL_SCBC_CBZD" => $bcjlScbcData->BCJL_SCBC_CBZD ?? '',
                    "BCJL_SCBC_CBZD_ONE" => $bcjlScbcData->BCJL_SCBC_CBZD_ONE ?? '',
                    "BCJL_SCBC_CBZD_OTHER" => $bcjlScbcData->BCJL_SCBC_CBZD_OTHER ?? '',
                    "BCJL_SCBC_ZDYJ" => $bcjlScbcData->BCJL_SCBC_ZDYJ ?? '',
                    "BCJL_SCBC_JBZD" => $bcjlScbcData->BCJL_SCBC_JBZD ?? '',
                    "BCJL_SCBC_JBZDMC" => $bcjlScbcData->BCJL_SCBC_JBZDMC ?? '',
                    "BCJL_SCBC_ZLJH" => $bcjlScbcData->BCJL_SCBC_ZLJH ?? '',
                    "BCJL_SCBC_HJNR" => $bcjlScbcData->BCJL_SCBC_HJNR ?? '',
                    "bllb294_45" => $bllb294_45,
                    "bllb303" => $bllb303,
                    "other_diagnosis" => $other_diagnosis,
                    "secondary_operation" => $secondary_operation,
                    "bllb294" => $bllb294,
                    "ssjl" => $ssjl,
                ], 'doc_as_upsert' => true];

                app('es')->bulk($es_params);
            }

            Setting::query()->where('name', '=', $setName)->update(['content' => date('Y-m-d',strtotime($endTime))]);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
