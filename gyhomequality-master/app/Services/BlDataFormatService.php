<?php

namespace App\Services;

use App\Model\Bllb1;
use App\Model\Bllb288;
use App\Model\Bllb292;
use App\Model\Bllb34Jjjctys;
use App\Model\Bllb34Sjgzs;
use App\Model\Bllb34Ybwffgzs;
use App\Model\Bllb34Zdcygzs;
use App\Model\Bllb294_295;
use App\Model\Bllb294_45;
use App\Model\Bllb303;
use App\Model\Bllb303_303;
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
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use App\Model\RuleConfig;
use App\Model\RuleWordMap;
use App\Model\SM_SSAP;
use App\Model\Staff;
use Illuminate\Database\QueryException;
use PhpParser\Node\Stmt\Continue_;
use Psr\Log\NullLogger;

class BlDataFormatService
{
    /**
     * 统一清理结构化病历里的各种空白字符，并标准化冒号，避免字段标题匹配失败。
     */
    private function normalizeExtractText($text = "")
    {
        if ($text === null) {
            return "";
        }

        $text = (string)$text;
        $text = preg_replace('/[\p{Z}\s\x{00A0}\x{1680}\x{180E}\x{2000}-\x{200D}\x{2028}\x{2029}\x{202F}\x{205F}\x{2060}\x{3000}\x{FEFF}]+/u', '', $text);

        return str_replace(['：', '﹕', '︓', '∶', '꞉'], ':', $text);
    }

    public function formatBlData($zyh = "", $mblb = 0, $blbh = "")
    {
        $blData = $this->getBlData($zyh, $mblb, $blbh);
        self::deleteBl01ByZYH($zyh);

        foreach ($blData as $item) {
            $mblb = $item['MBLB'];
            if ($mblb == 82) {
                // 首先尝试匹配"记录时间：{YYYY-MM-DD HH:MM}"格式
                preg_match("/术前小结及术前讨论结论记录\s*\{\s*(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\s*\}/", $item['HJNR'], $timeMatches);
                $timePrefix = !empty($timeMatches[1]) ? $timeMatches[1] : '';
                if (!empty($timePrefix)) {
                    EMR_BL_BL01::query()->where('BLBH', (string)$item['BLBH'])->update(['operation_time' => strtotime($timePrefix)]);
                }
            } else {
                $ruleWord = $this->formatBlDataItem($mblb);
                $this->insertData($ruleWord, $mblb, $item);
            }
        }
        return $blData;
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

       /*  $esIndex = ["bllb1_2023", "bllb292_2023", "bllb294_295_2023", "bllb294_45_2023", "bllb303_2023", "bllb303_303_2023"];
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
                try {
                    app('es')->deleteByQuery($params);
                } catch (\Exception $e) {
                    // 忽略异常，继续处理下一个索引
                    continue;
                }
            }
        } */
    }

