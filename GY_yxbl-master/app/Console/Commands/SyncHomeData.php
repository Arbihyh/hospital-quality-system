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
use App\Services\OdsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Reader\Ods;

class SyncHomeData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-home-data {startDate?} {endDate?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步patient_info前一天数据所有';
    
    /**
     * @var string[]
     */
    private $ageRegexp;
    /**
     * @var string[]
     */
    private $ageYcRegexp;
    /**
     * @var array
     */
    private $addressRegexp;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
//        $this->addressRegexp = RuleWordMap::query()->whereIn('id',['4041','4042','4011','4012','4021','4022','4023','4031','4032','4033'])->pluck('keyword','id')->toArray();
//        $this->ageRegexp = [1=>'/(\d+)岁/',2=>'/(\d+)月/'];
//        $this->ageYcRegexp = [1=>'/(.*?(岁))/u',2=>'/(.*?(月))/u'];
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $this->info('数据同步开始 - 开始');
        $carbon = new Carbon();
        //获取质控时间
        $startDate = data_get($this->arguments(),'startDate');
        $endDate = data_get($this->arguments(),'endDate');

        $startTime = $carbon::parse(($startDate?:NULL))->format("Y-m-d 00:00:00");

        $endTime = $endDate ? $carbon::parse($endDate)->format('Y-m-d 23:59:59') : date("Y-m-d 23:59:59");


        $this->info("开始时间：{$startTime},结束时间：{$endTime}");

        $syncExtendInfo = (new SyncExtendInfo);

