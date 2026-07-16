<?php

namespace App\Services;

use App\Model\BLLB292;
use App\Model\MainDiagnosis;
use App\Model\OtherDiagnosis;
use App\Model\RuleWordMap;
use App\Model\Yzb;

class BlHomeQualityService
{
    public static $noPhone = [11111111111, 12345678911, 1111111, 1234567];

    public static $ruleAct = [
        // 新增规则
        1443, 1444, 1445, 1446, 1447, 1448, 1449, 1450, 1451, 1452, 1453, 1454, 1455, 1456, 1457, 1458, 1459, 1461, 1462, 1463, 1464, 1466,
        1467, 1468, 1469, 1470, 1471, 1472, 1473, 1474, 1476, 1477, 1478, 1479, 1481, 1482, 1483, 1484, 1485, 1486, 1487, 1488, 1489, 1490,
        1491, 1492, 1493, 1494, 1495, 1496, 1497, 1498, 1499, 1500, 1501, 1502, 1503, 1504, 1505, 1506, 1507, 1508, 1509, 1510, 1511, 1514, 1547, 1548,
        1551, 1552, 1553, 1554, 1555, 1556, 1557, 1558, 1559, 1560,
        // 原有规则修改
        1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 16, 18, 20, 21, 23, 25, 31, 36, 40, 42, 43, 44, 47, 49, 50, 51, 65, 72,
        1344, 1345, 1346, 1350, 1351, 1352, 1367, 1419, 1420, 1422, 1423, 1427, 1429,
    ];

    /**
     * 病案首页质控
     * @param $data
     * @param $item
     * @return bool
     */
    public function homeQuality($data, $item)
    {
        // 方法名
        $method = 'rule' . $item['id'];
        // 验证方法是否存在
        if (!method_exists(new BlHomeQualityService(), $method)) {
            return false;
        }
        $res = $this->$method($data, $item);
        return $res;
    }

    // 切口等级为1，2，3者愈合类别不能为空
    public function rule1560($data, $item)
    {
        if (empty($data['operation'])) {
            return false;
        }
        foreach ($data['operation'] as $item) {
            if (!empty($item['QKDJ']) && !empty($item['YHDJ']) && in_array($item['QKDJ'], [1, 2, 3]) && empty($item['YHDJ'])) {
                return true;
            }
        }
        return false;
    }

    // 所有的护理天数只能是数字，不可以为横杠，可以为空
    public function rule1559($data, $item)
    {
        if (empty($data['TJHL']) && empty($data['YJHL']) && empty($data['EJHL']) && empty($data['SJHL'])) {
            return false;
        }
        if (trim($data['TJHL']) == '-' || trim($data['YJHL']) == '-' || trim($data['EJHL']) == '-' || trim($data['SJHL']) == '-') {
            return true;
        }
        return false;
    }

    // 年龄应大于等于0 且小于等于150
    public function rule1558($data, $item)
    {
        if ($data['AAA04'] > 0 && $data['AAA04'] <= 150) {
            return false;
        }
        return true;
    }

    // 年龄小于1周岁即年龄为0时，（年龄不足1周岁的）月龄不能为空
    // todo  月龄没有，先不做
    public function rule1557($data, $item)
    {
        if ($data['AAA04']) {
            return false;
        }


        return false;
    }

    // 年龄等于入院日期减出生日期（误差范围1岁）
    public function rule1556($data, $item)
    {
        if (!$data['AAA04']) {
            return false;
        }

        if (!in_array(
            intval(date("Y", strtotime($data['AAB01'])) - date("Y", strtotime($data['AAA03']))),
            [$data['AAA04'], $data['AAA04'] + 1, $data['AAA04'] - 1]
        )) {
            return true;
        }
        return false;
    }

    // 主要诊断中出现了C00到D48且为首次入院（住院次数=1），病理诊断编码【ABF01C】必须填写
    public function rule1555($data, $item)
    {
        $ICD10_ID1 = array_filter($data['diagnosis'], function ($item) {
            return $item['class'] == 'main' ? $item['ICD10_ID1'] : false;
        });
        $wordMap = RuleWordMap::query()->where(['id' => $item['relation_rule']])->first()->toArray();
        $res = array_intersect(array_column($ICD10_ID1, 'ICD10_ID1'), explode(',', $wordMap['keyword']));
        if ($data['AAA29'] == 1 && $res && empty($data['ABF01C'])) {
            return true;
        }

        return false;
    }

    // 女性出院诊断一般不应编"K40"(腹股沟疝)。
    public function rule1554($data, $item)
    {
        if ($data['AAA02C'] !== 2) {
            return false;
        }
        $ICD10_ID1 = array_column($data['diagnosis'], 'ICD10_ID1');
        if (in_array('K40', $ICD10_ID1)) {
            return true;
        }
        return false;
    }

    // 男性出院诊断一般不应为C50(乳房恶性肿瘤)
    public function rule1553($data, $item)
    {
        if ($data['AAA02C'] !== 1) {
            return false;
        }
        $ICD10_ID1 = array_column($data['diagnosis'], 'ICD10_ID1');
        if (in_array('C50', $ICD10_ID1)) {
            return true;
        }
        return false;
    }

    // 男性出院诊断一般不应编"N60-N64"(乳房疾患)。
    public function rule1552($data, $item)
    {
        if ($data['AAA02C'] !== 1) {
            return false;
        }
        $ICD10_ID1 = array_column($data['diagnosis'], 'ICD10_ID1');
        $res = array_intersect($ICD10_ID1, ['N60', 'N61', 'N62', 'N63', 'N64']);
        if (!$res) {
            return false;
        }
        return true;
    }

    //化疗(Z51)透析(Z49)不应该作为死亡病人（医嘱中含"死亡“）的主要诊断编码，
    public function rule1551($data, $item)
    {
        $yzbesService = new ElasticsearchService('yzb_2023');

        $params = $yzbesService
            ->queryByMust(['terms' => ['YZZT' => [0, 1, 5]]])
            ->queryByMust(['term' => ['ZYH' => $data['MED_REC_ID']]])
            ->queryByMust(['match_phrase' => ['YZMC' => '死亡']])
            ->getParams();
        $yzb = app('es')->search($params);
        $yzb = $yzbesService->getDataByEs($yzb);
        if (empty($yzb[1])) {
            return false;
        }
        $ICD10_ID1 = array_filter($data['diagnosis'], function ($item) {
            return $item['class'] == 'main' ? $item['ICD10_ID1'] : false;
        });

        $res = array_intersect(array_column($ICD10_ID1, 'ICD10_ID1'), ['Z49', 'Z51']);
        if ($res) {
            return true;
        }
        return false;
    }


    public function rule1548($data, $item)
    {
        // 其他诊断
        $ICD10_ID1 = array_filter($data['diagnosis'], function ($item) {
            return $item['class'] == 'other' ? $item['ICD10_ID1'] : false;
        });
        if (in_array('Z37', $ICD10_ID1) || (!$data['AAA04'] && $data['AAA40'] <= 28) || (strtotime($data['AAB01']) - strtotime($data['AAA03']) < 28 * 24 * 3600)) {
            if (empty($data['AAA42'])) {
                return true;
            }
        }
        return false;
    }

    // 当门（急）诊诊断编码 或 主要诊断编码 或 其它诊断编码出现P10～P15时，入院日期减出生日期必须≤28天 且（年龄不足1周岁的）年龄必须≤28天
    public function rule1547($data, $item)
    {
        $ICD10_ID1 = array_column($data['diagnosis'], 'ICD10_ID1');

        $res = array_intersect($ICD10_ID1, ['P10', 'P11', 'P12', 'P13', 'P14', 'P15']);
        if (empty($res)) {
            return false;
        }

        if (!$data['AAA04'] && $data['AAA40'] <= 28 && (strtotime($data['AAB01']) - strtotime($data['AAA03']) < 28 * 24 * 3600)) {
            return false;
        }
        return true;
    }


