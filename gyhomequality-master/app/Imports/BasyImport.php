<?php

namespace App\Imports;

use App\Model\BASY_BLZD;
use App\Model\BASY_FY;
use App\Model\BASY_JBXX;
use App\Model\BASY_SS;
use App\Model\BASY_YS;
use App\Model\BASY_ZD;
use App\Model\BASY_ZZJH;
use App\Model\Department;
use App\Model\Icu;
use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\OtherDiagnosis;
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
use App\Model\Setting;
use App\Services\ElasticsearchService;
use App\Services\ToolsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Pheanstalk\Pheanstalk;
use function GuzzleHttp\json_encode;

class BasyImport implements ToCollection
{
    public static $sumData = 0;
    public static $returnData = [];

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {

    }

    /**
     * @param Collection $rows
     * @return void
     */
    public function collection(Collection $rows)
    {
        //如果需要去除表头
//        unset($rows[0]);

        //$rows 是数组格式
        $this->createData($rows);
    }

    public function createData($rows)
    {
        $fieldInfo = $rows[0];
        unset($rows[0]);
        $field = [];
        foreach ($fieldInfo as $key => $row){
            $row = str_replace('X','x',$row);
            $field[$row] = $key;
        }

        if (!empty($rows)) {
            $beanstalkd = Pheanstalk::create(env("BEANSTALKD") ?? 'beanstalkd');
            $patientInfoService = new ElasticsearchService('patient_info');

            // 查询通用科室
            $delList = Department::query()->where('hospital_name','=','通用')->pluck('dep_name','dep_id')->toArray();

            $setName = 'auto_zyh';
            $ZYH = Setting::query()->where('name','=',$setName)->value('content');
            $ZYH = !empty($ZYH) ? $ZYH : 10000000000;

            $patientInfoId = '';
            foreach ($rows as $key => $val) {
                if (empty($val[0]) && empty($val[1]) && empty($val[2])) {
                    continue;
                }

                self::$sumData++;
                $A02_KEY = $field['A02'] ?? '';
                $A48_KEY = $field['A48'] ?? '';
                $B15_KEY = $field['B15'] ?? '';
                $A11_KEY = $field['A11'] ?? '';
                if (empty($val[$A02_KEY]) || empty($val[$A48_KEY]) || empty($val[$B15_KEY])) {
                    $B12 = !empty($field['B12']) ? $val[$field['B12']] : '';
                    $B15 = !empty($field['B15']) ? $val[$field['B15']] : '';
                    self::$returnData[] = [
                        'hospital_name' => $val[$A02_KEY] ?? '',
                        'AAA01' => $val[$A11_KEY] ?? '',
                        'AAA28' => $val[$A48_KEY] ?? '',
                        'AAB01' => $this->getDateTime($B12),
                        'AAC01' => $this->getDateTime($B15),
                        'error_msg' => '数据不全'
                    ];
                    continue;
                }

                $B15 = $this->getDateTime($val[$B15_KEY]);
                $must = [
                    ['match_phrase' => ['hospital_name' => $val[$A02_KEY]]],
                    ["term" => ['AAA28' => $val[$A48_KEY]]],
                    ["term" => ['AAC01' => $B15]]
                ];
                $params = $patientInfoService->clearMust()->queryByMustBatch($must)->getParams();
                $restful = app('es')->search($params);
                $patientInfoData = $patientInfoService->getDataByEs($restful);
                $patientInfo = !empty($patientInfoData[0]) ? $patientInfoData[0][0] : [];
                if ($patientInfo) {
                    // 有重复数据
                    $B12 = $val[$field['B12']] ?? '';
                    $B15 = $val[$field['B15']] ?? '';
                    self::$returnData[] = [
                        'hospital_name' => $val[$A02_KEY] ?? '',
                        'AAA01' => $val[$A11_KEY] ?? '',
                        'AAA28' => $val[$A48_KEY] ?? '',
                        'AAB01' => $this->getDateTime($B12),
                        'AAC01' => $this->getDateTime($B15),
                        'error_msg' => '数据重复'
                    ];
                    continue;
                }

                $ZYH++;

                // 主信息
                $patientInfoId = $this->addPatientInfo($val,$ZYH,$field,$delList);

                // 病案首页（诊断信息）
                $res = $this->addPatientMedicalInfo($val,$ZYH,$field);

                // 病案首页（费用信息）
                $res = $this->addPatientCostInfo($val,$ZYH,$field);

                // 病案首页（地址信息）
                $res = $this->addPatientAddressinfo($val,$ZYH,$field);

                // 病案首页（联系人信息）
                $res = $this->addPatientContactsInfo($val,$ZYH,$field);

                // 病案首页（医生信息）
                $res = $this->addPatientDoctorInfo($val,$ZYH,$field);

                // 病案首页（其它信息）
                $res = $this->addPatientOtherInfo($val,$ZYH,$field);

                // 病案首页（病案首页工作信息）
                $res = $this->addPatientWorkInfo($val,$ZYH,$field);

                // 病案首页（住院信息）
                $res = $this->addPatientHospitalInfo($val,$ZYH,$field,$delList);

                // 病案首页（主要诊断）
                $res = $this->addMainDiagnosis($val,$ZYH,$field);

                // 病案首页（其它诊断）
                $res = $this->addOtherDiagnosis($val,$ZYH,$field);

                // 病案首页（主要手术）
                $res = $this->addMainOperation($val,$ZYH,$field);

                // 病案首页（其它手术）
                $res = $this->addSecondaryOperation($val,$ZYH,$field);

                // 病案首页（重症监护）
                $res = $this->addICU($val,$ZYH,$field);

                // 质控
                $beanstalkd->useTube('validate')->put(json_encode(['AAA28' => $ZYH]));
            }

            if (!empty($patientInfoId)) {
                Setting::query()->updateOrInsert(['name'=>'home_quality_id'],['content'=>$patientInfoId]);
            }
            Setting::query()->updateOrInsert(['name'=>$setName], ['content'=>$ZYH]);
        }
    }

