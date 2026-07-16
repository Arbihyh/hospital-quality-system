<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BL01_NEW;
use App\Model\EMR_BL_BLSY;
use App\Model\Staff;
use App\Model\ZY_BRRY;
use App\Services\MedicalRecordService;
use App\Services\QualityService;
use App\Services\ToolsService;
use App\Services\TestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Pheanstalk\Pheanstalk;
use function GuzzleHttp\json_decode;

class MedicalRecordController extends Controller
{
    /**
     * home
     * 住院病案首页
     * @bodyParam id string required 病案号
     * @response  {
     *  "code":200,
     *  "msg":"结果",
     *  "data":{
     *      "AAA26C":"医疗付费方式",
     *      "AAA29":"住院次数",
     *      "AAB06C":"入院途径代码",
     *      "AAA01":"姓名",
     *      "AAA02C":"性别",
     *      "AAA03":"出生日期",
     *      "AAA04":"年龄",
     *      "AAA05C":"国籍",
     *      "AAA06C":"民族",
     *      "AAA07":"身份证号",
     *      "AAA08C":"婚姻状况代码",
     *      "AAA40":"不足1周岁年龄",
     *      "AEN01":"新生儿出生体重",
     *      "AAA42":"新生儿入院体重",
     *      "AAA26C":"医疗付费方式代吗",
     *      "AAC04":"实际住院(天)",
     *      "AAA09":"出生地省",
     *      "AAA10":"出生地市",
     *      "AAA11":"出生地县",
     *      "AAA43":"籍贯省",
     *      "AAA44":"籍贯市",
     *      "AAA48":"现居住地省",
     *      "AAA49":"现居住地市",
     *      "AAA50":"现居住地县",
     *      "AAA15":"现住址详细地址（居住半年以上）",
     *      "AAA51":"现住址电话",
     *      "AAA17C":"现住址邮政编码",
     *      "AAA45":"户籍省（区、市）",
     *      "AAA46":"户籍市",
     *      "AAA47":"户籍县",
     *      "AAA12":"户籍详细地址",
     *      "AAA14C":"户籍地址邮政编码",
     *      "AAA19":"工作单位及地址",
     *      "AAA20":"工作单位电话",
     *      "AAA21C":"工作单位邮政编码",
     *      "AAA18C":"职业代码(可能得换成职业名称)",
     *      "AAA22":"联系人姓名",
     *      "AAA24":"联系人地址",
     *      "AAA25":"联系人电话",
     *      "AAB01":"入院时间",
     *      "AAB02C":"入院科别代码",
     *      "AAB03":"入院病房",
     *      "AAC02C":"出院科别代码",
     *      "AAC03":"出院病房",
     *      "AAD01C":"转经科别代码",
     *      "ABA01N":"门（急）诊诊断名称",
     *      "ABA01C":"门(急)诊诊断编码-疾病编码",
     *      "diagnosis":{
     *          "list":[{
     *              "ICD10_NAME":"出院诊断名称",
     *              "ICD10_ID1":"诊断编码"
     *          }]
     *      },
     *      "other_diagnosis":{
     *          "list":[{
     *              "ICD10_NAME":"出院诊断名称",
     *              "ICD10_ID1":"诊断编码"
     *          }]
     *      },
     *      "diagnosis":{
     *          "list":[{
     *              "id":"id",
     *              "class":"主要诊断main,其他诊断other",
     *              "ICD10_NAME":"出院诊断名称",
     *              "ICD10_ID1":"诊断编码"
     *          }]
     *      },
     *      "ABC03C":"入院病情代码",
     *      "ABF01N":"病理诊断名称",
     *      "ABF04":"病理号",
     *      "ABF01C":"病理诊断编码(M码)ID",
     *      "AEB02C":"有无药物过敏",
     *      "AEB01":"过敏药物",
     *      "AEI01C":"是否尸检代码",
     *      "AEG01C":"血型代码",
     *      "AEG02C":"Rh 代码",
     *      "AEE01":"科主任姓名",
     *      "AEE02":"主(副主)任医师姓名",
     *      "AEE03":"主治医师姓名",
     *      "AEE04":"住院医师姓名",
     *      "AEE10":"责任护士姓名",
     *      "AEE05":"进修医师姓名",
     *      "AEE07":"实习医师姓名",
     *      "AEE08":"编码员姓名", 现在改为"BMY"
     *      "AED02":"质控医师姓名",
     *      "AED03":"质控护士姓名",
     *      "AED04":"病案质量检查日期",
     *      "AED01C":"病案质量代码",
     *      "main_operation":{
     *             "list": [{
     *                  "ICD9_ID1":"手术或操作ID",
     *                  "OPE_DATE":"手术或操作日期",
     *                  "OPE_LEVEL":"手术级别",
     *                  "ICD9_NAME":"手术或操作名称",
     *                  "OPE_MAN_NAME":"主刀医师姓名",
     *                  "FRIST_ASSISTANT_NAME":"一助医师姓名",
     *                  "SECOND_ASSISTANT_NAME":"二助医师姓名",
     *                  "INCISION_GRADE_ID":"切口愈合等级",
     *                  "HOCUS_WAY_ID":"麻醉方式",
     *                  "HOCUS_MAN_NAME":"麻醉医师名称"
     *              }]
     *          },
     *      "secondary_operation":{
     *             "list": [{
     *                  "ICD9_ID1":"手术或操作ID",
     *                  "OPE_DATE":"手术或操作日期",
     *                  "OPE_LEVEL":"手术级别",
     *                  "ICD9_NAME":"手术或操作名称",
     *                  "OPE_MAN_NAME":"主刀医师姓名",
     *                  "FRIST_ASSISTANT_NAME":"一助医师姓名",
     *                  "SECOND_ASSISTANT_NAME":"二助医师姓名",
     *                  "INCISION_GRADE_ID":"切口愈合等级",
     *                  "HOCUS_WAY_ID":"麻醉方式",
     *                  "HOCUS_MAN_NAME":"麻醉医师名称"
     *              }]
     *          },
     *      "operation":{
     *          "list": [{
     *                  "id":"id",
     *                  "class":"主要手术main,其他手术other",
     *                  "type":"手术操作类别。1，治疗性操作；2，诊断性操作；3，介入治疗；4，手术",
     *                  "ICD9_ID1":"手术或操作ID",
     *                  "OPE_DATE":"手术或操作日期",
     *                  "OPE_LEVEL":"手术级别",
     *                  "ICD9_NAME":"手术或操作名称",
     *                  "OPE_MAN_NAME":"主刀医师姓名",
     *                  "FRIST_ASSISTANT_NAME":"一助医师姓名",
     *                  "SECOND_ASSISTANT_NAME":"二助医师姓名",
     *                  "INCISION_GRADE_ID":"切口愈合等级",
     *                  "HOCUS_WAY_ID":"麻醉方式",
     *                  "HOCUS_MAN_NAME":"麻醉医师名称"
     *              }]
     *      },
     *      "AEM01C":"离院方式代码",
     *      "AEM03C":"是否有出院31日内再住院计划",
     *      "AEM04":"31日内再住院目的",
     *      "AEJ01":"颅脑损伤患者入院前昏迷时间（天）",
     *      "AEJ02":"颅脑损伤患者入院前昏迷时间（小时）",
     *      "AEJ03":"颅脑损伤患者入院前昏迷时间（分钟）",
     *      "AEJ04":"颅脑损伤患者入院后昏迷时间（天）",
     *      "AEJ05":"颅脑损伤患者入院后昏迷时间（小时）",
     *      "AEJ06":"颅脑损伤患者入院后昏迷时间（分钟）",
     *      "ADA0101":"自付金额",
     *      "D11":"一般医疗服务费",
     *      "D12":"一般治疗操作费",
     *      "D13":"护理费",
     *      "D13":"护理费",
     *      "D14":"综合医疗服务类其他费用",
     *      "D15":"病理诊断费",
     *      "D16":"实验室诊断费",
     *      "D17":"影像学诊断费",
     *      "D18":"临床诊断项目费",
     *      "D19":"非手术治疗项目费",
     *      "D19X01":"其中:临床物理治疗费",
     *      "D20":"手术治疗费",
     *      "D20X01":"其中：麻醉费",
     *      "D20X02":"其中：手术费",
     *      "D21":"康复费",
     *      "D22":"中医治疗费",
     *      "D23":"西药费",
     *      "D23X01":"其中：抗菌药物费",
     *      "D24":"中成药费",
     *      "D25":"中草药费",
     *      "D26":"血费",
     *      "D27":"白蛋白类制品费",
     *      "D28":"球蛋白类制品费",
     *      "D29":"凝血因子类制品费",
     *      "D30":"细胞因子类制品费",
     *      "D31":"检查用一次性医用材料费",
     *      "D32":"治疗用一次性医用材料费",
     *      "D33":"手术用一次性医用材料费",
     *      "D34":"其他费",
     *      "IS_MAIN_WAY":"重症监护室代码",
     *      "IN_TIME":"监护室进入日期时间",
     *      "OUT_TIME":"监护室退出日期时间",
     *      "AEL01":"呼吸机时间",
     *      "error":{
     *          "list":[{
     *              "down":"扣分",
     *              "desc":"提示",
     *              "error_field":"缺陷字段",
     *              "error_name":"缺陷字段名称"
     *          }]
     *      }
     *  },
     *  "time":123787842
     * }
     */
    public function home(Request $request)
    {
        $id = $request->post('id');

        if (empty($id)) {
            return ToolsService::returnData(400, [], '病案号不可以为空！');
        }

        $data = MedicalRecordService::getData($id);
        if (!$data) {
            return ToolsService::returnData(200, [], '没有数据！');
        }

        try {

            $zy_brry = ZY_BRRY::query()->where("ZYH", "=", $id)->first();
            if($zy_brry){
                $zy_brry = $zy_brry->toArray();
                $data["CH"] = $zy_brry["CH"];
            }
            $code = 200;
            /*
             * 添加Key：AAB01
             * 2023-02-23 gao
             * 原代码：
             *   $data = ToolsService::codeTransformationInfo(['AAA23C','ABF02C','AEB02C','AEI01C','AAA26C','AAA02C','AAA06C','AAA08C','AAA18C','AAB06C','AEM01C','AEM03C','AEG01C','AEG02C','AAA05C','AAB02C','AAC02C'],$data);
             */

            $data = ToolsService::codeTransformationInfo(['ABF02C', 'AEB02C', 'AEI01C', 'AAA26C', 'AAA02C', 'AAA06C', 'AAA08C', 'AAA18C', 'AAB06C', 'AEM01C', 'AEG01C', 'AEG02C', 'AAA05C'], $data);

            if (count($data['operation'])) {
                foreach ($data['operation'] as $k => $v) {
                    if (!empty($v['INCISION_GRADE_MC'])) $data['operation'][$k]['INCISION_GRADE_ID'] = $v['INCISION_GRADE_MC'];
                    if (null != config('dictionaries.INCISION_GRADE_ID.' . $v['INCISION_GRADE_ID'])) {
                        $data['operation'][$k]['INCISION_GRADE_ID'] = config('dictionaries.INCISION_GRADE_ID.' . $v['INCISION_GRADE_ID']);
                    }
                    if (null != config('dictionaries.HOCUS_WAY_ID.' . $v['HOCUS_WAY_ID'])) {
                        $data['operation'][$k]['HOCUS_WAY_ID'] = config('dictionaries.HOCUS_WAY_ID.' . $v['HOCUS_WAY_ID']);
                    }
                }
            }
            if (count($data['diagnosis'])) {
                foreach ($data['diagnosis'] as $k => $v) {
                    if (null != config('dictionaries.IN_STATUS.' . $v['RYQK'])) {
                        $data['diagnosis'][$k]['RYQK'] = config('dictionaries.IN_STATUS.' . $v['RYQK']);
                    }
                }
            }
            if (!empty($data['AAC03']) && null != config('dictionaries.ABAS02.' . $data['AAC03'])) {
                $data['AAC03'] = config('dictionaries.ABAS02.' . $data['AAC03']);
            }
            /*
            $data['RJSS'] = '';
            if (isset($data['operation'][0]['RJSS'])) {
                $data['RJSS'] = $data['operation'][0]['RJSS'];
            }
           */
            $data['AAA03'] = substr($data['AAA03'], 0, 10);
            if (strpos($data['AAB01'], 'T')) {
                $data['AAB01'] = str_replace('T', ' ', $data['AAB01']);
            }
            if (strpos($data['AAC01'], 'T')) {
                $data['AAC01'] = str_replace('T', ' ', $data['AAC01']);
            }
            $data['AEB02C'] = '1、' . ($data['AEB02C'] ?? '无') . ', 2、' . ($data['AEB01'] ?? '');
            if (!empty($data['AEE01_CODE'])) {
                $data['AEE01_CODE'] = QualityService::getStaffInfo($data['AEE01_CODE'], 'base_code');
            }
            if (!empty($data['AEE02_CODE'])) {
                $data['AEE02_CODE'] = QualityService::getStaffInfo($data['AEE02_CODE'], 'base_code');
            }
            if (!empty($data['AEE03_CODE'])) {
                $data['AEE03_CODE'] = QualityService::getStaffInfo($data['AEE03_CODE'], 'base_code');
            }
            if (!empty($data['AEE04_CODE'])) {
                $data['AEE04_CODE'] = QualityService::getStaffInfo($data['AEE04_CODE'], 'base_code');
            }
            if (!empty($data['ZRHSBM'])) {
                $data['ZRHSBM'] = QualityService::getStaffInfo($data['ZRHSBM'], 'base_code');
            }

            // 查询医生签名、创建时间、修改时间、完成时间
            $data['CJSJ'] = '';
            $data['ZXSJ'] = '';
            $data['WCSJ'] = '';
            $data['doctor_name'] = '';
            $bl01NewInfo = EMR_BL_BL01::query()
                ->where('JZHM', '=', $id)
                ->where('BLLB', '=', 2000001)
                ->where('BLZT', '!=', 9)
                ->first();
            if ($bl01NewInfo) {
                $data['CJSJ'] = $bl01NewInfo->CJSJ;
                $data['ZXSJ'] = $bl01NewInfo->first_blsy_time;
                $data['WCSJ'] = $bl01NewInfo->WCSJ;

                $SYYS = EMR_BL_BLSY::query()->where('BLBH', '=', $bl01NewInfo->BLBH)->pluck('SYYS')->toArray();
                if ($SYYS) {
                    $staffList = Staff::query()->whereIn('code', $SYYS)->get(['name', 'ygjb_text'])->toArray();
                    $nameList = [];
                    foreach ($staffList as $staffInfo) {
                        $nameList[] = $staffInfo['name'] . "（" . $staffInfo['ygjb_text'] . "）";
                    }
                    $data['doctor_name'] = implode('、', $nameList);
                }
            }

            $code = 200;
            $msg = 'ok';
            $data = dataDesensitize($data); // 数据脱敏
        } catch (\Exception $e) {
            $code = 4002;
            $msg = $e->getMessage();
        }

        //替换字段

        $data = $this->fieldEdit($data);
        return ToolsService::returnData($code, $data, $msg);
    }

