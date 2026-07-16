<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\CustomTemplate;
use App\Model\CustomTemplateDepartment;
use App\Model\CustomTemplateDisease;
use App\Model\Staff;
use App\Services\BigModelMedicalRecordService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BigModelMedicalRecordController extends Controller
{
    /**
     * 单点登录（工号验证）
     *
     * @param Request $request
     * @return array
     */
    public function staffLogin(Request $request)
    {
        $staffCode = $request->input('staff_code');

        if (empty($staffCode)) {
            return ToolsService::returnData(4001, [], '工号不能为空');
        }

        try {
            $staff = Staff::query()->where('code', $staffCode)->first();

            if (!$staff) {
                return ToolsService::returnData(4002, [], '职工表无此员工，请核实工号');
            }

            return ToolsService::returnData(200, [
                'staff_code' => $staff->code,
                'staff_name' => $staff->name,
                'department' => $staff->ks_name ?? '',
            ], '验证成功');
        } catch (\Throwable $e) {
            Log::error('工号验证失败', [
                'staff_code' => $staffCode,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ToolsService::returnData(500, [], '验证失败: ' . $e->getMessage());
        }
    }

    /**
     * 新增自定义模版
     *
     * @param Request $request
     * @return array
     */
    public function createCustomTemplate(Request $request)
    {
        return ToolsService::returnData(4001, [], '请在admin端新增公共模版，当前页面仅支持编辑个人模版');
    }

    /**
     * 获取自定义模版列表
     *
     * @param Request $request
     * @return array
     */
    public function getCustomTemplateList(Request $request)
    {
        $bigModelTemplateId = $request->input('big_model_template_id');
        $departmentId = $request->input('department_id');
        $diseaseId = $request->input('disease_id');
        $staffCode = $request->input('staff_code');
        $page = max(1, (int)$request->input('page', 1));
        $pageSize = max(1, (int)$request->input('page_size', 10));

        try {
            $query = $this->buildCustomTemplateQuery()
                ->where('custom_templates.status', 1)
                ->where('custom_templates.template_scope', 1);

            if (!empty($bigModelTemplateId)) {
                $query->where('custom_templates.big_model_template_id', $bigModelTemplateId);
            }

            $count = (clone $query)->count();
            if (!empty($diseaseId)) {
                $query->orderByRaw('CASE WHEN custom_templates.disease_id = ? THEN 0 ELSE 1 END', [$diseaseId]);
            }

            if (!empty($departmentId)) {
                $query->orderByRaw('CASE WHEN custom_templates.department_id = ? THEN 0 ELSE 1 END', [$departmentId]);
            }

            $publicList = $query->orderBy('custom_templates.id', 'desc')
                ->forPage($page, $pageSize)
                ->get()
                ->toArray();

            $list = $publicList;

            if (!empty($staffCode) && !empty($publicList)) {
                $publicIds = array_values(array_filter(array_map(function ($item) {
                    return (int)($item['id'] ?? 0);
                }, $publicList)));

                $userList = [];
                if (!empty($publicIds)) {
                    $userList = $this->buildCustomTemplateQuery()
                        ->where('custom_templates.status', 1)
                        ->where('custom_templates.template_scope', 2)
                        ->where('custom_templates.staff_code', $staffCode)
                        ->whereIn('custom_templates.source_template_id', $publicIds)
                        ->orderBy('custom_templates.id', 'desc')
                        ->get()
                        ->toArray();
                }

                $userMap = [];
                foreach ($userList as $item) {
                    $sourceTemplateId = (int)($item['source_template_id'] ?? 0);
                    if ($sourceTemplateId > 0 && !isset($userMap[$sourceTemplateId])) {
                        $userMap[$sourceTemplateId] = $item;
                    }
                }

                foreach ($list as $index => $item) {
                    $publicTemplateId = (int)($item['id'] ?? 0);
                    $list[$index]['public_template_id'] = $publicTemplateId;
                    $list[$index]['is_customized'] = 0;

                    if (!empty($userMap[$publicTemplateId])) {
                        $mergedItem = $userMap[$publicTemplateId];
                        $mergedItem['public_template_id'] = $publicTemplateId;
                        $mergedItem['public_template_name'] = $item['name'] ?? '';
                        $mergedItem['is_customized'] = 1;
                        $list[$index] = $mergedItem;
                    }
                }
            } else {
                foreach ($list as $index => $item) {
                    $list[$index]['public_template_id'] = (int)($item['id'] ?? 0);
                    $list[$index]['is_customized'] = 0;
                }
            }

            return ToolsService::returnData(200, [
                'list' => $list,
                'count' => $count,
                'page' => $page,
                'page_size' => $pageSize,
            ], '获取成功');
        } catch (\Throwable $e) {
            Log::error('获取自定义模版列表失败', [
                'big_model_template_id' => $bigModelTemplateId,
                'department_id' => $departmentId,
                'disease_id' => $diseaseId,
                'staff_code' => $staffCode,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ToolsService::returnData(500, [], '获取列表失败: ' . $e->getMessage());
        }
    }

    /**
     * 编辑个人自定义模版
     *
     * @param Request $request
     * @return array
     */
    public function editCustomTemplate(Request $request)
    {
        $id = (int)$request->input('id');
        $name = trim((string)$request->input('name'));
        $content = $request->input('content');
        $staffCode = trim((string)$request->input('staff_code'));
        $staffName = trim((string)$request->input('staff_name'));
        $documentType = $request->has('document_type') ? trim((string)$request->input('document_type')) : null;

        if ($id <= 0) {
            return ToolsService::returnData(4001, [], '模版ID不能为空');
        }

        if ($staffCode === '') {
            return ToolsService::returnData(4002, [], '工号不能为空');
        }

        if ($content === null || trim((string)$content) === '') {
            return ToolsService::returnData(4003, [], '模版内容不能为空');
        }

        try {
            $targetTemplate = CustomTemplate::query()
                ->where('id', $id)
                ->where('status', 1)
                ->first();

            if (!$targetTemplate) {
                return ToolsService::returnData(4004, [], '未找到对应模版');
            }

            $publicTemplate = null;
            $userTemplate = null;

            if ((int)$targetTemplate->template_scope === 2) {
                if ((string)$targetTemplate->staff_code !== $staffCode) {
                    return ToolsService::returnData(4005, [], '只能编辑自己的模版');
                }

                $userTemplate = $targetTemplate;
                $publicTemplate = !empty($targetTemplate->source_template_id)
                    ? CustomTemplate::query()->where('id', $targetTemplate->source_template_id)->first()
                    : null;
            } else {
                $publicTemplate = $targetTemplate;
                $userTemplate = CustomTemplate::query()
                    ->where('status', 1)
                    ->where('template_scope', 2)
                    ->where('source_template_id', $publicTemplate->id)
                    ->where('staff_code', $staffCode)
                    ->first();
            }

            $baseTemplate = $publicTemplate ?: $targetTemplate;
            $templateData = [
                'name' => $name !== '' ? $name : (string)$baseTemplate->name,
                'department' => $baseTemplate->department,
                'department_id' => $baseTemplate->department_id,
                'disease_id' => $baseTemplate->disease_id,
                'document_type' => $documentType !== null ? $documentType : (string)$baseTemplate->document_type,
                'content' => $content,
                'big_model_template_id' => $baseTemplate->big_model_template_id,
                'staff_code' => $staffCode,
                'staff_name' => $staffName,
                'template_scope' => 2,
                'source_template_id' => (int)($publicTemplate ? $publicTemplate->id : ($targetTemplate->source_template_id ?: 0)),
                'status' => 1,
            ];

            if ($userTemplate) {
                $userTemplate->fill($templateData);
                $userTemplate->save();
                $savedTemplate = $userTemplate;
            } else {
                $savedTemplate = CustomTemplate::query()->create($templateData);
            }

            return ToolsService::returnData(200, [
                'id' => (int)$savedTemplate->id,
                'public_template_id' => (int)($templateData['source_template_id'] ?: $savedTemplate->id),
                'name' => $savedTemplate->name,
                'content' => $savedTemplate->content,
                'department_id' => (int)$savedTemplate->department_id,
                'department_name' => $this->getDepartmentName($savedTemplate->department_id),
                'disease_id' => (int)$savedTemplate->disease_id,
                'disease_name' => $this->getDiseaseName($savedTemplate->disease_id),
                'document_type' => (string)$savedTemplate->document_type,
                'big_model_template_id' => (int)$savedTemplate->big_model_template_id,
                'staff_code' => $savedTemplate->staff_code,
                'staff_name' => $savedTemplate->staff_name,
                'template_scope' => (int)$savedTemplate->template_scope,
                'is_customized' => 1,
                'created_at' => $savedTemplate->created_at,
                'updated_at' => $savedTemplate->updated_at,
            ], '编辑成功');
        } catch (\Throwable $e) {
            Log::error('编辑个人自定义模版失败', [
                'id' => $id,
                'staff_code' => $staffCode,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ToolsService::returnData(500, [], '编辑失败: ' . $e->getMessage());
        }
    }
    /**
     * 病历保存列表
     *
     * @param Request $request
     * @return array
     */
    public function getMedicalRecordList(Request $request)
    {
        $page = $request->input('page', 1);
        $pageSize = $request->input('page_size', 10);

        $filters = [
            'zyh' => $request->input('zyh'),
            'title' => $request->input('title'),
            'code' => $request->input('code'),
        ];

        $service = new BigModelMedicalRecordService();

        return $service->getMedicalRecordList($page, $pageSize, $filters);
    }

    /**
     * 保存病历内容
     *
     * @param Request $request
     * @return array
     */
    public function saveMedicalRecord(Request $request)
    {
        $zyh = $request->input('zyh');
        $title = $request->input('title');
        $content = $request->input('content', $request->input('conetnt'));
        $code = $request->input('code');

        $service = new BigModelMedicalRecordService();

        return $service->saveMedicalRecord($zyh, $title, $content, $code);
    }

    /**
     * 根据模板组装病历生成数据
     *
     * @param Request $request
     * @return array
     */
    public function buildTemplateData(Request $request)
    {
        $zyh = $request->input('zyh');
        $templateId = $request->input('tempate_id', $request->input('template_id'));

        $service = new BigModelMedicalRecordService();

        return $service->buildTemplateData($zyh, $templateId);
    }

    /**
     * 根据模板生成病历内容
     *
     * @param Request $request
     * @return array
     */
    public function generateMedicalRecord(Request $request)
    {
        $content = $request->input('content');
        $templateId = $request->input('tempate_id', $request->input('template_id'));
        $customTemplateId = $request->input('custom_template_id');

        $service = new BigModelMedicalRecordService();

        return $service->generateMedicalRecord($content, $templateId, $customTemplateId);
    }

    /**
     * 语音识别
     *
     * @param Request $request
     * @return array
     */
    public function audioTranscription(Request $request)
    {
        $logPrefix = '[语音转文字]';
        
        Log::info("{$logPrefix} 接收请求", [
            'has_file' => $request->hasFile('file'),
            'file_url' => $request->input('file_url'),
            'audio_url' => $request->input('audio_url'),
            'file_input' => $request->input('file'),
            'language' => $request->input('language'),
        ]);

        $file = $request->file('file');
        $fileUrl = '';

        if (!$request->hasFile('file')) {
            $fileUrl = $request->input('file_url', $request->input('audio_url'));
            if (empty($fileUrl)) {
                $fileInput = $request->input('file');
                $fileUrl = is_string($fileInput) ? $fileInput : '';
            }
            Log::info("{$logPrefix} 使用文件URL", ['file_url' => $fileUrl]);
        } else {
            Log::info("{$logPrefix} 使用上传文件", [
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]);
        }

        $language = $request->input('language');

        $service = new BigModelMedicalRecordService();

        Log::info("{$logPrefix} 调用转写服务");
        $result = $service->transcribeAudio($file, $fileUrl, $language);
        
        Log::info("{$logPrefix} 转写完成", [
            'code' => $result['code'] ?? null,
            'msg' => $result['msg'] ?? null,
            'has_text' => !empty($result['data']['text'] ?? ''),
            'text_length' => isset($result['data']['text']) ? mb_strlen($result['data']['text']) : 0,
        ]);

        return $result;
    }

    /**
     * 获取自定义模板科室下拉
     *
     * @param Request $request
     * @return array
     */
    public function getCustomTemplateDepartments(Request $request)
    {
        $status = $request->input('status', 1);
        $name = trim((string)$request->input('name', ''));

        $query = CustomTemplateDepartment::query();
        if ($status !== '' && $status !== null) {
            $query->where('status', $status);
        }
        if ($name !== '') {
            $query->where('name', 'like', '%' . $name . '%');
        }

        $list = $query->orderBy('sort_num', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();

        return ToolsService::returnData(200, $list, '获取成功');
    }

    /**
     * 获取自定义模板病种下拉
     *
     * @param Request $request
     * @return array
     */
    public function getCustomTemplateDiseases(Request $request)
    {
        $status = $request->input('status', 1);
        $name = trim((string)$request->input('name', ''));

        $query = CustomTemplateDisease::query();
        if ($status !== '' && $status !== null) {
            $query->where('status', $status);
        }
        if ($name !== '') {
            $query->where('name', 'like', '%' . $name . '%');
        }

        $list = $query->orderBy('sort_num', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();

        return ToolsService::returnData(200, $list, '获取成功');
    }

    /**
     * 构建自定义模版基础查询
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildCustomTemplateQuery()
    {
        return CustomTemplate::query()
            ->leftJoin('custom_template_departments', 'custom_template_departments.id', '=', 'custom_templates.department_id')
            ->leftJoin('custom_template_diseases', 'custom_template_diseases.id', '=', 'custom_templates.disease_id')
            ->select(
                'custom_templates.*',
                'custom_template_departments.name as department_name',
                'custom_template_diseases.name as disease_name'
            );
    }

    /**
     * 获取科室名称
     *
     * @param int|string|null $departmentId
     * @return string
     */
    protected function getDepartmentName($departmentId)
    {
        if (empty($departmentId)) {
            return '';
        }

        return (string)CustomTemplateDepartment::query()->where('id', $departmentId)->value('name');
    }

    /**
     * 获取病种名称
     *
     * @param int|string|null $diseaseId
     * @return string
     */
    protected function getDiseaseName($diseaseId)
    {
        if (empty($diseaseId)) {
            return '';
        }

        return (string)CustomTemplateDisease::query()->where('id', $diseaseId)->value('name');
    }
}
