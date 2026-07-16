<?php

namespace App\Console\Commands\mysqltoes;

use App\Model\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class PatientInfoTargetTemporary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'es:patient_info_target_temporary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command';

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
     *
     */
    public function handle()
    {
        $setName = 'es_index_patient_info_target_temporary';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $page = 1;
        while (true) {
            $start = ($page - 1) * 20;
            $res = DB::select('SELECT a.id, a.ZYH, a.denominator_bl, a.numerator_bl, a.bl_error, a.denominator_kjyw, a.numerator_kjyw, a.kjyw_error, a.denominator_exzlhxzl, a.numerator_exzlhxzl, a.exzlhxzl_error, a.denominator_exzlfszl, a.numerator_exzlfszl, a.exzlfszl_error, a.denominator_zrw, a.numerator_zrw, a.zrw_name, a.denominator_ct, a.numerator_ct, a.ct_error, a.numerator_bhlbl, a.denominator_bhlbl, a.bhlbl_content, a.denominator_xjpy, a.numerator_xjpy, a.xjpy_error, a.denominator_lcyx, a.numerator_lcyx, a.lcyx_error, a.denominator_cyjl,a.numerator_cyjl,a.cyjl_error,a.denominator_hzqjjl,a.numerator_hzqjjl,a.hzqjjl_error,a.denominator_basy,a.numerator_basy,a.basy_error, a.numerator_operation, a.denominator_operation, a.operation_error,b.AAA02C,b.AAA04,b.AAC04,b.AAA29,b.MED_REC_ID,b.AAA01,b.AAC11N,IF(b.AAB01 != "",b.AAB01,"1970-01-01 00:00:00") as AAB01,IF(b.AAC01 != "",b.AAC01,"1970-01-01 00:00:00") as AAC01,b.AAA28,a.denominator_ryjl,a.numerator_ryjl,a.ryjl_error,a.denominator_operateCompletionRate,a.numerator_operateCompletionRate,a.operateCompletionRate_error,a.denominator_chafangCompletionRate,a.numerator_chafangCompletionRate,a.chafangCompletionRate_error,a.denominator_xjpy1,a.numerator_xjpy1,a.xjpy1_error,a.denominator_cyhzgd,a.numerator_cyhzgd,a.cyhzgd_error,a.denominator_zyzdtx,a.numerator_zyzdtx,a.zyzdtx_error,a.denominator_zysstx,a.numerator_zysstx,a.zysstx_error,a.denominator_zyzdbm,a.numerator_zyzdbm,a.zyzdbm_error,a.denominator_zyssbm,a.numerator_zyssbm,a.zyssbm_error,a.numerator_public_cyjl,a.denominator_hzqjcgl,a.numerator_hzqjcgl,a.hzqjcgl_error,a.denominator_zqtysgfqs,a.numerator_zqtysgfqs,a.zqtysgfqs_error,a.denominator_cyhzgdl,a.numerator_cyhzgdl,a.cyhzgdl_error,a.denominator_jjbl,a.numerator_jjbl,a.jjbl_error,a.denominator_ngzb,a.numerator_ngzb,a.ngzb_describe,a.denominator_xgzb,a.numerator_xgzb,a.xgzb_describe,a.NG_BLBH,a.XG_BLBH,a.denominator_ngrszb,a.numerator_ngrszb,a.ngrszb_describe,a.denominator_A,a.numerator_A,a.score,a.pacs_content FROM `patient_info_target_temporary` as a inner join patient_info as b on a.ZYH=b.MED_REC_ID  where a.AAC01 > "' . $lastId . '" limit ' . $start . ', 20');
            if (empty($res)) {
                return false;
            }
            $page++;
            $res = json_decode(json_encode($res), true);

            // 病例索引
            $es_params = [];
            $index = 'patient_info_target';
            foreach ($res as $item) {
                $lastId = $item['AAC01'];
                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['id']]];
                $es_params['body'][] = ['doc' => [
                    "ZYH" => $item['ZYH'],
                    "denominator_bl" => $item['denominator_bl'],
                    "numerator_bl" => $item['numerator_bl'],
                    "bl_error" => $item['bl_error'],
                    "denominator_kjyw" => $item['denominator_kjyw'],
                    "numerator_kjyw" => $item['numerator_kjyw'],
                    "kjyw_error" => $item['kjyw_error'],
                    "denominator_exzlhxzl" => $item['denominator_exzlhxzl'],
                    "numerator_exzlhxzl" => $item['numerator_exzlhxzl'],
                    "exzlhxzl_error" => $item['exzlhxzl_error'],
                    "denominator_exzlfszl" => $item['denominator_exzlfszl'],
                    "numerator_exzlfszl" => $item['numerator_exzlfszl'],
                    "exzlfszl_error" => $item['exzlfszl_error'],
                    "denominator_zrw" => $item['denominator_zrw'],
                    "numerator_zrw" => $item['numerator_zrw'],
                    "zrw_" => $item['zrw_'],
                    "denominator_ct" => $item['denominator_ct'],
                    "numerator_ct" => $item['numerator_ct'],
                    "ct_error" => $item['ct_error'],
                    "numerator_bhlbl" => $item['numerator_bhlbl'],
                    "denominator_bhlbl" => $item['denominator_bhlbl'],
                    "bhlbl_content" => $item['bhlbl_content'],
                    "denominator_xjpy" => $item['denominator_xjpy'],
                    "numerator_xjpy" => $item['numerator_xjpy'],
                    "xjpy_error" => $item['xjpy_error'],
                    "denominator_lcyx" => $item['denominator_lcyx'],
                    "numerator_lcyx" => $item['numerator_lcyx'],
                    "lcyx_error" => $item['lcyx_error'],
                    "denominator_cyjl" => $item['denominator_cyjl'],
                    "numerator_cyjl" => $item['numerator_cyjl'],
                    "cyjl_error" => $item['cyjl_error'],
                    "denominator_hzqjjl" => $item['denominator_hzqjjl'],
                    "numerator_hzqjjl" => $item['numerator_hzqjjl'],
                    "hzqjjl_error" => $item['hzqjjl_error'],
                    "denominator_basy" => $item['denominator_basy'],
                    "numerator_basy" => $item['numerator_basy'],
                    "basy_error" => $item['basy_error'],
                    "numerator_operation" => $item['numerator_operation'],
                    "denominator_operation" => $item['denominator_operation'],
                    "operation_error" => $item['operation_error'],
                    "AAA02C" => $item['AAA02C'],
                    "AAA04" => $item['AAA04'],
                    "AAC04" => $item['AAC04'],
                    "AAA29" => $item['AAA29'],
                    "MED_REC_ID" => $item['MED_REC_ID'],
                    "AAA01" => $item['AAA01'],
                    "AAC11N" => $item['AAC11N'],
                    "AAB01" => $item['AAB01'],
                    "AAC01" => $item['AAC01'],
                    "AAA28" => $item['AAA28'],
                    "denominator_ryjl" => $item['denominator_ryjl'],
                    "numerator_ryjl" => $item['numerator_ryjl'],
                    "ryjl_error" => $item['ryjl_error'],
                    "denominator_operateCompletionRate" => $item['denominator_operateCompletionRate'],
                    "numerator_operateCompletionRate" => $item['numerator_operateCompletionRate'],
                    "operateCompletionRate_error" => $item['operateCompletionRate_error'],
                    "denominator_chafangCompletionRate" => $item['denominator_chafangCompletionRate'],
                    "numerator_chafangCompletionRate" => $item['numerator_chafangCompletionRate'],
                    "chafangCompletionRate_error" => $item['chafangCompletionRate_error'],
                    "denominator_xjpy1" => $item['denominator_xjpy1'],
                    "numerator_xjpy1" => $item['numerator_xjpy1'],
                    "xjpy1_error" => $item['xjpy1_error'],
                    "denominator_cyhzgd" => $item['denominator_cyhzgd'],
                    "numerator_cyhzgd" => $item['numerator_cyhzgd'],
                    "cyhzgd_error" => $item['cyhzgd_error'],
                    "denominator_zyzdtx" => $item['denominator_zyzdtx'],
                    "numerator_zyzdtx" => $item['numerator_zyzdtx'],
                    "zyzdtx_error" => $item['zyzdtx_error'],
                    "denominator_zysstx" => $item['denominator_zysstx'],
                    "numerator_zysstx" => $item['numerator_zysstx'],
                    "zysstx_error" => $item['zysstx_error'],
                    "denominator_zyzdbm" => $item['denominator_zyzdbm'],
                    "numerator_zyzdbm" => $item['numerator_zyzdbm'],
                    "zyzdbm_error" => $item['zyzdbm_error'],
                    "denominator_zyssbm" => $item['denominator_zyssbm'],
                    "numerator_zyssbm" => $item['numerator_zyssbm'],
                    "zyssbm_error" => $item['zyssbm_error'],
                    "numerator_public_cyjl" => $item['numerator_public_cyjl'],
                    "denominator_hzqjcgl" => $item['denominator_hzqjcgl'],
                    "numerator_hzqjcgl" => $item['numerator_hzqjcgl'],
                    "hzqjcgl_error" => $item['hzqjcgl_error'],
                    "denominator_zqtysgfqs" => $item['denominator_zqtysgfqs'],
                    "numerator_zqtysgfqs" => $item['numerator_zqtysgfqs'],
                    "zqtysgfqs_error" => $item['zqtysgfqs_error'],
                    "denominator_cyhzgdl" => $item['denominator_cyhzgdl'],
                    "numerator_cyhzgdl" => $item['numerator_cyhzgdl'],
                    "cyhzgdl_error" => $item['cyhzgdl_error'],
                    "denominator_jjbl" => $item['denominator_jjbl'],
                    "numerator_jjbl" => $item['numerator_jjbl'],
                    "jjbl_error" => $item['jjbl_error'],
                    "denominator_ngzb" => $item['denominator_ngzb'],
                    "numerator_ngzb" => $item['numerator_ngzb'],
                    "ngzb_describe" => $item['ngzb_describe'],
                    "denominator_xgzb" => $item['denominator_xgzb'],
                    "numerator_xgzb" => $item['numerator_xgzb'],
                    "xgzb_describe" => $item['xgzb_describe'],
                    "NG_BLBH" => $item['NG_BLBH'],
                    "XG_BLBH" => $item['XG_BLBH'],
                    "denominator_ngrszb" => $item['denominator_ngrszb'],
                    "numerator_ngrszb" => $item['numerator_ngrszb'],
                    "ngrszb_describe" => $item['ngrszb_describe'],
                    "denominator_A" => $item['denominator_A'],
                    "numerator_A" => $item['numerator_A'],
                    "score" => $item['score'],
                ], 'doc_as_upsert' => true];
            }
            app('es')->bulk($es_params);
            Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        }
    }

}