    /**
     * 主信息 - patient_info 表
     * @param $val
     * @param $ZYH
     * @param $field
     * @param $delList
     * @return bool
     */
    public function addPatientInfo($val,$ZYH,$field,$delList)
    {
        $AAC11N = !empty($field['B16C']) ? $val[$field['B16C']] : '';
        if (!empty($AAC11N) && is_numeric($AAC11N)) {
            $AAC11N = $delList[$AAC11N] ?? $AAC11N;
        }

        // 国籍
        $AAA05C = !empty($field['A15C']) ? $val[$field['A15C']] : '';
        if (!empty($AAA05C)) {
            $AAA05C = $AAA05C=='中国' ? 1 : 2;
        }

        $insertData = [];
        $insertData['hospital_name'] = !empty($field['A02']) ? $val[$field['A02']] : '';    // 医院名称
        $insertData['MED_REC_ID'] = $ZYH;
        $insertData['AAA28'] = !empty($field['A48']) ? $val[$field['A48']] : '';    // 病案号
        $insertData['AAA01'] = !empty($field['A11']) ? desensitize($val[$field['A11']], 1, 1, '*') : '';    // 患者姓名
        $insertData['AAA02C'] = !empty($field['A12C']) ? $val[$field['A12C']] : ''; // 患者性别
        $insertData['AAA03'] = !empty($field['A13']) ? $this->getDateTime($val[$field['A13']]) : '';    // 出生日期
        $insertData['AAA04'] = !empty($field['A14']) ? $val[$field['A14']] : '';    // 年龄
        $insertData['AAA05C'] = $AAA05C; // 国籍
        $insertData['AAA40'] = !empty($field['A16']) ? str_replace('-','',$val[$field['A16']]) : '';    // 不足一岁的天龄
        $insertData['AAA42'] = !empty($field['A17']) ? str_replace('-','',$val[$field['A17']]) : '';    // 新生儿入院体重(克)
        $insertData['AEN01'] = !empty($field['A18x01']) ? $val[$field['A18x01']] : '';  // 新生儿出生体重(克)
        $insertData['AAA06C'] = !empty($field['A19C']) ? $val[$field['A19C']] : ''; // 民族代码
        $insertData['AAA07'] = !empty($field['A20']) ? desensitize($val[$field['A20']], 6, 8, '*') : '';    // 身份证号
        $insertData['AAA08C'] = !empty($field['A21C']) ? $val[$field['A21C']] : ''; // 婚姻
        $insertData['AEM01C'] = !empty($field['B34C']) ? $val[$field['B34C']] : ''; // 离院方式
        $insertData['AAB01'] = !empty($field['B12']) ? $this->getDateTime($val[$field['B12']]) : '';    // 入院时间
        $insertData['AAC01'] = !empty($field['B15']) ? $this->getDateTime($val[$field['B15']]) : '';    // 出院时间
        $insertData['AAC11N'] = $AAC11N;                                            // 出院科别
        $insertData['AAC04'] = !empty($field['B20']) ? $val[$field['B20']] : '';    // 实际住院天数
        $insertData['ADA01'] = !empty($field['D01']) ? $val[$field['D01']] : '';    // 总费用
        $insertData['ADA0101'] = !empty($field['D09']) ? $val[$field['D09']] : '';  // 住院总费用其中自付金额
        $insertData['AAA29'] = !empty($field['A49']) ? $val[$field['A49']] : '';    // 住院次数
        $insertData['AAB06C'] = !empty($field['B11C']) ? $val[$field['B11C']] : ''; // 入院途径
        $insertData['ABC01N'] = !empty($field['C04N']) ? trim($val[$field['C04N']]) : ''; // 出院主要诊断名称
        $insertData['ICD9_NAME'] = !empty($field['C15x01N']) ? trim($val[$field['C15x01N']]) : '';  // 主要手术名称

//        $insertData['ORG_STATE'] = !empty($field['']) ? $val[$field['']] : '';
        $insertData['AAA26C'] = !empty($field['A46C']) ? $val[$field['A46C']] : ''; // 医疗付费方式
//        $insertData['ATTEND_GRP_CODE'] = !empty($field['']) ? $val[$field['']] : '';// 主诊组编码
//        $insertData['ATTEND_GRP_NAME'] = !empty($field['']) ? $val[$field['']] : '';// 主诊组名称

        $F_D = 0;
        $J = 0;
        if (!empty($field)) {
            $D23 = !empty($field['D23']) ? $field['D23'] : 0;
            $D24 = !empty($field['D24']) ? $field['D24'] : 0;
            $D25 = !empty($field['D25']) ? $field['D25'] : 0;
            $F_D = sprintf('%.2f', $D23 + $D24 + $D25);
            $D31 = !empty($field['D31']) ? $field['D31'] : 0;
            $D32 = !empty($field['D32']) ? $field['D32'] : 0;
            $D33 = !empty($field['D33']) ? $field['D33'] : 0;
            $J = sprintf('%.2f',$D31 + $D32 + $D33);
        }
        $insertData['F_D'] = $F_D;  // 药品费用
        $insertData['J'] = $J;      // 材料费用
        $insertData['ICD10_NAME'] = !empty($field['C04N']) ? trim($val[$field['C04N']]) : '';

        foreach ($insertData as &$v) {
            $v = $v!==null ? $v : '';
        }

        $patientInfoId = PatientInfo::query()->insertGetId($insertData);

        try {
            $es_params['body'][] = ['update' => ['_index' => 'patient_info', '_id' => $patientInfoId]];
            $es_params['body'][] = ['doc' => [
                "data_id" => $patientInfoId,
                "hospital_name" => $insertData['hospital_name'] ?? '',
                "AAA28" => $insertData['AAA28'] ?? '',
                "MED_REC_ID" => $insertData['MED_REC_ID'] ?? '',
                "AAA01" => $insertData['AAA01'] ?? '',
                "AAA02C" => $insertData['AAA02C'] ?? '',
                "AAA03" => $insertData['AAA03'] ?? '',
                "AAA04" => $insertData['AAA04'] ?? '',
                "AAA05C" => $insertData['AAA05C'] ?? '',
                "AAA40" => $insertData['AAA40'] ?? '',
                "AAA42" => $insertData['AAA42'] ?? '',
                "AEN01" => $insertData['AEN01'] ?? '',
                "AAA06C" => $insertData['AAA06C'] ?? '',
                "AAA07" => $insertData['AAA07'] ?? '',
                "AAA08C" => $insertData['AAA08C'] ?? '',
                "AEM01C" => $insertData['AEM01C'] ?? '',
                "AAB01" => $insertData['AAB01'] ?? '',
                "AAC01" => $insertData['AAC01'] ?? '',
                "AAC11N" => $insertData['AAC11N'] ?? '',
                "AAC04" => $insertData['AAC04'] ?? '',
                "ADA01" => $insertData['ADA01'] ?? '',
                "ADA0101" => $insertData['ADA0101'] ?? '',
                "AAA29" => $insertData['AAA29'] ?? '',
                "AAB06C" => $insertData['AAB06C'] ?? '',
                "ABC01N" => $insertData['ABC01N'] ?? '',
                "ICD10_NAME" => $insertData['ICD10_NAME'] ?? '',
                "ICD9_NAME" => $insertData['ICD9_NAME'] ?? '',
                "ORG_STATE" => $insertData['ORG_STATE'] ?? '',
                "AAA26C" => $insertData['AAA26C'] ?? '',
                "ATTEND_GRP_CODE" => $insertData['ATTEND_GRP_CODE'] ?? '',
                "ATTEND_GRP_NAME" => $insertData['ATTEND_GRP_NAME'] ?? '',
                "F_D" => $insertData['F_D'] ?? '',
                "J" => $insertData['J'] ?? '',
                "coder_id" => $insertData['coder_id'] ?? '',
                "score" => $insertData['score'] ?? '',
                "is_error" => $insertData['is_error'] ?? '',
                "ABG01N" => $insertData['ABG01N'] ?? '',
                "ABG01C" => $insertData['ABG01C'] ?? '',
                "status" => $insertData['status'] ?? '',
                "created_at" => date('Y-m-d H:i:s', time()),
                "updated_at" => date('Y-m-d H:i:s', time()),
                "source" => $insertData['source'] ?? '',
                "level" => $insertData['level'] ?? '',
                "is_defect" => $insertData['is_defect'] ?? '',
                "is_defect_v2" => $insertData['is_defect_v2'] ?? '',
            ], 'doc_as_upsert' => true];
            app('es')->bulk($es_params);
        } catch (\Exception $e) {
            Log::info('病案首页数据同步Es失败', ['id'=>$patientInfoId,'msg'=>$e->getMessage()]);
        }

        return $patientInfoId;
    }

