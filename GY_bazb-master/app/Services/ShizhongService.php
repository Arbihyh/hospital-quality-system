<?php

namespace App\Services;


use App\Elastic\Zyhcmx;


use App\Model\MainOperation;
use App\Model\MedicinalInfo;

use App\Model\PatientDoctorInfo;
use App\Model\PatientHospitalInfo;
use App\Model\PatientInfo;

use App\Model\QualitySendMsgLog;

use App\Model\RuleWordMap;
use App\Model\Setting;

use App\Model\SSSQ;
use App\Model\Staff;


use App\Model\Zg_doctor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Model\CaseRule;

/**
 * 病例分析
 */
class ShizhongService
{
    const ID = 4257465;
    public $caseRule = [];
    public $yzzt = [0, 1, 5];
    public $diffHoure = 2;
    public $ygjb = ["副主任护师", "副主任检验师", "副主任技师", "副主任医师", "主任医师", "主任护士", "主任检验师", "主治医师", "主管技师", "主管护师", "主管检验师", "主管药师", "医师", "实习医生", "技师", "护士", "护士长", "护师", "检验师", "药师"];


    public function __construct()
    {

        $setting = Setting::query()->where('name', '=', 'diff_houre')->get()->toArray();
        $this->diffHoure = (int)$setting[0]['content'];
        //所有添加的质控规则
        $caseRule = CaseRule::query()->get()->toArray();
        $this->caseRule = array_column($caseRule, null, 'id');

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
        // 如果发送过则不在发送
        $res = QualitySendMsgLog::query()->where(['zyh' => $zyh, 'rule_id' => $ruleId])->get()->toArray();
        if (!empty($res)) {
            return false;
        }

        $setting = Setting::query()->where('name', '=', 'send_msg')->get()->toArray();
        $patientInfo = PatientInfo::query()->where(['MED_REC_ID' => $zyh])->get(['AAA28', 'AAA01'])->toArray();
        $doctor = PatientDoctorInfo::query()->where(['AAA28' => $zyh])->get()->toArray();
        if (empty($doctor)) {
            return false;
        }
        $patientHospitalInfo = PatientHospitalInfo::query()->where(['AAA28' => $zyh])->get(['AAB11N', 'AAA28'])->toArray();

        // 医生钉钉账号获取
        $doctorList[] = $doctor[0]['AEE03_CODE'] ?: '';
        $doctorList[] = $doctor[0]['AEE01_CODE'] ?: '';
        $doctorList = array_filter($doctorList);
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
        if (empty($YGBH)) {
            return false;
        }

        $zgDoctor = Zg_doctor::query()->whereIn('doctor_id', $YGBH)->get()->toArray();

        $url = env('DING_SEND_URL');

        $AAC11N = $patientHospitalInfo[0]['AAB11N'] ?: '';
        $title = $AAC11N;

        $msgStrYj = '';
        foreach ($msgYj as $myj) {
            $msgStrYj .= implode(',', $myj);
        }


        $msg = '';
        foreach ($zgDoctor as $zg) {

            $msgList[] = ["doctorId" => $zg['doctor_id'], "doctorName" => $zg['doctor_name'], "ddId" => $zg['dd_id'], "deptName" => $zg['depart_name']];
            $msgList = array_merge($msgList, $whiteNoticeAccepts);

            $msg = "尊敬的[{$zg['doctor_name']}]医师：
        您好！您负责的病历{$patientInfo[0]['AAA28']}{$patientInfo[0]['AAA01']}，“" . $content . "”，" . $msgStrYj . "，请按时完成，谢谢！
        祝您工作顺利！";
            $data = [
                "noticeTitle" => $title,
                "msg" => $msg,
                "deptId" => 1,
                "deptName" => "管理员",
                "noticeAccepts" => $msgList
            ];
            $res = [];
            if ($setting[0]['content'] == 1) {
                $res = requestPost($url, $data);
            }
        }

        // 将内容的时间解析出来，并转换成秒
        $str = str_replace('请在', '', $content);
        $str = explode('分钟', $str);
        $time = explode('小时', $str[0]);
        $second = $time[0] * 3600 + $time[1] * 60;
        QualitySendMsgLog::query()->insert(
            [
                'zyh' => $zyh, // 6位住院号
                'AAA28' => $patientInfo[0]['AAA28'], // 8位住院号
                'data_id' => ($dataId ?: ''), // 对应质控的数据ID，例如医嘱本的YZBXH，病程的BLBH
                'data_type' => ($dataType ?: ''), // 删除的数据类型
                'rule_id' => $ruleId, // 质控规则的ID
                'content' => $msg, // 发送的钉钉信息内容
                'quality_content' => $content, // 发送的钉钉信息内容
                'second' => $second, // 有效时间，单位秒，距离有效整改时间的倒计时
                'status' => 2, // 整改状态、未整改
                'AEE03_CODE' => !empty($doctorList[0]) ? $doctorList[0] : '',
                'AEE03' => !empty($zgDoctor[0]) ? $zgDoctor[0]['doctor_name'] : '',
                'AEE01_CODE' => !empty($doctorList[1]) ? $doctorList[1] : '',
                'AEE01' => !empty($zgDoctor[1]) ? $zgDoctor[1]['doctor_name'] : '',
                'title' => $title,
                'AAC11N' => $AAC11N, // 病人所属科室
                'msg_yj' => json_encode($msgYj, 256), // 质控依据
                'created_at' => time(),
                'result' => json_encode($res, 256),
                'send_status' => $res && $res->code == 200 ? 1 : 2,
            ]
        );
        //
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
//        $res = QualitySendMsgLog::query()->where(['zyh' => $zyh, 'rule_id' => $ruleId])->get()->toArray();
//        if (empty($res)) {
//            return false;
//        }
//        $pushTime = $res[0]['created_at'];
//        $second = $res[0]['second'];
//        $date = strtotime($date);
//        $isYouxiao = 0;
//        if ($pushTime + $second > $date) {
//            $isYouxiao = 1;
//        }
//
//
//        QualitySendMsgLog::query()->updateOrInsert(
//            ['zyh' => $zyh, 'rule_id' => $ruleId],
//            ['status' => 1, 'is_youxiao' => $isYouxiao]
//        );
//        return true;
//
//        // 如果住院号对应的规则不存在，则设置质控信息已整改
//        $res = CaseQuality::query()->where(['zyh' => $zyh, 'rule_id' => $ruleId])->get()->toArray();
//        if (!$res) {
//            QualitySendMsgLog::query()->updateOrInsert(
//                ['zyh' => $zyh, 'rule_id' => $ruleId],
//                ['status' => 1]
//            );
//        }
    }


