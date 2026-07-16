<?php

namespace App\Services;

use Exception;
use App\Model\Staff;
use App\Model\Appeal;
use App\Model\BLLB303;
use App\Model\CaseRule;
use App\Model\BLLB294_295;
use App\Model\CaseQuality;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use App\Model\EMR_BL_BLXG;
use App\Model\PatientInfo;
use App\Model\QualitySendMsgLog;
use Illuminate\Support\Facades\DB;
use App\Model\DiseaseDiagnosisCode;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;

/**
 * 病例分析
 */
class CaseService
{
    /**
     * @param int $doctor_id
     * @param string $msg
     * 推送消息提醒
     */
    public static function sendMsg($doctor_id = 0, $msg = "", $qualityType = 1, $title = "")
    {
        return false;
        // 消息推送
        $count = QualitySendMsgLog::query()
            ->where("doctor_id", $doctor_id)
            ->where("is_read", 0)
            ->where("quality_type", $qualityType)
            ->count();
        webSendMsg([
            "type" => "publish",
            "title" => $title,
            "quality_type" => $qualityType,
            "sendMsg" => $msg,
            "count" => $count
        ], $doctor_id);
    }


    const ID = 4257465;

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

    public static function getCaseQuality($zyh = 0)
    {

        $caseRule = CaseRule::query()->get()->toArray();
        $caseRule = array_column($caseRule, null, 'id');

        $newData = CaseQuality::getByJZHM($zyh);
        $score = 0;
        if ($newData) {
            $id = array_column($newData, 'id');
            $appeal = Appeal::query()->whereIn("error_id", $id)->where('type', 2)->get()->toArray();
            $appeal = array_column($appeal, null, 'error_id');
            foreach ($newData as &$v) {
                $v['basis'] = json_decode($v['basis']);
                $v['notice'] = $caseRule[$v['rule_id']]['notice'];
                $v['score'] = $caseRule[$v['rule_id']]['score'];
                $v['category'] = $caseRule[$v['rule_id']]['category'];
                $v['level'] = $caseRule[$v['rule_id']]['level'];
                $v['appeal_status'] = $appeal[$v['id']]['status'] ?? 0;
                $v['reject_content'] = $appeal[$v['id']]['reject_content'] ?? 0;

                $score += $v['score'];
            }
        }

        if ($newData) {
            foreach ($newData as $key => $row) {
                $field[$key] = $row['level'];
            }
            array_multisort($field, SORT_ASC, $newData); // 假设按’field’字段升序排序
        }

        $summary = [];
        foreach ($newData as $k => $v) {
            $summary[] = [
                'category' => $k,
                'nums' => count($v)
            ];
        }

        // 获取运行时病例信息
        $info = PatientInfo::query()
            ->where('MED_REC_ID', $zyh)
            ->select(['quality_time', 'is_case', 'edit_docter_document', 'edit_docter'])
            ->get()->toArray();
        return [
            'score' => 100 - $score,
            'data' => $newData,
            'total' => count($newData),
            'summary' => $summary,
            'run_score' => 0,
            'is_case' => $info[0]['is_case'],
            'appeal_docter' => $info[0]['edit_docter'],
            'appeal_document' => $info[0]['edit_docter_document'],
            'quality_time' => date("Y-m-d H:i:s", $info[0]['quality_time'])
        ];
    }

    public static function getCasePlatform($JZHM = 0, $bllb = 1, $isHight = 0, $keyWord = [])
    {
        $blbh = EMR_BL_BL01::query()
            ->where('JZHM', '=', $JZHM)
            ->where('BLLB', '=', $bllb)
            ->value('BLBH');

        $config = config("confAdmin");
        $deparment = !empty($config['department']) ? $config['department'] : [];
        $blxg = EMR_BL_BL01::query()
            ->where('BLLB', '=', $bllb)
            ->where('BLZT', '=', 1)
            ->where("JZHM", "=", $JZHM)
            ->get(['analysis_case', 'diagnose_list', 'BLLB', 'BRKS', 'BLBH', 'CJSJ', 'ZXSJ', 'WCSJ'])
            ->toArray();

        if (!$blxg) {
            return [];
        }
        if ($isHight) {
            $keyWord = is_array($keyWord) ? $keyWord : [$keyWord];
            foreach ($keyWord as $k) {
                $blxg[0]['analysis_case'] = str_replace($k, '<span class="keyHight">' . $k . '</span>', $blxg[0]['analysis_case']);
            }
        }
        $blxg[0]['analysis_case'] = str_replace('\r\n', '', $blxg[0]['analysis_case']);
        $analysiscase = json_decode($blxg[0]['analysis_case'], true);
        if (!$analysiscase) {
            $analysiscase = unserialize($blxg[0]['analysis_case']);
        }
        // 如果解析的入院记录不存在则通过oracle数据库重新同步
        if (empty($analysiscase)) {
            $oracleService = new OracleService();
            $oracleService->getBl01Data($blbh);
            return [];
        }
        $newData = [];
        $newData['department'] = !empty($deparment[$blxg[0]['BRKS']]) ? $deparment[$blxg[0]['BRKS']] : '';

        $keyMap = [
            'keyMap1' => [
                '75d15251ab7048d305b33eae0892444c' => "name",
                '021ab6d8680070343a50dab2c39a0290' => "ry_time",
                '2191d6819eccdd461d5badea3c2e800e' => "sex",
                'bd2cd0d5df70bf13b2d41da868cdde8d' => "cy_time", // 出院日期
                '0d975c61efda46ea809d62a18e553199' => "age",
                '2ab32984a6d66e3a47647d42d2fb8b38' => "zyts", // 住院天数
                'f56edc6122c32828c56ca2dbeb55d272' => "ryqk", // 入院情况:
                'd2b79a1d3d68a674c8b5779a4a265868' => "cbzd", // 初步诊断::
                '258fed9a8d9579aa182b9a8beab3781d' => "zljg", // 诊疗经过
                '0364be74c74e074c11eff6405ec15e62' => "cyqk", // 出院情况
                '3b0ff71ed4b1e15a5e7351a6268a532f' => "cyzd", // 出院诊断:
                'a1485e3eaca998bd8264c499e0b0cf0b' => "cyyz", // 出院医嘱
            ],
            'keyMap292' => [
                '75d15251ab7048d305b33eae0892444c' => "name",
                '53ccdd18ccb05f150c8feebaabbf858a' => "local_address",
                '2191d6819eccdd461d5badea3c2e800e' => "sex",
                'ef7666cc4c6dbd61ebc70ef243e4617a' => "job",
                '0d975c61efda46ea809d62a18e553199' => "age",
                '21f3568c10772f3776fc00635b05f9d2' => "ry_time",
                '98633d6e2c44bc01e6ac07b2ecae32e3' => "nation", // 民族
                '796cfada7d19a3b5ff024fc5f2e40412' => "record_time", // 记录时间
                '7b1ee861fa4672b6369aa4654946d3f2' => "marriage", // 婚姻
                '88422e115c799a30aa52391acdd764c0' => "narrator", // 陈述者
                'cbb1ab4ca9f5e907bedf7b1fe33fafee' => "zhusu", // 主诉
                'f5417c43295c0d67294f141ea6d47db1' => "xianbingshi", // 现病史
                '9d0550ebc6ce5175e0878caf9555312c' => "jiwangshi", // 既往史
                'ad0415504601b0e5e7af0da0a9e354b9' => "gerenshi", // 个人史
                '6f153a5f8946a7392a04789be355b007' => "hys", // 婚育史
                'e88960f01b3be7a35a1c1c9ac1f75c30' => "yjjhys", // 月经及婚育史
                'e62fd2ba94f47c94b9e47e1fb96e3914' => "jzs", // 家族史
                'd53647eedb2e963afa459cda02aad90f' => "tgjc", // 体格检查
                'ca9fa6c44dd8c758647c3155b5fbf753' => "fzjc", // 辅助检查
                '4f418fcdffbf606e1a66cae82ff104b1' => "bed_no", // 床号
                'e98d75d1ee6c335d1d34157fa0c93bb3' => "hospital_no", // 住院号
                'diagnose_list' => "diagnose_list", // 主要诊断
            ]
        ];

        $keyMap = $keyMap['keyMap' . $blxg[0]['BLLB']];
        foreach ($analysiscase as $key => $item) {
            if (!isset($keyMap[$key])) {
                continue;
            }
            $newData[$keyMap[$key]] = str_replace("\n", '', $item);
        }
        if (!empty($blxg[0]['diagnose_list'])) {
            $diagnoseList = base64_decode($blxg[0]['diagnose_list']);
            $diagnoseList = $diagnoseList ? explode(',', $diagnoseList) : [];
            $newData['diagnose_list'] = $diagnoseList ?: [];
        }
        if (!empty(request()->post('is_tm'))) {
            $newData['name']['value'] = desensitize($newData['name']['value'], 1, 0, '*');
            if (!empty($newData['local_address'])) {
                $newData['local_address']['value'] = desensitize($newData['local_address']['value'], 2, 0, '*');
            }
        }

        // 医师签名
        $newData['doctor_name'] = '';
        if (!empty($blxg[0]['BLBH'])) {
            $SYYS = EMR_BL_BLSY::query()->where('BLBH', '=', $blbh)->pluck('SYYS')->toArray();
            if ($SYYS) {
                $staffList = Staff::query()->whereIn('code', $SYYS)->get(['name', 'ygjb_text'])->toArray();
                $nameList = [];
                foreach ($staffList as $staffInfo) {
                    $nameList[] = $staffInfo['name'] . "（" . $staffInfo['ygjb_text'] . "）";
                }
                $newData['doctor_name'] = implode('、', $nameList);
            }
        }

        // 创建时间（cjsj）、修改时间（zxsj）、完成时间
        $newData['CJSJ'] = $blxg[0]['CJSJ'];
        $newData['ZXSJ'] = $blxg[0]['ZXSJ'];
        $newData['WCSJ'] = $blxg[0]['WCSJ'];

        // 住院号
        if (empty($newData['hospital_no'])) {
            $newData['hospital_no'] = [
                'title' => '住院号',
                'value' => PatientInfo::query()->where('MED_REC_ID', '=', $JZHM)->value('AAA28'),
            ];
        }
        // 姓名
        if (empty($newData['name'])) {
            $newData['name'] = [
                'title' => '姓名',
                'value' => PatientInfo::query()->where('MED_REC_ID', '=', $JZHM)->value('AAA01'),
            ];
        }

        // 入院时间
        if (!empty(trim($newData['ry_time']))) {
            $newData['ry_time']['value'] = date('Y-m-d H:i', strtotime($newData['ry_time']['value']));
            $newData['ry_time']['value'] = str_replace('00:00', '', $newData['ry_time']['value']);
        }
        // 出院时间
        if (!empty(trim($newData['cy_time']))) {
            $newData['cy_time']['value'] = date('Y-m-d H:i', strtotime($newData['cy_time']['value']));
            $newData['cy_time']['value'] = str_replace('00:00', '', $newData['cy_time']['value']);
        }
        // 记录时间
        if (!empty(trim($newData['record_time']['value']))) {
            $newData['record_time']['value'] = date('Y-m-d H:i', strtotime($newData['record_time']['value']));
            $newData['record_time']['value'] = str_replace('00:00', '', $newData['record_time']['value']);
        }

        return $newData;
    }