    /**
     * 组织机构代码
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1($data, $item)
    {
        $UNT_ID = $data['UNT_ID'] ?? '';
        if (mb_strlen($UNT_ID) < 6 || mb_strlen($UNT_ID) > 22 || $UNT_ID == 123456) {
            return true;
        }
        return false;
    }

    /**
     * 病案号
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule2($data, $item)
    {
        if (empty($data['AAA28'])) {
            return true;
        }
        return false;
    }

    /**
     * 医疗机构名称
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule3($data, $item)
    {
        if (empty($data['ZA03'])) {
            return true;
        }
        $res = preg_match('/^[\x7f-\xff]+$/', $data['ZA03']);
        if (mb_strlen($data['ZA03']) < 4 || $data['ZA03'] > 80 || !$res) {
            return true;
        }
        return false;
    }

    /**
     * 住院次数
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule4($data, $item)
    {
        $AAA29 = $data['AAA29'] ?? 0;
        if (!preg_match("/^[1-9][0-9]*$/", $AAA29)) {
            return true;
        }
        return false;
    }

    /**
     * 入院时间
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule5($data, $item)
    {
        if (empty($data['AAB01']) || $data['AAB01'] >= $data['AAC01']) {
            return true;
        }
        return false;
    }

    /**
     * 健康卡号
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule6($data, $item)
    {
//        if ($data[''] === '' || $data[''] === null) {
//            return true;
//        }
        return false;
    }

    /**
     * 患者姓名
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule7($data, $item)
    {
        $XM = $data['AAA01'];
        if (mb_strlen($XM) < 2 || mb_strlen($XM) > 40) {
            return true;
        } else {
            // 获取第一位
            $firstStr = mb_substr($XM, 0, 1);
            // 获取最后一位
            $xmLen = mb_strlen($XM);
            $endStr = mb_substr($XM, $xmLen - 1, 1);

            $res1 = PublicService::pregMatchTszf($firstStr, 'digit');
            $res2 = PublicService::pregMatchTszf($firstStr, 'punct');

            $res3 = PublicService::pregMatchTszf($endStr, 'digit');
            $res4 = PublicService::pregMatchTszf($endStr, 'punct');
            if ($res1 || $res2 || $res3 || $res4) {
                return true;
            }
        }

        return false;
    }

    /**
     * 出生地省
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule8($data, $item)
    {
        if (empty($data['AAA09']) || is_numeric($data['AAA09'])) {
            return true;
        }
        return false;
    }

    /**
     * 出生地市
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1344($data, $item)
    {
        if (empty($data['AAA10']) || is_numeric($data['AAA10'])) {
            return true;
        }
        return false;
    }

    /**
     * 出生地县
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1345($data, $item)
    {
        if (empty($data['AAA11']) || is_numeric($data['AAA11'])) {
            return true;
        }
        return false;
    }

    /**
     * 籍贯省
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule9($data, $item)
    {
        if (empty($data['AAA43']) || is_numeric($data['AAA43'])) {
            return true;
        }
        return false;
    }

    /**
     * 籍贯市
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1346($data, $item)
    {
        if (empty($data['AAA44']) || is_numeric($data['AAA44'])) {
            return true;
        }
        return false;
    }

    /**
     * 民族
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule10($data, $item)
    {
        if (empty($data['AAA06C'])) {
            return true;
        }
        $AAA06C = config('dictionaries.AAA06C');
        if (empty($AAA06C[$data['AAA06C']])) {
            return true;
        }
        return false;
    }

    /**
     * 身份证号
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule11($data, $item)
    {
        if (empty($data['AAA07'])) {
            return true;
        }
        if (mb_strlen($data['AAA07']) != 15 && mb_strlen($data['AAA07']) != 18) {
            return true;
        }
        $AAA07 = !empty($data['AAA07']) ? trim($data['AAA07']) : '';
        if ($data['AAC11C'] != 287 && mb_strlen($AAA07) != 15 && mb_strlen($AAA07) != 18) {
            return true;
        }

        // 匹配身份证号的正确性
        $pattern = "/^[1-9]\d{5}(18|19|20|21|22)?\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{3}(\d|[Xx])$/";
        $pregRes = preg_match($pattern, $AAA07);
        if (!$pregRes && $AAA07 != '-') {
            return true;
        }
        return false;
    }

    /**
     * 职业
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule12($data, $item)
    {
        if (empty($data['AAA18C'])) {
            return true;
        }
        $AAA18C = config('dictionaries.AAA18C');
        if (empty($AAA18C[$data['AAA18C']])) {
            return true;
        }
        return false;
    }

    /**
     * 婚姻状况
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule13($data, $item)
    {
        if (empty($data['AAA08C'])) {
            return true;
        }
        $AAA08C = config('dictionaries.AAA08C');
        if (empty($AAA08C[$data['AAA08C']])) {
            return true;
        }
        return false;
    }

    /**
     * 现住址省
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule14($data, $item)
    {
        if (empty($data['AAA48']) || is_numeric($data['AAA48'])) {
            return true;
        }
        return false;
    }

    /**
     * 现住址市
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1350($data, $item)
    {
        if (empty($data['AAA49']) || is_numeric($data['AAA49'])) {
            return true;
        }
        return false;
    }

    /**
     * 现住址县
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1351($data, $item)
    {
        if (empty($data['AAA50']) || is_numeric($data['AAA50'])) {
            return true;
        }
        return false;
    }

    /**
     * 现住址详细地址
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1352($data, $item)
    {
        if (empty($data['AAA15']) || is_numeric($data['AAA15'])) {
            return true;
        }
        return false;
    }

    /**
     * 电话
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule15($data, $item)
    {
        if (empty($data['AAA51'])) {
            return true;
        }
        $res1 = preg_match('/^1[0-9]{10}$/', $data['AAA51']);
        $res2 = preg_match('/^[0-9]{4}[\-][0-9]{7}$/', $data['AAA51']);
        $res3 = preg_match('/^[0-9]{7}$/', $data['AAA51']);
        if (!$res1 && !$res2 && !$res3 && $data['AAA51'] != '-' && in_array($data['AAA51'], self::$noPhone)) {
            return true;
        }
        return false;
    }

    /**
     * 现住址邮政编码
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule16($data, $item)
    {
        if (empty($data['AAA17C'])) {
            return true;
        } else {
            if (mb_strlen($data['AAA17C']) != 6 || $data['AAA17C'] == 123456) {
                return true;
            }
        }
        return false;
    }

    /**
     * 户籍省
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule17($data, $item)
    {
        if (empty($data['AAA45']) || is_numeric($data['AAA45'])) {
            return true;
        }
        return false;
    }

    /**
     * 户籍市
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1347($data, $item)
    {
        if (empty($data['AAA46']) || is_numeric($data['AAA46'])) {
            return true;
        }
        return false;
    }

    /**
     * 户籍县
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1348($data, $item)
    {
        if (empty($data['AAA47']) || is_numeric($data['AAA47'])) {
            return true;
        }
        return false;
    }

    /**
     * 户籍详细地址
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1349($data, $item)
    {
        if (empty($data['AAA12']) || is_numeric($data['AAA12'])) {
            return true;
        }
        return false;
    }

    /**
     * 户籍邮编
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule18($data, $item)
    {
        if (empty($data['AAA14C'])) {
            return true;
        } else {
            if (mb_strlen($data['AAA14C']) != 6 || $data['AAA14C'] == 123456) {
                return true;
            }
        }
        return false;
    }

    /**
     * 工作单位及地址
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule19($data, $item)
    {
        if (empty($data['AAA19'])) {
            return true;
        }
        return false;
    }

    /**
     * 单位电话
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule20($data, $item)
    {
        if (empty($data['AAA20'])) {
            return true;
        }
        $res1 = preg_match('/^1[0-9]{10}$/', $data['AAA20']);
        $res2 = preg_match('/^[0-9]{4}[\-][0-9]{7}$/', $data['AAA20']);
        $res3 = preg_match('/^[0-9]{7}$/', $data['AAA20']);
        if (!$res1 && !$res2 && !$res3 && $data['AAA20'] != '-' && in_array($data['AAA20'], self::$noPhone)) {
            return true;
        }
        return false;
    }

    /**
     * 单位邮编
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule21($data, $item)
    {
        if (empty($data['AAA21C'])) {
            return true;
        }
        if (mb_strlen($data['AAA21C']) != 6 || $data['AAA21C'] == 123456) {
            return true;
        }
        return false;
    }

    /**
     * 联系人姓名
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule22($data, $item)
    {
        if (empty($data['AAA22'])) {
            return true;
        }
        return false;
    }

    /**
     * 联系人关系
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule23($data, $item)
    {
        $AAA23C = config('dictionaries.AAA23C');
        if (empty($data['AAA23C'])) {
            return true;
        } else {
            if (empty($AAA23C[$data['AAA23C']])) {
                return true;
            }
        }
        return false;
    }

    /**
     * 联系人地址
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule24($data, $item)
    {
        if (empty($data['AAA24']) || is_numeric($data['AAA24'])) {
            return true;
        }
        return false;
    }

    /**
     * 联系人电话
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule25($data, $item)
    {
        if (empty($data['AAA25'])) {
            return true;
        }
        $res1 = preg_match('/^1[0-9]{10}$/', $data['AAA25']);
        $res2 = preg_match('/^[0-9]{4}[\-][0-9]{7}$/', $data['AAA25']);
        $res3 = preg_match('/^[0-9]{7}$/', $data['AAA25']);
        if (!$res1 && !$res2 && !$res3 && $data['AAA25'] != '-' && in_array($data['AAA25'], self::$noPhone)) {
            return true;
        }
        return false;
    }

    /**
     * 性别
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule26($data, $item)
    {
        $AAA02C = config('dictionaries.AAA02C');
        if (empty($AAA02C[$data['AAA02C']])) {
            return true;
        }
        return false;
    }

    /**
     * 出生日期
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule27($data, $item)
    {
        if (empty($data['AAA03'])) {
            return true;
        }
        return false;
    }

    /**
     * 年龄
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule28($data, $item)
    {
        if (empty($data['AAA04']) && empty($data['AAA40'])) {
            return true;
        }
        return false;
    }

    /**
     * 国籍
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule29($data, $item)
    {
        $AAA05C = config('dictionaries.AAA05C');
        if (empty($AAA05C[$data['AAA05C']])) {
            return true;
        }
        return false;
    }

    /**
     * 入院科别
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule30($data, $item)
    {
        if (empty($data['AAB02C']) || $data['AAB02C'] == '-' || $data['AAB02C'] == '--') {
            return true;
        }
        return false;
    }

    /**
     * 入院途径
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule31($data, $item)
    {
        if (empty($data['AAB06C'])) {
            return true;
        }
        $AAB06C = config('dictionaries.AAB06C');
        if (empty($AAB06C[$data['AAB06C']])) {
            return true;
        }
        return false;
    }

    /**
     * 入院病房
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule32($data, $item)
    {
        if (empty($data['BFRY'])) {
            return true;
        }
        return false;
    }

    /**
     * 转科科别
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule33($data, $item)
    {
        if (empty($data['ZKKB'])) {
            return true;
        }
        return false;
    }

    /**
     * 出院科别
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule34($data, $item)
    {
        if (empty($data['AAC02C']) || $data['AAC02C'] == '-' || $data['AAC02C'] == '--') {
            return true;
        }
        return false;
    }

    /**
     * 实际住院天数
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule36($data, $item)
    {
        if (!preg_match('/^[1-9]\d*$/', $data['AAC04'])) {
            return true;
        }
        return false;
    }

    /**
     * 门(急)诊诊断编码
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule37($data, $item)
    {
        if (empty($data['ABA01C'])) {
            return true;
        }
        return false;
    }

    /**
     * 门(急)诊诊断名称
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule38($data, $item)
    {
        if (empty($data['ABA01N'])) {
            return true;
        }
        return false;
    }

    /**
     * 出院主要诊断编码
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule39($data, $item)
    {
        if (empty($data['ABC01C'])) {
            return true;
        }

        $res = false;
        $arr = ['B95', 'B96', 'B97'];
        foreach ($arr as $val) {
            if (stripos($data['ABC01C'], $val) === 0) {
                $res = true;
                break;
            }
        }
        return $res;
    }

    /**
     * 出院主要诊断名称
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule40($data, $item)
    {
        // 查询主要诊断编码
        $ICD10_NAME = '';
        foreach ($data['diagnosis'] as $diagnosisInfo) {
            if ($diagnosisInfo['class'] == 'main') {
                $ICD10_NAME = $diagnosisInfo['ICD10_NAME'];
                break;
            }
        }

        if (empty($ICD10_NAME)) {
            return true;
        }
        return false;
    }

    /**
     * 科主任
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule43($data, $item)
    {
        if (empty($data['AEE01']) || mb_strlen($data['AEE01']) < 2) {
            return true;
        }
        return false;
    }

    /**
     * 主(副主)任医师
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule44($data, $item)
    {
        if (empty($data['AEE02']) || mb_strlen($data['AEE02']) < 2) {
            return true;
        }
        return false;
    }

    /**
     * 主(副主)任医师编码
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule45($data, $item)
    {
        if (empty($data['AEE02_CODE'])) {
            return true;
        }
        return false;
    }

    /**
     * 主治医师编码
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule46($data, $item)
    {
        if (empty($data['AEE03_CODE'])) {
            return true;
        }
        return false;
    }

    /**
     * 主治医师
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule47($data, $item)
    {
        if (empty($data['AEE03']) || mb_strlen($data['AEE03']) < 2) {
            return true;
        }
        return false;
    }

    /**
     * 住院医师编码
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule48($data, $item)
    {
        if (empty($data['AEE04_CODE'])) {
            return true;
        }
        return false;
    }

    /**
     * 住院医师
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule49($data, $item)
    {
        if (empty($data['AEE04']) || mb_strlen($data['AEE04']) < 2) {
            return true;
        }
        return false;
    }

    /**
     * 责任护士
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule50($data, $item)
    {
        if (empty($data['AEE10']) || mb_strlen($data['AEE10']) < 2) {
            return true;
        }
        return false;
    }

    /**
     * 编码员
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule51($data, $item)
    {
        if (empty($data['AEE08']) || mb_strlen($data['AEE08']) < 2) {
            return true;
        }
        return false;
    }

    /**
     * ABO血型
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule52($data, $item)
    {
        $AEG01C = config('dictionaries.AEG01C');
        if (empty($data['AEG01C']) || empty($AEG01C[$data['AEG01C']])) {
            return true;
        }
        return false;
    }

    /**
     * RH血型
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule53($data, $item)
    {
        $AEG02C = config('dictionaries.AEG02C');
        if (empty($data['AEG02C']) || empty($AEG02C[$data['AEG02C']])) {
            return true;
        }
        return false;
    }

    /**
     * 主要手术操作编码
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule54($data, $item)
    {
        $ICD9_ID1 = '';
        foreach ($data['operation'] as $operation) {
            if ($operation['class'] == 'main') {
                $ICD9_ID1 = $operation['ICD9_ID1'];
            }
        }

        $res = false;
        $arr = ['71.09', '71.71'];
        foreach ($arr as $val) {
            if (stripos($data['ICD9_ID1'], $ICD9_ID1) === 0) {
                $res = true;
                break;
            }
        }

        return $res;
    }

    /**
     * 住院总费用
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule65($data, $item)
    {
        $ADA01 = $data['ADA01'] ?? '';
        $ADA0101 = $data['ADA0101'] ?? '';
        if ($ADA01 < $ADA0101 || !preg_match('/^[0-9]+\d*(.\d{1,2})?$/', $ADA01)) {
            return true;
        }
        return false;
    }

    /**
     * 出院31天再住院目的
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule83($data, $item)
    {
        if (empty($data['AEM03C'])) {
            return false;
        }
        if ($data['AEM03C'] == 2 && (empty($data['AEM04']) || $data['AEM04'] == '无' || mb_strlen($data['AEM04']) > 100)) {
            return true;
        }
        return false;
    }

    /**
     * 是否有出院31日内再住院计划（病案首页是否有出院31天内再住院计划，如果选择无，目的格、-、文字。）
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1419($data, $item)
    {
        if (!empty($data['AEM03C'])) {
            $AEM04 = $data['AEM04'] ?? '';
            if ($data['AEM03C'] == 1 && mb_strlen($AEM04) > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 是否有出院31日内再住院计划（病案首页是否有出院31天内再住院计划，如果选择有，目的必填）
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1420($data, $item)
    {
        if (!empty($data['AEM03C'])) {
            $AEM04 = $data['AEM04'] ?? '';
            if ($data['AEM03C'] == 2 && strlen(trim($AEM04)) < 6) {
                return true;
            }
        }
        return false;
    }

    /**
     * 麻醉医师【有】，麻醉方式【必填】，不能为【-】
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1422($data, $item)
    {
        $res = false;
        if (!empty($data['operation'])) {
            foreach ($data['operation'] as $operation) {
                // 麻醉医师【有】
                if (!empty($operation['HOCUS_MAN_NAME'])) {
                    // 麻醉方式【必填】，不能为【-】 长度不能大于6
                    if (empty($operation['HOCUS_WAY_ID']) || $operation['HOCUS_WAY_ID'] == '-' || mb_strlen($operation['HOCUS_WAY_ID']) > 6) {
                        $res = true;
                        break;
                    }
                }
            }
        }
        return $res;
    }

    /**
     * 麻醉方式【有】，麻醉医师【必填】【不能为【-】，不能是【数字】
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1423($data, $item)
    {
        $res = false;
        if (!empty($data['operation'])) {
            foreach ($data['operation'] as $operation) {
                // 麻醉方式【有】
                if (!empty($operation['HOCUS_WAY_ID'])) {
                    // 麻醉医师【必填】【不能为【-】，不能是【数字】
                    if (mb_strlen($operation['HOCUS_MAN_NAME']) < 2 || $operation['HOCUS_MAN_NAME'] == '--' || is_numeric($operation['HOCUS_MAN_NAME'])) {
                        $res = true;
                        break;
                    }
                }
            }
        }
        return $res;
    }

    /**
     * 有无药物过敏（必填 1-无 2-有）
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule42($data, $item)
    {
        if (empty($data['AEB02C'])) {
            return true;
        }
        $AEB02C = config('dictionaries.AEB02C');
        if (empty($AEB02C[$data['AEB02C']]) && $data['AEB02C'] != '-') {
            return true;
        }
        return false;
    }

    /**
     * 过敏药物名称
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule72($data, $item)
    {
        $res = false;
        if (!empty($data['AEB02C'])) {
            if ($data['AEB02C'] == 1 && !empty($data['AEB01'])) {
                $res = true;
            } elseif ($data['AEB02C'] == 2 && (empty($data['AEB01']) || $data['AEB01'] == '无' || is_numeric($data['AEB01']))) {
                //  || $data['AEB01']=='-'
                $res = true;
            }
        }
        return $res;
    }

    /**
     * 病案首页离院方式为，医嘱转院和医嘱转社区卫生服务机构/乡镇卫生院，拟接受医疗机构名称必填
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1427($data, $item)
    {
        $AEM01C = $data['AEM01C'] ?? '';
        $ZA03 = $data['ZA03'] ?? '';
        if (in_array($AEM01C, [2, 3]) && empty($ZA03)) {
            return true;
        }
        return false;
    }

    public function rule1429($data, $item)
    {
        $AAA04 = $data['AAA04'] ?? '';
        $AAA18C = $data['AAA18C'] ?? '';
        if ($AAA04 < 6 && !in_array($AAA18C, [4, 14, 17])) {
            return true;
        }
        return false;
    }

    /**
     * N70-N77 或 Q50.401 的诊断编码,patient_info 中 AAA02C 为女（数字2为女）
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1443($data, $item)
    {
        $expRule = explode('|', $item['relation_rule']);
        $result = false;

        // 性别
        $AAA02C = $data['AAA02C'];

        // 诊断信息
        $diagnosis = !empty($data['diagnosis']) ? $data['diagnosis'] : [];
        if (empty($diagnosis)) {
            return $result;
        }

        $ICD10_ID1_ARR = array_column($diagnosis, 'ICD10_ID1');
        $ICD10_ID1_STR = implode('，', $ICD10_ID1_ARR);
        foreach ($expRule as $val) {
            if (stripos($ICD10_ID1_STR, $val) === 0 && $AAA02C != 2) {
                $result = true;
                break;
            }
        }

        return $result;
    }

    /**
     * 新生儿病例，出院时天龄大于28天，出院诊断不能有P编码的诊断
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1444($data, $item)
    {
        $expRule = explode('|', $item['relation_rule']);
        $result = false;

        // 天龄
        $AAA40 = $data['AAA40'];
        if ($AAA40 <= 28 && $data['AAA04'] < 1) {
            return $result;
        }

        // 诊断信息
        $diagnosis = !empty($data['diagnosis']) ? $data['diagnosis'] : [];
        if (empty($diagnosis)) {
            return $result;
        }

        $ICD10_ID1_ARR = array_column($diagnosis, 'ICD10_ID1');
        $ICD10_ID1_STR = implode('，', $ICD10_ID1_ARR);
        foreach ($expRule as $val) {
            if (strpos($ICD10_ID1_STR, $val) !== false) {
                $result = true;
                break;
            }
        }

        return $result;
    }

    /**
     * 女性病例的疾病诊断不能出现输精管或前列腺
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1445($data, $item)
    {
        $expRule = explode('|', $item['relation_rule']);
        $result = false;

        // 性别
        $AAA02C = $data['AAA02C'];
        if ($AAA02C != 2) {
            return $result;
        }

        // 诊断信息
        $diagnosis = !empty($data['diagnosis']) ? $data['diagnosis'] : [];
        if (empty($diagnosis)) {
            return $result;
        }

        $ICD10_NAME_ARR = array_column($diagnosis, 'ICD10_NAME');
        $ICD10_NAME_STR = implode('，', $ICD10_NAME_ARR);
        foreach ($expRule as $val) {
            if (stripos($ICD10_NAME_STR, $val) !== false) {
                $result = true;
                break;
            }
        }

        return $result;
    }

    /**
     * 女性病例的手术操作名称不能出现输精管或前列腺
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1446($data, $item)
    {
        $expRule = explode('|', $item['relation_rule']);
        $result = false;

        // 性别
        $AAA02C = $data['AAA02C'];
        if ($AAA02C != 2) {
            return $result;
        }

        // 手术信息
        $operation = !empty($data['operation']) ? $data['operation'] : [];
        if (empty($operation)) {
            return $result;
        }

        $ICD9_NAME_ARR = array_column($operation, 'ICD9_NAME');
        $ICD9_NAME_STR = implode('，', $ICD9_NAME_ARR);
        foreach ($expRule as $val) {
            if (stripos($ICD9_NAME_STR, $val) !== false) {
                $result = true;
                break;
            }
        }

        return $result;
    }

    /**
     * 新生儿病例中，出院时天龄小于28天，不能出现Z编码诊断
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1447($data, $item)
    {
        $expRule = explode('|', $item['relation_rule']);
        $result = false;

        // 出院科室不是 新生儿科 的 不处理
        if (stripos($data['AAC11N'], '新生儿科') === false) {
            return $result;
        }

        // 天龄
        $AAA40 = $data['AAA40'];
        if ($AAA40 >= 28) {
            return $result;
        }

        // 诊断信息
        $diagnosis = !empty($data['diagnosis']) ? $data['diagnosis'] : [];
        if (empty($diagnosis)) {
            return $result;
        }

        $ICD10_ID1_ARR = array_column($diagnosis, 'ICD10_ID1');
        $ICD10_ID1_STR = implode('，', $ICD10_ID1_ARR);
        foreach ($expRule as $val) {
            if (strpos($ICD10_ID1_STR, $val) !== false) {
                $result = true;
                break;
            }
        }

        return $result;
    }

    /**
     * 其他诊断编码为T81-T88，容易出现医疗事故的编码，请核对
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1448($data, $item)
    {
        $diagnosis = [];
        foreach ($data['diagnosis'] as $diagnosisInfo) {
            if ($diagnosisInfo['class'] == 'other') {
                $diagnosis[] = $diagnosisInfo['ICD10_ID1'];
            }
        }
        if (empty($diagnosis)) {
            return false;
        }

        $arr = ['T81', 'T82', 'T83', 'T84', 'T85', 'T86', 'T87', 'T88'];
        $ICD10_ID1_STR = implode('，', $diagnosis);
        $res = false;
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1_STR, $val) === 0) {
                $res = true;
                break;
            }
        }

        return $res;
    }

    /**
     * 含“小儿肠炎”，年龄要小于2岁
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1449($data, $item)
    {
        $expRule = explode('|', $item['relation_rule']);
        $result = false;

        // 年龄
        $AAA04 = $data['AAA04'];
        // 天龄
        $AAA40 = $data['AAA40'];

        // 诊断信息
        $diagnosis = !empty($data['diagnosis']) ? $data['diagnosis'] : [];
        if (empty($diagnosis)) {
            return $result;
        }

        $ICD10_NAME_ARR = array_column($diagnosis, 'ICD10_NAME');
        $ICD10_NAME_STR = implode('，', $ICD10_NAME_ARR);
        foreach ($expRule as $val) {
            if (stripos($ICD10_NAME_STR, $val) !== false && ($AAA04 > 2 || $AAA40 > 730)) {
                $result = true;
                break;
            }
        }

        return $result;
    }

    /**
     * K83.1梗阻性黄疸 和 K80胆结石 不能同时存在（诊断编码模糊匹配前几位）
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1450($data, $item)
    {
        $expRule = explode('|', $item['relation_rule']);

        // 诊断信息
        $diagnosis = !empty($data['diagnosis']) ? $data['diagnosis'] : [];
        if (empty($diagnosis)) {
            return false;
        }

        $ICD10_ID1_ARR = array_column($diagnosis, 'ICD10_ID1');
        $ICD10_ID1_STR = implode('，', $ICD10_ID1_ARR);
        $count = 0;
        foreach ($expRule as $val) {
            if (stripos($ICD10_ID1_STR, $val) !== false) {
                $count++;
            }
        }

        if ($count >= 2) {
            return true;
        }

        return false;
    }

    /**
     * 身份证与出生日期要对应
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1451($data, $item)
    {
        // 判断身份证号是否存在
        if (empty($data['AAA07'])) {
            return false;
        }

        // 解析身份证号中的 出生日期
        $csrq = substr($data['AAA07'], 6, 8);
        $csrq = date('Y-m-d', strtotime($csrq));

        // 判断身份证号中的出生日期 与 出生日期字段 是否一致
        $AAA03 = date('Y-m-d', strtotime($data['AAA03']));
        if ($AAA03 != $csrq) {
            return true;
        }

        return false;
    }

    /**
     * 护理天数之和等于住院天数
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1452($data, $item)
    {
        // 住院天数
        $AAC04 = $data['AAC04'];

        // 护理天数之和
        $hltsSum = 0;
        if (!empty($data['TJHL'])) {
            $hltsSum += $data['TJHL'];
        }
        if (!empty($data['YJHL'])) {
            $hltsSum += $data['YJHL'];
        }
        if (!empty($data['EJHL'])) {
            $hltsSum += $data['EJHL'];
        }
        if (!empty($data['SJHL'])) {
            $hltsSum += $data['SJHL'];
        }

        // 住院天数 不等于 护理天数之和，进行质控
        if ($AAC04 != $hltsSum && ($AAC04 + 1) != $hltsSum) {
            return true;
        }

        return false;
    }

    /**
     * 入院病情有诊断时误填或漏填
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1453($data, $item)
    {
        $res = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ICD10_ID1'];
            $ICD10_NAME = $diagnosis['ICD10_NAME'];
            $RYQK = $diagnosis['RYQK'];

            if (empty($ICD10_ID1) && empty($ICD10_NAME) && empty($RYQK)) {
                continue;
            }

            if (empty($ICD10_ID1) || empty($ICD10_NAME) || empty($RYQK)) {
                $res = true;
                break;
            }
        }

        return $res;
    }

    /**
     * 操作有96.7101，有创呼吸机使用时间应小于96小时
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1454($data, $item)
    {
        $res = false;
        $ICD9_ID1 = '';
        foreach ($data['operation'] as $operation) {
            if ($operation['ICD9_ID1'] == '96.7101') {
                $ICD9_ID1 = 96.7101;
                break;
            }
        }

        if ($ICD9_ID1) {
            // 查询呼吸机使用时间
            $feeDetail = !empty($data['feeDetail']) ? $data['feeDetail'] : [];
            $AEL01 = 0;
            foreach ($feeDetail as $v) {
                if (strpos($v['FYMC'], '呼吸机辅助呼吸') !== false) {
                    $AEL01 += $v['FYSL'];
                }
            }
            $AEL01 = intval($AEL01 ?: 0);
            if ($AEL01 > 96) {
                $res = true;
            }
        }

        return $res;
    }

    /**
     * 操作有96.7201，有创呼吸机使用时间应大于等于96小时
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1455($data, $item)
    {
        $res = false;
        $ICD9_ID1 = '';
        foreach ($data['operation'] as $operation) {
            if ($operation['ICD9_ID1'] == '96.7201') {
                $ICD9_ID1 = 96.7201;
                break;
            }
        }

        if ($ICD9_ID1) {
            // 查询呼吸机使用时间
            $feeDetail = !empty($data['feeDetail']) ? $data['feeDetail'] : [];
            $AEL01 = 0;
            foreach ($feeDetail as $v) {
                if (strpos($v['FYMC'], '呼吸机辅助呼吸') !== false) {
                    $AEL01 += $v['FYSL'];
                }
            }
            $AEL01 = intval($AEL01 ?: 0);
            if ($AEL01 < 96) {
                $res = true;
            }
        }

        return $res;
    }

    /**
     * 一级切口，愈合类别不能为丙级 todo 所需数据不全
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1456($data, $item)
    {
        if ($item['status'] == 1) {
            return false;
        }

        $res = false;


        return $res;
    }

    /**
     * 主要诊断编码出现S00-S09,颅内损伤昏迷时间6个空必填数字
     * @param $data
     * @param $item
     * @return false
     */
    public function rule1457($data, $item)
    {
        $expRule = explode('|', $item['relation_rule']);

        // 查询主要诊断编码
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosisInfo) {
            if ($diagnosisInfo['class'] == 'main') {
                $ICD10_ID1 = $diagnosisInfo['ICD10_ID1'];
                break;
            }
        }