    /**
     * 病案首页（诊断信息） - patient_medical_info 表
     * @param $val
     * @param $ZYH
     * @param $field
     * @return bool
     */
    public function addPatientMedicalInfo($val,$ZYH,$field)
    {
        $insertData = [];
        $insertData['AAA28'] = $ZYH;    // 住院号
        $insertData['ABA01C'] = !empty($field['C01C']) ? $val[$field['C01C']] : ''; // 门（急）诊诊断编码
        $insertData['ABA01N'] = !empty($field['C02N']) ? $val[$field['C02N']] : ''; // 门（急）诊诊断名称
        $insertData['ABC03C'] = !empty($field['F01']) ? $val[$field['F01']] : '';   // 入院病情代码
        $insertData['ABF01C'] = !empty($field['C09C']) ? $val[$field['C09C']] : ''; // 病理诊断编码
        $insertData['ABF01N'] = !empty($field['C10N']) ? $val[$field['C10N']] : ''; // 病理诊断名称
        $insertData['ABF04'] = !empty($field['C11']) ? $val[$field['C11']] : '';    // 病理号
//        $insertData['ABF02C'] = !empty($field['']) ? $val[$field['']] : '';       // 最高诊断依据代码
//        $insertData['ABF03C'] = !empty($field['']) ? $val[$field['']] : '';       // 分化程度编码
//        $insertData['ABH01C'] = !empty($field['']) ? $val[$field['']] : '';       // 肿瘤分期是否不详
//        $insertData['ABH0201C'] = !empty($field['']) ? $val[$field['']] : '';       // 肿瘤分期 TID
//        $insertData['ABH0202C'] = !empty($field['']) ? $val[$field['']] : '';       // 肿瘤分期 NID
//        $insertData['ABH0203C'] = !empty($field['']) ? $val[$field['']] : '';       // 肿瘤分期 MID
//        $insertData['ABH03C'] = !empty($field['']) ? $val[$field['']] : '';         // 0～Ⅳ肿瘤分期ID
        $insertData['AEB02C'] = !empty($field['C24C']) ? $val[$field['C24C']] : ''; // 有无药物过敏
        $insertData['AEB01'] = !empty($field['C25']) ? $val[$field['C25']] : '';    // 过敏药物名称
        $insertData['AED01C'] = !empty($field['B30C']) ? $val[$field['B30C']] : ''; // 病案质量代码
        $insertData['AEG01C'] = !empty($field['C26C']) ? $val[$field['C26C']] : ''; // 血型
        $insertData['AEG02C'] = !empty($field['C27C']) ? $val[$field['C27C']] : ''; // Rh血型
        $insertData['AEG04'] = !empty($field['F22']) ? $val[$field['F22']] : '';    // 红细胞
        $insertData['AEG05'] = !empty($field['F23']) ? $val[$field['F23']] : '';    // 血小板
        $insertData['AEG06'] = !empty($field['F24']) ? $val[$field['F24']] : '';    // 血浆
        $insertData['AEG07'] = !empty($field['F25']) ? $val[$field['F25']] : '';    // 全血
        $insertData['AEG08'] = !empty($field['F26']) ? $val[$field['F26']] : '';    // 自体血回输
        $insertData['AEJ01'] = !empty($field['C28']) ? $val[$field['C28']] : '';    // 颅脑损伤患者入院前昏迷时间（天）
        $insertData['AEJ02'] = !empty($field['C29']) ? $val[$field['C29']] : '';    // 颅脑损伤患者入院前昏迷时间（小时）
        $insertData['AEJ03'] = !empty($field['C30']) ? $val[$field['C30']] : '';    // 颅脑损伤患者入院前昏迷时间（分钟）
        $insertData['AEJ04'] = !empty($field['C31']) ? $val[$field['C31']] : '';    // 颅脑损伤患者入院后昏迷时间（天）
        $insertData['AEJ05'] = !empty($field['C32']) ? $val[$field['C32']] : '';    // 颅脑损伤患者入院后昏迷时间（小时）
        $insertData['AEJ06'] = !empty($field['C33']) ? $val[$field['C33']] : '';    // 颅脑损伤患者入院后昏迷时间（分钟）
//        $insertData['AEN02C'] = !empty($field['']) ? $val[$field['']] : '';         // 新生儿出生缺陷诊断
//        $insertData['AEN02N'] = !empty($field['']) ? $val[$field['']] : '';         // 新生儿出生缺陷诊断名称
//        $insertData['AEI09'] = !empty($field['']) ? $val[$field['']] : '';          // 日常生活能力评定量得分（出院）
//        $insertData['AEI10'] = !empty($field['']) ? $val[$field['']] : '';          // 日常生活能力评定量得分（出院）
//        $insertData['AEI08'] = !empty($field['']) ? $val[$field['']] : '';          // 备注

        foreach ($insertData as &$v) {
            $v = $v!==null ? $v : '';
        }

        return PatientMedicalInfo::query()->insert($insertData);
    }