    /**
     * @return array
     * 有抗菌药，开嘱时间+24小时 内要有病程记录
     */

    public function kjy2($info = []): array
    {

        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;


        if (!empty($info['yzb'])) {

            //提取抗菌药
            $yzbData = $info['yzb'];//$this->isKjy($info['yzb']);


//            foreach ($yzbData as $item) {
//                file_put_contents(storage_path() . '/kjy.log', json_encode($item, JSON_UNESCAPED_UNICODE) . "/n/n/n/n", FILE_APPEND);
//            }

            $bldate = $info['wanchengshijian'] ?? ''; //完成时间
//            array_multisort(array_column($yzbData, 'KJ'), SORT_DESC, $yzbData);
//            $kzsj = date("Y-m-d H:i:s", strtotime(array_shift($yzbData)['KJ']) + 24 * 3600);


            $basis = [];
            $isJilu = true;
            $bl01 = [];
            $kzsj = [];
            $msgYj = [];

            //if (!empty($yzbData)) {
            var_dump("\n\n\n" . '住院号============================================开始：' . $info['ZYH']);
            //}

            foreach ($yzbData as $yzbKey => $item) {

                var_dump('抗菌药------------------' . $item['kjyw_name']);

                if (isset($info['bl01'][$item['ZYH']]) && !empty($info['bl01'][$item['ZYH']])) {

                    $bl01[$yzbKey] = 0;
                    // 24小时内的病程记录
                    foreach ($info['bl01'][$item['ZYH']] as $bl01Item) { //循环找病程记录
                        if ($bl01Item['BLZT'] != 9 && $bl01Item['BLLB'] == 294 && false !== stripos($bl01Item['HJNR'], $item['kjyw_name'])) {


                            if (strtotime($bl01Item[$bldate]) <= strtotime($item['KJ']) + 24 * 3600) {
                                $bl01[$yzbKey] = 1;

                                break;
                            }
//                            else {
//                                //$basis[] = '病程记录【' . $bl01Item['BLMC'] . '，执行时间超24小时】';
//                                //$this->setSendLogStatus($info['ZYH'], 109, 0);
//                                $bl01[$yzbKey] = 1;
//                                break;
//                            }
                        }
                    }


                    if (!$bl01[$yzbKey]) {

                        var_dump('kjy_name----------' . $item['kjyw_name'] . '------------------未找到病例');
                        $isJilu = false;
                        $basis[] = '抗菌药名称【' . $item['kjyw_name'] . '】';
                        $basis[] = '开嘱时间【' . $item['KJ'] . '】';
                        $basis[] = '病程记录【无】';
                        $msgYj[] = ['开嘱时间【' . $item['KJ'] . '】', '医嘱名称【' . $item['YZMC'] . '】', '使用频次【' . $item['SYPC'] . '】', '使用剂量【' . $item['YCJL'] . '】', '24小时内的病程中无记录'];
                        $kzsj[] = ['KJ' => $item['KJ'], 'YZBXH' => $item['YZBXH']];
                    } else {
                        var_dump('kjy_name----------' . $item['kjyw_name'] . '------------------按时完成病程');
                    }

                } else {
                    var_dump('kjy_name----------' . $item['kjyw_name'] . '------------------无病例');
                    $isJilu = false;
                    $basis[] = '抗菌药名称【' . $item['kjyw_name'] . '】';
                    $basis[] = '开嘱时间【' . $item['KJ'] . '】';
                    $basis[] = '病程记录【无】';
                    $msgYj[] = ['开嘱时间【' . $item['KJ'] . '】', '医嘱名称【' . $item['YZMC'] . '】', '使用频次【' . $item['SYPC'] . '】', '使用剂量【' . $item['YCJL'] . '】', '24小时内的病程中无记录'];
                    $kzsj[] = ['KJ' => $item['KJ'], 'YZBXH' => $item['YZBXH']];
                }

                if (!empty($basis)) {
                    $basisList[] = $basis;
                }
            }


            if (!$isJilu) {
                // 检查是否举例完成时间小于两小时
                array_multisort(array_column($kzsj, 'KJ'), SORT_DESC, $kzsj);

                $kzsjArr = array_shift($kzsj);
                $bgsj = strtotime($kzsjArr['KJ']) + 24 * 3600;

                $diffTime = $bgsj - time();

                var_dump('---------------住院号' . $info['ZYH'] . '---------------提醒时间' . $diffTime . '-----------------开嘱时间' . $kzsjArr['KJ'] . '---------------' . "\n\n\n");

                if ($diffTime > 0 && $diffTime < $this->diffHoure * 3600) {

                    var_dump('==========================住院号' . $info['ZYH'] . '==========================已发送提醒' . "\n\n\n");
                    $t = remainderTime($diffTime);
                    $content = '请在' . $t . '内在病程中记录抗菌药使用情况';
                    //$msgYj = ['开嘱时间【' . $item['KJ'] . '】', '医嘱名称【' . $item['YZMC'] . '】', '使用频次【' . $item['SYPC'] . '】', '使用剂量【' . $item['YCJL'] . '】', '24小时内的病程中无记录'];
                    $this->sendMsg($info['ZYH'], $content, 109, $msgYj, $kzsjArr['YZBXH'], '医嘱');
                }
            } else {
                var_dump('==========================按时完成==========================' . "\n\n\n");
                $this->setSendLogStatus($info['ZYH'], 109, 1);
            }
        }


        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['ZYH'],
                'rule_id' => 109,
                'code' => 'kjy',
                'error_field' => $caseRule[109]['title']
            ];
        }

        return $errorNotice;
    }


    /**
     * @param array $info
     * @return array
     * 入院后8小时内完成首次病程记录
     */
    public function checkRy8($info = [], $zyh, $rulefirst)
    {

        var_dump('checkRy8====开始=====================================住院号：' . $zyh);
        $errorNotice = [];
        $caseRule = $this->caseRule;
//        sssq中【rjss=1  或 首页=日间手术   属于日间手术】且 住院天数=1   剔除
        $sssq = SSSQ::query()->where('ZYH', '=', $zyh)->where('RJSS', '=', 1)->first();
        $patientInfo = PatientInfo::query()->where('MED_REC_ID', '=', $zyh)->first();
        $mainOperation = MainOperation::query()->where('AAA28', '=', $zyh)->first();
        if ($patientInfo && $patientInfo['AAC04'] == 1 && (($mainOperation && $mainOperation['RJSS'] == '是') || !empty($sssq))) {
            return [];
        }
        // 有24小时出入院记录的不质控该规则
//        $resData = EMR_BL_BL01::query()->where(['JZHM' => $info['MED_REC_ID'], 'BLLB' => 18])->get(['BLBH', 'ZXSJ', 'CJSJ', 'WCSJ', 'BLLB'])->toArray();
//        if ($resData) {
//            $this->setSendLogStatus($info['MED_REC_ID'], 101, 1);
//            return [];
//        }
        $resData = [];
        foreach ($info['bl01'][$zyh] as $bl01Item) {
            if ($bl01Item['BLLB'] == 18) {
                $this->setSendLogStatus($zyh, 101, 1);
                return [];
                break;
            }

            if ($bl01Item['bl_type'] == 1 && $bl01Item['BLZT'] != 9) {
                $resData[] = $bl01Item;
            }
        }


        $basis = [];
        // 获取入院时间
        //$zyHcmxesService = new Zyhcmx();
        //$zyHcmxData = $zyHcmxesService->getHCRQ($info['MED_REC_ID']);

        //首次病程记录表：EMR_BL_BL01中【MBLB =295 且 blzt≠9】的【CJSJ】
        $flag = 1;

        $hcrq = $patientInfo['AAB01'];


        var_dump('checkRy8=====首次病程记录=================================入院时间：' . $hcrq . '===首次病程记录' . count($resData));
        $basis[] = '入院时间【' . $hcrq . '】';

//        $resData = EMR_BL_BL01::query()
//            ->where(['JZHM' => $info['MED_REC_ID'], 'bl_type' => 1])
//            ->where('BLZT', '<>', 9)
//            ->get(['BLBH', 'ZXSJ', 'CJSJ', 'WCSJ', 'BLLB'])->toArray();


        // 检查是否举例完成时间小于两小时
        $bgsj = strtotime($hcrq) + 8 * 3600;
        $diffTime = $bgsj - time();
        if (empty($resData)) {
            if ($diffTime < $this->diffHoure * 3600 && $diffTime > 0) {
                $flag = 0;
                $res = remainderTime($diffTime);
                $content = '请在' . $res . '内完成首次病程记录';

                $msgYj = ['入院时间【' . $hcrq . '】', '首次病程记录【无】'];

                $this->sendMsg($zyh, $content, 101, [$msgYj]);
            }
        }


        //替换mysql字典映射
        //$rulefirst = RuleWordMap::query()->where('name', '完成时间')->first();
        if ($rulefirst) {
            $wcsj = $resData[0][$rulefirst['keyword']] ?? '';
        } else {
            $wcsj = '';
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
            $this->setSendLogStatus($zyh, 101, $isYx);
        }

        if (empty($flag)) {
            $errorNotice = [
                'basis' => json_encode([$basis], 256),
                'JZHM' => $zyh,
                'rule_id' => 101,
                'code' => 'bingcheng_first',
                'error_field' => $caseRule[101]['title']
            ];
        }

        var_dump('checkRy8=====return=================================' . json_encode($errorNotice) . "\n\n\n");

        return $errorNotice;
    }


    /**
     * 入院记录
     * @param $caseRule
     * @param $ZYH
     * @return true
     */
    public function ruleRuYuan($ruleId, $zyh, $info, $rulefirst)
    {
        var_dump('ruleRuYuan=====开始=====================================住院号：' . $zyh);
        $errorNotice = [];
        $caseRule = $this->caseRule;
        $zyHcmxesService = new Zyhcmx();
        $zyHcmxData = $zyHcmxesService->getHCRQ($zyh);
        if (!empty($zyHcmxData[0])) {
            $HCRQ = $zyHcmxData[0][0]['HCRQ'];
            $HCRQ_NED = Carbon::parse($HCRQ)->addDay()->toDateTimeString();

//            $bl01Data = EMR_BL_BL01::query()
//                ->whereIn('BLLB', [292, 18])
//                ->where(['JZHM' => $ZYH])
//                ->where('BLZT', '<>', 9)->get()->toArray();


            $bl01Data = [];
            foreach ($info['bl01'][$zyh] as $bl01Item) {
                if (($bl01Item['BLLB'] == 292 || $bl01Item['BLLB'] == 18) and $bl01Item['BLZT'] != 9) {
                    $bl01Data[] = $bl01Item;
                }
            }


            var_dump('ruleRuYuan================护士分床时间' . $HCRQ . '==========================入院记录：' . count($bl01Data));
            //获取质控字典关键词映射
            //$rulefirst = RuleWordMap::query()->where('name', '完成时间')->first();
            if (!empty($bl01Data)) {
                if ($rulefirst) {
                    $bldate = $bl01Data[0][$rulefirst['keyword']] ?? '';
                } else {
                    $bldate = '';
                }
                $isYx = 1;
                if (empty($bldate)) {
                    $isYx = 0;
                    $errorNotice[] = [
                        'JZHM' => $zyh,
                        'rule_id' => $ruleId,
                        'code' => 'rule_' . $ruleId,
                        'error_field' => $caseRule[$ruleId]['title'],
                        'basis' => json_encode([['护士分床时间【' . $HCRQ . '】，入院记录完成时间【无，未完成】']], JSON_UNESCAPED_UNICODE)
                    ];
                } elseif ($HCRQ > $bldate || $HCRQ_NED < $bldate) {
                    $isYx = 0;
                    $errorNotice[] = [
                        'JZHM' => $zyh,
                        'rule_id' => $ruleId,
                        'code' => 'rule_' . $ruleId,
                        'error_field' => $caseRule[$ruleId]['title'],
                        'basis' => json_encode([['护士分床时间【' . $HCRQ . '】，入院记录完成时间【' . $bldate . '（超24小时）】']], JSON_UNESCAPED_UNICODE)
                    ];
                }

                $this->setSendLogStatus($zyh, $ruleId, $isYx);
            } else {

                // 检查是否举例完成时间小于两小时
                $bgsj = strtotime($HCRQ) + 24 * 3600;
                $diffTime = $bgsj - time();
                if ($diffTime < $this->diffHoure * 3600 && $diffTime > 0) {
                    $res = remainderTime($diffTime);
                    $content = '请在' . $res . '完成入院记录';
                    $msgYj = ['护士分床时间【' . $HCRQ . '】', '入院记录【无】'];
                    $this->sendMsg($zyh, $content, $ruleId, [$msgYj]);
                }
                $errorNotice[] = [
                    'JZHM' => $zyh,
                    'rule_id' => $ruleId,
                    'code' => 'rule_' . $ruleId,
                    'error_field' => $caseRule[$ruleId]['title'],
                    'basis' => json_encode([['入院时间【' . $HCRQ . '】，入院记录执行时间【无，未完成】']], JSON_UNESCAPED_UNICODE)
                ];
            }
        }

        var_dump('ruleRuYuan=====return=================================' . json_encode($errorNotice) . "\n\n\n");
        return $errorNotice;
    }


    public function isKjy($data)
    {

        $medicianlKjyw = cache()->remember('medicinal_data_key', 3600 * 24, function () {
            return MedicinalInfo::query()->where(['type' => 1])->pluck('name')->toArray();
        });


        $arr1 = [];
        foreach ($data as $yzbItem) {
            if ($yzbItem['YYSX'] == 4 || $yzbItem['GYTJ'] == 167 || ($yzbItem['YDYZLB'] == 901 && false !== stripos($yzbItem['YZMC'], '皮试')) || !in_array($yzbItem['YZZT'], $this->yzzt)) {
                continue;
            }

            foreach ($medicianlKjyw as $kjyName) {
                if (false !== stripos($yzbItem['YZMC'], $kjyName)) {
                    $yzbItem['kjyw_name'] = $kjyName;
                    $arr1[] = $yzbItem;
                }
            }
        }

        $cqyzArr = [];
        $dqyzArr = [];
        foreach ($arr1 as $a1) {
            if ($a1['YZQX'] == 1) {
                $cqyzArr[$a1['YZBXH']] = $a1;
            }

            if ($a1['YZQX'] == 2) {
                $dqyzArr[$a1['YZBXH']] = $a1;
            }
        }

        foreach ($dqyzArr as $dqKey => $dqyz) {
            if (isset($cqyzArr[$dqyz['YZBXH']]) && $dqyz['kjyw_name'] == $cqyzArr[$dqyz['YZBXH']]['kjyw_name'] && $dqyz['YCJL'] == $cqyzArr[$dqyz['YZBXH']]['YCJL'] && $dqyz['JLDW'] == $cqyzArr[$dqyz['YZBXH']]['JLDW']) {
                unset($dqyzArr[$dqKey]);
            }
        }

        $arr2 = array_merge($cqyzArr, $dqyzArr);

        $data = [];
        foreach ($arr2 as $a2) {
            $data[md5($a2['YZMC'] . $a2['JLDW'] . $a2['SYPC'] . $a2['YCJL'])] = $a2;
        }


        return $data;


//
//        foreach ($medicianlKjyw as $h) {
//            $where = [
//                ['YZMC', 'like', '%' . $h . '%'],
//                ['YYSX', '<>', 4],
//                ['GYTJ', '<>', 167],
//            ];
//            if($zyh){
//                $where[] = ['ZYH', '=', $zyh];
//            }else{
//                $where[] = ['id', '>', $lastId];
//            }
//            Yzb::query()
//                ->where($where)
//                ->whereRaw('(YDYZLB<>901 or (YDYZLB=901 and YZMC not like "%皮试%"))')
//                ->update(['is_has_kjyw' => 1, 'kjyw_name' => $h]);
//        }
//
//        // 长期医嘱
//        $wherecqyz = [
//            ['is_has_kjyw', '=', 1],
//            ['YZQX', '=', '1']
//        ];
//        if($zyh){
//            $wherecqyz[] = ['ZYH', '=', $zyh];
//        }else{
//            $wherecqyz[] = ['id', '>', $lastId];
//        }
//        $cqyz = Yzb::query()
//            ->where($wherecqyz)
//            ->get(['id', 'kjyw_name', 'ZYH', 'YCJL', 'JLDW'])->toArray();
//        $cqyzArr = [];
//        foreach ($cqyz as $c) {
//            $cqyzArr[$c['ZYH']][] = $c;
//        }
//        // 临时医嘱
//        $wherelsyz = [
//            ['is_has_kjyw', '=', 1],
//            ['YZQX', '=', '2']
//        ];
//        if($zyh){
//            $wherelsyz[] = ['ZYH', '=', $zyh];
//        }else{
//            $wherelsyz[] = ['id', '>', $lastId];
//        }
//        $lsyz = Yzb::query()
//            ->where($wherelsyz)
//            ->get(['id', 'kjyw_name', 'ZYH', 'YCJL', 'JLDW'])->toArray();
//        $lsyzArr = [];
//        foreach ($lsyz as $c) {
//            $lsyzArr[$c['ZYH']][] = $c;
//        }
//        foreach ($cqyzArr as $k => $c1) {
//            if (!empty($lsyzArr[$k])) {
//                foreach ($c1 as $c2) {
//                    foreach ($lsyzArr[$k] as $l1) {
//                        if ($c2['kjyw_name'] == $l1['kjyw_name'] && $c2['YCJL'] == $l1['YCJL'] && $c2['JLDW'] == $l1['JLDW']) {
//                            Yzb::query()->where('id', '=', $l1['id'])->update(['is_has_kjyw' => 0]);
//                        }
//                    }
//                }
//            }
//        }
    }

    private
    function errorNotice($basisList, $info, $ruleId, $code, $caseRule)
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
}

