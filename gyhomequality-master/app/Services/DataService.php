<?php

namespace App\Services;

use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\PatientInfo;
use App\Model\PatientOtherInfo;
use App\Model\PatientMedicalInfo;
use App\Model\PatientHospitalInfo;
use App\Model\PatientDoctorInfo;
use App\Model\PatientAddressInfo;
use App\Model\PatientWorkInfo;
use App\Model\PatientContactsInfo;
use App\Model\Icu;
use App\Model\PatientCostInfo;
use Illuminate\Support\Facades\DB;

class DataService
{
    public function insertData()
    {
        $INIT_MED_REC_MAIN =  DB::connection('oracle')->table('INIT_MED_REC_MAIN')->get();
        $INIT_MED_REC_OPERATION = DB::connection('oracle')->table('INIT_MED_REC_OPERATION')->get();
        $INIT_MED_REC_DIAGNOSIS = DB::connection('oracle')->table('INIT_MED_REC_DIAGNOSIS')->get();
        $INIT_MED_REC_TUTORSSIP = DB::connection('oracle')->table('INIT_MED_REC_TUTORSSIP')->get();

        $patient_info['AAA28'] = $INIT_MED_REC_MAIN['AAA28'];
        $patient_info['AAA01'] = $INIT_MED_REC_MAIN['AAA01'];//患者姓名
        $patient_info['AAA02C'] = $INIT_MED_REC_MAIN['AAA02C'];//患者性别
        $patient_info['AAA03'] = $INIT_MED_REC_MAIN['AAA03'];//出生日期
        $patient_info['AAA04'] = $INIT_MED_REC_MAIN['AAA04'];//年龄
        $patient_info['AAA05C'] = $INIT_MED_REC_MAIN['AAA05C'];//国籍
        $patient_info['AAA40'] = $INIT_MED_REC_MAIN['AAA40'];//不足一周岁年龄
        $patient_info['AAA42'] = $INIT_MED_REC_MAIN['AAA42'];//新生儿入院体重
        $patient_info['AEN01'] = $INIT_MED_REC_MAIN['AEN01'];//新生儿出生体重
        $patient_info['AAA06C'] = $INIT_MED_REC_MAIN['AAA06C'];//民族代码
        $patient_info['AAA07'] = $INIT_MED_REC_MAIN['AAA07'];//身份证号
        $patient_info['AAA08C'] = $INIT_MED_REC_MAIN['AAA08C'];//婚姻状况
        $patient_info['AEM01C'] = $INIT_MED_REC_MAIN['AEM01C'];//离院方式代码
        $patient_info['AAC01'] = $INIT_MED_REC_MAIN['AAC01'];//出院时间
        $patient_info['AAC11N'] = $INIT_MED_REC_MAIN['AAC11N'];//出院医院内部科室名称
        $patient_info['AAC04'] = $INIT_MED_REC_MAIN['AAC04'];//实际住院
        $patient_info['ADA01'] = $INIT_MED_REC_MAIN['ADA01'];//总费用
        $patient_info['AAA29'] = $INIT_MED_REC_MAIN['AAA29'];//住院次数
        $patient_info['ABG01C'] = $INIT_MED_REC_MAIN['ABG01C'];//损伤和中毒外部原因编码 no
        $patient_info['ABG01N'] = $INIT_MED_REC_MAIN['ABG01N'];//损伤和中毒外部原因名称 no
        $patient_info['AAB06C'] = $INIT_MED_REC_MAIN['AAB06C'];//入院途径代码
        $patient_info['ABC01N'] = $INIT_MED_REC_MAIN['ABC01N'];//出院主要诊断名称
        $patient_info['ICD9_NAME'] = $INIT_MED_REC_OPERATION['ICD9_NAME'];//主手术名称
        $patient_info['ORG_STATE'] = $INIT_MED_REC_MAIN['ORG_STATE'];//质控状态
        $patient_info['AAA26C'] = $INIT_MED_REC_MAIN['AAA26C'];//医疗付费方式代码
        $patient_info['ATTEND_GRP_CODE'] = $INIT_MED_REC_MAIN['ATTEND_GRP_CODE'];//主诊组编码
        $patient_info['ATTEND_GRP_NAME'] = $INIT_MED_REC_MAIN['ATTEND_GRP_NAME'];//主诊组名称
//        $patient_info['F_D'] = $INIT_TD_MED_REC_MAIN_MONITOR['F_D'];//药品费用 no
//        $patient_info['J'] = $INIT_TD_MED_REC_MAIN_MONITOR['J'];//材料费用 no
        PatientInfo::query()->insert($patient_info);


        $patient_other_info['AAA28'] = $INIT_MED_REC_MAIN['AAA28'];
        $patient_other_info['AAB07C'] = $INIT_MED_REC_MAIN['AAB07C'];//入院诊断id
        $patient_other_info['AAB07N'] = $INIT_MED_REC_MAIN['AAB07N'];//入院诊断名称
        $patient_other_info['AAB07'] = $INIT_MED_REC_MAIN['AAB07'];//入院时情况
        $patient_other_info['AAB07D'] = $INIT_MED_REC_MAIN['AAB07D'];//入院后确诊日期
        $patient_other_info['ABD04'] = $INIT_MED_REC_MAIN['ABD04'];//医院感染名称
        $patient_other_info['ABD051'] = $INIT_MED_REC_MAIN['ABD051'];//门诊与出院诊断符合情况
        $patient_other_info['ABD052'] = $INIT_MED_REC_MAIN['ABD052'];//术前与术后诊断符合情况
        $patient_other_info['ABD053'] = $INIT_MED_REC_MAIN['ABD053'];//临床与病理诊断符合情况
        $patient_other_info['ABD054'] = $INIT_MED_REC_MAIN['ABD054'];//放射与病理诊断符合情况
        $patient_other_info['ZB09'] = $INIT_MED_REC_MAIN['ZB09'];//手机
        $patient_other_info['ZB08'] = $INIT_MED_REC_MAIN['ZB08'];//
        $patient_other_info['ZB07'] = $INIT_MED_REC_MAIN['ZB07'];//
        $patient_other_info['ZB06'] = $INIT_MED_REC_MAIN['ZB06'];//
        $patient_other_info['ZB05'] = $INIT_MED_REC_MAIN['ZB05'];//
        $patient_other_info['ZB04'] = $INIT_MED_REC_MAIN['ZB04'];//
        $patient_other_info['ZB03'] = $INIT_MED_REC_MAIN['ZB03'];//
        $patient_other_info['ZB02'] = $INIT_MED_REC_MAIN['ZB02'];//
        $patient_other_info['ZB01C'] = $INIT_MED_REC_MAIN['ZB01C'];//
        $patient_other_info['ZA04'] = $INIT_MED_REC_MAIN['ZA04'];//
        $patient_other_info['MED_REC_ID'] = $INIT_MED_REC_MAIN['MED_REC_ID'];//病案⾸⻚ID
        $patient_other_info['UNT_ID'] = $INIT_MED_REC_MAIN['UNT_ID'];//组织机构代码ID
        $patient_other_info['ZA03'] = $INIT_MED_REC_MAIN['ZA03'];//机构名称
        $patient_other_info['AFA01'] = $INIT_MED_REC_MAIN['AFA01'];//抢救次数
        $patient_other_info['AFA02'] = $INIT_MED_REC_MAIN['AFA02'];//成本次数
        $patient_other_info['AFA03'] = $INIT_MED_REC_MAIN['AFA03'];//
        $patient_other_info['AFA04'] = $INIT_MED_REC_MAIN['AFA04'];//
        $patient_other_info['AFA05'] = $INIT_MED_REC_MAIN['AFA05'];//
        $patient_other_info['AFA06'] = $INIT_MED_REC_MAIN['AFA06'];//
        $patient_other_info['AFA07'] = $INIT_MED_REC_MAIN['AFA07'];//
        $patient_other_info['AFA08'] = $INIT_MED_REC_MAIN['AFA08'];//
        $patient_other_info['AFA09'] = $INIT_MED_REC_MAIN['AFA09'];//
        $patient_other_info['AFA010'] = $INIT_MED_REC_MAIN['AFA010'];//
        $patient_other_info['AFA011'] = $INIT_MED_REC_MAIN['AFA011'];//
        $patient_other_info['AFA012'] = $INIT_MED_REC_MAIN['AFA012'];//
        $patient_other_info['ZB10'] = $INIT_MED_REC_MAIN['ZB10'];//填报版本
        $patient_other_info['ZB11'] = $INIT_MED_REC_MAIN['ZB11'];//填报说明
        $patient_other_info['IS_VALID'] = $INIT_MED_REC_MAIN['IS_VALID'];//有效标识
        $patient_other_info['SYN_DATE'] = $INIT_MED_REC_MAIN['SYN_DATE'];//获取时间
        $patient_other_info['QU_STATE'] = $INIT_MED_REC_MAIN['QU_STATE'];//是否采集
        $patient_other_info['DATA_STATE'] = $INIT_MED_REC_MAIN['DATA_STATE'];//病案采集状态
        $patient_other_info['BALANCEID'] = $INIT_MED_REC_MAIN['BALANCEID'];//病案流水号
        $patient_other_info['AKC021'] = $INIT_MED_REC_MAIN['AKC021'];//⼈群类型
        PatientOtherInfo::query()->insert($patient_other_info);

        $patient_medical_info['AAA28'] = $INIT_MED_REC_MAIN['AAA28'];
        $patient_medical_info['ABA01C'] = $INIT_MED_REC_MAIN['ABA01C'];//门(急)诊诊断编码
        $patient_medical_info['ABA01N'] = $INIT_MED_REC_MAIN['ABA01N'];//⻔（急）诊诊断名称
        $patient_medical_info['ABC03C'] = $INIT_MED_REC_MAIN['ABC03C'];//入院病情代码
        $patient_medical_info['ABF01C'] = $INIT_MED_REC_MAIN['ABF01C'];//入院病情
        $patient_medical_info['ABF01N'] = $INIT_MED_REC_MAIN['ABF01N'];//病理诊断名称
        $patient_medical_info['ABF04'] = $INIT_MED_REC_MAIN['ABF04'];//病理号
        $patient_medical_info['ABF02C'] = $INIT_MED_REC_MAIN['ABF02C'];//最高诊断依据代码ID
        $patient_medical_info['ABF03C'] = $INIT_MED_REC_MAIN['ABF03C'];//分化程度编码ID
        $patient_medical_info['ABH01C'] = $INIT_MED_REC_MAIN['ABH01C'];//肿瘤分期是否不详
        $patient_medical_info['ABH0201C'] = $INIT_MED_REC_MAIN['ABH0201C'];//肿瘤分期 TID
        $patient_medical_info['ABH0202C'] = $INIT_MED_REC_MAIN['ABH0202C'];//肿瘤分期 NID
        $patient_medical_info['ABH0203C'] = $INIT_MED_REC_MAIN['ABH0203C'];//肿瘤分期 MID
        $patient_medical_info['ABH03C'] = $INIT_MED_REC_MAIN['ABH03C'];//0～Ⅳ肿瘤分期ID
        $patient_medical_info['AEB02C'] = $INIT_MED_REC_MAIN['AEB02C'];//有无药物过敏
        $patient_medical_info['AEB01'] = $INIT_MED_REC_MAIN['AEB01'];//过敏药物
        $patient_medical_info['AED01C'] = $INIT_MED_REC_MAIN['AED01C'];//病案质量代码ID
        $patient_medical_info['AEG01C'] = $INIT_MED_REC_MAIN['AEG01C'];//血型代码ID
        $patient_medical_info['AEG02C'] = $INIT_MED_REC_MAIN['AEG02C'];//Rh 代码ID
        $patient_medical_info['AEG04'] = $INIT_MED_REC_MAIN['AEG04'];//红细胞(单位)
        $patient_medical_info['AEG05'] = $INIT_MED_REC_MAIN['AEG05'];//血小板(袋)
        $patient_medical_info['AEG06'] = $INIT_MED_REC_MAIN['AEG06'];//血浆(ml)
        $patient_medical_info['AEG07'] = $INIT_MED_REC_MAIN['AEG07'];//全血(ml)
        $patient_medical_info['AEG08'] = $INIT_MED_REC_MAIN['AEG08'];//其它(ml)
        $patient_medical_info['AEJ01'] = $INIT_MED_REC_MAIN['AEJ01'];//颅脑损伤患者入院前昏迷时间（天）
        $patient_medical_info['AEJ02'] = $INIT_MED_REC_MAIN['AEJ02'];//颅脑损伤患者入院前昏迷时间（天）
        $patient_medical_info['AEJ03'] = $INIT_MED_REC_MAIN['AEJ03'];//颅脑损伤患者入院前昏迷时间（天）
        $patient_medical_info['AEJ04'] = $INIT_MED_REC_MAIN['AEJ04'];//颅脑损伤患者入院后昏迷时间（天）
        $patient_medical_info['AEJ05'] = $INIT_MED_REC_MAIN['AEJ05'];//颅脑损伤患者入院后昏迷时间（天）
        $patient_medical_info['AEJ06'] = $INIT_MED_REC_MAIN['AEJ06'];//颅脑损伤患者入院后昏迷时间（天）
        $patient_medical_info['AEL01'] = $INIT_MED_REC_MAIN['AEL01'];//呼吸机使用时间（天）
        $patient_medical_info['AEN02C'] = $INIT_MED_REC_MAIN['AEN02C'];//新生儿出生缺陷诊断
        $patient_medical_info['AEN02N'] = $INIT_MED_REC_MAIN['AEN02N'];//新生儿出生缺陷诊断名称
        $patient_medical_info['AEI09'] = $INIT_MED_REC_MAIN['AEI09'];//日常生活能力评定量得分
        $patient_medical_info['AEI10'] = $INIT_MED_REC_MAIN['AEI10'];//日常生活能力评定量得分
        $patient_medical_info['AEI08'] = $INIT_MED_REC_MAIN['AEI08'];//备注
        PatientMedicalInfo::query()->insert($patient_medical_info);

        //患者住院信息
        $patient_hospital_info['AAA28'] = $INIT_MED_REC_MAIN['AAA28'];
        $patient_hospital_info['AAA30'] = $INIT_MED_REC_MAIN['AAA30'];//住院号
        $patient_hospital_info['ABC01C'] = $INIT_MED_REC_MAIN['ABC01C'];//出院时主要诊断编码
        $patient_hospital_info['AAA27'] = $INIT_MED_REC_MAIN['AAA27'];//医疗保险手册(卡)号
        $patient_hospital_info['AAC001'] = $INIT_MED_REC_MAIN['AAC001'];//医保个人编号
        $patient_hospital_info['AAB01'] = $INIT_MED_REC_MAIN['AAB01'];//入院时间（时）
        $patient_hospital_info['AAB02C'] = $INIT_MED_REC_MAIN['AAB02C'];//入院科别代码
        $patient_hospital_info['AAB03'] = $INIT_MED_REC_MAIN['AAB03'];//入院病房
        $patient_hospital_info['AAB11C'] = $INIT_MED_REC_MAIN['AAB11C'];//入院医院内部科室代码ID
        $patient_hospital_info['AAB11N'] = $INIT_MED_REC_MAIN['AAB11N'];//入院医院内部科室名称
        $patient_hospital_info['AAC02C'] = $INIT_MED_REC_MAIN['AAC02C'];//出院科别代码ID
        $patient_hospital_info['AAC03'] = $INIT_MED_REC_MAIN['AAC03'];//出院病房
        $patient_hospital_info['AAC11C'] = $INIT_MED_REC_MAIN['AAC11C'];//出院医院内部科室代码ID
        $patient_hospital_info['AAD01C'] = $INIT_MED_REC_MAIN['AAD01C'];//转经科别代码ID
        $patient_hospital_info['AEM02'] = $INIT_MED_REC_MAIN['AEM02'];//医嘱转院、转社区、卫生院机编码ID
        $patient_hospital_info['AEM03C'] = $INIT_MED_REC_MAIN['AEM03C'];//是否有出院31日内再住院计划
        $patient_hospital_info['AEM04'] = $INIT_MED_REC_MAIN['AEM04'];//31日内再住院目的
        $patient_hospital_info['AEI01C'] = $INIT_MED_REC_MAIN['AEI01C'];//是否尸检代码ID
        PatientHospitalInfo::query()->insert($patient_medical_info);

        //患者医生信息
        $patient_doctor_info['AAA28'] = $INIT_MED_REC_MAIN['AAA28'];
        $patient_doctor_info['AED02'] = $INIT_MED_REC_MAIN['AED02'];//质控医师姓名
        $patient_doctor_info['AED03'] = $INIT_MED_REC_MAIN['AED03'];//质控护士姓名
        $patient_doctor_info['AED04'] = $INIT_MED_REC_MAIN['AED04'];//病案质量检查日期
        $patient_doctor_info['AEE01'] = $INIT_MED_REC_MAIN['AEE01'];//科主任姓名
        $patient_doctor_info['AEE02'] = $INIT_MED_REC_MAIN['AEE02'];//主(副主)任医师姓名
        $patient_doctor_info['AEE03'] = $INIT_MED_REC_MAIN['AEE03'];//主治医师姓
        $patient_doctor_info['AEE11'] = $INIT_MED_REC_MAIN['AEE11'];//主诊医师执业证书编码
        $patient_doctor_info['AEE09'] = $INIT_MED_REC_MAIN['AEE09'];//主诊医师姓名
        $patient_doctor_info['AEE04'] = $INIT_MED_REC_MAIN['AEE04'];//住院医师姓名
        $patient_doctor_info['AEE05'] = $INIT_MED_REC_MAIN['AEE05'];//进修医师姓名
        $patient_doctor_info['AEE07'] = $INIT_MED_REC_MAIN['AEE07'];//实习医师姓名
        $patient_doctor_info['AEE08'] = $INIT_MED_REC_MAIN['AEE08'];//编码员姓名
        $patient_doctor_info['AEE10'] = $INIT_MED_REC_MAIN['AEE10'];//责任护士姓名
        $patient_doctor_info['CODE_DATE'] = $INIT_MED_REC_MAIN['CODE_DATE'];//编码完成时间
        $patient_doctor_info['COMPLETION_DATE'] = $INIT_MED_REC_MAIN['COMPLETION_DATE'];//病历完成时间
        $patient_doctor_info['SIGN_IN_DATE'] = $INIT_MED_REC_MAIN['SIGN_IN_DATE'];//病案签收时间
        $patient_doctor_info['QUALITY_CONTROL'] = $INIT_MED_REC_MAIN['QUALITY_CONTROL'];//终末质控完成时间
        $patient_doctor_info['AEE02_CODE'] = $INIT_MED_REC_MAIN['AEE02_CODE'];//主（副主）任医师工号
        $patient_doctor_info['AEE03_CODE'] = $INIT_MED_REC_MAIN['AEE03_CODE'];//主治医师工号
        $patient_doctor_info['AEE04_CODE'] = $INIT_MED_REC_MAIN['AEE04_CODE'];//住院医师工号
        PatientDoctorInfo::query()->insert($patient_doctor_info);

        //患者地址相关信息
        $patient_address_info['AAA28'] = $INIT_MED_REC_MAIN['AAA28'];
        $patient_address_info['AAA09'] = $INIT_MED_REC_MAIN['AAA09'];//出生地省（区、市）
        $patient_address_info['AAA10'] = $INIT_MED_REC_MAIN['AAA10'];//出生地市
        $patient_address_info['AAA11'] = $INIT_MED_REC_MAIN['AAA11'];//出生地县
        $patient_address_info['AAA43'] = $INIT_MED_REC_MAIN['AAA43'];//籍贯省（区、市
        $patient_address_info['AAA44'] = $INIT_MED_REC_MAIN['AAA44'];//籍贯市
        $patient_address_info['AAA45'] = $INIT_MED_REC_MAIN['AAA45'];//户籍省（区、市）
        $patient_address_info['AAA46'] = $INIT_MED_REC_MAIN['AAA46'];//户籍市
        $patient_address_info['AAA47'] = $INIT_MED_REC_MAIN['AAA47'];//户籍县
        $patient_address_info['AAA12'] = $INIT_MED_REC_MAIN['AAA12'];//户籍详细地址
        $patient_address_info['AAA13C'] = $INIT_MED_REC_MAIN['AAA13C'];//户籍地址区县编码
        $patient_address_info['AAA33C'] = $INIT_MED_REC_MAIN['AAA33C'];//户籍街道乡镇代码ID
        $patient_address_info['AAA14C'] = $INIT_MED_REC_MAIN['AAA14C'];//户籍地址邮政编码
        $patient_address_info['AAA15'] = $INIT_MED_REC_MAIN['AAA15'];//现住址详细地址
        $patient_address_info['AAA48'] = $INIT_MED_REC_MAIN['AAA48'];//现住址省（区、市）
        $patient_address_info['AAA49'] = $INIT_MED_REC_MAIN['AAA49'];//现住址市
        $patient_address_info['AAA50'] = $INIT_MED_REC_MAIN['AAA50'];//现住址县
        $patient_address_info['AAA16C'] = $INIT_MED_REC_MAIN['AAA16C'];//现住址区县编码
        $patient_address_info['AAA36C'] = $INIT_MED_REC_MAIN['AAA36C'];//现住址街道乡镇代码
        $patient_address_info['AAA51'] = $INIT_MED_REC_MAIN['AAA51'];//现住址电话
        $patient_address_info['AAA17C'] = $INIT_MED_REC_MAIN['AAA17C'];//现住址邮政编码
        PatientAddressInfo::query()->insert($patient_address_info);

        //患者工作信息
        $patient_work_info['AAA28'] = $INIT_MED_REC_MAIN['AAA28'];
        $patient_work_info['AAA18C'] = $INIT_MED_REC_MAIN['AAA18C'];//职业代码ID
        $patient_work_info['AAA19'] = $INIT_MED_REC_MAIN['AAA19'];//工作单位及地址
        $patient_work_info['AAA20'] = $INIT_MED_REC_MAIN['AAA20'];//工作单位电话
        $patient_work_info['AAA21C'] = $INIT_MED_REC_MAIN['AAA21C'];//工作单位邮政编码
        PatientWorkInfo::query()->insert($patient_work_info);

        //患者联系人信息
        $patient_contacts_info['AAA28'] = $INIT_MED_REC_MAIN['AAA28'];
        $patient_contacts_info['AAA22'] = $INIT_MED_REC_MAIN['AAA22'];//联系人姓名
        $patient_contacts_info['AAA23C'] = $INIT_MED_REC_MAIN['AAA23C'];//联系人关系代码ID
        $patient_contacts_info['AAA24'] = $INIT_MED_REC_MAIN['AAA24'];//联系人地址
        $patient_contacts_info['AAA25'] = $INIT_MED_REC_MAIN['AAA25'];//联系人电话
        PatientContactsInfo::query()->insert($patient_contacts_info);

        //患者重症信息
        $icu['AAA28'] = $INIT_MED_REC_MAIN['AAA28'];
        $icu['IS_MAIN_WAY'] = $INIT_MED_REC_OPERATION['IS_MAIN_WAY'];//是否主要术式
        $icu['IN_TIME'] = $INIT_MED_REC_TUTORSSIP['IN_TIME'];//监护室进入日期时间
        $icu['OUT_TIME'] = $INIT_MED_REC_TUTORSSIP['OUT_TIME'];//监护室退出日期时间
        $icu['AREA_ID'] = $INIT_MED_REC_MAIN['AREA_ID'];//行政区划代码ID
        $icu['BATCH_ID'] = $INIT_MED_REC_MAIN['BATCH_ID'];//批次号
        Icu::query()->insert($icu);

        //患者主要诊断信息
        $main_diagnosis['AAA28'] = $INIT_MED_REC_MAIN['AAA28'];
        $main_diagnosis['ICD10_ID1'] = $INIT_MED_REC_DIAGNOSIS['ICD10_ID1'];//诊断编码(ICD-10)
        $main_diagnosis['ICD10_NAME'] = $INIT_MED_REC_DIAGNOSIS['ICD10_NAME'];//出院诊断名称
        $main_diagnosis['DIA_ORDER'] = $INIT_MED_REC_DIAGNOSIS['DIA_ORDER'];//诊断次序
        $main_diagnosis['AREA_ID'] = $INIT_MED_REC_MAIN['AREA_ID'];//行政区划代码ID
        $main_diagnosis['BATCH_ID'] = $INIT_MED_REC_MAIN['BATCH_ID'];//批次号
        MainDiagnosis::query()->insert($main_diagnosis);

        //患者其他诊断信息
        $other_diagnosis['AAA28'] = $INIT_MED_REC_MAIN['AAA28'];
//        $other_diagnosis['ICD10_ID1'] = $INIT_MED_REC_MAIN['ICD10_ID1'];//


        //患者费用信息
        $patient_cost_info['AAA28'] = $INIT_MED_REC_MAIN['AAA28'];
        $patient_cost_info['ADA0101'] = $INIT_MED_REC_MAIN['ADA0101'];//自付金额
        $patient_cost_info['AAE040'] = $INIT_MED_REC_MAIN['AAE040'];//结算时间
        $patient_cost_info['D11'] = $INIT_MED_REC_MAIN['D11'];//一般医疗服务费
        PatientCostInfo::query()->insert($patient_cost_info);

        //患者主要手术信息
        $main_operation['AAA28'] = $INIT_MED_REC_MAIN['AAA28'];
        $main_operation['ICD9_ID1'] = $INIT_MED_REC_OPERATION['ICD9_ID1'];//手术或操作ID
        $main_operation['ICD9_NAME'] = $INIT_MED_REC_OPERATION['ICD9_NAME'];//手术或操作名称
        $main_operation['OPE_DATE'] = $INIT_MED_REC_OPERATION['OPE_DATE'];//手术或操作日期
        $main_operation['OPE_MAN_NAME'] = $INIT_MED_REC_OPERATION['OPE_MAN_NAME'];//主刀医师姓名
        $main_operation['OPE_MAN_CODE'] = $INIT_MED_REC_OPERATION['OPE_MAN_CODE'];//主刀医师编码
        $main_operation['FRIST_ASSISTANT_CODE'] = $INIT_MED_REC_OPERATION['FRIST_ASSISTANT_CODE'];//一助医师编码
        $main_operation['FRIST_ASSISTANT_NAME'] = $INIT_MED_REC_OPERATION['FRIST_ASSISTANT_NAME'];//一助医师姓名
        $main_operation['SECOND_ASSISTANT_CODE'] = $INIT_MED_REC_OPERATION['SECOND_ASSISTANT_CODE'];//二助医师编码
        $main_operation['SECOND_ASSISTANT_NAME'] = $INIT_MED_REC_OPERATION['SECOND_ASSISTANT_NAME'];//二助医师姓名
        $main_operation['HOCUS_WAY_ID'] = $INIT_MED_REC_OPERATION['HOCUS_WAY_ID'];//麻醉方式
        $main_operation['INCISION_GRADE_ID'] = $INIT_MED_REC_OPERATION['INCISION_GRADE_ID'];//切口愈合等级
        $main_operation['HOCUS_MAN_CODE'] = $INIT_MED_REC_OPERATION['HOCUS_MAN_CODE'];//麻醉医师编码
        $main_operation['HOCUS_MAN_NAME'] = $INIT_MED_REC_OPERATION['HOCUS_MAN_NAME'];//麻醉医师名称
        $main_operation['START_TIME'] = $INIT_MED_REC_OPERATION['START_TIME'];//手术开始时间
        $main_operation['END_TIME'] = $INIT_MED_REC_OPERATION['END_TIME'];//手术结束时间
        $main_operation['OPE_ORDER'] = $INIT_MED_REC_OPERATION['OPE_ORDER'];//手术顺序号
        $main_operation['OPE_LEVEL'] = $INIT_MED_REC_OPERATION['OPE_LEVEL'];//手术级别
        $main_operation['AREA_ID'] = $INIT_MED_REC_MAIN['AREA_ID'];//行政区划代码ID
        $main_operation['BATCH_ID'] = $INIT_MED_REC_MAIN['BATCH_ID'];//批次号
        MainOperation::query()->insert($main_operation);


    }





}