    /**
     * 病案首页（费用信息） - patient_cost_info 表
     * @param $val
     * @param $ZYH
     * @param $field
     * @return bool
     */
    public function addPatientCostInfo($val,$ZYH,$field)
    {
        $insertData = [];
        $insertData['AAA28'] = $ZYH;    // 住院号
        $insertData['ADA0101'] = !empty($field['D09']) ? $val[$field['D09']] : ''; // 住院总费用其中自付金额
//        $insertData['AAE040'] = !empty($field['']) ? $val[$field['']] : ''; // 结算时间
        $insertData['D11'] = !empty($field['D11']) ? $val[$field['D11']] : '';  // 一般医疗服务费
        $insertData['D12'] = !empty($field['D12']) ? $val[$field['D12']] : '';  // 一般治疗操作费
        $insertData['D13'] = !empty($field['D13']) ? $val[$field['D13']] : '';  // 护理费
        $insertData['D14'] = !empty($field['D14']) ? $val[$field['D14']] : '';  // 综合医疗服务类其他费用
        $insertData['D15'] = !empty($field['D15']) ? $val[$field['D15']] : '';  // 病理诊断费
        $insertData['D16'] = !empty($field['D16']) ? $val[$field['D16']] : '';  // 实验室诊断费
        $insertData['D17'] = !empty($field['D17']) ? $val[$field['D17']] : '';  // 影像学诊断费
        $insertData['D18'] = !empty($field['D18']) ? $val[$field['D18']] : '';  // 临床诊断项目费
        $insertData['D19'] = !empty($field['D19']) ? $val[$field['D19']] : '';  // 非手术治疗项目费
        $insertData['D19X01'] = !empty($field['D19X01']) ? $val[$field['D19X01']] : ''; // 其中:临床物理治疗费
        $insertData['D20'] = !empty($field['D20']) ? $val[$field['D20']] : '';  // 手术治疗费
        $insertData['D20X01'] = !empty($field['D20X01']) ? $val[$field['D20X01']] : ''; // 其中：麻醉费
        $insertData['D20X02'] = !empty($field['D20X02']) ? $val[$field['D20X02']] : ''; // 其中：手术费
        $insertData['D21'] = !empty($field['D21']) ? $val[$field['D21']] : '';  // 康复费
        $insertData['D22'] = !empty($field['D22']) ? $val[$field['D22']] : '';  // 中医治疗费
        $insertData['D23'] = !empty($field['D23']) ? $val[$field['D23']] : '';  // 西药费
        $insertData['D23X01'] = !empty($field['D23X01']) ? $val[$field['D23X01']] : ''; // 其中：抗菌药物费
        $insertData['D24'] = !empty($field['D24']) ? $val[$field['D24']] : '';  // 中成药费
        $insertData['D25'] = !empty($field['D25']) ? $val[$field['D25']] : '';  // 中草药费
        $insertData['D26'] = !empty($field['D26']) ? $val[$field['D26']] : '';  // 血费
        $insertData['D27'] = !empty($field['D27']) ? $val[$field['D27']] : '';  // 白蛋白类制品费
        $insertData['D28'] = !empty($field['D28']) ? $val[$field['D28']] : '';  // 球蛋白类制品费
        $insertData['D29'] = !empty($field['D29']) ? $val[$field['D29']] : '';  // 凝血因子类制品费
        $insertData['D30'] = !empty($field['D30']) ? $val[$field['D30']] : '';  // 细胞因子类制品费
        $insertData['D31'] = !empty($field['D31']) ? $val[$field['D31']] : '';  // 检查用一次性医用材料费
        $insertData['D32'] = !empty($field['D32']) ? $val[$field['D32']] : '';  // 治疗用一次性医用材料费
        $insertData['D33'] = !empty($field['D33']) ? $val[$field['D33']] : '';  // 手术用一次性医用材料费
        $insertData['D34'] = !empty($field['D34']) ? $val[$field['D34']] : '';  // 其他费

        foreach ($insertData as &$v) {
            $v = $v!==null ? $v : '';
        }

        return PatientCostInfo::query()->insert($insertData);
    }

