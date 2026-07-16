<?php

namespace App\Services;

use App\Model\BigModelMedicalRecord;
use App\Model\BigModelTemplate;
use App\Model\CustomTemplate;
use App\Model\ModelConfig;
use App\Model\TableDict;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BigModelMedicalRecordService
{
    /**
     * 获取病历保存列表
     *
     * @param int $page
     * @param int $pageSize
     * @param array $filters
     * @return array
     */
    public function getMedicalRecordList($page = 1, $pageSize = 10, array $filters = [])
    {
        try {
            $page = max(1, (int)$page);
            $pageSize = max(1, (int)$pageSize);

            $query = BigModelMedicalRecord::query();

            if (!empty($filters['zyh'])) {
                $query->where('zyh', $filters['zyh']);
            }

            if (!empty($filters['title'])) {
                $query->where('title', 'like', '%' . $filters['title'] . '%');
            }

            if (!empty($filters['code'])) {
                $query->where('code', 'like', '%' . $filters['code'] . '%');
            }

            $count = (clone $query)->count();
            $list = $query->orderBy('id', 'desc')
                ->forPage($page, $pageSize)
                ->get()
                ->toArray();

            return ToolsService::returnData(200, [
                'list' => $list,
                'count' => $count,
                'page' => $page,
                'page_size' => $pageSize,
            ], '获取成功');
        } catch (\Throwable $e) {
            Log::error('获取病历保存列表失败', [
                'page' => $page,
                'page_size' => $pageSize,
                'filters' => $filters,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ToolsService::returnData(500, [], '获取列表失败: ' . $e->getMessage());
        }
    }

    /**
     * 保存病历内容
     *
     * @param string $zyh
     * @param string $title
     * @param string $content
     * @param string|null $code
     * @return array
     */
    public function saveMedicalRecord($zyh, $title, $content, $code = null)
    {
        if (empty($zyh)) {
            return ToolsService::returnData(4001, [], '住院号不能为空');
        }

        if (empty($title)) {
            return ToolsService::returnData(4002, [], '标题不能为空');
        }

        if (empty($content)) {
            return ToolsService::returnData(4003, [], '内容不能为空');
        }

        try {
            $record = BigModelMedicalRecord::query()->create([
                'zyh' => $zyh,
                'title' => $title,
                'content' => $content,
                'code' => $code ?: null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return ToolsService::returnData(200, [
                'id' => (int)$record->id,
                'zyh' => $record->zyh,
                'title' => $record->title,
                'content' => $record->content,
                'code' => $record->code,
                'created_at' => $record->created_at,
            ], '保存成功');
        } catch (\Throwable $e) {
            Log::error('保存病历内容失败', [
                'zyh' => $zyh,
                'title' => $title,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ToolsService::returnData(500, [], '保存失败: ' . $e->getMessage());
        }
    }

    /**
     * 根据模板配置组装病历生成数据
     *
     * @param string $zyh
     * @param int|string $templateId
     * @return array
     */
    public function buildTemplateData($zyh, $templateId)
    {
        if (empty($zyh)) {
            return ToolsService::returnData(4001, [], '住院号不能为空');
        }

        if (empty($templateId)) {
            return ToolsService::returnData(4002, [], '模板ID不能为空');
        }

        try {
            $template = BigModelTemplate::query()->where('id', $templateId)->first();
            if (!$template) {
                return ToolsService::returnData(4003, [], '未找到对应模板');
            }

            $dataType = json_decode($template->data_type ?: '[]', true);
            if (!is_array($dataType) || empty($dataType)) {
                return ToolsService::returnData(4004, [], '模板未配置可用数据源');
            }

            $tableDictRows = TableDict::query()->get()->toArray();
            $tableDict = array_column($tableDictRows, null, 'field');
            $tableFieldDict = $this->buildTableFieldDict($tableDictRows);

            $fields = [];
            $lines = [];

            foreach ($dataType as $config) {
                $table = $config['table'] ?? '';
                $configFields = $config['field'] ?? [];

                if (empty($table) || !is_array($configFields) || empty($configFields)) {
                    continue;
                }

                $record = $this->getTemplateRecord($table, $zyh, $configFields, $tableDict, $tableFieldDict);

                foreach ($configFields as $field) {
                    $fieldConfig = $this->getFieldDictConfig($table, $field, $tableDict, $tableFieldDict);
                    $fieldName = $fieldConfig['field_name'] ?? $field;
                    $value = '';

                    if (is_array($record) && array_key_exists($field, $record) && $record[$field] !== null) {
                        $value = is_scalar($record[$field])
                            ? (string)$record[$field]
                            : json_encode($record[$field], JSON_UNESCAPED_UNICODE);
                    }

                    $item = [
                        'table' => $table,
                        'field' => $field,
                        'field_name' => $fieldName,
                        'value' => $value,
                    ];

                    $fields[] = $item;
                    $lines[] = $fieldName . '：' . $value;
                }
            }

            if (empty($fields)) {
                return ToolsService::returnData(4005, [], '模板未匹配到可组装字段');
            }

            return ToolsService::returnData(200, [
                'template_id' => (int)$template->id,
                'template_title' => $template->title ?? '',
                'content' => implode("\n", $lines),
                'fields' => $fields,
            ], '获取成功');
        } catch (\Throwable $e) {
            Log::error('组装病历生成数据失败', [
                'zyh' => $zyh,
                'template_id' => $templateId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ToolsService::returnData(500, [], '组装病历生成数据失败: ' . $e->getMessage());
        }
    }

    /**
     * 根据模板调用大模型生成病历内容
     *
     * @param string $content
     * @param int|string $templateId
     * @param int|string|null $customTemplateId
     * @return array
     */
    public function generateMedicalRecord($content, $templateId, $customTemplateId = null)
    {
        if (empty($content)) {
            return ToolsService::returnData(4001, [], '内容不能为空');
        }

        if (empty($templateId)) {
            return ToolsService::returnData(4002, [], '模板ID不能为空');
        }

        try {
            $template = BigModelTemplate::query()->where('id', $templateId)->first();
            if (!$template) {
                return ToolsService::returnData(4003, [], '未找到对应模板');
            }

            $customTemplate = null;
            if (!empty($customTemplateId)) {
                $customTemplate = CustomTemplate::query()
                    ->where('id', $customTemplateId)
                    ->where('status', 1)
                    ->first();
            }

            $modelConfig = ModelConfig::query()->where('id', 2)->first();
            if (!$modelConfig) {
                return ToolsService::returnData(4004, [], '未找到模型配置信息');
            }

            $prompt = $this->buildPrompt($template, $modelConfig, $content, $customTemplate);

            $apiStartTime = microtime(true);
            $result = $this->callModelApi($modelConfig, $prompt);
            $apiTime = round(microtime(true) - $apiStartTime, 2);

            $response = $this->extractResponseContent($result);
            $response = $this->cleanModelResponse($response);

            return ToolsService::returnData(200, [
                'template_id' => (int) $template->id,
                'template_title' => $template->title ?? '',
                'custom_template_id' => $customTemplate ? (int)$customTemplate->id : null,
                'custom_template_name' => $customTemplate ? $customTemplate->name : null,
                'content' => $response,
                'generate_date' => date('Y-m-d H:i:s'),
                'api_time' => $apiTime,
            ], '生成成功');
        } catch (\Throwable $e) {
            Log::error('病历生成请求失败', [
                'template_id' => $templateId,
                'custom_template_id' => $customTemplateId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ToolsService::returnData(500, [], '病历生成失败: ' . $e->getMessage());
        }
    }

    /**
     * 调用语音识别模型，将音频转为文本
     *
     * @param UploadedFile|null $file
     * @param string|null $fileUrl
     * @param string|null $language
     * @return array
     */
    public function transcribeAudio($file = null, $fileUrl = null, $language = null)
    {
        $logPrefix = '[语音转文字Service]';
        
        Log::info("{$logPrefix} 开始处理", [
            'has_file' => $file instanceof UploadedFile,
            'file_url' => $fileUrl,
            'language' => $language,
        ]);

        if (!$file instanceof UploadedFile && empty($fileUrl)) {
            Log::warning("{$logPrefix} 参数错误：音频文件和URL均为空");
            return ToolsService::returnData(4001, [], '音频文件不能为空');
        }

        if ($file instanceof UploadedFile && !$file->isValid()) {
            Log::warning("{$logPrefix} 文件无效", [
                'error' => $file->getErrorMessage(),
            ]);
            return ToolsService::returnData(4002, [], '上传音频文件无效');
        }

        try {
            Log::info("{$logPrefix} 查询模型配置");
            $modelConfig = ModelConfig::query()->where('id', 3)->first();
            if (!$modelConfig) {
                Log::error("{$logPrefix} 模型配置不存在");
                return ToolsService::returnData(4003, [], '未找到语音识别模型配置信息');
            }

            Log::info("{$logPrefix} 模型配置", [
                'type' => $modelConfig->type,
                'url' => $modelConfig->url,
                'modelname' => $modelConfig->modelname,
            ]);

            if (empty($modelConfig->url)) {
                Log::error("{$logPrefix} 接口地址未配置");
                return ToolsService::returnData(4004, [], '语音识别接口地址未配置');
            }

            $modelType = strtolower(trim($modelConfig->type ?? ''));
            Log::info("{$logPrefix} 模型类型: {$modelType}");

            if ($modelType === 'funasr') {
                Log::info("{$logPrefix} 使用FunASR接口");
                return $this->transcribeAudioWithFunASR($modelConfig, $file, $fileUrl, $language);
            }

            if (empty($modelConfig->modelname)) {
                Log::error("{$logPrefix} 模型名称未配置");
                return ToolsService::returnData(4005, [], '语音识别模型名称未配置');
            }

            Log::info("{$logPrefix} 构建请求数据");
            $requestData = $this->buildAudioTranscriptionRequestData($modelConfig, $file, $fileUrl, $language);

            Log::info("{$logPrefix} 调用模型API");
            $apiStartTime = microtime(true);
            $result = $this->callMultipartModelApi($modelConfig, $requestData);
            $apiTime = round(microtime(true) - $apiStartTime, 2);
            
            Log::info("{$logPrefix} API调用完成", [
                'api_time' => $apiTime,
                'result_keys' => array_keys($result),
            ]);

            Log::info("{$logPrefix} 提取转写文本");
            $text = $this->extractTranscriptionText($result);
            
            Log::info("{$logPrefix} 转写成功", [
                'text_length' => mb_strlen($text),
                'has_segments' => !empty($result['segments']),
                'api_time' => $apiTime,
            ]);

            return ToolsService::returnData(200, [
                'text' => $text,
                'language' => $result['language'] ?? '',
                'segments' => $result['segments'] ?? [],
                'api_time' => $apiTime,
            ], '识别成功');
        } catch (\Throwable $e) {
            Log::error("{$logPrefix} 转写失败", [
                'file_name' => $file instanceof UploadedFile ? $file->getClientOriginalName() : '',
                'file_url' => $fileUrl,
                'language' => $language,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ToolsService::returnData(500, [], '语音识别失败: ' . $e->getMessage());
        }
    }

    /**
     * 使用 FunASR 接口进行语音识别
     *
     * @param ModelConfig $modelConfig
     * @param UploadedFile|null $file
     * @param string|null $fileUrl
     * @param string|null $language
     * @return array
     * @throws \Exception
     */
    protected function transcribeAudioWithFunASR($modelConfig, $file = null, $fileUrl = null, $language = null)
    {
        $requestData = [];

        if ($file instanceof UploadedFile) {
            $requestData['file'] = new \CURLFile(
                $file->getRealPath(),
                $file->getMimeType() ?: 'application/octet-stream',
                $file->getClientOriginalName() ?: 'audio'
            );
        } elseif (!empty($fileUrl)) {
            return ToolsService::returnData(4007, [], 'FunASR接口暂不支持文件URL，请上传音频文件');
        }

        $apiStartTime = microtime(true);
        $result = $this->callFunASRApi($modelConfig, $requestData);
        $apiTime = round(microtime(true) - $apiStartTime, 2);

        $text = $this->extractFunASRText($result);
        Log::info('FunASR API响应解析完成', [
            'code' => $result['code'] ?? null,
            'has_text' => $text !== '',
            'text_length' => mb_strlen($text),
            'api_time' => $apiTime,
        ]);

        if ($text === '') {
            Log::warning('FunASR识别结果为空', [
                'response_keys' => array_keys($result),
                'error' => $result['error'] ?? null,
                'msg' => $result['msg'] ?? ($result['message'] ?? null),
            ]);

            return ToolsService::returnData(4006, [
                'language' => $language ?? 'zh',
                'segments' => [],
                'api_time' => $apiTime,
            ], '语音识别结果为空，请确认音频格式或识别服务返回');
        }

        return ToolsService::returnData(200, [
            'text' => $text,
            'language' => $language ?? 'zh',
            'segments' => [],
            'api_time' => $apiTime,
        ], '识别成功');
    }

    /**
     * 根据表配置获取模板数据记录
     *
     * @param string $table
     * @param string $zyh
     * @param array $fields
     * @param array $tableDict
     * @param array $tableFieldDict
     * @return array
     */
    protected function getTemplateRecord($table, $zyh, array $fields, array $tableDict, array $tableFieldDict)
    {
        if ($this->shouldUseMysql($table, $tableDict)) {
            return $this->getMysqlRecord($table, $zyh, $fields, $tableDict, $tableFieldDict);
        }

        return $this->getEsRecord($table, $zyh, $fields, $tableDict);
    }

    /**
     * 判断当前表是否优先走 MySQL 查询
     *
     * @param string $table
     * @param array $tableDict
     * @return bool
     */
    protected function shouldUseMysql($table, array $tableDict)
    {
        return !empty(trim((string)($tableDict[$table]['mysql_field'] ?? '')));
    }

    /**
     * 查询 MySQL 中该模板对应的首条记录
     *
     * @param string $table
     * @param string $zyh
     * @param array $fields
     * @param array $tableDict
     * @param array $tableFieldDict
     * @return array
     */
    protected function getMysqlRecord($table, $zyh, array $fields, array $tableDict, array $tableFieldDict)
    {
        $tableConfig = $this->getTableDictConfig($table, $tableDict);
        $mysqlTable = trim((string)($tableConfig['mysql_field'] ?? ''));
        if (empty($mysqlTable)) {
            return [];
        }

        $zyhField = $tableConfig['zyh_field'] ?? 'ZYH';
        $mysqlZyhField = $this->resolveMysqlField($table, $zyhField, $tableDict, $tableFieldDict);
        if (empty($mysqlZyhField)) {
            return [];
        }

        $fieldMap = [];
        foreach (array_values(array_unique(array_filter($fields))) as $field) {
            $fieldMap[$field] = $this->resolveMysqlField($table, $field, $tableDict, $tableFieldDict);
        }

        $sourceFields = array_values(array_unique(array_filter($fieldMap)));
        if (empty($sourceFields)) {
            return [];
        }

        $record = DB::table($mysqlTable)
            ->where($mysqlZyhField, $zyh)
            ->select($sourceFields)
            ->first();

        if (!$record) {
            return [];
        }

        $record = (array)$record;
        $result = [];

        foreach ($fieldMap as $field => $mysqlField) {
            $result[$field] = $record[$mysqlField] ?? null;
        }

        return $result;
    }

    /**
     * 查询 ES 中该模板对应的首条记录
     *
     * @param string $table
     * @param string $zyh
     * @param array $fields
     * @param array $tableDict
     * @return array
     */
    protected function getEsRecord($table, $zyh, array $fields, array $tableDict)
    {
        $tableConfig = $this->getTableDictConfig($table, $tableDict);
        $zyhField = $tableConfig['zyh_field'] ?? 'ZYH';
        if (empty($zyhField)) {
            return [];
        }

        $sourceFields = array_values(array_unique(array_filter($fields)));
        $esService = new ElasticsearchService($table);
        $params = $esService->clearMust()
            ->queryByMust(['term' => [$zyhField => $zyh]])
            ->source($sourceFields)
            ->paginate(1, 1)
            ->getParams();

        $result = app('es')->search($params);
        $data = $esService->getDataByEs($result);

        return $data[0][0] ?? [];
    }

    /**
     * 获取字段对应的 MySQL 字段名
     *
     * @param string $table
     * @param string $field
     * @param array $tableDict
     * @param array $tableFieldDict
     * @return string
     */
    protected function resolveMysqlField($table, $field, array $tableDict, array $tableFieldDict)
    {
        if (empty($field)) {
            return '';
        }

        $fieldConfig = $this->getFieldDictConfig($table, $field, $tableDict, $tableFieldDict);
        $mysqlField = trim((string)($fieldConfig['mysql_field'] ?? ''));

        return $mysqlField !== '' ? $mysqlField : $field;
    }

    /**
     * 构建按父表分组的字段字典
     *
     * @param array $tableDictRows
     * @return array
     */
    protected function buildTableFieldDict(array $tableDictRows)
    {
        $tableFieldDict = [];

        foreach ($tableDictRows as $row) {
            $parentField = (int)($row['parent_field'] ?? 0);
            $field = $row['field'] ?? '';

            if ($parentField <= 0 || $field === '') {
                continue;
            }

            $tableFieldDict[$parentField][$field] = $row;
        }

        return $tableFieldDict;
    }

    /**
     * 获取表级字典配置
     *
     * @param string $table
     * @param array $tableDict
     * @return array
     */
    protected function getTableDictConfig($table, array $tableDict)
    {
        return $tableDict[$table] ?? [];
    }

    /**
     * 获取表内字段字典配置，优先当前表下的字段配置
     *
     * @param string $table
     * @param string $field
     * @param array $tableDict
     * @param array $tableFieldDict
     * @return array
     */
    protected function getFieldDictConfig($table, $field, array $tableDict, array $tableFieldDict)
    {
        $tableConfig = $this->getTableDictConfig($table, $tableDict);
        $tableId = (int)($tableConfig['id'] ?? 0);

        if ($tableId > 0 && !empty($tableFieldDict[$tableId][$field])) {
            return $tableFieldDict[$tableId][$field];
        }

        return $tableDict[$field] ?? [];
    }

    /**
     * 组装最终发送给模型的提示词
     *
     * @param BigModelTemplate $template
     * @param ModelConfig $modelConfig
     * @param string $content
     * @param CustomTemplate|null $customTemplate
     * @return string
     */
    protected function buildPrompt(BigModelTemplate $template, ModelConfig $modelConfig, $content, $customTemplate = null)
    {
        $prompt = ($modelConfig->qzgf ?: '')
            . ($modelConfig->jsdy ?: '')
            . ($template->content ?: '');

        if ($customTemplate && !empty($customTemplate->content)) {
            $prompt .= "\n\n按照以下模版生成病历：\n" . $customTemplate->content;
        }

        $prompt .= ($modelConfig->yuliu1 ?: '')
            . ($modelConfig->yuliu2 ?: '')
            . ($modelConfig->scyq ?: '')
            . ($modelConfig->srsl ?: '')
            . ($modelConfig->tzzl ?: '');

        return str_replace('{params}', $content, $prompt);
    }

    /**
     * 组装语音识别接口请求参数
     *
     * @param ModelConfig $modelConfig
     * @param UploadedFile|null $file
     * @param string|null $fileUrl
     * @param string|null $language
     * @return array
     */
    protected function buildAudioTranscriptionRequestData(ModelConfig $modelConfig, $file = null, $fileUrl = null, $language = null)
    {
        $logPrefix = '[语音转文字Service]';
        
        Log::info("{$logPrefix} 开始构建请求参数", [
            'model' => $modelConfig->modelname,
            'has_file' => $file instanceof UploadedFile,
            'file_url' => $fileUrl,
            'language' => $language,
        ]);

        $requestData = [
            'model' => $modelConfig->modelname,
        ];

        if ($file instanceof UploadedFile) {
            $realPath = $file->getRealPath();
            $mimeType = $file->getMimeType() ?: 'application/octet-stream';
            $fileName = $file->getClientOriginalName() ?: 'audio';
            
            Log::info("{$logPrefix} 准备上传文件", [
                'real_path' => $realPath,
                'mime_type' => $mimeType,
                'file_name' => $fileName,
                'file_size' => $file->getSize(),
            ]);
            
            $requestData['file'] = new \CURLFile($realPath, $mimeType, $fileName);
            
            Log::info("{$logPrefix} CURLFile对象创建成功");
        } else {
            Log::info("{$logPrefix} 使用文件URL", ['url' => $fileUrl]);
            $requestData['file'] = (string) $fileUrl;
        }

        if (!empty($language)) {
            $requestData['language'] = $language;
        }

        Log::info("{$logPrefix} 请求参数构建完成", [
            'has_curl_file' => isset($requestData['file']) && $requestData['file'] instanceof \CURLFile,
            'language' => $requestData['language'] ?? null,
        ]);

        return $requestData;
    }

    /**
     * 调用大模型接口
     *
     * @param ModelConfig $modelConfig
     * @param string $content
     * @return array
     * @throws \Exception
     */
    protected function callModelApi(ModelConfig $modelConfig, $content)
    {
        $modelType = $modelConfig->type ?? '';
        $requestData = [];
        $maxAttempts = 3;

        if ($modelType === 'llm') {
            $requestData = [
                'query' => $content,
            ];
        } elseif ($modelType === 'ollama') {
            $requestData = [
                'prompt' => $content,
                'model' => $modelConfig->modelname,
                'stream' => false,
            ];
        } else {
            $requestData = [
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $content,
                    ],
                ],
                'model' => $modelConfig->modelname,
                'stream' => false,
            ];
        }

        $headers = [
            'Content-Type: application/json',
        ];

        if ((int) $modelConfig->iskey === 1) {
            $headers[] = 'Authorization: ' . $modelConfig->key;
        }

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $modelConfig->url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($requestData));
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60);
            curl_setopt($curl, CURLOPT_TIMEOUT, 60);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            $errno = curl_errno($curl);
            curl_close($curl);

            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                Log::warning('病历生成API请求超时', [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'url' => $modelConfig->url,
                ]);

                if ($attempt < $maxAttempts) {
                    continue;
                }

                throw new \Exception('API请求超时');
            }

            if ($error) {
                throw new \Exception('API请求错误: ' . $error);
            }

            if ($httpCode !== 200) {
                throw new \Exception('API请求失败，状态码: ' . $httpCode);
            }

            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('API响应解析失败: ' . json_last_error_msg());
            }

            Log::info('FunASR API响应', [
                'code' => $result['code'] ?? null,
                'has_text' => isset($result['text']) && trim((string)$result['text']) !== '',
                'error' => $result['error'] ?? null,
            ]);

            if (array_key_exists('code', $result) && (int)$result['code'] !== 0) {
                $errorMessage = $result['error'] ?? ($result['msg'] ?? ($result['message'] ?? '未知错误'));
                throw new \Exception('FunASR识别失败: ' . $errorMessage);
            }

            return $result;
        }

        throw new \Exception('API请求失败，未获取到有效响应');
    }

    /**
     * 以 multipart/form-data 方式调用模型接口
     *
     * @param ModelConfig $modelConfig
     * @param array $requestData
     * @return array
     * @throws \Exception
     */
    protected function callMultipartModelApi(ModelConfig $modelConfig, array $requestData)
    {
        $logPrefix = '[语音转文字Service]';
        
        Log::info("{$logPrefix} 准备调用API", [
            'url' => $modelConfig->url,
            'has_auth' => (int) $modelConfig->iskey === 1,
        ]);

        $headers = [];
        $maxAttempts = 3;

        if ((int) $modelConfig->iskey === 1) {
            $headers[] = 'Authorization: ' . $this->buildBearerAuthorizationValue($modelConfig->key);
            Log::info("{$logPrefix} 已添加认证头");
        }

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            Log::info("{$logPrefix} 开始第 {$attempt}/{$maxAttempts} 次请求");
            
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $modelConfig->url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $requestData);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60);
            curl_setopt($curl, CURLOPT_TIMEOUT, 300);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

            Log::info("{$logPrefix} CURL配置完成，开始执行请求");
            $requestStartTime = microtime(true);
            
            $response = curl_exec($curl);
            $requestTime = round(microtime(true) - $requestStartTime, 2);
            
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            $errno = curl_errno($curl);
            $curlInfo = curl_getinfo($curl);
            curl_close($curl);

            Log::info("{$logPrefix} 请求完成", [
                'attempt' => $attempt,
                'request_time' => $requestTime,
                'http_code' => $httpCode,
                'errno' => $errno,
                'error' => $error,
                'total_time' => $curlInfo['total_time'] ?? null,
                'connect_time' => $curlInfo['connect_time'] ?? null,
                'upload_size' => $curlInfo['size_upload'] ?? null,
                'download_size' => $curlInfo['size_download'] ?? null,
            ]);

            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                Log::warning("{$logPrefix} API请求超时", [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'url' => $modelConfig->url,
                    'request_time' => $requestTime,
                ]);

                if ($attempt < $maxAttempts) {
                    Log::info("{$logPrefix} 准备重试");
                    continue;
                }

                throw new \Exception('API请求超时');
            }

            if ($error) {
                Log::error("{$logPrefix} CURL错误", [
                    'errno' => $errno,
                    'error' => $error,
                ]);
                throw new \Exception('API请求错误: ' . $error);
            }

            if ($httpCode !== 200) {
                $errorMessage = is_string($response) ? trim($response) : '';
                Log::error("{$logPrefix} HTTP状态码异常", [
                    'http_code' => $httpCode,
                    'response_preview' => mb_substr($errorMessage, 0, 500),
                ]);
                
                if ($errorMessage !== '') {
                    throw new \Exception('API请求失败，状态码: ' . $httpCode . '，响应: ' . mb_substr($errorMessage, 0, 500));
                }

                throw new \Exception('API请求失败，状态码: ' . $httpCode);
            }

            Log::info("{$logPrefix} 开始解析响应", [
                'response_length' => strlen($response),
            ]);
            
            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("{$logPrefix} JSON解析失败", [
                    'json_error' => json_last_error_msg(),
                    'response_preview' => mb_substr($response, 0, 500),
                ]);
                throw new \Exception('API响应解析失败: ' . json_last_error_msg());
            }

            Log::info("{$logPrefix} 响应解析成功", [
                'result_keys' => array_keys($result),
            ]);

            return $result;
        }

        Log::error("{$logPrefix} 所有重试均失败");
        throw new \Exception('API请求失败，未获取到有效响应');
    }

    /**
     * 提取模型响应中的文本内容
     *
     * @param array $response
     * @return string
     */
    protected function extractResponseContent(array $response)
    {
        if (isset($response['choices'][0]['delta']['content'])) {
            return $response['choices'][0]['delta']['content'];
        }

        if (isset($response['choices'][0]['message']['content'])) {
            return $response['choices'][0]['message']['content'];
        }

        if (isset($response['result']['message']['response'])) {
            return $response['result']['message']['response'];
        }

        if (isset($response['result']['response'])) {
            return $response['result']['response'];
        }

        if (isset($response['response'])) {
            return $response['response'];
        }

        if (isset($response['message']['content'])) {
            return $response['message']['content'];
        }

        return '';
    }

    /**
     * 清除模型响应中的 think 内容
     *
     * @param string $response
     * @return string
     */
    protected function cleanModelResponse($response)
    {
        if (!is_string($response) || $response === '') {
            return '';
        }

        $response = preg_replace('/<think\b[^>]*>.*?<\/think>/is', '', $response);
        $response = preg_replace('/^.*?<\/think>/is', '', $response);
        $response = preg_replace('/<\/?think\b[^>]*>/i', '', $response);

        return trim($response);
    }

    /**
     * 提取语音识别结果中的主文本
     *
     * @param array $response
     * @return string
     */
    protected function extractTranscriptionText(array $response)
    {
        if (isset($response['text']) && is_string($response['text'])) {
            return trim($response['text']);
        }

        return $this->cleanModelResponse($this->extractResponseContent($response));
    }

    /**
     * 组装 Bearer 认证头
     *
     * @param string|null $key
     * @return string
     */
    protected function buildBearerAuthorizationValue($key)
    {
        $key = trim((string) $key);
        if ($key === '') {
            return '';
        }

        if (preg_match('/^(Bearer|Basic)\s+/i', $key)) {
            return $key;
        }

        return 'Bearer ' . $key;
    }

    /**
     * 调用 FunASR 接口
     *
     * @param ModelConfig $modelConfig
     * @param array $requestData
     * @return array
     * @throws \Exception
     */
    protected function callFunASRApi(ModelConfig $modelConfig, array $requestData)
    {
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $modelConfig->url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $requestData);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60);
            curl_setopt($curl, CURLOPT_TIMEOUT, 300);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            $errno = curl_errno($curl);
            curl_close($curl);

            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                Log::warning('FunASR API请求超时', [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'url' => $modelConfig->url,
                ]);

                if ($attempt < $maxAttempts) {
                    continue;
                }

                throw new \Exception('API请求超时');
            }

            if ($error) {
                throw new \Exception('API请求错误: ' . $error);
            }

            if ($httpCode !== 200) {
                $errorMessage = is_string($response) ? trim($response) : '';
                if ($errorMessage !== '') {
                    throw new \Exception('API请求失败，状态码: ' . $httpCode . '，响应: ' . mb_substr($errorMessage, 0, 500));
                }

                throw new \Exception('API请求失败，状态码: ' . $httpCode);
            }

            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('API响应解析失败: ' . json_last_error_msg());
            }

            return $result;
        }

        throw new \Exception('API请求失败，未获取到有效响应');
    }

    /**
     * 从 FunASR 响应中提取文本
     *
     * @param array $response
     * @return string
     */
    protected function extractFunASRText(array $response)
    {
        if (isset($response['text']) && is_string($response['text'])) {
            return trim($response['text']);
        }

        if (isset($response['result']) && is_string($response['result'])) {
            return trim($response['result']);
        }

        if (isset($response['data']['text']) && is_string($response['data']['text'])) {
            return trim($response['data']['text']);
        }

        if (isset($response['data']['result']) && is_string($response['data']['result'])) {
            return trim($response['data']['result']);
        }

        return '';
    }
}
