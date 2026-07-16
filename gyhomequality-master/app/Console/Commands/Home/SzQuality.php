<?php

namespace App\Console\Commands\Home;

use App\Model\ErrorRule;
use App\Model\PatientInfo;
use App\Model\PatientInfoCostV2;
use App\Model\PatientInfoDiagnosisV2;
use App\Model\PatientInfoFeeDetailedV2;
use App\Model\PatientInfoIcuV2;
use App\Model\PatientInfoOperationV2;
use App\Model\PatientInfoV2;
use App\Model\Staff;
use App\Services\BasySzQualityService;
use App\Services\HomeService;
use Illuminate\Console\Command;

class SzQuality extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'home:szQuality {startTime?} {endTime?} {page?}';

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
        $startTime = $this->argument('startTime', '');
        $endTime = $this->argument('endTime', '');
        $page = $this->argument('page', '');

        if (empty($startTime) || empty($endTime)) {
            $startTime = date('Y-m-d', time() - 24 * 3600);
            $endTime = date('Y-m-d', time());
        }
        $this->info('病案首页事中质控 - 开始');

        $basySzQualityService = new BasySzQualityService();
        $homeSzService = new HomeService();

        // 获取质控规则
        $errorRuleData = ErrorRule::query()
            ->where('node', 'like', "%运行%")
            ->where('status', '=', 0)
            ->orderBy('id')
            ->get()->toArray();
        $errorRuleData = array_column($errorRuleData, null, 'id');

        $staffData = Staff::query()->pluck('name', 'code')->toArray();

        $hospitalName = config('confAdmin.hospital_name');
        $page = !empty($page) ? $page : 1;
        while (true) {
            $data = PatientInfo::query()
                ->where('hospital_name', '=', $hospitalName)
                ->whereBetween('AAC01', [$startTime . ' 00:00:00', $endTime . ' 23:59:59'])
                ->paginate(100, ['*'], 'page', $page)
                ->toArray();
            if (empty($data['data'])) {
                break;
            }

            $this->info('总页数：' . $data['last_page'] . '，当前执行页数：' . $page);
            $page++;

            foreach ($data['data'] as $info) {

                try{
                    $ZYH = $info['MED_REC_ID'];

                    // 获取用户信息
                    $patientInfo = $homeSzService->getPatientInfo($ZYH);
                    if (!empty($patientInfo)) {
                        // 补充信息
                        $other = $homeSzService->getBuChong($ZYH);

                        // 记录用户信息
                        $patientInfoId = $this->addPatientInfoV2($ZYH, $patientInfo, $other);

                        $this->addOther($ZYH, $patientInfoId, $other);

                        // 获取收费明细信息
                        $feeDetailedData = $homeSzService->getFyData($ZYH);
                        $this->addFeeDetailed($ZYH, $patientInfoId, $feeDetailedData);

                        // 获取重症监护（ICU）信息
                        $icuData = $homeSzService->getIcuInfo($ZYH);
                        $this->addIcu($ZYH, $patientInfoId, $icuData);

                        // 获取诊断信息
                        $diagnosisData = $homeSzService->getDiagnosisData($ZYH);
                        $this->addDiagnosis($ZYH, $patientInfoId, $diagnosisData);

                        // 获取手术信息
                        $operationData = $homeSzService->getOperationData($ZYH);
                        $this->addOperation($ZYH, $patientInfoId, $operationData);

                        // 医嘱信息
                        $yzData = $homeSzService->getYzbInfo($ZYH);

                        //
                        $zyZkjlData = $homeSzService->ZY_ZKJL($ZYH);

                        $content = [
                            'data' => $patientInfo[0],
                            'diagnosis' => $diagnosisData,
                            'operation' => $operationData,
                            'icu' => $icuData,
                            'fy' => $feeDetailedData,
                            'other' => $other[0] ?? [],
                            'yz' => $yzData,
                            'zy_zkjl' => $zyZkjlData
                        ];

                        $basySzQualityService->qualityContrl($content, $errorRuleData, $staffData);
                    }
                }catch (\Throwable $e) {
                    continue;
                }

            }
        }

        $this->info('病案首页事中质控 - 结束');
    }

    /**
     * 主信息
     * @param $ZYH
     * @param $patientInfo
     * @return int
     */
    public function addPatientInfoV2($ZYH, $patientInfo, $other)
    {
        $patientInfo = $patientInfo[0] ?? [];
        $other = $other[0] ?? [];
        $data = array_merge($patientInfo, $other);

        PatientInfoV2::query()->where('ZYH', '=', $ZYH)->update(['status' => 1]);
        $insertData = [
            'UNT_ID' => $data['UNT_ID'] ?? '',
            'ZA03' => $data['ZA03'] ?? '',
            'AAA28' => $data['AAA28'] ?? '',
            'ZYH' => $data['MED_REC_ID'] ?? '',
            'cid' => $data['cid'] ?? '',
            'AAA01' => !empty($data['AAA01']) ? desensitize($data['AAA01'], 1, 1) : '',
            'AAB01' => $data['AAB01'] ?? '',
            'AAC01' => $data['AAC01'] ?? '',
            'AAA02C' => $data['AAA02C'] ?? '',
            'AAA07' => !empty($data['AAA07']) ? desensitize($data['AAA07'], 14, 4) : '',
            'AAA03' => $data['AAA03'] ?? '',
            'AAA04' => $data['AAA04'] ?? '',
            'AAA40' => $data['AAA40'] ?? '',
            'AAA05C' => $data['AAA05C'] ?? '',
            'AAA06C' => $data['AAA06C'] ?? '',
            'AAA18C' => $data['AAA18C'] ?? '',
            'AAA08C' => $data['AAA08C'] ?? '',
            'AAA29' => $data['AAA29'] ?? '',
            'AAC04' => $data['AAC04'] ?? '',
            'AAA27' => $data['AAA27'] ?? '',
            'AAA26C' => $data['AAA26C'] ?? '',
            'AAA43' => $data['AAA43'] ?? '',
            'AAA44' => $data['AAA44'] ?? '',
            'GG' => $data['GG'] ?? '',
            'AAA09' => $data['AAA09'] ?? '',
            'AAA10' => $data['AAA10'] ?? '',
            'AAA11' => $data['AAA11'] ?? '',
            'CSD_JD' => $data['CSD_JD'] ?? '',
            'CSD' => $data['CSD'] ?? '',
            'AAA45' => $data['AAA45'] ?? '',
            'AAA46' => $data['AAA46'] ?? '',
            'AAA47' => $data['AAA47'] ?? '',
            'AAA33C' => $data['AAA33C'] ?? '',
            'AAA12' => $data['AAA12'] ?? '',
            'AAA13C' => $data['AAA13C'] ?? '',
            'AAA48' => $data['AAA48'] ?? '',
            'AAA49' => $data['AAA49'] ?? '',
            'AAA50' => $data['AAA50'] ?? '',
            'AAA36C' => $data['AAA36C'] ?? '',
            'AAA15' => $data['AAA15'] ?? '',
            'AAA17C' => $data['AAA17C'] ?? '',
            'AAA51' => !empty($data['AAA51']) ? desensitize($data['AAA51'], 3, 4) : '',
            'AAA19' => $data['AAA19'] ?? '',
            'AAA20' => !empty($data['AAA20']) ? desensitize($data['AAA20'], 3, 4) : '',
            'AAA21C' => $data['AAA21C'] ?? '',
            'AAA22' => !empty($data['AAA22']) ? desensitize($data['AAA22'], 1, 1) : '',
            'AAA23C' => $data['AAA23C'] ?? '',
            'AAA24' => $data['AAA24'] ?? '',
            'AAA25' => !empty($data['AAA25']) ? desensitize($data['AAA25'], 3, 4) : '',
            'ZLLB' => $data['ZLLB'] ?? '',
            'AAB06C' => $data['AAB06C'] ?? '',
            'AAB02C' => $data['AAB02C'] ?? '',
            'AAB11C' => $data['AAB11C'] ?? '',
            'AAB11N' => $data['AAB11N'] ?? '',
            'RYBQBM' => $data['RYBQBM'] ?? '',
            'RYBQMC' => $data['RYBQMC'] ?? '',
            'RYBFBM' => $data['RYBFBM'] ?? '',
            'RYBFMC' => $data['RYBFMC'] ?? '',
            'AAD01C' => $data['AAD01C'] ?? '',
            'AAC02C' => $data['AAC02C'] ?? '',
            'AAC11C' => $data['AAC11C'] ?? '',
            'AAC11N' => $data['AAC11N'] ?? '',
            'CYBQBM' => $data['CYBQBM'] ?? '',
            'CYBQMC' => $data['CYBQMC'] ?? '',
            'CYBFBM' => $data['CYBFBM'] ?? '',
            'CYBFMC' => $data['CYBFMC'] ?? '',
            'ABA01C' => $data['ABA01C'] ?? '',
            'ABA01N' => $data['ABA01N'] ?? '',
            'BRLY' => $data['BRLY'] ?? '',
            'SSLCLJ' => $data['SSLCLJ'] ?? '',
            'AFA01' => $data['AFA01'] ?? '',
            'AFA02' => $data['AFA02'] ?? '',
            'AAB07D' => $data['AAB07D'] ?? '',
            'WBYY' => $data['WBYY'] ?? '',
            'H23' => $data['H23'] ?? '',
            'ZQSS' => $data['ZQSS'] ?? '',
            'ABF01C' => $data['ABF01C'] ?? '',
            'ABF01N' => $data['ABF01N'] ?? '',
            'ABF04' => $data['ABF04'] ?? '',
            'AEB02C' => $data['AEB02C'] ?? '',
            'AEB01' => $data['AEB01'] ?? '',
            'AEI01C' => $data['AEI01C'] ?? '',
            'AEG01C' => $data['AEG01C'] ?? '',
            'AEG02C' => $data['AEG02C'] ?? '',
            'AEG04' => $data['AEG04'] ?? '',
            'AEG05' => $data['AEG05'] ?? '',
            'AEG06' => $data['AEG06'] ?? '',
            'AEG07' => $data['AEG07'] ?? '',
            'ZTXHS' => $data['ZTXHS'] ?? '',
            'AEE01_CODE' => $data['AEE01_CODE'] ?? '',
            'AEE01' => $data['AEE01'] ?? '',
            'AEE02_CODE' => $data['AEE02_CODE'] ?? '',
            'AEE02' => $data['AEE02'] ?? '',
            'AEE03_CODE' => $data['AEE03_CODE'] ?? '',
            'AEE03' => $data['AEE03'] ?? '',
            'AEE04_CODE' => $data['AEE04_CODE'] ?? '',
            'AEE04' => $data['AEE04'] ?? '',
            'AEE05_CODE' => $data['AEE05_CODE'] ?? '',
            'AEE05' => $data['AEE05'] ?? '',
            'AEE07_CODE' => $data['AEE07_CODE'] ?? '',
            'AEE07' => $data['AEE07'] ?? '',
            'AEE08_CODE' => $data['AEE08_CODE'] ?? '',
            'AEE08' => $data['AEE08'] ?? '',
            'AEE10_CODE' => $data['AEE10_CODE'] ?? '',
            'AEE10' => $data['AEE10'] ?? '',
            'AED02_CODE' => $data['AED02_CODE'] ?? '',
            'AED02' => $data['AED02'] ?? '',
            'AED03_CODE' => $data['AED03_CODE'] ?? '',
            'AED03' => $data['AED03'] ?? '',
            'AED01C' => $data['AED01C'] ?? '',
            'AED04' => $data['AED04'] ?? '',
            'AEM01C' => $data['AEM01C'] ?? '',
            'YZZY_YLJG' => $data['YZZY_YLJG'] ?? '',
            'WSY_YLJG' => $data['WSY_YLJG'] ?? '',
            'AEM03C' => $data['AEM03C'] ?? '',
            'AEM04' => $data['AEM04'] ?? '',
            'AEJ01' => $data['AEJ01'] ?? '',
            'AEJ02' => $data['AEJ02'] ?? '',
            'AEJ03' => $data['AEJ03'] ?? '',
            'AEJ04' => $data['AEJ04'] ?? '',
            'AEJ05' => $data['AEJ05'] ?? '',
            'AEJ06' => $data['AEJ06'] ?? '',
            'AEL01' => $data['AEL01'] ?? '',
            'WCFXJ' => $data['WCFXJ'] ?? '',
            'ADA01' => $data['ADA01'] ?? '',
            'ADA0101' => $data['ADA0101'] ?? '',
            'JSF' => $data['JSF'] ?? '',
            'SF_SYKSS' => $data['SF_SYKSS'] ?? '',
            'SF_SX' => $data['SF_SX'] ?? '',
            'SXFY' => $data['SXFY'] ?? '',
            'SF_SW' => $data['SF_SW'] ?? '',
            'TJHL' => $data['TJHL'] ?? '',
            'YJHL' => $data['YJHL'] ?? '',
            'EJHL' => $data['EJHL'] ?? '',
            'SJHL' => $data['SJHL'] ?? '',
            'CRBBG' => $data['CRBBG'] ?? '',
            'AFA03' => $data['AFA03'] ?? '',
            'AFA04' => $data['AFA04'] ?? '',
            'AFA05' => $data['AFA05'] ?? '',
            'KSS_FA' => $data['KSS_FA'] ?? '',
            'KSS_MD' => $data['KSS_MD'] ?? '',
            'KSS_SFSY' => $data['KSS_SFSY'] ?? '',
            'KSS_SFTS' => $data['KSS_SFTS'] ?? '',
            'AAB07' => $data['AAB07'] ?? '',
            'AAB07C' => $data['AAB07C'] ?? '',
            'AAB07N' => $data['AAB07N'] ?? '',
            'SFRJSS' => $data['SFRJSS'] ?? '',
            'SFYFJHECSS' => $data['SFYFJHECSS'] ?? '',
            'ZYQJSFCXWZ' => $data['ZYQJSFCXWZ'] ?? '',
            'SFZZJHS' => $data['SFZZJHS'] ?? '',
            'SQYYZRMC' => $data['SQYYZRMC'] ?? '',
            'YLTZRMC' => $data['YLTZRMC'] ?? '',
            'ZCFS' => $data['ZCFS'] ?? '',
            'ZCZSQYYMC' => $data['ZCZSQYYMC'] ?? '',
            'ZCZYLTMC' => $data['ZCZYLTMC'] ?? '',
            'ABD053' => $data['ZDFHQK_LCYBL'] ?? '',
            'ABD051' => $data['ZDFHQK_RYYCY'] ?? '',
            'ABD052' => $data['ZDFHQK_SQYSH'] ?? '',
            'ZJLB' => $data['ZJLB'] ?? '',
            'ZRFS' => $data['ZRFS'] ?? '',
            'AAA42' => $data['XSERYTZ'] ?? '',
            'AEN01' => $data['XSECSTZ'] ?? '',
            'CYQK' => $data['CYQK'] ?? ''
        ];

        return PatientInfoV2::query()->insertGetId($insertData);
    }

    /**
     * 重症监护信息
     * @param $ZYH
     * @param $patientInfoId
     * @param $icuData
     * @return void
     */
    public function addIcu($ZYH, $patientInfoId, $icuData)
    {
        PatientInfoIcuV2::query()->where('ZYH', '=', $ZYH)->update(['status' => 1]);
        if (!empty($icuData)) {
            $insertData = [];
            foreach ($icuData as $key => $val) {
                $insertData[] = [
                    'patient_info_v2_id' => $patientInfoId,
                    'ZYH' => $ZYH,
                    'IS_MAIN_WAY' => $val[''] ?? '',
                    'IN_TIME' => $val[''] ?? '',
                    'OUT_TIME' => $val[''] ?? '',
                    'sort' => $key + 1,
                ];
            }

            if (!empty($insertData)) {
                PatientInfoIcuV2::query()->insert($insertData);
            }
        }
    }

    /**
     * 诊断信息
     * @param $ZYH
     * @param $patientInfoId
     * @param $diagnosisData
     * @return void
     */
    public function addDiagnosis($ZYH, $patientInfoId, $diagnosisData)
    {
        PatientInfoDiagnosisV2::query()->where('ZYH', '=', $ZYH)->update(['status' => 1]);
        if (!empty($diagnosisData)) {
            $arr = ['有' => 1, '临床未确定' => 2, '情况不明' => 3, '无' => 4,];
            $inserData = [];
            foreach ($diagnosisData as $key => $val) {
                $inserData[] = [
                    'patient_info_v2_id' => $patientInfoId,
                    "ZYH" => $val['ZYH'] ?? '',
                    "ICD10_ID1" => $val['ZDBM'] ?? '',
                    "ICD10_NAME" => $val['ZDMC'] ?? '',
                    "RYBQ" => !empty($arr[$val['RYQK']]) ? $arr[$val['RYQK']] : $val['RYQK'],
                    "CYQK" => $val['CYQK'] ?? '',
                    "type" => $val['ZZPB'] == 1 ? 1 : 2,
                    "DIA_ORDER" => $key + 1,
                ];
            }

            if (!empty($inserData)) {
                PatientInfoDiagnosisV2::query()->insert($inserData);
            }
        }
    }

    /**
     * 手术信息
     * @param $ZYH
     * @param $patientInfoId
     * @param $operationData
     * @return void
     */
    public function addOperation($ZYH, $patientInfoId, $operationData)
    {
        PatientInfoOperationV2::query()->where('ZYH', '=', $ZYH)->update(['status' => 1]);
        if (!empty($operationData)) {
            $inserData = [];
            foreach ($operationData as $key => $val) {
                $SSPB_ARR = ['手术' => 1, '诊断操作' => 2, '治疗操作' => 3, '介入治疗' => 4, '空' => 5];
                $arr = [
                    'patient_info_v2_id' => $patientInfoId,
                    "ZYH" => $ZYH,
                    "ICD9_ID1" => $val['SSCZBM'] ?? '',
                    "ICD9_NAME" => $val['SSCZMC'] ?? '',
                    "OPE_DATE" => $val['SSCZRQ'] ?? '',
                    "OPE_LEVEL" => $val['SSJB'] ?? '',
                    "OPE_MAN_NAME" => $val['SZXM'] ?? '',
                    "FRIST_ASSISTANT_NAME" => $val['YZXM'] ?? '',
                    "SECOND_ASSISTANT_NAME" => $val['EZXM'] ?? '',
                    "QKDJ" => $val['QKDJ'] ?? '',
                    "QKYHLB" => $val['YHDJ'] ?? '',
                    "HOCUS_WAY_ID" => $val['MZFS'] ?? '',
                    "HOCUS_MAN_NAME" => $val['MZYSXM'] ?? '',
                    "OPE_ORDER" => $key + 1,
                    "RJSS" => $val['SFWRJSS'] ?? '',
                    "SSPB" => !empty($SSPB_ARR[$val['SSPB']]) ? $SSPB_ARR[$val['SSPB']] : $val['SSPB'],
                    "type" => $val['SFZYSS'] == 1 ? 1 : 2,
                ];
                $inserData[] = $arr;
            }

            if (!empty($inserData)) {
                PatientInfoOperationV2::query()->insert($inserData);
            }
        }
    }

    /**
     * 费用信息
     * @param $ZYH
     * @param $patientInfoId
     * @param $AAA28
     * @param $other
     * @return void
     */
    public function addOther($ZYH, $patientInfoId, $other)
    {
        PatientInfoCostV2::query()->where('ZYH', '=', $ZYH)->update(['status' => 1]);
        $other = $other[0];
        if (!empty($other)) {
            $insertData = [
                'patient_info_v2_id' => $patientInfoId,
                'ZYH' => $ZYH,
                'ADA01' => $other['ZYZFY'] ?? '',
                "ADA0101" => $other['ZFFY'] ?? '',
                "D11" => $other['YBYLFWF'] ?? '',
                "D12" => $other['YBZLCZF'] ?? '',
                "D13" => $other['HLF'] ?? '',
                "D14" => $other['ZHYLFWLQTFY'] ?? '',
                "D15" => $other['BLZDF'] ?? '',
                "D16" => $other['SYSZDF'] ?? '',
                "D17" => $other['YXXZDF'] ?? '',
                "D18" => $other['LCZDXMF'] ?? '',
                "D19" => $other['FSSZLXMF'] ?? '',
                "D19X01" => $other['LCWLZLF'] ?? '',
                "D20" => $other['SSZLF'] ?? '',
                "D20X01" => $other['MZF'] ?? '',
                "D20X02" => $other['SSF'] ?? '',
                "D21" => $other['KFF'] ?? '',
                "D22" => $other['ZYZLF'] ?? '',
                "D23" => $other['XYF'] ?? '',
                "D23X01" => $other['KJYWF'] ?? '',
                "D24" => $other['ZCHENGYF'] ?? '',
                "D25" => $other['ZCAOYF'] ?? '',
                "D26" => $other['XF'] ?? '',
                "D27" => $other['BDBLZPF'] ?? '',
                "D28" => $other['QDBLZPF'] ?? '',
                "D29" => $other['NXYZLZPF'] ?? '',
                "D30" => $other['XBYZLZPF'] ?? '',
                "D31" => $other['JCYYCXYYCLF'] ?? '',
                "D32" => $other['ZLYYCXYYCLF'] ?? '',
                "D33" => $other['SSYYCXYYCLF'] ?? '',
                "D34" => $other['QTF'] ?? '',
            ];

            PatientInfoCostV2::query()->insert($insertData);
        }
    }

    /**
     * 费用明细
     * @param $ZYH
     * @param $patientInfoId
     * @param $fyContent
     * @return void
     */
    public function addFeeDetailed($ZYH, $patientInfoId, $fyContent)
    {
        $insertData = [];
        if (!empty($fyContent)) {
            foreach ($fyContent as $value) {
                $insertData[] = [
                    'patient_info_id' => $patientInfoId,
                    'ZYH' => $ZYH,
                    'FYXH' => $value['FYXH'] ?? '',
                    'FYMC' => $value['FYMC'] ?? '',
                    'ZFJE' => $value['ZFJE'] ?? '',
                    'JFRQ' => $value['JFRQ'] ?? '',
                    'FYSL' => $value['FYSL'] ?? '',
                    'FYDJ' => $value['FYDJ'] ?? '',
                    'FYKS' => $value['FYKS'] ?? '',
                ];
            }
        }

        PatientInfoFeeDetailedV2::query()->where('ZYH', '=', $ZYH)->update(['status' => 1]);
        if (!empty($insertData)) {
            PatientInfoFeeDetailedV2::query()->insert($insertData);
        }
    }

}