    /**
     * 病案首页（地址信息） - patient_address_info 表
     * @param $val
     * @param $ZYH
     * @param $field
     * @return bool
     */
    public function addPatientAddressinfo($val,$ZYH,$field)
    {
        $insertData = [];
        $insertData['AAA28'] = $ZYH;    // 住院号

        // 出生地址解析
        $A22 = !empty($field['A22']) ? $val[$field['A22']] : '';
        $A22 = $this->getAddress($A22);
        $insertData['AAA09'] = $A22['province'];    // 出生地省（区、市）
        $insertData['AAA10'] = $A22['city'];        // 出生地市
        $insertData['AAA11'] = $A22['area'];        // 出生地县

        $insertData['AAA43'] = !empty($field['A23C']) ? $val[$field['A23C']] : '';  // 籍贯省
//        $insertData['AAA44'] = !empty($field['']) ? $val[$field['']] : '';// 籍贯市 todo 数据来源没有

        // 户籍解析
        $A24 = !empty($field['A24']) ? $val[$field['A24']] : '';
        $A24 = $this->getAddress($A24);
        $insertData['AAA45'] = $A24['province'];    // 户籍省
        $insertData['AAA46'] = $A24['city'];        // 户籍市
        $insertData['AAA47'] = $A24['area'];        // 户籍县
        $insertData['AAA12'] = $A24['address'];     // 户籍详细地址
//        $insertData['AAA13C'] = !empty($field['']) ? $val[$field['']] : '';// 户籍地址区县编码 todo 数据来源没有
//        $insertData['AAA33C'] = !empty($field['']) ? $val[$field['']] : '';// 户籍街道乡镇代码 todo 数据来源没有
        $insertData['AAA14C'] = !empty($field['A25C']) ? $val[$field['A25C']] : ''; // 户籍地址邮政编码

        // 现住址解析
        $A26 = !empty($field['A26']) ? $val[$field['A26']] : '';
        $A26 = $this->getAddress($A26);
        $insertData['AAA48'] = $A26['province'];    // 现住址省
        $insertData['AAA49'] = $A26['city'];        // 现住址市
        $insertData['AAA50'] = $A26['area'];        // 现住址县
        $insertData['AAA15'] = $A26['address'];     // 现住址详细地址
//        $insertData['AAA16C'] = !empty($field['']) ? $val[$field['']] : '';// 现住址区县编码 todo 数据来源没有
//        $insertData['AAA36C'] = !empty($field['']) ? $val[$field['']] : '';// 现住址街道乡镇代码 todo 数据来源没有
        $insertData['AAA51'] = !empty($field['A27']) ? $val[$field['A27']] : '';// 现住址电话
        $insertData['AAA17C'] = !empty($field['A28C']) ? $val[$field['A28C']] : '';// 现住址邮政编码

        foreach ($insertData as &$v) {
            $v = $v!==null ? $v : '';
        }

        return PatientAddressInfo::query()->insert($insertData);
    }

    /**
     * 病案首页（联系人信息） - patient_contacts_info 表
     * @param $val
     * @param $ZYH
     * @param $field
     * @return bool
     */
    public function addPatientContactsInfo($val,$ZYH,$field)
    {
        $insertData = [];
        $insertData['AAA22'] = !empty($field['A32']) ? desensitize($val[$field['A32']], 1, 1, '*') : '';    // 联系人姓名
        $insertData['AAA23C'] = !empty($field['A33C']) ? $val[$field['A33C']] : ''; // 联系人关系
        $insertData['AAA24'] = !empty($field['A34']) ? $val[$field['A34']] : '';    // 联系人地址
        $insertData['AAA25'] = !empty($field['A35']) ? $val[$field['A35']] : '';    // 联系人电话

        foreach ($insertData as &$v) {
            $v = $v!==null ? $v : '';
        }

        return PatientContactsInfo::query()->insert($insertData);
    }

    /**
     * 病案首页（医生信息） - patient_doctor_info 表
     * @param $val
     * @param $ZYH
     * @param $field
     * @return bool
     */
    public function addPatientDoctorInfo($val,$ZYH,$field)
    {
        $insertData = [];
        $insertData['AAA28'] = $ZYH;    // 住院号
        $insertData['AED02'] = !empty($field['B31']) ? $val[$field['B31']] : '';    // 质控医师姓名
        $insertData['AED03'] = !empty($field['B32']) ? $val[$field['B32']] : '';    // 质控护士姓名
        $insertData['AED04'] = !empty($field['B33']) ? $val[$field['B33']] : '';    // 质控日期
        $insertData['AEE01'] = !empty($field['B22']) ? $val[$field['B22']] : '';    // 科主任
        $insertData['AEE01_CODE'] = !empty($field['B22C']) ? $val[$field['B22C']] : ''; // 科主任编码
        $insertData['AEE02'] = !empty($field['B23']) ? $val[$field['B23']] : '';    // 主（副主）任医师
        $insertData['AEE02_CODE'] = !empty($field['B23C']) ? $val[$field['B23C']] : ''; // 主（副主）任医师编码
        $insertData['AEE03'] = !empty($field['B24']) ? $val[$field['B24']] : '';    // 主治医师姓名
        $insertData['AEE03_CODE'] = !empty($field['B24C']) ? $val[$field['B24C']] : ''; // 主治医师编码
//        $insertData['AEE11'] = !empty($field['']) ? $val[$field['']] : '';        // 主诊医师执业证书编码
//        $insertData['AEE09'] = !empty($field['']) ? $val[$field['']] : '';        // 主诊医师姓名
        $insertData['AEE04'] = !empty($field['B25']) ? $val[$field['B25']] : '';    // 住院医师姓名
        $insertData['AEE04_CODE'] = !empty($field['B25C']) ? $val[$field['B25C']] : ''; // 住院医师编码
        $insertData['AEE05'] = !empty($field['B27']) ? $val[$field['B27']] : '';    // 进修医师姓名
        $insertData['AEE07'] = !empty($field['B28']) ? $val[$field['B28']] : '';    // 实习医师姓名
        $insertData['AEE08'] = !empty($field['B29']) ? $val[$field['B29']] : '';    // 编码员姓名
        $insertData['AEE10'] = !empty($field['B26']) ? $val[$field['B26']] : '';    // 责任护士
//        $insertData['CODE_DATE'] = !empty($field['']) ? $val[$field['']] : '';  // 编码完成时间
//        $insertData['COMPLETION_DATE'] = !empty($field['']) ? $val[$field['']] : '';// 病历完成时间
//        $insertData['SIGN_IN_DATE'] = !empty($field['']) ? $val[$field['']] : '';   // 病案签收时间

        foreach ($insertData as &$v) {
            $v = $v!==null ? $v : '';
        }

        return PatientDoctorInfo::query()->insert($insertData);
    }

