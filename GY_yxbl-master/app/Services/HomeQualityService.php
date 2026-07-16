<?php

namespace App\Services;

use App\Console\Commands\DataFormat\Cyjl;
use App\Model\Appeal;
use App\Model\Bllb292;
use App\Model\CaseQuality;
use App\Model\CaseQualityV2;
use App\Model\DRG2;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\ErrorRule;
use App\Model\ErrorV2;
use App\Model\GY_ZD;
use App\Model\ICD10;
use App\Model\ICD9;
use App\Model\PatientInfoCostV2;
use App\Model\PatientInfo;
use App\Model\PatientInfoOperationV2;
use App\Model\RuleWordMap;
use App\Model\SM_SSAP;
use App\Model\SSCZ;
use App\Model\ZY_BRRY;
use Illuminate\Support\Facades\Log;

class HomeQualityService
{
    public static $noPhone = [11111111111, 12345678911, 1111111, 1234567];

    /**
     * 病案首页质控
     * @param $ZYH
     * @param $data
     * @param $errorRuleData
     * @param $depData
     * @return bool
     */
    public function qualityContrl($ZYH, $data, $errorRuleData, $depData)
    {
        // 入院时间
        $data["AAB01"] = "";
        if (!empty($data["RYSJ"])) {
            $RYSJ = str_replace("年", "-", $data["RYSJ"]);
            $RYSJ = str_replace("月", "-", $RYSJ);
            $RYSJ = str_replace("日", " ", $RYSJ);
            if (strpos($RYSJ, "时") !== false) {
                $RYSJ = str_replace("时", ":", $RYSJ) . "00:00";
            }
            if (stripos($RYSJ, "1970") === false) {
                $data["AAB01"] = $RYSJ;
            }
        }
        Log::info("RYSJ->AAB01:" . $data["AAB01"]);

        // 出院时间
        $data["AAC01"] = "";
        if (!empty($data["CYSJ"])) {
            $CYSJ = str_replace("年", "-", $data["CYSJ"]);
            $CYSJ = str_replace("月", "-", $CYSJ);
            $CYSJ = str_replace("日", " ", $CYSJ);
            if (strpos($CYSJ, "时") !== false) {
                $CYSJ = str_replace("时", ":", $CYSJ) . "00:00";
            }
            if (stripos($CYSJ, "1970") === false) {
                $data["AAC01"] = $CYSJ;
            }
        }
        $insertData = [];
        $required = 0;

        $appealRuleIds = [];
        $appeal = Appeal::query()
            ->where(["quality_type" => 1, "type" => 2, "ZYH" => (string)$ZYH])
            ->whereIn("status", ['1', '3'])
            ->get(['error_id'])
            ->toArray();
        //Log::info("appeal111---->" . $ZYH, $appeal);
        if ($appeal) {
            Log::info("appeal---->" . $ZYH, $appeal);
            $appealRuleIds = array_column($appeal, 'error_id');
        }
        // 删除历史质控数据
        $appealRuleIds = array_values(array_filter($appealRuleIds));

        //Log::info("appealRuleIds---->" . $ZYH, $appealRuleIds);

        //ErrorV2::query()->where("ZYH", "=", $ZYH)->whereIn("error_rule", $appealRuleIds)->update(["status" => 1]);

        /* $errorRuleData = RuleWordMap::query()->get()->toArray();
        $errorRuleData = array_column($errorRuleData, null, 'keyword'); */
        $score_1 = 0;
        foreach ($errorRuleData as $ruleId => $errorRule) {
            if (in_array($ruleId, $appealRuleIds)) {
                continue;
            }
            $method = "rule" . $ruleId;
            if (!empty($errorRuleData[$ruleId . "_" . $ZYH])) {
                continue;
            }

            if (!method_exists(new HomeQualityService(), $method)) {
                continue;
            }

            $res = $this->$method($ZYH, $data);
            if (!empty($res)) {
                $basis = '';
                $hospitalName = !empty($data["USERNAME"])
                    ? $data["USERNAME"]
                    : config("confAdmin.hospital_name");

                if ($ruleId == 2002 || $ruleId == 2003 || $ruleId == 2001 || $ruleId == 1458) {
                    $desc = !empty($res) ? $res : $errorRule["desc"];
                } else {
                    $desc = $errorRule["desc"];
                }
                //判断res是否为数组
                if (is_array($res)) {
                    //Log::info("res:" , $res);
                    //取desc，逗号分隔
                    foreach ($res as $value) {
                        $basis .= $value["desc"] . ",";
                    }
                    $basis = trim($basis, ",");
                }

                if ($errorRule["level"] == 0) {
                    $required++;
                }

                $CYKSBM = !empty($data["CYKSBM"]) ? $data["CYKSBM"] : "";
                $CYKSBM = !empty($depData[$CYKSBM])
                    ? $depData[$CYKSBM]
                    : $CYKSBM;
                $insertData[] = [
                    "hospital_name" => $hospitalName, // 医院名称
                    "error_rule" => $ruleId, // 规则id
                    "AAA28" => $data["BAH"], // 住院号码
                    "ZYH" => $ZYH, // 住院号
                    "AAA01" => $data["XM"] ?? "", // 姓名
                    "AAB01" => $data["AAB01"], // 入院时间
                    "AAC01" => $data["AAC01"], // 出院时间
                    "desc" => $desc, // 错误内容
                    "coder_id" => $data["BMY_BH"] ?? "", // 编码员编号
                    "coder_name" => $data["BMY"] ?? "", // 编码远姓名
                    "CYKSBM" => $CYKSBM, // 出院科室编码
                    "CYKB" => $data["CYKB"], // 出院科室名称
                    "basis" => $basis,
                    //"ZZYS_BH" => $data["ZZYS_BH"], // 主治医师编码
                    "ZZYS" => $data["ZZYS"], // 主治医师姓名
                    //"ZYYS_BH" => $data["ZYYS_BH"], // 住院医师编码
                    "ZYYS" => $data["ZYYS"], // 住院医师姓名
                    "ICD10_ID1" => $data["JBDM"], // 主要诊断编码
                    "ICD10_NAME" => $data["ZYZD"], // 主要诊断名称
                    "ICD9_ID1" => $data["SSJCZBM1"], // 主要手术编码
                    "ICD9_NAME" => $data["SSJCZMC1"], // 主要手术名称
                ];
                $score_1 += $errorRule["down"];
            }
        }

        ErrorV2::query()
            ->where("ZYH", "=", $ZYH)
            ->update(["status" => 1]);
        $appealids = [];
        if (!empty($insertData)) {
            foreach ($insertData as $key => $v) {
                $appeal1 = Appeal::where('ZYH', $v['ZYH'])->where('error_id', $v['error_rule'])->get()->toArray();
                if (!empty($appeal1)) {
                    $insertData[$key]['appeal_id'] = $appeal1[0]['id'];
                    $insertData[$key]['is_appeal'] = 1;
                    $appealids[] = $appeal1[0]['id'];
                } else {
                    $insertData[$key]['appeal_id'] = 0;
                    $insertData[$key]['is_appeal'] = 0;
                }
            }
            ErrorV2::query()->insert($insertData);
        }
        //获取所有首页规则id
        $allRuleIds = ErrorRule::where('status', '0')->pluck('id')->toArray();
        //转化为数组
        $allRuleIds = array_values($allRuleIds);
        //Log::info("allRuleIds---->" . $ZYH, $allRuleIds);
        if (empty($appealids)) {
            Appeal::where('ZYH', $ZYH)->whereIn('error_id', $allRuleIds)->where('status', '=', '0')->update(['status' => 3]);
        } else {
            Appeal::whereNotIn('id', $appealids)->where('ZYH', $ZYH)->whereIn('error_id', $allRuleIds)->where('status', '=', '0')->update(['status' => 3]);
        }


        $score = 100 - $score_1;
        $score_lv = "优";
        if ($score >= 97) {
            $score_lv = '优';
        } elseif ($score >= 90 && $score < 97) {
            $score_lv = '良';
        } elseif ($score >= 75 && $score < 90) {
            $score_lv = '中';
        } elseif ($score < 75) {
            $score_lv = '差';
        }
        // is_case 将病例的质控状态改为未质控
        PatientInfo::query()->where('MED_REC_ID', '=', $ZYH)->update(['home_ysz_score' => $score, 'home_ysz_score_lv' => $score_lv]);
        ZY_BRRY::query()->where('ZYH', '=', $ZYH)->update(['home_ysz_score' => $score, 'home_ysz_score_lv' => $score_lv]);

        return ['required' => $required, 'score' => $score_1];
    }

