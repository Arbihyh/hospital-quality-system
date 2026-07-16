<?php

namespace App\Console\Commands\BigModel;

use App\Model\BigModelTemplate;
use App\Model\CaseQuality;
use App\Model\CaseQualityZm;
use App\Model\PatientInfo;
use App\Model\TableDict;
use App\Model\ModelConfig;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class Index extends Command
{

    protected $signature = 'big_model {start?} {zyh?}';
    protected $description = '大模型-病历质控';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // 记录开始时间
        $startTime = microtime(true);

        // 显示开始提示
        $this->info('开始运行大模型质控...');
        $this->info('开始时间: ' . date('Y-m-d H:i:s'));

        // 获取ModelConfig的第一条数据
        $modelConfig = ModelConfig::query()->where('id', 1)->first();
        if (!$modelConfig) {
            $this->error('未找到模型配置信息');
            return 1;
        }

        $this->info('使用模型配置: URL=' . $modelConfig->url . ', iskey=' . $modelConfig->iskey);

        // 获取传入的时间参数和住院号参数
        $star = $this->argument('start');
        $zyh = $this->argument('zyh');

        // 如果没有传入start参数，则不添加AAC01条件
        if (!empty($star)) {
            // 确保时间格式正确，添加时分秒
            $star = date('Y-m-d 00:00:00', strtotime($star));
            // 设置结束时间为当前日期的23:59:59
            $end = date('Y-m-d 23:59:59');
            $this->info('处理时间范围: ' . $star . ' 至 ' . $end);
        }

        if (!empty($zyh)) {
            $this->info('指定住院号: ' . $zyh);
        }

        if (empty($zyh) && empty($star)) {
            //开始时间是设置前一天的00:00:00
            $star = date('Y-m-d 00:00:00', strtotime('-1 day'));
            $end = date('Y-m-d 23:59:59');
            $this->info('处理时间范围: ' . $star . '至 ' . $end);
        }

        $tableDict = TableDict::query()->get()->toArray();
        $tableDict = array_column($tableDict, null, 'field');

        try {
            // 构建查询条件
            $query = PatientInfo::query();

            // 如果指定了住院号，则按住院号查询
            if (!empty($zyh)) {
                $query->where("MED_REC_ID", $zyh);
            }

            // 如果指定了开始时间，则添加时间条件
            if (!empty($star)) {
                $query->where("AAB01", ">", $star);
            }

            $pi = $query->get(["MED_REC_ID"])->toArray();

            $this->info('找到 ' . count($pi) . ' 条病历需要处理');

            // 读取大模型设置的质控模板
            $templates = BigModelTemplate::query()->where('type', 1)->where('status', 1)->get()->toArray();
            $bitModelRuleIds = array_column($templates, "rule_id");
            // 处理每个病历
            foreach ($pi as $item) {

                CaseQuality::query()->where('JZHM', '=', $item['MED_REC_ID'])
                    ->where("is_artificial", "=", 0)
                    ->whereIn("rule_id", $bitModelRuleIds)
                    ->delete();

                CaseQualityZm::query()->where('JZHM', '=', $item['MED_REC_ID'])
                    ->where("is_artificial", "=", 0)
                    ->whereIn("rule_id", $bitModelRuleIds)
                    ->delete();


                foreach ($templates as $template) {
                    $dataType = json_decode($template['data_type'], true);

                    $content = "";
                    if ($dataType) {
                        foreach ($dataType as $v) {
                            $esName = $v["table"]; // es的索引名称

                            if (empty($tableDict[$esName])) {
                                continue;
                            }
                            if (empty($v["field"])) {
                                continue;
                            }
                            $filed = $v["field"][0];
                            $must = [];
                            $isblmc = false;
                            $fixed = '';
                            if ($esName == 'zy_brry' && $filed == 'bcjl') {
                                $isblmc = true;
                                $esName = 'bl01_202303';
                                $must[] = ['term' => ['JZHM' => $item["MED_REC_ID"]]];
                                $must[] = ['term' => ['BLLB' => 294]];
                                $fixed .= "病程记录:【";
                            } elseif ($esName == 'zy_brry' && $filed == 'ssjl') {
                                $isblmc = true;
                                $esName = 'bl01_202303';
                                $must[] = ['term' => ['JZHM' => $item["MED_REC_ID"]]];
                                $must[] = ['term' => ['MBLB' => 306]];
                                $fixed .= "手术记录:【";
                            } elseif ($esName == 'zy_brry' && $filed == 'sjyscf') {
                                $isblmc = true;
                                $esName = 'bl01_202303';
                                $must[] = ['term' => ['JZHM' => $item["MED_REC_ID"]]];
                                $must[] = ['term' => ['MBLB' => 50]];
                                $fixed .= "上级医师查房:【";
                            } elseif ($esName == 'zy_brry' && $filed == 'qjjl') {
                                $isblmc = true;
                                $esName = 'bl01_202303';
                                $must[] = ['term' => ['JZHM' => $item["MED_REC_ID"]]];
                                $must[] = ['term' => ['MBLB' => 27]];
                                $fixed .= "抢救记录:【";
                            } elseif ($esName == 'zy_brry' && $filed == 'rcbc') {
                                $isblmc = true;
                                $esName = 'bl01_202303';
                                $must[] = ['term' => ['JZHM' => $item["MED_REC_ID"]]];
                                $must[] = ['term' => ['MBLB' => 296]];
                                $fixed .= "日常病程:【";
                            } elseif ($esName == 'zy_brry' && $filed == 'zkjl') {
                                $isblmc = true;
                                $esName = 'bl01_202303';
                                $must[] = ['term' => ['JZHM' => $item["MED_REC_ID"]]];
                                $must[] = ['term' => ['MBLB' => 30]];
                                $fixed .= "转科记录:【";
                            } elseif ($esName == 'zy_brry' && $filed == 'scbc') {
                                $isblmc = true;
                                $esName = 'bl01_202303';
                                $must[] = ['term' => ['JZHM' => $item["MED_REC_ID"]]];
                                $must[] = ['term' => ['MBLB' => 295]];
                                $fixed .= "首次病程:【";
                            } else {
                                $must[] = ['term' => [$tableDict[$esName]["zyh_field"] => $item["MED_REC_ID"]]];
                                $fixed .= $tableDict[$esName]["field_name"] . ":【";
                            }
                            $esService = new ElasticsearchService($esName);

                            $params = $esService->clearMust()
                                ->queryByMustBatch($must)
                                ->paginate(1, 10000) //因为es默认取10条，所以取出10000条
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
                        continue;
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

                    // 调用大模型
                    var_dump('大模型质控开始时间：' . date("Y-m-d H:i:s") . "\r\n");
                    $res = $this->callDeepSeekApi($modelConfig, $content1);
                    var_dump('大模型质控结束时间：' . date("Y-m-d H:i:s") . "\r\n");

                    // 解析响应
                    $response = "";
                    if (isset($res['choices'][0]['delta']['content'])) {
                        $response = $res['choices'][0]['delta']['content'];
                    } elseif (isset($res['choices'][0]['message']['content'])) {
                        $response = $res['choices'][0]['message']['content'];
                    } elseif (isset($res['result']['response'])) {
                        $response = $res['result']['response'];
                    } elseif (isset($res['response'])) {
                        $response = $res['response'];
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
                    // 过滤掉</think>前的所有字符（包括</think>本身）
                    if (strpos($response, '</think>') !== false) {
                        $pos = strpos($response, '</think>');
                        $response = substr($response, $pos + strlen('</think>'));
                    }

                    $res = str_replace(["`", "json"], ' ', $response);
                    //获取```json```之间的内容
                    //$res = preg_replace('/```json(.*?)```/s', '$1', $response);
                    //print_r('错误信息: ' . $res);
                    $res = json_decode($res, true);
                    var_dump($res);
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
                            'JZHM' => $item['MED_REC_ID'],
                            'rule_id' => $template['rule_id'],
                            'code' => '',
                            'error_field' => ''
                        ];
                        try {
                            // 保存错误信息
                            CaseQualityZm::addData($errorNotice);
                            CaseQuality::addData($errorNotice);
                            //更新患者表状态
                            PatientInfo::query()
                                ->where('MED_REC_ID', '=', $item['MED_REC_ID'])
                                ->update(['is_defect' => 1]);
                        } catch (\Throwable $e) {
                            var_dump($e->getMessage());
                        }
                    }
                }
            }

            // 计算运行时间
            $endTime = microtime(true);
            $runTime = round($endTime - $startTime, 2);

            // 显示结束提示
            $this->info('大模型质控运行完成');
            $this->info('结束时间: ' . date('Y-m-d H:i:s'));
            $this->info('总耗时: ' . $runTime . ' 秒');
        } catch (\Exception $e) {
            $this->error('运行出错: ' . $e->getMessage());

            // 即使出错也显示运行时间
            $endTime = microtime(true);
            $runTime = round($endTime - $startTime, 2);
            $this->info('总耗时: ' . $runTime . ' 秒');
        }
    }

    /**
     * 调用神思 DeepSeek-R1 API
     *
     * @param object $modelConfig 模型配置
     * @param string $content 要发送的内容
     * @return array API响应结果
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

        // 调试信息
        echo "=== API调试信息 ===\n";
        echo "URL: " . $modelConfig->url . "\n";
        echo "Type: " . $modeltype . "\n";
        echo "Request Data: " . json_encode($requestData, JSON_UNESCAPED_UNICODE) . "\n";

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
        // 允许跟随重定向（处理307等重定向状态码）
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 5);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        // 调试响应信息
        echo "HTTP状态码: " . $httpCode . "\n";
        //echo "响应内容: " . $response . "\n";
        if ($error) {
            echo "CURL错误: " . $error . "\n";
        }

        if ($error) {
            $this->error("API请求错误: " . $error);
            return [];
        }

        if ($httpCode !== 200) {
            $this->error("API请求失败，状态码: " . $httpCode . "，响应: " . $response);
            return [];
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("API响应解析失败: " . json_last_error_msg());
            return [];
        }

        return $result;
    }
}
