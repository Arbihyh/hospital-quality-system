<?php

namespace App\Http\Controllers\Api;

use App\Console\Commands\BigModel\Index;
use App\Http\Controllers\Controller;
use App\Model\BigModelTemplate;
use App\Model\CaseQuality;
use App\Model\PatientInfo;
use App\Model\TableDict;
use App\Model\ModelConfig;
use App\Services\ElasticsearchService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BigModelQualityController extends Controller
{
    /**
     * 自定义内容大模型请求接口
     *
     * @param Request $request
     * @return array
     */
    public function customModelRequest(Request $request)
    {
        try {
            // 获取参数
            $content = $request->input('content');

            // 参数验证
            if (empty($content)) {
                return ToolsService::returnData(4001, [], '请求内容不能为空');
            }

            // 获取ModelConfig配置
            $modelConfig = ModelConfig::query()->where('id', 1)->first();
            if (!$modelConfig) {
                Log::error("未找到模型配置信息");
                return ToolsService::returnData(4002, [], '未找到模型配置信息');
            }

            // 调用大模型API
            $apiStartTime = microtime(true);
            $res = $this->callDeepSeekApi($modelConfig, $content);
            $apiEndTime = microtime(true);
            $apiTime = round($apiEndTime - $apiStartTime, 2);

            if ($res['success']) {
                Log::info("自定义大模型请求成功", [
                    'content_length' => strlen($content),
                    'api_time' => $apiTime
                ]);

                return ToolsService::returnData(200, [
                    'content' => $res['content'],
                    'api_time' => $apiTime
                ], '请求成功');
            } else {
                Log::error("自定义大模型请求失败", [
                    'error' => $res['error'],
                    'api_time' => $apiTime
                ]);

                return ToolsService::returnData(5001, [], '大模型请求失败: ' . $res['error']);
            }
        } catch (\Exception $e) {
            Log::error("自定义大模型请求异常", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ToolsService::returnData(5000, [], '系统异常: ' . $e->getMessage());
        }
    }

    /**
     * 单个患者大模型质控接口
     *
     * @param Request $request
     * @return array
     */
    public function singlePatientQuality(Request $request)
    {
        try {
            // 获取参数
            $zyh = $request->input('zyh');

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
            $templates = BigModelTemplate::query()
                ->where('status', 1) // 病历质控类型
                ->get()
                ->toArray();

            if (empty($templates)) {
                return ToolsService::returnData(4004, [], '未找到可用的质控模板');
            }

            $processedCount = 0;
            $errorCount = 0;
            $results = [];

            // 质控成功后删除大模型的质控结果
            $templates = BigModelTemplate::query()->get()->where('type', 1)->where("status", 1)->toArray();
            $bitModelRuleIds = array_column($templates, "rule_id");

            CaseQuality::query()->where('JZHM', '=', $patient['MED_REC_ID'])
                ->where("is_artificial", "=", 0)
                ->whereIn("rule_id", $bitModelRuleIds)
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

            return ToolsService::returnData(200, [
                'zyh' => $zyh,
                'processed_templates' => $processedCount,
                'error_count' => $errorCount,
                'run_time' => $runTime,
                'results' => $results,
                'message' => '质控完成'
            ], '质控结束');
        } catch (\Exception $e) {
            Log::error("单个患者质控异常", [
                'zyh' => $zyh ?? '',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return ToolsService::returnData(500, [], '质控过程中发生错误: ' . $e->getMessage());
        }
    }

    /**
     * 单个患者大模型质控接口,可调用
     *
     * @param Request $request
     * @return array
     */
    public function singlePatientQualityDy($ZYH)
    {
        try {
            // 获取参数
            $zyh = $ZYH;

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
            $templates = BigModelTemplate::query()
                ->where('status', 1) // 病历质控类型
                ->get()
                ->toArray();

            if (empty($templates)) {
                return ToolsService::returnData(4004, [], '未找到可用的质控模板');
            }

            $processedCount = 0;
            $errorCount = 0;
            $results = [];

            // 质控成功后删除大模型的质控结果
            $templates = BigModelTemplate::query()->get()->where('type', 1)->where("status", 1)->toArray();
            $bitModelRuleIds = array_column($templates, "rule_id");

            CaseQuality::query()->where('JZHM', '=', $patient['MED_REC_ID'])
                ->where("is_artificial", "=", 0)
                ->whereIn("rule_id", $bitModelRuleIds)
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
                    $must[] = ['term' => ['BLLB' => 294]];
                    $fixed .= "病程记录:【";
                } elseif ($esName == 'zy_brry' && $filed == 'ssjl') {
                    $isblmc = true;
                    $esName = 'bl01_202303';
                    $must[] = ['term' => ['JZHM' => $patient["MED_REC_ID"]]];
                    $must[] = ['term' => ['MBLB' => 306]];
                    $fixed .= "手术记录:【";
                } elseif ($esName == 'zy_brry' && $filed == 'sjyscf') {
                    $isblmc = true;
                    $esName = 'bl01_202303';
                    $must[] = ['term' => ['JZHM' => $patient["MED_REC_ID"]]];
                    $must[] = ['term' => ['MBLB' => 50]];
                    $fixed .= "上级医师查房:【";
                } elseif ($esName == 'zy_brry' && $filed == 'qjjl') {
                    $isblmc = true;
                    $esName = 'bl01_202303';
                    $must[] = ['term' => ['JZHM' => $patient["MED_REC_ID"]]];
                    $must[] = ['term' => ['MBLB' => 27]];
                    $fixed .= "抢救记录:【";
                } elseif ($esName == 'zy_brry' && $filed == 'rcbc') {
                    $isblmc = true;
                    $esName = 'bl01_202303';
                    $must[] = ['term' => ['JZHM' => $patient["MED_REC_ID"]]];
                    $must[] = ['term' => ['MBLB' => 296]];
                    $fixed .= "日常病程:【";
                } elseif ($esName == 'zy_brry' && $filed == 'zkjl') {
                    $isblmc = true;
                    $esName = 'bl01_202303';
                    $must[] = ['term' => ['JZHM' => $patient["MED_REC_ID"]]];
                    $must[] = ['term' => ['MBLB' => 30]];
                    $fixed .= "转科记录:【";
                } elseif ($esName == 'zy_brry' && $filed == 'scbc') {
                    $isblmc = true;
                    $esName = 'bl01_202303';
                    $must[] = ['term' => ['JZHM' => $patient["MED_REC_ID"]]];
                    $must[] = ['term' => ['MBLB' => 295]];
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
                        if(isset($ff)){
                            $content.= '记录名称'.$index.'{'.$ff['BLMC'].'}:记录内容'.$index.'{'. $ff['HJNR']. '}；'."\n";
                        }
                        
                    } else {
                        foreach ($v["field"] as $f) {
                            if (isset($ff) && isset($ff[$f])) {
                                $content .= $tableDict[$f]["field_name"] . ":" . $ff[$f] . "；";//换行
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
        $apiStartTime = microtime(true);
        $res = $this->callDeepSeekApi($modelConfig, $content1);
        $apiEndTime = microtime(true);
        $apiTime = round($apiEndTime - $apiStartTime, 2);



        // 解析响应
        $response = "";
        if (isset($res['choices'][0]['delta']['content'])) {
            $response = $res['choices'][0]['delta']['content'];
        } elseif (isset($res['choices'][0]['message']['content'])) {
            $response = $res['choices'][0]['message']['content'];
        } elseif (isset($res['result']['message']['response'])) {
            $response = $res['result']['message']['response'];
        }

        //如果有思考过程<think>...</think>，则去掉
        if (strpos($response, '<think>') !== false) {

            $response = preg_replace('/<think>.*<\/think>/', '', $response);
            //获取<think>和</think>的位置，删除think和</think>之间的内容
            $thinkStart = strpos($response, '<think>');
            $thinkEnd = strpos($response, '</think>');
            $thinkContent = substr($response, $thinkStart + 6, $thinkEnd - $thinkStart - 6);
            $response = str_replace($thinkContent, '', $response);
            //在删除<think>和</think>标签
            $response = str_replace(['<think>', '</think>', '<think', '</think'], '', $response);
        }

        $res = str_replace(["`", "json"], ' ', $response);
        //获取```json```之间的内容
        //$res = preg_replace('/```json(.*?)```/s', '$1', $response);
        //print_r('错误信息: ' . $res);
        $res = json_decode($res, true);
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

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $modelConfig->url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 600);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            Log::error("API请求错误", ['error' => $error]);
            throw new \Exception("API请求错误: " . $error);
        }

        if ($httpCode !== 200) {
            Log::error("API请求失败", ['http_code' => $httpCode, 'response' => $response]);
            throw new \Exception("API请求失败，状态码: " . $httpCode);
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("API响应解析失败", ['json_error' => json_last_error_msg()]);
            throw new \Exception("API响应解析失败: " . json_last_error_msg());
        }

        return $result;
    }
}
