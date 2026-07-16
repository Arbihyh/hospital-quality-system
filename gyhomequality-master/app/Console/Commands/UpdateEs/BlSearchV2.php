<?php

namespace App\Console\Commands\UpdateEs;

use App\Model\FeeDetailed;
use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\OtherDiagnosis;
use App\Model\Pacs;
use App\Model\PatientInfo;
use App\Model\SSSQ;
use App\Model\TESTRESULT;
use App\Model\YMRESULT;
use App\Model\Yzb;
use Illuminate\Console\Command;

class BlSearchV2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:updateEs
     */
    protected $signature = 'es:bl_search_v2 {start?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步ES中bl_search索引的数据，应用于2024年9月份的病例搜索改版';

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
        $star = $this->argument('start');
        $star = $star ?? 7;
        echo 'start bl_search：' . date("Y-m-d H:i:s");
        $yesterday = date("Y-m-d 00:00:00", time() - $star * 24 * 3600);
        $patientInfo = PatientInfo::query()->where('AAC01', '>=', $yesterday)->where("in_hospital", "=", 2)->get(["MED_REC_ID"])->toArray();
        echo 'end bl_search：' . date("Y-m-d H:i:s");
        $this->homeBl($patientInfo);
    }

    public function homeBl($patientInfo = [])
    {
        if (empty($patientInfo)) {
            return false;
        }
        $index = 'bl_search';

        foreach ($patientInfo as $val) {
            echo $val['MED_REC_ID'] . "\r\n";
            $esParams = [];
            $doc = ["id" => $val['MED_REC_ID']];

            // 主要诊断
            $mainDiagnosis = MainDiagnosis::query()->where("AAA28", '=', $val['MED_REC_ID'])->get(["ICD10_ID1 AS MD_ICD10_ID1", "ICD10_NAME AS MD_ICD10_NAME", "RYQK AS MD_RYQK"])->toArray();
            if (!empty($mainDiagnosis)) {
                echo "main_diagnosis-主要诊断 \r\n";
                $doc['main_diagnosis'] = $mainDiagnosis;
                $doc = array_merge($doc, $mainDiagnosis[0]);
            }
            // 其他诊断
            $otherDiagnosis = OtherDiagnosis::query()->where("AAA28", '=', $val['MED_REC_ID'])->get(["ICD10_ID1 AS O_ICD10_ID1", "ICD10_NAME AS O_ICD10_NAME", "RYQK AS O_RYQK"])->toArray();
            if (!empty($otherDiagnosis)) {
                echo "other_diagnosis-其他诊断 \r\n";
                $doc['other_diagnosis'] = $otherDiagnosis;
            }
            // 主要手术
            $mainOperation = MainOperation::query()->where("AAA28", '=', $val['MED_REC_ID'])->get(["ICD9_ID1 AS MD_ICD9_ID1", "ICD9_NAME AS MD_ICD9_NAME", "OPE_DATE AS MD_OPE_DATE","OPE_LEVEL AS MD_OPE_LEVEL","SSPB AS MD_SSPB","OPE_MAN_NAME AS OPE_MAN_NAME","FRIST_ASSISTANT_NAME AS MD_FRIST_ASSISTANT_NAME","SECOND_ASSISTANT_CODE AS MD_SECOND_ASSISTANT_CODE","HOCUS_WAY_ID AS MD_HOCUS_WAY_ID","QKDJ AS MD_QKDJ","YHDJ AS MD_YHDJ","HOCUS_MAN_NAME AS MD_HOCUS_MAN_NAME"])->toArray();
            if (!empty($mainOperation)) {
                foreach ($mainOperation as $k => $v) {
                    $mainOperation[$k]['MD_OPE_DATE'] = empty($v['MD_OPE_DATE']) ? '1970-01-01 00:00:00' : date('Y-m-d H:i:s', strtotime($v['MD_OPE_DATE']) < 0 ?: 0);
                }
                echo "main_operation-主要手术 \r\n";
                $doc['main_operation'] = $mainOperation;
                $doc = array_merge($doc, $mainOperation[0]);
            }
            // 主要手术
            $otherOperation = MainOperation::query()->where("AAA28", '=', $val['MED_REC_ID'])->get(["ICD9_ID1 AS O_ICD9_ID1", "ICD9_NAME AS O_ICD9_NAME", "OPE_DATE AS O_OPE_DATE","OPE_LEVEL AS O_OPE_LEVEL","SSPB AS O_SSPB","OPE_MAN_NAME AS O_OPE_MAN_NAME","FRIST_ASSISTANT_NAME AS O_FRIST_ASSISTANT_NAME","SECOND_ASSISTANT_CODE AS M_SECOND_ASSISTANT_CODE","HOCUS_WAY_ID AS O_HOCUS_WAY_ID","QKDJ AS O_QKDJ","YHDJ AS O_YHDJ","HOCUS_MAN_NAME AS O_HOCUS_MAN_NAME"])->toArray();
            if (!empty($otherOperation)) {
                foreach ($otherOperation as $k => $v) {
                    $otherOperation[$k]['MD_OPE_DATE'] = empty($v['MD_OPE_DATE']) ? '1970-01-01 00:00:00' : date('Y-m-d H:i:s', strtotime($v['MD_OPE_DATE']) < 0 ?: 0);
                }
                echo "other_operation-其他手术 \r\n";
                $doc['other_operation'] = $otherOperation;
            }
            // 费用明细
            $fymx = FeeDetailed::query()->where("AAA28", '=', $val['MED_REC_ID'])->get(['JFRQ', 'YBBM', 'FYMC', 'FYSL', 'FYDJ', 'ZJE', 'YSGH', 'ZLXZ', 'FYKS', 'ZXKS', 'FYGB', 'SYFYGB', 'YPLX'])->toArray();
            if (!empty($fymx)) {
                echo "费用明细- \r\n";
                $doc['fymx'] = $fymx;
            }
            // 检验
//            $testResult = TESTRESULT::query()->where("ZYH", '=', $val['MED_REC_ID'])->get(['YBLX', 'LCZD', 'JYXM', 'JG', 'TS', 'CKFW', 'DW', 'SJYS', 'BGSJ'])->toArray();
//            if (!empty($testResult)) {
//                foreach ($testResult as $k => $v) {
//                    $testResult[$k]['BGSJ'] = empty($v['BGSJ']) ? '1970-01-01 00:00:00' : date('Y-m-d H:i:s', strtotime($v['BGSJ']) < 0 ?: 0);
//                }
//                $doc['test_result'] = $testResult;
//            }
            // 药敏
//            $ymResult = YMRESULT::query()->where("ZYH", '=', $val['MED_REC_ID'])->get(['YBLX', 'LCZD', 'PYJG', 'XJMC', 'XJJL', 'YMMC', 'YMJG', 'YMBW', 'JYY', 'BGSJ'])->toArray();
//            if (!empty($ymResult)) {
//                foreach ($ymResult as $k => $v) {
//                    $ymResult[$k]['BGSJ'] = empty($v['BGSJ']) ? '1970-01-01 00:00:00' : date('Y-m-d H:i:s', strtotime($v['BGSJ']) < 0 ?: 0);
//                }
//                $doc['ym_result'] = $ymResult;
//            }
            // 手术申请
            $sssq = SSSQ::query()->where('ZYH', '=', $val['MED_REC_ID'])->get(['KSSJ', 'JSSJ', 'SSNM', 'SSMC', 'SSDM'])->toArray();
            if (!empty($sssq)) {
                echo "手术记录 \r\n";
                foreach ($sssq as $k => $v) {
                    $sssq[$k]['KSSJ'] = empty($v['KSSJ']) ? '1970-01-01 00:00:00' : date('Y-m-d H:i:s', strtotime($v['KSSJ']) < 0 ?: 0);
                    $sssq[$k]['JSSJ'] = empty($v['JSSJ']) ? '1970-01-01 00:00:00' : date('Y-m-d H:i:s', strtotime($v['JSSJ']) < 0 ?: 0);
                }
                $doc['sssq'] = $sssq;
            }
            // 医嘱本
//            $yzb = Yzb::query()->where("ZYH", '=', $val['MED_REC_ID'])->get(['SQDH', 'KZSJ', 'YZQX', 'KZKS', 'TZSJ', 'YZMC', 'XZJDSJ', 'KZYS', 'TZQRSJ', 'ZXSJ', 'XMLB', 'YZZT', 'PSBZ', 'PSJG', 'YYSX', 'YCJL', 'JLDW', 'ZL', 'ZLDW', 'SYPC', 'ZXZT', 'YDYZLB', 'JJYZ', 'ZTBZ', 'XZJDGH', 'TZQRGH'])->toArray();
//            if (!empty($yzb)) {
//                foreach ($yzb as $k => $v) {
//                    $yzb[$k]['ZXSJ'] = empty($v['ZXSJ']) ? '1970-01-01 00:00:00' : date('Y-m-d H:i:s', strtotime($v['ZXSJ']) < 0 ?: 0);
//                    $yzb[$k]['XZJDSJ'] = empty($v['XZJDSJ']) ? '1970-01-01 00:00:00' : date('Y-m-d H:i:s', strtotime($v['XZJDSJ']) < 0 ?: 0);
//                    $yzb[$k]['TZSJ'] = empty($v['TZSJ']) ? '1970-01-01 00:00:00' : date('Y-m-d H:i:s', strtotime($v['TZSJ']) < 0 ?: 0);
//                }
//                $SQDH = array_column($yzb, 'SQDH');
//                $doc['yzb'] = $yzb;
//
//                // 检查
//                if($SQDH){
//                    $SQDH = array_unique($SQDH);
//                    $SQDH = array_filter($SQDH, function ($i) {
//                        return $i?:false;
//                    });
//                    $pacs = PACS::query()->whereIn("SQDH", $SQDH)->get(['MZZYBZ', 'ExamType', 'SBBM', 'JCKSMC', 'JCYS', 'JCBW', 'JCMC', 'YXBX', 'YXZD', 'BGSJ'])->toArray();
//                    if (!empty($pacs)) {
//                        foreach ($pacs as $k => $v) {
//                            $yzb[$k]['BGSJ'] = empty($v['BGSJ']) ? '1970-01-01 00:00:00' : date('Y-m-d H:i:s', strtotime($v['BGSJ']) < 0 ?: 0);
//                        }
//                        $doc['jc_result'] = $pacs;
//                    }
//                }
//
//            }
            if (count($doc) > 1) {
                $esParams['body'][] = ['update' => ['_index' => $index, '_id' => $val['MED_REC_ID']]];
                $esParams['body'][] = ['doc' => $doc, 'doc_as_upsert' => true];
                $res = app('es')->bulk($esParams);
                if ($res['errors'] !== false) {
                    var_dump($res);
                } else {
                    echo "success\r\n";
                }
            }
        }
        return true;
    }
}
