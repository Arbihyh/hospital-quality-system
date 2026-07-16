<?php

namespace App\Services;

use App\Model\CaseQuality;
use App\Model\PatientInfo;
use Illuminate\Support\Facades\DB;
use App\Model\EMR_BL_BL01;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;
use App\Model\CaseRule;
use function GuzzleHttp\Psr7\str;

/**
 * 入院记录质控
 */
class RuyuanService
{
    public function __construct()
    {
    }


    /**
     * @param string $no
     * @param array $caseRule
     * @return array
     * 病例质控
     */
    public
    static function checkCase($zyh = '', $content = [])
    {
        $caseContent = $content['RYJLblnr'] ?: [];
        if (!$caseContent) {
            return [];
        }

        $bl01 = ['ZYH' => $zyh, 'HJNR' => $caseContent];
        //所有添加的质控规则
        $caseRule = CaseRule::query()->get()->toArray();
        $caseRule = array_column($caseRule, null, 'id');
        $errorNotice = [];
        $title = [
            "姓名:", "出生地:", "性别:", "职业:", "年龄:", "入院时间:", "民族:", "记录时间:", "婚姻:", "病史陈述者:",
            "主诉:", "现病史:", "既往史:", "个人史:", "月经及婚育史:", "婚育史:", "家族史:", "体格检查", "辅助检查",
            "初步诊断", "医师签名"
        ];

        try {
            if (empty($caseContent)) {
                return $errorNotice;
            }

            // 整理数据，将数据整理成数组结构
            $caseContent = str_replace("月经婚育史", "月经及婚育史", $caseContent);
            $caseContent = str_replace("\r\n", "!!", $caseContent);
            $caseContent = str_replace("\n", "!!", $caseContent);
            $caseContent = str_replace(" ", "", $caseContent);
            $caseContent = str_replace("：", ":", $caseContent);
            $caseContent = str_replace("“", "\"", $caseContent);
            $caseContent = str_replace("”", "\"", $caseContent);

            $caseContent1 = mb_substr($caseContent, 0, 100);
            $caseContent2 = mb_substr($caseContent, 100);
            // 将入院记录中后半部分的年龄关键字过滤一下，去掉冒号
            $caseContent2 = str_replace('年龄:', '年龄', $caseContent);

            $caseContent = $caseContent1 . $caseContent2;
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

            // 解析专科检查
            $fzjc = $newData[md5('体格检查')] && $newData[md5('体格检查')]['value'] ? $newData[md5('体格检查')]['value'] : '';
            $zkjcStr = '';
            if (!empty($fzjc) && strpos($fzjc, '专科检查') !== false) {
                $zkjc = str_replace('专科检查', '|&|专科检查', $fzjc);
                $zkjcArr = explode('|&|', $zkjc);
                $zkjcStr = !empty($zkjcArr[1]) ? $zkjcArr[1] : '';
            }
            $newData['zkjc'] = $zkjcStr;

            $method = [
                'rule219',
                'rule220',
                'rule221',
                'rule222',
                'rule223',
                'rule226',
            ];
            foreach ($method as $m) {
                $ruleRes = self::$m($newData, $bl01, $caseRule);
                $errorNotice = array_merge($errorNotice, $ruleRes);
            }

            if ($errorNotice) {
                EMR_BL_BL01::query()->where('BLBH', $zyh)->update(['is_defect' => 1]);
                PatientInfo::query()->where('MED_REC_ID', $zyh)->update(['is_defect_v2' => 1]);
                foreach ($errorNotice as $v) {
                    CaseQuality::addData($v);
                }
            }

        } catch (\Throwable $e) {
            Log::error("病例之间处理失败", ['msg' => $e->getMessage(), 'line' => $e->getLine()]);
            echo $e->getMessage() . '，所在行：' . $e->getLine();
        }


        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     */
    private static function rule226($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 226;
        if (empty($caseRule[$ruleid]['status']) || empty($caseRule[$ruleid]['is_shizhong'])) {
            return [];
        }
        $errorNotice = [];
        $xbs = $newData[md5('现病史:')]['value'];
        $cbzd = $newData[md5('初步诊断')]['value'];
        if (strpos($xbs, '高血脂') !== false && strpos($cbzd, '高血脂') === false) {
            $errorNotice[] = [
                'basis' => json_encode([['现病史有高血脂初步诊断无']], 256),
                'JZHM' => $bl01['ZYH'],
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
     */
    private static function rule223($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 223;
        if (empty($caseRule[$ruleid]['status']) || empty($caseRule[$ruleid]['is_shizhong'])) {
            return [];
        }
        $errorNotice = [];
        $xbs = $newData[md5('现病史:')]['value'];
        $jws = $newData[md5('既往史:')]['value'];
        $cbzd = $newData[md5('初步诊断')]['value'];
        if (
            strpos($xbs, '冠状动脉粥样硬化性心脏病') !== false || strpos($xbs, '冠状动脉粥样硬化型心脏病') !== false
        ) {
            if (
                strpos($cbzd, '冠状动脉粥样硬化性心脏病') === false &&
                strpos($cbzd, '冠状动脉粥样硬化型心脏病') === false &&
                strpos($cbzd, '冠状动脉性心脏病') === false &&
                strpos($cbzd, '冠状动脉型心脏病') === false &&
                strpos($cbzd, '冠状动脉硬化型心脏病') === false &&
                strpos($cbzd, '冠状动脉硬化性心脏病') === false
            ) {
                $errorNotice[] = [
                    'basis' => json_encode([['现病史含[冠状动脉粥样硬化性心脏病/冠状动脉粥样硬化型心脏病]，初步诊断中未提及']], 256),
                    'JZHM' => $bl01['ZYH'],
                    'rule_id' => $ruleid,
                    'code' => 'cbzd',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
            }
        }
        if (strpos($jws, '冠状动脉粥样硬化性心脏病') !== false || strpos($jws, '冠状动脉粥样硬化型心脏病') !== false) {
            if (
                strpos($cbzd, '冠状动脉粥样硬化性心脏病') === false &&
                strpos($cbzd, '冠状动脉粥样硬化型心脏病') === false &&
                strpos($cbzd, '冠状动脉性心脏病') === false &&
                strpos($cbzd, '冠状动脉型心脏病') === false &&
                strpos($cbzd, '冠状动脉硬化型心脏病') === false &&
                strpos($cbzd, '冠状动脉硬化性心脏病') === false
            ) {
                $errorNotice[] = [
                    'basis' => json_encode([['既往史含[冠状动脉粥样硬化性心脏病/冠状动脉粥样硬化型心脏病]，初步诊断中未提及']], 256),
                    'JZHM' => $bl01['ZYH'],
                    'rule_id' => $ruleid,
                    'code' => 'cbzd',
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
    private static function rule222($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 222;
        if (empty($caseRule[$ruleid]['status']) || empty($caseRule[$ruleid]['is_shizhong'])) {
            return [];
        }
        $errorNotice = [];
        $xbs = $newData[md5('现病史:')]['value'];
        $jws = $newData[md5('既往史:')]['value'];
        $bing = [
            [
                'xbs' => '高血压',
                'jws' => ['否认高血压', '否认"冠心病、高血压"', '否认"高血压病、糖尿病、冠状动脉粥样硬化性心脏病"']
            ],
            [
                'xbs' => '糖尿病',
                'jws' => ['否认糖尿病']
            ],
            [
                'xbs' => '冠心病',
                'jws' => ['否认冠心病']
            ]
        ];

        foreach ($bing as $item) {
            if (strpos($xbs, $item['xbs']) === false) {
                continue;
            }
            foreach ($item['jws'] as $j) {
                if (strpos($jws, $j) !== false) {
                    $errorNotice[] = [
                        'basis' => json_encode([['现病史【' . $item['xbs'] . '】'], ['既往史【' . $j . '】']], 256),
                        'JZHM' => $bl01['ZYH'],
                        'rule_id' => $ruleid,
                        'code' => 'jws',
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
     * 体格检查中含“强迫体位”，不能是“查体合作”
     */
    private static function rule221($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 221;
        if (empty($caseRule[$ruleid]['status']) || empty($caseRule[$ruleid]['is_shizhong'])) {
            return [];
        }
        $errorNotice = [];
        $tgjc = $newData[md5('体格检查')]['value'];
        if (strpos($tgjc, '强迫体位') === false) {
            return [];
        }
        if (strpos($tgjc, '查体合作') !== false) {
            $errorNotice[] = [
                'basis' => json_encode([['体格检查【强迫体位】'], ['体格检查【查体合作】']], 256),
                'JZHM' => $bl01['ZYH'],
                'rule_id' => $ruleid,
                'code' => 'tgjc',
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
    private static function rule220($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 220;
        if (empty($caseRule[$ruleid]['status']) || empty($caseRule[$ruleid]['is_shizhong'])) {
            return [];
        }
        $errorNotice = [];
        $cbzd = trim($newData[md5('初步诊断')]['value']);
        $tgjc = $newData[md5('体格检查')]['value'];
        if (strpos($cbzd, '偏瘫') === false) {
            return [];
        }
        if (strpos($tgjc, '活动自如') !== false) {
            $errorNotice[] = [
                'basis' => json_encode([['初步诊断【偏瘫】'], ['体格检查【活动自如】']], 256),
                'JZHM' => $bl01['ZYH'],
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
     * 年龄<2岁，入院记录|体格检查 头围在20-55cm之间
     */
    private static function rule219($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 219;
        if (empty($caseRule[$ruleid]['status']) || empty($caseRule[$ruleid]['is_shizhong'])) {
            return [];
        }
        $errorNotice = [];
        $age = trim($newData[md5('年龄:')]['value']);
        $oldAge = $age;
        $tgjc = $newData[md5('体格检查')]['value'];
        // 如果年龄是天为单位，那么转换成年
        if (strpos($age, '天') !== false || strpos($age, '分钟') !== false) {
            $age = 1;
        }
        if (intval($age) >= 2) {
            return $errorNotice;
        }
        preg_match_all("/头围:([\d\.]+)CM/", $tgjc, $res);
        if (empty($res[1])) {
            return [];
        }
        $touwei = !empty($res) && !empty($res[1]) ? $res[1][0] : 0;
        if ($touwei < 20 || $touwei > 55) {
            $errorNotice[] = [
                'basis' => json_encode([['年龄:' . $oldAge], ['头围:' . $touwei . 'CM']], 256),
                'JZHM' => $bl01['ZYH'],
                'rule_id' => $ruleid,
                'code' => 'tgjc',
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
    private static function rule155($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 154;
        if (empty($caseRule[$ruleid]['status']) || empty($caseRule[$ruleid]['is_shizhong'])) {
            return [];
        }
        $errorNotice = [];
        $zhusu = $newData[md5('主诉:')]['value'];
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
                'JZHM' => $bl01['BLBH'],
                'rule_id' => $ruleid,
                'code' => 'zs',
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
    private static function rule154($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 154;
        if (empty($caseRule[$ruleid]['status']) || empty($caseRule[$ruleid]['is_shizhong'])) {
            return [];
        }
        $errorNotice = [];
        $zhusu = $newData[md5('主诉:')]['value'];
        $flag = false;
        $name = '';
        foreach ($newData['diagnose_list'] as $v) {
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
                'JZHM' => $bl01['BLBH'],
                'rule_id' => $ruleid,
                'code' => 'zs',
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
    private static function rule152($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 152;
        if (empty($caseRule[$ruleid]['status']) || empty($caseRule[$ruleid]['is_shizhong'])) {
            return [];
        }
        $sjk = [215, 287, 4190, 123, 220, 118, 2062];
        if (!in_array($bl01['BRKS'], $sjk)) {
            return [];
        }

        $zkjcStr = $bl01['HJNR'];
        $errorNotice = [];
        $bscsz = $newData[md5('病史陈述者:')]['value'];

        if (
            strpos($zkjcStr, '昏迷') !== false ||
            strpos($zkjcStr, '精神障碍') !== false ||
            strpos($zkjcStr, '意识障碍') !== false
        ) {
            if ($bscsz == '患者本人') {
                $errorNotice[] = [
                    'basis' => json_encode([['病史陈述者不能是本人']], 256),
                    'JZHM' => $bl01['ZYH'],
                    'rule_id' => $ruleid,
                    'code' => 'bscsz',
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
    private static function rule149($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 149;
        if (empty($caseRule[$ruleid]['status']) || empty($caseRule[$ruleid]['is_shizhong'])) {
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
                'JZHM' => $bl01['ZYH'],
                'rule_id' => $ruleid,
                'code' => 'zkjc',
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
    private static function rule145($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 145;
        if (empty($caseRule[$ruleid]['status']) || empty($caseRule[$ruleid]['is_shizhong'])) {
            return [];
        }
        if ($newData['diagnose_list'][0] != '胎膜早破') {
            return [];
        }

        $checkStr = $newData[md5('主诉:')]['value'];
        if (empty($checkStr)) {
            return [];
        }
        $errorNotice = [];

        if (strpos($checkStr, '阴道流液') === false) {
            $errorNotice[] = [
                'basis' => json_encode([['在患者入院记录-初步诊断为胎膜早破时，需要记录“ph”或“胎膜破”']], 256),
                'JZHM' => $bl01['BLBH'],
                'rule_id' => $ruleid,
                'code' => 'zs',
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
    private static function rule144($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 144;
        if (empty($caseRule[$ruleid]['status']) || empty($caseRule[$ruleid]['is_shizhong'])) {
            return [];
        }
        if ($newData['diagnose_list'][0] != '胎膜早破') {
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
                'BLBH' => $bl01['BLBH'],
                'rule_id' => $ruleid,
                'code' => 'zkjc',
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
    private static function rule143($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 143;
        if (empty($caseRule[$ruleid]['status']) || empty($caseRule[$ruleid]['is_shizhong'])) {
            return [];
        }
        if ($newData['diagnose_list'][0] != '先兆临产') {
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
                'BLBH' => $bl01['BLBH'],
                'rule_id' => $ruleid,
                'code' => '',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }
        return $errorNotice;
    }

    private static function rule140($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 140;
        if (empty($caseRule[$ruleid]['status']) || empty($caseRule[$ruleid]['is_shizhong'])) {
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
                'BLBH' => $bl01['BLBH'],
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
    private static function rule139($newData = [], $bl01 = [], $caseRule = [])
    {
        $ruleid = 139;
        if (empty($caseRule[$ruleid]['status']) || empty($caseRule[$ruleid]['is_shizhong'])) {
            return [];
        }
        // 专科检查
        if (trim($newData['diagnose_list'][0]) != '结直肠恶性肿瘤') {
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
                'BLBH' => $bl01['BLBH'],
                'rule_id' => 139,
                'code' => '',
                'error_field' => $caseRule[139]['title']
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
    private
    static function rule34($newData = [], $bl01 = [], $caseRule = [])
    {
        $errorNotice = [];
//            3、规则：一般不超过 20 个字，＞20个字给出提醒。
        $zdData = $newData[md5('主诉:')]['value'];
        $zdDataLenth = mb_strlen($zdData);
        if ($zdDataLenth > $caseRule[55]['number1']) {
            $errorNotice[] = [
                'basis' => json_encode([['主诉不能超过20个字']], 250),
                'BLBH' => $bl01['BLBH'],
                'rule_id' => 55,
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
                        'basis' => json_encode([['症状≤3个']], 250),
                        'BLBH' => $bl01['BLBH'],
                        'rule_id' => 56,
                        'code' => config("confAdmin.keyMap")[md5('主诉:')],
                        'error_field' => '主诉'
                    ];
                }
                // 记录所有的持续时间，用于验证时间排序规则
                $zsMaxTime = [];
                foreach ($symptom as $s) {
                    if (!isset($s->timeAttribute) || empty($s->timeAttribute)) {
                        $errorNotice[] = [
                            'BLBH' => $bl01['BLBH'],
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
                            'basis' => json_encode([['按时间先后顺序列出']], 250),
                            'BLBH' => $bl01['BLBH'],
                            'rule_id' => 79,
                            'code' => config("confAdmin.keyMap")[md5('主诉:')],
                            'error_field' => '主诉'
                        ];
                    }
                }
            }

            // 5、规则：通过主诉的症状 和（或） 体征 推断出第一诊断
            self::rule5($newData, $bl01, $symptom);
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
    public
    static function rule5($newData = [], $bl01 = [], $symptom = [])
    {
        $errorNotice = [];
        if (empty($symptom)) {
            $errorNotice[] = [
                'BLBH' => $bl01['BLBH'],
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
                        'BLBH' => $bl01['BLBH'],
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
    private
    static function rule6($newData = [], $blxgId = '', $caseRule=[])
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
    static function rule7($newData = [], $blxgId = '', $caseRule=[])
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
    static function rule8($newData = [], $blxgId = '', $caseRule=[])
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
    static function rule9($newData = [], $blxgId = '', $caseRule=[])
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
    private
    static function rule13($newData = [], $blxgId = '', $caseRule = [])
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
    private
    static function rule14($newData = [], $blxgId = '', $caseRule=[])
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
    private
    static function rule16($newData = [], $blxgId = '', $caseRule = [])
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


}

