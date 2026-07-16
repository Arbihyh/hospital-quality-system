<?php

namespace App\Services;

use App\Model\Bllb303;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\FeeDetailed;
use App\Model\GY_SSML;
use App\Model\IndexCatalog;
use App\Model\Indicator;
use App\Model\MainOperation;
use App\Model\MedicinalInfo;
use App\Model\OperationInfo;
use App\Model\PACS;
use App\Model\Mzjl;
use App\Model\PatientDoctorInfo;
use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Model\PatientInfoTargetNew;
use App\Model\Pszb;
use App\Model\RuleWordMap;
use App\Model\RYQX;
use App\Model\Setting;
use App\Model\SM_SSAP;
use App\Model\SSSQ;
use App\Model\Staff;
use App\Model\Yzb;
use Carbon\Carbon;
use Exception;
use App\Model\PatientMedicalInfo;
use Illuminate\Support\Facades\DB;
use App\Model\EMR_BL_BLSY;
use App\Model\SecondaryOperation;
use App\Model\ZbBagl;
use App\Model\ZY_BRRY;
use App\Services\ElasticsearchService;

/**
 * 比例数据
 */
class RadioServiceV3
{
    // 手术判别是手术和介入治疗
    public const SSPB14 = [1, 4];
    public $startTime = '2023-05-01 00:00:00';
    public $endTime = '2023-06-01 00:00:00';

    public function __construct()
    {
        $this->startTime = time() - 24 * 3600 * 30;
    }

    public function cacheData()
    {
        // 病理
        $this->bingliData();
        // 抗菌药物检查
        $this->kjywData();
        // 化疗药物检查
        $this->exzlhxzlData();
        // 医嘱名称“放疗”关键字检查
        $this->exzlfszlData();
        // 不合理复制病历信息检查
        $this->buheliCopy();
        // 手术相关记录完整率
        $this->operationComplete();
        // 手术记录24小时内完成率（MER-TL-02）
        $this->operateCompletionRate();
        // 医师查房记录完整率
        $this->chafangCompletionRate();

        // 抢救记录及时记录率
        $this->qjjljll();

        // 抢救记录审核率
        $this->qjjlshl();

        // 首次病程记录8小时内完成率
        $this->bl8wcl();

        // 入院记录在患者入院 24 小时内完成的住院患者病历数
        $this->bl24wcl();

        // 死亡讨论及时完成率
        $this->swjlwcl();

        // 术前讨论完成率
        $this->sqtlwcl();

        // 手术记录24小时内完成率
        $this->ssjlwcl24();

        // 术后首次病程即刻完成率
        $this->shscbcjkwcl();

        // 抗菌药物处方权落实合格率
        $this->kjywcfqlshg();

        // 临床用血前评估记录率
        $this->lcyxqpgjl();

        // 特殊级抗菌药物会诊率
        $this->tsjkjywhz();

        // 超越权限的抗菌药物药物处方时限合格率
        $this->cyqxdkjywy();

        // 术前讨论人员规范参与率
        $this->sqtlrygfcyl();

        // 四级手术术前多学科讨论率
        $this->sjsssqdxktl();

        // 手术医嘱规范开具率
        $this->ssyzgfkjl();

        // 术者符合授权目录一致率
        $this->szfhsqmlyzl();

        // 四级手术并发症发生率比
        $this->sjssbfzfsl();

        // 四三级手术并发症发生率比
        $this->sanjssbfzfsl();
    }

