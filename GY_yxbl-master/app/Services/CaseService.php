<?php

namespace App\Services;

use App\Model\Staff;
use App\Model\Yzb;
use Exception;
use Illuminate\Filesystem\Cache;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pheanstalk\Pheanstalk;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;
use App\Model\CaseRule;
use function GuzzleHttp\json_encode;
use function GuzzleHttp\Psr7\str;
use App\Model\CaseQuality;

/**
 * 病例质控
 * 如果质控结果已改则增加已改标识。
 */
class CaseService
{
    public $zyh;
    public $caseRule = [];
    public $method = [
        'jbzd',
        'rule101', // 入院后8小时内完成首次病程记录
        'checkCt', // 检查报告单有ct（OCT除外）结果，24小时内要写病程记录
        'checkMr', // 检查报告单有mr结果，24小时内要写病程记录，或 24小时内出入院记录：关键字 "MR” 或 “磁共振”
        'kjy',// 有抗菌药，开嘱时间+24小时 内要有病程记录
        'hly', // 有化疗药，开嘱时间+24小时 内要有病程记录
        'sq24', // 术前24小时内，有术者查房（搜签名）
        'sh24',// 术后24小时内，有术者查房（搜签名）
        'zyts7',// 住院天数≥7天，病程记录：副高或正高级职称一周2次，中级职称一周3次
        'rule114', // 护士分床后48小时内，病程记录中，需要有一个 副高或正高签名（首次病程除外）
        'rule115', // 术前小结及术前讨论结论记录 在医嘱之前
        'rule116', // 术后即刻完成（当天完成）
        'rule117', // 术后48小时内须有术者查房记录
        'rule118', // 术后3天连续病程记录
        'rule119',  // 会诊记录，当天完成
        'rule121',// 阶段小结
        'rule122', // 由转入科室医师于患者转入后24小时内完成
        'rule123',// 抢救记录在开嘱6小时内
        'rule124',// 输血记录 - 输血当天要有病程记录
        'rule125',// 出院之前    (出院当天或出院前一天  ），需要写一个病程（首次病程除外）或 24小时出入院
        'rule132',// 日常病程
        'rule133',// 医嘱中 YDYZLB=901 医嘱名称中含“病危” ，  XZJDSJ 开始 - TZQRSJ 止，每1天有病程  （包含当天）             病程记录：通过 表：EMR_BL_BL01 中【BLLB =294 且 mblb=32的剔除且 blzt不等于9】中【blbh】去
        'rule134',// 医嘱中 YDYZLB=901 医嘱名称中含“病重” ，  XZJDSJ 开始 - TZQRSJ 止，每2天有病程  （包含当天）             病程记录：通过 表：EMR_BL_BL01 中【BLLB =294 且 mblb=32的剔除且 blzt不等于9】中【blbh】去
    ];

    public function __construct()
    {
        //所有添加的质控规则
        $caseRule = CaseRule::query()->get()->toArray();
        $this->caseRule = array_column($caseRule, null, 'id');

    }