    public function insertData($ruleWord, $mblb, $blData)
    {
        $hjnr = $blData['HJNR'];
        $data = [];
        $data['ZYH'] = $blData['JZHM'];
        $data['BLBH'] = $blData['BLBH']; //病历编号

        //表可能不存在需新增字段
        $data['BLMC'] = $blData['BLMC'] ?? ""; //病历名称
        $data['CJSJ'] = $blData['CJSJ'] ?? ""; //创建时间:系统时间
        $data['WCSJ'] = $blData['WCSJ'] ?? ""; //完成时间:写完病历时填写
        $data['SXYS'] = $blData['SXYS'] ?? ""; //书写医生
        $data['BRKS'] = $blData['BRKS'] ?? ""; //病人科室
        $data['CJKS'] = $blData['CJKS'] ?? ""; //创建科室
        $data['BLZT'] = $blData['BLZT'] ?? ""; //病历状态:0书写1完成2封存(归档)9删除



        // 统一去掉半角/全角/Unicode 空白，并标准化冒号
        $hjnr = $this->normalizeExtractText($hjnr);
        $data['HJNR'] = $hjnr; //病历痕迹内容(取时间最晚的一条）

        if ((env("APP_NAME") == "sanyuan") && $this->shouldInsertSanyuanConsentData($blData)) {
            if ($this->insertSanyuanConsentData($blData, $data)) {
                return true;
            }
        }

        if (in_array($mblb, [288])) {
            // 三院的入院信息特殊处理
            if (env("APP_NAME") == "sanyuan") {
                // 裁剪掉入院记录之前的所有数据
                $hjnr = mb_substr($hjnr, mb_strpos($hjnr, "死亡记录"));
            } elseif (env("APP_NAME") == "lanling") {
                // 裁剪掉入院记录之前的所有数据
            }
            //入院记录
            $resData = $this->cleanDataFilter("clean_bllb_288", $ruleWord, $hjnr);
            $data = array_merge($data, $resData);
            if (!empty($resData['SWSJ'])) {
                $data['SWSJ'] = $this->formatTime($resData['SWSJ']);
            }
            Bllb288::query()->updateOrInsert([
                'ZYH' => $blData['JZHM'],
            ], $data);
        } elseif (in_array($mblb, [292, 11])) {
            // 三院的入院信息特殊处理
            if (env("APP_NAME") == "sanyuan") {
                //删除（以上辅助检查结果来自济南市第三人民医院）
                $hjnr = preg_replace('/（以上辅助检查结果来自济南市第三人民医院）/', '', $hjnr);
                // 裁剪掉入院记录之前的所有数据
                $hjnr = mb_substr($hjnr, mb_strpos($hjnr, "入院记录"));
                $hjnr = preg_replace('/其余体格检查/', '其余体格1检查1', $hjnr, 1);
                $hjnr = preg_replace('/结合体格检查/', '结合体格1检查1', $hjnr, 1);
                $hjnr = preg_replace('/体格检查因/', '体格1检查1因', $hjnr, 1);
                $hjnr = preg_replace('/体格检查项目/', '体格1检查1项目', $hjnr, 1);
                //$hjnr = preg_replace('/体格检查:/', '体格1检查1:', $hjnr, 1);
                //如果有多个体格检查，把第一个体格检查替换成体格检查1
                if (substr_count($hjnr, '体格检查') > 1) {
                    if (strpos($hjnr, '体格检查；') !== false) {
                        $hjnr = preg_replace('/体格检查；/', '体格1检查1', $hjnr, 1);
                    } else {
                        $hjnr = preg_replace('/体格检查/', '体格1检查1', $hjnr, 1);
                    }
                }
                if (substr_count($hjnr, '辅助检查') > 1) {
                    // 获取所有"辅助检查"的位置
                    $count = substr_count($hjnr, '辅助检查');
                    // 除了最后一个，其他都替换成"辅助1检查1"
                    for ($i = 0; $i < $count - 1; $i++) {
                        $hjnr = preg_replace('/辅助检查/', '辅助1检查1', $hjnr, 1);
                    }
                }
            } elseif (env("APP_NAME") == "lanling") {
                // 裁剪掉入院记录之前的所有数据
                $hjnr = str_replace("婚姻状况:", "婚姻:", $hjnr);
                $hjnr = str_replace("婚育史:", "婚姻史:", $hjnr);
                if (substr_count($hjnr, '体格检查') > 1) {
                    $hjnr = preg_replace('/体格检查/', '体格1检查1', $hjnr, 1);
                }
            } elseif (env("APP_NAME") == "ningxia") {
                // 查找出生地，如果有两个，把第一个替换成出生地1
                $hjnr = preg_replace('/出生地:/', '出生地1:', $hjnr, 1);
                $hjnr = preg_replace('/年龄:/', '年龄1:', $hjnr, 1);
                $hjnr = preg_replace('/住址:/', '地址:', $hjnr, 1);
                if (substr_count($hjnr, '辅助检查') > 1) {
                    $hjnr = preg_replace('/辅助检查/', '辅助1检查1', $hjnr, 1);
                }
                //既往病史-》既往史
                if (stripos($blData['BLMC'], '眼科') !== false) {
                    $hjnr = preg_replace('/既往病史/', '既往史:', $hjnr);
                    $hjnr = preg_replace('/眼病史/', '现病史:', $hjnr);
                    $hjnr = preg_replace('/入院诊断/', '初步诊断:', $hjnr);
                }
                // 如果没有"现病史："但有"现病史"，把"现病史"替换成"现病史:"
                if (stripos($hjnr, '现病史:') === false && stripos($hjnr, '现病史') !== false) {
                    $hjnr = preg_replace('/现病史/', '现病史:', $hjnr, 1);
                }


                $data['first_blsy_time'] = $blData['first_blsy_time'] ?? "";
                //书写医生职称
                $data['SXYSZC'] = Staff::query()->where('YHID', $blData['SXYS'])->value('ygjb_text'); //书写医生职称
                $qmys = EMR_BL_BLSY::query()->where('BLBH', $blData['BLBH'])->where('JLSJ', $blData['first_blsy_time'])->get()->toArray(); //签名医生
                if (!empty($qmys)) {
                    $data['QMYS'] = $qmys[0]['SYYS'];
                    $data['QMYSZC'] = Staff::query()->where('code', $data['QMYS'])->value('ygjb_text'); //签名医生职称
                }
            } elseif (env("APP_NAME") == "laizhou") {
                $hjnr = preg_replace('/现住址:/', '住址:', $hjnr, 1);
                $hjnr = preg_replace('/病史陈述者:/', '陈述者:', $hjnr, 1);
                $hjnr = preg_replace('/陈述者与患者关系:/', '陈述者:', $hjnr, 1);
            }
            //入院记录
            $resData = $this->cleanDataFilter("clean_bllb_292", $ruleWord, $hjnr);

            if (env("APP_NAME") == "ningxia") {
                if (substr_count($hjnr, '联系人/电话:') == 1 && substr_count($hjnr, '电话:') == 1) {
                    //把DH设置为空
                    $resData['DH'] = '';
                }
            }
            $data = array_merge($data, $resData);

            //把.替换成-，比如2025.08.01 12:00:00 替换成 2025-08-01 12:00:00
            $data['RYSJ'] = str_replace('.', '-', $data['RYSJ']);
            $data['JLSJ'] = str_replace('.', '-', $data['JLSJ']);
            $data['JLSJ'] = str_replace('(分)', '', $data['JLSJ']);

            if (!empty($data['RYSJ'])) {
                //$data['RYSJ'] = substr($data['RYSJ'], 0, 10) . ' ' . substr($data['RYSJ'], 10);
                // 处理日期格式，确保月份和日期都是两位数
                // 先处理中文日期格式：2026年01月04日03时47分
                if (preg_match('/^(\d{4})年(\d{1,2})月(\d{1,2})日(\d{1,2})时(\d{1,2})分$/', $data['RYSJ'], $matches)) {
                    $year = $matches[1];
                    $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                    $day = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
                    $hour = str_pad($matches[4], 2, '0', STR_PAD_LEFT);
                    $minute = str_pad($matches[5], 2, '0', STR_PAD_LEFT);
                    $data['RYSJ'] = $year . '-' . $month . '-' . $day . ' ' . $hour . ':' . $minute;
                }
                // 处理中文日期格式（无秒）：2026年01月04日03时47分
                elseif (preg_match('/(\d{4})年(\d{1,2})月(\d{1,2})日([\d:时分]*)/', $data['RYSJ'], $matches)) {
                    $year = $matches[1];
                    $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                    $day = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
                    $time = $matches[4] ?? '';
                    $data['RYSJ'] = $year . '-' . $month . '-' . $day . ' ' . $time;
                }
                // 处理标准日期格式：2025-08-01 12:00:00
                elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})([\d:]*)/', $data['RYSJ'], $matches)) {
                    $year = $matches[1];
                    $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                    $day = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
                    $time = $matches[4] ?? '';
                    $data['RYSJ'] = $year . '-' . $month . '-' . $day . ' ' . $time;
                }
            }
            if (!empty($data['JLSJ'])) {
                //$data['JLSJ'] = substr($data['JLSJ'], 0, 10) . ' ' . substr($data['JLSJ'], 10);
                if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})([\d:]*)/', $data['JLSJ'], $matches)) {
                    $year = $matches[1];
                    $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                    $day = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
                    $time = $matches[4] ?? '';
                    $data['JLSJ'] = $year . '-' . $month . '-' . $day . ' ' . $time;
                }
            }

            $data['CBZB_FIRST'] = $this->getFirstDiagnosis($data['CBZD']); //第一个初步诊断

            // 获取带大括号的入院记录
            $resData = $this->cleanDataFilter("clean_bllb_292", $ruleWord, $hjnr, 2);
            $data["YJJHYS_2"] = $resData["YJJHYS"];

            // 如果性别男，则将月经生育史设置为空
            if ($data["XB"] == "男") {
                $data["YJJHYS"] = "";
            } elseif (!empty($data["YJJHYS_2"])) {
                $text1 = $data["YJJHYS_2"] ?? "";

                preg_match_all("/经期([\d\-]+)天/", $text1, $matches);
                if (!empty($matches[1])) {
                    $data["TGJC_YJCXSJ"] = $matches[1] ?? "";
                }
                preg_match_all("/月经周期([\d\-]+)天/", $text1, $matches);
                if (!empty($matches[1])) {
                    $data["TGJC_YJZQ"] = $matches[1] ?? "";
                }
                // 月经信息处理
                if (env("APP_NAME") == "sanyuan") {
                    $text1 = str_replace("{", "", trim($text1, "}"));
                    $matches = explode("}", $text1);
                    if (!empty($matches[1])) {
                        $data["TGJC_YJZQ"] = $matches[2] ?? "";
                        $data["TGJC_YJCXSJ"] = $matches[1] ?? "";
                    }
                } elseif (env("APP_NAME") == "lanling") {
                    $pattern = '/FourValues\|(\d+),([\d\-]+),([\d\-]+),/';
                    preg_match($pattern, $text1, $matches);
                    if (!empty($matches[1])) {
                        $data["TGJC_YJZQ"] = $matches[3] ?? "";
                        $data["TGJC_YJCXSJ"] = $matches[2] ?? "";
                    }
                }
                if (!empty($data["TGJC_YJZQ"]) && is_array($data["TGJC_YJZQ"])) {
                    $data["TGJC_YJZQ"] = $data["TGJC_YJZQ"][0] ?? "";
                }
                if (!empty($data["TGJC_YJCXSJ"]) && is_array($data["TGJC_YJCXSJ"])) {
                    $data["TGJC_YJCXSJ"] = $data["TGJC_YJCXSJ"][0] ?? "";
                }
            }

            $staff = Staff::query()->get()->toArray();
            $staffList = array_column($staff, "name");
            $data["CBZD"] = str_replace($staffList, '', $data["CBZD"]);

            $zdxy = $this->getBlData($blData['JZHM'], '29200001', '')[0]['HJNR'] ?? '';
            if (!empty($zdxy)) {
                //诊断续页
                $zdxyResData = $this->cleanDataFilter("clean_bllb_29200001", $ruleWord, $zdxy);
                $data = array_merge($data, $zdxyResData);
            }


            $lxr = $data['LXR'] ?? '';
            if (!empty($lxr)) {
                // 先将多个连续的/替换为单个/
                $lxr = preg_replace('/\/+/', '/', $lxr);
                // 按照/分隔
                $lxrList = explode('/', $lxr);
                if (count($lxrList) > 1) {
                    $data['LXR'] = $lxrList[0];
                    $data['LXRDH'] = $lxrList[1];
                }
            }

            $ryzd = $data['RYZD'] ?? '';
            if (!empty($ryzd)) {
                //按照/分隔
                $ryzdList = explode('时间:', $ryzd);
                if (count($ryzdList) > 1) {
                    $data['RYZD'] = $ryzdList[0];
                    $data['RYZDSJ'] = $ryzdList[1];
                }
            }

            $bczd = $data['BCZD'] ?? '';
            if (!empty($bczd)) {
                //按照/分隔
                $bczdList = explode('时间:', $bczd);
                if (count($bczdList) > 1) {
                    $data['BCZD'] = $bczdList[0];
                    $data['BCZDSJ'] = $bczdList[1];
                }
            }

            $xzzd = $data['XZZD'] ?? '';
            if (!empty($xzzd)) {
                //按照/分隔
                $xzzdList = explode('时间:', $xzzd);
                if (count($xzzdList) > 1) {
                    $data['XZZD'] = $xzzdList[0];
                    $data['XZZDSJ'] = $xzzdList[1];
                }
            }

            $cyzd = $data['CYZD'] ?? '';
            if (!empty($cyzd)) {
                //按照/分隔
                $cyzdList = explode('时间:', $cyzd);
                if (count($cyzdList) > 1) {
                    $data['CYZD'] = $cyzdList[0];
                    $data['CYZDSJ'] = $cyzdList[1];
                }
            }

            if (env("APP_NAME") == "fangcheng") {
                if (!empty($data['JLSJ'])) {
                    if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}(?::\d{2})?)/', $data['JLSJ'], $matches)) {
                        $data['JLSJ'] = $matches[1];
                    }
                }
                if (!empty($data['RYSJ'])) {
                    if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}(?::\d{2})?)/', $data['RYSJ'], $matches)) {
                        $data['RYSJ'] = $matches[1];
                    }
                }
                if (!empty($data['XB'])) {
                    // 正则表达式匹配性别，只保留男或女，其他情况置空
                    if (preg_match('/(男|女)/u', $data['XB'], $match)) {
                        $data['XB'] = $match[1];
                    } else {
                        $data['XB'] = '';
                    }
                }
                if (!empty($data['XM'])) {
                    // 李德香病案号码FC067029，正则匹配出病案号前面的姓名
                    if (preg_match('/^(.*?)(医院名称|病案号码|农民|工人|学生)/', $data['XM'], $match)) {
                        $data['XM'] = trim($match[1]);
                    }
                }
                if (!empty($data['HY'])) {
                    // 正则匹配出婚姻情况，只保留已婚、未婚、未知，其他情况置空
                    if (preg_match('/(已婚|未婚|未知)/u', $data['HY'], $match)) {
                        $data['HY'] = $match[1];
                    } else {
                        $data['HY'] = '';
                    }
                }
                // 处理体格检查(TGJC)一行内容，保证T/P/R/BP等之间有适当间隔
                if (!empty($data['TGJC'])) {
                    // 对含有体格检查内容的行进行正则替换，将T,P,R,BP间的/分替换成空格
                    $data['TGJC'] = preg_replace_callback(
                        '/(T\d{1,2}\.\d+℃)[\/\s]*([PR]{1}\d+次\/分)[\/\s]*([PR]{1}\d+次\/分)?[\/\s]*((BP\d+\/\d+mmHg)?)/u',
                        function ($matches) {
                            $result = [];
                            for ($i = 1; $i <= 4; $i++) {
                                if (isset($matches[$i]) && !empty($matches[$i])) {
                                    $result[] = trim($matches[$i]);
                                }
                            }
                            return implode('  ', $result);
                        },
                        $data['TGJC']
                    );
                    // 兼容有些写作T...P...R...BP...（可能顺序不一或部分缺失）每个项单独匹配后加空格
                    // 将"T\d{1,2}\.\d+℃" "P\d+次/分" "R\d+次/分" "BP\d+/\d+mmHg"之间缺少空格的加空格
                    $patterns = [
                        '/(T\d{1,2}\.\d+℃)[\s]*([PR]{1}\d+次\/分)/u',
                        '/([PR]{1}\d+次\/分)[\s]*([PR]{1}\d+次\/分)/u',
                        '/([PR]{1}\d+次\/分)[\s]*((?:BP)?\d+\/\d+mmHg)/u',
                        '/(BP\d+\/\d+mmHg)[\s]*(T\d{1,2}\.\d+℃)/u',
                    ];
                    foreach ($patterns as $p) {
                        $data['TGJC'] = preg_replace($p, '$1  $2', $data['TGJC']);
                    }
                }
            }

            //删除
            Bllb292::query()->where('ZYH', (string)$blData['JZHM'])->delete();
            Bllb292::query()->where('BLBH', (string)$blData['BLBH'])->delete();
            Bllb292::query()->updateOrInsert([
                'ZYH' => (string)$blData['JZHM'],
            ], $data);
            // 格式化的数据同步到ES
            //EsSaveService::bllb292($blData['JZHM']);
        } elseif (in_array($mblb, [1])) {

            if (env("APP_NAME") == "fangcheng") {
                $bllb1 = $this->formatFangchengBllb1($hjnr);
                $data = array_merge($data, $bllb1);
            } else {
                // 三院的入院信息特殊处理
                if (env("APP_NAME") == "sanyuan") {
                    $hjnr = mb_substr($hjnr, mb_strpos($hjnr, "出院记录"));
                } elseif (env("APP_NAME") == "lanling") {
                    $hjnr = str_replace("入院时间:", "入院日期:", $hjnr);
                } elseif (env("APP_NAME") == "ningxia") {
                    //如果包含出院时情况：不包含出院情况：，把出院时情况：替换成出院情况：
                    if (strpos($hjnr, '出院时情况:') !== false && strpos($hjnr, '出院情况:') === false) {
                        $hjnr = str_replace('出院时情况:', '出院情况:', $hjnr);
                    }
                    // 特殊情况处理，兼容眼科出院记录
                    if (strpos($hjnr, '入院情况:主诉') !== false) {
                        $hjnr = str_replace('入院情况:主诉:', '入院情况:', $hjnr);
                    }
                }
                $resData = $this->cleanDataFilter("clean_bllb_1", $ruleWord, $hjnr);
                $data = array_merge($data, $resData);
                $data['CYZD_FIRST'] = $this->getFirstDiagnosis($data['CYZD']); //第一个出院诊断
                // 时间在格式的时候去掉了空格，但是数据表中的字段时间格式，所以需要把去掉空格的时间格式化成正确的时间格式
                if (strpos($data['RYRQ'], '年') === false) {
                    $data['RYRQ'] = substr($data['RYRQ'], 0, 10) . ' ' . substr($data['RYRQ'], 10);
                } else {
                    $data['RYRQ'] = $this->formatTime($data['RYRQ']);
                }
                if (strpos($data['CYRQ'], '年') === false) {
                    $data['CYRQ'] = substr($data['CYRQ'], 0, 10) . ' ' . substr($data['CYRQ'], 10);
                } else {
                    $data['CYRQ'] = $this->formatTime($data['CYRQ']);
                }
            }
            Bllb1::query()->updateOrInsert([
                'ZYH' => (string)$blData['JZHM'],
            ], $data);
            // 格式化的数据同步到ES
            //EsSaveService::bllb1($blData['JZHM']);
        } elseif (in_array($mblb, [295])) {
            // 兰陵首次病程数据和其他医院格式不一样，其中的姓名、年龄、性别需要单独获取
            if (env("APP_NAME") == "lanling") {
                $hjnr = mb_substr($hjnr, mb_strpos($hjnr, "首次病程记录") + mb_strlen("首次病程记录"));
                $pattern = '/(?:患者)?([\x{4e00}-\x{9fa5}]+)，([男|女]性)，(\d+)岁/Uu';
                preg_match($pattern, $hjnr, $matches);
                if (preg_match($pattern, $hjnr, $matches)) {
                    $data["XM"] = $matches[1] ?? "";
                    $data["XB"] = $matches[2] ?? "";
                    $data["NL"] = $matches[3] ?? "";
                }
            }

            if (env("APP_NAME") == "ningxia") {
                //如果有多个，把第一个之后的都替换成带1的版本
                if (substr_count($hjnr, '姓名') > 1) {
                    // 找到所有"姓名"的位置
                    $positions = [];
                    $offset = 0;
                    while (($pos = mb_strpos($hjnr, '姓名', $offset)) !== false) {
                        $positions[] = $pos;
                        $offset = $pos + mb_strlen('姓名');
                    }

                    // 从后往前替换，避免位置偏移，保留第一个
                    for ($i = count($positions) - 1; $i > 0; $i--) {
                        $hjnr = mb_substr($hjnr, 0, $positions[$i]) .
                            '姓1名1' .
                            mb_substr($hjnr, $positions[$i] + mb_strlen('姓名'));
                    }
                }
                if (substr_count($hjnr, '年龄') > 1) {
                    // 找到所有"年龄"的位置
                    $positions = [];
                    $offset = 0;
                    while (($pos = mb_strpos($hjnr, '年龄', $offset)) !== false) {
                        $positions[] = $pos;
                        $offset = $pos + mb_strlen('年龄');
                    }

                    // 从后往前替换，避免位置偏移，保留第一个
                    for ($i = count($positions) - 1; $i > 0; $i--) {
                        $hjnr = mb_substr($hjnr, 0, $positions[$i]) .
                            '年1龄1' .
                            mb_substr($hjnr, $positions[$i] + mb_strlen('年龄'));
                    }
                }
                if (substr_count($hjnr, '性别') > 1) {
                    $positions = [];
                    $offset = 0;
                    while (($pos = mb_strpos($hjnr, '性别', $offset)) !== false) {
                        $positions[] = $pos;
                        $offset = $pos + mb_strlen('性别');
                    }

                    // 从后往前替换，避免位置偏移，保留第一个
                    for ($i = count($positions) - 1; $i > 0; $i--) {
                        $hjnr = mb_substr($hjnr, 0, $positions[$i]) .
                            '性1别1' .
                            mb_substr($hjnr, $positions[$i] + mb_strlen('性别'));
                    }
                }
                if (substr_count($hjnr, '民族') > 1) {
                    $positions = [];
                    $offset = 0;
                    while (($pos = mb_strpos($hjnr, '民族', $offset)) !== false) {
                        $positions[] = $pos;
                        $offset = $pos + mb_strlen('民族');
                    }

                    // 从后往前替换，避免位置偏移，保留第一个
                    for ($i = count($positions) - 1; $i > 0; $i--) {
                        $hjnr = mb_substr($hjnr, 0, $positions[$i]) .
                            '民1族1' .
                            mb_substr($hjnr, $positions[$i] + mb_strlen('民族'));
                    }
                }

                //如果不包含五、诊疗计划且包含六、诊疗计划，则把六、诊疗计划替换成五、诊疗计划
                if (strpos($hjnr, '五、诊疗计划') === false && strpos($hjnr, '六、诊疗计划') !== false) {
                    $hjnr = str_replace('六、诊疗计划', '五、诊疗计划', $hjnr);
                }
            }

            if (env("APP_NAME") == "laizhou") {
                $hjnr = preg_replace('/病史陈述者:/', '陈述者:', $hjnr, 1);
            }

            $resData = $this->cleanDataFilter("clean_bllb_294_295", $ruleWord, $hjnr);
            if (
                env("APP_NAME") == "ningxia" &&
                preg_match('/中医辨证辨病依据及鉴别诊断(.*?)2\.主诉/su', $hjnr, $matches)
            ) {
                $mergedDiagnosisContent = trim($matches[1] ?? '');
                if ($mergedDiagnosisContent !== '') {
                    $resData['ZDYJ'] = $mergedDiagnosisContent;
                    $resData['JBZD'] = $mergedDiagnosisContent;
                }
            }
            // 使用正则表达式提取第二条（2.）内容，并赋值给$resData['BLTD_2']
            if (isset($resData['BLTD'])) {
                // 匹配以2.开头，直到3.或字符串结尾的内容
                if (preg_match('/2\.(.*?)(3\.|$)/s', $resData['BLTD'], $match)) {
                    $resData['BLTD_2'] = trim($match[1]);
                } else {
                    $resData['BLTD_2'] = '';
                }
            }
            $data = array_merge($data, $resData);
            // 把替换掉的特殊字符在替换回来
            foreach ($data as $key => &$val2) {
                $val2 = str_replace('姓1名1', '姓名', $val2);
                $val2 = str_replace('年1龄1', '年龄', $val2);
                $val2 = str_replace('性1别1', '性别', $val2);
                $val2 = str_replace('民1族1', '民族', $val2);
            }

            Bllb294_295::query()->updateOrInsert([
                'ZYH' => (string)$blData['JZHM'],
            ], $data);
            // 格式化的数据同步到ES
            //EsSaveService::bllb294_295_2023($blData['JZHM']);
        } elseif (in_array($mblb, [45])) {
            //输血记录----这个也会有多个
            //输血记录,存在风险:,应对预案:,输血前医患沟通情况:,输血后疗效初步评价:,
            if (env("APP_NAME") == "ningxia") {
                $str_start = mb_stripos($hjnr, '患者于');
                $str_end = mb_stripos($hjnr, '输完');
                $data['SXKSSJ'] = mb_substr($hjnr, ($str_start + 3), ($str_start + 15));
                $data['SXJSSJ'] = mb_substr($hjnr, ($str_end - 18), ($str_end - 3));
            } elseif (env("APP_NAME") == "sanyuan") {
                $pattern = '/于(.*)至(.*?)给予/';
                if (preg_match($pattern, $hjnr, $matches)) {
                    $matches[1] = str_replace(["{", "}", "[", "]"], "", $matches[1]);
                    $matches[2] = str_replace(["{", "}", "[", "]"], "", $matches[2]);

                    $matches[1] = $this->getSxTime($matches[1], $blData['BLMC']);
                    $matches[2] = $this->getSxTime($matches[2], $blData['BLMC']);
                    $data['SXKSSJ'] = $matches[1];
                    $data['SXJSSJ'] = $matches[2];
                }
            }

            $resData = $this->cleanDataFilter("clean_bllb_292_45", $ruleWord, $hjnr);
            $data = array_merge($data, $resData);
            // 三院：XM|姓名,SXJL|病程记录
            // 输血记录,存在风险:,应对预案:,输血前医患沟通情况:,输血后疗效初步评价:
            Bllb294_45::query()->updateOrInsert([
                'BLBH' => (string)$blData['BLBH'],
            ], $data);
            // 格式化的数据同步到ES
            //EsSaveService::bllb294_45($blData['JZHM']);
        } elseif (in_array($mblb, [306, 74])) {

            if (env("APP_NAME") == "fangcheng") {
                $bllb1 = $this->formatFangchengBllb303($hjnr);
                $data = array_merge($data, $bllb1);
            } else {
                if (strpos($data['BLMC'], '粘贴') !== false || strpos($data['BLMC'], '化验') !== false || strpos($data['BLMC'], '核查') !== false || strpos($data['BLMC'], '附页') !== false) {
                    return true;
                }
                //手术记录--这个可能有多个
                if (env("APP_NAME") == "laizhou") {
                    $hjnr = preg_replace('/手术助手:/', '助手:', $hjnr, 1);
                }
                //记录时间:,姓名:,性别:,年龄:,手术日期:,术前诊断:,术后诊断:,拟施手术:,已施手术:,手术者:,助手:,护士:,麻醉方式:,麻醉者:,手术经过及标本送检:,手术中出血:,输血:,病理检查:
                $resData = $this->cleanDataFilter("clean_bllb_303", $ruleWord, $hjnr);
                $data = array_merge($data, $resData);
                foreach ($data as $key => &$value) {
                    $value = trim($value, '、');
                }

                if (isset($data['SSRQ'])) {
                    $data['SSRQ'] = date('Y-m-d', strtotime($this->formatTime($data['SSRQ'])));
                }

                if (!empty($data['SSKSSJ'])) {

                    // 判断$sssj[0]是否包含时间，如果不包含则$sssj[0]拼接上$data['SSRQ']字段
                    if (isset($data['SSRQ']) && preg_match('/^(\d{2}:\d{2})/', $data['SSKSSJ'], $matches)) {
                        // 确保中间有个空格
                        $data['SSKSSJ'] = $data['SSRQ'] . $matches[1];
                    }
                }

                if (!empty($data['SSJSSJ'])) {
                    // 判断$sssj[0]是否包含时间，如果不包含则$sssj[0]拼接上$data['SSRQ']字段
                    if (isset($data['SSRQ']) && preg_match('/^(\d{2}:\d{2})/', $data['SSJSSJ'], $matches)) {
                        // 确保中间有个空格
                        $data['SSJSSJ'] = $data['SSRQ'] . $matches[1];
                    }
                }
                // 手术日期的起止时间能获取到的话，则切割之后分为手术开始时间和手术结束时间
                if (isset($data['SSSJ']) && !empty($data['SSSJ']) && empty($data['SSKSSJ']) && empty($data['SSJSSJ'])) {
                    $data['SSSJ'] = str_replace("，", " ", $data['SSSJ']);
                    if (strpos($data['SSSJ'], '～') !== false) {
                        $sssj = explode('～', $data['SSSJ']);
                    } elseif (strpos($data['SSSJ'], '-') !== false) {
                        if (isset($data['SSSJ'])) {
                            $data['SSSJ'] = preg_replace('/-+/', '-', $data['SSSJ']);
                        }
                        $sssj = explode('-', $data['SSSJ']);
                    }

                    $ssrq = $data['SSRQ'];
                    $ssrq = explode('~', $ssrq);

                    if (!empty($sssj[0])) {
                        // 判断$sssj[0]是否包含时间，如果不包含则$sssj[0]拼接上$data['SSRQ']字段
                        if (isset($data['SSRQ']) && preg_match('/^(\d{1,2}:\d{2})/', $sssj[0])) {
                            // 确保中间有个空格
                            $sssj[0] = $ssrq[0] . ' ' . trim($sssj[0]);
                        }
                        // 将中文格式如"2025年11月05日15时45分"转成"2025-11-05 15:45"
                        if (!empty($sssj[0]) && preg_match('/(\d{4})年(\d{2})月(\d{2})日(\d{2})时(\d{2})分/', $sssj[0], $match)) {
                            $sssj[0] = sprintf('%s-%s-%s %s:%s', $match[1], $match[2], $match[3], $match[4], $match[5]);
                        }
                        $data['SSKSSJ'] = !empty($sssj[0]) ? date('Y-m-dH:i:s', strtotime($sssj[0])) : '';
                    }
                    if (!empty($sssj[1])) {
                        // 判断$sssj[0]是否包含时间，如果不包含则$sssj[0]拼接上$data['SSRQ']字段
                        if (isset($data['SSRQ']) && preg_match('/^(\d{1,2}:\d{2})/', $sssj[1])) {
                            // 确保中间有个空格
                            $sssj[1] = (!empty($ssrq[1]) ? $ssrq[1] : $ssrq[0]) . ' ' . trim($sssj[1]);
                        }
                        if (!empty($sssj[1]) && preg_match('/(\d{4})年(\d{2})月(\d{2})日(\d{2})时(\d{2})分/', $sssj[1], $match1)) {
                            $sssj[1] = sprintf('%s-%s-%s %s:%s', $match1[1], $match1[2], $match1[3], $match1[4], $match1[5]);
                        }
                        $data['SSJSSJ'] = !empty($sssj[1]) ? date('Y-m-dH:i:s', strtotime($sssj[1])) : '';
                    }
                }
                unset($data['SSSJ']);
                if (env("APP_NAME") == "lanling") {
                    preg_match('/(\d{4}-\d{2}-\d{2}\d{2}:\d{2})-(\d{4}-\d{2}-\d{2}\d{2}:\d{2})|(\d{2}:\d{2})-(\d{2}:\d{2})/', $hjnr, $matches);
                    if ($matches) {
                        if (!empty($matches[1]) && !empty($matches[2])) {
                            $startTime = $matches[1];
                            $endTime = $matches[2];
                        } else {
                            $startTime = $matches[3];
                            $endTime = $matches[4];
                        }
                        $data["SSKSSJ"] = $startTime;
                        $data["SSJSSJ"] = $endTime;
                    }
                }
                $data['SSKSSJ'] = substr($data['SSKSSJ'], 0, 10) . ' ' . substr($data['SSKSSJ'], 10);
                $data['SSKSSJ'] = date('Y-m-d H:i:s', strtotime($data['SSKSSJ']));
                $data['SSJSSJ'] = substr($data['SSJSSJ'], 0, 10) . ' ' . substr($data['SSJSSJ'], 10);
                $data['SSJSSJ'] = date('Y-m-d H:i:s', strtotime($data['SSJSSJ']));

                if (env("APP_NAME") == "laizhou") {
                    //从数组中去除JLSJ
                    if (isset($data['JLSJ'])) {
                        $jlsj = $data['JLSJ'];
                        // 如果$jlsj的格式是2025年07月16日13时15分，则替换成2025-07-16 13:15
                        if (preg_match('/(\d{4})年(\d{2})月(\d{2})日(\d{2})时(\d{2})分/', $jlsj, $match)) {
                            $jlsj = sprintf('%s-%s-%s %s:%s', $match[1], $match[2], $match[3], $match[4], $match[5]);
                        } else {
                            $jlsj = substr($jlsj, 0, 10) . ' ' . substr($jlsj, 10);
                        }

                        $bl01 = EMR_BL_BL01::query()->where('BLBH', (string)$blData['BLBH'])->get()->toArray();
                        $blmc = $bl01[0]['BLMC'];
                        //先看是否包含时间，如果包含时间，则替换时间
                        if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/', $blmc, $matches)) {
                            $blmc = str_replace($matches[1], $jlsj, $blmc);
                        } else {
                            $blmc = $jlsj . ' ' . $blmc;
                        }
                        EMR_BL_BL01::query()->where('BLBH', (string)$blData['BLBH'])->update(['BLMC' => $blmc, 'ZXSJ' => $jlsj]);
                        unset($data['JLSJ']);
                    }
                }
            }

            Bllb303::query()->updateOrInsert([
                'BLBH' => (string)$blData['BLBH'],
            ], $data);

            // if (env("APP_NAME") == "laizhou") {
            //     SM_SSAP::query()->updateOrInsert([
            //         'BLBH' => $data['BLBH'],
            //     ], [
            //         'ZYHM' => $data['ZYH'],
            //         'ZYH' => $data['ZYH'],
            //         'SSRQ' => $data['SSKSSJ'] ?? '',
            //         'SSKS' => $data['BRKS'] ?? '',
            //         'ICD9_SSCZMC' => $data['SSMC'] ?? '',
            //         'SZ' => $data['SSZ'] ?? '',
            //         'JSRQ' => $data['SSJSSJ'] ?? '',
            //     ]);
            // }


            //EsSaveService::bllb303($blData['JZHM']);
        } elseif (in_array($mblb, [30301])) {
            //剖宫产记录
            // CH|床位号,XM|姓名,XB|性别,NL|年龄,SQZD|术前诊断,SSMC|手术名称,SSZ|手术者,ZS|一助,YBJL|一般记录,SSQZSJ|手术起止时间,SSSC|手术时长,SHZD|术后诊断,NSSS|拟施手术,SSSYZ|手术适应症,EZ|二助,QXHS|器械护士,MZFS|麻醉方式,MZYS|麻醉医师,XHHS|巡回护士,SSGC|手术过程,TW|体位,QKBW|切口部位,SSBH|手术疤痕,ZFHD|脂肪厚度,ZGZDXC|子宫下段形成,XL|先露,ZGQK|子宫切口,QKYC|切口延长,QKLS|切口裂伤,YS|羊水,YSE|颜色,TPBL|胎盘剥离,TPBM|胎盘胎膜,TAIW|胎位,MCFSJQK|娩出方式及情况,GS|宫缩,GSJ|宫缩剂,GBFH|宫壁缝合,SFJ|双附件,FZFMFH|反折腹膜缝合,JYFS|绝育方式,SZSXL|术中失血量,CXYY|出血原因,SXQK|输血情况,CFQK|产妇情况,XSEQK|新生儿情况,XSEXB|宫壁缝合,XSETZ|宫壁缝合,PF|宫壁缝合,MZXG|宫壁缝合,TSQK|宫壁缝合
            $resData = $this->cleanDataFilter("clean_bllb_303_30301", $ruleWord, $hjnr);
            $data = array_merge($data, $resData);

            if (env("APP_NAME") == "lanling") {
                preg_match('/(\d{4}-\d{2}-\d{2}\d{2}:\d{2})-(\d{4}-\d{2}-\d{2}\d{2}:\d{2})|(\d{2}:\d{2})-(\d{2}:\d{2})/', $hjnr, $matches);
                if ($matches) {
                    if (!empty($matches[1]) && !empty($matches[2])) {
                        $startTime = $matches[1];
                        $endTime = $matches[2];
                    } else {
                        $startTime = $matches[3];
                        $endTime = $matches[4];
                    }
                    $data["SSKSSJ"] = $startTime;
                    $data["SSJSSJ"] = $endTime;
                    $data["SSQZSJ"] = $data["SSKSSJ"] . ' - ' . $data["SSJSSJ"];
                }
            }
            unset($data["SSKSSJ"], $data["SSJSSJ"]);

            Bllb303_303::query()->updateOrInsert([
                'ZYH' => $blData['JZHM'],
                'BLBH' => $blData['BLBH'],
            ], $data);
        } /* elseif (in_array($mblb, [3069999])) { //新增
            //分娩记录
            //详情记录:,阵缩开始:,婴儿出生情况:,性别:,胎膜破裂:,体重:,身长:,破膜方式:,娩出方位:,产瘤:,新生儿异常情况:,分娩时间:,
            //其他:,胎盘娩出:,胎盘体积:,脐带长短:,胎盘重量:,脐带附着:,产后失血:,胎盘异常:,脐带异常:,脐带扭转:,羊水颜色:,胎膜:,羊水量:,
            //胎膜破裂处:,羊水气味:,分娩麻醉:,宫缩剂:,时间:,产后时间:,血压:,心率:,宫缩:,宫底高度:,膀胱充盈:,肠胀气:,手术:,备注:,最后诊断:
            $data['CWH'] = $this->getContent($ruleWord, $hjnr, '床位号');  //床位号
            $data['XQJL'] = $this->getContent($ruleWord, $hjnr, '详情记录');  //详情记录
            $data['ZSKS'] = $this->getContent($ruleWord, $hjnr, '阵缩开始');  //阵缩开始
            $data['YECSQK'] = $this->getContent($ruleWord, $hjnr, '婴儿出生情况');  //婴儿出生情况
            $data['XB'] = $this->getContent($ruleWord, $hjnr, '性别');  //性别
            $data['TMPL'] = $this->getContent($ruleWord, $hjnr, '胎膜破裂');  //胎膜破裂
            $data['TZ'] = $this->getContent($ruleWord, $hjnr, '体重');  //体重
            $data['SC'] = $this->getContent($ruleWord, $hjnr, '身长');  //身长
            $data['PMFS'] = $this->getContent($ruleWord, $hjnr, '破膜方式');  //破膜方式
            $data['MCFW'] = $this->getContent($ruleWord, $hjnr, '娩出方位');  //娩出方位
            $data['CL'] = $this->getContent($ruleWord, $hjnr, '产瘤');  //产瘤
            $data['XSEYCQK'] = $this->getContent($ruleWord, $hjnr, '新生儿异常情况');  //新生儿异常情况
            $data['FMSJ'] = $this->getContent($ruleWord, $hjnr, '分娩时间');  //分娩时间
            $data['QT'] = $this->getContent($ruleWord, $hjnr, '其他');  //其他
            $data['TPMC'] = $this->getContent($ruleWord, $hjnr, '胎盘娩出');  //胎盘娩出
            $data['TPTJ'] = $this->getContent($ruleWord, $hjnr, '胎盘体积');  //胎盘体积
            $data['QDCD'] = $this->getContent($ruleWord, $hjnr, '脐带长短');  //脐带长短
            $data['TPZL'] = $this->getContent($ruleWord, $hjnr, '胎盘重量');  //胎盘重量
            $data['QDFZ'] = $this->getContent($ruleWord, $hjnr, '脐带附着');  //脐带附着
            $data['CHSX'] = $this->getContent($ruleWord, $hjnr, '产后失血');  //产后失血
            $data['TPYC'] = $this->getContent($ruleWord, $hjnr, '胎盘异常');  //胎盘异常
            $data['QDYC'] = $this->getContent($ruleWord, $hjnr, '脐带异常');  //脐带异常
            $data['QDNZ'] = $this->getContent($ruleWord, $hjnr, '脐带扭转');  //脐带扭转
            $data['YSYS'] = $this->getContent($ruleWord, $hjnr, '羊水颜色');  //羊水颜色
            $data['TM'] = $this->getContent($ruleWord, $hjnr, '胎膜');  //胎膜
            $data['YSL'] = $this->getContent($ruleWord, $hjnr, '羊水量');  //羊水量
            $data['TMPLC'] = $this->getContent($ruleWord, $hjnr, '胎膜破裂处');  //胎膜破裂处
            $data['YSQW'] = $this->getContent($ruleWord, $hjnr, '羊水气味');  //羊水气味
            $data['FMMZ'] = $this->getContent($ruleWord, $hjnr, '分娩麻醉');  //分娩麻醉
            $data['GSJ'] = $this->getContent($ruleWord, $hjnr, '宫缩剂');  //宫缩剂
            $data['SJ'] = $this->getContent($ruleWord, $hjnr, '时间');  //时间
            $data['CHSJ'] = $this->getContent($ruleWord, $hjnr, '产后时间');  //产后时间
            $data['XY'] = $this->getContent($ruleWord, $hjnr, '血压');  //血压
            $data['XL'] = $this->getContent($ruleWord, $hjnr, '心率');  //心率
            $data['GS'] = $this->getContent($ruleWord, $hjnr, '宫缩');  //宫缩
            $data['GDGD'] = $this->getContent($ruleWord, $hjnr, '宫底高度');  //宫底高度
            $data['PGCY'] = $this->getContent($ruleWord, $hjnr, '膀胱充盈');  //膀胱充盈
            $data['CZQ'] = $this->getContent($ruleWord, $hjnr, '肠胀气');  //肠胀气
            $data['SS'] = $this->getContent($ruleWord, $hjnr, '手术');  //手术
            $data['BZ'] = $this->getContent($ruleWord, $hjnr, '备注');  //备注
            $data['ZHZD'] = $this->getContent($ruleWord, $hjnr, '最后诊断');  //最后诊断

            Bllb303_9999::query()->updateOrInsert([
                'ZYH' => $blData['JZHM'],
                'BLBH' => $blData['BLBH'],
            ], $data);
        } */

        return true;
    }

    protected function insertSanyuanConsentData($blData = [], $baseData = [])
    {
        $templateKey = $this->matchSanyuanConsentTemplateByRecord($blData);
        if ($templateKey === '') {
            return false;
        }

        $data = array_merge($baseData, [
            'BLLB' => (int)($blData['BLLB'] ?? 0),
            'MBLB' => (int)($blData['MBLB'] ?? 0),
        ], $this->extractSanyuanConsentTemplateData($templateKey, $blData['HTML_PRINT'] ?? ''));

        switch ($templateKey) {
            case 'tszl':
                $this->updateOrInsertByBlbh(Bllb329Tszl::class, $data);
                break;
            case 'yzccstys':
                $this->updateOrInsertByBlbh(Bllb329Yzccstys::class, $data);
                break;
            case 'bdqp':
                $this->updateOrInsertByBlbh(Bllb329Bdqp::class, $data);
                break;
            case 'hltys':
                $this->updateOrInsertByBlbh(Bllb329Hltys::class, $data);
                break;
            case 'sszqtys':
                $this->updateOrInsertByBlbh(Bllb329Sszqtys::class, $data);
                break;
            case 'sxzqtys':
                $this->updateOrInsertByBlbh(Bllb329Sxzqtys::class, $data);
                break;
            case 'ctzq':
                $this->updateOrInsertByBlbh(Bllb329Ctzq::class, $data);
                break;
            case 'bwbztys':
                $this->updateOrInsertByBlbh(Bllb329Bwbztys::class, $data);
                break;
            case 'sqwts':
                $this->updateOrInsertByBlbh(Bllb329Sqwts::class, $data);
                break;
            case 'zrltys':
                $this->updateOrInsertByBlbh(Bllb329Zrltys::class, $data);
                break;
            case 'jjjctys':
                $this->updateOrInsertByBlbh(Bllb34Jjjctys::class, $data);
                break;
            case 'zdcygzs':
                $this->updateOrInsertByBlbh(Bllb34Zdcygzs::class, $data);
                break;
            case 'sjgzs':
                $this->updateOrInsertByBlbh(Bllb34Sjgzs::class, $data);
                break;
            case 'ybwffgzs':
                $this->updateOrInsertByBlbh(Bllb34Ybwffgzs::class, $data);
                break;
            default:
                return false;
        }

        return true;
    }

    protected function updateOrInsertByBlbh($modelClass, array $data)
    {
        $medicalRecordNumber = (string)($data['BLBH'] ?? '');
        if ($medicalRecordNumber === '') {
            return false;
        }

        $data['BLBH'] = $medicalRecordNumber;

        try {
            $modelClass::query()->updateOrInsert(['BLBH' => $medicalRecordNumber], $data);
            return true;
        } catch (QueryException $exception) {
            if (!$this->isDuplicateKeyException($exception)) {
                throw $exception;
            }

            $modelClass::query()
                ->where('BLBH', $medicalRecordNumber)
                ->getQuery()
                ->update($data);

            return true;
        }
    }

    protected function isDuplicateKeyException(QueryException $exception)
    {
        return (int)($exception->errorInfo[1] ?? 0) === 1062;
    }

    protected function shouldInsertSanyuanConsentData($blData = [])
    {
        if ($this->matchSanyuanConsentTemplateByRecord($blData) === 'yzccstys') {
            return true;
        }

        $bllb = (int)($blData['BLLB'] ?? 0);
        if ($bllb === 329) {
            return true;
        }

        if ($bllb !== 2000185) {
            if ($bllb !== 34) {
                return false;
            }

            return in_array(
                $this->matchSanyuanConsentTemplateByRecord($blData),
                ['jjjctys', 'zdcygzs', 'sjgzs', 'ybwffgzs'],
                true
            );
        }

        return $this->matchSanyuanConsentTemplateByRecord($blData) === 'bwbztys';
    }

    protected function matchSanyuanConsentTemplateByRecord($blData = [])
    {
        if ($this->isSanyuanYzccstysRecord($blData)) {
            return 'yzccstys';
        }

        return $this->matchSanyuanConsentTemplate($blData['BLMC'] ?? '', $blData['HTML_PRINT'] ?? '');
    }

    protected function isSanyuanYzccstysRecord($blData = [])
    {
        if ((int)($blData['BLLB'] ?? 0) !== 329) {
            return false;
        }

        $blmc = (string)($blData['BLMC'] ?? '');
        if ($blmc === '') {
            return false;
        }

        foreach ($this->getSanyuanYzccstysKeywords() as $keyword) {
            if ($this->containsNormalizedKeyword($blmc, $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected function getSanyuanYzccstysKeywords()
    {
        static $keywords = null;

        if ($keywords !== null) {
            return $keywords;
        }

        $keywordText = (string)RuleWordMap::query()->where('id', 8206)->value('keyword');
        $keywordList = preg_split('/[,，]/u', $keywordText);
        $keywords = [];

        foreach ($keywordList as $keyword) {
            $keyword = trim((string)$keyword);
            if ($keyword !== '') {
                $keywords[] = $keyword;
            }
        }

        return $keywords;
    }

    protected function matchSanyuanConsentTemplate($blmc = '', $htmlPrint = '')
    {
        $keywordMap = [
            'tszl' => '特殊检查/特殊治疗知情同意书',
            'bdqp' => '术中冰冻切片检查知情同意书',
            'hltys' => '化疗知情同意书',
            'sszqtys' => '手术知情同意书',
            'sxzqtys' => '输血治疗知情同意书',
            'ctzq' => 'CT增强检查知情同意书',
            'bwbztys' => '病危（病重）通知书',
            'sqwts' => '授权委托书',
            'zrltys' => '植入类或Ⅲ级医用耗材知情同意书',
            'jjjctys' => '拒绝检查/治疗告知书',
            'zdcygzs' => '自动出院/转院风险告知书',
            'sjgzs' => '尸检告知书',
            'ybwffgzs' => '医保外付费项目知情同意书',
        ];

        foreach ($keywordMap as $templateKey => $keyword) {
            if ($this->containsNormalizedKeyword($blmc, $keyword) || $this->containsNormalizedKeyword($htmlPrint, $keyword)) {
                return $templateKey;
            }
        }

        return '';
    }

    protected function extractSanyuanConsentTemplateData($templateKey = '', $htmlPrint = '')
    {
        $xpath = $this->createHtmlXPath($htmlPrint);
        if (empty($xpath)) {
            return [];
        }

        $data = $this->extractSanyuanConsentCommonFields($xpath);

        if ($templateKey === 'tszl') {
            return array_merge($data, [
                'XMMC' => $this->getHtmlFieldTextByXPath($xpath, '特殊治疗项目名称', ['项目名称']),
                'XMMD' => $this->getHtmlFieldTextByXPath($xpath, '检查及治疗目的文本', ['检查及治疗目的文本']),
                'TDFA' => $this->extractTszlAlternativePlanByXPath($xpath),
            ]);
        }

        if ($templateKey === 'yzccstys') {
            return array_merge($data, [
                'XMMC' => $this->getHtmlFieldTextByXPath($xpath, '特殊治疗项目名称', ['项目名称']),
                'XMMD' => $this->getHtmlFieldTextByXPath($xpath, '检查及治疗目的文本', ['检查及治疗目的文本']),
                'TDFA' => $this->getHtmlFieldTextByXPath($xpath, '特殊检查及特殊治疗替代治疗方案文本', ['请输入替代治疗方案']),
            ]);
        }

        if ($templateKey === 'bdqp') {
            return array_merge($data, [
                'JCMD' => $this->getHtmlFieldTextByXPath($xpath, '检查及治疗目的文本', ['检查及治疗目的文本']),
            ]);
        }

        if ($templateKey === 'hltys') {
            return array_merge($data, [
                'TDFA' => $this->getFirstNonEmptyHtmlFieldTextByXPath($xpath, [
                    ['data_id' => '特殊检查及特殊治疗替代治疗方案文本', 'placeholders' => ['请输入替代治疗方案']],
                    ['data_id' => '特殊检查及特殊治疗替代治疗方案补充', 'placeholders' => ['替代治疗方案补充']],
                ]),
            ]);
        }

        if ($templateKey === 'sszqtys') {
            return array_merge($data, [
                'YSQMSJ' => $this->getFirstNonEmptyHtmlFieldTextByXPath($xpath, [
                    ['data_id' => '主刀医师签名时间', 'placeholders' => ['主刀医师签名时间']],
                    ['data_id' => '医师签名时间', 'placeholders' => ['医师签名时间']],
                ]),
                'HZQM' => $this->getFirstNonEmptyHtmlFieldValueByXPath($xpath, [
                    ['data_id' => '患者1签名', 'placeholders' => ['患者签名']],
                    ['data_id' => '患者签名', 'placeholders' => ['患者签名']],
                ]),
                'SFHZQM' => $this->htmlFieldHasContentByXPath($xpath, [
                    ['data_id' => '患者1签名', 'placeholders' => ['患者签名']],
                    ['data_id' => '患者1签名指纹'],
                    ['data_id' => '患者签名', 'placeholders' => ['患者签名']],
                    ['data_id' => '患者签名指纹'],
                ]) ? 1 : 0,
                'HZQMSJ' => $this->getFirstNonEmptyHtmlFieldTextByXPath($xpath, [
                    ['data_id' => '患者1签名时间', 'placeholders' => ['患者签名时间']],
                    ['data_id' => '患者签名时间', 'placeholders' => ['患者签名时间']],
                ]),
                'DLRQM' => $this->getFirstNonEmptyHtmlFieldValueByXPath($xpath, [
                    ['data_id' => '被授权人1签名', 'placeholders' => ['被授权人签名']],
                    ['data_id' => '被授权人签名', 'placeholders' => ['被授权人签名']],
                ]),
                'SFDLRQM' => $this->htmlFieldHasContentByXPath($xpath, [
                    ['data_id' => '被授权人1签名', 'placeholders' => ['被授权人签名']],
                    ['data_id' => '被授权人1签名指纹'],
                    ['data_id' => '被授权人签名', 'placeholders' => ['被授权人签名']],
                    ['data_id' => '被授权人签名指纹'],
                ]) ? 1 : 0,
                'DLRGX' => $this->getFirstNonEmptyHtmlFieldTextByXPath($xpath, [
                    ['data_id' => '被授权人1与患者关系', 'placeholders' => ['与患者关系']],
                    ['data_id' => '代理人与患者关系', 'placeholders' => ['与患者关系', '代理人与患者关系']],
                ]),
                'DLRQMSJ' => $this->getFirstNonEmptyHtmlFieldTextByXPath($xpath, [
                    ['data_id' => '被授权人1签名时间', 'placeholders' => ['被授权人签名时间']],
                    ['data_id' => '被授权人签名时间', 'placeholders' => ['被授权人签名时间']],
                ]),
                'SQZD' => $this->getHtmlFieldTextByXPath($xpath, '目前诊断', ['目前诊断']),
                'NSSSMC' => $this->getHtmlFieldTextByXPath($xpath, '拟手术名称', ['拟手术名称']),
                'TDFA' => $this->extractSszqtysAlternativePlanByXPath($xpath),
                'SSZQM' => $this->getHtmlFieldValueByXPath($xpath, '主刀医师签名', ['主刀医师签名']),
            ]);
        }

        if ($templateKey === 'sxzqtys') {
            return array_merge($data, [
                'MQZD' => $this->getHtmlFieldTextByXPath($xpath, '目前诊断', ['目前诊断']),
                'TDFA' => $this->extractSxzqtysAlternativePlanByXPath($xpath),
            ]);
        }

        if ($templateKey === 'ctzq') {
            return array_merge($data, [
                'TDFA' => $this->getHtmlFieldTextByXPath($xpath, '特殊检查及特殊治疗替代治疗方案文本', ['请输入替代治疗方案']),
            ]);
        }

        if ($templateKey === 'bwbztys') {
            return array_merge($data, [
                'MQZD' => $this->getHtmlFieldTextByXPath($xpath, '目前诊断', ['目前诊断']),
                'HFYJ' => $this->extractBwbztysOpinionByXPath($xpath),
            ]);
        }

        if ($templateKey === 'sqwts') {
            return array_merge($data, [
                'DLRXM1' => $this->getHtmlFieldTextByXPath($xpath, '代理人姓名1', ['姓名']),
                'DLRNL1' => $this->getHtmlFieldTextByXPath($xpath, '代理人年龄1', ['年龄']),
                'DLRGX1' => $this->getHtmlFieldTextByXPath($xpath, '代理人与患者关系1', ['关系', '代理人与患者关系1']),
                'DLRDH1' => $this->getHtmlFieldTextByXPath($xpath, '代理人电话1', ['电话']),
                'DLRXM2' => $this->getHtmlFieldTextByXPath($xpath, '代理人姓名2', ['姓名']),
                'DLRNL2' => $this->getHtmlFieldTextByXPath($xpath, '代理人年龄2', ['年龄']),
                'DLRGX2' => $this->getHtmlFieldTextByXPath($xpath, '代理人与患者关系2', ['关系', '代理人与患者关系2']),
                'DLRDH2' => $this->getHtmlFieldTextByXPath($xpath, '代理人电话2', ['电话']),
                'DLRQM2' => $this->getHtmlFieldValueByXPath($xpath, '被授权人1签名', ['被授权人签名']),
                'SFDLRQM2' => $this->htmlFieldHasContentByXPath($xpath, [
                    ['data_id' => '被授权人1签名', 'placeholders' => ['被授权人签名']],
                    ['data_id' => '被授权人1签名指纹'],
                ]) ? 1 : 0,
                'DLRQMSJ2' => $this->getHtmlFieldTextByXPath($xpath, '被授权人1签名时间', ['被授权人签名时间']),
            ]);
        }

        if ($templateKey === 'zrltys') {
            return array_merge($data, [
                'XMMC' => $this->extractZrltysMaterialNamesByXPath($xpath),
                'TDFA' => $this->getHtmlFieldTextByXPath($xpath, '其他知情医用替代耗材文本', ['请输入替代耗材名称']),
            ]);
        }

        if ($templateKey === 'jjjctys') {
            return array_merge($data, [
                'XMMC' => $this->getHtmlFieldTextByXPath($xpath, '谈话检查治疗项目名称', ['检查治疗项目名称']),
            ]);
        }

        if ($templateKey === 'zdcygzs') {
            return $data;
        }

        if ($templateKey === 'sjgzs') {
            return $data;
        }

        if ($templateKey === 'ybwffgzs') {
            return array_merge($data, [
                'XMJSON' => json_encode($this->extractYbwffgzsRowsByXPath($xpath), JSON_UNESCAPED_UNICODE),
            ]);
        }

        return $data;
    }

    protected function extractSanyuanConsentCommonFields($xpath = null)
    {
        return [
            'XM' => $this->getHtmlFieldTextByXPath($xpath, '页眉姓名', ['姓名']),
            'XB' => $this->getHtmlFieldTextByXPath($xpath, '页眉性别', ['性别', 'xb']),
            'NL' => $this->getHtmlFieldTextByXPath($xpath, '页眉年龄文本', ['年龄']),
            'KS' => $this->getHtmlFieldTextByXPath($xpath, '页眉科室名称', ['科室']),
            'BAH' => $this->getHtmlFieldTextByXPath($xpath, '页眉病案号', ['病案号']),
            'YSQM' => $this->getHtmlFieldValueByXPath($xpath, '医师签名', ['医师签名']),
            'YSQMSJ' => $this->getHtmlFieldTextByXPath($xpath, '医师签名时间', ['医师签名时间']),
            'HFYJ' => $this->getFirstNonEmptyHtmlFieldValueByXPath($xpath, [
                ['data_id' => '患方手写意见', 'placeholders' => ['患方手写意见']],
                ['data_id' => '患方手写意见1', 'placeholders' => ['患方手写意见1']],
            ]),
            'HZQM' => $this->getHtmlFieldValueByXPath($xpath, '患者签名', ['患者签名']),
            'SFHZQM' => $this->htmlFieldHasContentByXPath($xpath, [
                ['data_id' => '患者签名', 'placeholders' => ['患者签名']],
                ['data_id' => '患者签名指纹'],
            ]) ? 1 : 0,
            'HZQMSJ' => $this->getHtmlFieldTextByXPath($xpath, '患者签名时间', ['患者签名时间']),
            'DLRQM' => $this->getHtmlFieldValueByXPath($xpath, '被授权人签名', ['被授权人签名']),
            'SFDLRQM' => $this->htmlFieldHasContentByXPath($xpath, [
                ['data_id' => '被授权人签名', 'placeholders' => ['被授权人签名']],
                ['data_id' => '被授权人签名指纹'],
            ]) ? 1 : 0,
            'DLRGX' => $this->getHtmlFieldTextByXPath($xpath, '被授权人与患者关系', ['被授权人与患者关系']),
            'DLRQMSJ' => $this->getHtmlFieldTextByXPath($xpath, '被授权人签名时间', ['被授权人签名时间']),
        ];
    }

    protected function createHtmlXPath($htmlPrint = '')
    {
        if ($htmlPrint === '') {
            return null;
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $htmlPrint);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return null;
        }

        return new \DOMXPath($dom);
    }

    protected function getHtmlFieldNodeByXPath($xpath = null, $dataId = '')
    {
        if (empty($xpath) || $dataId === '') {
            return null;
        }

        $query = '//*[@data-id=' . $this->toXPathLiteral($dataId) . ']//*[contains(concat(" ", normalize-space(@class), " "), " tag-value ")]';
        $nodes = $xpath->query($query);

        if (empty($nodes) || $nodes->length === 0) {
            return null;
        }

        return $nodes->item(0);
    }

    protected function extractYbwffgzsRowsByXPath($xpath = null)
    {
        if (empty($xpath)) {
            return [];
        }

        $tableNodes = $xpath->query('//*[@data-id=' . $this->toXPathLiteral('谈话记录') . ']');
        if (empty($tableNodes) || $tableNodes->length === 0) {
            return [];
        }

        $rows = $xpath->query('.//tbody/tr[position()>2]', $tableNodes->item(0));
        if (empty($rows) || $rows->length === 0) {
            return [];
        }

        $data = [];
        foreach ($rows as $row) {
            $cells = $xpath->query('./td', $row);
            if (empty($cells) || $cells->length < 5) {
                continue;
            }

            $itemName = $this->extractHtmlCellValue($cells->item(0));
            $patientOpinion = $this->extractHtmlCellValue($cells->item(1));
            $patientSign = $this->extractHtmlCellValue($cells->item(2));
            $doctorSign = $this->extractHtmlCellValue($cells->item(3));
            $signTime = $this->extractHtmlCellValue($cells->item(4));

            if ($itemName === '' && $patientOpinion === '' && $patientSign === '' && $doctorSign === '' && $signTime === '') {
                continue;
            }

            $data[] = [
                'item_name' => $itemName,
                'patient_opinion' => $patientOpinion,
                'patient_sign' => $patientSign,
                'doctor_sign' => $doctorSign,
                'sign_time' => $signTime,
            ];
        }

        return $data;
    }

    protected function extractSxzqtysAlternativePlanByXPath($xpath = null)
    {
        if (empty($xpath)) {
            return '';
        }

        $tableNodes = $xpath->query('//*[@data-id=' . $this->toXPathLiteral('输血知情替代治疗方案') . ']');
        if (empty($tableNodes) || $tableNodes->length === 0) {
            return '';
        }

        $rows = $xpath->query('.//tbody/tr[position()>1]', $tableNodes->item(0));
        if (empty($rows) || $rows->length === 0) {
            return '';
        }

        $contents = [];
        foreach ($rows as $row) {
            $text = $this->normalizeHtmlFieldValue($row->textContent ?? '', []);
            if ($text !== '') {
                $contents[] = $text;
            }
        }

        return empty($contents) ? '' : implode("\n", $contents);
    }

    protected function extractSszqtysAlternativePlanByXPath($xpath = null)
    {
        if (empty($xpath)) {
            return '';
        }

        $fieldValue = $this->getHtmlFieldTextByXPath($xpath, '替代治疗方案文本', ['请输入替代治疗方案']);
        if ($fieldValue !== '') {
            return $fieldValue;
        }

        $articleIds = [
            '手术知情替代治疗方案',
            '手术风险',
        ];

        foreach ($articleIds as $articleId) {
            $nodes = $xpath->query('//*[@data-id=' . $this->toXPathLiteral($articleId) . ']');
            if (empty($nodes) || $nodes->length === 0) {
                continue;
            }

            foreach ($nodes as $node) {
                $text = $this->normalizeHtmlFieldValue($node->textContent ?? '', []);
                if ($text === '') {
                    continue;
                }

                if (!preg_match('/二、替代(?:医疗)?方案(.*?)(三、其他告知内容|$)/us', $text, $matches)) {
                    continue;
                }

                $sectionText = $this->normalizeHtmlFieldValue($matches[1] ?? '', ['请输入替代治疗方案']);
                $sectionText = preg_replace('/^请输入替代治疗方案/u', '', $sectionText);
                $sectionText = trim((string)$sectionText);

                if ($sectionText !== '') {
                    return $sectionText;
                }
            }
        }

        return '';
    }

    /**
     * 提取特殊检查/特殊治疗知情同意书的替代治疗方案。
     * 优先取替代治疗方案标签字段；若标签为空，兼容部分模板将替代方案写在正文（如风险防范措施）里的情况，
     * 从相关文章正文中正则匹配"替代（医疗）方案：xxx"内容。
     *
     * @param \DOMXPath|null $xpath
     * @return string
     */
    protected function extractTszlAlternativePlanByXPath($xpath = null)
    {
        if (empty($xpath)) {
            return '';
        }

        // 优先读取替代治疗方案专用字段
        $fieldValue = $this->getHtmlFieldTextByXPath($xpath, '特殊检查及特殊治疗替代治疗方案文本', ['请输入替代治疗方案']);
        if ($fieldValue !== '') {
            return $fieldValue;
        }

        // 标签为空时，从正文相关文章中兼容提取
        $articleIds = [
            '特殊检查及特殊治疗风险防范措施',
            '特殊检查及特殊治疗风险',
        ];

        foreach ($articleIds as $articleId) {
            $nodes = $xpath->query('//*[@data-id=' . $this->toXPathLiteral($articleId) . ']');
            if (empty($nodes) || $nodes->length === 0) {
                continue;
            }

            foreach ($nodes as $node) {
                $text = $this->normalizeHtmlFieldValue($node->textContent ?? '', []);
                if ($text === '') {
                    continue;
                }

                // 匹配"替代医疗方案：xxx"或"替代方案：xxx"，截止到下一个中文数字序号章节或文本结尾
                if (!preg_match('/替代(?:医疗)?方案[:：]\s*(.*?)(?:[一二三四五六七八九十]、|$)/us', $text, $matches)) {
                    continue;
                }

                $sectionText = $this->normalizeHtmlFieldValue($matches[1] ?? '', ['请输入替代治疗方案']);
                if ($sectionText !== '') {
                    return $sectionText;
                }
            }
        }

        return '';
    }

    protected function extractZrltysMaterialNamesByXPath($xpath = null)
    {
        if (empty($xpath)) {
            return '';
        }

        $tableNodes = $xpath->query('//*[@data-id=' . $this->toXPathLiteral('其他知情医用耗材名称') . ']');
        if (empty($tableNodes) || $tableNodes->length === 0) {
            return '';
        }

        $rows = $xpath->query('.//tbody/tr[position()>1]', $tableNodes->item(0));
        if (empty($rows) || $rows->length === 0) {
            return '';
        }

        $names = [];
        foreach ($rows as $row) {
            $cells = $xpath->query('./td', $row);
            if (empty($cells) || $cells->length === 0) {
                continue;
            }

            $name = $this->normalizeHtmlFieldValue($cells->item(0)->textContent ?? '', []);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        $names = array_values(array_unique($names));

        return empty($names) ? '' : implode('、', $names);
    }

    protected function getHtmlDataNodeByXPath($xpath = null, $dataId = '')
    {
        if (empty($xpath) || $dataId === '') {
            return null;
        }

        $query = '//*[@data-id=' . $this->toXPathLiteral($dataId) . ']';
        $nodes = $xpath->query($query);

        if (empty($nodes) || $nodes->length === 0) {
            return null;
        }

        return $nodes->item(0);
    }

    protected function getHtmlFieldTextByXPath($xpath = null, $dataId = '', $placeholders = [])
    {
        $node = $this->getHtmlFieldNodeByXPath($xpath, $dataId);
        if (empty($node)) {
            return '';
        }

        return $this->normalizeHtmlFieldValue($node->textContent ?? '', array_merge([$dataId], $placeholders));
    }

    protected function getHtmlFieldValueByXPath($xpath = null, $dataId = '', $placeholders = [])
    {
        $node = $this->getHtmlFieldNodeByXPath($xpath, $dataId);
        if (empty($node)) {
            return '';
        }

        $textValue = $this->normalizeHtmlFieldValue($node->textContent ?? '', array_merge([$dataId], $placeholders));
        if ($textValue !== '') {
            return $textValue;
        }

        return $this->extractImageMarkerFromHtml($this->getNodeInnerHtml($node));
    }

    protected function getFirstNonEmptyHtmlFieldTextByXPath($xpath = null, $fieldConfigs = [])
    {
        foreach ($fieldConfigs as $fieldConfig) {
            $dataId = is_array($fieldConfig) ? ($fieldConfig['data_id'] ?? '') : $fieldConfig;
            $placeholders = is_array($fieldConfig) ? ($fieldConfig['placeholders'] ?? []) : [];

            $value = $this->getHtmlFieldTextByXPath($xpath, $dataId, $placeholders);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    protected function getFirstNonEmptyHtmlFieldValueByXPath($xpath = null, $fieldConfigs = [])
    {
        foreach ($fieldConfigs as $fieldConfig) {
            $dataId = is_array($fieldConfig) ? ($fieldConfig['data_id'] ?? '') : $fieldConfig;
            $placeholders = is_array($fieldConfig) ? ($fieldConfig['placeholders'] ?? []) : [];

            $value = $this->getHtmlFieldValueByXPath($xpath, $dataId, $placeholders);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    protected function htmlFieldHasContentByXPath($xpath = null, $fieldConfigs = [])
    {
        foreach ($fieldConfigs as $fieldConfig) {
            $dataId = is_array($fieldConfig) ? ($fieldConfig['data_id'] ?? '') : $fieldConfig;
            $placeholders = is_array($fieldConfig) ? ($fieldConfig['placeholders'] ?? []) : [];
            $node = $this->getHtmlFieldNodeByXPath($xpath, $dataId);

            if (empty($node)) {
                continue;
            }

            $innerHtml = $this->getNodeInnerHtml($node);
            if ($innerHtml !== '' && preg_match('/background-image\s*:|<img\b|<svg\b/u', $innerHtml)) {
                return true;
            }

            if ($this->getHtmlFieldTextByXPath($xpath, $dataId, $placeholders) !== '') {
                return true;
            }
        }

        return false;
    }

    protected function extractHtmlCellValue($cell = null)
    {
        if (empty($cell)) {
            return '';
        }

        $textValue = $this->normalizeHtmlFieldValue($cell->textContent ?? '', []);
        if ($textValue !== '') {
            return $textValue;
        }

        return $this->extractImageMarkerFromHtml($this->getNodeInnerHtml($cell));
    }

    protected function isHtmlCheckboxCheckedByXPath($xpath = null, $dataId = '')
    {
        $node = $this->getHtmlDataNodeByXPath($xpath, $dataId);
        if (empty($node)) {
            return false;
        }

        $checkboxes = $xpath->query('.//input[@type="checkbox"]', $node);
        if (empty($checkboxes) || $checkboxes->length === 0) {
            return false;
        }

        foreach ($checkboxes as $checkbox) {
            if ($checkbox->hasAttribute('checked')) {
                return true;
            }
        }

        return false;
    }

    protected function extractBwbztysOpinionByXPath($xpath = null)
    {
        $optionMap = [
            '1' => '药物性治疗',
            '2' => '心脏按压',
            '3' => '呼吸机辅助呼吸',
            '4' => '电除颤',
            '5' => '气管切开',
            '6' => '临时起搏器',
            '8' => '其他救治措施',
        ];

        $checkedOptions = [];
        foreach ($optionMap as $dataId => $label) {
            if ($this->isHtmlCheckboxCheckedByXPath($xpath, $dataId)) {
                $checkedOptions[] = $label;
            }
        }

        return implode('、', $checkedOptions);
    }

    protected function containsNormalizedKeyword($text = '', $keyword = '')
    {
        if ($text === '' || $keyword === '') {
            return false;
        }

        $normalizedText = preg_replace('/[\p{Z}\s]+/u', '', (string)$text);
        $normalizedKeyword = preg_replace('/[\p{Z}\s]+/u', '', (string)$keyword);

        return strpos($normalizedText, $normalizedKeyword) !== false;
    }

    protected function getNodeInnerHtml($node = null)
    {
        if (empty($node) || empty($node->ownerDocument)) {
            return '';
        }

        $html = '';
        foreach ($node->childNodes as $childNode) {
            $html .= $node->ownerDocument->saveHTML($childNode);
        }

        return $html;
    }

    protected function extractImageMarkerFromHtml($html = '')
    {
        if ($html === '') {
            return '';
        }

        if (preg_match('/background-image\s*:\s*url\((["\']?)(.*?)\1\)/iu', $html, $matches)) {
            return $this->normalizeImageFieldValue($matches[2] ?? '');
        }

        if (preg_match('/<img\b[^>]*\bsrc=(["\'])(.*?)\1/iu', $html, $matches)) {
            return $this->normalizeImageFieldValue($matches[2] ?? '');
        }

        if (preg_match('/<svg\b/iu', $html)) {
            return '[图片]';
        }

        return '';
    }

    protected function normalizeImageFieldValue($imageValue = '')
    {
        $imageValue = html_entity_decode((string)$imageValue, ENT_QUOTES | ENT_HTML5);
        $imageValue = trim($imageValue, " \t\n\r\0\x0B\"'");

        if ($imageValue === '') {
            return '';
        }

        if (stripos($imageValue, 'data:image') === 0) {
            return '[图片]';
        }

        if (mb_strlen($imageValue) > 250) {
            return '[图片]';
        }

        return $imageValue;
    }

    protected function normalizeHtmlFieldValue($value = '', $placeholders = [])
    {
        $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5);
        $value = preg_replace('/[\x{00A0}\s]+/u', ' ', trim($value));
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        foreach ($placeholders as $placeholder) {
            if ($placeholder !== '' && $value === $placeholder) {
                return '';
            }
        }

        return $value;
    }

    protected function toXPathLiteral($value = '')
    {
        if (strpos($value, "'") === false) {
            return "'" . $value . "'";
        }

        if (strpos($value, '"') === false) {
            return '"' . $value . '"';
        }

        $parts = explode("'", $value);
        return "concat('" . implode("', \"'\", '", $parts) . "')";
    }


    /**
     * 获取病人病历数据
     *
     * @param [type] $zyh
     * @return array
     */
    public function getBlData($zyh = "", $mblb = "", $blbh = "")
    {
        $blData = EMR_BL_BL01::where('EMR_BL_BL01.JZHM', '=', $zyh)
            ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where(function ($query) use ($mblb) {
                if ((int)$mblb === 329) {
                    return $query->where('BLLB', 329);
                } elseif (!empty($mblb)) {
                    return $query->where('MBLB', $mblb);
                }
            })
            ->where(function ($query) use ($blbh) {
                if (!empty($blbh)) {
                    return $query->where('EMR_BL_BL01.BLBH', $blbh);
                }
            })
            ->get(["EMR_BL_BL01.*", "EMR_BL_BLXG.HJNR"])->toArray();
        return $blData ?? [];
    }

    public function formatBlDataItem($mblb)
    {
        if (empty($mblb)) {
            return [];
        }
        $ruleId = 0;
        //这里使用数组是因为后期可能有不同的出现，但是都是表示的为同一个意思
        if (in_array($mblb, [292])) {
            //入院记录
            // 姓名:,出生地:,性别:,职业:,年龄:,科室:,民族:,记录时间:,病史陈述者:,婚姻:,病案号:,入院时间:,主诉:,既往史:,现病史:,婚姻史:,个人史:,月经生育史:,家族史:,体格检查:,辅助检查:,初步诊断:,入院记录,专科检查:,床位号
            $ruleId = 1;
        } elseif (in_array($mblb, [1])) {
            //出院记录
            // 科室:,姓名:,住院号码:,床号:,入院日期:,性别:,出院日期:,年龄:,入院情况:,入院诊断:,诊疗经过:,出院情况:,出院诊断:,出院医嘱:,出院记录
            $ruleId = 2;
        } elseif (in_array($mblb, [295])) {
            //首次病程记录
            // 科室:,住院号码:,姓名:,性别:,年龄:,床号:,病例特点:,初步诊断:,诊断依据:,鉴别诊断:,诊疗计划:,首次病程记录
            $ruleId = 3;
        } elseif (in_array($mblb, [45])) {
            //输血记录
            // 科室:,住院号码:,姓名:,性别:,年龄:,床号:,于,至,给予,输血记录,病案号
            $ruleId = 4;
        } elseif (in_array($mblb, [306])) {
            //手术记录
            // 科室:,住院号码:,姓名:,性别:,年龄:,床号:,床位号:,手术记录
            $ruleId = 5;
        } elseif (in_array($mblb, [30301])) {
            //剖宫产记录
            // 科室:,住院号码:,姓名:,性别:,年龄:,床号:,床位号:,剖宫产记录
            $ruleId = 6;
        } /* elseif (in_array($mblb, [3069999])) {
            //分娩记录
            $ruleId = 7;
        } */

        return RuleConfig::getValueById($ruleId, true);
    }


    function getContent($ruleMapArr, $str, $field, $filter = 1)
    {
        $str = $this->normalizeExtractText($str);
        $field = $this->normalizeExtractText($field);

        // 是否过滤小大括号
        if ($filter == 1) {
            $str = str_replace(["{", "}"], "", $str);
        }

        // 1. 先找到指定字段的位置
        $start = mb_strpos($str, "{$field}");
        if ($start === false) {
            return "";
        }

        // 2. 起始位置（跳过字段名和冒号）
        $start += mb_strlen($field);

        // 3. 查找下一个分隔点
        $end = PHP_INT_MAX;


        foreach ($ruleMapArr as $k) {
            $tmp = explode("|", $k);
            $keyword = $tmp[1];
            $keyword = $this->normalizeExtractText($keyword);
            if ($keyword == $field) {
                continue;
            }

            // 如果字符串长度小于等于 $start 直接复制字符串长度 避免报错
            $pos = mb_strlen($str) <= $start ? mb_strlen($str) : mb_strpos($str, $keyword, $start);

            if ($pos !== false && $pos > $start && $pos < $end) {
                $end = $pos;
            } elseif ($pos !== false && $end == PHP_INT_MAX) {
                $end = $pos;
            }
        }

        // 4. 提取内容
        if ($end === PHP_INT_MAX) {
            $content = trim(mb_substr($str, $start));
        } else {
            $content = trim(mb_substr($str, $start, $end - $start));
        }

        foreach ($ruleMapArr as $k) {
            $tmp = explode("|", $k);
            $keyword = $tmp[1];
            $keyword = $this->normalizeExtractText($keyword);

            if ($keyword == $field) {
                continue;
            }
            if (mb_strpos($content, $keyword) !== false) {
                // 如果内容中包含其他字段名，截断到该字段名之前
                $cutPos = mb_strpos($content, $keyword);
                $content = trim(mb_substr($content, 0, $cutPos));
                break;
            }
        }


        return $content;
    }

    function getFirstDiagnosis($str)
    {
        $str = $this->normalizeExtractText($str);

        // 方法1：使用正则表达式
        if (preg_match('/1\.(.*?)(?=2\.|$)/', $str, $matches)) {
            return trim($matches[1]);
        }

        return "";
    }

    /**
     * @param string $name
     * @param array $ruleWord
     * @param string $hjnr
     * @param string $filter
     * @return array
     * 根据配置信息完成数据清洗
     */
    public function cleanDataFilter($name = "", $ruleWord = [], $hjnr = "", $filter = 1)
    {
        // 获取后台设置的配置信息
        $keyword = RuleWordMap::query()->where("name", $name)->value("keyword");
        $keyword = explode(",", $keyword);
        //由于各个医院针对要清洗的内容格式不一样，所以需要把数据清洗的字段信息维护在各个项目的数据表中维护
        //三院格式如下：CWH|床位号,XM|姓名,XB|性别,NL|年龄,MZ|民族,BLTD|病例特点,CBZD|初步诊断,ZDYJ|诊断依据,JBZD|鉴别诊断,ZLJH|诊疗计划
        //滨医格式如下：CWH|床位号,XM|姓名,XB|性别,NL|年龄,MZ|民族,BLTD|一、病例特点,CBZD|二、初步诊断,ZDYJ|三、诊断依据,JBZD|四、鉴别诊断,ZLJH|四、诊疗计划
        $data = [];
        foreach ($keyword as $k) {
            $tmp = explode("|", $k);
            if (empty($tmp[0]) || !empty($data[$tmp[0]])) {
                continue;
            }
            $res = $this->getContent($keyword, $hjnr, $tmp[1], $filter);
            $data[$tmp[0]] = $res;
        }

        return $data;
    }

    function getSxTime($timeStr = '', $BLMC = '')
    {
        $timeStr = str_replace(["{", "}", "[", "]", "分"], "", $timeStr);
        $timeStr = str_replace("时", ":", $timeStr);
        if (preg_match("/\d{4}-\d{2}-\d{2}\d{2}:\d{2}/", $timeStr, $matches2) && !empty($matches2)) {
            //格式一: 年-月-日 时:分
            $timeStr = date('Y-m-d H:i:00', strtotime($matches2[0]));
        } elseif (preg_match("/\d{4}-\d{2}-\d{2}\d{2}:\d{2}:\d{2}/", $timeStr, $matches2) && !empty($matches2)) {
            //获取BLMC里面的年份
            $year = substr($BLMC, 0, 4);
            //格式二: 月-日 时:分
            $timeStr = date('Y-m-d H:i:00', strtotime($year . "-" . $matches2[0]));
        } elseif (preg_match("/\d{2}:\d{2}/", $timeStr, $matches2) && !empty($matches2[0])) {
            //获取BLMC里面的年月日
            $year = substr($BLMC, 0, 10);
            //格式三: 时:分
            $timeStr = date('Y-m-d H:i:00', strtotime($year . " " . $matches2[0]));
        }
        return $timeStr;
    }

    /**
     * 格式化时间
     * @param string $timeStr
     * @return string 格式化后的时间
     */
    function formatTime($timeStr = '')
    {
        if (empty($timeStr)) {
            return "";
        }
        if (strtotime($timeStr) === false) {
            return "";
        }

        $timeStr = str_replace(["年", "月"], "-", $timeStr);
        $timeStr = str_replace(["时", "分"], ":", $timeStr);
        $timeStr = str_replace("日", "", $timeStr);
        $timeStr = str_replace("秒", "", $timeStr);
        $timeStr = substr($timeStr, 0, 10) . ' ' . substr($timeStr, 10);
        $timeStr = date('Y-m-d H:i:s', strtotime($timeStr));
        return $timeStr;
    }

    /**
     * 格式化方程病历1
     * @param string $hjnr
     * @return array
     */
    public function formatFangchengBllb1($hjnr = "")
    {
        $data = [];
        $pattern = '/\s{4,5}/u'; // \s匹配所有空白字符，　匹配全角空格，+表示一个或多个，u表示Unicode模式
        $result = preg_split($pattern, $hjnr, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($result[0])) {
            return $data;
        }
        preg_match('/^(.*)病案号码(.*)科室名称(.*)病人性别(.*)病人姓名([^\d]+)/', str_replace([' ', "\t", "\n", "\r\n", ' n ', ' n'], '', $result[0]), $match1);

        $data['CBZD'] = $match1[1] ?? '';
        $data['RYZD'] = $match1[1] ?? '';
        $data['AAA28'] = $match1[2] ?? '';
        $data['BRKS'] = $match1[3] ?? '';
        $data['KS'] = $match1[3] ?? '';
        $data['XB'] = $match1[4] ?? '';
        $data['XM'] = $match1[5] ?? '';
        $data['CYYZ'] = $result[1] ?? '';
        $data['CYQK'] = $result[2] ?? '';
        if (!empty($result[3])) {
            preg_match('/^(.*)住院天数(.*)医院名称(.*)出院日期\(分\)(.*)入院日期\(分\)(.*)病人年龄(.*)/', str_replace([' ', "\t", "\n", "\r\n", ' n ', ' n'], '', $result[3]), $match);
            $data['ZLJG'] = $match[1] ?? '';
            $data['ZYTS'] = $match[2] ?? '';
            $data['CYRQ'] = $this->formatTime($match[4]);
            $data['RYRQ'] = $this->formatTime($match[5]);
            $data['NL_STR'] = $match[6] ?? '';
            $data['NL'] = intval($match[6] ?? 0);
        }
        $data['RYQK'] = $result[4] ?? '';
        $data['CYZD'] = $result[5] ?? '';

        return $data;
    }

    /**
     * 格式化方程病历303
     * @param string $hjnr
     * @return array
     */
    public function formatFangchengBllb303($hjnr = "")
    {
        $bllb303 = [];
        // 从原始文本中使用正则提取各字段
        $str_no_space = str_replace([' ', "\t", "\n", "\r\n", '　'], '', $hjnr);
        preg_match('/疾病诊断(.*)手术经过/u', $str_no_space, $sqzdMatch);
        if (empty($sqzdMatch[1])) {
            preg_match('/1\.([^\s]+)1\.([^\s]+)病人性别/', $str_no_space, $sqzdMatch);
        }
        $bllb303['SQZD'] = $sqzdMatch[1] ?? '';

        if (!empty($sqzdMatch[2])) {
            $pattern = '/\d\./u';
            $result = preg_split($pattern, $sqzdMatch[2], -1, PREG_SPLIT_NO_EMPTY);
            $bllb303['SZZD'] = $sqzdMatch[2];
            $bllb303['SZZD_ONE'] = $result[0];
        } else {
            preg_match('/术中诊断(.*)手术时间/u', $str_no_space, $sexMatch);
            $bllb303['SSZD'] = $sexMatch[1] ?? '';
        }
        preg_match('/手术指导者(.*)手术者/u', $str_no_space, $sexMatch);
        $bllb303['SSZD'] = $sexMatch[1] ?? '';

        preg_match('/手术经过(.*)病人性别/u', $str_no_space, $sexMatch);
        $bllb303['SSJG'] = $sexMatch[1] ?? '';

        // 病人性别
        preg_match('/病人性别(.*)病人姓名/', $str_no_space, $sexMatch);
        $bllb303['XB'] = $sexMatch[1] ?? '';
        // 科室名称
        preg_match('/科室名称(.*)病人年龄/u', $str_no_space, $sexMatch);
        $bllb303['BRKS'] = $sexMatch[1] ?? '';

        // 病人姓名
        preg_match('/病人姓名(.*)(病案号码|日期时间)/u', $str_no_space, $nameMatch);
        $bllb303['XM'] = $nameMatch[1] ?? '';

        // 手术助手11
        preg_match('/手术助手11(.*)手术医生1/u', $str_no_space, $sszs11Match);
        $bllb303['ZS'] = $sszs11Match[1] ?? '';
        if (empty($bllb303['ZS'])) {
            preg_match('/手术助手1(.*)/u', $str_no_space, $sszs11Match);
            $bllb303['ZS'] = $sszs11Match[1] ?? '';
        }


        // 手术助手12（可能为空或无，备用字段）
        preg_match('/手术助手12([\x{4e00}-\x{9fa5}\-]+)/u', $str_no_space, $sszs12Match);
        $bllb303['ERZHU'] = $sszs12Match[1] ?? '';
        if (empty($bllb303['ERZHU'])) {
            preg_match('/手术助手2(.*)手术助手1/u', $str_no_space, $sszs2Match);
            $bllb303['ERZHU'] = $sszs2Match[1] ?? '';
        }

        // 手术医生1
        preg_match('/手术医生1(.*)手术麻醉名称1/u', $str_no_space, $ssys1Match);
        $bllb303['SSZ'] = $ssys1Match[1] ?? '';
        if (empty($bllb303['SSZ'])) {
            preg_match('/手术者(.*)手术名称/u', $str_no_space, $ssys1Match);
            $bllb303['SSZ'] = $ssys1Match[1] ?? '';
        }

        // 手术麻醉名称1
        preg_match('/手术麻醉名称1([\x{4e00}-\x{9fa5}]+)手术名称/u', $str_no_space, $mzmcMatch);
        $bllb303['MZFS'] = $mzmcMatch[1] ?? '';
        if (empty($bllb303['MZFS'])) {
            preg_match('/麻醉方法(.*)手术助手2/u', $str_no_space, $mzmcMatch);
            $bllb303['MZFS'] = $mzmcMatch[1] ?? '';
        }

        // 手术名称1
        preg_match('/手术名称1([^\s]+?术)/u', $str_no_space, $ssmcMatch);
        $bllb303['SSMC'] = $ssmcMatch[1] ?? '';
        if (empty($bllb303['SSMC'])) {
            preg_match('/手术名称(.*)术中诊断/u', $str_no_space, $ssmcMatch);
            $bllb303['SSMC'] = $ssmcMatch[1] ?? '';
        }
        $bllb303['SSMC_ONE'] = $ssmcMatch[1] ?? '';

        // 病人年龄
        preg_match('/病人年龄(\d+)岁/', $str_no_space, $ageMatch);
        $bllb303['NL'] = $ageMatch[1] ?? '';

        // 手术日期(1)
        preg_match('/手术日期1(\d{4}[-\.]\d{2}[-\.]\d{2})/', $str_no_space, $ssrqMatch);
        if (!empty($ssrqMatch[1])) {
            $bllb303['SSRQ'] = $ssrqMatch[1];
        } else {
            preg_match('/日期时间(\d{4}[-\.]\d{2}[-\.]\d{2})/u', $str_no_space, $ssrqMatch);
            $bllb303['SSRQ'] = $ssrqMatch[1] ?? '';
        }

        // 手术开始/结束时间 例如09:26-10:26，均出现在文本中
        preg_match('/(\d{2}:\d{2})-(\d{2}:\d{2})/', $hjnr, $timeMatch);
        if (!empty($timeMatch)) {
            // 拼接手术日期+时间
            $baseDate = $bllb303['SSRQ'] ?? '';
            if ($baseDate != '') {
                $bllb303['SSKSSJ'] = $baseDate . ' ' . $timeMatch[1] . ':00';
                $bllb303['SSJSSJ'] = $baseDate . ' ' . $timeMatch[2] . ':00';
            } else {
                $bllb303['SSKSSJ'] = $timeMatch[1];
                $bllb303['SSJSSJ'] = $timeMatch[2];
            }
        } else {
            $bllb303['SSKSSJ'] = '';
            $bllb303['SSJSSJ'] = '';
        }

        return $bllb303;
    }
}
