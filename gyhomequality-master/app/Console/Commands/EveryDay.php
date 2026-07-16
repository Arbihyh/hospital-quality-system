<?php

namespace App\Console\Commands;

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
use App\Model\PatientOtherInfo;
use App\Model\PatientWorkInfo;
use App\Model\FeeDetailed;
use App\Model\SecondaryOperation;
use App\Services\DataSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Pheanstalk\Pheanstalk;

class EveryDay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:every';

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
        ini_set('default_socket_timeout', 0);
        $beanstalkd = Pheanstalk::create('beanstalkd')->watch('getOne');
        $config = config('dictionaries');
        while (true){
            $job = $beanstalkd->reserve();
            $data = json_decode($job->getData(), true);
            if (!$data) {
                $beanstalkd->delete($job);
                continue;
            }
            $MED_REC_ID = $data['MED_REC_ID'];
            //跳过已经存在的数据
            if (PatientInfo::query()->where('MED_REC_ID',$MED_REC_ID)->exists()){
                $beanstalkd->delete($job);
                continue;
            }
            try {
                $info = self::selectInsertData($MED_REC_ID);
            }catch (\Exception $e){
                $beanstalkd->delete($job);
                continue;
            }
            if (empty($info)){
                $beanstalkd->delete($job);
                continue;
            }
            //费用查询 返回查询数据
            $feeData = self::selectFeeDetail($MED_REC_ID);
            if (!empty($feeData)){
                //费用数据入库
                self::feeDetail($feeData,$config);
            }
            //手术数据查询
            $operationData = self::selectMainOperation($MED_REC_ID);
            if (!empty($operationData)){
                //手术数据入库
                self::mainOperation($operationData,$config);
            }
            //诊断数据查询
            $diagnosis = self::selectDiagnosis($MED_REC_ID);
            $ABC03C = [];
            if (!empty($diagnosis)){
                //诊断数据入库
                $ABC03C = self::diagnosis($diagnosis);
            }
            //患者信息入库
            self::insertDataMain($info,$ABC03C,$config);
            //费用信息查询
            $feeInfo = self::selectFeeInfo($MED_REC_ID);
            if (!empty($feeInfo)){
                //费用信息入库
                self::feeInfo($feeInfo,$info);
            }
            //icu信息查询
            $icu = self::selectIcuInfo($MED_REC_ID);
            if (!empty($icu)){
                //icu信息入库
                self::icuInfo($icu);
            }
            //医嘱本
            $yizhuben = self::selectYzb($MED_REC_ID);
            if (!empty($yizhuben)){
                self::Yzb($yizhuben);
            }
            // 临床书写首页的手术和诊断信息查询 chenyu
            $ebbInfo = DataSyncService::selectEbbInfo($MED_REC_ID);
            if (!empty($ebbInfo)) {
                DataSyncService::addEbbData($ebbInfo);
            }
            //手术申请信息查询 chenyu
            $sssqInfo = DataSyncService::selectSssqInfo($MED_REC_ID);
            if (!empty($sssqInfo)) {
                DataSyncService::addSssqData($sssqInfo);
            }
            //加入队列
            self::putBeanstalkd($MED_REC_ID);
        }
    }
    //查询患者信息
    public static function selectInsertData($MED_REC_ID){
        $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS', 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql = "SELECT *,to_char(AAA03,'yyyy-mm-dd hh24:mi:ss') as AAA03_A,to_char(SYN_DATE,'yyyy-mm-dd hh24:mi:ss') as SYN_DATE_A,to_char(AAB01,'yyyy-mm-dd hh24:mi:ss') as AAB01_A,to_char(AAC01,'yyyy-mm-dd hh24:mi:ss') as AAC01_A FROM PORTAL_HIS.INIT_MED_REC_MAIN WHERE MED_REC_ID = $MED_REC_ID";
        $result = oci_parse($con, $sql);
        oci_execute($result,OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data = $row;
        }
        unset($con);
        return $data;
    }
    //患者信息
    public static function insertDataMain($data,$ABC03C,$config)
    {
        $hospital_name = config('confAdmin.hospital_name');
        $patient_info['hospital_name'] = $data['ZA03'] ?: $hospital_name;//机构名称
        $patient_info['AAA28'] = $data['AAA28'];
        $patient_info['MED_REC_ID'] = $data['MED_REC_ID'];
        $patient_info['AAA01'] = $data['AAA01'] ?: '';//患者姓名
        $patient_info['AAA02C'] = $data['AAA02C'] ?: '';//患者性别
        $patient_info['AAA03'] = $data['AAA03_A'] ?: '';//出生日期
        $patient_info['AAA04'] = $data['AAA04'] ?: '';//年龄
        $patient_info['AAA05C'] = $data['AAA05C'] ?: '';//国籍
        $patient_info['AAA40'] = $data['AAA40'] ?: '';//不足一周岁年龄
        $patient_info['AAA42'] = $data['AAA42'] ?: '';//新生儿入院体重
        $patient_info['AEN01'] = $data['AEN01'] ?: '';//新生儿出生体重
        $patient_info['AAA06C'] = $data['AAA06C'] ?: '';//民族代码
        $patient_info['AAA07'] = $data['AAA07'] ?: '';//身份证号
        $patient_info['AAA08C'] = $data['AAA08C'] ?: '';//婚姻状况
        $patient_info['AEM01C'] = $data['AEM01C'] ?: '';//离院方式代码
        $patient_info['AAB01'] = $data['AAB01_A'] ?: '';//入院时间（时）
        $patient_info['AAC01'] = $data['AAC01_A'] ?: '';//出院时间
        $patient_info['AAC11N'] = $data['AAC11N'] ?: '';//出院医院内部科室名称
        $patient_info['AAC04'] = $data['AAC04'] ?: '';//实际住院
        $patient_info['ADA01'] = $data['ADA01'] ?: '';//总费用
        $patient_info['ADA0101'] = $data['ADA0101'] ?: '';//自付费用
        $patient_info['AAA29'] = $data['AAA29'] ?: '';//住院次数
        $patient_info['ABG01C'] = $data['ABG01C'] ?: '';//损伤和中毒外部原因编码 no
        $patient_info['ABG01N'] = $data['ABG01N'] ?: '';//损伤和中毒外部原因名称 no
        $patient_info['AAB06C'] = $data['AAB06C'] ?: '';//入院途径代码
        $patient_info['ABC01N'] = $data['ABC01N'] ?: '';//出院主要诊断名称
        $patient_info['ORG_STATE'] = $data['ORG_STATE'] ?: '';//质控状态
        $patient_info['AAA26C'] = $data['AAA26C'] ?: '';//医疗付费方式代码
        $patient_info['ATTEND_GRP_CODE'] = $data['ATTEND_GRP_CODE'] ?: '';//主诊组编码
        $patient_info['ATTEND_GRP_NAME'] = $data['ATTEND_GRP_NAME'] ?: '';//主诊组名称
        PatientInfo::query()->insert($patient_info);
        $patient_other_info['AAA28'] = $data['MED_REC_ID'];
        $patient_other_info['AAB07C'] = $data['AAB07C'] ?: '';//入院诊断id
        $patient_other_info['AAB07N'] = $data['AAB07N'] ?: '';//入院诊断名称
        $patient_other_info['AAB07'] = $data['AAB07'] ?: '';//入院时情况
        $patient_other_info['AAB07D'] = $data['AAB07D'] ?: '';//入院后确诊日期
        $patient_other_info['ABD04'] = $data['ABD04'] ?: '';//医院感染名称
        $patient_other_info['ABD051'] = $data['ABD051'] ?: '';//门诊与出院诊断符合情况
        $patient_other_info['ABD052'] = $data['ABD052'] ?: '';//术前与术后诊断符合情况
        $patient_other_info['ABD053'] = $data['ABD053'] ?: '';//临床与病理诊断符合情况
        $patient_other_info['ABD054'] = $data['ABD054'] ?: '';//放射与病理诊断符合情况
        $patient_other_info['ZB09'] = $data['ZB09'] ?: '';//手机
        $patient_other_info['ZB08'] = $data['ZB08'] ?: '';//
        $patient_other_info['ZB07'] = $data['ZB07'] ?: '';//
        $patient_other_info['ZB06'] = $data['ZB06'] ?: '';//
        $patient_other_info['ZB05'] = $data['ZB05'] ?: '';//
        $patient_other_info['ZB04'] = $data['ZB04'] ?: '';//
        $patient_other_info['ZB03'] = $data['ZB03'] ?: '';//
        $patient_other_info['ZB02'] = $data['ZB02'] ?: '';//
        $patient_other_info['ZB01C'] = $data['ZB01C'] ?: '';//
        $patient_other_info['ZA04'] = $data['ZA04'] ?: '';//
        $patient_other_info['MED_REC_ID'] = $data['MED_REC_ID'] ?: '';//病案⾸⻚ID
        $patient_other_info['UNT_ID'] = $data['UNT_ID'] ?: '';//组织机构代码ID
        $patient_other_info['ZA03'] = $data['ZA03'] ?: '';//机构名称
        $patient_other_info['AFA01'] = $data['AFA01'] ?: '';//抢救次数
        $patient_other_info['AFA02'] = $data['AFA02'] ?: '';//成本次数
        $patient_other_info['AFA03'] = $data['AFA03'] ?: '';//
        $patient_other_info['AFA04'] = $data['AFA04'] ?: '';//
        $patient_other_info['AFA05'] = $data['AFA05'] ?: '';//
        $patient_other_info['AFA06'] = $data['AFA06'] ?: '';//
        $patient_other_info['AFA07'] = $data['AFA07'] ?: '';//
        $patient_other_info['AFA08'] = $data['AFA08'] ?: '';//
        $patient_other_info['AFA09'] = $data['AFA09'] ?: '';//
        $patient_other_info['AFA10'] = $data['AFA10'] ?: '';//
        $patient_other_info['AFA11'] = $data['AFA11'] ?: '';//
        $patient_other_info['AFA12'] = $data['AFA12'] ?: '';//
        $patient_other_info['ZB10'] = $data['ZB10'] ?: '';//填报版本
        $patient_other_info['ZB11'] = $data['ZB11'] ?: '';//填报说明
        $patient_other_info['IS_VALID'] = $data['IS_VALID'] ?: '';//有效标识
        $patient_other_info['SYN_DATE'] = $data['SYN_DATE_A'] ?: '';//获取时间
        $patient_other_info['QU_STATE'] = $data['QU_STATE'] ?: '';//是否采集
        $patient_other_info['DATA_STATE'] = $data['DATA_STATE'] ?: '';//病案采集状态
        $patient_other_info['BALANCEID'] = $data['BALANCEID'] ?: '';//病案流水号
        $patient_other_info['AKC021'] = $data['AKC021'] ?: '';//⼈群类型
        PatientOtherInfo::query()->insert($patient_other_info);
        $patient_medical_info['AAA28'] = $data['MED_REC_ID'];
        $patient_medical_info['ABA01C'] = $data['ABA01C'] ?: '';//门(急)诊诊断编码
        $patient_medical_info['ABA01N'] = $data['ABA01N'] ?: '';//⻔（急）诊诊断名称
        $patient_medical_info['ABC03C'] =  empty($ABC03C[$data['MED_REC_ID']]) ? 4 : (array_search($ABC03C[$data['MED_REC_ID']],$config['RYQK']) ?: 4);//入院病情代码
        $patient_medical_info['ABF01C'] = $data['ABF01C'] ?: '';//入院病情
        $patient_medical_info['ABF01N'] = $data['ABF01N'] ?: '';//病理诊断名称
        $patient_medical_info['ABF04'] = $data['ABF04'] ?: '';//病理号
        $patient_medical_info['ABF02C'] = $data['ABF02C'] ?: '';//最高诊断依据代码ID
        $patient_medical_info['ABF03C'] = $data['ABF03C'] ?: '';//分化程度编码ID
        $patient_medical_info['ABH01C'] = $data['ABH01C'] ?: '';//肿瘤分期是否不详
        $patient_medical_info['ABH0201C'] = $data['ABH0201C'] ?: '';//肿瘤分期 TID
        $patient_medical_info['ABH0202C'] = $data['ABH0202C'] ?: '';//肿瘤分期 NID
        $patient_medical_info['ABH0203C'] = $data['ABH0203C'] ?: '';//肿瘤分期 MID
        $patient_medical_info['ABH03C'] = $data['ABH03C'] ?: '';//0～Ⅳ肿瘤分期ID
        $patient_medical_info['AEB02C'] = $data['AEB02C'] ?: '';//有无药物过敏
        $patient_medical_info['AEB01'] = $data['AEB01'] ?: '';//过敏药物
        $patient_medical_info['AED01C'] = $data['AED01C'] ?: '';//病案质量代码ID
        $patient_medical_info['AEG01C'] = $data['AEG01C'] ?: '';//血型代码ID
        $patient_medical_info['AEG02C'] = $data['AEG02C'] ?: '';//Rh 代码ID
        $patient_medical_info['AEG04'] = $data['AEG04'] ?: '';//红细胞(单位)
        $patient_medical_info['AEG05'] = $data['AEG05'] ?: '';//血小板(袋)
        $patient_medical_info['AEG06'] = $data['AEG06'] ?: '';//血浆(ml)
        $patient_medical_info['AEG07'] = $data['AEG07'] ?: '';//全血(ml)
        $patient_medical_info['AEG08'] = $data['AEG08'] ?: '';//其它(ml)
        $patient_medical_info['AEJ01'] = $data['AEJ01'] ?: '';//颅脑损伤患者入院前昏迷时间（天）
        $patient_medical_info['AEJ02'] = $data['AEJ02'] ?: '';//颅脑损伤患者入院前昏迷时间（天）
        $patient_medical_info['AEJ03'] = $data['AEJ03'] ?: '';//颅脑损伤患者入院前昏迷时间（天）
        $patient_medical_info['AEJ04'] = $data['AEJ04'] ?: '';//颅脑损伤患者入院后昏迷时间（天）
        $patient_medical_info['AEJ05'] = $data['AEJ05'] ?: '';//颅脑损伤患者入院后昏迷时间（天）
        $patient_medical_info['AEJ06'] = $data['AEJ06'] ?: '';//颅脑损伤患者入院后昏迷时间（天）
        $patient_medical_info['AEL01'] = $data['AEL01'] ?: '';//呼吸机使用时间（天）
        $patient_medical_info['AEN02C'] = $data['AEN02C'] ?: '';//新生儿出生缺陷诊断
        $patient_medical_info['AEN02N'] = $data['AEN02N'] ?: '';//新生儿出生缺陷诊断名称
        $patient_medical_info['AEI09'] = $data['AEI09'] ?: '';//日常生活能力评定量得分
        $patient_medical_info['AEI10'] = $data['AEI10'] ?: '';//日常生活能力评定量得分
        $patient_medical_info['AEI08'] = $data['AEI08'] ?: '';//备注
        PatientMedicalInfo::query()->insert($patient_medical_info);
        //患者住院信息
        $patient_hospital_info['AAA28'] = $data['MED_REC_ID'];
        $patient_hospital_info['AAA30'] = $data['AAA30'] ?: '';//住院号
        $patient_hospital_info['ABC01C'] = $data['ABC01C'] ?: '';//出院时主要诊断编码
        $patient_hospital_info['AAA27'] = $data['AAA27'] ?: '-';//医疗保险手册(卡)号
        $patient_hospital_info['AAC001'] = $data['AAC001'] ?: '';//医保个人编号
        $patient_hospital_info['AAB01'] = $data['AAB01_A'] ?: '';//入院时间（时）
        $patient_hospital_info['AAB02C'] = $data['AAB02C'] ?: '';//入院科别代码
        $patient_hospital_info['AAB03'] = $data['AAB03'] ?: '';//入院病房
        $patient_hospital_info['AAB11C'] = $data['AAB11C'] ?: '';//入院医院内部科室代码ID
        $patient_hospital_info['AAB11N'] = $data['AAB11N'] ?: '';//入院医院内部科室名称
        $patient_hospital_info['AAC02C'] = $data['AAC02C'] ?: '';//出院科别代码ID
        $patient_hospital_info['AAC03'] = $data['AAC03'] ?: '';//出院病房
        $patient_hospital_info['AAC11C'] = $data['AAC11C'] ?: '';//出院医院内部科室代码ID
        $patient_hospital_info['AAD01C'] = $data['AAD01C'] ?: '';//转经科别代码ID
        $patient_hospital_info['AEM02'] = $data['AEM02'] ?: '';//医嘱转院、转社区、卫生院机编码ID
        $patient_hospital_info['AEM03C'] = $data['AEM03C'] ?: '';//是否有出院31日内再住院计划
        $patient_hospital_info['AEM04'] = $data['AEM04'] ?: '';//31日内再住院目的
        $patient_hospital_info['AEI01C'] = $data['AEI01C'] ?: '';//是否尸检代码ID
        PatientHospitalInfo::query()->insert($patient_hospital_info);
        //患者医生信息
        $patient_doctor_info['AAA28'] = $data['MED_REC_ID'];
        $patient_doctor_info['AED02'] = $data['AED02'] ?: '';//质控医师姓名
        $patient_doctor_info['AED03'] = $data['AED03'] ?: '';//质控护士姓名
        $patient_doctor_info['AED04'] = $data['AED04'] ?: '';//病案质量检查日期
        $patient_doctor_info['AEE01'] = $data['AEE01'] ?: '';//科主任姓名
        $patient_doctor_info['AEE02'] = $data['AEE02'] ?: '';//主(副主)任医师姓名
        $patient_doctor_info['AEE03'] = $data['AEE03'] ?: '';//主治医师姓
        $patient_doctor_info['AEE11'] = $data['AEE11'] ?: '';//主诊医师执业证书编码
        $patient_doctor_info['AEE09'] = $data['AEE09'] ?: '';//主诊医师姓名
        $patient_doctor_info['AEE04'] = $data['AEE04'] ?: '';//住院医师姓名
        $patient_doctor_info['AEE05'] = $data['AEE05'] ?: '';//进修医师姓名
        $patient_doctor_info['AEE07'] = $data['AEE07'] ?: '';//实习医师姓名
        $patient_doctor_info['AEE08'] = $data['AEE08'] ?: '';//编码员姓名
        $patient_doctor_info['AEE10'] = $data['AEE10'] ?: '';//责任护士姓名
        $patient_doctor_info['AEE01_CODE'] = $data['AEE01_CODE'] ?: '';//科主任编码
        $patient_doctor_info['AEE02_CODE'] = $data['AEE02_CODE'] ?: '';//主（副主）任医师工号
        $patient_doctor_info['AEE03_CODE'] = $data['AEE03_CODE'] ?: '';//主治医师工号
        $patient_doctor_info['AEE04_CODE'] = $data['AEE04_CODE'] ?: '';//住院医师工号
        PatientDoctorInfo::query()->insert($patient_doctor_info);
        //患者地址相关信息
        $patient_address_info['AAA28'] = $data['MED_REC_ID'];
        $patient_address_info['AAA09'] = $data['AAA09'] ?: '';//出生地省（区、市）
        $patient_address_info['AAA10'] = $data['AAA10'] ?: '';//出生地市
        $patient_address_info['AAA11'] = $data['AAA11'] ?: '';//出生地县
        $patient_address_info['AAA43'] = $data['AAA43'] ?: '';//籍贯省（区、市
        $patient_address_info['AAA44'] = $data['AAA44'] ?: '';//籍贯市
        $patient_address_info['AAA45'] = $data['AAA45'] ?: '';//户籍省（区、市）
        $patient_address_info['AAA46'] = $data['AAA46'] ?: '';//户籍市
        $patient_address_info['AAA47'] = $data['AAA47'] ?: '';//户籍县
        $patient_address_info['AAA12'] = $data['AAA12'] ?: '';//户籍详细地址
        $patient_address_info['AAA13C'] = $data['AAA13C'] ?: '';//户籍地址区县编码
        $patient_address_info['AAA33C'] = $data['AAA33C'] ?: '';//户籍街道乡镇代码ID
        $patient_address_info['AAA14C'] = $data['AAA14C'] ?: '';//户籍地址邮政编码
        $patient_address_info['AAA15'] = $data['AAA15'] ?: '';//现住址详细地址
        $patient_address_info['AAA48'] = $data['AAA48'] ?: '';//现住址省（区、市）
        $patient_address_info['AAA49'] = $data['AAA49'] ?: '';//现住址市
        $patient_address_info['AAA50'] = $data['AAA50'] ?: '';//现住址县
        $patient_address_info['AAA16C'] = $data['AAA16C'] ?: '';//现住址区县编码
        $patient_address_info['AAA36C'] = $data['AAA36C'] ?: '';//现住址街道乡镇代码
        $patient_address_info['AAA51'] = $data['AAA51'] ?: '';//现住址电话
        $patient_address_info['AAA17C'] = $data['AAA17C'] ?: '';//现住址邮政编码
        PatientAddressInfo::query()->insert($patient_address_info);
        //患者工作信息
        $patient_work_info['AAA28'] = $data['MED_REC_ID'];
        $patient_work_info['AAA18C'] = $data['AAA18C'] ?: '';//职业代码ID
        $patient_work_info['AAA19'] = $data['AAA19'] ?: '';//工作单位及地址
        $patient_work_info['AAA20'] = $data['AAA20'] ?: '';//工作单位电话
        $patient_work_info['AAA21C'] = $data['AAA21C'] ?: '';//工作单位邮政编码
        PatientWorkInfo::query()->insert($patient_work_info);
        //患者联系人信息
        $patient_contacts_info['AAA28'] = $data['MED_REC_ID'];
        $patient_contacts_info['AAA22'] = $data['AAA22'] ?: '';//联系人姓名
        $patient_contacts_info['AAA23C'] = $data['AAA23C'] ?: '';//联系人关系代码ID
        $patient_contacts_info['AAA24'] = $data['AAA24'] ?: '';//联系人地址
        $patient_contacts_info['AAA25'] = $data['AAA25'] ?: '';//联系人电话
        PatientContactsInfo::query()->insert($patient_contacts_info);
    }
    //查询费用明细
    public static function selectFeeDetail($list){
        $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS',"UTF8");
        $sql = "SELECT ZYH,FYXH,FYMC,to_char(ZFJE,'fm9999990.000') as ZFJE,to_char(JFRQ,'yyyy-mm-dd hh24:mi:ss') as JFRQ,FYSL,to_char(FYDJ,'fm9999990.000') as FYDJ,to_char(ZJE,'fm9999990.000') as ZJE,FYKS,FYGB,SYFYGB FROM PORTAL_HIS.V_JMGS_BASY_FYMX WHERE ZYH = $list";
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);
        return $result;
    }
    //费用明细
    public static function feeDetail($data,$config){
        $fee = [];
        foreach ($data as $item) {
            $fee[] = [
                'AAA28' => $item['ZYH'],//病案号
                'FYXH' => $item['FYXH'] ?? '',//费用序号
                'FYMC' => $item['FYMC'] ?? '',//费用名称
                'ZFJE' => $item['ZFJE'] ?? '',//自付金额
                'JFRQ' => $item['JFRQ'] ?? '',//计费日期
                'FYSL' => $item['FYSL'] ?? '',//费用数量
                'FYDJ' => $item['FYDJ'] ?? '',//费用单价
                'ZJE' => $item['ZJE'] ?? '',//总金额
                'FYKS' => $item['FYKS'] ?? '',//费用科室
                'FYGB' => array_search($item['FYGB'], $config) ?? '',//费用归并
                'SYFYGB' => array_search($item['SYFYGB'], $config) ?? '',//首页费用归并
            ];
        }
        $start = 0;
        while (1){
            $temFee = array_slice($fee,$start,10);
            if (empty($temFee)){
                break;
            }
            FeeDetailed::query()->insert($temFee);
            $start += 10;
        }

    }
    //手术数据查询
    public static function selectMainOperation($list){
        $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS',"UTF8");
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql = "SELECT ZYH,SSCZBM,to_char(SSCZRQ,'yyyy-mm-dd hh24:mi:ss') as SSCZRI,SFZYSS,SSSX,SSCZMC,SSJB,SSLX,SZXM,SZBM,YZYSBM,YZXM,EZXM,EZYSBM,QKDJ,YHDJ,MZFS,MZYSXM,MZYSBM,to_char(SSKSSJ,'yyyy-mm-dd hh24:mi:ss') as SSKSSJ,to_char(SSJSSJ,'yyyy-mm-dd hh24:mi:ss') as SSJSSJ,SFWRJSS,SSPB FROM PORTAL_HIS.V_JMGS_BASY_SS WHERE ZYH = $list";
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);
        return $result;
    }
    //手术数据
    public static function mainOperation($data,$config){
        foreach ($data as $item) {
            if ($item['SFZYSS'] == 1){
                $main[] = [
                    'AAA28' => $item['ZYH'],
                    'ICD9_ID1' => $item['SSCZBM'] ?? '',//手术或操作ID
                    'ICD9_NAME' => $item['SSCZMC'] ?? '',//手术或操作名称
                    'OPE_DATE' => $item['SSCZRI'] ?? '',//手术或操作日期
                    'OPE_ORDER' =>  $item['SSSX'] ?? '',//手术序号
                    'OPE_LEVEL' => $item['SSJB'] ?? '',//手术级别
                    'OPE_TYPE' => $item['SSLX'] ?? '',//手术类型
                    'OPE_MAN_NAME' => $item['SZXM'] ?? '',//主刀医师姓名
                    'OPE_MAN_CODE' => $item['SZBM'] ?? '',//主刀医师编码
                    'FRIST_ASSISTANT_CODE' => $item['YZYSBM'] ?? '',//一助医师编码
                    'FRIST_ASSISTANT_NAME' => $item['YZXM'] ?? '',//一助医师姓名
                    'SECOND_ASSISTANT_CODE' => $item['EZYSBM'] ?? '',//二助医师编码
                    'SECOND_ASSISTANT_NAME' => $item['EZXM'] ?? '',//二助医师姓名
                    'INCISION_GRADE_ID' => $item['QKDJ'] ?? 100,//切口等级
                    'HEAL_ID' => $item['YHDJ'] ?? 100,//愈合等级
                    'HOCUS_WAY_ID' => $item['MZFS'] ?? '',//麻醉方式
                    'HOCUS_MAN_CODE' => $item['MZYSBM'] ?? '',//麻醉医师编码
                    'HOCUS_MAN_NAME' => $item['MZYSXM'] ?? '',//麻醉医师名称
                    'START_TIME' => $item['SSKSSJ'] ?? '',//手术开始时间
                    'END_TIME' => $item['SSJSSJ'] ?? '',//手术结束时间
                    'RJSS' => $item['SFWRJSS'] ?? '',//是否日间手术
                    'SSPB' => array_search($item['SSPB'],$config['SSPB']) ?? 5//手术判别
                ];
            }else{
                $other[] = [
                    'AAA28' => $item['ZYH'],
                    'ICD9_ID1' => $item['SSCZBM'] ?? '',//手术或操作ID
                    'ICD9_NAME' => $item['SSCZMC'] ?? '',//手术或操作名称
                    'OPE_DATE' => $item['SSCZRI'] ?? '',//手术或操作日期
                    'OPE_ORDER' =>  $item['SSSX'] ?? '',//手术序号
                    'OPE_LEVEL' => $item['SSJB'] ?? '',//手术级别
                    'OPE_TYPE' => $item['SSLX'] ?? '',//手术类型
                    'OPE_MAN_NAME' => $item['SZXM'] ?? '',//主刀医师姓名
                    'OPE_MAN_CODE' => $item['SZBM'] ?? '',//主刀医师编码
                    'FRIST_ASSISTANT_CODE' => $item['YZYSBM'] ?? '',//一助医师编码
                    'FRIST_ASSISTANT_NAME' => $item['YZXM'] ?? '',//一助医师姓名
                    'SECOND_ASSISTANT_CODE' => $item['EZYSBM'] ?? '',//二助医师编码
                    'SECOND_ASSISTANT_NAME' => $item['EZXM'] ?? '',//二助医师姓名
                    'INCISION_GRADE_ID' => $item['QKDJ'] ?? 100,//切口等级
                    'HEAL_ID' => $item['YHDJ'] ?? 100,//愈合等级
                    'HOCUS_WAY_ID' => $item['MZFS'] ?? '',//麻醉方式
                    'HOCUS_MAN_CODE' => $item['MZYSBM'] ?? '',//麻醉医师编码
                    'HOCUS_MAN_NAME' => $item['MZYSXM'] ?? '',//麻醉医师名称
                    'START_TIME' => $item['SSKSSJ'] ?? '',//手术开始时间
                    'END_TIME' => $item['SSJSSJ'] ?? '',//手术结束时间
                    'RJSS' => $item['SFWRJSS'] ?? '',//是否日间手术
                    'SSPB' => array_search($item['SSPB'],$config['SSPB']) ?? 5//手术判别
                ];
            }
        }
        if (!empty($main)) {
            MainOperation::query()->insert($main);
        }
        if (!empty($other)){
            SecondaryOperation::query()->insert($other);
        }
    }
    //诊断数据查询
    public static function selectDiagnosis($list){
        $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS',"UTF8");
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql1 = "SELECT * FROM PORTAL_HIS.V_JMGS_BASY_ZD WHERE ZYH = $list";
        $data = oci_parse($con, $sql1);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);
        return $result;
    }
    //诊断数据写入
    public static function diagnosis($data){
        $main = [];
        $diagnosis = [];
        foreach ($data as $item){
            if ($item['ZZPB'] == 1){
                $main[] = [
                    'AAA28' => $item['ZYH'],
                    'ICD10_ID1' => $item['ZDBM'],
                    'ICD10_NAME' => $item['ZDMC'],
                    'DIA_ORDER' => $item['ZDXH'],
                    'LBMC' =>  $item['LBMC'],
                    'RYQK' => $item['RYQK']
                ];
            }else{
                $diagnosis[] = [
                    'AAA28' => $item['ZYH'],
                    'ICD10_ID1' => $item['ZDBM'],
                    'ICD10_NAME' => $item['ZDMC'],
                    'DIA_ORDER' => $item['ZDXH'],
                    'LBMC' =>  $item['LBMC'],
                    'RYQK' => $item['RYQK']
                ];
            }
        }
        if (!empty($diagnosis)){
            OtherDiagnosis::query()->insert($diagnosis);
        }
        if (!empty($main)){
            MainDiagnosis::query()->insert($main);
        }
        return array_column($main,'RYQK','AAA28');
    }
    //费用信息
    public static function selectFeeInfo($list){
        $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS','UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql = "SELECT A.*,to_char(ZKRQ,'yyyy-mm-dd hh24:mi:ss') as ZKRQ1 FROM PORTAL_HIS.V_JMGS_BASY_FY A WHERE ZYH = $list";
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result = $row;
        }
        unset($con);
        return $result;
    }
    //费用信息
    public static function feeInfo($data,$info){
        $fee = [];
        $add = [];
        if ($data){
            $fee = [
                'AAA28' => $data['ZYH'],
                'ADA0101' => $info['ADA0101'] ?? 0,
                'D11' => $data['YBYLFWF'] ?? '',
                'D12' => $data['YBZLCZF'] ?? '',
                'D13' => $data['HLF'] ?? '',
                'D14' => $data['ZHYLFWLQTFY'] ?? '',
                'D15' => $data['BLZDF'] ?? '',
                'D16' => $data['SYSZDF'] ?? '',
                'D17' => $data['YXXZDF'] ?? '',
                'D18' => $data['LCZDXMF'] ?? '',
                'D19' => $data['FSSZLXMF'] ?? '',
                'D19X01' => $data['LCWLZLF'] ?? '',
                'D20' => $data['SSZLF'] ?? '',
                'D20X01' => $data['MZF'] ?? '',
                'D20X02' => $data['SSF'] ?? '',
                'D21' => $data['KFF'] ?? '',
                'D22' => $data['ZYZLF'] ?? '',
                'D23' => $data['XYF'] ?? '',
                'D23X01' => $data['KJYWF'] ?? '',
                'D24' => $data['ZCHENGYF'] ?? '',
                'D25' => $data['ZCAOYF'] ?? '',
                'D26' => $data['XF'] ?? '',
                'D27' => $data['BDBLZPF'] ?? '',
                'D28' => $data['QDBLZPF'] ?? '',
                'D29' => $data['NXYZLZPF'] ?? '',
                'D30' => $data['XBYZLZPF'] ?? '',
                'D31' => $data['JCYYCXYYCLF'] ?? '',
                'D32' => $data['ZLYYCXYYCLF'] ?? '',
                'D33' => $data['SSYYCXYYCLF'] ?? '',
                'D34' => $data['QTF'] ?? '',
            ];
            $add = [
                'AAA28' => $data['ZYH'],
                'TYSHXYDM' => $data['TYSHXYDM'] ?? '',
                'JKKH' => $data['JKKH'] ?? '',
                'SFZJLX' => $data['ZJLB'] ?? '',
                'SJHL' => $data['SJHL'] ?? '',
                'EJHL' => $data['EJHL'] ?? '',
                'YJHL' => $data['YJHL'] ?? '',
                'TJHL' => $data['TJHL'] ?? '',
                'ZRHS' => $data['ZRHS'] ?? '',
                'ZRHSBM' => $data['ZRHSBM'] ?? '',
                'ZKHS' => $data['ZKHS'] ?? '',
                'ZKHSBM' => $data['ZKHSBM'] ?? '',
                'ZKRQ' => $data['ZKRQ1'] ?? '',
                'ZHFZRYS' => $data['ZHFZRYS'] ?? '',
                'ZZYSBM' => $data['ZZYSBM'] ?? '',
                'ZYYSBM' => $data['ZYYSBM'] ?? '',
                'ZZZYSBM' => $data['ZZZYSBM'] ?? '',
                'KZRXM' => $data['KZRXM'] ?? '',
                'ZHFZRYSXM' => $data['ZHFZRYSXM'] ?? '',
                'ZZYSXM' => $data['ZZYSXM'] ?? '',
                'ZYYSXM' => $data['ZYYSXM'] ?? '',
                'ZZYISXM' => $data['ZZYISXM'] ?? '',
                'BMY' => $data['BMY'] ?? '',
                'RYKB' => $data['RYKB'] ?? '',
                'BFRY' => $data['BFRY'] ?? '',
                'ZKKB' => $data['ZKKB'] ?? '',
                'CYKB' => $data['CYKB'] ?? '',
                'HB' => $data['HB'] ?? '',
                'HCV' => $data['HCV'] ?? '',
                'HIV' => $data['HIV'] ?? '',
                'LCLJ' => $data['LCLJ'] ?? '', // 临床路径
                'WCQK' => $data['WCQK'] ?? '',
                'BYQK' => $data['BYQK'] ?? '',
            ];
        }

        if (!empty($fee)) {
            PatientCostInfo::query()->insert($fee);
        }
        if (!empty($add)) {
            PatientAdd::query()->insert($add);
        }
    }
    //查询icu信息
    public static function selectIcuInfo($list){
        $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS',"UTF8");
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql = "SELECT MED_REC_ID,AREA_ID,BATCH_ID,IS_MAIN_WAY,to_char(IN_TIME,'yyyy-mm-dd hh24:mi:ss') as IN_TIME,to_char(OUT_TIME,'yyyy-mm-dd hh24:mi:ss') as OUT_TIME FROM PORTAL_HIS.INIT_MED_REC_TUTORSSIP WHERE MED_REC_ID = $list";
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);
        return $result;
    }
    //icu信息入库
    public static function icuInfo($data){
        $icu = [];
        foreach ($data as $item) {
            $icu[] = [
                'AAA28' => $item['MED_REC_ID'],
                'IS_MAIN_WAY' => $item['IS_MAIN_WAY']??'',
                'IN_TIME' => $item['IN_TIME']??'',
                'OUT_TIME' => $item['OUT_TIME']??'',
                'AREA_ID' => $item['AREA_ID'] ?? 0,
                'BATCH_ID' => $item['BATCH_ID']??'',
            ];
        }
        if (!empty($icu)){
            \App\Model\Icu::query()->insert($icu);
        }
    }
    //医嘱本数据查询
    public static function selectYzb($list){
        $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS',"UTF8");
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql = "SELECT A.*,to_char(TZSJ,'yyyy-mm-dd hh24:mi:ss') as TJ,to_char(XZJDSJ,'yyyy-mm-dd hh24:mi:ss') as XJ,to_char(TZQRSJ,'yyyy-mm-dd hh24:mi:ss') as TZJ,to_char(APSJ,'yyyy-mm-dd hh24:mi:ss') as AJ,to_char(KZSJ,'yyyy-mm-dd hh24:mi:ss') as KJ,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZJ FROM PORTAL_HIS.EMR_YZB A WHERE ZYH = $list";
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);
        return $result;
    }
    //医嘱本数据
    public static function Yzb($data){
        foreach ($data as $item) {
            $list = [
                'ZYH' => $item['ZYH'],
                'YZBXH' => $item['YZBXH'] ?? '',
                'RID' => $item['RID'] ?? '',
                'YEPB' => $item['YEPB'] ?? '',
                'BRKS' =>  $item['BRKS'] ?? '',
                'BRBQ' => $item['BRBQ'] ?? '',
                'BRCH' => $item['BRCH'] ?? '',
                'YDYZLB' => $item['YDYZLB'] ?? '',
                'XMLB' => $item['XMLB'] ?? '',
                'XMID' => $item['XMID'] ?? '',
                'XMDJ' => $item['XMDJ'] ?? '',
                'YZZH' => $item['YZZH'] ?? '',
                'YZQX' => $item['YZQX'] ?? '',
                'YYSX' => $item['YYSX'] ?? '',
                'KZKS' => $item['KZKS'] ?? '',
                'KZYS' => $item['KZYS'] ?? '',
                'KZSJ' => $item['KJ'] ?? '',
                'YZMC' => $item['YZMC'] ?? '',
                'YPCD' => $item['YPCD'] ?? '',
                'FYSX' => $item['FYSX'] ?? '',
                'SYPC' => $item['SYPC'] ?? '',
                'GYTJ' => $item['GYTJ'] ?? '',
                'YCJL' => $item['YCJL'] ?? '',
                'JLDW' => $item['JLDW'] ?? '',
                'ZL' => $item['ZL'] ?? '',
                'ZLDW' => $item['ZLDW'] ?? '',
                'JJYZ' => $item['JJYZ'] ?? '',
                'BLYZ' => $item['BLYZ'] ?? '',
                'TZSJ' => $item['TJ'] ?? '',
                'TZYS' => $item['TZYS'] ?? '',
                'YZZT' => $item['YZZT'] ?? '',
                'ZXZT' => $item['ZXZT'] ?? '',
                'KZDY' => $item['KZDY'] ?? '',
                'ZTBZ' => $item['ZTBZ'] ?? '',
                'XZJDGH' => $item['XZJDGH'] ?? '',
                'XZJDSJ' => $item['XJ'] ?? '',
                'TZQRGH' => $item['TZQRGH'] ?? '',
                'TZQRSJ' => $item['TZJ'] ?? '',
                'APSJ' => $item['AJ'] ?? '',
                'YYTS' => $item['YYTS'] ?? '',
                'YSZT' => $item['YSZT'] ?? '',
                'SRCS' => $item['SRCS'] ?? '',
                'SRSD' => $item['SRSD'] ?? '',
                'ZXSD' => $item['ZXSD'] ?? '',
                'DS' => $item['DS'] ?? '',
                'DSDW' => $item['DSDW'] ?? '',
                'PSBZ' => $item['PSBZ'] ?? '',
                'PSJG' => $item['PSJG'] ?? '',
                'ZFPB' => $item['ZFPB'] ?? '',
                'YBLX' => $item['YBLX'] ?? '',
                'SPBH' => $item['SPBH'] ?? '',
                'CYJF' => $item['CYJF'] ?? '',
                'PLSX' => $item['PLSX'] ?? '',
                'CZBZ' => $item['CZBZ'] ?? '',
                'BZXX' => $item['BZXX'] ?? '',
                'SQDH' => $item['SQDH'] ?? '',
                'ZXKS' => $item['ZXKS'] ?? '',
                'YFGG' => $item['YFGG'] ?? '',
                'YFDW' => $item['YFDW'] ?? '',
                'YFBZ' => $item['YFBZ'] ?? '',
                'SFSJ' => $item['SFSJ'] ?? '',
                'YFYY' => $item['YFYY'] ?? '',
                'YFYYYY' => $item['YFYYYY'] ?? '',
                'QXKZ' => $item['QXKZ'] ?? '',
                'YYPS' => $item['YYPS'] ?? '',
                'FZLJ' => $item['FZLJ'] ?? '',
                'PASSINDEX' => $item['PASSINDEX'] ?? '',
                'QXMC' => $item['QXMC'] ?? '',
                'YZPLZH' => $item['YZPLZH'] ?? '',
                'LCTS' => $item['LCTS'] ?? '',
                'ZLFY' => $item['ZLFY'] ?? '',
                'YZLX' => $item['YZLX'] ?? '',
                'SSYZ' => $item['SSYZ'] ?? '',
                'CDA_PC' => $item['CDA_PC'] ?? '',
                'ZXSJ' => $item['ZJ'] ?? '',
                'NWARN' => $item['NWARN'] ?? ''
            ];
            \App\Model\Yzb::query()->insert($list);
        }
    }
    //队列
    public static function putBeanstalkd($MED_REC_ID)
    {
        //质控+统计
        $beanstalkd = Pheanstalk::create('beanstalkd')->useTube('validate');
        $beanstalkd->put(json_encode(['AAA28' => $MED_REC_ID]),0,5);
        unset($beanstalkd);//php对象引用问题，必须释放，避免出错
    }
}