    /**
     * 病案首页（其它信息） - patient_other_info 表
     * @param $val
     * @param $ZYH
     * @param $field
     * @return bool
     */
    public function addPatientOtherInfo($val,$ZYH,$field)
    {
        $AAB07D = !empty($field['F04']) ? $val[$field['F04']] : '';
        if ($AAB07D) {
            $AAB07D = $this->getDateTime($AAB07D);
        }
        $insertData = [];
        $insertData['AAA28'] = $ZYH;    // 住院号
        $insertData['AAB07C'] = !empty($field['F02C']) ? $val[$field['F02C']] : ''; // 入院诊断编码
        $insertData['AAB07N'] = !empty($field['F03N']) ? $val[$field['F03N']] : ''; // 入院诊断名称
        $insertData['AAB07'] = !empty($field['F01']) ? $val[$field['F01']] : '';    // 入院时情况
        $insertData['AAB07D'] = $AAB07D;    // 入院后确诊日期
        $insertData['MED_REC_ID'] = $ZYH;
        $insertData['UNT_ID'] = isset($field['A01']) ? $val[$field['A01']] : '';   // 组织机构代码
        $insertData['ZA03'] = isset($field['A02']) ? $val[$field['A02']] : '';     // 医疗机构名称
        $insertData['AFA03'] = !empty($field['F10']) ? $val[$field['F10']] : '';    // HBsAg
        $insertData['AFA04'] = !empty($field['F11']) ? $val[$field['F11']] : '';    // HCV-Ab
        $insertData['AFA05'] = !empty($field['F12']) ? $val[$field['F12']] : '';    // HIV-Ab

        foreach ($insertData as &$v) {
            $v = $v!==null ? $v : '';
        }

        return PatientOtherInfo::query()->insert($insertData);
    }

    /**
     * 病案首页（病案首页工作信息） - patient_work_info 表
     * @param $val
     * @param $ZYH
     * @param $field
     * @return bool
     */
    public function addPatientWorkInfo($val,$ZYH,$field)
    {
        // 职业
        $AAA18C = !empty($field['A38C']) ? $val[$field['A38C']] : '';
        if (!empty($AAA18C)) {
            $zhiye = array_flip(config('dictionaries.AAA18C'));
            $AAA18C = !empty($zhiye[$AAA18C]) ? $zhiye[$AAA18C] : $AAA18C;
        }
        $insertData = [];
        $insertData['AAA28'] = $ZYH;    // 住院号
        $insertData['AAA18C'] = $AAA18C; // 职业
        $insertData['AAA19'] = !empty($field['A29']) ? $val[$field['A29']] : '';    // 工作单位及地址
        $insertData['AAA20'] = !empty($field['A30']) ? desensitize($val[$field['A30']], 3, 4, '*') : '';    // 工作单位电话
        $insertData['AAA21C'] = !empty($field['A31C']) ? $val[$field['A31C']] : ''; // 工作单位邮政编码

        foreach ($insertData as &$v) {
            $v = $v!==null ? $v : '';
        }

        return PatientWorkInfo::query()->insert($insertData);
    }

    /**
     * 病案首页（住院信息） - patient_hospital_info 表
     * @param $val
     * @param $ZYH
     * @param $field
     * @param $delList
     * @return bool
     */
    public function addPatientHospitalInfo($val,$ZYH,$field,$delList)
    {
        $B13C = !empty($field['B13C']) ? $val[$field['B13C']] : '';
        $B13C_NAME = $B13C;
        if ($B13C) {
            $B13C_NAME = $delList[$B13C] ?? $B13C;
        }
        $insertData = [];
        $insertData['AAA28'] = $ZYH;    // 住院号
        $insertData['AAA30'] = !empty($field['A48']) ? $val[$field['A48']] : '';    // 病案号
        $insertData['ABC01C'] = !empty($field['C03C']) ? $val[$field['C03C']] : ''; // 出院时主要诊断编码
//        $insertData['AAA27'] = !empty($field['']) ? $val[$field['']] : '';  // 医疗保险手册(卡)号
//        $insertData['AAC001'] = !empty($field['']) ? $val[$field['']] : ''; // 医保个人编号
        $insertData['AAB01'] = !empty($field['B12']) ? $this->getDateTime($val[$field['B12']]) : '';    // 入院时间
        $insertData['AAB02C'] = !empty($field['B13C']) ? $val[$field['B13C']] : ''; // 入院科别代码
        $insertData['AAB03'] = !empty($field['B14']) ? $val[$field['B14']] : '';    // 入院病房
        $insertData['AAB11C'] = !empty($field['B13C']) ? $val[$field['B13C']] : ''; // 入院医院内部科室代码
        $insertData['AAB11N'] = $B13C_NAME;                                         // 入院医院内部科室名称
        $insertData['AAC02C'] = !empty($field['B16C']) ? $val[$field['B16C']] : ''; // 出院科别代码
        $insertData['AAC03'] = !empty($field['B17']) ? $val[$field['B17']] : '';    // 出院病房
        $insertData['AAC11C'] = !empty($field['B16C']) ? $val[$field['B16C']] : ''; // 出院医院内部科室代码
        $insertData['AAD01C'] = !empty($field['B21C']) ? $val[$field['B21C']] : ''; // 转经科别代码
        $insertData['AEM02'] = !empty($field['B35']) ? $val[$field['B35']] : '';    // 医嘱转院、转社区、卫生院机编码ID
        $insertData['AEM03C'] = !empty($field['B36C']) ? $val[$field['B36C']] : ''; // 是否有出院31日内再住院计划
        $insertData['AEM04'] = !empty($field['B37']) ? $val[$field['B37']] : '';    // 出院31天再住院计划目的
        $insertData['AEI01C'] = !empty($field['C34C']) ? $val[$field['C34C']] : ''; // 死亡患者尸检

        foreach ($insertData as &$v) {
            $v = $v!==null ? $v : '';
        }

        return PatientHospitalInfo::query()->insert($insertData);
    }

