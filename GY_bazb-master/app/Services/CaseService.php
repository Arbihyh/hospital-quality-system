<?php

namespace App\Services;

use App\Model\DataSyncLog;
use DateTime;
use Exception;
use App\Model\Yzb;
use Carbon\Carbon;
use App\Model\Mzjl;
use App\Model\SSSQ;
use App\Model\Bllb1;
use App\Model\Bllb34Jjjctys;
use App\Model\Bllb34Sjgzs;
use App\Model\Bllb34Ybwffgzs;
use App\Model\Bllb34Zdcygzs;
use App\Model\Bllb329Bdqp;
use App\Model\Bllb329Bwbztys;
use App\Model\Bllb329Ctzq;
use App\Model\Bllb329Hltys;
use App\Model\Bllb329Sszqtys;
use App\Model\Bllb329Sxzqtys;
use App\Model\Bllb329Sqwts;
use App\Model\Bllb329Tszl;
use App\Model\Bllb329Yzccstys;
use App\Model\Bllb329Zrltys;
use App\Model\Staff;
use App\Model\Appeal;
use App\Model\Bllb292;
use App\Model\Bllb303;
use App\Model\GY_BLLM;
use App\Model\ErrorV2;
use App\Model\Setting;
use App\Model\ZY_BRRY;
use App\Elastic\Zyhcmx;
use App\Model\CaseRule;
use App\Model\ErrorRule;
use App\Model\TableDict;
use App\Model\Zg_doctor;
use App\Model\BA_RECEIVE;
use App\Model\Department;
use App\Model\CaseQuality;
use App\Model\CaseQualityShizhongRecord;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use App\Model\EMR_BL_BLXG;
use App\Model\HomeQuality;
use App\Model\PatientInfo;
use App\Model\RuleSetting;
use App\Model\RuleWordMap;
use \App\Services\HomeData;
use App\Model\BigModelList;
use Illuminate\Support\Arr;
use App\Model\CaseQualityV2;
use App\Model\CaseQualityZm;
use App\Model\MainOperation;
use App\Model\PatientInfoV2;
use App\Model\EMR_BL_BL01_NEW;
use App\Model\V_JMGS_YMresult;
use App\Services\RadioService;
use App\Model\BigModelTemplate;
use App\Model\CaseQualityCount;
use App\Services\RuyuanService;
use \App\Services\EsSaveService;
use App\Model\CaseQualityDoctor;
use App\Model\PatientDoctorInfo;
use App\Model\PatientInfoTarget;
use App\Model\QualitySendMsgLog;
use App\Model\RuleSettingDetail;
use App\Model\BA_MR_CLASS_NUMBER;
use App\Model\CaseQualityHistory;
use App\Model\PatientHospitalInfo;
use function GuzzleHttp\Psr7\str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use App\Model\PatientInfoTargetTemporary;
use App\Services\QualityControl\ZkzkService;
use App\Http\Controllers\Api\BigModelQualityController;
use App\Model\Bllb288;
use App\Model\Bllb294_295;
use App\Model\Bllb294_45;
use App\Model\Bllb303_303;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;

/**
 * 病例分析
 */
class CaseService
{
    const ID = 4257465;
    public $caseRule = [];

    public $ruleSetting = [];
    public $yzzt = [0, 1, 5];
    public $diffHoure = 2;
    public $appealRuleIds = [];
    public $is_sz = 0; // 是否是事中质控
    public $ygjb = ["副主任护师", "副主任检验师", "副主任技师", "副主任医师", "主任医师", "主任护士", "主任检验师", "主治医师", "主管技师", "主管护师", "主管检验师", "主管药师", "医师", "实习医生", "技师", "护士", "护士长", "护师", "检验师", "药师"];


    public function __construct()
    {

        $setting = Setting::query()->where('name', '=', 'diff_houre')->get()->toArray();
        $this->diffHoure = (int)$setting[0]['content'];
        //所有添加的质控规则
        $caseRule = CaseRule::query()->where("status", 1)->get()->toArray();
        $this->caseRule = array_column($caseRule, null, 'id');

        $ruleSetting = RuleSetting::query()->get()->toArray();
        $this->ruleSetting = array_column($ruleSetting, null, 'id');
    }

    /**
     * @param string $defectContent
     * @param string $appealDocter
     * @param int $id 质控结果的数据ID
     * @param int $zyh 住院号
     * @param int $type 1忽略，2申诉
     * @return array
     * 申诉
     */
    public function appeal($id = 0, $zyh = 0, $type = 0, $qualityType = 0, $defectContent = "", $appealDocter = "")
    {
        if ($type == 2 && $defectContent == '') {
            return ToolsService::returnData(4001, [], '请填写申诉理由');
        }
        $data = ['error_id' => $id, 'ZYH' => $zyh, 'type' => $type, 'quality_type' => $qualityType];
        $has = Appeal::query()->where($data)->whereIn('status', [0, 1,2,3])->count();
        if ($has && $type == 2) {
            return ToolsService::returnData(4001, [], '无法重复提交');
        }
        // 判断是否为忽略操作，如果是忽略操作，则判断规则是否为人工质控，如果是，则禁止忽略
        $isArtificial = 0;
        if ($qualityType == 2) {
            if ($id > 1000000) {
                $rule = RuleSetting::query()->where('id', '=', $id - 1000000)->first()->toArray();
                $isArtificial = 1;
            } else {
                $rule = CaseRule::query()->where('id', '=', $id)->first()->toArray();
                $isArtificial = $rule["is_ai"];
            }
        } else {
            if ($id > 1000000) {
                $rule = RuleSetting::query()->where('id', '=', $id - 1000000)->first()->toArray();
                $isArtificial = 1;
            } else {
                $rule = ErrorRule::query()->where('id', '=', $id)->first()->toArray();
                $isArtificial = $rule["is_artificial"];
            }
            /* $rule = ErrorRule::query()->where('id', '=', $id)->first()->toArray();
            $isArtificial = $rule["is_artificial"]; */
        }
        if ($type == 1 && $isArtificial == 2) {
            return ToolsService::returnData(4001, [], '人工质控禁止忽略');
        }

        if ($has >= 3 && $type == 1) {
            return ToolsService::returnData(4001, [], '同一质控结果只能忽略三次');
        }
        $patientInfo = PatientInfo::query()->where('MED_REC_ID', '=', $zyh)->get()->toArray();
        $data['defect_content'] = $defectContent ?: '';
        $data['appeal_docter'] = $appealDocter;
        $data['AAA28'] = $patientInfo[0]['AAA28'];
        $data['AAB01'] = $patientInfo[0]['AAB01'];
        $data['appeal_time'] = time();
        $appeal_id = Appeal::query()->insertGetId($data);
        // 1是忽略、2是申诉
        if ($type == 1) {
            $update = ["is_ignore" => 1, 'appeal_id' => $appeal_id];
        } else {
            $update = ['appeal_id' => $appeal_id];
        }
        if ($qualityType == 2) {
            CaseQuality::query()->where('rule_id', '=', $id)->where('JZHM', $zyh)->update($update);
        } // 首页质控申诉
        elseif ($qualityType == 1) {
            ErrorV2::query()->where('error_rule', '=', $id)->where('ZYH', $zyh)->update($update);
        } // 首页质控申诉
        elseif ($qualityType == 3) {
            HomeQuality::query()->where('error_rule', '=', $id)->where('ZYH', $zyh)->update($update);
        }
        if (env('APP_NAME') == 'hlw') {
            try {
                $this->sendAppealWechatWorkNotification(
                    $type,
                    $zyh,
                    $rule ?? [],
                    $defectContent,
                    $has + 1
                );
            } catch (\Exception $e) {
                Log::warning('[企业微信] 申诉忽略通知发送失败', [
                    'error' => $e->getMessage(),
                    'zyh' => $zyh,
                    'rule_id' => $id,
                    'type' => $type,
                    'quality_type' => $qualityType,
                ]);
            }
        }
        return ToolsService::returnData(200, [], '提交成功');
    }

    /**
     * 发送申诉/忽略企业微信通知。
     */
    protected function sendAppealWechatWorkNotification($type, $zyh, array $rule, $defectContent = '', $ignoreTimes = 1)
    {
        $brry = ZY_BRRY::query()->where('ZYH', $zyh)->first();
        $caseNo = $brry['AAA28'] ?? '';
        $patientName = $brry['BRXM'] ?? '';
        $bedNo = $brry['CH'] ?? '';
        $ruleTitle = $this->getAppealWechatRuleTitle($rule, $defectContent);
        $wechatWorkService = new QualityWechatWorkNotificationService();

        if ((int)$type === 2) {
            $adminIds = $this->parseWechatWorkReceiverList(
                RuleWordMap::query()->where('name', 'wechat_admin_id')->value('keyword')
            );

            foreach ($adminIds as $adminId) {
                $staff = Staff::query()
                    ->where('code', $adminId)
                    ->orWhere('wechat_id', $adminId)
                    ->first();
                $receiver = !empty($staff['wechat_id']) ? $staff['wechat_id'] : $adminId;
                $departmentName = '质管办';
                if (!empty($staff['ksdm'])) {
                    $departmentName = Department::query()
                        ->where('dep_id', $staff['ksdm'])
                        ->value('dep_name') ?: '质管办';
                }

                $message = "尊敬的" . $departmentName . ":\n"
                    . "您好！运行病历 " . $caseNo . "（" . $patientName . "，" . $bedNo . "） 存在申诉问题："
                    . $ruleTitle . "。请及时处理，谢谢！\n祝您工作顺利!";
                $wechatWorkService->pushCustomMessage('申诉问题通知', $message, [$receiver], [], '', '');
            }

            return;
        }

        if ((int)$type === 1 && !empty($brry['GCYSDM'])) {
            $staff = Staff::query()->where('code', $brry['GCYSDM'])->first();
            if (empty($staff['wechat_id'])) {
                return;
            }

            $departmentName = '';
            if (!empty($staff['ksdm'])) {
                $departmentName = Department::query()
                    ->where('dep_id', $staff['ksdm'])
                    ->value('dep_name') ?: '';
            }

            $message = "尊敬的" . $departmentName . ":\n"
                . "您好！您负责的病历 " . $caseNo . "（" . $patientName . "，" . $bedNo . "） 存在 "
                . intval($ignoreTimes) . " 次忽略问题：" . $ruleTitle . "。\n祝您工作顺利!";
            $wechatWorkService->pushCustomMessage('忽略问题通知', $message, [$staff['wechat_id']], [], '', '');
        }
    }

    /**
     * 获取申诉/忽略通知规则名称。
     */
    protected function getAppealWechatRuleTitle(array $rule, $defectContent = '')
    {
        foreach (['notice', 'desc', 'description', 'title', 'name'] as $field) {
            if (!empty($rule[$field])) {
                return $rule[$field];
            }
        }

        return $defectContent ?: '';
    }

    /**
     * 解析逗号分隔的企业微信接收人配置。
     */
    protected function parseWechatWorkReceiverList($value)
    {
        if (empty($value)) {
            return [];
        }

        $value = str_replace('，', ',', $value);
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)))));
    }

    /**
     * @param string $zyh
     * @param string $content
     * @param int $ruleId
     * @return bool
     * 消息发送
     */
    public function sendMsg($zyh = '', $content = '', $ruleId = 0, $msgYj = [], $dataId = 0, $dataType = "")
    {
        // 只有允许的规则才可以预警
        $whiteRuleId = RuleWordMap::getInfo("warning_rule_id_white");
        if (!empty($whiteRuleId) && !in_array($ruleId, $whiteRuleId)) {
            return false;
        }

        Log::info("预警消息提醒：{$content}住院号：" . $zyh);
        // 默认同一住院号同一规则只发送一次；配置为1时允许重复插入预警。
        $repeatWarningSend = RuleWordMap::query()->where('name', '=', '是否多次发送预警')->value('keyword') ?? '0';
        if (trim((string)$repeatWarningSend) !== '1') {
            $sendLogQuery = QualitySendMsgLog::query()->where(['zyh' => $zyh, 'rule_id' => $ruleId]);
            if ($dataId !== 0 && $dataId !== '') {
                $sendLogQuery->where('data_id', '=', $dataId)->where('data_type', '=', ($dataType ?: ''));
            }

            if ($sendLogQuery->exists()) {
                return false;
            }
        }

        $ddSend = RuleWordMap::query()->where('name', '=', 'dd_send_msg')->value("keyword");
        $patientInfo = PatientInfo::query()->where(['MED_REC_ID' => $zyh])->get(['AAA28', 'AAA01'])->toArray();
        $doctor = PatientDoctorInfo::query()->where(['AAA28' => $zyh])->get()->toArray();
        $patientHospitalInfo = PatientHospitalInfo::query()->where(['AAA28' => $zyh])->get(['AAB11N', 'AAA28'])->toArray();
        $AAC11N = $patientHospitalInfo && $patientHospitalInfo[0]['AAB11N'] ?: '';
        $title = $AAC11N;

        // 医生钉钉账号获取
        $doctorList = [];
        if (!empty($doctor)) {
            $doctorList[] = $doctor[0]['AEE03_CODE'] ?: '';
            $doctorList[] = $doctor[0]['AEE01_CODE'] ?: '';
            $doctorList = array_filter($doctorList);
        }


        // 钉钉消息发送
        $res = ""; // 消息推送结果值
        if ($ddSend == 1 && $doctorList) {
            if (empty($doctorList)) {
                return false;
            }
            $staff = Staff::query()->whereIn('code', $doctorList)->get()->toArray();
            $YGBH = array_column($staff, 'YGBH');

            // 添加接受所有信息的管理员ID
            $white = RuleWordMap::getInfo('钉钉消息管理员');
            $whiteDoctor = Zg_doctor::query()->whereIn('doctor_id', $white)->get()->toArray();
            $whiteNoticeAccepts = [];
            foreach ($whiteDoctor as $zg) {
                $whiteNoticeAccepts[] = ["doctorId" => $zg['doctor_id'], "doctorName" => $zg['doctor_name'], "ddId" => $zg['dd_id'], "deptName" => $zg['depart_name']];
            }

            // 过滤掉不发信息的管理员
            $block = RuleWordMap::getInfo('钉钉消息屏蔽人');
            foreach ($YGBH as $k => $item) {
                if (in_array($item, $block) === true) {
                    unset($YGBH[$k]);
                }
            }
            if ($YGBH) {
                $url = env('DING_SEND_URL');

                $zgDoctor = Zg_doctor::query()->whereIn('doctor_id', $YGBH)->get()->toArray();
                foreach ($zgDoctor as $zg) {

                    $msgList[] = ["doctorId" => $zg['doctor_id'], "doctorName" => $zg['doctor_name'], "ddId" => $zg['dd_id'], "deptName" => $zg['depart_name']];
                    $msgList = array_merge($msgList, $whiteNoticeAccepts);

                    $msg = "尊敬的[{$zg['doctor_name']}]医师：
            您好！您负责的病历{$patientInfo[0]['AAA28']}{$patientInfo[0]['AAA01']}，“" . $content . "”，" . implode(',', $msgYj) . "，请按时完成，谢谢！
            祝您工作顺利！";
                    $data = [
                        "noticeTitle" => $title,
                        "msg" => $msg,
                        "deptId" => 1,
                        "deptName" => "管理员",
                        "noticeAccepts" => $msgList
                    ];
                    $res = requestPost($url, $data);
                }
            }
        }

        $msg = $content;
        // 将内容的时间解析出来，并转换成秒
        $second = 0;
        if (strpos($content, '小时') !== false) {
            $str = str_replace('请在', '', $content);
            $str = explode('分钟', $str);
            $time = explode('小时', $str[0]);
            $second = ($time[0] ?? 0) * 3600 + ($time[1] ?? 0) * 60;
        }

        $AEE03_CODE = !empty($doctorList) && !empty($doctorList[0]) ? $doctorList[0] : '';
        $pushTimestamp = time();
        $insertMsgYj = is_array($msgYj) ? $msgYj : [$msgYj];
        $insertMsgYj[] = '预警推送时间【' . date('Y-m-d H:i:s', $pushTimestamp) . '】';
        QualitySendMsgLog::query()->insert(
            [
                'zyh' => $zyh, // 6位住院号
                'AAA28' => $patientInfo[0]['AAA28'], // 8位住院号
                'data_id' => ($dataId ?: ''), // 对应质控的数据ID，例如医嘱本的YZBXH，病程的BLBH
                'data_type' => ($dataType ?: ''), // 删除的数据类型
                'rule_id' => $ruleId, // 质控规则的ID
                'content' => $msg ?? "", // 发送的钉钉信息内容
                'quality_content' => $content ?? "", // 发送的钉钉信息内容
                'second' => $second, // 有效时间，单位秒，距离有效整改时间的倒计时
                'status' => 2, // 整改状态、未整改
                'AEE03_CODE' => $AEE03_CODE,
                'doctor_id' => $AEE03_CODE,
                //                'AEE03' => !empty($zgDoctor[0]) ? $zgDoctor[0]['doctor_name'] : '',
                'AEE01_CODE' => !empty($doctorList) && !empty($doctorList[1]) ? $doctorList[1] : '',
                //                'AEE01' => !empty($zgDoctor[1]) ? $zgDoctor[1]['doctor_name'] : '',
                'title' => $title,
                'AAC11N' => $AAC11N, // 病人所属科室
                'msg_yj' => json_encode($insertMsgYj, 256), // 质控依据
                'created_at' => $pushTimestamp,
                'result' => json_encode($res, 256),
            ]
        );
        //获取插入id
        $setting = RuleWordMap::query()->where('name', '=', 'his_send_msg')->value("keyword");
        if ($AEE03_CODE and $setting == 1) {
            $id = QualitySendMsgLog::latest('id')->value('id');
            // 消息推送
            $count = QualitySendMsgLog::query()
                ->where("doctor_id", $AEE03_CODE)
                ->where("is_read", 0)
                ->where("quality_type", 1)
                ->count();
            webSendMsg([
                "type" => "publish",
                "id" => $id,
                "title" => $title,
                "quality_type" => 1,
                "content" => $msg,
                "created_at" => $pushTimestamp,
                "msg_type" => 1, //1发送消息，2清除消息
                "count" => $count,
            ], $AEE03_CODE);
        }
    }


    /**
     * @param string $zyh
     * @param int $ruleId
     * @param int $isYouxiao
     * @return bool
     * 修改缺陷提醒状态为已整改
     */
    public function setSendLogStatus($zyh = '', $ruleId = 0, $isYouxiao = 0)
    {
        QualitySendMsgLog::query()->where(['zyh' => $zyh, 'rule_id' => $ruleId])->update(['status' => 1, 'is_youxiao' => $isYouxiao, 'updated_at' => date("Y-m-d H:i:s", time())]);
        return true;
        $res = QualitySendMsgLog::query()->where(['zyh' => $zyh, 'rule_id' => $ruleId])->get()->toArray();
        if (empty($res)) {
            return false;
        }
        $pushTime = $res[0]['created_at'];
        $second = $res[0]['second'];
        $date = time();
        $isYouxiao = 0;
        if ($pushTime + $second > $date) {
            $isYouxiao = 1;
        }


        QualitySendMsgLog::query()->updateOrInsert(
            ['zyh' => $zyh, 'rule_id' => $ruleId],
            ['status' => 1, 'is_youxiao' => $isYouxiao]
        );
        return true;

        // 如果住院号对应的规则不存在，则设置质控信息已整改
        $res = CaseQuality::query()->where(['zyh' => $zyh, 'rule_id' => $ruleId])->get()->toArray();
        if (!$res) {
            QualitySendMsgLog::query()->updateOrInsert(
                ['zyh' => $zyh, 'rule_id' => $ruleId],
                ['status' => 1]
            );
        }
    }

    /**
     * 根据配置模板对病例进行质控
     * [{"param1":["无纸化信息","住院次数"],"param2":"4","condition":"包含"}]
     */
    public function qualityV2($zyh = "")
    {
        $ruleSetting = RuleSetting::query()->where('status', '=', 1)->get()->toArray();

        foreach ($ruleSetting as $rs) {
            $detail = RuleSettingDetail::query()->where('rule_id', '=', $rs['rule_id'])->get()->toArray();
            foreach ($detail as $d) {
                $content = json_decode($d['condition_content'], true);

                foreach ($content as $c) {
                    // 库表信息
                    $param1 = $c['param1'];
                    $param1 = explode(',', $param1);
                    $table = TableDict::query()->where([
                        ['status', '=', 1],
                        ['type', '=', 1],
                        ['field_name', '=', $param1[0]],
                    ])->get()->toArray();
                    $sql = 'select * from ' . $table[0]['field'] . ' where ';
                }
            }
        }

        $patientInfo = PatientInfoTarget::query()->where('MED_REC_ID', '=', $zyh)->get()->toArray();
    }

    /**
     * @param $zyh
     * @param $blbh
     * @return array|void
     *
     * 根据住院号完成相关数据同步
     */
    public function SyncData($zyh = "", $blbh = "", $cfjd = '', $qmys = '', $qmrq = '')
    {
        $moduleName = env("APP_NAME", "");
        /* if($moduleName == 'hlw'){
            return;
        } */
        $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\HomeData";
        $homeDataService = new $className();
        if ($moduleName == 'sanyuan') {
            $res = $homeDataService->getBldata($zyh, ['bl01', 'yzb', 'fee_detailed'], $blbh, '', $cfjd, $qmys, $qmrq);
        }elseif($moduleName == 'laizhou'){
            $res = $homeDataService->getBldata($zyh, ['bl01', 'yzb', 'fee_detailed','v_jmgs_ymresult'], $blbh);
        } else {
            $res = $homeDataService->getBldata($zyh, ['bl01', 'yzb', 'fee_detailed'], $blbh);
        }
        Log::info("SyncData 数据同步完毕");
        if ($res === false) {
            return ToolsService::returnData(4001, [], '参数错误');
        }

        // 清洗术者、手术时间
        OperationService::checkList([$zyh]);
        Log::info("SyncData 数据清洗完毕");

        // $service = new UltrasonicService();
        // $service->cleanPacs([$zyh]);

        // 清洗抗菌药物、化疗药物
        RadioService::filterField($zyh);
        Log::info("SyncData 抗菌药物、化疗药物数据清洗完毕");

        // 不在黑名单中的数据，导入ES
        $dataBlock = ['ningxia','hlw'];
        if (!in_array($moduleName, $dataBlock)) {
            EsSaveService::yzb($zyh);
            EsSaveService::sssq($zyh);
            EsSaveService::mzjl($zyh);
            EsSaveService::vjmgsymresult($zyh);
            EsSaveService::pacs($zyh);
            EsSaveService::feeDetailed($zyh);
            Log::info("SyncData ES数据导入完毕");
        }
        
    }

    /**
     * @param string $zyh
     * @param int $isSz
     * @return array|bool
     * 根据住院号同步数据，完成数据清洗以及数据导入ES
     */
    public function syncDataQuality($zyh = '', $isSz = 0, $blbh = "")
    {
        // 预警消息质控，只质控自定义规则
        if ($isSz == 2) {
            $info = PatientInfo::query()->where('MED_REC_ID', $zyh)->get(['MED_REC_ID', 'AAC04', 'AAB01', 'AAC01', 'AAA28', 'AAA29'])->toArray();
            $this->customizeRule($info, "时效性");
        } else {
            return $this->qualityContrlV2('', '', [$zyh], $isSz, $blbh);
        }
    }


    /**
     * @param string $zyh
     * @param int $isSz
     * @return array|bool
     * 根据住院号同步数据，完成数据清洗以及数据导入ES
     */
    public function syncDataQualityV2($zyh = '', $isSz = 0, $blbh = "")
    {

        $this->SyncData($zyh, $blbh);
        return $this->qualityContrl('', '', [$zyh], $isSz);
    }

    /**
     * @param $ZYH
     * 根据ZYH清空格式化后的数据
     */
    public static function deleteBl01ByZYH($zyh)
    {

        Bllb294_45::query()->where('ZYH', $zyh)->delete();
        Bllb303::query()->where('ZYH', $zyh)->delete();
        Bllb288::query()->where('ZYH', $zyh)->delete();
        Bllb292::query()->where('ZYH', $zyh)->delete();
        Bllb303_303::query()->where('ZYH', $zyh)->delete();
        Bllb1::query()->where('ZYH', $zyh)->delete();
        Bllb294_295::query()->where('ZYH', $zyh)->delete();
        Bllb329Tszl::query()->where('ZYH', $zyh)->delete();
        Bllb329Yzccstys::query()->where('ZYH', $zyh)->delete();
        Bllb329Bdqp::query()->where('ZYH', $zyh)->delete();
        Bllb329Hltys::query()->where('ZYH', $zyh)->delete();
        Bllb329Sszqtys::query()->where('ZYH', $zyh)->delete();
        Bllb329Sxzqtys::query()->where('ZYH', $zyh)->delete();
        Bllb329Ctzq::query()->where('ZYH', $zyh)->delete();
        Bllb329Bwbztys::query()->where('ZYH', $zyh)->delete();
        Bllb329Sqwts::query()->where('ZYH', $zyh)->delete();
        Bllb329Zrltys::query()->where('ZYH', $zyh)->delete();
        Bllb34Jjjctys::query()->where('ZYH', $zyh)->delete();
        Bllb34Zdcygzs::query()->where('ZYH', $zyh)->delete();
        Bllb34Sjgzs::query()->where('ZYH', $zyh)->delete();
        Bllb34Ybwffgzs::query()->where('ZYH', $zyh)->delete();

        $esIndex = ["bllb1_2023", "bllb292_2023", "bllb294_295_2023", "bllb294_45_2023", "bllb303_2023", "bllb303_303_2023"];
        foreach ($esIndex as $index) {
            $params = [
                'index' => $index,
                'body' => [
                    'query' => [
                        'term' => [
                            'ZYH' => $zyh
                        ]
                    ]
                ]
            ];
            // 先检查数据是否存在，如果存在则删除
            $result = app('es')->search($params);
            if (!empty($result['hits']['hits'])) {
                app('es')->deleteByQuery($params);
            }
        }
    }

    /**
     * @param $startTime
     * @param $endTime
     * @param $AAA28 病案号
     * @param $isSz 99终末质控，1医生端病例质控，2消息队列病例质控，4执行两次质控（不同步数据）
     * @param $blbh 根据病例编号同步数据
     * @return true
     */
    public function qualityContrlV2($startTime = '', $endTime = '', $AAA28 = [], $isSz = 0, $blbh = '', $cfjd = '', $qmys = '', $qmrq = '')
    {
        $AAA28 = array_filter($AAA28);
        // 获取规则
        $caseRule = $this->caseRule;
        $ruleSetting = $this->ruleSetting;
        //Log::channel('case_quality')->info('ruleSetting', $ruleSetting);

        $moduleName = env("APP_NAME", "");
        $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\BanhzkgzService";
        $banhzkgzService = new $className();

        //查询rulewordmap id = 8108的keyword
        $keyword8108 = RuleWordMap::query()->where('id', 8108)->value('keyword');
        //如果不为空
        if (!empty($keyword8108)) {
            //如果包含逗号，分隔
            if (strpos($keyword8108, ',') !== false) {
                $keyword8108 = explode(',', $keyword8108);
            } else {
                $keyword8108 = [$keyword8108];
            }
        } else {
            $keyword8108 = [];
        }


        // 病历数据格式化
        $blDataFormatService = new BlDataFormatService();
        // 时效性、内涵质控
        //$banhzkgzService = new BanhzkgzService();
        // 专科质控
        //$zkzkService = new ZkzkService();
        // 入院记录质控
        $ruyuan = new RuyuanService();
        // 事中质控，设置当前病例信息是质控中的状态
        if ($isSz == 1 || $isSz == 2) {
            Log::info('qualityHandleV2 质控开始', [
                'AAA28' => $AAA28,
                'isSz' => $isSz
            ]);
            foreach ($AAA28 as $zyh) {
                DataSyncLog::addData(['zyh' => $zyh, 'content' => '3、请求数据同步']);
                $this->SyncData($zyh, $blbh, $cfjd, $qmys, $qmrq);

                // 大模型质控：必须放在 SyncData 之后，否则可能获取不到 bllb
                /* $bllb = '';
                if (!empty($blbh)) {
                    $bl01 = EMR_BL_BL01::query()->where('BLBH', $blbh)->first(['MBLB']);
                    $bllb = !empty($bl01) ? (string)$bl01['MBLB'] : '';
                }
                if (in_array($bllb, ['292', '1', '294', '30', '295', '296', '50', '27'])) {
                    $hasBigModelQueue = BigModelList::query()->where('zyh', $zyh)->exists();
                    if (!$hasBigModelQueue) {
                        BigModelList::query()->insert([
                            'zyh' => $zyh,
                            'status' => 0,
                            'bllb' => $bllb,
                            'blbh' => $blbh,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                } */

                //从brry获取brks
                $brks = ZY_BRRY::query()->where('ZYH', $zyh)->value('BRKS');
                //如果8108不为空，就限制科室
                if (!empty($keyword8108)) {
                    //看brks在不在8108里面
                    if (in_array($brks, $keyword8108)) {
                        $hasBigModelQueue = BigModelList::query()->where('zyh', $zyh)->exists();
                        if (!$hasBigModelQueue) {
                            BigModelList::query()->insert([
                                'zyh' => $zyh,
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s'),
                            ]);
                        }
                    }
                } else {
                    //如果8108为空，就默认质控所有科室
                    $hasBigModelQueue = BigModelList::query()->where('zyh', $zyh)->exists();
                    if (!$hasBigModelQueue) {
                        BigModelList::query()->insert([
                            'zyh' => $zyh,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }

                DataSyncLog::addData(['zyh' => $zyh, 'content' => '9、数据同步完成']);
            }
            PatientInfo::query()->whereIn('MED_REC_ID', $AAA28)->update(['is_case' => 1]);
        }

        // 大模型质控：重新质控清理历史结果时，排除大模型的 rule_id（包含 isSz=99 对 case_quality_zm 的清理）
        $templates = BigModelTemplate::query()->get()->toArray();
        $bitModelRuleIds = array_column($templates, "rule_id");

        if (empty($startTime)) {
            $startTime = date("Y-m-d H:i:s", time() - 15 * 24 * 3600);
        } else {
            $startTime .= ' 00:00:00';
        }
        if (empty($endTime)) {
            $endTime = date("Y-m-d H:i:s", time());
        } else {
            $endTime .= ' 23:59:59';
        }
        $index = 1;

        # 员工信息
        $staff = Staff::query()->get(["code", "name", 'ksdm', 'YGBH'])->toArray();
        $staff = array_column($staff, null, "code");
        $staffByBh = array_column($staff, null, "YGBH");

        # 科室信息
        $dep = Department::query()->get(['dep_id', 'dep_name'])->toArray();
        $dep = array_column($dep, 'dep_name', 'dep_id');

        $defect = $isSz == 1 ? 'is_defect_v2' : 'is_defect';
        while (true) {
            $offset = ($index - 1) * 1000;
            $query = PatientInfo::query();
            if ($AAA28) {
                $query = $query->whereIn('MED_REC_ID', $AAA28);
            } else {
                $query = $query->whereBetween('AAC01', [$startTime, $endTime]);
            }
            $resData = $query->orderBy('AAC01', 'asc')
                ->offset($offset)
                ->limit(1000)
                ->get(['MED_REC_ID', 'AAC04', 'AAB01', 'AAC01', 'AAA28', 'AAA29', 'AAA01'])->toArray();
            if (empty($resData)) {
                break;
            }
            $index++;
            // 数据处理
            $version = time();
            foreach ($resData as $info) {

                Log::info("终末质控-" . $info['MED_REC_ID'] . "出院时间：-" . $info['AAC01']);
                if ($isSz != 99 && $isSz != 991) {
                    $res = CaseQuality::getByJZHM($info['MED_REC_ID']);
                    foreach ($res as &$v) {
                        $v['version'] = $version;
                    }
                    // 清空历史记录，只保留上一次的结果
                    CaseQualityHistory::query()->where("JZHM", $info['MED_REC_ID'])->delete();
                    DataSyncLog::addData(['zyh' => $info['MED_REC_ID'], 'content' => 'CaseQualityHistory删除完成']);
                    CaseQualityHistory::query()->insert($res);
                    DataSyncLog::addData(['zyh' => $info['MED_REC_ID'], 'content' => 'CaseQualityHistory插入完成']);
                }

                //if ($isSz == 99) {
                // 先删除患者病程记录格式化后的数据，再进行格式化
                //$this->deleteBl01ByZYH($info['MED_REC_ID']);
                if ($isSz != 4 && $isSz != 991) {
                    $blDataFormatService->formatBlData($info['MED_REC_ID']);
                }
                //}
                // 删除数据库中的质控数据
                // 获取申诉数据，如果有质控结果在申诉中或者审核通过，则不在质控
                $appeal = Appeal::query()
                    ->where(["quality_type" => 2, "type" => 2, "ZYH" => $info['MED_REC_ID']])->get(["error_id"])
                    ->whereIn("status", ['0', '1'])
                    ->toArray();
                if ($appeal) {
                    $appealRuleIds = array_column($appeal, "error_id");
                    //Log::info('appealruleIds---->', $appealRuleIds);
                    $this->appealRuleIds = $appealRuleIds;
                    $bitModelRuleIds = array_merge($bitModelRuleIds, $appealRuleIds);
                }
                // 删除历史质控数据
                $bitModelRuleIds = array_values(array_filter($bitModelRuleIds));
                DataSyncLog::addData(['zyh' => $info['MED_REC_ID'], 'content' => '计算要跳过删除的规则ID']);

                // 终末质控，删除历史质控结果以及签名医师、书写医师的关联关系
                if ($isSz == 99 || $isSz == 991) {
                    // isSz=99 下历史清理时也要保留大模型结果：仅删除非大模型 rule_id 的数据
                    $zmDeleteQuery = CaseQualityZm::query()->where('JZHM', '=', $info['MED_REC_ID']);
                    if (!empty($bitModelRuleIds)) {
                        $zmDeleteQuery->whereNotIn('rule_id', $bitModelRuleIds);
                    }
                    $zmDeleteQuery->delete();
                    // 删除历史的质控结果以及签名医师、书写医师的关联关系
                    CaseQualityDoctor::query()
                        ->where('ZYH', '=', $info['MED_REC_ID'])
                        ->delete();
                    DataSyncLog::addData(['zyh' => $info['MED_REC_ID'], 'content' => '删除历史的质控结果以及签名医师、书写医师的关联关系']);

                    // 删除ES中的质控数据
                    try {
                        $params = [
                            'index' => 'case_quality_2023',
                            'body' => [
                                'query' => [
                                    'term' => [
                                        'JZHM' => $info['MED_REC_ID']
                                    ]
                                ]
                            ]
                        ];
                        app('es')->deleteByQuery($params);
                        DataSyncLog::addData(['zyh' => $info['MED_REC_ID'], 'content' => '删除ES中的质控数据']);
                    } catch (\Exception $e) {
                        Log::error("删除ES质控数据失败:" . $e->getMessage());
                    }
                } else {

                    CaseQuality::query()->where('JZHM', '=', $info['MED_REC_ID'])
                        ->where("is_artificial", "=", 0)
                        ->whereNotIn("rule_id", $bitModelRuleIds)
                        ->delete();
                    DataSyncLog::addData(['zyh' => $info['MED_REC_ID'], 'content' => '删除历史的质控结果']);
                }

                // 排除郸城中新生儿的质控数据
                if ($moduleName == 'dancheng' && (strpos($info['AAA01'], '之女') !== false || strpos($info['AAA01'], '之子') !== false)) {

                    if ($isSz == 99 || $isSz == 991) {
                        PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_case' => 2, 'zm_score' => 100, 'zm_quality_time' => time(), 'zm_score_lv' => "甲"]);
                        ZY_BRRY::query()->where('ZYH', '=', $info['MED_REC_ID'])->update(['zm_score' => 100, 'zm_score_lv' => "甲"]);
                    } else {
                        PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_case' => 2, $defect => 0, 'quality_time' => time(), 'score' => 100, 'score_lv' => "甲"]);
                        ZY_BRRY::query()->where('ZYH', '=', $info['MED_REC_ID'])->update(['score' => 100, 'score_lv' => "甲"]);
                    }
                    continue;
                }

                // 排除宁夏中新生儿的质控数据
                if ($moduleName == 'ningxia') {
                    $xse = false;
                    // 排除新生儿
                    /* $keyword20047 = RuleWordMap::getArrayById(20047);
                    if ($keyword20047) {
                        $yzb = Yzb::query()->where('ZYH', $info['MED_REC_ID'])->get(['YZMC'])->toArray();
                        foreach ($yzb as $v) {
                            foreach ($keyword20047 as $keyword) {
                                if ($keyword !== '' && strpos($v['YZMC'], $keyword) !== false) {
                                    $xse = true;
                                }
                            }
                        }
                    } */

                    //如果info['AAA28']包含任意字母
                    if (preg_match('/[a-zA-Z]/', $info['AAA28'])) {
                        $xse = true;
                    }

                    if ($xse) {
                        if ($isSz == 99 || $isSz == 991) {
                            PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_case' => 2, 'zm_score' => 100, 'zm_quality_time' => time(), 'zm_score_lv' => "甲"]);
                            ZY_BRRY::query()->where('ZYH', '=', $info['MED_REC_ID'])->update(['zm_score' => 100, 'zm_score_lv' => "甲"]);
                        } else {
                            PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_case' => 2, $defect => 0, 'quality_time' => time(), 'score' => 100, 'score_lv' => "甲"]);
                            ZY_BRRY::query()->where('ZYH', '=', $info['MED_REC_ID'])->update(['score' => 100, 'score_lv' => "甲"]);
                        }
                        continue;
                    }
                }


                // 自定义质控规则
                //if ($isSz == 99 || $isSz == 991) {
                $customizeRuleRes = $this->customizeRule($info, '', $isSz);
                DataSyncLog::addData(['zyh' => $info['MED_REC_ID'], 'content' => '自定义质控规则完成']);

                // 免审
                if ($customizeRuleRes == 2) {
                    if ($isSz == 99 || $isSz == 991) {
                        PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_case' => 2, 'zm_score' => 100, 'zm_quality_time' => time(), 'zm_score_lv' => "甲"]);
                        ZY_BRRY::query()->where('ZYH', '=', $info['MED_REC_ID'])->update(['zm_score' => 100, 'zm_score_lv' => "甲"]);
                    } else {
                        PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_case' => 2, $defect => 0, 'quality_time' => time(), 'score' => 100, 'score_lv' => "甲"]);
                        ZY_BRRY::query()->where('ZYH', '=', $info['MED_REC_ID'])->update(['score' => 100, 'score_lv' => "甲"]);
                    }
                    Log::info("免审-" . $info['MED_REC_ID']);
                    continue;
                }
                //}
                // 实际质控逻辑
                //$this->quality($caseRule, $info);
                //$this->qualityControl($caseRule, $info);
                //                 时效性
                $banhzkgzService->zkgzHandle($caseRule, $info, $isSz);
                //                 内涵质控
                //$banhzkgzService->zkgzNhzkHandle($caseRule, $info);
                //                 专科质控
                //$zkzkService->qualityControl($caseRule, $info);
                // 入院记录质控
                $ruyuan->checkCaseList($info['MED_REC_ID'], $isSz);

                $field = ['BLBH', 'rule_id', 'notice', 'code', 'error_field', 'JZHM', 'BRBH', 'basis', 'is_ai'];
                if ($isSz == 99 || $isSz == 991) {
                    $res = CaseQualityZm::getByJZHM($info['MED_REC_ID'], $field);
                } else {
                    $res = CaseQuality::getByJZHM($info['MED_REC_ID'], $field);
                }
                $score = 0;

                // 记录所有和当前病案号有关的医生信息
                $userList = [];
                Log::info('qualityHandleV2 质控结果', [
                    'res' => $info['MED_REC_ID']
                ]);
                if ($res) {
                    foreach ($res as &$v) {
                        if ($v['rule_id'] > 1000000) {
                            $s = $ruleSetting[$v['rule_id'] - 1000000] ?? '';
                            if (!empty($s)) {
                                $score += $s['score'];
                            }
                        } elseif (isset($caseRule[$v['rule_id']])) {
                            $score += $caseRule[$v['rule_id']]['score'];
                        }

                        // 收集质控病例所有病程关联的书写医师和签名医师
                        $basis = json_decode($v['basis'], true);
                        if (!empty($basis)) {
                            foreach ($basis as $basOne) {
                                if (is_array($basOne)) {
                                    foreach ($basOne as $k => $blbh) {
                                        // 获取签名医师、书写医师
                                        if ($k == "BLBH") {
                                            $bl01 = EMR_BL_BL01::query()->where("BLBH", $blbh)->first(["SXYS", "JZHM", "BRBH"]);
                                            if ($bl01) {
                                                $bl01 = $bl01->toArray();
                                                if ($bl01["SXYS"]) {
                                                    $userList[] = !empty($staffByBh[$bl01["SXYS"]]) ? $staffByBh[$bl01["SXYS"]]['code'] : $bl01["SXYS"];
                                                }
                                            }

                                            $blsy = EMR_BL_BLSY::query()->where("BLBH", $blbh)->get(["SYYS"])->toArray();
                                            if ($blsy) {
                                                $userList = array_merge($userList, array_column($blsy, "SYYS"));
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $diffScore = $score;
                    $score = 100 - $score;
                    if ($score > 90) {
                        $score_lv = '甲';
                    } elseif ($score >= 75 && $score <= 90) {
                        $score_lv = '乙';
                    } elseif ($score < 75) {
                        $score_lv = '丙';
                    }
                    Log::info("计算得分:-" . $score . "，病例质量：" . $score_lv);
                    // is_case 将病例的质控状态改为未质控
                    if ($isSz == 99 || $isSz == 991) {
                        PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_case' => 2, 'zm_score' => $score, 'zm_quality_time' => time(), 'zm_score_lv' => $score_lv]);
                        ZY_BRRY::query()->where('ZYH', '=', $info['MED_REC_ID'])->update(['zm_score' => $score, 'zm_score_lv' => $score_lv]);
                    } else {
                        PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['score' => $score, 'is_case' => 2, 'quality_time' => time(), 'score_lv' => $score_lv]);
                        ZY_BRRY::query()->where('ZYH', '=', $info['MED_REC_ID'])->update(['score' => $score, 'score_lv' => $score_lv]);
                    }
                    # 病案首页中 -- 主治医师、住院医师、诊疗组长、主任医师、质控医师关联质控结果，用于医师排名
                    $brry = ZY_BRRY::query()->where('ZYH', '=', $info['MED_REC_ID'])->first();
                    if ($brry) {
                        $brry = $brry->toArray();
                        $PatientInfoV2 = PatientInfoV2::query()->where('ZYH', '=', $info['MED_REC_ID'])->first(['AEE01_CODE', 'AEE02_CODE', 'AEE03_CODE', 'AEE04_CODE']);
                        $PatientInfoV2 = $PatientInfoV2 ? $PatientInfoV2->toArray() : [];
                        if ($isSz == 99 || $isSz == 991) {
                            $userList = array_merge($userList, [$PatientInfoV2["AEE01_CODE"] ?? '', $PatientInfoV2["AEE02_CODE"] ?? '', $PatientInfoV2["AEE03_CODE"] ?? '', $PatientInfoV2["AEE04_CODE"] ?? '']);
                            $userList = array_unique($userList);
                            foreach ($userList as $v) {
                                if (!$v || empty($staff[$v])) {
                                    continue;
                                }
                                CaseQualityDoctor::query()->updateOrInsert([
                                    "ZYH" => $info['MED_REC_ID'],
                                    "code" => $v
                                ], [
                                    "AAA28" => $info['AAA28'] ?? "",
                                    "BLBH" => "",
                                    "name" => $staff[$v]["name"] ?? "",
                                    "dep_id" => $staff[$v]["ksdm"] ?? 0,
                                    "dep_name" => $dep[$staff[$v]["ksdm"] ?? 0] ?? "",
                                    'rule_id' => 0,
                                    'score' => $diffScore,
                                ]);
                            }
                        } else {
                            // 判断住院号$info['AAA28']在CaseQualityHistory和CaseQuality中的rule_id是否一样
                            $historyRuleIds = \App\Model\CaseQualityHistory::query()
                                ->where('JZHM', $info['MED_REC_ID'])
                                ->pluck('rule_id')
                                ->toArray();
                            $currentRuleIds = \App\Model\CaseQuality::query()
                                ->where('JZHM', $info['MED_REC_ID'])
                                ->pluck('rule_id')
                                ->toArray();
                            $isRuleIdSame = false;
                            if (!empty($historyRuleIds) && !empty($currentRuleIds)) {
                                // 比较两个数组的内容是否完全一致（顺序无关）
                                $isRuleIdSame = (count($historyRuleIds) === count($currentRuleIds)) && (count(array_diff($historyRuleIds, $currentRuleIds)) === 0) && (count(array_diff($currentRuleIds, $historyRuleIds)) === 0);
                            }

                            if ($isRuleIdSame === false) {
                                // 记录质控结果
                                CaseQualityCount::addData([
                                    'ZYH' => $info['MED_REC_ID'],
                                    'quality_date' => date('Y-m-d'),
                                    'BRXM' => $brry['BRXM'] ?? '',
                                    'BRKS' => $brry['BRKS'] ?? '',
                                    'CH' => $brry['CH'] ?? '',
                                    'is_viewed' => 0,
                                ]);
                            }
                        }
                    }
                } else {
                    if ($isSz == 99 || $isSz == 991) {
                        PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_case' => 2, 'zm_score' => 100, 'zm_quality_time' => time(), 'zm_score_lv' => "甲"]);
                        ZY_BRRY::query()->where('ZYH', '=', $info['MED_REC_ID'])->update(['zm_score' => 100, 'zm_score_lv' => "甲"]);
                    } else {
                        PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_case' => 2, $defect => 0, 'quality_time' => time(), 'score' => 100, 'score_lv' => "甲"]);
                        ZY_BRRY::query()->where('ZYH', '=', $info['MED_REC_ID'])->update(['score' => 100, 'score_lv' => "甲"]);
                    }
                }
            }
        }

        return true;
    }

    /**
     * 构建自定义规则表字段字典。
     *
     * @param array $tableDictRows
     * @return array
     */
    protected function buildCustomizeTableFieldDict(array $tableDictRows)
    {
        $tableFieldDict = [];
        $tableIdFieldMap = [];

        foreach ($tableDictRows as $row) {
            if (empty($row['parent_field'])) {
                $tableIdFieldMap[$row['id'] ?? $row['field']] = $row['field'];
            }
        }

        foreach ($tableDictRows as $row) {
            $parentField = $row['parent_field'] ?? 0;
            if (empty($parentField)) {
                continue;
            }

            $table = $tableIdFieldMap[$parentField] ?? $parentField;
            if (empty($table) || empty($row['field'])) {
                continue;
            }

            $tableFieldDict[$table][$row['field']] = $row;
        }

        return $tableFieldDict;
    }

    /**
     * 自定义规则统一取数，优先读取已配置的 MySQL 表。
     *
     * @param string $table
     * @param array $must
     * @param string $field
     * @param array $tableDict
     * @param array $tableFieldDict
     * @return array
     */
    protected function getCustomizeRuleFilterData($table, array $must, $field, array $tableDict, array $tableFieldDict = [])
    {
        // $logPrefix = '[自定义规则取数]';
        $useMysql = $this->shouldCustomizeUseMysql($table, $tableDict);
        $mysqlTable = $useMysql ? $this->getCustomizeMysqlTable($table, $tableDict) : '';

        /* Log::info("{$logPrefix} 开始取数", [
            'es_index' => $table,
            'mysql_table' => $mysqlTable,
            'field' => $field,
            'use_mysql' => $useMysql,
            'must' => $must,
        ]); */

        if ($useMysql) {
            try {
                $data = $this->getCustomizeRuleMysqlData($table, $must, $field, $tableDict, $tableFieldDict);
                /* Log::info("{$logPrefix} MySQL取数完成", [
                    'mysql_table' => $mysqlTable,
                    'field' => $field,
                    'count' => isset($data[0]) && is_array($data[0]) ? count($data[0]) : 0,
                ]); */

                return $data;
            } catch (\Throwable $e) {
                /* Log::error("{$logPrefix} MySQL取数异常，准备回退ES", [
                    'es_index' => $table,
                    'mysql_table' => $mysqlTable,
                    'field' => $field,
                    'must' => $must,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]); */
            }
        }

        /* Log::info("{$logPrefix} 使用ES取数", [
            'es_index' => $table,
            'field' => $field,
            'must' => $must,
        ]); */

        $data = $this->getCustomizeRuleEsData($table, $must);
        /* Log::info("{$logPrefix} ES取数完成", [
            'es_index' => $table,
            'field' => $field,
            'count' => isset($data[0]) && is_array($data[0]) ? count($data[0]) : 0,
        ]); */

        return $data;
    }

    /**
     * 判断自定义规则是否应读取 MySQL。
     *
     * @param string $table
     * @param array $tableDict
     * @return bool
     */
    protected function shouldCustomizeUseMysql($table, array $tableDict)
    {
        if ($table === 'bl01_202303') {
            return true;
        }

        $mysqlField = trim((string)($tableDict[$table]['mysql_field'] ?? ''));
        $shouldUse = !empty($mysqlField);

        /* Log::info('[shouldCustomizeUseMysql]', [
            'table' => $table,
            'mysql_field' => $mysqlField,
            'should_use' => $shouldUse,
            'table_dict_exists' => isset($tableDict[$table]),
        ]); */

        return $shouldUse;
    }

    /**
     * 读取自定义规则 MySQL 数据。
     *
     * @param string $table
     * @param array $must
     * @param string $field
     * @param array $tableDict
     * @param array $tableFieldDict
     * @return array
     */
    protected function getCustomizeRuleMysqlData($table, array $must, $field, array $tableDict, array $tableFieldDict = [])
    {
        // $logPrefix = '[自定义规则取数]';
        $mysqlTable = $this->getCustomizeMysqlTable($table, $tableDict);
        if (empty($mysqlTable)) {
            /* Log::warning("{$logPrefix} 未找到MySQL表配置，回退ES", [
                'table' => $table,
                'field' => $field,
                'must' => $must,
            ]); */

            return $this->getCustomizeRuleEsData($table, $must);
        }

        /* Log::info("{$logPrefix} 准备查询MySQL", [
            'table' => $table,
            'mysql_table' => $mysqlTable,
            'field' => $field,
            'must' => $must,
        ]); */

        $query = \Illuminate\Support\Facades\DB::table($mysqlTable);
        $canUseMysql = $this->applyCustomizeMustToMysqlQuery($query, $table, $must, $tableDict, $tableFieldDict);
        if (!$canUseMysql) {
            /* Log::warning("{$logPrefix} MySQL条件转换失败，回退ES", [
                'table' => $table,
                'mysql_table' => $mysqlTable,
                'field' => $field,
                'must' => $must,
            ]); */

            return $this->getCustomizeRuleEsData($table, $must);
        }

        $rows = $query->limit(10000)->get()->map(function ($row) {
            return (array)$row;
        })->toArray();
        $rows = $this->normalizeCustomizeMysqlRows($table, $rows, $tableFieldDict);

        /* Log::info("{$logPrefix} MySQL查询完成", [
            'table' => $table,
            'mysql_table' => $mysqlTable,
            'field' => $field,
            'count' => count($rows),
            'first_row_keys' => !empty($rows[0]) ? array_keys($rows[0]) : [],
        ]); */

        if ($table === 'bl01_202303' && strtoupper((string)$field) === 'HJNR') {
            $rows = $this->appendBlxgHjnrToBl01Rows($rows, $tableDict);

            /* Log::info("{$logPrefix} BL01补充HJNR完成", [
                'table' => $table,
                'field' => $field,
                'count' => count($rows),
                'hjnr_not_empty_count' => count(array_filter($rows, function ($row) {
                    return !empty($row['HJNR'] ?? '');
                })),
            ]); */
        }

        return [$rows];
    }

    /**
     * 将 MySQL 字段名补齐为自定义规则字段名。
     *
     * @param string $table
     * @param array $rows
     * @param array $tableFieldDict
     * @return array
     */
    protected function normalizeCustomizeMysqlRows($table, array $rows, array $tableFieldDict = [])
    {
        $fieldDict = $tableFieldDict[$table] ?? [];
        if (empty($rows) || empty($fieldDict)) {
            return $rows;
        }

        foreach ($rows as &$row) {
            foreach ($fieldDict as $field => $dict) {
                $mysqlField = trim((string)($dict['mysql_field'] ?? ''));
                if ($mysqlField === '' || !array_key_exists($mysqlField, $row) || array_key_exists($field, $row)) {
                    continue;
                }

                $row[$field] = $row[$mysqlField];
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * 获取自定义规则 MySQL 表名。
     *
     * @param string $table
     * @param array $tableDict
     * @return string
     */
    protected function getCustomizeMysqlTable($table, array $tableDict)
    {
        $mysqlTable = trim((string)($tableDict[$table]['mysql_field'] ?? ''));
        if ($mysqlTable !== '') {
            return $mysqlTable;
        }

        if ($table === 'bl01_202303') {
            return 'EMR_BL_BL01';
        }

        return '';
    }

    /**
     * 将 ES must 条件转换为 MySQL 查询条件。
     *
     * @param mixed $query
     * @param string $table
     * @param array $must
     * @param array $tableDict
     * @param array $tableFieldDict
     * @return bool
     */
    protected function applyCustomizeMustToMysqlQuery($query, $table, array $must, array $tableDict, array $tableFieldDict = [])
    {
        // $logPrefix = '[自定义规则取数]';
        if (empty($must)) {
            /* Log::warning("{$logPrefix} MySQL条件为空", [
                'table' => $table,
            ]); */

            return false;
        }

        foreach ($must as $condition) {
            if (isset($condition['term']) && is_array($condition['term'])) {
                foreach ($condition['term'] as $field => $value) {
                    $value = is_array($value) && array_key_exists('value', $value) ? $value['value'] : $value;
                    $mysqlField = $this->resolveCustomizeMysqlField($table, $field, $tableDict, $tableFieldDict);
                    /* Log::info("{$logPrefix} 转换term条件", [
                        'table' => $table,
                        'field' => $field,
                        'mysql_field' => $mysqlField,
                        'value' => $value,
                    ]); */
                    $query->where($mysqlField, $value);
                }
                continue;
            }

            if (isset($condition['terms']) && is_array($condition['terms'])) {
                foreach ($condition['terms'] as $field => $value) {
                    $mysqlField = $this->resolveCustomizeMysqlField($table, $field, $tableDict, $tableFieldDict);
                    /* Log::info("{$logPrefix} 转换terms条件", [
                        'table' => $table,
                        'field' => $field,
                        'mysql_field' => $mysqlField,
                        'value_count' => count((array)$value),
                    ]); */
                    $query->whereIn($mysqlField, (array)$value);
                }
                continue;
            }

            if (isset($condition['match_phrase']) && is_array($condition['match_phrase'])) {
                foreach ($condition['match_phrase'] as $field => $value) {
                    $escapedValue = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string)$value);
                    $mysqlField = $this->resolveCustomizeMysqlField($table, $field, $tableDict, $tableFieldDict);
                    /* Log::info("{$logPrefix} 转换match_phrase条件", [
                        'table' => $table,
                        'field' => $field,
                        'mysql_field' => $mysqlField,
                        'value' => $value,
                    ]); */
                    $query->where($mysqlField, 'like', '%' . $escapedValue . '%');
                }
                continue;
            }

            if (isset($condition['range']) && is_array($condition['range'])) {
                foreach ($condition['range'] as $field => $range) {
                    $mysqlField = $this->resolveCustomizeMysqlField($table, $field, $tableDict, $tableFieldDict);
                    foreach ((array)$range as $operator => $value) {
                        if ($operator === 'gte') {
                            $query->where($mysqlField, '>=', $value);
                        } elseif ($operator === 'gt') {
                            $query->where($mysqlField, '>', $value);
                        } elseif ($operator === 'lte') {
                            $query->where($mysqlField, '<=', $value);
                        } elseif ($operator === 'lt') {
                            $query->where($mysqlField, '<', $value);
                        }
                    }
                }
                continue;
            }

            /* Log::warning("{$logPrefix} 不支持的MySQL条件，准备回退ES", [
                'table' => $table,
                'condition' => $condition,
            ]); */

            return false;
        }

        return true;
    }

    /**
     * 解析自定义规则字段对应的 MySQL 字段。
     *
     * @param string $table
     * @param string $field
     * @param array $tableDict
     * @param array $tableFieldDict
     * @return string
     */
    protected function resolveCustomizeMysqlField($table, $field, array $tableDict, array $tableFieldDict = [])
    {
        $field = preg_replace('/\.keyword$/', '', (string)$field);
        $mysqlField = trim((string)($tableFieldDict[$table][$field]['mysql_field'] ?? ''));
        if ($mysqlField !== '') {
            return $mysqlField;
        }

        $mysqlField = trim((string)($tableDict[$field]['mysql_field'] ?? ''));
        if ($mysqlField !== '') {
            return $mysqlField;
        }

        return $field;
    }

    /**
     * 为 EMR_BL_BL01 查询结果补充 EMR_BL_BLXG 中的 HJNR。
     * 通过 BLBH（病历编号）关联查询。
     *
     * @param array $rows EMR_BL_BL01 查询结果
     * @param array $tableDict 表字典（保留参数以保持接口一致性）
     * @return array
     */
    protected function appendBlxgHjnrToBl01Rows(array $rows, array $tableDict)
    {
        // $logPrefix = '[自定义规则取数]';
        if (empty($rows)) {
            // Log::warning("{$logPrefix} BL01无数据，跳过补充HJNR");

            return $rows;
        }

        $blbhList = array_values(array_filter(array_column($rows, 'BLBH')));
        if (empty($blbhList)) {
            /* Log::warning("{$logPrefix} BL01结果缺少BLBH，无法补充HJNR", [
                'row_count' => count($rows),
                'first_row_keys' => !empty($rows[0]) ? array_keys($rows[0]) : [],
            ]); */

            return $rows;
        }

        /* Log::info("{$logPrefix} 开始通过BLBH查询BLXG内容", [
            'row_count' => count($rows),
            'blbh_count' => count($blbhList),
            'sample_blbh' => array_slice($blbhList, 0, 5),
        ]); */

        $blxgRows = \Illuminate\Support\Facades\DB::table('EMR_BL_BLXG')
            ->whereIn('BLBH', $blbhList)
            ->orderBy('JLXH', 'desc')
            ->get()
            ->map(function ($row) {
                return (array)$row;
            })
            ->toArray();

        /* Log::info("{$logPrefix} BLXG查询完成", [
            'blxg_count' => count($blxgRows),
            'hjnr_not_empty_count' => count(array_filter($blxgRows, function ($row) {
                return !empty($row['HJNR'] ?? '');
            })),
        ]); */

        $hjnrMap = [];
        foreach ($blxgRows as $row) {
            $blbh = $row['BLBH'] ?? '';
            if ($blbh === '' || isset($hjnrMap[$blbh])) {
                continue;
            }

            $hjnrMap[$blbh] = $row['HJNR'] ?? '';
        }

        foreach ($rows as &$row) {
            $blbh = $row['BLBH'] ?? '';
            $row['HJNR'] = $hjnrMap[$blbh] ?? ($row['HJNR'] ?? '');
        }
        unset($row);

        return $rows;
    }

    /**
     * 读取自定义规则 ES 数据。
     *
     * @param string $table
     * @param array $must
     * @return array
     */
    protected function getCustomizeRuleEsData($table, array $must)
    {
        $esService = new ElasticsearchService($table);
        $params = $esService->clearMust()
            ->queryByMustBatch($must)
            ->paginate(1, 10000)
            ->getParams();

        $res = app('es')->search($params);
        return $esService->getDataByEs($res);
    }

    /**
     * @param array $info
     * 自定义规则
     */
    public function customizeRule($info = [], $type = "", $isSz = 0)
    {
        $customizeLogPrefix = '[自定义规则质控]';
        $tableDictRows = TableDict::query()->get()->toArray();
        $tableDict = array_column($tableDictRows, null, 'field');
        $tableFieldDict = $this->buildCustomizeTableFieldDict($tableDictRows);
        $errorNotices = [];
        $query = RuleSetting::query()->where('status', '=', 1);
        if ($type) {
            $query = $query->where("type", $type);
        }
        $appealRuleIds = [];
        $appeal = Appeal::query()
            ->where(["quality_type" => 2, "type" => 2, "ZYH" => $info['MED_REC_ID']])
            ->whereIn("status", ['1', '3'])
            ->get(["error_id"])
            ->toArray();
        
        if ($appeal) {
            $appealRuleIds = array_column($appeal, "error_id");
        }

        // 删除历史质控数据
        $appealRuleIds = array_values(array_filter($appealRuleIds));
        // 只保留大于 1000000 的 error_id，然后将每个 error_id 减去 1000000
        $appealRuleIds = array_values(array_filter($appealRuleIds, function($id) {
            return $id > 1000000;
        }));

        $appealRuleIds = array_map(function($id) {
            return $id - 1000000;
        }, $appealRuleIds);

        if ($isSz == 1 || $isSz == 4) {

            $ruleSetting = $query->where("rule_type", "普通规则")->where("changjing", "like", "%运行%")->whereNotIn("id", $appealRuleIds)->get()->toArray();
        } else {
            $ruleSetting = $query->where("rule_type", "普通规则")->get()->toArray();
        }

        /* Log::info("{$customizeLogPrefix} 开始执行", [
            'zyh' => $info['MED_REC_ID'] ?? '',
            'type' => $type,
            'is_sz' => $isSz,
            'rule_count' => count($ruleSetting),
            'appeal_rule_ids' => $appealRuleIds,
        ]); */

        foreach ($ruleSetting as $r) {
            $basis = [];
            $ruleSettingDetail = RuleSettingDetail::query()->where('rule_id', '=', $r["id"])->get()->toArray();
            $currentRuleId = $r["id"];
            $customBasis = null;
            $tmpFlag = 1; // 规则前置条件是否满足
            $basisData = [];
            // 前置条件处理
            foreach ($ruleSettingDetail as $item) {

                $condition_content = $item["condition_content"];
                if (empty($condition_content)) {
                    continue;
                }

                // 不是前置条件就跳过
                if ($item["is_pre_condition"] != 1) {
                    continue;
                }
                $tmpFlag = 0;
                $condition_content = json_decode($condition_content, true);

                // [{"param1":["入院记录格式化","主诉"],"param2":"111","condition":"包含"}]
                foreach ($condition_content as $content) {
                    //$ruleFlag = 1;
                    //获取参数1中对应的数据
                    $esName = $content["param1"][0]; // es的索引名称
                    $fdata = null;
                    $must = [];
                    $blmc1 = '';
                    $field = '';
                    if ($esName == 'zy_brry' && $content["param1"][1] == 'dqsj') {
                        //fdata取当前时间
                        $fdata = date('Y-m-d H:i:s');
                    } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'BRKS') {
                        $deps = $content["param2"];
                        if (strpos($deps, ',')) {
                            $deps = explode(',', $deps);
                        } else {
                            $deps = [$deps];
                        }
                        $depsid = '';
                        foreach ($deps as $dep) {
                            //根据dep_name获取dep_id
                            $depid = Department::query()->where('dep_name', 'like', '%' . $dep . '%')->get()->toArray();
                            foreach ($depid as $v) {
                                $depsid .= $v['dep_id'] . ',';
                            }
                        }
                        $depsid = rtrim($depsid, ',');
                        $content["param2"] = $depsid;
                        $must[] = ['term' => [$tableDict[$esName]["zyh_field"] => $info["MED_REC_ID"]]];
                        $field = $content["param1"][1];
                    } else {

                        if ($esName == 'zy_brry' && $content["param1"][1] == 'bcjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['BLLB' => 294]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ssjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 306]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'scbc') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 295]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sjyscf') {
                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 50]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sxjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 45]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'qjjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 27]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'zkjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 30]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'jdxx') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 26]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'shscbc') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 42]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sqxj') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 82]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'hzjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['BLLB' => 294]];
                            $must[] = ['match_phrase' => ['BLMC' => '会诊']];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'swbltl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 4302]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ynbltl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 44]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'mdt') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 46]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ydfm') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 3069999]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'jjb') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 511]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'zl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 131]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'pgc') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 30301]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'fm') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 3069999]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ssaqhc') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 30375]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ssaqpg') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 76]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ycczaqhc') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 303751]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sstys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 30308]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'mzzqtys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 77]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sxzltys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 59]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'tsjctys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 60]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'zlcztys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 88]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'mzjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 32977]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sqgzs') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 85]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'qtzqtys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 32901]];
                            $field = 'HJNR';
                        } else {
                            $esName = $content["param1"][0]; // es的索引名称
                            $must[] = ['term' => [$tableDict[$esName]["zyh_field"] => $info["MED_REC_ID"]]];
                            $field = $content["param1"][1];
                        }
                    }
                    $filterData = [];
                    try {
                        $filterData = $this->getCustomizeRuleFilterData($esName, $must, $field, $tableDict, $tableFieldDict);
                    } catch (\Throwable $e) {
                        /* Log::error("{$customizeLogPrefix} 取数异常，跳过当前条件", [
                            'zyh' => $info['MED_REC_ID'] ?? '',
                            'rule_id' => $currentRuleId,
                            'table' => $esName,
                            'field' => $field,
                            'must' => $must,
                            'condition' => $content['condition'] ?? '',
                            'error' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ]); */

                        continue;
                    }

                    if (empty($filterData) || empty($filterData[0]) || empty($filterData[0][0])) {
                        /* Log::warning("{$customizeLogPrefix} 取数为空，跳过当前条件", [
                            'zyh' => $info['MED_REC_ID'] ?? '',
                            'rule_id' => $currentRuleId,
                            'rule_name' => $r['rule_name'] ?? ($r['name'] ?? ''),
                            'param1' => $content['param1'] ?? [],
                            'table' => $esName,
                            'field' => $field,
                            'must' => $must,
                            'condition' => $content['condition'] ?? '',
                        ]); */

                        continue;
                    }
                    /* Log::info("{$customizeLogPrefix} 取数成功", [
                        'zyh' => $info['MED_REC_ID'] ?? '',
                        'rule_id' => $currentRuleId,
                        'rule_name' => $r['rule_name'] ?? ($r['name'] ?? ''),
                        'param1' => $content['param1'] ?? [],
                        'table' => $esName,
                        'field' => $field,
                        'count' => count($filterData[0]),
                        'condition' => $content['condition'] ?? '',
                    ]); */
                    // $content["param1"][1] es数据中的字段
                    //判断filterData，如果filterData只有一条数据,则fdata为该条数据,如果有多条数据,则fdata为数组
                    if (count($filterData[0]) > 1) {
                        //开循环赋值
                        foreach ($filterData[0] as $val) {
                            $fdata[] = $val[$field];
                        }
                    } else {
                        $fdata = $filterData[0][0][$field] ?? "";
                        $blmc1 = $filterData[0][0]['BLMC'] ?? "";
                    }

                    $content2 = $content["param2"];
                    $condition = $content["condition"];
                    $conditionMet = false;

                    if ($fdata === '0' || $fdata === 0) {
                        $fdata = 0;
                    }

                    // 添加数据验证和错误处理
                    if (!$this->hasRuleValue($fdata) && $content["param1"][0] != 'zy_brry') {
                        $tmpFlag = 0;
                    }

                    //范围 100-200 减号分割,可能会存在100-或者-200的情况,需要处理,如果是100-就是>=100,如果是-200就是<=200
                    /**
                     * 时效规则待定
                     *
                     * [{"param1":["bllb292_2023","XBS"],"param2":"患者10年前无明显诱因下出现多尿","condition":"包含"},{"param1":["bllb303_2023","SSJSSJ"],"param2":"bllb303_2023.SSJSSJ-bllb303_2023.SSJSSJ+24","condition":"时效"}]
                     *
                     * 时效的情况下param2这个索引可能会和上面不一个,所以需要这样传,点前面是索引,后面是字段,然后也使用减号分割,后面的字段如果需要加时间,就像示例一样+24这样
                     *
                     * 时间校验规则
                     * 2.6 时间校验【月】【日】【时】【分】【秒】
                     * 判断选择的字段是否满足到月,日,时,分,秒  比如2024就不满足到月,2024-01就满足到月,以此类推,写分开的五个校验规则
                     * 时间校验【月】
                     * 时间校验【日】
                     * 时间校验【时】
                     * 时间校验【分】
                     * 时间校验【秒】
                     *
                     */

                    //判断是否是范围条件
                    if ($condition == "范围") {

                        $content2 = explode("-", $content2);

                        // 确保数值比较的类型一致
                        $fdata = is_numeric($fdata) ? floatval($fdata) : $fdata;

                        $content2[0] = is_numeric($content2[0]) ? floatval($content2[0]) : $content2[0];
                        $content2[1] = is_numeric($content2[1]) ? floatval($content2[1]) : $content2[1];

                        // 使用 isset() 而不是 empty() 来检查
                        if (isset($content2[0]) && $content2[0] !== '' && isset($content2[1]) && $content2[1] !== '') {
                            $min = is_numeric($content2[0]) ? floatval($content2[0]) : $content2[0];
                            $max = is_numeric($content2[1]) ? floatval($content2[1]) : $content2[1];
                            $conditionMet = $fdata >= $min && $fdata <= $max;
                        } else if (isset($content2[0]) && $content2[0] !== '') {
                            $min = is_numeric($content2[0]) ? floatval($content2[0]) : $content2[0];
                            $conditionMet = $fdata >= $min;
                        } else if (isset($content2[1]) && $content2[1] !== '') {
                            $max = is_numeric($content2[1]) ? floatval($content2[1]) : $content2[1];
                            $conditionMet = $fdata <= $max;
                        } else {
                            continue;
                        }
                    } // 重复率
                    elseif ($condition == "重复率") {
                        // 获取第二个参数的值
                        $p2 = explode(".", $content2);
                        $p2Must = [['term' => [$tableDict[$p2[0]]["zyh_field"] => $info['MED_REC_ID']]]];
                        $filData = $this->getCustomizeRuleFilterData($p2[0], $p2Must, $p2[1], $tableDict, $tableFieldDict);

                        if (empty($filData) || empty($filData[0]) || empty($filData[0][0])) {
                            continue;
                        }

                        $value2 = $filData[0][0][$p2[1]];
                        if (is_array($fdata)) {
                            $fdata = array_reverse($fdata);
                            $fdata = $fdata[0];
                        }

                        // 获取fdata和value2的字数（不包含标点符号）
                        $fdataChars = preg_replace('/[^\p{L}\p{N}]/u', '', $fdata);
                        $fdataCharCount = mb_strlen($fdataChars, 'UTF-8');

                        $value2Chars = preg_replace('/[^\p{L}\p{N}]/u', '', $value2);
                        $value2CharCount = mb_strlen($value2Chars, 'UTF-8');

                        // 谁大谁做分母，两个字数相除如果小于0.85就跳过
                        $maxCharCount = max($fdataCharCount, $value2CharCount);
                        $minCharCount = min($fdataCharCount, $value2CharCount);

                        if ($maxCharCount > 0 && ($minCharCount / $maxCharCount) < 0.85) {
                            continue;
                        }

                        $bcContentArray = preg_split('//u', $fdata, -1, PREG_SPLIT_NO_EMPTY);
                        $bcContentArray = array_unique(array_filter($bcContentArray));

                        $bcContentArray1 = preg_split('//u', $value2, -1, PREG_SPLIT_NO_EMPTY);
                        $bcContentArray1 = array_unique(array_filter($bcContentArray1));
                        // 获取交集
                        $res = array_intersect($bcContentArray, $bcContentArray1);
                        $subtract_value = $content["subtract_value"];
                        if (count($bcContentArray) > 0 && count($res) / count($bcContentArray) * 100 < intval($subtract_value)) {
                            $conditionMet = true;
                        }
                    } elseif ($condition == "时间校验【月】") {
                        //时间校验是否到月,fdata时需要校验的数据
                        // 处理多种可能的时间格式
                        $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                        if (strlen($fdata) == 4) {
                            // 只有年份 "2024"
                            $conditionMet = false;
                        } else {
                            try {
                                // 统一处理斜杠和横杠
                                $fdata = str_replace('/', '-', $fdata);
                                $date = new DateTime($fdata);
                                // 提取年月部分 (前7个字符)
                                $yearMonth = substr($fdata, 0, 7);
                                // 验证年月格式
                                $conditionMet = (bool)preg_match('/^\d{4}-([0][1-9]|1[0-2])$/', $yearMonth);
                            } catch (\Exception $e) {
                                $conditionMet = false;
                            }
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时间校验【日】") {
                        try {
                            $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                            $fdata = str_replace('/', '-', $fdata);
                            $date = new DateTime($fdata);
                            $yearMonthDay = substr($fdata, 0, 10);
                            $conditionMet = (bool)preg_match('/^\d{4}-([0][1-9]|1[0-2])-([0][1-9]|[12][0-9]|3[01])$/', $yearMonthDay);
                        } catch (\Exception $e) {
                            $conditionMet = false;
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时间校验【时】") {
                        try {
                            $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                            $fdata = str_replace('/', '-', $fdata);
                            $date = new DateTime($fdata);
                            $withHour = substr($fdata, 0, 13);
                            $conditionMet = (bool)preg_match('/^\d{4}-([0][1-9]|1[0-2])-([0][1-9]|[12][0-9]|3[01]) ([01][0-9]|2[0-3])$/', $withHour);
                        } catch (\Exception $e) {
                            $conditionMet = false;
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时间校验【分】") {
                        try {
                            $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                            $fdata = str_replace('/', '-', $fdata);
                            $withMinute = mb_substr($fdata, 0, 16);
                            $conditionMet = (bool)preg_match('/^\d{4}-([0][1-9]|1[0-2])-([0][1-9]|[12][0-9]|3[01]) ([01][0-9]|2[0-3]):([0-5][0-9])$/', $withMinute);
                        } catch (\Exception $e) {
                            $conditionMet = false;
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时间校验【秒】") {
                        try {
                            $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                            $fdata = str_replace('/', '-', $fdata);
                            //$date = new DateTime($fdata);
                            $withSecond = substr($fdata, 0, 19);
                            $conditionMet = (bool)preg_match('/^\d{4}-([0][1-9]|1[0-2])-([0][1-9]|[12][0-9]|3[01]) ([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $withSecond);
                        } catch (\Exception $e) {
                            $conditionMet = false;
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时效") {

                        // 初始化条件匹配结果为false
                        $conditionMet = false;

                        try {
                            // 获取参数
                            $param1 = $content["param1"]; // [索引, 字段]
                            $param2 = $content["param2"]; // 条件值（可能包含逗号分隔的多值）
                            $param3 = $content["param3"]; // [索引, 时间字段]
                            $param4 = $content["param4"]; // 时间范围检查配置数组

                            // 获取索引和字段
                            $indexName = $param1[0];
                            $fieldName = $param1[1];

                            // 查询条件值可能包含逗号分隔的多个值
                            $conditionValues = [];
                            if (strpos($param2, ",") !== false) {
                                $conditionValues = explode(",", $param2);
                            } else {
                                $conditionValues = [$param2];
                            }

                            // 统一入口取数：配置了 mysql_field 的表优先走 MySQL，避免时效规则绕过映射配置。
                            $timeMust = [
                                ['term' => [$tableDict[$indexName]["zyh_field"] => $info['MED_REC_ID']]]
                            ];
                            $filterData = $this->getCustomizeRuleFilterData($indexName, $timeMust, $fieldName, $tableDict, $tableFieldDict);

                            // 保留原 ES term 多值查询语义：先按患者取数，再按字段精确匹配过滤。
                            if (!empty($filterData[0])) {
                                $filterData[0] = array_values(array_filter($filterData[0], function ($record) use ($fieldName, $conditionValues) {
                                    $recordValue = $record[$fieldName] ?? '';
                                    foreach ($conditionValues as $value) {
                                        $value = trim((string)$value);
                                        if ($value === '') {
                                            continue;
                                        }

                                        if (is_array($recordValue)) {
                                            foreach ($recordValue as $item) {
                                                if (trim((string)$item) === $value) {
                                                    return true;
                                                }
                                            }
                                            continue;
                                        }

                                        if (trim((string)$recordValue) === $value) {
                                            return true;
                                        }
                                    }

                                    return false;
                                }));
                            }

                            // 如果没有找到匹配的记录，则跳过
                            if (empty($filterData) || empty($filterData[0])) {
                                continue;
                            }

                            // 获取时间字段数据
                            $timeFieldName = $param3[1];
                            $timeRecords = [];

                            foreach ($filterData[0] as $record) {
                                if (!empty($record[$timeFieldName])) {
                                    $timeRecords[] = [
                                        'time' => $record[$timeFieldName],
                                        'record' => $record
                                    ];
                                }
                            }

                            // 如果没有有效的时间记录，则跳过
                            if (empty($timeRecords)) {
                                continue;
                            }

                            // 遍历每个时间记录
                            foreach ($timeRecords as $timeRecord) {
                                $recordTime = strtotime($timeRecord['time']);
                                $allConditionsMet = true;

                                // 检查每个param4配置
                                foreach ($param4 as $p4) {
                                    $kssj = $p4['kssj']; // 开始时间字段
                                    $jssj = $p4['jssj']; // 结束时间字段
                                    $sjjg = $p4['sjjg']; // 时间间隔(小时)
                                    $indexField = explode('.', $p4['index'])[1]; // 需要检索的字段
                                    $condition1 = $p4['condition1']; // 需要包含的关键词
                                    $condition2 = $p4['condition2']; // 需要排除的关键词

                                    // 解析索引和字段
                                    list($checkIndexName, $checkFieldName) = explode('.', $kssj);

                                    // 统一入口取数：配置了 mysql_field 的表优先走 MySQL，包含/排除条件在取数后保持原语义过滤。
                                    $checkMust = [
                                        ['term' => [$tableDict[$checkIndexName]["zyh_field"] => $info['MED_REC_ID']]]
                                    ];
                                    $checkData = $this->getCustomizeRuleFilterData($checkIndexName, $checkMust, $indexField, $tableDict, $tableFieldDict);

                                    $condition1Values = [];
                                    if (!empty($condition1)) {
                                        $condition1Values = strpos($condition1, ",") !== false ? explode(',', $condition1) : [$condition1];
                                        $condition1Values = array_values(array_filter(array_map('trim', $condition1Values), function ($value) {
                                            return $value !== '';
                                        }));
                                    }

                                    $condition2Values = [];
                                    if (!empty($condition2)) {
                                        $condition2Values = strpos($condition2, ",") !== false ? explode(',', $condition2) : [$condition2];
                                        $condition2Values = array_values(array_filter(array_map('trim', $condition2Values), function ($value) {
                                            return $value !== '';
                                        }));
                                    }

                                    if (!empty($checkData[0])) {
                                        $checkData[0] = array_values(array_filter($checkData[0], function ($checkRecord) use ($indexField, $condition1Values, $condition2Values) {
                                            $fieldValue = (string)($checkRecord[$indexField] ?? '');

                                            if (!empty($condition1Values)) {
                                                $includeMatched = false;
                                                foreach ($condition1Values as $value) {
                                                    if (strpos($fieldValue, $value) !== false) {
                                                        $includeMatched = true;
                                                        break;
                                                    }
                                                }

                                                if (!$includeMatched) {
                                                    return false;
                                                }
                                            }

                                            foreach ($condition2Values as $value) {
                                                if (strpos($fieldValue, $value) !== false) {
                                                    return false;
                                                }
                                            }

                                            return true;
                                        }));
                                    }

                                    // 如果没有找到匹配的检查记录，则标记条件未满足
                                    if (empty($checkData) || empty($checkData[0])) {
                                        $allConditionsMet = false;
                                        break;
                                    }

                                    // 检查每个检查记录，是否在时间范围内
                                    $checkConditionMet = false;
                                    foreach ($checkData[0] as $checkRecord) {
                                        // 获取开始和结束时间
                                        list($kssjIndex, $kssjField) = explode('.', $kssj);
                                        list($jssjIndex, $jssjField) = explode('.', $jssj);

                                        $startTime = strtotime($checkRecord[$kssjField]);
                                        $endTime = strtotime($checkRecord[$jssjField]);

                                        // 如果有时间间隔，应用到结束时间
                                        if (!empty($sjjg)) {
                                            $endTime = $startTime + (intval($sjjg) * 3600); // 转换为秒
                                        }

                                        // 检查记录时间是否在范围内
                                        if ($recordTime >= $startTime && $recordTime <= $endTime) {
                                            $checkConditionMet = true;
                                            break;
                                        }
                                    }

                                    // 如果当前param4条件未满足，则所有条件未满足
                                    if (!$checkConditionMet) {
                                        $allConditionsMet = false;
                                        break;
                                    }
                                }

                                // 如果所有条件都满足，则满足整体条件
                                if ($allConditionsMet) {
                                    $conditionMet = true;
                                    break;
                                }
                            }
                        } catch (\Exception $e) {
                            // 记录错误日志
                            Log::error('时效规则执行错误', [
                                'error' => $e->getMessage(),
                                'content' => $content,
                                'info' => $info
                            ]);
                            continue;
                        }
                    } elseif ($condition == "包含") {
                        $conditionMet = false; // 默认设置为 false
                        //新增delete_field参数，先删除对应字段，可能会包含逗号
                        $delete_field = $content['delete_field'] ?? '';
                        //如果fdata为数组,则循环
                        if (is_array($fdata)) {
                            foreach ($fdata as $val) {
                                if ($conditionMet) {
                                    break;
                                }

                                $fdata1 = $val;

                                // 添加到basisData,保存es索引名和字段名和对应的数据

                                if (!empty($delete_field)) {
                                    //如果包含逗号,则按照逗号分隔
                                    if (strpos($delete_field, ",") !== false) {
                                        $delete_field_array = explode(",", $delete_field);
                                        foreach ($delete_field_array as $val) {
                                            $fdata1 = str_replace($val, '', $fdata1);
                                        }
                                    } else {
                                        $fdata1 = str_replace($delete_field, '', $fdata1);
                                    }
                                }
                                $v = '';
                                if (strpos($content2, ",") !== false) {
                                    $content2Array = explode(",", $content2);
                                    foreach ($content2Array as $value) {
                                        //$value = trim($value); // 去除可能的空格
                                        if (empty($value)) {
                                            continue;
                                        }
                                        if (strpos($fdata1, $value) !== false) {
                                            $conditionMet = true;
                                            $v = $value;
                                            break;
                                        }
                                        //Log::info('包含', ['value' => $value, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                                    }
                                } else {
                                    // 如果content2不包含逗号,则直接判断是否包含
                                    $conditionMet = strpos($fdata1, $content2) !== false;
                                    if ($conditionMet) {
                                        $v = $content2;
                                    }
                                    //Log::info('包含', ['value' => $content2, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                                }

                                $basisData[] = [
                                    'es_name' => $content["param1"][0],
                                    'field_name' => $content["param1"][1],
                                    'fdata' => $blmc1,
                                    'blmc' => $fdata['blmc'] ?? '',
                                    'conditionMet' => $conditionMet,
                                    'condition' => $condition
                                ];
                            }
                        } else {
                            if (!empty($delete_field)) {
                                //如果包含逗号,则按照逗号分隔
                                if (strpos($delete_field, ",") !== false) {
                                    $delete_field_array = explode(",", $delete_field);
                                    foreach ($delete_field_array as $val) {
                                        $fdata = str_replace($val, '', $fdata);
                                    }
                                } else {
                                    $fdata = str_replace($delete_field, '', $fdata);
                                }
                            }
                            $v = '';
                            if (strpos($content2, ",") !== false) {
                                $content2Array = explode(",", $content2);
                                foreach ($content2Array as $value) {
                                    //$value = trim($value); // 去除可能的空格
                                    if (empty($value)) {
                                        continue;
                                    }
                                    //Log::info('测试包含', ['fdata-v' => $fdata, 'value-v' => $value]);
                                    if (strpos($fdata, $value) !== false) {
                                        $conditionMet = true;
                                        $v = $value;
                                        break;
                                    }
                                    //Log::info('包含', ['value' => $value, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                                }
                            } else {
                                // 如果content2不包含逗号,则直接判断是否包含
                                $conditionMet = strpos($fdata, (string)$content2) !== false;
                                if ($conditionMet) {
                                    $v = $content2;
                                }
                                //Log::info('包含', ['value' => $content2, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                            }
                            // 添加到basisData,保存es索引名和字段名和对应的数据
                            //Log::info('ceshi---------',['fdata'=>$fdata,'condition'=>$condition,'content'=>$content2,'met'=>$conditionMet]);
                            //if ($conditionMet) {
                            $basisData[] = [
                                'es_name' => $content["param1"][0],
                                'field_name' => $content["param1"][1],
                                'fdata' => $blmc1,
                                'blmc' => $blmc ?? '',
                                'conditionMet' => $conditionMet,
                                'condition' => $condition
                            ];
                            //}
                        }
                    } elseif ($condition == "不包含") { //包含和不包含分开
                        // 其他条件的判断
                        //修改一下.如果是包含不包含,就判断content2是否包含英文的逗号,如果包含就按照逗号分隔,然后判断是否包含分隔后的每一个值,满足一个就是true,如果没有逗号就正常判断
                        $conditionMet = false; // 默认设置为 false

                        //新增delete_field参数，先删除对应字段，可能会包含逗号
                        $delete_field = $content['delete_field'] ?? '';
                        //如果fdata为数组,则循环
                        if (is_array($fdata)) {
                            foreach ($fdata as $val) {
                                if ($conditionMet) {
                                    break;
                                }


                                $fdata1 = $val;


                                if (!empty($delete_field)) {
                                    //如果包含逗号,则按照逗号分隔
                                    if (strpos($delete_field, ",") !== false) {
                                        $delete_field_array = explode(",", $delete_field);
                                        foreach ($delete_field_array as $val) {
                                            $fdata1 = str_replace($val, '', $fdata1);
                                        }
                                    } else {
                                        $fdata1 = str_replace($delete_field, '', $fdata1);
                                    }
                                }
                                if (strpos($content2, ",") !== false) {
                                    $content2Array = explode(",", $content2);
                                    foreach ($content2Array as $value) {
                                        if (empty($value)) {
                                            continue;
                                        }
                                        if (strpos($fdata1, $value) === false) {
                                            $conditionMet = true;
                                        } else {
                                            $conditionMet = false;
                                            break;
                                        }
                                    }
                                } else {
                                    // 如果content2不包含逗号,则直接判断是否不包含
                                    $conditionMet = strpos($fdata1, $content2) === false;
                                    //Log::info('不包含', ['value' => $content2, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                                }
                                //if ($conditionMet) {
                                $basisData[] = [
                                    'es_name' => $content["param1"][0],
                                    'field_name' => $content["param1"][1],
                                    'fdata' => $blmc1,
                                    'blmc' => $fdata['blmc'] ?? '',
                                    'conditionMet' => $conditionMet,
                                    'condition' => $condition
                                ];
                                //}
                            }
                        } else {
                            if (!empty($delete_field)) {
                                //如果包含逗号,则按照逗号分隔
                                if (strpos($delete_field, ",") !== false) {
                                    $delete_field_array = explode(",", $delete_field);
                                    foreach ($delete_field_array as $val) {
                                        $fdata = str_replace($val, '', $fdata);
                                    }
                                } else {
                                    $fdata = str_replace($delete_field, '', $fdata);
                                }
                            }

                            if (strpos($content2, ",") !== false) {
                                $content2Array = explode(",", $content2);
                                foreach ($content2Array as $value) {
                                    //Log::info('不包含', ['value' => $value, 'fdata' => $fdata]);
                                    if (empty($value)) {
                                        continue;
                                    }
                                    if (strpos($fdata, $value) === false) {
                                        $conditionMet = true;
                                    } else {
                                        $conditionMet = false;
                                        break;
                                    }
                                }
                            } else {
                                // 如果content2不包含逗号,则直接判断是否不包含
                                $conditionMet = strpos($fdata, $content2) === false;
                                //Log::info('不包含', ['value' => $content2, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                            }
                            //Log::info('ceshi---------',['fdata'=>$fdata,'condition'=>$condition,'content'=>$content2,'met'=>$conditionMet]);
                            //if ($conditionMet) {
                            $basisData[] = [
                                'es_name' => $content["param1"][0],
                                'field_name' => $content["param1"][1],
                                'fdata' => $blmc1,
                                'blmc' => $blmc ?? '',
                                'conditionMet' => $conditionMet,
                                'condition' => $condition
                            ];
                            //}
                        }
                    } elseif ($condition == "病程记录关联") {
                        // 初始化条件匹配结果为false
                        $conditionMet = false;

                        // 获取病程记录数据
                        $indexName = $content["param1"][0]; // 索引名称，例如 bl01_202303
                        $mblbField = $content["param1"][1]; // MBLB字段
                        $blbhField = $content["param1"][2]; // BLBH字段
                        //可能会包含逗号
                        if (strpos($content["param2"], ",") !== false) {
                            $mblbValues = explode(",", $content["param2"]); // 可能包含逗号分隔的多个值
                        } else {
                            $mblbValues = [$content["param2"]];
                        }

                        // 查询条件
                        $must = [];
                        $must[] = ['term' => [$tableDict[$indexName]["zyh_field"] => $info['MED_REC_ID']]];

                        // 处理MBLB条件，支持多值查询
                        $shouldTerms = [];
                        foreach ($mblbValues as $value) {
                            $shouldTerms[] = ['term' => [$mblbField => trim($value)]];
                        }

                        if (count($shouldTerms) == 1) {
                            $must[] = $shouldTerms[0];
                        } else if (count($shouldTerms) > 1) {
                            $must[] = ['bool' => ['should' => $shouldTerms, 'minimum_should_match' => 1]];
                        }

                        try {
                            // 统一入口取数：配置了 mysql_field 的表优先走 MySQL，MBLB 多值条件取数后过滤。
                            $filterMust = [
                                ['term' => [$tableDict[$indexName]["zyh_field"] => $info['MED_REC_ID']]]
                            ];
                            $filterData = $this->getCustomizeRuleFilterData($indexName, $filterMust, $blbhField, $tableDict, $tableFieldDict);

                            if (!empty($filterData[0])) {
                                $mblbValues = array_values(array_filter(array_map('trim', $mblbValues), function ($value) {
                                    return $value !== '';
                                }));

                                $filterData[0] = array_values(array_filter($filterData[0], function ($record) use ($mblbField, $mblbValues) {
                                    if (empty($mblbValues)) {
                                        return true;
                                    }

                                    $recordValue = trim((string)($record[$mblbField] ?? ''));
                                    foreach ($mblbValues as $value) {
                                        if ($recordValue === trim((string)$value)) {
                                            return true;
                                        }
                                    }

                                    return false;
                                }));
                            }

                            // 如果没有找到匹配的记录，则跳过
                            if (empty($filterData) || empty($filterData[0])) {
                                continue;
                            }

                            // 保存first_blsy_time和BLBH信息
                            $blsyTimeField = $content["param3"][1]; // fisrt_blsy_time字段
                            $blRecords = [];

                            foreach ($filterData[0] as $record) {
                                if (!empty($record[$blsyTimeField]) && !empty($record[$blbhField])) {
                                    $blRecords[] = [
                                        'blsy_time' => $record[$blsyTimeField],
                                        'blbh' => $record[$blbhField]
                                    ];
                                }
                            }

                            // 如果没有有效的病程记录，则跳过
                            if (empty($blRecords)) {
                                continue;
                            }

                            // 获取param4参数
                            $param4 = $content["param4"];
                            $matchedRecords = [];

                            // 处理每个param4条件,param4只有一条,只是根据里面的条件和所有有可能查出多条,所以不使用foreach
                            foreach ($param4 as $p4) {
                                $kssj = $p4['kssj']; // 开始时间字段
                                $jssj = $p4['jssj']; // 结束时间字段
                                $sjjg = $p4['sjjg']; // 时间间隔(小时)
                                $params1 = $p4['params1']; // 需要获取的字段
                                $condition1 = $p4['condition1']; // 需要包含的关键词
                                $condition2 = $p4['condition2']; // 需要排除的关键词
                                $zj = $p4['zj']; // 关联字段

                                // 解析索引和字段
                                list($indexName, $fieldName) = explode('.', $kssj);
                                list($endTimeIndex, $endTimeField) = explode('.', $jssj);

                                // 处理包含条件,可能会包含逗号
                                $shouldTerms1 = [];
                                if (!empty($condition1)) {
                                    // 如果包含逗号,则按照逗号分隔
                                    if (strpos($condition1, ",") !== false) {
                                        $condition1Values = explode(',', $condition1);
                                        foreach ($condition1Values as $val) {
                                            $val = trim($val);
                                            if (!empty($val)) {
                                                $shouldTerms1[] = ['match' => ['bgd' => $val]];
                                            }
                                        }
                                    } else {
                                        $shouldTerms1[] = ['match' => ['bgd' => $condition1]];
                                    }
                                }

                                // 处理排除条件
                                $mustNotTerms = [];
                                if (!empty($condition2)) {
                                    // 如果包含逗号,则按照逗号分隔
                                    if (strpos($condition2, ",") !== false) {
                                        $condition2Values = explode(',', $condition2);
                                        foreach ($condition2Values as $val) {
                                            $val = trim($val);
                                            if (!empty($val)) {
                                                $mustNotTerms[] = ['match' => ['bgd' => $val]];
                                            }
                                        }
                                    } else {
                                        $mustNotTerms[] = ['match' => ['bgd' => $condition2]];
                                    }
                                }

                                // 统一入口取数：配置了 mysql_field 的表优先走 MySQL，包含/排除条件取数后过滤。
                                $checkMust = [
                                    ['term' => [$tableDict[$indexName]["zyh_field"] => $info['MED_REC_ID']]]
                                ];
                                $checkData = $this->getCustomizeRuleFilterData($indexName, $checkMust, $fieldName, $tableDict, $tableFieldDict);

                                $includeValues = [];
                                foreach ($shouldTerms1 as $term) {
                                    if (!empty($term['match']) && is_array($term['match'])) {
                                        $includeValues[] = trim((string)reset($term['match']));
                                    }
                                }
                                $includeValues = array_values(array_filter($includeValues, function ($value) {
                                    return $value !== '';
                                }));

                                $excludeValues = [];
                                foreach ($mustNotTerms as $term) {
                                    if (!empty($term['match']) && is_array($term['match'])) {
                                        $excludeValues[] = trim((string)reset($term['match']));
                                    }
                                }
                                $excludeValues = array_values(array_filter($excludeValues, function ($value) {
                                    return $value !== '';
                                }));

                                if (!empty($checkData[0])) {
                                    $checkData[0] = array_values(array_filter($checkData[0], function ($record) use ($includeValues, $excludeValues) {
                                        $bgd = (string)($record['bgd'] ?? '');

                                        if (!empty($includeValues)) {
                                            $includeMatched = false;
                                            foreach ($includeValues as $value) {
                                                if (strpos($bgd, $value) !== false) {
                                                    $includeMatched = true;
                                                    break;
                                                }
                                            }

                                            if (!$includeMatched) {
                                                return false;
                                            }
                                        }

                                        foreach ($excludeValues as $value) {
                                            if (strpos($bgd, $value) !== false) {
                                                return false;
                                            }
                                        }

                                        return true;
                                    }));
                                }
                                // 如果没有找到匹配的记录，则继续下一个
                                if (empty($checkData) || empty($checkData[0])) {
                                    continue;
                                }

                                // 对于每条检查记录，检查是否有病程记录在时间范围内
                                foreach ($checkData[0] as $checkRecord) {
                                    // 获取开始和结束时间
                                    $startTime = strtotime($checkRecord[$fieldName]);
                                    $endTime = strtotime($checkRecord[$endTimeField]);

                                    // 如果有时间间隔，加到结束时间上
                                    if (!empty($sjjg)) {
                                        $endTime = $startTime + (intval($sjjg) * 3600); // 转换为秒
                                    }

                                    // 检查每条病程记录是否在时间范围内
                                    foreach ($blRecords as $blRecord) {
                                        $blsyTime = strtotime($blRecord['blsy_time']);

                                        // 检查病程记录时间是否在范围内
                                        if ($blsyTime >= $startTime && $blsyTime <= $endTime) {
                                            // 保存匹配记录的信息
                                            $matchedRecords[] = [
                                                'blbh' => $blRecord['blbh'],
                                                'checkBLBH' => isset($checkRecord[$params1]) ? $checkRecord[$params1] : '',
                                                'relation_type' => $content["param5"] // gl或bgl
                                            ];
                                        }
                                    }
                                }
                            }

                            // 如果找到了匹配的记录，检查是否满足关联条件
                            if (!empty($matchedRecords)) {
                                $param5 = $content["param5"]; // gl(关联)或bgl(不关联)
                                $param6 = $content["param6"]; // [索引, 包含条件, 不包含条件]

                                $indexName = $param6[0];
                                list($indexName, $hjnrField) = explode('.', $indexName);
                                //如果包含逗号,则按照逗号分隔
                                if (strpos($param6[1], ",") !== false) {
                                    $includeTerms = explode(',', $param6[1]);
                                } else {
                                    $includeTerms = [$param6[1]];
                                }
                                if (strpos($param6[2], ",") !== false) {
                                    $excludeTerms = explode(',', $param6[2]);
                                } else {
                                    $excludeTerms = [$param6[2]];
                                }
                                // 处理每条匹配记录
                                foreach ($matchedRecords as $record) {
                                    // 构建统一取数条件：配置了 mysql_field 的表优先走 MySQL。
                                    $hjnrMust = [
                                        ['term' => [$tableDict[$indexName]["zyh_field"] => $info['MED_REC_ID']]]
                                    ];

                                    // 如果是关联(gl)模式，添加最开始保存的blbh条件。
                                    if ($param5 == 'gl' && !empty($record['blbh'])) {
                                        $hjnrMust[] = ['term' => [$blbhField => $record['blbh']]];
                                    }

                                    $hjnrData = $this->getCustomizeRuleFilterData($indexName, $hjnrMust, $hjnrField, $tableDict, $tableFieldDict);

                                    // 如果没有找到病历内容，则继续下一个
                                    if (empty($hjnrData) || empty($hjnrData[0]) || empty($hjnrData[0][0]) || empty($hjnrData[0][0][$hjnrField])) {
                                        continue;
                                    }

                                    $hjnr = $hjnrData[0][0][$hjnrField];
                                    $passInclude = true;
                                    $passExclude = true;

                                    // 检查是否包含需要的关键词
                                    if (!empty($includeTerms)) {
                                        $passInclude = false;
                                        //是否是数组
                                        if (is_array($includeTerms)) {
                                            foreach ($includeTerms as $term) {
                                                $term = trim($term);
                                                if (!empty($term) && strpos($hjnr, $term) !== false) {
                                                    $passInclude = true;
                                                    break;
                                                }
                                            }
                                        } else {
                                            if (!empty($includeTerms) && strpos($hjnr, $includeTerms) !== false) {
                                                $passInclude = true;
                                            }
                                        }
                                    }

                                    // 检查是否不包含需要排除的关键词
                                    if (!empty($excludeTerms)) {
                                        $passExclude = true;
                                        //是否是数组
                                        if (is_array($excludeTerms)) {
                                            foreach ($excludeTerms as $term) {
                                                $term = trim($term);
                                                if (!empty($term) && strpos($hjnr, $term) !== false) {
                                                    $passExclude = false;
                                                    break;
                                                }
                                            }
                                        } else {
                                            if (!empty($excludeTerms) && strpos($hjnr, $excludeTerms) !== false) {
                                                $passExclude = false;
                                            }
                                        }
                                    }

                                    // 如果同时满足包含和排除条件，则条件满足
                                    if ($passInclude && $passExclude) {
                                        $conditionMet = true;
                                        break; // 只要有一条记录满足条件就可以
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            // 记录错误日志
                            Log::error('病程记录关联规则执行错误', [
                                'error' => $e->getMessage(),
                                'content' => $content,
                                'info' => $info
                            ]);
                            continue;
                        }
                    } elseif ($condition == "减") {
                        // 获取第二个参数的值
                        $p2 = [$content['subtract_param'][0], $content['subtract_param'][1]];
                        $value2 = null;
                        if ($p2[0] == 'zy_brry' && $p2[1] == 'dqsj') {
                            $value2 = date('Y-m-d H:i:s');
                        } else {
                            $p2Must = [['term' => [$tableDict[$p2[0]]["zyh_field"] => $info['MED_REC_ID']]]];
                            $filData = $this->getCustomizeRuleFilterData($p2[0], $p2Must, $p2[1], $tableDict, $tableFieldDict);

                            if (empty($filData) || empty($filData[0]) || empty($filData[0][0])) {
                                continue;
                            }

                            $value2 = $filData[0][0][$p2[1]];
                        }
                        // 计算差值
                        if ($content['subtract_value_type'] == 'hour' || $content['subtract_value_type'] == 'minute') {
                            // 时间差值计算
                            $time1 = strtotime($fdata);
                            $time2 = strtotime($value2);
                            $diffSeconds = abs($time1 - $time2);

                            if ($content['subtract_value_type'] == 'hour') {
                                $diff = $diffSeconds / 3600; // 转换为小时
                            } else {
                                $diff = $diffSeconds / 60; // 转换为分钟
                            }
                        } else {
                            // 数值差值计算
                            $diff = abs(floatval($fdata) - floatval($value2));
                        }

                        // 根据比较条件判断
                        switch ($content['subtract_condition']) {
                            case 'gt':
                                $conditionMet = $diff > floatval($content['subtract_value']);
                                break;
                            case 'lt':
                                $conditionMet = $diff < floatval($content['subtract_value']);
                                break;
                            case 'eq':
                                $conditionMet = abs($diff - floatval($content['subtract_value'])) < 0.000001; // 浮点数比较
                                break;
                            default:
                                $conditionMet = false;
                        }
                    } else {
                        $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                        //如果包含岁，月，时，分
                        if (strpos($fdata, '岁') !== false || strpos($fdata, '月') !== false || strpos($fdata, '时') !== false || strpos($fdata, '天') !== false || strpos($fdata, '分') !== false) {
                            //如果包含岁就提取岁前面的数字，不包含岁就设置为0
                            if (strpos($fdata, '岁') !== false) {
                                //提取岁前面的数字，比如3岁2月就提取3，45岁就提取45
                                preg_match('/(\d+)岁/', $fdata, $matches);
                                $fdata = !empty($matches[1]) ? intval($matches[1]) : 0;
                            } else {
                                $fdata = 0;
                            }
                        }
                        /* //如果是6岁8月这种格式，转换成6.8这样的数字
                        if (is_string($fdata) && preg_match('/(\d+)岁(\d+)月/', $fdata, $matches)) {
                            $years = intval($matches[1]);
                            $months = intval($matches[2]);
                            $fdata = $years + ($months / 12); // 转换为类似6.8的格式
                        }
                        //如果是45岁这种格式，转换成45这样的数字
                        if (is_string($fdata) && preg_match('/(\d+)岁/', $fdata, $matches)) {
                            $fdata = intval($matches[1]);
                        } */
                        $conditionMet = ($condition == "等于" && $fdata == $content2) ||
                            ($condition == "不等于" && $fdata != $content2) ||
                            ($condition == "大于" && $fdata > $content2) ||
                            ($condition == "小于" && $fdata < $content2) ||
                            ($condition == "大于等于" && $fdata >= $content2) ||
                            ($condition == "小于等于" && $fdata <= $content2) ||
                            ($condition == "不为空" && $this->hasRuleValue($fdata));
                        //Log::info('ceshi---------',['fdata'=>$fdata,'condition'=>$condition,'content'=>$content2,'met'=>$conditionMet]);
                    }


                    if (!$conditionMet) {
                        $tmpFlag = 0;
                        /* Log::info("{$customizeLogPrefix} 前置条件不满足", [
                            'zyh' => $info['MED_REC_ID'] ?? '',
                            'rule_id' => $currentRuleId,
                            'rule_name' => $r['rule_name'] ?? '',
                            'param1' => $content['param1'] ?? [],
                            'param2' => $content['param2'] ?? '',
                            'condition' => $condition,
                            'fdata' => is_array($fdata) ? json_encode($fdata, JSON_UNESCAPED_UNICODE) : $fdata,
                            'conditionMet' => $conditionMet,
                            'condition_relation' => $item['condition_relation'] ?? 0,
                        ]); */
                        if ($item['condition_relation'] == 1) {
                            break;
                        }
                    } else {
                        $tmpFlag = 1;
                        /* Log::info("{$customizeLogPrefix} 前置条件满足", [
                            'zyh' => $info['MED_REC_ID'] ?? '',
                            'rule_id' => $currentRuleId,
                            'rule_name' => $r['rule_name'] ?? '',
                            'param1' => $content['param1'] ?? [],
                            'param2' => $content['param2'] ?? '',
                            'condition' => $condition,
                            'fdata' => is_array($fdata) ? json_encode($fdata, JSON_UNESCAPED_UNICODE) : $fdata,
                            'conditionMet' => $conditionMet,
                            'condition_relation' => $item['condition_relation'] ?? 0,
                        ]); */

                        if ($item['condition_relation'] == 2) {
                            //根据条件判断不满足就删除对应的basisData
                            break;
                        }
                    }
                    //Log::info('tmpFlag', ['tmpFlag' . $item['rule_id'] => $tmpFlag]);
                }
                //如果tmpflag=1且condition_type=2就是免审,跳过本条规则
                if ($tmpFlag == 1 && $item['condition_type'] == 2) {
                    continue 2;
                } //如果tmpflag=0且condition_type=2跳过这个条件
                if ($tmpFlag == 0 && $item['condition_type'] == 2) {
                    $tmpFlag = 1;
                }
                //有一个前置不满足就跳出循环
                if ($tmpFlag == 0) {
                    /* Log::info("{$customizeLogPrefix} 前置条件整体不满足，跳过规则", [
                        'zyh' => $info['MED_REC_ID'] ?? '',
                        'rule_id' => $currentRuleId,
                        'rule_name' => $r['rule_name'] ?? '',
                        'tmpFlag' => $tmpFlag,
                    ]); */
                    break;
                }
            }

            /* Log::info("{$customizeLogPrefix} 前置条件检查完成", [
                'zyh' => $info['MED_REC_ID'] ?? '',
                'rule_id' => $currentRuleId,
                'rule_name' => $r['rule_name'] ?? '',
                'tmpFlag' => $tmpFlag,
            ]); */

            if ($tmpFlag == 0) {
                /* Log::info("{$customizeLogPrefix} 跳过规则（前置条件不满足）", [
                    'zyh' => $info['MED_REC_ID'] ?? '',
                    'rule_id' => $currentRuleId,
                    'rule_name' => $r['rule_name'] ?? '',
                ]); */
                continue;
            }



            // 免审处理
            /* foreach ($ruleSettingDetail as $item) {
                if ($item["condition_type"] == 2) {
                    continue 2;
                }
            } */

            // 处理实际的规则
            $ruleFlag = 1;
            $preWarningTime = 0;
            $customMsg = null;
            $qtTime = null;
            foreach ($ruleSettingDetail as $item) {

                //$ruleFlag = 1;
                $condition_content = $item["condition_content"];
                $detailstatus = $item['detail_status'];
                if (empty($condition_content)) {
                    continue;
                }
                // 前置条件就跳过
                if ($item["is_pre_condition"] == 1) {
                    continue;
                }
                if (!empty($item['custom_basis'])) {
                    $customBasis = json_decode($item['custom_basis'], true);
                }
                $condition_content = json_decode($condition_content, true);
                if ($item['pre_warning_time'] > 0) {
                    $preWarningTime = $item['pre_warning_time'];
                    $customMsg = json_decode($item['custom_msg'], true);
                    if (!empty($customMsg)) {
                        $esName = $customMsg['param1'][0];
                        $fieldName = $customMsg['param1'][1];
                        $customMsgMust = [['term' => [$tableDict[$esName]["zyh_field"] => $info['MED_REC_ID']]]];
                        $filterData = $this->getCustomizeRuleFilterData($esName, $customMsgMust, $fieldName, $tableDict, $tableFieldDict);
                        if ($filterData && $filterData[0] && $filterData[0][0]) {
                            $qtTime = $filterData[0][0][$fieldName];
                        }
                    }
                }

                // [{"param1":["入院记录格式化","主诉"],"param2":"111","condition":"包含"}]
                foreach ($condition_content as $content) {

                    if (empty($content["param1"])) {
                        continue;
                    }
                    $ruleFlag = 1;
                    $esName = $content["param1"][0]; // es的索引名称
                    $fdata = null;
                    $must = [];
                    $field = '';
                    $isblmc = false;
                    $blmc = '';
                    $blmc1 = '';
                    if ($esName == 'zy_brry' && $content["param1"][1] == 'dqsj') {
                        //fdata取当前时间
                        $fdata = date('Y-m-d H:i:s');
                    } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'BRKS') {
                        $deps = $content["param2"];
                        if (strpos($deps, ',')) {
                            $deps = explode(',', $deps);
                        } else {
                            $deps = [$deps];
                        }
                        $depsid = '';
                        foreach ($deps as $dep) {
                            //根据dep_name获取dep_id
                            $depid = Department::query()->where('dep_name', 'like', '%' . $dep . '%')->get()->toArray();
                            foreach ($depid as $v) {
                                $depsid .= $v['dep_id'] . ',';
                            }
                        }
                        $depsid = rtrim($depsid, ',');
                        $content["param2"] = $depsid;
                        $must[] = ['term' => [$tableDict[$esName]["zyh_field"] => $info["MED_REC_ID"]]];
                        $field = $content["param1"][1];
                    } else {

                        if ($esName == 'zy_brry' && $content["param1"][1] == 'bcjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['BLLB' => 294]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ssjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 306]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'scbc') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 295]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sjyscf') {
                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 50]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sxjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 45]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'qjjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 27]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'zkjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 30]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'jdxx') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 26]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'shscbc') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 42]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sqxj') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 82]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'hzjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['BLLB' => 294]];
                            $must[] = ['match_phrase' => ['BLMC' => '会诊']];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'swbltl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 4302]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ynbltl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 44]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'mdt') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 46]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ydfm') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 3069999]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'jjb') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 511]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'zl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 131]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'pgc') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 30301]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'fm') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 3069999]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ssaqhc') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 30375]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ssaqpg') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 76]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ycczaqhc') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 303751]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sstys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 30308]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'mzzqtys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 77]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sxzltys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 59]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'tsjctys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 60]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'zlcztys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 88]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'mzjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 32977]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sqgzs') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 85]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'qtzqtys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 32901]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } else {
                            $esName = $content["param1"][0]; // es的索引名称
                            $must[] = ['term' => [$tableDict[$esName]["zyh_field"] => $info["MED_REC_ID"]]];
                            $field = $content["param1"][1];
                        }
                    }

                    $filterData = [];
                    try {
                        $filterData = $this->getCustomizeRuleFilterData($esName, $must, $field, $tableDict, $tableFieldDict);
                    } catch (\Throwable $e) {
                        continue;
                    }

                    if (empty($filterData) || empty($filterData[0]) || empty($filterData[0][0])) {
                        continue;
                    }
                    // $content["param1"][1] es数据中的字段
                    //判断filterData，如果filterData只有一条数据,则fdata为该条数据,如果有多条数据,则fdata为数组
                    if (count($filterData[0]) > 1) {
                        //开循环赋值
                        foreach ($filterData[0] as $val) {
                            if ($isblmc) {
                                $fdata[] = ['blmc' => $val['BLMC'] ?? '', 'hjnr' => $val[$field] ?? ''];
                            } else {
                                $fdata[] = $val[$field] ?? '';
                            }
                        }
                    } else {
                        $fdata = !empty($filterData[0][0][$field]) ? $filterData[0][0][$field] : '';
                        $blmc1 = $filterData[0][0]['BLMC'] ?? '';
                        if ($isblmc) {
                            $blmc = $filterData[0][0]['BLMC'] ?? '';
                        }
                    }


                    $content2 = $content["param2"];
                    $condition = $content["condition"];
                    $conditionMet = null;


                    if ($fdata === '0' || $fdata === 0) {
                        $fdata = 0;
                    }
                    // 添加数据验证和错误处理
                    /* if (empty($fdata) && $fdata !== 0 && $content["param1"][0] != 'zy_brry') {
                        continue;
                    } */

                    //范围 100-200 减号分割,可能会存在100-或者-200的情况,需要处理,如果是100-就是>=100,如果是-200就是<=200
                    /**
                     * 时效规则待定
                     *
                     * [{"param1":["bllb292_2023","XBS"],"param2":"患者10年前无明显诱因下出现多尿","condition":"包含"},{"param1":["bllb303_2023","SSJSSJ"],"param2":"bllb303_2023.SSJSSJ-bllb303_2023.SSJSSJ+24","condition":"时效"}]
                     *
                     * 时效的情况下param2这个索引可能会和上面不一个,所以需要这样传,点前面是索引,后面是字段,然后也使用减号分割,后面的字段如果需要加时间,就像示例一样+24这样
                     *
                     * 时间校验规则
                     * 2.6 时间校验【月】【日】【时】【分】【秒】
                     * 判断选择的字段是否满足到月,日,时,分,秒  比如2024就不满足到月,2024-01就满足到月,以此类推,写分开的五个校验规则
                     * 时间校验【月】
                     * 时间校验【日】
                     * 时间校验【时】
                     * 时间校验【分】
                     * 时间校验【秒】
                     *
                     */

                    //判断是否是范围条件

                    if ($condition == "范围") {

                        $content2 = explode("-", $content2);

                        // 确保数值比较的类型一致
                        $fdata = is_numeric($fdata) ? floatval($fdata) : $fdata;

                        $content2[0] = is_numeric($content2[0]) ? floatval($content2[0]) : $content2[0];
                        $content2[1] = is_numeric($content2[1]) ? floatval($content2[1]) : $content2[1];

                        // 使用 isset() 而不是 empty() 来检查
                        if (isset($content2[0]) && $content2[0] !== '' && isset($content2[1]) && $content2[1] !== '') {
                            $min = is_numeric($content2[0]) ? floatval($content2[0]) : $content2[0];
                            $max = is_numeric($content2[1]) ? floatval($content2[1]) : $content2[1];
                            $conditionMet = $fdata >= $min && $fdata <= $max;
                        } else if (isset($content2[0]) && $content2[0] !== '') {
                            $min = is_numeric($content2[0]) ? floatval($content2[0]) : $content2[0];
                            $conditionMet = $fdata >= $min;
                        } else if (isset($content2[1]) && $content2[1] !== '') {
                            $max = is_numeric($content2[1]) ? floatval($content2[1]) : $content2[1];
                            $conditionMet = $fdata <= $max;
                        } else {
                            continue;
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } // 重复率
                    elseif ($condition == "重复率") {
                        // 获取第二个参数的值
                        $p2 = explode(".", $content2);
                        $p2Must = [['term' => [$tableDict[$p2[0]]["zyh_field"] => $info['MED_REC_ID']]]];
                        $filData = $this->getCustomizeRuleFilterData($p2[0], $p2Must, $p2[1], $tableDict, $tableFieldDict);

                        if (empty($filData) || empty($filData[0]) || empty($filData[0][0])) {
                            continue;
                        }

                        $value2 = $filData[0][0][$p2[1]];

                        $ts = $fdata;
                        if (is_array($fdata)) {
                            $fdata = array_reverse($fdata);
                            $ts = $fdata[0];
                        }


                        // 获取fdata和value2的字数（不包含标点符号）
                        $fdataChars = preg_replace('/[^\p{L}\p{N}]/u', '', $ts);
                        $fdataCharCount = mb_strlen($fdataChars, 'UTF-8');

                        $value2Chars = preg_replace('/[^\p{L}\p{N}]/u', '', $value2);
                        $value2CharCount = mb_strlen($value2Chars, 'UTF-8');

                        // 谁大谁做分母，两个字数相除如果小于0.85就跳过
                        $maxCharCount = max($fdataCharCount, $value2CharCount);
                        $minCharCount = min($fdataCharCount, $value2CharCount);

                        if ($maxCharCount > 0 && ($minCharCount / $maxCharCount) < 0.85) {
                            continue;
                        }

                        $bcContentArray = preg_split('//u', $ts, 0, PREG_SPLIT_NO_EMPTY);
                        $bcContentArray = array_unique(array_filter($bcContentArray));

                        $bcContentArray1 = preg_split('//u', $value2, 0, PREG_SPLIT_NO_EMPTY);
                        $bcContentArray1 = array_unique(array_filter($bcContentArray1));
                        // 获取交集
                        $res = array_intersect($bcContentArray, $bcContentArray1);
                        $subtract_value = $content["subtract_value"];
                        if (count($bcContentArray) && count($res) / count($bcContentArray) * 100 < intval($subtract_value)) {
                            $conditionMet = true;
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据，这个条件中还有value2,需要保存,需要保存value2的索引名和字段名，分成数组
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                        $basisData[] = [
                            'es_name' => $p2[0],
                            'field_name' => $p2[1],
                            'fdata' => $value2,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时间校验【月】") {
                        //时间校验是否到月,fdata时需要校验的数据
                        // 处理多种可能的时间格式
                        $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                        if (strlen($fdata) == 4) {
                            // 只有年份 "2024"
                            $conditionMet = false;
                        } else {
                            try {
                                // 统一处理斜杠和横杠
                                $fdata = str_replace('/', '-', $fdata);
                                $date = new DateTime($fdata);
                                // 提取年月部分 (前7个字符)
                                $yearMonth = substr($fdata, 0, 7);
                                // 验证年月格式
                                $conditionMet = (bool)preg_match('/^\d{4}-([0][1-9]|1[0-2])$/', $yearMonth);
                            } catch (\Exception $e) {
                                $conditionMet = false;
                            }
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时间校验【日】") {
                        try {
                            $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                            $fdata = str_replace('/', '-', $fdata);
                            $date = new DateTime($fdata);
                            $yearMonthDay = substr($fdata, 0, 10);
                            $conditionMet = (bool)preg_match('/^\d{4}-([0][1-9]|1[0-2])-([0][1-9]|[12][0-9]|3[01])$/', $yearMonthDay);
                        } catch (\Exception $e) {
                            $conditionMet = false;
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时间校验【时】") {
                        try {
                            $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                            $fdata = str_replace('/', '-', $fdata);
                            $date = new DateTime($fdata);
                            $withHour = substr($fdata, 0, 13);
                            $conditionMet = (bool)preg_match('/^\d{4}-([0][1-9]|1[0-2])-([0][1-9]|[12][0-9]|3[01]) ([01][0-9]|2[0-3])$/', $withHour);
                        } catch (\Exception $e) {
                            $conditionMet = false;
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时间校验【分】") {
                        try {
                            $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                            $fdata = str_replace('/', '-', $fdata);
                            $withMinute = mb_substr($fdata, 0, 16);
                            $conditionMet = (bool)preg_match('/^\d{4}-([0][1-9]|1[0-2])-([0][1-9]|[12][0-9]|3[01]) ([01][0-9]|2[0-3]):([0-5][0-9])$/', $withMinute);
                        } catch (\Exception $e) {
                            $conditionMet = false;
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时间校验【秒】") {
                        try {
                            $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                            $fdata = str_replace('/', '-', $fdata);
                            //$date = new DateTime($fdata);
                            $withSecond = substr($fdata, 0, 19);
                            $conditionMet = (bool)preg_match('/^\d{4}-([0][1-9]|1[0-2])-([0][1-9]|[12][0-9]|3[01]) ([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $withSecond);
                        } catch (\Exception $e) {
                            $conditionMet = false;
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时效") {
                        //Log::info('时效', ['esName' => $esName, 'content' => $content]);
                        // 初始化条件匹配结果为false
                        $conditionMet = false;
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];

                        try {
                            // 获取参数
                            $param1 = $content["param1"]; // [索引, 字段]
                            $param2 = $content["param2"]; // 条件值（可能包含逗号分隔的多值）
                            $param3 = $content["param3"]; // [索引, 时间字段]
                            $param4 = $content["param4"]; // 时间范围检查配置数组

                            // 获取索引和字段
                            $indexName = $param1[0];
                            $fieldName = $param1[1];

                            // 查询条件值可能包含逗号分隔的多个值
                            $conditionValues = [];
                            if (strpos($param2, ",") !== false) {
                                $conditionValues = explode(",", $param2);
                            } else {
                                $conditionValues = [$param2];
                            }

                            // 统一入口取数：配置了 mysql_field 的表优先走 MySQL，避免时效规则绕过映射配置。
                            $timeMust = [
                                ['term' => [$tableDict[$indexName]["zyh_field"] => $info['MED_REC_ID']]]
                            ];
                            $filterData = $this->getCustomizeRuleFilterData($indexName, $timeMust, $fieldName, $tableDict, $tableFieldDict);

                            // 保留原 ES term 多值查询语义：先按患者取数，再按字段精确匹配过滤。
                            if (!empty($filterData[0])) {
                                $filterData[0] = array_values(array_filter($filterData[0], function ($record) use ($fieldName, $conditionValues) {
                                    $recordValue = $record[$fieldName] ?? '';
                                    foreach ($conditionValues as $value) {
                                        $value = trim((string)$value);
                                        if ($value === '') {
                                            continue;
                                        }

                                        if (is_array($recordValue)) {
                                            foreach ($recordValue as $item) {
                                                if (trim((string)$item) === $value) {
                                                    return true;
                                                }
                                            }
                                            continue;
                                        }

                                        if (trim((string)$recordValue) === $value) {
                                            return true;
                                        }
                                    }

                                    return false;
                                }));
                            }


                            //Log::info('filterData', ['filterData' => $filterData]);

                            // 如果没有找到匹配的记录，则跳过
                            if (empty($filterData) || empty($filterData[0])) {
                                continue;
                            }


                            // 获取时间字段数据
                            $timeFieldName = $param3[1];
                            $timeRecords = [];

                            foreach ($filterData[0] as $record) {
                                if (!empty($record[$timeFieldName])) {
                                    $timeRecords[] = [
                                        'time' => $record[$timeFieldName],
                                        'record' => $record
                                    ];
                                    // 添加到basisData,保存es索引名和字段名和对应的数据，储存时间字段和记录
                                    $basisData[] = [
                                        'es_name' => $indexName,
                                        'field_name' => $timeFieldName,
                                        'fdata' => $record[$timeFieldName],
                                        'conditionMet' => $conditionMet,
                                        'condition' => $condition
                                    ];
                                }
                            }

                            // 如果没有有效的时间记录，则跳过
                            if (empty($timeRecords)) {
                                continue;
                            }

                            // 遍历每个时间记录
                            foreach ($timeRecords as $timeRecord) {
                                $recordTime = strtotime($timeRecord['time']);
                                $allConditionsMet = true;

                                // 检查每个param4配置
                                foreach ($param4 as $p4) {
                                    $kssj = $p4['kssj']; // 开始时间字段
                                    $jssj = $p4['jssj']; // 结束时间字段
                                    $sjjg = $p4['sjjg']; // 时间间隔(小时)
                                    //判断是否存在index字段
                                    if (isset($p4['index'])) {
                                        $indexField = explode('.', $p4['index'])[1]; // 需要检索的字段
                                        $condition1 = $p4['condition1']; // 需要包含的关键词
                                        $condition2 = $p4['condition2']; // 需要排除的关键词
                                    }


                                    // 解析索引和字段
                                    list($checkIndexName, $checkFieldName) = explode('.', $kssj);

                                    // 统一入口取数：配置了 mysql_field 的表优先走 MySQL，包含/排除条件在取数后保持原语义过滤。
                                    $checkMust = [
                                        ['term' => [$tableDict[$checkIndexName]["zyh_field"] => $info['MED_REC_ID']]]
                                    ];
                                    $checkData = $this->getCustomizeRuleFilterData($checkIndexName, $checkMust, $indexField, $tableDict, $tableFieldDict);

                                    $condition1Values = [];
                                    if (!empty($condition1)) {
                                        $condition1Values = strpos($condition1, ",") !== false ? explode(',', $condition1) : [$condition1];
                                        $condition1Values = array_values(array_filter(array_map('trim', $condition1Values), function ($value) {
                                            return $value !== '';
                                        }));
                                    }

                                    $condition2Values = [];
                                    if (!empty($condition2)) {
                                        $condition2Values = strpos($condition2, ",") !== false ? explode(',', $condition2) : [$condition2];
                                        $condition2Values = array_values(array_filter(array_map('trim', $condition2Values), function ($value) {
                                            return $value !== '';
                                        }));
                                    }

                                    if (!empty($checkData[0])) {
                                        $checkData[0] = array_values(array_filter($checkData[0], function ($checkRecord) use ($indexField, $condition1Values, $condition2Values) {
                                            $fieldValue = (string)($checkRecord[$indexField] ?? '');

                                            if (!empty($condition1Values)) {
                                                $includeMatched = false;
                                                foreach ($condition1Values as $value) {
                                                    if (strpos($fieldValue, $value) !== false) {
                                                        $includeMatched = true;
                                                        break;
                                                    }
                                                }

                                                if (!$includeMatched) {
                                                    return false;
                                                }
                                            }

                                            foreach ($condition2Values as $value) {
                                                if (strpos($fieldValue, $value) !== false) {
                                                    return false;
                                                }
                                            }

                                            return true;
                                        }));
                                    }

                                    // 如果没有找到匹配的检查记录，则标记条件未满足
                                    if (empty($checkData) || empty($checkData[0])) {
                                        $allConditionsMet = false;
                                        break;
                                    }

                                    // 检查每个检查记录，是否在时间范围内
                                    $checkConditionMet = false;
                                    foreach ($checkData[0] as $checkRecord) {
                                        // 获取开始和结束时间
                                        list($kssjIndex, $kssjField) = explode('.', $kssj);
                                        list($jssjIndex, $jssjField) = explode('.', $jssj);

                                        $startTime = strtotime($checkRecord[$kssjField]);
                                        $endTime = strtotime($checkRecord[$jssjField]);

                                        // 如果有时间间隔，应用到结束时间
                                        if (!empty($sjjg)) {
                                            $endTime = $startTime + (intval($sjjg) * 3600); // 转换为秒
                                        }

                                        // 检查记录时间是否在范围内
                                        if ($recordTime >= $startTime && $recordTime <= $endTime) {
                                            $checkConditionMet = true;
                                            break;
                                        }
                                        //储存开始时间字段和结束时间字段和对应的数据
                                        $basisData[] = [
                                            'es_name' => $checkIndexName,
                                            'field_name' => $kssjField,
                                            'fdata' => $checkRecord[$kssjField],
                                            'conditionMet' => $checkConditionMet,
                                            'condition' => $condition
                                        ];
                                    }

                                    // 如果当前param4条件未满足，则所有条件未满足
                                    if (!$checkConditionMet) {
                                        $allConditionsMet = false;
                                        break;
                                    }
                                }

                                // 如果所有条件都满足，则满足整体条件
                                if ($allConditionsMet) {
                                    $conditionMet = true;
                                    break;
                                }
                            }
                        } catch (\Exception $e) {
                            // 记录错误日志
                            Log::error('时效规则执行错误', [
                                'error' => $e->getMessage(),
                                'content' => $content,
                                'info' => $info
                            ]);
                            continue;
                        }
                    } elseif ($condition == "包含") {
                        $conditionMet = false; // 默认设置为 false
                        //新增delete_field参数，先删除对应字段，可能会包含逗号
                        $delete_field = $content['delete_field'] ?? '';
                        //如果fdata为数组,则循环
                        if (is_array($fdata)) {
                            foreach ($fdata as $val) {
                                if ($conditionMet) {
                                    break;
                                }
                                if ($isblmc) {
                                    $fdata1 = $val['hjnr'];
                                } else {
                                    $fdata1 = $val;
                                }
                                // 添加到basisData,保存es索引名和字段名和对应的数据

                                if (!empty($delete_field)) {
                                    //如果包含逗号,则按照逗号分隔
                                    if (strpos($delete_field, ",") !== false) {
                                        $delete_field_array = explode(",", $delete_field);
                                        foreach ($delete_field_array as $val1) {
                                            $fdata1 = str_replace($val1, '', $fdata1);
                                        }
                                    } else {
                                        $fdata1 = str_replace($delete_field, '', $fdata1);
                                    }
                                }
                                if (strpos($content2, ",") !== false) {
                                    $content2Array = explode(",", $content2);
                                    foreach ($content2Array as $value) {
                                        //$value = trim($value); // 去除可能的空格
                                        if (empty($value)) {
                                            continue;
                                        }
                                        if (empty($value)) {
                                            continue;
                                        }
                                        if (strpos($fdata1, $value) !== false) {
                                            $conditionMet = true;
                                            break;
                                        }
                                        //Log::info('包含', ['value' => $value, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                                    }
                                } else {
                                    // 如果content2不包含逗号,则直接判断是否包含
                                    $conditionMet = strpos($fdata1, $content2) !== false;
                                    //Log::info('包含', ['value' => $content2, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                                }
                                if (($conditionMet && $detailstatus == 2) || (!$conditionMet && $detailstatus == 1)) {
                                    $basisData[] = [
                                        'es_name' => $content["param1"][0],
                                        'field_name' => $content["param1"][1],
                                        'fdata' => $val['blmc'] ?? '',
                                        'blmc' => $val['blmc'] ?? '',
                                        'conditionMet' => $conditionMet,
                                        'condition' => $condition
                                    ];
                                    /* if ($info['MED_REC_ID'] == '588028' && $content2 == '*') {
                                        Log::info('basisData111', ['fdata' => $val]);
                                        Log::info('basisData', ['basisData' => $basisData]);
                                    } */
                                }
                                /*} elseif ($conditionMet && $detailstatus == 1) {
                                    $basisData[] = [
                                        'es_name' => $content["param1"][0],
                                        'field_name' => $content["param1"][1],
                                        'fdata' => $tableDict[$esName]["field_name"],
                                        'blmc' => $fdata['blmc'] ?? '',
                                        'conditionMet' => $conditionMet,
                                        'condition' => $condition
                                    ];
                                }elseif ($detailstatus == 1 && !$conditionMet) {
                                    $basisData[] = [
                                        'es_name' => $content["param1"][0],
                                        'field_name' => $content["param1"][1],
                                        'fdata' => $tableDict[$esName]["field_name"],
                                        'blmc' => $fdata['blmc'] ?? '',
                                        'conditionMet' => $conditionMet,
                                        'condition' => $condition
                                    ];
                                }elseif ($detailstatus == 1 && $conditionMet) {
                                    $basisData[] = [
                                        'es_name' => $content["param1"][0],
                                        'field_name' => $content["param1"][1],
                                        'fdata' => $tableDict[$esName]["field_name"],
                                        'blmc' => $fdata['blmc'] ?? '',
                                        'conditionMet' => $conditionMet,
                                        'condition' => $condition
                                    ];
                                } */
                            }
                        } else {
                            if (!empty($delete_field)) {
                                //如果包含逗号,则按照逗号分隔
                                if (strpos($delete_field, ",") !== false) {
                                    $delete_field_array = explode(",", $delete_field);
                                    foreach ($delete_field_array as $val) {
                                        $fdata = str_replace($val, '', $fdata);
                                    }
                                } else {
                                    $fdata = str_replace($delete_field, '', $fdata);
                                }
                            }
                            if (strpos($content2, ",") !== false) {
                                $content2Array = explode(",", $content2);
                                foreach ($content2Array as $value) {
                                    //$value = trim($value); // 去除可能的空格
                                    if (empty($value)) {
                                        continue;
                                    }
                                    //Log::info('测试包含', ['fdata-v' => $fdata, 'value-v' => $value]);
                                    if (strpos($fdata, $value) !== false) {
                                        $conditionMet = true;
                                        break;
                                    }
                                    //Log::info('包含', ['value' => $value, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                                }
                            } else {
                                // 如果content2不包含逗号,则直接判断是否包含
                                $conditionMet = strpos($fdata, $content2) !== false;
                                //Log::info('包含', ['value' => $content2, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                            }
                            // 添加到basisData,保存es索引名和字段名和对应的数据
                            //if ($conditionMet && $detailstatus == 2) {
                            $basisData[] = [
                                'es_name' => $content["param1"][0],
                                'field_name' => $content["param1"][1],
                                'fdata' => $blmc1,
                                'blmc' => $fdata['blmc'] ?? '',
                                'conditionMet' => $conditionMet,
                                'condition' => $condition
                            ];
                            /* }elseif ($conditionMet && $detailstatus == 1) {
                                $basisData[] = [
                                    'es_name' => $content["param1"][0],
                                    'field_name' => $content["param1"][1],
                                    'fdata' => $tableDict[$esName]["field_name"],
                                    'blmc' => $fdata['blmc'] ?? '',
                                    'conditionMet' => $conditionMet,
                                    'condition' => $condition
                                ];
                            }elseif ($detailstatus == 1 && !$conditionMet) {
                                $basisData[] = [
                                    'es_name' => $content["param1"][0],
                                    'field_name' => $content["param1"][1],
                                    'fdata' => $tableDict[$esName]["field_name"],
                                    'blmc' => $fdata['blmc'] ?? '',
                                    'conditionMet' => $conditionMet,
                                    'condition' => $condition
                                ];
                            }elseif ($detailstatus == 1 && $conditionMet) {
                                $basisData[] = [
                                    'es_name' => $content["param1"][0],
                                    'field_name' => $content["param1"][1],
                                    'fdata' => $tableDict[$esName]["field_name"],
                                    'blmc' => $fdata['blmc'] ?? '',
                                    'conditionMet' => $conditionMet,
                                    'condition' => $condition
                                ];
                            } */
                        }
                    } elseif ($condition == "不包含") { //包含和不包含分开
                        // 其他条件的判断
                        //修改一下.如果是包含不包含,就判断content2是否包含英文的逗号,如果包含就按照逗号分隔,然后判断是否包含分隔后的每一个值,满足一个就是true,如果没有逗号就正常判断
                        $conditionMet = false; // 默认设置为 false

                        //新增delete_field参数，先删除对应字段，可能会包含逗号
                        $delete_field = $content['delete_field'] ?? '';
                        //如果fdata为数组,则循环
                        if (is_array($fdata)) {
                            foreach ($fdata as $val) {
                                if ($conditionMet) {
                                    break;
                                }

                                if ($isblmc) {
                                    $fdata1 = $val['hjnr'];
                                } else {
                                    $fdata1 = $val;
                                }

                                if (!empty($delete_field)) {
                                    //如果包含逗号,则按照逗号分隔
                                    if (strpos($delete_field, ",") !== false) {
                                        $delete_field_array = explode(",", $delete_field);
                                        foreach ($delete_field_array as $val) {
                                            $fdata1 = str_replace($val, '', $fdata1);
                                        }
                                    } else {
                                        $fdata1 = str_replace($delete_field, '', $fdata1);
                                    }
                                }
                                if (strpos($content2, ",") !== false) {
                                    $content2Array = explode(",", $content2);
                                    foreach ($content2Array as $value) {
                                        if (empty($value)) {
                                            continue;
                                        }

                                        if (strpos($fdata1, $value) === false) {
                                            $conditionMet = true;
                                        } else {
                                            $conditionMet = false;
                                            break;
                                        }
                                    }
                                } else {
                                    // 如果content2不包含逗号,则直接判断是否不包含
                                    $conditionMet = strpos($fdata1, $content2) === false;
                                    //Log::info('不包含', ['value' => $content2, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                                }
                                //if ($conditionMet && $detailstatus == 2) {
                                if (($conditionMet && $detailstatus == 2) || (!$conditionMet && $detailstatus == 1)) {
                                    $basisData[] = [
                                        'es_name' => $content["param1"][0],
                                        'field_name' => $content["param1"][1],
                                        'fdata' => $val['blmc'] ?? '',
                                        'blmc' => $val['blmc'] ?? '',
                                        'conditionMet' => $conditionMet,
                                        'condition' => $condition
                                    ];
                                    /* if ($info['MED_REC_ID'] == '588028' && $content2 == '*') {
                                            Log::info('basisData111', ['fdata' => $val]);
                                            Log::info('basisData', ['basisData' => $basisData]);
                                        } */
                                }
                                /* }elseif ($conditionMet && $detailstatus == 1) {
                                    $basisData[] = [
                                        'es_name' => $content["param1"][0],
                                        'field_name' => $content["param1"][1],
                                        'fdata' => $fdata1,
                                        'blmc' => $fdata['blmc'] ?? '',
                                        'conditionMet' => $conditionMet,
                                        'condition' => $condition
                                    ];
                                }elseif ($detailstatus == 1 && !$conditionMet) {
                                    $basisData[] = [
                                        'es_name' => $content["param1"][0],
                                        'field_name' => $content["param1"][1],
                                        'fdata' => '',
                                        'blmc' => $fdata['blmc'] ?? '',
                                        'conditionMet' => $conditionMet,
                                        'condition' => $condition
                                    ];
                                }elseif ($detailstatus == 1 && $conditionMet) {
                                    $basisData[] = [
                                        'es_name' => $content["param1"][0],
                                        'field_name' => $content["param1"][1],
                                        'fdata' => $fdata1,
                                        'blmc' => $fdata['blmc'] ?? '',
                                        'conditionMet' => $conditionMet,
                                        'condition' => $condition
                                    ];
                                } */
                            }
                        } else {
                            if (!empty($delete_field)) {
                                //如果包含逗号,则按照逗号分隔
                                if (strpos($delete_field, ",") !== false) {
                                    $delete_field_array = explode(",", $delete_field);
                                    foreach ($delete_field_array as $val) {
                                        $fdata = str_replace($val, '', $fdata);
                                    }
                                } else {
                                    $fdata = str_replace($delete_field, '', $fdata);
                                }
                            }

                            if (strpos($content2, ",") !== false) {
                                $content2Array = explode(",", $content2);
                                foreach ($content2Array as $value) {
                                    //Log::info('不包含', ['value' => $value, 'fdata' => $fdata]);
                                    if (empty($value)) {
                                        continue;
                                    }
                                    if (strpos($fdata, $value) === false) {
                                        $conditionMet = true;
                                    } else {
                                        $conditionMet = false;
                                        break;
                                    }
                                }
                            } else {
                                // 如果content2不包含逗号,则直接判断是否不包含
                                $conditionMet = strpos($fdata, $content2) === false;
                                //Log::info('不包含', ['value' => $content2, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                            }
                            if ($conditionMet && $detailstatus == 2) {
                                $basisData[] = [
                                    'es_name' => $content["param1"][0],
                                    'field_name' => $content["param1"][1],
                                    'fdata' => '',
                                    'blmc' => $fdata['blmc'] ?? '',
                                    'conditionMet' => $conditionMet,
                                    'condition' => $condition
                                ];
                            } elseif ($conditionMet && $detailstatus == 1) {
                                $basisData[] = [
                                    'es_name' => $content["param1"][0],
                                    'field_name' => $content["param1"][1],
                                    'fdata' => $fdata,
                                    'blmc' => $fdata['blmc'] ?? '',
                                    'conditionMet' => $conditionMet,
                                    'condition' => $condition
                                ];
                            } elseif ($detailstatus == 1 && !$conditionMet) {
                                $basisData[] = [
                                    'es_name' => $content["param1"][0],
                                    'field_name' => $content["param1"][1],
                                    'fdata' => '',
                                    'blmc' => $fdata['blmc'] ?? '',
                                    'conditionMet' => $conditionMet,
                                    'condition' => $condition
                                ];
                            } elseif ($detailstatus == 1 && $conditionMet) {
                                $basisData[] = [
                                    'es_name' => $content["param1"][0],
                                    'field_name' => $content["param1"][1],
                                    'fdata' => $fdata,
                                    'blmc' => $fdata['blmc'] ?? '',
                                    'conditionMet' => $conditionMet,
                                    'condition' => $condition
                                ];
                            }
                        }
                    } elseif ($condition == "病程记录关联") {
                        // 初始化条件匹配结果为false
                        $conditionMet = false;
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                        // 获取病程记录数据
                        $indexName = $content["param1"][0]; // 索引名称，例如 bl01_202303
                        $mblbField = $content["param1"][1]; // MBLB字段
                        $blbhField = $content["param1"][2]; // BLBH字段
                        //可能会包含逗号
                        if (strpos($content["param2"], ",") !== false) {
                            $mblbValues = explode(",", $content["param2"]); // 可能包含逗号分隔的多个值
                        } else {
                            $mblbValues = [$content["param2"]];
                        }

                        // 查询条件
                        $must = [];
                        $must[] = ['term' => [$tableDict[$indexName]["zyh_field"] => $info['MED_REC_ID']]];

                        // 处理MBLB条件，支持多值查询
                        $shouldTerms = [];
                        foreach ($mblbValues as $value) {
                            $shouldTerms[] = ['term' => [$mblbField => trim($value)]];
                        }

                        if (count($shouldTerms) == 1) {
                            $must[] = $shouldTerms[0];
                        } else if (count($shouldTerms) > 1) {
                            $must[] = ['bool' => ['should' => $shouldTerms, 'minimum_should_match' => 1]];
                        }

                        try {
                            // 统一入口取数：配置了 mysql_field 的表优先走 MySQL，MBLB 多值条件取数后过滤。
                            $filterMust = [
                                ['term' => [$tableDict[$indexName]["zyh_field"] => $info['MED_REC_ID']]]
                            ];
                            $filterData = $this->getCustomizeRuleFilterData($indexName, $filterMust, $blbhField, $tableDict, $tableFieldDict);

                            if (!empty($filterData[0])) {
                                $mblbValues = array_values(array_filter(array_map('trim', $mblbValues), function ($value) {
                                    return $value !== '';
                                }));

                                $filterData[0] = array_values(array_filter($filterData[0], function ($record) use ($mblbField, $mblbValues) {
                                    if (empty($mblbValues)) {
                                        return true;
                                    }

                                    $recordValue = trim((string)($record[$mblbField] ?? ''));
                                    foreach ($mblbValues as $value) {
                                        if ($recordValue === trim((string)$value)) {
                                            return true;
                                        }
                                    }

                                    return false;
                                }));
                            }
                            Log::info('病程记录', [
                                'must' => $filterMust,
                                'filterData' => $filterData
                            ]);
                            // 如果没有找到匹配的记录，则跳过
                            if (empty($filterData) || empty($filterData[0])) {
                                continue;
                            }

                            // 保存first_blsy_time和BLBH信息
                            $blsyTimeField = $content["param3"][1]; // fisrt_blsy_time字段
                            $blRecords = [];

                            foreach ($filterData[0] as $record) {
                                if (!empty($record[$blsyTimeField]) && !empty($record[$blbhField])) {
                                    $blRecords[] = [
                                        'blsy_time' => $record[$blsyTimeField],
                                        'blbh' => $record[$blbhField]
                                    ];
                                    // 添加到basisData,保存es索引名和字段名和对应的数据，储存病程记录时间字段和病程记录编号
                                    $basisData[] = [
                                        'es_name' => $indexName,
                                        'field_name' => $blsyTimeField,
                                        'fdata' => $record[$blsyTimeField],
                                        'conditionMet' => $conditionMet,
                                        'condition' => $condition
                                    ];
                                }
                            }

                            // 如果没有有效的病程记录，则跳过
                            if (empty($blRecords)) {
                                continue;
                            }

                            // 获取param4参数
                            $param4 = $content["param4"];
                            $matchedRecords = [];

                            // 处理每个param4条件,param4只有一条,只是根据里面的条件和所有有可能查出多条,所以不使用foreach
                            foreach ($param4 as $p4) {
                                $kssj = $p4['kssj']; // 开始时间字段
                                $jssj = $p4['jssj']; // 结束时间字段
                                $sjjg = $p4['sjjg']; // 时间间隔(小时)
                                $params1 = $p4['params1']; // 需要获取的字段
                                $condition1 = $p4['condition1']; // 需要包含的关键词
                                $condition2 = $p4['condition2']; // 需要排除的关键词
                                $zj = $p4['zj']; // 关联字段

                                // 解析索引和字段
                                $tiaojian = explode('.', $param1)[1];
                                list($indexName, $fieldName) = explode('.', $kssj);
                                list($endTimeIndex, $endTimeField) = explode('.', $jssj);
                                // 处理包含条件,可能会包含逗号
                                $shouldTerms1 = [];
                                if (!empty($condition1)) {
                                    // 如果包含逗号,则按照逗号分隔
                                    if (strpos($condition1, ",") !== false) {
                                        $condition1Values = explode(',', $condition1);
                                        foreach ($condition1Values as $val) {
                                            $val = trim($val);
                                            if (!empty($val)) {
                                                $shouldTerms1[] = ['match' => [$tiaojian => $val]];
                                            }
                                        }
                                    } else {
                                        $shouldTerms1[] = ['match' => [$tiaojian => $condition1]];
                                    }
                                }

                                // 处理排除条件
                                $mustNotTerms = [];
                                if (!empty($codition2)) {
                                    // 如果包含逗号,则按照逗号分隔
                                    if (strpos($condition2, ",") !== false) {
                                        $condition2Values = explode(',', $condition2);
                                        foreach ($condition2Values as $val) {
                                            $val = trim($val);
                                            if (!empty($val)) {
                                                $mustNotTerms[] = ['match' => [$tiaojian => $val]];
                                            }
                                        }
                                    } else {
                                        $mustNotTerms[] = ['match' => [$tiaojian => $condition2]];
                                    }
                                }

                                // 统一入口取数：配置了 mysql_field 的表优先走 MySQL，包含/排除条件取数后过滤。
                                $checkMust = [
                                    ['term' => [$tableDict[$indexName]["zyh_field"] => $info['MED_REC_ID']]]
                                ];
                                $checkData = $this->getCustomizeRuleFilterData($indexName, $checkMust, $tiaojian, $tableDict, $tableFieldDict);

                                $includeValues = [];
                                foreach ($shouldTerms1 as $term) {
                                    if (!empty($term['match']) && is_array($term['match'])) {
                                        $includeValues[] = trim((string)reset($term['match']));
                                    }
                                }
                                $includeValues = array_values(array_filter($includeValues, function ($value) {
                                    return $value !== '';
                                }));

                                $excludeValues = [];
                                foreach ($mustNotTerms as $term) {
                                    if (!empty($term['match']) && is_array($term['match'])) {
                                        $excludeValues[] = trim((string)reset($term['match']));
                                    }
                                }
                                $excludeValues = array_values(array_filter($excludeValues, function ($value) {
                                    return $value !== '';
                                }));

                                if (!empty($checkData[0])) {
                                    $checkData[0] = array_values(array_filter($checkData[0], function ($record) use ($tiaojian, $includeValues, $excludeValues) {
                                        $fieldValue = (string)($record[$tiaojian] ?? '');

                                        if (!empty($includeValues)) {
                                            $includeMatched = false;
                                            foreach ($includeValues as $value) {
                                                if (strpos($fieldValue, $value) !== false) {
                                                    $includeMatched = true;
                                                    break;
                                                }
                                            }

                                            if (!$includeMatched) {
                                                return false;
                                            }
                                        }

                                        foreach ($excludeValues as $value) {
                                            if (strpos($fieldValue, $value) !== false) {
                                                return false;
                                            }
                                        }

                                        return true;
                                    }));
                                }
                                Log::info('病程记录检查数据', [
                                    'must' => $checkMust,
                                    'checkData' => $checkData
                                ]);
                                // 如果没有找到匹配的记录，则继续下一个
                                if (empty($checkData) || empty($checkData[0])) {
                                    continue;
                                }

                                // 对于每条检查记录，检查是否有病程记录在时间范围内
                                foreach ($checkData[0] as $checkRecord) {
                                    // 获取开始和结束时间
                                    $startTime = strtotime($checkRecord[$fieldName]);
                                    $endTime = strtotime($checkRecord[$endTimeField]);

                                    // 如果有时间间隔，加到结束时间上
                                    if (!empty($sjjg)) {
                                        $endTime = $startTime + (intval($sjjg) * 3600); // 转换为秒
                                    }

                                    // 检查每条病程记录是否在时间范围内
                                    foreach ($blRecords as $blRecord) {
                                        $blsyTime = strtotime($blRecord['blsy_time']);

                                        // 检查病程记录时间是否在范围内
                                        if ($blsyTime >= $startTime && $blsyTime <= $endTime) {
                                            // 保存匹配记录的信息
                                            $matchedRecords[] = [
                                                'blbh' => $blRecord['blbh'],
                                                'checkBLBH' => isset($checkRecord[$params1]) ? $checkRecord[$params1] : '',
                                                'relation_type' => $content["param5"] // gl或bgl
                                            ];
                                            // 添加到basisData,保存es索引名和字段名和对应的数据，储存病程记录编号和病程记录时间字段
                                            $basisData[] = [
                                                'es_name' => $indexName,
                                                'field_name' => $blsyTimeField,
                                                'fdata' => $blRecord['blsy_time'],
                                                'conditionMet' => $conditionMet,
                                                'condition' => $condition
                                            ];
                                        }
                                    }
                                }
                            }

                            // 如果找到了匹配的记录，检查是否满足关联条件
                            Log::info('病程记录匹配记录', [
                                'matchedRecords' => $matchedRecords
                            ]);
                            if (!empty($matchedRecords)) {
                                $param5 = $content["param5"]; // gl(关联)或bgl(不关联)
                                $param6 = $content["param6"]; // [索引, 包含条件, 不包含条件]

                                $indexName = $param6[0];
                                list($indexName, $hjnrField) = explode('.', $indexName);
                                //如果包含逗号,则按照逗号分隔
                                if (strpos($param6[1], ",") !== false) {
                                    $includeTerms = explode(',', $param6[1]);
                                } else {
                                    $includeTerms = [$param6[1]];
                                }
                                if (strpos($param6[2], ",") !== false) {
                                    $excludeTerms = explode(',', $param6[2]);
                                } else {
                                    $excludeTerms = [$param6[2]];
                                }

                                // 处理每条匹配记录
                                foreach ($matchedRecords as $record) {
                                    // 构建统一取数条件：配置了 mysql_field 的表优先走 MySQL。
                                    $hjnrMust = [
                                        ['term' => [$tableDict[$indexName]["zyh_field"] => $info['MED_REC_ID']]]
                                    ];

                                    // 如果是关联(gl)模式，添加BLBH条件
                                    if ($param5 == 'gl' && !empty($record['blbh'])) {
                                        $hjnrMust[] = ['term' => [$blbhField => $record['blbh']]];
                                    }

                                    $hjnrData = $this->getCustomizeRuleFilterData($indexName, $hjnrMust, $hjnrField, $tableDict, $tableFieldDict);

                                    // 如果没有找到病历内容，则继续下一个
                                    if (empty($hjnrData) || empty($hjnrData[0]) || empty($hjnrData[0][0]) || empty($hjnrData[0][0][$hjnrField])) {
                                        continue;
                                    }

                                    $hjnr = $hjnrData[0][0][$hjnrField];
                                    $passInclude = true;
                                    $passExclude = true;

                                    // 检查是否包含需要的关键词
                                    if (!empty($includeTerms)) {
                                        $passInclude = false;
                                        //是否是数组
                                        if (is_array($includeTerms)) {
                                            foreach ($includeTerms as $term) {
                                                $term = trim($term);
                                                if (!empty($term) && strpos($hjnr, $term) !== false) {
                                                    $passInclude = true;
                                                    break;
                                                }
                                            }
                                        } else {
                                            if (!empty($includeTerms) && strpos($hjnr, $includeTerms) !== false) {
                                                $passInclude = true;
                                            }
                                        }
                                    }

                                    // 检查是否不包含需要排除的关键词
                                    if (!empty($excludeTerms)) {
                                        $passExclude = false;
                                        //是否是数组
                                        if (is_array($excludeTerms)) {
                                            foreach ($excludeTerms as $term) {
                                                $term = trim($term);
                                                if (!empty($term) && strpos($hjnr, $term) !== false) {
                                                    $passExclude = false;
                                                    break;
                                                }
                                            }
                                        } else {
                                            if (!empty($excludeTerms) && strpos($hjnr, $excludeTerms) !== false) {
                                                $passExclude = false;
                                            }
                                        }
                                    }

                                    // 如果同时满足包含和排除条件，则条件满足
                                    if ($passInclude && $passExclude) {
                                        $conditionMet = true;
                                        break; // 只要有一条记录满足条件就可以
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            // 记录错误日志
                            Log::error('病程记录关联规则执行错误', [
                                'error' => $e->getMessage(),
                                'content' => $content,
                                'info' => $info
                            ]);
                            continue;
                        }
                    } elseif ($condition == "减") {
                        // 获取第二个参数的值
                        $p2 = [$content['subtract_param'][0], $content['subtract_param'][1]];
                        $p2Must = [['term' => [$tableDict[$p2[0]]["zyh_field"] => $info['MED_REC_ID']]]];
                        $filData = $this->getCustomizeRuleFilterData($p2[0], $p2Must, $p2[1], $tableDict, $tableFieldDict);

                        if (empty($filData) || empty($filData[0]) || empty($filData[0][0])) {
                            continue;
                        }

                        $value2 = $filData[0][0][$p2[1]];

                        // 计算差值
                        if ($content['subtract_value_type'] == 'hour' || $content['subtract_value_type'] == 'minute') {
                            // 时间差值计算
                            $time1 = strtotime($fdata);
                            $time2 = strtotime($value2);
                            $diffSeconds = abs($time1 - $time2);

                            if ($content['subtract_value_type'] == 'hour') {
                                $diff = $diffSeconds / 3600; // 转换为小时
                            } else {
                                $diff = $diffSeconds / 60; // 转换为分钟
                            }
                        } else {
                            // 数值差值计算
                            $diff = abs(floatval($fdata) - floatval($value2));
                        }

                        // 根据比较条件判断
                        switch ($content['subtract_condition']) {
                            case 'gt':
                                $conditionMet = $diff > floatval($content['subtract_value']);
                                break;
                            case 'lt':
                                $conditionMet = $diff < floatval($content['subtract_value']);
                                break;
                            case 'eq':
                                $conditionMet = abs($diff - floatval($content['subtract_value'])) < 0.000001; // 浮点数比较
                                break;
                            default:
                                $conditionMet = false;
                        }
                    } else {
                        $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                        //如果是6岁8月这种格式，转换成6.8这样的数字
                        if (is_string($fdata) && preg_match('/(\d+)岁(\d+)月/', $fdata, $matches)) {
                            $years = intval($matches[1]);
                            $months = intval($matches[2]);
                            $fdata = $years + ($months / 12); // 转换为类似6.8的格式
                        }
                        //如果是45岁这种格式，转换成45这样的数字
                        if (is_string($fdata) && preg_match('/(\d+)岁/', $fdata, $matches)) {
                            $fdata = intval($matches[1]);
                        }
                        $conditionMet = ($condition == "等于" && $fdata == $content2) ||
                            ($condition == "不等于" && $fdata != $content2) ||
                            ($condition == "大于" && $fdata > $content2) ||
                            ($condition == "小于" && $fdata < $content2) ||
                            ($condition == "大于等于" && $fdata >= $content2) ||
                            ($condition == "小于等于" && $fdata <= $content2) ||
                            ($condition == "不为空" && $this->hasRuleValue($fdata));
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        if ($conditionMet && $detailstatus == 2) {
                            $basisData[] = [
                                'es_name' => $content["param1"][0],
                                'field_name' => $content["param1"][1],
                                'fdata' => $fdata,
                                'blmc' => $blmc ?? '',
                                'conditionMet' => $conditionMet,
                                'condition' => $condition
                            ];
                        } elseif ($detailstatus == 1 && !$conditionMet) {
                            $basisData[] = [
                                'es_name' => $content["param1"][0],
                                'field_name' => $content["param1"][1],
                                'fdata' => $fdata,
                                'blmc' => $blmc ?? '',
                                'conditionMet' => $conditionMet,
                                'condition' => $condition
                            ];
                        }
                    }

                    // 条件状态：1正确（条件不符合质控），2错误（条件符合质控）
                    if ($item['detail_status'] == 1) {
                        if (!$conditionMet) {
                            $ruleFlag = 0;
                            if ($item['condition_relation'] == 1) {
                                break;
                            }
                        } else {
                            $ruleFlag = 1;

                            if ($item['condition_relation'] == 2) {
                                //根据条件判断不满足就删除对应的basisData
                                break;
                            }
                        }
                    } elseif ($item['detail_status'] == 2) {
                        if ($conditionMet) {
                            $ruleFlag = 0;
                            if ($item['condition_relation'] == 2) {
                                break;
                            }
                        } else {
                            $ruleFlag = 1;
                            if ($item['condition_relation'] == 1) {
                                break;
                            }
                        }
                    }
                }
            }

            if ($preWarningTime > 0 && !empty($qtTime)) {
                // 如果 customMsg 中有 aftertime，则将其加到 qtTime 上
                if (!empty($customMsg['aftertime'])) {
                    $qtTime = date('Y-m-d H:i:s', strtotime($qtTime) + $customMsg['aftertime'] * 60);
                } else {
                    $qtTime = date('Y-m-d H:i:s', strtotime($qtTime));
                }

                // 计算剩余时间（分钟）
                $currentTime = time();
                $qtTimestamp = strtotime($qtTime);
                $timeDifference = ($qtTimestamp - $currentTime) / 60; // 转换为分钟


                // 如果剩余时间小于预警时间，则发送预警信息
                if ($timeDifference < $preWarningTime && $timeDifference > 0) {
                    //$remainingTime = round($timeDifference); // 剩余时间取整
                    //remainingTime转换成多少小时多少分钟得字符串
                    $remainingTimeStr = remainderTime($timeDifference * 60);
                    $msg = $customMsg['input1'] . $remainingTimeStr . $customMsg['input2'];

                    // 处理包含 HTML 标签的数组，转换成简单字符串数组
                    $formattedBasis = [];
                    foreach ($basis as $item) {
                        if (is_array($item) && !empty($item[0])) {
                            // 去除 HTML 标签并清理空格
                            $text = trim(strip_tags($item[0]));
                            if ($text) {
                                $formattedBasis[] = $text;
                            }
                        }
                    }
                    $this->sendMsg($info['MED_REC_ID'], $msg, $currentRuleId + 1000000, $formattedBasis);
                }
            }
            if ($ruleFlag == 0) {
                //Log::info('测试包含', ['customBasis-v' => $customBasis]);
                $basis = [];
                if (!empty($customBasis)) {
                    // 根据fdata字段对$basisData进行去重
                    $uniqueBasisData = [];
                    $seenFdata = [];
                    foreach ($basisData as $item) {
                        $fdataKey = is_array($item['fdata']) ? serialize($item['fdata']) : $item['fdata'];
                        if (!in_array($fdataKey, $seenFdata)) {
                            $seenFdata[] = $fdataKey;
                            $uniqueBasisData[] = $item;
                        }
                    }
                    $basisData = $uniqueBasisData;

                    foreach ($customBasis as $cb) {
                        if ($info['MED_REC_ID'] == '588028') {
                            Log::info('basisData22222', ['basisData' => $basisData]);
                            Log::info('basis33333', ['customBasis' => $cb]);
                        }
                        //读取basisData中的数据，设置相对应数据
                        //Log::info('测试包含', ['basisData-v' => $basisData]);
                        foreach ($basisData as $basisdata) {
                            if (!empty($cb['param1']) && $basisdata['es_name'] == $cb['param1'][0] && $basisdata['field_name'] == $cb['param1'][1]) {
                                //如果$basis['fdata']长度过长(超过20个汉字)就只展示一部分
                                $con = is_array($basisdata['fdata']) ? $basisdata['fdata'][0] : $basisdata['fdata'];
                                if (mb_strlen($con) > 20) {
                                    $con = mb_substr($con, 0, 20, 'utf-8') . '...';
                                }
                                $input1 = '';
                                if (!empty($cb['input1'])) {
                                    $input1 = $cb['input1'];
                                } else {
                                    $input1 = '';
                                }

                                // 确保basis始终是数组格式，不会变成false
                                if (!is_array($basis)) {
                                    $basis = [];
                                }

                                if (!empty($basisdata['blmc'])) {
                                    $str = '病程【' . $basisdata['blmc'] . '】' . $cb['input2'];
                                    $basis[] = $str;
                                } else {
                                    $str = $input1 . '【' . $con . '】' . $cb['input2'];
                                    $basis[] = $str;
                                }
                                //Log::info('测试包含', ['basis-v' => $basis, 'basis_type' => gettype($basis)]);
                            } else {
                                $input1 = '';
                                if (!empty($cb['input1'])) {
                                    $input1 = $cb['input1'];
                                } else {
                                    $input1 = '';
                                }

                                $str = $input1 . '' . $cb['input2'];
                                $basis[] = $str;
                            }
                        }
                        //}
                    }
                }
                try {
                    // 确保在json_encode之前basis是有效的数组
                    if (!is_array($basis)) {
                        $basis = [];
                        //Log::warning('basis不是数组类型，已重置为空数组', ['basis_original' => $basis]);
                    }

                    $errorNotices[] = [
                        'basis' => json_encode($basis, JSON_UNESCAPED_UNICODE),
                        'JZHM' => $info['MED_REC_ID'],
                        'rule_id' => $currentRuleId,
                        'code' => '',
                        'error_field' => ""
                    ];
                    //Log::info('测试包含', ['errorNotices-v' => $errorNotices, 'basis_final' => $basis]);
                } catch (\Exception $e) {
                    Log::error('测试包含Error creating error notice', [
                        'error' => $e->getMessage(),
                        'rule_id' => $currentRuleId,
                        'info' => $info
                    ]);
                }
            }
        }
        $errorNotice = array_filter($errorNotices);
        $appealids = [];
        if ($errorNotice) {
            foreach ($errorNotices as $v) {
                try {
                    $v['rule_id'] = $v['rule_id'] + 1000000;
                    if ($isSz == 99 || $isSz == 991) {
                        CaseQualityZm::addData($v);
                    } else {
                        $appeal1 = Appeal::where('ZYH', $v['JZHM'])->where('error_id', $v['rule_id'])->get()->toArray();
                        //Log::info('appeal1--ruleids-->', $appeal1);
                        if (!empty($appeal1)) {
                            $v['appeal_id'] = $appeal1[0]['id'];
                            $v['is_appeal'] = 1;
                            $appealids[] = $appeal1[0]['id'];
                        } else {
                            $v['appeal_id'] = 0;
                            $v['is_appeal'] = 0;
                        }
                        CaseQuality::addData($v);
                        if ($isSz == 1 || $isSz == 4) {
                            $this->saveShizhongQualityRecord((string)$v['JZHM'], (int)$v['rule_id']);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('测试包含CaseQuality addData error', [
                        'error' => $e->getMessage(),
                        'data' => $v,
                        'rule_id' => $v['rule_id']
                    ]);
                }
            }
        }
        //只取rulesetting id+1000000
        $ruleSettingIds = RuleSetting::query()->where("rule_type", "普通规则")->get()->toArray();
        $ruleSettingIds = array_column($ruleSettingIds, 'id');
        $ruleSettingIds = array_map(function ($id) {
            return $id + 1000000;
        }, $ruleSettingIds);
        if (empty($appealids)) {
            Appeal::where('ZYH', $info['MED_REC_ID'])->whereIn('error_id', $ruleSettingIds)->where('status', '!=', '1')->update(['status' => 3]);
        } else {
            Appeal::whereNotIn('id', is_array($appealids) ? $appealids : [$appealids])->where('ZYH', $info['MED_REC_ID'])->whereIn('error_id', $ruleSettingIds)->where('status', '!=', '1')->update(['status' => 3]);
        }

        return 1;
    }

    /**
     * 保存事中质控触发记录
     *
     * @param string $jzhm
     * @param int $ruleId
     * @return bool
     */
    private function saveShizhongQualityRecord($jzhm = '', $ruleId = 0)
    {
        if ($jzhm === '' || empty($ruleId)) {
            return false;
        }

        $brry = ZY_BRRY::query()->where('ZYH', '=', $jzhm)->first();
        if (empty($brry)) {
            return false;
        }

        $brryData = $brry->toArray();
        $doctorName = empty($brryData['GCYSMC']) ? '' : $brryData['GCYSMC'];
        $doctorCode = empty($brryData['GCYSDM']) ? '' : $brryData['GCYSDM'];
        $residentDoctor = $doctorName;
        if ($doctorName !== '' && $doctorCode !== '') {
            $residentDoctor = $doctorName . '(' . $doctorCode . ')';
        } elseif ($doctorCode !== '') {
            $residentDoctor = $doctorCode;
        }

        return CaseQualityShizhongRecord::addData([
            'jzhm' => $jzhm,
            'rule_id' => $ruleId,
            'department' => empty($brryData['BRKS']) ? '' : $brryData['BRKS'],
            'lock_count' => 1,
            'resident_doctor' => $residentDoctor,
            'medical_record_no' => empty($brryData['AAA28']) ? '' : $brryData['AAA28'],
            'patient_name' => empty($brryData['BRXM']) ? (empty($brryData['XM']) ? '' : $brryData['XM']) : $brryData['BRXM'],
            'bed_no' => empty($brryData['CH']) ? '' : $brryData['CH'],
            'last_quality_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 清理字符串中的非法 UTF-8 字符，确保可被 json_encode 处理
     * @param string $str 待处理的字符串
     * @return string 清理后的字符串
     */
    function gbk_to_utf8_safe($str)
    {
        // 步骤1：判断是否已为UTF-8（避免重复转换）
        if (mb_check_encoding($str, 'UTF-8')) {
            return $str;
        }

        // 步骤2：强制尝试GBK→UTF-8转换（医疗数据多为GBK）
        $utf8_str = iconv('GBK', 'UTF-8//IGNORE', $str);
        // iconv的//IGNORE参数会忽略无法转换的非法字符，避免整体失败

        // 步骤3：验证转换结果，若失败则用mb_convert_encoding重试
        if (!mb_check_encoding($utf8_str, 'UTF-8')) {
            $utf8_str = mb_convert_encoding($str, 'UTF-8', 'GBK');
        }

        // 步骤4：清理残留的不可见控制字符（避免json_encode问题）
        $utf8_str = preg_replace('/[\x00-\x1F\x7F]/', '', $utf8_str);

        return $utf8_str;
    }

    /**
     * 病例质控的所有指标集合
     */
    public function quality($caseRule = [], $info = [])
    {
        $errorNotice = [];

        $yzbesService = new ElasticsearchService('yzb_2023');
        $params = $yzbesService->clearMust()
            ->queryByMust(['terms' => ['YZZT' => $this->yzzt]])
            ->queryByMust(['term' => ['ZYH' => $info['MED_REC_ID']]])
            ->queryByMust(['terms' => ['YDYZLB' => [303, 305]]])
            ->getParams();
        $res = app('es')->search($params);
        $resData = $yzbesService->getDataByEs($res);
        if (!empty($resData[1])) {
            $yzb = $resData[0][0];
            $info['AAC01'] = $yzb['XZJDSJ']; // 住院结束时间
        }

        $method = [
            //101 => 'checkRy8', // 入院后8小时内完成首次病程记录
            102 => 'checkCt', // 检查报告单有ct（OCT除外）结果，24小时内要写病程记录
            103 => 'checkMr', // 检查报告单有mr结果，24小时内要写病程记录，或 24小时内出入院记录：关键字 "MR” 或 “磁共振”
            //            109 => 'kjy', // 有抗菌药，开嘱时间+24小时 内要有病程记录
            110 => 'hly', // 有化疗药，开嘱时间+24小时 内要有病程记录
            111 => 'sq24', // 术前24小时内，有术者查房（搜签名）
            112 => 'sh24', // 术后24小时内，有术者查房（搜签名）
            115 => 'rule115', // 术前小结及术前讨论结论记录 在医嘱之前
            116 => 'rule116', // 术后即刻完成（当天完成）
            118 => 'rule118', // 术后3天连续病程记录
            119 => 'rule119',  // 会诊记录，当天完成
            121 => 'rule121', // 阶段小结
            122 => 'rule122', // 由转入科室医师于患者转入后24小时内完成
            123 => 'rule123', // 抢救记录在开嘱6小时内
            124 => 'rule124', // 输血记录 - 输血当天要有病程记录
            125 => 'rule125', // 出院上级医师查房记录，出院之前    (出院当天或出院前一天  ），需要写一个病程（首次病程除外）或 24小时出入院
            133 => 'rule133', // 医嘱中 YDYZLB=901 医嘱名称中含“病危” ，  XZJDSJ 开始 - TZQRSJ 止，每1天有病程  （包含当天）             病程记录：通过 表：EMR_BL_BL01 中【BLLB =294 且 mblb=32的剔除且 blzt不等于9】中【blbh】去
            229 => 'rule229', // 75%的相同病程
            234 => 'rule234', // 出院记录书写不规范
            237 => 'rule237', // 出院记录中“住院天数”书写不正确
            235 => 'rule235', // 首次病程缺少医师签名
            238 => 'rule238', //
            239 => 'rule239', //
            241 => 'rule241', // 无医师签名
            244 => 'rule244', // 参保人员住院协议中“身份证号”未填写
            245 => 'rule245', // 核实上级医师审核日期
            258 => 'rule258', // 无纸化归档率
            259 => 'rule259', // 无纸化归档完整率
            257 => 'rule257', // 【术前小结及术前讨论结论记录】 在【手术知情同意书】之前
            260 => 'rule260', // 【术前小结及术前讨论结论记录】在【手术医嘱】之前
            261 => 'rule261', // 【手术知情同意书】在【手术医嘱】之前
            262 => 'rule262', // 医嘱【备血（第一次的就可以）】开嘱时间，之前签【输血知情同意书】创建时间
            263 => 'rule263', // 病理报告结果 24小时有病程
            264 => 'rule264', // 有放疗药，没有病程记录
            265 => 'rule265', // 不合理复制率不能连续20个字一样
            99999 => 'tiwen', // 检查病程中的体温是否有上升趋势，临时使用，发送钉钉消息提醒
        ];

        foreach ($method as $ruleId => $m) {
            // 申诉审核中、审核通过的则不在质控
            if (empty($caseRule[$ruleId]['status']) || in_array($ruleId, $this->appealRuleIds)) {
                continue;
            }
            Log::info("质控规则：{$m}");
            $qualityRes = $this->$m($info);
            $errorNotice[] = $qualityRes;
        }

        $errorNotice = array_filter($errorNotice);
        if ($errorNotice) {
            foreach ($errorNotice as $v) {
                CaseQuality::addData($v);
            }
        }
    }

    /**
     * @param array $info
     * @return array
     * 检查体温变化
     */
    public function tiwen($info = [])
    {
        // 获取所有日常病程记录，BLLB=294
        $bllb294 = EMR_BL_BL01::query()->where('JZHM', '=', $info['MED_REC_ID'])->get(['BLBH'])->toArray();
        // 病程记录小于2条，不检查体温变化
        if (count($bllb294) < 2) {
            return [];
        }
        // 获取入院记录-体格检查中的体温
        $bllb292 = Bllb292::query()->where('ZYH', '=', $info['MED_REC_ID'])->get()->toArray();
        if (empty($bllb292)) {
            return [];
        }
        $pattern = "/[体温:|T]+(\d+\.\d+)℃/";
        preg_match_all($pattern, $bllb292[0]['TGJC'], $tiwen);
        if (empty($tiwen[1])) {
            return [];
        }
        $temperature = $tiwen[1][0] ?? 0;

        // 获取病程内容
        $blbh = array_column($bllb294, 'BLBH');
        $blxg = EMR_BL_BLXG::query()->whereIn('BLBH', $blbh)->get(['HJNR'])->toArray();
        $temperatureChange = [];
        foreach ($blxg as $item) {
            preg_match_all($pattern, $item['HJNR'], $tiwen1);
            $temperature1 = $tiwen1[1][0] ?? 0;
            if ($temperature1) {
                $temperatureChange[] = $temperature1;
            }
        }
        sort($temperatureChange);
        if ($temperatureChange && max($temperatureChange) > $temperature) {
            $content = '体温发生变化:' . implode('->', $temperatureChange);

            $msgYj = ['入院记录体温【' . $temperature . '】', '病程记录体温【' . implode(',', $temperatureChange) . '】'];

            $this->sendMsg($info['MED_REC_ID'], $content, 99999, $msgYj);
        }
    }

    public function rule259($info)
    {
        $bl01Service = new ElasticsearchService('bl01_202303');
        // 获取规则
        $errorNotice = [];
        $caseRule = $this->caseRule;

        $AAA28 = $info['AAA28'];
        $ZYH = $info['MED_REC_ID'];
        $AAB01 = $info['AAB01'];
        $AAA29 = $info['AAA29'];

        $numerator = 1;
        $cyhzgdlError = '';

        // 病案首页
        $bl01NewData = EMR_BL_BL01_NEW::query()->where('JZHM', '=', (string)$ZYH)->get()->toArray();
        $content = '';
        if (empty($bl01NewData[0])) {
            $content .= '【病案首页】';
        }

        // 出院记录（或 24小时出入院记录 或 死亡记录）
        $must = [['term' => ["JZHM" => $ZYH]]];
        $should = [['term' => ["BLLB" => 1]], ['term' => ["BLLB" => 288]]];
        $cyjl = $this->bl01Data($bl01Service, $must, $should);
        if (empty($cyjl)) {
            $must = [
                ['term' => ["JZHM" => $ZYH]],
                ['term' => ["BLLB" => 18]],
                ['match_phrase' => ["HJNR" => '出院记录']]
            ];
            $cyjl = $this->bl01Data($bl01Service, $must);
        }
        if (empty($cyjl)) {
            $content .= '【出院记录】';
        }

        // 入院记录（或 24小时出入院记录）
        $must = [['term' => ["JZHM" => $ZYH]], ['term' => ["BLLB" => 292]]];
        $ryjl = $this->bl01Data($bl01Service, $must);
        if (empty($ryjl)) {
            $must = [
                ['term' => ["JZHM" => $ZYH]],
                ['term' => ["BLLB" => 18]],
                ['match_phrase' => ["HJNR" => '入院记录']]
            ];
            $ryjl = $this->bl01Data($bl01Service, $must);
        }
        if (empty($ryjl)) {
            $content .= '【入院记录】';
        }

        // 病程记录
        $must = [['term' => ["JZHM" => $ZYH]], ['term' => ["BLLB" => 294]]];
        $bcjl = $this->bl01Data($bl01Service, $must);
        if (empty($bcjl)) {
            $content .= '【病程记录】';
        }

        // 手术记录
        $must = [['term' => ["JZHM" => $ZYH]], ['term' => ["BLLB" => 303]]];
        $should = [['term' => ['MBLB' => 306]], ['term' => ['MBLB' => 74]]];
        $ssjl = $this->bl01Data($bl01Service, $must, $should);
        if (empty($ssjl)) {
            $content .= '【手术记录】';
        }

        // 手术麻醉相关记录（麻醉记录单）
        $mzjl = Mzjl::query()->where('HOSPIZATIONID', '=', $ZYH)->get()->toArray();
        if (empty($mzjl[0])) {
            $content .= '【手术麻醉相关记录】';
        }

        // 知情同意书
        $must = [['term' => ["JZHM" => $ZYH]], ['term' => ["BLLB" => 329]]];
        $zqtys = $this->bl01Data($bl01Service, $must);
        if (!empty($zqtys)) {
            $content .= '【知情同意书】';
        }

        // 检验（检验报告单）
        $jybgd = V_JMGS_YMresult::query()->where(['ZYH' => $ZYH, 'STAYHOSPITALMODE' => 2])->get()->toArray();
        if (!empty($jybgd)) {
            $content .= '【检验报告单】';
        }

        // 医嘱
        $yzbService = new ElasticsearchService('yzb_2023');
        $must = [['term' => ['ZYH' => $ZYH]]];
        $params = $yzbService->clearMust()
            ->queryByMustBatch($must)
            ->queryByMust(['terms' => ['YZZT' => $this->yzzt]])
            ->getParams();
        $restful = app('es')->search($params);
        $yz = $yzbService->getDataByEs($restful);
        if (empty($yz[0])) {
            $content .= '【医嘱】';
        }

        return $errorNotice;
    }

    protected function bl01Data($bl01Service, $must = [], $should = [])
    {
        if ($should) {
            $params = $bl01Service->clearMust()
                ->queryByMustBatch($must)
                ->queryByShouldBatch($should)
                ->minimumShouldMatch()
                ->getParams();
        } else {
            $params = $bl01Service->clearMust()->queryByMustBatch($must)->getParams();
        }

        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);

        return !empty($bl01Data[0]) ? $bl01Data[0] : [];
    }


    protected function baMrClassNumber($AAA28, $AAA29, $MrClass)
    {
        $bmcnData = BA_MR_CLASS_NUMBER::query()->where(["patient_id" => $AAA28, "visit_id" => $AAA29, "MrClass" => $MrClass])->get()->toArray();
        return $bmcnData ?: [];
    }

    public function rule258($info)
    {
        // 获取规则
        $ruleId = 258;
        $errorNotice = [];
        $caseRule = $this->caseRule;

        $AAA28 = $info['AAA28'];
        $AAC01 = $info['AAC01'];

        $baReceive = BA_RECEIVE::query()
            ->where('bah', '=', $AAA28)
            ->where('cysj', '=', $AAC01)
            ->whereNotNull('MaxCheckTime')
            ->get()->toArray();

        // 查询归档时间
        $bgsj = strtotime($AAC01) + 48 * 3600;
        if (empty($baReceive)) {

            // 检查是否举例完成时间小于两小时
            $diffTime = $bgsj - time();
            if ($diffTime < $this->diffHoure * 3600 && $diffTime > 0 && empty($resData[1])) {
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内完成无纸化归档';
                $msgYj = ['出院时间【' . $AAC01 . '】', '归档时间【无】'];
                $this->sendMsg($info['MED_REC_ID'], $content, 258, $msgYj);
            }

            $errorNotice = [
                'basis' => json_encode(['出院时间【' . $AAC01 . '】,归档时间【无】'], 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => $ruleId,
                'code' => 'bingcheng_first',
                'error_field' => $caseRule[$ruleId]['title']
            ];
        } else {
            $this->setSendLogStatus($info['MED_REC_ID'], $ruleId, 1);
        }
        return $errorNotice;
    }

    /**
     * 指令
     * php artisan command:quality-bl01 bl01294
     *
     */
    public function rule245($info = [])
    {
        // 获取规则
        $caseRule = $this->caseRule;
        $bl01Res = EMR_BL_BL01::query()
            ->leftJoin("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.BLZT', '<>', 9)
            ->where('EMR_BL_BL01.BLLB', '=', 294)
            ->where('EMR_BL_BL01.JZHM', '=', $info['MED_REC_ID'])
            ->get(['EMR_BL_BL01.JZHM', 'EMR_BL_BL01.BLMC', 'EMR_BL_BL01.BLBH', 'EMR_BL_BL01.BRBH', 'EMR_BL_BLXG.HJNR', 'EMR_BL_BL01.ZXSJ'])
            ->toArray();
        if (empty($bl01Res)) {
            return [];
        }

        $errorNotice = [];
        foreach ($bl01Res as $item) {
            $ZXSJ = date("Y年m月d日", strtotime($item['ZXSJ']));
            if (strpos($item['HJNR'], '上级医师审核日期：') === false) {
                continue;
            }
            if (strpos($item['HJNR'], '上级医师审核日期：' . $ZXSJ) === false) {
                $errorNotice = [
                    'basis' => json_encode([["【{$item['BLMC']}】请核实上级医师审核日期"]], 256),
                    'JZHM' => $item['JZHM'],
                    'rule_id' => 245,
                    'code' => '',
                    'error_field' => $caseRule[245]['title'],
                ];
            }
        }
        return $errorNotice;
    }

    /**
     * 指令
     * php artisan command:quality-bl01 bl01329
     *
     */
    public function rule244($info = [])
    {
        // 年龄小于等于6岁不质控
        $AAA04 = $info['AAA04'] ?? 0;
        if ($AAA04 <= 6) {
            return [];
        }

        // 获取规则
        $caseRule = $this->caseRule;

        $bl01Res = EMR_BL_BL01::query()
            ->join('EMR_BL_BLXG as xg', 'EMR_BL_BL01.BLBH', '=', 'xg.BLBH')
            ->where('EMR_BL_BL01.BLZT', '<>', 9)
            ->where('EMR_BL_BL01.BLLB', '=', 329)
            ->where('EMR_BL_BL01.JZHM', '=', $info['MED_REC_ID'])
            ->get(['EMR_BL_BL01.JZHM', 'EMR_BL_BL01.BLBH', 'EMR_BL_BL01.BRBH', 'xg.HJNR'])
            ->toArray();
        if (empty($bl01Res)) {
            return [];
        }

        $errorNotice = [];
        foreach ($bl01Res as $item) {
            if (strpos($item['HJNR'], '烟台市参保人员住院协议') === false) {
                continue;
            }
            preg_match_all("/(\d{17})/", $item['HJNR'], $xinlv);
            if (empty($xinlv[1][0])) {
                $errorNotice = [
                    'basis' => json_encode([["烟台市参保人员住院协议【身份证号未填写】"]], 256),
                    'JZHM' => $item['JZHM'],
                    'rule_id' => 244,
                    'code' => '',
                    'error_field' => $caseRule[244]['title']
                ];
            }
        }
        return $errorNotice;
    }

    /**
     *
     * update patient_info set is_defect=0;
     * update setting set content=0 where name="quality_last_zyh";
     * truncate `case_quality`;
     * 指令
     * php artisan command:quality-bl01 bl01303
     *
     */
    public function rule239($info = [])
    {
        // 获取规则
        $caseRule = $this->caseRule;
        $ygjb = $this->ygjb;
        $bl01Res = Bllb303::query()->where('ZYH', '=', $info['MED_REC_ID'])->get()->toArray();
        if (empty($bl01Res)) {
            return [];
        }
        $blbh = array_column($bl01Res, 'BLBH');
        $bl01 = EMR_BL_BL01::query()
            ->whereIn('BLBH', $blbh)
            ->get(['BLMC', 'BLBH'])->toArray();
        $bl01 = array_column($bl01, NULL, 'BLBH');

        $errorNotice = [];
        foreach ($bl01Res as $item) {
            $item['JZHM'] = $item['ZYH'];
            $item['BRBH'] = $item['AAA28'];

            // rule239 手术记录中未填写手术时间
            if ($caseRule[239]['status'] == 1 && strtotime($item['SSRQ']) == strtotime($item['SSKSSJ']) && strtotime($item['SSRQ']) == strtotime($item['SSJSSJ'])) {
                $errorNotice = [
                    'basis' => json_encode([["手术记录中手术时间不能为空"]], 256),
                    'rule_id' => 239,
                    'JZHM' => $item['JZHM'],
                    'code' => '',
                    'error_field' => $caseRule[239]['title']
                ];
            }

            // rule240 手术记录中无术者签名
            if ($item['SSZ']) {
                $ssz = str_replace($ygjb, '', $item['SSZ']);
                $ssz = explode(',', str_replace('、', ',', $ssz));
            }

            if ($caseRule[240]['status'] == 1 && !empty($item['SSZ']) && !empty($ssz)) {
                foreach ($ssz as $sz) {
                    $blsy = EMR_BL_BLSY::query()
                        ->leftJoin("staff", 'staff.code', '=', 'EMR_BL_BLSY.SYYS')
                        ->where("EMR_BL_BLSY.BLBH", '=', $item["BLBH"])
                        ->where("staff.name", '=', $sz)
                        ->get()->toArray();
                    if (empty($blsy)) {
                        $errorNotice = [
                            'basis' => json_encode([["【{$bl01[$item['BLBH']]['BLMC']}】术者【" . $sz . "】无签名"]], 256),
                            'rule_id' => 240,
                            'JZHM' => $item['JZHM'],
                            'code' => '',
                            'error_field' => $caseRule[240]['title']
                        ];
                    }
                }
            }
        }

        return $errorNotice;
    }

    /**
     *
     * update patient_info set is_defect=0;
     * update setting set content=0 where name="quality_last_zyh";
     * truncate `case_quality`;
     * 指令
     * php artisan command:quality-bl01 rule241
     *
     */
    public function rule241($info = [])
    {
        // 获取规则
        $caseRule = $this->caseRule;
        if (empty($caseRule[241]['status'])) {
            return [];
        }

        $bl01Res = EMR_BL_BL01::query()
            ->whereIn('BLLB', [2000001, 1, 292, 294, 18, 303, 288])
            ->where('MBLB', '<>', 75)
            ->where('BLZT', '<>', 9)
            ->where('JZHM', '=', $info['MED_REC_ID'])
            ->get(['JZHM', 'BLBH', 'BRBH', 'BLMC'])
            ->toArray();
        if (empty($bl01Res)) {
            return [];
        }

        $errorNotice = [];
        foreach ($bl01Res as $item) {

            $blsy = EMR_BL_BLSY::query()->where("BLBH", "=", $item['BLBH'])->get()->toArray();
            if (empty($blsy)) {
                $errorNotice = [
                    'basis' => json_encode([["【{$item['BLMC']}】无医师签名"]], 256),
                    'rule_id' => 241,
                    'JZHM' => $info['MED_REC_ID'],
                    'code' => '',
                    'error_field' => $caseRule[241]['title'],
                ];
            }
        }

        return $errorNotice;
    }

    /**
     *
     * update patient_info set is_defect=0;
     * update setting set content=0 where name="quality_last_zyh";
     * truncate `case_quality`;
     *
     */
    public function rule238($info = [])
    {
        $ruleid = 238;
        // 获取规则
        $caseRule = $this->caseRule;
        $staff = Staff::query()->get(['name', 'ygjb_text', 'code'])->toArray();
        $ygjb = $this->ygjb;

        $bl01Res = EMR_BL_BL01::query()
            ->where('BLZT', '<>', 9)
            ->where('JZHM', '=', $info['MED_REC_ID'])
            ->orderBy("BLBH", 'asc')
            ->limit(100)
            ->get(['JZHM', 'BLBH', 'BRBH', 'BLMC'])
            ->toArray();
        if (empty($bl01Res)) {
            return [];
        }

        $errorNotice = [];
        foreach ($bl01Res as $item) {
            // 病例名称中的医生级别
            $blmcYgjb = "";
            foreach ($ygjb as $s) {
                if (strpos($item['BLMC'], $s) !== false) {
                    $blmcYgjb = $s;
                    break;
                }
            }

            $isHas = false;
            foreach ($staff as $s) {
                if (empty($s['name']) || empty($s['ygjb_text'])) {
                    continue;
                }
                if (strpos($item['BLMC'], $s['name']) !== false && strpos($item['BLMC'], $s['ygjb_text']) !== false) {
                    $isHas = true;
                    break;
                } elseif (strpos($item['BLMC'], $s['name']) !== false && strpos($item['BLMC'], $s['ygjb_text']) === false) {
                    $errorNotice = [
                        'basis' => json_encode([["【" . $item['BLMC'] . "】【" . $s['name'] . "】【" . $blmcYgjb . "】应修改为【" . $s['ygjb_text'] . "】"]], 256),
                        'rule_id' => $ruleid,
                        'JZHM' => $info['MED_REC_ID'],
                        'code' => '',
                        'error_field' => $caseRule[$ruleid]['title']
                    ];
                }
            }
        }
        if ($isHas === true) {
            return [];
        }
        return $errorNotice;
    }

    /**
     * @param array $item
     * @param array $caseRule
     * @return array
     * 首次病程缺少医师签名
     */
    private function rule235($info = [])
    {

        $ruleid = 235;
        $errorNotice = [];
        $caseRule = $this->caseRule;
        $bl01Res = EMR_BL_BL01::query()
            ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.BLZT', '<>', 9)
            ->where('EMR_BL_BL01.BLLB', '=', 294)
            ->where('EMR_BL_BL01.MBLB', '=', 295)
            ->where('EMR_BL_BL01.JZHM', '=', $info['MED_REC_ID'])
            ->get(['EMR_BL_BL01.JZHM', 'EMR_BL_BL01.BLBH', 'EMR_BL_BL01.BRBH', 'EMR_BL_BLXG.HJNR'])
            ->toArray();
        if (empty($bl01Res)) {
            return [];
        }

        $item = $bl01Res[0];
        // 获取医生信息
        $staff = Staff::query()->get(['name', 'ygjb_text', 'code'])->toArray();
        $isHas = false;
        foreach ($staff as $v) {

            if ($item['HJNR'] && $v['name'] && strpos($item['HJNR'], $v['name']) !== false) {
                $blsy = EMR_BL_BLSY::query()
                    ->where("BLBH", "=", $item['BLBH'])
                    ->where("SYYS", "=", $v['code'])
                    ->get()->toArray();

                if (empty($blsy)) {
                    $errorNotice = [
                        'basis' => json_encode(["【" . $v['name'] . "】无签名"], 256),
                        'rule_id' => $ruleid,
                        'JZHM' => $info['MED_REC_ID'],
                        'code' => '',
                        'error_field' => $caseRule[$ruleid]['title']
                    ];
                    break;
                } else {
                    $isHas = true;
                }
            }
        }
        if ($isHas === true) {
            return [];
        }
        return $errorNotice;
    }

    /**
     * @param array $item
     * @param array $caseRule
     * @return array
     * 出院记录中“住院天数”书写不正确
     */
    private function rule237($info = [])
    {

        $ruleid = 237;
        $errorNotice = [];
        $caseRule = $this->caseRule;
        $bl01Res = Bllb1::query()
            ->where('ZYH', '=', $info['MED_REC_ID'])
            ->get()
            ->toArray();
        if (empty($bl01Res)) {
            return [];
        }
        $item = $bl01Res[0];
        $ZYTS = $item['ZYTS'] ?: 0;


        $patient = PatientInfo::query()->where('MED_REC_ID', '=', $item['AAA28'])->get(['AAC04', 'AAB01', 'AAC01'])->toArray();
        if (empty($patient)) {
            return [];
        }

        $aab01 = strtotime(date("Y-m-d", strtotime($patient[0]['AAB01'])));
        $aac01 = strtotime(date("Y-m-d", strtotime($patient[0]['AAC01'])));
        $day = ($aac01 - $aab01) / 24 / 3600;
        if ($ZYTS != $patient[0]['AAC04'] && $ZYTS != $day) {
            $errorNotice = [
                'basis' => json_encode([], 256),
                'rule_id' => $ruleid,
                'JZHM' => $info['MED_REC_ID'],
                'code' => '',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * @param array $item
     * @param array $caseRule
     * @return array
     * 出院记录书写不规范
     */
    public function rule234($info = [])
    {
        $ruleid = 234;
        $errorNotice = [];
        $caseRule = $this->caseRule;
        $bl01Res = Bllb1::query()
            ->where('ZYH', '=', $info['MED_REC_ID'])
            ->get()
            ->toArray();
        if (empty($bl01Res)) {
            return [];
        }
        $basis = [];
        $item = $bl01Res[0];
        if (empty(trim($item['XM']))) {
            $basis[] = '姓名（未填写）';
        }
        if (empty(trim($item['NL']))) {
            $basis[] = '年龄（未填写）';
        }
        //        if (empty(trim($item['ZYTS']))) {
        //            $basis[] = '住院天数（未填写）';
        //        }
        if (empty(trim($item['RYQK']))) {
            $basis[] = '入院情况（未填写）';
        }

        //        if (empty(trim($item['RYZD']))) {
        if (empty(trim($item['RYZD']))) {
            $basis[] = '初步诊断（未填写）';
        }
        if (empty(trim($item['ZLJG']))) {
            $basis[] = '诊疗经过（未填写）';
        }
        if (empty(trim($item['CYQK']))) {
            $basis[] = '出院情况（未填写）';
        }
        if (empty(trim($item['CYZD']))) {
            $basis[] = '出院诊断（未填写）';
        }
        if (empty(trim($item['CYYZ']))) {
            $basis[] = '出院医嘱（未填写）';
        }

        $blsy = EMR_BL_BLSY::query()->where('BLBH', '=', $item['BLBH'])->get()->toArray();
        if (empty($blsy)) {
            $basis[] = '医生签名（未填写）';
        }

        if ($basis) {
            $errorNotice = [
                'basis' => json_encode([$basis], 256),
                'rule_id' => $ruleid,
                'JZHM' => $info['MED_REC_ID'],
                'code' => '',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }

        return $errorNotice;
    }

    public function qualityControl($caseRule = [], $info = [])
    {

        $errorNotice = [];
        $errorNotice[] = $this->jbzd($info);
        $errorNotice = array_filter($errorNotice);
        if ($errorNotice) {
            foreach ($errorNotice as $v) {
                CaseQuality::addData($v);
            }
        }
    }

    /**
     * @return array
     * 首次病程记录 鉴别诊断条数 小于 2
     */
    public function jbzd($info = []): array
    {
        $errorNotice = [];
        $caseRule = $this->caseRule;
        if (empty($caseRule[126]['status']) || in_array(126, $this->appealRuleIds)) {
            return [];
        }
        $bl01esService = new ElasticsearchService('bl01_202303');
        // 首次病程记录 鉴别诊断条数 小于 2
        $params = $bl01esService->clearMust()
            ->queryByMust(['term' => ['JZHM' => $info['MED_REC_ID']]])
            ->queryByMust(['term' => ['MBLB' => 295]])
            ->getParams();

        $res = app('es')->search($params);
        $resData = $bl01esService->getDataByEs($res);

        if (!empty($resData[0])) {
            $caseContent = $resData[0][0]['HJNR'];
            // 整理数据
            $caseContent = str_replace("\r\n", "!!", $caseContent);
            $caseContent = str_replace("\n", "!!", $caseContent);
            $caseContent = str_replace(" ", "", $caseContent);
            $caseContent = str_replace("：", ":", $caseContent);
            $caseContent = str_replace("“", "\"", $caseContent);
            $caseContent = str_replace("”", "\"", $caseContent);
            preg_match_all("/鉴别诊断:(.*?)诊疗计划/u", str_replace("五、", "", $caseContent), $jbzd);
            $jbzdArr = [];

            if (array_filter($jbzd)) {
                //$jbzdArr = explode(';', $jbzd[1][0]);
                preg_match_all('/\d+/', $jbzd[1][0], $jbzdArr);
            }
            if (isset($jbzdArr[0]) && count($jbzdArr[0]) < 3) {
                $jbzdArr = explode('。', $jbzd[1][0]);
                preg_match_all("/(.*?):/u", Arr::get($jbzdArr, 0), $res1);
                if (array_filter($res1)) {
                    $basis = [['鉴别诊断【' . $res1[1][0] . '】']];
                } else {
                    $str = str_replace("；", "", $jbzd[1]);
                    $basis = [$str];
                }
                $errorNotice = [
                    'basis' => json_encode($basis, 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => 126,
                    'notice' => '必须要有鉴别诊断，数量>=2',
                    'code' => 'bingcheng_first',
                    'error_field' => $caseRule[101]['title']
                ];
            }
        }
        return $errorNotice;
    }

    public function rule229($info = [])
    {

        $ruleid = 229;
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;

        // 获取病例对应的病程信息
        $bingcheng = EMR_BL_BL01::query()
            ->where('JZHM', $info['MED_REC_ID'])
            ->where('EMR_BL_BL01.BLZT', '!=', 9)
            ->where('BLLB', '=', '294')
            ->get(['JZHM', 'bcts', 'bc_content', 'MBLB', 'BLMC'])
            ->toArray();
        if (empty($bingcheng)) {
            return [];
        }

        $newBingcheng = [];
        foreach ($bingcheng as $val) {
            if (!$val['bcts'] && !$val['bc_content']) {
                continue;
            }
            if (strpos($val['BLMC'], '首次病程') !== false) {
                $newBingcheng['tese'] = $val;
            }
            $newBingcheng['other'][] = $val;
        }
        // 获取病例对应的入院信息
        $xbs = EMR_BL_BL01::query()
            ->join('EMR_BL_BLXG as xg', 'EMR_BL_BL01.BLBH', '=', 'xg.BLBH')
            ->where('EMR_BL_BL01.JZHM', $info['MED_REC_ID'])
            ->where('EMR_BL_BL01.BLLB', '=', '292')
            ->where('EMR_BL_BL01.BLZT', '<>', 9)
            ->get(['EMR_BL_BL01.JZHM', 'xg.HJNR'])
            ->toArray();
        $xbs = empty($xbs[0]) ? '' : $xbs[0]['HJNR'];

        // 1、 整个病程中，每次记录的病程不能相同
        $other = !empty($newBingcheng['other']) ? $newBingcheng['other'] : [];
        $isIdentical = false;
        $resBc = '';
        foreach ($other as $key => $val) {
            $bcContent = $val['bc_content'] ?: '';
            if (empty($bcContent)) {
                continue;
            }

            $bcContentArray = preg_split('//u', $bcContent, 0, PREG_SPLIT_NO_EMPTY);
            $bcContentArray = array_unique(array_filter($bcContentArray));

            $isIdenticalOther = false;
            foreach ($other as $key1 => $val1) {
                // 比较过的和数据本身不比较
                if ($key >= $key1 || empty($val1['bc_content'])) {
                    continue;
                }

                $bcContentArray1 = preg_split('//u', $val1['bc_content'], 0, PREG_SPLIT_NO_EMPTY);
                $bcContentArray1 = array_unique(array_filter($bcContentArray1));
                // 获取交集
                $res = array_intersect($bcContentArray, $bcContentArray1);
                if (count($res) / count($bcContentArray) >= 0.75) {
                    $basisList[] = ['病程记录 【' . $val['BLMC'] . '】和病程【' . $val1['BLMC'] . '】 75%雷同'];
                    $isIdenticalOther = true;
                    $isIdentical = true;
                    break;
                }
            }
            if ($isIdenticalOther === true) {
                break;
            }
        }

        // 如果病程之间没有雷同，则校验病例特色和现病史之间的雷同
        if ($isIdentical === false && !empty($xbs) && !empty($newBingcheng['tese']) && !empty($newBingcheng['tese']['bcts'])) {

            $tese = $newBingcheng['tese']['bcts'];
            // 病程特色内容
            $teseContentArray = preg_split('//u', $tese, 0, PREG_SPLIT_NO_EMPTY);
            $teseContentArray = array_unique(array_filter($teseContentArray));

            // 入院记录
            $ryjlXbsContentArray = preg_split('//u', $xbs, 0, PREG_SPLIT_NO_EMPTY);
            $ryjlXbsContentArray = array_unique(array_filter($ryjlXbsContentArray));
            // 获取交集
            $res = array_intersect($teseContentArray, $ryjlXbsContentArray);
            if (count($res) / count($teseContentArray) >= 0.75) {
                $basisList[] = ['病例特点【75%内容】和入院记录雷同'];
            }
        }

        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => $ruleid,
                'code' => 'rcbc',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }

        return $errorNotice;
    }

    /** 病危患者至少每天1次
     * @return array
     */
    public function rule133($info = [])
    {
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        $yzbesService = new ElasticsearchService('yzb_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');


        $params = $yzbesService
            ->queryByMust(['terms' => ['YZZT' => $this->yzzt]])
            ->queryByMust(['term' => ['ZYH' => $info['MED_REC_ID']]])
            ->queryByMust(['match_phrase' => ['YZMC' => '病危']])
            ->getParams();
        $yzb = app('es')->search($params);
        $yzb = $yzbesService->getDataByEs($yzb);
        if (empty($yzb[1])) {
            return $errorNotice;
        }

        $startTime = date("Y-m-d", strtotime($yzb[0][0]['XZJDSJ']));
        $startTime = date("Y-m-d H:i:s", strtotime($startTime));
        $endTime = $yzb[0][0]['TZQRSJ'];

        //替换mysql字典映射
        $rulefirst = RuleWordMap::query()->where('name', '完成时间')->first();
        $bldate = $rulefirst['keyword'];

        while (true) {
            $toTime = date("Y-m-d H:i:s", strtotime($startTime) + 24 * 3600 - 1);

            if (strtotime($toTime) > strtotime($endTime)) {
                break;
            }
            $bl01must = [
                ['term' => ['BLLB' => 294]],
                //原搜索字段 ZXSJ
                ['range' => [$bldate => ['gt' => $startTime, 'lt' => $toTime]]]
            ];

            $params = $bl01esService->clearMust()
                ->queryByMustNot(['term' => ['BLZT' => 9]])
                ->queryByMustNot(['term' => ['mblb' => 32]])
                ->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'ZXSJ', 'BLMC', 'WCSJ'])->getParams();
            $bl01Res = app('es')->search($params);
            $bl01Res = $bl01esService->getDataByEs($bl01Res);

            $basis = [];
            $basis[] = '时间段【' . substr($toTime, 0, 10) . '】，';
            if (empty($bl01Res[1])) {
                // 检查是否举例完成时间小于两小时
                $bgsj = strtotime($toTime);
                $diffTime = $bgsj - time();
                if ($diffTime < $this->diffHoure * 3600 && $diffTime > 0) {
                    $res = remainderTime($diffTime);
                    $content = '请在' . $res . '内完成病程记录';
                    $msgYj = ['开嘱时间【' . $yzb[0][0]['KZSJ'] . '】', '医嘱名称【' . $yzb[0][0]['YZMC'] . '】', '需每天写一次日常病程'];
                    $this->sendMsg($info['MED_REC_ID'], $content, 133, $msgYj);
                }

                $basis[] = '病程记录【无】';
                $basisList[] = $basis;
                //原取自 $bl01Res[0][0]['ZXSJ']
            } else {
                $this->setSendLogStatus($info['MED_REC_ID'], 133, 1);
            }
            $startTime = $toTime;
        }

        if ($basisList) {
            array_unshift($basisList, ['病危：' . $yzb[0][0]['XZJDSJ'] . ' - ' . $endTime]);
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 133,
                'code' => 'rcbc',
                'error_field' => $caseRule[133]['title']
            ];
        }

        return $errorNotice;
    }


    /**
     * @param array $info
     * @return array
     * 出院上级医师查房记录
     */
    public function rule125($info = [])
    {
        $errorNotice = [];
        $basis = [];
        $aac01 = $info['AAC01'];
        if (empty($aac01)) {
            return $errorNotice;
        }
        $caseRule = $this->caseRule;
        $bl01esService = new ElasticsearchService('bl01_202303');
        $startTime = date('Y-m-d H:i:s', strtotime(date('Y-m-d', strtotime($aac01))) - 24 * 3600);
        $endTime = date('Y-m-d H:i:s', strtotime($startTime) + 48 * 3600);
        // 【EMR_BL_BL01】【BLLB =294 且 mblb=32的剔除且 blzt不等于9】【cjSJ】   或
        //  24小时出入院记录：表：EMR_BL_BL01中【BLLB =18 且 blzt≠9】的【CJSJ】
        $bl01must = [
            ['term' => ["JZHM" => $info['MED_REC_ID']]],
            ['terms' => ["BLLB" => [294, 18]]],
            ['range' => ["ZXSJ" => ['from' => $startTime, 'to' => $endTime]]]
        ];
        $params = $bl01esService->clearMust()
            ->queryByMustNot(['term' => ['BLZT' => 9]])
            ->queryByMustNot(['terms' => ['MBLB' => [32, 295]]])
            ->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
        $bl01Res = app('es')->search($params);
        $bl01Res = $bl01esService->getDataByEs($bl01Res);
        if (empty($bl01Res[1])) {
            $basis[] = '出院时间【' . $aac01 . '】';
            $bl01must = [
                ['term' => ["JZHM" => $info['MED_REC_ID']]],
                ['terms' => ["BLLB" => [294, 18]]],
                ['range' => ["ZXSJ" => ['from' => $endTime, 'to' => date('Y-m-d H:i:s', strtotime($endTime) + 24 * 3600)]]]
            ];
            $params = $bl01esService->clearMust()
                ->queryByMustNot(['term' => ['BLZT' => 9]])
                ->queryByMustNot(['terms' => ['MBLB' => [32, 295]]])
                ->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
            $bl01Res = app('es')->search($params);
            if (!empty($bl01Res[1])) {
                $basis[] = '【' . $bl01Res[0][0]['BLMC'] . '、执行时间超24小时】';
            } else {
                $basis[] = '病程记录【无】';
            }
        }
        if ($basis) {
            $errorNotice = [
                'basis' => json_encode([$basis], 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 125,
                'code' => 'cyjl', // 出院记录
                'error_field' => $caseRule[125]['title']
            ];
        }
        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 输血当天要有病程记录
     */
    public function rule124($info = [])
    {
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        $yzbesService = new ElasticsearchService('yzb_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');
        // 获取医嘱本信息
        $params = $yzbesService->clearMust()
            ->queryByMust(['terms' => ['YZZT' => $this->yzzt]])
            ->queryByMust(['term' => ['ZYH' => $info['MED_REC_ID']]])
            ->queryByMust(['term' => ['YZQX' => '2']])
            ->queryByMustNot(['match_phrase' => ['YZMC' => '备血']])
            ->queryByMust(['match_phrase' => ['YZMC' => '输']])
            ->queryByMust([
                "bool" => [
                    "should" => [
                        ['match_phrase' => ['YZMC' => 'RH']],
                        ['match_phrase' => ['YZMC' => 'Rh']],
                        ['match_phrase' => ['YZMC' => 'rh']],
                        ['match_phrase' => ['YZMC' => 'rH']],
                        ['match_phrase' => ['YZMC' => '红细胞']],
                        ['match_phrase' => ['YZMC' => '血小板']],
                        ['match_phrase' => ['YZMC' => '血浆']],
                        ['match_phrase' => ['YZMC' => '自体血']],
                        ['match_phrase' => ['YZMC' => '凝血因子']],
                        ['match_phrase' => ['YZMC' => '冷沉淀']],
                    ]
                ]
            ])
            ->source(['YZMC', 'KZSJ'])
            ->getParams();
        $res = app('es')->search($params);
        $resData = $yzbesService->getDataByEsToArray($res);
        if (empty($resData[1])) {
            return [];
        }

        $newData = [];
        foreach ($resData[0] as $v) {
            // 每天只保留一条数据
            $newData[date("Y-m-d", strtotime($v['KZSJ']))] = $v;
        }

        //获取质控字典关键词映射
        $rulefirst = RuleWordMap::query()->where('name', '完成时间')->first();
        $blDate = $rulefirst['keyword'];

        foreach ($newData as $k => $item) {

            // 当天的时间
            $kzsj = strtotime(date('Y-m-d', strtotime($k)));
            $endTime = $kzsj + 24 * 3600;

            // 先搜索MBLB是45的数据，如果没有则搜索bllb=295并且blmc中包含“输血记录”的数据
            $bl01must = [
                ['term' => ["JZHM" => $info['MED_REC_ID']]],
                ['term' => ["MBLB" => 45]],
                //原字段为ZXSJ
                ['range' => [$blDate => ['from' => date('Y-m-d H:i:s', $kzsj), 'to' => date('Y-m-d H:i:s', $endTime)]]]
            ];
            $params = $bl01esService->clearMust()
                ->queryByMustNot(['term' => ['BLZT' => 9]])
                ->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
            $bl01Res = app('es')->search($params);
            $bl01Res = $bl01esService->getDataByEs($bl01Res);
            if (empty($bl01Res[1])) {
                $bl01must = [
                    ['term' => ["JZHM" => $info['MED_REC_ID']]],
                    ['term' => ["BLLB" => 294]],
                    ['match_phrase' => ["BLMC" => "输血记录"]],
                    //原字段为ZXSJ
                    ['range' => [$blDate => ['from' => date('Y-m-d H:i:s', $kzsj), 'to' => date('Y-m-d H:i:s', $endTime)]]]
                ];
                $params = $bl01esService->clearMust()
                    ->queryByMustNot(['term' => ['BLZT' => 9]])
                    ->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
                $bl01Res = app('es')->search($params);
                $bl01Res = $bl01esService->getDataByEs($bl01Res);
                $basis[] = '医嘱名称【' . $item['YZMC'] . '】开嘱时间【' . $item['KZSJ'] . '】';
                if (empty($bl01Res[1])) {
                    // 检查是否举例完成时间小于两小时
                    $diffTime = $endTime - time();
                    if ($diffTime < $this->diffHoure * 3600 && $diffTime > 0) {
                        $res = remainderTime($diffTime);
                        $content = '请在' . $res . '内完成输血病程记录';
                        $msgYj = ['医嘱名称【' . $item['YZMC'] . '】', '开嘱时间【' . $item['KZSJ'] . '】', '输血当天要没有病程记录'];
                        $this->sendMsg($info['MED_REC_ID'], $content, 124, $msgYj);
                    }

                    $basis[] = '病程时间【无】';
                    $basisList[] = $basis;
                } else {
                    $this->setSendLogStatus($info['MED_REC_ID'], 124, 1);
                }
            }
        }

        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 124,
                'code' => 'sxjl',
                'error_field' => $caseRule[124]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 抢救记录在开嘱6小时内
     */
    public function rule123($info = [])
    {

        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        $yzbesService = new ElasticsearchService('yzb_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');
        // 获取医嘱本信息
        $params = $yzbesService->clearMust()
            ->queryByMust(['terms' => ['YZZT' => $this->yzzt]])
            ->queryByMust(['term' => ['ZYH' => $info['MED_REC_ID']]])
            ->queryByMust(['match_phrase' => ['YZMC' => '抢救']])
            ->getParams();
        $res = app('es')->search($params);
        $resData = $yzbesService->getDataByEs($res);
        if (empty($resData[1])) {
            return $errorNotice;
        }

        //获取质控字典关键词映射
        $rulefirst = RuleWordMap::query()->where('name', '完成时间')->first();
        $bldate = $rulefirst['keyword'];
        foreach ($resData[0] as $item) {
            $basis = [];
            $kzsj = strtotime($item['KZSJ']);
            $endTime = $kzsj + 6 * 3600;
            // 抢救记录
            $bl01must = [
                ['term' => ["JZHM" => $info['MED_REC_ID']]],
                ['term' => ["BLLB" => 294]],
                ['match_phrase' => ["HJNR" => '抢救记录']],
                //原搜索字段为ZXSJ
                ['range' => [$bldate => ['from' => date('Y-m-d H:i:s', $kzsj), 'to' => date('Y-m-d H:i:s', $endTime)]]]
            ];
            $params = $bl01esService->clearMust()
                ->queryByMustNot(['term' => ['BLZT' => 9]])
                ->queryByMustBatch($bl01must)
                ->source(['BLBH', 'CJSJ', 'ZXSJ', 'WCSJ'])->getParams();
            $bl01Res = app('es')->search($params);
            $bl01Res = $bl01esService->getDataByEs($bl01Res);
            if (empty($bl01Res[1])) {
                // 检查是否举例完成时间小于两小时
                $diffTime = $endTime - time();
                if ($diffTime < $this->diffHoure * 3600 && $diffTime > 0) {
                    $res = remainderTime($diffTime);
                    $content = '请在' . $res . '内完成抢救记录';
                    $msgYj = ['开嘱时间【' . $item['KZSJ'] . '】', '医嘱名称【' . $item['YZMC'] . '】', '开嘱6小时内无抢救记录'];
                    $this->sendMsg($info['MED_REC_ID'], $content, 123, $msgYj);
                }

                $basis[] = '开嘱时间【' . $item['KZSJ'] . '】';
                $basis[] = '抢救记录病程时间【无】';
            } else {
                $isYx = 1;
                if (strtotime($bl01Res[0][0]['CJSJ']) < $kzsj || strtotime($bl01Res[0][0]['CJSJ']) > $endTime) {
                    $basis[] = '开嘱时间【' . $item['KZSJ'] . '】';
                    $basis[] = '抢救记录病程时间【' . $bl01Res[0][0]['CJSJ'] . '、超6小时】';
                    $basisList[] = $basis;
                    $isYx = 0;
                }
                $this->setSendLogStatus($info['MED_REC_ID'], 123, $isYx);
            }
        }
        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 123,
                'code' => 'qjjl',
                'error_field' => $caseRule[123]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 转入记录
     */
    public function rule122($info = [])
    {

        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;

        $yzbesService = new ElasticsearchService('yzb_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');

        // 获取医嘱本信息
        $params = $yzbesService->clearMust()
            ->queryByMust(['terms' => ['YZZT' => $this->yzzt]])
            ->queryByMust(['term' => ['ZYH' => $info['MED_REC_ID']]])
            ->queryByMust(['term' => ['YDYZLB' => 901]])
            ->queryByMust(['match_phrase' => ['YZMC' => '转入']])
            ->getParams();
        $res = app('es')->search($params);
        $resData = $yzbesService->getDataByEs($res);
        if (empty($resData[1])) {
            return $errorNotice;
        }
        foreach ($resData[0] as $item) {
            $basis = [];

            $xzjdsj = strtotime($item['XZJDSJ']);
            $endTime = $xzjdsj + 24 * 3600;

            // 搜索24小时内的病程记录
            $bl01must = [
                ['term' => ["JZHM" => $info['MED_REC_ID']]],
                ['term' => ["MBLB" => 30]],
                ['match_phrase' => ["BLMC" => '转入记录']],
                ['range' => ["ZXSJ" => ['from' => date('Y-m-d H:i:s', $xzjdsj), 'to' => date('Y-m-d H:i:s', $endTime)]]]
            ];
            $params = $bl01esService->clearMust()
                ->queryByMustNot(['term' => ['BLZT' => 9]])
                ->queryByMustBatch($bl01must)
                ->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
            $bl01Res = app('es')->search($params);
            $bl01Res = $bl01esService->getDataByEs($bl01Res);

            if (empty($bl01Res[1])) {
                $basis[] = '转入时间【' . $item['XZJDSJ'] . '】';
                $basis[] = '转入记录的病程时间【无】';
            } elseif (strtotime($bl01Res[0][0]['CJSJ']) < $xzjdsj || strtotime($bl01Res[0][0]['CJSJ']) > $endTime) {
                $basis[] = '转入时间【' . $item['XZJDSJ'] . '】';
                $basis[] = '转入记录的病程时间【' . $bl01Res[0][0]['BLMC'] . '、' . $bl01Res[0][0]['CJSJ'] . '超24小时】';
            }
            $basisList[] = $basis;
        }

        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 122,
                'code' => 'zrjl',
                'error_field' => $caseRule[122]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 阶段小结
     */
    public function rule121($info = [])
    {

        $errorNotice = [];
        $basislist = [];
        $caseRule = $this->caseRule;

        $bl01esService = new ElasticsearchService('bl01_202303');
        // 获取护士分床时间
        $zyHcmxesService = new Zyhcmx();
        $mzRes = $zyHcmxesService->getHCRQ($info['MED_REC_ID']);
        if (empty($mzRes[0]) || empty($info['AAC01'])) {
            return $errorNotice;
        }

        $startTime = strtotime($mzRes[0][0]['HCRQ']); // 开始时间
        $basis[] = '护士分床时间【' . $mzRes[0][0]['HCRQ'] . '】';
        $endTime = strtotime($info['AAC01']); // 结束时间
        // 从开始时间加7天超过住院结束时间，则失败
        if (empty($startTime) || empty($endTime)) {
            return $errorNotice;
        }
        // 如果开始时间到出院时间小于30天，则不校验
        if ($startTime + 30 * 24 * 3600 > $endTime) {
            return $errorNotice;
        }

        $flag = 0;
        while (true) {
            $fromTime = $startTime;
            $toTime = $startTime + 30 * 24 * 3600;
            if ($startTime + 30 * 24 * 3600 > $endTime) {
                break;
            }
            // 查找出院小结
            $bl01must = [
                ['term' => ["JZHM" => $info['MED_REC_ID']]],
                ['terms' => ["MBLB" => [26, 30, 35, 98]]],
                ['range' => ["ZXSJ" => ['from' => date('Y-m-d H:i:s', $fromTime), 'to' => date('Y-m-d H:i:s', $toTime)]]]
            ];
            $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
            $bl01Res = app('es')->search($params);
            $bl01Res = $bl01esService->getDataByEs($bl01Res);
            $startTime = $toTime;
            $basis = [];
            $basis[] = '时间段【' . date('Y-m-d H:i:s', $fromTime) . '-' . date('Y-m-d H:i:s', $toTime) . '】';
            if (empty($bl01Res[1])) {
                $flag = 1;
                $basis[] = '阶段小结时间【无】';
                $basislist[] = $basis;
            }
        }
        if ($flag) {
            $errorNotice = [
                'basis' => json_encode($basislist, 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 121,
                'code' => 'jdxj',
                'error_field' => $caseRule[121]['title']
            ];
        }

        return $errorNotice;
    }


    /**
     * @param array $info
     * @return array
     * 术前小结及术前讨论结论记录 在医嘱之前
     */
    public function rule115($info = [])
    {

        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        $sssqesService = new ElasticsearchService('sssq_2023');
        $mustNot = ['term' => ['BLZT' => 9]];
        $bl01must = [
            ['term' => ["ZYH" => $info['MED_REC_ID']]],
            ['term' => ["zfbz" => 0]],
        ];
        $params = $sssqesService->clearMust()->queryByMustNot($mustNot)->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ'])->getParams();
        $res = app('es')->search($params);
        $resData = $sssqesService->getDataByEs($res);
        if (!empty($resData[1])) {
            foreach ($resData[0] as $item) {
                // yzb中含有关键字   【拟***年*月*日 】的数据
                $yzbesService = new ElasticsearchService('yzb_2023');
                $bl01must = [
                    ['term' => ["ZYH" => $info['MED_REC_ID']]],
                    ['terms' => ["is_operation" => 1]],
                    ['match_phrase' => ['YZMC' => $item['NSSMC']]]
                ];
                $params = $yzbesService->clearMust()
                    ->queryByMustNot($mustNot)
                    ->queryByMust(['terms' => ['YZZT' => $this->yzzt]])
                    ->queryByMustBatch($bl01must)
                    ->source(['KZSJ'])->getParams();
                $res = app('es')->search($params);
                $resData = $yzbesService->getDataByEs($res);
                if (!empty($resData[1])) {

                    // 术前小结及术前讨论结论记录
                    $bl01esService = new ElasticsearchService('bl01_202303');
                    $bl01must = [
                        ['term' => ["JZHM" => $info['MED_REC_ID']]],
                        ['terms' => ["MBLB" => [82]]],
                        ['range' => ['ZXSJ' => ['lt' => $resData[0][0]['KZSJ']]]]
                    ];
                    $params = $bl01esService->clearMust()
                        ->queryByMustNot($mustNot)
                        ->orderBy('ZXSJ', 'asc')
                        ->paginate(1, 1)
                        ->queryByMustBatch($bl01must)
                        ->source(['BLBH', 'CJSJ'])->getParams();
                    $bl01Res = app('es')->search($params);
                    $bl01Res = $bl01esService->getDataByEs($bl01Res);
                    if (empty($bl01Res[1])) {
                        $basis = [];
                        $basis[] = '手术名称【' . $item['NSSMC'] . '】';
                        $basis[] = '开嘱时间【' . $resData[0][0]['KZSJ'] . '】';
                        $basis[] = '术前小结及术前讨论结论记录【无】';
                        $basisList[] = $basis;
                    } else {
                        $cjsj = $bl01Res[0][0]['CJSJ'];
                        if (strtotime($cjsj) > strtotime($resData[0][0]['KZSJ'])) {
                            $basis = [];
                            $basis[] = '手术名称【' . $item['NSSMC'] . '】';
                            $basis[] = '开嘱时间【' . $resData[0][0]['KZSJ'] . '】';
                            $basis[] = '术前小结及术前讨论结论记录【' . $cjsj . '不在开嘱时间之前】';
                            $basisList[] = $basis;
                        }
                    }
                }
            }
        }
        if ($basisList) {
            $errorNotice = $this->errorNotice($basisList, $info, 115, 'sqxj', $caseRule[115]['title']);
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 115,
                'code' => 'sqxj',
                'error_field' => $caseRule[115]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 术后即刻完成（当天完成）
     */
    public function rule116($info = [])
    {

        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;

        $bl01esService = new ElasticsearchService('bl01_202303');
        // 手术记录
        $mzRes = $this->getBl01303($info['MED_REC_ID']); // 获取手术记录

        if (!empty($mzRes[0])) {
            foreach ($mzRes[0] as $v) {
                $basis = [];
                // 手术结束时间
                //                $endTime = $this->getOperationEndTime($v);
                $bllb303 = Bllb303::query()->where('BLBH', $v['BLBH'])->first();
                if ($bllb303 && $bllb303['SSRQ'] && strtotime($bllb303['SSRQ']) > 0) {
                    $endTime = Carbon::parse($bllb303['SSRQ'])->format("Y-m-d H:i:s");
                    $basis[] = '手术结束时间【' . $endTime . '】';
                    $endTime = date("Y-m-d 00:00:00", strtotime($endTime));
                    $from = $endTime;
                    $to = date("Y-m-d 23:59:59", strtotime($endTime));
                    $bl01must = [
                        ['term' => ["JZHM" => $info['MED_REC_ID']]],
                        ['term' => ["MBLB" => 54]],
                        [
                            'range' => [
                                "ZXSJ" => [
                                    'from' => $from,
                                    'to' => $to
                                ]
                            ]
                        ]
                    ];
                    $mustNot = ['term' => ['BLZT' => 9]];
                    $params = $bl01esService->clearMust()->queryByMustNot($mustNot)->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
                    $bl01Res = app('es')->search($params);
                    $bl01Res = $bl01esService->getDataByEs($bl01Res);

                    if (empty($bl01Res[1])) {
                        $basis[] = '术后首次病程【无】';
                        $basisList[] = $basis;
                    } elseif (strtotime($bl01Res[0][0]['CJSJ']) > 24 * 3600 + strtotime($endTime)) {
                        $basis[] = '术后首次病程【' . $bl01Res[0][0]['CJSJ'] . '（未在手术当天完成）】';
                        $basisList[] = $basis;
                    }
                }
            }
        }
        if ($basisList) {
            $errorNotice = $this->errorNotice($basisList, $info, 116, 'fenchuanghouchafang', $caseRule[116]['title']);
        }

        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 术后48小时内须有术者查房记录
     */
    public function rule117($info = [])
    {

        $zyh = $info['MED_REC_ID'];
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        // 获取麻醉记录的信息，用户获取手术时间
        $mzjlEsService = new ElasticsearchService('mzjl_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');

        // 手术记录找术者
        $bl01must = [
            ['term' => ["JZHM" => $zyh]],
            ['term' => ["BLLB" => 303]],
            ['match_phrase' => ['BLMC' => '手术记录']],
        ];
        $params = $bl01esService->clearMust()
            ->queryByMustNot(['term' => ['BLZT' => 9]])
            ->queryByMustBatch($bl01must)
            ->queryByShould(['terms' => ['MBLB' => [306, 74]]])
            ->minimumShouldMatch(1)
            ->source(['BLBH', 'JZHM', 'CJSJ', 'operation_handler', 'operation_time', 'operation_end_time', 'MBLB', 'BLLB', 'BLMC'])
            ->paginate(1, 1000)
            ->getParams();

        $bl01Res = app('es')->search($params);
        $bl01Res = $bl01esService->getDataByEs($bl01Res);

        if (!empty($bl01Res[1])) {
            foreach ($bl01Res[0] as $b) {
                $operation_time = $b['operation_time'];
                $start = date('Y-m-d H:i:s', $operation_time);
                $end = date('Y-m-d H:i:s', $operation_time + 48 * 3600);
                $params = $mzjlEsService->clearMust()
                    ->queryByMust(['term' => ['HOSPIZATIONID' => $b['JZHM']]])
                    ->queryByMust(['range' => ['OPERATESTARTTIME' => ['gt' => $start, 'lt' => $end]]])
                    ->getParams();
                $res = app('es')->search($params);
                $resData = $mzjlEsService->getDataByEs($res);
                if (!empty($resData[1])) {
                    $startTime = $resData[0][0]['OPERATEENDTIME'];
                } else {
                    $startTime = $b['operation_end_time'];
                }
                if (empty($startTime) || !strtotime($startTime)) {
                    break;
                }
                $basis = [];
                // 如果手术时间存在，则获取手术时间48内的所有病程信息
                $endTime = date("Y-m-d H:i:s", strtotime($startTime) + 48 * 3600);
                $bl01must = [
                    ['term' => ["JZHM" => $zyh]],
                    ['term' => ["BLLB" => 294]],
                    ['range' => ["ZXSJ" => ['gt' => $startTime, 'lt' => $endTime]]]
                ];
                $params = $bl01esService->clearMust()
                    ->queryByMustNot(['terms' => ['MBLB' => [32, 54]]])
                    ->queryByMustNot(['term' => ['BLZT' => 9]])
                    ->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
                $bl01Res = app('es')->search($params);
                $bl01Res = $bl01esService->getDataByEs($bl01Res);
                // 如果病程信息不存在，则提示质控错误信息
                $basis[0] = '手术结束时间【' . $startTime . '】';
                $basis[1] = '术者【' . $b['operation_handler'] . '】';
                if (empty($bl01Res[1])) {
                    $basis[2] = '病程记录【无】';
                } else {
                    $res = $bl01Res[0];
                    $zfzhi = $this->shuzhe($res, $b['operation_handler']);

                    if (empty($zfzhi[0])) {
                        $basis[2] = '病程记录【无】';
                    }
                }

                if ($basis) {
                    $basisList[] = $basis;
                }
            }
        }
        if ($basisList) {
            $errorNotice = $this->errorNotice($basisList, $zyh, 117, 'sq48', $caseRule[117]['title']);
        }
        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 会诊记录，当天完成
     */
    public function rule119($info = [])
    {

        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;

        $yzbesService = new ElasticsearchService('yzb_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');

        // 获取医嘱本信息
        $params = $yzbesService->clearMust()
            ->queryByMust(['terms' => ['YZZT' => $this->yzzt]])
            ->queryByMust(['term' => ['ZYH' => $info['MED_REC_ID']]])
            ->queryByMust(['term' => ['YDYZLB' => 901]])
            ->queryByMust(['match_phrase' => ['YZMC' => '会诊']])
            ->queryByMustNot(['match_phrase' => ['YZMC' => 'PICC']])
            ->queryByMustNot(['match_phrase' => ['YZMC' => '取消']])
            ->source(['XZJDSJ', 'KZSJ', 'YZMC'])
            ->getParams();
        $res = app('es')->search($params);
        $resData = $yzbesService->getDataByEs($res);
        if (!empty($resData[1])) {
            //获取质控字典关键词映射
            $rulefirst = RuleWordMap::query()->where('name', '完成时间')->first();
            $bldate = $rulefirst['keyword'] ?? '';
            foreach ($resData[0] as $item) {
                $basis = [];
                $XZJDSJ = strtotime(date("Y-m-d", strtotime($item['XZJDSJ'])));
                $basis[] = '医嘱会诊时间【' . $item['XZJDSJ'] . '】';
                if (!empty($XZJDSJ)) {
                    $endTime = date("Y-m-d H:i:s", $XZJDSJ + 24 * 3600);

                    $bl01must = [
                        ['term' => ["JZHM" => $info['MED_REC_ID']]],
                        ['term' => ["MBLB" => 32]],
                        [
                            'range' => [
                                //原搜索字段为 ZXSJ
                                $bldate => [
                                    'from' => date("Y-m-d H:i:s", $XZJDSJ),
                                    'to' => $endTime
                                ]
                            ]
                        ]
                    ];
                    $mustNot = [
                        ['term' => ['BLZT' => 9]],
                    ];
                    $params = $bl01esService->clearMust()
                        ->queryByMustNotBatch($mustNot)
                        ->queryByMustBatch($bl01must)
                        ->source(['BLBH', 'CJSJ'])->getParams();
                    $bl01Res = app('es')->search($params);
                    $bl01Res = $bl01esService->getDataByEs($bl01Res);
                    if (!empty($bl01Res[1])) {
                        $bl01294 = empty($bl01Res[0]) ? [] : $bl01Res[0];
                        $zheng = [];
                        foreach ($bl01294 as $item) {
                            $blsy = EMR_BL_BLSY::query()
                                ->where('BLBH', '=', $item['BLBH'])
                                ->get()->toArray();
                            $code = array_column($blsy, 'SYYS');
                            if ($blsy) {
                                $staff = Staff::query()->whereIn('code', $code)->get()->toArray();
                                foreach ($staff as $b) {
                                    if (in_array($b['ygjb'], [1, 2])) {
                                        $zheng[] = $item['CJSJ'];
                                    }
                                }
                            }
                        }
                        if (empty($zheng)) {
                            // 检查是否举例完成时间小于两小时
                            $bgsj = strtotime($endTime);
                            $diffTime = $bgsj - time();
                            if ($diffTime < $this->diffHoure * 3600 && $diffTime > 0) {
                                $res = remainderTime($diffTime);
                                $content = '请在' . $res . '内完成会诊记录';
                                $msgYj = ['开嘱时间【' . $item['KZSJ'] . '】', '医嘱名称【' . $item['YZMC'] . '】', '当天无会诊记录'];
                                $this->sendMsg($info['MED_REC_ID'], $content, 119, $msgYj);
                            }
                            $basis[] = '会诊记录【无】';
                            $basisList[] = $basis;
                        } else {
                            $this->setSendLogStatus($info['MED_REC_ID'], 119, 1);
                        }
                    } else {
                        // 检查是否举例完成时间小于两小时
                        $bgsj = strtotime($endTime);
                        $diffTime = $bgsj - time();
                        if ($diffTime < $this->diffHoure * 3600 && $diffTime > 0) {
                            $res = remainderTime($diffTime);
                            $content = '请在' . $res . '内完成会诊记录';
                            $basis[] = $content;
                            $this->sendMsg($info['MED_REC_ID'], $content, 119);
                        }
                        $basis[] = '会诊记录【无】';
                        $basisList[] = $basis;
                    }
                }
            }
        }

        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 119,
                'code' => 'sh48',
                'error_field' => $caseRule[119]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 会诊记录单，当天完成
     */
    public function rule120($info = [])
    {

        $errorNotice = [];
        $basis = [];
        $caseRule = $this->caseRule;

        $yzbesService = new ElasticsearchService('yzb_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');

        // 获取医嘱本信息
        $params = $yzbesService->clearMust()
            ->queryByMust(['terms' => ['YZZT' => $this->yzzt]])
            ->queryByMust(['term' => ['ZYH' => $info['MED_REC_ID']]])
            ->queryByMust(['term' => ['YDYZLB' => 901]])
            ->queryByMust(['match_phrase' => ['YZMC' => '会诊']])
            ->queryByMustNot(['match_phrase' => ['YZMC' => 'PICC']])
            ->queryByMustNot(['match_phrase' => ['YZMC' => '取消']])
            ->getParams();
        $res = app('es')->search($params);
        $resData = $yzbesService->getDataByEs($res);
        if (!empty($resData[1])) {
            $XZJDSJ = strtotime($resData[0][0]['XZJDSJ']);
            if (!empty($XZJDSJ)) {

                $bl01must = [
                    ['term' => ["JZHM" => $info['MED_REC_ID']]],
                    ['term' => ["MBLB" => 32]],
                    [
                        'range' => [
                            "ZXSJ" => [
                                'from' => date("Y-m-d H:i:s", $XZJDSJ),
                                'to' => date("Y-m-d H:i:s", $XZJDSJ + 24 * 3600)
                            ]
                        ]
                    ]
                ];
                $mustNot = [
                    ['term' => ['BLZT' => 9]],
                ];
                $params = $bl01esService->clearMust()->queryByMustNotBatch($mustNot)->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ'])->getParams();
                $bl01Res = app('es')->search($params);
                $bl01Res = $bl01esService->getDataByEs($bl01Res);
                if (!empty($bl01Res[1])) {
                    $bl01294 = empty($bl01Res[0]) ? [] : $bl01Res[0];
                    $zheng = [];
                    foreach ($bl01294 as $item) {
                        $blsy = EMR_BL_BLSY::query()
                            ->where('BLBH', '=', $item['BLBH'])
                            ->get()->toArray();
                        $code = array_column($blsy, 'SYYS');
                        if ($blsy) {
                            $staff = Staff::query()->whereIn('code', $code)->get()->toArray();
                            foreach ($staff as $b) {
                                if (in_array($b['ygjb'], [1, 2])) {
                                    $zheng[] = $item['CJSJ'];
                                }
                            }
                        }
                    }
                    if (empty($zheng)) {
                        $basis[] = '会诊记录【无正或副高签名】';
                        $errorNotice = [
                            'basis' => json_encode($basis),
                            'JZHM' => $info['MED_REC_ID'],
                            'rule_id' => 120,
                            'code' => 'sh48',
                            'error_field' => $caseRule[120]['title']
                        ];
                    }
                } else {
                    $basis[] = '会诊记录【无】';
                    $errorNotice = [
                        'basis' => json_encode($basis),
                        'JZHM' => $info['MED_REC_ID'],
                        'rule_id' => 120,
                        'code' => 'sh48',
                        'error_field' => $caseRule[120]['title']
                    ];
                }
            }
        }
        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 术后3天连续病程记录
     */
    public function rule118($info = [])
    {
        $zyh = $info['MED_REC_ID'];
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;

        $mzjlesService = new ElasticsearchService('mzjl_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');

        // 手术记录找术者
        $bl01must = [
            ['term' => ["JZHM" => $zyh]],
            ['term' => ["BLLB" => 303]],
            ['match_phrase' => ['BLMC' => '手术记录']],
        ];
        $params = $bl01esService->clearMust()
            ->queryByMustNot(['term' => ['BLZT' => 9]])
            ->queryByMustBatch($bl01must)
            ->queryByShould(['terms' => ['MBLB' => [306, 74]]])
            ->minimumShouldMatch(1)
            ->source(['BLBH', 'JZHM', 'CJSJ', 'operation_handler', 'operation_time', 'operation_end_time', 'MBLB', 'BLLB', 'BLMC'])
            ->paginate(1, 1000)
            ->getParams();

        $bl01Res = app('es')->search($params);
        $bl01Res = $bl01esService->getDataByEs($bl01Res);

        if (!empty($bl01Res[1])) {
            foreach ($bl01Res[0] as $k => $b) {
                if (!empty($bl01Res[0][$k + 1])) {
                    $nextBl = [];
                    if (!empty($bl01Res[0][$k + 1])) {
                        $nextBl = $bl01Res[0][$k + 1];
                    }
                    $basis = [];
                    $operation_time = $b['operation_time'];
                    $start = date('Y-m-d H:i:s', $operation_time);
                    $end = date('Y-m-d H:i:s', $operation_time + 24 * 3600);
                    $params = $mzjlesService->clearMust()
                        ->queryByMust(['term' => ['HOSPIZATIONID' => $b['JZHM']]])
                        ->queryByMust(['range' => ['OPERATESTARTTIME' => ['gt' => $start, 'lt' => $end]]])
                        ->getParams();
                    $res = app('es')->search($params);
                    $resData = $mzjlesService->getDataByEs($res);
                    if (!empty($resData[1])) {
                        $startTime = $resData[0][0]['OPERATEENDTIME'];
                    } else {
                        $startTime = $b['operation_end_time'];
                    }
                    if (empty($startTime) || !strtotime($startTime)) {
                        break;
                    }

                    if ($startTime) {

                        $basis[] = '手术结束时间【' . $startTime . '】';
                        $cysj = strtotime(date("Y-m-d", strtotime($info['AAC01'])));
                        $startTime = strtotime(date("Y-m-d", strtotime($startTime)));
                        // 出院当天, 查出院当天是否有病程
                        if ($cysj == $startTime) {
                            $from = $startTime;
                            $to = $startTime + 24 * 3600;
                            $bingcheng = $this->rule118Helper($info['MED_REC_ID'], $from, $to, 1);
                            if (empty($bingcheng)) {
                                $basis[] = '【' . date("Y-m-d", $from) . '（无）】';
                            } else {
                                $basis[] = '【' . $bingcheng['BLMC'] . '】';
                            }
                        } else {
                            if ($cysj && $cysj >= $startTime + 24 * 3600 && $nextBl['operation_time'] > $startTime + 24 * 3600) {

                                $from = $startTime + 24 * 3600;
                                $to = $startTime + 24 * 3600 * 2;
                                $bingcheng = $this->rule118Helper($info['MED_REC_ID'], $from, $to, 1);
                                if (empty($bingcheng)) {
                                    $basis[] = '【' . date("Y-m-d", $from) . '（无）】';
                                } else {
                                    $basis[] = '【' . $bingcheng['BLMC'] . '】';
                                }
                            }
                            if ($cysj && $cysj >= $startTime + 2 * 24 * 3600 && $nextBl['operation_time'] > $startTime + 2 * 24 * 3600) {
                                $from = $startTime + 24 * 3600 * 2;
                                $to = $startTime + 24 * 3600 * 3;
                                $bingcheng = $this->rule118Helper($info['MED_REC_ID'], $from, $to, 1);
                                if (empty($bingcheng)) {
                                    $basis[] = '【' . date("Y-m-d", $from) . '（无）】';
                                } else {
                                    $basis[] = '【' . $bingcheng['BLMC'] . '】';
                                }
                            }
                            if ($cysj && $cysj >= $startTime + 3 * 24 * 3600 && $nextBl['operation_time'] > $startTime + 3 * 24 * 3600) {
                                $from = $startTime + 24 * 3600 * 3;
                                $to = $startTime + 24 * 3600 * 4;
                                $bingcheng = $this->rule118Helper($info['MED_REC_ID'], $from, $to, 1);
                                if (empty($bingcheng)) {
                                    $basis[] = '【' . date("Y-m-d", $from) . '（无）】';
                                } else {
                                    $basis[] = '【' . $bingcheng['BLMC'] . '】';
                                }
                            }
                        }
                        if (count($basis) > 1) {
                            $basisList[] = $basis;
                        }
                    }
                }
            }
            if ($basisList) {
                $errorNotice = [
                    'basis' => json_encode($basisList, 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => 118,
                    'code' => 'sh3t',
                    'error_field' => $caseRule[118]['title']
                ];
            }

            return $errorNotice;
        }
    }

    /**
     * @param string $zyh
     * @param string $from
     * @param string $to
     * 规则118的辅助方法
     */
    public
    function rule118Helper(
        $zyh = '',
        $from = '',
        $to = '',
        $type = 0
    ) {
        if ($from <= 0 || $to <= 0) {
            return [];
        }
        $bl01esService = new ElasticsearchService('bl01_202303');
        $bl01must = [
            ['term' => ["JZHM" => $zyh]],
            ['term' => ["BLLB" => 294]],
            [
                'range' => [
                    "ZXSJ" => [
                        'from' => date("Y-m-d H:i:s", $from),
                        'to' => date("Y-m-d H:i:s", $to)
                    ]
                ]
            ]
        ];
        $mustNot = [
            ['term' => ['BLZT' => 9]],
            ['terms' => ['MBLB' => [54, 32]]],
        ];
        $params = $bl01esService->clearMust()
            ->queryByMustNotBatch($mustNot)
            ->queryByMustBatch($bl01must)
            ->orderBy("CJSJ", "asc")
            ->source(['BLBH', 'CJSJ', 'BLMC'])
            ->getParams();
        $bl01Res = app('es')->search($params);
        $bl01Res = $bl01esService->getDataByEs($bl01Res);

        $bingcheng = empty($bl01Res[1]) ? [] : $bl01Res[0][0];
        return $bingcheng;
    }

    /**
     * @param array $info
     * @return array
     * 入院后8小时内完成首次病程记录
     */
    public function checkRy8($info = [])
    {
        $errorNotice = [];
        $caseRule = $this->caseRule;
        //        sssq中【rjss=1  或 首页=日间手术   属于日间手术】且 住院天数=1   剔除
        $sssq = SSSQ::query()->where('ZYH', '=', $info['MED_REC_ID'])->where('RJSS', '=', 1)->get()->toArray();
        $patientInfo = PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->get()->toArray();
        $mainOperation = MainOperation::query()->where('AAA28', '=', $info['MED_REC_ID'])->get(['RJSS'])->toArray();
        if ($patientInfo && $patientInfo[0]['AAC04'] == 1 && (($mainOperation && $mainOperation[0]['RJSS'] == '是') || !empty($sssq))) {
            return [];
        }
        // 有24小时出入院记录的不质控该规则
        $resData = EMR_BL_BL01::query()->where(['JZHM' => $info['MED_REC_ID'], 'BLLB' => 18])->get(['BLBH', 'ZXSJ', 'CJSJ', 'WCSJ', 'BLLB'])->toArray();
        if ($resData) {
            $this->setSendLogStatus($info['MED_REC_ID'], 101, 1);
            return [];
        }


        $basis = [];
        // 获取入院时间
        $zyHcmxesService = new Zyhcmx();
        $zyHcmxData = $zyHcmxesService->getHCRQ($info['MED_REC_ID']);

        //首次病程记录表：EMR_BL_BL01中【MBLB =295 且 blzt≠9】的【CJSJ】
        $flag = 1;
        if (!empty($zyHcmxData[0])) {
            $hcrq = $zyHcmxData[0][0]['HCRQ'];
            Log::info('入院时间', [$hcrq]);
            $basis[] = '入院时间【' . $hcrq . '】';

            //替换mysql字典映射
            $rulefirst = RuleWordMap::query()->where('id', '=', 22)->value('keyword');
            $resData = EMR_BL_BL01::query()
                ->where(['JZHM' => $info['MED_REC_ID'], "MBLB" => 295])
                ->where('BLZT', '<>', 9)
                ->get(['BLBH', 'ZXSJ', 'CJSJ', 'WCSJ', 'BLLB', $rulefirst])->toArray();
            // 检查是否举例完成时间小于两小时
            $bgsj = strtotime($hcrq) + 8 * 3600;
            $diffTime = $bgsj - time();
            Log::info("首次病程记录", [count($resData)]);

            if (empty($resData)) {
                if ($diffTime < $this->diffHoure * 3600 && $diffTime > 0) {
                    $flag = 0;
                    $res = remainderTime($diffTime);
                    $content = '请在' . $res . '内完成首次病程记录';
                    $msgYj = ['入院时间【' . $hcrq . '】', '首次病程记录【未创建】'];
                    $this->sendMsg($info['MED_REC_ID'], $content, 101, $msgYj);
                }
            }
            $wcsj = $resData[0][$rulefirst] ?? '';
            if (empty($wcsj) && $diffTime < $this->diffHoure * 3600 && $diffTime > 0) {
                $flag = 0;
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内完成首次病程记录';
                $msgYj = ['入院时间【' . $hcrq . '】', '首次病程记录【未签名】'];
                $this->sendMsg($info['MED_REC_ID'], $content, 101, $msgYj);
            }
            if (empty($resData) || empty($wcsj)) {
                $flag = 0;
                $basis[] = '首次病程记录完成时间【无，未完成】';
            } else {
                $isYx = 1;
                $cjsj = strtotime($wcsj);
                if ($bgsj < $cjsj) {
                    $isYx = 0;
                    $flag = 0;
                    $basis[] = '首次病程记录完成时间【' . $wcsj . '、完成时间超8小时】';
                }
                $this->setSendLogStatus($info['MED_REC_ID'], 101, $isYx);
            }
        }
        if (empty($flag)) {
            $errorNotice = [
                'basis' => json_encode([$basis], 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 101,
                'code' => 'bingcheng_first',
                'error_field' => $caseRule[101]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * 检查报告单有ct（OCT除外）结果，24小时内要写病程记录
     * 或 24小时出入院记录  ,且   含 关键字“CT”（OCT除外）
     */
    public function checkCt($info = [])
    {
        $aab01 = $info['AAB01'];
        $aac01 = $info['AAC01'];
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        $bl01esService = new ElasticsearchService('bl01_202303');
        $pacsesService = new ElasticsearchService('pacs');
        if (empty($aac01)) {
            return [];
        }
        //替换ct、oct 字典
        $ctFirst = RuleWordMap::query()->where('name', 'CT')->first();
        $octFirst = RuleWordMap::query()->where('name', '不属于CT范围')->first();

        $must = [
            ['term' => ['JZLSH' => $info['AAA28']]],
            ['match_phrase' => ['JCMC' => $ctFirst['keyword']]]
        ];
        if ($aab01 && $aac01) {
            $must[] = ['range' => ['KDSJ' => ["gt" => $aab01, 'lt' => $aac01]]];
        }

        $params = $pacsesService->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNot(['match_phrase' => ['JCMC' => $octFirst['keyword']]])
            ->source(['BGSJ', 'JCMC'])
            ->orderBy('KDSJ', 'asc')
            ->getParams();
        $res = app('es')->search($params);
        $resData = $pacsesService->getDataByEs($res);
        if (!empty($resData[1])) {
            foreach ($resData[0] as $v) {
                $basis = [];
                $basis[] = '报告单时间【' . $v['BGSJ'] . '】';
                $bgsj = date("Y-m-d H:i:s", strtotime($v['BGSJ']) + 24 * 3600);
                // 病程记录
                $must = [
                    ['term' => ['JZHM' => $info['MED_REC_ID']]],
                    ['terms' => ['BLLB' => [294, 18]]],
                    ['match_phrase' => ['HJNR' => $ctFirst['keyword']]]
                ];
                $mustNot = [
                    ['term' => ['BLZT' => 9]],
                    ['term' => ['MBLB' => 32]],
                    ['match_phrase' => ['HJNR' => $octFirst['keyword']]]
                ];
                //获取质控字典关键词映射
                $rulefirst = RuleWordMap::query()->where('name', '完成时间')->first();
                $wcsj = $rulefirst['keyword'] ?? '';
                $params = $bl01esService->clearMust()
                    ->queryByShouldBatch($must)
                    ->queryByMust(['range' => [$wcsj => ['gt' => $v['BGSJ'], 'lt' => $bgsj]]])
                    ->queryByMustNotBatch($mustNot)
                    ->source(['BLBH', 'CJSJ', 'BLMC', 'BLLB'])
                    ->getParams();
                $res = app('es')->search($params);
                $resData = $bl01esService->getDataByEs($res);
                // 病程记录无
                if (empty($resData[1])) {

                    // 检查是否提前
                    $params = $bl01esService->clearMust()
                        ->queryByShouldBatch($must)
                        ->queryByMust(['range' => [$wcsj => ['gt' => date('Y-m-d', strtotime($v['BGSJ'])) . ' 00:00:00', 'lt' => $v['BGSJ']]]])
                        ->queryByMustNotBatch($mustNot)
                        ->source(['BLBH', 'CJSJ', 'BLMC', 'BLLB', 'WCSJ'])
                        ->getParams();
                    $res = app('es')->search($params);
                    $resData = $bl01esService->getDataByEs($res);


                    if (!empty($resData[1])) {
                        //$basis[] = ($resData[0][0]['BLLB'] == 18 ? '24小时出入院记录' : '病程记录') . '【' . $resData[0][0]['CJSJ'] . '（提前创建）】';
                        $basis[] = ($resData[0][0]['BLLB'] == 18 ? '24小时出入院记录' : '病程记录') . '【' . $wcsj . '（提前创建）】';
                        $this->setSendLogStatus($info['MED_REC_ID'], 102, 0);
                    } else {
                        // 检查是否超24小时
                        $params = $bl01esService->clearMust()
                            ->queryByShouldBatch($must)
                            ->queryByMust(['range' => [$wcsj => ['gt' => $bgsj, 'lt' => date('Y-m-d', strtotime($v['BGSJ']) + 24 * 3600 * 2) . ' 00:00:00']]])
                            ->queryByMustNotBatch($mustNot)
                            ->source(['BLBH', 'CJSJ', 'BLMC', 'BLLB', 'WCSJ'])
                            ->getParams();
                        $res = app('es')->search($params);
                        $resData = $bl01esService->getDataByEs($res);
                        if (!empty($resData[1])) {
                            $wcsj = $resData[0][0][$rulefirst['keyword']] ?? '';
                            $basis[] = ($resData[0][0]['BLLB'] == 18 ? '24小时出入院记录' : '病程记录') . '【' . $wcsj . '（超24小时）】';
                            $this->setSendLogStatus($info['MED_REC_ID'], 102, 0);
                        } else {
                            $basis[] = '病程记录【没有记录CT检查结果】';
                            // 检查是否举例完成时间小于两小时
                            $bgsj = strtotime($bgsj);
                            $diffTime = $bgsj - time();
                            if ($diffTime < $this->diffHoure * 3600 && $diffTime > 0) {
                                $res = remainderTime($diffTime);
                                $content = '请在' . $res . '内在病程中记录CT报告结果';
                                $msgYj = ['报告时间【' . $v['BGSJ'] . '】', '检查名称【' . $v['JCMC'] . '】', '24小时内的病程中无记录'];
                                $this->sendMsg($info['MED_REC_ID'], $content, 102, $msgYj);
                            }
                        }
                    }
                    $basisList[] = $basis;
                } else {
                    $this->setSendLogStatus($info['MED_REC_ID'], 102, 1);
                }
            }
            //
            if (count($basisList)) {
                $errorNotice = [
                    'basis' => json_encode($basisList, 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => 102,
                    'code' => 'jcbgd',
                    'error_field' => $caseRule[102]['title']
                ];
            }
        }

        return $errorNotice;
    }

    /*
     * 检查报告单有mr结果，24小时内要写病程记录，或 24小时内出入院记录：关键字 "MR” 或 “磁共振”
     */
    public function checkMr($info = [])
    {

        $aab01 = $info['AAB01'];
        $aac01 = $info['AAC01'];
        if (empty($aac01)) {
            return [];
        }
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        $bl01esService = new ElasticsearchService('bl01_202303');
        $pacsesService = new ElasticsearchService('pacs');

        $must = [
            ['term' => ['JZLSH' => $info['AAA28']]],
            ['match_phrase' => ['JCMC' => "MR"]]
        ];
        if ($aab01 && $aac01) {
            $must[] = ['range' => ['KDSJ' => ["gt" => $aab01, 'lt' => $aac01]]];
        }

        $params = $pacsesService->clearMust()
            ->queryByMustBatch($must)
            ->orderBy('KDSJ', 'asc')
            ->getParams();
        $res = app('es')->search($params);
        $resData = $pacsesService->getDataByEs($res);
        if (!empty($resData[1])) {
            foreach ($resData[0] as $v) {
                $basis = [];
                $basis[] = '检查报告单时间【' . $v['BGSJ'] . '】';
                $bgsj = date("Y-m-d H:i:s", strtotime($v['BGSJ']) + 24 * 3600);
                // 病程记录
                $must = [
                    ['term' => ['JZHM' => $info['MED_REC_ID']]],
                    ['terms' => ['BLLB' => [294, 18]]],
                    [
                        "bool" => [
                            "should" => [
                                ['match_phrase' => ['HJNR' => "MR"]],
                                ['match_phrase' => ['HJNR' => "磁共振"]],
                            ]
                        ]
                    ]
                ];
                $mustNot = [
                    ['term' => ['BLZT' => 9]],
                    ['term' => ['MBLB' => 32]],
                ];
                $params = $bl01esService->clearMust()
                    ->queryByMustBatch($must)
                    ->queryByMust(['range' => ['ZXSJ' => ['gt' => $v['BGSJ'], 'lt' => $bgsj]]])
                    ->queryByMustNotBatch($mustNot)
                    ->source(['BLBH', 'CJSJ', 'BLMC'])
                    ->getParams();
                $res = app('es')->search($params);
                $resData = $bl01esService->getDataByEs($res);
                if (empty($resData[1])) {

                    // 检查是否提前
                    $params = $bl01esService->clearMust()
                        ->queryByShouldBatch($must)
                        ->queryByMust(['range' => ['ZXSJ' => ['gt' => date('Y-m-d', strtotime($v['BGSJ'])) . ' 00:00:00', 'lt' => $v['BGSJ']]]])
                        ->queryByMustNotBatch($mustNot)
                        ->source(['BLBH', 'CJSJ', 'BLMC', 'BLLB'])
                        ->getParams();
                    $res = app('es')->search($params);
                    $resData = $bl01esService->getDataByEs($res);
                    if (!empty($resData[1])) {
                        $this->setSendLogStatus($info['MED_REC_ID'], 103, 0);
                        $basis[] = ($resData[0][0]['BLLB'] == 18 ? '24小时出入院记录' : '病程记录') . '【' . $resData[0][0]['CJSJ'] . '（提前创建）】';
                    } else {
                        // 检查是否超24小时
                        $params = $bl01esService->clearMust()
                            ->queryByShouldBatch($must)
                            ->queryByMust(['range' => ['ZXSJ' => ['gt' => $bgsj, 'lt' => date('Y-m-d', strtotime($v['BGSJ']) + 24 * 3600 * 2) . ' 00:00:00']]])
                            ->queryByMustNotBatch($mustNot)
                            ->source(['BLBH', 'CJSJ', 'BLMC', 'BLLB'])
                            ->getParams();
                        $res = app('es')->search($params);
                        $resData = $bl01esService->getDataByEs($res);
                        if (!empty($resData[1])) {
                            $this->setSendLogStatus($info['MED_REC_ID'], 103, 0);
                            $basis[] = ($resData[0][0]['BLLB'] == 18 ? '24小时出入院记录' : '病程记录') . '【' . $resData[0][0]['CJSJ'] . '（超24小时）】';
                        } else {
                            $basis[] = '病程记录【没有记录MR检查结果】';
                            $bgsj = strtotime($bgsj);
                            $diffTime = $bgsj - time();
                            if ($diffTime < $this->diffHoure * 3600 && $diffTime > 0) {
                                $res = remainderTime($diffTime);
                                $content = '请在' . $res . '内在病程中记录MR报告结果';
                                $msgYj = ['报告时间【' . $v['BGSJ'] . '】', '检查名称【' . $v['JCMC'] . '】', '24小时内的病程中无记录'];
                                $this->sendMsg($info['MED_REC_ID'], $content, 103, $msgYj);
                            }
                        }
                    }
                    $basisList[] = $basis;
                } else {
                    $this->setSendLogStatus($info['MED_REC_ID'], 103, 1);
                }
            }

            //
            if (count($basisList)) {
                $errorNotice = [
                    'basis' => json_encode($basisList, 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => 103,
                    'code' => 'jcbgd',
                    'error_field' => $caseRule[103]['title']
                ];
            }
        }

        return $errorNotice;
    }

    /**
     * @return array
     * 有抗菌药，开嘱时间+24小时 内要有病程记录
     */
    public function kjy($info = []): array
    {
        Log::info("rule:kjy start--------" . date('Y-m-d H:i:s'));
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        $bl01esService = new ElasticsearchService('bl01_202303');

        $resData = Yzb::query()
            ->select(['YZMC', 'JLDW', 'SYPC', 'YCJL', 'KZSJ', 'kjyw_name', 'YZBXH'])
            ->where('ZYH', '=', (string)$info['MED_REC_ID'])
            ->whereIn('YZZT', $this->yzzt)
            ->where('is_has_kjyw', '=', 1)
            ->get()->toArray();

        if (!empty($resData)) {

            Log::info("rule:kjy 有数据--------" . date('Y-m-d H:i:s'));
            $resArr = [];
            foreach ($resData as $item) {
                $resArr[md5($item['YZMC'] . $item['JLDW'] . $item['SYPC'] . $item['YCJL'])] = $item;
            }

            //获取质控字典关键词映射
            $rulefirst = RuleWordMap::query()->where('name', '完成时间')->first();
            $bldate = $rulefirst['keyword'] ?? '';
            foreach ($resArr as $v) {
                $kzsj = date("Y-m-d H:i:s", strtotime($v['KZSJ']) + 24 * 3600);
                $must = [
                    ['term' => ['JZHM' => $info['MED_REC_ID']]],
                    ['term' => ['BLLB' => 294]],
                    ['match_phrase' => ['HJNR' => $v['kjyw_name']]]
                ];
                // 24小时内的病程记录
                $params = $bl01esService->clearMust()
                    ->queryByMustBatch($must)
                    ->queryByMust(['range' => [$bldate => ['lt' => $kzsj]]])
                    ->queryByMustNot(['term' => ['BLZT' => 9]])
                    ->getParams();
                $res = app('es')->search($params);
                $resData1 = $bl01esService->getDataByEs($res);
                $basis = [];
                $basis[] = '抗菌药名称【' . $v['kjyw_name'] . '】';
                $basis[] = '开嘱时间【' . $v['KZSJ'] . '】';
                if (empty($resData1[1])) {

                    Log::info("rule:kjy 24小时内的病程记录--------" . date('Y-m-d H:i:s'));
                    // 24小时内的病程记录
                    $params = $bl01esService->clearMust()
                        ->queryByMustBatch($must)
                        ->queryByMust(['range' => [$bldate => ['gt' => $kzsj]]])
                        ->queryByMustNot(['term' => ['BLZT' => 9]])
                        ->getParams();
                    $res = app('es')->search($params);
                    $resData = $bl01esService->getDataByEs($res);
                    if (!empty($resData[1])) {
                        $basis[] = '病程记录【' . $resData[0][0]['BLMC'] . '，执行时间超24小时】';
                        $this->setSendLogStatus($info['MED_REC_ID'], 109, 0);
                    } else {
                        $basis[] = '病程记录时间【无】';
                        // 检查是否举例完成时间小于两小时
                        $bgsj = strtotime($kzsj);
                        $diffTime = $bgsj - time();
                        if ($diffTime < $this->diffHoure * 3600 && $diffTime > 0) {
                            $res = remainderTime($diffTime);
                            $content = '请在' . $res . '内在病程中记录抗菌药使用情况';
                            $msgYj = ['开嘱时间【' . $v['KZSJ'] . '】', '医嘱名称【' . $v['YZMC'] . '】', '使用频次【' . $v['SYPC'] . '】', '使用剂量【' . $v['YCJL'] . '】', '24小时内的病程中无记录'];
                            $this->sendMsg($info['MED_REC_ID'], $content, 109, $msgYj, $v['YZBXH'], '医嘱');
                        }
                    }
                    $basisList[] = $basis;
                } else {
                    $this->setSendLogStatus($info['MED_REC_ID'], 109, 1);
                }
            }

            if ($basisList) {
                $errorNotice = [
                    'basis' => json_encode($basisList, 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => 109,
                    'code' => 'kjy',
                    'error_field' => $caseRule[109]['title']
                ];
            }
        }

        return $errorNotice;
    }

    /**
     * @return array
     * 有化疗药，开嘱时间+24小时 内要有病程记录
     */
    public function hly($info = []): array
    {
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        $bl01esService = new ElasticsearchService('bl01_202303');

        // 有化疗药，开嘱时间+24小时 内要有病程记录
        $resData = Yzb::query()
            ->select(['YZMC', 'JLDW', 'SYPC', 'YCJL', 'KZSJ', 'kjyw_name'])
            ->where('ZYH', '=', (string)$info['MED_REC_ID'])
            ->where('is_has_hlyw', '=', 1)
            ->whereIn('YZZT', $this->yzzt)
            ->get()->toArray();
        if (!empty($resData)) {
            $resArr = [];
            foreach ($resData as $item) {
                $resArr[md5($item['YZMC'] . $item['JLDW'] . $item['SYPC'] . $item['YCJL'])] = $item;
            }

            //获取质控字典关键词映射
            $rulefirst = RuleWordMap::query()->where('name', '完成时间')->first();
            $bldate = $rulefirst['keyword'] ?? '';
            foreach ($resArr as $v) {
                if (!isset($v['hlyw_name']) || empty($v['hlyw_name'])) {
                    continue;
                }
                $basis = [];
                $kzsj = date("Y-m-d H:i:s", strtotime($v['KZSJ']) + 24 * 3600);

                $must = [
                    ['term' => ['JZHM' => $info['MED_REC_ID']]],
                    ['term' => ['BLLB' => 294]],
                    ['match_phrase' => ['HJNR' => $v['hlyw_name']]]
                ];
                $params = $bl01esService->clearMust()
                    ->queryByMustBatch($must)
                    ->queryByMust(['range' => [$bldate => ['gt' => $v['KZSJ'], 'lt' => $kzsj]]])
                    ->queryByMustNot(['term' => ['BLZT' => 9]])
                    ->queryByMustNot(['term' => ['MBLB' => 32]])
                    ->getParams();
                $res = app('es')->search($params);
                $resData1 = $bl01esService->getDataByEs($res);
                $basis[] = '化疗药名称【' . $v['hlyw_name'] . '】';
                $basis[] = '开嘱时间【' . $v['KZSJ'] . '】';
                if (empty($resData1[1])) {
                    // 24小时内的病程记录
                    $params = $bl01esService->clearMust()
                        ->queryByMustBatch($must)
                        ->queryByMust(['range' => [$bldate => ['gt' => $kzsj]]])
                        ->queryByMustNot(['term' => ['BLZT' => 9]])
                        ->queryByMustNot(['term' => ['MBLB' => 32]])
                        ->getParams();
                    $res = app('es')->search($params);
                    $resData = $bl01esService->getDataByEs($res);
                    if (!empty($resData[1])) {
                        $this->setSendLogStatus($info['MED_REC_ID'], 110, 0);
                        $basis[] = '病程记录【' . $resData[0][0]['BLMC'] . '，执行时间超24小时】';
                    } else {
                        $basis[] = '病程记录时间【无】';
                        // 检查是否举例完成时间小于两小时
                        $bgsj = strtotime($kzsj);
                        $diffTime = $bgsj - time();
                        if ($diffTime < $this->diffHoure * 3600 && $diffTime > 0) {
                            $res = remainderTime($diffTime);
                            $content = '请在' . $res . '内在病程中记录化疗药使用情况';
                            $msgYj = ['开嘱时间【' . $v['KZSJ'] . '】', '医嘱名称【' . $v['YZMC'] . '】', '使用频次【' . $v['SYPC'] . '】', '使用剂量【' . $v['YCJL'] . '】', '24小时内的病程中无记录'];
                            $this->sendMsg($info['MED_REC_ID'], $content, 110, $msgYj);
                        }
                    }
                    $basisList[] = $basis;
                } else {
                    $this->setSendLogStatus($info['MED_REC_ID'], 110, 1);
                }
            }

            if ($basisList) {
                $errorNotice = [
                    'basis' => json_encode($basisList, 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => 110,
                    'code' => 'hly',
                    'error_field' => $caseRule[110]['title']
                ];
            }
        }

        return $errorNotice;
    }

    /**
     * @param string $info
     * @return array
     * 术前24小时内，有术者查房（搜签名）
     */
    public function sq24($info = [])
    {
        if ($info['AAC04'] == 1) {
            return [];
        }

        $zyh = $info['MED_REC_ID'];
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        // 获取麻醉记录的信息，用户获取手术时间
        $bl01Res = $this->getBl01303($zyh); // 获取手术记录
        $bl01 = EMR_BL_BL01::query()->where(["JZHM" => $zyh, "BLLB" => 294])->orderBy("ZXSJ", "asc")->get(['BLBH', 'CJSJ', 'BLMC', "ZXSJ", 'BLZT'])->toArray();

        if (!empty($bl01Res[1])) {
            foreach ($bl01Res[0] as $b) {
                //                手术开始时间 执行时间中的年份+表头中的时间
                preg_match('/\d{2}\.\d{2} \d{2}:\d{2}/', $b["BLMC"], $matches);
                if (empty($matches[0])) {
                    continue;
                }
                // 拼接执行时间和BLMC表头中的时间
                $startTime = strtotime(date("Y", strtotime($b["ZXSJ"])) . '-' . str_replace('.', '-', $matches[0]));
                $basis = [];
                $rData = [];
                foreach ($bl01 as $k => $v) {
                    if (in_array($v["BLMC"], [32, 54]) || $v['BLZT'] == 9) {
                        continue;
                    }
                    preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $v["BLMC"], $matches);
                    if (empty($matches)) {
                        continue;
                    }
                    // 拼接执行时间和BLMC表头中的时间
                    $blmcTime = strtotime($matches[0]);
                    // 判断时间是否在术前24小时内
                    if ($startTime - 24 * 3600 > $blmcTime or $blmcTime > $startTime) {
                        continue;
                    }
                    $rData[] = $v;
                }

                // 检查是否有正副高职签字的病程
                $basis[0] = '手术开始时间【' . date("Y-m-d H:i:s", $startTime) . '】';
                $basis[1] = '术者【' . $b['operation_handler'] . '】';
                if (empty($rData)) {
                    $basis[2] = '病程记录【无术者签名】';
                } else {

                    $lastData = end($rData);
                    // 术者工号
                    //                    $code = Staff::query()->whereIn('name', $b['operation_handler'])->value("code");
                    //                    $blsy = EMR_BL_BLSY::query()->where(["BLBH" => $bl01[0]['BLBH'], "SYYS" => $code])->orderBy("SYSJ", "asc")->get(["SYSJ"])->toArray();
                    $blsy = EMR_BL_BLSY::query()->where(["BLBH" => $lastData['BLBH']])->orderBy("SYSJ", "asc")->get(["SYSJ"])->toArray();
                    $sysj = data_get($blsy, "0.SYSJ", "");
                    if (empty($sysj)) {
                        $basis[2] = '病程记录【无术者签名】';
                    } else {
                        if ($startTime - 24 * 3600 > strtotime($sysj) or strtotime($sysj) > $startTime) {
                            $basis[2] = data_get($lastData, "0.BLMC", "") . "，首次提交时间超24小时";
                        }
                    }
                }
                if (data_get($basis, "2")) {
                    $basisList[] = $basis;
                }
            }
        }
        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $zyh,
                'rule_id' => 111,
                'code' => 'sq24',
                'error_field' => $caseRule[111]['title']
            ];
        }
        return $errorNotice;
    }

    /**
     * @param array $res
     * @return array
     *
     */
    public
    function shuzhe(
        $res = [],
        $operator = ''
    ) {
        $zhengName = '';
        $cjsj = '';
        foreach ($res as $r) {
            $blsy = EMR_BL_BLSY::query()
                ->where('BLBH', '=', $r['BLBH'])
                ->get()->toArray();
            $code = array_column($blsy, 'SYYS');
            if ($blsy) {
                $staff = Staff::query()->whereIn('code', $code)->get()->toArray();
                foreach ($staff as $b) {
                    if (strpos(trim($operator), trim($b['name'])) !== false) {
                        $zhengName = $b['name'];
                        $cjsj = $r['BLMC'];
                        break;
                    }
                }
                if (!empty($cjsj)) {
                    break;
                }
            }
        }

        return [$zhengName, $cjsj];
    }

    /**
     * @param array $res
     * @return array
     * 获取病程中的正副高职信息
     */
    public
    function getZhengFu(
        $res = []
    ) {
        $zhengName = '';
        $fuName = '';
        $cjsj = '';
        foreach ($res as $r) {
            $blsy = EMR_BL_BLSY::query()
                ->where('BLBH', '=', $r['BLBH'])
                ->get()->toArray();
            $code = array_column($blsy, 'SYYS');
            if ($blsy) {
                $staff = Staff::query()->whereIn('code', $code)->get()->toArray();
                foreach ($staff as $b) {
                    if ($b['ygjb'] == 1) {
                        $zhengName = $b['name'];
                        $cjsj = $r['CJSJ'];
                        break;
                    } elseif ($b['ygjb'] == 2) {
                        $fuName = $b['name'];
                        $cjsj = $r['CJSJ'];
                        break;
                    }
                }
            }
        }

        return [$zhengName, $fuName, $cjsj];
    }

    /**
     * @param string $zyh
     * @return array
     * 获取一个住院号的所有手术记录
     */
    public function getBl01303($zyh = '')
    {
        $bl01Res = EMR_BL_BL01::query()->whereIn("MBLB", [306, 74])->where("JZHM", $zyh)->get(['BLBH', 'JZHM', 'CJSJ', 'operation_handler', 'operation_time', 'operation_end_time', 'MBLB', 'BLLB', 'BLMC', 'ZXSJ'])->toArray();

        return [$bl01Res, count($bl01Res)];
    }

    /**
     * @param array $bl01
     * @return mixed
     * 获取手术结束时间
     */
    public function getOperationEndTime($bl01 = [])
    {
        $mzjlEsService = new ElasticsearchService('mzjl_2023');
        $operation_time = $bl01['operation_time'];
        $start = date('Y-m-d H:i:s', $operation_time);
        $end = date('Y-m-d H:i:s', $operation_time + 24 * 3600);
        $params = $mzjlEsService->clearMust()
            ->queryByMust(['term' => ['HOSPIZATIONID' => $bl01['JZHM']]])
            ->queryByMust(['range' => ['OPERATESTARTTIME' => ['gt' => $start, 'lt' => $end]]])
            ->getParams();
        $res = app('es')->search($params);
        $resData = $mzjlEsService->getDataByEs($res);
        if (!empty($resData[1])) {
            $endTime = $resData[0][0]['OPERATEENDTIME'];
        } else {
            $endTime = date("Y-m-d H:i:s", $bl01['operation_end_time']);
        }

        return $endTime;
    }

    /**
     * @param array $zyh
     * @return array
     * 术后24小时内，有术者查房（搜签名）
     */
    public
    function sh24(
        $info = []
    ) {
        if ($info['AAC04'] == 1) {
            return [];
        }

        $zyh = $info['MED_REC_ID'];
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        // 获取麻醉记录的信息，用户获取手术时间
        $mzjlEsService = new ElasticsearchService('mzjl_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');
        $bl01Res = $this->getBl01303($zyh); // 获取手术记录

        $carbon = new Carbon();

        if (!empty($bl01Res[1])) {
            foreach ($bl01Res[0] as $b) {
                if (!($bllb303 = Bllb303::query()->where('BLBH', $b['BLBH'])->first())) {
                    break;
                }

                $startTime = $carbon::parse($bllb303['SSRQ'])->format("Y-m-d H:i:s");
                $basis = [];
                $basis[0] = '手术结束时间【' . $startTime . '】';
                $basis[1] = '术者【' . $bllb303['SSZ'] . '】';
                // 如果手术时间存在，则获取手术时间24内的所有病程信息
                $endTime = $carbon::parse($bllb303['SSRQ'])->addDays()->format("Y-m-d H:i:s");
                if (strtotime($startTime) < 0 || !strtotime($endTime) < 0) {
                    break;
                }

                $bl01must = [
                    ['term' => ["JZHM" => $zyh]],
                    ['term' => ["BLLB" => 294]],
                    ['range' => ["ZXSJ" => ['gt' => $startTime, 'lt' => $endTime]]]
                ];
                $params = $bl01esService->clearMust()
                    ->queryByMustNot(['terms' => ['MBLB' => [32, 54]]])
                    ->queryByMustNot(['term' => ['BLZT' => 9]])
                    ->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
                $bl01Res = app('es')->search($params);
                $bl01Res = $bl01esService->getDataByEs($bl01Res);
                // 如果病程信息不存在，则提示质控错误信息
                $zfzhi = [];
                if (!empty($bl01Res[1])) {
                    $res = $bl01Res[0];
                    $zfzhi = $this->shuzhe($res, $bllb303['SSZ']);
                }
                // 检查是否有正副高职签字的病程
                if (empty($bl01Res[1]) || empty($zfzhi[0])) {
                    $basis[2] = '病程记录【无术者签名】';
                    $basisList[] = $basis;
                }
            }
        }
        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $zyh,
                'rule_id' => 112,
                'code' => 'sq24',
                'error_field' => $caseRule[112]['title']
            ];
        }
        return $errorNotice;
    }


    public static function getCaseDetail($blbh = 0)
    {

        $blxg = EMR_BL_BL01::query()->where("BRBH", '=', $blbh)->orWhere("JZHM", '=', $blbh)->get()->toArray();
        if (!$blxg) {
            return [];
        }
        $blbh = $blxg[0]['BLBH'];
        $res = CaseQuality::getById($blbh);
        return $res;
    }

    public static function getCasePlatform($JZHM = 0, $bllb = 1, $isHight = 0, $keyWord = [])
    {
        $config = config("confAdmin");
        $deparment = !empty($config['department']) ? $config['department'] : [];
        $blxg = EMR_BL_BL01::query()
            ->where('BLLB', '=', (string)$bllb)
            ->where('BLZT', '<>', 9)
            ->where("JZHM", "=", (string)$JZHM)
            ->orderBy("id", "desc")
            ->get(['analysis_case', 'diagnose_list', 'BLLB', 'BRKS', 'BLBH', 'CJSJ', 'ZXSJ', 'WCSJ', 'first_blsy_time', 'HTML_PRINT', 'YWSJ'])
            ->toArray();

        if (!$blxg) {
            return [];
        }
        // 如果解析的入院记录不存在则通过oracle数据库重新同步
        $newData["blbh"] = $blbh = $blxg[0]['BLBH'];

        //查询基本信息
        $brry = ZY_BRRY::query()->where('ZYH', (string)$JZHM)->get()->toArray();
        $newData['基本信息'] = [];
        $newData['基本信息'][] = [
            'title' => '姓名',
            'value' => $brry[0]['BRXM'] ?? '',
        ];

        $newData['基本信息'][] = [
            'title' => '床号',
            'value' => $brry[0]['CH'] ?? '',
        ];

        $brks = $brry[0]['BRKS'] ?? '';
        if (!empty($brks)) {
            $brks = Department::query()->where('dep_id', $brks)->value('dep_name');
        }
        $newData['基本信息'][] = [
            'title' => '科室',
            'value' => $brks,
        ];

        $newData['基本信息'][] = [
            'title' => '病案号',
            'value' => $brry[0]['AAA28'] ?? '',
        ];

        $newData['department'] = !empty($deparment[$blxg[0]['BRKS']]) ? $deparment[$blxg[0]['BRKS']] : '';
        $blxg[0]["HJNR"] = EMR_BL_BLXG::query()->where("BLBH", $blbh)->first()->HJNR;
        /* $bllb_field = [
            '1' => [
                'XM' => ['title' => '姓名', 'key' => "name"],
                'RYRQ' => ['title' => '入院日期', 'key' => "ry_time"],
                'XB' => ['title' => '性别', 'key' => "sex"],
                'CYRQ' => ['title' => '出院日期', 'key' => "cy_time"], // 出院日期
                'NL' => ['title' => '年龄', 'key' => "age"],
                'ZYTS' => ['title' => '住院天数', 'key' => "zyts"], // 住院天数
                'RYQK' => ['title' => '入院情况', 'key' => "ryqk"], // 入院情况:
                'RYZD' => ['title' => '初步诊断', 'key' => "cbzd"], // 初步诊断::
                'ZLJG' => ['title' => '诊疗经过', 'key' => "zljg"], // 诊疗经过
                'CYQK' => ['title' => '出院情况', 'key' => "cyqk"], // 出院情况
                'CYZD' => ['title' => '出院诊断', 'key' => "cyzd"], // 出院诊断:
                'CYYZ' => ['title' => '出院医嘱', 'key' => "cyyz"], // 出院医嘱
                'ZYH' => ['title' => '住院号', 'key' => "hospital_no"], // 住院号
            ],
            '292' => [
                'XM' => ['title' => '姓名', 'key' => "name"],
                'CSD' => ['title' => '出生地', 'key' => "local_address"],
                'XB' => ['title' => '性别', 'key' => "sex"],
                'ZHY' => ['title' => '职业', 'key' => "job"],
                'NL' => ['title' => '年龄', 'key' => "age"],
                'RYSJ' => ['title' => '入院时间', 'key' => "ry_time"],
                'MZ' => ['title' => '民族', 'key' => "nation"], // 民族
                'JLSJ' => ['title' => '记录时间', 'key' => "record_time"], // 记录时间
                'HY' => ['title' => '婚姻', 'key' => "marriage"], // 婚姻
                'BSCSZ' => ['title' => '陈述者', 'key' => "narrator"], // 陈述者
                'ZHS' => ['title' => '主诉', 'key' => "zhusu"], // 主诉
                'XBS' => ['title' => '现病史', 'key' => "xianbingshi"], // 现病史
                'JWS' => ['title' => '既往史', 'key' => "jiwangshi"], // 既往史
                'GRS' => ['title' => '个人史', 'key' => "gerenshi"], // 个人史
                'HYS' => ['title' => '婚育史', 'key' => "hys"], // 婚育史
                'YJJHYS' => ['title' => '月经史', 'key' => "yjjhys"], // 月经及婚育史
                'YJJHYS_2' => ['title' => '月经史', 'key' => "yjjhys_2"], // 月经及婚育史
                'JZS' => ['title' => '家族史', 'key' => "jzs"], // 家族史
                'TGJC' => ['title' => '体格检查', 'key' => "tgjc"], // 体格检查
                'FZJC' => ['title' => '辅助检查', 'key' => "fzjc"], // 辅助检查
                'CHH' => ['title' => '床号', 'key' => "bed_no"], // 床号
                'ZYH' => ['title' => '住院号', 'key' => "hospital_no"], // 住院号
                'CBZD' => ['title' => '初步诊断', 'key' => "diagnose_list"], // 主要诊断
            ]
        ]; */

        $bllb_field = [
            '1' => [
                'XM' => ['title' => '姓名', 'key' => "name"],
                'RYRQ' => ['title' => '入院日期', 'key' => "ry_time"],
                'XB' => ['title' => '性别', 'key' => "sex"],
                'CYRQ' => ['title' => '出院日期', 'key' => "cy_time"], // 出院日期
                'NL' => ['title' => '年龄', 'key' => "age"],
                'ZYTS' => ['title' => '住院天数', 'key' => "zyts"], // 住院天数
                'RYQK' => ['title' => '入院情况', 'key' => "ryqk"], // 入院情况:
                'RYZD' => ['title' => '初步诊断', 'key' => "cbzd"], // 初步诊断::
                'ZLJG' => ['title' => '诊疗经过', 'key' => "zljg"], // 诊疗经过
                'CYQK' => ['title' => '出院情况', 'key' => "cyqk"], // 出院情况
                'CYZD' => ['title' => '出院诊断', 'key' => "cyzd"], // 出院诊断:
                'CYYZ' => ['title' => '出院医嘱', 'key' => "cyyz"], // 出院医嘱
                'ZYH' => ['title' => '住院号', 'key' => "hospital_no"], // 住院号
            ],
            '292' => [
                '一般项目' => [],
                '记录内容' => [],
                '检查内容' => [],
                '诊断内容' => [],
            ]
        ];

        //打印当前时间
        $bllmConfigs = [];
        if (isset($bllb_field[$bllb])) {
            if ($bllb == 292) {
                // 对于292，先从GY_BLLM获取字段配置
                // 先找到bllb292的父级记录（p_id=0, field='bllb292'）
                $parentRecord = GY_BLLM::query()
                    ->where('field', '=', 'bllb292')
                    ->where('p_id', '=', 0)
                    ->first();

                if ($parentRecord) {
                    // 查询该父级下的所有子级配置，status=1，按sort排序
                    // 根据图片，p_id=1表示是id=1（入院记录）的子级
                    $bllmConfigs = GY_BLLM::query()
                        ->where('status', '=', '1')
                        ->where('p_id', '=', $parentRecord->id)
                        ->orderBy('sort', 'asc')
                        ->get(['id', 'p_id', 'name', 'field', 'lb', 'sort', 'status'])
                        ->toArray();
                } else {
                    // 如果没有找到父级记录，尝试直接查询p_id=1的记录（入院记录的子级）
                    $bllmConfigs = GY_BLLM::query()
                        ->where('status', '=', '1')
                        ->where('p_id', '=', 1)
                        ->orderBy('sort', 'asc')
                        ->get(['id', 'p_id', 'name', 'field', 'lb', 'sort', 'status'])
                        ->toArray();

                    // 如果还是没有数据，则查询所有p_id>0且status=1的记录
                    if (empty($bllmConfigs)) {
                        $bllmConfigs = GY_BLLM::query()
                            ->where('status', '=', '1')
                            ->where('p_id', '>', 0)
                            ->orderBy('sort', 'asc')
                            ->get(['id', 'p_id', 'name', 'field', 'lb', 'sort', 'status'])
                            ->toArray();
                    }
                }

                // 获取所有需要查询的字段
                $fields = array_unique(array_column($bllmConfigs, 'field'));
                $fields = array_filter($fields); // 过滤空值

                // 临时调试日志（可以删除）
                // Log::info('GY_BLLM配置', ['configs_count' => count($bllmConfigs), 'fields' => $fields, 'blbh' => $blbh]);

                if (!empty($fields)) {
                    // 保持字段名原样（不转小写），因为数据库字段可能是大写
                    $select = implode(',', $fields);
                    $query = Bllb292::query();
                    $res = $query->select(DB::raw($select))->where('BLBH', (string)$blbh)->first();
                    $res = !empty($res) ? $res->attributesToArray() : [];

                    // 临时调试日志（可以删除）
                    // Log::info('Bllb292查询结果', ['res' => $res, 'select' => $select]);
                } else {
                    $res = [];
                }
            } else {
                $select = implode(',', array_keys($bllb_field[$bllb]));
                if ($bllb == 1) {
                    $query = Bllb1::query();
                } else {
                    $query = Bllb292::query();
                }
                $res = $query->select(DB::raw($select))->where('BLBH', (string)$blbh)->first();
                $res = !empty($res) ? $res->attributesToArray() : [];
            }
        } else {
            $res = [];
        }
        $newData["is_pormat"] = 1;
        if (empty($res)) {
            $newData["is_pormat"] = 0;
        }
        //打印当前时间
        //Log::info('打印当前时间2', ['time' => date('Y-m-d H:i:s', time())]);
        $resData = [];
        if ($bllb == 292) {
            // 根据GY_BLLM配置组织数据，按类别分组
            if (!empty($bllmConfigs)) {
                // 初始化类别数组
                $resData = [
                    '一般项目' => [],
                    '记录内容' => [],
                    '检查内容' => [],
                    '诊断内容' => [],
                ];

                foreach ($bllmConfigs as $config) {
                    $lb = $config['lb'] ?: '其他';
                    $field = $config['field'];
                    $name = $config['name'];

                    if (empty($field)) {
                        continue; // 跳过没有字段名的配置
                    }

                    // 从查询结果中获取字段值，尝试大小写两种方式
                    $value = '';
                    if (!empty($res)) {
                        //如果$field不包含逗号走以下逻辑，如果包含逗号，逗号分隔，读取每个值，使用斜杠/分隔拼接，然后赋值给$value
                        if (strpos($field, ",") !== false) {
                            $fieldArray = explode(",", $field);
                            foreach ($fieldArray as $val) {
                                $val = trim($val); // 去除空格
                                $fieldValue = '';
                                // 先尝试原字段名（可能是大写）
                                if (isset($res[$val])) {
                                    $fieldValue = $res[$val];
                                }
                                // 再尝试小写字段名
                                elseif (isset($res[strtolower($val)])) {
                                    $fieldValue = $res[strtolower($val)];
                                }
                                // 再尝试大写字段名
                                elseif (isset($res[strtoupper($val)])) {
                                    $fieldValue = $res[strtoupper($val)];
                                }
                                // 只有当找到值时才拼接
                                if ($fieldValue !== '') {
                                    $value .= $fieldValue . "/";
                                }
                            }
                            $value = rtrim($value, "/");
                        } else {
                            // 先尝试原字段名（可能是大写）
                            if (isset($res[$field])) {
                                $value = $res[$field];
                            }
                            // 再尝试小写字段名
                            elseif (isset($res[strtolower($field)])) {
                                $value = $res[strtolower($field)];
                            }
                            // 再尝试大写字段名
                            elseif (isset($res[strtoupper($field)])) {
                                $value = $res[strtoupper($field)];
                            }
                        }
                    }

                    // 生成唯一key，使用field的小写作为key
                    //$key = strtolower($field);

                    // 如果类别不存在，则初始化为空数组
                    if (!isset($resData[$lb])) {
                        $resData[$lb] = [];
                    }

                    //如果是一般信息的月经生育史，如果xb中包含女才添加，如果xb中包含男不添加
                    $xb = $res['XB'] ?? '';
                    if ($lb == '记录内容' && $field == 'YJJHYS') {
                        if (strpos($xb, '女') !== false) {
                            $resData[$lb][] = [
                                'title' => $name,
                                'value' => $value,
                                'sort' => $config['sort']
                            ];
                        }
                    } else {
                        // 按类别分组存储
                        $resData[$lb][] = [
                            'title' => $name,
                            'value' => $value,
                            'sort' => $config['sort']
                        ];
                    }
                }

                // 对每个类别内的数据按sort排序
                foreach ($resData as $lbKey => $lbData) {
                    if (!empty($lbData)) {
                        uasort($resData[$lbKey], function ($a, $b) {
                            return ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0);
                        });
                    }
                }
            } else {
                // 如果没有配置，返回空结构
                $resData = [
                    '一般项目' => [],
                    '记录内容' => [],
                    '检查内容' => [],
                    '诊断内容' => [],
                ];
            }
        } else {
            foreach ($res as $k => $v) {
                $resData[$bllb_field[$bllb][$k]['key']] = [
                    'title' => $bllb_field[$bllb][$k]['title'],
                    'value' => $v
                ];
            }
            $resData['hospital_no']['value'] = $brry[0]['AAA28'] ?? '';
        }
        /* foreach ($res as $k => $v) {
            $resData[$bllb_field[$bllb][$k]['key']] = [
                'title' => $bllb_field[$bllb][$k]['title'],
                'value' => $v
            ];
        } */


        //打印当前时间
        //Log::info('打印当前时间3', ['time' => date('Y-m-d H:i:s', time())]);
        if ($bllb == 292 && !empty($res)) {
            $text1 = !empty($resData["yjjhys_2"]) ? $resData["yjjhys_2"]["value"] : "";
            $text1 = str_replace("{", "", trim($text1, "}"));
            $matches = explode("}", $text1);
            $yjjhys = [];
            if (!empty($matches[3])) {
                $yjjhys = [$matches[0], $matches[1], $matches[2], ($matches[3] == "已绝经" ? "" : $matches[4]), $matches[5]];
            }
            $resData["yjjhys_format"] = ['title' => '月经及婚育史', "value" => $yjjhys];
        }
        $newData = array_merge($newData, $resData);
        //打印当前时间
        //Log::info('打印当前时间4', ['time' => date('Y-m-d H:i:s', time())]);
        if (!empty($blxg[0]['diagnose_list'])) {
            //            $diagnoseList = base64_decode($blxg[0]['diagnose_list']);
            //            $diagnoseList = $diagnoseList ? explode(',', $diagnoseList) : [];
            //            $newData['diagnose_list'] = $diagnoseList ?: [];
        }
        if (!empty($newData['name']) && !empty($res)) {
            $newData['name']['value'] = desensitize($newData['name']['value'], 1, 1, '*');
        }
        //打印当前时间
        //Log::info('打印当前时间5', ['time' => date('Y-m-d H:i:s', time())]);
        if (!empty(request()->post('is_tm'))) {
            if (!empty($newData['local_address'])) {
                $newData['local_address']['value'] = desensitize($newData['local_address']['value'], 2, 0, '*');
            }
        }
        //打印当前时间
        //Log::info('打印当前时间6', ['time' => date('Y-m-d H:i:s', time())]);
        // 医师签名
        $newData['doctor_name'] = '';
        if (!empty($blxg[0]['BLBH'])) {
            $SYYS = EMR_BL_BLSY::query()->where('BLBH', '=', $blbh)->pluck('SYYS')->toArray();
            if ($SYYS) {
                $staffList = Staff::query()->whereIn('code', $SYYS)->get(['code', 'name', 'ygjb_text'])->toArray();
                $nameList = [];
                foreach ($staffList as $staffInfo) {
                    $nameList[$staffInfo['code']] = $staffInfo['name'] . "（" . $staffInfo['ygjb_text'] . "）";
                }
                $newData['doctor_name'] = implode('、', $nameList);
            }
        }

        $newData['sxys_name'] = '';
        if (!empty($blxg[0]['SXYS'])) {
            $sxys = $blxg[0]['SXYS'];
            if ($sxys) {
                $staffList = Staff::query()->where('code', $sxys)->get(['name', 'ygjb_text'])->toArray();
                if (!empty($staffList)) {
                    $staffInfo = $staffList[0];
                    $name = $staffInfo['name'] . "（" . $staffInfo['ygjb_text'] . "）";
                    $newData['sxys_name'] = $name;
                }
            }
        }

        // 创建时间（cjsj）、修改时间（zxsj）、完成时间
        $newData['HJNR'] = $blxg[0]["HJNR"] ?? "";
        $newData['CJSJ'] = $blxg[0]['CJSJ'];
        $newData['ZXSJ'] = $blxg[0]['first_blsy_time'];
        $newData['WCSJ'] = $blxg[0]['WCSJ'];
        $newData['YWSJ'] = $blxg[0]['YWSJ'];
        $newData['HTML_PRINT'] = $blxg[0]['HTML_PRINT'];

        // 入院时间
        /* if (!empty($newData['ry_time']) && !empty($newData['ry_time']['value'])) {
            $newData['ry_time']['value'] = date('Y-m-d H:i', strtotime($newData['ry_time']['value']));
            $newData['ry_time']['value'] = str_replace('00:00', '', $newData['ry_time']['value']);
        }
        // 出院时间
        if (!empty($newData['cy_time']) && !empty(trim($newData['cy_time']['value']))) {
            $newData['cy_time']['value'] = date('Y-m-d H:i', strtotime($newData['cy_time']['value']));
            $newData['cy_time']['value'] = str_replace('00:00', '', $newData['cy_time']['value']);
        }
        // 记录时间
        if (!empty($newData['record_time']['value'])) {
            $newData['record_time']['value'] = date('Y-m-d H:i', strtotime($newData['record_time']['value']));
            $newData['record_time']['value'] = str_replace('00:00', '', $newData['record_time']['value']);
        } */

        return $newData;
    }

    /**
     * @param string $no
     * @return array|bool
     * 解析病例内容
     */
    public static function analysisCase($zyh = [])
    {
        // 获取最后一次质控的住院号
        $lastId = Setting::query()->where('name', '=', 'platform_ruyuan_last_id')->pluck('content')->first();
        $lastNo = $lastId ?: 0;
        if ($zyh) {
            $lastNo = 0;
        }
        $pageSize = 100;
        try {
            $oracleService = new OracleService();
            while (1) {
                $query = EMR_BL_BL01::query()->whereIn("BLLB", [1, 292]);
                if ($zyh) {
                    $query = $query->whereIn('JZHM', $zyh);
                } else {
                    $query = $query->where('BLBH', '>', $lastNo)
                        ->orderBy("BLBH", "asc")
                        ->LIMIT($pageSize);
                }

                $res01 = $query->get()->toArray();
                if (!$res01) {
                    break;
                }
                foreach ($res01 as $bl01) {
                    if ($bl01['BLZT'] == 9) {
                        Bllb292::query()->where(['BLBH' => $bl01['BLBH']])->delete();
                        continue;
                    }
                    $lastNo = $bl01['BLBH'];
                    $res = EMR_BL_BLXG::getById($bl01['BLBH']);
                    if (!$res) {
                        continue;
                    }
                    $caseContent = $res[0]['HJNR'];
                    $caseContent = str_replace(['{', '}'], '', $caseContent);
                    if (empty($caseContent)) {
                        $oracleService->getBl01Data($bl01['BLBH']);
                    } else {
                        if ($bl01['BLLB'] == 1) {
                            self::analysisCaseCy($bl01, $res);
                        } elseif ($bl01['BLLB'] == 292) {
                            self::analysisCaseRy($bl01, $caseContent);
                        }
                    }
                }
                if ($zyh) {
                    break;
                }
            }

            Setting::query()->where('name', '=', 'platform_ruyuan_last_id')->update(['content' => $lastNo]);
        } catch (\Throwable $e) {
            Log::error("病例处理失败", ['msg' => $e->getMessage(), 'line' => $e->getLine()]);
            echo $e->getMessage() . '，所在行：' . $e->getLine();
        }
        return true;
    }


    /**
     * @param array $no
     * @param string $caseContent
     * @return array|bool
     * 出院记录解析病例内容
     */
    public
    static function analysisCaseRy(
        $bl01 = [],
        $caseContent = ""
    ) {
        if (!$caseContent || !$bl01) {
            return false;
        }
        $title = [
            "姓名:",
            "出生地:",
            "性别:",
            "职业:",
            "年龄:",
            "入院时间:",
            "民族:",
            "记录时间:",
            "婚姻:",
            "病史陈述者:",
            "主诉:",
            "现病史:",
            "既往史:",
            "个人史:",
            "月经及婚育史:",
            "婚育史:",
            "家族史:",
            "体格检查",
            "辅助检查",
            "初步诊断",
            "医师签名",
            "床号:",
            "住院号:"
        ];

        // 整理数据，将数据整理成数组结构
        $caseContent = str_replace("月经婚育史", "月经及婚育史", $caseContent);
        $caseContent = str_replace("入院诊断", "初步诊断", $caseContent);

        $isWomen = strpos($caseContent, '月经及婚育史');
        $caseContent = str_replace(" ", "", $caseContent);
        $caseContent = str_replace("：", ":", $caseContent);

        // 通过原始数据获取年龄
        preg_match("/年龄[:：]?(.*?)入院时间/", $caseContent, $match);
        $NL = $match[1] ?? 0;

        foreach ($title as $t) {
            // 如果病例中有月经及婚育史，则不通过婚育史提取数据
            if ($isWomen && $t == '婚育史:') {
                unset($title[15]);
                continue;
            }

            $caseContent = str_replace($t, "|&|" . $t . '||', $caseContent);
        }

        $caseContentArr = explode("|&|", $caseContent);
        $caseContentArr = array_filter($caseContentArr);

        $newData = [];
        foreach ($caseContentArr as $item) {
            $itemArr = explode("||", $item);
            if (!in_array($itemArr[0], $title) || !isset($itemArr[1])) {
                continue;
            }
            $info['title'] = $itemArr[0];
            $info['value'] = $itemArr[1] ?? '';
            if ($info['title'] == '初步诊断') {
                $cbzd = $info['value'];
                $cbzd = str_replace("\r\n", "!!", $cbzd);
                $cbzd = str_replace("\n", "!!", $cbzd);
                $info['value'] = str_replace('!!', '|', $cbzd);
            } elseif ($info['title'] == '辅助检查') {
                $fzjc = trim($info['value']);
                $fzjc = str_replace("\r\n", "!!", $fzjc);
                $fzjc = str_replace("\n", "!!", $fzjc);
                $fzjc = explode("!!", $fzjc);
                $fzjc = array_chunk($fzjc, 4);
                $info['value'] = $fzjc;
            } elseif ($info['title'] == '体格检查') {
                $tgjc = trim($info['value']);
                $tgjc = str_replace("\r\n", "<br />", $tgjc);
                $tgjc = str_replace("脉搏", "  脉搏", $tgjc);
                $tgjc = str_replace("呼吸", "  呼吸", $tgjc);
                $tgjc = str_replace("血压", "  血压", $tgjc);
                $tgjc = str_replace("BP", "  BP", $tgjc);
                $info['value'] = $tgjc;
            }
            $info['md5'] = md5($itemArr[0]);
            $newData[] = $info;
        }
        $newData = array_column($newData, null, 'md5');

        // 解析专科检查
        $fzjc = !empty($newData[md5('体格检查')]) ? $newData[md5('体格检查')]['value'] : '';
        $zkjcStr = '';
        if (!empty($fzjc) && (strpos($fzjc, '专科检查') !== false || strpos($fzjc, '专科情况') !== false)) {
            $zkjc = str_replace(['专科检查', '专科情况'], '|&|专科检查', $fzjc);
            $zkjcArr = explode('|&|', $zkjc);
            $zkjcStr = !empty($zkjcArr[1]) ? $zkjcArr[1] : '';
        }
        $newData[md5('专科检查')] = [
            'title' => '专科检查',
            'value' => $zkjcStr,
            'md5' => md5('专科检查')
        ];

        // 初步诊断数据解析成数组
        $cbzdData = '';
        $diagnosList = [];
        if (isset($newData[md5('初步诊断')])) {
            $cbzdData = $newData[md5('初步诊断')]['value'];
        }
        if ($cbzdData) {
            $cbzdData = str_replace([':', '滨医_住院签名', '滨医_', '住院签名', '主治签名', '{}', '!'], '', $cbzdData);
            $cbzdData = preg_replace("/(\d)+[\.]/", '|', $cbzdData);
            $cbzdData = explode("|", $cbzdData);
            $cbzdData = array_filter($cbzdData);
            foreach ($cbzdData as $c) {
                preg_match_all("/第(\d+)页/", $c, $pagePregRes);
                if ($pagePregRes[1]) {
                    break;
                }
                $diagnosList[] = str_replace(' ', '', $c);
            }
        }
        $addData = [
            'ZYH' => $bl01['JZHM'],
            'AAA28' => $bl01['BRBH'],
            'AAB01' => $bl01['AAB01'],
            'AAC01' => $bl01['AAC01'],
            'XM' => (!empty($newData[md5('姓名:')]) ? trim($newData[md5('姓名:')]['value']) : ''),
            'CSD' => (!empty($newData[md5('出生地:')]) ? trim($newData[md5('出生地:')]['value']) : ''),
            'XB' => (!empty($newData[md5('性别:')]) ? trim($newData[md5('性别:')]['value']) : ''),
            'ZHY' => (!empty($newData[md5('职业:')]) ? trim($newData[md5('职业:')]['value']) : ''),
            'NL' => $NL,
            'RYSJ' => (!empty($newData[md5('入院时间:')]) ? trim($newData[md5('入院时间:')]['value']) : ''),
            'MZ' => (!empty($newData[md5('民族:')]) ? trim($newData[md5('民族:')]['value']) : ''),
            'JLSJ' => (!empty($newData[md5('记录时间:')]) ? trim($newData[md5('记录时间:')]['value']) : ''),
            'HY' => (!empty($newData[md5('婚姻:')]) ? trim($newData[md5('婚姻:')]['value']) : ''),
            'BSCSZ' => (!empty($newData[md5('病史陈述者:')]) ? trim($newData[md5('病史陈述者:')]['value']) : ''),
            'ZHS' => (!empty($newData[md5('主诉:')]) ? trim($newData[md5('主诉:')]['value']) : ''),
            'XBS' => (!empty($newData[md5('现病史:')]) ? trim($newData[md5('现病史:')]['value']) : ''),
            'JWS' => (!empty($newData[md5('既往史:')]) ? trim($newData[md5('既往史:')]['value']) : ''),
            'YJJHYS' => (!empty($newData[md5('月经及婚育史:')]) ? trim($newData[md5('月经及婚育史:')]['value']) : ''),
            'HYS' => (!empty($newData[md5('婚育史:')]) ? trim($newData[md5('婚育史:')]['value']) : ''),
            'JZS' => (!empty($newData[md5('家族史:')]) ? trim($newData[md5('家族史:')]['value']) : ''),
            'TGJC' => (!empty($newData[md5('体格检查')]) ? trim($newData[md5('体格检查')]['value']) : ''),
            'FZJC' => (!empty($newData[md5('辅助检查')]) ? json_encode($newData[md5('辅助检查')]['value'], 256) : ''),
            'CBZD' => json_encode($diagnosList, 256),
            'YSQM' => (!empty($newData[md5('医师签名')]) ? trim($newData[md5('医师签名')]['value']) : ''),
            'CHH' => (!empty($newData[md5('床号:')]) ? trim($newData[md5('床号:')]['value']) : ''),
            'ZHUANKE' => (!empty($newData[md5('专科检查')]) ? trim($newData[md5('专科检查')]['value']) : ''),
            'CBZB_FIRST' => !empty($diagnosList[0]) ? $diagnosList[0] : '',
        ];
        $addData['RYSJ'] = date("Y-m-d H:i:s", strtotime($addData['RYSJ']));
        $addData['RYSJ'] = str_replace('00:00:00', '', $addData['RYSJ']);
        $addData['JLSJ'] = date("Y-m-d H:i:s", strtotime($addData['JLSJ']));
        $addData['JLSJ'] = str_replace('00:00:00', '', $addData['JLSJ']);

        if (empty($addData['YJJHYS'])) {
            $addData['YJJHYS'] = $addData['HYS'];
        }

        $jsonData = json_encode($newData, 256);
        if ($diagnosList && is_array($diagnosList)) {
            $diagnosList = implode(',', $diagnosList);
        } elseif (!is_array($diagnosList)) {
            exit;
        }
        $updata = ['analysis_index' => ($bl01['analysis_index'] + 1), 'diagnose_list' => ($diagnosList ? base64_encode($diagnosList) : ''), 'analysis_case' => $jsonData];
        if (!empty($newData[md5('现病史:')])) {
            $xbsData = $newData[md5('现病史:')]['value'];
            // 整理数据，将数据整理成数组结构
            $xbsData = str_replace("\r\n", "", $xbsData);
            $xbsData = str_replace("\n", "", $xbsData);
            $xbsData = str_replace("：", ":", $xbsData);
            $updata['ryjl_xbs'] = $xbsData ?: '';
        }
        $res = EMR_BL_BL01::updateById($bl01['BLBH'], $updata);
        Bllb292::query()->updateOrInsert(['BLBH' => $bl01['BLBH']], $addData);
        return $jsonData;
    }


    /**
     * @param string $no
     * @return array|bool
     * 出院记录解析病例内容
     */
    public
    static function analysisCaseCy(
        $bl01 = [],
        $res = []
    ) {
        $caseContent = $res[0]['HJNR'];
        if (!$caseContent || !$bl01) {
            return false;
        }
        $title = [
            "姓名:",
            "入院日期:",
            "性别:",
            "出院日期:",
            "年龄:",
            "住院天数:",
            "入院情况:",
            "初步诊断:",
            "诊疗经过:",
            "出院情况:",
            "出院诊断:",
            "出院医嘱:",
            "床号:",
            "住院号:",
            "每次复查时请携带"
        ];

        $caseContent = str_replace(" ", "", $caseContent);
        $caseContent = str_replace("：", ":", $caseContent);
        foreach ($title as $t) {
            $caseContent = str_replace($t, "|&|" . $t . '||', $caseContent);
        }

        $caseContentArr = explode("|&|", $caseContent);
        $caseContentArr = array_filter($caseContentArr);

        $newData = [];
        foreach ($caseContentArr as $item) {
            $itemArr = explode("||", $item);
            if (!in_array($itemArr[0], $title) || !isset($itemArr[1])) {
                continue;
            }
            $info['title'] = $itemArr[0];
            $info['value'] = $itemArr[1] ?? '';
            if ($info['title'] == '初步诊断') {
                $cbzd = $info['value'];
                $cbzd = str_replace("\r\n", "!!", $cbzd);
                $cbzd = str_replace("\n", "!!", $cbzd);
                $info['value'] = str_replace('!!', '|', $cbzd);
            }
            $info['md5'] = md5($itemArr[0]);
            $newData[] = $info;
        }
        $newData = array_column($newData, null, 'md5');
        unset($newData['78e02f7fc69578f7a22f1ad1eaa710a2']);

        // 初步诊断数据解析成数组
        $jsonData = json_encode($newData, 256);
        $updata = [
            'analysis_index' => ($bl01['analysis_index'] + 1),
            'analysis_case' => $jsonData
        ];
        EMR_BL_BL01::updateById($bl01['BLBH'], $updata);

        return $jsonData;
    }

    public
    function getSurgeryData(
        $blbh,
        $bllb
    ) {
        $dataList = EMR_BL_BL01::query()
            ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.BLBH', '=', $blbh)
            ->where('BLLB', '=', $bllb)
            ->where('BLZT', '!=', 9)
            ->get(['EMR_BL_BL01.BLBH', 'BRXM', 'JZHM', 'BLMC', 'BRBH', 'BRXM', 'HJNR', 'surgery_content'])
            ->toArray();

        $returnData = [];
        foreach ($dataList as $value) {
            if (!empty($value['surgery_content'])) {
                $returnData[] = [
                    'is_format' => 1,
                    'surgery_data' => json_decode($value['surgery_content'], true),
                ];
            } else {
                $surgeryType = '';
                if (stripos($value['HJNR'], '手术风险评估表')) {
                    $surgeryType = 1;
                } elseif (stripos($value['HJNR'], '手术安全核查表')) {
                    $surgeryType = 2;
                } elseif (stripos($value['HJNR'], '手术同意书')) {
                    $surgeryType = 3;
                } elseif (stripos($value['HJNR'], '手术记录')) {
                    $surgeryType = 4;
                }
                $returnData[] = [
                    'is_format' => 0,
                    'surgery_data' => [
                        'type' => $surgeryType,
                        'content' => $value['HJNR']
                    ],
                ];
            }
        }

        return $returnData;
    }


    /**
     * @param int $zyh
     * @return array
     * 获取病例的质控结果
     */
    public function getCaseQuality($zyh = 0, $showCorrection = 0, $source = '')
    {

        $info = PatientInfo::query()
            ->where('MED_REC_ID', $zyh)
            ->select(['quality_time', 'is_case', 'edit_docter_document', 'edit_docter'])
            ->get()->toArray();

        $caseRule = CaseRule::query()->where("status", 1)->get()->toArray();
        $caseRule = array_column($caseRule, null, 'id');

        $newData = CaseQuality::getByJZHM($zyh);
        $score = 0;
        if ($newData) {
            $appealIds = array_column($newData, 'appeal_id');
            $appeal = Appeal::query()->whereIn("id", $appealIds)->whereIn('status', [0, 1,2])->get()->toArray();
            $appeal = array_column($appeal, null, 'id');

            // 自定义规则
            $ruleSetting = RuleSetting::query()->where('status', '=', 1)->get()->toArray();
            $ruleSetting = array_column($ruleSetting, null, 'id');
            foreach ($newData as $k => &$v) {
                $basis = json_decode($v['basis'], true);
                if ($basis) {
                    foreach ($basis as &$b) {
                        if (is_array($b) && !empty($b['BLBH'])) {
                            unset($b['BLBH']);
                        }
                    }
                }
                $v['basis'] = $basis ?: [];
                // 自定义配置的规则
                if ($v['rule_id'] > 1000000 && isset($ruleSetting[$v['rule_id'] - 1000000])) {
                    if (empty(data_get($ruleSetting, $v['rule_id'] - 1000000, ""))) {
                        unset($newData[$k]);
                        continue;
                    }
                    $v['notice'] = $ruleSetting[$v['rule_id'] - 1000000]['description'];
                    $v['score'] = $ruleSetting[$v['rule_id'] - 1000000]['score'];
                    $v['category'] = $ruleSetting[$v['rule_id'] - 1000000]['case_type'];
                    $v['error_field'] = $ruleSetting[$v['rule_id'] - 1000000]['case_type'];
                    $v['level'] = $ruleSetting[$v['rule_id'] - 1000000]['error_level'];
                    $v['one_no'] = $ruleSetting[$v['rule_id'] - 1000000]['is_not'] ?? 0;
                } else {
                    if (empty(data_get($caseRule, $v['rule_id'], ""))) {
                        unset($newData[$k]);
                        continue;
                    }
                    $v['notice'] = empty($caseRule[$v['rule_id']]) ? '' : ($caseRule[$v['rule_id']]['notice'] ?? '');
                    $v['score'] = empty($caseRule[$v['rule_id']]) ? '' : ($caseRule[$v['rule_id']]['score'] ?? '');
                    $v['category'] = empty($caseRule[$v['rule_id']]) ? '' : ($caseRule[$v['rule_id']]['category'] ?? '');
                    $v['error_field'] = empty($caseRule[$v['rule_id']]) ? '' : ($caseRule[$v['rule_id']]['category'] ?? '');
                    $v['level'] = empty($caseRule[$v['rule_id']]) ? '' : ($caseRule[$v['rule_id']]['level'] ?? '');
                    $v['one_no'] = $caseRule[$v['rule_id']]['one_no'] ?? 0;
                    $v['is_ai'] = $caseRule[$v['rule_id']]['is_ai'] ?? 0;
                }
                $v['desc'] = $v['basis'] ?? '';
                $v['is_appeal'] = empty($appeal[$v['appeal_id']]) ? 0 : 1;
                $v['appeal_id'] = $appeal[$v['appeal_id']]['id'] ?? 0;
                $v['appeal_status'] = $appeal[$v['appeal_id']]['status'] ?? -1;
                $v['appeal_type'] = $appeal[$v['appeal_id']]['type'] ?? 0;
                $v['reject_content'] = $appeal[$v['appeal_id']]['defect_content'] ?? '';
                $v['case_docter'] = $appeal[$v['appeal_id']]['case_docter'] ?? '';
                $v['defect_content'] = $appeal[$v['appeal_id']]['defect_content'] ?? "";
                $v['appeal_docter'] = $appeal[$v['appeal_id']]['appeal_docter'] ?? "";
                $v['is_artificial'] = $v['is_artificial'] ?? 0;
                $v['sort'] = $v['is_artificial'] == 1 ? -1 : $v['level']; // 用于排序，预警信息排第一，人工质控排在第二，强制问题第三
                $v['cate'] = 2;
                // 隐藏已整改数据
                if ($showCorrection == 2 and ($v['is_correction'] == 1 || in_array($v['appeal_status'], ['1']))) {
                    unset($newData[$k]);
                    continue;
                }

                # 申诉审核通过，申诉中，忽略的，则不在展示
                if (in_array($v['appeal_status'], [1]) || $v['is_ignore'] == 1) {
                    unset($newData[$k]);
                    continue;
                }

                // 查看申诉过的质控结果
                if ($source == 'appeal' && empty($v['appeal_id'])) {
                    unset($newData[$k]);
                    continue;
                }

                // 未整改的数据才计算分数
                if (empty($v['is_correction'])) {
                    $score += round($v['score'], 1);
                }
            }
        }
        //
        $sendMsgRows = QualitySendMsgLog::query()
            ->where('zyh', '=', (string)$zyh)
            ->where('is_warning_msg', '=', 1)
            ->where('status', '=', 2)
            ->get()->toArray();

        $parseWarningCreatedAt = function ($createdAt) {
            if (is_numeric($createdAt)) {
                return (int)$createdAt;
            }

            $timestamp = strtotime((string)$createdAt);
            return $timestamp === false ? 0 : $timestamp;
        };

        $sendMsg = [];
        foreach ($sendMsgRows as $sendMsgRow) {
            $dataId = trim((string)($sendMsgRow['data_id'] ?? ''));
            $groupKey = $dataId !== ''
                ? 'data_id:' . $dataId
                : 'rule:' . (string)($sendMsgRow['zyh'] ?? $zyh) . ':' . (string)($sendMsgRow['rule_id'] ?? '');

            if (
                empty($sendMsg[$groupKey])
                || $parseWarningCreatedAt($sendMsgRow['created_at'] ?? 0) >= $parseWarningCreatedAt($sendMsg[$groupKey]['created_at'] ?? 0)
            ) {
                $sendMsg[$groupKey] = $sendMsgRow;
            }
        }

        $sendMsg = array_values($sendMsg);

        foreach ($sendMsg as $s) {
            // 如果已过整改期限，则跳过
            if ($parseWarningCreatedAt($s["created_at"] ?? 0) + intval($s['second']) < time()) {
                continue;
            }
            $ruleid = $s['rule_id'];
            $rule = CaseRule::query()->where('id', $ruleid)->first();
            $s['basis'] = json_decode($s["msg_yj"], true);
            $s['level'] = '-1';
            $s['notice'] = $s['quality_content'];
            $s['error_field'] = $rule['category'];
            $s['category'] = "其他";
            $s['score'] = 0;
            $s['sort'] = -2;
            $newData[] = $s;
        }

        if ($newData) {
            array_multisort(array_column($newData, "sort"), SORT_ASC, $newData); // 假设按’sort’字段升序排序
        }

        $summary = ["cyjl" => [], "ryjl" => [], 'bcjl' => [], 'ssjl' => [], 'tys' => [], 'qt' => []];
        foreach ($newData as $k => $n) {
            if ($n["category"] == "出院记录") {
                $summary["cyjl"][] = $n;
            } elseif ($n["category"] == "入院记录") {
                $summary["ryjl"][] = $n;
            } elseif (strpos($n["category"], "病程记录") !== false) {
                $summary["bcjl"][] = $n;
            } elseif (strpos($n["category"], "手术记录") !== false) {
                $summary["ssjl"][] = $n;
            } elseif (strpos($n["category"], "同意书") !== false) {
                $summary["tys"][] = $n;
            } else {
                $summary["qt"][] = $n;
            }
        }

        $zy_brry = ZY_BRRY::query()
            ->where('ZYH', $zyh)
            ->select(['CH', 'AAA28', 'ZYCS'])
            ->first();
        if ($zy_brry) {
            $zy_brry = $zy_brry->toArray();
        }

        // case_quality_count中的数据改为已读
        $quality_date = date('Y-m-d', strtotime("-1 day"));
        CaseQualityCount::query()
            ->where('zyh', $zyh)
            ->where('quality_date', $quality_date)
            ->where('is_viewed', 0)
            ->update(['is_viewed' => 1]);

        return [
            'score' => 100 - $score,
            'data' => $newData,
            'total' => count($newData),
            'summary' => $summary,
            'run_score' => 0,
            'CH' => $zy_brry['CH'] ?? '',
            'AAA28' => $zy_brry['AAA28'] ?? '',
            'ZYCS' => $zy_brry['ZYCS'] ?? '',
            'is_case' => $info[0]['is_case'] ?? '',
            'appeal_docter' => $info[0]['edit_docter'] ?? '',
            'appeal_document' => $info[0]['edit_docter_document'] ?? '',
            'quality_time' => !empty($info[0]['quality_time']) ? date("Y-m-d H:i:s", $info[0]['quality_time']) : '',
        ];
    }


    /**
     * @param int $zyh
     * @return array
     * 获取病例的质控结果
     */
    public function getCaseQualityZm($zyh = 0, $showCorrection = 0, $source = '')
    {

        $info = PatientInfo::query()
            ->where('MED_REC_ID', $zyh)
            ->select(['zm_quality_time', 'is_case', 'edit_docter_document', 'edit_docter'])
            ->get()->toArray();

        $caseRule = CaseRule::query()->where("status", 1)->get()->toArray();
        $caseRule = array_column($caseRule, null, 'id');

        $newData = CaseQualityZm::getByJZHM($zyh);
        $score = 0;
        if ($newData) {
            $appealIds = array_column($newData, 'appeal_id');
            $appeal = Appeal::query()->whereIn("id", $appealIds)->get()->toArray();
            $appeal = array_column($appeal, null, 'id');

            // 自定义规则
            $ruleSetting = RuleSetting::query()->where('status', '=', 1)->get()->toArray();
            $ruleSetting = array_column($ruleSetting, null, 'id');
            foreach ($newData as $k => &$v) {
                $basis = json_decode($v['basis'], true);
                if ($basis) {
                    foreach ($basis as &$b) {
                        if (is_array($b) && !empty($b['BLBH'])) {
                            unset($b['BLBH']);
                        }
                    }
                }
                $v['basis'] = $basis ?: [];
                // 自定义配置的规则
                if ($v['rule_id'] > 1000000 && isset($ruleSetting[$v['rule_id'] - 1000000])) {
                    if (empty(data_get($ruleSetting, $v['rule_id'] - 1000000, ""))) {
                        unset($newData[$k]);
                        continue;
                    }
                    $v['notice'] = $ruleSetting[$v['rule_id'] - 1000000]['description'];
                    $v['score'] = $ruleSetting[$v['rule_id'] - 1000000]['score'];
                    $v['category'] = $ruleSetting[$v['rule_id'] - 1000000]['case_type'];
                    $v['error_field'] = $ruleSetting[$v['rule_id'] - 1000000]['case_type'];
                    $v['level'] = $ruleSetting[$v['rule_id'] - 1000000]['error_level'];
                    $v['one_no'] = $ruleSetting[$v['rule_id'] - 1000000]['is_not'] ?? 0;
                } else {
                    if (empty(data_get($caseRule, $v['rule_id'], ""))) {
                        unset($newData[$k]);
                        continue;
                    }
                    $v['notice'] = empty($caseRule[$v['rule_id']]) ? '' : ($caseRule[$v['rule_id']]['notice'] ?? '');
                    $v['score'] = empty($caseRule[$v['rule_id']]) ? '' : ($caseRule[$v['rule_id']]['score'] ?? '');
                    $v['category'] = empty($caseRule[$v['rule_id']]) ? '' : ($caseRule[$v['rule_id']]['category'] ?? '');
                    $v['error_field'] = empty($caseRule[$v['rule_id']]) ? '' : ($caseRule[$v['rule_id']]['category'] ?? '');
                    $v['level'] = empty($caseRule[$v['rule_id']]) ? '' : ($caseRule[$v['rule_id']]['level'] ?? '');
                    $v['one_no'] = $caseRule[$v['rule_id']]['one_no'] ?? 0;
                    $v['is_ai'] = $caseRule[$v['rule_id']]['is_ai'] ?? 0;
                }
                $v['desc'] = $v['basis'] ?? '';
                $v['is_appeal'] = empty($appeal[$v['appeal_id']]) ? 0 : 1;
                $v['appeal_id'] = $appeal[$v['appeal_id']]['id'] ?? 0;
                $v['appeal_status'] = $appeal[$v['appeal_id']]['status'] ?? -1;
                $v['appeal_type'] = $appeal[$v['appeal_id']]['type'] ?? 0;
                $v['reject_content'] = $appeal[$v['appeal_id']]['reject_content'] ?? '';
                $v['case_docter'] = $appeal[$v['appeal_id']]['case_docter'] ?? '';
                $v['defect_content'] = $appeal[$v['appeal_id']]['defect_content'] ?? "";
                $v['appeal_docter'] = $appeal[$v['appeal_id']]['appeal_docter'] ?? "";
                $v['is_artificial'] = $v['is_artificial'] ?? 0;
                $v['sort'] = $v['is_artificial'] == 1 ? -1 : $v['level']; // 用于排序，预警信息排第一，人工质控排在第二，强制问题第三
                $v['cate'] = 2;
                // 隐藏已整改数据
                if ($showCorrection == 2 and ($v['is_correction'] == 1 || in_array($v['appeal_status'], [0, 1]))) {
                    unset($newData[$k]);
                    continue;
                }

                # 申诉审核通过，申诉中，忽略的，则不在展示
                if (in_array($v['appeal_status'], [1]) || $v['is_ignore'] == 1) {
                    unset($newData[$k]);
                    continue;
                }

                // 查看申诉过的质控结果
                if ($source == 'appeal' && empty($v['appeal_id'])) {
                    unset($newData[$k]);
                    continue;
                }

                // 未整改的数据才计算分数
                if (empty($v['is_correction'])) {
                    $score += round($v['score'], 1);
                }
            }
        }
        //
        $sendMsg = QualitySendMsgLog::query()
            ->where('zyh', '=', (string)$zyh)
            ->where('is_warning_msg', '=', 1)
            ->where('status', '=', 2)
            ->get()->toArray();

        foreach ($sendMsg as $s) {
            // 如果已过整改期限，则跳过
            if (strtotime($s["created_at"]) + intval($s['second']) < time()) {
                continue;
            }
            $s['basis'] = json_decode($s["msg_yj"], true);
            $s['notice'] = $s['quality_content'];
            $s['error_field'] = $s['title'];
            $s['category'] = "其他";
            $s['score'] = 0;
            $s['sort'] = -2;
            $newData[] = $s;
        }

        if ($newData) {
            array_multisort(array_column($newData, "sort"), SORT_ASC, $newData); // 假设按’sort’字段升序排序
        }

        $summary = ["cyjl" => [], "ryjl" => [], 'bcjl' => [], 'ssjl' => [], 'tys' => [], 'qt' => []];
        foreach ($newData as $k => $n) {
            if ($n["category"] == "出院记录") {
                $summary["cyjl"][] = $n;
            } elseif ($n["category"] == "入院记录") {
                $summary["ryjl"][] = $n;
            } elseif (strpos($n["category"], "病程记录") !== false) {
                $summary["bcjl"][] = $n;
            } elseif (strpos($n["category"], "手术记录") !== false) {
                $summary["ssjl"][] = $n;
            } elseif (strpos($n["category"], "同意书") !== false) {
                $summary["tys"][] = $n;
            } else {
                $summary["qt"][] = $n;
            }
        }

        $zy_brry = ZY_BRRY::query()
            ->where('ZYH', $zyh)
            ->select(['CH', 'AAA28', 'ZYCS'])
            ->first();
        if ($zy_brry) {
            $zy_brry = $zy_brry->toArray();
        }

        // case_quality_count中的数据改为已读
        $quality_date = date('Y-m-d', strtotime("-1 day"));
        CaseQualityCount::query()
            ->where('zyh', $zyh)
            ->where('quality_date', $quality_date)
            ->where('is_viewed', 0)
            ->update(['is_viewed' => 1]);
        $quality_time = $info[0]['zm_quality_time'] ?? '';
        //如果是数字，则转换为时间
        if (!empty($quality_time)) {
            if (is_numeric($quality_time)) {
                $quality_time = date('Y-m-d H:i:s', $quality_time);
            } else {
                //使用strtotime
                $quality_time = strtotime($quality_time);
                $quality_time = date('Y-m-d H:i:s', $quality_time);
            }
        }
        return [
            'score' => 100 - $score,
            'data' => $newData,
            'total' => count($newData),
            'summary' => $summary,
            'run_score' => 0,
            'CH' => $zy_brry['CH'] ?? '',
            'AAA28' => $zy_brry['AAA28'] ?? '',
            'ZYCS' => $zy_brry['ZYCS'] ?? '',
            'is_case' => $info[0]['is_case'] ?? '',
            'appeal_docter' => $info[0]['edit_docter'] ?? '',
            'appeal_document' => $info[0]['edit_docter_document'] ?? '',
            'quality_time' => $quality_time,
        ];
    }

    /**
     * @param int $zyh
     * @return array
     * 【术前小结及术前讨论结论记录】 在【手术知情同意书】之前
     */
    public function rule257($info = [])
    {
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;

        $mustNot = ['term' => ['BLZT' => 9]];
        //替换mysql字典映射
        $rulefirst = RuleWordMap::query()->where('name', '完成时间')->first();
        $bldate = $rulefirst['keyword'];
        $bl01esService = new ElasticsearchService('bl01_202303');
        // 术前小结及术前讨论结论记录
        $bl01must = [
            ['term' => ["JZHM" => $info['MED_REC_ID']]],
            ['terms' => ["MBLB" => [82]]],
            //['range' => ['ZXSJ' => ['lt' => $resData[0][0]['KZSJ']]]]
        ];
        $params = $bl01esService->clearMust()
            ->queryByMustNot($mustNot)
            ->orderBy($bldate, 'asc')
            ->paginate(1, 1000)
            ->queryByMustBatch($bl01must)
            ->source(['BLBH', 'CJSJ', 'ZXSJ', 'WCSJ'])->getParams();
        $bl01Res = app('es')->search($params);
        $bl01Res = $bl01esService->getDataByEs($bl01Res);
        if (!empty($bl01Res[1])) {
            foreach ($bl01Res[0] as $k => $v) {
                //查找手术知情同意书 MBLB" => 8 或 60 入院时间ZXSJ  lt   info AAB01
                if ($k == 0) {
                    $bl02must = [
                        ['term' => ["JZHM" => $info['MED_REC_ID']]],
                        ['range' => [$bldate => ['lt' => $v[$bldate]]]]
                        //['range' => [$bldate => ['lt' => $info['AAB01']+24,'gt'=>$v[$bldate]]]]
                    ];
                } else {
                    $bl02must = [
                        ['term' => ["JZHM" => $info['MED_REC_ID']]],
                        ['range' => [$bldate => ['lt' => $v[$bldate], 'gt' => $bl01Res[0][$k - 1][$bldate]]]]
                        //['range' => [$bldate => ['lt' => $info['AAB01']+24,'gt'=>$v[$bldate]]]]
                    ];
                }
                $params1 = $bl01esService->clearMust()
                    ->queryByShould(['terms' => ['MBLB' => [8, 60]]])
                    //->orderBy('ZXSJ', 'asc')
                    ->paginate(1, 1)
                    ->queryByMustBatch($bl02must)
                    ->source(['BLBH', 'CJSJ', 'ZXSJ', 'WCSJ'])->getParams();
                $bl01Res1 = app('es')->search($params1);
                $bl01Res1 = $bl01esService->getDataByEs($bl01Res1);
                if (!empty($bl01Res1[1])) {
                    $basis = [];
                    $basis[] = '术前小结及术前讨论结论记录【' . $v[$bldate] . '】>手术知情同意书【' . $bl01Res1[0][0][$bldate] . '】';
                    $basisList[] = $basis;
                }
            }
        }

        //        if (empty($bl01Res[1])) {
        //            $basis = [];
        //            $basis[] = '手术名称【' . $item['NSSMC'] . '】';
        //            $basis[] = '开嘱时间【' . $resData[0][0]['KZSJ'] . '】';
        //            $basis[] = '术前小结及术前讨论结论记录【无】';
        //            $basisList[] = $basis;
        //        } else {
        //            $cjsj = $bl01Res[0][0]['CJSJ'];
        //            if (strtotime($cjsj) > strtotime($resData[0][0]['KZSJ'])) {
        //                $basis = [];
        //                $basis[] = '手术名称【' . $item['NSSMC'] . '】';
        //                $basis[] = '开嘱时间【' . $resData[0][0]['KZSJ'] . '】';
        //                $basis[] = '术前小结及术前讨论结论记录【' . $cjsj . '不在开嘱时间之前】';
        //                $basisList[] = $basis;
        //            }
        //        }

        if ($basisList) {
            $errorNotice = $this->errorNotice($basisList, $info, 257, 'sqxj', $caseRule[257]['title']);
        }

        return $errorNotice;
    }

    /*
     * 【术前小结及术前讨论结论记录】在【手术医嘱】之前
     */
    public function rule260($info = [])
    {
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;

        $bl01esService = new ElasticsearchService('bl01_202303');
        $yzbesService = new ElasticsearchService('yzb_2023');
        $mustNot = ['term' => ['BLZT' => 9]];
        //替换mysql字典映射
        $rulefirst = RuleWordMap::query()->where('name', '完成时间')->first();
        $bldate = $rulefirst['keyword'];
        // 术前小结及术前讨论结论记录
        $bl01must = [
            ['term' => ["JZHM" => $info['MED_REC_ID']]],
            ['terms' => ["MBLB" => [82]]],
            //['range' => ['ZXSJ' => ['lt' => $resData[0][0]['KZSJ']]]]
        ];
        $params = $bl01esService->clearMust()
            ->queryByMustNot($mustNot)
            ->orderBy($bldate, 'asc')
            ->paginate(1, 1000)
            ->queryByMustBatch($bl01must)
            ->source(['BLBH', 'CJSJ', 'ZXSJ', 'WCSJ'])->getParams();
        $bl01Res = app('es')->search($params);
        $bl01Res = $bl01esService->getDataByEs($bl01Res);

        if (!empty($bl01Res[1])) {
            foreach ($bl01Res[0] as $k => $v) {
                //手术医嘱
                $params = $yzbesService->clearMust()
                    ->queryByMust(['term' => ['ZYH' => $info['MED_REC_ID']]])
                    ->queryByMust(['terms' => ['YZZT' => $this->yzzt]])
                    ->queryByMust(['match_phrase' => ['YZMC' => '术']])
                    ->queryByMust(['match_phrase' => ['YZMC' => '拟']])
                    ->queryByMust(['range' => ['KZSJ' => ['lt' => $v[$bldate]]]])
                    ->source(['YZMC', 'KZSJ', 'TZSJ'])
                    ->paginate(1, 1)
                    ->getParams();
                $res = app('es')->search($params);
                $resData = $yzbesService->getDataByEsToArray($res);
                if (!empty($resData[1])) {
                    $basis = [];
                    $basis[] = '术前小结及术前讨论结论记录签署时间【' . $v[$bldate] . '】＞开嘱时间【' . $resData[0][0]['KZSJ'] . '】';
                    $basis[] = '医嘱名称【' . $resData[0][0]['YZMC'] . '】';
                    $basisList[] = $basis;
                }
            }
        }

        if ($basisList) {
            $errorNotice = $this->errorNotice($basisList, $info, 260, 'sqxj', $caseRule[260]['title']);
        }
        return $errorNotice;
    }

    /*
     * 【手术知情同意书】在【手术医嘱】之前
     */
    public function rule261($info = [])
    {
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;

        $bl01esService = new ElasticsearchService('bl01_202303');
        $yzbesService = new ElasticsearchService('yzb_2023');
        $mustNot = ['term' => ['BLZT' => 9]];
        //替换mysql字典映射
        $rulefirst = RuleWordMap::query()->where('name', '完成时间')->first();
        $bldate = $rulefirst['keyword'];
        // 手术知情同意书
        $params = $bl01esService->clearMust()
            ->queryByMust(['term' => ['JZHM' => $info['MED_REC_ID']]])
            ->queryByMust(['term' => ['MBLB' => 8]])
            ->queryByMustNot($mustNot)
            ->paginate(1, 1000)
            ->source(['BLBH', 'CJSJ', 'ZXSJ', 'WCSJ', 'HJNR'])->getParams();
        $bl01Res = app('es')->search($params);
        $bl01Res = $bl01esService->getDataByEs($bl01Res);

        if (!empty($bl01Res[1])) {
            foreach ($bl01Res[0] as $k => $v) {
                //手术医嘱
                $params = $yzbesService->clearMust()
                    ->queryByMust(['term' => ['ZYH' => $info['MED_REC_ID']]])
                    ->queryByMust(['terms' => ['YZZT' => $this->yzzt]])
                    ->queryByMust(['match_phrase' => ['YZMC' => '术']])
                    ->queryByMust(['match_phrase' => ['YZMC' => '拟']])
                    ->queryByMustNot($mustNot)
                    //                    ->queryByMust([
                    //                        "bool" => [
                    //                            "should" => [
                    //                                ['match_phrase' => ['YZMC' => '术']],
                    //                                ['match_phrase' => ['YZMC' => '拟']],
                    //                            ]
                    //                        ]
                    //                    ])
                    ->queryByMust(['range' => ['KZSJ' => ['lt' => $v[$bldate]]]])
                    ->source(['YZMC', 'KZSJ'])
                    ->paginate(1, 1)
                    ->getParams();
                $res = app('es')->search($params);
                $resData = $yzbesService->getDataByEsToArray($res);
                if (!empty($resData[1])) {
                    $basis = [];
                    $str = explode("签字时间", $v['HJNR']);
                    $time = explode("因抢救", $str[1]);
                    $basis[] = '手术知情同意书【' . trim($time[0]) . '】＞开嘱时间【' . $resData[0][0]['KZSJ'] . '】';
                    $basis[] = '医嘱名称【' . $resData[0][0]['YZMC'] . '】';
                    $basisList[] = $basis;
                }
            }
        }

        if ($basisList) {
            $errorNotice = $this->errorNotice($basisList, $info, 261, 'bcjl', $caseRule[261]['title']);
        }
        return $errorNotice;
    }

    /*
     * 医嘱【备血（第一次的就可以）】开嘱时间，之前签【输血知情同意书】创建时间
     */
    public function rule262($info = [])
    {
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;

        $bl01esService = new ElasticsearchService('bl01_202303');
        $yzbesService = new ElasticsearchService('yzb_2023');
        $mustNot = ['term' => ['BLZT' => 9]];
        //替换mysql字典映射
        $rulefirst = RuleWordMap::query()->where('name', '完成时间')->first();
        $bldate = $rulefirst['keyword'];

        // 输血知情同意书
        $bl01must = [
            ['term' => ["JZHM" => $info['MED_REC_ID']]],
            ['terms' => ["MBLB" => [59]]],
            //['range' => ['ZXSJ' => ['lt' => $resData[0][0]['KZSJ']]]]
        ];
        $params = $bl01esService->clearMust()
            //->orderBy('ZXSJ', 'asc')
            ->paginate(1, 1000)
            ->queryByMustBatch($bl01must)
            ->source(['BLBH', 'CJSJ', 'ZXSJ', 'WCSJ', 'HJNR'])->getParams();
        $bl01Res = app('es')->search($params);
        $bl01Res = $bl01esService->getDataByEs($bl01Res);

        if (!empty($bl01Res[1])) {
            foreach ($bl01Res[0] as $k => $v) {
                //手术医嘱
                $params = $yzbesService->clearMust()
                    ->queryByMust(['term' => ['ZYH' => $info['MED_REC_ID']]])
                    ->queryByMust(['terms' => ['YZZT' => $this->yzzt]])
                    //                    ->queryByMust([
                    //                        "bool" => [
                    //                            "should" => [
                    //                                ['match_phrase' => ['YZMC' => '术']],
                    //                                ['match_phrase' => ['YZMC' => '拟']],
                    //                            ]
                    //                        ]
                    //                    ])
                    ->queryByMust(['range' => ['KZSJ' => ['lt' => $v[$bldate]]]])
                    ->source(['YZMC', 'KZSJ'])
                    ->paginate(1, 1)
                    ->getParams();
                $res = app('es')->search($params);
                $resData = $yzbesService->getDataByEsToArray($res);
                if (!empty($resData[1])) {
                    if (!empty($v['HJNR'])) {
                        //匹配签署时间
                        $pattern = '/签署时间:(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/';
                        if (preg_match($pattern, $v['HJNR'], $matches)) {
                            $signTime = $matches[1];
                        } else {
                            $signTime = '无';
                        }
                    } else {
                        $signTime = '无';
                    }
                    $basis = [];
                    $basis[] = '输血同意书签署时间【' . $signTime . '】＞开嘱时间【' . $resData[0][0]['KZSJ'] . '】';
                    $basis[] = '医嘱名称【' . $resData[0][0]['YZMC'] . '】';
                    $basisList[] = $basis;
                }
            }
        }

        if ($basisList) {
            $errorNotice = $this->errorNotice($basisList, $info, 262, 'bcjl', $caseRule[262]['title']);
        }
        return $errorNotice;
    }

    /*
     * 病理报告结果 24小时有病程
     */
    public function rule263($info = [])
    {
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;

        $bl01esService = new ElasticsearchService('bl01_202303');
        //替换mysql字典映射
        $rulefirst = RuleWordMap::query()->where('name', '完成时间')->first();
        $bldate = $rulefirst['keyword'];
        //查病理报告
        $ymresult = V_JMGS_YMresult::query()->where('ZYH', $info['MED_REC_ID'])->get()->toArray();
        if (!empty($ymresult)) {
            foreach ($ymresult as $k => $v) {
                if (empty($v['BGSJ']) || empty($v['PYJG']) || $v['BGSJ'] == '0000-00-00 00:00:00') {
                    continue;
                }
                //取病理报告时间
                $startTime = date("Y-m-d H:i:s", strtotime($v['BGSJ']));
                $endTime = date("Y-m-d H:i:s", strtotime($startTime) + 24 * 3600);
                //查病程记录
                $must = [
                    ['term' => ['JZHM' => $info['MED_REC_ID']]],
                    ['term' => ['BLLB' => 294]],
                    ['match_phrase' => ['HJNR' => $v['PYJG']]]
                ];
                $params = $bl01esService->clearMust()
                    ->queryByMustBatch($must)
                    ->queryByMust(['range' => [$bldate => ['gt' => $startTime, 'lt' => $endTime]]])
                    ->queryByMustNot(['term' => ['BLZT' => 9]])
                    ->queryByMustNot(['term' => ['MBLB' => 32]])
                    ->getParams();
                $res = app('es')->search($params);
                $resData = $bl01esService->getDataByEs($res);
                //24小时之内无病程 质控出来
                if (empty($resData[1])) {
                    $basis = [];
                    $basis[] = '报告时间【' . $v['BGSJ'] . '; 培养结果【' . $v['PYJG'] . '】; 病程记录【无】';
                    $basisList[] = $basis;
                }
            }
        }
        if ($basisList) {
            $errorNotice = $this->errorNotice($basisList, $info, 263, 'bcjl', $caseRule[263]['title']);
        }
        return $errorNotice;
    }

    /*
     * 有放疗药，没有病程记录
     */
    public function rule264($info = [])
    {
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        $yzbesService = new ElasticsearchService('yzb_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');

        //查找放疗药
        $params = $yzbesService->clearMust()
            ->queryByMust(['term' => ['ZYH' => $info['MED_REC_ID']]])
            ->queryByMust(['terms' => ['YZZT' => $this->yzzt]])
            ->queryByMust(['match_phrase' => ['YZMC' => '放疗']])
            ->paginate(1, 10000)
            ->getParams();
        $res = app('es')->search($params);
        $resData = $yzbesService->getDataByEsToArray($res);
        if (!empty($resData[1])) {
            $resArr = [];
            foreach ($resData[0] as $item) {
                $resArr[md5($item['YZMC'] . $item['JLDW'] . $item['SYPC'] . $item['YCJL'])] = $item;
            }
            //获取质控字典关键词映射
            $rulefirst = RuleWordMap::query()->where('name', '完成时间')->first();
            $bldate = $rulefirst['keyword'] ?? '';
            //循环找病程记录
            foreach ($resArr as $v) {
                $basis = [];
                $kzsj = date("Y-m-d H:i:s", strtotime($v['KZSJ']) + 24 * 3600);

                $must = [
                    ['term' => ['JZHM' => $info['MED_REC_ID']]],
                    ['term' => ['BLLB' => 294]],
                    ['match_phrase' => ['HJNR' => '放疗']]
                ];
                $params = $bl01esService->clearMust()
                    ->queryByMustBatch($must)
                    ->queryByMust(['range' => [$bldate => ['gt' => $v['KZSJ'], 'lt' => $kzsj]]])
                    ->queryByMustNot(['term' => ['BLZT' => 9]])
                    ->queryByMustNot(['term' => ['MBLB' => 32]])
                    ->getParams();
                $res = app('es')->search($params);
                $resData1 = $bl01esService->getDataByEs($res);
                //$basis[] = '化疗药名称【' . $v['hlyw_name'] . '】';
                $basis[] = '开嘱时间【' . $v['KZSJ'] . '】';
                if (empty($resData1[1])) {
                    // 24小时内的病程记录
                    $params = $bl01esService->clearMust()
                        ->queryByMustBatch($must)
                        ->queryByMust(['range' => [$bldate => ['gt' => $kzsj]]])
                        ->queryByMustNot(['term' => ['BLZT' => 9]])
                        ->queryByMustNot(['term' => ['MBLB' => 32]])
                        ->getParams();
                    $res = app('es')->search($params);
                    $resData = $bl01esService->getDataByEs($res);
                    if (!empty($resData[1])) {
                        $this->setSendLogStatus($info['MED_REC_ID'], 264, 0);
                        $basis[] = '病程记录【' . $resData[0][0]['BLMC'] . '，执行时间超24小时】，医嘱名称【' . $v['YZMC'] . '】';
                    } else {
                        $basis[] = '病程记录时间【无】';
                        // 检查是否举例完成时间小于两小时
                        $bgsj = strtotime($kzsj);
                        $diffTime = $bgsj - time();
                        if ($diffTime < $this->diffHoure * 3600 && $diffTime > 0) {
                            $res = remainderTime($diffTime);
                            $content = '请在' . $res . '内在病程中记录放疗药使用情况';
                            $basis[] = $content;
                            $this->sendMsg($info['MED_REC_ID'], $content, 264);
                        }
                    }
                    $basisList[] = $basis;
                } else {
                    $this->setSendLogStatus($info['MED_REC_ID'], 264, 1);
                }
            }

            if ($basisList) {
                $errorNotice = $this->errorNotice($basisList, $info, 264, 'bcjl', $caseRule[264]['title']);
            }
        }

        return $errorNotice;
    }

    /*
     * 不合理复制率不能连续20个字一样
     */
    public function rule265($info = [])
    {
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;

        //查找emr_bl01表的blbh
        $blbh = EMR_BL_BL01::query()->where('JZHM', $info['MED_REC_ID'])->pluck('BLBH');
        if (empty($blbh)) {
            return [];
        }
        //查找emr_bl_blxg
        $blxg = EMR_BL_BLXG::query()->whereIn('BLBH', $blbh)->get(['BLBH', 'HJNR'])->toArray();
        if (!empty($blxg)) {
            foreach ($blxg as $k => $v) {
                foreach ($blxg as $ke => $va) {
                    if ($v['BLBH'] != $va['BLBH']) {
                        $pattern = '/([\p{L}\p{N}\p{Han}])\1{19}/u'; // 正则表达式，匹配连续出现 20 个相同字符的情况
                        if (preg_match_all($pattern, $v['HJNR'], $matches1) && preg_match_all($pattern, $va['HJNR'], $matches2)) {
                            $intersection = array_values(array_intersect($matches1[0], $matches2[0]));
                            if (!empty($intersection)) {
                                $blbhAnd = EMR_BL_BL01::query()->where('JZHM', $info['MED_REC_ID'])->first('BLMC');
                                $basis = [];
                                $basis[] = '病例名称【' . $blbhAnd['BLMC'] . '】';
                                $basis[] = '病例内容【' . $intersection[0] . '处；有雷同';
                                $basisList[] = $basis;
                                break;
                            }
                        }
                    }
                }
            }
        }

        if ($basisList) {
            $errorNotice = $this->errorNotice($basisList, $info, 265, 'zt', $caseRule[265]['title']);
        }
        return $errorNotice;
    }

    private function errorNotice($basisList, $info, $ruleId, $code, $caseRule)
    {
        $errorNotice = [
            'basis' => json_encode($basisList, 256),
            'JZHM' => $info['MED_REC_ID'],
            'rule_id' => $ruleId,
            'code' => $code,
            'error_field' => $caseRule
        ];
        return $errorNotice;
    }

    private function hasRuleValue($value)
    {
        if ($value === 0 || $value === '0') {
            return true;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->hasRuleValue($item)) {
                    return true;
                }
            }

            return false;
        }

        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return !empty($value);
    }
}