        //开始循环
        $page = 1;
        $pageSize = 100;
        while (true) {
//            $patientData = PatientInfo::query()
//                ->whereBetween('AAC01', [$startTime,$endTime])
//                ->orderBy('AAC01','desc')
//                ->paginate($pageSize, ['id', 'MED_REC_ID','AAA28','AAA29','ZYH_ID'], 'page', $page)
//                ->toArray();
//            if (empty($patientData['data'])) {
//                break;
//            }
            $patientData = OdsService::getService()->getSyncData()->getResult();
            if(empty($patientData)) break;

            $page++;
            foreach ($patientData as $value) {
                echo $value['ZYH_ID'] . "开始时间:".date("Y-m-d H:i:s")."\n";
                if($value['IS_CATA'] == 1){
                    $this->zkInfo($value);//获取质控数据
                }else{
                    $syncExtendInfo->zkInfo($value);
                }
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

        $patientInfo = OdsService::getService()->getInfoSql()->setCommonWhere($ZYH_ID)->getResult();
        if (is_array($patientInfo) && count($patientInfo) > 0) $patientInfo = $patientInfo[0];

        //如果没有获取到数据则去mysql库取,获取到了则更新mysql到数据
        if (!empty($patientInfo)){
            //清洗年龄 年
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
//        $patientHospitalInfo = OdsService::getOdsInfoV2($this->getHospitalSql(),$ZYH_ID);
        $patientHospitalInfo = OdsService::getService()->getHospitalSql()->setCommonWhere($ZYH_ID)->getResult();
        if (is_array($patientHospitalInfo) && count($patientHospitalInfo) > 0) $patientHospitalInfo = $patientHospitalInfo[0];

        if (!empty($patientHospitalInfo)){
            $patientHospitalInfo['updated_at'] = $patientHospitalInfo['created_at'] = $currentTime;
            PatientHospitalInfo::query()->updateOrInsert(['ZYH_ID'=> $ZYH_ID],$patientHospitalInfo);
        }

        //patient_doctor_info表
//        $patientDoctorInfo = OdsService::getOdsInfoV2($this->getDoctorSql(),$ZYH_ID);
        $patientDoctorInfo = OdsService::getService()->getDoctorSql()->setCommonWhere($ZYH_ID)->getResult();
        if (is_array($patientDoctorInfo) && count($patientDoctorInfo) > 0) $patientDoctorInfo = $patientDoctorInfo[0];
        if (!empty($patientDoctorInfo)){
            //$patientDoctorInfo['updated_at'] = $patientDoctorInfo['created_at'] = $currentTime;
            PatientDoctorInfo::query()->updateOrInsert(['ZYH_ID'=> $ZYH_ID],$patientDoctorInfo);
        }

        //patient_contacts_info表
//        $patientContactsInfo = OdsService::getOdsInfoV2($this->getContactsSql(),$ZYH_ID);
        $patientContactsInfo = OdsService::getService()->getContactsSql()->setCommonWhere($ZYH_ID)->getResult();
        if (is_array($patientContactsInfo) && count($patientContactsInfo) > 0) $patientContactsInfo = $patientContactsInfo[0];
        if (!empty($patientContactsInfo)){
            $patientContactsInfo['updated_at'] = $patientContactsInfo['created_at'] =  $currentTime;
            PatientContactsInfo::query()->updateOrInsert(['ZYH_ID'=> $ZYH_ID],$patientContactsInfo);
        }
        //patient_address_info表
//        $patientAddressInfo = OdsService::getOdsInfoV2($this->getAddressSql(),$ZYH_ID);
        $patientAddressInfo = OdsService::getService()->getAddressSql()->setCommonWhere($ZYH_ID)->getResult();
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
//        $patientAdd = OdsService::getOdsInfoV2($this->getAddSql(),$ZYH_ID);
        $patientAdd = OdsService::getService()->getAddSql()->setCommonWhere($ZYH_ID)->getResult();
        if (is_array($patientAdd) && count($patientAdd) > 0) $patientAdd = $patientAdd[0];
        if (!empty($patientAdd)){
            $patientAdd['updated_at'] = $patientAdd['created_at'] = $currentTime;
            PatientAdd::query()->updateOrInsert(['ZYH_ID'=> $ZYH_ID],$patientAdd);
        }

        //patient_work_info表
//        $patientWorkInfo = OdsService::getOdsInfoV2($this->getWorkSql(),$ZYH_ID);
        $patientWorkInfo = OdsService::getService()->getWorkSql()->setCommonWhere($ZYH_ID)->getResult();
        if (is_array($patientWorkInfo) && count($patientWorkInfo) > 0) $patientWorkInfo = $patientWorkInfo[0];
        if (!empty($patientWorkInfo)){
            $patientWorkInfo['updated_at'] = $patientWorkInfo['created_at'] = $currentTime;
            PatientWorkInfo::query()->updateOrInsert(['ZYH_ID'=> $ZYH_ID],$patientWorkInfo);
        }

        //patient_medical_info表
//        $patientMedicalInfo = OdsService::getOdsInfoV2($this->getMedicalSql(),$ZYH_ID);
        $patientMedicalInfo = OdsService::getService()->getMedicalSql()->setCommonWhere($ZYH_ID)->getResult();
        if (is_array($patientMedicalInfo) && count($patientMedicalInfo) > 0) $patientMedicalInfo = $patientMedicalInfo[0];
        if (!empty($patientMedicalInfo)){
            $patientMedicalInfo['updated_at'] = $patientMedicalInfo['created_at'] =  $currentTime;
            PatientMedicalInfo::query()->updateOrInsert(['ZYH_ID'=> $ZYH_ID],$patientMedicalInfo);
        }

        //patient_cost_info表
//        $patientCostInfo = OdsService::getOdsInfoV2($this->getCostSql(),$ZYH_ID);
        $patientCostInfo = OdsService::getService()->getCostSql()->setCommonWhere($ZYH_ID)->getResult();
        if (is_array($patientCostInfo) && count($patientCostInfo) > 0) $patientCostInfo = $patientCostInfo[0];
        if (!empty($patientCostInfo)){
            $patientCostInfo['updated_at'] = $patientCostInfo['created_at'] = $currentTime;
            PatientCostInfo::query()->updateOrInsert(['ZYH_ID'=> $ZYH_ID],$patientCostInfo);
        }

        //所属院区
        $YqCode = [];
        if(!empty($patientHospitalInfo['AAC02C']))
        {
//            $YqCode = OdsService::getYqV2($patientHospitalInfo['AAC02C']);
            $YqCode = OdsService::getService()->getYq($patientHospitalInfo['AAC02C'])->getResult();
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
//        $diagnosisData = OdsService::getOdsInfoV2($this->getDiagnosisSql(),$ZYH_ID);
        $diagnosisData = OdsService::getService()->getDiagnosisSql()->setCommonWhere($ZYH_ID)->getResult();
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
//        $operationData = OdsService::getOdsInfoV2($this->getOperationSql(),$ZYH_ID);
        $operationData = OdsService::getService()->getOperationSql()->setCommonWhere($ZYH_ID)->getResult();
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
//        $feeDetailedData = OdsService::getOdsInfoV2($this->getFeeDetailedSql(),$ZYH_ID);
        $feeDetailedData = OdsService::getService()->getFeeDetailedSql()->setCommonWhere($ZYH_ID)->getResult();
        if (!empty($feeDetailedData)){
            foreach ($feeDetailedData as $v){
                $v['updated_at'] = $v['created_at'] = $currentTime;;
                FeeDetailed::query()->updateOrInsert(['FYXH_NO' => $v['FYXH_NO']],$v);
            }
        }

        //医嘱
//        $yzData = OdsService::getOdsInfoV2($this->getYzSql(),$ZYH_ID);
        $yzData = OdsService::getService()->getYzSql()->setCommonWhere($ZYH_ID)->getResult();
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


    private function getPatientInfo($time){
        $date_time = Carbon::parse($time)->format("Y-m-d 00:00:00");
        return "SELECT NVL(HIS.CI_MR_FP_PAT.ID_ORG, '') AS hospital_name, NVL(HIS.CI_MR_FP_PAT.CODE_AMR_IP, '') AS AAA28, NVL(HIS.CI_MR_FP_PAT.ID_ENT, '') AS ZYH_ID, (NVL(HIS.CI_MR_FP_PAT.CODE_AMR_IP, '') || '' || NVL(HIS.CI_MR_FP_PAT.N_TIMES_INHOSPITAL, '')) AS MED_REC_ID, NVL(HIS.CI_MR_FP_PAT.NAME_PAT, '') AS AAA01, NVL(CASE WHEN HIS.CI_MR_FP_PAT.ID_SEX = '@@@@Z81000000003NSBP' THEN 0 WHEN HIS.CI_MR_FP_PAT.ID_SEX = '@@@@Z81000000003NSBQ' THEN 1 WHEN HIS.CI_MR_FP_PAT.ID_SEX = '@@@@Z81000000003NSBR' THEN 2 WHEN HIS.CI_MR_FP_PAT.ID_SEX = '' THEN 9 END, '') AS AAA02C, NVL(HIS.CI_MR_FP_PAT.NAME_SEX, '') AS AAA02C_MC, NVL(EN_ENT.DT_BIRTH_PAT, '') AS AAA03, NVL(HIS.CI_MR_FP_PAT.AGE, '') AS AAA04_1, NVL(CASE WHEN HIS.CI_MR_FP_PAT.SD_COUNTRY = '156' THEN 1 WHEN HIS.CI_MR_FP_PAT.SD_COUNTRY = 'CHN' THEN 1 ELSE 2 END, '') AS AAA05C, NVL(HIS.CI_MR_FP_PAT.NAME_COUNTRY, '') AS AAA05C_MC, NVL(HIS.CI_MR_FP_PAT.ADDMISSION_WEIGHT, '') AS AAA42, NVL(HIS.CI_MR_FP_PAT.BIRTH_WEIGHT, '') AS AEN01, NVL(HIS.CI_MR_FP_PAT.BIRTH_WEIGHT_ONE, '') AS AEN01_2, NVL(HIS.CI_MR_FP_PAT.BIRTH_WEIGHT_TWO, '') AS AEN01_3, NVL(HIS.CI_MR_FP_PAT.BIRTH_WEIGHT_THREE, '') AS AEN01_4, NVL(HIS.CI_MR_FP_PAT.BIRTH_WEIGHT_FOUR, '') AS AEN01_5, NVL(CASE WHEN HIS.CI_MR_FP_PAT.SD_NATION = '01' THEN 1 WHEN HIS.CI_MR_FP_PAT.SD_NATION = '02' THEN 2 WHEN HIS.CI_MR_FP_PAT.SD_NATION = '03' THEN 3 WHEN HIS.CI_MR_FP_PAT.SD_NATION = '05' THEN 5 WHEN HIS.CI_MR_FP_PAT.SD_NATION = '11' THEN 11 WHEN HIS.CI_MR_FP_PAT.SD_NATION = '97' THEN 66 END, '') AS AAA06C, NVL(HIS.CI_MR_FP_PAT.NAME_NATION, '') AS AAA06C_MC, NVL(HIS.CI_MR_FP_PAT.ID_CODE, '') AS AAA07, NVL(HIS.CI_MR_FP_PAT.SD_MARRY, '') AS AAA08C, NVL(HIS.CI_MR_FP_PAT.NAME_MARRY, '') AS AAA08C_MC, NVL(CI_MR_FP_OTHER.OUT_HOS_MODE, '') AS AEM01C, NVL(CI_MR_FP_OTHER.NAME_OUT_HOS_MODE, '') AS AEM01C_MC, NVL(HIS.CI_MR_FP_PAT.DT_ACPT, '') AS AAB01, NVL(HIS.CI_MR_FP_PAT.DT_END, '') AS AAC01, NVL(HIS.CI_MR_FP_PAT.HOSDAYS, '') AS AAC04, NVL(CI_MR_FP_BL.AMOUNT, '') AS ADA01, NVL(CI_MR_FP_BL.CMS_SPAMOUNT, '') AS ADA0101, NVL(HIS.CI_MR_FP_PAT.N_TIMES_INHOSPITAL, '') AS AAA29, NVL(CASE WHEN HIS.CI_MR_FP_PAT.SD_REFERALSRC = '1' THEN 2 WHEN HIS.CI_MR_FP_PAT.SD_REFERALSRC = '2' THEN 1 WHEN HIS.CI_MR_FP_PAT.SD_REFERALSRC = '3' THEN 3 WHEN HIS.CI_MR_FP_PAT.SD_REFERALSRC = '9' THEN 9 END, '') AS AAB06C, NVL(HIS.CI_MR_FP_PAT.NAME_REFERALSRC, '') AS AAB06C_MC, NVL(CASE WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '1.1' THEN 1 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '1.2' THEN 1 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '2.1' THEN 2 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '2.2' THEN 2 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '3.1' THEN 3 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '3.2' THEN 3 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '4' THEN 4 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '5' THEN 5 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '6' THEN 6 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '7' THEN 7 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '8' THEN 8 WHEN HIS.CI_MR_FP_PAT.SD_PAY_METHOD = '9' THEN 99 END, '') AS AAA26C, NVL(HIS.CI_MR_FP_PAT.NAME_PAY_METHOD, '') AS AAA26C_MC, NVL(CI_MR_FP_OTHER.NAME_IIOUTREASON, '') AS ABG01N, NVL(CI_MR_FP_OTHER.SD_IIOUTREASON, '') AS ABG01C,NVL( CASE WHEN HIS.CI_MR_FP_PAT_CATA.ID_ENT IS NULL THEN 2 ELSE 1 END,'') AS IS_CATA,NVL( CASE WHEN HIS.EN_ENT_IP.SD_STATUS = '25' THEN 1 ELSE 2 END,'') AS in_hospital FROM HIS.CI_MR_FP_PAT LEFT JOIN HIS.EN_ENT ON HIS.EN_ENT.ID_ENT = HIS.CI_MR_FP_PAT.ID_ENT LEFT JOIN HIS.CI_MR_FP_OTHER ON HIS.CI_MR_FP_OTHER.ID_ENT= HIS.CI_MR_FP_PAT.ID_ENT LEFT JOIN HIS.CI_MR_FP_BL ON HIS.CI_MR_FP_OTHER.ID_ENT= HIS.CI_MR_FP_BL.ID_ENT LEFT JOIN HIS.EN_ENT_IP ON HIS.EN_ENT_IP.ID_ENT = HIS.CI_MR_FP_PAT.ID_ENT LEFT JOIN HIS.CI_MR_FP_PAT_CATA ON HIS.CI_MR_FP_PAT_CATA.ID_ENT = HIS.CI_MR_FP_PAT.ID_ENT WHERE (HIS.EN_ENT_IP.SD_STATUS = '25' OR HIS.EN_ENT_IP.SD_STATUS = '28') AND HIS.CI_MR_FP_PAT.\"ods_update_time\" >= TO_DATE('$start','yyyy-mm-dd hh24:mi:ss') ORDER BY HIS.CI_MR_FP_PAT.\"ods_update_time\" ASC ";
    }

}