    /**
     * 病案首页（主要诊断） - main_diagnosis 表
     * @param $val
     * @param $ZYH
     * @param $field
     * @return bool
     */
    public function addMainDiagnosis($val,$ZYH,$field)
    {
        $RYQK = '';
        if (!empty($field['C05C'])) {
            $RYQK = MainDiagnosis::RYQK[$val[$field['C05C']]] ?? $val[$field['C05C']];
        }

        $insertData = [];
        $insertData['AAA28'] = $ZYH;    // 住院号
        $insertData['ICD10_ID1'] = !empty($field['C03C']) ? $val[$field['C03C']] : '';  // 出院主要诊断编码
        $insertData['ICD10_NAME'] = !empty($field['C04N']) ? trim($val[$field['C04N']]) : ''; // 出院主要诊断名称
        $insertData['DIA_ORDER'] = 1;   // 诊断次序
        $insertData['AREA_ID'] = '';    // 所属区域ID
        $insertData['BATCH_ID'] = '';   // 批次号
        $insertData['LBMC'] = '出院诊断';
        $insertData['RYQK'] = $RYQK;    // 入院情况

        foreach ($insertData as &$v) {
            $v = $v!==null ? $v : '';
        }

        return MainDiagnosis::query()->insert($insertData);
    }

    /**
     * 病案首页（其它诊断） - other_diagnosis 表
     * @param $val
     * @param $ZYH
     * @param $field
     * @return bool
     */
    public function addOtherDiagnosis($val,$ZYH,$field)
    {
        $qtZdArr = [
            '01','02','03','04','05','06','07','08','09','10','11','12','13','14','15','16','17','18','19','20','21',
            '22','23','24','25','26','27','28','29','30','31','32','33','34','35','36','37','38','39','40'
        ];

        $insertData = [];
        foreach ($qtZdArr as $i) {
            if ((!empty($field['C06x'.$i.'C']) && !empty($field['C07x'.$i.'N'])) && ($val[$field['C06x'.$i.'C']] && $val[$field['C07x'.$i.'N']])) {
                $RYQK = '';
                if (!empty($field['C05C'])) {
                    $RYQK = MainDiagnosis::RYQK[$val[$field['C08x'.$i.'C']]] ?? $val[$field['C08x'.$i.'C']];
                }
                $insertData[] = [
                    'AAA28' => $ZYH,
                    'ICD10_ID1' => $val[$field['C06x'.$i.'C']] ? trim($val[$field['C06x'.$i.'C']]) : '',
                    'ICD10_NAME' => $val[$field['C07x'.$i.'N']] ? trim($val[$field['C07x'.$i.'N']]) : '',
                    'DIA_ORDER' => 1,
                    'AREA_ID' => '',
                    'RYQK' => $RYQK,
                ];
            }
        }

        if (!empty($insertData)) {
            return OtherDiagnosis::query()->insert($insertData);
        }

        return true;
    }

    /**
     * 病案首页（主要手术） - main_operation 表
     * @param $val
     * @param $ZYH
     * @param $field
     * @return bool
     */
    public function addMainOperation($val,$ZYH,$field)
    {
        $insertData = [];
        $insertData['AAA28'] = $ZYH;    // 住院号
        $insertData['ICD9_ID1'] = !empty($field['C14x01C']) ? trim($val[$field['C14x01C']]) : '';     // 主要手术编码
        $insertData['ICD9_NAME'] = !empty($field['C15x01N']) ? trim($val[$field['C15x01N']]) : '';    // 主要手术名称
        if (empty($insertData['ICD9_ID1']) || $insertData['ICD9_ID1']=='NULL' || $insertData['ICD9_ID1']=='-') {
            return true;
        }

        $START_TIME = !empty($field['C16x01']) ? $this->getDateTime($val[$field['C16x01']]) : '';
        $endTime = strtotime($START_TIME)+($val[$field['F13']]*3600);
        $END_TIME = date('Y-m-d H:i:s', $endTime);
        $OPE_DATE = date('Y-m-d', strtotime($START_TIME)).' 00:00:00';

        $insertData['OPE_DATE'] = $OPE_DATE;        // 主要手术日期
        $insertData['OPE_MAN_NAME'] = !empty($field['C18x01']) ? $val[$field['C18x01']] : '';   // 主要手术操作术者
        $insertData['OPE_MAN_CODE'] = '';
        $insertData['FRIST_ASSISTANT_NAME'] = !empty($field['C19x01']) ? $val[$field['C19x01']] : '';   // 主要手术操作Ⅰ助
        $insertData['FRIST_ASSISTANT_CODE'] = '';
        $insertData['SECOND_ASSISTANT_NAME'] = !empty($field['C20x01']) ? $val[$field['C20x01']] : '';  // 主要手术操作Ⅱ助
        $insertData['SECOND_ASSISTANT_CODE'] = '';
        $insertData['HOCUS_WAY_ID'] = !empty($field['F15']) ? $val[$field['F15']] : '';         // 麻醉方式
        $insertData['INCISION_GRADE_ID'] = !empty($field['C21x01C']) ? $val[$field['C21x01C']] : '';    // 主要手术操作切口愈合等级
        $insertData['HOCUS_MAN_NAME'] = !empty($field['C23x01']) ? $val[$field['C23x01']] : ''; // 主要手术操作麻醉医师
        $insertData['HOCUS_MAN_CODE'] = '';
        $insertData['START_TIME'] = $START_TIME;    // 手术开始时间
        $insertData['END_TIME'] = $END_TIME;        // 手术结束时间
        $insertData['OPE_ORDER'] = 1;               // 手术顺序
        $insertData['OPE_LEVEL'] = !empty($field['C17x01']) ? $val[$field['C17x01']] : '';      // 手术级别
        $insertData['RJSS'] = '否';
        $insertData['AREA_ID'] = '';
        $insertData['BATCH_ID'] = '';

        foreach ($insertData as &$v) {
            $v = $v!==null ? $v : '';
        }

        return MainOperation::query()->insert($insertData);
    }