    /**
     * 获取格式化数据公共头部（科室、姓名、住院号）
     * @param $blbh
     * @return string[]
     */
    public function getPatienPublicTopInfo($blbh)
    {
        $data = ['ks' => '', 'xm' => '', 'zyh' => '', 'ysqm' => ''];

        if (!$blbh) {
            return $data;
        }

        // 医师签名
        $SYYS = EMR_BL_BLSY::query()->where('BLBH', '=', $blbh)->value('SYYS');
        if ($SYYS) {
            $data['ysqm'] = Staff::query()->where('code', '=', $SYYS)->value('name');
        }

        // 住院号
        $JZHM = EMR_BL_BL01::query()->where('BLBH', '=', $blbh)->value('JZHM');
        if ($JZHM && empty($newData['hospital_no'])) {
            $patientInfo = PatientInfo::query()->where('MED_REC_ID', '=', $JZHM)->first();
            $data['ks'] = $patientInfo->AAC11N ?? '';
            $data['name'] = $patientInfo->AAA01 ?? '';
            $data['zyh'] = $patientInfo->AAA28 ?? '';
        }

        return $data;
    }

    public static function checkCaseList()
    {

        $page = 1;
        $pageSize = 10;
        $column = ['BLBH', 'JZHM', 'BRBH'];

        $caseRule = CaseRule::query()->select()->get()->toArray();
        $caseRule = array_column($caseRule, null, 'id');

        while (1) {
            $res = EMR_BL_BL01::getList($page, $pageSize, $column, ['BLLB' => 292]);
            if (!$res) {
                echo '获取数据完毕';
                break;
            }
            $errorNotice = [];
            foreach ($res as $no) {
                $checkRes = self::checkCase($no['BLBH'], $caseRule);
                if ($checkRes) {
                    $errorNotice = array_merge($errorNotice, $checkRes);
                    EMR_BL_BL01::query()->where('BLBH', '=', $no['BLBH'])->update(['is_defect' => 1]);
                    PatientInfo::query()->where('MED_REC_ID', '=', $no['JZHM'])->update(['is_defect' => 1]);
                }
                foreach ($errorNotice as &$e) {
                    $e['JZHM'] = $no['JZHM'];
                    $e['BRBH'] = $no['BRBH'];
                }
            }

            $page++;
            $res = CaseQuality::addData($errorNotice);
            var_dump($res);
        }

        Log::info('病例之间处理完成');
    }

