<?php

namespace App\Services;

use App\Model\Appeal;
use App\Model\BigModelTemplate;
use App\Model\part;
use App\Model\Bllb292;
use App\Model\Setting;
use App\Model\Symptom;
use App\Model\ZY_HCMX;
use App\Model\ZY_BRRY;
use App\Model\CaseRule;
use App\Model\SSKEYWORD;
use App\Model\CaseQuality;
use App\Model\CaseQualityShizhongRecord;
use App\Model\Department;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\PatientInfo;
use App\Model\RuleWordMap;
use App\Model\CaseQualityZm;
use Illuminate\Support\Facades\DB;
use App\Model\DiseaseDiagnosisCode;

use function GuzzleHttp\Psr7\str;
use Illuminate\Support\Facades\Log;
use App\Services\ElasticsearchService;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;

/**
 * 入院记录质控
 */
class RuyuanService
{
    const ID = 5739572;
    public $caseRule = [];
    const CODE = [
        139 => 'tgjc',
        140 => 'tgjc',
        143 => 'tgjc',
        144 => 'tgjc',
        145 => 'zhusu',
        149 => 'tgjc',
        152 => 'zhengti',
        154 => 'zhengti',
        155 => 'zhengti',
        219 => 'tgjc',
        220 => 'tgjc',
        221 => 'tgjc',
        222 => 'tgjc',
        223 => 'chzd',
    ];

    public function __construct()
    {
        //所有添加的质控规则
        $caseRule = CaseRule::query()->where("status", 1)->get()->toArray();
        $this->caseRule = array_column($caseRule, null, 'id');
    }

    /**
     *
     * update patient_info set is_defect=0;
     * update setting set content=0 where name="quality_last_zyh";
     * truncate `case_quality`;
     *
     */
    public function checkCaseList($ZYH = '', $isSz = 0)
    {

        // 获取规则
        $caseRule = $this->caseRule;
        $bl01Res = Bllb292::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($bl01Res)) {
            return false;
        }

        //rule8099
        $rule8099 = RuleWordMap::query()->where('id', '=', 8099)->value('keyword');
        if (!empty($rule8099)) {
            if (strpos($rule8099, ',') !== false) {
                $rule8099 = array_filter(array_map('trim', explode(',', $rule8099)));
            } else {
                $rule8099 = [trim($rule8099)];
            }
        }

        if (!empty($rule8099)) {
            $brks = ZY_BRRY::query()->where('ZYH', $ZYH)->value('BRKS');
            if (!empty($brks)) {
                $depName = Department::query()->where('dep_id', $brks)->value('dep_name');
                if (!empty($depName)) {
                    // 如果科室名称包含 rule8099 中的任何一个，则不质控
                    foreach ($rule8099 as $rule) {
                        if ($rule !== '' && strpos($depName, $rule) !== false) {
                            return true;
                        }
                    }
                }
            }
        }

        // 护士分床时间
        /*             $zyhcmx = ZY_HCMX::query()
                ->where('HCLX', '=', 0)
                ->where('ZYH', '=', $ZYH)
                ->get(['ZYH', 'HCRQ'])->toArray();
            $zyhcmx = array_column($zyhcmx, null, 'ZYH'); */

        $errorNotice = [];
        $blbh = [];
        $appealids = [];
        foreach ($bl01Res as $item) {
            $item['JZHM'] = $item['ZYH'];
            $item['BRBH'] = $item['AAA28'];

            $lastId = $item['id'];
            /* $item['AAB01'] = '';
                if (!empty($zyhcmx[$item['ZYH']])) {
                    $item['AAB01'] = $zyhcmx[$item['ZYH']]['HCRQ'];
                } */
            $checkRes = self::checkCase($item, $caseRule, $isSz);
            if (empty($checkRes)) {
                continue;
            }
            foreach ($checkRes as $e) {
                if (empty($e)) {
                    continue;
                }
                $blbh[] = $item['BLBH'];
                $e['JZHM'] = $item['JZHM'];
                $e['BLBH'] = $item['BLBH'];
                $e['BRBH'] = $item['BRBH'];
            }
            $errorNotice = array_merge($errorNotice, $checkRes);
        }