    /**
     * 病案首页（其它手术） - secondary_operation 表
     * @param $val
     * @param $ZYH
     * @param $field
     * @return bool
     */
    public function addSecondaryOperation($val,$ZYH,$field)
    {
        $qtSSarr = [
            '01','02','03','04','05','06','07','08','09','10','11','12','13','14','15','16','17','18','19','20','21',
            '22','23','24','25','26','27','28','29','30','31','32','33','34','35','36','37','38','39','40'
        ];

        $insertData = [];
        $OPE_ORDER = 2;
        foreach ($qtSSarr as $i) {
            if ((!empty($field['C35x'.$i.'C']) && !empty($field['C36x'.$i.'N'])) && ($val[$field['C35x'.$i.'C']] && $val[$field['C36x'.$i.'N']])) {
                $START_TIME = !empty($field['C37x'.$i]) ? $this->getDateTime($val[$field['C37x'.$i]]) : '';
                $endTime = strtotime($START_TIME)+($val[$field['F14x'.$i]]*3600);
                $END_TIME = date('Y-m-d H:i:s', $endTime);

                $OPE_DATE = date('Y-m-d', strtotime($START_TIME)).' 00:00:00';
                $insertData[] = [
                    'AAA28' => $ZYH,
                    'ICD9_ID1' => trim($val[$field['C35x'.$i.'C']]),
                    'ICD9_NAME' => trim($val[$field['C36x'.$i.'N']]),
                    'OPE_DATE' =>  $OPE_DATE,
                    'OPE_MAN_NAME' => $val[$field['C39x'.$i]] ?: '',
                    'OPE_MAN_CODE' => '',
                    'FRIST_ASSISTANT_NAME' => $val[$field['C40x'.$i]] ?: '',
                    'FRIST_ASSISTANT_CODE' => '',
                    'SECOND_ASSISTANT_NAME' => $val[$field['C41x'.$i]] ?: '',
                    'SECOND_ASSISTANT_CODE' => '',
                    'HOCUS_WAY_ID' => $val[$field['C43x'.$i.'C']] ?: '',
                    'INCISION_GRADE_ID' => $val[$field['C42x'.$i.'C']] ?: '',
                    'HOCUS_MAN_NAME' => $val[$field['C44x'.$i]] ?: '',
                    'HOCUS_MAN_CODE' => '',
                    'START_TIME' => $START_TIME,
                    'END_TIME' => $END_TIME,
                    'OPE_ORDER' => $OPE_ORDER,
                    'OPE_LEVEL' => $val[$field['C38x'.$i]] ?: '',
                    'RJSS' => '否',
                    'AREA_ID' => '',
                    'BATCH_ID' => '',
                ];
                $OPE_ORDER++;
            }
        }

        if (!empty($insertData)) {
            return SecondaryOperation::query()->insert($insertData);
        }

        return true;
    }

    /**
     * 病案首页（重症监信息） - icu 表
     * @param $val
     * @param $ZYH
     * @param $field
     * @return bool
     */
    public function addICU($val,$ZYH,$field)
    {
        $zzjhArr = ['01','02','03','04','05'];

        $insertData = [];
        foreach ($zzjhArr as $i) {
            if (!empty($field['C48x'.$i.'C']) && !empty($val[$field['C48x'.$i.'C']])) {
                $C49 = $this->getDateTime($val[$field['C49x'.$i]]);
                $C50 = $this->getDateTime($val[$field['C50x'.$i]]);
                $IS_MAIN_WAY = !empty($val[$field['C48x'.$i.'C']]) ? $val[$field['C48x'.$i.'C']] : '';
                $insertData[] = [
                    'AAA28' => $ZYH,
                    'IS_MAIN_WAY' => $IS_MAIN_WAY,
                    'C49' => $C49,
                    'C50' => $C50
                ];
            }
        }

        if (!empty($insertData)) {
            return Icu::query()->insert($insertData);
        }

        return true;
    }

    /**
     * 地址解析省、市、县、详细地址
     * @param $address
     * @return array
     */
    public function getAddress($address)
    {
        // 解析省
        preg_match('/(.*?(省|自治区|北京|天津|上海|重庆))/', $address, $matches);
        if (count($matches) > 1) {
            $province = $matches[count($matches) - 2];
            $address = preg_replace('/(.*?(省|自治区|北京|天津|上海|重庆))/','', $address, 1);
        }

        // 解析市
        preg_match('/(.*?(市|自治州|地区|区划|县))/', $address, $matches);
        if (count($matches) > 1) {
            $city = $matches[count($matches) - 2];
            $address = str_replace($city, '', $address);
        }

        // 解析县、详细地址
        preg_match('/(.*?(区|县|镇|乡|街道))/', $address, $matches);
        if (count($matches) > 1) {
            $area = $matches[count($matches) - 2];
            $address = str_replace($area, '', $address);
        }

        return [
            'province' => isset($province) ? $province : '',
            'city' => isset($city) ? $city : '',
            'area' => isset($area) ? $area : '',
            "address" => $address
        ];
    }

    /**
     * @param $time
     * @param $type
     * @return false|string
     */
    public function getDateTime($time,$type=1)
    {
        if (empty(trim($time)) || $time=='-' || $time=='--') {
            return '';
        }

        if (is_numeric($time)) {
            try {
                $time = (string)($time*3600*24);
                $time = strtotime('1900-01-01 00:00:00')+$time+343-3600*48;
            } catch (\Exception $e) {
                $time = '';
            }

            if (empty($time)) {
                return '';
            }

            if ($type == 2) {
                return date("Y-m-d",$time);
            }

            return date("Y-m-d H:i:s",$time);
        } else {
            if ($type == 2) {
                return date("Y-m-d", strtotime($time));
            }

            return $time;
        }
    }

}
