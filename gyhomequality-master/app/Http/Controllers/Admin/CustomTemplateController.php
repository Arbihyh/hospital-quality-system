<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\CustomTemplate;
use App\Model\CustomTemplateDepartment;
use App\Model\CustomTemplateDisease;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomTemplateController extends Controller
{
    public function getList(Request $request)
    {
        $name = trim((string)$request->post('name', ''));
        $status = $request->post('status', '');
        $bigModelTemplateId = $request->post('big_model_template_id', '');
        $departmentId = $request->post('department_id', '');
        $diseaseId = $request->post('disease_id', '');
        $page = max(1, (int)$request->post('page', 1));
        $pageSize = max(1, (int)$request->post('page_size', 20));

        $query = $this->buildQuery()->where('custom_templates.template_scope', 1);

        if ($name !== '') {
            $query->where('custom_templates.name', 'like', '%' . $name . '%');
        }

        if ($status !== '' && $status !== null) {
            $query->where('custom_templates.status', $status);
        }

        if (!empty($bigModelTemplateId)) {
            $query->where('custom_templates.big_model_template_id', $bigModelTemplateId);
        }

        if (!empty($departmentId)) {
            $query->where('custom_templates.department_id', $departmentId);
        }

        if (!empty($diseaseId)) {
            $query->where('custom_templates.disease_id', $diseaseId);
        }

        $count = (clone $query)->count();
        $pageStart = ($page - 1) * $pageSize;
        $list = $query->orderBy('custom_templates.id', 'desc')
            ->offset($pageStart)
            ->limit($pageSize)
            ->get()
            ->toArray();

        return ToolsService::returnAdmin(0, [
            'count' => $count,
            'list' => $list,
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    public function setTemplate(Request $request)
    {
        $id = (int)$request->post('id', 0);
        $name = trim((string)$request->post('name', ''));
        $content = $request->post('content', '');
        $bigModelTemplateId = (int)$request->post('big_model_template_id', 0);
        $departmentId = $request->post('department_id');
        $diseaseId = $request->post('disease_id');
        $documentType = trim((string)$request->post('document_type', ''));
        $status = (int)$request->post('status', 1);

        if ($name === '') {
            return ToolsService::returnAdmin(1, [], '模版名称不能为空');
        }

        if ($bigModelTemplateId <= 0) {
            return ToolsService::returnAdmin(1, [], '关联大模型模版不能为空');
        }

        if (trim((string)$content) === '') {
            return ToolsService::returnAdmin(1, [], '模版内容不能为空');
        }

        DB::beginTransaction();
        try {
            $data = [
                'name' => $name,
                'department' => $this->getDepartmentName($departmentId),
                'department_id' => $this->normalizeNullableId($departmentId),
                'disease_id' => $this->normalizeNullableId($diseaseId),
                'document_type' => $documentType,
                'content' => $content,
                'big_model_template_id' => $bigModelTemplateId,
                'template_scope' => 1,
                'source_template_id' => null,
                'status' => $status,
            ];

            if ($id > 0) {
                $template = CustomTemplate::query()
                    ->where('id', $id)
                    ->where('template_scope', 1)
                    ->first();

                if (!$template) {
                    DB::rollBack();
                    return ToolsService::returnAdmin(1, [], '未找到公共模版');
                }

                $template->fill($data);
                $template->save();

                CustomTemplate::query()
                    ->where('template_scope', 2)
                    ->where('source_template_id', $template->id)
                    ->update([
                        'department' => $data['department'],
                        'department_id' => $data['department_id'],
                        'disease_id' => $data['disease_id'],
                        'document_type' => $data['document_type'],
                        'big_model_template_id' => $bigModelTemplateId,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                $savedTemplate = $template;
            } else {
                $savedTemplate = CustomTemplate::query()->create($data);
            }

            DB::commit();

            return ToolsService::returnAdmin(0, [
                'id' => (int)$savedTemplate->id,
                'name' => $savedTemplate->name,
                'department' => $savedTemplate->department,
                'department_id' => (int)$savedTemplate->department_id,
                'department_name' => $this->getDepartmentName($savedTemplate->department_id),
                'disease_id' => (int)$savedTemplate->disease_id,
                'disease_name' => $this->getDiseaseName($savedTemplate->disease_id),
                'document_type' => (string)$savedTemplate->document_type,
                'content' => $savedTemplate->content,
                'big_model_template_id' => (int)$savedTemplate->big_model_template_id,
                'status' => (int)$savedTemplate->status,
                'template_scope' => (int)$savedTemplate->template_scope,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return ToolsService::returnAdmin(1, [], $e->getMessage());
        }
    }

    public function delTemplate(Request $request)
    {
        $id = (int)$request->post('id', 0);

        if ($id <= 0) {
            return ToolsService::returnAdmin(1, [], '模版ID不能为空');
        }

        DB::beginTransaction();
        try {
            $template = CustomTemplate::query()
                ->where('id', $id)
                ->where('template_scope', 1)
                ->first();

            if (!$template) {
                DB::rollBack();
                return ToolsService::returnAdmin(1, [], '未找到公共模版');
            }

            CustomTemplate::query()
                ->where('id', $id)
                ->orWhere(function ($query) use ($id) {
                    $query->where('template_scope', 2)
                        ->where('source_template_id', $id);
                })
                ->update([
                    'status' => 0,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            DB::commit();
            return ToolsService::returnAdmin(0, []);
        } catch (\Throwable $e) {
            DB::rollBack();
            return ToolsService::returnAdmin(1, [], $e->getMessage());
        }
    }

    public function getDepartmentList(Request $request)
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

        return ToolsService::returnAdmin(0, $list);
    }

    public function getDiseaseList(Request $request)
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

        return ToolsService::returnAdmin(0, $list);
    }

    protected function buildQuery()
    {
        return CustomTemplate::query()
            ->leftJoin('custom_template_departments', 'custom_template_departments.id', '=', 'custom_templates.department_id')
            ->leftJoin('custom_template_diseases', 'custom_template_diseases.id', '=', 'custom_templates.disease_id')
            ->leftJoin('big_model_template', 'big_model_template.id', '=', 'custom_templates.big_model_template_id')
            ->select(
                'custom_templates.*',
                'custom_template_departments.name as department_name',
                'custom_template_diseases.name as disease_name',
                'big_model_template.title as big_model_template_title'
            );
    }

    protected function normalizeNullableId($value)
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return (int)$value > 0 ? (int)$value : null;
    }

    protected function getDepartmentName($departmentId)
    {
        if (empty($departmentId)) {
            return '';
        }

        return (string)CustomTemplateDepartment::query()->where('id', $departmentId)->value('name');
    }

    protected function getDiseaseName($diseaseId)
    {
        if (empty($diseaseId)) {
            return '';
        }

        return (string)CustomTemplateDisease::query()->where('id', $diseaseId)->value('name');
    }
}