    /**
     * 组织机构代码
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1($ZYH, $data)
    {
        if (
            mb_strlen($data["hospitalId"]) < 6 ||
            mb_strlen($data["hospitalId"]) > 22 ||
            $data["hospitalId"] == 123456
        ) {
            return true;
        }
        return false;
    }

    /**
     * 病案号
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule2($ZYH, $data)
    {
        if (!empty($data["BAH"]) && (mb_strlen($data["BAH"]) < 6 || mb_strlen($data["BAH"]) > 50)) {
            return true;
        }
        return false;
    }

    /**
     * 医疗机构名称
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule3($ZYH, $data)
    {
        $res = preg_match('/^[\x7f-\xff]+$/', $data["USERNAME"]);
        if (
            mb_strlen($data["USERNAME"]) < 4 ||
            $data["USERNAME"] > 80 ||
            !$res
        ) {
            return true;
        }
        return false;
    }

    /**
     * 住院次数
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule4($ZYH, $data)
    {
        if (empty($data["ZYCS"])) {
            return false;
        }
        $res = preg_match('/^[1-9]\d*$/', $data["ZYCS"]);
        if (!$res) {
            return true;
        }
        return false;
    }

    /**
     * 入院时间
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule5($ZYH, $data)
    {
        if (empty($data["RYSJ"])) {
            return false;
        }

        if (
            stripos($data["RYSJ"], "年") === false ||
            stripos($data["RYSJ"], "月") === false ||
            stripos($data["RYSJ"], "日") === false ||
            stripos($data["RYSJ"], "时") === false
        ) {
            return false;
        }

        // 入院时间格式处理
        $AAB01 = "";
        if (!empty($data["RYSJ"])) {
            $RYSJ = str_replace("年", "-", $data["RYSJ"]);
            $RYSJ = str_replace("月", "-", $RYSJ);
            $RYSJ = str_replace("日", "", $RYSJ);
            if (strpos($RYSJ, "时") !== false) {
                $RYSJ = str_replace("时", ":", $RYSJ) . "00:00";
            }
            if (stripos($RYSJ, "1970") === false) {
                $AAB01 = $RYSJ;
            }
        }

        // 出院时间格式处理
        $AAC01 = "";
        if (!empty($data["CYSJ"])) {
            $CYSJ = str_replace("年", "-", $data["CYSJ"]);
            $CYSJ = str_replace("月", "-", $CYSJ);
            $CYSJ = str_replace("日", " ", $CYSJ);
            if (strpos($CYSJ, "时") !== false) {
                $CYSJ = str_replace("时", ":", $CYSJ) . "00:00";
            }
            if (stripos($CYSJ, "1970") === false) {
                $AAC01 = $CYSJ;
            }
        }
        if (empty($AAC01) || stripos($AAC01, "1970") !== false || stripos($AAC01, "0000") !== false) {
            return false;
        }

        if (empty($AAB01) || $AAB01 >= $AAC01) {
            return true;
        }
        return false;
    }

    /**
     * 健康卡号
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule6($ZYH, $data)
    {
        if (empty($data["JKKH"])) {
            return false;
        }

        if ($data["JKKH"] === "" || $data["JKKH"] === null) {
            return true;
        }
        return false;
    }

    /**
     * 患者姓名
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule7($ZYH, $data)
    {
        $basis = [];
        if (empty($data["XM"])) {
            $basis[] = ['desc' => '患者姓名不能为空', 'location' => ['user' => ['XM']]];
            return $basis;
        }

        if (
            (!empty($data["GJ"]) && $data["GJ"] != "中国")
        ) {
            return $basis;
        }
        $XM = $data["XM"];
        if (mb_strlen($XM) < 2 || mb_strlen($XM) > 40) {
            return true;
        } else {
            // 获取第一位
            $firstStr = mb_substr($XM, 0, 1);
            // 获取最后一位
            $xmLen = mb_strlen($XM);
            $endStr = mb_substr($XM, $xmLen - 1, 1);

            $res1 = PublicService::pregMatchTszf($firstStr, "digit");
            $res2 = PublicService::pregMatchTszf($firstStr, "punct");

            $res3 = PublicService::pregMatchTszf($endStr, "digit");
            $res4 = PublicService::pregMatchTszf($endStr, "punct");

            $res5 = PublicService::pregMatchTszf($XM, "space");
            if ($res2 || $res4) {
                $basis[] = ['desc' => '患者姓名中有标点符号', 'location' => ['user' => ['XM']]];
            } elseif ($res1 || $res3) {
                $basis[] = ['desc' => '患者姓名中有数字', 'location' => ['user' => ['XM']]];
            } elseif ($res5) {
                $basis[] = ['desc' => '患者姓名中有空格', 'location' => ['user' => ['XM']]];
            }
        }

        return $basis;
    }

    /**
     * 出生地省
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule8($ZYH, $data)
    {
        if (empty($data["CSD_SHENG"])) {
            return false;
        }
        if (
            mb_strlen(trim($data["CSD_SHENG"])) < 2 ||
            is_numeric(trim($data["CSD_SHENG"]))
        ) {
            return true;
        }
        return false;
    }

    /**
     * 籍贯省
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule9($ZYH, $data)
    {
        if (empty($data["GG_SHENG"])) {
            return false;
        }
        if (
            mb_strlen(trim($data["GG_SHENG"])) < 2 ||
            is_numeric(trim($data["GG_SHENG"]))
        ) {
            return true;
        }
        return false;
    }

    /**
     * 民族
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule10($ZYH, $data)
    {
        if (empty($data["MZ"])) {
            return false;
        }
        if (empty($data["MZ"]) || $data["MZ"] == "-") {
            return true;
        }
        return false;
    }

    /**
     * 身份证号
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule11($ZYH, $data)
    {
        //如果是里面的住院号，放过不质控身份证号为空的情况
        $zyhStr = RuleWordMap::query()->where('id', 120)->value('keyword');
        Log::info("zyh:" . $ZYH);
        Log::info("wwwwww:" . $zyhStr);
        Log::info("qqqqqqq:" . $data['ZYH']);
        $zyhArr = explode(',', $zyhStr);
        if (in_array($data['ZYH'], $zyhArr)) {
            return false;
        }

        if (!empty($data["XM"]) && false !== stripos($data["XM"], "无名氏")) {
            return false;
        }
        if (
            (!empty($data["GJ"]) && $data["GJ"] != "中国") ||
            false !== stripos($data["CYBF"], "儿科")
        ) {
            return false;
        }
        //如果年龄小于等于1岁，放过不质控身份证号为空的情况
        if ($data["NL"] <= 1) {
            return false;
        }

        if (!empty($data["SFZH"]) && mb_substr(trim($data["SFZH"]), 0, 1) === "A") {
            return false;
        }

        if (
            (!empty($data["CYBF"])) && (false !== stripos($data["CYBF"], "儿科")) &&
            mb_strlen($data["SFZH"]) != 15 &&
            mb_strlen($data["SFZH"]) != 18
        ) {
            return true;
        }

        // 匹配身份证号的正确性
        $pattern =
            "/^[1-9]\d{5}(18|19|20|21|22)?\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{3}(\d|[Xx])$/";
        $pregRes = preg_match($pattern, $data["SFZH"]);
        if (!$pregRes && $data["SFZH"] != "-") {
            return true;
        }
        return false;
    }

    /**
     * 职业
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule12($ZYH, $data)
    {
        if (empty($data["ZY"])) {
            return true;
        }
        return false;
    }

    /**
     * 婚姻状况
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule13($ZYH, $data)
    {
        if (empty($data["HY"])) {
            return false;
        }
        //1-未婚 2-已婚 3-离婚4-丧偶 5-其他 8-未说明
        //现在HY包含的字符串设置为1-未婚 2-已婚 3-离婚 4-丧偶 5-其他 8-未说明
        /* if(strpos($data['HY'],'未婚') !== false){
            $data['HY'] = 1;
        }elseif(strpos($data['HY'],'已婚') !== false){
            $data['HY'] = 2;
        }elseif(strpos($data['HY'],'离婚') !== false){
            $data['HY'] = 3;
        }elseif(strpos($data['HY'],'丧偶') !== false){
            $data['HY'] = 4;
        }elseif(strpos($data['HY'],'其他') !== false){
            $data['HY'] = 5;
        }elseif(strpos($data['HY'],'未说明') !== false){
            $data['HY'] = 8;
        } */
        //从GY_ZD表中获取婚姻状况
        $HY = GY_ZD::query()->where('lx', 'HY')->get()->toArray();
        $hyarr = [];
        foreach ($HY as $item) {
            $hyarr[] = $item['jm_field'];
            if (strpos($data['HY'], $item['suroce_field']) !== false) {
                $data['HY'] = $item['jm_field'];
            }
        }
        if (!in_array(intval($data["HY"]), $hyarr)) {
            return true;
        }
        // if (mb_strlen($data['HY']) < 1) {
        //     return true;
        // }
        return false;
    }

    /**
     * 现住址省
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule14($ZYH, $data)
    {
        if (empty($data["XZZ_SHENG"])) {
            return false;
        }
        if (
            mb_strlen(trim($data["XZZ_SHENG"])) < 2 ||
            is_numeric(trim($data["XZZ_SHENG"]))
        ) {
            return true;
        }
        return false;
    }

    /**
     * 电话
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule15($ZYH, $data)
    {
        if (empty($data["DH"])) {
            return false;
        }

        if ($data["DH"] == "-") {
            return false;
        }

        if (
            mb_strlen($data["DH"]) < 7 ||
            in_array($data["DH"], self::$noPhone)
        ) {
            return true;
        }
        $res1 = preg_match('/^1[0-9]{10}$/', $data["DH"]);
        $res2 = preg_match('/^[0-9]{4}[\-][0-9]{7}$/', $data["DH"]);
        $res3 = preg_match('/^[0-9]{7}$/', $data["DH"]);
        if (!$res1 && !$res2 && !$res3) {
            return true;
        }

        return false;
    }

    /**
     * 现住址邮政编码
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule16($ZYH, $data)
    {
        if (mb_strlen($data["YB1"]) != 6 || $data["YB1"] == 123456) {
            return true;
        }
        return false;
    }

    /**
     * 户籍省
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule17($ZYH, $data)
    {
        if (
            mb_strlen(trim($data["HKDZ_SHENG"])) < 2 ||
            is_numeric(trim($data["HKDZ_SHENG"]))
        ) {
            return true;
        }
        return false;
    }

    /**
     * 户籍邮编
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule18($ZYH, $data)
    {
        if (mb_strlen($data["YB2"]) != 6 || $data["YB2"] == 123456) {
            return true;
        }
        return false;
    }

    /**
     * 工作单位及地址
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule19($ZYH, $data)
    {
        if (trim($data["GZDWJDZ"]) == "无" || trim($data["GZDWJDZ"]) == "-") {
            return false;
        }

        if (
            mb_strlen(trim($data["GZDWJDZ"])) < 2 ||
            is_numeric(trim($data["GZDWJDZ"]))
        ) {
            return true;
        }
        return false;
    }

    /**
     * 单位电话
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule20($ZYH, $data)
    {
        if ($data["DWDH"] == "-") {
            return false;
        }

        if (
            mb_strlen($data["DWDH"]) < 7 ||
            in_array($data["DWDH"], self::$noPhone)
        ) {
            return true;
        }
        $res1 = preg_match('/^1[0-9]{10}$/', $data["DWDH"]);
        $res2 = preg_match('/^[0-9]{4}[\-][0-9]{7}$/', $data["DWDH"]);
        $res3 = preg_match('/^[0-9]{7}$/', $data["DWDH"]);
        if (!$res1 && !$res2 && !$res3) {
            return true;
        }
        return false;
    }

    /**
     * 单位邮编
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule21($ZYH, $data)
    {
        if (mb_strlen($data["YB3"]) != 6 || $data["YB3"] == 123456) {
            return true;
        }
        return false;
    }

    /**
     * 联系人姓名
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule22($ZYH, $data)
    {
        $basis = [];
        $LXRXM = $data["LXRXM"];
        if (mb_strlen($LXRXM) < 2 || mb_strlen($LXRXM) > 40) {
            $basis[] = ['desc' => '联系人姓名长度应在2~40之间', 'location' => ['user' => ['LXRXM']]];
        } else {
            // 获取第一位
            $firstStr = mb_substr($LXRXM, 0, 1);
            // 获取最后一位
            $xmLen = mb_strlen($LXRXM);
            $endStr = mb_substr($LXRXM, $xmLen - 1, 1);

            $res1 = PublicService::pregMatchTszf($firstStr, "digit");
            $res2 = PublicService::pregMatchTszf($firstStr, "punct");

            $res3 = PublicService::pregMatchTszf($endStr, "digit");
            $res4 = PublicService::pregMatchTszf($endStr, "punct");

            $res5 = PublicService::pregMatchTszf($LXRXM, "space");
            if ($res2 || $res4) {
                $basis[] = ['desc' => '联系人姓名中有标点符号', 'location' => ['user' => ['LXRXM'], 'zd' => [], 'ss' => []]];
            } elseif ($res1 || $res3) {
                $basis[] = ['desc' => '联系人姓名中有数字', 'location' => ['user' => ['LXRXM'], 'zd' => [], 'ss' => []]];
            } elseif ($res5) {
                $basis[] = ['desc' => '联系人姓名中有空格', 'location' => ['user' => ['LXRXM'], 'zd' => [], 'ss' => []]];
            }
        }

        return $basis;
    }

    /**
     * 联系人关系
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule23($ZYH, $data)
    {
        if (mb_strlen($data["GX"]) < 1) {
            return true;
        }
        return false;
    }

    /**
     * 联系人地址
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule24($ZYH, $data)
    {
        if (mb_strlen(trim($data["DZ"])) < 2 || is_numeric(trim($data["DZ"]))) {
            return true;
        }
        return false;
    }

    /**
     * 联系人电话
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule25($ZYH, $data)
    {
        if ($data["DH2"] == "-") {
            return false;
        }

        if (
            mb_strlen($data["DH2"]) < 7 ||
            in_array($data["DH2"], self::$noPhone)
        ) {
            return true;
        }
        $res1 = preg_match('/^1[0-9]{10}$/', $data["DH2"]);
        $res2 = preg_match('/^[0-9]{4}[\-][0-9]{7}$/', $data["DH2"]);
        $res3 = preg_match('/^[0-9]{7}$/', $data["DH2"]);
        if (!$res1 && !$res2 && !$res3) {
            return true;
        }
        return false;
    }

    /**
     * 性别
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule26($ZYH, $data)
    {
        if (mb_strlen($data["XB"]) < 1) {
            return true;
        }
        return false;
    }

    /**
     * 出生日期
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule27($ZYH, $data)
    {
        if (mb_strlen($data["CSRQ"]) < 6) {
            return true;
        }
        return false;
    }

    /**
     * 年龄
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule28($ZYH, $data)
    {
        if (
            $data["NL"] == 0 &&
            $data["BZYZSNL"] == 0 &&
            $data["CYBF"] == "新生儿科、儿童重症医学科"
        ) {
            return false;
        }

        $res1 = preg_match('/^[1-9][0-9]*$/', $data["NL"]);
        $arr = [];
        for ($i = 1; $i < 365; $i++) {
            $arr[] = $i;
        }
        if (!$res1 && !in_array($data["BZYZSNL"], $arr)) {
            return true;
        }
        return false;
    }

    /**
     * 国籍
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule29($ZYH, $data)
    {
        $GJ = trim($data["GJ"]);
        if (mb_strlen($GJ) < 2 || is_numeric($GJ)) {
            return true;
        }
        return false;
    }

    /**
     * 入院科别
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule30($ZYH, $data)
    {
        if (empty($data["RYKB"])) {
            return true;
        }
        return false;
    }

    /**
     * 入院途径
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule31($ZYH, $data)
    {
        if (mb_strlen($data["RYTJ"]) < 1) {
            return true;
        }
        return false;
    }

    /**
     * 入院病房
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule32($ZYH, $data)
    {
        if (empty($data["RYBF"])) {
            return true;
        }
        return false;
    }

    // /**
    //  * 转科科别
    //  * @param $ZYH
    //  * @param $data
    //  * @return bool
    //  */
    // public function rule33($ZYH, $data)
    // {
    //     $homeSzService = new HomeSzService();
    //     $zyZkjlData = $homeSzService->ZY_ZKJL($ZYH);
    //     if (empty($zyZkjlData)) {
    //         if (!empty($data["ZKKB"])) {
    //             return true;
    //         }
    //         return false;
    //     }

    //     $res = false;
    //     foreach ($zyZkjlData as $value) {
    //         if (in_array($value["HCLX"], [1, 3])) {
    //             $res = true;
    //             break;
    //         }
    //     }

    //     if ($res) {
    //         if (empty($data["ZKKB"]) || mb_strlen($data["ZKKB"]) < 3) {
    //             return true;
    //         }
    //     } else {
    //         if ($data["ZKKB"] == "-") {
    //             return false;
    //         }

    //         if (!empty($data["ZKKB"])) {
    //             return true;
    //         }
    //     }

    //     return false;
    // }

    /**
     * 出院科别
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule34($ZYH, $data)
    {
        if (empty($data["CYKB"]) && empty($data["AAC02C"])) {
            return true;
        }
        return false;
    }

    /**
     * 实际住院天数
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule36($ZYH, $data)
    {
        $res = preg_match('/^[1-9]\d*$/', $data["SJZYTS"]);
        if (!$res) {
            return true;
        }
        return false;
    }

    /**
     * 门(急)诊诊断编码
     * @param $data
     * @return array
     */
    public function rule37($ZYH, $data)
    {
        //$basis = [];
        if (empty($data['JBBM'])) {
            return true;
        }
        return false;
    }


    /**
     * 门(急)诊诊断名称
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule38($ZYH, $data)
    {
        if (empty($data["MZZD"])) {
            return true;
        }
        return false;
    }

    /**
     * 出院主要诊断编码
     * @param $data
     * @return array
     */
    public function rule39($ZYH, $data)
    {
        if (empty(trim($data["JBDM"]))) {
            return true;
        }
        return false;
    }



    /**
     * 出院主要诊断名称
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule40($ZYH, $data)
    {
        if (empty(trim($data["ZYZD"]))) {
            return true;
        }
        return false;
    }

    /**
     * 有无药物过敏
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule42($ZYH, $data)
    {
        if ($data["YWGM"] == "-") {
            return false;
        }

        if (empty($data["YWGM"])) {
            return true;
        }

        return false;
    }

    /**
     * 科主任
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule43($ZYH, $data)
    {
        //        $res = preg_match('/^[\x7f-\xff]+$/', $data['KZR']);
        if (((mb_strlen($data["KZR"]) < 2 && mb_strlen($data["KZR_BH"]) < 2)) || ((mb_strlen($data["KZR"]) > 40 && mb_strlen($data["KZR_BH"]) > 40))) {
            return true;
        }
        return false;
    }

    /**
     * 主(副主)任医师
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule44($ZYH, $data)
    {
        if (((mb_strlen($data["ZRYS"]) < 2 && mb_strlen($data["ZRYS_BH"]) < 2)) || ((mb_strlen($data["ZRYS"]) > 40 && mb_strlen($data["ZRYS_BH"]) > 40))) {
            return true;
        }
        return false;
    }

    /**
     * 主治医师
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule47($ZYH, $data)
    {
        if (((mb_strlen($data["ZZYS"]) < 2 && mb_strlen($data["ZZYS_BH"]) < 2)) || ((mb_strlen($data["ZZYS"]) > 40 && mb_strlen($data["ZZYS_BH"]) > 40))) {
            return true;
        }
        return false;
    }

    /**
     * 住院医师
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule49($ZYH, $data)
    {
        if (((mb_strlen($data["ZYYS"]) < 2 && mb_strlen($data["ZYYS_BH"]) < 2)) || ((mb_strlen($data["ZYYS"]) > 40 && mb_strlen($data["ZYYS_BH"]) > 40))) {
            return true;
        }
        return false;
    }

    /**
     * 责任护士
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule50($ZYH, $data)
    {
        if (((mb_strlen($data["ZRHS"]) < 2 && mb_strlen($data["ZRHS_BH"]) < 2)) || ((mb_strlen($data["ZRHS"]) > 40 && mb_strlen($data["ZRHS_BH"]) > 40))) {
            return true;
        }
        return false;
    }

    /**
     * ABO血型
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule52($ZYH, $data)
    {
        $AEG01C = config("dictionaries.AEG01C");
        if (empty($data["XX"])) {
            return true;
        }
        return false;
    }

    /**
     * RH血型
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule53($ZYH, $data)
    {
        $AEG02C = config("dictionaries.AEG02C");

        if (empty($data["RH"])) {
            return true;
        }
        return false;
    }

    /**
     * 手术操作编码
     * @param $data
     * @return array
     */
    public function rule54($data)
    {
        $basis = [];
        for ($i = 1; $i <= 41; $i++) {

            if (empty($data["SSJCZBM" . $i])) {
                $desc = '手术编码不能为空未填写';
                $basis[] = ['desc' => $desc, 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
            }
        }
        return $basis;
    }

    /**
     * 主要手术操作名称
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule55($ZYH, $data)
    {
        if ($data["D33"] > 0) {
            if (empty($data["SSJCZMC1"])) {
                return true;
            }
        }
        return false;
    }

    /**
     * 主要手术操作日期
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule56($ZYH, $data)
    {
        for ($i = 1; $i <= 41; $i++) {
            if (isset($data["SSJCZMC" . $i]) && !empty($data["SSJCZMC" . $i])) {
                if (empty($data["SSJCZRQ" . $i])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 是否有出院31日内再住院计划
     * @param $ZYH
     * @param $data
     * @return bool
     */
    /* public function rule63($ZYH, $data)
    {
        $AEM03C = config("dictionaries.AEM03C");
        if (empty($data["SFZZYJH"])) {
            return true;
        }
        return false;
    } */

    /**
     * 离院方式
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule64($ZYH, $data)
    {
        $AEM01C = config("dictionaries.AEM01C");
        if ($data["LYFS"] == '-' || $data["LYFS"] == '' || empty($data["LYFS"]) || $data["LYFS"] == '其他' || $data["LYFS"] == 9) {
            return true;
        }
        return false;
    }

    /**
     * 住院总费用
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule65($ZYH, $data)
    {
        // 添加调试日志记录实际值和类型
        Log::info("rule65 check: ZFY={$data['ZFY']}(type:" . gettype($data['ZFY']) . "), ZFJE={$data['ZFJE']}(type:" . gettype($data['ZFJE']) . ")");

        // 确保比较使用浮点数值，并修正正则表达式中的小数点转义
        $condition1 = floatval($data["ZFY"]) < floatval($data["ZFJE"]);
        $condition2 = !preg_match('/^\d+(\.\d{1,4})?$/', $data["ZFY"]);
        // 记录每个条件的结果
        Log::info("rule65 conditions: condition1(ZFY < ZFJE)=" . ($condition1 ? 'true' : 'false') . ", condition2(format check)=" . ($condition2 ? 'true' : 'false'));

        if ($condition2 || $condition1) {
            return true;
        }

        return false;
    }

    /**
     * 住院总费用其中自付金额
     * @param $data
     * @return array
     */
    public function rule66($ZYH, $data)
    {
        $ADA01 = $data['ZFY'] ?? '';
        $ADA0101 = $data['ZFJE'] ?? '';
        $basis = [];
        if (!is_numeric($ADA0101)) {
            return true;
        } elseif ($ADA01 < $ADA0101) {
            return true;
        }
        return false;
    }

    /**
     * 病理诊断编码
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule67($ZYH, $data)
    {
        $zyzd = !empty($data["JBDM"]) ? $data["JBDM"] : "";
        if (empty($zyzd)) {
            return false;
        }

        $arr = [
            "C",
            "DO0",
            "DO1",
            "DO2",
            "DO3",
            "DO4",
            "DO5",
            "DO6",
            "DO7",
            "DO8",
            "DO9",
        ];
        for ($i = 10; $i <= 48; $i++) {
            $arr[] = "D" . $i;
        }

        $res = false;
        foreach ($arr as $value) {
            if (stripos($zyzd, $value) === 0) {
                $res = true;
                break;
            }
        }
        if ($res && empty($data["JBMM"])) {
            return true;
        }

        return false;
    }

    /**
     * 病理诊断名称
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule68($ZYH, $data)
    {
        $zyzd = !empty($data["JBDM"]) ? $data["JBDM"] : "";
        if (empty($zyzd)) {
            return false;
        }

        $arr = [
            "DO0",
            "DO1",
            "DO2",
            "DO3",
            "DO4",
            "DO5",
            "DO6",
            "DO7",
            "DO8",
            "DO9",
        ];
        for ($i = 10; $i <= 48; $i++) {
            $arr[] = "D" . $i;
        }

        for ($j = 0; $j <= 97; $j++) {
            if ($j < 10) {
                $arr[] = "C0" . $j;
            } else {
                $arr[] = "C" . $j;
            }
        }

        $res = false;
        foreach ($arr as $value) {
            if (stripos($zyzd, $value) === 0) {
                $res = true;
                break;
            }
        }
        if ($res && empty($data["BLZD"])) {
            return true;
        }

        return false;
    }

    /**
     * 病理号
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule69($ZYH, $data)
    {
        if (
            ($data["JBMM"] == "无" ||
                $data["JBMM"] == "-" ||
                $data["JBMM"] == "未出" ||
                $data["JBMM"] == "未归") &&
            empty($data["BLH"])
        ) {
            return false;
        }

        if (!empty($data["JBMM"]) && empty($data["BLH"])) {
            return true;
        }
        return false;
    }

    /**
     * 损伤和中毒外部原因编码
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule70($ZYH, $data)
    {
        $zyzd = !empty($data["JBDM"]) ? $data["JBDM"] : "";
        if (empty($zyzd)) {
            return false;
        }

        if (stripos($zyzd, "S") === 0 || stripos($zyzd, "T") === 0) {
            if (empty($data["H23"])) {
                return true;
            }
        }

        return false;
    }

    /**
     * 损伤和中毒外部原因
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule71($ZYH, $data)
    {
        $zyzd = !empty($data["JBDM"]) ? $data["JBDM"] : "";
        if (empty($zyzd)) {
            return false;
        }

        if (stripos($zyzd, "S") === 0 || stripos($zyzd, "T") === 0) {
            if (empty($data["WBYY"])) {
                return true;
            }
        }

        return false;
    }

    /**
     * 过敏药物名称
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule72($ZYH, $data)
    {
        $res = false;
        if ($data["GMYW"] == "-") {
            return $res;
        }
        if ($data["YWGM"] == '有' && empty($data["GMYW"])) {
            $res = true;
        } elseif (
            $data["YWGM"] == '无' &&
            !(empty($data["GMYW"]))
        ) {
            // || $data['GMYW']=='-'
            $res = true;
        } elseif ($data["YWGM"] == "-" && !empty($data["GMYW"])) {
            $res = true;
        }

        return $res;
    }

    /**
     * 主要手术操作级别
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule73($ZYH, $data)
    {
        /* if (!empty($data["SSJCZBM1"]) && empty($data["SSJB1"])) {
            return true;
        } */
        $basis = [];
        if (empty($data["SSJCZBM1"])) {
            return $basis;
        }
        for ($i = 1; $i <= 41; $i++) {
            if (!empty($data["SSJCZBM" . $i]) && empty($data["SSJB" . $i])) {
                $sscz = SSCZ::query()->where('SSBM', $data["SSJCZBM" . $i])->first();
                if (empty($sscz)) {
                    continue;
                }
                $sspb = $sscz['SSLB'] ?? '';
                if (strpos($sspb, '手术') !== false || strpos($sspb, '介入治疗') !== false) {
                    $basis[] = ['desc' => '手术【' . $data["SSJCZMC" . $i] . '】操作级别未填写', 'location' => ['ss' => ['SSJB' => $i], 'user' => [], 'zd' => []]];
                }
            }
        }
        return $basis;
    }

    /**
     * 主要手术操作术者
     * @param $ZYH
     * @param $data
     * @return bool
     */
    /* public function rule74($ZYH, $data)
    {
        if (!empty($data["SSJCZBM1"]) && empty($data["SZ1"])) {
            return true;
        }
        return false;
    } */

    /**
     * 主要手术操作I助
     * @param $ZYH
     * @param $data
     * @return bool
     */
    /* public function rule75($ZYH, $data)
    {
        if (!empty($data["SSJCZBM1"]) && empty($data["YZ1"])) {
            return true;
        }
        return false;
    } */

    /**
     * 主要手术操作II助
     * @param $ZYH
     * @param $data
     * @return bool
     */
    /* public function rule76($ZYH, $data)
    {
        if (!empty($data["SSJCZBM1"]) && empty($data["EZ1"])) {
            return true;
        }
        return false;
    } */

    /**
     * 切口愈合等级
     * @param $ZYH
     * @param $data
     * @return bool
     */
    /* public function rule77($ZYH, $data)
    {
        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            if (!empty($data["SSJCZBM" . $i]) && empty($data["QKYHLB" . $i])) {
                $res = true;
                break;
            }
        }

        return $res;
    } */

    /**
     * 主要手术操作麻醉方式
     * @param $ZYH
     * @param $data
     * @return bool
     */
    /* public function rule78($ZYH, $data)
    {
        if (!empty($data["SSJCZBM1"]) && empty($data["MZFS1"])) {
            return true;
        }
        return false;
    } */

    /**
     * 主要手术操作麻醉医师
     * @param $ZYH
     * @param $data
     * @return bool
     */
    /* public function rule79($ZYH, $data)
    {
        if (!empty($data["SSJCZBM1"]) && empty($data["MZYS1"])) {
            return true;
        }
        return false;
    } */

    /**
     * 新生儿入院体重(克)
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule82($ZYH, $data)
    {
        if (empty($data["BZYZSNL"])) {
            return false;
        }

        //$XSERYTZ = preg_match("/^[1-9][0-9]{2,3}$/", $data["XSERYTZ"]);
        //只保留数字，比如“999克”只保留999，“9999只”克保留9999
        $XSERYTZ = '';
        if (isset($data["XSERYTZ"])) {
            preg_match('/\d+/', $data["XSERYTZ"], $matches);
            if (!empty($matches)) {
                $XSERYTZ = $matches[0];
            }
        }
        Log::info("XSERYTZ:" . $XSERYTZ);
        Log::info("BZYZSNL:" . $data["BZYZSNL"]);
        //如果体重为空，或者小于100或者大于9999，则返回true
        if (
            $data["BZYZSNL"] <= 28 &&
            (empty($XSERYTZ) || $XSERYTZ < 100 || $XSERYTZ > 9999)
        ) {
            return true;
        }

        return false;
    }

    /**
     * 出院31天再住院目的
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule83($ZYH, $data)
    {
        if (strpos($data["SFZZYJH"], '无') !== false) {
            if ($data["MD"] == "-") {
                return false;
            }

            if (!empty($data["MD"])) {
                return true;
            }
        } elseif (strpos($data["SFZZYJH"], '有') !== false) {
            if (
                empty($data["MD"]) ||
                $data["MD"] == "-" ||
                $data["MD"] == "无"
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * 医嘱转院、转社区、卫生院机编码
     * @param $ZYH
     * @param $data
     * @return bool
     */
    /* public function rule84($ZYH, $data)
    {
        $AEM01C = config("dictionaries.AEM01C");
        if (empty($data["LYFS"])) {
            return true;
        }
        return false;
    } */


    /**
     * 病理诊断编码只能以M开头
     * @param $data
     * @return array
     */
    public function rule86($data)
    {
        $basis = [];
        if (empty($data['JBMM'])) {
            return false;
        }

        $zyzd = $data['JBMM'];

        if ($zyzd) {

            $res = false;
            //如果zyzd以M开头
            if (stripos($zyzd, 'M') === 0) {
                $res = false;
            } else {
                $res = true;
            }
            if ($res) {
                return true;
            }
        }

        return false;
    }

    /**
     * 出院诊断编码（12岁及以下儿童出院诊断不应编为C50-C63(乳房、女性及男性生殖器恶性肿瘤)）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    /* public function rule87($ZYH, $data)
    {
        $NL = !empty($data["NL"]) ? $data["NL"] : 0;
        if ($NL > 12) {
            return false;
        }

        $JBDM_ARR = [];
        if (!empty($data["JBDM"])) {
            $JBDM_ARR[] = $data["JBDM"];
        }
        for ($i = 1; $i <= 40; $i++) {
            // 诊断编码
            if (!empty($data["JBDM" . $i])) {
                $JBDM_ARR[] = $data["JBDM" . $i];
            }
        }

        $arr = [
            "C50",
            "C51",
            "C52",
            "C53",
            "C54",
            "C55",
            "C56",
            "C57",
            "C58",
            "C59",
            "C60",
            "C61",
            "C62",
            "C63",
        ];
        $res = false;
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($JBDM_ARR as $JBDM) {
                if (stripos($JBDM, $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    } */

    /**
     * 出院诊断编码（12岁及以下儿童出院诊断不应编为O00-O99(妊娠、分娩和产褥期疾病)）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    /* public function rule88($ZYH, $data)
    {
        $NL = !empty($data["NL"]) ? $data["NL"] : 0;
        if ($NL > 12) {
            return false;
        }

        $JBDM_ARR = [];
        if (!empty($data["JBDM"])) {
            $JBDM_ARR[] = $data["JBDM"];
        }
        for ($i = 1; $i <= 40; $i++) {
            // 诊断编码
            if (!empty($data["JBDM" . $i])) {
                $JBDM_ARR[] = $data["JBDM" . $i];
            }
        }

        $arr = [
            "O00",
            "O01",
            "O02",
            "O03",
            "O04",
            "O05",
            "O06",
            "O07",
            "O08",
            "O09",
        ];
        for ($i = 10; $i <= 99; $i++) {
            $arr[] = "O" . $i;
        }
        $res = false;
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($JBDM_ARR as $JBDM) {
                if (stripos($JBDM, $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    } */

    /**
     * 出院诊断编码（12岁及以下儿童出院诊断不应编为"D24-D29"女性及男性生殖器官良性肿瘤）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    /* public function rule89($ZYH, $data)
    {
        $NL = !empty($data["NL"]) ? $data["NL"] : 0;
        if ($NL > 12) {
            return false;
        }

        $JBDM_ARR = [];
        if (!empty($data["JBDM"])) {
            $JBDM_ARR[] = $data["JBDM"];
        }
        for ($i = 1; $i <= 40; $i++) {
            // 诊断编码
            if (!empty($data["JBDM" . $i])) {
                $JBDM_ARR[] = $data["JBDM" . $i];
            }
        }

        $arr = ["D24", "D25", "D26", "D27", "D28", "D29"];
        $res = false;
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($JBDM_ARR as $JBDM) {
                if (stripos($JBDM, $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    } */

    /**
     * 出院诊断编码（12岁及以下儿童出院诊断不应编为"D24-D29"(乳房、女性及男性生殖器官良性肿瘤)）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    /* public function rule90($ZYH, $data)
    {
        //        $NL = !empty($data['NL']) ? $data['NL'] : 0;
        //        if ($NL > 12) {
        //            return false;
        //        }
        //
        //        $JBDM_ARR = [];
        //        if (!empty($data['JBDM'])) {
        //            $JBDM_ARR[] = $data['JBDM'];
        //        }
        //        for ($i=1;$i<=40;$i++) {
        //            // 诊断编码
        //            if (!empty($data['JBDM'.$i])) {
        //                $JBDM_ARR[] = $data['JBDM'.$i];
        //            }
        //        }
        //
        //        $arr = ['D24','D25','D26','D27','D28','D29'];
        //        $res = false;
        //        foreach ($arr as $value) {
        //            if ($res) {
        //                break;
        //            }
        //            foreach ($JBDM_ARR as $JBDM) {
        //                if (stripos($JBDM, $value) === 0) {
        //                    $res = true;
        //                    break;
        //                }
        //            }
        //        }
        //
        //        return $res;
        return false;
    } */

    /**
     * 新生儿出生体重(克)（新生儿出生体重(克)未填写）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    /* public function rule1342($ZYH, $data)
    {
        //        if (empty($data['XSECSTZ'])) {
        //            return true;
        //        }
        return false;
    } */

    /**
     * 出生地市
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1344($ZYH, $data)
    {
        if (
            mb_strlen(trim($data["CSD_SHI"])) < 2 ||
            is_numeric(trim($data["CSD_SHI"]))
        ) {
            return true;
        }
        return false;
    }

    /**
     * 出生地县
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1345($ZYH, $data)
    {
        if (
            mb_strlen(trim($data["CSD_XIAN"])) < 2 ||
            is_numeric(trim($data["CSD_XIAN"]))
        ) {
            return true;
        }
        return false;
    }

    /**
     * 籍贯市（籍贯市未填写）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1346($ZYH, $data)
    {
        if (
            mb_strlen(trim($data["GG_SHI"])) < 2 ||
            is_numeric(trim($data["GG_SHI"]))
        ) {
            return true;
        }
        return false;
    }

    /**
     * 户籍市（户籍市未填写）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1347($ZYH, $data)
    {
        if (
            mb_strlen(trim($data["HKDZ_SHI"])) < 2 ||
            is_numeric(trim($data["HKDZ_SHI"]))
        ) {
            return true;
        }
        return false;
    }

    /**
     * 户籍县（户籍县未填写）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1348($ZYH, $data)
    {
        if (
            mb_strlen(trim($data["HKDZ_XIAN"])) < 2 ||
            is_numeric(trim($data["HKDZ_XIAN"]))
        ) {
            return true;
        }
        return false;
    }

    /**
     * 户籍详细地址（户籍详细地址未填写）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1349($ZYH, $data)
    {
        if (
            mb_strlen(trim($data["HKDZ"])) < 2 ||
            is_numeric(trim($data["HKDZ"]))
        ) {
            return true;
        }
        return false;
    }

    /**
     * 现住址市
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1350($ZYH, $data)
    {
        if (
            mb_strlen(trim($data["XZZ_SHI"])) < 2 ||
            is_numeric(trim($data["XZZ_SHI"]))
        ) {
            return true;
        }
        return false;
    }

    /**
     * 现住址县
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1351($ZYH, $data)
    {
        if (
            mb_strlen(trim($data["XZZ_XIAN"])) < 2 ||
            is_numeric(trim($data["XZZ_XIAN"]))
        ) {
            return true;
        }
        return false;
    }

    /**
     * 现住址详细地址
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1352($ZYH, $data)
    {
        if (
            mb_strlen(trim($data["XZZ"])) < 2 ||
            is_numeric(trim($data["XZZ"]))
        ) {
            return true;
        }
        return false;
    }

    /**
     * 出院病房（出院病房 不能为空）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1359($ZYH, $data)
    {
        if (empty($data["CYBF"])) {
            return true;
        }
        return false;
    }

    /**
     * 新生儿入院体重（与年龄冲突）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    /* public function rule1360($ZYH, $data)
    {
        //        if (empty($data[''])) {
        //            return true;
        //        }
        return false;
    } */

    /**
     * 新生儿出生体重（与年龄冲突）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    /* public function rule1361($ZYH, $data)
    {
        //        if (empty($data[''])) {
        //            return true;
        //        }
        return false;
    } */

    /**
     * 手术及操作名称（收费项目和手术名称不匹配）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    /* public function rule1394($ZYH, $data)
    {
        //        if (empty($data[''])) {
        //            return true;
        //        }
        return false;
    } */

    /**
     * 是否有出院31日内再住院计划（病案首页是否有出院31天内再住院计划，如果选择无，目的不能为空格、-、文字。）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    /* public function rule1419($ZYH, $data)
    {
        if (strpos($data["SFZZYJH"], '无') !== false && mb_strlen(trim($data["MD"])) > 0) {
            return true;
        }
        return false;
    } */

    /**
     * 是否有出院31日内再住院计划（病案首页是否有出院31天内再住院计划，如果选择有，目的必填）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1420($ZYH, $data)
    {
        //        if ($data['SFZZYJH'] == 2 && ($data['MD']==='' || $data['MD']===null || $data['MD']=='无')) {
        if (!empty($data['SFZZYJH']) && ($data['SFZZYJH'] == 2 || $data['SFZZYJH'] == '有' || $data['SFZZYJH'] == '2') && empty($data['MD'])) {
            return true;
        }

        return false;
    }

    /**
     * 手术编码（病案首页有手术操作，手术级别、手术类型、术者必填【术者信息没有】）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    /* public function rule1421($ZYH, $data)
    {
        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            if (
                !empty($data["SSJCZBM" . $i]) &&
                !empty($data["SSJB1" . $i]) &&
                empty($data["SZ" . $i])
            ) {
                $res = true;
                break;
            }
        }

        return $res;
    } */

    /**
     * 麻醉方式（病案首页有麻醉方式，麻醉医师必填）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1422($ZYH, $data)
    {
        $res = false;
        $basis = [];
        for ($i = 1; $i <= 41; $i++) {
            if (
                !empty($data["MZFS" . $i]) && mb_strlen($data["MZFS" . $i]) > 2 && $data["MZFS" . $i] != '无麻醉' &&
                (mb_strlen($data["MZYS" . $i]) < 2 ||
                    $data["MZYS" . $i] == "-" ||
                    is_numeric($data["MZYS" . $i]))
            ) {
                $desc = '手术名称：【' . $data['SSJCZMC' . $i] . '】';
                $basis[] = ['desc' => $desc, 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
            }
        }

        return $basis;
    }

    /**
     * 麻醉医师（病案首页有麻醉医师，麻醉方式必填）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1423($ZYH, $data)
    {
        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            if (
                !empty($data["MZYS" . $i]) && mb_strlen($data["MZYS" . $i]) > 2 &&
                (empty($data["MZFS" . $i]) || $data["MZFS" . $i] == "-")
            ) {
                $desc = '手术名称：【' . $data['SSJCZMC' . $i] . '】';
                $basis[] = ['desc' => $desc, 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                $res = true;
                break;
            }
        }

        return $res;
    }

    /**
     * 主要诊断编码（主要诊断为T88.6-T88.7，药物过敏应为有，且应填写过敏药物）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1424($ZYH, $data)
    {
        if (empty($data["JBDM"])) {
            return false;
        }
        if (
            stripos($data["JBDM"], "T88.6") === 0 ||
            stripos($data["JBDM"], "T88.7") === 0
        ) {
            if ($data["YWGM"] != 2 || empty($data["GMYW"])) {
                return true;
            }
        }
        return false;
    }

    /**
     * 离院方式（病案首页离院方式为医嘱转院 和 医嘱转社区卫生服务机构/乡镇卫生院，拟接受医疗机构名称必填）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    //    public function rule1427($ZYH, $data)
    //    {
    //        if (!in_array($data['LYFS'], [2, 3])) {
    //            return false;
    //        }
    //        if ($data['LYFS'] == 2 && empty($data['YZZY_YLJG'])) {
    //            return true;
    //        }
    //        if ($data['LYFS'] == 3 && empty($data['WSY_YLJG'])) {
    //            return true;
    //        }
    //        return false;
    //    }

    /**
     * 病案质量（病案首页病案质量为乙或丙，提示是否应修改为甲）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1428($ZYH, $data)
    {
        if (in_array($data["BAZL"], [2, 3])) {
            return true;
        }
        return false;
    }

    /**
     * 年龄（病案首页年龄小于6岁，职业应选择其他）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1429($ZYH, $data)
    {
        if (
            ($data["NL"] == "" || $data["NL"] == null) &&
            ($data["BZYZSNL"] == "" || $data["BZYZSNL"] == null)
        ) {
            return false;
        }
        $nl = !empty($data["NL"]) ? $data["NL"] : 0;
        $zy = !empty($data["ZY"]) ? $data["ZY"] : "";
        if ($nl >= 6 || empty($zy)) {
            return false;
        }

        if (in_array($zy, ["其他", "其它", "学生", "90", '无业人员'])) {
            return false;
        }

        return true;
    }

    /**
     * 出院时间（病案首页质控日期应大于等于出院时间）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1430($ZYH, $data)
    {
        // 出院日期2025-05-29 14:36:49
        if (empty($data["ZKRQ"]) || empty($data["CYSJ"])) {
            return false;
        }
        $CYRQ = substr($data["CYSJ"], 0, 14);

        $CYRQ = str_replace("年", "-", $CYRQ);
        $CYRQ = str_replace("月", "-", $CYRQ);

        $CYRQ = date('Y-m-d', strtotime($CYRQ));

        // 质控日期
        $ZKRQ = str_replace("年", "-", $data["ZKRQ"]);
        $ZKRQ = str_replace("月", "-", $ZKRQ);
        $ZKRQ = str_replace("日", "", $ZKRQ);
        $ZKRQ = date('Y-m-d', strtotime($ZKRQ));


        if ($ZKRQ < $CYRQ) {
            return true;
        }
        return false;
    }

    /**
     * 证件号（身份号 男 最后二位奇数 女最后二位是 偶数）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1431($ZYH, $data)
    {
        if ($data["GJ"] != "中国") {
            return false;
        }
        //如果包含男就设置为1，包含女就设置为2
        if (strpos($data['XB'], '男') !== false) {
            $data['XB'] = 1;
        } elseif (strpos($data['XB'], '女') !== false) {
            $data['XB'] = 2;
        }
        if (empty($data["SFZH"]) || in_array($data["XB"], [0, 9])) {
            return false;
        }

        // 检查身份证号长度，至少需要2位才能获取倒数第二位
        $sfzh = trim($data["SFZH"]);
        if (mb_strlen($sfzh) < 2) {
            return false;
        }

        // $sex 1:男 2:女
        // 获取身份证号倒数第二位数字，用于判断性别
        $secondLastChar = substr($sfzh, -2, 1);
        // 确保是数字字符
        if (!is_numeric($secondLastChar)) {
            return false;
        }
        $sex = intval($secondLastChar) % 2 ? "1" : "2";
        if ($sex != $data["XB"]) {
            return true;
        }
        return false;
    }

    /**
     * 手术名称（收费中含"视网膜激光光凝术"手术名称无关键字"视网膜"）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1432($ZYH, $data)
    {
        if (empty($data["fee_detailed"])) {
            return false;
        }

        $FYMC_LIST = array_column($data["fee_detailed"], "FYMC");
        $FYMC_STR = implode(",", $FYMC_LIST);
        if (stripos($FYMC_STR, "视网膜激光光凝术") === false) {
            return false;
        }

        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            // 手术名称
            if (!empty($data["SSJCZMC" . $i])) {
                if (stripos($data["SSJCZMC" . $i], "视网膜") !== false) {
                    $res = true;
                    break;
                }
            }
        }

        if ($res) {
            return false;
        }

        return true;
    }

    /**
     * 手术名称（收费明细 含【纤维支气管镜检查】 手术名称不含【纤维支气管镜检查】）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1433($ZYH, $data)
    {
        if (empty($data["fee_detailed"])) {
            return false;
        }

        $FYMC_LIST = array_column($data["fee_detailed"], "FYMC");
        $FYMC_STR = implode(",", $FYMC_LIST);
        if (stripos($FYMC_STR, "纤维支气管镜检查") === false) {
            return false;
        }

        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            // 手术名称
            if (!empty($data["SSJCZMC" . $i])) {
                if (
                    //纤维支气管镜检查,经人工造口的支气管镜检查,闭合性[内镜的]支气管活组织检查,支气管镜下支气管活检,支气管镜下诊断性支气管肺泡灌洗[BAL],超声内镜下支气管穿刺活组织检查术,纤维支气管镜检查伴肺泡灌洗术,气管镜刷检术,支气管灌洗,支气管刷检术
                    stripos($data["SSJCZMC" . $i], "纤维支气管镜检查") !== false || stripos($data["SSJCZMC" . $i], "经人工造口的支气管镜检查") !== false || stripos($data["SSJCZMC" . $i], "闭合性[内镜的]支气管活组织检查") !== false || stripos($data["SSJCZMC" . $i], "支气管镜下支气管活检") !== false || stripos($data["SSJCZMC" . $i], "支气管镜下诊断性支气管肺泡灌洗[BAL]") !== false || stripos($data["SSJCZMC" . $i], "超声内镜下支气管穿刺活组织检查术") !== false || stripos($data["SSJCZMC" . $i], "纤维支气管镜检查伴肺泡灌洗术") !== false || stripos($data["SSJCZMC" . $i], "气管镜刷检术") !== false || stripos($data["SSJCZMC" . $i], "支气管灌洗") !== false || stripos($data["SSJCZMC" . $i], "支气管刷检术") !== false || stripos($data["SSJCZMC" . $i], "电子支气管镜检查") !== false
                ) {
                    $res = true;
                    break;
                }
            }
        }

        if ($res) {
            return false;
        }

        return true;
    }

    /**
     * 手术名称（收费明细 含【宫颈扩张术】手术名称不含【子宫颈扩张引产】）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1434($ZYH, $data)
    {
        if (empty($data["fee_detailed"])) {
            return false;
        }

        $FYMC_LIST = array_column($data["fee_detailed"], "FYMC");
        $FYMC_STR = implode(",", $FYMC_LIST);
        if (stripos($FYMC_STR, "宫颈扩张术") === false) {
            return false;
        }

        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            // 手术名称
            if (!empty($data["SSJCZMC" . $i])) {
                if (
                    stripos($data["SSJCZMC" . $i], "子宫颈扩张引产") !== false
                ) {
                    $res = true;
                    break;
                }
            }
        }

        if ($res) {
            return false;
        }

        return true;
    }

    /**
     * 手术名称（收费明细 含【急性缺血性脑卒中静脉溶栓治疗】 手术名称不含【脑动脉血栓溶解剂灌注】）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1435($ZYH, $data)
    {
        if (empty($data["fee_detailed"])) {
            return false;
        }

        $FYMC_LIST = array_column($data["fee_detailed"], "FYMC");
        $FYMC_STR = implode(",", $FYMC_LIST);
        if (stripos($FYMC_STR, "急性缺血性脑卒中静脉溶栓治疗") === false) {
            return false;
        }

        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            // 手术名称
            if (!empty($data["SSJCZMC" . $i])) {
                if (
                    stripos($data["SSJCZMC" . $i], "脑动脉血栓溶解剂灌注") !==
                    false
                ) {
                    $res = true;
                    break;
                }
            }
        }

        if ($res) {
            return false;
        }

        return true;
    }

    /**
     * 手术名称（收费明细 含【人工破膜术】手术名称不含【人工破膜引产】）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1436($ZYH, $data)
    {
        if (empty($data["fee_detailed"])) {
            return false;
        }

        $FYMC_LIST = array_column($data["fee_detailed"], "FYMC");
        $FYMC_STR = implode(",", $FYMC_LIST);
        if (stripos($FYMC_STR, "人工破膜术") === false) {
            return false;
        }

        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            // 手术名称
            if (!empty($data["SSJCZMC" . $i])) {
                if (stripos($data["SSJCZMC" . $i], "人工破膜") !== false) {
                    $res = true;
                    break;
                }
            }
        }

        if ($res) {
            return false;
        }

        return true;
    }

    /**
     * 手术名称（收费明细 含【手取胎盘术】 手术名称不含【手取胎盘】）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1437($ZYH, $data)
    {
        if (empty($data["fee_detailed"])) {
            return false;
        }

        $FYMC_LIST = array_column($data["fee_detailed"], "FYMC");
        $FYMC_STR = implode(",", $FYMC_LIST);
        if (stripos($FYMC_STR, "手取胎盘术") === false) {
            return false;
        }

        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            // 手术名称
            if (!empty($data["SSJCZMC" . $i])) {
                if (stripos($data["SSJCZMC" . $i], "手取胎盘") !== false) {
                    $res = true;
                    break;
                }
            }
        }

        if ($res) {
            return false;
        }

        return true;
    }

    /**
     * 手术名称（收费明细 含【无创呼吸机辅助呼吸，手术名称不含【无创呼吸机辅助通气】）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1438($ZYH, $data)
    {
        if (empty($data["fee_detailed"])) {
            return false;
        }

        $FYMC_LIST = array_column($data["fee_detailed"], "FYMC");
        $FYMC_STR = implode(",", $FYMC_LIST);
        if (stripos($FYMC_STR, "无创呼吸机辅助呼吸") === false) {
            return false;
        }

        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            // 手术名称
            if (!empty($data["SSJCZMC" . $i])) {
                if (
                    stripos($data["SSJCZMC" . $i], "无创呼吸机辅助通气") !==
                    false
                ) {
                    $res = true;
                    break;
                }
            }
        }

        if ($res) {
            return false;
        }

        return true;
    }

    /**
     * 手术名称（收费明细 含【呼吸机辅助呼吸】 且 收费数量≥96，手术名称不含【呼吸机治疗[大于等于96小时]】）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1439($ZYH, $data)
    {
        if (empty($data["fee_detailed"])) {
            return false;
        }

        $res = false;
        $FYSL = 0;
        foreach ($data["fee_detailed"] as $value) {
            if (stripos($value["FYMC"], "呼吸机辅助呼吸") !== false) {
                $FYSL += intval($value["FYSL"]);
            }
        }

        if ($FYSL > 0 && $FYSL >= 96) {
            $res = true;
        }

        if (!$res) {
            return false;
        }

        $res1 = false;
        for ($i = 1; $i <= 41; $i++) {
            // 手术名称
            //如果SSJCZMC拼上i后不存在，则跳过
            if (!isset($data["SSJCZMC" . $i])) {
                continue;
            }
            if (!empty($data["SSJCZMC" . $i])) {
                if (
                    stripos(
                        $data["SSJCZMC" . $i],
                        "呼吸机治疗[大于等于96小时]"
                    ) !== false
                ) {
                    $res1 = true;
                    break;
                }
            }
        }

        if ($res1) {
            return false;
        }

        return true;
    }

    /**
     * 手术名称（收费明细 含【呼吸机辅助呼吸】 且 收费数量＜96，手术名称不含【呼吸机治疗[小于96小时]】）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1440($ZYH, $data)
    {

        $rule = RuleWordMap::query()->where("id", '=', 133)->get()->toArray();
        if ($rule) {
            $zyhList = explode(',', $rule[0]['keyword']);
            if (in_array($ZYH, $zyhList)) {
                return false;
            }
        }

        if (empty($data["fee_detailed"])) {
            return false;
        }

        $res = false;
        $FYSL = 0;
        foreach ($data["fee_detailed"] as $value) {
            if (stripos($value["FYMC"], "呼吸机辅助呼吸") !== false) {
                $FYSL += $value["FYSL"];
            }
        }

        if ($FYSL > 0 && $FYSL < 96) {
            $res = true;
        }

        if (!$res) {
            return false;
        }

        $res1 = false;
        for ($i = 1; $i <= 41; $i++) {
            // 手术名称
            if (!empty($data["SSJCZMC" . $i])) {
                if (
                    stripos($data["SSJCZMC" . $i], "呼吸机治疗[小于96小时]") !==
                    false
                ) {
                    $res1 = true;
                    break;
                }
            }
        }

        if ($res1) {
            return false;
        }

        return true;
    }

    /**
     * 有创呼吸机使用时间（收费明细 含【呼吸机辅助呼吸】，有创呼吸机使用时间【不能为0，不能为空】）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1441($ZYH, $data)
    {
        if (empty($data["fee_detailed"])) {
            return false;
        }

        $res = false;
        foreach ($data["fee_detailed"] as $value) {
            if (stripos($value["FYMC"], "呼吸机辅助呼吸") !== false) {
                $res = true;
                break;
            }
        }
        if (!$res) {
            return false;
        }

        $ycfxj = !empty($data["YCFXJ"]) ? $data["YCFXJ"] : $data["AEL01"];
        if (empty($ycfxj)) {
            $ycfxj = 0;
        }
        if (!empty($data["AEL01_T"])) {
            $ycfxj = $ycfxj + ($data["AEL01_T"] * 24);
        }

        if (is_numeric($ycfxj) && $ycfxj > 0) {
            return false;
        }
        return true;
    }

    /**
     * 手术名称（收费明细 含【体外人工膜肺(ECMO)】,手术名称不含【体外膜氧合[ECMO]】）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1442($ZYH, $data)
    {
        if (empty($data["fee_detailed"])) {
            return false;
        }

        $FYMC_LIST = array_column($data["fee_detailed"], "FYMC");
        $FYMC_STR = implode(",", $FYMC_LIST);
        if (stripos($FYMC_STR, "体外人工膜肺(ECMO)") === false) {
            return false;
        }

        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            // 手术名称
            if (!empty($data["SSJCZMC" . $i])) {
                if (
                    stripos($data["SSJCZMC" . $i], "体外膜氧合[ECMO]") !== false
                ) {
                    $res = true;
                    break;
                }
            }
        }

        if ($res) {
            return false;
        }

        return true;
    }

    /**
     * 出生日期（身份证与出生日期要对应）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1451($ZYH, $data)
    {
        if ($data["GJ"] != "中国") {
            return false;
        }

        if (empty($data["SFZH"]) || empty($data["CSRQ"]) || $data["SFZH"] == "-") {
            return false;
        }

        if (!empty($data["SFZH"]) && mb_substr(trim($data["SFZH"]), 0, 1) === "A") {
            return false;
        }

        if (
            false !== stripos($data["XM"], "无名氏") ||
            false !== stripos($data["CYBF"], "儿科") ||
            false !== stripos($data["CYBF"], "新生儿科")
        ) {
            return false;
        }

        // 身份号上的出生日期
        $SFZH_CSRQ = date("Y-m-d", strtotime(mb_substr($data["SFZH"], 6, 8)));
        // 出生日期
        $CSRQ = str_replace("年", "-", $data["CSRQ"]);
        $CSRQ = str_replace("月", "-", $CSRQ);
        $CSRQ = str_replace("日", "", $CSRQ);
        $CSRQ = date("Y-m-d", strtotime($CSRQ));
        if ($SFZH_CSRQ != $CSRQ) {
            return true;
        }
        return false;
    }

    /**
     * 住院天数（护理天数之和等于住院天数）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1452($ZYH, $data)
    {
        // 住院天数
        $SJZYTS = !empty($data["SJZYTS"]) ? $data["SJZYTS"] : 0;

        // 特技护理、一级护理、二级护理、三级护理 之和

        $TJHLTS = 0;
        if (!empty($data["TJHLTS"])) {
            $t_arr = explode(" ", $data["TJHLTS"]);
            if (count($t_arr) > 1) {
                $t1 = explode("/", $t_arr[1]);
                if ($t1[0] < $t1[1]) {
                    $TJHLTS = $t_arr[0] + 1;
                } else {
                    $TJHLTS = $t_arr[0] + 2;
                }
            } else {
                if (false !== stripos($data["TJHLTS"], "/")) {
                    $t1 = explode("/", $data["TJHLTS"]);
                    if ($t1[0] < $t1[1]) {
                        $TJHLTS = 1;
                    } else {
                        $TJHLTS = 2;
                    }
                } else {
                    $TJHLTS = $data["TJHLTS"];
                }
            }
        }

        $YJHLTS = 0;
        if (!empty($data["YJHLTS"])) {
            $y_arr = explode(" ", $data["YJHLTS"]);
            if (count($y_arr) > 1) {
                $t2 = explode("/", $y_arr[1]);
                if ($t2[0] < $t2[1]) {
                    $YJHLTS = $y_arr[0] + 1;
                } else {
                    $YJHLTS = $y_arr[0] + 2;
                }
            } else {
                if (false !== stripos($data["YJHLTS"], "/")) {
                    $t1 = explode("/", $data["YJHLTS"]);
                    if ($t1[0] < $t1[1]) {
                        $YJHLTS = 1;
                    } else {
                        $YJHLTS = 2;
                    }
                } else {
                    $YJHLTS = $data["YJHLTS"];
                }
            }
        }

        $EJHLTS = 0;
        if (!empty($data["EJHLTS"])) {
            $e_arr = explode(" ", $data["EJHLTS"]);
            if (count($e_arr) > 1) {
                $t3 = explode("/", $e_arr[1]);
                if ($t3[0] < $t3[1]) {
                    $EJHLTS = $e_arr[0] + 1;
                } else {
                    $EJHLTS = $e_arr[0] + 2;
                }
            } else {
                if (false !== stripos($data["EJHLTS"], "/")) {
                    $t1 = explode("/", $data["EJHLTS"]);
                    if ($t1[0] < $t1[1]) {
                        $EJHLTS = 1;
                    } else {
                        $EJHLTS = 2;
                    }
                } else {
                    $EJHLTS = $data["EJHLTS"];
                }
            }
        }

        $SJHLTS = 0;
        if (!empty($data["SJHLTS"])) {
            $s_arr = explode(" ", $data["SJHLTS"]);
            if (count($s_arr) > 1) {
                $t4 = explode("/", $s_arr[1]);
                if ($t4[0] < $t4[1]) {
                    $SJHLTS = $s_arr[0] + 1;
                } else {
                    $SJHLTS = $s_arr[0] + 2;
                }
            } else {
                if (false !== stripos($data["SJHLTS"], "/")) {
                    $t1 = explode("/", $data["SJHLTS"]);
                    if ($t1[0] < $t1[1]) {
                        $SJHLTS = 1;
                    } else {
                        $SJHLTS = 2;
                    }
                } else {
                    $SJHLTS = $data["SJHLTS"];
                }
            }
        }

        //$TJHLTS = !empty($data['TJHLTS']) ? $data['TJHLTS'] : 0;
        //$YJHLTS = !empty($data['YJHLTS']) ? $data['YJHLTS'] : 0;
        //$EJHLTS = !empty($data['EJHLTS']) ? $data['EJHLTS'] : 0;
        //$SJHLTS = !empty($data['SJHLTS']) ? $data['SJHLTS'] : 0;
        $HLTS_SUM =
            intval($TJHLTS) +
            intval($YJHLTS) +
            intval($EJHLTS) +
            intval($SJHLTS);

        if (
            $data["CYBF"] == "脊柱外科" ||
            $data["CYBF"] == "脊柱二科" ||
            $data["CYBF"] == "重症医学科" ||
            $data["CYBF"] == "肿瘤中心乳腺妇瘤病区"
        ) {
            if ($SJZYTS == $HLTS_SUM || $SJZYTS == $HLTS_SUM - 1) {
                return false;
            } else {
                return true;
            }
        } else {
            if ($SJZYTS != $HLTS_SUM) {
                return true;
            }
        }

        return false;
    }

    /**
     * 入院情况（入院病情有诊断时误填或漏填）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1453($ZYH, $data)
    {
        $RYBQ_ARR = [1, 2, 3, 4];

        // 主要诊断
        if (!empty($data["ZYZD"]) && !in_array($data["RYBQ"], $RYBQ_ARR)) {
            return true;
        }

        // 其它诊断
        $res = false;
        for ($i = 1; $i <= 40; $i++) {
            if (
                !empty($data["QTZD" . $i]) &&
                !in_array($data["RYBQ" . $i], $RYBQ_ARR)
            ) {
                $res = true;
                break;
            }
        }
        return $res;
    }

    /**
     * 手术编码有【96.7101】，有创呼吸机使用时间应【小于96小时】
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1454($ZYH, $data)
    {
        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            if (
                !empty($data["SSJCZBM" . $i]) &&
                $data["SSJCZBM" . $i] == "96.7101"
            ) {
                $res = true;
                break;
            }
        }
        if (!$res) {
            return $res;
        }

        $YCFXJ = !empty($data["YCFXJ"]) ? $data["YCFXJ"] : $data["AEL01"];
        if (empty($YCFXJ)) {
            $YCFXJ = 0;
        }
        $AEL01_T = !empty($data["AEL01_T"]) ? $data["AEL01_T"] : 0;
        if ($AEL01_T > 0) {
            $YCFXJ = $YCFXJ + ($AEL01_T * 24);
        }
        if ($YCFXJ >= 96) {
            return true;
        }
        return false;
    }

    /**
     * 手术编码有【96.7201】，有创呼吸机使用时间应【大于等于96小时】
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1455($ZYH, $data)
    {
        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            if (
                !empty($data["SSJCZBM" . $i]) &&
                $data["SSJCZBM" . $i] == "96.7201"
            ) {
                $res = true;
                break;
            }
        }
        if (!$res) {
            return $res;
        }

        $YCFXJ = !empty($data["YCFXJ"]) ? $data["YCFXJ"] : $data["AEL01"];
        $AEL01_T = !empty($data["AEL01_T"]) ? $data["AEL01_T"] : 0;
        if (empty($YCFXJ)) {
            $YCFXJ = 0;
        }
        if ($AEL01_T > 0) {
            $YCFXJ = $YCFXJ + ($AEL01_T * 24);
        }
        if ($YCFXJ < 96) {
            return true;
        }
        return false;
    }

    /**
     * 切口愈合等级（一级切口，愈合类别不能为丙级）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1456($ZYH, $data)
    {
        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            if (
                !empty($data["SSJCZBM" . $i]) &&
                $data["QKDJ" . $i] == "Ⅰ" &&
                $data["YHDJ" . $i] == "丙"
            ) {
                $res = true;
                break;
            }
        }
        return $res;
    }

    /**
     * 主要诊断编码（诊断编码出现S00-S09，颅内损伤昏迷时间6个空必填数字）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1457($ZYH, $data)
    {
        $res = false;
        if (empty($data["JBDM"])) {
            return $res;
        }

        $arr = [
            "S00",
            "S01",
            "S02",
            "S03",
            "S04",
            "S05",
            "S06",
            "S07",
            "S08",
            "S09",
        ];
        foreach ($arr as $value) {
            if (stripos($data["JBDM"], $value) !== false) {
                $res = true;
            }
        }
        if (!$res) {
            return false;
        }

        if (empty($data["RYQ_T"]) || empty($data["RYQ_XS"]) || empty($data["RYQ_F"]) || empty($data["RYH_T"]) || empty($data["RYH_XS"]) || empty($data["RYH_F"])) {
            return true;
        }

        if (
            !is_numeric($data["RYQ_T"]) &&
            !is_numeric($data["RYQ_XS"]) &&
            !is_numeric($data["RYQ_F"]) &&
            !is_numeric($data["RYH_T"]) &&
            !is_numeric($data["RYH_XS"]) &&
            !is_numeric($data["RYH_F"])
        ) {
            return true;
        }
        return false;
    }

    /**
     * 主要诊断编码（无效的主要诊断编码）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1458($ZYH, $data)
    {
        $res = false;
        if (empty($data["JBDM"])) {
            return $res;
        }

        $str =
            "T92.600x002:创伤性手指缺如|T90.501:陈旧性颅脑损伤|T75:陈旧性跟骨骨折|R65|B95:病原体的附加编码|B96:病原体的附加编码|B97:病原体的附加编码|T30:烧伤部位面积未特指|T31:烧伤部位面积未特指|T32:腐蚀上的面积编码|Z33:单纯妊娠状态|Z37:分娩结局 活产儿分娩地点|Z38:分娩结局 活产儿分娩地点|Z53:由于XX原因 治疗未实施|Z80:家族史|Z81:家族史|Z82:家族史|Z83:家族史|Z84:家族史|Z85:恶行肿瘤个人史|Z86:其他疾病个人史|Z87:其他疾病个人史|Z88:药物 生物制剂过敏史|Z89:肢体 器官后天缺失|Z90:肢体 器官后天缺失|Z91:危险因素个人史|Z92:医疗个人史|Z93:单纯人工造口状态|Z94:组织和器官移植状态|Z95:具有心脏 血管的植入物和移植物|Z96:具有其它功能性植入物和装置|Z97:具有其它功能性植入物和装置|Z98:其它单纯的手术后状态|Z99:依赖于可启动装置和机器|U80:耐药菌感染|U81:耐药菌感染|U82:耐药菌感染|U83:耐药菌感染|U84:耐药菌感染|U85:耐药菌感染|U86";
        $expRule = explode("|", $str);
        foreach ($expRule as $val) {
            $zdbm = explode(":", $val);
            if (stripos($data["JBDM"], $zdbm[0]) === 0) {
                $res = $zdbm[0];
                if (!empty($zdbm[1])) {
                    $res .= "（" . $zdbm[1] . "）";
                } else {
                    $res .= " ";
                }
                $res .= "无效的诊断编码";
                break;
            }
        }

        return $res;
    }

    /**
     * 完成情况 todo 没有完成情况字段
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1461($ZYH, $data)
    {
        //return false;
        Log::info('SSLCLJ: ' . $data["SSLCLJ"].' WCQK: ' . $data["WCQK"]);
        if (($data["SSLCLJ"] == '1' || $data["SSLCLJ"] == 1 || $data["SSLCLJ"] == '是') && empty($data["WCQK"])) {
            return true;
        }
        return false;
    }

    /**
     * 变异情况 todo 没有完变异情况字段
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1462($ZYH, $data)
    {
        //return false;
        Log::info('SSLCLJ: ' . $data["SSLCLJ"].' BYQK: ' . $data["BYQK"]);
        if (($data["SSLCLJ"] == '1' || $data["SSLCLJ"] == 1 || $data["SSLCLJ"] == '是') && empty($data["BYQK"])) {
            return true;
        }
        return false;
    }

    /**
     * 术者
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1463($ZYH, $data)
    {
        $res = false;
        $basis = [];
        for ($i = 1; $i <= 41; $i++) {
            if (!empty($data["SSJCZMC" . $i])) {
                if (
                    mb_strlen($data["SZ" . $i]) < 2 ||
                    $data["SZ" . $i] == "--" ||
                    is_numeric($data["SZ" . $i])
                ) {
                    $basis[] = ['desc' => '手术操作名称：【' . $data["SSJCZMC" . $i] . '】', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                    //break;
                }
            }
        }
        return $basis;
    }

    /**
     * 入院病情不能全部是【4-无】
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1464($ZYH, $data)
    {
        $count1 = 0;
        $count2 = 0;
        $qtzd = 0;
        $zyzd = 0;

        if (!empty($data["ZYZD"])) {
            $count1++;
            $zyzd++;
            if ($data["RYBQ"] == 4) {
                $count2++;
            }
        } else {
            return false;
        }

        for ($i = 1; $i <= 40; $i++) {
            if (!empty($data["QTZD" . $i])) {
                $count1++;
                $qtzd++;

                if ($data["RYBQ" . $i] == 4) {
                    $count2++;
                }
            }
        }

        if ($count1 == 1) {
            return false;
        }

        if ($count1 == $count2) {
            return true;
        }
        return false;
    }

    /**
     * 证件类别
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1465($ZYH, $data)
    {
        //        if (empty($data['ZJLB'])) {
        //            return true;
        //        }
        return false;
    }

    /**
     * 诊断名称、编码不能重复
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1367($ZYH, $data)
    {
        $zdmcArr = [];
        $zdbmArr = [];

        if (!empty($data["ZYZD"])) {
            $zdmcArr[] = $data["ZYZD"];
        }
        if (!empty($data["JBDM"])) {
            $zdbmArr[] = $data["JBDM"];
        }

        $res = false;
        for ($i = 1; $i <= 40; $i++) {
            // 诊断名称
            if (!empty($data["QTZD" . $i])) {
                if (!in_array($data["QTZD" . $i], $zdmcArr)) {
                    $zdmcArr[] = $data["QTZD" . $i];
                } else {
                    $res = true;
                    break;
                }
            }

            // 诊断编码
            if (!empty($data["JBDM" . $i])) {
                if (!in_array($data["JBDM" . $i], $zdbmArr)) {
                    $zdbmArr[] = $data["JBDM" . $i];
                } else {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * 手术名称、编码不能重复
     * @param $ZYH
     * @param $data
     * @return bool
     */
    /* public function rule1466($ZYH, $data)
    {
        $ssmcArr = [];
        $ssbmArr = [];

        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            // 手术名称
            if (!empty($data["SSJCZMC" . $i])) {
                if (!in_array($data["SSJCZMC" . $i], $ssmcArr)) {
                    $ssmcArr[] = $data["SSJCZMC" . $i];
                } else {
                    $res = true;
                    break;
                }
            }

            // 手术编码
            if (!empty($data["SSJCZBM" . $i])) {
                if (!in_array($data["SSJCZBM" . $i], $ssbmArr)) {
                    $ssbmArr[] = $data["SSJCZBM" . $i];
                } else {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    } */

    /**
     * 出院时间不能早于入院时间
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1512($ZYH, $data)
    {
        // 入院时间
        $AAB01 = $data['AAB01'] ?? '';


        // 出院时间
        $AAC01 = $data['AAC01'] ?? '';

        if (empty($AAB01) || empty($AAC01)) {
            return false;
        }


        if ($AAB01 > $AAC01) {
            return true;
        }

        return false;
    }

    /**
     * 手术时间不能早于入院时间且不能晚于出院时间
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1513($ZYH, $data)
    {

        $AAB01 = !empty($data["AAB01"])
            ? date("Y-m-d", strtotime($data["AAB01"]))
            : "";
        $AAC01 = !empty($data["AAC01"])
            ? date("Y-m-d", strtotime($data["AAC01"]))
            : "";

        $res = false;

        if (empty($AAB01) || empty($AAC01)) {
            return false;
        }
        $rulemap9033 = RuleWordMap::query()->where('id', '=', 9033)->value('keyword');
        if (!empty($rulemap9033)) {
            if (strpos($rulemap9033, ',') !== false) {
                $rulemap9033 = explode(',', $rulemap9033);
            } else {
                $rulemap9033 = [$rulemap9033];
            }
            $ryjl = EMR_BL_BL01::query()->where('JZHM', '=', (string)$ZYH)->where('BLLB', '=', 292)->get()->toArray();
            if (!empty($ryjl)) {
                $isbh = false;
                $blbh = $ryjl[0]['BLBH'];
                $hjnr = EMR_BL_BLXG::query()->where('BLBH', '=', $blbh)->value('HJNR');
                if (!empty($hjnr)) {
                    foreach ($rulemap9033 as $item) {
                        if (strpos($hjnr, $item) !== false) {
                            $isbh = true;
                            break;
                        }
                    }
                }
                if ($isbh) {
                    return false;
                }
            }
        }


        for ($i = 1; $i <= 41; $i++) {
            if (
                !empty($data["SSJCZBM" . $i]) ||
                !empty($data["SSJCZMC" . $i])
            ) {
                $ssrq = $data["SSJCZRQ" . $i] ?? "";
                if (empty($ssrq)) {
                    continue;
                }
                $ssrq = date('Y-m-d', strtotime($ssrq));
                if ($AAB01 > $ssrq || $AAC01 < $ssrq) {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * 出院科室：产科，诊断编码：Z37，新生儿出生体重必填
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1477($ZYH, $data)
    {
        if (stripos($data["CYBF"], "新生儿科") !== false) {
            $XSECSTZ = preg_match("/^[1-9][0-9]{2,3}$/", $data["XSECSTZ"]);

            if (!$XSECSTZ || substr($data["XSECSTZ"], -2, 1) == 0) {
                return true;
            }
        }

        if ($data["CYBF"] == "产科") {
            $FYMC_LIST = array_column($data["fee_detailed"], "FYMC");
            $FYMC_STR = implode(",", $FYMC_LIST);
            if (stripos($FYMC_STR, "死胎接生") !== false) {
                return false;
            }

            if (stripos($FYMC_STR, "接生") !== false) {
                //$XSERYTZ = preg_match("/^[1-9][0-9]{2,3}$/", $data["XSECSTZ"]);
                $XSERYTZ = '';
                if (isset($data["XSECSTZ"])) {
                    preg_match('/\d+/', $data["XSECSTZ"], $matches);
                    if (!empty($matches)) {
                        $XSERYTZ = $matches[0];
                    }
                }

                if (empty($XSERYTZ) || $XSERYTZ < 100 || $XSERYTZ > 9999) { //这里为什么倒数第二位不能是0
                    return true;
                }
            }
        }

        //        $ICD10_ID1 = '';
        //        if (stripos($data['JBDM'], 'Z37') === 0) {
        //            $ICD10_ID1 = $data['JBDM'];
        //        }
        //
        //        if (empty($ICD10_ID1)) {
        //            for ($i = 1; $i <= 40; $i++) {
        //                // 诊断编码
        //                if (!empty($data['JBDM' . $i])) {
        //                    if (stripos($data['JBDM' . $i], 'Z37') === 0) {
        //                        $ICD10_ID1 = $data['JBDM' . $i];
        //                        break;
        //                    }
        //                }
        //            }
        //        }
        //
        //        if (!empty($ICD10_ID1) && empty($data['XSECSTZ'])) {
        //            return true;
        //        }
        return false;
    }

    /**
     * 首页收费【麻醉费】，要填写麻醉医师和麻醉方式
     * @param $data
     * @return bool
     */
    public function rule1478($ZYH, $data)
    {
        if (empty($data["MAF"]) || $data["MAF"] <= 0) {
            return false;
        }

        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            if (!empty($data["MZYS" . $i])) {
                $res = true;
                break;
            }
        }

        if ($res) {
            return false;
        }

        return true;
    }

    /**
     * 离院方式
     * @param $data
     * @return false|string
     */
    public function rule1515($ZYH, $data)
    {
        if (empty($data["yz"])) {
            return false;
        }
        //医嘱名称里面包含死亡两个字，并且离院方式<>5
        $arr = array_column($data["yz"], "YZMC");
        $yzmcStr = implode(",", $arr);
        if (strpos($yzmcStr, "死亡") !== false && strpos($data["LYFS"], '死亡') !== false) {
            return true;
        }

        // $arr = array_unique(array_column($data['yz'], 'YDYZLB'));
        // if (in_array(305, $arr) && $data['data']['AEM01C'] != 5) {
        //     return true;
        // }

        return false;
    }

    /**
     * 费用或者医嘱单有"阿替普酶"或者"急性缺血性脑卒中静脉溶栓治疗"，病案首页的手术操作要有"脑动脉血栓溶解剂灌注"
     * @param $data
     * @return bool
     */
    public function rule1516($ZYH, $data)
    {
        $res = false;
        $basis = [];
        $name1 = "阿替普酶";
        $name2 = "急性缺血性脑卒中静脉溶栓治疗";
        // 费用
        if (!$res && !empty($data["fee_detailed"])) {
            $fymcList = array_column($data["fee_detailed"], "FYMC");
            $fymcStr = implode(",", $fymcList);
            if (
                stripos($fymcStr, $name1) !== false ||
                stripos($fymcStr, $name2) !== false
            ) {
                $res = true;
            }
        }

        if (!$res && !empty($data["yz"])) {
            $yzmcList = array_column($data["yz"], "YZMC");
            $yzmcStr = implode(",", $yzmcList);
            if (
                stripos($yzmcStr, $name1) !== false ||
                stripos($yzmcStr, $name2) !== false
            ) {
                $res = true;
            }
        }

        // 医嘱和费用都不存在，不质控
        if (!$res) {
            return $basis;
        }

        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            $ssbm = $data["SSJCZBM" . $i];
            $ssbmstr = substr($ssbm, 0, 5);
            if ($ssbmstr == '99.10') {
                $res = true;
                break;
            }
        }

        if ($res) {
            return $basis;
        }

        $basis[] = ['desc' => '费用有【阿替普酶】或【急性缺血性脑卒中静脉溶栓治疗】', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];

        return $basis;
    }

    /**
     * 收费【阿替普酶】手术要有【99.1005 脑动脉血栓溶解剂灌注】
     * @param $data
     * @return array
     */
    public function rule1505($ZYH, $data)
    {
        $basis = [];
        if (empty($data["fee_detailed"])) {
            return $basis;
        }

        $res = false;
        // 费用
        if (!$res && !empty($data["fee_detailed"])) {
            $fymcList = array_column($data["fee_detailed"], "FYMC");
            $fymcStr = implode(",", $fymcList);
            if (
                stripos($fymcStr, "阿替普酶") !== false
            ) {
                $res = true;
            }
        }
        if (!$res) {
            return $basis;
        }

        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            $ssbm = $data["SSJCZBM" . $i];
            $ssbmstr = substr($ssbm, 0, 5);
            if ($ssbmstr == '99.10') {
                $res = true;
                break;
            }
        }

        if ($res) {
            return $basis;
        }

        $basis[] = ['desc' => '费用有【阿替普酶】', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];

        return $basis;
    }

    //新增sh

    /**
     * 住院天数（护理天数之和等于住院天数）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1519($ZYH, $data)
    {
        // 住院天数
        $SJZYTS = !empty($data["SJZYTS"]) ? $data["SJZYTS"] : 0;

        $CYSJ = substr(
            str_replace("月", "-", str_replace("年", "-", $data["CYSJ"])),
            0,
            10
        );
        $RYSJ = substr(
            str_replace("月", "-", str_replace("年", "-", $data["RYSJ"])),
            0,
            10
        );

        $diff = strtotime($CYSJ) - strtotime($RYSJ);
        if ($diff) {
            $d = abs(round($diff / 86400));
        } else {
            $d = 1;
        }

        if ($SJZYTS != $d) {
            return true;
        }
        return false;
    }

    /**
     * 诊断名称 含【盆腔积液】，患者性别不为男性
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1520($ZYH, $data)
    {
        // 主要诊断
        //如果包含男就设置为1，包含女就设置为2
        if (strpos($data['XB'], '男') !== false) {
            $data['XB'] = 1;
        } elseif (strpos($data['XB'], '女') !== false) {
            $data['XB'] = 2;
        }
        if (
            !empty($data["ZYZD"]) &&
            false !== strpos($data["ZYZD"], "盆腔积液") &&
            $data["XB"] == 1
        ) {
            return true;
        }

        // 其它诊断
        $res = false;
        for ($i = 1; $i <= 40; $i++) {
            if (
                isset($data["QTZD" . $i]) &&
                !empty($data["QTZD" . $i]) &&
                false !== strpos($data["QTZD" . $i], "盆腔积液") &&
                $data["XB"] == 1
            ) {
                $res = true;
                break;
            }
        }
        return $res;
    }

    /**
     * 付费方式不能为空
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1521($ZYH, $data)
    {
        $res = false;

        if (empty($data["YLFKFS"])) {
            return true;
        }

        return $res;
    }

    public function rule1459($ZYH, $data)
    {
        if (
            strpos($data["LYFS"], '转院') !== false &&
            (empty($data["YZZY_YLJG"]) ||
                $data["YZZY_YLJG"] == "-" ||
                $data["YZZY_YLJG"] == "无" ||
                $data["YZZY_YLJG"] == "没有")
        ) {
            return true;
        }

        return false;
    }

    public function rule1517($ZYH, $data)
    {
        if (
            strpos($data["LYFS"], '转社区') !== false ||
            strpos($data["LYFS"], '转乡镇') !== false &&
            (empty($data["WSY_YLJG"]) ||
                $data["WSY_YLJG"] == "-" ||
                $data["WSY_YLJG"] == "无" ||
                $data["WSY_YLJG"] == "没有")
        ) {
            return true;
        }

        return false;
    }

    //质控医师不能为空
    public function rule1522($ZYH, $data)
    {
        if (empty($data["ZKYS"]) && empty($data["ZKYS_BH"])) {
            return true;
        }

        return false;
    }

    //质控护士 不能为空
    public function rule1523($ZYH, $data)
    {
        if (empty($data["ZKHS"]) && empty($data["ZKHS_BH"])) {
            return true;
        }

        return false;
    }

    //质控医师  只能填医师姓名
    // public function rule1524($ZYH, $data)
    // {
    //     if (empty($data["ZKYS"])) {
    //         return true;
    //     }

    //     $info = (new HomeSzService())->GY_YGDM($data["ZKYS"]);
    //     if (empty($info)) {
    //         return true;
    //     } else {
    //         if (!empty($info[0]["YGJB"])) {
    //             if ($info[0]["YGXM"] == "王东") {
    //                 return false;
    //             }

    //             if (!in_array($info[0]["YGJB"], [1, 2, 3, 6, 7, 21])) {
    //                 return true;
    //             }
    //         }
    //     }

    //     return false;
    // }

    //质控护士  只能填护士姓名
    public function rule1525($ZYH, $data)
    {
        if (empty($data["ZKHS"])) {
            return true;
        }
        // GY_YGDM在代码中没有，会报错，暂时注释掉
        // $info = (new HomeSzService())->GY_YGDM($data["ZKHS"]);
        // if (empty($info)) {
        //     return true;
        // } else {
            if (!empty($info[0]["YGJB"])) {
                if ($info[0]["YGXM"] == "王东") {
                    return false;
                }

                if (!in_array($info[0]["YGJB"], [4, 5, 8, 9, 10, 20])) {
                    return true;
                }
            }
        // }

        return false;
    }

    /**
     * 新增：新生儿入院体重(克)填写  ，天龄（不足1周岁）应≤28天
     * @param $ZYH
     * @param $data
     * @return bool
     */
    /* public function rule1526($ZYH, $data)
    {
        if (
            intval($data["XSERYTZ"]) > 0 &&
            (empty($data["BZYZSNL"]) || $data["BZYZSNL"] > 28)
        ) {
            return true;
        }

        return false;
    } */

    /**
     * 新增：【手术治疗费】有 ，手术操作栏不能为空
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1527($ZYH, $data)
    {
        $basis = [];

        if (!empty($data['D20X01']) && $data['D20X01'] > 0) {
            if (empty($data['SSJCZMC1'])) {
                $basis[] = ['desc' => '有【手术费】 ，手术操作栏不能为空', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
            } else {
                $isss = false;
                for ($i = 1; $i <= 41; $i++) {
                    if (!empty($data['SSJCZMC' . $i]) && $data['SSJCZMC' . $i] != '-') {
                        $isss = true;
                        break;
                    }
                }
                if (!$isss) {
                    $basis[] = ['desc' => '有【手术费】 ，手术操作栏不能为空', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                }
            }
        }

        return $basis;
    }

    /**
     * 新增：有【血费】，血型不能填"5.不详"或"6.未查"
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1528($ZYH, $data)
    {
        if (!empty($data["XF"]) && (strpos($data["XX"], '不详') !== false || strpos($data["XX"], '未查') !== false)) {
            return true;
        }

        return false;
    }

    /**
     * 新增：收费名称有【经肠镜特殊治疗】，手术操作名称未填
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1529($ZYH, $data)
    {
        if (empty($data["fee_detailed"])) {
            return false;
        }

        //退费
        $refundArr = [];
        foreach ($data["fee_detailed"] as $feeItem) {
            //zfje是否存在
            if (!isset($feeItem["ZFJE"])) {
                continue;
            }
            if (!isset($refundArr[$feeItem["FYMC"]])) {
                $refundArr[$feeItem["FYMC"]] = $feeItem["ZFJE"];
            } else {
                $refundArr[$feeItem["FYMC"]] += $feeItem["ZFJE"];
            }
        }

        foreach ($data["fee_detailed"] as $feeKey => $feeItem2) {
            if (
                isset($refundArr[$feeItem2["FYMC"]]) &&
                $refundArr[$feeItem2["FYMC"]] == 0
            ) {
                unset($data["fee_detailed"][$feeKey]);
            }
        }

        $FYMC_LIST = array_column($data["fee_detailed"], "FYMC");
        $FYMC_STR = implode(",", $FYMC_LIST);
        if (stripos($FYMC_STR, "经肠镜特殊治疗") === false) {
            return false;
        }

        $mc = '内镜下十二指肠病损切除术或破坏术
                内镜下十二指肠病损切除术
                内镜下十二指肠病损氩离子凝固治疗术
                内镜下十二指肠病损光动力治疗（PDT)
                内镜下十二指肠黏膜下剥离术(ESD)
                内镜下十二指肠黏膜切除术(EMR)
                内镜下十二指肠病损射频消融术
                内镜下经黏膜下隧道十二指肠病损切除术(STER)
                内镜下小肠黏膜切除术(EMR)
                内镜下小肠黏膜下剥离术(ESD)
                内镜下经黏膜下隧道小肠病损切除术(STER)
                内镜下小肠病损切除术
                内镜下空肠病损氩气刀治疗术（APC)
                内镜下回肠病损氩气刀治疗术（APC)
                内镜下大肠息肉切除术
                内镜下结肠息肉消融术
                内镜下乙状结肠息肉切除术
                内镜下大肠其他病损或组织破坏术
                内镜下结肠黏膜下剥离术(ESD)
                内镜下结肠黏膜切除术(EMR)
                内镜下经黏膜下隧道结肠病损切除术(STER)
                内镜下结肠病损氩气刀治疗术（APC)
                内镜下乙状结肠病损切除术
                内镜下结肠病损切除术
                内镜下盲肠病损切除术
                内镜下直肠病损氩离子凝固术
                内镜下直肠病损切除术
                内镜下直肠黏膜下剥离术(ESD)
                内镜下直肠黏膜切除术(EMR)
                内镜下直肠病损光动力治疗术（PDT)
                直肠[内镜的]息肉切除术
                内镜下直肠息肉氩离子凝固术（APC)
                纤维结肠镜下结肠息肉切除术
                结肠镜下结肠病损电凝术
                直肠-乙状结肠镜下直肠病损电切术
                直肠-乙状结肠镜下直肠息肉切除术
                内镜下内痔套扎治疗
                内痔硬化剂注射治疗';

        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            // 手术名称
            if (!empty($data["SSJCZMC" . $i])) {
                if (stripos($mc, $data["SSJCZMC" . $i]) !== false) {
                    $res = true;
                    break;
                }
            }
        }

        if ($res) {
            return false;
        }

        return true;
    }

    /**
     * 新增：有【手术操作名称】，收费名称中无【经肠镜特殊治疗】，请核实。
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1530($ZYH, $data)
    {
        $mc = '内镜下十二指肠病损切除术或破坏术
                内镜下十二指肠病损切除术
                内镜下十二指肠病损氩离子凝固治疗术
                内镜下十二指肠病损光动力治疗（PDT)
                内镜下十二指肠黏膜下剥离术(ESD)
                内镜下十二指肠黏膜切除术(EMR)
                内镜下十二指肠病损射频消融术
                内镜下经黏膜下隧道十二指肠病损切除术(STER)
                内镜下小肠黏膜切除术(EMR)
                内镜下小肠黏膜下剥离术(ESD)
                内镜下经黏膜下隧道小肠病损切除术(STER)
                内镜下小肠病损切除术
                内镜下空肠病损氩气刀治疗术（APC)
                内镜下回肠病损氩气刀治疗术（APC)
                内镜下大肠息肉切除术
                内镜下结肠息肉消融术
                内镜下乙状结肠息肉切除术
                内镜下大肠其他病损或组织破坏术
                内镜下结肠黏膜下剥离术(ESD)
                内镜下结肠黏膜切除术(EMR)
                内镜下经黏膜下隧道结肠病损切除术(STER)
                内镜下结肠病损氩气刀治疗术（APC)
                内镜下乙状结肠病损切除术
                内镜下结肠病损切除术
                内镜下盲肠病损切除术
                内镜下直肠病损氩离子凝固术
                内镜下直肠病损切除术
                内镜下直肠黏膜下剥离术(ESD)
                内镜下直肠黏膜切除术(EMR)
                内镜下直肠病损光动力治疗术（PDT)
                直肠[内镜的]息肉切除术
                内镜下直肠息肉氩离子凝固术（APC)
                纤维结肠镜下结肠息肉切除术
                结肠镜下结肠病损电凝术
                直肠-乙状结肠镜下直肠病损电切术
                直肠-乙状结肠镜下直肠息肉切除术';

        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            // 手术名称
            if (!empty($data["SSJCZMC" . $i])) {
                if (stripos($mc, $data["SSJCZMC" . $i]) !== false) {
                    $res = true;
                    break;
                }
            }
        }

        if ($res) {
            if (empty($data["fee_detailed"])) {
                return true;
            }

            $FYMC_LIST = array_column($data["fee_detailed"], "FYMC");
            $FYMC_STR = implode(",", $FYMC_LIST);
            if (stripos($FYMC_STR, "经肠镜特殊治疗") === false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 主要诊断        编码：”V01-Y98 ”不能作为主要诊断 
     */
    public function rule2001($data)
    {
        $ICD10_ID1 = '';
        if (empty($data["JBDM"])) {
            return false;
        }


        $ICD10_ID1 = $data["JBDM"];

        if (empty($ICD10_ID1)) {
            return false;
        }
        $res = $this->isProhibitedDiagnosisCode($ICD10_ID1);
        if (empty($res)) {
            return false;
        } else {

            $desc = $res . '不能作为主要诊断';

            return $desc;
        }

        return false;
    }

    /** 
     * 检查诊断代码是否属于禁止范围 
     * 
     * @param string $code 诊断代码 
     * @return string|bool 如果属于禁止范围返回具体的编码（如'V01'），否则返回false 
     */
    private function isProhibitedDiagnosisCode($code)
    {
        // 只取前三位字符进行检查 
        $checkCode = substr($code, 0, 3);

        // 提取字母和数字部分 
        if (preg_match('/^([A-Z])(\d+)/i', $checkCode, $matches)) {
            $letter = strtoupper($matches[1]);
            $number = (int)$matches[2];

            // 检查是否在禁止范围内 
            if (($letter === 'V' && $number >= 1 && $number <= 99) ||
                ($letter === 'W' && $number >= 1 && $number <= 99) ||
                ($letter === 'Y' && $number >= 1 && $number <= 98)
            ) {
                return $checkCode;
            }
        }

        return '';
    }

    /**
     * 有手术日期手术名称不能为空
     * @param $data
     * @return bool
     */
    public function rule2002($data)
    {
        $basis = [];
        for ($i = 1; $i <= 41; $i++) {
            if (isset($data["SSJCZRQ" . $i]) && !empty($data["SSJCZRQ" . $i])) {
                if (empty($data["SSJCZMC" . $i])) {
                    $desc = '手术日期【' . $data["SSJCZRQ" . $i] . '】对应的手术名称未填写';
                    $basis[] = ['desc' => $desc, 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                }
            }
        }
        return $basis;
    }

    /**
     * 切口类型为0时，愈合等级不能为空
     * @param $data
     * @return bool
     */
    public function rule2003($data)
    {
        $basis = [];

        for ($i = 1; $i <= 41; $i++) {
            if (isset($data["QKDJ" . $i]) && ($data["QKDJ" . $i] == '0' || empty($data["QKDJ" . $i]))) {
                if (!empty($data["YHDJ" . $i])) {
                    $desc = $data['SSJCZMC' . $i] . '切口类型为0时，愈合等级应为空';
                    $basis[] = ['desc' => $desc, 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                }
            }
        }
        return $basis;
    }

    /**
     * 麻醉开始时间（有麻醉开始时间MZKSSJ，麻醉方式MZFS不能为空）
     * @param $ZYH
     * @param $data
     * @return array|bool
     */
    public function rule2004($ZYH, $data)
    {
        $res = false;
        $basis = [];

        for ($i = 1; $i <= 41; $i++) {
            if (
                !empty($data["MZKSSJ" . $i]) && $data["MZKSSJ" . $i] != "-" &&
                (empty($data["MZFS" . $i]) || $data["MZFS" . $i] == "-")
            ) {
                $desc = '手术名称：【' . ($data['SSJCZMC' . $i] ?? '') . '】，有麻醉开始时间，麻醉方式不能为空';
                $basis[] = ['desc' => $desc, 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                $res = true;
            }
        }

        return $res ? $basis : $res;
    }

    /**
     * 麻醉分级（有麻醉分级MZFJ，麻醉方式MZFS不能为空）
     * @param $ZYH
     * @param $data
     * @return array|bool
     */
    public function rule2005($ZYH, $data)
    {
        $res = false;
        $basis = [];

        for ($i = 1; $i <= 41; $i++) {
            if (
                !empty($data["MZFJ" . $i]) && $data["MZFJ" . $i] != "-" &&
                (empty($data["MZFS" . $i]) || $data["MZFS" . $i] == "-")
            ) {
                $desc = '手术名称：【' . ($data['SSJCZMC' . $i] ?? '') . '】，有麻醉分级，麻醉方式不能为空';
                $basis[] = ['desc' => $desc, 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                $res = true;
            }
        }

        return $res ? $basis : $res;
    }

    /**
     * 手术开始时间（有手术开始时间SSKSSJ，手术名称SSJCZMC不能为空）
     * @param $ZYH
     * @param $data
     * @return array|bool
     */
    public function rule2006($ZYH, $data)
    {
        $res = false;
        $basis = [];

        for ($i = 1; $i <= 41; $i++) {
            if (
                !empty($data["SSKSSJ" . $i]) && $data["SSKSSJ" . $i] != "-" &&
                (empty($data["SSJCZMC" . $i]) || $data["SSJCZMC" . $i] == "-")
            ) {
                $desc = '有手术开始时间【' . $data["SSKSSJ" . $i] . '】，手术名称不能为空';
                $basis[] = ['desc' => $desc, 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                $res = true;
            }
        }

        return $res ? $basis : $res;
    }

    /**
     * 有手术名称（有手术名称，手术类型SSLX不能为空或者-，但是0是正确的）
     * @param $ZYH
     * @param $data
     * @return array|bool
     */
    public function rule2007($ZYH, $data)
    {
        $res = false;
        $basis = [];
        for ($i = 1; $i <= 41; $i++) {
            if (
                !empty($data["SSJCZMC" . $i]) && $data["SSJCZMC" . $i] != "-" &&
                ((empty($data["SSLX" . $i]) && $data["SSLX" . $i] != 0) || $data["SSLX" . $i] == "-")
            ) {
                $desc = '有手术名称【' . $data["SSJCZMC" . $i] . '】，手术类型不能为空';
                $basis[] = ['desc' => $desc, 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                $res = true;
            }
        }
        return $res ? $basis : $res;
    }

    /**
     * 麻醉方式（首页麻醉费用不为空，至少有一条麻醉方式不为空）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule2008($ZYH, $data)
    {
        if (empty($data["D20X01"])) {
            return false;
        }

        $res = false;
        for ($i = 1; $i <= 41; $i++) {
            // 麻醉方式
            if (!empty($data["MZFS" . $i])) {
                $res = true;
                break;
            }
        }

        if (!$res) {
            return true;
        }

        return false;
    }


    /**
     * 主诊断不可入组
     * 主诊断编码不能包含DRG2.0-ZD表中的zdbm字段
     */
    public function rule2009($ZYH, $data)
    {
        $basis = [];
        if (empty($data['JBDM'])) {
            return $basis;
        }

        $res = DRG2::query()->where('zdbm', $data['JBDM'])->first();
        if ($res) {
            $basis[] = ['desc' => '主诊断编码【' . $res->zdbm . '】，主诊断名称【' . $res->zdmc . '】不能作为主要诊断', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 离院方式为死亡的，所有出院情况需要填死亡
     */

    public function rule2010($ZYH, $data)
    {
        $basis = [];
        //AEM01C = 5 或者=死亡
        if ($data['AEM01C'] != 5 && $data['AEM01C'] != '死亡') {
            return $basis;
        } else {
            if (!empty($data['CYQK']) && $data['CYQK'] != '死亡') {
                $desc = '离院方式为死亡的，所有出院情况需要填死亡';
                $basis[] = ['desc' => $desc, 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
            }
        }

        return $basis;
    }


    /**
     * 所有诊断编码如果包含‘*’必须包含‘+’
     */
    public function rule2011($ZYH, $data)
    {
        $basis = [];
        if (empty($data['JBDM'])) {
            return $basis;
        }
        // 诊断编码
        if (!empty($data["JBDM"])) {
            if (strpos($data["JBDM"], '*') !== false && strpos($data["JBDM"], '+') === false) {
                $basis[] = ['desc' => '诊断编码【' . $data["JBDM"] . '】【*】不能单独使用', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
            }
        }
        for ($i = 1; $i <= 40; $i++) {
            if (!empty($data["JBDM" . $i])) {
                if (strpos($data["JBDM" . $i], '*') !== false && strpos($data["JBDM" . $i], '+') === false) {
                    $basis[] = ['desc' => '诊断编码【' . $data["JBDM" . $i] . '】【*】不能单独使用', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                }
            }
        }
        return $basis;
    }


    /**
     * 年龄小于15周岁患者，诊断不能下【支气管炎J40.x00】，应为【急性支气管炎J20.900】
     */
    public function rule2012($ZYH, $data)
    {
        $basis = [];
        $age = $data['NL'] ?? 0;
        if ($age >= 15) {
            return $basis;
        }
        if (empty($data['JBDM'])) {
            return $basis;
        }



        $zdbm = $data['JBDM'];
        //取前7位
        $zdbmstr = substr($zdbm, 0, 7);
        if ($zdbmstr == 'J40.x00') {
            $basis[] = ['desc' => '年龄小于15周岁患者，诊断不能下【支气管炎' . $zdbm . '】，应为【急性支气管炎J20.900】', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
        }

        for ($i = 1; $i <= 40; $i++) {
            if (!empty($data['JBDM' . $i])) {
                $zdbm = $data['JBDM' . $i];
                $zdbmstr = substr($zdbm, 0, 7);
                if ($zdbmstr == 'J40.x00') {
                    $basis[] = ['desc' => '年龄小于15周岁患者，诊断不能下【支气管炎' . $zdbm . '】，应为【急性支气管炎J20.900】', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                }
            }
        }
        return $basis;
    }

    /**
     * T93不能作为主诊断
     */
    public function rule2013($ZYH, $data)
    {
        $basis = [];
        if (empty($data['JBDM'])) {
            return $basis;
        }
        $zdbm = $data['JBDM'];
        $zdbmstr = substr($zdbm, 0, 3);
        if ($zdbmstr == 'T93') {
            $basis[] = ['desc' => '诊断编码【' . $zdbm . '】不能作为主诊断', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }


    /**
     * 手术名称包含血管支架植入，需另编码0.40-0.43，00.44，00.45-00.48
     */
    public function rule2014($ZYH, $data)
    {
        $basis = [];
        if (empty($data['SSJCZMC1'])) {
            return $basis;
        }
        $isxgzj = false;
        for ($i = 1; $i <= 41; $i++) {
            if (strpos($data['SSJCZMC' . $i], '血管支架植入') !== false) {
                $isxgzj = true;
                break;
            }
        }
        if (!$isxgzj) {
            return $basis;
        }
        $is4043 = false;
        $is404548 = false;
        for ($i = 1; $i <= 41; $i++) {

            $ssbm = $data['SSJCZBM' . $i];

            $ssbmstr = substr($ssbm, 0, 4);
            if ($ssbmstr == '0.40' || $ssbmstr == '0.41' || $ssbmstr == '0.42' || $ssbmstr == '0.43') {
                $is4043 = true;
            }
            $ssbmstr = substr($ssbm, 0, 5);

            if ($ssbmstr == '00.45' || $ssbmstr == '00.46' || $ssbmstr == '00.47' || $ssbmstr == '00.48') {
                $is404548 = true;
            }
        }

        if (!$is4043 || !$is404548) {
            $basis[] = ['desc' => '手术名称包含血管支架植入，需另编码0.40-0.43，00.44，00.45-00.48', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }


    /**
     * 手术名称包含球囊扩张，需另编码0.40-0.43，00.44
     */
    public function rule2015($ZYH, $data)
    {
        $basis = [];
        if (empty($data['SSJCZMC1'])) {
            return $basis;
        }
        $isxgzj = false;
        for ($i = 1; $i <= 41; $i++) {
            if (strpos($data['SSJCZMC' . $i], '球囊扩张') !== false) {
                $isxgzj = true;
                break;
            }
        }
        if (!$isxgzj) {
            return $basis;
        }
        $is4043 = false;
        for ($i = 1; $i <= 41; $i++) {

            $ssbm = $data['SSJCZBM' . $i];

            $ssbmstr = substr($ssbm, 0, 4);
            if ($ssbmstr == '0.40' || $ssbmstr == '0.41' || $ssbmstr == '0.42' || $ssbmstr == '0.43') {
                $is4043 = true;
            }
        }

        if (!$is4043) {
            $basis[] = ['desc' => '手术名称包含球囊扩张，需另编码0.40-0.43，00.44', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 有手术名称（有手术名称，麻醉方式不能为空）
     * @param $ZYH
     * @param $data
     * @return array|bool
     */
    public function rule2016($ZYH, $data)
    {
        $res = false;
        $basis = [];
        for ($i = 1; $i <= 41; $i++) {
            if (
                !empty($data["SSJCZMC" . $i]) && $data["SSJCZMC" . $i] != "-" &&
                (empty($data["MZFS" . $i]) || $data["MZFS" . $i] == "-")
            ) {
                $desc = '有手术名称【' . $data["SSJCZMC" . $i] . '】，麻醉方式不能为空';
                $basis[] = ['desc' => $desc, 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                $res = true;
            }
        }
        return $res ? $basis : $res;
    }

    /**
     * 【入院后确诊日期】需在 入院时间 至 出院时间之间
     */
    public function rule2017($ZYH, $data)
    {
        $basis = [];
        if (empty($data['QZSJ'])) {
            return $basis;
        }
        if (empty($data['AAB01'])) {
            return $basis;
        }
        if (empty($data['AAC01'])) {
            return $basis;
        }
        //转换成y-m-d
        $QZSJ = date('Y-m-d', strtotime($data['QZSJ']));
        $AAB01 = date('Y-m-d', strtotime($data['AAB01']));
        $AAC01 = date('Y-m-d', strtotime($data['AAC01']));
        if ($QZSJ < $AAB01 || $QZSJ > $AAC01) {
            $basis[] = ['desc' => '入院后确诊日期【' . $QZSJ . '】需在 入院时间【' . $AAB01 . '】至 出院时间【' . $AAC01 . '】之间', 'location' => ['user' => ['QZSJ', 'AAB01', 'AAC01']]];
        }
        return $basis;
    }

    /**
     * ICD-O-3必须为空
     */
    public function rule2018($ZYH, $data)
    {
        $basis = [];
        if (!empty($data['ICD-O-3'])) {
            $basis[] = ['desc' => 'ICD-O-3必须为空', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 入院记录既往史中记载过敏史的，首页【药物过敏】应勾选“是”并如实填写
     */
    public function rule2019($ZYH, $data)
    {
        $basis = [];
        $ryjl = Bllb292::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($ryjl)) {
            return $basis;
        }
        $ryjlData = $ryjl[0];
        $jws = $ryjlData['JWS'] ?? '';
        if (empty($jws)) {
            return $basis;
        }

        $rulemap8098 = RuleWordMap::query()->where('id', 8098)->value('keyword') ?? '否认过敏史,否认食物过敏史,否认药物过敏史,否认其他过敏史';

        if (strpos($rulemap8098, ',') !== false) {
            $rulemap8098 = explode(',', $rulemap8098);
        } else {
            $rulemap8098 = [$rulemap8098];
        }

        //删除关键词
        foreach ($rulemap8098 as $v) {
            $jws = str_replace($v, '', $jws);
        }

        if (strpos($jws, '有过敏史') !== false && ($data['AEB02C'] != 2 && $data['AEB02C'] != '是' && $data['AEB02C'] != '2')) {
            $basis[] = ['desc' => '入院记录既往史中记载过敏史的，首页【药物过敏】应勾选“是”并如实填写', 'location' => ['user' => ['AEB02C']]];
        }
        return $basis;
    }

    /**
     * 过敏药物中不能包含"" ""  空格  *   -  #
     */
    public function rule2020($ZYH, $data)
    {
        $basis = [];
        if (!empty($data['AEB01'])) {
            $AEB01 = $data['AEB01'];
            if (strpos($AEB01, ' ') !== false || strpos($AEB01, '*') !== false || strpos($AEB01, '-') !== false || strpos($AEB01, '#') !== false || strpos($AEB01, '\"') !== false || strpos($AEB01, '"') !== false) {
                $basis[] = ['desc' => '过敏药物中不能包含"" ""  空格  *   -  #', 'location' => ['user' => ['AEB01']]];
            }
        }
        return $basis;
    }

    /**
     * 凡有诊断的病历，出院情况（治愈/好转/未愈/死亡）必须填写
     */
    public function rule1541($ZYH, $data)
    {
        $basis = [];

        if (!empty($data['JBDM']) && empty($data['CYQK'])) {
            $basis[] = ['desc' => '诊断【' . $data['ZYZD'] . '】，出院情况不能为空，请核实。', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
        }

        for ($i = 1; $i <= 40; $i++) {
            if (!empty($data['JBDM' . $i]) && empty($data['CYQK' . $i])) {
                $basis[] = ['desc' => '诊断【' . $data['QTZD' . $i] . '】，出院情况不能为空，请核实。', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
            }
        }  

        return $basis;
    }

    /**
     * 主要诊断不可治愈检查
     * 若主要诊断编码的前三位属于以下列表：C91–C95、C71、C78、C79、G20、E10–E14、K70–K77，
     * 则该疾病通常不可治愈，出院情况不得填写为"治愈"
     */
    public function rule1548($ZYH, $data)
    {
        $basis = [];
        if (empty($data['JBDM'])) {
            return $basis;
        }

        $substring = substr($data['JBDM'], 0, 3);
        $subArray = ['C91', 'C92', 'C93', 'C94', 'C95', 'K70', 'K71', 'K72', 'K73', 'K74', 'K75', 'K76', 'K77', 'C71', 'C78', 'C79', 'G20', 'E10', 'E11', 'E12', 'E13', 'E14'];

        foreach ($subArray as $sub) {
            if ($substring == $sub) {
                $cyqk = $data['CYQK'] ?? '';
                if (mb_strpos($cyqk, '治愈') !== false) {
                    $basis[] = ['desc' => '诊断编码' . $data['JBDM'] . '主要诊断不可治愈，请核实。', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                    break;
                }
            }
        }

        return $basis;
    }

    /**
     * 出院情况检查（rule1548之外的情况）
     * 若主要诊断编码的前三位不属于不可治愈列表（C91–C95、C71、C78、C79、G20、E10–E14、K70–K77），
     * 则出院情况通常应为"治愈"或"好转"
     */
    public function rule2021($ZYH, $data)
    {
        $basis = [];
        if (empty($data['JBDM'])) {
            return $basis;
        }

        // 不可治愈的疾病编码列表（前三位）
        $incurableCodes = ['C91', 'C92', 'C93', 'C94', 'C95', 'C71', 'C78', 'C79', 'G20', 'E10', 'E11', 'E12', 'E13', 'E14', 'K70', 'K71', 'K72', 'K73', 'K74', 'K75', 'K76', 'K77'];

        $substring = substr($data['JBDM'], 0, 3);

        // 判断是否在不可治愈列表中
        $isIncurable = false;
        foreach ($incurableCodes as $code) {
            if ($substring == $code) {
                $isIncurable = true;
                break;
            }
        }

        // 如果不在不可治愈列表中（rule1548之外的情况），且出院情况不包含"治愈"或"好转"
        if (!$isIncurable) {
            $cyqk = $data['CYQK'] ?? '';
            // 检查出院情况是否为空，或者不包含"治愈"和"好转"
            if (empty($cyqk) || (mb_strpos($cyqk, '治愈') === false && mb_strpos($cyqk, '好转') === false)) {
                $basis[] = ['desc' => '出院诊断【' . ($data['ZYZD'] ?? '') . '】，通常应为"1.治愈"或"2.好转"，请核实', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
            }
        }

        return $basis;
    }

    /**
     * 诊断名称填写错误
     * 查询所有诊断，查询ICD10模型 ZDBM=诊断编码获取ZDMC，如果和查到的不一致就质控
     */
    public function rule2022($ZYH, $data)
    {
        $basis = [];

        // 检查主要诊断
        if (!empty($data['JBDM'])) {
            $icd10 = ICD10::query()->where('ZDBM', $data['JBDM'])->first();
            if ($icd10 && !empty($icd10->ZDMC)) {
                $standardZdmc = trim($icd10->ZDMC);
                $actualZdmc = trim($data['ZYZD'] ?? '');

                // 如果诊断名称不一致，则质控
                if ($actualZdmc !== $standardZdmc) {
                    $basis[] = ['desc' => '诊断编码【' . $data['JBDM'] . '】对应的规范诊断名称应为【' . $standardZdmc . '】', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                }
            }
        }

        // 检查其他诊断（1-40）
        for ($i = 1; $i <= 40; $i++) {
            $zdbmField = 'JBDM' . $i;
            $zdmcField = 'QTZD' . $i;

            if (!empty($data[$zdbmField])) {
                $icd10 = ICD10::query()->where('ZDBM', $data[$zdbmField])->first();
                if ($icd10 && !empty($icd10->ZDMC)) {
                    $standardZdmc = trim($icd10->ZDMC);
                    $actualZdmc = trim($data[$zdmcField] ?? '');

                    // 如果诊断名称不一致，则质控
                    if ($actualZdmc !== $standardZdmc) {
                        $basis[] = ['desc' => '诊断编码【' . $data[$zdbmField] . '】对应的规范诊断名称应为【' . $standardZdmc . '】', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                    }
                }
            }
        }

        return $basis;
    }

    /**
     * 诊断编码填写错误
     * 查询所有诊断，查询ICD10模型 ZDMC=诊断名称获取ZDBM，如果和查到的不一致就质控
     */
    public function rule2023($ZYH, $data)
    {
        $basis = [];

        // 检查主要诊断
        $actualZdmc = trim($data['ZYZD'] ?? '');
        $actualZdbm = trim($data['JBDM'] ?? '');
        if (!empty($actualZdmc) && !empty($actualZdbm)) {
            $icd10 = ICD10::query()->where('ZDMC', $actualZdmc)->first();
            if ($icd10 && !empty($icd10->ZDBM)) {
                $standardZdbm = trim($icd10->ZDBM);
                if ($actualZdbm !== $standardZdbm) {
                    $basis[] = ['desc' => '诊断名称【' . $actualZdmc . '】对应的规范诊断编码应为【' . $standardZdbm . '】', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                }
            }
        }

        // 检查其他诊断（1-40）
        for ($i = 1; $i <= 40; $i++) {
            $zdbmField = 'JBDM' . $i;
            $zdmcField = 'QTZD' . $i;

            $actualZdmc = trim($data[$zdmcField] ?? '');
            $actualZdbm = trim($data[$zdbmField] ?? '');
            if (empty($actualZdmc) || empty($actualZdbm)) {
                continue;
            }

            $icd10 = ICD10::query()->where('ZDMC', $actualZdmc)->first();
            if ($icd10 && !empty($icd10->ZDBM)) {
                $standardZdbm = trim($icd10->ZDBM);
                if ($actualZdbm !== $standardZdbm) {
                    $basis[] = ['desc' => '诊断名称【' . $actualZdmc . '】对应的规范诊断编码应为【' . $standardZdbm . '】', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                }
            }
        }

        return $basis;
    }

    /**
     * 手术名称填写错误
     * 查询所有手术，查询ICD9模型 SSCZBM=手术编码获取SSCZMC，如果和查到的不一致就质控
     */
    public function rule2024($ZYH, $data)
    {
        $basis = [];

        for ($i = 1; $i <= 41; $i++) {
            $ssbmField = 'SSJCZBM' . $i;
            $ssmcField = 'SSJCZMC' . $i;

            $actualSsbm = trim($data[$ssbmField] ?? '');
            if (empty($actualSsbm)) {
                continue;
            }

            $icd9 = ICD9::query()->where('SSCZBM', $actualSsbm)->first();
            if ($icd9 && !empty($icd9->SSCZMC)) {
                $standardSsmc = trim($icd9->SSCZMC);
                $actualSsmc = trim($data[$ssmcField] ?? '');
                if ($actualSsmc !== $standardSsmc) {
                    $basis[] = ['desc' => '手术编码【' . $actualSsbm . '】对应的规范手术名称应为【' . $standardSsmc . '】', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                }
            }
        }

        return $basis;
    }

    /**
     * 手术编码填写错误
     * 查询所有手术，查询ICD9模型 SSCZMC=手术名称获取SSCZBM，如果和查到的不一致就质控
     */
    public function rule2025($ZYH, $data)
    {
        $basis = [];

        for ($i = 1; $i <= 41; $i++) {
            $ssbmField = 'SSJCZBM' . $i;
            $ssmcField = 'SSJCZMC' . $i;

            $actualSsmc = trim($data[$ssmcField] ?? '');
            $actualSsbm = trim($data[$ssbmField] ?? '');
            if (empty($actualSsmc) || empty($actualSsbm)) {
                continue;
            }

            $icd9 = ICD9::query()->where('SSCZMC', $actualSsmc)->first();
            if ($icd9 && !empty($icd9->SSCZBM)) {
                $standardSsbm = trim($icd9->SSCZBM);
                if ($actualSsbm !== $standardSsbm) {
                    $basis[] = ['desc' => '手术名称【' . $actualSsmc . '】对应的规范手术编码应为【' . $standardSsbm . '】', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                }
            }
        }

        return $basis;
    }

    /**
     * 手麻系统中有手术，首页手术名称未填
     */
    public function rule2028($ZYH, $data)
    {
        $basis = [];

        $ssapRecords = SM_SSAP::query()
            ->where('ZYH', $ZYH)
            ->whereNotNull('ICD9_SSCZMC')
            ->where('ICD9_SSCZMC', '<>', '')
            ->where('ICD9_SSCZMC', '<>', 'NULL')
            ->pluck('ICD9_SSCZMC')
            ->toArray();

        $ssapNames = [];
        foreach ($ssapRecords as $name) {
            $name = trim((string)$name);
            if ($name === '' || strtoupper($name) === 'NULL') {
                continue;
            }
            $ssapNames[] = $name;
        }
        $ssapNames = array_values(array_unique($ssapNames));

        if (empty($ssapNames)) {
            return $basis;
        }

        $homeOperationNames = PatientInfoOperationV2::query()
            ->where('ZYH', $ZYH)
            ->pluck('ICD9_NAME')
            ->toArray();

        $homeOperationNames = array_values(array_filter(array_map(function ($name) {
            $name = trim((string)$name);
            return ($name === '' || strtoupper($name) === 'NULL') ? null : $name;
        }, $homeOperationNames)));

        if (!empty($homeOperationNames)) {
            return $basis;
        }

        $basis[] = [
            'desc' => '手麻手术名称：【' . implode('】、【', $ssapNames) . '】',
            'location' => ['user' => [], 'zd' => [], 'ss' => []]
        ];

        return $basis;
    }

    /**
     * 有手术费，手术名称未填
     */
    public function rule2029($ZYH, $data)
    {
        $basis = [];

        $operationFees = PatientInfoCostV2::query()
            ->where('ZYH', $ZYH)
            ->pluck('D20X02')
            ->toArray();

        $validOperationFees = [];
        foreach ($operationFees as $fee) {
            if ((float)$fee > 0) {
                $validOperationFees[] = trim((string)$fee);
            }
        }
        $validOperationFees = array_values(array_unique($validOperationFees));

        if (empty($validOperationFees)) {
            return $basis;
        }

        $homeOperationNames = PatientInfoOperationV2::query()
            ->where('ZYH', $ZYH)
            ->pluck('ICD9_NAME')
            ->toArray();

        $homeOperationNames = array_values(array_filter(array_map(function ($name) {
            $name = trim((string)$name);
            return ($name === '' || strtoupper($name) === 'NULL') ? null : $name;
        }, $homeOperationNames)));

        if (!empty($homeOperationNames)) {
            return $basis;
        }

        $basis[] = [
            'desc' => '手术费【' . implode('】、【', $validOperationFees) . '】',
            'location' => ['user' => [], 'zd' => [], 'ss' => []]
        ];

        return $basis;
    }

    /**
     * 手术名称不为空，是否为日间手术未填
     */
    public function rule2030($ZYH, $data)
    {
        $basis = [];

        for ($i = 1; $i <= 41; $i++) {
            $operationName = trim((string)($data["SSJCZMC" . $i] ?? ''));
            if ($operationName === '' || $operationName === '-') {
                continue;
            }

            if (!$this->hasHomeFieldValue($data["SFFJHZRY" . $i] ?? null)) {
                $basis[] = [
                    'desc' => '手术名称【' . $operationName . '】，是否为日间手术未填写',
                    'location' => ['user' => [], 'zd' => [], 'ss' => []]
                ];
            }
        }

        return $basis;
    }

    /**
     * 手术名称不为空，是否为日间操作未填
     */
    public function rule2031($ZYH, $data)
    {
        $basis = [];

        for ($i = 1; $i <= 41; $i++) {
            $operationName = trim((string)($data["SSJCZMC" . $i] ?? ''));
            if ($operationName === '' || $operationName === '-') {
                continue;
            }

            if (!$this->hasHomeFieldValue($data["SFWRJBF" . $i] ?? null)) {
                $basis[] = [
                    'desc' => '手术名称【' . $operationName . '】，是否为日间操作未填写',
                    'location' => ['user' => [], 'zd' => [], 'ss' => []]
                ];
            }
        }

        return $basis;
    }

    /**
     * 手术名称不为空，是否非计划再手术未填
     */
    public function rule2032($ZYH, $data)
    {
        $basis = [];

        for ($i = 1; $i <= 41; $i++) {
            $operationName = trim((string)($data["SSJCZMC" . $i] ?? ''));
            if ($operationName === '' || $operationName === '-') {
                continue;
            }

            if (!$this->hasHomeFieldValue($data["SFFJHZSS" . $i] ?? null)) {
                $basis[] = [
                    'desc' => '手术名称【' . $operationName . '】，是否非计划再手术未填写',
                    'location' => ['user' => [], 'zd' => [], 'ss' => []]
                ];
            }
        }

        return $basis;
    }

    /**
     * 主要诊断C开头，TNM未填写
     */
    public function rule2033($ZYH, $data)
    {
        $basis = [];

        $diagnosisCode = strtoupper(trim((string)($data['JBDM'] ?? '')));
        $diagnosisName = $data['ZYZD'] ?? '';
        //查询rulewordmap id = 20078
        $ruleWordMap = RuleWordMap::query()->where('id', 20078)->first();
        //如果keyword存在，包含逗号就按逗号分割为数组
        $keyword = $ruleWordMap->keyword;
        if ($keyword) {
            if(strpos($keyword,',') !== false){
                $keywordArray = explode(',', $keyword);
                foreach ($keywordArray as $item) {
                    if (strpos($diagnosisName, $item) !== false) {
                        return $basis;
                    }
                }
            }else{
                if(strpos($diagnosisName, $keyword) !== false){
                    return $basis;
                }
            }
        }
        if ($diagnosisCode === '' || strpos($diagnosisCode, 'C') !== 0) {
            return $basis;
        }

        if (!$this->hasHomeFieldValue($data['TNM'] ?? null)) {
            $basis[] = [
                'desc' => '主要诊断编码【' . $diagnosisCode . '】为C开头，TNM未填写',
                'location' => ['user' => [], 'zd' => [], 'ss' => []]
            ];
        }

        return $basis;
    }

    /**
     * 病案首页脑梗死、后循环缺血不满足DRG审核要求
     */
    public function rule2034($ZYH, $data)
    {
        $basis = [];

        if (!$this->isFangchengAppName()) {
            return $basis;
        }

        $rawDiagnosisCode = trim((string)($data['JBDM'] ?? ''));
        $diagnosisCode = strtoupper($rawDiagnosisCode);
        if ($diagnosisCode === '' || strpos($diagnosisCode, 'I63') === false) {
            return $basis;
        }

        $orderNames = $this->getMedicalOrderNames($data['yz'] ?? []);
        $antiThromboticDrugs = [
            '阿司匹林肠溶片',
            '硫酸氢氯吡格雷片',
            '替罗非班注射液',
            '利伐沙班片',
            '华法林片',
        ];
        $lipidStabilizingDrugs = [
            '阿托伐他汀钙片',
            '瑞舒伐他汀钙片',
        ];

        $matchedAntiThromboticDrugs = $this->matchOrderDrugNames($orderNames, $antiThromboticDrugs);
        $matchedLipidStabilizingDrugs = $this->matchOrderDrugNames($orderNames, $lipidStabilizingDrugs);
        if (!empty($matchedAntiThromboticDrugs) && !empty($matchedLipidStabilizingDrugs)) {
            return $basis;
        }

        $matchedDrugNames = array_values(array_unique(array_merge($matchedAntiThromboticDrugs, $matchedLipidStabilizingDrugs)));
        if (empty($matchedDrugNames)) {
            $desc = '主诊【编码 ' . $rawDiagnosisCode . '】医嘱中无“抗血栓药物 + 降脂稳斑药物”';
        } else {
            $desc = '主诊【编码 ' . $rawDiagnosisCode . '】医嘱【' . implode('】、【', $matchedDrugNames) . '】';
        }

        $basis[] = [
            'desc' => $desc,
            'location' => ['user' => [], 'zd' => [], 'ss' => []]
        ];

        return $basis;
    }

    /**
     * 入院诊断编码不能选用损伤中毒外部原因编码
     *
     * @param mixed $ZYH
     * @param array $data
     * @return array
     */
    public function rule2035($ZYH, $data)
    {
        $basis = [];
        $originalAdmissionDiagnosisCode = trim((string)($data['AAB07C'] ?? ''));
        if ($originalAdmissionDiagnosisCode === '') {
            return $basis;
        }

        $admissionDiagnosisCode = strtoupper($originalAdmissionDiagnosisCode);
        $disallowedCodeKeywordText = (string)RuleWordMap::query()
            ->where('id', 50079)
            ->value('keyword');
        if (trim($disallowedCodeKeywordText) === '') {
            return $basis;
        }

        $disallowedCodeKeywords = array_values(array_unique(array_filter(array_map(
            static function ($disallowedCodeKeyword) {
                return strtoupper(trim((string)$disallowedCodeKeyword));
            },
            explode(',', $disallowedCodeKeywordText)
        ))));

        foreach ($disallowedCodeKeywords as $disallowedCodeKeyword) {
            $keywordLength = mb_strlen($disallowedCodeKeyword);
            $admissionDiagnosisCodePrefix = mb_substr($admissionDiagnosisCode, 0, $keywordLength);
            if ($admissionDiagnosisCodePrefix !== $disallowedCodeKeyword) {
                continue;
            }

            $basis[] = [
                'desc' => '入院诊断编码不能选用' . $originalAdmissionDiagnosisCode,
                'location' => ['user' => [], 'zd' => [], 'ss' => []]
            ];

            break;
        }

        return $basis;
    }

    private function isFangchengAppName()
    {
        $appName = env('app_name', env('APP_NAME'));

        return strtolower((string)$appName) === 'fangcheng';
    }

    private function getMedicalOrderNames($orders)
    {
        if (empty($orders) || !is_array($orders)) {
            return [];
        }

        $orderNames = [];
        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }

            $orderName = trim((string)($order['YZMC'] ?? ''));
            if ($orderName === '') {
                continue;
            }

            $orderNames[] = $orderName;
        }

        return $orderNames;
    }

    private function matchOrderDrugNames($orderNames, $drugNames)
    {
        $matchedDrugNames = [];
        foreach ($orderNames as $orderName) {
            foreach ($drugNames as $drugName) {
                if (strpos($orderName, $drugName) !== false) {
                    $matchedDrugNames[] = $drugName;
                }
            }
        }

        return array_values(array_unique($matchedDrugNames));
    }

    private function hasHomeFieldValue($value)
    {
        if ($value === 0 || $value === '0') {
            return true;
        }

        if ($value === null) {
            return false;
        }

        return trim((string)$value) !== '' && trim((string)$value) !== '-';
    }
}