    /**
     * editHome
     * 病案首页编辑
     *
     * @response {
     *  "code":200,
     *    "msg":"结果",
     *  "data":{
     * }
     * }
     */
    public function editHome(Request $request)
    {

        $all = $request->all();
        if (!isset($all['AAA28'])) {
            return ToolsService::returnData(400, [], '病案号不可以为空！');
        }

        $result = MedicalRecordService::editData($all);

        $results = json_decode($result, true);
        if ($results['code'] == 200) {
            $code = 200;
            $msg = 'ok';
        } else {
            $code = 0;
            $msg = $results['msg'];
        }
        $beanstalkd = Pheanstalk::create(env('BEANSTALKD') ?? 'localhost');
        //$beanstalkd->useTube('save')->put(json_encode(['AAA28' => $all['MED_REC_ID']]),0,3);
        //$beanstalkd->useTube('patient_follow')->put(json_encode(['AAA28' => $all['MED_REC_ID']]));
        $beanstalkd->useTube('validate')->put(json_encode(['AAA28' => $all['MED_REC_ID']]));
        //$beanstalkd->useTube('count')->put(json_encode(['AAA28' => $all['MED_REC_ID']]));
        return ToolsService::returnData($code, $results, $msg);

    }

    private function fieldEdit($data)
    {
        $replaceMap = [
            "AAB11N" => "BFRY",
            //  "AAC11N" => "AAC03",
            "AAD01C" => "ZKKB",
            "AEB01" => "GMYW",
            "AED04" => "ZKRQ",
            // "D11" => "YBYLFWF",
            //"D12" => "YBZLCZF",
            //"D13" => "HLF",
            //"D14" => "ZHYLFWLQTFY",
            //"D15" => "ZHYLFWLQTFY",
            // "D16" => "BLZDF",
            // "D17" => "SYSZDF",
            //  "D18" => "YXXZDF",
            //  "D19" => "LCZDXMF",
            //  "D19X01" => "LCWLZLF",
            //   "D20" => "SSZLF",
            //  "D20X01" => "MZF",
            //   "D20X02" => "SSF",
            //   "D21" => "KFF",
            //   "D22" => "ZYZLF",
            //  "D23" => "XYF",
            //  "D23X01" => "KJYWF",
            //   "D24" => "ZCHENGYF",
            //  "D25" => "ZCAOYF",
            // "D26" => "XF",
            // "D27" => "BDBLZPF",
            // "D28" => "QDBLZPF",
            //  "D29" => "NXYZLZPF",
            //  "D30" => "XBYZLZPF",
            //   "D31" => "JCYYCXYYCLF",
            //   "D32" => "ZLYYCXYYCLF",
            //   "D33" => "SSYYCXYYCLF",
            //   "D34" => "QTF",
        ];
        if (isset($data['diagnosis_list'])) {
            $data['diagnosis'] = $data['diagnosis_list'];
        }
        // 遍历数组并替换下标
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $replaceMap)) {
                $newKey = $replaceMap[$key];
                unset($data[$key]); // 删除旧下标
                $data[$newKey] = $value; // 设置新下标和对应的值
            }
        }
        return $data;
    }
}