        if ($errorNotice) {
            foreach ($errorNotice as $v) {
                if ($isSz == 99 || $isSz == 991) {
                    CaseQualityZm::addData($v);
                } else {
                    $appeal = Appeal::where('ZYH', $v['JZHM'])->where('error_id', $v['rule_id'])->get()->toArray();
                    if ($appeal) {
                        $v['appeal_id'] = $appeal[0]['id'];
                        $v['is_appeal'] = 1;
                        $appealids[] = $appeal[0]['id'];
                    } else {
                        $v['appeal_id'] = 0;
                        $v['is_appeal'] = 0;
                    }
                    CaseQuality::addData($v);
                    if ($isSz == 1 || $isSz == 4) {
                        $this->saveShizhongQualityRecord((string)$v['JZHM'], (int)$v['rule_id']);
                    }
                }
            }

            EMR_BL_BL01::query()->whereIn('BLBH', $blbh)->update(['is_defect' => 1]);
        }
        if (empty($appealids)) {
            Appeal::where('ZYH', $ZYH)->whereIn('error_id', [55, 1019, 1020, 1042, 1043, 1046, 1212, 1217])->where('status', '=', '0')->update(['status' => 3]);
        } else {
            Appeal::whereNotIn('id', is_array($appealids) ? $appealids : [$appealids])->where('ZYH', $ZYH)->whereIn('error_id', [55, 1019, 1020, 1042, 1043, 1046, 1212, 1217])->where('status', '=', '0')->update(['status' => 3]);
        }
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
     * @param string $no
     * @param array $caseRule
     * @return array
     * 病例质控
     */
    public static function checkCase($bl01 = [], $caseRule = [], $isSz = 0)
    {
        $errorNotice = [];
        try {
            $blxg = EMR_BL_BLXG::getById($bl01['BLBH']);
            $caseContent = $blxg[0]['HJNR'] ?: '';

            $method = [
                /* 219 => 'rule219',
                220 => 'rule220',
                221 => 'rule221',
                222 => 'rule222',
                223 => 'rule223',
                224 => 'rule224',
                226 => 'rule226',
                231 => 'rule231',
                233 => 'rule233',
                236 => 'rule236',
                242 => 'rule242',
                243 => 'rule243',
                246 => 'rule246', */
                55 => 'rule55', //主诉不超过20字
                1019 => 'rule1019', //主诉中不包含症状
                1020 => 'rule1020', //主诉中包含症状超过三个
                1042 => 'rule1042', // 入院记录既往史中有手术，体格检查中无“瘢痕/疤痕”
                1043 => 'rule1043', // 入院记录【主诉】与【现病史】中，症状不一致
                1046 => 'rule1046',
                1212 => 'rule1212',
                1217 => 'rule1217', //记录时间与入院时间小于10分钟
            ];
            $bl01['CBZD'] = json_decode($bl01['CBZD']);
            $bl01['CBZD'] = $bl01['CBZD'] ?: "";

            //$ruleRes = self::rule232($caseContent);
            //$errorNotice = array_merge($errorNotice, $ruleRes);
            // 大模型质控，重新质控过程中不删除大模型的质控结果
            $appealRuleIds = [];
            $templates = BigModelTemplate::query()->get()->toArray();
            $bitModelRuleIds = array_column($templates, "rule_id");
            $appeal = Appeal::query()
                ->where(["quality_type" => 2, "type" => 2, "ZYH" => $bl01['JZHM']])
                ->whereIn("status", ['1', '3'])
                ->get(["error_id"])
                ->toArray();
            if ($appeal) {
                $appealRuleIds = array_column($appeal, "error_id");
                $appealRuleIds = array_merge($appealRuleIds, $bitModelRuleIds);
            }
            // 删除历史质控数据
            $appealRuleIds = array_values(array_filter($bitModelRuleIds));
            foreach ($method as $ruleid => $m) {
                if (empty($caseRule[$ruleid]['status']) || in_array($ruleid, $appealRuleIds) || (($isSz == 1 || $isSz == 4) && $caseRule[$ruleid]['is_shizhong'] == 0)) {
                    continue;
                }
                $ruleRes = self::$m($bl01, $caseRule);
                if (!empty($ruleRes)) {
                    $errorNotice = array_merge($errorNotice, $ruleRes);
                }
            }
        } catch (\Throwable $e) {
            Log::error("command quality 错误:code:" . $e->getFile() . ':' . $e->getCode() . '；line:' . $e->getLine() . '；错误信息：' . $e->getMessage());
        }

        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param string $blxgId
     * @return array
     * 主诉相关规则
     */
    private static function rule55($bl01 = [], $caseRule = [])
    {
        $ruleid = 55;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $blmc = $bl01['BLMC'];
        $rulemap8082 = RuleWordMap::query()->where('id', 8082)->value('keyword');
        if (!empty($rulemap8082)) {
            if (strpos($blmc, $rulemap8082) !== false) {
                $rulemap8082 = explode(',', $rulemap8082);
            } else {
                $rulemap8082 = [$rulemap8082];
            }
        }

        if (!empty($rulemap8082)) {
            foreach ($rulemap8082 as $v) {
                if (strpos($blmc, $v) !== false) {
                    return [];
                }
            }
        }

        $errorNotice = [];
        //            3、规则：一般不超过 20 个字，＞20个字给出提醒。
        $zdData = $bl01["ZHS"];
        $basis = [];
        $rulemap8070 = RuleWordMap::query()->where('id', 8070)->value('keyword');
        //转换成数字
        if (isset($rulemap8070) && !empty($rulemap8070)) {
            $rulemap8070 = intval($rulemap8070);
        } else {
            $rulemap8070 = 20;
        }

        //去除标点符号
        $zdData = preg_replace('/[^\p{L}\p{N}\s]/u', '', $zdData);
        //去除空格
        $zdData = preg_replace('/\s+/', '', $zdData);
        $zdDataLenth = mb_strlen($zdData);
        if ($zdDataLenth > $rulemap8070) {
            $basis[] = '主诉不能超过' . $rulemap8070 . '个字';
            $basis['BLBH'] = $bl01['BLBH'];
            $errorNotice[] = [
                'JZHM' => $bl01['JZHM'],
                'rule_id' => $ruleid,
                'code' => 'rule_' . $ruleid,
                'error_field' => $caseRule[$ruleid]['title'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE)
            ];
        }
        return $errorNotice;
    }

    /**
     * @author lzh
     * @param array $newData
     * @param array $caseRule
     * @return array
     * 主诉中不包含症状
     */
    private static function rule1019($newData = [], $caseRule = [])
    {
        $ruleid = 1019;
        //查询症状表,获取所有症状
        $symptom = Symptom::query()->get()->toArray();
        //获取主诉
        $zhusu = $newData["ZHS"];
        //判断主诉中是否包含症状
        $flag = false;

        $rulemap8069 = RuleWordMap::query()->where('id', 8069)->value('keyword');

        //是否包含逗号
        if (strpos($rulemap8069, ',') !== false) {
            $rulemap8069 = explode(',', $rulemap8069);
        } else {
            $rulemap8069 = [$rulemap8069];
        }



        $errorNotice = [];

        foreach ($rulemap8069 as $k) {
            if (strpos($zhusu, $k) !== false) {

                $flag = true;
                break;
            }
        }
        if (!$flag) {
            foreach ($symptom as $v) {
                if (strpos($zhusu, $v['content']) !== false) {
                    $flag = true;
                    break;
                }
            }
        }

        if (!$flag) {
            $basis = [];
            $basis[] = '主诉中不包含症状';
            $basis['BLBH'] = $newData['BLBH'];
            $errorNotice[] = [
                'JZHM' => $newData['JZHM'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE),
                'rule_id' => $ruleid,
                'code' => 'rule_' . $ruleid,
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }
        return $errorNotice;
    }

    /**
     * @author lzh
     * @param array $newData
     * @param array $caseRule
     * @return array
     * 主诉中包含症状超过三个
     */
    private static function rule1020($newData = [], $caseRule = [])
    {
        $ruleid = 1020;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $blmc = $newData['BLMC'];
        //如果包含日间跳过
        if (strpos($blmc, '日间') !== false) {
            return [];
        }
        $zhusu = $newData["ZHS"];
        $zhwngzhuangstr = '';
        $symptom = Symptom::query()->get()->toArray();
        $iszk = false;
        $flag = 0;
        $basislist = [];
        foreach ($symptom as $v) {
            if (strpos($zhusu, $v['content']) !== false) {
                $flag++;
                $basis = [];
                $basis[] = "症状：【" . $v['content'] . "】";
                $basislist[] = $basis;
                //break;
            }
        }
        $rulemap8069 = RuleWordMap::query()->where('id', 8069)->value('keyword');

        //是否包含逗号
        if (strpos($rulemap8069, ',') !== false) {
            $rulemap8069 = explode(',', $rulemap8069);
        } else {
            $rulemap8069 = [$rulemap8069];
        }


        foreach ($rulemap8069 as $k) {
            if (strpos($zhusu, $k) !== false) {
                $iszk = true;
                break;
            }
        }
        $errorNotice = [];
        if ($flag > 3 && !$iszk) {

            $basis['BLBH'] = $newData['BLBH'];
            $errorNotice[] = [
                'JZHM' => $newData['JZHM'],
                'basis' => json_encode($basislist, JSON_UNESCAPED_UNICODE),
                'rule_id' => $ruleid,
                'code' => 'rule_' . $ruleid,
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }
        return $errorNotice;
    }

    /**
     * 入院记录既往史中有手术，体格检查中无“瘢痕/疤痕”
     */
    private static function rule1042($newData = [], $caseRule = [])
    {
        $ruleid = 1042;
        $flag = false;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $sskeyword = SSKEYWORD::query()->where('id', 1042)->get()->toArray();
        $rulemap8071 = RuleWordMap::query()->where('id', 8071)->value('keyword');
        $errorNotice = [];
        if (strpos($rulemap8071, ',') !== false) {
            $rulemap8071 = explode(',', $rulemap8071);
        } else {
            $rulemap8071 = [$rulemap8071];
        }
        if (empty($sskeyword)) {
            return [];
        }
        $isss = false;
        foreach ($sskeyword as $v) {
            if (strpos($newData['JWS'], $v['keyword']) !== false) {
                $isss = true;
                $tgjc = $newData['TGJC'];
                foreach ($rulemap8071 as $v) {
                    if (strpos($tgjc, $v) !== false) {
                        $flag = true;
                        break;
                    }
                }
            }
        }
        if (!$isss) {
            return [];
        }
        if (!$flag) {
            $basis = [];
            $basis[] = '入院记录既往史中有手术，体格检查中无【瘢痕/疤痕】';
            $basis['BLBH'] = $newData['BLBH'];
            $errorNotice[] = [
                'JZHM' => $newData['JZHM'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE),
                'rule_id' => $ruleid,
                'code' => 'rule_' . $ruleid,
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }
        return $errorNotice;
    }


    /**
     * 入院记录既往史中“否认手术史”与“行××术”矛盾
     */
    private static function rule1043($newData = [], $caseRule = [])
    {
        $ruleid = 1043;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $rulemap8072 = RuleWordMap::query()->where('id', 8072)->value('keyword');
        Log::info('rulemap8072::' . $rulemap8072);
        if (empty($rulemap8072)) {
            return [];
        }
        if (strpos($rulemap8072, ',') !== false) {
            $rulemap8072 = explode(',', $rulemap8072);
        } else {
            $rulemap8072 = [$rulemap8072];
        }
        $rulemap8073 = RuleWordMap::query()->where('id', 8073)->value('keyword');
        if (strpos($rulemap8073, ',') !== false) {
            $rulemap8073 = explode(',', $rulemap8073);
        } else {
            $rulemap8073 = [$rulemap8073];
        }
        $rulemap8079 = RuleWordMap::query()->where('id', 8079)->value('keyword');
        if (strpos($rulemap8079, ',') !== false) {
            $rulemap8079 = explode(',', $rulemap8079);
        } else {
            $rulemap8079 = [$rulemap8079];
        }
        //先查看jws中是否包含8072，如果没有在看xbs是否包含，如果都不包含就直接return 【】
        $jws = $newData['JWS'];
        //如果既往史包含未行手术直接返回
        if (empty($jws) || strpos($jws, '未行手术') !== false) {
            return [];
        }
        $flag = false;
        foreach ($rulemap8072 as $v) {
            if (strpos($jws, $v) !== false) {
                $flag = true;
                break;
            }
        }
        if ($flag) {
            return [];
        }
        $errorNotice = [];
        $shushi = '';
        //查看xbs或者jws中是否包含8073或者“...行...术...”(正则)
        if ($flag) {
            $isshu = false;
            foreach ($rulemap8079 as $v) {
                if (strpos($jws, $v) !== false) {
                    //删除8079
                    $jws = str_replace($v, '', $jws);
                }
            }
            // 优先检查"行...术"模式
            if (preg_match('/行.*术.*/', $jws)) {
                if (preg_match('/行(.*?)术/', $jws, $matches)) {
                    // 提取"行"到"术"之间的内容
                    $shushi = '行' . trim($matches[1] ?? '') . '术';
                    $isshu = true;
                }
            } else {
                // 检查关键词匹配
                foreach ($rulemap8073 as $v) {
                    if (strpos($jws, $v) !== false) {
                        //取出匹配到的手术史，比如直肠癌手术史，取手术史到前面的第一个标点符号，分号；，逗号，句号。
                        // 获取$v在文本中的位置
                        $pos = mb_strpos($jws, $v);
                        if ($pos !== false) {
                            // 查找$v前面最近的标点符号位置
                            $prefix = mb_substr($jws, 0, $pos);
                            $punctuations = ['，', '；', '。', '“', '：'];
                            $punctPos = -1;

                            foreach ($punctuations as $punctuation) {
                                $currentPos = mb_strrpos($prefix, $punctuation);
                                if ($currentPos !== false && $currentPos > $punctPos) {
                                    $punctPos = $currentPos;
                                }
                            }

                            if ($punctPos !== -1) {
                                $punctPos += 1; // 从标点后一位开始提取
                                $shushi = mb_substr($jws, $punctPos, $pos - $punctPos) . $v;
                            } else {
                                // 如果没有找到标点符号，从文本开始提取到$v
                                $shushi = $v;
                            }
                            $shushi = trim($shushi);
                            $isshu = true;
                            break;
                        }
                    }
                }
            }

            if ($isshu) {
                $basis = [];
                $basis[] = '入院记录既往史中“否认手术史”与“行××术”矛盾，手术史为：' . $shushi;

                $basis['BLBH'] = $newData['BLBH'];
                $errorNotice[] = [
                    'JZHM' => $newData['JZHM'],
                    'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE),
                    'rule_id' => $ruleid,
                    'code' => 'rule_' . $ruleid,
                    'error_field' => $caseRule[$ruleid]['title']
                ];
            }
        }
        return $errorNotice;
    }

    private static function rule1212($newData = [], $caseRule = [])
    {
        $ruleid = 1212;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $RYSJ = $newData['RYSJ'];
        $JLSJ = $newData['JLSJ'];

        $errorNotice = [];

        // 检查时间格式是否正常
        $rysj_timestamp = strtotime($RYSJ);
        $jlsj_timestamp = strtotime($JLSJ);

        if ($rysj_timestamp !== false && $jlsj_timestamp !== false) {
            // 时间格式正常，判断RYSJ是否大于JLSJ
            if ($rysj_timestamp > $jlsj_timestamp) {
                $basis = [];
                $basis[] = '入院时间不能晚于记录时间';
                $basis['BLBH'] = $newData['BLBH'];

                $errorNotice[] = [
                    'JZHM' => $newData['JZHM'],
                    'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE),
                    'rule_id' => $ruleid,
                    'code' => 'rule_' . $ruleid,
                    'error_field' => $caseRule[$ruleid]['title']
                ];
            }
        } else {
            return $errorNotice;
        }
        return $errorNotice;
    }

    private static function rule1046($newData = [], $caseRule = [])
    {
        $ruleid = 1046;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $part = part::query()->get()->toArray();
        //主诉
        $zhusu = $newData['ZHS'];
        //现病史
        $xbs = $newData['XBS'];
        $basisList = [];
        $errorNotice = [];
        foreach ($part as $v) {
            $bw = $v['bw'];
            //是否包含逗号
            if (empty($bw)) {
                continue;
            }
            if (strpos($bw, ',') !== false) {
                $bw = explode(',', $bw);
            } else {
                $bw = [$bw];
            }
            $jybw = $v['jybw'];
            if (empty($jybw)) {
                $jybw = [];
            } else {
                if (strpos($jybw, ',') !== false) {
                    $jybw = explode(',', $jybw);
                } else {
                    $jybw = [$jybw];
                }
            }

            $sc = $v['sc'];
            if (empty($sc)) {
                $sc = [];
            } else {
                if (strpos($sc, ',') !== false) {
                    $sc = explode(',', $sc);
                } else {
                    $sc = [$sc];
                }
            }
            $isbw = false;
            $isjybw = false;
            $basis = [];
            foreach ($bw as $w) {
                if (strpos($zhusu, $w) !== false) {
                    $isbw = true;
                    //break;
                }
                if (!$isbw) {
                    continue;
                }
                foreach ($jybw as $w2) {
                    if (strpos($zhusu, $w2) !== false) {
                        $isjybw = true;
                        break;
                    }
                }
                if ($isbw && $isjybw) {
                    continue;
                }
                if (!empty($sc)) {
                    foreach ($sc as $s) {
                        //现病史删除s
                        if (strpos($xbs, $s) !== false) {
                            $xbs = str_replace($s, '', $xbs);
                        }
                    }
                }

                //查看现病史中是否包含jybw

                foreach ($jybw as $w1) {
                    if (strpos($xbs, $w1) !== false) {
                        $basis[] = "主诉中包含【" . $w . "】，现病史中包含【" . $w1 . "】";
                    }
                }
            }
            if (!empty($basis)) {
                $basisList[] = $basis;
            }
        }
        if (!empty($basisList)) {
            $errorNotice[] = [
                'JZHM' => $newData['JZHM'],
                'basis' => json_encode($basisList, JSON_UNESCAPED_UNICODE),
                'rule_id' => $ruleid,
                'code' => 'rule_' . $ruleid,
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }
        return $errorNotice;
    }

    /**
     * @param array $newData
     * @param array $caseRule
     * @return array
     * 
     * 记录时间与入院时间小于10分钟
     */
    private static function rule1217($newData = [], $caseRule = [])
    {
        $ruleid = 1217;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $errorNotice = [];
        $RYSJ = $newData['RYSJ'];
        $JLSJ = $newData['JLSJ'];
        if (empty($RYSJ) || empty($JLSJ) || strpos($RYSJ, '1970-01-01') !== false || strpos($JLSJ, '1970-01-01') !== false) {
            return [];
        }
        $RYSJ_timestamp = strtotime($RYSJ);
        $JLSJ_timestamp = strtotime($JLSJ);
        $timeDiff = $JLSJ_timestamp - $RYSJ_timestamp;
        if ($timeDiff < 10 * 60) {
            $basis = [];
            $basis[] = '记录时间与入院时间小于10分钟';
            $errorNotice[] = [
                'JZHM' => $newData['JZHM'],
                'basis' => json_encode([$basis], JSON_UNESCAPED_UNICODE),
                'rule_id' => $ruleid,
                'code' => 'rule_' . $ruleid,
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }
        return $errorNotice;
    }

    /**\
     * @param array $newData
     * @param array $caseRule
     * @return array
     */
    /*     private static function rule246($newData = [], $caseRule = [])
    {
        $AAB01 = strtotime($newData["AAB01"]);

        $bl01esService = new ElasticsearchService('bl01_202303');
        $bl01must = [
            ['term' => ["JZHM" => $newData['JZHM']]],
            ['term' => ["BLLB" => 294]],
            ['range' => ["ZXSJ" => ['from' => date('Y-m-d H:i:s', $AAB01), 'to' => date('Y-m-d H:i:s', $AAB01 + 48 * 3600)]]]
        ];
        $params = $bl01esService->clearMust()
            ->queryByMustNot(['term' => ['BLZT' => 9]])
            ->queryByMustNot(['term' => ['MBLB' => 295]])
            ->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'BLMC', 'HJNR'])->getParams();
        $bl01Res = app('es')->search($params);
        $bl01Res = $bl01esService->getDataByEs($bl01Res);

        return RuleService::rule246($bl01Res[0], $caseRule, $newData);
    } */



    /**
     * @param array $newData
     * @param array $caseRule
     * @return array
     * '现病史有【高血脂病史】，初步诊断中没有书写'
     */
    private static function rule226($newData = [], $caseRule = [])
    {
        return RuleService::rule226($newData, $caseRule);
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 体格检查中含“强迫体位”，不能是“查体合作”
     */
    private static function rule223($newData = [], $caseRule = [])
    {
        return RuleService::rule223($newData, $caseRule);
    }


    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 现病史“高血压”、“糖尿病”、“冠心病”
     * 既往史[否认糖尿病].或[否认冠心病] 或[否认高血压]或[否认“冠心病、高血压”]或[否认“高血压病、糖尿病、冠状动脉粥样硬化性心脏病”]
     */
    private static function rule222($newData = [], $caseRule = [])
    {
        return RuleService::rule222($newData, $caseRule);
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 现病史“高血压”、“糖尿病”、“冠心病”
     * 既往史[否认糖尿病].或[否认冠心病] 或[否认高血压]或[否认“冠心病、高血压”]或[否认“高血压病、糖尿病、冠状动脉粥样硬化性心脏病”]
     */
    private static function rule224($newData = [], $caseRule = [])
    {
        return RuleService::rule224($newData, $caseRule);
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 现病史“高血压”、“糖尿病”、“冠心病”
     * 既往史[否认糖尿病].或[否认冠心病] 或[否认高血压]或[否认“冠心病、高血压”]或[否认“高血压病、糖尿病、冠状动脉粥样硬化性心脏病”]
     */
    private static function rule231($newData = [], $caseRule = [])
    {
        return RuleService::rule231($newData, $caseRule);
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 整体{}超过2个
     */
    private static function rule232($content = "", $caseRule = [])
    {
        return RuleService::rule232($content, $caseRule);
    }


    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 整体{}超过2个
     */
    private static function rule242($newData = [], $caseRule = [])
    {
        return RuleService::rule242($newData, $caseRule);
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     */
    private static function rule243($newData = [], $caseRule = [])
    {
        return RuleService::rule243($newData, $caseRule);
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 整体{}超过2个
     */
    private static function rule236($newData = [], $caseRule = [])
    {
        return RuleService::rule236($newData, $caseRule);
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 入院记录书写不规范
     */
    private static function rule233($newData = [], $caseRule = [])
    {
        return RuleService::rule233($newData, $caseRule);
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 体格检查中含“强迫体位”，不能是“查体合作”
     */
    private static function rule221($newData = [], $caseRule = [])
    {
        return RuleService::rule221($newData, $caseRule);
    }

    /**
     * @param array $newData
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 入院记录初步诊断含“偏瘫”，入院记录的体格检查含“活动自如”
     */
    private static function rule220($newData = [], $caseRule = [])
    {
        $ruleid = 220;
        if (empty($caseRule[$ruleid]['status'])) {
            return [];
        }
        $errorNotice = [];
        if (!is_array($newData["CBZD"]) || empty($newData["CBZD"])) {
            return [];
        }

        $newData["CBZD"] = implode(',', $newData["CBZD"]);
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
     * 患者为 泌尿系感染，入院记录中典型症状记录缺失
     */
    private static function rule155($newData = [], $bl01 = [], $caseRule = [])
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
    private static function rule154($newData = [], $bl01 = [], $caseRule = [])
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
    private static function rule152($newData = [], $bl01 = [], $caseRule = [])
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
    private static function rule149($newData = [], $bl01 = [], $caseRule = [])
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
    private static function rule145($newData = [], $bl01 = [], $caseRule = [])
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
    private static function rule144($newData = [], $bl01 = [], $caseRule = [])
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
    private static function rule143($newData = [], $bl01 = [], $caseRule = [])
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

    private static function rule140($newData = [], $bl01 = [], $caseRule = [])
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
    private static function rule139($newData = [], $bl01 = [], $caseRule = [])
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
     * @param array $bl01
     * @param array $caseRule
     * @return array
     * 有症状（或体征）+时间，缺一不可
     */
    private static function rule76($newData = [], $bl01 = [], $caseRule = [])
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
    /* public static function rule5($newData = [], $bl01 = [], $symptom = [])
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
    } */

    /**
     * @param array $newData
     * @param string $blxgId
     * @return array
     * // 6、记录发病的时间   规则：按时间先后顺序排序，早的在前边
     * // 11、③现病史中有症状体征，主诉里没写对应的症状，需要提醒  （主诉和现病史不一致，如果现病史没有症状描述，那主诉里边可以写表现）
     */
    private
    static function rule6($newData = [], $blxgId = '', $caseRule)
    {

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
    private
    static function rule7($newData = [], $blxgId = '', $caseRule)
    {

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
    private
    static function rule8($newData = [], $blxgId = '', $caseRule)
    {
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
    private
    static function rule9($newData = [], $blxgId = '', $caseRule)
    {
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
    private
    static function rule10($newData = [], $blxgId = '', $caseRule = [])
    {
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
    private
    static function rule13($newData = [], $blxgId = '', $caseRule = [])
    {
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
    private
    static function rule14($newData = [], $blxgId = '', $caseRule)
    {
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
    private
    static function rule16($newData = [], $blxgId = '', $caseRule = [])
    {
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
}