    public function sanjssbfzfsl($zyh = "", $startTime = "", $end_time = "")
    {
        IndexCatalog::editStatus("sanjssbfzfsl", 1);

        $end = $end_time ?: time() + 24 * 3600;
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > $end) {
                break;
            }
            $query = PatientInfo::query();
            if ($zyh) {
                $query = $query->where('MED_REC_ID', '=', $zyh);
            } else {
                $query = $query
                    ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                    ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime));
            }
            $patientInfo = $query->get(['MED_REC_ID', "AAC01", 'AAB01'])->toArray();
            $startTime = $entTime;

            $sql = ' (ICD10_ID1 like "T80%" OR ICD10_ID1 like "T81%" OR ICD10_ID1 like "T82%" OR ICD10_ID1 like "T83%" OR ICD10_ID1 like "T84%")';

            $qualityRes = [];
            foreach ($patientInfo as $item) {

                $fenzi = ['ZYH' => $item['MED_REC_ID'], 'fenzi' => 0, 'fenmu' => 0, "content" => []];
                /**
                 * 获取需排查的T80-T84
                 */
                // 查询四级手术（使用病案首页数据）
                $four_results = DB::select("SELECT AAA28,OPE_MAN_NAME,ICD9_NAME,MAX(ICD9_ID1) as ICD9_ID1  FROM ( SELECT AAA28,OPE_MAN_NAME,ICD9_NAME,ICD9_ID1 FROM main_operation WHERE ope_level = 3 UNION ALL SELECT AAA28,OPE_MAN_NAME,ICD9_NAME,ICD9_ID1 FROM secondary_operation WHERE ope_level = 3 ) operation WHERE AAA28 = :id", ['id' => data_get($item, 'MED_REC_ID')]);

                if ($four_results) {
                    $fenzi['fenmu'] += 1;
                    $error[] = ["status" => 1, "content" => "四级手术名称【{$four_results[0]->ICD9_NAME}"];

                    $four_count = DB::select("SELECT ICD10_NAME,ICD10_ID1 FROM (SELECT AAA28,ICD10_ID1,ICD10_NAME FROM main_diagnosis where " . $sql . " UNION ALL SELECT AAA28,ICD10_ID1,ICD10_NAME FROM other_diagnosis where " . $sql . ") diagnosis WHERE AAA28 = :id", ['id' => data_get($item, 'MED_REC_ID')]);
                    echo '四级手术并发症信息：' . count($four_count) . PHP_EOL;

                    if ($four_count) {
                        $error[] = ["status" => 1, "content" => "诊断名称【{$four_count[0]->ICD10_NAME}】诊断编码【{$four_count[0]->ICD10_ID1}】"];
                        $fenzi['fenzi'] += 1;
                    } else {
                        $error[] = ["status" => 0, "content" => "四级手术并发症信息不存在"];
                    }
                    $fenzi[0]['content'] = $error;
                }

                $qualityRes[] = $fenzi;
            }

            if ($qualityRes) {
                $this->saveIndexRes($qualityRes, "sanjssbfzfsl");
            }
        }
        IndexCatalog::query()->where("index_name", "=", "sanjssbfzfsl")->update(["status" => 2, 'quality_time' => time()]);
    }

    public function sjssbfzfsl($zyh = "", $startTime = "", $end_time = "")
    {
        IndexCatalog::editStatus("sjssbfzfsl", 1);

        $end = $end_time ?: time() + 24 * 3600;
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > $end) {
                break;
            }
            $query = PatientInfo::query();
            if ($zyh) {
                $query = $query->where('MED_REC_ID', '=', $zyh);
            } else {
                $query = $query
                    ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                    ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime));
            }
            $patientInfo = $query->get(['MED_REC_ID', "AAC01", 'AAB01'])->toArray();
            $startTime = $entTime;

            $sql = ' (ICD10_ID1 like "T80%" OR ICD10_ID1 like "T81%" OR ICD10_ID1 like "T82%" OR ICD10_ID1 like "T83%" OR ICD10_ID1 like "T84%")';

            $qualityRes = [];
            foreach ($patientInfo as $item) {

                $fenzi = ['ZYH' => $item['MED_REC_ID'], 'fenzi' => 0, 'fenmu' => 0, "content" => []];
                /**
                 * 获取需排查的T80-T84
                 */
                // 查询四级手术（使用病案首页数据）
                $four_results = DB::select("SELECT AAA28,OPE_MAN_NAME,ICD9_NAME,MAX(ICD9_ID1) as ICD9_ID1  FROM ( SELECT AAA28,OPE_MAN_NAME,ICD9_NAME,ICD9_ID1 FROM main_operation WHERE ope_level = 4 UNION ALL SELECT AAA28,OPE_MAN_NAME,ICD9_NAME,ICD9_ID1 FROM secondary_operation WHERE ope_level = 4 ) operation WHERE AAA28 = :id", ['id' => data_get($item, 'MED_REC_ID')]);

                if ($four_results) {
                    $fenzi['fenmu'] += 1;
                    $error[] = ["status" => 1, "content" => "四级手术名称【{$four_results[0]->ICD9_NAME}"];

                    $four_count = DB::select("SELECT ICD10_NAME,ICD10_ID1 FROM (SELECT AAA28,ICD10_ID1,ICD10_NAME FROM main_diagnosis where " . $sql . " UNION ALL SELECT AAA28,ICD10_ID1,ICD10_NAME FROM other_diagnosis where " . $sql . ") diagnosis WHERE AAA28 = :id", ['id' => data_get($item, 'MED_REC_ID')]);
                    echo '四级手术并发症信息：' . count($four_count) . PHP_EOL;

                    if ($four_count) {
                        $error[] = ["status" => 1, "content" => "诊断名称【{$four_count[0]->ICD10_NAME}】诊断编码【{$four_count[0]->ICD10_ID1}】"];
                        $fenzi['fenzi'] += 1;
                    } else {
                        $error[] = ["status" => 0, "content" => "四级手术并发症信息不存在"];
                    }
                    $fenzi[0]['content'] = $error;
                }

                $qualityRes[] = $fenzi;
            }

            if ($qualityRes) {
                $this->saveIndexRes($qualityRes, "sjssbfzfsl");
            }
        }
        IndexCatalog::query()->where("index_name", "=", "sjssbfzfsl")->update(["status" => 2, 'quality_time' => time()]);
    }

    public function szfhsqmlyzl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("szfhsqmlyzl", 1);
        $ruleMap2012 = RuleWordMap::query()->where("id", "=", 2012)->first()->toArray();
        $ruleMap2013 = RuleWordMap::query()->where("id", "=", 2013)->first()->toArray();
        $ruleMap2014 = RuleWordMap::query()->where("id", "=", 2014)->first()->toArray();
        $ruleMap2015 = RuleWordMap::query()->where("id", "=", 2015)->first()->toArray();
        $ruleMap2017 = RuleWordMap::query()->where("id", "=", 2017)->first()->toArray();
        $ruleMap2001 = RuleWordMap::query()->where("id", "=", 2001)->first()->toArray();
        $ruleMap2011 = RuleWordMap::query()->where("id", "=", 2011)->first()->toArray();
        $ruleMap1053 = RuleWordMap::query()->where("id", "=", 1053)->first()->toArray();
        $ruleMap2036 = RuleWordMap::query()->where("id", "=", 2036)->first()->toArray();

        $startTime = $start_time ?: $this->startTime;
        $end = $end_time ?: time() + 24 * 3600;
        $qualityRes = [];
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > $end) {
                break;
            }
            $query = PatientInfo::query();
            if ($zyh) {
                $query = $query->where('MED_REC_ID', '=', $zyh);
            } else {
                $query = $query
                    ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                    ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime));
            }
            $patientInfo = $query->get(['MED_REC_ID', "AAC01", 'AAB01'])->toArray();
            $startTime = $entTime;

            foreach ($patientInfo as $p) {
                echo $p['MED_REC_ID'] . "\r\n";
                $keyword = explode(',', $ruleMap2011['keyword']);
                $results = SM_SSAP::query()->where('ZYH', '=', $p['MED_REC_ID'])->whereIn("ICD9_SSLB", $keyword)->get()->toArray();
                if (!$results) {
                    continue;
                }

                $fenzi = ['ZYH' => $p['MED_REC_ID'], 'fenzi' => 0, 'fenmu' => count($results), "content" => []];
                if (!$results) {
                    continue;
                } else {
                    foreach ($results as $r) {
                        $ssrq = substr($r['SSRQ'], 0, 10);
                        $bl01 = EMR_BL_BL01::query()
                            ->where("JZHM", "=", $p['MED_REC_ID'])
                            ->where("MBLB", "=", $ruleMap1053['keyword'])
                            ->where("BLZT", "<>", $ruleMap2036['keyword'])
                            ->get(["BLBH", "BLMC", "MBLB", 'BLZT'])->toArray();
                        if (!$bl01) {
                            continue;
                        }

                        foreach ($bl01 as $k => $v) {
                            $flag = 0;
                            $error = [];
                            $blxg = EMR_BL_BLXG::query()->where("BLBH", '=', $v["BLBH"])->get()->toArray();
                            if (!$blxg) {
                                continue;
                            }

                            $HJNR = $blxg[0]['HJNR'];
                            if (strpos($HJNR, $ssrq) == false) {
                                continue;
                            }
                            $HJNR = str_replace("：", ":", $HJNR);
                            $error[] = ["status" => 1, "手术名称【{$r["ICD9_SSCZMC"]}】"];
                            $error[] = ["status" => 1, "手术类型【{$r["ICD9_SSLB"]}】"];
                            $error[] = ["status" => 1, "手术记录【{$v["BLMC"]}】"];

                            // 获取术者
                            preg_match_all('/手术者\{(.*?)}/', $HJNR, $shuzhe);
                            $HJNR = str_replace(["{", "}", "(", ")"], "", $HJNR);
                            if (empty($shuzhe[1])) {
                                $error[] = ["status" => 0, "术者【无】"];
                            } else {
                                $error[] = ["status" => 1, "术者【{$shuzhe[1][0]}】"];


                                // 手术开始时间
                                preg_match_all('/' . $ruleMap2012['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2013['keyword'] . '/', $HJNR, $matches);
                                preg_match_all('/' . $ruleMap2014['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2015['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*\-[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2017['keyword'] . '/', $HJNR, $matches1);
                                $opeStartTime = '';
                                if (empty($matches[0]) && empty($matches1[0])) {
                                    $error[] = ["status" => 0, "手术开始时间【无】"];
                                } else {
                                    $opeStartTime = $matches[0] ? $matches[1][0] : ($matches1[1][0] . ' ' . $matches1[2][0]);
                                    $opeStartTime = str_replace('年', '-', str_replace('月', '-', str_replace('日', ' ', str_replace('时', ':', str_replace('分', '', $opeStartTime)))));
                                    $error[] = ["status" => 1, "content" => "手术开始时间【{$opeStartTime}】"];
                                }
                                if (strpos($opeStartTime, $ssrq) === false) {
                                    continue;
                                }

                                $staff = Staff::query()->where("name", "=", $shuzhe[1][0])->get()->toArray();
                                if (!empty($staff)) {
                                    $code = $staff[0]["code"];
                                    $ssml = GY_SSML::query()->where("YY_SSCZBM", '=', $r['ICD9_SSCZBM'])->get()->toArray();
                                    if ($ssml && strpos($ssml[0]["code"], $code) !== false) {   
                                        $flag = 1;
                                        $fenzi["fenzi"] += 1;
                                        $error[] = ["status" => 1, "授权的手术【{$ssml[0]["KSMC"]}+{$ssml[0]["YY_SSJBBM"]}+{$ssml[0]["YY_SSXH"]}】"];
                                    } else {
                                        $error[] = ["status" => 0, "授权的手术【无】"];
                                    }
                                }
                                break;
                            }
                        }

                        $errorContent['status'] = $flag;
                        $errorContent['content'] = $error;
                        $fenzi["content"][] = $errorContent;
                    }
                }
                $qualityRes[] = $fenzi;
            }

            // 指定住院号计算指标，则退出下次循环
            if ($zyh) {
                break;
            }
        }

        if ($qualityRes) {
            $this->saveIndexRes($qualityRes, "szfhsqmlyzl");
        }

        IndexCatalog::query()->where("index_name", "=", "szfhsqmlyzl")->update(["status" => 2, 'quality_time' => time()]);
    }


    public function ssyzgfkjl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("ssyzgfkjl", 1);
        $ruleMap2001 = RuleWordMap::query()->where("id", "=", 2001)->first()->toArray();
        $ruleMap2011 = RuleWordMap::query()->where("id", "=", 2011)->first()->toArray();
        $ruleMap1046 = RuleWordMap::query()->where("id", "=", 1046)->first()->toArray();
        $ruleMap2036 = RuleWordMap::query()->where("id", "=", 2036)->first()->toArray();
        $ruleMap2035 = RuleWordMap::query()->where("id", "=", 2035)->first()->toArray();

        $startTime = $start_time ?: $this->startTime;
        $end = $end_time ?: time() + 24 * 3600;
        $qualityRes = [];
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > $end) {
                break;
            }
            $query = PatientInfo::query();
            if ($zyh) {
                $query = $query->where('MED_REC_ID', '=', $zyh);
            } else {
                $query = $query
                    ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                    ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime));
            }
            $patientInfo = $query->get(['MED_REC_ID', "AAC01", 'AAB01'])->toArray();
            $startTime = $entTime;

            foreach ($patientInfo as $p) {
                echo $p['MED_REC_ID'] . "\r\n";
                $keyword = explode(',', $ruleMap2011['keyword']);
                $results = SM_SSAP::query()->where('ZYH', '=', $p['MED_REC_ID'])->whereIn("ICD9_SSLB", $keyword)->get()->toArray();
                if (!$results) {
                    continue;
                }

                $fenzi = ['ZYH' => $p['MED_REC_ID'], 'fenzi' => 0, 'fenmu' => count($results), "content" => []];
                if (!$results) {
                    continue;
                } else {

                    $yzb = Yzb::query()->where("YDYZLB", "=", 204)
                        ->where("ZYH", '=', $p['MED_REC_ID'])
                        ->get(["YZMC", "KZSJ", "YZZT"])->toArray();

                    $index = 0;
                    $preKzsj = '';
                    foreach ($results as $r) {
                        $ssrq = substr($r['SSRQ'], 0, 10);
                        echo '手术日期：' . $ssrq . PHP_EOL;
                        echo '手术名称：' . $r['ICD9_SSCZMC'] . PHP_EOL;
                        // 结果为 2025-03-20}
                        foreach ($yzb as $k => $v) {
                            $flag = 0;
                            if ($v['YZZT'] == $ruleMap2035['keyword']) {
                                continue;
                            }

                            // 从字符串中提取年月日信息，并整理成2025-01-01格式
                            $text = $v['YZMC'];
                            echo '医嘱名称：' . $text . PHP_EOL;
                            $dateStr = '';
                            if (preg_match('/(\d{4})年(\d{2})月(\d{2})日/', $text, $matches)) {
                                $year = $matches[1];
                                $month = $matches[2];
                                $day = $matches[3];
                                $dateStr = "{$year}-{$month}-{$day}";
                            }
                            if (strpos($ssrq, $dateStr) === false) {
                                continue;
                            }
                            $error = [];
                            $error[] = ["status" => 1, "医嘱名称【{$v['YZMC']}】"];
                            $error[] = ["status" => 1, "开嘱时间【{$v['KZSJ']}】"];

                            $query = EMR_BL_BL01::query()
                                ->where("JZHM", "=", $p['MED_REC_ID'])
                                ->where("MBLB", "=", $ruleMap1046['keyword'])
                                ->where("BLZT", "<>", $ruleMap2036['keyword'])
                                ->where($ruleMap2001['keyword'], "<", $v['KZSJ']);

                            // 如果是不是第一次手术则需要查找上次开嘱时间和本次开嘱时间之间的术前讨论结论记录
                            if ($index > 0) {
                                $query->where($ruleMap2001['keyword'], ">", $preKzsj);
                            }
                            $preKzsj = $v['KZSJ'];
                            $index++;

                            $bl01 = $query->get(["BLBH", "BLMC", "MBLB", 'BLZT', $ruleMap2001['keyword']])->toArray();
                            echo '术前讨论结论记录：' . count($bl01) . PHP_EOL;
                            if ($bl01) {
                                $flag = 1;
                                $fenzi['fenzi'] += 1;
                                $error[] = ["status" => 1, "术前讨论结论记录【{$bl01[0]['BLMC']}】"];
                                $error[] = ["status" => 1, "首次完成时间【{$bl01[0][$ruleMap2001['keyword']]}】"];
                            } else {
                                $error[] = ["status" => 0, "术前讨论结论记录【无】"];
                            }

                            $errorContent['status'] = $flag;
                            $errorContent['content'] = $error;
                            $fenzi["content"][] = $errorContent;
                        }
                    }
                }
                $qualityRes[] = $fenzi;
            }

            // 指定住院号计算指标，则退出下次循环
            if ($zyh) {
                break;
            }
        }

        if ($qualityRes) {
            $this->saveIndexRes($qualityRes, "ssyzgfkjl");
        }
        IndexCatalog::query()->where("index_name", "=", "ssyzgfkjl")->update(["status" => 2, 'quality_time' => time()]);
    }

    public function sjsssqdxktl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("sjsssqdxktl", 1);
        $page = 1;

        // 获取规则配置
        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母
        $bagl_11 = ZbBagl::getFirstById(11, true); // 病程记录时间字段

        // 手术记录相关规则
        $rule1053 = RuleWordMap::query()->where('id', 1053)->value('keyword'); // 手术记录MBLB (306)
        $rule2036 = RuleWordMap::query()->where('id', 2036)->value('keyword'); // 作废状态 (9)

        // 四级手术术前多学科讨论相关规则
        $rule292 = RuleWordMap::query()->where('id', 292)->value('keyword'); // 四级手术术前多学科讨论BLLB (292)
        $rule1055 = RuleWordMap::query()->where('id', 1055)->value('keyword'); // 四级手术术前多学科讨论结论记录关键词

        // 手术时间提取规则
        $ruleMap2012 = RuleWordMap::query()->where("id", "=", 2012)->first()->toArray();
        $ruleMap2013 = RuleWordMap::query()->where("id", "=", 2013)->first()->toArray();
        $ruleMap2014 = RuleWordMap::query()->where("id", "=", 2014)->first()->toArray();
        $ruleMap2015 = RuleWordMap::query()->where("id", "=", 2015)->first()->toArray();
        $ruleMap2017 = RuleWordMap::query()->where("id", "=", 2017)->first()->toArray();
        $ruleMap2021 = RuleWordMap::query()->where("id", "=", 2021)->first()->toArray();
        $ruleMap2022 = RuleWordMap::query()->where("id", "=", 2022)->first()->toArray();

        // 初始化ES服务
        $bl01Service = new ElasticsearchService('bl01_202303');

        while (true) {
            // 查询住院病人
            $data = DB::table('patient_info')
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->leftJoin('patient_doctor_info', 'patient_info.MED_REC_ID', '=', 'patient_doctor_info.AAA28')
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('MED_REC_ID', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [date("Y-m-d H:i:s", $start_time), date("Y-m-d H:i:s", $end_time)])
                ->paginate(500, ['patient_info.MED_REC_ID', 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);

            if ($page == 1) {
                echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'sjsssqdxktl_fz' => 0,
                    'sjsssqdxktl_fm' => 0,
                    'sjsssqdxktl_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['sjsssqdxktl_fm' => 0, 'sjsssqdxktl_fz' => 0, 'sjsssqdxktl_error' => null]);
                echo '住院号 : ' . $ZYH . PHP_EOL;
                // 查询四级手术（使用病案首页数据）GROUP BY AAA28,DATE_FORMAT( OPE_DATE, '%Y-%m-%d' ),ICD9_NAME,OPE_MAN_NAME
                /*                 $results = DB::select("SELECT AAA28,DATE_FORMAT( OPE_DATE, '%Y-%m-%d' ) as OPE_DATE_FORMAT,OPE_MAN_NAME,ICD9_NAME,ICD9_ID1,START_TIME,END_TIME  FROM ( SELECT AAA28, OPE_DATE, OPE_MAN_NAME,ICD9_NAME,ICD9_ID1,START_TIME,END_TIME FROM main_operation WHERE ope_level = 4 UNION ALL SELECT AAA28, OPE_DATE, OPE_MAN_NAME,ICD9_NAME,ICD9_ID1,START_TIME,END_TIME FROM secondary_operation WHERE ope_level = 4 ) operation WHERE AAA28 = :id ", ['id' => $ZYH]);
 */
                $mainoperation = MainOperation::query()->where('AAA28', '=', $ZYH)->where('OPE_LEVEL', '=', '4')->get()->toArray();
                $secondaryoperation = SecondaryOperation::query()->where('AAA28', '=', $ZYH)->where('OPE_LEVEL', '=', '4')->get()->toArray();
                $results = array_merge($mainoperation, $secondaryoperation);

                // 按照START_TIME分组去重
                $uniqueResults = [];
                foreach ($results as $item) {
                    if (empty($item['START_TIME'])) {
                        continue; // 没有START_TIME则跳过
                    }
                    $uniqueResults[$item['START_TIME']] = $item; // 用START_TIME作为key，自动去重
                }
                $results = array_values($uniqueResults);

                echo '四级手术台次 : ' . count($results) . PHP_EOL;
                if (!$results) {
                    continue; // 没有四级手术记录，跳过
                }

                // 分母：按日期+术者分组的四级手术台次
                $insert['sjsssqdxktl_fm'] = count($results);


                $m = 1;
                $secsj = null;
                foreach ($results as $surgeryGroup) {
                    $surgeryGroupContent = [
                        'status' => 0,
                        'content' => []
                    ];

                    //设置手术名称 以及 手术编码
                    $surgeryGroupContent['content'][] = ["status" => 1, "content" => "首页手术名称【" . $surgeryGroup['ICD9_NAME'] . "】"];
                    $surgeryGroupContent['content'][] = ["status" => 1, "content" => "首页手术编码【" . $surgeryGroup['ICD9_ID1'] . "】"];
                    $surgeryGroupContent['content'][] = ["status" => 1, "content" => "首页手术级别【" . $surgeryGroup['OPE_LEVEL'] . "】"];
                    $surgeryGroupContent['content'][] = ["status" => 1, "content" => "首页手术开始时间【" . $surgeryGroup['START_TIME'] . "】"];
                    // 查询手术记录bllb303，SSRQ和ope_date在同一天，ssz和OPE_MAN_NAME相同
                    /* $ssjl = Bllb303::query()
                        ->where("ZYH", "=", $ZYH)
                        ->where("SSRQ", "=", $surgeryGroup->OPE_DATE_FORMAT)
                        ->where("SSZ", "=", $surgeryGroup->OPE_MAN_NAME)
                        ->orderBy("SSKSSJ", "asc")
                        ->groupBy("SSZ")
                        ->get()->toArray();

                    if (empty($ssjl)) {
                        $surgeryGroupContent['content'][] = ["status" => 0, "content" => "手术记录【无】"];
                        $errorContent[] = $surgeryGroupContent;
                        continue;
                    } else {
                        $ssjl = $ssjl[0];
                    } */
                    // 查询手术记录SM_SSAP，SSRQ和ope_date在同一天，sz和OPE_MAN_NAME相同
                    // $ssjl = SM_SSAP::query()
                    //     ->where("ZYH", "=", $ZYH)
                    //     ->whereRaw("date_format(SSRQ, '%Y-%m-%d') = ?", [$surgeryGroup->OPE_DATE_FORMAT])
                    //     ->where("SZ", "=", $surgeryGroup->OPE_MAN_NAME)
                    //     ->orderBy("SSRQ", "asc")
                    //     ->first();

                    // if (empty($ssjl)) {
                    //     $surgeryGroupContent['content'][] = ["status" => 0, "content" => "手术记录【无】"];
                    //     $errorContent[] = $surgeryGroupContent;
                    //     continue;
                    // } else {
                    //     $ssjl = $ssjl->toArray();
                    // }


                    //获取手术开始时间
                    $sskssj = $surgeryGroup['START_TIME'];
                    echo '手术开始时间 : ' . $sskssj . PHP_EOL;
                    //获取手术结束时间
                    $ssjssj = $surgeryGroup['END_TIME'];
                    echo '手术结束时间 : ' . $ssjssj . PHP_EOL;

                    //查询四级手术术前多学科讨论
                    $bl01must = [
                        ['term' => ["JZHM" => $ZYH]],
                        ['term' => ["BLLB" => 294]]
                    ];
                    if ($m = 1) {
                        //小于开始时间
                        $bl01must[] = ['range' => ["ZXSJ" => ["lte" => $sskssj]]];
                    } else {
                        $bl01must[] = ['range' => ["ZXSJ" => ["gt" => $secsj, "lte" => $sskssj]]];
                    }
                    //$bl01must[] = ["term" => ["MBLB" => $rule1055]];
                    $bl01must[] = ["match_phrase" => ["BLMC" => "多学科讨论"]];
                    $bl01esService = new ElasticsearchService('bl01_202303');
                    $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->getParams();
                    $bl01Res = app('es')->search($params);
                    $bl01Res = $bl01esService->getDataByEs($bl01Res);
                    echo '四级手术术前多学科讨论 : ' . count($bl01Res[0]) . PHP_EOL;
                    if (count($bl01Res[0]) > 0) {
                        $surgeryGroupContent['content'][] = ["status" => 1, "content" => "手术术前多学科讨论【" . $bl01Res[0][0]['BLMC'] . "】"];
                        //签名时间
                        $firstTime = $bl01Res[0][0]['first_blsy_time'];
                        echo '首次签名时间 : ' . $firstTime . PHP_EOL;
                        if ($m == 1) {
                            if ($firstTime > $sskssj) {
                                $surgeryGroupContent['content'][] = ["status" => 0, "content" => "首次签名时间【" . $firstTime . "(超时)】"];
                            } else {
                                //分子
                                $insert['sjsssqdxktl_fz'] += 1;
                                $surgeryGroupContent['status'] = 1;
                                $surgeryGroupContent['content'][] = ["status" => 1, "content" => "首次签名时间【" . $firstTime . "】"];
                            }
                            $errorContent[] = $surgeryGroupContent;
                        } else {
                            if ($firstTime < $secsj) {
                                $surgeryGroupContent['content'][] = ["status" => 0, "content" => "首次签名时间【" . $firstTime . "(提前)】"];
                            } elseif ($firstTime > $ssjssj) {
                                $surgeryGroupContent['content'][] = ["status" => 0, "content" => "首次签名时间【" . $firstTime . "(超时)】"];
                            } else {
                                //分子
                                $insert['sjsssqdxktl_fz'] += 1;
                                $surgeryGroupContent['status'] = 1;
                                $surgeryGroupContent['content'][] = ["status" => 1, "content" => "首次签名时间【" . $firstTime . "】"];
                            }
                            $errorContent[] = $surgeryGroupContent;
                        }
                    } else {
                        $surgeryGroupContent['content'][] = ["status" => 0, "content" => "术前多学科讨论【无】"];

                        $errorContent[] = $surgeryGroupContent;
                    }
                    $secsj = $ssjssj;
                    $m++;
                }

                if ($insert['sjsssqdxktl_fm'] > 0) {
                    $insert['sjsssqdxktl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                    Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                }

                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }

            // 指定住院号计算指标，则退出下次循环
            if ($zyh) {
                break;
            }
        }
        IndexCatalog::query()->where("index_name", "=", "sjsssqdxktl")->update(["status" => 2, 'quality_time' => time()]);

        return true;
    }

    public function sjsssqdxktl_old($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("sjsssqdxktl", 1);
        $ruleMap2001 = RuleWordMap::query()->where("id", "=", 2001)->first()->toArray();
        $ruleMap2011 = RuleWordMap::query()->where("id", "=", 2011)->first()->toArray();
        $ruleMap2012 = RuleWordMap::query()->where("id", "=", 2012)->first()->toArray();
        $ruleMap2013 = RuleWordMap::query()->where("id", "=", 2013)->first()->toArray();
        $ruleMap2014 = RuleWordMap::query()->where("id", "=", 2014)->first()->toArray();
        $ruleMap2015 = RuleWordMap::query()->where("id", "=", 2015)->first()->toArray();
        $ruleMap1046 = RuleWordMap::query()->where("id", "=", 1046)->first()->toArray();
        $ruleMap2017 = RuleWordMap::query()->where("id", "=", 2017)->first()->toArray();
        $ruleMap2036 = RuleWordMap::query()->where("id", "=", 2036)->first()->toArray();
        $ruleMap2021 = RuleWordMap::query()->where("id", "=", 2021)->first()->toArray();
        $ruleMap2022 = RuleWordMap::query()->where("id", "=", 2022)->first()->toArray();
        $ruleMap1053 = RuleWordMap::query()->where("id", "=", 1053)->first()->toArray();
        $ruleMap1055 = RuleWordMap::query()->where("id", "=", 1055)->first()->toArray();

        $startTime = $start_time ?: $this->startTime;
        $end = $end_time ?: time() + 24 * 3600;
        $qualityRes = [];
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > $end) {
                break;
            }
            $query = PatientInfo::query();
            if ($zyh) {
                $query = $query->where('MED_REC_ID', '=', $zyh);
            } else {
                $query = $query
                    ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                    ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime));
            }
            $patientInfo = $query->get(['MED_REC_ID', "AAC01", 'AAB01'])->toArray();
            $startTime = $entTime;

            foreach ($patientInfo as $p) {
                $flag = 0;
                echo $p['MED_REC_ID'] . "\r\n";
                $keyword = explode(',', $ruleMap2011['keyword']);
                $results = SM_SSAP::query()->where('ZYH', '=', $p['MED_REC_ID'])->whereIn("ICD9_SSLB", $keyword)->get()->toArray();
                if (!$results) {
                    continue;
                }
                // 查找四级手术
                $ICD9_SSCZBM = array_column($results, 'ICD9_SSCZBM');
                $fourLevel = GY_SSML::query()->whereIn("YY_SSCZBM", $ICD9_SSCZBM)->get()->toArray();
                foreach ($fourLevel as $k => $v) {
                    if ($v['YY_SSJBBM'] != 4) {
                        unset($fourLevel[$k]);
                    }
                }
                $YY_SSCZBM = array_column($fourLevel, 'YY_SSCZBM');
                // 过滤掉不是四级手术的手术记录
                foreach ($results as $key => $v) {
                    if (in_array($v['ICD9_SSCZBM'], $YY_SSCZBM)) {
                        unset($results[$key]);
                    }
                }

                $fenzi = ['ZYH' => $p['MED_REC_ID'], 'fenzi' => 0, 'fenmu' => count($results), "content" => []];
                if (!$results) {
                    continue;
                } else {
                    $bl01 = EMR_BL_BL01::query()
                        ->where("JZHM", "=", $p['MED_REC_ID'])
                        ->where("MBLB", "=", $ruleMap1053['keyword'])
                        ->where("BLZT", "<>", $ruleMap2036['keyword'])
                        ->get(["BLBH", "BLMC", "MBLB", 'BLZT'])->toArray();
                    if (!$bl01) {
                        continue;
                    }

                    // 四级手术多学科讨论记录
                    $bl01Tmp = EMR_BL_BL01::query()
                        ->where("JZHM", "=", $p['MED_REC_ID'])
                        ->where("BLZT", "<>", $ruleMap2036['keyword'])
                        ->get(["NA_MED", 'MBLB', 'BLBH', 'BLMC', 'CJSJ'])->toArray();
                    foreach ($bl01Tmp as $k => $item) {
                        // 过滤掉不包含指定关键字的手术记录
                        if (strpos($item['NA_MED'], $ruleMap1055['keyword']) === false) {
                            unset($bl01Tmp[$k]);
                            continue;
                        }
                        preg_match_all('/([0-9|年|月|日|时|分|\)|\(|\-|\:|\.|\s]+)/', $item['BLMC'], $matches);
                        if (empty($matches[1])) {
                            unset($bl01Tmp[$k]);
                            continue;
                        }
                        $timeTmp = substr($item['BLMC'], 0, 16);
                        $timeTmp = str_replace('.', '-', str_replace('年', '-', str_replace('月', '-', str_replace('日', ' ', str_replace('时', ':', str_replace('分', '', $timeTmp))))));
                        $bl01Tmp[$k]['time_tmp'] = strtotime($timeTmp);
                    }

                    foreach ($bl01 as $k => $b) {
                        $error = [];
                        $blxg = EMR_BL_BLXG::query()->where("BLBH", '=', $b["BLBH"])->get()->toArray();
                        if (!$blxg) {
                            continue;
                        }
                        $error[] = ["status" => 1, "手术记录【" . $b['BLMC'] . "】"];

                        $HJNR = $blxg[0]['HJNR'];
                        $HJNR = str_replace("：", ":", $HJNR);
                        $HJNR = str_replace(["{", "}", "(", ")"], "", $HJNR);

                        // 手术开始时间
                        preg_match_all('/' . $ruleMap2012['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2013['keyword'] . '/', $HJNR, $matches);
                        preg_match_all('/' . $ruleMap2014['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2015['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*\-[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2017['keyword'] . '/', $HJNR, $matches1);
                        $opeStartTime = '';
                        if (empty($matches[0]) && empty($matches1[0])) {
                            $error[] = ["status" => 0, "手术开始时间【无】"];
                        } else {
                            $opeStartTime = $matches[0] ? $matches[1][0] : ($matches1[1][0] . ' ' . $matches1[2][0]);
                            $opeStartTime = str_replace('年', '-', str_replace('月', '-', str_replace('日', ' ', str_replace('时', ':', str_replace('分', '', $opeStartTime)))));
                            $error[] = ["status" => 1, "content" => "手术开始时间【{$opeStartTime}】"];
                        }

                        if (!$k) {
                            $opeStartTime = date("Y-m-d H:i:s", strtotime($opeStartTime));
                            echo $opeStartTime . "\r\n";
                            // 为了方便后面判断首次签名时间是否在手术范围内，在这里针对第一个手术的时间进行修改
                            $opeEndTime = $opeStartTime;
                            $opeStartTime = 0;
                        } else {
                            $blxg = EMR_BL_BLXG::query()->where("BLBH", '=', $bl01[$k - 1]["BLBH"])->get()->toArray();
                            $HJNR = $blxg[0]['HJNR'];
                            $HJNR = str_replace("：", ":", $HJNR);
                            $HJNR = str_replace(["{", "}", "(", ")"], "", $HJNR);
                            // 手术结束时间
                            $opeEndTime = "";
                            preg_match_all('/' . $ruleMap2021['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2022['keyword'] . '/', $HJNR, $matches);
                            preg_match_all('/' . $ruleMap2014['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2015['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*\-[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2017['keyword'] . '/', $HJNR, $matches1);
                            if (!empty($matches[0]) || !empty($matches1[0])) {
                                $opeEndTime = $matches[0] ? $matches[1][0] : ($matches1[1][0] . ' ' . $matches1[3][0]);
                                $opeEndTime = str_replace('年', '-', str_replace('月', '-', str_replace('日', ' ', str_replace('时', ':', str_replace('分', '', $opeEndTime)))));
                                $error[] = ["status" => 1, "content" => "手术结束时间【{$opeEndTime}】"];
                            } else {
                                $error[] = ["status" => 1, "content" => "手术结束时间【无】"];
                            }
                        }

                        $blmc = "";
                        foreach ($bl01Tmp as $item) {
                            if ($item['time_tmp'] > strtotime($opeStartTime) && $item['time_tmp'] < strtotime($opeEndTime)) {
                                $flag = 1;
                                $fenzi["fenzi"] += 1;
                                $blmc = $item['BLMC'];
                            }
                        }
                        if ($blmc) {
                            $error[] = ["status" => 1, "content" => "四级手术术前多学科讨论结论记录【{$blmc}】"];
                        } else {
                            $error[] = ["status" => 0, "content" => "四级手术术前多学科讨论结论记录【无】"];
                        }

                        $errorContent['status'] = $flag;
                        $errorContent['content'] = $error;
                        $fenzi["content"][] = $errorContent;
                    }
                }
                $qualityRes[] = $fenzi;
            }

            // 指定住院号计算指标，则退出下次循环
            if ($zyh) {
                break;
            }
        }

        if ($qualityRes) {
            $this->saveIndexRes($qualityRes, "sjsssqdxktl");
        }
        IndexCatalog::query()->where("index_name", "=", "sjsssqdxktl")->update(["status" => 2, 'quality_time' => time()]);
    }

    public function sqtlrygfcyl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("sqtlrygfcyl", 1);
        $ruleMap2001 = RuleWordMap::query()->where("id", "=", 2001)->first()->toArray();
        $ruleMap2011 = RuleWordMap::query()->where("id", "=", 2011)->first()->toArray();
        $ruleMap2012 = RuleWordMap::query()->where("id", "=", 2012)->first()->toArray();
        $ruleMap2013 = RuleWordMap::query()->where("id", "=", 2013)->first()->toArray();
        $ruleMap2014 = RuleWordMap::query()->where("id", "=", 2014)->first()->toArray();
        $ruleMap2015 = RuleWordMap::query()->where("id", "=", 2015)->first()->toArray();
        $ruleMap1046 = RuleWordMap::query()->where("id", "=", 1046)->first()->toArray();
        $ruleMap2017 = RuleWordMap::query()->where("id", "=", 2017)->first()->toArray();
        $ruleMap2036 = RuleWordMap::query()->where("id", "=", 2036)->first()->toArray();
        $ruleMap2021 = RuleWordMap::query()->where("id", "=", 2021)->first()->toArray();
        $ruleMap2022 = RuleWordMap::query()->where("id", "=", 2022)->first()->toArray();
        $ruleMap2023 = RuleWordMap::query()->where("id", "=", 2023)->first()->toArray();

        $startTime = $start_time ?: $this->startTime;
        $end = $end_time ?: time() + 24 * 3600;
        $qualityRes = [];
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > $end) {
                break;
            }
            $query = PatientInfo::query();
            if ($zyh) {
                $query = $query->where('MED_REC_ID', '=', $zyh);
            } else {
                $query = $query
                    ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                    ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime));
            }
            $patientInfo = $query->get(['MED_REC_ID', "AAC01", 'AAB01'])->toArray();
            $startTime = $entTime;

            foreach ($patientInfo as $p) {
                $flag = 0;
                echo $p['MED_REC_ID'] . "\r\n";
                $keyword = explode(',', $ruleMap2011['keyword']);
                $results = SM_SSAP::query()->where('ZYH', '=', $p['MED_REC_ID'])->whereIn("ICD9_SSLB", $keyword)->get()->toArray();
                $fenzi = ['ZYH' => $p['MED_REC_ID'], 'fenzi' => 0, 'fenmu' => count($results), "content" => []];
                $ICD9_SSCZBM = array_column($results, "ICD9_SSCZBM");
                if (!$results) {
                    continue;
                } else {
                    // 查找手术安排24小时内的手术记录
                    $bl01 = EMR_BL_BL01::query()
                        ->where("JZHM", "=", $p['MED_REC_ID'])
                        ->whereIn("MBLB", explode(',', $ruleMap2023['keyword']))
                        ->get(["BLBH", "BLMC", "MBLB", "BLZT"])->toArray();
                    if (!$bl01) {
                        continue;
                    }
                    foreach ($bl01 as $k => $b) {
                        if ($b['BLZT'] == $ruleMap2036["keyword"]) {
                            continue;
                        }
                        $error = [];
                        $blxg = EMR_BL_BLXG::query()->where("BLBH", '=', $b["BLBH"])->get()->toArray();
                        if (!$blxg) {
                            continue;
                        }

                        $HJNR = $blxg[0]['HJNR'];
                        $HJNR = str_replace("：", ":", $HJNR);
                        $error[] = ["status" => 1, "手术记录【{$b["BLMC"]}】"];

                        // 获取术者
                        preg_match_all('/手术者\{(.*?)}/', $HJNR, $shuzhe);
                        $HJNR = str_replace(["{", "}", "(", ")"], "", $HJNR);
                        var_dump($shuzhe);
                        if (empty($shuzhe[1])) {
                            $error[] = ["status" => 0, "术者【无】"];
                        } else {
                            $error[] = ["status" => 1, "术者【{$shuzhe[1][0]}】"];

                            // 手术开始时间
                            preg_match_all('/' . $ruleMap2012['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2013['keyword'] . '/', $HJNR, $matches);
                            preg_match_all('/' . $ruleMap2014['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2015['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*\-[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2017['keyword'] . '/', $HJNR, $matches1);
                            $opeStartTime = '';
                            if (empty($matches[0]) && empty($matches1[0])) {
                                $error[] = ["status" => 0, "手术开始时间【无】"];
                            } else {
                                $opeStartTime = $matches[0] ? $matches[1][0] : ($matches1[1][0] . ' ' . $matches1[2][0]);
                                $opeStartTime = str_replace('年', '-', str_replace('月', '-', str_replace('日', ' ', str_replace('时', ':', str_replace('分', '', $opeStartTime)))));
                                $error[] = ["status" => 1, "content" => "手术开始时间【{$opeStartTime}】"];
                            }


                            // 如果是第一条手术记录，则查询手术开始时间之前的术前小结
                            $sqxj = [];
                            if (!$k) {
                                $opeStartTime = date("Y-m-d H:i:s", strtotime($opeStartTime));
                                // 术前小结
                                echo $opeStartTime . "\r\n";
                                $sqxj = EMR_BL_BL01::query()
                                    ->where($ruleMap2001['keyword'], "<", $opeStartTime)
                                    ->where("JZHM", "=", $p['MED_REC_ID'])
                                    ->where("BLZT", "<>", $ruleMap2036['keyword'])
                                    ->where("MBLB", '=', $ruleMap1046['keyword'])
                                    ->get(["CJSJ", 'BLMC', 'BLBH', $ruleMap2001["keyword"]])->toArray();
                            } else {
                                $blxg = EMR_BL_BLXG::query()->where("BLBH", '=', $bl01[$k - 1]["BLBH"])->get()->toArray();
                                $HJNR = $blxg[0]['HJNR'];
                                $HJNR = str_replace("：", ":", $HJNR);
                                $HJNR = str_replace(["{", "}", "(", ")"], "", $HJNR);
                                // 手术结束时间
                                $opeEndTime = "";
                                preg_match_all('/' . $ruleMap2021['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2022['keyword'] . '/', $HJNR, $matches);
                                preg_match_all('/' . $ruleMap2014['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2015['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*\-[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2017['keyword'] . '/', $HJNR, $matches1);
                                if (!empty($matches[0]) || !empty($matches1[0])) {
                                    $opeEndTime = $matches[0] ? $matches[1][0] : ($matches1[1][0] . ' ' . $matches1[3][0]);
                                    $opeEndTime = str_replace('年', '-', str_replace('月', '-', str_replace('日', ' ', str_replace('时', ':', str_replace('分', '', $opeEndTime)))));
                                }
                                if (!empty($opeEndTime)) {

                                    // 术前小结
                                    $sqxj = EMR_BL_BL01::query()
                                        ->where($ruleMap2001['keyword'], ">", date("Y-m-d H:i:s", strtotime($opeStartTime)))
                                        ->where($ruleMap2001['keyword'], "<", date("Y-m-d H:i:s", strtotime($opeEndTime)))
                                        ->where("JZHM", "=", $p['MED_REC_ID'])
                                        ->where("BLZT", "<>", $ruleMap2036['keyword'])
                                        ->where("MBLB", '=', $ruleMap1046['keyword'])
                                        ->get(["CJSJ", 'BLMC', 'BLBH', $ruleMap2001["keyword"]])->toArray();
                                }
                            }


                            if (empty($sqxj)) {
                                $error[] = ["status" => 0, "content" => "术前小节【无】"];
                            } else {

                                $staff = Staff::query()->where("name", "=", $shuzhe[1][0])->get()->toArray();
                                $blsyFlag = 0;
                                if (!empty($staff)) {

                                    foreach ($sqxj as $xj) {
                                        $blxg = EMR_BL_BLSY::query()
                                            ->where("BLBH", '=', $xj['BLBH'])
                                            ->where("SYYS", '=', $staff[0]["code"])
                                            ->get()->toArray();
                                        if ($blxg) {
                                            $flag = 1;
                                            $blsyFlag = 1;
                                            $error[] = ["status" => 1, "content" => "术前小节【{$xj['BLMC']}】"];
                                            $error[] = ["status" => 1, "content" => "首次完成时间【{$xj[$ruleMap2001["keyword"]]}】"];
                                            break;
                                        }
                                    }
                                } else {
                                    $error[] = ["status" => 0, "content" => "术者【无】"];
                                }
                                if (!$blsyFlag) {
                                    $error[] = ["status" => 0, "content" => "术前小节【无】"];
                                }
                                if ($flag == 1) {
                                    $fenzi["fenzi"] += 1;
                                }
                            }
                        }

                        $errorContent['status'] = $flag;
                        $errorContent['content'] = $error;
                        $fenzi["content"][] = $errorContent;
                    }
                }
                $qualityRes[] = $fenzi;
            }

            // 指定住院号计算指标，则退出下次循环
            if ($zyh) {
                break;
            }
        }

        if ($qualityRes) {
            $this->saveIndexRes($qualityRes, "sqtlrygfcyl");
        }
        IndexCatalog::query()->where("index_name", "=", "sqtlrygfcyl")->update(["status" => 2, 'quality_time' => time()]);
    }

    public function cyqxdkjywy($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("cyqxdkjywy", 1);
        $ruleMap2001 = RuleWordMap::query()->where("id", "=", 2001)->value("keyword");
        $ruleMap2035 = RuleWordMap::query()->where("id", "=", 2035)->value("keyword");
        $ruleMap2036 = RuleWordMap::query()->where("id", "=", 2036)->value("keyword");
        $ruleMap2040 = RuleWordMap::query()->where("id", "=", 2040)->value("keyword");
        $ruleMap2050 = RuleWordMap::query()->where("id", "=", 2050)->value("keyword");

        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, 'name', 'code');
        $startTime = $start_time ?: $this->startTime;
        $end = $end_time ?: time() + 24 * 3600;
        $qxbm = [1 => '010106', 2 => '010107', 3 => '010108'];
        $kjjb = [1 => '非限制使用级', 2 => '限制使用级', 3 => '特殊使用级'];
        $qxbmMap = [10106 => '非限制使用级', 10107 => '限制使用级', 10108 => '特殊使用级'];
        $qualityRes = [];
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > $end) {
                break;
            }
            $query = PatientInfo::query();
            if ($zyh) {
                $query = $query->where('MED_REC_ID', '=', $zyh);
            } else {
                $query = $query
                    ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                    ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime));
            }
            $patientInfo = $query->get(['MED_REC_ID', "AAC01", 'AAB01'])->toArray();
            $startTime = $entTime;
            foreach ($patientInfo as $p) {
                $flag1 = 0;
                $flag2 = 0;

                echo $p['MED_REC_ID'] . "\r\n";
                $errorContent = [];
                $yzb = Yzb::query()->where("ZYH", '=', $p['MED_REC_ID'])
                    ->where('is_has_kjyw', '=', 1)
                    ->where('YZZT', '<>', $ruleMap2035)
                    ->get(['id', 'KZYS', 'KJJB', 'YZMC', 'YZQX', 'KZSJ', 'TZSJ', 'kjyw_name'])->toArray();

                $fenzi = ['ZYH' => $p['MED_REC_ID'], 'fenzi' => 0, 'fenmu' => count($yzb), "content" => []];
                if (!$yzb) {
                    continue;
                } else {
                    $KZYS = array_column($yzb, 'KZYS');
                    $RYQX = RYQX::query()->whereIn("YSDM", $KZYS)->get()->toArray();
                    foreach ($yzb as $y) {
                        $content = [];
                        $content[] = ['status' => 1, 'content' => "医嘱期效【" . ($y['YZQX'] == 1 ? '长期医嘱】' : '临时医嘱】')];
                        $content[] = ['status' => 1, 'content' => "医嘱名称【{$y['YZMC']}】"];
                        $content[] = ['status' => 1, 'content' => "抗菌药级别【" . (empty($kjjb[$y['KJJB']]) ? "未知" : $kjjb[$y['KJJB']]) . "】"];
                        $content[] = ['status' => 1, 'content' => "开嘱医师【{$y['KZYS']} {$staff[$y['KZYS']]}】"];
                        $QXMCIndex = 0;
                        foreach ($RYQX as $r) {
                            if (empty($qxbm[$y['KJJB']])) {
                                continue;
                            }
                            // 获取最高级的权限
                            $QXMCIndex = intval($r['QXBM']) > $QXMCIndex ? intval($r['QXBM']) : $QXMCIndex;
                            if ($y['KZYS'] == $r['YSDM'] && $qxbm[$y['KJJB']] == $r['QXBM']) {
                                $flag1 = 1;
                            }
                        }
                        $content[] = ["status" => 1, "content" => "医师权限【" . ($QXMCIndex && !empty($qxbmMap[$QXMCIndex]) ? $qxbmMap[$QXMCIndex] : "") . "】【" . ($flag1 == 1 ? "未越权" : "越权") . "】"];


                        $content[] = ['status' => 1, 'content' => "开嘱时间(起)【{$y['KZSJ']}】"];
                        if ($y['YZQX'] == 1) {
                            if (strtotime($y['TZSJ']) - strtotime($y['KZSJ']) <= 24 * 3600) {
                                $flag2 = 1;
                                $content[] = ['status' => 1, 'content' => "开嘱时间(止)【{$y['TZSJ']}(≤24小时)】"];
                            }
                        } else {
                            $endTime = "";
                            foreach ($yzb as $y1) {
                                // strtotime($y1['KZSJ']) > strtotime($y['KZSJ']) 只校验后面的医嘱
                                if ($y['kjyw_name'] == $y1['kjyw_name'] && strtotime($y1['KZSJ']) > strtotime($y['KZSJ'])) {
                                    $endTime = $y1['KZSJ'];
                                }
                            }
                            if (!empty($endTime)) {
                                if (strtotime($endTime) - strtotime($y['KZSJ']) <= 24 * 3600) {
                                    $flag2 = 1;
                                }
                                $content[] = ['status' => 1, 'content' => "开嘱时间(止)【{$endTime}" . (strtotime($endTime) - strtotime($y['KZSJ']) <= 24 * 3600 ? '(≤24小时)' : "") . "】"];
                            } else {
                                $flag2 = 1;
                                $content[] = ['status' => 1, 'content' => "开嘱时间(止)【{$y['KZSJ']}(≤24小时)】"];
                            }
                        }
                        // 越权 并且 开嘱时间在24小时内
                        if ($flag1 == 1 && $flag2 == 1) {
                            $fenzi["fenzi"] += 1;
                        }
                        $errorContent['status'] = $flag1;
                        $errorContent['content'] = $content;
                        $fenzi["content"][] = $errorContent;
                    }
                }
                $qualityRes[] = $fenzi;
            }

            // 指定住院号计算指标，则退出下次循环
            if ($zyh) {
                break;
            }
        }

        if ($qualityRes) {
            $this->saveIndexRes($qualityRes, "cyqxdkjywy");
        }
        IndexCatalog::query()->where("index_name", "=", "cyqxdkjywy")->update(["status" => 2, 'quality_time' => time()]);
    }

    public function tsjkjywhz($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("tsjkjywhz", 1);
        $ruleMap2001 = RuleWordMap::query()->where("id", "=", 2001)->value("keyword");
        $ruleMap2035 = RuleWordMap::query()->where("id", "=", 2035)->value("keyword");
        $ruleMap2036 = RuleWordMap::query()->where("id", "=", 2036)->value("keyword");
        $ruleMap2040 = RuleWordMap::query()->where("id", "=", 2040)->value("keyword");
        $ruleMap2050 = RuleWordMap::query()->where("id", "=", 2050)->value("keyword");

        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, 'name', 'code');
        $startTime = $start_time ?: $this->startTime;
        $end = $end_time ?: time() + 24 * 3600;
        while (1) {
            $qualityRes = [];
            $entTime = $startTime + 86400;
            if ($startTime > $end) {
                break;
            }
            $query = PatientInfo::query();
            if ($zyh) {
                $query = $query->where('MED_REC_ID', '=', $zyh);
            } else {
                $query = $query
                    ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                    ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime));
            }
            $patientInfo = $query->get(['MED_REC_ID', "AAC01", 'AAB01'])->toArray();
            $startTime = $entTime;
            foreach ($patientInfo as $p) {
                $flag = 0;

                $fenzi = ['ZYH' => $p['MED_REC_ID'], 'fenzi' => 0, 'fenmu' => 0, "content" => []];
                echo $p['MED_REC_ID'] . "\r\n";
                $errorContent = [];
                $yzb = Yzb::query()->where("ZYH", '=', $p['MED_REC_ID'])
                    ->where('is_has_kjyw', '=', 1)
                    ->where('YZQX', '=', $ruleMap2040)
                    ->where('KJJB', '=', 3)
                    ->where('YZZT', '<>', $ruleMap2035)
                    ->get(['KZYS', 'KJJB', 'YZMC', 'KZSJ'])->toArray();
                echo '医嘱数量：' . count($yzb) . PHP_EOL;
                if (!$yzb) {
                    continue;
                } else {
                    $bl01 = EMR_BL_BL01::query()->where("JZHM", '=', $p['MED_REC_ID'])
                        ->where('BLZT', '<>', $ruleMap2036)
                        ->get(['BLBH', 'BLMC', $ruleMap2001])->toArray();
                    foreach ($bl01 as $k => $v) {
                        if (strpos($v['BLMC'], $ruleMap2050) === false) {
                            unset($bl01[$k]);
                        }
                    }

                    foreach ($yzb as $y) {
                        $content = [];
                        $content[] = ['status' => 1, 'content' => "医嘱名称【{$y['YZMC']}】"];
                        $content[] = ['status' => 1, 'content' => "开嘱时间【{$y['KZSJ']}】"];
                        if (empty($bl01)) {
                            $content[] = ['status' => 0, 'content' => "输血记录【无】"];
                        } else {
                            $tmp = 0;
                            $kzsj = strtotime($y['KZSJ']);
                            foreach ($bl01 as $b) {
                                if (strtotime($b[$ruleMap2001]) > $kzsj - 24 * 3600 && strtotime($b[$ruleMap2001]) < $kzsj + 24 * 3600) {
                                    $tmp = 1;
                                    $flag = 1;
                                    $fenzi["fenzi"] += 1;
                                    $content[] = ['status' => 1, 'content' => "会诊记录【{$b['BLMC']}】"];
                                    $content[] = ['status' => 1, 'content' => "会诊记录完成时间【{$b[$ruleMap2001]}】"];
                                    break;
                                }
                            }
                            if (empty($tmp)) {
                                $content[] = ['status' => 0, 'content' => "会诊记录【无】"];
                                $content[] = ['status' => 0, 'content' => "会诊记录完成时间【无】"];
                            }
                        }
                        $errorContent['status'] = $flag;
                        $errorContent['content'] = $content;
                        $fenzi["fenmu"] += 1; // 分母+1
                        $fenzi["content"][] = $errorContent;
                    }
                }
                $qualityRes[] = $fenzi;
            }

            // 指定住院号计算指标，则退出下次循环
            if ($zyh) {
                break;
            }

            if ($qualityRes) {
                $this->saveIndexRes($qualityRes, "tsjkjywhz");
            }
        }
        IndexCatalog::query()->where("index_name", "=", "tsjkjywhz")->update(["status" => 2, 'quality_time' => time()]);
    }


    public function lcyxqpgjl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("lcyxqpgjl", 1);
        $ruleMap2001 = RuleWordMap::query()->where("id", "=", 2001)->value("keyword");
        $ruleMap2026 = RuleWordMap::query()->where("id", "=", 2026)->value("keyword");
        $ruleMap2027 = RuleWordMap::query()->where("id", "=", 2027)->value("keyword");
        $ruleMap2027 = explode(',', $ruleMap2027);
        $ruleMap2028 = RuleWordMap::query()->where("id", "=", 2028)->value("keyword");
        $ruleMap2029 = RuleWordMap::query()->where("id", "=", 2029)->value("keyword");
        $ruleMap2031 = RuleWordMap::query()->where("id", "=", 2031)->value("keyword");
        $ruleMap2031 = explode(',', $ruleMap2031);
        $ruleMap2032 = RuleWordMap::query()->where("id", "=", 2032)->value("keyword");
        $ruleMap2032 = explode(',', $ruleMap2032);
        $ruleMap2033 = RuleWordMap::query()->where("id", "=", 2033)->value("keyword");
        $ruleMap2033 = explode(',', $ruleMap2033);
        $ruleMap2034 = RuleWordMap::query()->where("id", "=", 2034)->value("keyword");
        $ruleMap2034 = explode(',', $ruleMap2034);
        $ruleMap2035 = RuleWordMap::query()->where("id", "=", 2035)->value("keyword");
        $ruleMap2036 = RuleWordMap::query()->where("id", "=", 2036)->value("keyword");
        $ruleMap2037 = RuleWordMap::query()->where("id", "=", 2037)->value("keyword");

        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, 'name', 'code');
        $startTime = $start_time ?: $this->startTime;
        $end = $end_time ?: time() + 24 * 3600;
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > $end) {
                break;
            }
            $query = PatientInfo::query();
            if ($zyh) {
                $query = $query->where('MED_REC_ID', '=', $zyh);
            } else {
                $query = $query
                    ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                    ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime));
            }
            $patientInfo = $query->get(['MED_REC_ID', "AAC01", 'AAB01'])->toArray();
            $startTime = $entTime;

            foreach ($patientInfo as $p) {
                $flag = 0;

                $fenzi = ['ZYH' => $p['MED_REC_ID'], 'fenzi' => 0, 'fenmu' => 0, "content" => null];
                echo $p['MED_REC_ID'] . "\r\n";
                $errorContent = [];
                $yzb = Yzb::query()->where("ZYH", '=', $p['MED_REC_ID'])
                    ->where('YZZT', '<>', $ruleMap2035)
                    ->get(['KZYS', 'KJJB', 'YZMC', 'KZSJ', 'ZYH'])->toArray();

                // 过滤掉包含指定关键字的
                foreach ($yzb as $k => $y) {
                    // 必须包含2026对应关键字
                    // 必须不包含2028对应的关键字
                    if (strpos($y['YZMC'], $ruleMap2026) === false || strpos($y['YZMC'], $ruleMap2028) !== false) {
                        unset($yzb[$k]);
                    }

                    // 必须包含2027对应的任意一个关键字
                    $tmp = 0;
                    foreach ($ruleMap2027 as $r2027) {
                        if (strpos($y['YZMC'], $r2027) !== false) {
                            $tmp = 1;
                            continue;
                        }
                    }
                    if (!$tmp) {
                        unset($yzb[$k]);
                    }
                }

                if (!$yzb) {
                    continue;
                } else {
                    $bl01 = EMR_BL_BL01::query()->where("JZHM", '=', $p['MED_REC_ID'])
                        ->where('MBLB', '=', $ruleMap2029)
                        ->where('BLZT', '<>', $ruleMap2036)
                        ->get(['BLBH', 'BLMC', $ruleMap2001])->toArray();

                    foreach ($yzb as $y) {
                        $content = [];
                        $content[] = ['status' => 1, 'content' => "医嘱名称【{$y['YZMC']}】"];
                        $content[] = ['status' => 1, 'content' => "开嘱时间【{$y['KZSJ']}】"];
                        if (empty($bl01)) {
                            $content[] = ['status' => 0, 'content' => "输血记录【无】"];
                        } else {
                            $tmp = 0;
                            $kzsj = strtotime($y['KZSJ']);
                            foreach ($bl01 as $b) {
                                if (strtotime($b[$ruleMap2001]) > $kzsj && strtotime($b[$ruleMap2001]) < $kzsj + 24 * 3600) {
                                    $tmp = 1;
                                    $content[] = ['status' => 1, 'content' => "输血记录【{$b['BLMC']}】"];
                                    //                                    输血记录中是否进行用血前评估
                                    $blxg = EMR_BL_BLXG::query()->where("BLBH", '=', $b['BLBH'])->get()->toArray();
                                    $pgTmp = 0;
                                    if (strpos($y['YZMC'], '红细胞')) {
                                        foreach ($ruleMap2031 as $r) {
                                            if (strpos($blxg[0]['HJNR'], $r)) {
                                                $pgTmp = 1;
                                            }
                                        }
                                    }
                                    if (strpos($y['YZMC'], '血浆')) {
                                        foreach ($ruleMap2032 as $r) {
                                            if (strpos($blxg[0]['HJNR'], $r)) {
                                                $pgTmp = 1;
                                            }
                                        }
                                    }
                                    if (strpos($y['YZMC'], '凝血因子')) {
                                        foreach ($ruleMap2033 as $r) {
                                            if (strpos($blxg[0]['HJNR'], $r)) {
                                                $pgTmp = 1;
                                            }
                                        }
                                    }
                                    if (strpos($y['YZMC'], '血小板')) {
                                        foreach ($ruleMap2034 as $r) {
                                            if (strpos($blxg[0]['HJNR'], $r)) {
                                                $pgTmp = 1;
                                            }
                                        }
                                    }
                                    if (empty($pgTmp)) {
                                        $content[] = ['status' => 0, 'content' => "用血前评估【无】"];
                                    } else {
                                        $flag = 1;
                                        $fenzi["fenzi"] += 1;
                                        $content[] = ['status' => 1, 'content' => "用血前评估【有】"];
                                    }
                                    break;
                                }
                            }
                            if (empty($tmp)) {
                                $content[] = ['status' => 0, 'content' => "输血记录【无】"];
                            }
                        }
                    }
                    $errorContent['status'] = $flag;
                    $errorContent['content'] = $content;
                    $fenzi["fenmu"] += 1; // 分母+1
                    $fenzi["content"][] = $errorContent;
                }
                $qualityRes[] = $fenzi;
            }

            // 指定住院号计算指标，则退出下次循环
            if ($zyh) {
                break;
            }
        }

        if ($qualityRes) {
            $this->saveIndexRes($qualityRes, "lcyxqpgjl");
        }
        IndexCatalog::query()->where("index_name", "=", "lcyxqpgjl")->update(["status" => 2, 'quality_time' => time()]);
    }

    public function kjywcfqlshg($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("kjywcfqlshg", 1);
        $ruleMap2040 = RuleWordMap::query()->where("id", "=", 2040)->value("keyword");
        $ruleMap2035 = RuleWordMap::query()->where("id", "=", 2035)->value("keyword");

        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, 'name', 'code');
        $startTime = $start_time ?: $this->startTime;
        $end = $end_time ?: time() + 24 * 3600;
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > $end) {
                break;
            }
            $query = PatientInfo::query();
            if ($zyh) {
                $query = $query->where('MED_REC_ID', '=', $zyh);
            } else {
                $query = $query
                    ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                    ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime));
            }
            $patientInfo = $query->get(['MED_REC_ID', "AAC01", 'AAB01'])->toArray();
            $startTime = $entTime;

            foreach ($patientInfo as $p) {
                $flag = 0;

                $fenzi = ['ZYH' => $p['MED_REC_ID'], 'fenzi' => 0, 'fenmu' => 0, "content" => []];
                echo $p['MED_REC_ID'] . "\r\n";
                $errorContent = [];
                // 清洗抗菌药物、化疗药物
                RadioService::filterField($p['MED_REC_ID']);
                $yzb = Yzb::query()->where("ZYH", '=', $p['MED_REC_ID'])
                    ->where('is_has_kjyw', '=', 1)
                    ->where('YZQX', '=', $ruleMap2040)
                    ->where('YZZT', '<>', $ruleMap2035)
                    ->get(['KZYS', 'KJJB', 'YZMC', 'KZSJ'])->toArray();
                echo '医嘱：' . count($yzb) . "\r\n";
                if (!$yzb) {
                    continue;
                } else {
                    $KZYS = array_column($yzb, 'KZYS');
                    echo "开嘱医师：" . json_encode($KZYS) . "\r\n";
                    $RYQX = RYQX::query()->whereIn("YSDM", $KZYS)->get()->toArray();
                    $qxbm = [1 => '010106', 2 => '010107', 3 => '010108'];
                    $kjjb = [1 => '非限制使用级', 2 => '限制使用级', 3 => '特殊使用级'];
                    foreach ($yzb as $y) {
                        $content = [];
                        $content[] = ['status' => 1, 'content' => "医嘱名称{$y['YZMC']}"];
                        $content[] = ['status' => 1, 'content' => "抗菌药级别" . (empty($kjjb[$y['KJJB']]) ? "" : $kjjb[$y['KJJB']])];
                        $content[] = ['status' => 1, 'content' => "开嘱医师{$y['KZYS']} {$staff[$y['KZYS']]}"];
                        $content[] = ['status' => 1, 'content' => "开嘱时间{$y['KZSJ']}"];
                        $tmpFlag = 0;
                        foreach ($RYQX as $r) {
                            if ($y['KZYS'] == $r['YSDM'] && !empty($kjjb[$y['KJJB']]) && $qxbm[$y['KJJB']] == $r['QXBM']) {
                                echo '抗菌级别：' . $y['KJJB'] . "\r\n";
                                $tmpFlag = 1;
                                $flag = 1;
                                $fenzi["fenzi"] += 1;
                                $content[] = ["status" => 1, "content" => "医师权限【{$r['QXMC']}】"];
                            } elseif ($y['KJJB'] == 9) {
                                $tmpFlag = 1;
                                $content[] = ["status" => 0, "content" => "医师权限【未知】"];
                            }
                        }
                        if (!$tmpFlag) {
                            $content[] = ["status" => 0, "content" => "医师权限不一致"];
                        }
                        $errorContent['status'] = $flag;
                        $errorContent['content'] = $content;
                        $fenzi["fenmu"] += 1; // 分母+1
                        $fenzi["content"][] = $errorContent;
                    }
                }
                $qualityRes[] = $fenzi;
            }

            // 指定住院号计算指标，则退出下次循环
            if ($zyh) {
                break;
            }
        }

        if ($qualityRes) {
            $this->saveIndexRes($qualityRes, "kjywcfqlshg");
        }
        IndexCatalog::query()->where("index_name", "=", "kjywcfqlshg")->update(["status" => 2, 'quality_time' => time()]);
    }

    public function shscbcjkwcl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("shscbcjkwc", 1);

        $ruleMap2001 = RuleWordMap::query()->where("id", "=", 2001)->first()->toArray();
        $ruleMap2011 = RuleWordMap::query()->where("id", "=", 2011)->first()->toArray();
        $ruleMap2014 = RuleWordMap::query()->where("id", "=", 2014)->first()->toArray();
        $ruleMap2015 = RuleWordMap::query()->where("id", "=", 2015)->first()->toArray();
        $ruleMap2017 = RuleWordMap::query()->where("id", "=", 2017)->first()->toArray();
        $ruleMap2018 = RuleWordMap::query()->where("id", "=", 2018)->first()->toArray();
        $ruleMap2021 = RuleWordMap::query()->where("id", "=", 2021)->first()->toArray();
        $ruleMap2022 = RuleWordMap::query()->where("id", "=", 2022)->first()->toArray();
        $ruleMap2023 = RuleWordMap::query()->where("id", "=", 2023)->first()->toArray();

        $startTime = $start_time ?: $this->startTime;
        $end = $end_time ?: time() + 24 * 3600;
        $qualityRes = [];
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > $end) {
                break;
            }
            $query = PatientInfo::query();
            if ($zyh) {
                $query = $query->where('MED_REC_ID', '=', $zyh);
            } else {
                $query = $query
                    ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                    ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime));
            }
            $patientInfo = $query->get(['MED_REC_ID', "AAC01", 'AAB01'])->toArray();

            $startTime = $entTime;
            foreach ($patientInfo as $p) {
                // 祝愿天数小于1天的则过滤
                if (strtotime($p['AAC01']) - strtotime($p['AAB01']) < 24 * 3600) {
                    continue;
                }
                $fenzi = ['ZYH' => $p['MED_REC_ID'], 'fenzi' => 0, 'fenmu' => 0, "content" => []];
                $flag = 0;

                $keyword = explode(',', $ruleMap2011['keyword']);
                $results = SM_SSAP::query()->where('ZYH', '=', $p['MED_REC_ID'])->whereIn("ICD9_SSLB", $keyword)->get()->toArray();


                if (!$results) {
                    continue;
                } else {
                    // 获取手术安排对应的手术记录
                    foreach ($results as $res) {
                        $error = [];
                        $error[] = ["status" => 1, "content" => "入院时间【{$p['AAB01']}】"];
                        $error[] = ["status" => 1, "content" => "出院时间【{$p['AAC01']}】"];
                        // 查找手术安排24小时内的手术记录
                        $bl01 = EMR_BL_BL01::query()
                            ->where("CJSJ", ">", $res['SSRQ'])
                            ->where("CJSJ", "<", date("Y-m-d H:i:s", strtotime($res['SSRQ']) + 24 * 3600))
                            ->where("JZHM", "=", $p['MED_REC_ID'])
                            ->whereIn("MBLB", explode(',', $ruleMap2023['keyword']))->get()->toArray();
                        if (!$bl01) {
                            $error[] = ["status" => 0, "手术记录【无】"];
                        } else {
                            $blxg = EMR_BL_BLXG::query()->where("BLBH", '=', $bl01[0]["BLBH"])->get()->toArray();
                            if (!$blxg) {
                                continue;
                            }
                            $HJNR = $blxg[0]['HJNR'];
                            $HJNR = str_replace("：", ":", $HJNR);
                            $HJNR = str_replace(["{", "}", "(", ")"], "", $HJNR);
                            preg_match_all('/' . $ruleMap2021['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2022['keyword'] . '/', $HJNR, $matches);
                            preg_match_all('/' . $ruleMap2014['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2015['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*\-[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2017['keyword'] . '/', $HJNR, $matches1);
                            if (empty($matches[0]) && empty($matches1[0])) {
                                $error[] = ["status" => 0, "手术结束时间【无】"];
                            } else {
                                $opeStartTime = $matches[0] ? $matches[1][0] : $matches1[1][0] . ' ' . $matches1[3][0];
                                $opeStartTime = str_replace('年', '-', str_replace('月', '-', str_replace('日', ' ', str_replace('时', ':', str_replace('分', '', $opeStartTime)))));
                                $error[] = ["status" => 1, "content" => "手术结束时间【{$opeStartTime}】"];
                                $opeStartTime = strtotime($opeStartTime);

                                $scbc = EMR_BL_BL01::query()
                                    ->where("JZHM", "=", $p['MED_REC_ID'])
                                    ->where("MBLB", "=", 42)->get()->toArray();
                                if (empty($scbc)) {
                                    $error[] = ["status" => 0, "content" => "术后首次病程【无】"];
                                } else {
                                    $bcTime = "";
                                    foreach ($scbc as $v) {
                                        if (empty($v[$ruleMap2001["keyword"]]) || empty($opeStartTime)) {
                                            continue;
                                        }
                                        // 术后首次创建病程的时间在手术结束后24小
                                        if (strtotime($v[$ruleMap2001["keyword"]]) > $opeStartTime and strtotime($v[$ruleMap2001["keyword"]]) < $opeStartTime + 24 * 3600) {
                                            $bcTime = $v[$ruleMap2001["keyword"]];
                                        }
                                    }
                                    if ($bcTime) {
                                        $flag = 1;
                                        $fenzi["fenzi"] += 1;
                                        $error[] = ["status" => 1, "content" => "术后首次病程【{$bcTime}（24小时内）】"];
                                    } else {
                                        $error[] = ["status" => 0, "content" => "术后首次病程【无】"];
                                    }
                                }
                            }
                        }

                        $errorContent['status'] = $flag;
                        $errorContent['content'] = $error;
                        $fenzi["fenmu"] += 1; // 分母+1
                        $fenzi["content"][] = $errorContent;
                    }
                }
                $qualityRes[] = $fenzi;
            }

            // 指定住院号计算指标，则退出下次循环
            if ($zyh) {
                break;
            }
        }

        if ($qualityRes) {
            $this->saveIndexRes($qualityRes, "shscbcjkwc");
        }

        IndexCatalog::query()->where("index_name", "=", "shscbcjkwc")->update(["status" => 2, 'quality_time' => time()]);
    }

    public function ssjlwcl24($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("ssjlwc24", 1);
        $ruleMap2001 = RuleWordMap::query()->where("id", "=", 2001)->first()->toArray();
        $ruleMap2011 = RuleWordMap::query()->where("id", "=", 2011)->first()->toArray();
        $ruleMap2014 = RuleWordMap::query()->where("id", "=", 2014)->first()->toArray();
        $ruleMap2015 = RuleWordMap::query()->where("id", "=", 2015)->first()->toArray();
        $ruleMap2017 = RuleWordMap::query()->where("id", "=", 2017)->first()->toArray();
        $ruleMap2018 = RuleWordMap::query()->where("id", "=", 2018)->first()->toArray();
        $ruleMap2021 = RuleWordMap::query()->where("id", "=", 2021)->first()->toArray();
        $ruleMap2022 = RuleWordMap::query()->where("id", "=", 2022)->first()->toArray();
        $ruleMap2023 = RuleWordMap::query()->where("id", "=", 2023)->first()->toArray();

        $startTime = $start_time ?: $this->startTime;
        $end = $end_time ?: time() + 24 * 3600;
        $qualityRes = [];
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > $end) {
                break;
            }
            $query = PatientInfo::query();
            if ($zyh) {
                $query = $query->where('MED_REC_ID', '=', $zyh);
            } else {
                $query = $query
                    ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                    ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime));
            }
            $patientInfo = $query->get(['MED_REC_ID', "AAC01", 'AAB01'])->toArray();
            $startTime = $entTime;

            foreach ($patientInfo as $p) {
                echo $p['MED_REC_ID'] . "\r\n";
                $fenzi = ['ZYH' => $p['MED_REC_ID'], 'fenzi' => 0, 'fenmu' => 0, "content" => []];
                $flag = 0;

                $keyword = explode(',', $ruleMap2011['keyword']);
                $results = SM_SSAP::query()->where('ZYH', '=', $p['MED_REC_ID'])->whereIn("ICD9_SSLB", $keyword)->get()->toArray();

                if (!$results) {
                    continue;
                } else {
                    // 获取手术安排对应的手术记录
                    foreach ($results as $res) {
                        $error = [];
                        $error[] = ["status" => 1, "content" => "手术类型【{$res['ICD9_SSLB']}】"];
                        $error[] = ["status" => 1, "content" => "手术名称【{$res['ICD9_SSCZMC']}】"];
                        // 查找手术安排24小时内的手术记录
                        $bl01 = EMR_BL_BL01::query()
                            ->where("CJSJ", ">", $res['SSRQ'])
                            ->where("CJSJ", "<", date("Y-m-d H:i:s", strtotime($res['SSRQ']) + 24 * 3600))
                            ->where("JZHM", "=", $p['MED_REC_ID'])
                            ->whereIn("MBLB", explode(',', $ruleMap2023['keyword']))->get()->toArray();
                        if (!$bl01) {
                            $error[] = ["status" => 0, "content" => "手术记录【无】"];
                        } else {
                            $blxg = EMR_BL_BLXG::query()->where("BLBH", '=', $bl01[0]["BLBH"])->get()->toArray();
                            if (!$blxg) {
                                continue;
                            }
                            $HJNR = $blxg[0]['HJNR'];
                            $HJNR = str_replace("：", ":", $HJNR);
                            $HJNR = str_replace(["{", "}", "(", ")"], "", $HJNR);

                            preg_match_all('/' . $ruleMap2021['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2022['keyword'] . '/', $HJNR, $matches);
                            preg_match_all('/' . $ruleMap2014['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2015['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*\-[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2017['keyword'] . '/', $HJNR, $matches1);
                            if (empty($matches[0]) && empty($matches1[0])) {
                                $error[] = ["status" => 0, "content" => "手术结束时间【无】"];
                            } else {
                                $opeStartTime = $matches[0] ? $matches[1][0] : $matches1[1][0] . ' ' . $matches1[3][0];
                                $opeStartTime = str_replace('年', '-', str_replace('月', '-', str_replace('日', ' ', str_replace('时', ':', str_replace('分', '', $opeStartTime)))));
                                $error[] = ["status" => 1, "content" => "手术结束时间【{$opeStartTime}】"];
                                $opeStartTime = strtotime($opeStartTime);

                                if (strtotime($bl01[0][$ruleMap2001["keyword"]]) > $opeStartTime and strtotime($bl01[0][$ruleMap2001["keyword"]]) < $opeStartTime + 24 * 3600) {
                                    $flag = 1;
                                    $fenzi["fenzi"] += 1;
                                    $error[] = ["status" => 1, "content" => "术前讨论签名【{$ruleMap2001["keyword"]}（24小时内）】"];
                                } else {
                                    $error[] = ["status" => 0, "content" => "术前讨论签名【{$ruleMap2001["keyword"]}（超24小时）】"];
                                }
                            }
                        }

                        $errorContent['status'] = $flag;
                        $errorContent['content'] = $error;
                        $fenzi["fenmu"] += 1; // 分母+1
                        $fenzi["content"][] = $errorContent;
                    }
                }
                $qualityRes[] = $fenzi;
            }

            // 指定住院号计算指标，则退出下次循环
            if ($zyh) {
                break;
            }
        }

        if ($qualityRes) {
            $this->saveIndexRes($qualityRes, "ssjlwc24");
        }
        IndexCatalog::query()->where("index_name", "=", "ssjlwc24")->update(["status" => 2, 'quality_time' => time()]);
    }

    public function sqtlwcl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("sqtlwcl", 1);
        $ruleMap2001 = RuleWordMap::query()->where("id", "=", 2001)->first()->toArray();
        $ruleMap2011 = RuleWordMap::query()->where("id", "=", 2011)->first()->toArray();
        $ruleMap2012 = RuleWordMap::query()->where("id", "=", 2012)->first()->toArray();
        $ruleMap2013 = RuleWordMap::query()->where("id", "=", 2013)->first()->toArray();
        $ruleMap2014 = RuleWordMap::query()->where("id", "=", 2014)->first()->toArray();
        $ruleMap2015 = RuleWordMap::query()->where("id", "=", 2015)->first()->toArray();
        $ruleMap2016 = RuleWordMap::query()->where("id", "=", 2016)->first()->toArray();
        $ruleMap2017 = RuleWordMap::query()->where("id", "=", 2017)->first()->toArray();
        $ruleMap2018 = RuleWordMap::query()->where("id", "=", 2018)->first()->toArray();
        $ruleMap2023 = RuleWordMap::query()->where("id", "=", 2023)->first()->toArray();

        $startTime = $start_time ?: $this->startTime;
        $end = $end_time ?: time() + 24 * 3600;
        $qualityRes = [];
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > $end) {
                break;
            }
            $query = PatientInfo::query();
            if ($zyh) {
                $query = $query->where('MED_REC_ID', '=', $zyh);
            } else {
                $query = $query
                    ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                    ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime));
            }
            $patientInfo = $query->get(['MED_REC_ID', "AAC01", 'AAB01'])->toArray();
            $startTime = $entTime;

            foreach ($patientInfo as $p) {
                $fenzi = ['ZYH' => $p['MED_REC_ID'], 'fenzi' => 0, 'fenmu' => 0, "content" => []];
                $flag = 0;

                $keyword = explode(',', $ruleMap2011['keyword']);
                $results = SM_SSAP::query()->where('ZYH', '=', $p['MED_REC_ID'])->whereIn("ICD9_SSLB", $keyword)->get()->toArray();
                if (!$results) {
                    continue;
                } else {
                    // 获取手术安排对应的手术记录
                    foreach ($results as $res) {
                        $error = [];
                        $error[] = ["status" => 1, "手术类型【{$res['ICD9_SSLB']}】"];
                        $error[] = ["status" => 1, "手术名称【{$res['ICD9_SSCZMC']}】"];
                        // 查找手术安排24小时内的手术记录
                        $bl01 = EMR_BL_BL01::query()
                            ->where("CJSJ", ">", $res['SSRQ'])
                            ->where("CJSJ", "<", date("Y-m-d H:i:s", strtotime($res['SSRQ']) + 24 * 3600))
                            ->where("JZHM", "=", $p['MED_REC_ID'])
                            ->whereIn("MBLB", explode(',', $ruleMap2023['keyword']))->get()->toArray();

                        if (!$bl01) {
                            continue;
                        }
                        $blxg = EMR_BL_BLXG::query()->where("BLBH", '=', $bl01[0]["BLBH"])->get()->toArray();
                        if (!$blxg) {
                            continue;
                        }
                        $HJNR = $blxg[0]['HJNR'];
                        $HJNR = str_replace("：", ":", $HJNR);
                        $HJNR = str_replace(["{", "}", "(", ")"], "", $HJNR);
                        preg_match_all('/' . $ruleMap2012['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2013['keyword'] . '/', $HJNR, $matches);
                        preg_match_all('/' . $ruleMap2014['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*' . $ruleMap2015['keyword'] . '[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*\-/', $HJNR, $matches1);
                        $opeStartTime = '';
                        if (empty($matches[0]) && empty($matches1[0])) {
                            $error[] = ["status" => 0, "手术开始时间【无】"];
                        } else {
                            $opeStartTime = $matches[0] ? $matches[1][0] : $matches1[1][0] . ' ' . $matches1[2][0];
                            $opeStartTime = str_replace('年', '-', str_replace('月', '-', str_replace('日', ' ', str_replace('时', ':', str_replace('分', '', $opeStartTime)))));
                            $error[] = ["status" => 1, "content" => "手术开始时间【{$opeStartTime}】"];
                        }

                        // 术前讨论记录
                        $sqtl = EMR_BL_BL01::query()
                            ->where("CJSJ", ">", $res['SSRQ'])
                            ->where("CJSJ", "<", date("Y-m-d H:i:s", strtotime($res['SSRQ']) + 24 * 3600))
                            ->where("JZHM", "=", $p['MED_REC_ID'])
                            ->where("MBLB", '=', $ruleMap2018['keyword'])->get()->toArray();
                        if (empty($sqtl)) {
                            $error[] = ["status" => 0, "content" => "术前讨论【无】"];
                        } elseif (empty($sqtl[0][$ruleMap2001["keyword"]])) {
                            $error[] = ["status" => 0, "content" => "术前讨论签名【无】"];
                        } elseif (!empty($opeStartTime)) {
                            $error[] = ["status" => 1, "content" => "术前讨论【{$sqtl[0]["BLMC"]}】"];
                            if (strtotime($sqtl[0][$ruleMap2001["keyword"]]) > strtotime($opeStartTime) and strtotime($sqtl[0][$ruleMap2001["keyword"]]) < strtotime($opeStartTime) + 24 * 3600) {
                                $flag = 1;
                                $fenzi["fenzi"] += 1;
                                $error[] = ["status" => 1, "content" => "术前讨论签名【{$ruleMap2001["keyword"]}（24小时内）】"];
                            } else {
                                $error[] = ["status" => 0, "content" => "术前讨论签名【{$ruleMap2001["keyword"]}（超24小时）】"];
                            }
                        }
                        $errorContent['status'] = $flag;
                        $errorContent['content'] = $error;
                        $fenzi["fenmu"] += 1; // 分母+1
                        $fenzi["content"][] = $errorContent;
                    }
                }
                $qualityRes[] = $fenzi;
            }

            // 指定住院号计算指标，则退出下次循环
            if ($zyh) {
                break;
            }
        }

        if ($qualityRes) {
            $this->saveIndexRes($qualityRes, "sqtlwcl");
        }
        IndexCatalog::query()->where("index_name", "=", "sqtlwcl")->update(["status" => 2, 'quality_time' => time()]);
    }

    /**
     * @param array $fenzi
     * @return bool
     * 保存指标结果
     */
    public function saveIndexRes($fenzi = [], $indexName = "")
    {
        $zyh = array_column($fenzi, "ZYH");
        $pi = PatientInfo::query()->whereIn("MED_REC_ID", $zyh)->get(["MED_REC_ID", "AAC11N", "AAC01"])->toArray();
        $pi = array_column($pi, null, "MED_REC_ID");
        $pdi = PatientDoctorInfo::query()->whereIn("AAA28", $zyh)->get(["AAA28", "AEE03"])->toArray();
        $pdi = array_column($pdi, null, "AAA28");
        echo "生成数据" . count($fenzi) . "条\r\n";
        foreach ($fenzi as $d) {
            var_dump("分子：" . $d['fenzi']);
            Indicator::query()->updateOrInsert(
                ['zyh' => $d['ZYH']],
                [
                    'AAC11N' => $pi[$d['ZYH']]['AAC11N'],
                    'AEE03' => $pdi[$d['ZYH']]['AEE03'] ?? "",
                    'AAC01' => $pi[$d['ZYH']]['AAC01'],
                    'AAC01_YEAR' => substr($pi[$d['ZYH']]['AAC01'], 0, 4),
                    'AAC01_MONTH' => substr($pi[$d['ZYH']]['AAC01'], 5, 2),
                    $indexName . '_fm' => $d['fenmu'],
                    $indexName . '_fz' => $d['fenzi'],
                    $indexName . '_error' => json_encode($d['content'], 256)
                ]
            );
        }

        return true;
    }

    public function swjlwcl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("swtljswcl", 1);
        $ruleMap2001 = RuleWordMap::query()->where("id", "=", 2001)->first()->toArray();
        $ruleMap2010 = RuleWordMap::query()->where("id", "=", 2010)->first()->toArray();
        $ruleMap2009 = RuleWordMap::query()->where("id", "=", 2009)->first()->toArray();

        $startTime = $start_time ?: $this->startTime;
        $end = $end_time ?: time() + (24 * 3600);
        $swsj = ZY_BRRY::query()->where('ZYH', '=', $zyh)->get()->toArray();
        $swsj = $swsj[0]['AAC01'] ?? '';
        if (empty($swsj)) {
            return true;
        }
        $qualityRes = [];
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > $end) {
                break;
            }
            $query = Yzb::query();
            if ($zyh) {
                $query = $query->where('ZYH', '=', $zyh);
            } else {
                $query = $query
                    ->where('KZSJ', '>=', date("Y-m-d H:i:s", $startTime))
                    ->where('KZSJ', '<=', date("Y-m-d H:i:s", $entTime));
            }
            $yzb = $query->get(['ZYH', 'KZSJ', 'YZMC', 'XZJDSJ'])->toArray();
            $startTime = $entTime;


            foreach ($yzb as $p) {
                $flag = 0;
                // 不包含指定关键字，则跳过
                if (strpos($p['YZMC'], $ruleMap2009['keyword']) === false) {
                    continue;
                }
                $errorContent = [];
                $errorContent[] = [
                    "status" => 1,
                    "content" => "医嘱名称【{$p['YZMC']}】"
                ];
                $errorContent[] = [
                    "status" => 1,
                    "content" => "死亡时间【{$swsj}】"
                ];
                $bl01 = EMR_BL_BL01::query()->where("JZHM", '=', $p['ZYH'])
                    ->where('BLLB', '=', '294')
                    ->where('BLMC', 'like', '%死亡病例讨论%')
                    ->where('ZXSJ', '>=', $swsj)
                    ->where('ZXSJ', '<=', date("Y-m-d H:i:s", strtotime($swsj) + 120 * 3600))
                    ->get([$ruleMap2001['keyword']])->toArray();

                if (!$bl01) {
                    $errorContent[] = [
                        "status" => 0,
                        "content" => "死亡病例讨论记录【无】"
                    ];
                } else {
                    $tmpTime = $bl01[0][$ruleMap2001['keyword']] ?? 0;
                    var_dump("死亡病例讨论记录添加时间：" . $tmpTime);
                    if (strtotime($tmpTime) >= strtotime($swsj) && strtotime($tmpTime) <= strtotime($swsj) + (120 * 3600)) {
                        $flag = 1;
                        $errorContent[] = [
                            "status" => 1,
                            "content" => "死亡病例讨论记录【{$tmpTime}（5天内）】"
                        ];
                    } elseif (strtotime($tmpTime) > strtotime($swsj) + 120 * 3600) {
                        $errorContent[] = [
                            "status" => 0,
                            "content" => "死亡病例讨论记录【{$tmpTime}（超5天）】"
                        ];
                    } elseif (strtotime($tmpTime) < strtotime($swsj)) {
                        $errorContent[] = [
                            "status" => 0,
                            "content" => "死亡病例讨论记录【{$tmpTime}（提前创建）】"
                        ];
                    }
                }
                $fenzi[] = [
                    'ZYH' => $p['ZYH'],
                    'fenzi' => $flag,
                    'fenmu' => 1,
                    "content" =>
                    [["status" => $flag, "content" => $errorContent]]
                ];
            }

            // 指定住院号计算指标，则退出下次循环
            if ($zyh) {
                break;
            }
        }

        if ($fenzi) {
            $this->saveIndexRes($fenzi, "swtljswcl");
        }
        IndexCatalog::query()->where("index_name", "=", "swtljswcl")->update(["status" => 2, 'quality_time' => time()]);
    }

    public function bl24wcl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("ryjlxswcl24", 1);
        $ruleMap2001 = RuleWordMap::query()->where("id", "=", 2001)->first()->toArray();
        $ruleMap2007 = RuleWordMap::query()->where("id", "=", 2007)->first()->toArray();

        $startTime = $start_time ?: $this->startTime;
        $end = $end_time ?: time() + 24 * 3600;
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > $end) {
                break;
            }
            $query = PatientInfo::query();
            if ($zyh) {
                $query = $query->where('MED_REC_ID', '=', $zyh);
            } else {
                $query = $query
                    ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                    ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime));
            }
            $patientInfo = $query->get(['MED_REC_ID', "AAC01", 'AAB01','AAC04'])->toArray();
            $startTime = $entTime;

            foreach ($patientInfo as $p) {
                //删除
                Indicator::query()->updateOrInsert(['zyh' => $p['MED_REC_ID']], ['ryjlxswcl24_fm' => 0, 'ryjlxswcl24_fz' => 0, 'ryjlxswcl24_error' => null]);
                $flag = 0;
                // 祝愿天数小于1天的则过滤
                if (strtotime($p['AAC01']) - strtotime($p['AAB01']) < 24 * 3600) {
                    continue;
                }
                echo $p['MED_REC_ID'] . "\r\n";
                $errorContent = [];
                $errorContent[] = ["status" => 1, "content" => "入院时间【{$p['AAB01']}】"];
                $bl01 = EMR_BL_BL01::query()->where("JZHM", '=', $p['MED_REC_ID'])
                    ->where('MBLB', '=', $ruleMap2007['keyword'])
                    ->get([$ruleMap2001['keyword'],'BLMC'])->toArray();

                //如果入院记录不存在 并且 存在24小时入院记录或24小时死亡记录 跳过
                if (!$bl01 && (EMR_BL_BL01::query()->where("JZHM", '=', $p['MED_REC_ID'])
                    ->where(function ($query) {
                        $query->where('MBLB', 306)->orWhere('MBLB', 21);
                    })
                    ->get([$ruleMap2001['keyword'],'BLMC'])->toArray())) {
                    continue;
                }

                if (!$bl01) {
                    $errorContent[] = ["status" => 1, "content" => "住院天数【".$p['AAC04']."】"];
                    $errorContent[] = ["status" => 0, "content" => "入院记录【无】"];
                } else {
                    $tmpTime = $bl01[0][$ruleMap2001['keyword']] ?? 0;
                    $errorContent[] = ["status" => 1, "content" => "住院天数【".$p['AAC04']."】"];
                    $errorContent[] = ["status" => 1, "content" => "入院记录【".$bl01[0]['BLMC']."】"];
                    if (strtotime($tmpTime) < strtotime($p['AAB01'])) {
                        $errorContent[] = ["status" => 0, "content" => "入院记录【{$tmpTime}（提前创建）"];
                    } elseif (strtotime($tmpTime) > strtotime($p['AAB01']) + 24 * 3600) {
                        $errorContent[] = ["status" => 0, "content" => "入院记录【{$tmpTime}（超24小时）"];
                    } else {
                        $flag = 1;
                        $errorContent[] = ["status" => 1, "content" => "入院记录【{$tmpTime}（24小时内）"];
                    }
                }
                $fenzi[] = [
                    'ZYH' => $p['MED_REC_ID'],
                    'fenzi' => $flag,
                    'fenmu' => 1,
                    "content" =>
                    [["status" => $flag, "content" => $errorContent]]
                ];
            }

            // 指定住院号计算指标，则退出下次循环
            if ($zyh) {
                break;
            }
        }

        if ($fenzi) {
            $this->saveIndexRes($fenzi, "ryjlxswcl24");
        }
        IndexCatalog::query()->where("index_name", "=", "ryjlxswcl24")->update(["status" => 2, 'quality_time' => time()]);
    }

    public function bl8wcl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("scbcjlwc8", 1);
        $ruleMap2001 = RuleWordMap::query()->where("id", "=", 2001)->first()->toArray();
        $ruleMap2008 = RuleWordMap::query()->where("id", "=", 2008)->first()->toArray();

        $startTime = $start_time ?: $this->startTime;
        $end = $end_time ?: time() + 24 * 3600;
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > $end) {
                break;
            }
            $query = PatientInfo::query();
            if ($zyh) {
                $query = $query->where('MED_REC_ID', '=', $zyh);
            } else {
                $query = $query
                    ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                    ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime));
            }
            $patientInfo = $query->get(['MED_REC_ID', "AAC01", 'AAB01'])->toArray();
            $startTime = $entTime;

            foreach ($patientInfo as $p) {
                $flag = 0;
                // 住院天数小于1天的则过滤
                if (strtotime($p['AAC01']) - strtotime($p['AAB01']) < 24 * 3600) {
                    continue;
                }
                echo $p['MED_REC_ID'] . "\r\n";
                $errorContent = [];
                $errorContent[] = ["status" => 1, "content" => "入院时间【{$p['AAB01']}】"];
                $bl01 = EMR_BL_BL01::query()->where("JZHM", '=', $p['MED_REC_ID'])
                    ->where('MBLB', '=', $ruleMap2008['keyword'])
                    ->get([$ruleMap2001['keyword']])->toArray();

                //如果入院记录不存在  查看是否存在24小时入院记录或24小时死亡记录  如果存在跳过
                if (!$bl01 && (EMR_BL_BL01::query()->where("JZHM", '=', $p['MED_REC_ID'])
                    ->where(function ($query) {
                        $query->where('MBLB', 306)->orWhere('MBLB', 21);
                    })
                    ->get([$ruleMap2001['keyword']])->toArray())) {
                    continue;
                }

                if (!$bl01) {
                    $errorContent[] = ["status" => 0, "content" => "首次病程记录【无】"];
                } else {
                    $tmpTime = $bl01[0][$ruleMap2001['keyword']] ?? 0;
                    if (strtotime($tmpTime) < strtotime($p['AAB01'])) {
                        $errorContent[] = ["status" => 0, "content" => "首次病程记录【{$tmpTime}（提前创建）】"];
                    } elseif (strtotime($tmpTime) > strtotime($p['AAB01']) + 8 * 3600) {
                        $errorContent[] = ["status" => 0, "content" => "首次病程记录【{$tmpTime}（超8小时）】"];
                    } else {
                        $flag = 1;
                        $errorContent[] = ["status" => 1, "content" => "首次病程记录【{$tmpTime}（8小时内）】"];
                    }
                }
                $fenzi[] = ['ZYH' => $p['MED_REC_ID'], 'fenzi' => $flag, 'fenmu' => 1, "content" => [
                    ["status" => $flag, "content" => $errorContent]
                ]];
            }

            // 指定住院号计算指标，则退出下次循环
            if ($zyh) {
                break;
            }
        }

        if ($fenzi) {
            $this->saveIndexRes($fenzi, 'scbcjlwc8');
        }

        IndexCatalog::query()->where("index_name", "=", "scbcjlwc8")->update(["status" => 2, 'quality_time' => time()]);
    }

    /**
     * @param string $zyh
     * @return bool
     * 抢救记录及时记录率
     */
    public function qjjljll($zyh = "", $start_time = "", $end_time = "")
    {
        $ruleMap2000 = RuleWordMap::query()->where("id", "=", 2000)->first()->toArray();
        $ruleMap2001 = RuleWordMap::query()->where("id", "=", 2001)->first()->toArray();
        $ruleMap2002 = RuleWordMap::query()->where("id", "=", 2002)->first()->toArray();
        $ruleMap2005 = RuleWordMap::query()->where("id", "=", 2005)->first()->toArray();
        $ruleMap2006 = RuleWordMap::query()->where("id", "=", 2006)->first()->toArray();
        $keyword = explode(',', $ruleMap2000['keyword']);
        if (!$keyword) {
            return false;
        }

        $fdEsService = new ElasticsearchService('fee_detailed');
        $bl01esService = new ElasticsearchService('bl01_202303');
        $startTime = $start_time ?: $this->startTime;
        $end = $end_time ?: time() + 24 * 3600;
        $qualityRes = [];
        IndexCatalog::editStatus("qjjljsjl", 1);
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > $end) {
                break;
            }
            $query = PatientInfo::query();
            if ($zyh) {
                $query = $query->where('MED_REC_ID', '=', $zyh);
            } else {
                $query = $query
                    ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                    ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime));
            }
            $patientInfo = $query->get(['MED_REC_ID'])->toArray();

            $startTime = $entTime;
            $keyShould = [];
            if ($keyword) {
                foreach ($keyword as $kw) {
                    $keyShould[] = ['match_phrase' => ['FYMC' => $kw]];
                }
            }
            foreach ($patientInfo as $p) {
                echo $p['MED_REC_ID'] . "\r\n";
                // 收费名称含【ID2000  大抢救,小抢救 】，一个ZYH有几次算几次  【同一收费名称 总金额  收费+  退费-  两者相抵消】
                //        表fee_detailed -- 收费名称：FYMC
                //          表fee_detailed -- 总金额：ZJE
                //          表rule_word_map -- 质控字典：  ID2000           抢救费                 大抢救,小抢救       【BZMC抢救记录及时记录率】
                $params = $fdEsService->clearMust()
                    ->queryByMust(['term' => ['MED_REC_ID' => $p['MED_REC_ID']]])
                    ->queryByMust([
                        "bool" => [
                            "should" => $keyShould
                        ]
                    ])->paginate(1, 10000)->getParams();
                $res = app('es')->search($params);
                $resData = $fdEsService->getDataByEs($res);
                if (!$resData[1]) {
                    continue;
                }
                $fenzi = ['ZYH' => $p['MED_REC_ID'], 'fenzi' => 0, 'fenmu' => $resData[1], "content" => []];
                $fymc = $resData[0][0]['FYMC'];

                // 如果存在收费+  退费-  两者相抵消的情况，则不满足要求
                $zje = array_sum(array_column($resData[0], 'ZJE'));
                if (!$zje) {
                    continue;
                }

                //                病历标题【ID2002  抢救记录】提取  【ID2001 CJSJ】，通过【BLBH】关联blxg ，找病历内容中的  【ID2005   ID2006  抢救时间】，
                //                CJSJ   需在抢救时间     至  抢救时间  +6小时    时间段之内【    抢救时间＜  CJSJ  ≤ 抢救时间+6小时  】
                $params = $bl01esService->clearMust()
                    ->queryByMust(['term' => ['JZHM' => $p['MED_REC_ID']]])
                    ->queryByMust(['match_phrase' => ['BLMC' => $ruleMap2002['keyword']]])
                    ->source([$ruleMap2001['keyword'], 'HJNR', 'BLMC', "BLBH"])->getParams();
                $res = app('es')->search($params);
                $resData = $bl01esService->getDataByEs($res);
                if (!$resData[1]) {
                    $errorContent['status'] = 0;
                    $errorContent['content'] = [
                        ["status" => 1, "content" => "收费项目【" . $fymc . "】"],
                        ["status" => 0, "content" => "抢救记录【无】"],
                        ["status" => 0, "content" => "抢救结束时间【无】"],
                        ["status" => 0, "content" => "抢救记录完成时间【无】"],
                    ];
                    $fenzi["content"][] = $errorContent;
                } else {

                    $qjjl = $resData[0];
                    foreach ($qjjl as $item) {
                        $content = [];
                        $flag = 0;
                        $content[] = ["status" => 1, "content" => "收费项目【" . $fymc . "】"];


                        // 1、获取抢救记录的抢救时间，获取blmc中的时间
                        $BLMC = $item["BLMC"] ?: "";
                        // 将病例名称中的时间格式化成统一格式
                        $BLMC = str_replace('年', '-', str_replace('月', '-', str_replace('日', ' ', str_replace('时', ':', str_replace('分', '', $BLMC)))));
                        // 把时间通过正则匹配出来
                        preg_match_all("/([0-9|\-|\:|\s]+)/", $BLMC, $pregRes);
                        if (empty($pregRes[0])) {
                            $content[] = ["status" => 0, "content" => "抢救记录【有】【无】"];
                        } else {
                            $content[] = ["status" => 1, "content" => "抢救记录【有】【 " . $pregRes[1][0] . "】"];
                        }


                        // 2、判断病例内容中是否有抢救时间
                        $item['HJNR'] = str_replace("：", ":", $item['HJNR']);
                        $item['HJNR'] = str_replace(["{", "}", "(", ")"], "", $item['HJNR']);

                        preg_match_all('/(' . $ruleMap2005['keyword'] . ')+[\(\{]*([0-9|年|月|日|时|分|\)|\(|\-|\:|\s]+)[\)|\}|\s]*(' . $ruleMap2006['keyword'] . ')+/', $item['HJNR'], $matches);
                        if (empty($matches[0])) {
                            $content[] = ["status" => 0, "content" => "抢救结束时间【无】"];
                            // 没有抢救记录完成时间
                            if (!isset($item[$ruleMap2001['keyword']]) || empty($item[$ruleMap2001['keyword']]) || $item[$ruleMap2001['keyword']] == "1970-01-01 00:00:00") {
                                $content[] = ["status" => 0, "content" => "抢救记录完成时间【无】"];
                            } else {
                                $content[] = ["status" => 0, "content" => "抢救记录完成时间【" . $item[$ruleMap2001['keyword']] . "（无法校验）】"];
                            }
                        } else {
                            $date_str = $matches[2][0];
                            $content[] = ["status" => 1, "content" => "抢救结束时间【" . $date_str . "】"];

                            if (!isset($item[$ruleMap2001['keyword']]) || empty($item[$ruleMap2001['keyword']]) || $item[$ruleMap2001['keyword']] == "1970-01-01 00:00:00") {
                                $content[] = ["status" => 0, "content" => "抢救记录完成时间【无】"];
                            } else {
                                $timestamp = str_replace('年', '-', str_replace('月', '-', str_replace('日', ' ', str_replace('时', ':', str_replace('分', '', $date_str)))));

                                // 判断抢救记录中的时间是否是一个完整的时间格式，如果不是，则需要拼接病例名称中的时间
                                if (strpos($timestamp, '-') === false && !empty($pregRes[0])) {
                                    $timestamp = date("Y-m-d", strtotime($pregRes[0][0])) . $timestamp;
                                }
                                var_dump($timestamp);
                                $timestamp = strtotime($timestamp);
                                var_dump($item[$ruleMap2001['keyword']]);

                                $cjsj = strtotime($item[$ruleMap2001['keyword']]);
                                if ($timestamp < $cjsj && $cjsj < $timestamp + 6 * 3600) {
                                    $flag = 1;
                                    $fenzi["fenzi"] += 1; // 分子+1
                                    $content[] = ["status" => 1, "content" => "抢救记录完成时间【" . $item[$ruleMap2001['keyword']] . "（六小时内）】"];
                                } else {
                                    $content[] = ["status" => 0, "content" => "抢救记录完成时间【" . $item[$ruleMap2001['keyword']] . "（超六小时）】"];
                                }
                            }
                        }

                        $errorContent['status'] = $flag;
                        $errorContent['content'] = $content;
                        $fenzi["content"][] = $errorContent;
                    }
                }
                $qualityRes[] = $fenzi;
            }


            // 指定住院号计算指标，则退出下次循环
            if ($zyh) {
                break;
            }
        }

        if ($qualityRes) {
            $this->saveIndexRes($qualityRes, "qjjljsjl");
        }
        IndexCatalog::query()->where("index_name", "=", "qjjljsjl")->update(["status" => 2, 'quality_time' => time()]);
    }

    /**
     * @param string $zyh
     * @return bool
     * 抢救记录审核率
     */
    public function qjjlshl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("qjjlsh", 1);
        $ruleMap2001 = RuleWordMap::query()->where("id", "=", 2001)->first()->toArray();
        $ruleMap2002 = RuleWordMap::query()->where("id", "=", 2002)->first()->toArray();
        $ruleMap2003 = RuleWordMap::query()->where("id", "=", 2003)->first()->toArray();
        $ruleMap2004 = RuleWordMap::query()->where("id", "=", 2004)->first()->toArray();

        $bl01esService = new ElasticsearchService('bl01_202303');
        $startTime = $start_time ?: $this->startTime;
        $end = $end_time ?: time() + 24 * 3600;
        $qualityRes = [];

        $staff = Staff::query()->get()->toArray();
        $staffArr = array_column($staff, "name", "code");
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > $end) {
                break;
            }
            $query = PatientInfo::query();
            if ($zyh) {
                $query = $query->where('MED_REC_ID', '=', $zyh);
            } else {
                $query = $query
                    ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                    ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime));
            }
            $patientInfo = $query->get(['MED_REC_ID'])->toArray();

            $startTime = $entTime;
            foreach ($patientInfo as $p) {

                $fenzi = ['ZYH' => $p['MED_REC_ID'], 'fenzi' => 0, 'fenmu' => 0, "content" => []];
                //                病历标题blmc【ID2002   抢救记录】，一个ZYH有几次算几次
                $params = $bl01esService->clearMust()
                    ->queryByMust(['term' => ['JZHM' => $p['MED_REC_ID']]])
                    ->queryByMust(['match_phrase' => ['BLMC' => $ruleMap2002['keyword']]])
                    ->source([$ruleMap2001['keyword'], 'HJNR', 'BLMC', "BLBH"])->getParams();
                $res = app('es')->search($params);
                $resData = $bl01esService->getDataByEs($res);

                if (!$resData[1]) {
                    continue;
                } else {

                    $qjjl = $resData[0];
                    foreach ($qjjl as $item) {
                        $content = [];
                        $flag = 0;
                        $content[] = ["status" => 1, "content" => "抢救记录【" . $item['BLMC'] . "】"];

                        // 通过【BLBH】关联blxg ，找病历内容中的（ID2003  ID2004  主持抢救医师   示例：刘梦萱 第一个抢救者   ），
                        $item['HJNR'] = str_replace("：", ":", $item['HJNR']);
                        $item['HJNR'] = str_replace("，", ",", $item['HJNR']);
                        $item['HJNR'] = str_replace(["{", "}", "(", ")"], "", $item['HJNR']);
                        preg_match_all('/(' . $ruleMap2003['keyword'] . ')+[\(\{]*(.*?)(' . $ruleMap2004['keyword'] . ')+/', $item['HJNR'], $matches);

                        $blsy = EMR_BL_BLSY::query()->where('BLBH', '=', $item['BLBH'])->get()->toArray();
                        if (empty($matches[0])) {
                            $content[] = ["status" => 0, "content" => "主持抢救医师【无】"];
                            $content[] = ["status" => 1, "content" => "抢救记录签名【" . implode(',', array_column($blsy, 'SYYS')) . "】"];
                        } else {

                            $date_str = $matches[2][0];
                            $content[] = ["status" => 1, "content" => "主持抢救医师【{$date_str}】"];
                            if (in_array(array_search($date_str, $staffArr), array_column($blsy, 'SYYS'))) {
                                $content[] = ["status" => 1, "content" => "抢救记录签名【{$date_str}】"];
                                $flag = 1;
                                $fenzi["fenzi"] += 1;
                            } else {

                                $content[] = ["status" => 1, "content" => "抢救记录签名【" . implode(',', array_column($blsy, 'SYYS')) . "】"];
                            }
                        }
                        $errorContent['status'] = $flag;
                        $errorContent['content'] = $content;
                        $fenzi["fenmu"] += 1; // 分母+1
                        $fenzi["content"][] = $errorContent;
                    }
                }
                $qualityRes[] = $fenzi;
            }

            // 指定住院号计算指标，则退出下次循环
            if ($zyh) {
                break;
            }
        }

        if ($qualityRes) {
            $this->saveIndexRes($qualityRes, "qjjlsh");
        }
        IndexCatalog::query()->where("index_name", "=", "qjjlsh")->update(["status" => 2, 'quality_time' => time()]);
    }

    // 医师查房记录完整率
    // update patient_info_target set denominator_chafangCompletionRate=0,numerator_chafangCompletionRate=0,chafangCompletionRate_error="";
    public function chafangCompletionRate($zyh = "", $start_time = "", $end_time = "")
    {
        $setName = 'quality_zb_chafangCompletionRate';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $staff = Staff::query()->get()->toArray();
        $staffArr = [];
        foreach ($staff as $v) {
            $staffArr[$v['code']] = $v;
        }

        $page = 1;
        $pageSize = 100000;
        $piEesService = new ElasticsearchService('patient_info');
        $mzjlesService = new ElasticsearchService('mzjl_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');
        $sssqesService = new ElasticsearchService('sssq_2023');
        $zyHcmxEsService = new ElasticsearchService('zy_hcmx');
        $startTime = $this->startTime;
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > time()) {
                break;
            }
            var_dump('时间：' . date("Y-m-d H:i:s", $startTime));
            $params = $piEesService->paginate($page, $pageSize)
                ->queryByMust(['range' => ['AAC01' => ['gte' => date("Y-m-d H:i:s", $startTime), 'lte' => date("Y-m-d H:i:s", $entTime)]]])
                ->source(['MED_REC_ID', 'AAB01', 'AAC01', 'AAC04', 'AAC01'])
                ->getParams();
            $res = app('es')->search($params);
            $res = $piEesService->getDataByEs($res);
            $startTime = $entTime;
            if (empty($res[0])) {
                continue;
            }
            $fenzi = [];
            $patientInfo = $res[0];

            // 手术申请单
            $zyh = array_column($patientInfo, 'MED_REC_ID');
            $sssqMust = [
                'terms' => [
                    "ZYH" => $zyh
                ]
            ];
            $params = $sssqesService->clearMust()->queryByMust($sssqMust)->source(['ZYH', 'NSSMC'])->getParams();
            $mzRes = app('es')->search($params);
            $mzRes = $sssqesService->getDataByEs($mzRes);
            $sssqData = [];
            if (!empty($mzRes[0])) {
                foreach ($mzRes[0] as $m) {
                    $sssqData[$m['ZYH']][] = $m;
                }
            }

            // 护士分床时间
            $params = $zyHcmxEsService->clearMust()->paginate(1, 10000)
                ->queryByMust(['terms' => ["ZYH" => $zyh]])
                ->queryByMust(['term' => ["HCLX" => 0]])
                ->source(['ZYH', 'HCRQ'])->getParams();
            $mzRes = app('es')->search($params);
            $mzRes = $zyHcmxEsService->getDataByEs($mzRes);
            $zyHcmxData = [];
            if (!empty($mzRes[0])) {
                $zyHcmxData = array_column($mzRes[0], null, 'ZYH');
            }

            // 医嘱本
            $yzb = Yzb::query()->whereIn('ZYH', $zyh)
                ->whereIn('YDYZLB', [303, 305])
                ->get(['ZYH', 'XZJDSJ'])->toArray();
            $yzb = array_column($yzb, null, 'ZYH');

            foreach ($patientInfo as $p) {
                $lastId = $p['AAC01'];
                var_dump($lastId);
                $sssqError = '';
                $flag = 1;
                $operateCompletionRateError = [];
                $sssq = empty($sssqData[$p['MED_REC_ID']]) ? [] : $sssqData[$p['MED_REC_ID']];

                $error = '';
                if ($sssq) {
                    $sssqError = [];
                    foreach ($sssq as $s) {
                        $sqe = '';
                        // 通过病历文本搜“手术记录”，获取“术者姓名”，再去匹配“工号”
                        $bl01must = [];
                        $bl01must[] = [
                            'term' => [
                                "JZHM" => $p['MED_REC_ID']
                            ]
                        ];
                        $bl01must[] = [
                            'match_phrase' => [
                                "HJNR" => "手术记录"
                            ]
                        ];
                        $bl01must[] = [
                            'match_phrase' => [
                                "HJNR" => $s['NSSMC']
                            ]
                        ];
                        $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['BLBH', 'operation_time', 'operation_handler', 'operation_handler_code'])->getParams();
                        $bl01Res = app('es')->search($params);
                        $bl01Res = $bl01esService->getDataByEs($bl01Res);

                        if (empty($bl01Res[0]) || empty($bl01Res[0][0]) || empty($bl01Res[0][0]['operation_handler'])) {
                            $flag = 0;
                            $sqe .= '手术记录【无】';
                        } else {

                            // 获取“麻醉记录单中手术开始时间”
                            $operationHandler = $bl01Res[0][0]['operation_handler']; // 术者
                            $operationHandlerCode = $bl01Res[0][0]['operation_handler_code']; // 术者工号
                            $BLBH = $bl01Res[0][0]['BLBH']; // 病例编号
                            $operationTime = $bl01Res[0][0]['operation_time']; // 手术时间

                            $sqe .= '术者【EMR_BL_BLXG ' . $operationHandler . '】';
                            $mzjlmust = [];
                            $mzjlmust[] = [
                                'term' => [
                                    "HOSPIZATIONID" => $p['MED_REC_ID']
                                ]
                            ];
                            $mzjlmust[] = [
                                'term' => [
                                    "PREOPERATIONNAME" => $s['NSSMC']
                                ]
                            ];
                            $params = $mzjlesService->clearMust()->queryByMustBatch($mzjlmust)->source(['OPERATESTARTTIME', 'OPERATEENDTIME'])->getParams();
                            $mzRes = app('es')->search($params);
                            $mzRes = $mzjlesService->getDataByEs($mzRes);

                            if (empty($mzRes[0])) {
                                $flag = 0;
                                $sqe .= "麻醉记录【无】";
                            } else {
                                // 术前病程
                                $startTime = $mzRes[0][0]['OPERATESTARTTIME'];
                                $endTime = date("Y-m-d H:i:s", strtotime($startTime) - 24 * 3600);
                                $bl01must = [];
                                $bl01must[] = [
                                    'term' => [
                                        "JZHM" => $p['MED_REC_ID']
                                    ]
                                ];
                                $bl01must[] = [
                                    'term' => [
                                        "BLLB" => 294
                                    ]
                                ];
                                $bl01must[] = [
                                    'range' => [
                                        "CJSJ" => [
                                            'from' => $endTime,
                                            'to' => $startTime
                                        ]
                                    ]
                                ];
                                $bl01must[] = [
                                    'match_phrase' => [
                                        "HJNR" => $operationHandler
                                    ]
                                ];
                                $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['CJSJ'])->getParams();
                                $bl01Res = app('es')->search($params);
                                $bl01Res = $bl01esService->getDataByEs($bl01Res);

                                if (empty($bl01Res[0]) || empty($bl01Res[0][0])) {
                                    $flag = 0;
                                    $sqe .= '，术前病程【无】';
                                } else {
                                    $sqe .= '，术前病程【' . $bl01Res[0][0]['CJSJ'] . '、术前24h内】';
                                }

                                // 术后病程
                                $startTime = $mzRes[0][0]['OPERATEENDTIME'];
                                $endTime = date("Y-m-d H:i:s", strtotime($startTime) + 24 * 3600);
                                $bl01must = [];
                                $bl01must[] = [
                                    'term' => [
                                        "JZHM" => $p['MED_REC_ID']
                                    ]
                                ];
                                $bl01must[] = [
                                    'term' => [
                                        "BLLB" => 294
                                    ]
                                ];
                                $bl01must[] = [
                                    'range' => [
                                        "CJSJ" => [
                                            'from' => $startTime,
                                            'to' => $endTime
                                        ]
                                    ]
                                ];
                                $bl01must[] = [
                                    'match_phrase' => [
                                        "HJNR" => $operationHandler
                                    ]
                                ];
                                $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['CJSJ'])->getParams();
                                $bl01Res = app('es')->search($params);
                                $bl01Res = $bl01esService->getDataByEs($bl01Res);
                                if (empty($bl01Res[0]) || empty($bl01Res[0][0])) {
                                    $flag = 0;
                                    $sqe .= '，手术病程【无】';
                                } else {
                                    $sqe .= '，手术病程【' . $bl01Res[0][0]['CJSJ'] . '、术后24h内】';

                                    // 4、手术时间术后3天有病程
                                    $cysj = strtotime(date("Y-m-d", strtotime($p['AAC01'])));
                                    $bingcheng1 = 0;
                                    $bingcheng2 = 0;
                                    $bingcheng3 = 0;
                                    // 如果当天出院则只需要出院当天的病程记录
                                    if ($cysj == $operationTime) {
                                        $bl01must = [];
                                        $bl01must[] = [
                                            'term' => [
                                                "JZHM" => $p['MED_REC_ID']
                                            ]
                                        ];
                                        $bl01must[] = [
                                            'term' => [
                                                "BLLB" => 294
                                            ]
                                        ];
                                        $bl01must[] = [
                                            'range' => [
                                                "CJSJ" => [
                                                    'from' => date("Y-m-d H:i:s", $operationTime),
                                                    'to' => date("Y-m-d H:i:s", $operationTime + 24 * 3600)
                                                ]
                                            ]
                                        ];
                                        $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['CJSJ'])->getParams();
                                        $bl01Res = app('es')->search($params);
                                        $bl01Res = $bl01esService->getDataByEs($bl01Res);
                                        $bingcheng1 = empty($bl01Res[0][0]) ? [] : $bl01Res[0][0];
                                        $bingcheng2 = ['CJSJ' => '-'];
                                        $bingcheng3 = ['CJSJ' => '-'];
                                    } else {
                                        if ($cysj && $cysj >= $operationTime + 24 * 3600) {

                                            $bl01must = [];
                                            $bl01must[] = [
                                                'term' => [
                                                    "JZHM" => $p['MED_REC_ID']
                                                ]
                                            ];
                                            $bl01must[] = [
                                                'term' => [
                                                    "BLLB" => 294
                                                ]
                                            ];
                                            $bl01must[] = [
                                                'range' => [
                                                    "CJSJ" => [
                                                        'from' => date("Y-m-d H:i:s", $operationTime + 24 * 3600),
                                                        'to' => date("Y-m-d H:i:s", $operationTime + 24 * 3600 * 2)
                                                    ]
                                                ]
                                            ];
                                            $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['CJSJ'])->getParams();
                                            $bl01Res = app('es')->search($params);
                                            $bl01Res = $bl01esService->getDataByEs($bl01Res);
                                            $bingcheng1 = empty($bl01Res[0][0]) ? [] : $bl01Res[0][0];
                                            $bingcheng2 = ['CJSJ' => '-'];
                                            $bingcheng3 = ['CJSJ' => '-'];
                                        }
                                        if ($cysj && $cysj >= $operationTime + 2 * 24 * 3600) {

                                            $bl01must = [];
                                            $bl01must[] = [
                                                'term' => [
                                                    "JZHM" => $p['MED_REC_ID']
                                                ]
                                            ];
                                            $bl01must[] = [
                                                'term' => [
                                                    "BLLB" => 294
                                                ]
                                            ];
                                            $bl01must[] = [
                                                'range' => [
                                                    "CJSJ" => [
                                                        'from' => date("Y-m-d H:i:s", $operationTime + 24 * 3600 * 2),
                                                        'to' => date("Y-m-d H:i:s", $operationTime + 24 * 3600 * 3)
                                                    ]
                                                ]
                                            ];
                                            $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['CJSJ'])->getParams();
                                            $bl01Res = app('es')->search($params);
                                            $bl01Res = $bl01esService->getDataByEs($bl01Res);
                                            $bingcheng2 = empty($bl01Res[0][0]) ? [] : $bl01Res[0][0];
                                            $bingcheng3 = ['CJSJ' => '-'];
                                        }
                                        if ($cysj && $cysj >= $operationTime + 3 * 24 * 3600) {

                                            $bl01must = [];
                                            $bl01must[] = [
                                                'term' => [
                                                    "JZHM" => $p['MED_REC_ID']
                                                ]
                                            ];
                                            $bl01must[] = [
                                                'term' => [
                                                    "BLLB" => 294
                                                ]
                                            ];
                                            $bl01must[] = [
                                                'range' => [
                                                    "CJSJ" => [
                                                        'from' => date("Y-m-d H:i:s", $operationTime + 24 * 3600 * 3),
                                                        'to' => date("Y-m-d H:i:s", $operationTime + 24 * 3600 * 4)
                                                    ]
                                                ]
                                            ];
                                            $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['CJSJ'])->getParams();
                                            $bl01Res = app('es')->search($params);
                                            $bl01Res = $bl01esService->getDataByEs($bl01Res);
                                            $bingcheng3 = empty($bl01Res[0][0]) ? [] : $bl01Res[0][0];
                                        }
                                    }

                                    if (!$bingcheng1 || !$bingcheng2 || !$bingcheng3) {
                                        $sqe .= '，术后病程【术后病程记录（无）】';
                                        $flag = 0;
                                    } else {
                                        $sqe .= '，术后病程【3天，' . $bingcheng1['CJSJ'] . '、' . $bingcheng2['CJSJ'] . '、' . $bingcheng3['CJSJ'] . '、】';
                                    }
                                }
                            }
                        }
                        $sssqError[] = $sqe;
                    }
                    if ($sssqError) {

                        $errorContent = '';
                        foreach ($sssqError as $k => $item) {
                            $errorContent .= ($k + 1) . '：' . $item;
                        }
                        $error .= $errorContent . '，';
                    }
                }
                // 如果上面的条件不正确则错误信息也不显示
                if (!$flag) {
                    $error = '';
                    $flag = 1;
                }

                if (empty($zyHcmxData[$p['MED_REC_ID']])) {
                    $error .= '护士分床数据不存在';
                }
                if (empty($yzb[$p['MED_REC_ID']])) {
                    $error .= '，医嘱本信息不存在';
                }
                if ($p['AAC04'] && !empty($zyHcmxData[$p['MED_REC_ID']]) && !empty($yzb[$p['MED_REC_ID']])) {
                    $zhengName = $fuName = '';
                    $error .= '，住院天数【' . $p['AAC04'] . '】';
                    $error .= '，分床时间：' . $zyHcmxData[$p['MED_REC_ID']]['HCRQ'] . '-' . $yzb[$p['MED_REC_ID']]['XZJDSJ'];
                    if (
                        !empty($yzb[$p['MED_REC_ID']]['XZJDSJ']) &&
                        !empty($zyHcmxData[$p['MED_REC_ID']]['HCRQ']) &&
                        strtotime($yzb[$p['MED_REC_ID']]['XZJDSJ']) > 0 &&
                        strtotime($zyHcmxData[$p['MED_REC_ID']]['HCRQ']) > 0
                    ) {
                        $startTime = $fenchuangTime = $zyHcmxData[$p['MED_REC_ID']]['HCRQ'];
                        $endTime = $yzb[$p['MED_REC_ID']]['XZJDSJ'];

                        // 住院天数大于7天
                        if ($p['AAC04'] > 7) {
                            while (true) {
                                $fromTime = $startTime;
                                $toTime = date("Y-m-d H:i:s", strtotime($startTime) + 7 * 24 * 3600);

                                if (strtotime($toTime) > strtotime($endTime)) {
                                    break;
                                }

                                $bl01must = [
                                    ['term' => ["JZHM" => $p['MED_REC_ID']]],
                                    ['term' => ["BLLB" => 294]],
                                    ['range' => ["CJSJ" => ['from' => $fromTime, 'to' => $toTime]]]
                                ];
                                $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ'])->getParams();
                                $bl01Res = app('es')->search($params);
                                $bl01Res = $bl01esService->getDataByEs($bl01Res);
                                $bl01294 = empty($bl01Res[0]) ? [] : $bl01Res[0];

                                //                                    获取7天所有病程中的所有正副科的数据
                                $zheng = $fu = $zhong = [];
                                foreach ($bl01294 as $item) {

                                    $blsy = EMR_BL_BLSY::query()
                                        ->where('BLBH', '=', $item['BLBH'])
                                        ->get()->toArray();
                                    $code = array_column($blsy, 'SYYS');
                                    if ($blsy) {
                                        $staff = Staff::query()->whereIn('code', $code)->get()->toArray();
                                        foreach ($staff as $b) {
                                            if ($b['ygjb'] == 1) {
                                                $zhengName = $b['name'];
                                                $zheng[] = $item['CJSJ'];
                                            } elseif ($b['ygjb'] == 2) {
                                                $fuName = $b['name'];
                                                $fu[] = $item['CJSJ'];
                                            } elseif ($b['ygjb'] == 6) {
                                                $zhong[] = $item['CJSJ'];
                                            }
                                        }
                                    }
                                }

                                if (!empty($zheng)) {
                                    $error .= '，正高查房【' . count($zheng) . '次/7d，' . implode(',', $zheng) . '】';
                                }
                                if (!empty($fu)) {
                                    $error .= '，副高查房【' . count($fu) . '次/7d，' . implode(',', $fu) . '】';
                                }
                                if (!empty($zhong)) {
                                    $error .= '，中级职称查房【' . count($zhong) . '次/7d，' . implode(',', $zhong) . '】';
                                }

                                $startTime = $toTime;
                            }
                        }

                        $startDay = date('z', strtotime($startTime));
                        $endDay = date('z', strtotime($endTime));
                        $diffDay = $endDay - $startDay;
                        if ($diffDay >= 3) {

                            $bl01must = [
                                ['term' => ["JZHM" => $p['MED_REC_ID']]],
                                ['term' => ["BLLB" => 294]],
                                ['range' => ["CJSJ" => ['from' => $startTime, 'to' => $endTime]]]
                            ];
                            $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ'])->getParams();
                            $bl01Res = app('es')->search($params);
                            $bl01Res = $bl01esService->getDataByEs($bl01Res);
                            $bl01294 = empty($bl01Res[0]) ? [] : $bl01Res[0];

                            //                                    获取7天所有病程中的所有正副科的数据
                            $zheng = $fu = $zhong = [];
                            foreach ($bl01294 as $item) {

                                $blsy = EMR_BL_BLSY::query()
                                    ->where('BLBH', '=', $item['BLBH'])
                                    ->get()->toArray();
                                $code = array_column($blsy, 'SYYS');
                                if ($blsy) {
                                    $staff = Staff::query()->whereIn('code', $code)->get()->toArray();
                                    foreach ($staff as $b) {
                                        if ($b['ygjb'] == 1) {
                                            $zhengName = $b['name'];
                                            $zheng[] = $item['CJSJ'];
                                        } elseif ($b['ygjb'] == 2) {
                                            $fuName = $b['name'];
                                            $fu[] = $item['CJSJ'];
                                        } elseif ($b['ygjb'] == 6) {
                                            $zhong[] = $item['CJSJ'];
                                        }
                                    }
                                }
                            }

                            if (!empty($zheng)) {
                                $error .= '，正高查房【' . count($zheng) . '次/' . $diffDay . 'd，' . implode(',', $zheng) . '】';
                            }
                            if (!empty($fu)) {
                                $error .= '，副高查房【' . count($fu) . '次/' . $diffDay . 'd，' . implode(',', $fu) . '】';
                            }
                            if (!empty($zhong)) {
                                $error .= '，中级职称查房【' . count($zhong) . '次/' . $diffDay . 'd，' . implode(',', $zhong) . '】';
                            }
                        }

                        // 出院前48小时内的病程记录
                        $bl01must = [
                            ['term' => ["JZHM" => $p['MED_REC_ID']]],
                            ['term' => ["BLLB" => 294]],
                            ['range' => ["CJSJ" => ['from' => date('Y-m-d H:i:s', strtotime($endTime) - 48 * 3600), 'to' => $endTime]]]
                        ];
                        $params = $bl01esService->clearMust()->queryByMustNot(['term' => ["MBLB" => 295]])->queryByMustBatch($bl01must)->source(['CJSJ'])->getParams();
                        $bl01Res = app('es')->search($params);
                        $bl01Res = $bl01esService->getDataByEs($bl01Res);
                        $bl01294 = empty($bl01Res[0]) ? [] : $bl01Res[0];

                        // 24小时出入院记录BLLB=18
                        $bl01must = [
                            ['term' => ["JZHM" => $p['MED_REC_ID']]],
                            ['term' => ["BLLB" => 18]]
                        ];
                        $params = $bl01esService->clearMust()->queryByMustNot(['term' => ["MBLB" => 295]])->queryByMustBatch($bl01must)->source(['CJSJ'])->getParams();
                        $bl01Res = app('es')->search($params);
                        $bl01Res = $bl01esService->getDataByEs($bl01Res);
                        $bl0118 = empty($bl01Res[0]) ? [] : $bl01Res[0];

                        if (empty($bl01294) && empty($bl0118)) {
                            $flag = 0;
                            $error .= '，出院48小时内病程【无】';
                        } elseif (!empty($bl01294)) {
                            $error .= '，出院48小时内病程【' . $bl01294[0]['CJSJ'] . '】';
                        } elseif ($bl0118) {
                            $error .= '，24小时出入院';
                        }

                        // 护士分床后48小时内，病程记录中，需要有一个 副高或正高签名（首次病程除外）
                        $bl01must = [
                            ['term' => ["JZHM" => $p['MED_REC_ID']]],
                            ['term' => ["BLLB" => 294]],
                            ['match_phrase' => ["HJNR" => $zhengName]],
                            ['range' => ["CJSJ" => ['from' => $fenchuangTime, 'to' => date('Y-m-d H:i:s', strtotime($fenchuangTime) + 48 * 3600)]]]
                        ];
                        $params = $bl01esService->clearMust()->queryByMustNot(['term' => ["MBLB" => 295]])->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ'])->getParams();
                        $bl01Res = app('es')->search($params);
                        $bl01Res = $bl01esService->getDataByEs($bl01Res);
                        $bl01294Zheng = empty($bl01Res[0]) ? [] : $bl01Res[0];

                        $bl01294Fu = [];
                        if ($fuName) {
                            $bl01must = [
                                ['term' => ["JZHM" => $p['MED_REC_ID']]],
                                ['term' => ["BLLB" => 294]],
                                ['match_phrase' => ["HJNR" => $fuName]],
                                ['range' => ["CJSJ" => ['from' => $fenchuangTime, 'to' => date('Y-m-d H:i:s', strtotime($fenchuangTime) + 48 * 3600)]]]
                            ];
                            $params = $bl01esService->clearMust()->queryByMustNot(['term' => ["MBLB" => 295]])->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ'])->getParams();
                            $bl01Res = app('es')->search($params);
                            $bl01Res = $bl01esService->getDataByEs($bl01Res);
                            $bl01294Fu = empty($bl01Res[0]) ? [] : $bl01Res[0];
                        }
                        if (empty($zhengName) && empty($fuName) && empty($bl01294Zheng) && empty($bl01294Fu) && empty($bl0118)) {
                            $flag = 0;
                            $error .= '，入院48小时内正或副高查房【无】';
                        } elseif ($bl01294Zheng || $bl01294Fu) {
                            $error .= '，入院48小时内正或副高查房【';
                            $total = [];
                            $total = array_merge($total, array_column($bl01294Zheng, 'BLBH'));
                            $total = array_merge($total, array_column($bl01294Fu, 'BLBH'));
                            $error .= count(array_unique($total)) . '、';
                            if ($bl01294Zheng) {
                                foreach ($bl01294Zheng as $item) {
                                    $error .= '正' . $item['CJSJ'] . '、';
                                }
                            }
                            if ($bl01294Fu) {
                                foreach ($bl01294Fu as $item) {
                                    $error .= '副' . $item['CJSJ'] . '、';
                                }
                            }
                            $error .= '】';
                        } elseif ($bl0118) {
                            $error .= '，护士分床后48小时内有24小时出入院';
                        }
                    }
                } else {
                    $flag = 0;
                    $error .= '，住院天数【无】';
                }
                $operateCompletionRateError[] = $error;

                $kjywErrorContent = '';
                foreach ($operateCompletionRateError as $k => $item) {
                    $kjywErrorContent .= ($k + 1) . '：' . $item;
                }

                $kjywErrorContent = $kjywErrorContent ?: $sssqError;
                $fenzi[] = ['ZYH' => $p['MED_REC_ID'], 'numerator_chafangCompletionRate' => $flag, 'chafangCompletionRate_error' => $kjywErrorContent];
            }

            if ($fenzi) {
                foreach ($fenzi as $d) {
                    $d['ZYH'] = (string)$d['ZYH'];
                    PatientInfoTarget::query()->updateOrInsert(
                        ['ZYH' => $d['ZYH']],
                        [
                            'denominator_chafangCompletionRate' => 1,
                            'numerator_chafangCompletionRate' => $d['numerator_chafangCompletionRate'],
                            'chafangCompletionRate_error' => $d['chafangCompletionRate_error']
                        ]
                    );
                }
                var_dump($page);
            }
        }
    }

    /**
     * 手术记录24小时内完成率（MER-TL-02）
     * update patient_info_target set denominator_operateCompletionRate=0,numerator_operateCompletionRate=0,operateCompletionRate_error="";
     * ALTER TABLE `quality`.`patient_info_target`
     * ADD COLUMN `denominator_operateCompletionRate` tinyint(1) NOT NULL DEFAULT 0 COMMENT '手术记录24小时内完成率分母' AFTER `ryjl_error`,
     * ADD COLUMN `numerator_operateCompletionRate` tinyint(1) NOT NULL DEFAULT 0 COMMENT '手术记录24小时内完成率分子' AFTER `denominator_operateCompletionRate`,
     * ADD COLUMN `operateCompletionRate_error` text NULL AFTER `numerator_operateCompletionRate`,
     * ADD COLUMN `denominator_chafangCompletionRate` tinyint(1) NOT NULL DEFAULT 0 COMMENT '医师查房记录完整率分母',
     * ADD COLUMN `numerator_chafangCompletionRate` tinyint(1) NOT NULL DEFAULT 0 COMMENT '医师查房记录完整率分子',
     * ADD COLUMN `chafangCompletionRate_error` text NULL;
     */
    public
    function operateCompletionRate($zyh = "", $start_time = "", $end_time = "")
    {
        $page = 1;
        $pageSize = 100000;
        $piEesService = new ElasticsearchService('patient_info');
        $mzjlesService = new ElasticsearchService('mzjl_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');
        $sssqesService = new ElasticsearchService('sssq_2023');
        $startTime = $this->startTime;
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > time()) {
                break;
            }
            var_dump('时间：' . date("Y-m-d H:i:s", $startTime));
            $patientInfo = PatientInfo::query()
                ->where('AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                ->where('AAC01', '<=', date("Y-m-d H:i:s", $entTime))
                ->get(['MED_REC_ID', 'id', 'AAC01'])->toArray();
            $startTime = $entTime;
            if (empty($patientInfo)) {
                continue;
            }
            $fenzi = [];
            foreach ($patientInfo as $p) {
                $lastId = $p['MED_REC_ID'];
                var_dump($lastId);
                // 获取手术申请单，如果存在，则满足分母的条件
                $mzRes = SSSQ::query()->where(['ZYH' => $p['MED_REC_ID'], 'ZFBZ' => 0])->get()->toArray();
                if (empty($mzRes)) {
                    continue;
                }

                $operateCompletionRateError = [];
                $flag = 1;
                $sssq = $mzRes;
                // 获取手术申请相关的麻醉记录信息
                foreach ($sssq as $item) {
                    $error = '手术名称【' . $item['NSSMC'] . '】';

                    $mzRes = mzjl::query()->where(['HOSPIZATIONID' => $p['MED_REC_ID'], 'PREOPERATIONNAME' => $item['NSSMC']])->get()->toArray();
                    $ssstartTime = 0;
                    if (empty($mzRes)) {
                        $error .= "麻醉记录【无】";
                    } else {
                        $ssstartTime = strtotime($mzRes[0]['OPERATEENDTIME']);
                        $error .= '，手术结束时间（手麻）【' . $mzRes[0]['OPERATEENDTIME'] . '】';
                    }

                    // 获取手术记录
                    $bl01must = [];
                    $bl01must[] = ['term' => ["JZHM" => $p['MED_REC_ID']]];
                    $bl01must[] = ['term' => ["BLLB" => 303]];
                    $bl01Should = [];
                    $bl01Should[] = ['terms' => ["MBLB" => [306, 74]]];
                    $bl01Should[] = ['match_phrase' => ["BLMC" => "手术记录"]];
                    $params = $bl01esService->clearMust()->queryByShouldBatch($bl01Should)->queryByMustBatch($bl01must)->minimumShouldMatch(1)->source(['CJSJ', 'MBLB', 'operation_end_time'])->getParams();
                    $bl01Res = app('es')->search($params);
                    $bl01Res = $bl01esService->getDataByEs($bl01Res);
                    if (empty($bl01Res[0]) || empty($bl01Res[0][0])) {
                        $flag = 0;
                        $error .= '，手术记录【无】';
                    } else {
                        if (empty($ssstartTime) && !empty($bl01Res[0][0]['operation_end_time'])) {
                            $ssstartTime = $bl01Res[0][0]['operation_end_time'];
                            $error .= '，手术结束时间【' . date("Y-m-d H:i:s", $bl01Res[0][0]['operation_end_time']) . '】';
                        }
                        $cjsj = strtotime($bl01Res[0][0]['CJSJ']);
                        $error .= '，手术记录【' . $bl01Res[0][0]['CJSJ'] . '';
                        if ($ssstartTime) {
                            $endTime = $ssstartTime + 24 * 3600;

                            if ($cjsj > $endTime) {
                                $flag = 0;
                                $error .= '、创建时间超24小时】';
                            } else {
                                $error .= '、24小时内】';
                            }
                        } else {
                            $flag = 0;
                            $error .= '，手术结束时间【无】';
                        }
                    }

                    $operateCompletionRateError[] = $error;
                }

                $kjywErrorContent = '';
                foreach ($operateCompletionRateError as $k => $item) {
                    $kjywErrorContent .= ($k + 1) . '：' . $item;
                }
                $fenzi[] = ['ZYH' => $p['MED_REC_ID'], 'numerator_operateCompletionRate' => $flag, 'operateCompletionRate_error' => $kjywErrorContent];
            }

            if ($fenzi) {
                foreach ($fenzi as $d) {
                    $d['ZYH'] = (string)$d['ZYH'];
                    PatientInfoTarget::query()->updateOrInsert(
                        ['ZYH' => $d['ZYH']],
                        [
                            'denominator_operateCompletionRate' => 1,
                            'numerator_operateCompletionRate' => $d['numerator_operateCompletionRate'],
                            'operateCompletionRate_error' => $d['operateCompletionRate_error']
                        ]
                    );
                }
            }
        }
    }

    /**
     * @param array $where
     * @param int $type
     * @return mixed
     * @throws Exception
     * 获取绩效考核指标数据
     */
    public
    function getList(array $where = [], int $type = 0)
    {

        if ($type === 0) {
            $res = $this->leaveHospital($where); // 出院患者手术占比
        } elseif ($type === 1) {
            $res = $this->gradeFour($where); // 出院患者四级手术占比
        } elseif ($type === 2) {
            $res = $this->infectRadio($where); // I类切口手术部位感染率
        } elseif ($type === 3) {
            $res = $this->miniInvasive($where); // 出院患者微创手术占比
        } elseif ($type === 4) {
            $res = $this->complication($where); // 手术患者并发症发生率
        } elseif (in_array($type, [11, 12, 13])) {
            $res = $this->hrConfigZb($type, $where);
        } else {
            $res = $this->getZbv2($type, $where);
        }

        if (empty($res)) {
            return ['numerator' => 0, 'denominator' => 0, 'res' => 0];
        }

        return $res;
    }

    /**
     * 获取新指标数据
     */
    public
    function getZbv2($type = 0, $where = [])
    {

        $dateType = [
            31 => ['numerator_ct', 'denominator_ct'],
            32 => ['numerator_bl', 'denominator_bl'],
            33 => ['numerator_xjpy', 'denominator_xjpy'],
            41 => ['numerator_kjyw', 'denominator_kjyw'],
            42 => ['numerator_exzlhxzl', 'denominator_exzlhxzl'],
            43 => ['numerator_exzlfszl', 'denominator_exzlfszl'],
            44 => ['numerator_operation', 'denominator_operation'],
            45 => ['numerator_zrw', 'denominator_zrw'],
            57 => ['numerator_bhlbl', 'denominator_bhlbl'],
            46 => ['numerator_lcyx', 'denominator_lcyx'],
            23 => ['numerator_cyjl', 'denominator_cyjl'],
            24 => ['numerator_basy', 'denominator_basy'],
            48 => ['numerator_hzqjjl', 'denominator_hzqjjl'],
            21 => ['numerator_ryjl', 'denominator_ryjl'],
            34 => ['numerator_xjpy1', 'denominator_xjpy1'],
            22 => ['numerator_operateCompletionRate', 'denominator_operateCompletionRate'],
            51 => ['numerator_cyhzgd', 'denominator_cyhzgd'],
            47 => ['numerator_chafangCompletionRate', 'denominator_chafangCompletionRate'],
            53 => ['numerator_zyzdtx', 'denominator_zyzdtx'],
            54 => ['numerator_zyzdbm', 'denominator_zyzdbm'],
            55 => ['numerator_zysstx', 'denominator_zysstx'],
            56 => ['numerator_zyssbm', 'denominator_zyssbm'],
            58 => ['numerator_zqtysgfqs', 'denominator_zqtysgfqs'],
            49 => ['numerator_hzqjcgl', 'denominator_hzqjcgl'],
            52 => ['numerator_cyhzgdl', 'denominator_cyhzgdl'],
            59 => ['numerator_A', 'denominator_A'],
            60 => ['numerator_ngzb', 'denominator_ngzb'],
            61 => ['numerator_xgzb', 'denominator_xgzb'],
            62 => ['numerator_ngrszb', 'denominator_ngrszb'],
        ];

        $year = $where['year'] ?? date('Y');

        $esService = new ElasticsearchService('patient_info_target');
        $aggs = [
            "AAC01" => [
                "date_histogram" => [
                    "field" => "AAC01",
                    "calendar_interval" => "1M",
                    "format" => "yyyy-MM",
                    'extended_bounds' => [
                        "min" => $year . "-01",
                        "max" => $year . "-12"
                    ],
                    "keyed" => true
                ],
            ]
        ];
        $fenmu = [
            "term" => [
                $dateType[$type][1] => 1
            ]
        ];
        $fenzi = [
            "term" => [
                $dateType[$type][0] => 1
            ]
        ];

        // 分母
        $params = $esService->queryByMust($fenmu)->source(['MED_REC_ID'])->aggs($aggs)->getParams();
        $restful = app('es')->search($params);
        $fenmuBuckets = $restful['aggregations']['AAC01']['buckets'];

        // 分子
        $params = $esService->queryByMust($fenzi)->source(['MED_REC_ID'])->aggs($aggs)->getParams();
        $restful = app('es')->search($params);
        $fenziBuckets = $restful['aggregations']['AAC01']['buckets'];


        $resArr = [];
        for ($i = 1; $i <= 12; $i++) {

            $key = $year . '-' . sprintf('%02s', $i);
            $denominator = !empty($fenmuBuckets[$key]) ? $fenmuBuckets[$key]['doc_count'] : 0;
            $numerator = !empty($fenziBuckets[$key]) ? $fenziBuckets[$key]['doc_count'] : 0;
            if (!$denominator) {
                $resArr[] = ['numerator' => 0, 'denominator' => 0, 'res' => 0, 'time' => $key, 'source' => '系统提取'];
                continue;
            }

            $res = bcdiv((string)$numerator, (string)$denominator, 4);
            $resArr[] = ['numerator' => $numerator, 'denominator' => $denominator, 'res' => $res, 'time' => $key, 'source' => '系统提取'];
        }
        $resArr[] = self::zbCount($resArr);
        return $resArr;
    }

    public
    static function zbCount($resArr = [])
    {
        $numerator = array_sum(array_column($resArr, 'numerator'));
        $denominator = array_sum(array_column($resArr, 'denominator'));
        $count = [
            'numerator' => $numerator,
            'denominator' => $denominator,
            'res' => $denominator ? round($numerator / $denominator, 4) : 0,
            'time' => '全年',
            'source' => '系统提取'
        ];
        return $count;
    }

    /**
     * @param array $where
     * @return array|int
     * 出院患者手术占比
     */
    public
    function leaveHospital(array $where = [])
    {
        // 出院患者手术台次数，手术判别SSPB是手术1和介入治疗4相加总人数。
        $where['SSPB'] = self::SSPB14;
        $operation = MainOperation::getLeaveHospitalData($where);
        if (!$operation) {
            return 0;
        }

        // 设置手术判别为空，获取所有的同期出院人数
        unset($where['SSPB']);
        $operationTotal = MainOperation::getLeaveHospitalData($where);
        if (!$operationTotal) {
            return 0;
        }

        // 出院比例 = 手术判别是手术和介入治疗相加总人数 / 同期出院总人数
        $res = bcdiv((string)$operation, (string)$operationTotal, 4);

        return ['numerator' => $operation, 'denominator' => $operationTotal, 'res' => $res];
    }

    /**
     * @param array $where
     * @return array|int
     * 出院患者四级手术占比
     */
    public
    function gradeFour(array $where = [])
    {
        // 获取四级手术的信息
        $operationInfo = OperationInfo::getList(['type' => 0], ['ICD9_ID1']);
        if (empty($operationInfo)) {
            return 0;
        }
        $operationIds = array_column($operationInfo, 'ICD9_ID1');

        // 出院患者住院期间实施四级手术和按照四级手术管理的介入诊疗人数之和
        $where['SSPB'] = self::SSPB14;
        $where['ICD9_ID1'] = $operationIds;
        $operation = MainOperation::getLeaveHospitalData($where);
        if (!$operation) {
            return 0;
        }

        // 出院患者手术（含介入）人数。
        unset($where['ICD9_ID1']);
        $operationTotal = MainOperation::getLeaveHospitalData($where);
        if (!$operationTotal) {
            return 0;
        }

        $res = bcdiv((string)$operation, (string)$operationTotal, 4);

        return ['numerator' => $operation, 'denominator' => $operationTotal, 'res' => $res];
    }

    /**
     * @param array $where
     * @return array|int
     * I类切口手术部位感染率
     */
    public
    function infectRadio(array $where = [])
    {
        // INCISION_GRADE_ID 切口愈合等级ID
        // 手术为I类切口且切口愈合等级为“丙级愈合”（代码为3）选项的人数
        $where['INCISION_GRADE_ID'] = '1-3';
        $operation = MainOperation::getLeaveHospitalData($where);
        if (!$operation) {
            return 0;
        }

        // 同期出院患者手术为I类切口人数
        $where['INCISION_GRADE_ID'] = ['1-0', '1-1', '1-2', '1-3'];
        $operationTotal = MainOperation::getLeaveHospitalData($where);
        if (!$operationTotal) {
            return 0;
        }

        $res = bcdiv((string)$operation, (string)$operationTotal, 4);
        return ['numerator' => $operation, 'denominator' => $operationTotal, 'res' => $res];
    }

    /**
     * @param array $where
     * @return array|int
     * 出院患者微创手术占比
     */
    public
    function miniInvasive(array $where = [])
    {
        // 获取微创手术的信息
        $operationInfo = OperationInfo::getList(['type' => 1], ['ICD9_ID1']);
        if (empty($operationInfo)) {
            return 0;
        }
        $operationIds = array_column($operationInfo, 'ICD9_ID1');

        // 出院患者住院期间实施四级手术和按照四级手术管理的介入诊疗人数之和
        $where['ICD9_ID1'] = $operationIds;
        $operation = MainOperation::getLeaveHospitalData($where);
        if (!$operation) {
            return 0;
        }

        // 出院患者手术（含介入）人数。
        unset($where['ICD9_ID1']);
        $where['SSPB'] = self::SSPB14;
        $operationTotal = MainOperation::getLeaveHospitalData($where);
        if (!$operationTotal) {
            return 0;
        }

        $res = bcdiv((string)$operation, (string)$operationTotal, 4);
        return ['numerator' => $operation, 'denominator' => $operationTotal, 'res' => $res];
    }

    // 手术患者并发症发生率▲
    // 8.1.手术患者并发症发生例数
    // 做过手术，出院诊断【3-ICD10_NAME】符合“手术并发症诊断相关名称”且该诊断入院病情【ABC03C】为“无”（代码为4）的病例。
    //（同一次住院就诊期间患有同一疾病或不同疾病施行多次手术者，按1人统计）
    // 8.2.同期出院的手术患者人数
    // 同期出院的手术患者人数是指同期出院患者【SSLX】择期手术人数。
    // | 代码 |名称|
    // | 1  |择期手术|
    // | 2  |急诊手术|
    // | 3  |限期手术|
    // 统计单位以人数计算，总数为实施择期手术和介入治疗人数累加求和。
    // 不包括妊娠、分娩、围产期、新生儿患者。
    //（同一次住院就诊期间患有同一疾病或不同疾病施行多次手术者，按1人统计）
    /**
     * @param array $where
     * @return array|int
     * 手术患者并发症发生率
     */
    public
    function complication(array $where = [])
    {
        // 做过手术，出院诊断【3-ICD10_NAME】符合“手术并发症诊断相关名称”且该诊断入院病情【ABC03C】为“无”（代码为4）的病例。
        $startTime = $where['start_time'];
        $endTime = $where['end_time'];
        $obj = PatientMedicalInfo::query()
            ->Join("main_operation", "main_operation.AAA28", "=", "patient_medical_info.AAA28")
            ->Join("main_diagnosis", "main_diagnosis.AAA28", "=", "patient_medical_info.AAA28")
            ->join('disease_diagnosis_code as ddc', 'ddc.OLD_ICD10_ID1', '=', 'main_diagnosis.ICD10_ID1')
            ->where('patient_medical_info.ABC03C', '=', 4);
        if ($startTime && $endTime) {
            $obj = $obj->whereBetween(Db::raw('UNIX_TIMESTAMP(main_operation.OPE_DATE)'), [$startTime, $endTime]);
        }
        $bfzArr = ['I26', 'I80.2', 'I82.8', 'A40.0', 'A40.9', 'A41.0', 'A41.9', 'T81.411', 'B37.700', 'B49.x00x019', 'T81.0', 'T81.3', 'R96.0', 'R96.1', 'I46.1', 'J95.800x004', 'J96.0', 'J96.1', 'J96.9', 'E89.0', 'E89.9', 'T81.4', 'T81.5', 'T81.6', 'T88.2', 'T88.5', 'J95.1', 'J95.4', 'J95.8', 'J95.9', 'J98.4', 'J15', 'J16', 'J18', 'T81.2', 'N17.0', 'N17.9', 'N99.0', 'K91.0', 'K91.9', 'I97.0', 'I97.1', 'I97.8', 'I97.9', 'G97.0', 'G97.1', 'G97.2', 'G97.8', 'G97.9', 'I60', 'I64', 'H59.0', 'H59.8', 'H59.9', 'H95.0', 'H95.1', 'H95.8', 'H95.9', 'M96.0', 'M96.9', 'N98.0', 'N99.9', 'K11.4', 'T81.2', 'T82.0', 'T82.9', 'T83.0', 'T83.9', 'T84.0', 'T84.9', 'T85.0', 'T85.9', 'T86.0', 'T86.9', 'T87.0', 'T87.6', 'T81.1', 'T81.7', 'T81.8', 'T81.9'];
        $likeRawSql = '(';
        foreach ($bfzArr as $sk => $sv) {
            if (count($bfzArr) == $sk + 1) {
                $likeRawSql .= 'ddc.ICD10_ID1 like "' . $sv . '%"';
            } else {
                $likeRawSql .= 'ddc.ICD10_ID1 like "' . $sv . '%" or ';
            }
        }
        $likeRawSql .= ')';
        $obj = $obj->whereRaw($likeRawSql);
        $operation = $obj->count();

        if (!$operation) {
            return 0;
        }

        // 同期出院的手术患者人数是指同期出院患者【SSLX】择期手术人数,OPE_TYPE对应的是dis中的SSLX
        $obj = PatientMedicalInfo::query()
            ->Join("patient_info", "patient_info.MED_REC_ID", "=", "patient_medical_info.AAA28")
            ->Join("main_operation", "main_operation.AAA28", "=", "patient_medical_info.AAA28")
            ->Join("main_diagnosis", "main_diagnosis.AAA28", "=", "patient_medical_info.AAA28")
            ->join('disease_diagnosis_code as ddc', 'ddc.OLD_ICD10_ID1', '=', 'main_diagnosis.ICD10_ID1')
            ->where('main_operation.OPE_TYPE', '=', 4);
        if ($startTime && $endTime) {
            $obj = $obj->whereBetween(Db::raw('UNIX_TIMESTAMP(main_operation.OPE_DATE)'), [$startTime, $endTime]);
        }
        $obj = $obj->whereRaw('ddc.ICD10_ID1 not like "O%" AND ddc.ICD10_ID1 not like "P%" and (patient_info.AAA04 > 0 or (patient_info.AAA04 = 0 and patient_info.AAA40 > 28))');
        $operationTotal = $obj->count();

        if (!$operationTotal) {
            return 0;
        }

        $res = bcdiv((string)$operation, (string)$operationTotal, 4);

        return ['numerator' => $operation, 'denominator' => $operationTotal, 'res' => $res];
    }


    /**
     * 病理检查记录符合率
     * php artisan command:feeClean
     * update patient_info_target set denominator_bl=0,numerator_bl=0,bl_error="";
     */
    public function bingliData($zyh = "", $start_time = "", $end_time = "")
    {

        $setName = 'quality_zb_bingli';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $pageSize = 500;
        $startTime = $this->startTime;
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > time()) {
                break;
            }
            var_dump('时间：' . date("Y-m-d H:i:s", $startTime));
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->join('fee_detailed', 'fee_detailed.AAA28', '=', 'patient_info.MED_REC_ID')
                ->where('fee_detailed.is_bingli', '=', 1)
                ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime))
                ->get(['patient_info.id', 'patient_info.AAC01', 'patient_info.MED_REC_ID', 'patient_info.AAA28', 'patient_info.AAB01', 'patient_info.AAC01', 'fee_detailed.FYMC', 'fee_detailed.pre_FYMC'])->toArray();

            $startTime = $entTime;

            $newData = [];
            foreach ($data as $d) {
                $lastId = $d['AAC01'];
                $newData[$d['MED_REC_ID']][] = $d;
            }

            $numerator = [];
            $firstBlKw = ['图文病理报告', '液基细胞学病理检查', '病理液基细胞学(TCT)', '液基薄层细胞制片术'];

            foreach ($newData as $zyh => $data) {

                $isError = 1;
                $sfxmError = '收费项目【';
                $sfxm = [];
                foreach ($data as $d) {
                    $yzb = Yzb::query()
                        ->where('YZMC', '=', $d['pre_FYMC'])
                        ->count();
                    if ($yzb) {
                        $sfxm[] = $d['pre_FYMC'];
                    }
                }
                if ($sfxm) {
                    $sfxm = array_unique($sfxm);
                    $tuyj = array_intersect($firstBlKw, $sfxm);
                    if ($tuyj) {
                        $sfxmError .= implode(',', $tuyj) . '】';
                    } else {
                        $sfxmError .= implode(',', $sfxm) . '】';
                    }
                } else {
                    $sfxmError .= '收费项目匹配失败，默认第一个收费项目：' . $data[0]['pre_FYMC'] . '】';
                }
                $bl_error = $sfxmError;
                $fee = FeeDetailed::query()
                    ->where('AAA28', '=', $data[0]['MED_REC_ID'])
                    ->where('FYMC', 'LIKE', "%液基%")
                    ->count();
                if (!$fee) {
                    $bl_error .= '，收费项目： 【关键字：液基（无）】';
                    $isError = 0;
                } else {
                    $bl_error .= '，收费项目： 【关键字：液基（有）】';
                }

                $aab01 = $data[0]['AAB01'];
                $aac01 = $data[0]['AAC01'];
                // 报告单  备注：报告单在其他数据表
                $bgd = PACS::query()
                    ->where('PACS.JZLSH', '=', $data[0]['AAA28'])
                    ->whereBetween('KDSJ', [$aab01, $aac01])
                    ->where('PACS.YXZD', '<>', "")
                    ->whereRaw('PACS.BGSJ > PACS.KDSJ')
                    ->where('PACS.ExamType', '=', "07")
                    ->get(['YXZD'])->toArray();
                if (!$bgd) {
                    $bl_error .= '，检查报告单： （无）';
                    $isError = 0;
                } else {
                    $bl_error .= '，检查报告单： 【' . $bgd[0]['YXZD'] . '】';
                }

                // 病程
                $shoushu = EMR_BL_BL01::query()
                    ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('EMR_BL_BL01.JZHM', '=', $data[0]['MED_REC_ID'])
                    ->where('EMR_BL_BL01.BLLB', '=', 294)
                    ->where('EMR_BL_BL01.BLZT', '!=', 9)
                    ->whereRaw('(EMR_BL_BLXG.HJNR like "%液基%" or EMR_BL_BLXG.HJNR like "%tct%" or EMR_BL_BLXG.HJNR like "%病理%")')
                    ->count();
                if (!$shoushu) {
                    $bl_error .= '，病程记录： 【关键字：“液基” 或 “tct”或“病理”（无）】';
                    $isError = 0;
                } else {
                    $bl_error .= '，病程记录： 【关键字：“液基” 或 “tct”或“病理”（有）】';
                }
                if ($isError) {
                    $numerator[] = ['ZYH' => $d['MED_REC_ID'], 'numerator_bl' => $isError, 'bl_error' => $bl_error];
                    continue;
                }
                // 两组条件，上面逻辑符合或者下面的逻辑符合
                $isError = 1;
                // 检查报告单
                if (!$bgd) {
                    $isError = 0;
                }
                // 手术记录
                $shoushu = EMR_BL_BL01::query()
                    ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('EMR_BL_BL01.JZHM', '=', $data[0]['MED_REC_ID'])
                    ->where('EMR_BL_BL01.BLLB', '=', 303)
                    ->where('EMR_BL_BL01.BLZT', '!=', 9)
                    ->whereRaw('(EMR_BL_BLXG.HJNR like "%病理%" or EMR_BL_BLXG.HJNR like "%取%")')
                    ->count();
                if ($shoushu) {
                    $bl_error .= '，手术记录： 【关键字：“病理” 或 “取”（有）】';
                }

                // 病程记录
                $bingchengBl = EMR_BL_BL01::query()
                    ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('EMR_BL_BL01.JZHM', '=', $data[0]['MED_REC_ID'])
                    ->where('EMR_BL_BL01.BLLB', '=', 294)
                    ->whereRaw('(EMR_BL_BLXG.HJNR like "%病理%")')
                    ->count();
                if ($bingchengBl) {
                    $bl_error .= '，病程记录：【关键字：“病理”（有）】';
                } else {
                    // 病程记录
                    $bingchengQ = EMR_BL_BL01::query()
                        ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                        ->where('EMR_BL_BL01.JZHM', '=', $data[0]['MED_REC_ID'])
                        ->where('EMR_BL_BL01.BLLB', '=', 294)
                        ->whereRaw('(EMR_BL_BLXG.HJNR like "%取%")')
                        ->count();
                    if ($bingchengQ) {
                        $bl_error .= '，病程记录：【关键字：“取”（有）】';
                    } else {
                        $bl_error .= '，病程记录：【关键字：“病理”或“取”（无）】';
                        $isError = 0;
                    }
                }
                var_dump($d['MED_REC_ID']);
                $numerator[] = ['ZYH' => $d['MED_REC_ID'], 'numerator_bl' => $isError, 'bl_error' => $bl_error];
            }

            if ($numerator) {
                foreach ($numerator as $d) {
                    $d['ZYH'] = (string)$d['ZYH'];
                    PatientInfoTarget::query()->updateOrInsert(
                        ['ZYH' => $d['ZYH']],
                        ['denominator_bl' => 1, 'numerator_bl' => $d['numerator_bl'], 'bl_error' => $d['bl_error']]
                    );
                }
            }
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
    }

    /**
     * 抗菌药物使用记录符合率
     */
    public
    function kjyw($type = 0)
    {

        $dateType = [
            31 => ['numerator_ct', 'denominator_ct'],
            32 => ['numerator_bl', 'denominator_bl'],
            33 => ['numerator_xjpy', 'denominator_xjpy'],
            41 => ['numerator_kjyw', 'denominator_kjyw'],
            42 => ['numerator_exzlhxzl', 'denominator_exzlhxzl'],
            43 => ['numerator_exzlfszl', 'denominator_exzlfszl'],
            44 => ['numerator_operation', 'denominator_operation'],
            45 => ['numerator_zrw', 'denominator_zrw'],
            57 => ['numerator_bhlbl', 'denominator_bhlbl'],
            46 => ['numerator_lcyx', 'denominator_lcyx'],
        ];

        $year = $where['year'] ?? date('Y');


        $resArr = [];
        for ($i = 1; $i <= 12; $i++) {
            $startTime = strtotime($year . '-' . $i . '-01');
            $t = date('t', $startTime);
            $endTime = $startTime + $t * 3600 * 24;
            // 所有符合条件的病例信息
            $denominator = PatientInfo::query()
                ->join('patient_info_target as target', 'target.ZYH', '=', 'patient_info.MED_REC_ID')
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->where('target.denominator_kjyw', '=', 1)->count();

            if (!$denominator) {
                $resArr[] = ['numerator' => 0, 'denominator' => 0, 'res' => 0, 'time' => $year . '-' . sprintf('%02s', $i), 'source' => '系统提取'];
                continue;
            }
            // 所有符合条件的病例信息
            $numerator = $bgdObj = PatientInfo::query()
                ->join('patient_info_target as target', 'target.ZYH', '=', 'patient_info.MED_REC_ID')
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->where('target.numerator_kjyw', '=', 1)->count();

            $res = bcdiv((string)$numerator, (string)$denominator, 4);
            $resArr[] = ['numerator' => $numerator, 'denominator' => $denominator, 'res' => $res, 'time' => $year . '-' . sprintf('%02s', $i), 'source' => '系统提取'];
        }
        $resArr[] = self::zbCount($resArr);
        return $resArr;
    }


    /**
     * 抗菌药物病例的数据清洗，将含有抗菌药物的病例信息打上标识
     * update patient_info_target set denominator_kjyw=0,numerator_kjyw=0,kjyw_error="";
     */
    public function kjywData($zyh = "", $start_time = "", $end_time = "")
    {
        $setName = 'quality_zb_kjyw';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $pageSize = 500;
        $startTime = $this->startTime;
        $bl01esService = new ElasticsearchService('bl01_202303');
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > time()) {
                break;
            }
            var_dump('时间：' . date("Y-m-d H:i:s", $startTime));
            // 所有符合条件的病例信息
            // SELECT * FROM `patient_info` as a left join yzb as b on a.MED_REC_ID=b.ZYH
            // where a.denominator_kjyw=0 and b.is_has_kjyw=1
            $data = PatientInfo::query()
                ->Join('yzb', 'patient_info.MED_REC_ID', '=', 'yzb.ZYH')
                ->where('yzb.is_has_kjyw', '=', 1)
                ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime))
                ->get(['patient_info.AAC01', 'patient_info.id', 'yzb.XMLB', 'yzb.YZQX', 'yzb.ZYH', 'yzb.YZMC', 'yzb.KZSJ', 'yzb.kjyw_name', 'yzb.JLDW', 'yzb.YCJL', 'yzb.SYPC'])->toArray();

            $startTime = $entTime;

            $newData = [];
            foreach ($data as $d) {
                $lastId = $d['AAC01'];
                $uniqueId = md5($d['ZYH'] . $d['YZMC'] . $d['JLDW'] . $d['YCJL'] . $d['SYPC']);
                $newData[$d['ZYH']][$uniqueId] = $d;
            }
            $fenzi = [];
            foreach ($newData as $med => $data) {
                $flag = true;
                $kjywError = [];
                $XMLBData = [];
                foreach ($data as $y) {
                    if ($y['XMLB'] == 9 && in_array($y['YZMC'], $XMLBData)) {
                        continue;
                    }
                    $XMLBData[] = $y['YZMC'];
                    $kjyw_name = $y['KZSJ'] . '-' . $y['YZMC'] . ($y['YZQX'] == 1 ? '（长期医嘱）' : '') . ($y['YZQX'] == 2 ? '（临时医嘱）' : '') . '/' . $y['YCJL'] . $y['JLDW'] . '/' . $y['SYPC'];

                    $bl01must = [
                        ['term' => ["JZHM" => $y['ZYH']]],
                        ['term' => ["BLLB" => 294]],
                        ['match_phrase' => ["HJNR" => $y['kjyw_name']]],
                    ];
                    $params = $bl01esService->clearMust()
                        ->queryByMustNot(['term' => ['BLZT' => 9]])
                        ->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'BLMC', 'ZXSJ'])->getParams();
                    $bl01Res = app('es')->search($params);
                    $bl01Res = $bl01esService->getDataByEs($bl01Res);
                    var_dump($bl01Res);
                    if (!$bl01Res[1]) {
                        $flag = false;
                        $kjywError[] = '医嘱【' . $kjyw_name . '】' . '病程记录【 无  ' . $y['kjyw_name'] . '】';
                    } else {
                        $sTime = strtotime($y['KZSJ']);
                        $ZXSJTime = strtotime($bl01Res[0][0]['ZXSJ']);
                        $eTime = $sTime + 24 * 3600;
                        if ($ZXSJTime > $eTime) {
                            $flag = false;
                            $kjywError[] = '医嘱【' . $kjyw_name . '】病程【' . $y['YZMC'] . '】但是病程记录【 执行时间超过24小时 】';
                        } else {
                            $kjywError[] = '医嘱有【' . $kjyw_name . '】' . '病程记录有【' . $bl01Res[0][0]['ZXSJ'] . ' - ' . $y['kjyw_name'] . '】';
                        }
                    }
                }
                $kjywErrorContent = '';
                foreach ($kjywError as $k => $item) {
                    $kjywErrorContent .= ($k + 1) . '：' . $item;
                }
                var_dump($med);
                $fenzi[] = ['ZYH' => (string)$med, 'numerator_kjyw' => ($flag === true ? 1 : 0), 'kjyw_error' => $kjywErrorContent];
            }

            if ($fenzi) {
                foreach ($fenzi as $d) {
                    $d['ZYH'] = (string)$d['ZYH'];
                    $has = PatientInfoTarget::query()->where('ZYH', '=', $d['ZYH'])->count();
                    if ($has) {
                        PatientInfoTarget::query()->where('ZYH', '=', $d['ZYH'])->update([
                            'denominator_kjyw' => 1,
                            'numerator_kjyw' => $d['numerator_kjyw'],
                            'kjyw_error' => $d['kjyw_error']
                        ]);
                    } else {
                        PatientInfoTarget::query()->insert(
                            [
                                'ZYH' => $d['ZYH'],
                                'denominator_kjyw' => 1,
                                'numerator_kjyw' => $d['numerator_kjyw'],
                                'kjyw_error' => $d['kjyw_error']
                            ]
                        );
                    }
                }
            }
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        var_dump('kjywData_end');
    }

    /**
     * 恶性肿瘤化学治疗记录符合率，将含有化学药品的病例信息打上标识
     * update patient_info_target set denominator_exzlhxzl=0,numerator_exzlhxzl=0,exzlhxzl_error="";
     */
    public function exzlhxzlData($zyh = "", $start_time = "", $end_time = "")
    {
        $setName = 'quality_zb_exzlhxzl';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $pageSize = 500;
        $bl01esService = new ElasticsearchService('bl01_202303');
        $startTime = $this->startTime;
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > time()) {
                break;
            }
            var_dump('时间：' . date("Y-m-d H:i:s", $startTime));
            // 所有符合条件的病例信息
            $data = $bgdObj = PatientInfo::query()
                ->Join('yzb', 'patient_info.MED_REC_ID', '=', 'yzb.ZYH')
                ->where('yzb.is_has_hlyw', '=', 1)
                ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime))
                ->get(['patient_info.AAC01', 'patient_info.id', 'yzb.YZQX', 'yzb.ZYH', 'yzb.YZMC', 'yzb.KZSJ', 'yzb.hlyw_name', 'yzb.SYPC', 'yzb.YCJL'])->toArray();

            $startTime = $entTime;

            $newData = [];
            foreach ($data as $d) {
                $lastId = $d['AAC01'];
                $uniqueId = md5($d['ZYH'] . $d['YZMC'] . $d['YCJL'] . $d['SYPC']);
                $newData[$d['ZYH']][$uniqueId] = $d;
            }
            $fenzi = [];
            foreach ($newData as $med => $data) {
                $flag = true;
                $exzlhxzlError = [];
                foreach ($data as $y) {
                    $hlyw_name = $y['KZSJ'] . '-' . $y['YZMC'] . ($y['YZQX'] == 1 ? '（长期医嘱）' : '') . ($y['YZQX'] == 2 ? '（临时医嘱）' : '') . '/' . $y['SYPC'] . '/' . $y['YCJL'];

                    $bl01must = [
                        ['term' => ["JZHM" => $y['ZYH']]],
                        ['term' => ["BLLB" => 294]],
                        ['match_phrase' => ["HJNR" => $y['hlyw_name']]],
                    ];
                    $params = $bl01esService->clearMust()
                        ->queryByMustNot(['term' => ['BLZT' => 9]])
                        ->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'ZXSJ', 'BLMC'])->getParams();
                    $bl01Res = app('es')->search($params);
                    $bl01Res = $bl01esService->getDataByEs($bl01Res);
                    var_dump($bl01Res);

                    if (!$bl01Res[1]) {
                        $flag = false;
                        $exzlhxzlError[] = '医嘱有【' . $hlyw_name . '】' . '病程记录【 无  ' . $y['hlyw_name'] . '】';
                    } else {
                        $sTime = strtotime($y['KZSJ']);
                        $ZXSJTime = strtotime($bl01Res[0][0]['ZXSJ']);
                        $eTime = $sTime + 24 * 3600;
                        if ($ZXSJTime > $eTime) {
                            $flag = false;
                            $exzlhxzlError[] = '医嘱【' . $hlyw_name . '】' . '病程记录【 执行时间超过24小时 】';
                        } else {
                            $exzlhxzlError[] = '医嘱有【' . $hlyw_name . '】病程有【' . $bl01Res[0][0]['ZXSJ'] . ' - ' . $y['hlyw_name'] . '】';
                        }
                    }
                }

                $exzlhxzlErrorContent = '';
                foreach ($exzlhxzlError as $k => $item) {
                    $exzlhxzlErrorContent .= ($k + 1) . '：' . $item;
                }
                var_dump($med);
                $fenzi[] = ['ZYH' => $med, 'numerator_exzlhxzl' => ($flag === true ? 1 : 0), 'exzlhxzl_error' => $exzlhxzlErrorContent];
            }

            if ($fenzi) {
                foreach ($fenzi as $d) {
                    $d['ZYH'] = (string)$d['ZYH'];
                    $has = PatientInfoTarget::query()->where('ZYH', '=', $d['ZYH'])->count();
                    if ($has) {
                        PatientInfoTarget::query()->where('ZYH', '=', $d['ZYH'])->update([
                            'denominator_exzlhxzl' => 1,
                            'numerator_exzlhxzl' => $d['numerator_exzlhxzl'],
                            'exzlhxzl_error' => $d['exzlhxzl_error']
                        ]);
                    } else {
                        PatientInfoTarget::query()->insert(
                            [
                                'ZYH' => $d['ZYH'],
                                'denominator_exzlhxzl' => 1,
                                'numerator_exzlhxzl' => $d['numerator_exzlhxzl'],
                                'exzlhxzl_error' => $d['exzlhxzl_error']
                            ]
                        );
                    }
                }
            }
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
    }

    /**
     * 恶性肿瘤放射治疗记录符合率
     */
    public
    function exzlfszlData($zyh = "", $start_time = "", $end_time = "")
    {
        $setName = 'quality_zb_exzlfszl';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $pageSize = 100;
        $startTime = $this->startTime;
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > time()) {
                break;
            }
            var_dump('时间：' . date("Y-m-d H:i:s", $startTime));
            // 所有符合条件的病例信息
            $data = $bgdObj = PatientInfo::query()
                ->Join('yzb', 'patient_info.MED_REC_ID', '=', 'yzb.ZYH')
                ->where('yzb.is_fangliao', '=', 1)
                ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime))
                ->get(['patient_info.AAC01', 'patient_info.id', 'MED_REC_ID', 'ZYH', 'YZMC', 'KZSJ'])->toArray();
            $startTime = $entTime;

            $allData = $fenzi = [];
            foreach ($data as $y) {
                $lastId = $y['AAC01'];
                var_dump($y['id']);
                $yzmc = $y['YZMC'] ?: '';
                $yzmc = str_replace(' ', '', $yzmc);
                if (empty($yzmc)) {
                    continue;
                }
                preg_match_all("/放疗(\d+)次/", $yzmc, $res);
                if (!$res[1]) {
                    continue;
                }
                // 如果医嘱中有放疗*次，则记录下来，负责分母的要求
                $allData[] = ['ZYH' => $y['ZYH'], 'exzlfszl_error' => '医嘱【放疗*次】'];

                // 病程记录
                $bingcheng = EMR_BL_BL01::query()
                    ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('EMR_BL_BL01.JZHM', '=', $y['ZYH'])
                    ->where('EMR_BL_BL01.BLLB', '=', 294)
                    ->where('EMR_BL_BL01.BLZT', '<>', 9)
                    ->where('EMR_BL_BLXG.is_fangliao', '=', 1)
                    ->get()->toArray();
                if ($bingcheng) {
                    $fenzi[] = ['ZYH' => $y['ZYH'], 'numerator_exzlfszl' => 1, 'exzlfszl_error' => '医嘱【放疗*次】病程记录【有放疗关键字】'];
                } else {
                    $fenzi[] = ['ZYH' => $y['ZYH'], 'numerator_exzlfszl' => 0, 'exzlfszl_error' => '医嘱【放疗*次】病程记录【无放疗关键字】'];
                }
            }

            if ($allData) {
                foreach ($allData as $d) {
                    $d['ZYH'] = (string)$d['ZYH'];
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => $d['ZYH']], ['denominator_exzlfszl' => 1, 'exzlfszl_error' => $d['exzlfszl_error']]);
                }
            }
            if ($fenzi) {
                foreach ($fenzi as $d) {
                    $d['ZYH'] = (string)$d['ZYH'];
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => $d['ZYH']], ['numerator_exzlfszl' => $d['numerator_exzlfszl'], 'exzlfszl_error' => $d['exzlfszl_error']]);
                }
            }
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
    }


    /**
     * 清洗医嘱本中医嘱名称是否包含抗菌药物或者化疗药物
     * update `yzb` set is_has_kjyw=0,is_has_hlyw=0,kjyw_name="",hlyw_name="";
     */
    public
    static function filterField($zyh = '')
    {
        $zyh = (string)$zyh;
        $setName = 'qx_YzbClean_last_id';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;

        $maxId = Yzb::query()->max('id');
        if ($lastId == $maxId) {
            return false;
        }

        $medicianlKjyw = MedicinalInfo::query()->where(['type' => 1])->pluck('name')->toArray(); // 抗菌药物
        foreach ($medicianlKjyw as $h) {
            $where = [
                ['YZMC', 'like', '%' . $h . '%'],
                ['YYSX', '<>', 4],
            ];
            if ($zyh) {
                $where[] = ['ZYH', '=', $zyh];
            } else {
                $where[] = ['id', '>', $lastId];
            }
            Yzb::query()
                ->where($where)
                ->update(['is_has_kjyw' => 1, 'kjyw_name' => $h]);
        }

        // 长期医嘱
        $wherecqyz = [
            ['is_has_kjyw', '=', 1],
            ['YZQX', '=', '1']
        ];
        if ($zyh) {
            $wherecqyz[] = ['ZYH', '=', $zyh];
        } else {
            $wherecqyz[] = ['id', '>', $lastId];
        }
        $cqyz = Yzb::query()
            ->where($wherecqyz)
            ->get(['id', 'kjyw_name', 'ZYH', 'YCJL', 'JLDW'])->toArray();
        $cqyzArr = [];
        foreach ($cqyz as $c) {
            $cqyzArr[$c['ZYH']][] = $c;
        }
        // 临时医嘱
        $wherelsyz = [
            ['is_has_kjyw', '=', 1],
            ['YZQX', '=', '2']
        ];
        if ($zyh) {
            $wherelsyz[] = ['ZYH', '=', $zyh];
        } else {
            $wherelsyz[] = ['id', '>', $lastId];
        }
        $lsyz = Yzb::query()
            ->where($wherelsyz)
            ->get(['id', 'kjyw_name', 'ZYH', 'YCJL', 'JLDW'])->toArray();
        $lsyzArr = [];
        foreach ($lsyz as $c) {
            $lsyzArr[$c['ZYH']][] = $c;
        }
        foreach ($cqyzArr as $k => $c1) {
            if (!empty($lsyzArr[$k])) {
                foreach ($c1 as $c2) {
                    foreach ($lsyzArr[$k] as $l1) {
                        if ($c2['kjyw_name'] == $l1['kjyw_name'] && $c2['YCJL'] == $l1['YCJL'] && $c2['JLDW'] == $l1['JLDW']) {
                            Yzb::query()->where('id', '=', $l1['id'])->update(['is_has_kjyw' => 0, 'kjyw_name' => ""]);
                        }
                    }
                }
            }
        }


        $medicianlHlyw = MedicinalInfo::query()->where(['type' => 2])->pluck('name')->toArray(); // 化疗药物
        foreach ($medicianlHlyw as $h) {

            $where = [
                ['YZMC', 'like', '%' . $h . '%'],
                ['YYSX', '<>', 4],
            ];
            if ($zyh) {
                $where[] = ['ZYH', '=', $zyh];
            } else {
                $where[] = ['id', '>', $lastId];
            }
            Yzb::query()
                ->where($where)
                ->update(['is_has_hlyw' => 1, 'hlyw_name' => $h]);
        }


        // 长期医嘱
        $wherecqyz = [
            ['is_has_hlyw', '=', 1],
            ['YZQX', '=', '1']
        ];
        if ($zyh) {
            $wherecqyz[] = ['ZYH', '=', $zyh];
        } else {
            $wherecqyz[] = ['id', '>', $lastId];
        }
        $cqyz = Yzb::query()
            ->where($wherecqyz)
            ->get(['id', 'hlyw_name', 'ZYH', 'YCJL', 'JLDW', 'YDYZLB', 'YZMC'])->toArray();
        $cqyzArr = [];
        foreach ($cqyz as $c) {
            preg_match_all('/[a-zA-Z\d]+/', $c['YZMC'], $res);
            if ($c['YDYZLB'] == 901 && !$res[0]) {
                Yzb::query()->where('id', '=', $c['id'])->update(['is_has_hlyw' => 0]);
                continue;
            }
            $cqyzArr[$c['ZYH']][] = $c;
        }
        // 临时医嘱
        $wherelsyz = [
            ['is_has_hlyw', '=', 1],
            ['YZQX', '=', '2']
        ];
        if ($zyh) {
            $wherelsyz[] = ['ZYH', '=', $zyh];
        } else {
            $wherelsyz[] = ['id', '>', $lastId];
        }
        $lsyz = Yzb::query()
            ->where($wherelsyz)
            ->get(['id', 'hlyw_name', 'ZYH', 'YCJL', 'JLDW', 'YDYZLB', 'YZMC'])->toArray();
        $lsyzArr = [];
        foreach ($lsyz as $c) {
            preg_match_all('/[a-zA-Z\d]+/', $c['YZMC'], $res);
            if ($c['YDYZLB'] == 901 && !$res[0]) {
                Yzb::query()->where('id', '=', $c['id'])->update(['is_has_hlyw' => 0, 'hlyw_name' => ""]);
                continue;
            }
            $lsyzArr[$c['ZYH']][] = $c;
        }
        foreach ($cqyzArr as $k => $c1) {
            if (!empty($lsyzArr[$k])) {
                foreach ($c1 as $c2) {
                    foreach ($lsyzArr[$k] as $l1) {
                        if ($c2['hlyw_name'] == $l1['hlyw_name'] && $c2['YCJL'] == $l1['YCJL'] && $c2['JLDW'] == $l1['JLDW']) {
                            Yzb::query()->where('id', '=', $l1['id'])->update(['is_has_hlyw' => 0, 'hlyw_name' => ""]);
                        }
                    }
                }
            }
        }
        // 是否包含放疗关键词
        if ($zyh) {
            DB::update('update yzb set is_fangliao=1 where YZMC like "%放疗%" and YZMC like "%次%" and ZYH="' . $zyh . '"');
        } else {
            DB::update('update yzb set is_fangliao=1 where YZMC like "%放疗%" and YZMC like "%次%" and id>' . $lastId);
            Setting::query()->where('name', '=', $setName)->update(['content' => $maxId]);
        }
    }

    public function cleanPreFymc()
    {
        $settingid = 7;
        $lastId = Setting::query()->where('id', '=', $settingid)->pluck('content')->first();
        $lastId = $lastId ?: 0;

        $maxId = FeeDetailed::query()->max('id');
        while (true) {
            $end = $lastId + 10000;
            DB::update("update `fee_detailed` set pre_FYMC=SUBSTRING_INDEX(FYMC,'/', 1) where id between {$lastId} and {$end}");
            if ($end > $maxId) {
                $lastId = $maxId;
                break;
            }
            $lastId = $end;
        }
        Setting::query()->where('id', '=', $settingid)->update(['content' => $lastId]);
    }

    /**
     * 清洗费用明细中的病理费用
     * update `fee_detailed` set is_clean=0,is_bingli=0;
     */
    public
    function feeClean()
    {
        $blKeyword = [
            "病理费",
            "病理活检标本",
            "病理单切标本",
            "病理大标本",
            "病理快速切片诊断",
            "病理穿刺标本",
            "病理组织化学",
            "病理免疫荧光",
            "病理免疫组化",
            "病理DNA探针(分子病理诊断)",
            "RDNA探针(分子病理诊断)",
            "病理DNA倍体分析",
            "尸体病理诊断",
            "病理疑难会诊",
            "病理普通会诊",
            "病细胞学诊断",
            "显微图像肿瘤细胞分析",
            "液基细胞学病理检查",
            "肾穿刺病理费",
            "病理脱钙标本",
            "病理蜡块增收",
            "病理快速增收",
            "病理图文报告",
            "病理细针穿刺细胞检查",
            "病理液基细胞学(TCT)",
            "尸体解剖与防腐处理",
            "尸检病理诊断",
            "儿童及胎儿尸检病理诊断",
            "尸体化学防腐处理",
            "细胞病理学检查与诊断",
            "体液细胞学检查与诊断",
            "图文病理报告",
            "拉网细胞学检查与诊断",
            "细针穿刺细胞学检查与诊断",
            "脱落细胞学检查与诊断",
            "细胞学计数",
            "组织病理学检查与诊断",
            "穿刺组织活检检查与诊断",
            "蜡块增加",
            "涂片增加",
            "内镜组织活检与诊断",
            "局部切除组织活检检查与诊断",
            "骨髓组织活检检查与诊断",
            "手术标本检查与诊断",
            "塑料包埋",
            "截肢标本病理检查与诊断",
            "不脱钙直接切片",
            "牙齿及骨骼磨片诊断（不脱钙）",
            "牙齿及骨骼磨片诊断（脱钙）",
            "颌骨样本及牙体周样本诊断",
            "冰冻切片与快速石蜡切片检查与诊断",
            "冰冻切片检查与诊断",
            "特异性感染标本",
            "部位增加",
            "快速石蜡切片检查与诊断",
            "特殊染色诊断技术",
            "特殊染色及酶组织化学染色诊断",
            "免疫组织化学染色诊断",
            "免疫荧光染色诊断",
            "电镜病理诊断",
            "普通投射电镜检查与诊断",
            "免疫电镜检查与诊断",
            "扫描电镜检查与诊断",
            "分子病理学诊断技术",
            "原位杂交技术",
            "印迹杂交技术",
            "脱氧核糖核酸（DNA）测序",
            "基因芯片技术",
            "其它病理技术项目",
            "病理体视学检查与图像分析",
            "宫颈细胞学计算机辅助诊断",
            "膜式病变细胞采集技术",
            "液基薄层细胞制片术",
            "病理大体标本摄影",
            "显微摄影术",
            "疑难病理会诊",
            "普通病理会诊",
            "手术标本检查与诊断（单切）",
            "根治",
            "手术标本检查与诊断(单切)",
            "手术标本检查与诊断(根治)",
            "内镜组织活检与诊断（增加部位加收）",
            "人乳头瘤病毒（HPV)核酸检测",
            "荧光原位杂交（FISH）",
            "荧光原位杂交（FISH）（三项及以上）",
            "免疫组织化学染色诊断（液盖膜涡流混均加收）",
            "儿童及胎儿尸检病理诊断",
            "儿童及胎儿尸检病理诊断（开颅加收）",
            "尸检病理诊断（开颅加收）",
            "尸检病理诊断（传染病和特异性感染病尸加收）",
            "宫颈细胞学计算机辅导诊断",
            "细胞蜡块诊断",
            "手术标本检查与诊断（内镜切除）",
            "液基薄层细胞制片术（超过两片每片加收）",
            "纤维喉镜检查（床旁检查加收）",
            "全自动病理组织特殊染色"
        ];
        $blKeyword = array_unique($blKeyword);

        $settingId = 1;
        $lastId = Setting::query()->where('id', '=', $settingId)->pluck('content')->first();
        $lastId = $lastId ?: 0;

        $maxId = FeeDetailed::query()->max('id');
        foreach ($blKeyword as $item) {

            $limit = 10000;
            $start = $lastId;
            while (true) {
                $end = $start + $limit;
                DB::update("update `fee_detailed` set is_bingli=1 where pre_FYMC='" . $item . "' and id between {$start} and {$end}");
                if ($end > $maxId) {
                    break;
                }
                $start = $end;
            }
        }
        Setting::query()->where('id', '=', $settingId)->update(['content' => $maxId]);
    }


    /**
     * 清洗医嘱本中有手术数据信息的病例
     */
    public
    static function operationClean()
    {
        $setName = 'qx_operationClean_last_id';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;

        // 清洗医嘱本中有手术数据信息的病例
        $sql = 'SELECT id FROM `yzb` WHERE id>' . $lastId . ' AND is_operation=0 AND YZMC LIKE "拟%" and  YZMC LIKE "%年%" AND YZMC LIKE "%月%" AND  YZMC LIKE "%日%" AND ZYH not in (SELECT ZYH FROM yzb WHERE YZMC LIKE "%取消手术%" AND  YZMC LIKE "%手术取消%")';
        $res = DB::select($sql);
        if ($res) {
            $YzbIds = array_column($res, 'id');
            $chunkIds = array_chunk($YzbIds, 1000);
            foreach ($chunkIds as $ids) {
                DB::table('yzb')->whereIn('id', $ids)->update(['is_operation' => 1]);
                var_dump(count($ids));
            }
            $lastId = array_pop($YzbIds);
            Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        }
    }

    /**
     * @param array $where
     * @return array
     * @throws Exception
     * 指标详细数据列表
     *
     */
    public function getZbList($where = [])
    {
        $time = $where['time'];
        $id = $where['id'];
        $isError = $where['is_error'] ?? 200;
        $startTimeInt = strtotime($time . '-01');
        $startTime = date("Y-m-d H:i:s", $startTimeInt);
        $t = date('t', $startTimeInt);
        $endTime = $startTimeInt + $t * 3600 * 24;
        $endTime = date("Y-m-d H:i:s", $endTime);

        if ($time == '全年') {
            $startTime = $where['year'] . '-01-01 00:00:00';
            $endTime = $where['year'] . '-12-31 23:59:59';
        }

        //特殊处理 心梗 脑梗 出院时间筛选
        if ($where['start_time']) {
            $startTime = $where['start_time'] . ' 00:00:00';
            $endTime = $where['end_time'] . ' 23:59:59';
        }

        $dateType = [
            31 => ['numerator_ct', 'denominator_ct', 'ct_error'],
            32 => ['numerator_bl', 'denominator_bl', 'bl_error'],
            33 => ['numerator_xjpy', 'denominator_xjpy', 'xjpy_error'],
            41 => ['numerator_kjyw', 'denominator_kjyw', 'kjyw_error'],
            42 => ['numerator_exzlhxzl', 'denominator_exzlhxzl', 'exzlhxzl_error'],
            43 => ['numerator_exzlfszl', 'denominator_exzlfszl', 'exzlfszl_error'],
            44 => ['numerator_operation', 'denominator_operation', 'operation_error'],
            45 => ['numerator_zrw', 'denominator_zrw', 'zrw_name'],
            57 => ['numerator_bhlbl', 'denominator_bhlbl', 'bhlbl_content'],
            46 => ['numerator_lcyx', 'denominator_lcyx', 'lcyx_error'],
            23 => ['numerator_cyjl', 'denominator_cyjl', 'cyjl_error'],
            24 => ['numerator_basy', 'denominator_basy', 'basy_error'],
            48 => ['numerator_hzqjjl', 'denominator_hzqjjl', 'hzqjjl_error'],
            21 => ['numerator_ryjl', 'denominator_ryjl', 'ryjl_error'],
            34 => ['numerator_xjpy1', 'denominator_xjpy1', 'xjpy1_error'],
            22 => ['numerator_operateCompletionRate', 'denominator_operateCompletionRate', 'operateCompletionRate_error'],
            51 => ['numerator_cyhzgd', 'denominator_cyhzgd', 'cyhzgd_error'],
            47 => ['numerator_chafangCompletionRate', 'denominator_chafangCompletionRate', 'chafangCompletionRate_error'],
            53 => ['numerator_zyzdtx', 'denominator_zyzdtx', 'zyzdtx_error'],
            54 => ['numerator_zyzdbm', 'denominator_zyzdbm', 'zyzdbm_error'],
            55 => ['numerator_zysstx', 'denominator_zysstx', 'zysstx_error'],
            56 => ['numerator_zyssbm', 'denominator_zyssbm', 'zyssbm_error'],
            11 => ['numerator_public_cyjl', '', ''],
            13 => ['numerator_public_cyjl', '', ''],
            58 => ['numerator_zqtysgfqs', 'denominator_zqtysgfqs', 'zqtysgfqs_error'],
            49 => ['numerator_hzqjcgl', 'denominator_hzqjcgl', 'hzqjcgl_error'],
            52 => ['numerator_cyhzgdl', 'denominator_cyhzgdl', 'cyhzgdl_error'],
            59 => ['numerator_A', 'denominator_A', 'score'],
            60 => ['numerator_ngzb', 'denominator_ngzb', 'ngzb_describe'],
            61 => ['numerator_xgzb', 'denominator_xgzb', 'xgzb_describe'],
            62 => ['numerator_ngrszb', 'denominator_ngrszb', 'ngrszb_describe'],
        ];

        $dateTypeMap = [
            31 => ['numerator_ct', 'denominator_ct'],
            32 => ['numerator_bl', 'denominator_bl'],
            33 => ['numerator_xjpy', 'denominator_xjpy'],
            41 => ['numerator_kjyw', 'denominator_kjyw'],
            42 => ['numerator_exzlhxzl', 'denominator_exzlhxzl'],
            43 => ['numerator_exzlfszl', 'denominator_exzlfszl'],
            44 => ['numerator_operation', 'denominator_operation'],
            45 => ['numerator_zrw', 'denominator_zrw'],
            46 => ['numerator_lcyx', 'denominator_lcyx'],
            57 => ['numerator_bhlbl', 'denominator_bhlbl'],
            23 => ['numerator_cyjl', 'denominator_cyjl'],
            24 => ['numerator_basy', 'denominator_basy'],
            48 => ['numerator_hzqjjl', 'denominator_hzqjjl'],
            21 => ['numerator_ryjl', 'denominator_ryjl'],
            34 => ['numerator_xjpy1', 'denominator_xjpy1'],
            22 => ['numerator_operateCompletionRate', 'denominator_operateCompletionRate'],
            51 => ['numerator_cyhzgd', 'denominator_cyhzgd'],
            47 => ['numerator_chafangCompletionRate', 'denominator_chafangCompletionRate'],
            53 => ['numerator_zyzdtx', 'denominator_zyzdtx'],
            54 => ['numerator_zyzdbm', 'denominator_zyzdbm'],
            55 => ['numerator_zysstx', 'denominator_zysstx'],
            56 => ['numerator_zyssbm', 'denominator_zyssbm'],
            11 => ['numerator_public_cyjl', ''],
            13 => ['numerator_public_cyjl', ''],
            58 => ['numerator_zqtysgfqs', 'denominator_zqtysgfqs'],
            49 => ['numerator_hzqjcgl', 'denominator_hzqjcgl'],
            52 => ['numerator_cyhzgdl', 'denominator_cyhzgdl'],
            59 => ['numerator_A', 'denominator_A'],
            60 => ['numerator_ngzb', 'denominator_ngzb'],
            61 => ['numerator_xgzb', 'denominator_xgzb'],
            62 => ['numerator_ngrszb', 'denominator_ngrszb'],
        ];
        if (empty($dateType[$id])) {
            throw new Exception('参数有误');
        }

        $esService = new ElasticsearchService('patient_info_target');
        // 分子搜索还是分母搜索
        $must[] = [
            "term" => [
                $dateType[$id][$where['data_type']] => 1
            ]
        ];
        // 时间范围
        $must[] = [
            'range' => [
                'AAC01' => [
                    'gte' => $startTime,
                    'lte' => $endTime,
                ]
            ]
        ];
        if ($isError != 200) {
            // 57指标正确性正好和其他相反
            if ($id == 57) {
                $isError = $isError == 1 ? 0 : 1;
            }
            $must[] = [
                "term" => [
                    $dateType[$id][0] => $isError
                ]
            ];
        }
        // 住院号搜索
        if (!empty($where['AAA28'])) {
            $must[] = [
                "term" => [
                    'AAA28' => $where['AAA28']
                ]
            ];
        }
        // 住院号搜索
        if (!empty($where['AAC11N'])) {
            $must[] = [
                "term" => [
                    'AAC11N' => $where['AAC11N']
                ]
            ];
        }

        // 排序
        $order = !empty($where['order']) ? $where['order'] : 'AAB01';
        $order_sort = !empty($where['order_sort']) ? $where['order_sort'] : 'asc';

        // 分母
        $params = $esService->queryByMustBatch($must);
        $params = $params->paginate($where['page'], $where['page_size'])
            ->orderBy($order, $order_sort)
            ->trackTotalHits()
            ->getParams();
        $restful = app('es')->search($params);
        $hits = $esService->getDataByEs($restful);
        if (!$hits[1]) {
            return ['count' => 0, 'data' => []];
        }
        $data = $hits[0];
        $count = $hits[1];
        foreach ($data as &$d) {
            $d['description'] = !empty($dateType[$id][2]) ? $d[$dateType[$id][2]] : '';

            $den = $dateTypeMap[$id];
            if ($id == 57) { // 57指标正确性正好和其他相反
                $d['numerator'] = $d[$den[0]] == 1 ? 0 : 1;
            } else {
                $d['numerator'] = $d[$den[0]];
            }
            $d['denominator'] = !empty($d[$den[1]]) ? $d[$den[1]] : '';
            //心梗 脑梗 发病时间
            if ($id == 60 || $id == 61) {
                $ngXgData = $this->getNgXgData($id, $d);
                $d['fbsj'] = $ngXgData ? $ngXgData->fbsj_datetime : '';
                $d['fbsj_s'] = $ngXgData ? $ngXgData->fbsj_s : '';
                $d['zhusu'] = $ngXgData ? $ngXgData->zhusu : '';
                $d['sssj'] = $ngXgData ? $ngXgData->sssj_datetime : '';
                $d['zyzbbh'] = $ngXgData ? $ngXgData->ICD10_ID1 : '';
                $d['zyzdmc'] = $ngXgData ? $ngXgData->ICD10_NAME : '';
                $d['ssbh'] = $ngXgData ? $ngXgData->ICD9_ID1 : '';
                $d['ssmc'] = $ngXgData ? $ngXgData->ICD9_NAME : '';

                if ($d['description']) {
                    preg_replace("/发病时间【(.*?)】/", '发病时间【' . $d['fbsj'] . '】', $d['description']);
                    preg_replace("/发病时间-时【(.*?)】/", '发病时间-时【' . $d['fbsj_s'] . '】', $d['description']);
                }
            }
            if ($id == 62) {
                $ngXgData = $this->getNgXgData($id, $d);
                $d['fbsj'] = $ngXgData ? $ngXgData->fbsj_datetime : '';
                $d['rssj'] = $ngXgData ? $ngXgData->rssj : '';
                $d['yzmc'] = $ngXgData ? $ngXgData->yzmc : '';
                $d['XZJDSJ'] = $ngXgData ? $ngXgData->XZJDSJ : '';
                $d['zyzbbh'] = $ngXgData ? $ngXgData->ICD10_ID1 : '';
                $d['zyzdmc'] = $ngXgData ? $ngXgData->ICD10_NAME : '';
            }
            $d['AAB01'] = $d['AAB01'] == '1970-01-01 00:00:00' ? '' : $d['AAB01'];
            $d['AAC01'] = $d['AAC01'] == '1970-01-01 00:00:00' ? '' : $d['AAC01'];
        }

        return ['count' => $count, 'data' => $data];
    }

    /**
     * 获取心梗 脑梗指标数据
     * @param $type
     * @param $data
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getNgXgData($type, $data)
    {
        $BLBH = '';
        if ($type == 60 || $type == 62) {
            $BLBH = $data['NG_BLBH'];
        }
        if ($type == 61) {
            $BLBH = $data['XG_BLBH'];
        }

        return Pszb::query()->where('BLBH', $BLBH)->first();
    }


    /**
     * 不合理的数据复制
     * update patient_info_target set numerator_bhlbl=0,denominator_bhlbl=0,bhlbl_content="";
     */
    public
    function buheliCopy($zyh = "", $start_time = "", $end_time = "")
    {

        $setName = 'quality_zb_buheli';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $pageSize = 1000;
        $startTime = $this->startTime;
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > time()) {
                break;
            }
            var_dump('时间：' . date("Y-m-d H:i:s", $startTime));
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->where('AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                ->where('AAC01', '<=', date("Y-m-d H:i:s", $entTime))
                ->get(['MED_REC_ID', 'id', 'AAC01'])->toArray();

            $startTime = $entTime;

            $nos = array_column($data, 'MED_REC_ID');
            // 获取病例对应的病程信息
            $bingcheng = EMR_BL_BL01::query()
                ->whereIn('JZHM', $nos)
                ->where('EMR_BL_BL01.BLZT', '<>', 9)
                ->where('BLLB', '=', '294')
                ->get(['JZHM', 'bcts', 'bc_content', 'MBLB', 'BLMC'])
                ->toArray();
            if (empty($bingcheng)) {
                continue;
            }

            $newBingcheng = [];
            foreach ($bingcheng as $val) {
                if (!$val['bcts'] && !$val['bc_content']) {
                    continue;
                }
                if (strpos($val['BLMC'], '首次病程') !== false) {
                    $newBingcheng[$val['JZHM']]['tese'] = $val;
                }
                $newBingcheng[$val['JZHM']]['other'][] = $val;
            }
            // 获取病例对应的入院信息
            $xbs = EMR_BL_BL01::query()
                ->join('EMR_BL_BLXG as xg', 'EMR_BL_BL01.BLBH', '=', 'xg.BLBH')
                ->whereIn('EMR_BL_BL01.JZHM', $nos)
                ->where('EMR_BL_BL01.BLLB', '=', '292')
                ->where('EMR_BL_BL01.BLZT', '<>', 9)
                ->get(['EMR_BL_BL01.JZHM', 'xg.HJNR'])
                ->toArray();
            $xbs = array_column($xbs, null, 'JZHM');

            $fenzi = [];
            foreach ($data as $y) {
                $lastId = $y['AAC01'];
                var_dump($lastId);
                // 1、 整个病程中，每次记录的病程不能相同
                $other = !empty($newBingcheng[$y['MED_REC_ID']]['other']) ? $newBingcheng[$y['MED_REC_ID']]['other'] : [];
                $isIdentical = false;
                $resBc = '';
                foreach ($other as $key => $val) {
                    $bcContent = $val['bc_content'] ?: '';
                    if (empty($bcContent)) {
                        continue;
                    }

                    // 将连续内容按照75%拆分
                    //                    $chuckStr = [];
                    ////                    $strLength = mb_strlen($bcContent);
                    ////                    $checkLength = ceil($strLength * 0.75);
                    ////                    for ($i = 0; $i < $strLength; $i++) {
                    ////                        $resStr = mb_substr($bcContent, $i, $checkLength);
                    ////                        if (mb_strlen($resStr) < $checkLength) {
                    ////                            break;
                    ////                        }
                    ////                        if ($resStr) {
                    ////                            $chuckStr[] = $resStr;
                    ////                        }
                    ////                    }
                    ///
                    ///

                    $bcContentArray = preg_split('//u', $bcContent, null, PREG_SPLIT_NO_EMPTY);
                    $bcContentArray = array_unique(array_filter($bcContentArray));

                    $isIdenticalOther = false;
                    foreach ($other as $key1 => $val1) {
                        // 比较过的和数据本身不比较
                        if ($key >= $key1 || empty($val1['bc_content'])) {
                            continue;
                        }

                        $bcContentArray1 = preg_split('//u', $val1['bc_content'], null, PREG_SPLIT_NO_EMPTY);
                        $bcContentArray1 = array_unique(array_filter($bcContentArray1));
                        // 获取交集
                        $res = array_intersect($bcContentArray, $bcContentArray1);
                        if (count($res) / count($bcContentArray) >= 0.9) {
                            $fenzi[] = ['MED_REC_ID' => $y['MED_REC_ID'], 'bhlbl_content' => ' 病程记录 【' . $val['BLMC'] . '】和病程【' . $val1['BLMC'] . '】 90%雷同'];
                            $isIdenticalOther = true;
                            $isIdentical = true;
                            break;
                        }

                        //                        foreach ($chuckStr as $item) {
                        //                            if (strpos($val1['bc_content'], $item) !== false) {
                        //                                $fenzi[] = ['MED_REC_ID' => $y['MED_REC_ID'], 'bhlbl_content' => ' 病程记录 【' . $val['BLMC'] . '】和病程【' . $val1['BLMC'] . '】 75%雷同'];
                        //                                $isIdenticalOther = true;
                        //                                $isIdentical = true;
                        //                                break;
                        //                            }
                        //                        }
                    }
                    if ($isIdenticalOther === true) {
                        break;
                    }
                }

                // 如果病程之间没有雷同，则校验病例特色和现病史之间的雷同
                if ($isIdentical === false) {

                    if (empty($xbs[$y['MED_REC_ID']])) {
                        continue;
                    }

                    if (empty($newBingcheng[$y['MED_REC_ID']]['tese'])) {
                        continue;
                    }
                    $tese = $newBingcheng[$y['MED_REC_ID']]['tese'] ? $newBingcheng[$y['MED_REC_ID']]['tese']['bcts'] : '';
                    if (!$tese) {
                        continue;
                    }
                    // 病程特色内容
                    $teseContentArray = preg_split('//u', $tese, null, PREG_SPLIT_NO_EMPTY);
                    $teseContentArray = array_unique(array_filter($teseContentArray));

                    // 入院记录
                    $ryjlXbs = $xbs[$y['MED_REC_ID']]['HJNR'];
                    $ryjlXbsContentArray = preg_split('//u', $ryjlXbs, null, PREG_SPLIT_NO_EMPTY);
                    $ryjlXbsContentArray = array_unique(array_filter($ryjlXbsContentArray));
                    // 获取交集
                    $res = array_intersect($teseContentArray, $ryjlXbsContentArray);
                    if (count($res) / count($teseContentArray) >= 0.75) {
                        $fenzi[] = ['MED_REC_ID' => $y['MED_REC_ID'], 'bhlbl_content' => '病例特点【90%内容】和入院记录雷同'];
                    }

                    // 病程特色内容
                    //                    $chuckStr = [];
                    //                    $strLength = mb_strlen($tese);
                    //                    $checkLength = ceil($strLength * 0.75);
                    //                    for ($i = 0; $i < $strLength; $i++) {
                    //                        $resStr = mb_substr($tese, $i, $checkLength);
                    //                        if (mb_strlen($resStr) < $checkLength) {
                    //                            break;
                    //                        }
                    //                        if ($resStr) {
                    //                            $chuckStr[] = $resStr;
                    //                        }
                    //                    }
                    //
                    //                    // 入院记录
                    //                    $ryjl = $xbs[$y['MED_REC_ID']]['HJNR'];
                    //                    foreach ($chuckStr as $item) {
                    //                        if (strpos($ryjl, $item) !== false) {
                    //                            $fenzi[] = ['MED_REC_ID' => $y['MED_REC_ID'], 'bhlbl_content' => '病例特点【75%内容】和入院记录雷同'];
                    //                            break;
                    //                        }
                    //                    }
                }
            }


            $allData = array_unique($nos);
            if ($allData) {
                foreach ($allData as $d) {
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => $d], ['denominator_bhlbl' => 1]);
                }
            }
            if ($fenzi) {
                foreach ($fenzi as $d) {
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => (string)$d['MED_REC_ID']], ['numerator_bhlbl' => 1, 'bhlbl_content' => $d['bhlbl_content']]);
                }
            }
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        var_dump('numerator_bhlbl_end');
    }

    /**
     * 手术相关记录完整率
     * update EMR_BL_BLXG set is_operation=1 where HJNR like "%术前小结及术前讨论结论记录%"
     * update patient_info_target set denominator_operation=0,numerator_operation=0,operation_error="";
     */
    public
    function operationComplete($zyh = "", $start_time = "", $end_time = "")
    {
        $setName = 'quality_zb_operationComplete';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $pageSize = 500;
        $startTime = $this->startTime;
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > time()) {
                break;
            }
            var_dump('时间：' . date("Y-m-d H:i:s", $startTime));
            // 所有符合条件的病例信息
            $patientInfo = $patientInfoCopy = PatientInfo::query()
                ->where('AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                ->where('AAC01', '<=', date("Y-m-d H:i:s", $entTime))
                ->get(['MED_REC_ID', 'AAC01', 'id', 'AAC01'])->toArray();
            $startTime = $entTime;
            $patientInfo = array_column($patientInfo, 'AAC01', 'MED_REC_ID');

            // 所有住院号
            $ids = array_keys($patientInfo);
            // 所有符合条件的病例信息
            $data = SSSQ::query()
                ->where('ZFBZ', '=', 0)
                ->whereIn('ZYH', $ids)
                ->get(['ZYH', 'NSSMC', 'SSRQ'])->toArray();
            if (!$data) {
                continue;
            }
            $newData = [];
            foreach ($data as $item) {
                $item['AAC01'] = $patientInfo[$item['ZYH']];
                $newData[$item['ZYH']][] = $item;
            }

            // 获取数据的最后一条
            $lastData = array_pop($patientInfoCopy);
            $lastId = $lastData['AAC01'];

            $fenzi = [];
            foreach ($newData as $zyh => $data) {
                $zyh = (string)$zyh;
                var_dump($zyh);
                $operationError = [];
                $isError = 1;
                foreach ($data as $y) {
                    $ssrq = $y['SSRQ'] ?: '';
                    $operation_error = '';
                    if (empty($y['NSSMC'])) {
                        $isError = 0;
                        $operationError[] = '拟手术名称为空';
                        continue;
                    }

                    // 获取医嘱信息
                    $yizhu = Yzb::query()
                        ->where('ZYH', '=', $y['ZYH'])
                        ->where('is_operation', '=', 1)
                        ->where('YZMC', 'like', '%' . $y['NSSMC'] . '%')
                        ->limit(1)
                        ->get(['KZSJ', 'JJYZ'])->toArray();
                    if (!$yizhu) {
                        $operation_error .= '医嘱【 拟*年*月*日' . $y['NSSMC'] . '（无） 】';
                        $isError = 0;
                    } else {
                        $operation_error .= '医嘱【 拟*年*月*日' . $y['NSSMC'] . '（有） 】';

                        $kzsj = $yizhu[0]['KZSJ'];
                        $kzsj = strtotime($kzsj);
                        // 不是紧急医嘱则校验
                        if ($yizhu[0]['JJYZ'] == 0) {
                            $bingcheng = EMR_BL_BL01::query()
                                ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                                ->where('EMR_BL_BL01.JZHM', '=', $y['ZYH'])
                                ->where('EMR_BL_BL01.BLLB', '=', 294)
                                ->where('EMR_BL_BL01.BLZT', '<>', 9)
                                ->where('EMR_BL_BLXG.is_operation', '=', 1)
                                ->limit(1)
                                ->get(['CJSJ'])->toArray();
                            if (!$bingcheng) {
                                $operation_error .= '术前小结及术前讨论结论记录【无】';
                                $isError = 0;
                            } else {
                                $cjsj = strtotime($bingcheng[0]['CJSJ']);
                                $operation_error .= '术前小结及术前讨论结论记录【有、 ' . $bingcheng[0]['CJSJ'] . '、';
                                if ($cjsj < $kzsj) {
                                    $operation_error .= '<开嘱时间】';
                                } else {
                                    $operation_error .= '>开嘱时间】';
                                }
                            }
                        }


                        // 获取手术安全核查表
                        //                        $ssaqhcb = EMR_BL_BL01::query()
                        //                            ->where('JZHM', '=', $y['ZYH'])
                        //                            ->where('EMR_BL_BL01.BLZT', '=', 1)
                        //                            ->where('BLMC', '=', '手术安全核查表')
                        //                            ->limit(1)
                        //                            ->get(['operation_time'])->toArray();
                        //                        if (!$ssaqhcb) {
                        //                            $operation_error .= 'BL01表【手术安全核查表（无）】';
                        //                            $isError = 0;
                        //                        } else {
                        //                            $operation_error .= 'BL01表【手术安全核查表】';
                        //                        }

                        // 3、手术记录时间在开嘱时间之后
                        $ssrq = strtotime(date("Y-m-d", strtotime($ssrq))); // 手术日期
                        $ssjl = EMR_BL_BL01::query()
                            ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                            ->where('EMR_BL_BL01.JZHM', '=', $y['ZYH'])
                            ->whereRaw('(EMR_BL_BLXG.HJNR like "%手术记录%" or EMR_BL_BLXG.HJNR like "%剖宫产记录%")')
                            ->where(Db::raw('UNIX_TIMESTAMP(EMR_BL_BL01.CJSJ)'), '>', $kzsj)
                            ->where('EMR_BL_BL01.BLZT', '<>', 9)
                            ->limit(1)
                            ->get(['EMR_BL_BL01.CJSJ', 'EMR_BL_BL01.operation_time'])->toArray();
                        if (!$ssjl) {
                            $operation_error .= '手术记录【无】';
                            $isError = 0;
                        } else {
                            $operation_error .= '手术记录【（有）、' . $ssjl[0]['CJSJ'] . '、';
                            if (!empty($bingcheng)) {
                                $cjsj = strtotime($bingcheng[0]['CJSJ']);
                                if ($cjsj < $kzsj) {
                                    $operation_error .= '<开嘱时间';
                                } else {
                                    $operation_error .= '>开嘱时间';
                                }
                            }
                            $operation_error .= '】';
                        }

                        // 手术同意书
                        $sstys1 = EMR_BL_BL01::query()
                            ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                            //                            ->where(Db::raw('UNIX_TIMESTAMP(EMR_BL_BL01.CJSJ)'), '>', $kzsj)
                            ->where('EMR_BL_BL01.JZHM', '=', $y['ZYH'])
                            ->whereIn('EMR_BL_BL01.BLLB', [303, 329])
                            ->where('EMR_BL_BL01.BLZT', '<>', 9)
                            ->where('EMR_BL_BLXG.HJNR', 'like', "%手术同意书%")
                            ->limit(1)
                            ->get(['CJSJ', 'BLMC'])->toArray();
                        if (!empty($sstys1)) {
                            $operation_error .= '手术同意书【' . $sstys1[0]['BLMC'] . '（有） 】';
                        } else {
                            $sstys2 = EMR_BL_BL01::query()
                                ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                                //                            ->where(Db::raw('UNIX_TIMESTAMP(EMR_BL_BL01.CJSJ)'), '>', $kzsj)
                                ->where('EMR_BL_BL01.JZHM', '=', $y['ZYH'])
                                ->whereIn('EMR_BL_BL01.BLLB', [303, 329])
                                ->where('EMR_BL_BL01.BLZT', '<>', 9)
                                ->where('EMR_BL_BLXG.HJNR', 'like', "%知情同意书%")
                                ->limit(1)
                                ->get(['CJSJ', 'BLMC'])->toArray();
                            if (!empty($sstys2)) {
                                $operation_error .= '知情同意书【' . $sstys2[0]['BLMC'] . '（有） 】';
                            } else {
                                $operation_error .= '手术记录【手术同意书 或者 知情同意书（无） 】';
                                $isError = 0;
                            }
                        }

                        // 4、手术时间术后3天有病程
                        if (!empty($ssjl[0])) {
                            $cysj = strtotime(date("Y-m-d", strtotime($y['AAC01'])));
                            $operationTime = $ssjl[0]['operation_time'];
                            // 如果当天出院则只需要出院当天的病程记录

                            $bingcheng1 = 0;
                            $bingcheng2 = 0;
                            $bingcheng3 = 0;
                            if ($cysj == $operationTime) {
                                $bingcheng1 = EMR_BL_BL01::query()
                                    ->where('JZHM', '=', $y['ZYH'])
                                    ->where('BLLB', '=', 294)
                                    ->where('EMR_BL_BL01.BLZT', '<>', 9)
                                    ->whereBetween(Db::raw('UNIX_TIMESTAMP(ZXSJ)'), [$operationTime, $operationTime + 24 * 3600])
                                    ->get(['CJSJ', 'BLMC'])->toArray();
                                $bingcheng2 = 1;
                                $bingcheng3 = 1;
                            } else {
                                if ($cysj && $cysj >= $operationTime + 24 * 3600) {
                                    $bingcheng1 = EMR_BL_BL01::query()
                                        ->where('JZHM', '=', $y['ZYH'])
                                        ->where('BLLB', '=', 294)
                                        ->where('EMR_BL_BL01.BLZT', '!=', 9)
                                        ->whereBetween(Db::raw('UNIX_TIMESTAMP(ZXSJ)'), [$operationTime + 24 * 3600, $operationTime + 24 * 3600 * 2])
                                        ->get(['CJSJ', 'BLMC'])->toArray();
                                }
                                if ($cysj && $cysj >= $operationTime + 2 * 24 * 3600) {
                                    $bingcheng2 = EMR_BL_BL01::query()
                                        ->where('JZHM', '=', $y['ZYH'])
                                        ->where('BLLB', '=', 294)
                                        ->where('EMR_BL_BL01.BLZT', '!=', 9)
                                        ->whereBetween(Db::raw('UNIX_TIMESTAMP(ZXSJ)'), [$operationTime + 2 * 24 * 3600, $operationTime + 24 * 3600 * 3])
                                        ->get(['CJSJ', 'BLMC'])->toArray();
                                }
                                if ($cysj && $cysj >= $operationTime + 3 * 24 * 3600) {
                                    $bingcheng3 = EMR_BL_BL01::query()
                                        ->where('JZHM', '=', $y['ZYH'])
                                        ->where('BLLB', '=', 294)
                                        ->where('EMR_BL_BL01.BLZT', '!=', 9)
                                        ->whereBetween(Db::raw('UNIX_TIMESTAMP(ZXSJ)'), [$operationTime + 3 * 24 * 3600, $operationTime + 24 * 3600 * 4])
                                        ->get(['CJSJ', 'BLMC'])->toArray();
                                }
                            }

                            if (!$bingcheng1 || !$bingcheng2 || !$bingcheng3) {
                                $operation_error .= '术后病程记录【（无）】';
                                $isError = 0;
                            } else {
                                $operation_error .= '术后病程记录【（有）、';
                                if (!empty($bingcheng1[0]['CJSJ'])) {
                                    $operation_error .= $bingcheng1[0]['CJSJ'] . '、';
                                }
                                if (!empty($bingcheng2[0]['CJSJ'])) {
                                    $operation_error .= $bingcheng2[0]['CJSJ'] . '、';
                                }
                                if (!empty($bingcheng3[0]['CJSJ'])) {
                                    $operation_error .= $bingcheng3[0]['CJSJ'] . '、';
                                }
                                $operation_error .= '】';
                            }
                        }
                    }

                    $operationError[] = $operation_error;
                }

                $operationErrorContent = '';
                foreach ($operationError as $k => $item) {
                    $operationErrorContent .= ($k + 1) . '：' . $item;
                }
                $fenzi[] = ['ZYH' => $zyh, 'numerator_operation' => $isError, 'operation_error' => $operationErrorContent]; // 分子+1
            }

            if ($fenzi) {
                foreach ($fenzi as $d) {
                    $d['ZYH'] = (string)$d['ZYH'];
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => $d['ZYH']], ['denominator_operation' => 1, 'numerator_operation' => $d['numerator_operation'], 'operation_error' => $d['operation_error']]);
                }
            }
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        var_dump('手术相关记录完整率_end');
    }


    /**
     * wcy
     * CT/MRI检查记录符合率接口
     * @param $where
     * @return array
     */
    protected
    function getIrcrData(array $where = [])
    {
        $year = $where['year'] ?? Carbon::now()->year;
        $data = [];

        for ($month = 1; $month <= 12; $month++) {
            $startTime = strtotime($year . '-' . $month . '-01');
            $t = date('t', $startTime);
            $endTime = $startTime + $t * 3600 * 24;

            // 分母
            $denominatorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_ct', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');

            // 分子
            $numeratorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_ct', '=', 1)
                ->where('patient_info_target.numerator_ct', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');

            // 计算符合率
            $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
            $data[] = [
                'title' => 'CT/MRI检查记录符合率',
                'time' => $year . '-' . sprintf('%02s', $month),
                'denominator' => $denominatorCount,
                'numerator' => $numeratorCount,
                'res' => $complianceRate,
                'source' => '系统提取',
            ];
        }

        // 分母
        $denominatorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_ct', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 分子
        $numeratorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_ct', '=', 1)
            ->where('patient_info_target.numerator_ct', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');

        // 计算符合率
        $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
        $data[] = [
            'title' => 'CT/MRI检查记录符合率',
            'time' => '全年',
            'denominator' => $denominatorCount,
            'numerator' => $numeratorCount,
            'res' => $complianceRate,
            'source' => '系统提取',
        ];

        return $data;
    }

    /**
     * wcy
     * 植入物相关记录符合率
     * @param $where
     * @return array
     */
    protected
    function getZrwData(array $where = [])
    {
        $year = $where['year'] ?? Carbon::now()->year;
        $data = [];

        for ($month = 1; $month <= 12; $month++) {
            $startTime = strtotime($year . '-' . $month . '-01');
            $t = date('t', $startTime);
            $endTime = $startTime + $t * 3600 * 24;

            // 分母
            $denominatorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_zrw', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');;
            // 分子
            $numeratorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_zrw', '=', 1)
                ->where('patient_info_target.numerator_zrw', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');

            // 计算符合率
            $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
            $data[] = [
                'title' => '植入物相关记录符合率',
                'time' => $year . '-' . sprintf('%02s', $month),
                'denominator' => $denominatorCount,
                'numerator' => $numeratorCount,
                'res' => $complianceRate,
                'source' => '系统提取',
            ];
        }

        // 分母
        $denominatorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_zrw', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 分子
        $numeratorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_zrw', '=', 1)
            ->where('patient_info_target.numerator_zrw', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 计算符合率
        $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
        $data[] = [
            'title' => '植入物相关记录符合率',
            'time' => '全年',
            'denominator' => $denominatorCount,
            'numerator' => $numeratorCount,
            'res' => $complianceRate,
            'source' => '系统提取',
        ];

        return $data;
    }

    /**
     * wcy
     * 细菌培养相关记录符合率
     * @param $where
     * @return array
     */
    protected
    function getXjpyData(array $where = [])
    {
        $year = $where['year'] ?? Carbon::now()->year;
        $data = [];

        for ($month = 1; $month <= 12; $month++) {
            $startTime = strtotime($year . '-' . $month . '-01');
            $t = date('t', $startTime);
            $endTime = $startTime + $t * 3600 * 24;

            // 分母
            $denominatorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_xjpy', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');;
            // 分子
            $numeratorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_xjpy', '=', 1)
                ->where('patient_info_target.numerator_xjpy', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');

            // 计算符合率
            $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
            $data[] = [
                'title' => '细菌培养相关记录符合率',
                'time' => $year . '-' . sprintf('%02s', $month),
                'denominator' => $denominatorCount,
                'numerator' => $numeratorCount,
                'res' => $complianceRate,
                'source' => '系统提取',
            ];
        }

        // 分母
        $denominatorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_xjpy', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 分子
        $numeratorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_xjpy', '=', 1)
            ->where('patient_info_target.numerator_xjpy', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 计算符合率
        $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
        $data[] = [
            'title' => '细菌培养相关记录符合率',
            'time' => '全年',
            'denominator' => $denominatorCount,
            'numerator' => $numeratorCount,
            'res' => $complianceRate,
            'source' => '系统提取',
        ];

        return $data;
    }

    /**
     * wcy
     * 临床用血相关记录符合率
     * @param $where
     * @return array
     */
    protected
    function getLcyxData(array $where = [])
    {
        $year = $where['year'] ?? Carbon::now()->year;
        $data = [];

        for ($month = 1; $month <= 12; $month++) {
            $startTime = strtotime($year . '-' . $month . '-01');
            $t = date('t', $startTime);
            $endTime = $startTime + $t * 3600 * 24;

            // 分母
            $denominatorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_lcyx', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');
            // 分子
            $numeratorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_lcyx', '=', 1)
                ->where('patient_info_target.numerator_lcyx', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');
            // 计算符合率
            $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
            $data[] = [
                'title' => '临床用血相关记录符合率',
                'time' => $year . '-' . sprintf('%02s', $month),
                'denominator' => $denominatorCount,
                'numerator' => $numeratorCount,
                'res' => $complianceRate,
                'source' => '系统提取',
            ];
        }

        // 分母
        $denominatorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_lcyx', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 分子
        $numeratorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_lcyx', '=', 1)
            ->where('patient_info_target.numerator_lcyx', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 计算符合率
        $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
        $data[] = [
            'title' => '临床用血相关记录符合率',
            'time' => '全年',
            'denominator' => $denominatorCount,
            'numerator' => $numeratorCount,
            'res' => $complianceRate,
            'source' => '系统提取',
        ];

        return $data;
    }

    public function addZbData($saveData)
    {
        foreach ($saveData as $value) {
            $where = [
                'year' => $value['year'],
                'month' => $value['month'],
                'flag' => $value['flag']
            ];
            $saveData = [
                $value['type'] => $value['num']
            ];

            PatientInfoTargetNew::query()->updateOrInsert($where, $saveData);
        }


        return true;
    }

    public function hrConfigZb($type, $where)
    {
        $year = $where['year'] ?? date('Y');

        $data = [];
        $source = '系统提取';
        if ($type == 11 && $where['request_source'] == 2) {
            $source = '人工录入';
        } elseif ($type == 12) {
            $source = '人工录入';
        } elseif ($type == 13 && $where['request_source'] == 2) {
            $source = '人工录入';
        }
        for ($i = 1; $i <= 12; $i++) {
            $month = sprintf('%02s', $i);
            $zbData = PatientInfoTargetNew::query()
                ->where('year', '=', $year)
                ->where('month', '=', $month)
                ->where('flag', '=', $type)
                ->first();
            if (!$zbData) {
                $data[] = [
                    'time' => $year . '-' . $month,
                    'denominator' => 0,
                    'numerator' => 0,
                    'res' => 0,
                    'source' => $source
                ];
            } else {
                $time = $year . '-' . $month;
                if ($type == 12) {
                    $numerator = $zbData->numerator;
                } else {
                    $numerator = PatientInfo::query()
                        ->whereBetween('AAC01', [$time . '-01 00:00:00', $time . '-31 23:59:59'])
                        ->count();
                }

                $res = 0;
                if ($numerator > 0 && $zbData->denominator > 0) {
                    $res = bcdiv((string)$numerator, (string)$zbData->denominator, 4);
                }

                $data[] = [
                    'time' => $time,
                    'denominator' => $zbData->denominator,
                    'numerator' => $numerator,
                    'res' => $res,
                    'source' => $source
                ];
            }
        }

        $yearData = ['time' => '全年', 'denominator' => 0, 'numerator' => 0, 'res' => 0, 'source' => $source];
        foreach ($data as $val) {
            $yearData['denominator'] += $val['denominator'];
            $yearData['numerator'] += $val['numerator'];
        }

        $yearData['res'] = 0;
        if ($yearData['numerator'] > 0 && $yearData['denominator'] > 0) {
            $yearData['res'] = bcdiv((string)$yearData['numerator'], (string)$yearData['denominator'], 4);
        }
        $data[] = $yearData;

        return $data;
    }


    public function checkCaseList($zyh = 0)
    {

        $pageSize = 1000;
        $page = 1;
        $settingId = 4;
        $lastId = Setting::query()->where('id', '=', $settingId)->pluck('content')->first();
        $lastNo = $lastId ?: 0;
        if ($zyh) {
            $lastNo = 0;
        }
        //        $start = date('Y-m-d 00:00:00',strtotime("-2 day",time()));
        //        $end = date('Y-m-d H:i:s',time());
        $column = ['BLBH', 'MBLB'];
        $lastBLBH = 0;
        while (1) {
            $page++;
            $offset = ($page - 1) * $pageSize;
            echo $page . PHP_EOL;
            $query = EMR_BL_BL01::query()
                ->whereIn("BLLB", [1, 294])
                ->where('BLBH', '>', $lastNo)
                ->where('BLZT', '!=', 9)
                //                ->whereBetween('updated_at',[$start,$end])
                ->where('BLBH', '!=', 0)
                ->orderBy("BLBH", "asc")
                ->select($column)
                ->offset($offset)
                ->LIMIT($pageSize);

            if ($zyh) {
                $query = $query->whereIn('JZHM', $zyh);
            }
            $res = $query->get()->toArray();
            if (!$res) {
                break;
            }
            foreach ($res as $no) {
                $lastBLBH = $no['BLBH'];
                self::checkCase($no['BLBH'], $no['MBLB']);
            }
        }
        Setting::query()->where('id', '=', $settingId)->update(['content' => $lastBLBH]);
        return true;
    }

    /**
     * @param string $no
     * @param string $mblb
     * @return array|mixed|string
     */
    public static function checkCase($no = '', $mblb = '')
    {
        $title = ['病例特点', '诊断依据', '鉴别诊断', '诊疗计划'];
        $res = EMR_BL_BLXG::getById($no);
        if (!$res) {
            return '';
        }
        $caseContent = $res[0]['HJNR'];
        // 整理数据，将数据整理成数组结构
        $caseContent = str_replace("\r\n", "", $caseContent);
        $caseContent = str_replace("\n", "", $caseContent);
        $caseContent = str_replace(['{', '}'], '', $caseContent);
        //        $caseContent = str_replace("：", ":", $caseContent);
        //        $caseContent = str_replace(['.', '(', ')', '（', '）', "“", "”", "，", "。", "；", "、", ' '], "", $caseContent);
        // 非首次病程则直接返回数据
        if ($mblb != 295) {
            return $caseContent;
        }
        foreach ($title as $t) {
            $caseContent = str_replace($t, "|&|" . $t . '||', $caseContent);
        }
        $caseContentArr = explode("|&|", $caseContent);

        $newData = [];
        foreach ($caseContentArr as $item) {
            $itemArr = explode("||", $item);
            if (!in_array($itemArr[0], $title) || !isset($itemArr[1])) {
                continue;
            }
            $info['title'] = $itemArr[0];
            $info['value'] = $itemArr[1] ?? '';
            $newData[] = $info;
        }
        if (empty($newData)) {
            return '';
        }

        $bcStr = $newData[0]['value'];
        //        $myArray = preg_split('//u', $bcStr, null, PREG_SPLIT_NO_EMPTY);
        if ($mblb == 295) {
            $eidtData = ['bcts' => $bcStr];
        } else {
            $eidtData = ['bc_content' => $bcStr];
        }
        EMR_BL_BL01::updateById($no, $eidtData);
    }
}