    /**
     * @param string $no
     * 病例质控
     */
    public static function checkCase($no = '', $caseRule = [])
    {
        $errorNotice = [];
        $title = ["姓名:", "出生地:", "性别:", "职业:", "年龄:", "入院时间:", "民族:", "记录时间:", "婚姻:", "病史陈述者:", "主诉:", "现病史:", "既往史:", "个人史:", "月经及婚育史:", "婚育史:", "家族史:", "体格检查", "辅助检查", "初步诊断", "医师签名"];

        Log::info('质检病例编号 ' . $no);
        try {

            $keyMap = config("confAdmin.keyMap");
            if (empty($no)) {
                $no = self::ID;
            }
            $res = EMR_BL_BLXG::getById($no);
            if (!$res) {
                return [];
            }
            $caseContent = $res[0]['HJNR'];
            $blxgId = $res[0]['BLBH']; // EMR_BL_BLXG的主键字段

            // 整理数据，将数据整理成数组结构
            $caseContent = str_replace("月经婚育史", "月经及婚育史", $caseContent);
            $caseContent = str_replace("\r\n", "!!", $caseContent);
            $caseContent = str_replace("\n", "!!", $caseContent);
            $caseContent = str_replace(" ", "", $caseContent);
            $caseContent = str_replace("：", ":", $caseContent);
            $caseContent = str_replace("“", "\"", $caseContent);
            $caseContent = str_replace("”", "\"", $caseContent);

            $isWomen = strpos($caseContent, '月经及婚育史');
            // 如果病例中有月经及婚育史，则不通过婚育史提取数据
            if ($isWomen) {
                unset($title[15]);
            } else {
                unset($title[14]);
            }
            foreach ($title as $t) {
                $caseContent = str_replace($t, "|&|" . $t . '||', $caseContent);
            }
            // 去掉不用的病例项
            unset($title[20]);

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
                if ($info['title'] == '辅助检查') {
                    $info['value'] = str_replace('!!', '。', $info['value']);
                } elseif ($info['title'] == '初步诊断') {
                    $info['value'] = str_replace('!!', '|', $info['value']);
                } else {
                    $info['value'] = str_replace('!!', '', $info['value']);
                }
                $info['md5'] = md5($itemArr[0]);
                $newData[] = $info;
            }

            $newData = array_column($newData, null, 'md5');

            // 初步诊断数据解析成数组
            if (isset($newData[md5('初步诊断')])) {
                $cbzdData = $newData[md5('初步诊断')]['value'];
                if ($cbzdData) {
                    $cbzdData = str_replace([':', '滨医_住院签名', '滨医_', '住院签名', '主治签名', '{}', '!'], '', $cbzdData);
                    $cbzdData = preg_replace("/(\d)+[\.、]/", '|', $cbzdData);
                    $cbzdData = explode("|", $cbzdData);
                    $cbzdData = array_filter($cbzdData);
                    $newData['diagnose_list'] = [];
                    foreach ($cbzdData as $c) {
                        preg_match_all("/第(\d+)页/", $c, $pagePregRes);
                        if ($pagePregRes[1]) {
                            break;
                        }
                        $newData['diagnose_list'][] = $c;
                    }
                }
            }
            // 第一规则：是否有漏项
            foreach ($title as $t) {
                $md5Title = md5($t);
                if (empty($newData[$md5Title]['value'])) {
                    $errorNotice[] = [
                        'BLBH' => $res[0]['BLBH'],
                        'rule_id' => 53,
                        'notice' => $t . $caseRule[53]['notice'],
                        'code' => $keyMap[$md5Title],
                        'error_field' => $t
                    ];
                }
            }

            //            第二规则：要精确到区
            $localAddress = $newData[md5('出生地:')]['value'];
            $localAddressArr = explode('市', $localAddress);
            if (empty($localAddressArr[1])) {
                $errorNotice[] = [
                    'BLBH' => $res[0]['BLBH'],
                    'rule_id' => 54,
                    'notice' => $caseRule[54]['notice'],
                    'code' => $keyMap[md5('出生地:')],
                    'error_field' => '出生地'
                ];
            }

            // 规则3、4、5
            $rule34Res = self::rule34($newData, $blxgId, $caseRule);
            $errorNotice = array_merge($errorNotice, $rule34Res);

            // 6、记录发病的时间   规则：按时间先后顺序排序，早的在前边
            $rule6Res = self::rule6($newData, $blxgId, $caseRule);
            $errorNotice = array_merge($errorNotice, $rule6Res);

            // 7、规则：对患者提供的药名、诊断、手术需加  “  ”  （例：口服“卡马西平”治疗，疼痛能控制）
            $rule7Res = self::rule7($newData, $blxgId, $caseRule);
            $errorNotice = array_merge($errorNotice, $rule7Res);

            if ($isWomen) {
                // 8、规则：月经   性别是女的有，男的没有
                $rule8Res = self::rule8($newData, $blxgId, $caseRule);
                $errorNotice = array_merge($errorNotice, $rule8Res);
            }


            // ①现病史有，既往史中否认了，是模板没删除，需要质控出来
            $rule9Res = self::rule9($newData, $blxgId, $caseRule);
            $errorNotice = array_merge($errorNotice, $rule9Res);

            //10、②既往史中，写的5年前做过甲状腺手术，（诊断中得需要有甲状腺术后，如果既往史中有某某手术，但是诊断中没有某某术后的就为遗漏诊断，这个需要质控出来）诊断没写就要质控出来
            //要点：既往史中的手术在诊断中得体现为手术术后
            $rule10Res = self::rule10($newData, $blxgId, $caseRule);
            $errorNotice = array_merge($errorNotice, $rule10Res);

            $rule13Res = self::rule13($newData, $blxgId, $caseRule);
            $errorNotice = array_merge($errorNotice, $rule13Res);

            // 体格检查
            $rule14Res = self::rule14($newData, $blxgId, $caseRule);
            $errorNotice = array_merge($errorNotice, $rule14Res);

            $rule15Res = self::rule16($newData, $blxgId, $caseRule);
            $errorNotice = array_merge($errorNotice, $rule15Res);
        } catch (\Throwable $e) {
            Log::error("病例之间处理失败", ['msg' => $e->getMessage(), 'line' => $e->getLine()]);
            echo $e->getMessage() . '，所在行：' . $e->getLine();
        }

        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param string $blxgId
     * @return array
     * 主诉相关规则
     */
    private static function rule34($newData = [], $blxgId = '', $caseRule)
    {
        $errorNotice = [];
        //            3、规则：一般不超过 20 个字，＞20个字给出提醒。
        $zdData = $newData[md5('主诉:')]['value'];
        $zdDataLenth = mb_strlen($zdData);
        if ($zdDataLenth > $caseRule[55]['number1']) {
            $errorNotice[] = [
                'BLBH' => $blxgId,
                'rule_id' => 55,
                'notice' => $caseRule[55]['notice'],
                'code' => config("confAdmin.keyMap")[md5('主诉:')],
                'error_field' => '主诉'
            ];
        }

        // 4、规则：症状≤3个，按时间先后顺序列出，并记录每个症状的持续时间
        $response = ApiService::getDiseaseRes($zdData);
        $symptom = ''; // 主诉症状
        if (!$response) {
            return [];
        } else {
            $symptom = $response->symptom ?? '';
            if ($symptom) {
                if (count($symptom) > $caseRule[56]['number1']) {
                    $errorNotice[] = [
                        'BLBH' => $blxgId,
                        'rule_id' => 56,
                        'notice' => $caseRule[56]['notice'],
                        'code' => config("confAdmin.keyMap")[md5('主诉:')],
                        'error_field' => '主诉'
                    ];
                }
                // 记录所有的持续时间，用于验证时间排序规则
                $zsMaxTime = [];
                foreach ($symptom as $s) {
                    if (!isset($s->timeAttribute) || empty($s->timeAttribute)) {
                        $errorNotice[] = [
                            'BLBH' => $blxgId,
                            'rule_id' => 76,
                            'notice' => $caseRule[76]['notice'],
                            'code' => config("confAdmin.keyMap")[md5('主诉:')],
                            'error_field' => '主诉'
                        ];
                    } else {
                        $zsMaxTime[] = intval($s->timeAttribute);
                    }
                }
                if ($zsMaxTime) {
                    $flag = 0;
                    for ($i = 0; $i < count($zsMaxTime) - 1; $i++) {
                        if ($zsMaxTime[$i + 1] > $zsMaxTime[$i]) {
                            $flag++; // 如果出现下一个数字比前一个数字大则更新标识
                        }
                    }
                    if ($flag) {
                        $errorNotice[] = [
                            'BLBH' => $blxgId,
                            'rule_id' => 79,
                            'notice' => $caseRule[79]['notice'],
                            'code' => config("confAdmin.keyMap")[md5('主诉:')],
                            'error_field' => '主诉'
                        ];
                    }
                }
            }

            // 5、规则：通过主诉的症状 和（或） 体征 推断出第一诊断
            self::rule5($newData, $blxgId, $symptom);
        }

        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param string $blxgId
     * @param array $symptom
     * @return array
     * 5、规则：通过主诉的症状 和（或） 体征 推断出第一诊断
     */
    public static function rule5($newData = [], $blxgId = '', $symptom = [])
    {
        $errorNotice = [];
        if (empty($symptom)) {
            $errorNotice[] = [
                'BLBH' => $blxgId,
                'rule_id' => 5,
                'notice' => '主诉的症状信息获取失败',
                'code' => config("confAdmin.keyMap")[md5('主诉:')],
                'error_field' => '主诉'
            ];
            return $errorNotice;
        }
        $entity = array_column($symptom, 'entity');
        $entity = implode(',', $entity);

        $cbzdData = $newData['diagnose_list'];

        $zsDataResponse = ApiService::cdssAdvisorySubmit($entity);
        if ($zsDataResponse) {
            $disease = array_column($zsDataResponse, 'name');
            if ($disease) {
                $cbzdFirst = array_shift($cbzdData);
                if (!in_array($cbzdFirst, $disease)) {
                    $errorNotice[] = [
                        'BLBH' => $blxgId,
                        'rule_id' => 5,
                        'notice' => $cbzdFirst . '不在主诉症状的推断结果中',
                        'code' => config("confAdmin.keyMap")[md5('主诉:')],
                        'error_field' => $cbzdFirst
                    ];
                }
            }
        }
        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param string $blxgId
     * @return array
     * // 6、记录发病的时间   规则：按时间先后顺序排序，早的在前边
     * // 11、③现病史中有症状体征，主诉里没写对应的症状，需要提醒  （主诉和现病史不一致，如果现病史没有症状描述，那主诉里边可以写表现）
     */
    private static function rule6($newData = [], $blxgId = '', $caseRule)
    {

        $errorNotice = [];
        $zdData = $newData[md5('主诉:')]['value'];
        $zsResponse = ApiService::getDiseaseRes($zdData);
        $zsSymptom = ''; // 主诉症状
        if ($zsResponse) {
            $zsSymptom = $zsResponse->symptom ?? '';
        }

        $xbsData = $newData[md5('现病史:')]['value'];
        $response = ApiService::getDiseaseRes($xbsData);

        if (!$response) {
            return [];
        } else {

            // 症状体征数据
            $symptom = $response->symptom ?? '';
            if ($symptom) {

                // 记录所有的持续时间，用于验证时间排序规则
                $zsMaxTime6 = [];
                foreach ($symptom as $s) {
                    $timeAttribute = $s->timeAttribute ?? 0;
                    $zsMaxTime6[] = intval($timeAttribute);
                    // 校验症状是否记录在主诉中，没有记录则提醒
                    // 主诉里没有症状或体征,现病史里有症状或体征，就要提示
                    if (!$zsSymptom) {
                        // 11、③现病史中有症状体征，主诉里没写对应的症状，需要提醒  （主诉和现病史不一致，如果现病史没有症状描述，那主诉里边可以写表现）
                        if (strpos($zdData, $s->entity) === false) {
                            $errorNotice[] = [
                                'BLBH' => $blxgId,
                                'rule_id' => 57,
                                'notice' => $s->entity . '：' . $caseRule[57]['notice'],
                                'code' => config("confAdmin.keyMap")[md5('现病史:')],
                                'error_field' => $s->entity
                            ];
                        }
                    }
                }
                if ($zsMaxTime6) {
                    $flag = 0;
                    for ($i = 0; $i < count($zsMaxTime6) - 1; $i++) {
                        if ($zsMaxTime6[$i + 1] > $zsMaxTime6[$i]) {
                            $flag++; // 如果出现下一个数字比前一个数字大则更新标识
                        }
                    }
                    if ($flag) {
                        $errorNotice[] = [
                            'BLBH' => $blxgId,
                            'rule_id' => 6,
                            'notice' => '现病史症状的时间先后顺序有误',
                            'code' => config("confAdmin.keyMap")[md5('现病史:')],
                            'error_field' => '现病史'
                        ];
                    }
                }
            }
        }


        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param string $blxgId
     * @return array
     * 7、规则：对患者提供的药名、诊断、手术需加  “  ”  （例：口服“卡马西平”治疗，疼痛能控制）
     */
    private
    static function rule7($newData = [], $blxgId = '', $caseRule)
    {

        $errorNotice = [];
        $xbsData = $newData[md5('现病史:')]['value'];
        $response = ApiService::getDiseaseRes($xbsData);
        if (!$response) {
            return [];
        } else {
            $jwsAllTitle = [];
            // 用药
            $medicine = $response->medicine ?? '';

            if ($medicine) {
                $medicine = array_unique(array_column($medicine, 'entity'));
                foreach ($medicine as $t) {
                    $hasStr = strpos($xbsData, '"' . $t . '"');
                    if ($hasStr === false) {
                        $errorNotice[] = [
                            'BLBH' => $blxgId,
                            'rule_id' => 58,
                            'notice' => '现病史中对患者提供的 药名【' . $t . '】需要加引号',
                            'code' => config("confAdmin.keyMap")[md5('现病史:')],
                            'error_field' => $t
                        ];
                    }
                }
            }
            // 疾病
            $disease = $response->disease ?? '';
            if ($disease) {
                $disease = array_unique(array_column($disease, 'entity'));
                foreach ($disease as $t) {
                    $hasStr = strpos($xbsData, '"' . $t . '"');
                    if ($hasStr === false) {
                        $errorNotice[] = [
                            'BLBH' => $blxgId,
                            'rule_id' => 58,
                            'notice' => '现病史中对患者提供的诊断【' . $t . '】需要加引号',
                            'code' => config("confAdmin.keyMap")[md5('现病史:')],
                            'error_field' => $t
                        ];
                    }
                }
            }
        }


        $jwsData = $newData[md5('既往史:')]['value'];
        $response = ApiService::getDiseaseRes($jwsData);
        if (!$response) {
            return [];
        } else {
            $jwsAllTitle = [];
            // 用药
            $medicine = $response->medicine ?? '';
            if ($medicine) {
                $medicine = array_unique(array_column($medicine, 'entity'));
                foreach ($medicine as $t) {
                    $hasStr = strpos($jwsData, '"' . $t . '"');
                    if ($hasStr === false) {
                        $errorNotice[] = [
                            'BLBH' => $blxgId,
                            'rule_id' => 58,
                            'notice' => '既往史中对患者提供的 药名【' . $t . '】需要加引号',
                            'code' => config("confAdmin.keyMap")[md5('现病史:')],
                            'error_field' => $t
                        ];
                    }
                }
            }
            // 疾病
            $disease = $response->disease ?? '';
            if ($disease) {
                $disease = array_unique(array_column($disease, 'entity'));
                foreach ($disease as $t) {
                    $hasStr = strpos($jwsData, '"' . $t . '"');
                    if ($hasStr === false) {
                        $errorNotice[] = [
                            'BLBH' => $blxgId,
                            'rule_id' => 58,
                            'notice' => '既往史中对患者提供的诊断【' . $t . '】需要加引号',
                            'code' => config("confAdmin.keyMap")[md5('现病史:')],
                            'error_field' => $t
                        ];
                    }
                }
            }
        }
        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param string $blxgId
     * @return array
     * 8、规则：月经   性别是女的有，男的没有
     */
    private
    static function rule8($newData = [], $blxgId = '', $caseRule)
    {
        $errorNotice = [];
        $xbData = $newData[md5('性别:')]['value'];
        $yjjhysData = $newData[md5('月经及婚育史:')]['value'] ?? '';
        $hysData = $newData[md5('婚育史:')]['value'] ?? '';
        if (($xbData == '女' && empty($yjjhysData)) || ($xbData == '男' && !empty($yjjhysData))) {
            $errorNotice[] = [
                'BLBH' => $blxgId,
                'rule_id' => 62,
                'notice' => $caseRule[62]['notice'],
                'code' => config("confAdmin.keyMap")[md5('月经及婚育史:')]
            ];
        }
        $yjjhysData = str_replace('，', ',', $yjjhysData);

        preg_match_all("/月经周期(\d+-\d+)天/", $yjjhysData, $yjzq);
        if ($yjzq[1]) {
            $yjzq = explode('-', $yjzq[1][0]);

            if ($yjzq[0] < $caseRule[64]['number1'] || $yjzq[1] > $caseRule[64]['number2']) {
                $errorNotice[] = [
                    'BLBH' => $blxgId,
                    'rule_id' => 64,
                    'notice' => $caseRule[64]['notice'],
                    'code' => config("confAdmin.keyMap")[md5('月经及婚育史:')],
                    'error_field' => '月经周期'
                ];
            }
        }

        preg_match_all("/经期(\d+-\d+)天/", $yjjhysData, $jq);
        if ($jq[1]) {
            $jq = explode('-', $jq[1][0]);

            if ($jq[0] < $caseRule[80]['number1'] || $jq[1] > $caseRule[80]['number2']) {
                $errorNotice[] = [
                    'BLBH' => $blxgId,
                    'rule_id' => 80,
                    'notice' => $caseRule[80]['notice'],
                    'code' => config("confAdmin.keyMap")[md5('月经及婚育史:')],
                    'error_field' => '经期'
                ];
            }
        }

        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param string $blxgId
     * @return array
     * 9、①现病史有，既往史中否认了，是模板没删除，需要质控出来
     * "现病史:", "既往史:",
     */
    private
    static function rule9($newData = [], $blxgId = '', $caseRule)
    {
        $errorNotice = [];
        $xbsData = $newData[md5('现病史:')]['value'];
        $jwsData = $newData[md5('既往史:')]['value'] ?? '';
        $cbzdData = $newData['diagnose_list'] ? implode(',', $newData['diagnose_list']) : [];

        $xbsDataResponse = ApiService::getDiseaseRes($xbsData);
        if ($xbsDataResponse) {

            $disease = $xbsDataResponse->disease ?? '';
            if ($disease) {

                $disease = array_unique(array_column($disease, 'entity'));
                // 记录所有的持续时间，用于验证时间排序规则
                $flag = 0;

                // 正则表达式匹配一次否认多个疾病的信息，例如：否认"肝结菌痢、伤寒"等传染病史
                preg_match_all("/否认\"(.*)\"/", $jwsData, $jwsDataNoRes);

                foreach ($disease as $s) {
                    $entity = $s ?: '';
                    if (empty($entity)) {
                        continue;
                    }

                    if (strpos($cbzdData, $entity) === false) {
                        $errorNotice[] = [
                            'BLBH' => $blxgId,
                            'rule_id' => 71,
                            'notice' => '现病史中的【' . $entity . '】未体现在初步诊断中',
                            'code' => config("confAdmin.keyMap")[md5('现病史:')],
                            'error_field' => $entity
                        ];
                    }

                    // 字符串匹配是否有否认的疾病，例如：否认冠心病
                    $checkRes = strpos($jwsData, '否认' . $entity);
                    if ($checkRes !== false) {
                        $errorNotice[] = [
                            'BLBH' => $blxgId,
                            'rule_id' => 60,
                            'notice' => $caseRule[60]['notice'],
                            'code' => config("confAdmin.keyMap")[md5('现病史:')],
                            'error_field' => $entity
                        ];
                    }

                    // 根据正则匹配的结果判断是否存在被否认疾病
                    if ($jwsDataNoRes[1]) {
                        foreach ($jwsDataNoRes[1] as $j) {
                            $checkRes = strpos($j, $entity);
                            if ($checkRes !== false) {
                                $errorNotice[] = [
                                    'BLBH' => $blxgId,
                                    'rule_id' => 60,
                                    'notice' => $caseRule[60]['notice'],
                                    'code' => config("confAdmin.keyMap")[md5('现病史:')],
                                    'error_field' => $entity
                                ];
                            }
                        }
                    }
                }
            }
        }
        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param string $blxgId
     * @return array
     * 10、②既往史中，写的5年前做过甲状腺手术，
     * （诊断中得需要有甲状腺术后，如果既往史中有某某手术，但是诊断中没有某某术后的就为遗漏诊断，这个需要质控出来）诊断没写就要质控出来
     */
    private
    static function rule10($newData = [], $blxgId = '', $caseRule = [])
    {
        $errorNotice = [];
        $jwsData = $newData[md5('既往史:')]['value'] ?? '';
        $xbsDataResponse = ApiService::getDiseaseRes($jwsData);

        if (!$xbsDataResponse) {
            return [];
        } else {

            $medicine = $response->medicine ?? '';
            if (!$medicine) {
                return [];
            } else {
                $cbzdData = $newData['diagnose_list'];
                $zsMaxTime = [];
                foreach ($medicine as $s) {
                    $timeAttribute = $s->timeAttribute ?? 0;
                    $zsMaxTime[] = intval($timeAttribute);
                    $entity = $s->entity ?: '';
                    if (!in_array($entity . '术后', $cbzdData)) {
                        $errorNotice[] = [
                            'BLBH' => $blxgId,
                            'rule_id' => 61,
                            'notice' => $caseRule[61]['notice'],
                            'code' => config("confAdmin.keyMap")[md5('既往史:')],
                            'error_field' => $entity
                        ];
                    }
                }

                if ($zsMaxTime) {
                    $flag = 0;
                    for ($i = 0; $i < count($zsMaxTime) - 1; $i++) {
                        if ($zsMaxTime[$i + 1] > $zsMaxTime[$i]) {
                            $flag++; // 如果出现下一个数字比前一个数字大则更新标识
                        }
                    }
                    if ($flag) {
                        $errorNotice[] = [
                            'BLBH' => $blxgId,
                            'rule_id' => 63,
                            'notice' => $caseRule[63]['notice'],
                            'code' => config("confAdmin.keyMap")[md5('既往史:')],
                            'error_field' => '既往史'
                        ];
                    }
                }
            }
        }
        return $errorNotice;
    }


    /**
     * @param array $newData
     * @param string $blxgId
     * @return array
     * 13、通过国临2.0疾病编码来判断每个疾病诊断的名称是否啥标准化，如果不是标准化就提示出标准化的名称是什么
     */
    private static function rule13($newData = [], $blxgId = '', $caseRule = [])
    {
        $errorNotice = [];
        $cbzdData = $newData['diagnose_list'];
        if ($cbzdData) {
            foreach ($cbzdData as $v) {
                // 判断诊断名称是否存在标准中
                $res = DiseaseDiagnosisCode::getInfoByName($v);
                if (!$res) {
                    $notice = [
                        'BLBH' => $blxgId,
                        'rule_id' => 72,
                        'notice' => $v . '：' . $caseRule[72]['notice'],
                        'code' => 'diagnose_list',
                        'error_field' => $v
                    ];
                    // 不是标准化名称则需要查找标准化名称
                    $rightName = '';
                    for ($i = mb_strlen($v); $i >= 1; $i--) {
                        $resStr = mb_substr($v, 0, $i);
                        $res = DiseaseDiagnosisCode::getInfoByName($resStr);
                        if ($res) {
                            $rightName = $resStr;
                        }
                    }
                    if ($rightName) {
                        $notice['notice'] .= '，标准化名称为：' . $rightName;
                    }

                    $errorNotice[] = $notice;
                }
            }
        }


        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param string $blxgId
     * @return array
     * 14、体格检查中有的阳性体征的描述，对应的模板中正常描述需要删除。    和诊断名称对比
     */
    private static function rule14($newData = [], $blxgId = '', $caseRule)
    {
        $errorNotice = [];
        $cbzdData = $newData[md5('体格检查')]['value'] ?: '';
        $tgjcData = $cbzdData;

        $jwsData = $newData[md5('既往史:')]['value'] ?? '';
        //        将所有信息都分割为数组，并将阳性符号统一为"(+)"
        $cbzdData = str_replace(["，", '。'], '|', $cbzdData);
        $cbzdData = str_replace('（', '(', $cbzdData);
        $cbzdData = str_replace('）', ')', $cbzdData);
        $cbzdData = str_replace('阳性', '(+)', $cbzdData);
        $cbzdData = explode('|', $cbzdData);
        if (empty($cbzdData)) {
            Log::info($blxgId . '：体格检查解析失败');
            return [];
        }

        //        体温检测
        preg_match_all("/[体温:]*(\d+\.\d+)/", $cbzdData[0], $tiwen);
        $diagnoseList = $newData['diagnose_list'] ?: [];

        if (!empty($tiwen[1][0]) && $diagnoseList) {
            if (
                ($caseRule[82]['number1'] <= $tiwen[1][0] && $caseRule[82]['number2'] > $tiwen[1][0]) ||
                ($caseRule[83]['number1'] <= $tiwen[1][0] && $caseRule[83]['number2'] > $tiwen[1][0]) ||
                ($caseRule[84]['number1'] <= $tiwen[1][0] && $caseRule[84]['number2'] > $tiwen[1][0]) ||
                ($caseRule[65]['number2'] <= $tiwen[1][0])
            ) {
                $diagnoseList = implode(',', $diagnoseList);
                if (strpos($diagnoseList, '发热') === false) {
                    $errorNotice[] = [
                        'BLBH' => $blxgId,
                        'rule_id' => 82,
                        'notice' => $caseRule[82]['notice'],
                        'code' => config("confAdmin.keyMap")[md5('体格检查')],
                        'error_field' => $tiwen[1][0]
                    ];
                }
            }
        }


        //        脉搏检测
        preg_match_all("/脉搏:(\d+)次\/分/", $tgjcData, $maibo);
        if ($maibo[1] && $maibo[1][0] > $caseRule[66]['number2']) {
            $errorNotice[] = [
                'BLBH' => $blxgId,
                'rule_id' => 66,
                'notice' => $caseRule[66]['notice'],
                'code' => config("confAdmin.keyMap")[md5('体格检查')],
                'error_field' => $maibo[1][0] ?: 0
            ];
        }
        if ($maibo[1] && $maibo[1][0] < $caseRule[67]['number1']) {
            $errorNotice[] = [
                'BLBH' => $blxgId,
                'rule_id' => 67,
                'notice' => $caseRule[67]['notice'],
                'code' => config("confAdmin.keyMap")[md5('体格检查')],
                'error_field' => $maibo[1][0] ?: 0
            ];
        }

        //        呼吸:检测
        preg_match_all("/呼吸:(\d+)次\/分/", $tgjcData, $maibo);
        if ($maibo[1] && $maibo[1][0] > $caseRule[68]['number2']) {
            $errorNotice[] = [
                'BLBH' => $blxgId,
                'rule_id' => 68,
                'notice' => $caseRule[68]['notice'],
                'code' => config("confAdmin.keyMap")[md5('体格检查')],
                'error_field' => $maibo[1][0] ?: 0
            ];
        }
        if ($maibo[1] && $maibo[1][0] < $caseRule[69]['number1']) {
            $errorNotice[] = [
                'BLBH' => $blxgId,
                'rule_id' => 69,
                'notice' => $caseRule[69]['notice'],
                'code' => config("confAdmin.keyMap")[md5('体格检查')],
                'error_field' => $maibo[1][0] ?: 0
            ];
        }


        // 收集阳性症状
        $yxData = [];
        foreach ($cbzdData as $item) {
            if (strpos($item, '(+)') !== false) {
                $yxData[] = $item;
            }
        }

        // 正则表达式匹配一次否认多个疾病的信息，例如：否认"肝结菌痢、伤寒"等传染病史
        preg_match_all("/否认\"(.*)\"/", $jwsData, $jwsDataNoRes);

        foreach ($yxData as $entity) {
            if (empty($entity)) {
                continue;
            }

            // 字符串匹配是否有否认的疾病，例如：否认冠心病
            $checkRes = strpos($jwsData, '否认' . $entity);
            if ($checkRes !== false) {
                $errorNotice[] = [
                    'BLBH' => $blxgId,
                    'rule_id' => 85,
                    'notice' => $caseRule[85]['notice'],
                    'code' => config("confAdmin.keyMap")[md5('体格检查')],
                    'error_field' => $entity ?: 0
                ];
            }

            // 根据正则匹配的结果判断是否存在被否认疾病
            if ($jwsDataNoRes[1]) {
                foreach ($jwsDataNoRes[1] as $j) {
                    $checkRes = strpos($j, $entity);
                    if ($checkRes !== false) {
                        $errorNotice[] = [
                            'BLBH' => $blxgId,
                            'rule_id' => 85,
                            'notice' => $caseRule[85]['notice'],
                            'code' => config("confAdmin.keyMap")[md5('体格检查')],
                            'error_field' => $entity ?: 0
                        ];
                    }
                }
            }
        }

        preg_match_all("/[血压BP:]*(\d+\/\d+)mmHg/", $tgjcData, $bloodPressure);
        if ($bloodPressure[1]) {
            // 分割舒张压和收缩压
            $bloodPressure = explode('/', $bloodPressure[1][0]);
            // 收缩压
            if (
                $caseRule[86]['number1'] >= $bloodPressure[0] && $caseRule[86]['number2'] <= $bloodPressure[0] ||
                $caseRule[87]['number1'] >= $bloodPressure[1] && $caseRule[87]['number2'] <= $bloodPressure[1]
            ) {
                $errorNotice[] = [
                    'BLBH' => $blxgId,
                    'rule_id' => 86,
                    'notice' => $caseRule[86]['notice'],
                    'code' => config("confAdmin.keyMap")[md5('体格检查')],
                    'error_field' => $bloodPressure[1][0] ?: 0
                ];
            } elseif (
                $caseRule[88]['number1'] >= $bloodPressure[0] && $caseRule[88]['number2'] <= $bloodPressure[0] ||
                $caseRule[89]['number1'] >= $bloodPressure[1] && $caseRule[89]['number2'] <= $bloodPressure[1]
            ) {
                $errorNotice[] = [
                    'BLBH' => $blxgId,
                    'rule_id' => 88,
                    'notice' => $caseRule[88]['notice'],
                    'code' => config("confAdmin.keyMap")[md5('体格检查')],
                    'error_field' => $bloodPressure[1][0] ?: 0
                ];
            }

            if ($caseRule[90]['number2'] <= $bloodPressure[0] || $caseRule[91]['number2'] <= $bloodPressure[1]) {
                $errorNotice[] = [
                    'BLBH' => $blxgId,
                    'rule_id' => 90,
                    'notice' => $caseRule[90]['notice'],
                    'code' => config("confAdmin.keyMap")[md5('体格检查')],
                    'error_field' => $bloodPressure[1][0] ?: 0
                ];
            }
        }
        return $errorNotice;
    }


    /**
     * @param array $newData
     * @param string $blxgId
     * @return array
     * 16、辅助检查中出现阳性检查结果，系统提醒关联诊断。如乙肝五项检查结果中，若出现表面抗原阳性，提示乙肝小三阳，若表面抗原及e抗原阳性时提示乙肝大三阳。
     */
    private static function rule16($newData = [], $blxgId = '', $caseRule = [])
    {
        $errorNotice = [];
        $fzjcData = $fzjcDataOld = $newData[md5('辅助检查')]['value'];
        $cbzdData = $newData[md5('初步诊断')]['value'];
        //        将所有信息都分割为数组，并将阳性符号统一为"(+)"
        $fzjcData = str_replace(["，", '。', '、'], '|', $fzjcData);
        $fzjcData = str_replace('（', '(', $fzjcData);
        $fzjcData = str_replace('）', ')', $fzjcData);
        $fzjcData = str_replace('阳性', '(+)', $fzjcData);
        $fzjcData = explode('|', $fzjcData);

        // 收集阳性症状
        $yxData = [];
        foreach ($fzjcData as $item) {
            if (strpos($item, '(+)') !== false) {
                $yxData[] = $item;
            }
        }

        foreach ($yxData as $entity) {
            // 字符串匹配是否有否认的疾病，例如：否认冠心病
            if (strpos($cbzdData, $entity) === false) {
                $errorNotice[] = [
                    'BLBH' => $blxgId,
                    'rule_id' => 75,
                    'notice' => '辅助检查中的【' . $entity . '】未体现在诊断结果中',
                    'code' => config("confAdmin.keyMap")[md5('辅助检查')],
                    'error_field' => $entity ?: 0
                ];
            }
        }

        $pattern = '/(19|20)\d{2}-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[01])/';
        if (strpos($fzjcDataOld, '我院') !== false || strpos($fzjcDataOld, '医院') !== false) {
            if (preg_match($pattern, $fzjcDataOld)) {
                $errorNotice[] = [
                    'BLBH' => $blxgId,
                    'rule_id' => 94,
                    'notice' => $caseRule[94]['notice'],
                    'code' => config("confAdmin.keyMap")[md5('辅助检查')],
                    'error_field' => '辅助检查'
                ];
            }
        }

        return $errorNotice;
    }

    /**
     * @param string $no
     * @return array|bool
     * 解析病例内容
     */
    public static function analysisCase()
    {
        $pageSize = 10;
        $lastNo = 0;
        $column = ['BLBH', 'JZHM', 'BLLB', 'analysis_index'];
        try {
            while (1) {
                $res01 = EMR_BL_BL01::query()
                    ->select($column)
                    ->where('analysis_index', '<', 5)
                    ->whereIn("BLLB", [1, 292])
                    ->where('BLBH', '>', $lastNo)
                    ->where('BLZT', '=', 1)
                    ->orderBy("BLBH", "asc")
                    ->LIMIT($pageSize)
                    ->get()->toArray();

                if (!$res01) {
                    echo '获取数据完毕';
                    break;
                }
                foreach ($res01 as $no) {
                    $lastNo = $no['BLBH'];
                    $res = EMR_BL_BLXG::getById($no['BLBH']);
                    if (!$res) {
                        continue;
                    }
                    $caseContent = $res[0]['HJNR'];
                    if ($no['BLLB'] == 1) {
                        self::analysisCaseCy($no, $caseContent);
                    } elseif ($no['BLLB'] == 292) {
                        self::analysisCaseRy($no, $caseContent);
                    }
                }
            }
        } catch (\Throwable $e) {
            var_dump("病例处理失败", ['msg' => $e->getMessage(), 'line' => $e->getLine()]);
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
    public static function analysisCaseRy($no = [], $caseContent = "")
    {
        if (!$caseContent || !$no) {
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

        // 初步诊断数据解析成数组
        $cbzdData = '';
        $diagnosList = [];
        if (isset($newData[md5('初步诊断')])) {
            $cbzdData = $newData[md5('初步诊断')]['value'];
        }
        if ($cbzdData) {
            $cbzdData = str_replace([':', '滨医_住院签名', '滨医_', '住院签名', '主治签名', '{}', '!'], '', $cbzdData);
            $cbzdData = preg_replace("/(\d)+[\.、]/", '|', $cbzdData);
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
        $jsonData = json_encode($newData, 256);
        if ($diagnosList && is_array($diagnosList)) {
            $diagnosList = implode(',', $diagnosList);
        } elseif (!is_array($diagnosList)) {
            exit;
        }
        $updata = ['analysis_index' => ($no['analysis_index'] + 1), 'diagnose_list' => ($diagnosList ? base64_encode($diagnosList) : ''), 'analysis_case' => $jsonData];

        if (!empty($newData[md5('现病史:')])) {

            $xbsData = $newData[md5('现病史:')]['value'];

            // 整理数据，将数据整理成数组结构
            $xbsData = str_replace("\r\n", "", $xbsData);
            $xbsData = str_replace("\n", "", $xbsData);
            $xbsData = str_replace("：", ":", $xbsData);
            //                        $xbsData = str_replace(['.', '(', ')', '（', '）', "“", "”", "，", "。", "；", "、", ' '], "", $xbsData);

            $updata['ryjl_xbs'] = $xbsData ?: '';
        }
        $res = EMR_BL_BL01::updateById($no['BLBH'], $updata);
        return $jsonData;
    }


    /**
     * @param string $no
     * @return array|bool
     * 出院记录解析病例内容
     */
    public static function analysisCaseCy($no = [], $caseContent = "")
    {
        if (!$caseContent || !$no) {
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
            'analysis_index' => ($no['analysis_index'] + 1),
            'analysis_case' => $jsonData
        ];
        $res = EMR_BL_BL01::updateById($no['BLBH'], $updata);
        return $jsonData;
    }

    /**
     * 获取手术格式化数据
     * @param $blbh
     * @param $bllb
     * @return array
     */
    public function getSurgeryData($blbh, $bllb)
    {
        $dataList = EMR_BL_BL01::query()
            ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.BLBH', '=', $blbh)
            ->where('BLLB', '=', $bllb)
            ->where('BLZT', '!=', 9)
            ->get(['EMR_BL_BL01.BLBH', 'MBLB', 'BRXM', 'EMR_BL_BL01.JZHM', 'BLMC', 'BRBH', 'BRXM', 'HJNR', 'surgery_content', 'ZXSJ', 'CJSJ', 'WCSJ', 'first_blsy_time', 'HTML_PRINT'])
            ->toArray();

        $returnData = [];
        foreach ($dataList as $value) {
            if (!empty($value['surgery_content']) && empty($value['HTML_PRINT'])) {
                $doctorName = '';
                $SYYS = EMR_BL_BLSY::query()->where('BLBH', '=', $blbh)->pluck('SYYS')->toArray();
                if ($SYYS) {
                    $staffList = Staff::query()->whereIn('code', $SYYS)->get(['code', 'name', 'ygjb_text', 'YGBH'])->toArray();
                    $nameList = [];
                    foreach ($staffList as $staffInfo) {
                        $nameList[$staffInfo['code']] = $staffInfo['name'] . "(" . $staffInfo['YGBH'] . ")" . "（" . $staffInfo['ygjb_text'] . "）";
                    }
                    $doctorName = implode('、', $nameList);
                }

                $surgeryData = json_decode($value['surgery_content'], true);
                $surgeryData['ZXSJ'] = $value['first_blsy_time'];
                $surgeryData['CJSJ'] = $value['CJSJ'];
                $surgeryData['WCSJ'] = $value['WCSJ'];
                $surgeryData['doctor_name'] = $doctorName;
                $returnData[] = [
                    'is_format' => 1,
                    'blbh' => $blbh,
                    'surgery_data' => $surgeryData,
                ];
            } else {
                $doctorName = '';
                $SYYS = EMR_BL_BLSY::query()->where('BLBH', '=', $blbh)->pluck('SYYS')->toArray();
                if ($SYYS) {
                    $staffList = Staff::query()->whereIn('code', $SYYS)->get(['name', 'ygjb_text', 'YGBH'])->toArray();
                    $nameList = [];
                    foreach ($staffList as $staffInfo) {
                        $nameList[] = $staffInfo['name'] . "(" . $staffInfo['YGBH'] . ")" . "（" . $staffInfo['ygjb_text'] . "）";
                    }
                    $doctorName = implode('、', $nameList);
                }

                if ($value['MBLB'] == 306) {
                    $bllb303 = BLLB303::query()->where('BLBH', '=', $value['BLBH'])->first();
                }
                if (!empty($bllb303) && empty($value['HTML_PRINT'])) {
                    $surgeryData = $bllb303->toArray();
                    $surgeryData['SQZD'] = !empty($surgeryData['SQZD']) ? json_decode($surgeryData['SQZD'], true) : '';
                    $surgeryData['SZZD'] = !empty($surgeryData['SZZD']) ? json_decode($surgeryData['SZZD'], true) : '';
                    $surgeryData['SSJG'] = !empty($surgeryData['SSJG']) ? json_decode($surgeryData['SSJG'], true) : '';

                    $surgeryData['ZXSJ'] = $value['first_blsy_time'];
                    $surgeryData['CJSJ'] = $value['CJSJ'];
                    $surgeryData['WCSJ'] = $value['WCSJ'];
                    $surgeryData['doctor_name'] = $doctorName;
                    $returnData[] = [
                        'is_format' => 1,
                        'type' => 2,
                        'blbh' => $blbh,
                        'surgery_data' => $surgeryData,
                    ];
                } else {
                    $hjnr = $value['HJNR'];
                    $surgeryType = '';
                    if (stripos($hjnr, '手术风险评估表')) {
                        $surgeryType = 1;
                    } elseif (stripos($hjnr, '手术安全核查表')) {
                        $surgeryType = 2;
                    } elseif (stripos($hjnr, '手术同意书')) {
                        $surgeryType = 3;
                    } elseif (stripos($hjnr, '手术记录')) {
                        $surgeryType = 4;
                    } elseif (stripos($hjnr, '剖宮产记录')) {
                        //                        $surgeryType = 5;
                    }
                    $hjnr = $value['HTML_PRINT'] ?: $hjnr;
                    $returnData[] = [
                        'is_format' => 0,
                        'blbh' => $blbh,
                        'surgery_data' => [
                            'type' => $surgeryType,
                            'content' => $hjnr,
                            'ZXSJ' => $value['first_blsy_time'],
                            'CJSJ' => $value['CJSJ'],
                            'WCSJ' => $value['WCSJ'],
                            'doctor_name' => $doctorName,
                        ],
                    ];
                }
            }
        }

        // 数据脱敏
        if (!empty(request()->post('is_tm')) && !empty($dataList) && !empty($returnData) && !empty($dataList[0]['BRXM'])) {
            $brxm = $dataList[0]['BRXM'];
            $desensitizeData = desensitize($brxm, 1, 0, $re = '*');
            foreach ($returnData as &$d) {
                if (!empty($d['surgery_data']['content'])) {
                    $d['surgery_data']['content'] = str_replace($brxm, $desensitizeData, $d['surgery_data']['content']);
                }
                if (!empty($d['surgery_data']['brxm'])) {
                    $d['surgery_data']['brxm'] = str_replace($brxm, $desensitizeData, $d['surgery_data']['brxm']);
                }
            }
        }

        return $returnData;
    }

    /**
     * 获取病程格式化数据
     * @param $blbh
     * @param $bllb
     * @return array
     */
    public function getBcData($blbh, $bllb)
    {
        $dataList = EMR_BL_BL01::query()
            ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.BLBH', '=', $blbh)
            ->where('EMR_BL_BL01.BLZT', '!=', 9)
            ->get(['EMR_BL_BL01.BLBH', 'EMR_BL_BL01.YWSJ', 'BLLB', 'MBLB', 'BRXM', 'EMR_BL_BL01.JZHM', 'BLMC', 'BRBH', 'BRXM', 'BRKS', 'HJNR', 'bingcheng_content', 'ZXSJ', 'CJSJ', 'WCSJ', 'first_blsy_time', 'HTML_PRINT'])
            ->toArray();

        $returnData = [];
        foreach ($dataList as $value) {
            $bcData = [];
            if ($value["MBLB"] == 295) {
                $bcData = BLLB294_295::query()->where("BLBH", $value["BLBH"])->first();
                if ($bcData) {
                    $bcData = $bcData->toArray();
                }
                $bcData["type"] = 1;
            }
            if (!empty($bcData)) {
                $bcData['ZXSJ'] = $value['first_blsy_time'];
                $bcData['CJSJ'] = $value['CJSJ'];
                $bcData['WCSJ'] = $value['WCSJ'];
                $bcData['title'] = $value['BLMC'];
                $bcData['YWSJ'] = $value['YWSJ'];
                $bcData['HJNR'] = $value['HJNR'];
                $bcData['HTML_PRINT'] = $value['HTML_PRINT'];
                $doctorName = '';
                $SYYS = EMR_BL_BLSY::query()->where('BLBH', '=', $blbh)->where('FG_ACTIVE', '=', 1)->pluck('SYYS')->toArray();
                if ($SYYS) {
                    $SYYS = array_unique($SYYS);
                    $staffList = Staff::query()->whereIn('code', $SYYS)->get(['name', 'code', 'ygjb_text', 'YGBH'])->toArray();
                    $nameList = [];
                    foreach ($staffList as $staffInfo) {
                        $nameList[trim($staffInfo['YGBH'])] = $staffInfo['name'] . "(" . $staffInfo['YGBH'] . ")" . "（" . $staffInfo['ygjb_text'] . "）";
                    }
                    $doctorName = implode('、', $nameList);
                }
                $bcData['doctor_name'] = $doctorName;
                $returnData[] = [
                    'is_format' => 1,
                    'bc_data' => $bcData,
                ];
            } else {
                $hjnr = $value['HJNR'];
                $surgeryType = '';
                if (stripos($hjnr, '术前小结及术前讨论结论记录')) {
                    $surgeryType = 1;
                } elseif (stripos($hjnr, '术后首次病程记录')) {
                    $surgeryType = 2;
                } elseif (stripos($hjnr, '首次病程记录')) {
                    $surgeryType = 3;
                } elseif (stripos($hjnr, '查房记录')) {
                    $surgeryType = 4;
                } elseif (stripos($hjnr, '转入记录')) {
                    $surgeryType = 5;
                } elseif (stripos($hjnr, '转出记录')) {
                    $surgeryType = 6;
                }

                $doctorName = '';
                $SYYS = EMR_BL_BLSY::query()->where('BLBH', '=', $blbh)->where('FG_ACTIVE', '=', 1)->pluck('SYYS')->toArray();
                if ($SYYS) {
                    $SYYS = array_unique($SYYS);
                    $staffList = Staff::query()->whereIn('code', $SYYS)->get(['name', 'code', 'ygjb_text', 'YGBH'])->toArray();
                    $nameList = [];
                    foreach ($staffList as $staffInfo) {
                        $nameList[trim($staffInfo['YGBH'])] = $staffInfo['name'] . "(" . $staffInfo['YGBH'] . ")" . "（" . $staffInfo['ygjb_text'] . "）";
                    }
                    $doctorName = implode('、', $nameList);
                }

                $returnData[] = [
                    'is_format' => 0,
                    'bc_data' => [
                        'type' => $surgeryType,
                        'content' => $hjnr,
                        'HTML_PRINT' => $value['HTML_PRINT'],
                        'ZXSJ' => $value['first_blsy_time'],
                        'CJSJ' => $value['CJSJ'],
                        'YWSJ' => $value['YWSJ'],
                        'WCSJ' => $value['WCSJ'],
                        'doctor_name' => $doctorName,
                    ],
                ];
            }
        }

        // 数据脱敏
        // !empty(request()->post('is_tm')) &&
        if (!empty($returnData) && !empty($returnData[0]['bc_data']['brxm'])) {
            $brxm = $returnData[0]['bc_data']['brxm'];
            $desensitizeData = desensitize($brxm, 1, 1, $re = '*');
            $returnData[0]['bc_data']['brxm'] = $desensitizeData;
            if (!empty($returnData[0]['bc_data']['BLTD'])) {
                foreach ($returnData[0]['bc_data']['BLTD'] as &$d) {
                    $d = str_replace($brxm, $desensitizeData, $d);
                }
            }
            if (!empty($returnData[0]['bc_data']['desc'])) {
                foreach ($returnData[0]['bc_data']['desc'] as &$d) {
                    $d = str_replace($brxm, $desensitizeData, $d);
                }
            }
        }

        return $returnData;
    }
}
