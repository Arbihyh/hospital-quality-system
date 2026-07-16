<?php

namespace App\Console\Commands;

use App\Model\ErrorRule;
use App\Model\FeeDetailed;
use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\OtherDiagnosis;
use App\Model\PatientAdd;
use App\Model\PatientAddressInfo;
use App\Model\PatientContactsInfo;
use App\Model\PatientCostInfo;
use App\Model\PatientDoctorInfo;
use App\Model\PatientHospitalInfo;
use App\Model\PatientInfo;
use App\Model\PatientMedicalInfo;
use App\Model\PatientWorkInfo;
use App\Model\RuleWordMap;
use App\Model\SecondaryOperation;
use App\Model\Yzb;
use App\Services\BasyQualityService;
use App\Services\HomeSzService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncExtendInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-extend-info {startDate?} {endDate?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步patient_info表中未编目扩展数据';
    protected $HomeSzService;
    protected $addressRegexp;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
//        $this->HomeSzService = new HomeSzService();
//        $this->addressRegexp = RuleWordMap::query()->whereIn('id',['4041','4042','4011','4012','4021','4022','4023','4031','4032','4033'])->pluck('keyword','id')->toArray();
//        $this->ageRegexp = [1=>'/(\d+)岁/',2=>'/(\d+)月/'];
//        $this->ageYcRegexp = [1=>'/(.*?(岁))/u',2=>'/(.*?(月))/u'];
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('同步扩展信息 - 开始');

        //获取质控时间
        $startDate = $this->argument('startDate') ?: '';
        $endDate = $this->argument('endDate') ?: '';
        if (!empty($startDate) && !empty($endDate)) {
            $field = 'AAC01';
            $startTime = $startDate.' 00:00:00';
            $endTime = $endDate.' 23:59:59';
        } else {
            $field = 'created_at';
            $date = Carbon::parse()->addDay(-1)->toDateString();
            $startTime = $date.' 00:00:00';
            $endTime = $date.' 23:59:59';
        }

        //开始循环
        $basyQualityService = new BasyQualityService();
        $page = 1;
        $pageSize = 100;
        while (true) {
            $patientData = PatientInfo::query()
                ->whereBetween('AAC01', [$startTime,$endTime])
                ->where('IS_CATA',2)
                ->orderBy('AAC01','desc')
                ->paginate($pageSize, ['id', 'MED_REC_ID','AAA28','AAA29','ZYH_ID'], 'page', $page)
                ->toArray();
            if (empty($patientData['data'])) {
                break;
            }
            $page++;

            foreach ($patientData['data'] as $value) {
                echo $value['MED_REC_ID'] . "开始时间:".date("Y-m-d H:i:s")."\n";
                $this->zkInfo($value);//获取质控数据
            }
        }

        $this->info('同步扩展信息 - 完毕');
    }


    /**质控信息
     * @return void
     */
    public function zkInfo($data)
    {
        $ZYH_ID = $data['ZYH_ID'];//ID_ENT唯一标识
        if (empty($ZYH_ID)) return;
        $currentTime = date("Y-m-d H:i:s");//当前时间

        //region patient信息===
        //先去ods库中取数据，如果没有再去我们自己的数据库取
        $patientInfo = $this->HomeSzService->getOdsInfoV2($this->getInfoSql(),$ZYH_ID);
        if (is_array($patientInfo) && count($patientInfo) > 0) $patientInfo = $patientInfo[0];
        //如果没有获取到数据则去mysql库取,获取到了则更新mysql到数据
        if (!empty($patientInfo)){
            //清洗年龄
            //年
            $ageYear = $this->parseAndTrimAge($patientInfo['AAA04_1'],$this->ageRegexp[1],$this->ageYcRegexp[1]);
            $patientInfo['AAA04'] = $ageYear['num'];
            //月
            $ageMonth = $this->parseAndTrimAge($ageYear['age'],$this->ageRegexp[2],$this->ageYcRegexp[2]);
            $patientInfo['AAA40'] = $ageMonth['num'] > 0 ? $ageMonth['num']*30 : '';
            $patientInfo['updated_at'] = $patientInfo['created_at'] = $currentTime;
            unset($patientInfo['ZYH_ID']);
            PatientInfo::query()->updateOrInsert(['ZYH_ID'=> $ZYH_ID],$patientInfo);
        }


        //patient_hospital_info表
        $patientHospitalInfo = $this->HomeSzService->getOdsInfoV2($this->getHospitalSql(),$ZYH_ID);
        if (is_array($patientHospitalInfo) && count($patientHospitalInfo) > 0) $patientHospitalInfo = $patientHospitalInfo[0];

        if (!empty($patientHospitalInfo)){
            $patientHospitalInfo['updated_at'] = $patientHospitalInfo['created_at'] = $currentTime;
            PatientHospitalInfo::query()->updateOrInsert(['ZYH_ID'=> $ZYH_ID],$patientHospitalInfo);
        }

        //patient_doctor_info表
        $patientDoctorInfo = $this->HomeSzService->getOdsInfoV2($this->getDoctorSql(),$ZYH_ID);
        if (is_array($patientDoctorInfo) && count($patientDoctorInfo) > 0) $patientDoctorInfo = $patientDoctorInfo[0];
        if (!empty($patientDoctorInfo)){
            //$patientDoctorInfo['updated_at'] = $patientDoctorInfo['created_at'] = $currentTime;
            PatientDoctorInfo::query()->updateOrInsert(['ZYH_ID'=> $ZYH_ID],$patientDoctorInfo);
        }

        //patient_contacts_info表
        $patientContactsInfo = $this->HomeSzService->getOdsInfoV2($this->getContactsSql(),$ZYH_ID);
        if (is_array($patientContactsInfo) && count($patientContactsInfo) > 0) $patientContactsInfo = $patientContactsInfo[0];
        if (!empty($patientContactsInfo)){
            $patientContactsInfo['updated_at'] = $patientContactsInfo['created_at'] =  $currentTime;
            PatientContactsInfo::query()->updateOrInsert(['ZYH_ID'=> $ZYH_ID],$patientContactsInfo);
        }
        //patient_address_info表
        $patientAddressInfo = $this->HomeSzService->getOdsInfoV2($this->getAddressSql(),$ZYH_ID);
        if (is_array($patientAddressInfo) && count($patientAddressInfo) > 0) $patientAddressInfo = $patientAddressInfo[0];

        if (!empty($patientAddressInfo)){
            //清洗出生地
            $csdAddress = $patientAddressInfo['CSD'];

            $csdProvinceResult = $this->parseAndTrimAddress($csdAddress,$this->addressRegexp['4041']);
            $patientAddressInfo['AAA09'] = $csdProvinceResult['result'];
            $csdCityResult = $this->parseAndTrimAddress($csdProvinceResult['address'],$this->addressRegexp['4042']);
            $patientAddressInfo['AAA10'] = $csdCityResult['result'];
            $patientAddressInfo['AAA11'] = $csdCityResult['address'];

            //清洗籍贯
            $ggAddress = $patientAddressInfo['GG'];
            $ggProvinceResult = $this->parseAndTrimAddress($ggAddress,$this->addressRegexp['4011']);
            $patientAddressInfo['AAA43'] = $csdProvinceResult['result'];
            $ggCityResult = $this->parseAndTrimAddress($ggProvinceResult['address'],$this->addressRegexp['4012']);
            $patientAddressInfo['AAA44'] = $ggCityResult['result'];

            //清洗户籍
            $hjAddress = $patientAddressInfo['AAA12'];
            $hjProvinceResult = $this->parseAndTrimAddress($hjAddress,$this->addressRegexp['4021']);
            $patientAddressInfo['AAA45'] = $hjProvinceResult['result'];
            $hjCityResult = $this->parseAndTrimAddress($hjProvinceResult['address'],$this->addressRegexp['4022']);
            $patientAddressInfo['AAA46'] = $hjCityResult['result'];
            $hjAreaResult = $this->parseAndTrimAddress($hjCityResult['address'],$this->addressRegexp['4023']);
            $patientAddressInfo['AAA47'] = $hjAreaResult['result'];

            //清洗现住址
            $xzzAddress = $patientAddressInfo['AAA15'];
            $xzzProvinceResult = $this->parseAndTrimAddress($xzzAddress,$this->addressRegexp['4031']);
            $patientAddressInfo['AAA48'] = $xzzProvinceResult['result'];
            $xzzCityResult = $this->parseAndTrimAddress($xzzProvinceResult['address'],$this->addressRegexp['4032']);
            $patientAddressInfo['AAA49'] = $xzzCityResult['result'];
            $xzzAreaResult = $this->parseAndTrimAddress($xzzCityResult['address'],$this->addressRegexp['4033']);
            $patientAddressInfo['AAA50'] = $xzzAreaResult['result'];
            $patientAddressInfo['updated_at'] = $patientAddressInfo['created_at'] = $currentTime;
            PatientAddressInfo::query()->updateOrInsert(['ZYH_ID'=> $ZYH_ID],$patientAddressInfo);
        }

        //patient_add表
        $patientAdd = $this->HomeSzService->getOdsInfoV2($this->getAddSql(),$ZYH_ID);
        if (is_array($patientAdd) && count($patientAdd) > 0) $patientAdd = $patientAdd[0];
        if (!empty($patientAdd)){
            $patientAdd['updated_at'] = $patientAdd['created_at'] = $currentTime;
            PatientAdd::query()->updateOrInsert(['ZYH_ID'=> $ZYH_ID],$patientAdd);
        }

        //patient_work_info表
        $patientWorkInfo = $this->HomeSzService->getOdsInfoV2($this->getWorkSql(),$ZYH_ID);
        if (is_array($patientWorkInfo) && count($patientWorkInfo) > 0) $patientWorkInfo = $patientWorkInfo[0];
        if (!empty($patientWorkInfo)){
            $patientWorkInfo['updated_at'] = $patientWorkInfo['created_at'] = $currentTime;
            PatientWorkInfo::query()->updateOrInsert(['ZYH_ID'=> $ZYH_ID],$patientWorkInfo);
        }

        //patient_medical_info表
        $patientMedicalInfo = $this->HomeSzService->getOdsInfoV2($this->getMedicalSql(),$ZYH_ID);
        if (is_array($patientMedicalInfo) && count($patientMedicalInfo) > 0) $patientMedicalInfo = $patientMedicalInfo[0];
        if (!empty($patientMedicalInfo)){
            $patientMedicalInfo['updated_at'] = $patientMedicalInfo['created_at'] =  $currentTime;
            PatientMedicalInfo::query()->updateOrInsert(['ZYH_ID'=> $ZYH_ID],$patientMedicalInfo);
        }

        //patient_cost_info表
        $patientCostInfo = $this->HomeSzService->getOdsInfoV2($this->getCostSql(),$ZYH_ID);
        if (is_array($patientCostInfo) && count($patientCostInfo) > 0) $patientCostInfo = $patientCostInfo[0];
        if (!empty($patientCostInfo)){
            $patientCostInfo['updated_at'] = $patientCostInfo['created_at'] = $currentTime;
            PatientCostInfo::query()->updateOrInsert(['ZYH_ID'=> $ZYH_ID],$patientCostInfo);
        }

        //所属院区
        $YqCode = [];
        if(!empty($patientHospitalInfo['AAC02C']))
        {
            $YqCode = $this->HomeSzService->getYqV2($patientHospitalInfo['AAC02C']);
            if (is_array($YqCode) && count($YqCode) > 0) $YqCode = $YqCode[0] ?? [];
        }

        $patientInfo = array_merge($patientInfo,$patientHospitalInfo,$patientDoctorInfo,$patientContactsInfo,
            $patientAddressInfo,$patientAdd,$patientWorkInfo,$patientMedicalInfo,$patientCostInfo);
        $patientInfo['ZA03'] = $patientInfo['HOSPITAL_NAME'] ?? $patientInfo['hospital_name'];
        $patientInfo['ABB02C'] = "";
        $patientInfo['AAC11N'] = "";
        $patientInfo['YQ_CODE'] = $YqCode['YQ_CODE'] ?? "";
        $patientInfo['GX_MC'] = $patientInfo['GX_MC'] ?? "";
        $patientInfo['AAB02C'] = $patientInfo['AAB02C'] ?? "";
        $patientInfo['AEB02C'] = $patientInfo['AEB02C'] ?? "";
        $patientInfo['AEE08'] = $patientInfo['AEE08'] ?? "";
        $patientInfo['ABF01N'] = $patientInfo['ABF01N'] ?? "";
        $patientInfo['AEM03C'] = $patientInfo['AEM03C'] ?? "";
        $patientInfo['AED01C'] = $patientInfo['AED01C'] ?? "";
        $patientInfo['AED04'] = $patientInfo['AED04'] ?? "";
        //endregion

        //region diagnosis信息===
        $diagnosisData = $this->HomeSzService->getOdsInfoV2($this->getDiagnosisSql(),$ZYH_ID);
        if (!empty($diagnosisData)){
            //如果ZZPB为1，则更新主诊断表，否则更新其他诊断表
            foreach ($diagnosisData as $v){
                $v['updated_at'] = $v['created_at'] = $currentTime;
                if ($v['ZZPB'] === 1){
                    MainDiagnosis::query()->updateOrInsert(['ZDXH'=> $v['ZDXH']],$v);
                }else{
                    OtherDiagnosis::query()->updateOrInsert(['ZDXH'=> $v['ZDXH']],$v);
                }
            }
        }
        foreach ($diagnosisData as $k=>$v){
            $diagnosisData[$k]['ZDBM'] = $v['ICD10_ID1']??"";//诊断编码
            $diagnosisData[$k]['ZDMC'] = $v['ICD10_NAME']??"";//诊断名称
        }
        //endregion

        //region 手术信息===
        $operationData = $this->HomeSzService->getOdsInfoV2($this->getOperationSql(),$ZYH_ID);
        if (!empty($operationData)){
            //如果SFZYSS为1，则更新主手术表，否则更新次手术表
            foreach ($operationData as $v){
                $v['updated_at'] = $v['created_at'] = $currentTime;
                if ($v['SFZYSS'] === 1){
                    MainOperation::query()->updateOrInsert(['SSXH' => $v['SSXH']],$v);
                }else{
                    SecondaryOperation::query()->updateOrInsert(['SSXH' => $v['SSXH']],$v);
                }
            }
        }
        foreach ($operationData as $k=>$v)
        {
            $operationData[$k]['SSCZBM'] = $v['ICD9_ID1']??"";//手术操作编码
            $operationData[$k]['SSCZMC'] = $v['ICD9_NAME']??"";//手术操作名称
            $operationData[$k]['SSCZRQ'] = $v['OPE_DATE'];//手术操作日期
            $operationData[$k]['SSSX'] = $v['SFZYSS'];
            $operationData[$k]['SZXM'] = $v['OPE_MAN_NAME'];//手术者对应主刀医生名称
        }
        //endregion

        //费用详情
        $feeDetailedData = $this->HomeSzService->getOdsInfoV2($this->getFeeDetailedSql(),$ZYH_ID);
        if (!empty($feeDetailedData)){
            foreach ($feeDetailedData as $v){
                $v['updated_at'] = $v['created_at'] = $currentTime;;
                FeeDetailed::query()->updateOrInsert(['FYXH_NO' => $v['FYXH_NO']],$v);
            }
        }

        //医嘱
        $yzData = $this->HomeSzService->getOdsInfoV2($this->getYzSql(),$ZYH_ID);
        if (!empty($yzData)){
            foreach ($yzData as $v){
                $v['updated_at'] = $v['created_at'] = $currentTime;
                Yzb::query()->updateOrInsert(['YZBXH' => $v['YZBXH']],$v);
            }
        }

        return true;
    }

    /** 清洗地址
     * @params string $address
     * @param string $regexp
     * return array
     */
    protected function parseAndTrimAddress($address,$regexp)
    {
        preg_match("{$regexp}",$address,$matches);
        $result = isset($matches[1]) ? $matches[1] : '';
        if (!empty($result))
        {
            $address = preg_replace('/' . preg_quote($result, '/') . '/', '', $address, 1);
        }
        return ['result'=>$result,'address'=>$address];
    }

    /**
     * 清洗年龄
     */
    protected function parseAndTrimAge($age,$regexp,$ycRegexp=null)
    {
        // 使用正则表达式匹配岁前面的数字
        preg_match($regexp, $age, $matches);
        $num = isset($matches[1]) ? intval($matches[1]) : 0;//获取数字
        //移除
        preg_match($ycRegexp, $age, $matches);
        $result = isset($matches[1]) ? $matches[1] : '';
        if (!empty($matches))
        {
            $age = preg_replace('/' . preg_quote($result, '/') . '/', '', $age, 1);
        }
        return ['num'=>$num,'age'=>$age];
    }


    /**
     * 获取用户主信息
     * @param $ZYH
     * @return array
     */
    public function getInfoSql()
    {
        $sql = "SELECT NVL(HIS.CI_MR_FP_PAT.ID_ORG, '') AS hospital_name, NVL(HIS.CI_MR_FP_PAT.CODE_AMR_IP, '') AS AAA28, NVL(HIS.CI_MR_FP_PAT.ID_ENT, '') AS ZYH_ID, (NVL(HIS.CI_MR_FP_PAT.CODE_AMR_IP, '') || '' || NVL(HIS.CI_MR_FP_PAT.N_TIMES_INHOSPITAL, '')) AS MED_REC_ID, NVL(HIS.CI_MR_FP_PAT.NAME_PAT, '') AS AAA01, NVL(CASE WHEN HIS.CI_MR_FP_PAT.ID_SEX = '@@@@Z81000000003NSBP' THEN 0 WHEN HIS.CI_MR_FP_PAT.ID_SEX = '@@@@Z81000000003NSBQ' THEN 1 WHEN HIS.CI_MR_FP_PAT.ID_SEX = '@@@@Z81000000003NSBR' THEN 2 WHEN HIS.CI_MR_FP_PAT.ID_SEX = '' THEN 9 END, '') AS AAA02C, NVL(HIS.CI_MR_FP_PAT.NAME_SEX, '') AS AAA02C_MC, NVL(EN_ENT.DT_BIRTH_PAT, '') AS AAA03, NVL(HIS.CI_MR_FP_PAT.AGE, '') AS AAA04_1, NVL(CASE WHEN HIS.CI_MR_FP_PAT.SD_COUNTRY = '156' THEN 1 WHEN HIS.CI_MR_FP_PAT.SD_COUNTRY = 'CHN' THEN 1 ELSE 2 END, '') AS AAA05C, NVL(HIS.CI_MR_FP_PAT.NAME_COUNTRY, '') AS AAA05C_MC, NVL(HIS.CI_MR_FP_PAT.ADDMISSION_WEIGHT, '') AS AAA42, NVL(HIS.CI_MR_FP_PAT.BIRTH_WEIGHT, '') AS AEN01, NVL(HIS.CI_MR_FP_PAT.BIRTH_WEIGHT_ONE, '') AS AEN01_2, NVL(HIS.CI_MR_FP_PAT.BIRTH_WEIGHT_TWO, '') AS AEN01_3, NVL(HIS.CI_MR_FP_PAT.BIRTH_WEIGHT_THREE, '') AS AEN01_4, NVL(HIS.CI_MR_FP_PAT.BIRTH_WEIGHT_FOUR, '') AS AEN01_5, NVL(CASE WHEN HIS.CI_MR_FP_PAT.SD_NATION = '01' THEN 1 WHEN HIS.CI_MR_FP_PAT.SD_NATION = '02' THEN 2 WHEN HIS.CI_MR_FP_PAT.SD_NATION = '03' THEN 3 WHEN HIS.CI_MR_FP_PAT.SD_NATION = '05' THEN 5 WHEN HIS.CI_MR_FP_PAT.SD_NATION = '11' THEN 11 WHEN HIS.CI_MR_FP_PAT.SD_NATION = '97' THEN 66 END, '') AS AAA06C, NVL(HIS.CI_MR_FP_PAT.NAME_NATION, '') AS AAA06C_MC, NVL(HIS.CI_MR_FP_PAT.ID_CODE, '') AS AAA07, NVL(HIS.CI_MR_FP_PAT.SD_MARRY, '') AS AAA08C, NVL(HIS.CI_MR_FP_PAT.NAME_MARRY, '') AS AAA08C_MC, NVL(CI_MR_FP_OTHER.OUT_HOS_MODE, '') AS AEM01C, NVL(CI_MR_FP_OTHER.NAME_OUT_HOS_MODE, '') AS AEM01C_MC, NVL(HIS.CI_MR_FP_PAT.DT_ACPT, '') AS AAB01, NVL(HIS.CI_MR_FP_PAT.DT_END, '') AS AAC01, NVL(HIS.CI_MR_FP_PAT.HOSDAYS, '') AS AAC04, NVL(CI_MR_FP_BL.AMOUNT, '') AS ADA01, NVL(CI_MR_FP_BL.CMS_SPAMOUNT, '') AS ADA0101, NVL(HIS.CI_MR_FP_PAT.N_TIMES_INHOSPITAL, '') AS AAA29, NVL(CASE WHEN HIS.CI_MR_FP_PAT.SD_REFERALSRC = '1' THEN 2 WHEN HIS.CI_MR_FP_PAT.SD_REFERALSRC = '2' THEN 1 WHEN HIS.CI_MR_FP_PAT.SD_REFERALSRC = '3' THEN 3 WHEN HIS.CI_MR_FP_PAT.SD_REFERALSRC = '9' THEN 9 END, '') AS AAB06C, NVL(HIS.CI_MR_FP_PAT.NAME_REFERALSRC, '') AS AAB06C_MC, NVL(CASE WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '1.1' THEN 1 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '1.2' THEN 1 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '2.1' THEN 2 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '2.2' THEN 2 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '3.1' THEN 3 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '3.2' THEN 3 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '4' THEN 4 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '5' THEN 5 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '6' THEN 6 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '7' THEN 7 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '8' THEN 8 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '9' THEN 99 END, '') AS AAA26C, NVL(HIS.CI_MR_FP_PAT.NAME_PAY_METHOD, '') AS AAA26C_MC, NVL(CI_MR_FP_OTHER.NAME_IIOUTREASON, '') AS ABG01N, NVL(CI_MR_FP_OTHER.SD_IIOUTREASON, '') AS ABG01C, NVL(CASE WHEN HIS.CI_MR_FP_PAT.ID_ENT IS NULL THEN 2 ELSE 1 END , '') AS IS_CATA , NVL(CASE WHEN HIS.EN_ENT_IP.SD_STATUS = '25' THEN 1 ELSE 2 END, '') AS in_hospital FROM HIS.CI_MR_FP_PAT LEFT JOIN HIS.EN_ENT ON HIS.EN_ENT.ID_ENT = HIS.CI_MR_FP_PAT.ID_ENT LEFT JOIN HIS.CI_MR_FP_OTHER ON HIS.CI_MR_FP_OTHER.ID_ENT= HIS.CI_MR_FP_PAT.ID_ENT LEFT JOIN HIS.CI_MR_FP_BL ON HIS.CI_MR_FP_OTHER.ID_ENT= HIS.CI_MR_FP_BL.ID_ENT LEFT JOIN HIS.EN_ENT_IP ON HIS.EN_ENT_IP.ID_ENT= HIS.CI_MR_FP_BL.ID_ENT";
        return $sql;
    }

    /**
     *获取patient_hospital_info表Sql
     */
    public function getHospitalSql()
    {
        $sql = "SELECT NVL(HIS.CI_MR_FP_PAT.SD_DEP_PHYADM, '') AS AAB02C, NVL(HIS.CI_MR_FP_PAT.NAME_DEP_PHYADM, '') AS AAB03, NVL(HIS.CI_MR_FP_PAT.SD_IN_BED, '') AS AAB11C, NVL(HIS.CI_MR_FP_PAT.NAME_IN_BED, '') AS AAB11N, NVL(HIS.CI_MR_FP_PAT.SD_DEP_PHYDISC, '') AS AAC02C, NVL(HIS.CI_MR_FP_PAT.NAME_DEP_PHYDISC, '') AS AAC02C_MC, NVL(HIS.CI_MR_FP_PAT.NAME_OUT_BED, '') AS AAC03, NVL(HIS.CI_MR_FP_PAT.SD_OUT_BED, '') AS AAC11C, NVL(HIS.CI_MR_FP_PAT.SD_DEP_TRANS, '') AS AAD01C, NVL(HIS.CI_MR_FP_PAT.NAME_DEP_TRANS, '') AS ZKKBMC, NVL(CI_MR_FP_OTHER.NAME_MED_IN_1, '') AS AEM02, NVL(CI_MR_FP_OTHER.NAME_IS_HAVE_INHOS_PLAN, '') AS AEM03C, NVL(CI_MR_FP_OTHER.GOAL_INHOS_PLAN, '') AS AEM04, NVL(CI_MR_FP_OTHER.NAME_AUT_DEAD_PAT, '') AS AEI01C FROM HIS.CI_MR_FP_PAT LEFT JOIN HIS.CI_MR_FP_OTHER ON HIS.CI_MR_FP_OTHER.ID_ENT= HIS.CI_MR_FP_PAT.ID_ENT";
        return $sql;
    }

    //patient_doctor_info表Sql
    public function getDoctorSql()
    {
        $sql =" SELECT NVL(CI_MR_FP_OTHER.SD_QCP_DOC, '') AS ZKYS_BH, NVL(CI_MR_FP_OTHER.NAME_QCP_DOC, '') AS AED02, NVL(CI_MR_FP_OTHER.SD_QCP_NUR, '') AS ZKHS_BH, NVL(CI_MR_FP_OTHER.NAME_QCP_NUR, '') AS AED03, NVL(CI_MR_FP_OTHER.QC_DATE, '') AS AED04, NVL(CI_MR_FP_OTHER.DIROFDEPT, '') AS AEE01, NVL(CI_MR_FP_OTHER.SD_DIROFDEPT, '') AS AEE01_CODE, NVL(CI_MR_FP_OTHER.NAME_ZR_DOC, '') AS AEE02, NVL(CI_MR_FP_OTHER.NAME_ZZ_DOC, '') AS AEE03, NVL(CI_MR_FP_OTHER.NAME_ZY_DOC, '') AS AEE04, NVL(CI_MR_FP_OTHER.NAME_LEARN_DOC, '') AS AEE05, NVL(CI_MR_FP_OTHER.NAME_INTERN_DOC, '') AS AEE07, NVL(CI_MR_FP_OTHER.SD_INTERN_DOC, '') AS SXYS_BH, NVL(CI_MR_FP_OTHER.NAME_CODER, '') AS AEE08, NVL(CI_MR_FP_OTHER.SD_CODER, '') AS BMY_BH, NVL(CI_MR_FP_OTHER.NAME_EMP_NUR, '') AS AEE10, NVL(CI_MR_FP_OTHER.SD_EMP_NUR, '') AS ZRHS_BH, NVL(CI_MR_FP_OTHER.NAME_TEAM_DOC, '') AS YLZZ, NVL(CI_MR_FP_OTHER.SD_TEAM_DOC, '') AS YLZZ_BH, NVL(CI_MR_FP_OTHER.SD_ZR_DOC, '') AS AEE02_CODE, NVL(CI_MR_FP_OTHER.SD_ZZ_DOC, '') AS AEE03_CODE, NVL(CI_MR_FP_OTHER.SD_ZY_DOC, '') AS AEE04_CODE FROM HIS.CI_MR_FP_PAT LEFT JOIN HIS.CI_MR_FP_OTHER ON HIS.CI_MR_FP_OTHER.ID_ENT= HIS.CI_MR_FP_PAT.ID_ENT ";
        return $sql;
    }

    //patient_contacts_info表sql
    public function getContactsSql()
    {
        $sql = " SELECT NVL(HIS.CI_MR_FP_PAT.NAME_CONT, '') AS AAA22, NVL(HIS.CI_MR_FP_PAT.NAME_CONTTP, '') AS GX_MC, NVL(HIS.CI_MR_FP_PAT.ADDR_CONT, '') AS AAA24, NVL(HIS.CI_MR_FP_PAT.TEL_CONT, '') AS AAA25 FROM HIS.CI_MR_FP_PAT ";
        return $sql;
    }

    //patient_address_info表sql
    public function getAddressSql()
    {
        $sql = "SELECT NVL(HIS.CI_MR_FP_PAT.ADDR_NOW, '') AS AAA15,NVL(HIS.CI_MR_FP_PAT.TEL_ADDR_NOW, '') AS AAA51,NVL(HIS.CI_MR_FP_PAT.ADDR_BORN, '') AS CSD, NVL(HIS.CI_MR_FP_PAT.ADDR_ORIGIN, '') AS GG, NVL(HIS.CI_MR_FP_PAT.ADDR_CENCUS, '') AS AAA12, NVL(HIS.CI_MR_FP_PAT.ZIP_ADDR_CENCUS, '') AS AAA14C, NVL(HIS.CI_MR_FP_PAT.ZIP_ADDR_NOW, '') AS AAA17C FROM HIS.CI_MR_FP_PAT ";
        return $sql;
    }

    //patient_add表sql
    public function getAddSql()
    {
        $sql = " SELECT NVL(HIS.CI_MR_FP_PAT.HEALTH_CARD_ID, '') AS JKKH, NVL(HIS.CI_MR_FP_PAT.NAME_IDTP, '') AS ZJLB_MC, NVL(CI_MR_FP_OTHER.THREELEV_NUR_DAYS, '') AS SJHL, NVL(CI_MR_FP_OTHER.TWOLEV_NUR_DAYS, '') AS EJHL, NVL(CI_MR_FP_OTHER.ONELEV_NUR_DAYS, '') AS YJHL, NVL(CI_MR_FP_OTHER.SUPERLEV_NUR_DAYS, '') AS TJHL, NVL(CI_MR_FP_OTHER.NAME_INPATHSTATUS, '') AS LCLJ, NVL(CI_MR_FP_OTHER.NAME_COMPLETESTATUS, '') AS WCQK, NVL(CI_MR_FP_OTHER.NAME_VARIATIONSTAUS, '') AS BYQK FROM HIS.CI_MR_FP_PAT LEFT JOIN HIS.CI_MR_FP_OTHER ON HIS.CI_MR_FP_OTHER.ID_ENT= HIS.CI_MR_FP_PAT.ID_ENT ";
        return $sql;
    }

    //patient_work_info表sql
    public function getWorkSql()
    {
        $sql = " SELECT NVL(HIS.CI_MR_FP_PAT.SD_OCCU, '') AS AAA18C, NVL(HIS.CI_MR_FP_PAT.NAME_OCCU, '') AS ZY_MC, NVL(HIS.CI_MR_FP_PAT.WORKUNIT, '') AS GZDWJ, NVL(HIS.CI_MR_FP_PAT.ADDR_WORK, '') AS GZDWJDZ, (NVL(HIS.CI_MR_FP_PAT.WORKUNIT, '') || '' || NVL(HIS.CI_MR_FP_PAT.ADDR_WORK, '')) AS AAA19, NVL(HIS.CI_MR_FP_PAT.DEL_ADDR_WORK, '') AS AAA20, NVL(HIS.CI_MR_FP_PAT.ZIP_ADDR_WORK, '') AS AAA21C FROM HIS.CI_MR_FP_PAT ";
        return $sql;
    }

    //patient_medical_info表
    public function getMedicalSql()
    {
        $sql="SELECT NVL(HIS.CI_MR_FP_PAT.SD_OUTP_EMER_DI, '') AS ABA01C, NVL(HIS.CI_MR_FP_PAT.NAME_OUTP_EMER_DI, '') AS ABA01N, NVL(CI_MR_FP_OTHER.SD_DIPATHOLOGY, '') AS ABF01C, NVL(CI_MR_FP_OTHER.NAME_DIPATHOLOGY, '') AS ABF01N, NVL(CI_MR_FP_OTHER.NUM_PATHO, '') AS ABF04, NVL(CI_MR_FP_OTHER.NAME_HIGHTESTDI, '') AS ABF02C, NVL(CI_MR_FP_OTHER.NAME_DRUG_ALLERGY, '') AS AEB02C, NVL(CI_MR_FP_OTHER.ALLERGIC_DRUGS, '') AS AEB01, NVL(CI_MR_FP_OTHER.NAME_QOM_RECORD, '') AS AED01C, NVL(CI_MR_FP_OTHER.NAME_BLOOD_TYPE, '') AS AEG01C, NVL(CI_MR_FP_OTHER.NAME_RH_TYPE, '') AS AEG02C, NVL(CI_MR_FP_OTHER.COMA_TIME_BEF_INHOS_DAYS, '') AS AEJ01, NVL(CI_MR_FP_OTHER.COMA_TIME_BEF_INHOS_HOURS, '') AS AEJ02, NVL(CI_MR_FP_OTHER.COMA_TIME_BEF_INHOS_MINS, '') AS AEJ03, NVL(CI_MR_FP_OTHER.COMA_TIME_INHOS_DAYS, '') AS AEJ04, NVL(CI_MR_FP_OTHER.COMA_TIME_INHOS_HOURS, '') AS AEJ05, NVL(CI_MR_FP_OTHER.COMA_TIME_INHOS_MINS, '') AS AEJ06, NVL(CI_MR_FP_OTHER.VENTILATOR_USE_TIME_DAYS, '') AS AEL01_T, NVL(CI_MR_FP_OTHER.VENTILATOR_USE_TIME_HOURS, '') AS AEL01, NVL(CI_MR_FP_OTHER.VENTILATOR_USE_TIME_MINUTES, '') AS AEL01_F, NVL(CI_MR_FP_OTHER.FG_DAY_SURGERY, '') AS SFRJSS FROM HIS.CI_MR_FP_PAT LEFT JOIN HIS.CI_MR_FP_OTHER ON HIS.CI_MR_FP_OTHER.ID_ENT= HIS.CI_MR_FP_PAT.ID_ENT  ";
        return $sql;
    }

    //patient_cost_info表sql
    public function getCostSql()
    {
        $sql = " SELECT NVL(CI_MR_FP_BL.CMS_GMSFEE, '') AS D11, NVL(CI_MR_FP_BL.CMS_GTOFEE, '') AS D12, NVL(CI_MR_FP_BL.CMS_NURFEE, '') AS D13, NVL(CI_MR_FP_BL.CMS_OTHERFEE, '') AS D14, NVL(CI_MR_FP_BL.DI_PDIFEE, '') AS D15, NVL(CI_MR_FP_BL.DI_LDIFEE, '') AS D16, NVL(CI_MR_FP_BL.DI_IDIFEE, '') AS D17, NVL(CI_MR_FP_BL.DI_CDIFEE, '') AS D18, NVL(CI_MR_FP_BL.TC_NSTPFEE, '') AS D19, NVL(CI_MR_FP_BL.TC_CPTFEE, '') AS D19X01, NVL(CI_MR_FP_BL.TC_STFEE, '') AS D20, NVL(CI_MR_FP_BL.TC_ANFEE, '') AS D20X01, NVL(CI_MR_FP_BL.TC_OPFEE, '') AS D20X02, NVL(CI_MR_FP_BL.RC_RCFEE, '') AS D21, NVL(CI_MR_FP_BL.TCM_CMTFEE, '') AS D22, NVL(CI_MR_FP_BL.WM_WMFEE, '') AS D23, NVL(CI_MR_FP_BL.WM_AGFEE, '') AS D23X01, NVL(CI_MR_FP_BL.TCMT_CPMFEE, '') AS D24, NVL(CI_MR_FP_BL.TCMT_CHMFEE, '') AS D25, NVL(CI_MR_FP_BL.BABP_BFEE, '') AS D26, NVL(CI_MR_FP_BL.BABP_APFEE, '') AS D27, NVL(CI_MR_FP_BL.BABP_GPFEE, '') AS D28, NVL(CI_MR_FP_BL.BABP_BCFFEE, '') AS D29, NVL(CI_MR_FP_BL.BABP_CFLFEE, '') AS D30, NVL(CI_MR_FP_BL.SC_DMMFIFEE, '') AS D31, NVL(CI_MR_FP_BL.SC_DMMFTFEE, '') AS D32, NVL(CI_MR_FP_BL.SC_DMMFSFEE, '') AS D33, NVL(CI_MR_FP_BL.OC_OCFEE, '') AS D34 FROM HIS.CI_MR_FP_PAT LEFT JOIN HIS.CI_MR_FP_BL ON HIS.CI_MR_FP_PAT.ID_ENT= HIS.CI_MR_FP_BL.ID_ENT ";
        return $sql;
    }

    //
    public function getDiagnosisSql()
    {
        $sql = " SELECT NVL(CI_MR_FP_XYDI.SORTNO, '') AS DIA_ORDER, NVL(CASE HIS.CI_MR_FP_XYDI.FG_MAINDI WHEN 'Y' THEN 1 WHEN 'N' THEN 0 ELSE 2 END, '') AS ZZPB, NVL(CI_MR_FP_XYDI.NAME_DI_TYPE, '') AS LBMC, NVL(CI_MR_FP_XYDI.SD_DI, '') AS ICD10_ID1, NVL(CI_MR_FP_XYDI.NAME_DI, '') AS ICD10_NAME, NVL(CI_MR_FP_XYDI.ID_DISLVL_INP, '') AS RYQK, NVL(CI_MR_FP_XYDI.SD_TREATMENT_OUTCOME, '') AS CYQK,NVL ( CI_MR_FP_XYDI.ID_MRFPXYDI, '' ) AS ZDXH FROM HIS.CI_MR_FP_PAT LEFT JOIN HIS.CI_MR_FP_DI ON HIS.CI_MR_FP_DI.ID_ENT = HIS.CI_MR_FP_PAT.ID_ENT LEFT JOIN HIS.CI_MR_FP_XYDI ON HIS.CI_MR_FP_XYDI.ID_MRFPDI = HIS.CI_MR_FP_DI.ID_MRFPDI";
        return $sql;
    }

    //operation表sql
    public function getOperationSql()
    {
        $sql = " SELECT (NVL(HIS.CI_MR_FP_PAT.CODE_AMR_IP, '') || '' || NVL(HIS.CI_MR_FP_PAT.N_TIMES_INHOSPITAL, '')) AS AAA28, NVL(CI_MR_FP_SUG.ID_ENT, '') AS ZYH_ID, NVL(CI_MR_FP_SUG.SD_SUG, '') AS ICD9_ID1, NVL(CI_MR_FP_SUG.NAME_SUG, '') AS ICD9_NAME, NVL(CI_MR_FP_SUG.DT_START_SUG, '') AS OPE_DATE, NVL(CI_MR_FP_SUG.NAME_EMP_SUG, '') AS OPE_MAN_NAME, NVL(CI_MR_FP_SUG.SD_EMP_SUG, '') AS OPE_MAN_CODE, NVL(CI_MR_FP_SUG.SD_EMP_ASST1, '') AS FRIST_ASSISTANT_CODE, NVL(CI_MR_FP_SUG.NAME_EMP_ASST1, '') AS FRIST_ASSISTANT_NAME, NVL(CI_MR_FP_SUG.SD_EMP_ASST2, '') AS SECOND_ASSISTANT_CODE, NVL(CI_MR_FP_SUG.NAME_EMP_ASST2, '') AS SECOND_ASSISTANT_NAME, NVL(CI_MR_FP_SUG.SD_ANESTP, '') AS HOCUS_WAY_ID, NVL(CI_MR_FP_SUG.NAME_ANESTP, '') AS HOCUS_WAY_MC, NVL(CI_MR_FP_SUG.ID_INCICONDI, '') AS INCISION_GRADE_ID, NVL(CI_MR_FP_SUG.NAME_INCICONDI, '') AS INCISION_GRADE_MC, NVL(CI_MR_FP_SUG.SD_EMP_ANES, '') AS HOCUS_MAN_CODE, NVL(CI_MR_FP_SUG.NAME_EMP_ANES, '') AS HOCUS_MAN_NAME, NVL(CI_MR_FP_SUG.DT_START_SUG, '') AS START_TIME, NVL(CI_MR_FP_SUG.DT_END_SUG, '') AS END_TIME, NVL(CI_MR_FP_SUG.SORTNO, '') AS OPE_ORDER, NVL(CI_MR_FP_SUG.SD_LVLSUG, '') AS OPE_LEVEL, NVL(CI_MR_FP_SUG.NAME_LVLSUG, '') AS OPE_LEVEL_MC, NVL(CI_MR_FP_SUG.SD_CLASS_SUG, '') AS SSPB, NVL(CI_MR_FP_SUG.NAME_CLASS_SUG, '') AS SSPB_MC, NVL(CI_MR_FP_SUG.SORTNO, '') AS SFZYSS, NVL(CI_MR_FP_SUG.SD_INCITP, '') AS QKDJ, NVL(CI_MR_FP_SUG.NAME_INCITP, '') AS QKDJ_MC, NVL(CI_MR_FP_SUG.SD_METHOD_SUG, '') AS SSLX, NVL(CI_MR_FP_SUG.NAME_METHOD_SUG, '') AS SSLX_MC,NVL ( CI_MR_FP_SUG.ID_MRFPSUG, '' ) AS SSXH FROM HIS.CI_MR_FP_PAT LEFT JOIN HIS.CI_MR_FP_SUG ON HIS.CI_MR_FP_SUG.ID_ENT = HIS.CI_MR_FP_PAT.ID_ENT";
        return $sql;
    }

    //feeDetailed表sql
    public function getFeeDetailedSql()
    {
        $sql = "SELECT NVL(BL_CG_IP.ID_CGIP, '') AS FYXH, NVL(BL_CG_IP.NAME_SRV, '') AS FYMC, NVL(BL_CG_IP.DT_ST, '') AS JFRQ, NVL(BL_CG_IP.QUAN, '') AS FYSL, NVL(BL_CG_IP.AMT_STD, '') AS FYDJ, NVL(BL_CG_IP.ID_DEP_OR, '') AS FYKS, NVL(BL_CG_IP.NAME_INCCAITM, '') AS FYGB, NVL(BL_CG_IP.ID_CGIP, '') AS JLXH, NVL(BL_CG_IP.ID_DEP_MP, '') AS ZXKS, NVL(BL_CG_IP.CODE_HPPR, '') AS YBBM, NVL(BL_CG_IP.FG_REFUND, '') AS TKBZ, NVL(BL_CG_IP.NAME_ACCOUNT, '') AS SYGB,(NVL ( BL_CG_IP.ID_CGIP, '' ) || '' || NVL (to_char (BL_CG_IP.\"ods_insert_time\", 'yyyymmddHH24miss'),'')) AS FYXH_NO FROM HIS.BL_CG_IP LEFT JOIN HIS.CI_MR_FP_PAT ON HIS.BL_CG_IP.ID_ENT = HIS.CI_MR_FP_PAT.ID_ENT ";
        return $sql;
    }

    //yz表sql
    public function getYzSql()
    {
        $sql =  "SELECT (NVL(CI_MR_FP_PAT.CODE_AMR_IP, '') || '' || NVL(CI_MR_FP_PAT.N_TIMES_INHOSPITAL, '')) AS ZYH, NVL(CI_ORDER.ID_EN, '') AS ZYH_ID, NVL(CI_ORDER.ID_OR, '') AS YZBXH, NVL(CI_ORDER.ID_DEP_OR, '') AS BRKS, NVL(CI_ORDER.ID_WG_OR, '') AS BRBQ, NVL(CI_ORDER.SD_SRVTP, '') AS YDYZLB, NVL(CASE CI_ORDER.FG_LONG WHEN 'Y' THEN 1 WHEN 'N' THEN 2 ELSE 3 END, '') AS YZQX, NVL(CI_ORDER.ID_DEP_OR, '') AS KZKS, NVL(CI_ORDER.ID_EMP_OR, '') AS KZYS, NVL(CI_ORDER.DT_ENTRY, '') AS KZSJ, NVL(CI_ORDER.NAME_OR, '') AS YZMC, NVL(CI_ORDER.ID_FREQ, '') AS YPCD, NVL(CI_ORDER.ID_FREQ, '') AS SYPC, NVL(CI_ORDER.ID_ROUTE, '') AS GYTJ, NVL(CI_ORDER.ID_ROUTE, '') AS YCJL, NVL(CASE CI_ORDER.FG_URGENT WHEN 'Y' THEN 1 WHEN 'N' THEN 0 ELSE 9 END, '') AS JJYZ, NVL(CI_ORDER.SD_SU_OR, '') AS YZZT, NVL(CI_ORDER.SD_SU_MP, '') AS ZXZT, NVL(CI_ORDER.ID_EMP_CHK, '') AS XZJDGH, NVL(CI_ORDER.DT_CHK, '') AS XZJDSJ, NVL( CASE CI_ORDER.FG_SKINTEST WHEN 'Y' THEN 1 WHEN 'N' THEN 0 ELSE 9 END, '' ) AS PSBZ,NVL( CASE CI_ORDER.FG_CANC WHEN 'Y' THEN 1 WHEN 'N' THEN 0 ELSE 9 END, '' ) AS ZFBS FROM HIS.CI_ORDER LEFT JOIN HIS.CI_MR_FP_PAT ON HIS.CI_ORDER.ID_EN = HIS.CI_MR_FP_PAT.ID_ENT ";
        return $sql;
    }

}
