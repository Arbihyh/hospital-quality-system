<?php

/**
 * 科室质控详页面情
 */

namespace App\Http\Controllers\Api\Artificial\Department;

use App\Http\Controllers\Controller;
use App\Model\CaseQuality;
use App\Model\CaseQualityDoctor;
use App\Model\CaseQualityZm;
use App\Model\CaseRule;
use App\Model\Department;
use App\Model\EMR_BL_BL01;
use App\Model\ErrorRule;
use App\Model\ErrorV2;
use App\Model\HomeQuality;
use App\Model\PatientDoctorInfo;
use App\Model\PatientInfo;
use App\Model\QualitySendMsgLog;
use App\Model\RuleWordMap;
use App\Model\Staff;
use App\Model\ZY_BRRY;
use App\Services\CaseService;
use App\Services\QualityWechatWorkNotificationService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use mysql_xdevapi\Exception;


class DetailController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getZkInfo()
    {
        $sy_zk = ErrorRule::query()->where('is_artificial', 2)->get([
            'id as value',
            'desc as label',
            'down',
            'error_type',
            'level',
            'type'
        ])
            ->toArray();
        $bl_zk = CaseRule::query()->where('is_ai', 2)->get(['id as value', 'notice as label', 'score', 'level', 'category'])
            ->toArray();
        $data = [];
        $data[0]['label'] = '首页质控规则';
        $data[0]['value'] = '1';
        $data[0]['children'] = $sy_zk;
        $data[1]['label'] = "病历质控规则";
        $data[1]['value'] = "2";
        $data[1]['children'] = $bl_zk;
        return ToolsService::returnData(200, $data);
    }


    public function addRule(Request $request)
    {
        //region 判断数据===

        $data = [];
        $zk_type = $request->post('zk_type');
        if ($zk_type != 1 && $zk_type != 2) return ToolsService::returnData(1, [], '请选择正确的质控规则类型');
        $currentTime = date("Y-m-d H:i:s");

        //region 如果是首页质控
        if ($zk_type == 1) {
            //质控规则名称
            $data['field'] = '病案首页';
            $data['desc'] = $request->post('field', '');
            if (!$data['desc']) return ToolsService::returnData(1, [], '请填写质控描述');
            if (empty($data['desc'])) return ToolsService::returnData(1, [], '质控规则名称不能为空');
            if (!empty(ErrorRule::query()->where('desc', $data['desc'])->first())) return ToolsService::returnData(1, [], '此条质控规则已经存在');
            //错误扣分
            $data['down'] = $request->post('down', '');
            if (empty($data['down'])) return ToolsService::returnData(1, [], '请填写正确的错误扣分');
            //错误级别
            $data['level'] = $request->post('level', '');
            if (!is_numeric($data['level'])) return ToolsService::returnData(1, [], '请选择正确的错误级别');
            //如果level不为空-1
            if ($data['level'] != '') {
                $data['level'] = $data['level'] - 1;
            }
            //缺陷分类
            $data['type'] = $request->post('type', '');
            if (!is_numeric($data['type'])) return ToolsService::returnData(1, [], '请选择正确的缺陷分类');
            if ($data['type'] != '') {
                $data['type'] = $data['type'] - 1;
            }
            //缺陷类型
            $data['error_type'] = $request->post('error_type', '');
            if (!is_numeric($data['error_type'])) return ToolsService::returnData(1, [], '请选择正确的错误类型');
            if ($data['error_type'] != '') {
                $data['error_type'] = $data['error_type'] - 1;
            }
            //缺陷类别
            $data['category'] = $request->post('category');
            if (!is_numeric($data['category'])) return ToolsService::returnData(1, [], '请选择正确的缺陷类别');
            if ($data['category'] != '') {
                $data['category'] = $data['category'] - 1;
            }

            $data['auth'] = 'is_artificial';
            $data['created_at'] = $currentTime;
            $data['updated_at'] = $currentTime;
            $data['is_artificial'] = 2;
            if (!ErrorRule::query()->insert($data)) return ToolsService::returnData(1, [], '添加质控规则失败');
        }
        //endregion

        //region 如果是病历质控===
        if ($zk_type == 2) {
            //质控名称
            $data['title'] = '人工质控';
            //错误描述
            $data['notice'] = $request->post('field', "") or "";
            if (!$data['notice']) return ToolsService::returnData(1, [], '请填写质控描述');
            //if (empty($data['title'])) return ToolsService::returnData(1, [], '请填写正确的质控名称');
            if (!empty(CaseRule::query()->where('notice', $data['notice'])->first())) return ToolsService::returnData(1, [], '此条质控规则已经存在');
            //错误扣分
            $data['score'] = $request->post('down', '');
            if ($data['score'] < 0.5 || $data['score'] > 100) return ToolsService::returnData(1, [], '错误扣分不在0.5-100之间');
            //错误级别
            $data['level'] = $request->post('level');
            if (!is_numeric($data['level'])) return ToolsService::returnData(1, [], '请选择错误级别');

            

            $data['category'] = "病历文书";
            $data['type'] = "内涵性";
            $data['is_ai'] = 2;
            //$data['is_shizhong'] = 1;
            $data['created_at'] = $currentTime;
            $data['updated_at'] = $currentTime;
            if (!CaseRule::query()->insert($data)) return ToolsService::returnData(1, [], '添加病历质控规则失败');
        }
        //endregion

        return ToolsService::returnData(200, [], '添加质控规则成功');
    }

    /**
     * @param Request $request
     * @return array
     */
    public function addQualityControlInfo(Request $request)
    {
        //region 判断数据===
        $data = [];
        //住院号
        $MED_REC_ID = $request->post('MED_REC_ID');
        $BLBH = $request->post('blbh', '');
        if (empty($MED_REC_ID)) return ToolsService::returnData(1, [], '住院号不能为空');
        $patientRow = PatientInfo::query()->where('MED_REC_ID', $MED_REC_ID)->first();
        if (empty($patientRow)) return ToolsService::returnData(1, [], '此住院号不存在');

        //质控规则
        $ruleArray = $request->post('ruleArray');
        if (empty($ruleArray)) return ToolsService::returnData(1, [], '请选择正确的质控规则');
        $rule_type = $ruleArray[0];

        if ($rule_type != 1 && $rule_type != 2) return ToolsService::returnData(1, [], '请选择正确的质控规则');


        $rule_id = $ruleArray[1];
        if (!is_numeric($rule_id)) return ToolsService::returnData(1, [], '请选择正确的质控规则');

        //如果是首页质控规则
        if ($rule_type == 1) {
            $ruleRow = ErrorRule::query()->where('is_artificial', 2)->where('id', $rule_id)->first();
            if (empty($ruleRow)) return ToolsService::returnData(1, [], '此质控规则不存在');
        }

        //如果是病历质控规则
        if ($rule_type == 2) {
            $ruleRow = CaseRule::query()->where('is_ai', 2)->where('id', $rule_id)->first();
            if (empty($ruleRow)) return ToolsService::returnData(1, [], '此质控规则不存在');
        }

        //接收端
        $data['cate'] = $request->post('cate');
        if (empty($data['cate'])) return ToolsService::returnData(1, [], '请选择正确的接收端');

        //接收科室
        $data['JSKS'] = $request->post('JSKS');
        if (empty($data['JSKS'])) return ToolsService::returnData(1, [], '请选择正确的接收科室');

        //接收人
        $data['JSR'] = $request->post('JSR', '');
        if (empty($data['JSR'])) {
            $data['JSR'] = '101';
        }

        //整改天数
        $data['correction_date'] = $request->post('correction_date', 7);

        //整改依据
        $data['basis'] = $request->post('basis');
        if (empty($data['basis'])) return ToolsService::returnData(1, [], '请填写质控依据');
        $basis = $data['basis'];

        //整改说明
        $data['notice'] = $request->post('notice');
        if (empty($data['notice'])) return ToolsService::returnData(1, [], '请填写质控内容');

        // 质控人
        $data['ZKR'] = $request->post('ZKR');
        if (empty($data['ZKR'])) return ToolsService::returnData(1, [], '请填写质控人');

        // 质控人工号
        $data['ZKR_CODE'] = $request->post('ZKR_CODE', '');
        if (empty($data['ZKR_CODE'])) return ToolsService::returnData(1, [], '请填写质控人工号');

        // 质控目录
        $data['error_field'] = $request->post('error_field');
        if (empty($data['error_field'])) return ToolsService::returnData(1, [], '请选择质控目录');
        //endregion

        $currentTime = date("Y-m-d H:i:s"); //当前时间
        // 编目首页
        $patientDoctorRow = PatientDoctorInfo::query()->where('AAA28', $patientRow['MED_REC_ID'])->first();
        if ($data['cate'] == 3) {
            //插入数据
            $data['basis'] = json_encode([$data['basis']], 256);
            $data['AAA28'] = $patientRow['AAA28'];
            $data['ZYH'] = $patientRow['MED_REC_ID'];
            $data['AAC01'] = $patientRow['AAC01'];
            $data['AAC02C'] = $patientRow['AAC02C'];
            $data['YQ_CODE'] = $patientRow['YQ_CODE'];
            $data['AAC11C'] = $patientRow['BQ_CODE'];
            $data['in_hospital'] = $patientRow['in_hospital'];
            $data['is_CATA'] = $patientRow['IS_CATA'];
            $data['AEE01_CODE'] = $patientDoctorRow['AEE01_CODE'] ?? "";
            $data['AEE02_CODE'] = $patientDoctorRow['AEE02_CODE'] ?? "";
            $data['AEE03_CODE'] = $patientDoctorRow['AEE03_CODE'] ?? "";
            $data['AEE04_CODE'] = $patientDoctorRow['AEE04_CODE'] ?? "";
            $data['error_rule'] = $ruleRow['id'];
            $data['is_artificial'] = 1;
            $data['created_at'] = $currentTime;
            $data['updated_at'] = $currentTime;
            $data['submit_user'] = $request->user()['id'];
            $lastId = HomeQuality::query()->insertGetId($data);

            $title = "编目首页整改通知提醒";
        } // 运行首页
        elseif ($data['cate'] == 2) {

            $department = Department::query()->get()->toArray();
            $department = array_column($department, 'dep_name', 'dep_id');

            $staff = Staff::query()->get()->toArray();
            $staff = array_column($staff, 'name', 'code');
            //插入数据
            $data['AAA01'] = $patientRow['AAA01'];
            $data['AAA28'] = $patientRow['AAA28'];
            $data['ZYH'] = $patientRow['MED_REC_ID'];
            $data['AAB01'] = $patientRow['AAB01'];
            $data['AAC01'] = $patientRow['AAC01'];
            $data['error_rule'] = $ruleRow['id'];
            $data['desc'] = $ruleRow['desc'];
            $data['coder_id'] = $patientRow['AEE08_CODE'];
            $data['CYKSBM'] = $patientRow['AAC02C'];
            $data['coder_name'] = $staff[$patientRow['AEE08_CODE']] ?? "";
            $data['coder_name'] = $department[$patientRow['AAC02C']] ?? "";
            $data['ZZYS_BH'] = $patientDoctorRow['AEE03_CODE'] ?? "";
            $data['ZZYS_BH'] = $patientDoctorRow['AEE03'] ?? "";
            $data['ZYYS_BH'] = $patientDoctorRow['AEE04'] ?? "";
            $data['ZYYS'] = $patientDoctorRow['AEE04_CODE'] ?? "";
            $data['ICD10_NAME'] = $patientRow['ICD10_NAME'];
            $data['ICD9_NAME'] = $patientRow['ICD9_NAME'];
            $data['is_artificial'] = 1;
            $data['created_at'] = $currentTime;
            $data['updated_at'] = $currentTime;
            $data['submit_user'] = $request->user()['id'];
            $lastId = ErrorV2::query()->insertGetId($data);

            $title = "运行首页整改通知提醒";
        } // 运行病例
        elseif ($data['cate'] == 1) {
            $blmc = '';
            if ($BLBH) {
                $bl01 = EMR_BL_BL01::query()->where("BLBH", $BLBH)->first();
                $blmc = $bl01['BLMC'];
            }
            //$res = CaseQuality::query()->where(["rule_id" => $ruleRow['id'], "JZHM" => $patientRow['MED_REC_ID']])->get()->toArray();
            $data['basis'] = "【" . $blmc . "】\n" . "\r\n" . "" . $data['basis'] . "";
            /* if (!empty($res)) {
                //return ToolsService::returnData(1, [], '该规则的质控结果已存在，无法重复添加');
                //获取存在的质控的basis,示例：[["住院号：1234567890，住院时间：2025-01-01 10:00:00，住院科室：内科"]]，把现在的$data['basis']合并进去
                $exist_basis = json_decode($res[0]['basis'], true);
                $exist_basis[] = [$data['basis']];
                $data['basis'] = json_encode($exist_basis, 256);
            }else{ */
            $data['basis'] = json_encode([[$data['basis']]], 256);
            //}
            $data['JZHM'] = $patientRow['MED_REC_ID']; //就诊号码
            $data['rule_id'] = $ruleRow['id']; //规则id
            $data['created_at'] = $currentTime; //创建时间
            $data['is_artificial'] = 1; //是否人工质控
            $data['AAC02C'] = $patientRow['AAC02C']; //出院科室
            $data['YQ_CODE'] = $patientRow['YQ_CODE']; //出院病区
            $data['BQ_CODE'] = $patientRow['BQ_CODE']; //出院病区
            $data['submit_user'] = $request->user()['id'];

            //如果res不为空，则更新
            /* if (!empty($res)) {
                CaseQuality::query()->where(["rule_id" => $ruleRow['id'], "JZHM" => $patientRow['MED_REC_ID']])->update($data);
                $lastId = $res[0]['id'];
                CaseQualityZm::query()->where(["rule_id" => $ruleRow['id'], "JZHM" => $patientRow['MED_REC_ID']])->update(['basis' => $data['basis']]);
            }else{ */
            $lastId = CaseQuality::query()->insertGetId($data);
            //CaseQualityZm::query()->insertGetId($data);
            //}


            //$blbh = $request->post('blbh');
            if ($BLBH) {
                $data['AAA28'] = $patientRow['AAA28'];
                Log::info('人工质控AAA28:' . $data['AAA28']);
                CaseQualityDoctor::addData($BLBH, $data);
            }

            $title = "运行病例整改通知提醒";
        }
        $jsr = array_values(array_filter(array_map('trim', explode(",", $data['JSR']))));
        if (empty($jsr)) {
            $jsr = ['101'];
        }

        $wechatWorkReceivers = $jsr;
        $fsr = $request->post('fsr', $request->post('FSR', ''));
        if (!empty($fsr)) {
            $fsrList = str_replace('，', ',', $fsr);
            $fsrList = array_values(array_filter(array_map('trim', explode(',', $fsrList))));
            if (!empty($fsrList)) {
                $fsrWechatIds = Staff::query()
                    ->whereIn('code', $fsrList)
                    ->whereNotNull('wechat_id')
                    ->pluck('wechat_id')
                    ->toArray();
                $wechatWorkReceivers = array_merge($wechatWorkReceivers, $fsrWechatIds);
            }
        }

        $wechatAdminIds = RuleWordMap::query()->where('name', 'wechat_admin_id')->value('keyword');
        if (!empty($wechatAdminIds)) {
            $wechatAdminIds = str_replace('，', ',', $wechatAdminIds);
            $wechatAdminIds = array_filter(array_map('trim', explode(',', $wechatAdminIds)));
            $wechatWorkReceivers = array_merge($wechatWorkReceivers, $wechatAdminIds);
        }
        $wechatWorkReceivers = array_values(array_unique(array_filter($wechatWorkReceivers)));
        if (empty($wechatWorkReceivers)) {
            $wechatWorkReceivers = ['101'];
        }

        /* foreach ($jsr as $v) {
            QualitySendMsgLog::query()->insert(
                [
                    'zyh' => $patientRow["MED_REC_ID"], // 6位住院号
                    'AAA28' => $patientRow['AAA28'], // 8位住院号
                    'rule_id' => $ruleRow['id'], // 质控规则的ID
                    'content' => $basis, // 发送的钉钉信息内容
                    'quality_content' => $data['notice'], // 发送的钉钉信息内容
                    'status' => 2, // 整改状态、未整改
                    'is_warning_msg' => 2, // 整改状态、未整改
                    'doctor_id' => $v,
                    'title' => $title,
                    'AAC11N' => $patientRow['AAC02C'] ?? "", // 病人所属科室
                    'msg_yj' => $data['basis'], // 质控依据
                    'created_at' => time(),
                    'case_quality_id' => $lastId,
                    'quality_type' => $data['cate'],
                ]
            );
            CaseService::sendMsg($v, $data["basis"], $data['cate'], $title);
        } */

        if (env('APP_NAME') == 'hlw') {
            $wechatWorkQuestion = $data['notice'];
            if ((int) $data['cate'] === 1) {
                $wechatWorkQuestion = $ruleRow['notice'] ?? $wechatWorkQuestion;
            } elseif (in_array((int) $data['cate'], [2, 3], true)) {
                $wechatWorkQuestion = $ruleRow['desc'] ?? $wechatWorkQuestion;
            }

            $brryRow = ZY_BRRY::query()->where('ZYH', $patientRow['MED_REC_ID'])->first();
            $wechatWorkDepartment = '';
            if (!empty($brryRow) && !empty($brryRow['BRKS'])) {
                $wechatWorkDepartment = Department::query()
                    ->where('dep_id', $brryRow['BRKS'])
                    ->value('dep_name') ?: '';
            }
            $wechatWorkCaseNo = $brryRow['AAA28'] ?? '';
            $wechatWorkPatientName = $brryRow['BRXM'] ?? '';
            $wechatWorkBedNo = $brryRow['CH'] ?? '';

            try {
                (new QualityWechatWorkNotificationService())->pushCustomMessage(
                    '人工质控整改通知',
                    "尊敬的" . $wechatWorkDepartment . ":\n您负责的病历" . $wechatWorkCaseNo . "(" . $wechatWorkPatientName . "," . $wechatWorkBedNo . "),存在问题：" . $wechatWorkQuestion."。请及时处理，谢谢！\n  祝您工作顺利!",
                    $wechatWorkReceivers,
                    []
                );
            } catch (\Exception $e) {
                Log::warning('[企业微信] 人工质控整改通知发送失败', [
                    'error' => $e->getMessage(),
                    'jsr' => $wechatWorkReceivers,
                    'cate' => $data['cate'],
                    'rule_id' => $ruleRow['id'],
                ]);
            }
        }

        if (!$lastId) return ToolsService::returnData(1, [], '发送整改失败');
        return ToolsService::returnData(200, [], '发送整改成功');
    }
}