        $res = false;
        if ($ICD10_ID1) {
            // 入院记录 主诉中 含关键字“昏迷”
//            $bllb292Info = BLLB292::query()
//                ->where('ZYH','=',$data['MED_REC_ID'])
//                ->where('ZHS','like',"%昏迷%")
//                ->first();

            $bllb292Service = new ElasticsearchService('bllb292_2023');
            $must = [
                ["term" => ['ZYH' => $data['MED_REC_ID']]],
                ["match_phrase" => ['ZHS' => '昏迷']]
            ];
            $params = $bllb292Service->clearMust()->queryByMustBatch($must)->getParams();
            $restful = app('es')->search($params);
            $bllb292Data = $bllb292Service->getDataByEs($restful);
            $bllb292Info = !empty($bllb292Data[0]) ? $bllb292Data[0][0] : [];
            if ($bllb292Info) {
                foreach ($expRule as $val) {
                    if (stripos($ICD10_ID1, $val) !== false) {
                        if ($data['AEJ01'] == '-' && $data['AEJ02'] == '-' && $data['AEJ03'] == '-' && $data['AEJ04'] == '-' && $data['AEJ05'] == '-' && $data['AEJ06'] == '-') {
                            $res = true;
                            break;
                        }
                    }
                }
            }
        }

