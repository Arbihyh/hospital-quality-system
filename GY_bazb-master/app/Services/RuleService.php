<?php

namespace App\Services;

use App\Model\DiseaseDiagnosisCode;
use App\Model\EMR_BL_BLSY;
use App\Model\RuleWordMap;
use App\Model\Staff;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;
use function GuzzleHttp\Psr7\str;

/**
 * 入院记录质控
 */
class RuleService
{
    public function __construct()
    {

    }

    /**
     * @param array $data
     * @param array $caseRule
     * @param array $newData
     * @return array
     *
     * 入院后48小时的病程记录（首程除外）必须有 “主任或副主任”查房'
     */
    public static function rule246($data = [], $caseRule = [], $newData = [])
    {
        $ruleid = 246;
        if (empty($caseRule[$ruleid]['status']) || empty($data)) {
            return [];
        }
        $isHas = false;
        $errorNotice = [];
        $blsy = EMR_BL_BLSY::query()->where('BLBH', '=', $data[0]['BLBH'])->get()->toArray();
        if (empty($blsy) && strpos($data[0]['BLMC'], '主任') === false) {
            $errorNotice[] = [
                'basis' => json_encode([['入院时间【' . $newData["AAB01"] . '】'], ['【' . $data[0]['BLMC'] . ',无主任/副主任医师查房']], 256),
                'rule_id' => $ruleid,
                'code' => 'cbzd',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        } else {
            $isHas = true;
        }

        if ($isHas == true) {
            return [];
        }

        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param array $caseRule
     * @return array
     * '现病史有【高血脂病史】，初步诊断中没有书写'
     */
    public static function rule226($data = [], $caseRule = [])
    {
        $ruleid = 226;
        if (empty($caseRule[$ruleid]['status']) || empty($data)) {
            return [];
        }
        $errorNotice = [];
        $xbs = $data['JWS'];
        $cbzd = $data["CBZD"];
        if(empty($cbzd)){
            return [];
        }
        $cbzd = implode(',', $cbzd);
        if ($xbs && strpos($xbs, '高血脂') !== false && strpos($cbzd, '高血脂') === false) {
            $errorNotice[] = [
                'basis' => json_encode([['既往史有【高血脂病史】，初步诊断中没有书写']], 256),
                'rule_id' => $ruleid,
                'code' => 'cbzd',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }
        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 体格检查中含“强迫体位”，不能是“查体合作”
     */
    public static function rule223($newData = [], $caseRule = [])
    {
        $ruleid = 223;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $errorNotice = [];
        $xbs = $newData["XBS"] ?? '';
        $jws = $newData["JWS"] ?? '';
        $cbzd = $newData["CBZD"] ?? '';
        if (empty($cbzd)) {
            return [];
        }
        $cbzd = implode(',', $cbzd);
        if (
            strpos($xbs, '冠状动脉粥样硬化性心脏病') !== false || strpos($xbs, '冠状动脉粥样硬化型心脏病') !== false
        ) {
            if (
                $cbzd &&
                strpos($cbzd, '冠状动脉粥样硬化性心脏病') === false &&
                strpos($cbzd, '冠状动脉粥样硬化型心脏病') === false &&
                strpos($cbzd, '冠状动脉性心脏病') === false &&
                strpos($cbzd, '冠状动脉型心脏病') === false &&
                strpos($cbzd, '冠状动脉硬化型心脏病') === false &&
                strpos($cbzd, '冠状动脉硬化性心脏病') === false
            ) {
                $errorNotice[] = [
                    'basis' => json_encode([['现病史含[冠状动脉粥样硬化性心脏病/冠状动脉粥样硬化型心脏病]，初步诊断中未提及']], 256),
                    'rule_id' => $ruleid,
                    'code' => '',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
            }
        }
        if (strpos($jws, '冠状动脉粥样硬化性心脏病') !== false || strpos($jws, '冠状动脉粥样硬化型心脏病') !== false) {
            if (
                $cbzd &&
                strpos($cbzd, '冠状动脉粥样硬化性心脏病') === false &&
                strpos($cbzd, '冠状动脉粥样硬化型心脏病') === false &&
                strpos($cbzd, '冠状动脉性心脏病') === false &&
                strpos($cbzd, '冠状动脉型心脏病') === false &&
                strpos($cbzd, '冠状动脉硬化型心脏病') === false &&
                strpos($cbzd, '冠状动脉硬化性心脏病') === false
            ) {
                $errorNotice[] = [
                    'basis' => json_encode([['既往史含[冠状动脉粥样硬化性心脏病/冠状动脉粥样硬化型心脏病]，初步诊断中未提及']], 256),
                    'rule_id' => $ruleid,
                    'code' => '',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
            }
        }
        return $errorNotice;
    }


    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 现病史“高血压”、“糖尿病”、“冠心病”
     * 既往史[否认糖尿病].或[否认冠心病] 或[否认高血压]或[否认“冠心病、高血压”]或[否认“高血压病、糖尿病、冠状动脉粥样硬化性心脏病”]
     */
    public static function rule222($newData = [], $caseRule = [])
    {
        $ruleid = 222;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $errorNotice = [];
        $xbs = $newData["XBS"] ?? '';
        $jws = $newData["JWS"] ?? '';
        $bing = [
            [
                'xbs' => '高血压',
                'jws' => ['否认高血压', '否认"冠心病、高血压"', '否认"高血压病、糖尿病、冠状动脉粥样硬化性心脏病"']
            ]
        ];

        foreach ($bing as $item) {
            if (strpos($xbs, $item['xbs']) === false) {
                continue;
            }

            preg_match_all("/门诊以(.*?)院/", $xbs, $xbsRes);
            if (!empty($xbsRes[1])) {
                continue;
            }
            if ($xbsRes && $xbsRes[1] && strpos($xbsRes[1][0], $item['xbs']) !== false) {
                continue;
            }
            foreach ($item['jws'] as $j) {
                if (strpos($jws, $j) !== false) {
                    $errorNotice[] = [
                        'basis' => json_encode([['现病史【' . $item['xbs'] . '】'], ['既往史【' . $j . '】']], 256),
                        'rule_id' => $ruleid,
                        'code' => '',
                        'error_field' => $caseRule[$ruleid]['title']
                    ];
                }
            }
        }

        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 现病史“高血压”、“糖尿病”、“冠心病”
     * 既往史[否认糖尿病].或[否认冠心病] 或[否认高血压]或[否认“冠心病、高血压”]或[否认“高血压病、糖尿病、冠状动脉粥样硬化性心脏病”]
     */
    public static function rule224($newData = [], $caseRule = [])
    {
        //        $ruleid = 224;
//        if (empty($caseRule[$ruleid]['status'])) {
//            return [];
//        }
//        $errorNotice = [];
//        $xbs = $newData["XBS"] ?? '';
//        $jws = $newData["JWS"] ?? '';
//        $bing = [
//            [
//                'xbs' => '冠心病',
//                'jws' => ['否认冠心病']
//            ]
//        ];
//
//        foreach ($bing as $item) {
//            if (strpos($xbs, $item['xbs']) === false) {
//                continue;
//            }
//
//            preg_match_all("/门诊以(.*?)院/", $xbs, $xbsRes);
//            if (!empty($xbsRes[1])) {
//                continue;
//            }
//            if ($xbsRes && $xbsRes[1] && strpos($xbsRes[1][0], $item['xbs']) !== false) {
//                continue;
//            }
//            foreach ($item['jws'] as $j) {
//                if (strpos($jws, $j) !== false) {
//                    $errorNotice[] = [
//                        'basis' => json_encode([['现病史【' . $item['xbs'] . '】'], ['既往史【' . $j . '】']], 256),
//                        'rule_id' => $ruleid,
//                        'code' => '',
//                        'error_field' => $caseRule[$ruleid]['title']
//                    ];
//                }
//            }
//        }

        // 使用新规则
        $ruleid = 224;
        if (empty($caseRule[$ruleid]['status']) || empty($newData)) {
            return [];
        }
        $errorNotice = [];
        $xbs = $newData['JWS'];
        $cbzd = $newData["CBZD"];
        if (empty($cbzd)) {
            return [];
        }
        $cbzd = implode(',', $cbzd);

        $containsDisease = strpos($xbs, '冠心病') !== false;//判断既往史中是否包含冠心病
        $containsNotDisease = strpos($xbs, '否认冠心病') !== false; //判断既往史中是否包含否认冠心病
        //获取冠心病的相关病史关键字
        $ruleMap1056 = RuleWordMap::query()->where('id', '=', 1056)->value('keyword');
        $ruleMap1056 = !empty($ruleMap1056) ? explode(',', $ruleMap1056) : ["冠心病", "冠状动脉粥样硬化性心脏病"];
        if ($containsDisease && !$containsNotDisease) {
            $flag = 0;
            foreach ($ruleMap1056 as $word) {
                if (strpos($cbzd, $word) !== false) {
                    $flag = 1;
                    break;
                }
            }
            if (empty($flag)) {
                $errorNotice[] = [
                    'basis' => json_encode([['既往史有【冠心病病史】，初步诊断中没有书写']], 256),
                    'rule_id' => $ruleid,
                    'code' => 'cbzd',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
            }
        }
        //        if ($xbs && strpos($xbs, '冠心病') !== false && strpos($cbzd, '冠心病') === false) {
//            $errorNotice[] = [
//                'basis' => json_encode([['既往史有【冠心病病史】，初步诊断中没有书写']], 256),
//                'rule_id' => $ruleid,
//                'code' => 'cbzd',
//                'error_field' => $caseRule[$ruleid]['title']
//            ];
//        }
        return $errorNotice;

    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 现病史“高血压”、“糖尿病”、“冠心病”
     * 既往史[否认糖尿病].或[否认冠心病] 或[否认高血压]或[否认“冠心病、高血压”]或[否认“高血压病、糖尿病、冠状动脉粥样硬化性心脏病”]
     */
    public static function rule231($newData = [], $caseRule = [])
    {
        $ruleid = 231;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $errorNotice = [];
        $xbs = $newData["XBS"] ?? '';
        $jws = $newData["JWS"] ?? '';
        $bing = [
            [
                'xbs' => '糖尿病',
                'jws' => ['否认糖尿病']
            ],
        ];

        foreach ($bing as $item) {
            if (strpos($xbs, $item['xbs']) === false) {
                continue;
            }

            preg_match_all("/门诊以(.*?)院/", $xbs, $xbsRes);
            if (!empty($xbsRes[1])) {
                continue;
            }
            if ($xbsRes && $xbsRes[1] && strpos($xbsRes[1][0], $item['xbs']) !== false) {
                continue;
            }
            foreach ($item['jws'] as $j) {
                if (strpos($jws, $j) !== false) {
                    $errorNotice[] = [
                        'basis' => json_encode([['现病史【' . $item['xbs'] . '】'], ['既往史【' . $j . '】']], 256),
                        'rule_id' => $ruleid,
                        'code' => '',
                        'error_field' => $caseRule[$ruleid]['title']
                    ];
                }
            }
        }

        return $errorNotice;
    }

    /**
     * @param string $content
     * @param array $caseRule
     * @return array
     *
     * 整体{}超过2个
     */
    public static function rule232($content = "", $caseRule = [])
    {
        $ruleid = 232;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $num1 = substr_count($content, '{');
        $num2 = substr_count($content, '}');
        if ($num2 + $num1 > 2) {
            $errorNotice[] = [
                'basis' => json_encode([['有【' . ($num2 + $num1) . '】个元素']], 256),
                'rule_id' => $ruleid,
                'code' => '',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }
        return $errorNotice;
    }


    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 整体{}超过2个
     */
    public static function rule242($newData = [], $caseRule = [])
    {
        $ruleid = 242;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $tgjcData = $newData["TGJC"] ?: '';
        $errorNotice = [];
        preg_match_all("/脉搏:(\d+)次\/分/", $tgjcData, $maibo);
        preg_match_all("/心率:(\d+)次\/分/", $tgjcData, $xinlv);
        if ($maibo[1] && $xinlv[1]) {
            // 收缩压
            if ($maibo[1][0] != $xinlv[1][0]) {
                $errorNotice[] = [
                    'basis' => json_encode([["入院记录中心率和脉搏书写不一致】"]], 256),
                    'rule_id' => $ruleid,
                    'code' => '',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
            }
        }

        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     */
    public static function rule243($newData = [], $caseRule = [])
    {
        $ruleid = 243;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $yjjhysData = $newData["YJJHYS"] ?: '';
        $yjjhysData = str_replace('/', '-', $yjjhysData);

        $errorNotice = [];
        preg_match_all("/周期(\d+-\d+)天/", $yjjhysData, $yjzq);
        $zqNew = '';
        if (empty($yjzq[1])) {
            preg_match_all("/周期(\d+)天/", $yjjhysData, $yjzq);
            if (!empty($yjzq[1])) {
                $zqNew = $yjzq[1][0];
            }
        } else {
            $zhouqi = explode('-', $yjzq[1][0]);
            $zqNew = $zhouqi[0];
        }
        preg_match_all("/经期(\d+-\d+)天/", $yjjhysData, $jq);
        $jqNew = '';
        if (empty($jq[1])) {
            preg_match_all("/经期(\d+)天/", $yjjhysData, $jq);
            if (!empty($jq[1])) {
                $jqNew = $jq[1][0];
            }
        } else {
            $jingqi = explode('-', $jq[1][0]);
            $jqNew = $jingqi[1];
        }

        if (!empty($zqNew) && !empty($jqNew)) {
            if ($jqNew >= $zqNew) {
                $errorNotice[] = [
                    'basis' => json_encode([["周期【" . $yjzq[1] . "】经期【" . $jq[1] . "】，请核实"]], 256),
                    'rule_id' => $ruleid,
                    'code' => '',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
            }
        }

        //        if ($yjzq[1]) {
//            $zhouqi = explode('-', $yjzq[1][0]);
//            if ($zhouqi[0] > 10 && $zhouqi[0] < 21 && $zhouqi[1] < 21) {
//                $errorNotice[] = [
//                    'basis' => json_encode([["周期【{$yjzq[1][0]}】<21，请核实"]], 256),
//                    'rule_id' => $ruleid,
//                    'code' => '',
//                    'error_field' => $caseRule[$ruleid]['title']
//                ];
//            } elseif ($zhouqi[1] < 15 && $zhouqi[0] > 8 && $zhouqi[1] > 8) {
//                $errorNotice[] = [
//                    'basis' => json_encode([["经期【{$yjzq[1][0]}】>8天，请核实"]], 256),
//                    'rule_id' => $ruleid,
//                    'code' => '',
//                    'error_field' => $caseRule[$ruleid]['title']
//                ];
//            }
//        }

        //        if ($jq[1]) {
//            $jingqi = explode('-', $jq[1][0]);
//            if ($jingqi[0] > 10 && $jingqi[0] < 21 && $jingqi[1] < 21) {
//                $errorNotice[] = [
//                    'basis' => json_encode([["周期【{$yjzq[1][0]}】<21，请核实"]], 256),
//                    'rule_id' => $ruleid,
//                    'code' => '',
//                    'error_field' => $caseRule[$ruleid]['title']
//                ];
//            } elseif ($jingqi[1] < 15 && $jingqi[0] >8 && $jingqi[1] > 8) {
//                $errorNotice[] = [
//                    'basis' => json_encode([["经期【{$yjzq[1][0]}】>8天，请核实"]], 256),
//                    'rule_id' => $ruleid,
//                    'code' => '',
//                    'error_field' => $caseRule[$ruleid]['title']
//                ];
//            }
//        }

        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 整体{}超过2个
     */
    public static function rule236($newData = [], $caseRule = [])
    {
        $ruleid = 236;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $tgjcData = $newData["TGJC"] ?: '';
        $CBZD = $newData["CBZD"] ?: '';
        if (empty($CBZD)) {
            return [];
        }
        $CBZD = implode(',', $CBZD);
        $errorNotice = [];
        preg_match_all("/[血压BP:]*(\d+\/\d+)mmHg/", $tgjcData, $bloodPressure);
        if ($bloodPressure[1]) {
            $bloodPressureRes = explode('/', $bloodPressure[1][0]);
            // 收缩压
            if ($bloodPressureRes[0] > 140 || $bloodPressureRes[1] > 90) {
                if (strpos($CBZD, '高血压') === false) {
                    $errorNotice[] = [
                        'basis' => json_encode([["体格检查【血压：" . $bloodPressure[1][0] . "】"]], 256),
                        'rule_id' => $ruleid,
                        'code' => '',
                        'error_field' => $caseRule[$ruleid]['title']
                    ];
                }
            }
        }

        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 入院记录书写不规范
     */
    public static function rule233($newData = [], $caseRule = [])
    {
        $ruleid = 233;
        if (empty($caseRule[$ruleid]['status']) || empty($newData)) {
            return [];
        }
        $age = $newData["NL"] ?? "";
        $zhusu = $newData["ZHS"] ?? "";
        $xbs = $newData["XBS"] ?? "";
        $jws = $newData["JWS"] ?? "";
        $grs = $newData["GRS"] ?? "";
        $jzs = $newData["JZS"] ?? "";
        $tgjc = $newData["TGJC"] ?? "";
        $cbzd = $newData['CBZD'] ?? "";

        $basis = [];
        if (empty(trim($age))) {
            $basis[] = '年龄（未填写）';
        }
        if (empty(trim($zhusu))) {
            $basis[] = '主诉（未填写）';
        }
        if (empty(trim($xbs))) {
            $basis[] = '现病史（未填写）';
        }
        if (empty(trim($jws))) {
            $basis[] = '既往史（未填写）';
        }
        if (empty(trim($grs))) {
            $basis[] = '个人史（未填写）';
        }
        if (empty(trim($jzs))) {
            $basis[] = '家族史（未填写）';
        }
        if (empty(trim($tgjc))) {
            $basis[] = '体格检查（未填写）';
        }
        if (empty($cbzd) || $cbzd == '[]') {
            $basis[] = '初步诊断（未填写）';
        }
        $errorNotice = [];
        if ($basis) {
            $errorNotice[] = [
                'basis' => json_encode([$basis], 256),
                'rule_id' => $ruleid,
                'code' => '',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 体格检查中含“强迫体位”，不能是“查体合作”
     */
    public static function rule221($newData = [], $caseRule = [])
    {
        $ruleid = 221;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $errorNotice = [];
        $tgjc = $newData["TGJC"] ?? '';
        if (strpos($tgjc, '强迫体位') === false) {
            return [];
        }
        if (strpos($tgjc, '查体合作') !== false) {
            $errorNotice[] = [
                'basis' => json_encode([['入院记录中【强迫体位】【查体合作】不能同时存在']], 256),
                'BLBH' => $newData['BLBH'],
                'rule_id' => $ruleid,
                'code' => '',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }
        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 入院记录初步诊断含“偏瘫”，入院记录的体格检查含“活动自如”
     */
    public static function rule220($newData = [], $caseRule = [])
    {
        $ruleid = 220;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $errorNotice = [];
        $cbzd = trim($newData["CBZD"]);
        $tgjc = $newData["TGJC"];
        if (strpos($cbzd, '偏瘫') === false) {
            return [];
        }
        if (strpos($tgjc, '活动自如') !== false) {
            $errorNotice[] = [
                'basis' => json_encode([['初步诊断中有【偏瘫】，体格检查中有【活动自如】']], 256),
                'rule_id' => $ruleid,
                'code' => '',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }
        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 年龄<2岁，入院记录|体格检查 头围在20-55cm之间
     */
    public static function rule219($newData = [], $caseRule = [])
    {
        $ruleid = 219;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $errorNotice = [];
        $age = trim($newData["NL"]);
        $oldAge = $age;
        $tgjc = $newData["TGJC"];
        // 如果年龄是天为单位，那么转换成年
        if (strpos($age, '天') !== false || strpos($age, '分钟') !== false) {
            $age = 1;
        }
        if (intval($age) > 2) {
            return $errorNotice;
        }
        preg_match_all("/头围:([\d\.]+)CM/", $tgjc, $res);
        if (empty($res[1])) {
            return [];
        }
        $touwei = !empty($res) && !empty($res[1]) ? $res[1][0] : 0;
        if ($touwei < 20 || $touwei > 55) {
            $errorNotice[] = [
                'basis' => json_encode([['年龄【≤2岁】'], ['头围:' . $touwei . 'CM（与头围标准不符）']], 256),
                'rule_id' => $ruleid,
                'code' => '',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }
        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 患者为 泌尿系感染，入院记录中典型症状记录缺失
     */
    public static function rule155($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 154;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $errorNotice = [];
        $zhusu = $newData["ZHS"];
        $flag = false;
        $name = '';
        if (
            strpos($zhusu, '尿路刺激征') ||
            strpos($zhusu, '尿频') ||
            strpos($zhusu, '尿急') ||
            strpos($zhusu, '尿痛')
        ) {

        }
        if ($flag) {
            $errorNotice[] = [
                'basis' => json_encode([['主诉能含有诊断名称:' . $name]], 256),
                'rule_id' => $ruleid,
                'code' => '',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }
        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 原则上，主诉不能含有诊断名称（“体检”发现的除外）
     */
    public static function rule154($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 154;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $errorNotice = [];
        $zhusu = $newData["ZHS"];
        $flag = false;
        $name = '';
        foreach ($newData['CBZD'] as $v) {
            // 体检排除
            if (strpos($v, '体检')) {
                continue;
            }
            // 判断主诉是否包含初步诊断
            if (strpos($zhusu, $v) !== false) {
                $flag = true;
                $name = $v;
                break;
            }
        }
        if ($flag) {
            $errorNotice[] = [
                'basis' => json_encode([['主诉能含有诊断名称:' . $name]], 256),
                'rule_id' => $ruleid,
                'code' => '',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }
        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 儿科、临床心理科、神经外昏迷的患者（患者年龄） 或 “昏迷” 或 “意识障碍” 或 "精神障碍“，病史陈述者不能是本人（从症状取）
     */
    public static function rule152($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 152;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $sjk = [215, 287, 4190, 123, 220, 118, 2062];
        if (!in_array($bl01['BRKS'], $sjk)) {
            return [];
        }

        $zkjcStr = $bl01['HJNR'];
        $errorNotice = [];
        $bscsz = $newData["BSCSZ"];

        if (
            strpos($zkjcStr, '昏迷') !== false ||
            strpos($zkjcStr, '精神障碍') !== false ||
            strpos($zkjcStr, '意识障碍') !== false
        ) {
            if ($bscsz == '患者本人') {
                $errorNotice[] = [
                    'basis' => json_encode([['病史陈述者不能是本人']], 256),
                    'rule_id' => $ruleid,
                    'code' => '',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
            }
        }
        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 在患者入院科室为神经内/外科时，入院记录-专科检查是否缺少 肌力、肌张力 特殊专科内容
     */
    public static function rule149($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 149;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $sjk = [2062, 2022, 2021, 1208, 118];
        if (!in_array($bl01['BRKS'], $sjk)) {
            return [];
        }

        if (empty($newData['zkjc'])) {
            return [];
        }
        $zkjcStr = $newData['zkjc'];
        $errorNotice = [];

        if (
            strpos($zkjcStr, '肌力') === false &&
            strpos($zkjcStr, '肌张力') === false
        ) {
            $errorNotice[] = [
                'basis' => json_encode([['在患者入院科室为神经内/外科时，入院记录-专科检查是否缺少 肌力、肌张力 特殊专科内容']], 256),
                'rule_id' => $ruleid,
                'code' => '',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }
        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 初步诊断为胎膜早破时，搜 “阴道流液”
     */
    public static function rule145($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 145;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        if ($newData['CBZD'][0] != '胎膜早破') {
            return [];
        }

        $checkStr = $newData["ZHS"];
        if (empty($checkStr)) {
            return [];
        }
        $errorNotice = [];

        if (strpos($checkStr, '阴道流液') === false) {
            $errorNotice[] = [
                'basis' => json_encode([['在患者入院记录-初步诊断为胎膜早破时，需要记录“ph”或“胎膜破”']], 256),
                'rule_id' => $ruleid,
                'code' => '',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }
        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 在患者入院记录-初步诊断为胎膜早破时，需要记录“ph”或“胎膜破”
     */
    public static function rule144($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 144;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        if ($newData['CBZD'][0] != '胎膜早破') {
            return [];
        }

        if (empty($newData['zkjc'])) {
            return [];
        }
        $zkjcStr = $newData['zkjc'];
        $errorNotice = [];

        if (
            strpos($zkjcStr, 'ph') === false &&
            strpos($zkjcStr, '胎膜破') === false
        ) {
            $errorNotice[] = [
                'basis' => json_encode([['在患者入院记录-初步诊断为胎膜早破时，需要记录“ph”或“胎膜破”']], 256),
                'rule_id' => $ruleid,
                'code' => '',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }
        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 先兆临产患者，现病史中需描写宫缩、宫颈情况和阴道见红
     * 初步诊断【先兆临产】
     * 入院记录文本中含    “宫缩”、”宫颈 、宫口 、或“见红”
     */
    public static function rule143($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 143;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        if ($newData['CBZD'][0] != '先兆临产') {
            return [];
        }

        if (empty($newData['zkjc'])) {
            return [];
        }
        $zkjcStr = $newData['zkjc'];
        $errorNotice = [];

        if (
            strpos($zkjcStr, '宫缩') === false &&
            strpos($zkjcStr, '宫颈') === false &&
            strpos($zkjcStr, '宫口') === false &&
            strpos($zkjcStr, '见红') === false
        ) {
            $errorNotice[] = [
                'basis' => json_encode([['初步诊断为先兆临产相关的诊断时，入院记录中需要记录“”宫缩“”、”宫颈 、宫口 、或“见红”']], 256),
                'rule_id' => $ruleid,
                'code' => '',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }
        return $errorNotice;
    }

    public static function rule140($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 140;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        if ($bl01['BRKS'] != 213) {
            return [];
        }
        if (empty($newData['zkjc'])) {
            return [];
        }
        $zkjcStr = $newData['zkjc'];
        $errorNotice = [];

        $basis = '专科检查：';
        $flag = false;
        if (strpos($zkjcStr, '宫高') === false) {
            $flag = true;
            $basis .= '宫高（无）';
        } else {
            $basis .= '宫高（有）';
        }
        if (strpos($zkjcStr, '腹围') === false) {
            $flag = true;
            $basis .= '腹围（无）';
        } else {
            $basis .= '腹围（有）';
        }
        if (strpos($zkjcStr, '宫口') === false) {
            $flag = true;
            $basis .= '宫口（无）';
        } else {
            $basis .= '宫口（有）';
        }
        if (strpos($zkjcStr, '胎头') === false) {
            $flag = true;
            $basis .= '胎头（无）';
        } else {
            $basis .= '胎头（有）';
        }
        if ($flag) {
            $errorNotice[] = [
                'basis' => json_encode([[$basis]], 256),
                'rule_id' => $ruleid,
                'code' => '',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }


        return $errorNotice;
    }

    /**
     * @param array $newData 入院记录信息
     * @param string $blxgId 病例编号
     * @param array $caseRule 规则
     * 入院第一诊断为结直肠恶性肿瘤 ，专科检查中含关键字“指诊”
     * （入院记录中把 专科检查从辅助检查中拆出来1）
     * （1）初步诊断：表：EMR_BL_BL01中【BLLB =292 且 blzt≠9】   关联BLXG文本中 取  初步诊断（第一个）（初步诊断做格式化拆出来2）
     * （2）专科检查中含关键字“指诊”
     */
    public static function rule139($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 139;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }

        // 专科检查
        if (trim($newData['CBZD'][0]) != '结直肠恶性肿瘤') {
            return [];
        }
        if (empty($newData['zkjc'])) {
            return [];
        }
        $zkjcStr = $newData['zkjc'];
        $errorNotice = [];
        if (strpos($zkjcStr, '指诊') === false) {
            $basis = [];
            $basis[] = '初步诊断【结直肠恶性肿瘤】';
            $basis[] = '专科检查【指诊（无）】';
            $errorNotice[] = [
                'basis' => json_encode([$basis], 256),
                'rule_id' => $ruleid,
                'code' => '',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }

        return $errorNotice;
    }


    /**
     * @param array $newData
     * @param string $blxgId
     * @return array
     * 主诉相关规则
     */
    public static function rule55($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 55;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $errorNotice = [];
        //            3、规则：一般不超过 20 个字，＞20个字给出提醒。
        $zdData = $newData["ZHS"];
        $zdDataLenth = mb_strlen($zdData);
        if ($zdDataLenth > $caseRule[$ruleid]['number1']) {
            $errorNotice[] = [
                'basis' => json_encode([['主诉不能超过20个字']], 250),
                'rule_id' => $ruleid,
                'code' => '',
                'error_field' => '主诉'
            ];
        }
        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 有症状（或体征）+时间，缺一不可
     */
    public static function rule76($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 76;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $zdData = $newData["ZHS"];
        // 4、规则：症状≤3个，按时间先后顺序列出，并记录每个症状的持续时间
        $response = ApiService::getDiseaseRes($zdData);
        $symptom = ''; // 主诉症状
        if (!$response) {
            return [];
        } else {
            $symptom = $response->symptom ?? '';

            if ($symptom) {

                foreach ($symptom as $s) {
                    $basis = [];
                    if (empty($s->entity)) {
                        $basis[] = ['缺少症状或体征'];
                    }
                    if (empty($s->timeAttribute)) {
                        $basis[] = ['缺少时间'];
                    }

                    $errorNotice[] = [
                        'basis' => json_encode($basis, 250),
                        'rule_id' => $ruleid,
                        'code' => '',
                        'error_field' => '主诉'
                    ];
                }
                //
//                if (count($symptom) > $caseRule[56]['number1']) {
//                    $errorNotice[] = [
//                        'basis' => json_encode([['症状≤3个']], 250),
//                        'BLBH' => $bl01['BLBH'],
//                        'rule_id' => 56,
//                        'code' => config("confAdmin.keyMap")["ZHS"],
//                        'error_field' => '主诉'
//                    ];
//                }
//                // 记录所有的持续时间，用于验证时间排序规则
//                $zsMaxTime = [];
//                foreach ($symptom as $s) {
//                    if (!isset($s->timeAttribute) || empty($s->timeAttribute)) {
//                        $errorNotice[] = [
//                            'BLBH' => $bl01['BLBH'],
//                            'rule_id' => 76,
//                            'notice' => $caseRule[76]['notice'],
//                            'code' => config("confAdmin.keyMap")["ZHS"],
//                            'error_field' => '主诉'
//                        ];
//                    } else {
//                        $zsMaxTime[] = intval($s->timeAttribute);
//                    }
//                }
//                if ($zsMaxTime) {
//                    $flag = 0;
//                    for ($i = 0; $i < count($zsMaxTime) - 1; $i++) {
//                        if ($zsMaxTime[$i + 1] > $zsMaxTime[$i]) {
//                            $flag++; // 如果出现下一个数字比前一个数字大则更新标识
//                        }
//                    }
//                    if ($flag) {
//                        $errorNotice[] = [
//                            'basis' => json_encode([['按时间先后顺序列出']], 250),
//                            'BLBH' => $bl01['BLBH'],
//                            'rule_id' => 79,
//                            'code' => config("confAdmin.keyMap")["ZHS"],
//                            'error_field' => '主诉'
//                        ];
//                    }
//                }
            }
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
    public static function rule5($newData = [], $bl01 = [], $symptom = [])
    {
        // 5、规则：通过主诉的症状 和（或） 体征 推断出第一诊断
        self::rule5($newData, $bl01, $symptom);
        $errorNotice = [];
        if (empty($symptom)) {
            $errorNotice[] = [
                'BLBH' => $bl01['BLBH'],
                'rule_id' => 5,
                'notice' => '主诉的症状信息获取失败',
                'code' => config("confAdmin.keyMap")["ZHS"],
                'error_field' => '主诉'
            ];
            return $errorNotice;
        }
        $entity = array_column($symptom, 'entity');
        $entity = implode(',', $entity);

        $cbzdData = $newData['CBZD'];

        $zsDataResponse = ApiService::cdssAdvisorySubmit($entity);
        if ($zsDataResponse) {
            $disease = array_column($zsDataResponse, 'name');
            if ($disease) {
                $cbzdFirst = array_shift($cbzdData);
                if (!in_array($cbzdFirst, $disease)) {
                    $errorNotice[] = [
                        'BLBH' => $bl01['BLBH'],
                        'rule_id' => 5,
                        'notice' => $cbzdFirst . '不在主诉症状的推断结果中',
                        'code' => config("confAdmin.keyMap")["ZHS"],
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
    public
    static function rule6(
        $newData = [],
        $blxgId = '',
        $caseRule
    ) {

        $errorNotice = [];
        $zdData = $newData["ZHS"];
        $zsResponse = ApiService::getDiseaseRes($zdData);
        $zsSymptom = ''; // 主诉症状
        if ($zsResponse) {
            $zsSymptom = $zsResponse->symptom ?? '';
        }

        $xbsData = $newData["XBS"];
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
                                'code' => config("confAdmin.keyMap")["XBS"],
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
                            'code' => config("confAdmin.keyMap")["XBS"],
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
    public
    static function rule7(
        $newData = [],
        $blxgId = '',
        $caseRule
    ) {

        $errorNotice = [];
        $xbsData = $newData["XBS"];
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
                            'code' => config("confAdmin.keyMap")["XBS"],
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
                            'code' => config("confAdmin.keyMap")["XBS"],
                            'error_field' => $t
                        ];

                    }
                }
            }

        }


        $jwsData = $newData["JWS"];
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
                            'code' => config("confAdmin.keyMap")["XBS"],
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
                            'code' => config("confAdmin.keyMap")["XBS"],
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
    public
    static function rule8(
        $newData = [],
        $blxgId = '',
        $caseRule
    ) {
        $errorNotice = [];
        $xbData = $newData["XB"];
        $yjjhysData = $newData["YJJHYS"] ?? '';
        $hysData = $newData["HYS"] ?? '';
        if (($xbData == '女' && empty($yjjhysData)) || ($xbData == '男' && !empty($yjjhysData))) {
            $errorNotice[] = [
                'BLBH' => $blxgId,
                'rule_id' => 62,
                'notice' => $caseRule[62]['notice'],
                'code' => config("confAdmin.keyMap")["YJJHYS"]
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
                    'code' => config("confAdmin.keyMap")["YJJHYS"],
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
                    'code' => config("confAdmin.keyMap")["YJJHYS"],
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
    public
    static function rule9(
        $newData = [],
        $blxgId = '',
        $caseRule
    ) {
        $errorNotice = [];
        $xbsData = $newData["XBS"];
        $jwsData = $newData["JWS"] ?? '';
        $cbzdData = $newData['CBZD'] ? implode(',', $newData['CBZD']) : [];

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
                            'code' => config("confAdmin.keyMap")["XBS"],
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
                            'code' => config("confAdmin.keyMap")["XBS"],
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
                                    'code' => config("confAdmin.keyMap")["XBS"],
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
    public
    static function rule10(
        $newData = [],
        $blxgId = '',
        $caseRule = []
    ) {
        $errorNotice = [];
        $jwsData = $newData["JWS"] ?? '';
        $xbsDataResponse = ApiService::getDiseaseRes($jwsData);

        if (!$xbsDataResponse) {
            return [];
        } else {

            $medicine = $response->medicine ?? '';
            if (!$medicine) {
                return [];
            } else {
                $cbzdData = $newData['CBZD'];
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
                            'code' => config("confAdmin.keyMap")["JWS"],
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
                            'code' => config("confAdmin.keyMap")["JWS"],
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
    public
    static function rule13(
        $newData = [],
        $blxgId = '',
        $caseRule = []
    ) {
        $errorNotice = [];
        $cbzdData = $newData['CBZD'];
        if ($cbzdData) {
            foreach ($cbzdData as $v) {
                // 判断诊断名称是否存在标准中
                $res = DiseaseDiagnosisCode::getInfoByName($v);
                if (!$res) {
                    $notice = [
                        'BLBH' => $blxgId,
                        'rule_id' => 72,
                        'notice' => $v . '：' . $caseRule[72]['notice'],
                        'code' => 'CBZD',
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
    public
    static function rule14(
        $newData = [],
        $blxgId = '',
        $caseRule
    ) {
        $errorNotice = [];
        $cbzdData = $newData["TGJC"] ?: '';
        $tgjcData = $cbzdData;

        $jwsData = $newData["JWS"] ?? '';
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
        $diagnoseList = $newData['CBZD'] ?: [];

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
                        'code' => config("confAdmin.keyMap")["TGJC"],
                        'error_field' => $tiwen[1][0]
                    ];
                }
            }
        }


        //        脉搏检测
        preg_match_all("/脉搏:(\d+)次\/分/", $tgjcData, $maibo);
        preg_match_all("/心率:(\d+)次\/分/", $tgjcData, $maibo);
        if ($maibo[1] && $maibo[1][0] > $caseRule[66]['number2']) {
            $errorNotice[] = [
                'BLBH' => $blxgId,
                'rule_id' => 66,
                'notice' => $caseRule[66]['notice'],
                'code' => config("confAdmin.keyMap")["TGJC"],
                'error_field' => $maibo[1][0] ?: 0
            ];
        }
        if ($maibo[1] && $maibo[1][0] < $caseRule[67]['number1']) {
            $errorNotice[] = [
                'BLBH' => $blxgId,
                'rule_id' => 67,
                'notice' => $caseRule[67]['notice'],
                'code' => config("confAdmin.keyMap")["TGJC"],
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
                'code' => config("confAdmin.keyMap")["TGJC"],
                'error_field' => $maibo[1][0] ?: 0
            ];
        }
        if ($maibo[1] && $maibo[1][0] < $caseRule[69]['number1']) {
            $errorNotice[] = [
                'BLBH' => $blxgId,
                'rule_id' => 69,
                'notice' => $caseRule[69]['notice'],
                'code' => config("confAdmin.keyMap")["TGJC"],
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
                    'code' => config("confAdmin.keyMap")["TGJC"],
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
                            'code' => config("confAdmin.keyMap")["TGJC"],
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
                    'code' => config("confAdmin.keyMap")["TGJC"],
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
                    'code' => config("confAdmin.keyMap")["TGJC"],
                    'error_field' => $bloodPressure[1][0] ?: 0
                ];
            }

            if ($caseRule[90]['number2'] <= $bloodPressure[0] || $caseRule[91]['number2'] <= $bloodPressure[1]) {
                $errorNotice[] = [
                    'BLBH' => $blxgId,
                    'rule_id' => 90,
                    'notice' => $caseRule[90]['notice'],
                    'code' => config("confAdmin.keyMap")["TGJC"],
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
    public
    static function rule16(
        $newData = [],
        $blxgId = '',
        $caseRule = []
    ) {
        $errorNotice = [];
        $fzjcData = $fzjcDataOld = $newData["FZJC"];
        $cbzdData = $newData["CBZD"];
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
                    'code' => config("confAdmin.keyMap")["FZJC"],
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
                    'code' => config("confAdmin.keyMap")["FZJC"],
                    'error_field' => '辅助检查'
                ];
            }
        }

        return $errorNotice;
    }


    /**
     * @param array $item
     * @param array $caseRule
     * @return array
     * 出院记录书写不规范
     */
    public static function rule234($item = [], $caseRule = [])
    {
        if (empty($item)) {
            return [];
        }

        $ruleid = 234;
        $basis = [];
        if (empty(trim($item['XM']))) {
            $basis[] = '姓名（未填写）';
        }
        if (empty(trim($item['NL_STR']))) {
            $basis[] = '年龄（未填写）';
        }
        if (empty(trim($item['ZYTS']))) {
            $basis[] = '住院天数（未填写）';
        }
        if (empty(trim($item['RYQK']))) {
            $basis[] = '入院情况（未填写）';
        }
        if (empty(trim($item['CBZD']))) {
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

        $errorNotice = [];
        if ($basis) {
            $errorNotice[] = [
                'basis' => json_encode([$basis], 256),
                'rule_id' => $ruleid,
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
     * 首次病程缺少医师签名
     */
    private static function rule235($item = [], $caseRule = [])
    {
        $ruleid = 235;
        $errorNotice = [];
        // 获取医生信息
        $staff = Staff::query()->get(['name', 'ygjb_text', 'code'])->toArray();
        foreach ($staff as $v) {
            if (strpos($item['HJNR'], $v['name']) === false) {
                continue;
            }
            $blsy = EMR_BL_BLSY::query()->where("BLBH", "=", $item['BLBH'])->get()->toArray();
            if (empty($blsy)) {
                $errorNotice[] = [
                    'basis' => json_encode(["【" . $v['name'] . "】无签名"], 256),
                    'rule_id' => $ruleid,
                    'code' => '',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
                break;
            }
        }

        return $errorNotice;
    }
}

