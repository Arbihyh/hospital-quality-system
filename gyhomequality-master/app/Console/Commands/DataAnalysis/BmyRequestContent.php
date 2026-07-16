<?php

namespace App\Console\Commands\DataAnalysis;

use App\Model\HomeBmyRequestContent;
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
use App\Model\SecondaryOperation;
use App\Model\Staff;
use Illuminate\Console\Command;

class BmyRequestContent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:bmyContent';

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
        $this->info('编码请求数据解析 - 开始');

        $data = HomeBmyRequestContent::query()
            ->where('is_format','=',0)
            ->whereNotNull('content')
            ->where('content','!=','')
            ->get()->toArray();

        if (!empty($data)) {
            foreach ($data as $value) {
                $ZYH = $value['ZYH'];

                echo $ZYH.PHP_EOL;

                $content = json_decode($value['content'], true);

                // 主信息
                $this->patientData($ZYH,$content);

                // 其他信息
                $this->addBuChong($ZYH,$content);

                // 诊断信息
                $this->diagnosis($ZYH,$content);

                // 手术信息
                $this->operation($ZYH,$content);

                HomeBmyRequestContent::query()->where('id','=',$value['id'])->update(['is_format'=>1]);
            }
        }

        $this->info('编码请求数据解析 - 结束');
    }

    /**
     * 用户信息
     * @param $ZYH
     * @param $content
     * @return void
     */
    public function patientData($ZYH,$content)
    {
        $data = $content['data'];

        // 主信息
        $hospital_name = config('confAdmin.hospital_name');

        $otherData = $content['other'];
        $F_D = 0;
        $J = 0;
        if (!empty($otherData)) {
            $F_D = sprintf('%.2f', $otherData['XYF'] + $otherData['ZCHENGYF'] + $otherData['ZCAOYF']) ?? 0;
            $J = sprintf('%.2f',$otherData['JCYYCXYYCLF'] + $otherData['ZLYYCXYYCLF'] + $otherData['SSYYCXYYCLF']) ?? 0;
        }

        $patient_info = [
            'hospital_name' => $data['ZA03'] ?: $hospital_name, //机构名称
            'AAA28' => $data['AAA28'],
            'AAA01' => $data['AAA01'] ? desensitize($data['AAA01'], 1, 1, '*') : '',        //患者姓名
            'AAA02C' => $data['AAA02C'] ?: '',      //患者性别
            'AAA03' => $data['AAA03'] ?: '',        //出生日期
            'AAA04' => $data['AAA04'] ?: '',        //年龄
            'AAA05C' => $data['AAA05C'] ?: '',      //国籍
            'AAA40' => $data['AAA40'] ?: '',        //不足一周岁年龄
            'AAA42' => $data['AAA42'] ?: '',        //新生儿入院体重
            'AEN01' => $data['AEN01'] ?: '',        //新生儿出生体重
            'AAA06C' => $data['AAA06C'] ?: '',      //民族代码
            'AAA07' => $data['AAA07'] ? desensitize($data['AAA07'], 6, 8, '*')  : '',        //身份证号
            'AAA08C' => $data['AAA08C'] ?: '',      //婚姻状况
            'AEM01C' => $data['AEM01C'] ?: '',      //离院方式代码
            'AAB01' => $data['AAB01'] ?: '',        //入院时间
            'AAC01' => $data['AAC01'] ?: '',        //出院时间
            'AAC11N' => $data['AAC11N'] ?: '',      //出院医院内部科室名称
            'AAC04' => $data['AAC04'] ?? '',        //实际住院天数
            'ADA01' => $data['ADA01'] ?: '',        //总费用
            'ADA0101' => $data['ADA0101'] ?: '',    //自付费用
            'AAA29' => $data['AAA29'] ?? '',        //住院次数
            'ABG01C' => $data['ABG01C'] ?: '',      //损伤和中毒外部原因编码 no
            'ABG01N' => $data['ABG01N'] ?: '',      //损伤和中毒外部原因名称 no
            'AAB06C' => $data['AAB06C'] ?: '',      //入院途径代码
            'ABC01N' => $data['ABC01N'] ?: '',      //出院主要诊断名称
            'ORG_STATE' => $data['ORG_STATE'] ?: '',//质控状态
            'AAA26C' => $data['AAA26C'] ?: '',      //医疗付费方式代码
            'ATTEND_GRP_CODE' => $data['ATTEND_GRP_CODE'] ?: '',    //主诊组编码
            'ATTEND_GRP_NAME' => $data['ATTEND_GRP_NAME'] ?: '',    //主诊组名称
            'F_D'   => $F_D,
            'J'     => $J,
            'updated_at' => date('Y-m-d H:i:s', time()),
        ];
        $patientInfo = PatientInfo::query()->where('MED_REC_ID','=',$ZYH)->first();
        $patientInfoId = $patientInfo->id ?? '';
        if ($patientInfoId) {
            PatientInfo::query()->where('MED_REC_ID','=',$ZYH)->update($patient_info);
        } else {
            $patient_info['MED_REC_ID'] = $ZYH;
            $patientInfoId = PatientInfo::query()->insertGetId($patient_info);
        }
        $es_params = [];
        $es_params['body'][] = ['update' => ['_index' => 'patient_info', '_id' => $patientInfoId]];
        $es_params['body'][] = ['doc' => [
            "data_id" => $patientInfoId,
            "MED_REC_ID" => $ZYH,
            'hospital_name' => $data['ZA03'] ?: $hospital_name, //机构名称
            'AAA28' => $data['AAA28'],
            'AAA01' => $data['AAA01'],
            'AAA02C' => $data['AAA02C'] ?: '',
            'AAA03' => $data['AAA03'] ?: '',
            'AAA04' => $data['AAA04'] ?: '',
            'AAA05C' => $data['AAA05C'] ?: '',
            'AAA40' => $data['AAA40'] ?: '',
            'AAA42' => $data['AAA42'] ?: '',
            'AEN01' => $data['AEN01'] ?: '',
            'AAA06C' => $data['AAA06C'] ?: '',
            'AAA07' => $data['AAA07'],
            'AAA08C' => $data['AAA08C'],
            'AEM01C' => $data['AEM01C'],
            'AAB01' => $data['AAB01'],
            'AAC01' => $data['AAC01'],
            'AAC11N' => $data['AAC11N'],
            'AAC04' => $data['AAC04'],
            'ADA01' => $data['ADA01'],
            'ADA0101' => $data['ADA0101'],
            'AAA29' => $data['AAA29'],
            'ABG01C' => $data['ABG01C'],
            'ABG01N' => $data['ABG01N'],
            'AAB06C' => $data['AAB06C'],
            'ABC01N' => $data['ABC01N'],
            'ORG_STATE' => $data['ORG_STATE'],
            'AAA26C' => $data['AAA26C'],
            'ATTEND_GRP_CODE',
            'ATTEND_GRP_NAME',
            'F_D'   => $F_D,
            'J'     => $J,
            'created_at' => $patientInfo->created_at ?? date('Y-m-d H:i:s', time()),
            'updated_at' => date('Y-m-d H:i:s', time()),
            'home_bmy_score' => $patientInfo->home_bmy_score ?? '',
            'AAC11C'     => $patientInfo->AAC11C ?? '',
            'AEE03_CODE' => $patientInfo->AEE03_CODE ?? '',
            'AEE04_CODE' => $patientInfo->AEE04_CODE ?? '',
            'AEE08_CODE' => $patientInfo->AEE08_CODE ?? '',
            'ICD9_NAME'  => $patientInfo->ICD9_NAME ?? '',
            'ICD10_NAME' => $patientInfo->ICD10_NAME ?? '',
        ], 'doc_as_upsert' => true];
        app('es')->bulk($es_params);

        // 其他信息
        $patient_other_info = [
            'AAB07C' => $data['AAB07C'] ?: '',      //入院诊断id
            'AAB07N' => $data['AAB07N'] ?: '',      //入院诊断名称
            'AAB07' => $data['AAB07'] ?: '',        //入院时情况
            'AAB07D' => $data['AAB07D'] ?: '',      //入院后确诊日期
            'ABD04' => $data['ABD04'] ?: '',        //医院感染名称
            'ABD051' => $data['ABD051'] ?: '',      //门诊与出院诊断符合情况
            'ABD052' => $data['ABD052'] ?: '',      //术前与术后诊断符合情况
            'ABD053' => $data['ABD053'] ?: '',      //临床与病理诊断符合情况
            'ABD054' => $data['ABD054'] ?: '',      //放射与病理诊断符合情况
            'ZB09' => $data['ZB09'] ?: '',          //手机
            'ZB08' => $data['ZB08'] ?: '',
            'ZB07' => $data['ZB07'] ?: '',
            'ZB06' => $data['ZB06'] ?: '',
            'ZB05' => $data['ZB05'] ?: '',
            'ZB04' => $data['ZB04'] ?: '',
            'ZB03' => $data['ZB03'] ?: '',
            'ZB02' => $data['ZB02'] ?: '',
            'ZB01C' => $data['ZB01C'] ?: '',
            'ZA04' => $data['ZA04'] ?: '',
            'MED_REC_ID' => $data['MED_REC_ID'] ?: '',  //病案⾸⻚ID
            'UNT_ID' => $data['UNT_ID'] ?: '',      //组织机构代码ID
            'ZA03' => $data['ZA03'] ?: '',          //机构名称
            'AFA01' => $data['AFA01'] ?: '',        //抢救次数
            'AFA02' => $data['AFA02'] ?: '',        //成本次数
            'AFA03' => $data['AFA03'] ?: '',
            'AFA04' => $data['AFA04'] ?: '',
            'AFA05' => $data['AFA05'] ?: '',
            'AFA06' => $data['AFA06'] ?: '',
            'AFA07' => $data['AFA07'] ?: '',
            'AFA08' => $data['AFA08'] ?: '',
            'AFA09' => $data['AFA09'] ?: '',
            'AFA10' => $data['AFA10'] ?: '',
            'AFA11' => $data['AFA11'] ?: '',
            'AFA12' => $data['AFA12'] ?: '',
            'ZB10' => $data['ZB10'] ?: '',          //填报版本
            'ZB11' => $data['ZB11'] ?: '',          //填报说明
            'IS_VALID' => $data['IS_VALID'] ?: '',  //有效标识
            'SYN_DATE' => $data['SYN_DATE'] ?: '',  //获取时间
            'QU_STATE' => $data['QU_STATE'] ?: '',  //是否采集
            'DATA_STATE' => $data['DATA_STATE'] ?: '',  //病案采集状态
            'BALANCEID' => $data['BALANCEID'] ?: '',    //病案流水号
            'AKC021' => $data['AKC021'] ?: '',      //⼈群类型
            'updated_at' => date('Y-m-d H:i:s', time()),
        ];
        PatientOtherInfo::query()->updateOrInsert(['AAA28'=>$ZYH],$patient_other_info);

        $patient_medical_info = [
            'ABA01C' => $data['ABA01C'] ?: '',      //门(急)诊诊断编码
            'ABA01N' => $data['ABA01N'] ?: '',      //⻔（急）诊诊断名称
            'ABC03C' => $data['ABC03C'] ?: '',      //入院病情代码
            'ABF01C' => $data['ABF01C'] ?: '',      //病理诊断编码
            'ABF01N' => $data['ABF01N'] ?: '',      //病理诊断名称
            'ABF04' => $data['ABF04'] ?: '',        //病理号
            'ABF02C' => $data['ABF02C'] ?: '',      //最高诊断依据代码ID
            'ABF03C' => $data['ABF03C'] ?: '',      //分化程度编码ID
            'ABH01C' => $data['ABH01C'] ?: '',      //肿瘤分期是否不详
            'ABH0201C' => $data['ABH0201C'] ?: '',  //肿瘤分期 TID
            'ABH0202C' => $data['ABH0202C'] ?: '',  //肿瘤分期 NID
            'ABH0203C' => $data['ABH0203C'] ?: '',  //肿瘤分期 MID
            'ABH03C' => $data['ABH03C'] ?: '',      //0～Ⅳ肿瘤分期ID
            'AEB02C' => $data['AEB02C'] ?: '',      //有无药物过敏
            'AEB01' => $data['AEB01'] ?: '',        //过敏药物
            'AED01C' => $data['AED01C'] ?: '',      //病案质量代码ID
            'AEG01C' => $data['AEG01C'] ?: '',      //血型代码ID
            'AEG02C' => $data['AEG02C'] ?: '',      //Rh 代码ID
            'AEG04' => $data['AEG04'] ?: '',        //红细胞(单位)
            'AEG05' => $data['AEG05'] ?: '',        //血小板(袋)
            'AEG06' => $data['AEG06'] ?: '',        //血浆(ml)
            'AEG07' => $data['AEG07'] ?: '',        //全血(ml)
            'AEG08' => $data['AEG08'] ?: '',        //其它(ml)
            'AEJ01' => $data['AEJ01'] ?: '',        //颅脑损伤患者入院前昏迷时间（天）
            'AEJ02' => $data['AEJ02'] ?: '',        //颅脑损伤患者入院前昏迷时间（天）
            'AEJ03' => $data['AEJ03'] ?: '',        //颅脑损伤患者入院前昏迷时间（天）
            'AEJ04' => $data['AEJ04'] ?: '',        //颅脑损伤患者入院后昏迷时间（天）
            'AEJ05' => $data['AEJ05'] ?: '',        //颅脑损伤患者入院后昏迷时间（天）
            'AEJ06' => $data['AEJ06'] ?: '',        //颅脑损伤患者入院后昏迷时间（天）
            'AEL01' => $data['AEL01'] ?: '',        //呼吸机使用时间（天）
            'AEN02C' => $data['AEN02C'] ?: '',      //新生儿出生缺陷诊断
            'AEN02N' => $data['AEN02N'] ?: '',      //新生儿出生缺陷诊断名称
            'AEI09' => $data['AEI09'] ?: '',        //日常生活能力评定量得分
            'AEI10' => $data['AEI10'] ?: '',        //日常生活能力评定量得分
            'AEI08' => $data['AEI08'] ?: '',        //备注
            'updated_at' => date('Y-m-d H:i:s', time()),
        ];
        PatientMedicalInfo::query()->updateOrInsert(['AAA28'=>$ZYH],$patient_medical_info);

        //患者住院信息
        $patient_hospital_info = [
            'AAA30' => $data['AAA30'] ?: '',    //住院号
            'ABC01C' => $data['ABC01C'] ?: '',  //出院时主要诊断编码
            'AAA27' => $data['AAA27'] ?: '-',   //医疗保险手册(卡)号
            'AAC001' => $data['AAC001'] ?: '',  //医保个人编号
            'AAB01' => $data['AAB01'] ?: '',    //入院时间（时）
            'AAB02C' => $data['AAB02C'] ?: '',  //入院科别代码
            'AAB03' => $data['AAB03'] ?: '',    //入院病房
            'AAB11C' => $data['AAB11C'] ?: '',  //入院医院内部科室代码ID
            'AAB11N' => $data['AAB11N'] ?: '',  //入院医院内部科室名称
            'AAC02C' => $data['AAC02C'] ?: '',  //出院科别代码ID
            'AAC03' => $data['AAC03'] ?: '',    //出院病房
            'AAC11C' => $data['AAC11C'] ?: '',  //出院医院内部科室代码ID
            'AAD01C' => $data['AAD01C'] ?: '',  //转经科别代码ID
            'AEM02' =>  $data['AEM02'] ?: '',   //医嘱转院、转社区、卫生院机编码ID
            'AEM03C' => $data['AEM03C'] ?: '',  //是否有出院31日内再住院计划
            'AEM04' => $data['AEM04'] ?: '',    //31日内再住院目的
            'AEI01C' => $data['AEI01C'] ?: '',  //是否尸检代码ID
            'updated_at' => date('Y-m-d H:i:s', time()),
        ];
        PatientHospitalInfo::query()->updateOrInsert(['AAA28'=>$ZYH],$patient_hospital_info);

        //患者医生信息
        $patient_doctor_info = [
            'AED02' => $data['AED02'] ?: '',    //质控医师姓名
            'AED03' => $data['AED03'] ?: '',    //质控护士姓名
            'AED04' => $data['AED04'] ?: '',    //病案质量检查日期
            'AEE01' => $data['AEE01'] ?: '',    //科主任姓名
            'AEE02' => $data['AEE02'] ?: '',    //主(副主)任医师姓名
            'AEE03' => $data['AEE03'] ?: '',    //主治医师姓
            'AEE11' => $data['AEE11'] ?: '',    //主诊医师执业证书编码
            'AEE09' => $data['AEE09'] ?: '',    //主诊医师姓名
            'AEE04' => $data['AEE04'] ?: '',    //住院医师姓名
            'AEE05' => $data['AEE05'] ?: '',    //进修医师姓名
            'AEE07' => $data['AEE07'] ?: '',    //实习医师姓名
            'AEE08' => $data['AEE08'] ?: '',    //编码员姓名
            'AEE10' => $data['AEE10'] ?: '',    //责任护士姓名
            'AEE01_CODE' => $data['AEE01_CODE'] ?: '',  //科主任编码
            'AEE02_CODE' => $data['AEE02_CODE'] ?: '',  //主（副主）任医师工号
            'AEE03_CODE' => $data['AEE03_CODE'] ?: '',  //主治医师工号
            'AEE04_CODE' => $data['AEE04_CODE'] ?: '',  //住院医师工号
            'updated_at' => date('Y-m-d H:i:s', time()),
        ];
        PatientDoctorInfo::query()->updateOrInsert(['AAA28'=>$ZYH],$patient_doctor_info);

        //患者地址相关信息
        $patient_address_info = [
            'AAA09' => $data['AAA09'] ?: '',    //出生地省
            'AAA10' => $data['AAA10'] ?: '',    //出生地市
            'AAA11' => $data['AAA11'] ?: '',    //出生地县
            'AAA43' => $data['AAA43'] ?: '',    //籍贯省
            'AAA44' => $data['AAA44'] ?: '',    //籍贯市
            'AAA45' => $data['AAA45'] ?: '',    //户籍省
            'AAA46' => $data['AAA46'] ?: '',    //户籍市
            'AAA47' => $data['AAA47'] ?: '',    //户籍县
            'AAA12' => $data['AAA12'] ?: '',    //户籍详细地址
            'AAA13C' => $data['AAA13C'] ?: '',  //户籍地址区县编码
            'AAA33C' => $data['AAA33C'] ?: '',  //户籍街道乡镇代码ID
            'AAA14C' => $data['AAA14C'] ?: '',  //户籍地址邮政编码
            'AAA15' => $data['AAA15'] ?: '',    //现住址详细地址
            'AAA48' => $data['AAA48'] ?: '',    //现住址省
            'AAA49' => $data['AAA49'] ?: '',    //现住址市
            'AAA50' => $data['AAA50'] ?: '',    //现住址县
            'AAA16C' => $data['AAA16C'] ?: '',  //现住址区县编码
            'AAA36C' => $data['AAA36C'] ?: '',  //现住址街道乡镇代码
            'AAA51' => $data['AAA51'] ? desensitize($data['AAA51'], 3, 4, '*') : '',    //现住址电话
            'AAA17C' => $data['AAA17C'] ?: '',  //现住址邮政编码
            'updated_at' => date('Y-m-d H:i:s', time()),
        ];
        PatientAddressInfo::query()->updateOrInsert(['AAA28'=>$ZYH],$patient_address_info);

        //患者工作信息
        $patient_work_info = [
            'AAA18C' => $data['AAA18C'] ?: '',      //职业代码ID
            'AAA19' => $data['AAA19'] ?: '',        //工作单位及地址
            'AAA20' => $data['AAA20'] ? desensitize($data['AAA20'], 3, 4, '*') : '',        //工作单位电话
            'AAA21C' => $data['AAA21C'] ?: '',      //工作单位邮政编码
            'updated_at' => date('Y-m-d H:i:s', time()),
        ];
        PatientWorkInfo::query()->updateOrInsert(['AAA28'=>$ZYH],$patient_work_info);

        //患者联系人信息
        $patient_contacts_info = [
            'AAA22' => $data['AAA22'] ? desensitize($data['AAA22'], 1, 1, '*') : '',    //联系人姓名
            'AAA23C' => $data['AAA23C'] ?: '',  //联系人关系代码ID
            'AAA24' => $data['AAA24'] ?: '',    //联系人地址
            'AAA25' => $data['AAA25'] ? desensitize($data['AAA25'], 3, 4, '*') : '',    //联系人电话
            'updated_at' => date('Y-m-d H:i:s', time()),
        ];
        PatientContactsInfo::query()->updateOrInsert(['AAA28'=>$ZYH],$patient_contacts_info);
    }

    /**
     * 补充信息
     * @param $ZYH
     * @param $content
     * @return true
     */
    public function addBuChong($ZYH,$content)
    {
        $data = $content['other'];

        // 费用信息
        $feeData = [
            'ADA0101' => $data['ZFFY'] ?? 0,
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
            'updated_at' => date('Y-m-d H:i:s', time()),
        ];
        PatientCostInfo::query()->updateOrInsert(['AAA28'=>$ZYH], $feeData);

        // 补充信息
        $addData = [
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
            'updated_at' => date('Y-m-d H:i:s', time()),
        ];
        PatientAdd::query()->updateOrInsert(['AAA28'=>$ZYH], $addData);

        return true;
    }

    /**
     * 诊断信息
     * @param $ZYH
     * @param $content
     * @return bool
     */
    public function diagnosis($ZYH,$content)
    {
        $diagnosis = $content['diagnosis'];

        $mainDiagnosis = [];
        $otherDiagnosis = [];
        if (!empty($diagnosis)) {
            $index = 1;
            foreach ($diagnosis as $value) {
                if ($value['ZZPB'] == 1) {
                    $mainDiagnosis = [
                        'AAA28' => $ZYH,
                        'ICD10_ID1' => $value['ZDBM'] ?? '',
                        'ICD10_NAME' => $value['ZDMC'] ?? '',
                        'DIA_ORDER' => $index,
                        'LBMC' => $value['LBMC'] ?? '',
                        'RYQK' => $value['RYQK'] ?? '',
                    ];
                    $index++;
                } else {
                    $otherDiagnosis[] = [
                        'AAA28' => $ZYH,
                        'ICD10_ID1' => $value['ZDBM'] ?? '',
                        'ICD10_NAME' => $value['ZDMC'] ?? '',
                        'DIA_ORDER' => $index,
                        'LBMC' => $value['LBMC'] ?? '',
                        'RYQK' => $value['RYQK'] ?? '',
                    ];
                    $index++;
                }
            }
        }

        // 主要诊断
        if (!empty($mainDiagnosis)) {
            $es_params = [];
            $mdId = MainDiagnosis::query()->where('AAA28','=',$ZYH)->value('id');
            if ($mdId) {
                MainDiagnosis::query()->where('id','=',$mdId)->update($mainDiagnosis);
            } else {
                $mdId = MainDiagnosis::query()->insertGetId($mainDiagnosis);
            }

            $es_params['body'][] = ['update' => ['_index' => 'main_diagnosis', '_id' => $mdId]];
            $es_params['body'][] = ['doc' => [
                "data_id" => $mdId,
                "ZYH" => $ZYH,
                "ICD10_ID1" => $mainDiagnosis['ICD10_ID1'],
                "ICD10_NAME" => $mainDiagnosis['ICD10_NAME'],
                "DIA_ORDER" => $mainDiagnosis['DIA_ORDER'],
                "created_at" => date('Y-m-d H:i:s', time()),
                "LBMC" => $mainDiagnosis['LBMC'],
                "RYQK" => $mainDiagnosis['RYQK'],
            ], 'doc_as_upsert' => true];
            app('es')->bulk($es_params);
        }

        // 其他诊断
        if (!empty($otherDiagnosis)) {
            $odIdList = OtherDiagnosis::query()->where('AAA28','=',$ZYH)->pluck('id')->toArray();
            $es_params = [];
            if (!empty($odIdList)) {
                OtherDiagnosis::query()->where('AAA28','=',$ZYH)->delete();
                foreach ($odIdList as $value) {
                    $es_params['body'][] = ['delete' => ['_index' => 'other_diagnosis_2023', '_id' => $value]];
                }
                app('es')->bulk($es_params);
            }

            $es_params = [];
            foreach ($otherDiagnosis as $value) {
                $mdId = OtherDiagnosis::query()->insertGetId($value);

                $es_params['body'][] = ['update' => ['_index' => 'other_diagnosis_2023', '_id' => $mdId]];
                $es_params['body'][] = ['doc' => [
                    "data_id" => $mdId,
                    "ZYH" => $ZYH,
                    "ICD10_ID1" => $value['ICD10_ID1'],
                    "ICD10_NAME" => $value['ICD10_NAME'],
                    "DIA_ORDER" => $value['DIA_ORDER'],
                    "created_at" => date('Y-m-d H:i:s', time()),
                    "LBMC" => $value['LBMC'],
                    "RYQK" => $value['RYQK'],
                ], 'doc_as_upsert' => true];
            }

            app('es')->bulk($es_params);
        }

        return true;
    }

    /**
     * 手术信息
     * @param $ZYH
     * @param $content
     * @return bool
     */
    public function operation($ZYH,$content)
    {
        $operation = $content['operation'];

        $mainOperation = [];
        $secondaryOperation = [];
        if (!empty($operation)) {
            $SSPB_ARR = MainOperation::SSPB;
            foreach ($operation as $value) {
                if ($value['SFZYSS'] == 1) {
                    $mainOperation = [
                        'AAA28' => $ZYH,
                        'ICD9_ID1' => $value['SSCZBM'] ?? '',
                        'ICD9_NAME' => $value['SSCZMC'] ?? '',
                        'OPE_DATE' => $value['SSCZRQ'] ?? '',
                        'OPE_MAN_NAME' => $value['SZXM'] ?? '',
                        'OPE_MAN_CODE' => $value['SZBM'] ?? '',
                        'FRIST_ASSISTANT_CODE' => $value['YZYSBM'] ?? '',
                        'FRIST_ASSISTANT_NAME' => $value['YZXM'] ?? '',
                        'SECOND_ASSISTANT_CODE' => $value['EZYSBM'] ?? '',
                        'SECOND_ASSISTANT_NAME' => $value['EZXM'] ?? '',
                        'HOCUS_WAY_ID' => $value['MZFS'] ?? '',
                        'INCISION_GRADE_ID' => $value['QKDJ'] ?? '',
                        'HEAL_ID' => $value['YHDJ'] ?? '',
                        'HOCUS_MAN_CODE' => $value['MZYSBM'] ?? '',
                        'HOCUS_MAN_NAME' => $value['MZYSXM'] ?? '',
                        'START_TIME' => $value['SSKSSJ'] ?? '',
                        'END_TIME' => $value['SSJSSJ'] ?? '',
                        'OPE_ORDER' => $value['SSSX'] ?? '',
                        'OPE_LEVEL' => $value['SSJB'] ?? '',
                        'RJSS' => $value['SFWRJSS'] ?? '',
                        'SSPB' => !empty($SSPB_ARR[$value['SSPB']]) ? $SSPB_ARR[$value['SSPB']] : $value['SSPB'],
                    ];
                } else {
                    $secondaryOperation[] = [
                        'AAA28' => $ZYH,
                        'ICD9_ID1' => $value['SSCZBM'] ?? '',
                        'ICD9_NAME' => $value['SSCZMC'] ?? '',
                        'OPE_DATE' => $value['SSCZRQ'] ?? '',
                        'OPE_MAN_NAME' => $value['SZXM'] ?? '',
                        'OPE_MAN_CODE' => $value['SZBM'] ?? '',
                        'FRIST_ASSISTANT_CODE' => $value['YZYSBM'] ?? '',
                        'FRIST_ASSISTANT_NAME' => $value['YZXM'] ?? '',
                        'SECOND_ASSISTANT_CODE' => $value['EZYSBM'] ?? '',
                        'SECOND_ASSISTANT_NAME' => $value['EZXM'] ?? '',
                        'HOCUS_WAY_ID' => $value['MZFS'] ?? '',
                        'INCISION_GRADE_ID' => $value['QKDJ'] ?? '',
                        'HEAL_ID' => $value['YHDJ'] ?? '',
                        'HOCUS_MAN_CODE' => $value['MZYSBM'] ?? '',
                        'HOCUS_MAN_NAME' => $value['MZYSXM'] ?? '',
                        'START_TIME' => $value['SSKSSJ'] ?? '',
                        'END_TIME' => $value['SSJSSJ'] ?? '',
                        'OPE_ORDER' => $value['SSSX'] ?? '',
                        'OPE_LEVEL' => $value['SSJB'] ?? '',
                        'RJSS' => $value['SFWRJSS'] ?? '',
                        'SSPB' => !empty($SSPB_ARR[$value['SSPB']]) ? $SSPB_ARR[$value['SSPB']] : $value['SSPB'],
                    ];
                }
            }
        }

        // 主要手术
        if (!empty($mainOperation)) {
            $es_params = [];
            $moId = MainOperation::query()->where('AAA28','=',$ZYH)->value('id');
            if ($moId) {
                MainOperation::query()->where('id','=',$moId)->update($mainOperation);
            } else {
                $moId = MainOperation::query()->insertGetId($mainOperation);
            }

            $es_params['body'][] = ['update' => ['_index' => 'main_operation', '_id' => $moId]];
            $es_params['body'][] = ['doc' => [
                "data_id" => $moId,
                "AAA28" => $ZYH,
                "ICD9_ID1" => $mainOperation['ICD9_ID1'],
                "ICD9_NAME" => $mainOperation['ICD9_NAME'],
                "OPE_DATE" => $mainOperation['OPE_DATE'],
                "OPE_MAN_NAME" => $mainOperation['OPE_MAN_NAME'],
                "OPE_MAN_CODE" => $mainOperation['OPE_MAN_CODE'],
                "FRIST_ASSISTANT_CODE" => $mainOperation['FRIST_ASSISTANT_CODE'],
                "FRIST_ASSISTANT_NAME" => $mainOperation['FRIST_ASSISTANT_NAME'],
                "SECOND_ASSISTANT_CODE" => $mainOperation['SECOND_ASSISTANT_CODE'],
                "SECOND_ASSISTANT_NAME" => $mainOperation['SECOND_ASSISTANT_NAME'],
                "HOCUS_WAY_ID" => $mainOperation['HOCUS_WAY_ID'],
                "INCISION_GRADE_ID" => $mainOperation['INCISION_GRADE_ID'],
                "HOCUS_MAN_CODE" => $mainOperation['HOCUS_MAN_CODE'],
                "HOCUS_MAN_NAME" => $mainOperation['HOCUS_MAN_NAME'],
                "START_TIME" => $mainOperation['START_TIME'],
                "END_TIME" => $mainOperation['END_TIME'],
                "OPE_ORDER" => $mainOperation['OPE_ORDER'],
                "OPE_LEVEL" => $mainOperation['OPE_LEVEL'],
                "RJSS" => $mainOperation['RJSS'],
//                "created_at" => $mainOperation['created_at'],
//                "updated_at" => $mainOperation['updated_at'],
//                "OPE_TYPE" => $mainOperation['OPE_TYPE'],
                "SSPB" => $mainOperation['SSPB'],
                "HEAL_ID" => $mainOperation['HEAL_ID'],
            ], 'doc_as_upsert' => true];
            app('es')->bulk($es_params);
        }

        // 其他手术
        if (!empty($secondaryOperation)) {
            $soIdList = SecondaryOperation::query()->where('AAA28','=',$ZYH)->pluck('id')->toArray();
            if (!empty($soIdList)) {
                $es_params = [];
                SecondaryOperation::query()->where('AAA28','=',$ZYH)->delete();
                foreach ($soIdList as $value) {
                    $es_params['body'][] = ['delete' => ['_index' => 'secondary_operation_2023', '_id' => $value]];
                }
                app('es')->bulk($es_params);
            }

            $es_params = [];
            foreach ($secondaryOperation as $value) {
                $soId = SecondaryOperation::query()->insertGetId($value);
                $es_params['body'][] = ['update' => ['_index' => 'secondary_operation_2023', '_id' => $soId]];
                $es_params['body'][] = ['doc' => [
                    "data_id" => $soId,
                    "AAA28" => $ZYH,
                    "ICD9_ID1" => $value['ICD9_ID1'],
                    "ICD9_NAME" => $value['ICD9_NAME'],
                    "OPE_DATE" => $value['OPE_DATE'],
                    "OPE_MAN_NAME" => $value['OPE_MAN_NAME'],
                    "OPE_MAN_CODE" => $value['OPE_MAN_CODE'],
                    "FRIST_ASSISTANT_CODE" => $value['FRIST_ASSISTANT_CODE'],
                    "FRIST_ASSISTANT_NAME" => $value['FRIST_ASSISTANT_NAME'],
                    "SECOND_ASSISTANT_CODE" => $value['SECOND_ASSISTANT_CODE'],
                    "SECOND_ASSISTANT_NAME" => $value['SECOND_ASSISTANT_NAME'],
                    "HOCUS_WAY_ID" => $value['HOCUS_WAY_ID'],
                    "INCISION_GRADE_ID" => $value['INCISION_GRADE_ID'],
                    "HOCUS_MAN_CODE" => $value['HOCUS_MAN_CODE'],
                    "HOCUS_MAN_NAME" => $value['HOCUS_MAN_NAME'],
                    "START_TIME" => $value['START_TIME'],
                    "END_TIME" => $value['END_TIME'],
                    "OPE_ORDER" => $value['OPE_ORDER'],
                    "OPE_LEVEL" => $value['OPE_LEVEL'],
                    "RJSS" => $value['RJSS'],
//                    "created_at" => $value['created_at'],
//                    "updated_at" => $value['updated_at'],
//                    "OPE_TYPE" => $value['OPE_TYPE'],
                    "SSPB" => $value['SSPB'],
                    "HEAL_ID" => $value['HEAL_ID'],
                ], 'doc_as_upsert' => true];
            }
            app('es')->bulk($es_params);
        }

        return true;
    }


}