        return $res;
    }

    /**
     * 无效的主要诊断编码
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1458($data, $item)
    {
        $expRule = explode('|', $item['relation_rule']);

        // 查询主要诊断编码
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosisInfo) {
            if ($diagnosisInfo['class'] == 'main' && $diagnosisInfo['ICD10_ID1'] != 'O82.9') {
                $ICD10_ID1 = $diagnosisInfo['ICD10_ID1'];
                break;
            }
        }

        $res = false;
        if ($ICD10_ID1) {
            foreach ($expRule as $val) {
                $zdbm = explode(":", $val);
                if (stripos($ICD10_ID1, $zdbm[0]) === 0) {
                    $res = $zdbm[0];
                    if (!empty($zdbm[1])) {
                        $res .= '（' . $zdbm[1] . '）';
                    }
                    $res .= ' 无效的诊断编码';
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * 离院方式（AEM01C）等于 2或3时，接收医疗机构名称（AEM02）
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1459($data, $item)
    {
        $AEM01C = $data['AEM01C'] ?? '';
        $AEM02 = $data['AEM02'] ?? '';
        if (in_array($AEM01C, [2, 3]) && ($AEM02 === '' || $AEM02 === null)) {
            return true;
        }
        return false;
    }

    /**
     * 完成情况
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1461($data, $item)
    {
        if (isset($data['LCLJ'])) {
            $WCQK = $data['WCQK'] ?? '';
            if ($data['LCLJ'] === 1 && ($WCQK === '' || $WCQK === null)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 变异情况
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1462($data, $item)
    {
        if (isset($data['LCLJ'])) {
            $BYQK = $data['BYQK'] ?? '';
            if ($data['LCLJ'] === 1 && ($BYQK === '' || $BYQK === null)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 手术操作名称【有】，手术者【有】【长度2-40】【不能为-】【不能全是是数字】
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1463($data, $item)
    {
        if (!empty($data['operation'])) {
            foreach ($data['operation'] as $operation) {
                if (!empty($operation['ICD9_NAME'])) {
                    $OPE_MAN_NAME = $operation['OPE_MAN_NAME'];
                    if (mb_strlen($OPE_MAN_NAME) < 2 || $OPE_MAN_NAME == '--' || is_numeric($OPE_MAN_NAME)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * 入院病情（不能全部是【4】 1-有 2-临床未确定 3-情况不明 4-无）
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1464($data, $item)
    {
        if (!empty($data['diagnosis'])) {
            $count = 0;
            foreach ($data['diagnosis'] as $diagnosis) {
                if ($diagnosis['RYQK'] == '无') {
                    $count++;
                }
            }

            if (count($data['diagnosis']) == $count) {
                return true;
            }
        }

        return false;
    }

    /**
     * Z37、O80、O81、O82、O83、O84、O26.9应同时存在
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1467($data, $item)
    {
        if (empty($data['diagnosis']) || $data['AAC11N'] != '产科') {
            return false;
        }

        $bl01Service = new ElasticsearchService('bl01_202303');

        // 病程记录
        $must = [
            ['term' => ['JZHM' => $data['MED_REC_ID']]],
            ['term' => ['BLLB' => 294]],
            ['match_phrase' => ['HJNR' => '分娩记录']]
        ];
        $mustNot = ['term' => ['BLZT' => 9]];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNot($mustNot)
            ->trackTotalHits()
            ->getParams();
        $restful = app('es')->search($params);
        $bcjlData = $bl01Service->getDataByEsToArray($restful);

        // 手术记录
        $must = [
            ['term' => ['JZHM' => $data['MED_REC_ID']]],
            ['term' => ['BLLB' => 303]]
        ];
        $should = [
            ['match_phrase' => ['HJNR' => '刨宫产记录']],
            ['match_phrase' => ['HJNR' => '刨宮产记录']]
        ];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByShouldBatch($should)
            ->queryByMustNot($mustNot)
            ->minimumShouldMatch()
            ->getParams();
        $restful = app('es')->search($params);
        $ssjlData = $bl01Service->getDataByEsToArray($restful);

        // 验证
        if (empty($bcjlData[0]) && empty($ssjlData[0])) {
            return false;
        }

        $Z37 = 0;
        $O8 = 0;
        $O26 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ICD10_ID1'];
            if (stripos($ICD10_ID1, 'Z37') !== false) {
                $Z37 = 1;
            } elseif (stripos($ICD10_ID1, 'O26.9') !== false) {
                $O26 = 1;
            } else if (stripos($ICD10_ID1, 'O80') !== false || stripos($ICD10_ID1, 'O81') !== false || stripos($ICD10_ID1, 'O82') !== false || stripos($ICD10_ID1, 'O83') !== false || stripos($ICD10_ID1, 'O84') !== false) {
                $O8 = 1;
            }
        }

        $sumScore = $Z37 + $O8 + $O26;
        if (!in_array($sumScore, [0, 3])) {
            return true;
        }

        return false;
    }

    /**
     * Z37码段不能重复编码
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1468($data, $item)
    {
        if ($data['AAC11N'] != '产科') {
            return false;
        }

        $count = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ICD10_ID1'];
            if (stripos($ICD10_ID1, 'Z37') !== false) {
                $count++;
            }
        }

        if ($count > 1) {
            return true;
        }
        return false;
    }

    /**
     * 出院诊断:有【K56.700 肠梗阻】和【K66.002 肠粘连】应合并编码为【K56.500x003 粘连性肠梗阻】
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1469($data, $item)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ICD10_ID1'];
            if ($ICD10_ID1 == 'K56.700') {
                $res1 = 1;
            } elseif ($ICD10_ID1 == 'K66.002') {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 出院诊断:【S52.500x001 桡骨远端骨折】和【S52.802 尺骨茎突骨折】应合并编码为【S52.600x002 尺骨茎突骨折伴桡骨远端骨折】
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1470($data, $item)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ICD10_ID1'];
            if ($ICD10_ID1 == 'S52.500x001') {
                $res1 = 1;
            } elseif ($ICD10_ID1 == 'S52.802') {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 出院诊断:【I50.101 急性左心衰竭】、【J81.x00 肺水肿】应合并编码为【I50.103 左心衰竭合并肺水肿】
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1471($data, $item)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ICD10_ID1'];
            if ($ICD10_ID1 == 'I50.101') {
                $res1 = 1;
            } elseif ($ICD10_ID1 == 'J81.x00') {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 出院诊断:【M51.202 腰椎间盘突出】和【M54.300 坐骨神经痛】应合并编码为【M51.101+ 腰椎间盘脱出伴坐骨神经痛】
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1472($data, $item)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ICD10_ID1'];
            if ($ICD10_ID1 == 'M51.202') {
                $res1 = 1;
            } elseif ($ICD10_ID1 == 'J81.x00') {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 出院诊断:【H02.003 睑内翻】和【H02.004 倒睫】应合并编码为【H02.000 睑内翻和倒睫】
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1473($data, $item)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ICD10_ID1'];
            if ($ICD10_ID1 == 'H02.003') {
                $res1 = 1;
            } elseif ($ICD10_ID1 == 'H02.004') {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 【食管静脉曲张】或 【胃底静脉曲张】和【肝硬化】应合并为【肝硬化伴食管静脉曲张】或【肝硬化伴胃底静脉曲张】
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1474($data, $item)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ICD10_ID1'];
            if ($ICD10_ID1 == 'I86.400x001' || $ICD10_ID1 == 'I85.900x001') {
                $res1 = 1;
            } elseif ($ICD10_ID1 == 'K74.100') {
                $res2 = 1;
            }
        }
        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 联系人关系不合理
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1476($data, $item)
    {
        $AAA23C = !empty($data['AAA23C']) ? $data['AAA23C'] : '';
        if ($data['AAA04'] < 20 && in_array($AAA23C, [2, 3])) {
            return true;
        }
        return false;
    }

    /**
     * 新生儿出生体重未填写
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1477($data, $item)
    {
        if ($data['AAC11N'] != '产科') {
            return false;
        }

        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ICD10_ID1'], 'Z37') === 0) {
                $ICD10_ID1 = $diagnosis['ICD10_ID1'];
            }
        }
        if (!empty($ICD10_ID1) && empty($data['AEN01'])) {
            return true;
        }
        return false;
    }

    /**
     * 麻醉方式未填写
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1478($data, $item)
    {
        // 麻醉费
        if (empty($data['D20X01'])) {
            return false;
        }

        $res = false;
        if (!empty($data['operation'])) {
            foreach ($data['operation'] as $operation) {
                if (!empty($operation['ICD9_NAME']) && empty($operation['HOCUS_WAY_ID'])) {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    }

    /**
     *  出院诊断:【E14 】与【E11或 E10】不能同时存在
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1479($data, $item)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ICD10_ID1'];
            if (stripos($ICD10_ID1, 'E14') === 0) {
                $res1 = 1;
            } elseif (stripos($ICD10_ID1, 'E11') === 0 || stripos($ICD10_ID1, 'E10') === 0) {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 【I25.103 冠状动脉粥样硬化性心脏病】或【I20.000不稳定型心绞痛】与【I21急性心肌梗死】同时存在，
     * 【I25.103 】或【I20.000】不能作为主要诊断
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1481($data, $item)
    {
        $res1 = 0;
        $res2 = 0;
        $ZYZD = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['class'] == 'main') {
                $ZYZD = $diagnosis['ICD10_ID1'];
            }
            $ICD10_ID1 = $diagnosis['ICD10_ID1'];
            if ($ICD10_ID1 == 'I25.103' || $ICD10_ID1 == 'I20.000') {
                $res1 = 1;
            } elseif (stripos($ICD10_ID1, 'I21') === 0) {
                $res2 = 1;
            }
        }
        if ($res1 && $res2 && in_array($ZYZD, ['I25.103', 'I20.000'])) {
            return true;
        }

        return false;
    }

    /**
     * 出院诊断:【E10.9】与【E10.0-E10.8】不能同时存在
     * 出院诊断:【E11.9】与【E11.0-E11.8】不能同时存在
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1482($data, $item)
    {
        $arr1 = ['E10.0', 'E10.1', 'E10.2', 'E10.3', 'E10.4', 'E10.5', 'E10.6', 'E10.7', 'E10.8'];
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            $ICD10_ID1 = $diagnosis['ICD10_ID1'];
            if (stripos($ICD10_ID1, 'E10.9') === 0) {
                $res1 = 1;
            } elseif (stripos($ICD10_ID1, 'E10') === 0) {
                foreach ($arr1 as $val) {
                    if (stripos($ICD10_ID1, $val) === 0) {
                        $res2 = 1;
                        break;
                    }
                }
            }
        }
        if ($res1 && $res2) {
            return true;
        }

        $arr2 = ['E11.0', 'E11.1', 'E11.2', 'E11.3', 'E11.4', 'E11.5', 'E11.6', 'E11.7', 'E11.8'];
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            $ICD10_ID1 = $diagnosis['ICD10_ID1'];
            if (stripos($ICD10_ID1, 'E10.9') === 0) {
                $res1 = 1;
            } elseif (stripos($ICD10_ID1, 'E10') === 0) {
                foreach ($arr2 as $val) {
                    if (stripos($ICD10_ID1, $val) === 0) {
                        $res2 = 1;
                        break;
                    }
                }
            }
        }
        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 诊断【i63.9 脑梗死】 + 手术【88.4101脑血管造影】 + 【诊断有 i65】 建议更换为 i63.0 - i63.8
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1483($data, $item)
    {
        $ICD9_ID1 = '';
        foreach ($data['operation'] as $operation) {
            if ($operation['ICD9_ID1'] == '88.4101') {
                $ICD9_ID1 = $operation['ICD9_ID1'];
                break;
            }
        }
        if (empty($ICD9_ID1)) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ICD10_ID1'];
            if (stripos($ICD10_ID1, 'i63.9') === 0) {
                $res1 = 1;
            } elseif (stripos($ICD10_ID1, 'i65') === 0) {
                $res2 = 1;
            }
        }
        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 诊断【K21.9 胃-食管反流性疾病不伴有食管炎】与【K21.0 胃-食管反流性疾病伴有食管炎】逻辑冲突(伴~不伴)
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1484($data, $item)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ICD10_ID1'];
            if (stripos($ICD10_ID1, 'K21.9') === 0) {
                $res1 = 1;
            } elseif (stripos($ICD10_ID1, 'K21.0') === 0) {
                $res2 = 1;
            }
        }
        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 手术【51.1】 不能 同时有手术【51.64 或 51.84-51.88 或 52.14 或 52.21 或 52.93 h或 52.94 或 52.97 或 52.98】（双向质控）
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1485($data, $item)
    {
        $arr = ['51.64', '51.84', '51.85', '51.86', '51.87', '51.88', '52.14', '52.21', '52.93', '52.94', '52.97', '52.98'];
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            $ICD9_ID1 = $operation['ICD9_ID1'];
            if (stripos($ICD9_ID1, '51.1') === 0) {
                $res1 = 1;
            } else {
                foreach ($arr as $val) {
                    if (stripos($ICD9_ID1, $val) === 0) {
                        $res2 = 1;
                        break;
                    }
                }
            }
        }

        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 诊断【Z51.0】手术操作必须有【92.2 - 92.3】
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1486($data, $item)
    {
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ICD10_ID1'], 'Z51.0') === 0) {
                $ICD10_ID1 = $diagnosis['ICD10_ID1'];
                break;
            }
        }
        if (empty($ICD10_ID1)) {
            return false;
        }

        $ICD9_ID1 = '';
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['ICD9_ID1'], '92.2') === 0 || stripos($operation['ICD9_ID1'], '92.3') === 0) {
                $ICD9_ID1 = $operation['ICD9_ID1'];
                break;
            }
        }
        if (empty($ICD9_ID1)) {
            return true;
        }

        return false;
    }

    /**
     * 产科【产科】诊断【O36.4】 其他诊断不应有【Z37 或 O80 - O84】
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1487($data, $item)
    {
        if ($data['AAC11N'] != '产科') {
            return false;
        }

        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ICD10_ID1'], 'O36.4') === 0) {
                $ICD10_ID1 = $diagnosis['ICD10_ID1'];
                break;
            }
        }
        if (empty($ICD10_ID1)) {
            return false;
        }

        $arr = ['Z37', 'O80', 'O81', 'O82', 'O83', 'O84'];
        $res = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res) {
                break;
            }
            foreach ($arr as $val) {
                if ($diagnosis['class'] == 'other' && stripos($diagnosis['ICD10_ID1'], $val) === 0) {
                    $res = 1;
                    break;
                }
            }
        }
        if ($res) {
            return true;
        }

        return false;
    }

    /**
     * 产科【产科】诊断【O36.4】和 手术【74.1】手术应换成74.9
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1488($data, $item)
    {
        if ($data['AAC11N'] != '产科') {
            return false;
        }

        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ICD10_ID1'], 'O36.4') === 0) {
                $ICD10_ID1 = $diagnosis['ICD10_ID1'];
                break;
            }
        }
        if (empty($ICD10_ID1)) {
            return false;
        }

        $ICD9_ID1 = '';
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['ICD9_ID1'], '74.1') === 0) {
                $ICD9_ID1 = $operation['ICD9_ID1'];
            }
        }
        if ($ICD9_ID1) {
            return true;
        }

        return false;
    }

    /**
     * 主诊断【C00-C76】病理诊断编码必须是 M****\/3
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1489($data, $item)
    {
        $arr = [
            'C00', 'C01', 'C02', 'C03', 'C04', 'C05', 'C06', 'C07', 'C08', 'C09', 'C10', 'C11', 'C12', 'C13', 'C14', 'C15', 'C16',
            'C17', 'C18', 'C19', 'C20', 'C21', 'C22', 'C23', 'C24', 'C25', 'C26', 'C27', 'C28', 'C29', 'C30', 'C31', 'C32', 'C33',
            'C34', 'C35', 'C36', 'C37', 'C38', 'C39', 'C40', 'C41', 'C42', 'C43', 'C44', 'C45', 'C46', 'C47', 'C48', 'C49', 'C50',
            'C51', 'C52', 'C53', 'C54', 'C55', 'C56', 'C57', 'C58', 'C59', 'C60', 'C61', 'C62', 'C63', 'C64', 'C65', 'C66', 'C67',
            'C68', 'C69', 'C70', 'C71', 'C72', 'C72', 'C74', 'C75', 'C76'
        ];
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['class'] == 'main') {
                $ICD10_ID1 = $diagnosis['ICD10_ID1'];
                break;
            }
        }
        if (!$ICD10_ID1) {
            return false;
        }

        $res1 = 0;
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $res1 = 1;
            }
        }
        if (!$res1) {
            return false;
        }

        $ABF01C = !empty($data['ABF01C']) ? $data['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][3]$/', $ABF01C);
        if (!$res2) {
            return true;
        }

        return false;
    }

    /**
     * 主诊断【D00-D09】病理诊断编码必须是 M****\/2
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1490($data, $item)
    {
        $arr = [
            'D00', 'D01', 'D02', 'D03', 'D04', 'D05', 'D06', 'D07', 'D08', 'D09'
        ];
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['class'] == 'main') {
                $ICD10_ID1 = $diagnosis['ICD10_ID1'];
                break;
            }
        }
        if (!$ICD10_ID1) {
            return false;
        }

        $res1 = 0;
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $res1 = 1;
            }
        }
        if (!$res1) {
            return false;
        }

        $ABF01C = !empty($data['ABF01C']) ? $data['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][2]$/', $ABF01C);
        if (!$res2) {
            return true;
        }

        return false;
    }

    /**
     * 主诊断【D10-D36】病理诊断编码必须是 M****\/0
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1491($data, $item)
    {
        $arr = [
            'D10', 'D11', 'D12', 'D13', 'D14', 'D15', 'D16', 'D17', 'D18', 'D19', 'D20', 'D21', 'D22', 'D23', 'D24', 'D25', 'D26',
            'D27', 'D28', 'D29', 'D30', 'D31', 'D32', 'D33', 'D34', 'D35', 'D36'
        ];
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['class'] == 'main') {
                $ICD10_ID1 = $diagnosis['ICD10_ID1'];
                break;
            }
        }
        if (!$ICD10_ID1) {
            return false;
        }

        $res1 = 0;
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $res1 = 1;
            }
        }
        if (!$res1) {
            return false;
        }

        $ABF01C = !empty($data['ABF01C']) ? $data['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][0]$/', $ABF01C);
        if (!$res2) {
            return true;
        }

        return false;
    }

    /**
     * 主诊断【D37-D48】病理诊断编码必须是 M****\/1
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1492($data, $item)
    {
        $arr = [
            'D37', 'D38', 'D39', 'D40', 'D41', 'D42', 'D43', 'D44', 'D45', 'D46', 'D47', 'D48'
        ];
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['class'] == 'main') {
                $ICD10_ID1 = $diagnosis['ICD10_ID1'];
                break;
            }
        }
        if (!$ICD10_ID1) {
            return false;
        }

        $res1 = 0;
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $res1 = 1;
            }
        }
        if (!$res1) {
            return false;
        }

        $ABF01C = !empty($data['ABF01C']) ? $data['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][1]$/', $ABF01C);
        if (!$res2) {
            return true;
        }

        return false;
    }

    /**
     * 主诊断【C77-C79】 病理诊断编码必须是 M****\/6
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1493($data, $item)
    {
        $arr = ['C77', 'C78', 'C79'];
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['class'] == 'main') {
                $ICD10_ID1 = $diagnosis['ICD10_ID1'];
                break;
            }
        }
        if (!$ICD10_ID1) {
            return false;
        }

        $res1 = 0;
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $res1 = 1;
            }
        }
        if (!$res1) {
            return false;
        }

        $ABF01C = !empty($data['ABF01C']) ? $data['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][6]$/', $ABF01C);
        if (!$res2) {
            return true;
        }

        return false;
    }

    /**
     * 主诊断【C80-C97】病理诊断编码必须是 M****\/3
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1494($data, $item)
    {
        $arr = [
            'C80', 'C81', 'C82', 'C83', 'C84', 'C85', 'C86', 'C87', 'C88', 'C89', 'C90', 'C91', 'C92', 'C93', 'C94', 'C95', 'C96', 'C99'
        ];
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['class'] == 'main') {
                $ICD10_ID1 = $diagnosis['ICD10_ID1'];
                break;
            }
        }
        if (!$ICD10_ID1) {
            return false;
        }

        $res1 = 0;
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $res1 = 1;
            }
        }
        if (!$res1) {
            return false;
        }

        $ABF01C = !empty($data['ABF01C']) ? $data['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][3]$/', $ABF01C);
        if (!$res2) {
            return true;
        }

        return false;
    }

    /**
     * 【68.1200x001 宫腔镜检查】不能和【宫腔镜其他诊断】同时存在
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1495($data, $item)
    {
        $str = "66.2900x003,66.8x03,66.9600x003,67.2x01,67.3203,67.3902,67.4x08,68.1602,68.2101,68.2204,68.2206,68.2300x005,68.2302,68.2900x048,68.2913,68.2914,68.2915,68.2916,68.2917,69.0902,69.4900x006,69.4904,69.5103,70.1408,74.3x00x016,74.3x00x017,74.3x00x018,74.3x09,97.7102,98.1600x002";
        $arr = explode(",", $str);
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            if ($operation['ICD9_ID1'] == '68.1200x001') {
                $res1 = 1;
            } elseif (in_array($operation['ICD9_ID1'], $arr)) {
                $res2 = 1;
            }
        }
        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 【54.2100 腹腔镜检查】不能和【腹腔镜手术】同时存在
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1496($data, $item)
    {
        $str = "02.3405,05.2301,05.2401,07.1200x003,07.2102,07.2201,07.2902,07.3x01,07.4102,17.1100,17.1100x001,17.1200,17.1200x001,17.1300,17.1300x001,17.1300x002,17.2100,17.2100x001,17.2200,17.2200x001,17.2300,17.2300x001,17.2400,17.2400x001,17.3100,17.3101,17.3200,17.3200x001,17.3200x002,17.3300,17.3300x002,17.3400,17.3401,17.3500,17.3500x001,17.3600,17.3600x001,17.3900,17.3900x002,17.3900x003,17.3901,17.4200,34.8100x002,38.8700x009,38.8700x011,38.8700x012,40.1100x003,40.2900x027,40.5301,40.5400x002,40.5900x010,40.5911,40.5912,41.2x03,41.2x04,41.4301,41.5x01,41.9301,41.9504,42.7x02,43.0x03,43.1900x006,43.3x01,43.4203,43.5x03,43.6x02,43.7x00x002,43.7x03,43.8200,43.8200x001,43.8201,43.9102,43.9900x003,43.9900x005,43.9904,43.9905,44.0001,44.2900x003,44.3800,44.3801,44.3802,44.3803,44.3804,44.4102,44.4200x001,44.4202,44.6401,44.6700,44.6701,44.6800,44.6800x002,44.6801,44.6902,44.9100x005,44.9500,44.9501,44.9600,44.9601,44.9602,44.9700,44.9701,44.9800,44.9801,44.9802,45.0204,45.3303,45.3304,45.4100x002,45.4100x003,45.4100x004,45.4100x005,45.6100x001,45.6200x001,45.6200x002,45.6200x003,45.6200x004,45.6200x005,45.6200x006,45.6208,45.6300x001,45.7900x004,45.8100,45.8100x001,46.1000x007,46.1100x002,46.1301,46.2001,46.2301,46.3900x006,46.3900x007,46.3905,46.4201,46.4202,46.6400x001,46.7303,46.7506,46.7604,46.7900x009,46.8100x001,46.8100x002,46.8200x001,46.8200x002,47.0100,47.1100,47.2x01,48.3507,48.4106,48.4200,48.4903,48.5100,48.5100x002,48.6100x001,48.6100x002,48.6201,48.6300x001,48.6300x002,48.6300x003,48.6302,48.6303,48.6900x002,48.6909,48.6910,48.6911,48.6912,48.6913,48.7101,48.7605,48.8205,48.8206,49.7904,50.0x00x004,50.0x03,50.0x04,50.0x05,50.1400,50.2203,50.2204,50.2205,50.2206,50.2500,50.2501,50.2502,50.2503,50.2900x020,50.2900x021,50.2909,50.2910,50.3x05,50.3x06,51.0301,51.0400x005,51.0404,51.1104,51.1105,51.2300,51.2301,51.2400,51.2401,51.3100x001,51.3203,51.3204,51.3301,51.3700x001,51.3700x002,51.3907,51.5900x006,51.6100x002,51.6300x001,51.6900x013,51.7909,51.7910,51.8701,51.8800x006,51.8803,51.8805,51.9101,52.0101,52.0102,52.0900x001,52.0904,52.1200x001,52.1302,52.2100x001,52.2100x002,52.2100x003,52.2100x004,52.2101,52.4x04,52.4x05,52.4x06,52.4x07,52.5204,52.5205,52.5206,52.5301,52.5905,52.5906,52.6x02,52.6x03,52.7x01,52.9301,52.9605,53.0002,53.0203,53.0204,53.1200x001,53.1203,53.2100x001,53.2900x001,53.3100x001,53.4200,53.4201,53.4300,53.4301,53.5101,53.5902,53.6200,53.6300,53.6301,53.6302,53.7100,53.7100x001,53.7101,53.8300,53.9x00x020,53.9x00x021,53.9x00x022,54.1101,54.1900x005,54.1900x006,54.2100,54.2100x005,54.2200x003,54.2300x004,54.2300x005,54.2300x006,54.3x02,54.4x00x050,54.4x00x052,54.4x00x053,54.4x10,54.4x11,54.4x12,54.4x13,54.4x14,54.4x15,54.4x16,54.5100,54.5100x005,54.5100x009,54.5101,54.5102,54.5103,54.6400x001,54.9202,54.9300x005,54.9300x009,54.9500x005,54.9703,54.9900x010,54.9900x011,54.9903,54.9904,55.0106,55.0109,55.0110,55.0111,55.0201,55.1108,55.1109,55.3400,55.3400x001,55.3400x002,55.3900x004,55.4x03,55.5103,55.5104,55.5105,55.5106,55.5401,55.7x01,55.8501,55.8600x006,55.8606,55.8703,55.8704,55.8900x003,55.9903,56.2x04,56.4100x009,56.4100x011,56.4105,56.4201,56.6100x004,56.7100x004,56.7402,56.8200x002,56.8900x006,56.8908,56.8909,56.9500x001,57.5100x003,57.5102,57.6x06,57.7103,57.7901,57.8400x004,57.8700x005,57.8700x006,57.8700x007,57.8700x008,57.8900x003,57.8905,58.4305,58.4702,59.0300,59.0300x002,59.0301,59.0302,59.0303,59.0904,59.1200,59.5x02,60.5x02,60.6101,60.6900x002,60.7300x003,60.7300x004,61.4905,62.0x01,62.2x00x003,62.3x04,62.4103,62.4105,62.5x01,63.1x03,63.6x00x005,65.0100,65.0100x002,65.0100x003,65.0101,65.0102,65.0103,65.0104,65.0105,65.1300,65.1400,65.2300,65.2400,65.2500,65.2500x003,65.2500x005,65.2500x011,65.2501,65.2502,65.2503,65.2504,65.2505,65.3100,65.4100,65.5300,65.5400,65.6300,65.6300x001,65.6400,65.7400,65.7500,65.7600,65.7900x008,65.7900x009,65.7904,65.7905,65.8100,65.8101,65.8102,65.9101,65.9900x006,65.9902,66.0100x003,66.0101,66.0102,66.0103,66.0202,66.0203,66.1101,66.2101,66.2102,66.2200x001,66.2201,66.2900x001,66.2901,66.2902,66.2903,66.4x02,66.5102,66.5201,66.6100x002,66.6100x003,66.6100x006,66.6100x007,66.6103,66.6104,66.6200x004,66.6201,66.6301,66.6902,66.69x002,66.7100x002,66.7301,66.7900x008,66.7900x009,66.7905,66.7906,66.8x02,66.9100x003,66.9203,66.9204,66.9205,66.9500x001,66.9502,66.9600x002,67.3903,67.4x05,67.4x06,67.4x07,67.5101,68.0x00x006,68.0x01,68.1501,68.1601,68.2203,68.2205,68.2401,68.2501,68.2900x013,68.2908,68.2909,68.2910,68.2911,68.2912,68.2918,68.3100,68.3102,68.3103,68.3104,68.3105,68.3106,68.4100,68.4101,68.4102,68.4103,68.4104,68.5100,68.5100x004,68.5100x005,68.5101,68.5102,68.5103,68.6100,68.6100x001,68.6100x002,68.6101,68.7100,68.7100x001,69.1900x022,69.1907,69.1908,69.1909,69.2200x007,69.2200x008,69.2200x009,69.2200x016,69.2200x017,69.2200x018,69.2208,69.2209,69.2210,69.2211,69.2212,69.3x02,69.4201,69.4902,69.4903,70.1200x002,70.1202,70.1400x002,70.1407,70.2301,70.3201,70.3305,70.4x00x001,70.4x05,70.5002,70.5102,70.5202,70.6101,70.6300x001,70.6300x002,70.6300x003,70.7700x004,70.7802,70.7909,74.3x00x012,74.3x00x014,74.3x00x015,74.3x05,74.3x06,74.3x07,74.3x08,74.9100x001,74.9101,84.6501";
        $arr = explode(",", $str);
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            if ($operation['ICD9_ID1'] == '54.2100') {
                $res1 = 1;
            } elseif (in_array($operation['ICD9_ID1'], $arr)) {
                $res2 = 1;
            }
        }
        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 70.78另编码使用生物学物质（70.94）或人造物质（70.95）
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1497($data, $item)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['ICD9_ID1'];
            if (stripos($ICD9_ID1, '70.78') === 0) {
                $res1 = 1;
            } elseif (stripos($ICD9_ID1, '70.94') === 0 || stripos($ICD9_ID1, '70.95') === 0) {
                $res2 = 1;
            }
        }

        if (empty($res1)) {
            return false;
        }
        if (empty($res2)) {
            return true;
        }
        return false;
    }

    /**
     * 手术【81.0或81.3】手术中要有【81.62-81.64】融合椎骨的总数
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1498($data, $item)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['ICD9_ID1'];
            if (stripos($ICD9_ID1, '81.0') !== false || stripos($ICD9_ID1, '81.3') !== false) {
                $res1 = 1;
            } elseif (stripos($ICD9_ID1, '81.62') !== false || stripos($ICD9_ID1, '81.63') !== false || stripos($ICD9_ID1, '81.64') !== false) {
                $res2 = 1;
            }
        }

        if (empty($res1)) {
            return false;
        }
        if (empty($res2)) {
            return true;
        }

        return false;
    }

    /**
     * 手术【81.51-81.53髋关节置换术】需同时编码【00.74-00.78】任何明确类型轴面
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1499($data, $item)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['ICD9_ID1'];
            if (stripos($ICD9_ID1, '81.51') !== false || stripos($ICD9_ID1, '81.52') !== false || stripos($ICD9_ID1, '81.53') !== false) {
                $res1 = 1;
            } elseif (stripos($ICD9_ID1, '00.74') !== false || stripos($ICD9_ID1, '00.75') !== false || stripos($ICD9_ID1, '00.76') !== false || stripos($ICD9_ID1, '00.77') !== false || stripos($ICD9_ID1, '00.78') !== false) {
                $res2 = 1;
            }
        }

        if (empty($res1)) {
            return false;
        }

        if (empty($res2)) {
            return true;
        }

        return false;
    }

    /**
     * 手术【39.61】不能有【50.92或39.65或39.95或39.66】
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1500($data, $item)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['ICD9_ID1'];
            if (stripos($ICD9_ID1, '39.61') !== false) {
                $res1 = 1;
            } elseif (stripos($ICD9_ID1, '50.92') !== false || stripos($ICD9_ID1, '39.65') !== false || stripos($ICD9_ID1, '39.95') !== false || stripos($ICD9_ID1, '39.66') !== false) {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 诊断【T20-T30】烧伤，同时需要编【T31或T32】面积
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1501($data, $item)
    {
        $arr = ['T20', 'T21', 'T22', 'T23', 'T24', 'T25', 'T26', 'T27', 'T28', 'T29', 'T30'];
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }

            $ICD10_ID1 = $diagnosis['ICD10_ID1'];
            if (stripos($ICD10_ID1, 'T31') === 0 || stripos($ICD10_ID1, 'T32') === 0) {
                $res2 = 1;
            } else {
                foreach ($arr as $val) {
                    if (stripos($ICD10_ID1, $val) === 0) {
                        $res1 = 1;
                        break;
                    }
                }
            }
        }

        if (empty($res1)) {
            return false;
        }

        if (empty($res2)) {
            return true;
        }

        return false;
    }

    /**
     * 38.45另编码心肺搭桥[体外循环]（39.61）
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1502($data, $item)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['ICD9_ID1'];
            if (stripos($ICD9_ID1, '38.45') === 0) {
                $res1 = 1;
            } elseif (stripos($ICD9_ID1, '39.61') === 0) {
                $res2 = 1;
            }
        }

        if (empty($res1)) {
            return false;
        }
        if (empty($res2)) {
            return true;
        }
        return false;
    }

    /**
     * 手术【39.90或36.06或36.07或00.55或00.63或00.64或00.65】手术中需要有【00.45-00.48 + 治疗血管的数量00.40-00.43】
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1503($data, $item)
    {
        $arr1 = ['39.90', '36.06', '36.07', '00.55', '00.63', '00.64', '00.65'];
        $res1 = 0;
        $res2 = 0;
        $res3 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['ICD9_ID1'];
            foreach ($arr1 as $val) {
                if (stripos($ICD9_ID1, $val) !== false) {
                    $res1 = 1;
                } elseif (stripos($ICD9_ID1, '00.45') !== false || stripos($ICD9_ID1, '00.46') !== false || stripos($ICD9_ID1, '00.47') !== false || stripos($ICD9_ID1, '00.48') !== false) {
                    $res2 = 1;
                } elseif (stripos($ICD9_ID1, '00.40') !== false || stripos($ICD9_ID1, '00.41') !== false || stripos($ICD9_ID1, '00.42') !== false || stripos($ICD9_ID1, '00.43') !== false) {
                    $res3 = 1;
                }
            }
        }

        if (empty($res1)) {
            return false;
        }
        if (empty($res2) || empty($res3)) {
            return true;
        }
        return false;
    }

    /**
     * 手术【39.74】手术中需要有治疗血管的数量【00.40-00.43】
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1504($data, $item)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['ICD9_ID1'];
            if (stripos($ICD9_ID1, '39.74') !== false) {
                $res1 = 1;
            } elseif (stripos($ICD9_ID1, '00.40') !== false || stripos($ICD9_ID1, '00.41') !== false || stripos($ICD9_ID1, '00.42') !== false || stripos($ICD9_ID1, '00.43') !== false) {
                $res2 = 1;
            }
        }

        if (empty($res1)) {
            return false;
        }
        if (empty($res2)) {
            return true;
        }
        return false;
    }

    /**
     * 收费【阿替普酶】手术要有【99.1005 脑动脉血栓溶解剂灌注】
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1505($data, $item)
    {
        $FYMC_ARR = array_column($data['feeDetail'], 'FYMC');
        $res = false;
        foreach ($FYMC_ARR as $value) {
            if (stripos($value, '阿替普酶') !== false) {
                $res = true;
            }
        }
        if (!$res) {
            return false;
        }

        $res1 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['ICD9_ID1'];
            if ($ICD9_ID1 == '99.1005') {
                $res1 = 1;
            }
        }

        if (empty($res1)) {
            return true;
        }

        return false;
    }

    /**
     * 手术中 03.90另编码输注泵的置入（86.06）
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1506($data, $item)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['ICD9_ID1'];
            if (stripos($ICD9_ID1, '03.90') === 0) {
                $res1 = 1;
            } elseif (stripos($ICD9_ID1, '86.06') === 0) {
                $res2 = 1;
            }
        }

        if (empty($res1)) {
            return false;
        }
        if (empty($res2)) {
            return true;
        }
        return false;
    }

    /**
     * 37.8有导线起搏器另编导线置入、置换、去除和修复（37.70-37.77）
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1507($data, $item)
    {
        $arr = ['37.70', '37.71', '37.72', '37.73', '37.74', '37.75', '37.76', '37.77'];
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            $ICD9_ID1 = $operation['ICD9_ID1'];
            if (stripos($ICD9_ID1, '37.8') === 0) {
                $res1 = 1;
            } else {
                foreach ($arr as $val) {
                    if (stripos($ICD9_ID1, $val) === 0) {
                        $res2 = 1;
                        break;
                    }
                }
            }
        }

        if (empty($res1)) {
            return false;
        }
        if (empty($res2)) {
            return true;
        }
        return false;
    }

    /**
     * 手术名称有【***粘连松解术】 诊断名称要有【 ***粘连】
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1508($data, $item)
    {
        $res1 = 0;
        foreach ($data['operation'] as $operation) {
            if (preg_match('/^(.*)粘连松解术$/', $operation['ICD9_NAME'])) {
                $res1 = 1;
            }
        }
        if (!$res1) {
            return false;
        }

        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if (preg_match('/^(.*)粘连$/', $diagnosis['ICD10_NAME'])) {
                $res2 = 1;
            }
        }
        if (!$res2) {
            return true;
        }

        return false;
    }

    /**
     * 诊断【N20.0和N20.1】需要合并到N20.2
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1509($data, $item)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ICD10_ID1'], 'N20.0') === 0) {
                $res1 = 1;
            } elseif (stripos($diagnosis['ICD10_ID1'], 'N20.1') === 0) {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 诊断【N20和N13.3】需要合并到N13.2
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1510($data, $item)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ICD10_ID1'], 'N20') === 0) {
                $res1 = 1;
            } elseif (stripos($diagnosis['ICD10_ID1'], 'N13.3') === 0) {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 诊断【N13.0-N13.5和 N15.9】需要合并到N13.6
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1511($data, $item)
    {
        $arr = ['N13.0', 'N13.1', 'N13.2', 'N13.3', 'N13.4', 'N13.5'];
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($diagnosis['ICD10_ID1'], 'N15.9') === 0) {
                $res2 = 1;
            } else {
                foreach ($arr as $val) {
                    if (stripos($diagnosis['ICD10_ID1'], $val) === 0) {
                        $res1 = 1;
                        break;
                    }
                }
            }
        }

        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 诊断信息除最后一条外，诊断编码或名称不能为空
     * @param $data
     * @param $item
     * @return bool
     */
    public function rule1514($data, $item)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }
        $res = '';
        foreach ($data['diagnosis'] as $key => $diagnosis) {
            if (empty($diagnosis['ICD10_ID1']) || empty($diagnosis['ICD10_NAME'])) {
                $res = $key;
                break;
            }
        }

        if ($res !== '') {
            $res1 = count($data['diagnosis']) - 1;
            if ($res < $res1) {
                return true;
            }
        }

        return false;
    }


}
