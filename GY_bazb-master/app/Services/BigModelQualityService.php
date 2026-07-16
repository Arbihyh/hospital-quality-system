<?php

namespace App\Services;

use App\Model\Staff;
use App\Model\ZY_BRRY;
use App\Model\CaseRule;
use App\Model\TableDict;
use App\Model\CaseQuality;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use App\Model\ModelConfig;
use App\Model\PatientInfo;
use App\Model\CaseQualityZm;
use App\Model\PatientInfoV2;
use App\Model\BigModelTemplate;
use App\Model\CaseQualityCount;
use App\Model\CaseQualityDoctor;
use Illuminate\Support\Facades\Log;

class BigModelQualityService
{
    private $blbh;
    private $bllb;
    public function __construct()
    {
        $this->blbh = '';
        $this->bllb = '';
    }

    /***
     * 带参数质控
     */
    public function singlePatientQualityBybllb($zyh, $bllb, $blbh)
    {
        $this->blbh = $blbh;
        $this->bllb = $bllb;
        return $this->singlePatientQuality($zyh);
    }


    /**
     * 单个患者大模型质控接口,可调用
     *
     * @param Request $request
     * @return array
     */
    public function singlePatientQuality($ZYH)
    {
        try {
            // 获取参数
            $zyh = $ZYH;
            $bllb = $this->bllb;
            $blbh = $this->blbh;

            // 参数验证
            if (empty($zyh)) {
                return ToolsService::returnData(4001, [], '住院号不能为空');
            }


            // 记录开始时间
            $startTime = microtime(true);
            Log::info("开始单个患者质控", ['zyh' => $zyh, 'start_time' => date('Y-m-d H:i:s')]);

            // 获取ModelConfig配置
            $modelConfig = ModelConfig::query()->where('id', 1)->first();
            if (!$modelConfig) {
                Log::error("未找到模型配置信息");
                return ToolsService::returnData(4002, [], '未找到模型配置信息');
            }

            // 验证患者是否存在
            $patient = PatientInfo::query()->where("MED_REC_ID", $zyh)->first(["MED_REC_ID"]);
            if (!$patient) {
                return ToolsService::returnData(4003, [], '未找到该住院号的患者信息');
            }

            // 获取表字典
            $tableDict = TableDict::query()->get()->toArray();
            $tableDict = array_column($tableDict, null, 'field');

            // 获取启用的大模型质控模板
            $templates = null;
            if (!empty($bllb) && !empty($blbh)) {
                $templates = BigModelTemplate::query()
                    ->where('status', 1) // 病历质控类型
                    ->where('type', 1)
                    ->where('mblb', $bllb)
                    ->get()
                    ->toArray();
            } else {
                $templates = BigModelTemplate::query()
                    ->where('status', 1) // 病历质控类型
                    ->where('type', 1)
                    ->get()
                    ->toArray();
            }


            if (empty($templates)) {
                return ToolsService::returnData(4004, [], '未找到可用的质控模板');
            }

            $processedCount = 0;
            $errorCount = 0;
            $results = [];

            // 质控成功后删除大模型的质控结果
            $allTemplatesQuery = BigModelTemplate::query()->where('type', 1);
            if (!empty($bllb) && !empty($blbh)) {
                $allTemplatesQuery->where('mblb', $bllb);
            }
            $allBitModelRuleIds = $allTemplatesQuery->pluck('rule_id')->toArray();

            CaseQuality::query()->where('JZHM', '=', $patient['MED_REC_ID'])
                ->where("is_artificial", "=", 0)
                ->whereIn("rule_id", $allBitModelRuleIds)
                ->delete();

            CaseQualityZm::query()->where('JZHM', '=', $patient['MED_REC_ID'])
                ->where("is_artificial", "=", 0)
                ->whereIn("rule_id", $allBitModelRuleIds)
                ->delete();


            // 处理每个模板
            foreach ($templates as $template) {
                try {
                    $result = $this->processTemplate($template, $patient, $tableDict, $modelConfig);
                    $results[] = $result;
                    $processedCount++;

                    if ($result['has_error']) {
                        $errorCount++;
                    }
                } catch (\Exception $e) {
                    Log::error("处理模板失败", [
                        'template_id' => $template['id'],
                        'zyh' => $zyh,
                        'error' => $e->getMessage()
                    ]);
                    $results[] = [
                        'template_id' => $template['id'],
                        'template_title' => $template['title'] ?? '',
                        'success' => false,
                        'error' => $e->getMessage(),
                        'has_error' => false
                    ];
                }
            }

            // 计算运行时间
            $endTime = microtime(true);
            $runTime = round($endTime - $startTime, 2);

            Log::info("单个患者质控完成", [
                'zyh' => $zyh,
                'processed_templates' => $processedCount,
                'error_count' => $errorCount,
                'run_time' => $runTime
            ]);

            $staff = Staff::query()->get(["code", "name", 'ksdm', 'YGBH'])->toArray();
            $staff = array_column($staff, null, "code");
            $staffByBh = array_column($staff, null, "YGBH");
            $caseRule = CaseRule::query()->where("status", 1)->get()->toArray();
            $res = CaseQualityZm::getByJZHM($patient['MED_REC_ID']);
            $score = 0;
            $defect = 'is_defect';

            // 记录所有和当前病案号有关的医生信息
            $userList = [];
            if ($res) {
                foreach ($res as &$v) {
                    if ($v['rule_id'] > 1000000) {
                        $s = $ruleSetting[$v['rule_id'] - 1000000] ?? '';
                        if (!empty($s)) {
                            $score += $s['score'];
                        }
                    } elseif (isset($caseRule[$v['rule_id']])) {
                        $score += $caseRule[$v['rule_id']]['score'];
                    }

                    // 收集质控病例所有病程关联的书写医师和签名医师
                    $basis = json_decode($v['basis'], true);
                    if (!empty($basis)) {
                        foreach ($basis as $basOne) {
                            if (is_array($basOne)) {
                                foreach ($basOne as $k => $blbh) {
                                    // 获取签名医师、书写医师
                                    if ($k == "BLBH") {
                                        $bl01 = EMR_BL_BL01::query()->where("BLBH", $blbh)->first(["SXYS", "JZHM", "BRBH"]);
                                        if ($bl01) {
                                            $bl01 = $bl01->toArray();
                                            if ($bl01["SXYS"]) {
                                                $userList[] = !empty($staffByBh[$bl01["SXYS"]]) ? $staffByBh[$bl01["SXYS"]]['code'] : $bl01["SXYS"];
                                            }
                                        }

                                        $blsy = EMR_BL_BLSY::query()->where("BLBH", $blbh)->get(["SYYS"])->toArray();
                                        if ($blsy) {
                                            $userList = array_merge($userList, array_column($blsy, "SYYS"));
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $diffScore = $score;
                $score = 100 - $score;
                if ($score > 90) {
                    $score_lv = '甲';
                } elseif ($score >= 75 && $score <= 90) {
                    $score_lv = '乙';
                } elseif ($score < 75) {
                    $score_lv = '丙';
                }
                Log::info("计算得分:-" . $score . "，病例质量：" . $score_lv);
                // is_case 将病例的质控状态改为未质控
                PatientInfo::query()->where('MED_REC_ID', '=', $patient['MED_REC_ID'])->update(['is_case' => 2, 'zm_score' => $score, 'zm_quality_time' => time(), 'zm_score_lv' => $score_lv]);
                ZY_BRRY::query()->where('ZYH', '=', $patient['MED_REC_ID'])->update(['zm_score' => $score, 'zm_score_lv' => $score_lv]);
                # 病案首页中 -- 主治医师、住院医师、诊疗组长、主任医师、质控医师关联质控结果，用于医师排名
                $brry = ZY_BRRY::query()->where('ZYH', '=', $patient['MED_REC_ID'])->first();
                if ($brry) {
                    $brry = $brry->toArray();
                    $PatientInfoV2 = PatientInfo::query()->where('ZYH', '=', $patient['MED_REC_ID'])->first(['AEE01_CODE', 'AEE02_CODE', 'AEE03_CODE', 'AEE04_CODE']);
                    $PatientInfoV2 = $PatientInfoV2 ? $PatientInfoV2->toArray() : [];
                    $userList = array_merge($userList, [$PatientInfoV2["AEE01_CODE"] ?? '', $PatientInfoV2["AEE02_CODE"] ?? '', $PatientInfoV2["AEE03_CODE"] ?? '', $PatientInfoV2["AEE04_CODE"] ?? '']);
                    $userList = array_unique($userList);
                    foreach ($userList as $v) {
                        if (!$v || empty($staff[$v])) {
                            continue;
                        }
                        CaseQualityDoctor::query()->updateOrInsert([
                            "ZYH" => $patient['MED_REC_ID'],
                            "code" => $v
                        ], [
                            "AAA28" => $patient['AAA28'] ?? "",
                            "BLBH" => "",
                            "name" => $staff[$v]["name"] ?? "",
                            "dep_id" => $staff[$v]["ksdm"] ?? 0,
                            "dep_name" => $dep[$staff[$v]["ksdm"] ?? 0] ?? "",
                            'rule_id' => 0,
                            'score' => $diffScore,
                        ]);
                    }
                }
            } else {
                PatientInfo::query()->where('MED_REC_ID', '=', $patient['MED_REC_ID'])->update(['is_case' => 2, 'zm_score' => 100, 'zm_quality_time' => time(), 'zm_score_lv' => "甲"]);
                ZY_BRRY::query()->where('ZYH', '=', $patient['MED_REC_ID'])->update(['zm_score' => 100, 'zm_score_lv' => "甲"]);
            }


            // 运行病例质控分数计算
            $res = CaseQuality::getByJZHM($patient['MED_REC_ID']);
            $score = 0;
            $defect = 'is_defect_v2';

            // 记录所有和当前病案号有关的医生信息
            $userList = [];
            if ($res) {
                foreach ($res as &$v) {
                    if ($v['rule_id'] > 1000000) {
                        $s = $ruleSetting[$v['rule_id'] - 1000000] ?? '';
                        if (!empty($s)) {
                            $score += $s['score'];
                        }
                    } elseif (isset($caseRule[$v['rule_id']])) {
                        $score += $caseRule[$v['rule_id']]['score'];
                    }
                }
                $diffScore = $score;
                $score = 100 - $score;
                if ($score > 90) {
                    $score_lv = '甲';
                } elseif ($score >= 75 && $score <= 90) {
                    $score_lv = '乙';
                } elseif ($score < 75) {
                    $score_lv = '丙';
                }
                Log::info("计算得分:-" . $score . "，病例质量：" . $score_lv);
                // is_case 将病例的质控状态改为未质控
                PatientInfo::query()->where('MED_REC_ID', '=', $patient['MED_REC_ID'])->update(['score' => $score, 'is_case' => 2, 'quality_time' => time(), 'score_lv' => $score_lv]);
                ZY_BRRY::query()->where('ZYH', '=', $patient['MED_REC_ID'])->update(['score' => $score, 'score_lv' => $score_lv]);
                
                $brry = ZY_BRRY::query()->where('ZYH', '=', $patient['MED_REC_ID'])->first();
                if ($brry) {
                    $brry = $brry->toArray();
                    // 判断住院号$info['AAA28']在CaseQualityHistory和CaseQuality中的rule_id是否一样
                    $historyRuleIds = \App\Model\CaseQualityHistory::query()
                        ->where('JZHM', $patient['MED_REC_ID'])
                        ->pluck('rule_id')
                        ->toArray();
                    $currentRuleIds = \App\Model\CaseQuality::query()
                        ->where('JZHM', $patient['MED_REC_ID'])
                        ->pluck('rule_id')
                        ->toArray();
                    $isRuleIdSame = false;
                    if (!empty($historyRuleIds) && !empty($currentRuleIds)) {
                        // 比较两个数组的内容是否完全一致（顺序无关）
                        $isRuleIdSame = (count($historyRuleIds) === count($currentRuleIds)) && (count(array_diff($historyRuleIds, $currentRuleIds)) === 0) && (count(array_diff($currentRuleIds, $historyRuleIds)) === 0);
                    }

                    if ($isRuleIdSame === false) {
                        // 记录质控结果
                        CaseQualityCount::addData([
                            'ZYH' => $patient['MED_REC_ID'],
                            'quality_date' => date('Y-m-d'),
                            'BRXM' => $brry['BRXM'] ?? '',
                            'BRKS' => $brry['BRKS'] ?? '',
                            'CH' => $brry['CH'] ?? '',
                            'is_viewed' => 0,
                        ]);
                    }
                }
            } else {
                PatientInfo::query()->where('MED_REC_ID', '=', $patient['MED_REC_ID'])->update(['is_case' => 2, $defect => 0, 'quality_time' => time(), 'score' => 100, 'score_lv' => "甲"]);
                ZY_BRRY::query()->where('ZYH', '=', $patient['MED_REC_ID'])->update(['score' => 100, 'score_lv' => "甲"]);
            }


            return true;
        } catch (\Exception $e) {
            Log::error("单个患者质控异常", [
                'zyh' => $zyh ?? '',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return ToolsService::returnData(500, [], '质控过程中发生错误: ' . $e->getMessage());
        }
        return true;
    }

    /**
     * 处理单个模板
     * 
     * @param array $template
     * @param object $patient
     * @param array $tableDict
     * @param object $modelConfig
     * @return array
     */
    private function processTemplate($template, $patient, $tableDict, $modelConfig)
    {
        $dataType = json_decode($template['data_type'], true);
        $content = "";
        $blbh = $this->blbh;
        // 构建内容
        if ($dataType) {
            foreach ($dataType as $v) {
                $esName = $v["table"]; // es的索引名称
                $filed = $v["field"][0];
                if (empty($tableDict[$esName])) {
                    continue;
                }
                $must = [];
                $isblmc = false;
                $fixed = '';

                if ($esName == 'zy_brry' && $filed == 'bcjl') {
                    $isblmc = true;
                    $esName = 'bl01_202303';
                    $must[] = ['term' => ['JZHM' => $patient["MED_REC_ID"]]];

                    if (!empty($blbh)) {
                        $must[] = ['term' => ['BLBH' => $blbh]];
                    } else {
                        $must[] = ['term' => ['BLLB' => 294]];
                    }
                    $fixed .= "病程记录:【";
                } elseif ($esName == 'zy_brry' && $filed == 'ssjl') {
                    $isblmc = true;
                    $esName = 'bl01_202303';
                    $must[] = ['term' => ['JZHM' => $patient["MED_REC_ID"]]];
                    if (!empty($blbh)) {
                        $must[] = ['term' => ['BLBH' => $blbh]];
                    } else {
                        $must[] = ['term' => ['MBLB' => 306]];
                    }
                    $fixed .= "手术记录:【";
                } elseif ($esName == 'zy_brry' && $filed == 'sjyscf') {
                    $isblmc = true;
                    $esName = 'bl01_202303';
                    $must[] = ['term' => ['JZHM' => $patient["MED_REC_ID"]]];
                    if (!empty($blbh)) {
                        $must[] = ['term' => ['BLBH' => $blbh]];
                    } else {
                        $must[] = ['term' => ['MBLB' => 50]];
                    }
                    $fixed .= "上级医师查房:【";
                } elseif ($esName == 'zy_brry' && $filed == 'qjjl') {
                    $isblmc = true;
                    $esName = 'bl01_202303';
                    $must[] = ['term' => ['JZHM' => $patient["MED_REC_ID"]]];
                    if (!empty($blbh)) {
                        $must[] = ['term' => ['BLBH' => $blbh]];
                    } else {
                        $must[] = ['term' => ['MBLB' => 27]];
                    }
                    $fixed .= "抢救记录:【";
                } elseif ($esName == 'zy_brry' && $filed == 'rcbc') {
                    $isblmc = true;
                    $esName = 'bl01_202303';
                    $must[] = ['term' => ['JZHM' => $patient["MED_REC_ID"]]];
                    if (!empty($blbh)) {
                        $must[] = ['term' => ['BLBH' => $blbh]];
                    } else {
                        $must[] = ['term' => ['MBLB' => 296]];
                    }
                    $fixed .= "日常病程:【";
                } elseif ($esName == 'zy_brry' && $filed == 'zkjl') {
                    $isblmc = true;
                    $esName = 'bl01_202303';
                    $must[] = ['term' => ['JZHM' => $patient["MED_REC_ID"]]];
                    if (!empty($blbh)) {
                        $must[] = ['term' => ['BLBH' => $blbh]];
                    } else {
                        $must[] = ['term' => ['MBLB' => 30]];
                    }
                    $fixed .= "转科记录:【";
                } elseif ($esName == 'zy_brry' && $filed == 'scbc') {
                    $isblmc = true;
                    $esName = 'bl01_202303';
                    $must[] = ['term' => ['JZHM' => $patient["MED_REC_ID"]]];
                    if (!empty($blbh)) {
                        $must[] = ['term' => ['BLBH' => $blbh]];
                    } else {
                        $must[] = ['term' => ['MBLB' => 295]];
                    }
                    $fixed .= "首次病程:【";
                } else {
                    $must[] = ['term' => [$tableDict[$esName]["zyh_field"] => $patient["MED_REC_ID"]]];
                    $fixed .= $tableDict[$esName]["field_name"] . ":【";
                }

                $esService = new ElasticsearchService($esName);

                $params = $esService->clearMust()
                    ->queryByMustBatch($must)
                    ->getParams();
                $res = app('es')->search($params);
                $filterData = $esService->getDataByEs($res);
                if (empty($filterData) || empty($filterData[0]) || empty($filterData[0][0])) {
                    continue;
                }
                $fdata = $filterData[0] ?? [];
                if (empty($fdata)) {
                    continue;
                }

                $content .= $fixed;

                foreach ($fdata as $index => $ff) {
                    if ($isblmc) {
                        if (isset($ff)) {
                            $content .= '记录名称' . $index . '{' . $ff['BLMC'] . '}:记录内容' . $index . '{' . $ff['HJNR'] . '}；' . "\n";
                        }
                    } else {
                        foreach ($v["field"] as $f) {
                            if (isset($ff) && isset($ff[$f])) {
                                $content .= $tableDict[$f]["field_name"] . ":" . $ff[$f] . "；"; //换行
                            }
                        }
                    }
                }
                $content .= "】；";
                //入院记录：【全文：。。。】；出院记录：【既往史：。。。；现病史：。。。】；病程记录：【blmc：。。。；blmc：。。。】
            }
        }

        if (empty($content)) {
            return [
                'template_id' => $template['id'],
                'template_title' => $template['title'] ?? '',
                'success' => false,
                'error' => '未找到相关数据',
                'has_error' => false
            ];
        }

        // 移除最后的逗号
        $content = rtrim($content, ',');

        //前置规范
        $qzgf = $modelConfig->qzgf ? $modelConfig->qzgf : '';
        //角色定义
        $jsdy = $modelConfig->jsdy ? $modelConfig->jsdy : '';
        //预留1
        $yuliu1 = $modelConfig->yuliu1 ? $modelConfig->yuliu1 : '';
        //预留2
        $yuliu2 = $modelConfig->yuliu2 ? $modelConfig->yuliu2 : '';
        //输出要求
        $scyq = $modelConfig->scyq ? $modelConfig->scyq : '';
        //输入示例
        $srsl = $modelConfig->srsl ? $modelConfig->srsl : '';
        //特殊指令
        $tzzl = $modelConfig->tzzl ? $modelConfig->tzzl : '';

        // 替换模板中的占位符
        //$content = str_replace('{params}', $content, $template['content']);
        $content1 = $qzgf . $jsdy . $template['content'] . $yuliu1 . $yuliu2 . $scyq . $srsl . $tzzl;
        $content1 = str_replace('{params}', $content, $content1);
        //var_dump('替换后的内容: ' . $content1);

        // 调用神思 DeepSeek-R1 API

        //var_dump($content1);
        $apiStartTime = microtime(true);
        //开始时间
        var_dump('开始时间: ' . date('Y-m-d H:i:s', $apiStartTime));
        $res = $this->callDeepSeekApi($modelConfig, $content1);
        $apiEndTime = microtime(true);
        $apiTime = round($apiEndTime - $apiStartTime, 2);
        //结束时间
        var_dump('结束时间: ' . date('Y-m-d H:i:s', $apiEndTime));
        //耗时
        var_dump('耗时: ' . $apiTime . '秒');
        // 解析响应
        $response = "";
        if (isset($res['choices'][0]['delta']['content'])) {
            $response = $res['choices'][0]['delta']['content'];
        } elseif (isset($res['choices'][0]['message']['content'])) {
            $response = $res['choices'][0]['message']['content'];
        } elseif (isset($res['result']['message']['response'])) {
            $response = $res['result']['message']['response'];
        } elseif (isset($res['result']['response'])) {
            $response = $res['result']['response'];
        } elseif (isset($res['response'])) {
            $response = $res['response'];
        } elseif (isset($res['message']['content'])) {
            $response = $res['message']['content'];
        }

        $response = $this->cleanModelResponse($response);
        $res = $this->extractJsonContent($response);
        //获取```json```之间的内容
        //$res = preg_replace('/```json(.*?)```/s', '$1', $response);
        //print_r('错误信息: ' . $res);
        var_dump('错误信息: ' . $res);
        $res = json_decode($res, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("模型返回内容不是有效JSON: " . json_last_error_msg());
        }
        //有可能是二维数组，如果是一维数组就转成二维数组
        if (!isset($res[0])) {
            $res = [$res];
        }
        //遍历数组，判断status是否包含错误
        $basiaList = [];
        //是否有错误
        $hasError = false;
        foreach ($res as $v) {
            if (!isset($v['status'])) {
                continue;
            }
            if (strpos($v['status'], '错误') === false) {
                continue;
            }
            $hasError = true;
            $basis = [];
            if (isset($v['reason'])) {
                $basis[] = $v['reason'];
                $basiaList[] = $basis;
            }
        }
        if ($hasError) {
            // 处理错误结果
            $errorNotice = [
                'basis' => json_encode($basiaList, JSON_UNESCAPED_UNICODE),
                'JZHM' => $patient['MED_REC_ID'],
                'rule_id' => $template['rule_id'],
                'code' => '',
                'error_field' => ''
            ];
            try {
                // 保存错误信息
                CaseQuality::addData($errorNotice);
                CaseQualityZm::addData($errorNotice);
                //更新患者表状态
                PatientInfo::query()
                    ->where('MED_REC_ID', '=', $patient['MED_REC_ID'])
                    ->update(['is_defect' => 1]);
            } catch (\Throwable $e) {
                var_dump($e->getMessage());
            }
        }

        return [
            'template_id' => $template['id'],
            'template_title' => $template['title'] ?? '',
            'rule_id' => $template['rule_id'],
            'success' => true,
            'has_error' => $hasError,
            'response' => $response,
            'api_time' => $apiTime,
            'content_length' => strlen($content)
        ];
    }

    /**
     * 调用神思 DeepSeek-R1 API
     * 
     * @param object $modelConfig
     * @param string $content
     * @return array
     */
    private function callDeepSeekApi($modelConfig, $content)
    {
        $modeltype = $modelConfig->type ?? '';
        $requestData = [];
        $maxAttempts = 3;
        if ($modeltype == 'llm') {
            $requestData = [
                'query' => $content,
            ];
        } elseif ($modeltype == 'ollama') {
            $requestData = [
                'prompt' => $content,
                'model' => $modelConfig->modelname,
                'stream' => false
            ];
        } else {
            $requestData = [
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $content
                    ]
                ],
                'model' => $modelConfig->modelname,
                'stream' => false
            ];
        }

        $headers = [
            'Content-Type: application/json'
        ];

        // 根据iskey判断是否需要添加Authorization头
        if ($modelConfig->iskey == 1) {
            $headers[] = 'Authorization: ' . $modelConfig->key;
        }

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $attemptStartTime = date('Y-m-d H:i:s');
            if ($attempt === 1) {
                var_dump("首次请求发起时间: {$attemptStartTime}");
            } else {
                $retryCount = $attempt - 1;
                var_dump("第{$retryCount}次重试发起时间: {$attemptStartTime}");
            }

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $modelConfig->url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($requestData));
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); // 允许跟随 307 等重定向
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60);
            curl_setopt($curl, CURLOPT_TIMEOUT, 60);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            $errno = curl_errno($curl);
            curl_close($curl);

            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                Log::warning("API请求超时", [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'timeout' => 60,
                    'url' => $modelConfig->url,
                    'attempt_start_time' => $attemptStartTime
                ]);

                if ($attempt < $maxAttempts) {
                    $nextRetryCount = $attempt;
                    $nextRetryTime = date('Y-m-d H:i:s');
                    var_dump("本次请求超时，准备发起第{$nextRetryCount}次重试，时间: {$nextRetryTime}");
                    continue;
                }

                throw new \Exception("API请求超时，最后一次请求开始于{$attemptStartTime}，累计重试" . ($maxAttempts - 1) . "次");
            }

            if ($error) {
                Log::error("API请求错误", ['error' => $error, 'attempt' => $attempt]);
                throw new \Exception("API请求错误: " . $error);
            }

            if ($httpCode !== 200) {
                Log::error("API请求失败", ['http_code' => $httpCode, 'response' => $response, 'attempt' => $attempt]);
                throw new \Exception("API请求失败，状态码: " . $httpCode);
            }

            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("API响应解析失败", ['json_error' => json_last_error_msg(), 'attempt' => $attempt]);
                throw new \Exception("API响应解析失败: " . json_last_error_msg());
            }

            return $result;
        }

        throw new \Exception("API请求失败，未获取到有效响应");
    }

    private function cleanModelResponse($response)
    {
        if (!is_string($response) || $response === '') {
            return '';
        }

        // 删除完整 think 块，并兼容大小写、换行和标签属性。
        $response = preg_replace('/<think\b[^>]*>.*?<\/think>/is', '', $response);
        // 有些模型会残留一个单独的 </think>，保留其后的真正结果。
        $response = preg_replace('/^.*?<\/think>/is', '', $response);
        // 再兜底去掉残留标签。
        $response = preg_replace('/<\/?think\b[^>]*>/i', '', $response);

        return trim($response);
    }

    private function extractJsonContent($response)
    {
        if (!is_string($response) || $response === '') {
            return '';
        }

        $response = preg_replace('/```(?:json)?/i', '', $response);
        $response = str_replace('```', '', $response);
        $response = trim($response);

        if (preg_match('/(\[\s*{[\s\S]*}\s*\]|\{[\s\S]*\})/u', $response, $matches)) {
            return trim($matches[1]);
        }

        return $response;
    }
}