    /**
     * @param string $zyh
     * @param array $content
     * @return array
     * 病例质控分发方法
     */
    public function qualityContrl($zyh = '', $content = [])
    {

        // 获取规则
        $this->zyh = $zyh;
        $errorNotice = [];
        $method = $this->method;
        foreach ($method as $m) {
            $res = $this->$m($content);
            if (empty($res)) {
                continue;
            }
            $errorNotice[] = $res;
        }

        if ($errorNotice) {
            foreach ($errorNotice as $item) {
                $item['status'] = 1; // 设置有问题的质控数据
                CaseQuality::query()->updateOrInsert(['rule_id' => $item['rule_id'], 'JZHM' => $item['JZHM']], $item);
            }
        }

        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 入院后8小时内完成首次病程记录
     * {"hsfcsj":"护士分床时间", "bc_data":{[{"HJNR":"修改内容","MBLB":"模板类别","CJSJ":"创建时间"}]}}
     */
    public function rule101($info = [])
    {
        $ruleid = 101;
        if (empty($caseRule[$ruleid]['status']) || empty($caseRule[$ruleid]['is_shizhong'])) {
            return [];
        }
        $errorNotice = [];
        $caseRule = $this->caseRule;

        $basis = [];
        // 护士分床时间
        if (empty($info['HCRQ'])) {
            return [];
        }
        $flag = 1;
        $hcrq = $info['HCRQ'];
        $basis[] = '护士分床时间【' . $hcrq . '】';
        $bgsj = strtotime($hcrq) + 8 * 3600;

        // 病程数据
        if (empty($info['SCBCblnr'])) {
            return [];
        }
        $shoucheng = []; // 首次病程记录变量
        // 病程的最后修改内容
        $shoucheng['hjnr'] = $info['SCBCblnr'] ?: '';
        $shoucheng['CJSJ'] = $info['SCBCCJSJ'] ?: '';
        $shoucheng['ZXSJ'] = $info['SCBCZXSJ'] ?: '';

        if (empty($shoucheng['ZXSJ'])) {
            $flag = 0;
            $basis[] = '首次病程记录【无】';
        } else {
            $basis[] = '首次病程记录【' . $shoucheng['ZXSJ'] . '】';
            $cjsj = strtotime($shoucheng['ZXSJ']);
            if ($cjsj < strtotime($hcrq)) {
                $flag = 0;
                $basis[] = '首次病程记录执行时间早于护士分床时间';
            } elseif ($bgsj < $cjsj) {
                $flag = 0;
                $basis[] = '首次病程记录执行时间【' . $shoucheng['ZXSJ'] . '、超8小时】';
            }
        }
        if (empty($flag)) {
            $errorNotice = [
                'basis' => json_encode([$basis], 256),
                'JZHM' => $this->zyh,
                'rule_id' => $ruleid,
                'code' => 'bingcheng_first',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * @return array
     * 首次病程记录 鉴别诊断条数 小于 2
     */
    public
    function jbzd($info = []): array
    {
        $ruleid = 126;
        if (empty($caseRule[$ruleid]['status']) || empty($caseRule[$ruleid]['is_shizhong'])) {
            return [];
        }
        $errorNotice = [];
        $caseRule = $this->caseRule;
        if (empty($info['bl01'])) {
            return [];
        }
        $bl01 = $info['bl01'] ?: [];
        $bl01Res = [];
        foreach ($bl01 as $b) {
            if ($b['MBLB'] == 295) {
                $bl01Res[] = $b;
            }
        }

        if (!empty($bl01Res)) {
            $caseContent = $bl01Res[0]['HJNR'];
            // 整理数据
            $caseContent = str_replace("\r\n", "!!", $caseContent);
            $caseContent = str_replace("\n", "!!", $caseContent);
            $caseContent = str_replace(" ", "", $caseContent);
            $caseContent = str_replace("：", ":", $caseContent);
            $caseContent = str_replace("“", "\"", $caseContent);
            $caseContent = str_replace("”", "\"", $caseContent);
            preg_match_all("/鉴别诊断:(.*?)诊疗计划/u", $caseContent, $jbzd);
            $jbzdArr = [];

            if (array_filter($jbzd)) {
                $jbzdArr = explode('!!', $jbzd[1][0]);
            }
            if (count($jbzdArr) < 3) {
                preg_match_all("/(.*?):/u", Arr::get($jbzdArr, 0), $res1);
                if (array_filter($res1)) {
                    $basis = [['鉴别诊断【' . $res1[1][0] . '】']];
                } else {
                    $str = str_replace("!!", "", $jbzd[1]);
                    $basis = [$str];
                }
                $errorNotice = [
                    'basis' => json_encode($basis, 256),
                    'JZHM' => $info['ZYH'],
                    'rule_id' => $ruleid,
                    'notice' => '必须要有鉴别诊断，数量>=2',
                    'code' => 'bingcheng_first',
                    'error_field' => $caseRule[$ruleid]['title']
                ];
            }
        }
        return $errorNotice;
    }


    /**
     * @return array
     */
    public function rule133($info = [])
    {
        $ruleid = 133;
        if (empty($caseRule[$ruleid]['status']) || empty($caseRule[$ruleid]['is_shizhong'])) {
            return [];
        }
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        if (empty($info['bl01'])) {
            return [];
        }
        // 获取bl01表中的数据
        $bl01 = $info['bl01'] ?: []; // 病例数据
        $yzb = $info['yzb'] ?: [];

        $yzbRes = [];
        foreach ($yzb as $y) {
            if (strpos($y['YZMC'], '病危') !== false) {
                $yzbRes = $y;
                break;
            }
        }
        if (empty($yzbRes)) {
            return $errorNotice;
        }
        $startTime = strtotime($yzbRes['XZJDSJ']);
        $endTime = strtotime($yzbRes['TZQRSJ']);
        if ($startTime > $endTime) {
            return $errorNotice;
        }

        while (true) {
            $toTime = $startTime + 24 * 3600 - 1;
            if ($toTime > $endTime) {
                break;
            }
            $bl01Res = [];
            foreach ($bl01 as $b) {
                if ($b['BLLB'] == 294 && strtotime($b['CJSJ']) > $startTime && strtotime($b['CJSJ']) < $toTime && $b['BLZT'] != 9 && $b['MBLB'] != 32) {
                    $bl01Res = $b;
                    break;
                }
            }
            $basis = [];
            $basis[] = '时间段【' . date("Y-m-d", $startTime) . '】，';
            if (empty($bl01Res)) {
                $basis[] = '病程记录【无】';
                $basisList[] = $basis;
            } elseif (strtotime($bl01Res['CJSJ']) < $startTime || strtotime($bl01Res['CJSJ']) > $toTime) {
                $basis[] = '病程记录【' . $bl01Res['BLMC'] . '、超24小时】';
                $basisList[] = $basis;
            }
            $startTime = $toTime;
        }

        if ($basisList) {
            array_unshift($basisList, ['病危：' . $yzb['XZJDSJ'] . ' - ' . date('Y-m-d H:i:s', $endTime)]);
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['ZYH'],
                'rule_id' => $ruleid,
                'code' => 'rcbc',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * 病重患者需要2天一次病程
     * @return array
     */
    public function rule134($info = [])
    {
        $ruleid = 134;
        if (empty($caseRule[$ruleid]['status']) || empty($caseRule[$ruleid]['is_shizhong'])) {
            return [];
        }
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        if (empty($info['bl01'])) {
            return [];
        }
        // 获取bl01表中的数据
        $bl01 = $info['bl01'] ?: []; // 病例数据
        $yzb = $info['yzb'] ?: [];

        $yzbRes = [];
        foreach ($yzb as $y) {
            if (strpos($y['YZMC'], '病重') !== false) {
                $yzbRes = $y;
                break;
            }
        }
        if (empty($yzbRes)) {
            return $errorNotice;
        }
        $startTime = strtotime($yzbRes['XZJDSJ']);
        $endTime = strtotime($yzbRes['TZQRSJ']);
        if ($startTime > $endTime) {
            return $errorNotice;
        }

        while (true) {
            $toTime = $startTime + 2 * 24 * 3600;

            //
            if ($toTime > $endTime) {
                break;
            }
            $bl01Res = [];
            foreach ($bl01 as $b) {
                if (strtotime($b['CJSJ']) > $startTime && strtotime($b['CJSJ']) < $toTime && $b['BLZT'] != 9 && $b['MBLB'] != 32) {
                    $bl01Res = $b;
                    break;
                }
            }

            $basis = [];
            if (empty($bl01Res)) {
                $basis[] = '时间段【' . date("Y-m-d", $startTime) . '-' . date("Y-m-d", $toTime) . '】，';
                $basis[] = '病程记录【无】';
                $basisList[] = $basis;
            }
            $startTime = $toTime;
        }

        if ($basisList) {
            array_unshift($basisList, ['病重：' . $yzb['XZJDSJ'] . ' - ' . $endTime]);
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['ZYH'],
                'rule_id' => $ruleid,
                'code' => 'rcbc',
                'error_field' => $caseRule[$ruleid]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 日常病程记录
     */
    public function rule132($info = [])
    {
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        if (empty($caseRule[132]['is_shizhong']) || empty($info['bl01'])) {
            return [];
        }
        $startTime = $info['AAB01'];
        $endTime = $info['AAC01'];
        if (empty($startTime) || empty($endTime)) {
            return $errorNotice;
        }
        // 获取bl01表中的数据
        $bl01 = $info['bl01'] ?: []; // 病例数据
        $startTime = strtotime(date('Y-m-d', strtotime($startTime)));

        while (true) {
            $toTime = $startTime + 3 * 24 * 3600;

            // 从开始时间加7天超过住院结束时间，则失败
            if ($toTime > strtotime($endTime)) {
                break;
            }
            $bl01Res = [];
            foreach ($bl01 as $b) {
                if ($b['BLZT'] != 9 && $b['mblb'] != 32 && $b['BLLB'] == 294 && strtotime($b['ZXSJ']) > $startTime && strtotime($b['ZXSJ']) < $toTime) {
                    $bl01Res[] = $b;
                }
            }

            $basis = [];
            $basis[] = '时间段【' . date("Y-m-d", strtotime($startTime)) . ' / ' . substr(date("Y-m-d H:i:s", $toTime - 1), 0, 10) . '】，';
            if (empty($bl01Res[1])) {
                $basis[] = '病程记录【无】';
                $basisList[] = $basis;
            }
            $startTime = $toTime;
        }

        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['ZYH'],
                'rule_id' => 132,
                'code' => 'rcbc',
                'error_field' => $caseRule[132]['title']
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
        $caseRule = $this->caseRule;
        if (empty($caseRule[125]['is_shizhong']) || empty($info['bl01'])) {
            return [];
        }
        $aac01 = $info['AAC01'];
        $startTime = strtotime(date('Y-m-d', strtotime($aac01))) - 24 * 3600;
        // 获取bl01表中的数据
        $bl01 = $info['bl01'] ?: []; // 病例数据
        $yzb = $info['yzb'] ?: []; // 医嘱本信息
        $bl01Res = [];
        foreach ($bl01 as $b) {
            if (in_array($b['MBLB'], [32, 295]) && $b['BLZT'] !== 9 && in_array($b['BLLB'], [294, 18]) && strtotime($b['ZXSJ']) > $startTime && strtotime($b['ZXSJ']) < $startTime + 48 * 3600) {
                $bl01Res[] = $b;
            }
        }
        if (empty($bl01Res)) {
            return $errorNotice;
        }

        if (empty($bl01Res)) {
            $basis[] = '出院时间【' . $aac01 . '】';
            $basis[] = '病程记录【无】';
        }
        if ($basis) {
            $errorNotice = [
                'basis' => json_encode([$basis], 256),
                'JZHM' => $info['ZYH'],
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
        if (empty($caseRule[124]['is_shizhong']) || empty($info['bl01']) || empty($info['yzb'])) {
            return [];
        }
        // 获取bl01表中的数据
        $bl01 = $info['bl01'] ?: []; // 病例数据
        $yzb = $info['yzb'] ?: []; // 医嘱本信息
        $yzbRes = [];
        foreach ($yzb as $y) {
            if ($y['YZQX'] == 2 && strpos($y['YZMC'], '输') !== false) {
                $yzbRes[strtotime(date('Y-m-d', strtotime($y['KZSJ'])))] = $y;
            }
        }
        if (empty($yzbRes)) {
            return $errorNotice;
        }


        foreach ($yzbRes as $kzsj => $item) {
            // 当天的时间
            $endTime = $kzsj + 24 * 3600;

            // 先搜索MBLB是45的数据，如果没有则搜索bllb=295并且blmc中包含“输血记录”的数据
            $bl01Res = [];
            foreach ($bl01 as $b) {
                if ($b['BLZT'] != 9 && $b['MBLB'] == 45 && strtotime($b['ZXSJ']) > $kzsj && strtotime($b['ZXSJ']) < $endTime) {
                    $bl01Res = $b;
                    break;
                }
            }
            if (empty($bl01Res)) {
                $bl01Res = [];
                foreach ($bl01 as $b) {
                    if ($b['BLZT'] != 9 && $b['BLLB'] == 294 && strtotime($b['ZXSJ']) > $kzsj && strtotime($b['ZXSJ']) < $endTime && strpos($b['BLMC'], '输血记录') !== false) {
                        $bl01Res = $b;
                        break;
                    }
                }
                $basis = [];
                $basis[] = '输血的开嘱时间【' . date('Y-m-d H:i:s', $kzsj) . '】';
                if (empty($bl01Res)) {
                    $basis[] = '病程时间【无】';
                    $basisList[] = $basis;
                }
            }

            if (strtotime($bl01Res['CJSJ']) < $kzsj || strtotime($bl01Res['CJSJ']) > $endTime) {
                $basis[] = '病程时间【' . $bl01Res['BLMC'] . '、超出24小时】';
                $basisList[] = $basis;
            }
        }

        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['ZYH'],
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
        if (empty($caseRule[123]['is_shizhong']) || empty($info['bl01'])) {
            return [];
        }
        // 获取bl01表中的数据
        $bl01 = $info['bl01'] ?: []; // 病例数据
        $yzb = $info['yzb'] ?: []; // 医嘱本信息
        $yzbRes = [];
        foreach ($yzb as $y) {
            if (strpos($y['YZMC'], '抢救') !== false) {
                $yzbRes[] = $y;
            }
        }
        if (empty($yzbRes)) {
            return $errorNotice;
        }

        foreach ($yzbRes as $item) {
            $basis = [];
            $kzsj = strtotime($item['KZSJ']);
            $endTime = $kzsj + 6 * 3600;
            $bl01Res = [];
            foreach ($bl01 as $b) {
                if ($b['BLZT'] != 9 && $b['BLLB'] == 294 && strpos($b['HJNR'], '抢救记录') !== false && strtotime($b['ZXSJ']) > $kzsj && strtotime($b['ZXSJ']) < $endTime) {
                    $bl01Res = $b;
                    break;
                }
            }
            if (empty($bl01Res)) {
                $basis[] = '开嘱时间【' . $item['KZSJ'] . '】';
                $basis[] = '抢救记录病程时间【无】';
            } elseif (strtotime($bl01Res['CJSJ']) < $kzsj || strtotime($bl01Res['CJSJ']) > $endTime) {
                $basis[] = '开嘱时间【' . $item['KZSJ'] . '】';
                $basis[] = '抢救记录病程时间【' . $bl01Res['CJSJ'] . '、超6小时】';
                $basisList[] = $basis;
            }
        }
        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['ZYH'],
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
        if (empty($caseRule[122]['is_shizhong']) || empty($info['bl01'])) {
            return [];
        }
        // 获取bl01表中的数据
        $bl01 = $info['bl01'] ?: []; // 病例数据
        $yzb = $info['yzb'] ?: []; // 医嘱本信息

        $yzbRes = [];
        foreach ($yzb as $y) {
            if ($y['YDYZLB'] == 901 && strpos($y['YZMC'], '转入') !== false) {
                $yzbRes[] = $y;
            }
        }
        if (empty($yzbRes)) {
            return $errorNotice;
        }
        foreach ($yzbRes as $item) {
            $basis = [];

            $xzjdsj = strtotime($item['XZJDSJ']);
            $endTime = $xzjdsj + 24 * 3600;

            $bl01Res = [];
            foreach ($bl01 as $b) {
                if ($b['MBLB'] == 30 && strpos($b['BLMC'], '转入记录') !== false && strtotime($b['ZXSJ']) > $xzjdsj && strtotime($b['ZXSJ']) < $endTime) {
                    $bl01Res = $b;
                    break;
                }
            }
            if (empty($bl01Res)) {
                $basis[] = '转入时间【' . $item['XZJDSJ'] . '】';
                $basis[] = '转入记录的病程时间【无】';
            } elseif (strtotime($bl01Res['CJSJ']) < $xzjdsj || strtotime($bl01Res['CJSJ']) > $endTime) {
                $basis[] = '转入时间【' . $item['XZJDSJ'] . '】';
                $basis[] = '转入记录的病程时间【' . $bl01Res['BLMC'] . '、' . $bl01Res['CJSJ'] . '超24小时】';
            }
            $basisList[] = $basis;
        }

        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['ZYH'],
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
        if (empty($caseRule[121]['is_shizhong']) || empty($info['bl01'])) {
            return [];
        }
        // 获取bl01表中的数据
        $bl01 = $info['bl01'] ?: []; // 病例数据

        $bl01esService = new ElasticsearchService('bl01_202303');
        // 获取护士分床时间
        $startTime = strtotime($info['HCRQ']); // 开始时间
        $basis[] = '护士分床时间【' . $info['HCRQ'] . '】';
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
            $bl01Res = [];
            foreach ($bl01 as $b) {
                if (in_array($b['MBLB'], [26, 30, 35, 98]) && strtotime($b['ZXSJ']) > $fromTime && strtotime($b['ZXSJ']) < $toTime) {
                    $bl01Res = $b;
                    break;
                }
            }
            $startTime = $toTime;
            $basis = [];
            $basis[] = '时间段【' . date('Y-m-d H:i:s', $fromTime) . '-' . date('Y-m-d H:i:s', $toTime) . '】';
            if (empty($bl01Res)) {
                $basis[] = '阶段小结时间【无】';
                $basislist[] = $basis;
            }
        }
        if ($flag) {
            $errorNotice = [
                'basis' => json_encode($basislist, 256),
                'JZHM' => $info['ZYH'],
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
     * 住院天数≥7天
     * 病程记录：副高或正高级职称一周2次，中级职称一周3次
     */
    public function zyts7($info = [])
    {

        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;

        if (!$caseRule[113]['is_shizhong'] || empty($info['bl01'])) {
            return [];
        }
        // 获取bl01表中的数据
        $bl01 = $info['bl01'] ?: []; // 病例数据
        $blsy = $info['blsy'] ?: []; // 病例数据
        // 住院天数大于7天
        if ($info['AAC04'] >= 7) {

            $startTime = $info['HCRQ'] ?: ''; // 护士分床时间就是住院开始时间
            $endTime = $info['AAC01']; // 结束时间

            if (empty($startTime) || empty($endTime)) {
                return $errorNotice;
            }
            while (true) {
                $toTime = strtotime($startTime) + 7 * 24 * 3600;

                // 从开始时间加7天超过住院结束时间，则失败
                if (strtotime($toTime) > strtotime($endTime)) {
                    break;
                }

                $bl01Res = [];
                foreach ($bl01 as $b) {
                    if ($b['BLLB'] == 294 && $b['ZXSJ'] > $startTime && $b['ZXSJ'] < $toTime) {
                        $bl01Res[] = $b;
                    }
                }

                $basis = [];
                if (empty($bl01Res)) {
                    $basis[] = '时间段【' . $startTime . '-' . $toTime . '】';
                    $basis[] = '病程记录【无】';
                    $basisList[] = $basis;
                } else {
                    $bl01294 = empty($bl01Res) ? [] : $bl01Res;
                    //  获取7天所有病程中的所有正副科的数据
                    $zheng = $zhong = [];
                    $zhengNum = $zhongnum = 0;

                    foreach ($bl01294 as $item) {


                        $code = [];
                        foreach ($blsy as $item) {
                            if ($item['BLBH'] == $item) {
                                $code[] = $item['SYYS'];
                            }
                        }
                        if ($blsy) {
                            $staff = Staff::query()->whereIn('code', $code)->get()->toArray();
                            foreach ($staff as $b) {
                                if (in_array($b['ygjb'], [1, 2])) {
                                    $zhengNum++;
                                } elseif ($b['ygjb'] == 6) {
                                    $zhongnum++;
                                }
                            }
                        }
                    }

                    if ($zhengNum < 2 && $zhongnum < 3) {
                        $basis[] = '时间段【' . substr($startTime, 0, 10) . ' / ' . date("Y-m-d", strtotime($toTime) - 1) . '】';
                        $basis[] = '正副高/' . $zhengNum . '次，中级职称/' . $zhongnum . '次';
                        $basis = array_merge($basis, $zheng, $zhong);
                        $basisList[] = $basis;
                    }
                }
                $startTime = $toTime;
            }
        }
        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['ZYH'],
                'rule_id' => 113,
                'code' => 'bingcheng_first',
                'error_field' => $caseRule[113]['title']
            ];
        }
        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 护士分床后48小时内，病程记录中，需要有一个 副高或正高签名（首次病程除外）
     */
    public function rule114($info = [])
    {

        $errorNotice = [];
        $basis = [];
        $caseRule = $this->caseRule;
        if (!$caseRule[114]['is_shizhong'] || empty($info['bl01'])) {
            return [];
        }
        // 获取bl01表中的数据
        $bl01 = $info['bl01'] ?: []; // 病例数据
        $blsy = $info['blsy'] ?: []; // 病例数据

        $startTime = $info['HCRQ']; // 护士分床时间就是住院的开始时间
        $basis[] = '护士分床时间【' . $startTime . '】';
        $endTime = strtotime($startTime) + 2 * 24 * 3600;

        // 从开始时间加7天超过住院结束时间，则失败
        if (empty($endTime) || empty($endTime)) {
            return $errorNotice;
        }

        $bl01Res = [];
        foreach ($bl01 as $b) {
            if ($b['BLLB'] == 294 && $b['ZXSJ'] > $startTime && $b['ZXSJ'] < $endTime) {
                $bl01Res[] = $b;
            }
        }

        if (empty($bl01Res[1])) {
            $basis[] = '病程记录【无】';
            $errorNotice = [
                'basis' => json_encode([$basis], 256),
                'JZHM' => $info['ZYH'],
                'rule_id' => 114,
                'code' => 'fenchuanghouchafang',
                'error_field' => $caseRule[114]['title']
            ];
        } else {
            $bl01294 = empty($bl01Res) ? [] : $bl01Res;
            $zheng = [];
            foreach ($bl01294 as $item) {
                $code = [];
                foreach ($blsy as $item) {
                    if ($item['BLBH'] == $item) {
                        $code[] = $item['SYYS'];
                    }
                }
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
                $basis[] = '副高或正高签名【无】';
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['ZYH'],
                    'rule_id' => 114,
                    'code' => 'bingcheng_first',
                    'error_field' => $caseRule[114]['title']
                ];
            }
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
        if (!$caseRule[114]['is_shizhong'] || empty($info['bl01'])) {
            return [];
        }
        // 获取bl01表中的数据
        $bl01 = $info['bl01'] ?: []; // 病例数据
        $sssq = $info['SSSQ'] ?: []; // 手术申请单
        $yzb = $info['yzb'] ?: []; // 医嘱本
        $resData = [];
        foreach ($sssq as $s) {
            if ($s['BLZT'] != 9 && $s['zfbz'] == 0) {
                $resData[] = $s;
            }
        }
        if (!empty($resData)) {
            foreach ($resData as $item) {
                // yzb中含有关键字   【拟***年*月*日 】的数据
                $yzbRes = [];
                foreach ($yzb as $y) {
                    if (
                        strpos($y['YZMC'], $item['NSSMC']) !== false &&
                        strpos($y['YZMC'], '拟') !== false &&
                        strpos($y['YZMC'], '年') !== false &&
                        strpos($y['YZMC'], '月') !== false &&
                        strpos($y['YZMC'], '日') !== false
                    ) {
                        $yzbRes[] = $y;
                    }
                }
                if (!empty($yzbRes)) {
                    $bl01Res = [];
                    // 术前小结及术前讨论结论记录
                    foreach ($bl01 as $b) {
                        if ($b['MBLB'] == 82 && strtotime($b['ZXSJ']) < strtotime($yzbRes[0]['KZSJ']) && $b['zfbz'] == 0) {
                            $bl01Res[] = $b;
                        }
                    }
                    if (empty($bl01Res)) {
                        $basis = [];
                        $basis[] = '手术名称【' . $item['NSSMC'] . '】';
                        $basis[] = '开嘱时间【' . $yzbRes[0]['KZSJ'] . '】';
                        $basis[] = '术前小结及术前讨论结论记录【无】';
                        $basisList[] = $basis;
                    } else {
                        $cjsj = $bl01Res[0]['CJSJ'];
                        if (strtotime($cjsj) > strtotime($resData[0]['KZSJ'])) {
                            $basis = [];
                            $basis[] = '手术名称【' . $item['NSSMC'] . '】';
                            $basis[] = '开嘱时间【' . $resData[0]['KZSJ'] . '】';
                            $basis[] = '术前小结及术前讨论结论记录【' . $cjsj . '不在开嘱时间之前】';
                            $basisList[] = $basis;
                        }
                    }
                }
            }
        }
        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['ZYH'],
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
        if (empty($caseRule[116]['is_shizhong']) || empty($info['bl01'])) {
            return [];
        }
        // 获取bl01表中的数据
        $bl01 = $info['bl01'] ?: []; // 病例数据
        $mzjl = $info['mzjl'] ?: []; // 手术申请单

        $bl01Res = [];
        foreach ($bl01 as $b) {
            if (
                $b['BLLB'] == 303 &&
                strpos($b['BLMC'], '手术记录') !== false &&
                $b['BLZT'] != 9 &&
                in_array($b['MBLB'], [306, 74])
            ) {
                $hjnr = self::analysisHjnr($b['HJNR']);
                $b['operation_time'] = $hjnr['operation_time'] ?: 0;
                $b['operation_end_time'] = $hjnr['operation_end_time'] ?: 0;
                $b['operation_handler'] = $hjnr['operation_handler'] ?: '';
                $bl01Res[] = $b;
            }
        }
        if (!empty($bl01Res)) {
            foreach ($bl01Res as $v) {
                $basis = [];
                $startTime = $v['operation_time'] ?: 0;
                // 获取麻醉记录
                $mzjlRes = [];
                foreach ($mzjl as $m) {
                    if (strtotime($m['OPERATESTARTTIME']) > $startTime || strtotime($m['OPERATESTARTTIME']) < $startTime + 24 * 3600) {
                        $mzjlRes = $m;
                        break;
                    }
                }
                if (!empty($mzjlRes)) {
                    $startTime = strtotime($mzjlRes['OPERATEENDTIME']);
                }
                if ($startTime) {
                    $basis[] = '手术日期【' . date("Y-m-d", $startTime) . '】';
                    $from = $startTime;
                    $to = $startTime + 48 * 3600;
                    $bl01Res1 = [];
                    foreach ($bl01 as $b) {
                        if ($b['MBLB'] == 54 && $b['ZXSJ'] > $from && $b['ZXSJ'] < $to && $b['BLZT'] != 9) {
                            $bl01Res1 = $b;
                            break;
                        }
                    }

                    if (empty($bl01Res1)) {
                        $basis[] = '术后首次病程【无】';
                        $basisList[] = $basis;
                    } elseif (strtotime($bl01Res1['CJSJ']) > 24 * 3600 + $startTime) {
                        $basis[] = '术后首次病程【' . $bl01Res1['BLMC'] . '、超时】';
                        $basisList[] = $basis;
                    }
                }
            }
        }
        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['ZYH'],
                'rule_id' => 116,
                'code' => 'fenchuanghouchafang',
                'error_field' => $caseRule[116]['title']
            ];
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

        $zyh = $info['ZYH'];
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        if (empty($caseRule[117]['is_shizhong']) || empty($info['bl01'])) {
            return [];
        }
        // 获取bl01表中的数据
        $bl01 = $info['bl01'] ?: []; // 病例数据
        $mzjl = $info['mzjl'] ?: []; // 麻醉记录
        $blsy = $info['blsy'] ?: []; // 病例签名
        $bl01Res = [];
        foreach ($bl01 as $b) {
            // 手术记录找术者：     表：EMR_BL_BL01 中【    MBLB=306    或     MBLB=74  或   bllb=303 含关键字“手术记录”  且 blzt不等于9】
            if (
                (in_array($b['MBLB'], [306, 74]) || ($b['BLLB'] == 303 && strpos($b['BLMC'], '手术记录') !== false)) &&
                $b['BLZT'] != 9
            ) {
                $hjnr = self::analysisHjnr($b['HJNR']);
                $b['operation_time'] = $hjnr['operation_time'] ?: 0;
                $b['operation_end_time'] = $hjnr['operation_end_time'] ?: 0;
                $b['operation_handler'] = $hjnr['operation_handler'] ?: '';
                $bl01Res[] = $b;
            }
        }

        if (!empty($bl01Res)) {
            foreach ($bl01Res as $b) {
                $startTime = $b['operation_time'];
                $start = $startTime;
                $end = $startTime + 48 * 3600;
                $mzjlRes = [];
                foreach ($mzjl as $m) {
                    if ($m['OPERATESTARTTIME'] > $start && $m['OPERATESTARTTIME'] < $end) {
                        $mzjlRes = $m;
                        break;
                    }
                }
                if (!empty($mzjlRes)) {
                    $startTime = $mzjlRes['OPERATEENDTIME'];
                }
                if (empty($startTime)) {
                    break;
                }
                $basis = [];
                // 如果手术时间存在，则获取手术时间48内的所有病程信息
                $endTime = $startTime + 48 * 3600;
                $bl01Res1 = [];
                foreach ($bl01 as $b1) {
                    if ($b1['BLLB'] == 294 && $b1['ZXSJ'] > $startTime && $b1['ZXSJ'] < $endTime && !in_array($b1['MBLB'], [32, 54]) && $b1['BLZT'] != 9) {
                        $bl01Res1[] = $b1;
                    }
                }
                // 如果病程信息不存在，则提示质控错误信息
                if (empty($bl01Res1)) {
                    $basis[0] = '手术结束时间【' . $startTime . '】';
                    $basis[1] = '术者【' . $b['operation_handler'] . '】';
                    $basis[2] = '病程记录【无】';
                }

                if ($basis) {
                    $basisList[] = $basis;
                }
            }
        }
        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $zyh,
                'rule_id' => 117,
                'code' => 'sq48',
                'error_field' => $caseRule[117]['title']
            ];
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
        if (empty($caseRule[119]['is_shizhong']) || empty($info['bl01'])) {
            return [];
        }
        // 获取bl01表中的数据
        $bl01 = $info['bl01'] ?: []; // 病例数据
        $yzb = $info['yzb'] ?: []; // 医嘱本
        $blsy = $info['blsy'] ?: [];
        $resData = [];
        foreach ($yzb as $b) {
            if (
                $b['YDYZLB'] == 901 &&
                strpos($b['YZMC'], 'YZMC') !== false &&
                strpos($b['YZMC'], 'PICC') == false &&
                strpos($b['YZMC'], '取消') == false &&
                $b['BLZT'] != 9
            ) {
                $resData[] = $b;
            }
        }

        if (!empty($resData)) {
            foreach ($resData as $item) {
                $basis = [];
                $XZJDSJ = strtotime(date("Y-m-d", strtotime($item['XZJDSJ'])));
                $basis[] = '医嘱会诊时间【' . $item['XZJDSJ'] . '】';
                if (!empty($XZJDSJ)) {
                    $bl01294 = [];
                    foreach ($bl01 as $b) {
                        if ($b['MBLB'] == 32 && $b['BLZT'] != 9 && $b['ZXSJ'] > $XZJDSJ && $b['ZXSJ'] < $XZJDSJ + 24 * 3600) {
                            $bl01294[] = $b;
                        }
                    }

                    if (!empty($bl01294)) {
                        $zheng = [];
                        foreach ($bl01294 as $item) {
                            $code = [];
                            foreach ($blsy as $item) {
                                if ($item['BLBH'] == $item) {
                                    $code[] = $item['SYYS'];
                                }
                            }
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
                            $basis[] = '会诊记录【无】';
                            $basisList[] = $basis;
                        }
                    } else {
                        $basis[] = '会诊记录【无】';
                        $basisList[] = $basis;
                    }
                }
            }
        }

        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['ZYH'],
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
     * 术后3天连续病程记录
     */
    public function rule118($info = [])
    {
        $zyh = $info['ZYH'];
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        if (empty($caseRule[117]['is_shizhong']) || empty($info['bl01'])) {
            return [];
        }
        // 获取bl01表中的数据
        $bl01 = $info['bl01'] ?: []; // 病例数据
        $mzjl = $info['mzjl'] ?: []; // 麻醉记录
        $blsy = $info['blsy'] ?: []; // 病例签名
        $bl01Res = [];
        foreach ($bl01 as $b) {
            // 手术记录找术者：     表：EMR_BL_BL01 中【    MBLB=306    或     MBLB=74  或   bllb=303 含关键字“手术记录”  且 blzt不等于9】
            if (
                (in_array($b['MBLB'], [306, 74]) || ($b['BLLB'] == 303 && strpos($b['BLMC'], '手术记录') !== false)) &&
                $b['BLZT'] != 9
            ) {
                $hjnr = self::analysisHjnr($b['HJNR']);
                $b['operation_time'] = $hjnr['operation_time'] ?: 0;
                $b['operation_end_time'] = $hjnr['operation_end_time'] ?: 0;
                $b['operation_handler'] = $hjnr['operation_handler'] ?: '';
                $bl01Res[] = $b;
            }
        }

        if (!empty($bl01Res)) {
            foreach ($bl01Res as $b) {
                $basis = [];
                $startTime = $b['operation_time'];
                $start = $startTime;
                $end = $startTime + 24 * 3600;

                $mzjlRes = [];
                foreach ($mzjl as $m) {
                    if ($m['OPERATESTARTTIME'] > $start && $m['OPERATESTARTTIME'] < $end) {
                        $mzjlRes = $m;
                        break;
                    }
                }
                if (!empty($mzjlRes)) {
                    $startTime = $mzjlRes['OPERATEENDTIME'];
                }
                if (empty($startTime)) {
                    break;
                }

                if ($startTime) {

                    $basis[] = '手术结束时间【' . $startTime . '】';
                    $cysj = strtotime(date("Y-m-d", strtotime($info['AAC01'])));
                    $startTime = strtotime(date("Y-m-d", $startTime));
                    // 出院当天, 查出院当天是否有病程
                    if ($cysj == $startTime) {
                        $from = $startTime;
                        $to = $startTime + 24 * 3600;
                        $bingcheng = $this->rule118Helper($bl01, $from, $to, 1);
                        if (empty($bingcheng)) {
                            $bingcheng = $this->rule118Helper($bl01, $from, $to);
                            if (empty($bingcheng)) {
                                $basis[] = '时间【' . date("Y-m-d", $from) . '】';
                                $basis[] = '病程记录【无】';
                            }
                        }
                    } else {
                        if ($cysj && $cysj >= $startTime + 24 * 3600) {

                            $from = $startTime + 24 * 3600;
                            $to = $startTime + 24 * 3600 * 2;
                            $bingcheng = $this->rule118Helper($info['ZYH'], $from, $to, 1);
                            if (empty($bingcheng)) {
                                $bingcheng = $this->rule118Helper($info['ZYH'], $from, $to);
                                if (empty($bingcheng)) {
                                    $basis[] = '时间【' . date("Y-m-d", $from) . '】';
                                    $basis[] = '病程记录【无】';
                                }
                            }
                        }
                        if ($cysj && $cysj >= $startTime + 2 * 24 * 3600) {
                            $from = $startTime + 24 * 3600 * 2;
                            $to = $startTime + 24 * 3600 * 3;
                            $bingcheng = $this->rule118Helper($info['ZYH'], $from, $to, 1);
                            if (empty($bingcheng)) {
                                $bingcheng = $this->rule118Helper($info['ZYH'], $from, $to);
                                if (empty($bingcheng)) {
                                    $basis[] = '时间【' . date("Y-m-d", $from) . '】';
                                    $basis[] = '病程记录【无】';
                                }
                            }
                        }
                        if ($cysj && $cysj >= $startTime + 3 * 24 * 3600) {

                            $from = $startTime + 24 * 3600 * 3;
                            $to = $startTime + 24 * 3600 * 4;
                            $bingcheng = $this->rule118Helper($info['ZYH'], $from, $to, 1);
                            if (empty($bingcheng)) {
                                $bingcheng = $this->rule118Helper($info['ZYH'], $from, $to);
                                if (empty($bingcheng)) {
                                    $basis[] = '时间【' . date("Y-m-d", $from) . '】';
                                    $basis[] = '病程记录【无】';
                                }
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
                'JZHM' => $info['ZYH'],
                'rule_id' => 118,
                'code' => 'sh3t',
                'error_field' => $caseRule[118]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * @param array $bl01
     * @param string $from
     * @param string $to
     * 规则118的辅助方法
     */
    public function rule118Helper($bl01 = [], $from = '', $to = '', $type = 0)
    {
        $blo1Res = [];
        foreach ($bl01 as $b) {
            if ($b['BLLB'] == 294 && strtotime($b['ZXSJ']) > $from && strtotime($b['ZXSJ']) < $to && $b['BLZT'] != 9 && !in_array($b['MBLB'], [54, 32])) {
                // 如果type=1则是获取包含创建条件的搜索
                if ($type == 1) {
                    if (strtotime($b['CJSJ']) > $from && strtotime($b['CJSJ']) < $to) {
                        $bl01must[] = $b;
                    }
                } else {
                    $bl01must[] = $b;
                }
            }

        }
        return $blo1Res;
    }

    /**
     * 检查报告单有ct（OCT除外）结果，24小时内要写病程记录
     * 或 24小时出入院记录  ,且   含 关键字“CT”（OCT除外）
     */
    public
    function checkCt($info = [])
    {
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        // 如果没有检查报告单，则不进行质控
        if (!$caseRule[102]['is_shizhong'] || empty($info['jcbgd'])) {
            return [];
        }

        // 病程信息
        $bc = $info['bc'] ?? [];

        foreach ($info['jcbgd'] as $item) {
            if (strpos($item['JCMC'], "CT") !== false && strpos($item['JCMC'], "OCT") === false) {
                $basis = [];
                $basis[] = '检查报告单时间【' . $item['BGSJ'] . '】';
                $item['BGSJ'] = strtotime($item['BGSJ']);
                $bgsj = strtotime($item['BGSJ']) + 24 * 3600;
                $resData = [];
                foreach ($bc as $bcItem) {
                    if (
                        in_array($bcItem['BLLB'], [294, 18]) &&
                        strpos($bcItem['HJNR'], 'CT') !== false &&
                        $bcItem['ZXSJ'] > $item['BGSJ'] &&
                        $bcItem['ZXSJ'] < $bgsj &&
                        $bcItem['BLZT'] != 9 &&
                        $bcItem['MBLB'] != 32 &&
                        strpos($bcItem['HJNR'], 'OCT') === false
                    ) {
                        $resData = $bcItem;
                        break;
                    }
                }
                $cjsjInt = strtotime($resData['CJSJ']);
                if (empty($cjsjInt)) {
                    $basis[] = '病程记录【无】';
                    $basisList[] = $basis;
                } elseif ($cjsjInt < $item['BGSJ'] || $cjsjInt > $bgsj) {
                    $basis[] = '病程记录【' . $resData['BLMC'] . '、超24小时】';
                    $basisList[] = $basis;
                }

                if (count($basisList)) {
                    $errorNotice = [
                        'basis' => json_encode($basisList, 256),
                        'JZHM' => $item['ZYH'],
                        'rule_id' => 102,
                        'code' => 'jcbgd',
                        'error_field' => $caseRule[102]['title']
                    ];
                }

            }
        }


        return $errorNotice;
    }

    /*
     * 检查报告单有mr结果，24小时内要写病程记录，或 24小时内出入院记录：关键字 "MR” 或 “磁共振”
     */
    public
    function checkMr($info = [])
    {
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        // 如果没有检查报告单，则不进行质控
        if (!$caseRule[103]['is_shizhong'] || empty($info['jcbgd'])) {
            return [];
        }

        // 病程信息
        $bc = $info['bc'] ?? [];

        foreach ($info['jcbgd'] as $item) {
            if (strpos($item['JCMC'], 'MR') !== false) {
                $basis = [];
                $basis[] = '检查报告单时间【' . $item['BGSJ'] . '】';
                $item['BGSJ'] = strtotime($item['BGSJ']);
                $bgsj = strtotime($item['BGSJ']) + 24 * 3600;
                // 病程记录
                $resData = [];
                foreach ($bc as $bcItem) {
                    if (
                        in_array($bcItem['BLLB'], [294, 18]) &&
                        (strpos($bcItem['HJNR'], 'MR') !== false || strpos($bcItem['HJNR'], '磁共振') !== false) &&
                        $bcItem['ZXSJ'] > $item['BGSJ'] &&
                        $bcItem['ZXSJ'] < $bgsj &&
                        $bcItem['BLZT'] != 9 &&
                        $bcItem['MBLB'] != 32
                    ) {
                        $resData = $bcItem;
                        break;
                    }
                }
                if (empty($resData)) {
                    $basis[] = '病程记录【无】';
                    $basisList[] = $basis;
                } elseif (strtotime($resData['CJSJ']) < $item['BGSJ'] || strtotime($resData['CJSJ']) > $bgsj) {
                    $basis[] = '病程记录【' . $resData['BLMC'] . '、超24小时】';
                    $basisList[] = $basis;
                }

                if (count($basisList)) {
                    $errorNotice = [
                        'basis' => json_encode($basisList, 256),
                        'JZHM' => $item['ZYH'],
                        'rule_id' => 103,
                        'code' => 'jcbgd',
                        'error_field' => $caseRule[103]['title']
                    ];
                }
            }
        }

        return $errorNotice;
    }

    /**
     * @return array
     * 有抗菌药，开嘱时间+24小时 内要有病程记录
     */
    public
    function kjy($info = []): array
    {
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        if (!$caseRule[109]['is_shizhong'] || empty($info['yzb'])) {
            return [];
        }
        $bc = $info['bc'] ?? [];
        $yzbInfo = $info['yzb'];
        $cqyzArr = [];
        $lsyzArr = [];
        foreach ($yzbInfo as $k => $v) {
            // 剔除医嘱本中的皮试数据
            if ($v['YYSX'] == 4 || $v['GYTJ'] == 167 || $v['YDYZLB'] == 901) {
                unset($yzbInfo[$k]);
                continue;
            }

            if ($v['YDYZLB'] != 901 || ($v['YDYZLB'] == 901 && strpos($v['YZMC'], '皮试')) === false) {

            } else {
                unset($yzbInfo[$k]);
                continue;
            }
            // 长期医嘱
            if ($v['YZQX'] == 1) {
                $cqyzArr[] = $v;
            }
            // 临时医嘱
            if ($v['YZQX'] == 2) {
                $lsyzArr[] = $v;
            }
        }

        // 长期医嘱   和  临时医嘱   药名+单次剂量【 YCJL】单位【JLDW】  一样，就把临时医嘱剔除，只保留长期医嘱的药名
        foreach ($cqyzArr as $k => $c1) {
            if (!empty($lsyzArr[$k])) {
                foreach ($c1 as $c2) {
                    foreach ($lsyzArr[$k] as $k1 => $l1) {
                        if ($c2['kjyw_name'] == $l1['kjyw_name'] && $c2['YCJL'] == $l1['YCJL'] && $c2['JLDW'] == $l1['JLDW']) {
                            unset($lsyzArr[$k][$k1]);
                        }
                    }
                }
            }
        }
        // 合并所有的医嘱本数据
        $allYzb = array_merge($cqyzArr, $lsyzArr);
        $medicianlKjyw = MedicinalInfo::query()->where(['type' => 1])->pluck('name')->toArray(); // 抗菌药物

        // 设置医嘱本信息中的抗菌药物
        foreach ($allYzb as $k3 => $item) {
            foreach ($medicianlKjyw as $h) {
                if (strpos($item['YZMC'], $h) !== false) {
                    $allYzb[$k3]['kjyw_name'] = $h;
                    break;
                }
            }
        }
        // 如果没有抗菌药物则不质控
        if (empty($kjyw_name)) {
            return [];
        }
        $resArr = [];
        foreach ($yzbInfo as $item) {
            $resArr[md5($item['YZMC'] . $item['JLDW'] . $item['SYPC'] . $item['YCJL'])] = $item;
        }

        foreach ($resArr as $item) {
            $v['KZSJ'] = strtotime($v['KZSJ']);
            $kzsj = strtotime($v['KZSJ']) + 24 * 3600;

            // 病程记录
            $resData = [];
            foreach ($bc as $v) {
                if ($item['BLLB'] == 294 && $item['CJSJ'] < $kzsj && strpos($item['HJNR'], $v['YZMC']) !== false && $item['BLZT'] != 9) {
                    $resData = $v;
                    break;
                }
            }

            $basis = [];
            $basis[] = '抗菌药名称【' . $v['YZMC'] . '】';
            $basis[] = '开嘱时间【' . $v['KZSJ'] . '】';
            if (!empty($resData)) {
                $basis[] = '病程记录时间【无】';
                $basisList[] = $basis;
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
     * @return array
     * 有化疗药，开嘱时间+24小时 内要有病程记录
     */
    public
    function hly($info = []): array
    {
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        if (!$caseRule[110]['is_shizhong'] || empty($info['yzb'])) {
            return [];
        }
        $bc = $info['bc'] ?? [];
        $yzbInfo = $info['yzb'];
        $cqyzArr = [];
        $lsyzArr = [];
        foreach ($yzbInfo as $k => $v) {
            if ($v['YYSX'] == 4 && $v['YDYZLB'] == 901) {
                unset($yzbInfo[$k]);
                continue;
            }
            // 长期医嘱
            if ($v['YZQX'] == 1) {
                $cqyzArr[] = $v;
            }
            // 临时医嘱
            if ($v['YZQX'] == 2) {
                $lsyzArr[] = $v;
            }
        }

        // 长期医嘱   和  临时医嘱   药名+单次剂量【 YCJL】单位【JLDW】  一样，就把临时医嘱剔除，只保留长期医嘱的药名
        foreach ($cqyzArr as $k => $c1) {
            if (!empty($lsyzArr[$k])) {
                foreach ($c1 as $c2) {
                    foreach ($lsyzArr[$k] as $k1 => $l1) {
                        if ($c2['hlyw_name'] == $l1['hlyw_name'] && $c2['YCJL'] == $l1['YCJL'] && $c2['JLDW'] == $l1['JLDW']) {
                            unset($lsyzArr[$k][$k1]);
                        }
                    }
                }
            }
        }
        // 合并所有的医嘱本数据
        $allYzb = array_merge($cqyzArr, $lsyzArr);
        $medicianlKjyw = MedicinalInfo::query()->where(['type' => 2])->pluck('name')->toArray(); // 抗菌药物

        // 设置医嘱本信息中的化疗药物
        foreach ($allYzb as $k3 => $item) {
            foreach ($medicianlKjyw as $h) {
                if (strpos($item['YZMC'], $h) !== false) {
                    $allYzb[$k3]['hlyw_name'] = $h;
                    break;
                }
            }
        }
        // 如果没有抗菌药物则不质控
        if (empty($kjyw_name)) {
            return [];
        }
        $resArr = [];
        foreach ($yzbInfo as $item) {
            $resArr[md5($item['YZMC'] . $item['JLDW'] . $item['SYPC'] . $item['YCJL'])] = $item;
        }

        foreach ($resArr as $item) {
            $v['KZSJ'] = strtotime($v['KZSJ']);
            $kzsj = strtotime($v['KZSJ']) + 24 * 3600;

            // 病程记录
            $resData = [];
            foreach ($bc as $v) {
                if ($item['BLLB'] == 294 && $item['CJSJ'] < $kzsj && strpos($item['HJNR'], $v['YZMC']) !== false && $item['BLZT'] != 9 && $item['MBLB'] != 32) {
                    $resData = $v;
                    break;
                }
            }

            $basis = [];
            $basis[] = '化疗药名称【' . $v['YZMC'] . '】';
            $basis[] = '开嘱时间【' . $v['KZSJ'] . '】';
            if (!empty($resData)) {
                $basis[] = '病程记录时间【无】';
                $basisList[] = $basis;
            }
        }

        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['ZYH'],
                'rule_id' => 110,
                'code' => 'hly',
                'error_field' => $caseRule[110]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * @param string $info
     * @return array
     * 术前24小时内，有术者查房（搜签名）
     */
    public
    function sq24($info = [])
    {
        $zyh = $info['ZYH'];
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        if (!$caseRule[111]['is_shizhong'] || empty($info['bl01'])) {
            return [];
        }
        // 获取bl01表中的数据
        $bl01 = $info['bl01'] ?: []; // 病例数据
        $mzjl = $info['mzjl'] ?: []; // 麻醉记录
        $blsy = $info['blsy'] ?: []; // 病例签名
        $bl01Res = [];
        foreach ($bl01 as $b) {
            // 手术记录找术者：     表：EMR_BL_BL01 中【    MBLB=306    或     MBLB=74  或   bllb=303 含关键字“手术记录”  且 blzt不等于9】
            if (
                (in_array($b['MBLB'], [306, 74]) || ($b['BLLB'] == 303 && strpos($b['BLMC'], '手术记录') !== false)) &&
                $b['BLZT'] != 9
            ) {
                $hjnr = self::analysisHjnr($b['HJNR']);
                $b['operation_time'] = $hjnr['operation_time'] ?: 0;
                $b['operation_end_time'] = $hjnr['operation_end_time'] ?: 0;
                $b['operation_handler'] = $hjnr['operation_handler'] ?: '';
                $bl01Res[] = $b;
            }
        }

        if (!empty($bl01Res)) {
            foreach ($bl01Res as $b) {
                $operation_time = $b['operation_time'];
                $start = $operation_time;
                $end = $operation_time + 24 * 3600;
                $mzjlRes = [];
                foreach ($mzjl as $m) {
                    if ($m['OPERATESTARTTIME'] > $start && $m['OPERATESTARTTIME'] < $end) {
                        $mzjlRes = $m;
                        break;
                    }
                }

                if (!empty($mzjlRes)) {
                    $startTime = $mzjlRes['OPERATESTARTTIME'];
                } else {
                    $startTime = $operation_time;
                }
                if (empty($startTime)) {
                    break;
                }
                $basis = [];
                // 如果手术时间存在，则获取手术时间24内的所有病程信息
                $endTime = strtotime($startTime) - 24 * 3600;

                $bl01294 = [];
                foreach ($bl01 as $item) {
                    if ($item['BLLB'] == 294 && $item['ZXSJ'] > $endTime && $item['ZXSJ'] < $startTime && !in_array($item['MBLB'], [32, 54]) && $item['BLZT'] != 9) {
                        $bl01294[] = $item;
                    }
                }
                // 如果病程信息不存在，则提示质控错误信息
                $zfzhi = [];
                if (!empty($bl01294)) {
                    $zfzhi = $this->shuzhe($bl01294, $b['operation_handler'], $blsy);
                }
                // 检查是否有正副高职签字的病程
                if (empty($bl01294) || empty($zfzhi[0])) {
                    $basis[0] = '手术开始时间【' . $startTime . '】';
                    $basis[1] = '术者【' . $b['operation_handler'] . '】';

                    // 查找24之后的病程信息

                    $bl01294 = [];
                    foreach ($bl01 as $item) {
                        if ($item['BLLB'] == 294 && $item['ZXSJ'] < $endTime && $item['ZXSJ'] > $startTime && !in_array($item['MBLB'], [32, 54]) && $item['BLZT'] != 9) {
                            $bl01294[] = $item;
                        }
                    }
                    if (empty($bl01294)) {
                        $basis[2] = '病程记录【无】';
                    } else {
                        $zfzhi = $this->shuzhe($bl01294, $b['operation_handler'], $blsy);
                        if (!empty($zfzhi[0])) {
                            $basis[2] = '病程记录【' . $zfzhi[1] . '、 超24小时】';
                        } else {
                            $basis[2] = '病程记录【无】';
                        }
                    }
                }

                if ($basis) {
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
     * @param string $hjnr
     * @return array|int
     * 解析病例内容
     */
    public static function analysisHjnr($hjnr = '')
    {
        if (empty($hjnr)) {
            return [];
        }
        $caseContent = str_replace("\r\n", "", $hjnr);
        $caseContent = str_replace("\n", "", $caseContent);
        $caseContent = str_replace('手术日期：', "|&|手术日期||", $caseContent);
        $caseContent = str_replace('手术时间：', "|&|手术时间||", $caseContent);
        $caseContent = str_replace('术前诊断：', "|&|术前诊断||", $caseContent);
        $caseContentArr = explode("|&|", $caseContent);
        if (empty($caseContentArr[1])) {
            return 0;
        }

        $timeRes = explode('||', $caseContentArr[1]);
        $operationDate = trim($timeRes[1]);

        preg_match_all('/\d{4}-\d{1,2}-\d{1,2}/', $operationDate, $res);
        preg_match_all('/\d{4}年\d{1,2}月\d{1,2}日/', $operationDate, $res1);

        $time = 0;
        if (!empty($res[0])) {
            $time = strtotime($operationDate);
        } elseif (!empty($res1[0])) {
            $operationDateRes = date_parse_from_format("Y年m月d日", $operationDate);
            $time = mktime(0, 0, 0, $operationDateRes['month'], $operationDateRes['day'], $operationDateRes['year']);
        }

        $endTime = 0;
        if (!empty($caseContentArr[2])) {
            $endTimeArr = explode('||', $caseContentArr[2]);
            if (!empty($endTimeArr) && !empty($time)) {
                $endTimeArr[1] = str_replace(' ', '', $endTimeArr[1]);
                $endTimeArr = explode('-', $endTimeArr[1]);
                if (!empty($endTimeArr[1])) {
                    $endTime = date("Y-m-d", $time) . ' ' . $endTimeArr[1];
                    $endTime = strtotime($endTime);
                }
            }
        }

        // 手术者
        $hjnr = str_replace('主治医生', '', $hjnr);
        $caseContent = str_replace(['手术者:', '手术者：'], "|&|手术者||", $hjnr);
        $caseContent = str_replace('助手：', "|&|助手||", $caseContent);
        $caseContentArr = explode("|&|", $caseContent);
        $operationHandler = '';
        if (!empty($caseContentArr[1])) {
            $operationHandlerData = explode('||', $caseContentArr[1]);
            $operationHandler = !empty($operationHandlerData[1]) ? $operationHandlerData[1] : '';
        }

        $eidtData = ['operation_time' => ($time ?: 0), 'operation_end_time' => $endTime, 'operation_handler' => $operationHandler];
        return $eidtData;
    }

    /**
     * @param array $res
     * @param string $operator
     * @param array $blsy
     * @return array
     */
    public function shuzhe($res = [], $operator = '', $blsy = [])
    {
        $zhengName = '';
        $cjsj = '';
        foreach ($res as $r) {
            if ($blsy) {
                $code = [];
                foreach ($blsy as $item) {
                    if ($item['BLBH'] == $item) {
                        $code[] = $item['SYYS'];
                    }
                }
                $staff = Staff::query()->whereIn('code', $code)->get()->toArray();
                foreach ($staff as $b) {
                    if ($b['name'] == $operator) {
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
    public function getZhengFu($res = [], $blsy = [])
    {
        $zhengName = '';
        $fuName = '';
        $cjsj = '';
        foreach ($res as $r) {

            $code = [];
            foreach ($blsy as $item) {
                if ($item['BLBH'] == $item) {
                    $code[] = $item['SYYS'];
                }
            }
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
     * @param array $zyh
     * @return array
     * 术后24小时内，有术者查房（搜签名）
     */
    public
    function sh24($info = [])
    {

        $zyh = $info['ZYH'];
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        if (!$caseRule[112]['is_shizhong'] || empty($info['bl01'])) {
            return [];
        }
        // 获取bl01表中的数据
        $bl01 = $info['bl01'] ?: []; // 病例数据
        $mzjl = $info['mzjl'] ?: []; // 麻醉记录
        $blsy = $info['blsy'] ?: []; // 病例签名
        $bl01Res = [];
        foreach ($bl01 as $b) {
            // 手术记录找术者：     表：EMR_BL_BL01 中【    MBLB=306    或     MBLB=74  或   bllb=303 含关键字“手术记录”  且 blzt不等于9】
            if (
                (in_array($b['MBLB'], [306, 74]) || ($b['BLLB'] == 303 && strpos($b['BLMC'], '手术记录') !== false)) &&
                $b['BLZT'] != 9
            ) {
                $hjnr = self::analysisHjnr($b['HJNR']);
                $b['operation_time'] = $hjnr['operation_time'] ?: 0;
                $b['operation_end_time'] = $hjnr['operation_end_time'] ?: 0;
                $b['operation_handler'] = $hjnr['operation_handler'] ?: '';
                $bl01Res[] = $b;
            }
        }

        if (!empty($bl01Res)) {
            foreach ($bl01Res as $b) {
                $operation_time = $b['operation_time'];
                $start = $operation_time;
                $end = $operation_time + 24 * 3600;
                $mzjlRes = [];
                foreach ($mzjl as $m) {
                    if ($m['OPERATESTARTTIME'] > $start && $m['OPERATESTARTTIME'] < $end) {
                        $mzjlRes = $m;
                        break;
                    }
                }

                if (!empty($mzjlRes)) {
                    $startTime = $mzjlRes['OPERATESTARTTIME'];
                } else {
                    $startTime = $operation_time;
                }
                if (empty($startTime)) {
                    break;
                }
                $basis = [];
                // 如果手术时间存在，则获取手术时间24内的所有病程信息
                $endTime = $end;

                $bl01294 = [];
                foreach ($bl01 as $item) {
                    if ($item['BLLB'] == 294 && $item['ZXSJ'] > $startTime && $item['ZXSJ'] < $endTime && !in_array($item['MBLB'], [32, 54]) && $item['BLZT'] != 9) {
                        $bl01294[] = $item;
                    }
                }
                // 如果病程信息不存在，则提示质控错误信息
                $zfzhi = [];
                if (!empty($bl01294)) {
                    $zfzhi = $this->shuzhe($bl01294, $b['operation_handler'], $blsy);
                }
                // 检查是否有正副高职签字的病程
                if (empty($bl01294) || empty($zfzhi[0])) {
                    $basis[0] = '手术开始时间【' . $startTime . '】';
                    $basis[1] = '术者【' . $b['operation_handler'] . '】';

                    // 查找24之后的病程信息

                    $bl01294 = [];
                    foreach ($bl01 as $item) {
                        if ($item['BLLB'] == 294 && $item['ZXSJ'] > $startTime && !in_array($item['MBLB'], [32, 54]) && $item['BLZT'] != 9) {
                            $bl01294[] = $item;
                        }
                    }
                    if (empty($bl01294)) {
                        $basis[2] = '病程记录【无】';
                    } else {
                        $zfzhi = $this->shuzhe($bl01294, $b['operation_handler'], $blsy);
                        if (!empty($zfzhi[0])) {
                            $basis[2] = '病程记录【' . $zfzhi[1] . '、 超24小时】';
                        } else {
                            $basis[2] = '病程记录【无】';
                        }
                    }
                }

                if ($basis) {
                    $basisList[] = $basis;
                }
            }
        }
        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $zyh,
                'rule_id' => 112,
                'code' => 'sh24',
                'error_field' => $caseRule[112]['title']
            ];
        }
        return $errorNotice;
    }


}

