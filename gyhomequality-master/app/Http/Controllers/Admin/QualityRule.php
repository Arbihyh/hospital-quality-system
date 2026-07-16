<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DataExport;
use App\Http\Controllers\Controller;
use App\Model\Admin;
use App\Model\CaseRule;
use App\Model\CustomTemplateDepartment;
use App\Model\Department;
use App\Model\RuleSetting;
use App\Model\RuleSettingDetail;
use App\Model\RuleWordMap;
use App\Model\TableDict;
use App\Services\AdminService;
use App\Services\CaseService;
use App\Services\ToolsService;
use Doctrine\Inflector\Rules\Ruleset;
use Illuminate\Http\Request;
use App\Model\RuleFormula;
use App\Model\RuleSettingOther;
use App\Model\TableDictMz;
use App\Model\TableDictSY;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class QualityRule extends Controller
{
    /**
     * @param Request $request
     * @return array
     * 添加规则信息
     */
    public function addDict(Request $request)
    {
        $id = intval($request->post('id'));
        $table = $request->post('table');
        $tableField = $request->post('table_field');
        $field = $request->post('field');
        $fieldName = $request->post('field_name');
        $remark = $request->post('remark');
        if (!$fieldName) {
            return ToolsService::returnData(4001, [], '参数不能为空');
        }

        $type = 1; // 表
        $parentField = $table ?: 0;
        if ($parentField) {
            $type = 2; // 字段
            $parentField = $tableField ?: 0;
            // 如果选择了字段则代表要在字段下面添加字典
            if ($parentField) {
                $type = 3;
            } else {
                // 如果没有选择字段，则表示要在表下面添加字段
                $parentField = $table;
            }
        }

        if (empty($id)) {
            // 判断数据是否重复添加
            $dict = TableDict::query()
                ->where('parent_field', '=', $parentField)
                ->where('field', '=', $field)
                ->where('field_name', '=', $fieldName)
                ->get()->toArray();
            if (!empty($dict[0])) {
                return ToolsService::returnAdmin(4001, [], '数据已存在，不能重复添加');
            }
        }

        $msg = '';
        try {
            TableDict::query()->insert([
                'parent_field' => $parentField,
                'field' => $field,
                'field_name' => $fieldName,
                'type' => $type,
                'remark' => $remark ?? "",
            ]);
            $code = 0;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnAdmin($code, true, $msg);
    }

    /**
     * @param Request $request
     * @return array
     * 修改字段
     */
    public function editField(Request $request)
    {
        $id = intval($request->post('id'));
        $fieldName = $request->post('field_name');
        $remark = $request->post('remark');
        $status = $request->post('status');
        if (!$fieldName || !$id) {
            return ToolsService::returnData(4001, [], '参数不能为空');
        }

        $msg = '';
        try {
            TableDict::query()->where(['id' => $id])->update([
                'field_name' => $fieldName,
                'remark' => $remark,
                'status' => $status,
            ]);
            $code = 0;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnAdmin($code, true, $msg);
    }

    /**
     * @param Request $request
     * @return array
     * 修改字段字典
     */
    public function editFieldDict(Request $request)
    {
        $id = intval($request->post('id'));
        $field = $request->post('field');
        $fieldName = $request->post('field_name');
        $remark = $request->post('remark');
        $status = $request->post('status');
        if (!$fieldName || !$id || !$field) {
            return ToolsService::returnData(4001, [], '参数不能为空');
        }

        $msg = '';
        try {
            TableDict::query()->where(['id' => $id])->update([
                'field' => $field,
                'field_name' => $fieldName,
                'remark' => $remark,
                'status' => $status,
            ]);
            $code = 0;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnAdmin($code, true, $msg);
    }

    /**
     * @param Request $request
     * @param CaseDict $caseDict
     * @return array
     * 获取规则列表
     */
    //    public function getDict()
    public function getDict(Request $request)
    {
        $table = $request->post('table', '');
        $field = $request->post('field', '');
        $field_name = $request->post('field_name', '');
        $dict = $request->post('dict', '');
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 20);
        try {
            $query = TableDict::query()->where('parent_field', '=', 0)->where('status', '=', 1);
            if (!empty($table)) {
                $query = $query->where('id', '=', $table);
            }
            $parentField = $query->get()->toArray();
            $tableList = array_column($parentField, null, 'id');

            // 根据字典查询
            $fieldIds = [];
            if (!empty($dict)) {
                $dictRes = TableDict::query()->where('type', '=', 3)->where('field_name', '=', $dict)->get()->toArray();
                $fieldIds = array_column($dictRes, 'parent_field');
            }

            $obj = TableDict::query()->whereIn('parent_field', array_keys($tableList));
            if (!empty($fieldIds)) {
                $obj = $obj->whereIn('id', $fieldIds);
            }
            if (!empty($field)) {
                $obj = $obj->where('field', 'like', '%' . $field . '%');
            }
            if (!empty($field_name)) {
                $obj = $obj->where('field_name', 'like', '%' . $field_name . '%');
            }
            $count = $obj->count();

            $pageStart = ($page - 1) * $pageSize;
            $data = $obj->offset($pageStart)->LIMIT($pageSize)->get()->toArray();


            foreach ($data as &$v) {
                $v['parent_fildname'] = $tableList[$v['parent_field']]['field_name'] ?? '';
                $v['parent_field'] = $tableList[$v['parent_field']]['field'] ?? '';
            }

            $code = 0;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }

        return ToolsService::returnAdmin($code, ['count' => $count, 'list' => $data], $msg ?? '');
    }

    public function getDataByField(Request $request)
    {
        $field = $request->get('field');
        $id = $request->get('id', 0);
        $field = empty($field) ? $id : $field;
        $field_name = $request->get('field_name');

        $query = TableDict::query()->where('parent_field', '=', $field)->where('status', '=', 1);
        if (!empty($field_name)) {
            $query = $query->where('field_name', '=', $field_name);
        }
        $res = $query->get()->toArray();
        foreach ($res as &$r) {
            $r['field'] = $r['field'] . '(' . $r['field_name'] . ')';
        }
        return ToolsService::returnAdmin(0, $res, $msg ?? '');
    }

    /**
     * @param Request $request
     * @param CaseService $caseService
     * @return array
     * 获取字典详情
     */
    public function getDictDetail(Request $request)
    {
        $id = $request->get('id');
        if (!$id) {
            return ToolsService::returnAdmin(4001, [], '请求的参数有误');
        }
        $res = TableDict::query()->where('id', '=', $id)->get()->toArray();
        if (!$res) {
            return ToolsService::returnAdmin(4001, [], '数据不存在');
        }

        return ToolsService::returnAdmin(0, $res[0], $msg ?? '');
    }

    /**
     * @param Request $request
     * @param CaseService $caseService
     * @return array
     * 获取字典详情
     */
    public function delDict(Request $request, CaseService $caseService)
    {
        $id = $request->post('id');
        if (!$id) {
            return ToolsService::returnAdmin(4001, [], '请求的参数有误');
        }
        $res = TableDict::query()->where('id', '=', $id)->get()->toArray();
        if (!$res) {
            return ToolsService::returnAdmin(4001, [], '数据不存在');
        }

        try {
            TableDict::query()->where('id', '=', $id)->delete();
            $msg = '删除成功';
            $code = 0;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }

        return ToolsService::returnAdmin($code, $res[0], $msg ?? '');
    }

    /**
     * @param Request $request
     * @return array
     * 修改字典状态
     */
    public function editDictStatus(Request $request)
    {
        $id = $request->post('id');
        $status = $request->post('status');
        if (!$id) {
            return ToolsService::returnAdmin(4001, [], '请求的参数有误');
        }
        $res = TableDict::query()->where('id', '=', $id)->get()->toArray();
        if (!$res) {
            return ToolsService::returnAdmin(4001, [], '数据不存在');
        }

        try {
            TableDict::query()->where('id', '=', $id)->update(['status' => $status]);
            $code = 0;
            $msg = '修改成功';
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }

        return ToolsService::returnAdmin($code, $res[0], $msg ?? '');
    }

    /**
     * @param Request $request
     * @return array
     * 根据表名和字段名查询数据
     */
    public function getDictByType(Request $request)
    {
        //传过来的参数table是表名,field是字段名,根据这个查询并返回数据
        $table = $request->get('table');
        $field = $request->get('field');
        $res = DB::table($table)->select($field)->get()->toArray();
        return ToolsService::returnAdmin(0, $res, $msg ?? '');
    }

    /**
     * @return array
     * 质控项目的下拉
     */
    public function getSelectObjectValue(Request $request)
    {
        $rule_type = $request->get('rule_type', 1);
        $keyword = $request->get('keyword', '');
        if (!empty($rule_type) && $rule_type == 2) {
            $query = TableDictSY::query()
                ->where('status', '=', 1)
                ->whereIn('type', [1, 2, 3, 4]);
        } else if (!empty($rule_type) && $rule_type == 1) {
            // 获取所有type为1,2,3的数据
            $query = TableDict::query()
                ->where('status', '=', 1)
                ->whereIn('type', [1, 2, 3, 4]);
        } else if (!empty($rule_type) && $rule_type == 3) {
            $query = TableDictMz::query()
                ->where('status', '=', 1)
                ->whereIn('type', [1, 2, 3, 4]);
        }
        if ($keyword) {
            $query = $query->where("field_name", "like", "%{$keyword}%");
        }

        $query1 = clone $query;
        $query1 = $query1->where('sort', '!=', 0)->orderBy('sort', 'asc')->get(['id', 'parent_field', 'field', 'field_name', 'type'])->toArray();

        $query2 = clone $query;
        $query2 = $query2->where('sort', '=', 0)->orderBy('id', 'asc')->get(['id', 'parent_field', 'field', 'field_name', 'type'])->toArray();

        $res = array_merge($query1, $query2);

        // sort不为0的部分按照sort排序,sort为0的部分按照id排序
        // 上面已经完成排序：先查询sort!=0按sort排序，再查询sort=0按id排序，最后合并
        $return = array_column($res, null, 'id');

        $category_tree = array();
        foreach ($return as $key => $v) {
            if ($v['parent_field'] == 0) {
                // 一级目录
                $category_tree[] = &$return[$key];
            } else {
                if ($v['type'] == 2) {
                    // 二级目录
                    $return[$v['parent_field']]['child'][] = &$return[$key];
                } else if ($v['type'] == 3) {
                    // 三级目录,直接添加到parent_field对应的二级目录下
                    if (!isset($return[$v['parent_field']]['child'])) {
                        $return[$v['parent_field']]['child'] = [];
                    }
                    $return[$v['parent_field']]['child'][] = &$return[$key];
                } else if ($v['type'] == 4) {
                    // 三级目录,直接添加到parent_field对应的二级目录下
                    if (!isset($return[$v['parent_field']]['child'])) {
                        $return[$v['parent_field']]['child'] = [];
                    }
                    $return[$v['parent_field']]['child'][] = &$return[$key];
                }
            }
        }

        return ToolsService::returnAdmin(0, $category_tree, $msg ?? '');
    }

    /**
     * @return array
     * 质控科室的下拉
     */
    public function getSelectDepartmentValue()
    {
        $res = Department::query()->where('type_id', '=', '2')->get(['dep_id', 'dep_name'])->toArray();
        return ToolsService::returnAdmin(0, $res, $msg ?? '');
    }

    /**
     * @return array
     * 自定义质控科室的下拉
     */
    public function getSelectDepartmentValue2()
    {
        //从custom_template_departments获取,dep_id,name as dep_name
        $res = CustomTemplateDepartment::query()->get(['dep_id', 'name as dep_name'])->toArray();
        return ToolsService::returnAdmin(0, $res, $msg ?? '');
    }

    /**
     * 添加规则
     */
    public function addRule(Request $request)
    {
        $id = intval($request->post('id'));
        $caseType = $request->post('case_type', '');
        $changjing = $request->post('changjing', '');
        $department = $request->post('department', '');
        $object = $request->post('object', '');
        $type = $request->post('type', '');
        $ruleType = $request->post('rule_type', '');
        $isNot = $request->post('is_not', 0);
        $description = $request->post('description', '');
        $score = $request->post('score', 0);
        $errorLevel = $request->post('error_level', 0);
        $disease = $request->post('disease', '');
        $triggerCondition = $request->post('trigger_condition', '');
        $judgmentCaliber = $request->post('judgment_caliber', '');
        $qualityBasis = $request->post('quality_basis', '');
        $basisSource = $request->post('basis_source', '');
        $dataSource = $request->post('data_source', '');
        $status = $this->normalizeStatusFilter($request->post('status', 1), 2);
        $rule = $request->post('rule');
        $updatedBy = $this->getCurrentAdminNickname($request);

        if (!is_array($department)) {
            return ToolsService::returnAdmin(1, '', '科室参数需要是数组');
        }
        if (!is_array($object)) {
            return ToolsService::returnAdmin(1, '', '场景参数需要是数组');
        }

        $msg = '';
        $data = [
            'case_type' => $caseType ?? '',
            'department' => json_encode($department),
            'object' => implode(',', $object),
            'changjing' => implode(',', $changjing),
            'type' => $type ?? '',
            'rule_type' => $ruleType ?? '',
            'is_not' => $isNot ?? 0,
            'description' => $description ?? '',
            'score' => $score ?? 0,
            'error_level' => $errorLevel ?? 0,
            'disease' => $disease,
            'trigger_condition' => $triggerCondition,
            'judgment_caliber' => $judgmentCaliber,
            'quality_basis' => $qualityBasis,
            'basis_source' => $basisSource,
            'data_source' => $dataSource,
            'status' => $status,
            'updated_by' => $updatedBy,
        ];
        if (empty($id)) {
            $ruleId = RuleSetting::query()->insertGetId($data);
        } else {
            RuleSetting::query()->where(['id' => $id])->update($data);
            $ruleId = $id;
        }


        $ruleDetail = [];
        foreach ($rule as $r) {
            $ruleDetail[] = [
                'rule_id' => $ruleId,
                'condition_type' => $r['condition_type'] ?? 0,
                'condition_relation' => $r['condition_relation'] ?? 0,
                'is_pre_condition' => $r['is_pre_condition'] ?? 0,
                'condition_content' => !empty($r['condition_content']) ? json_encode($r['condition_content'], 256) : "",
                'pre_warning' => $r['pre_warning'] ?? 0,
                'pre_warning_time' => $r['pre_warning_time'] ?? 0,
                'time_judge' => $r['time_judge'] ?? 0,
                'time_judge_value' => $r['time_judge_value'] ?? "",
                'use_big_model' => $r['use_big_model'] ?? 0,
                'detail_status' => $r['detail_status'] ?? 2,
                'custom_basis' => !empty($r['custom_basis']) ? json_encode($r['custom_basis'], 256) : "",
                'custom_msg' => !empty($r['custom_msg']) ? json_encode($r['custom_msg'], 256) : "",
            ];
        }
        RuleSettingDetail::query()->where(['rule_id' => $ruleId])->delete();
        RuleSettingDetail::query()->insert($ruleDetail);
        $code = 0;
        return ToolsService::returnAdmin($code, true, $msg);
    }


    /**
     * @param Request $request
     * @param CaseDict $caseDict
     * @return array
     * 获取规则列表
     */
    public function getRuleList(Request $request)
    {
        $caseType = $request->get('case_type', '');
        $object = $request->get('object', '');
        $department = $request->get('department', []);
        $isNot = $request->get('is_not', '');
        $status = $this->normalizeStatusFilter($request->get('status', ''), 2);
        $type = $request->get('type', '');
        $rule_type = $request->get('rule_type', '');
        $changjing = $request->get('changjing', '');
        $errorLevel = $request->get('error_level', '');
        $score = $request->get('score', '');
        $description = $request->get('description', '');
        $page = $request->get('page', 1);
        $pageSize = $request->get('page_size', 20);

        $obj = RuleSetting::query()->where('status', '!=', 3);
        if (!empty($fieldMap)) {
            $obj = $obj->whereIn('field', $fieldMap);
        }
        if (!empty($description)) {
            $obj = $obj->where('description', 'like', '%' . $description . '%');
        }
        if (!empty($score)) {
            $obj = $obj->where('score', '=', $score);
        }
        if (!empty($errorLevel)) {
            $obj = $obj->where('error_level', '=', $errorLevel);
        }
        if (!empty($caseType)) {
            $obj = $obj->where('case_type', '=', $caseType);
        }
        if (!empty($object)) {
            // 获取$object的最后一个元素
            $object = implode(',', $object);
            $obj = $obj->where('object', '=', $object);
        }
        if (count($department) > 0) {
            $departmentWhere = [];
            foreach ($department as $item) {
                $departmentWhere[] = "JSON_CONTAINS(department, '" . $item . "')";
            }
            $obj = $obj->whereRaw('(' . implode(' or ', $departmentWhere) . ')');
        }
        if (!empty($isNot)) {
            $obj = $obj->where('is_not', '=', $isNot);
        }
        if ($this->hasFilterValue($status)) {
            $obj = $obj->where('status', '=', $status);
        }
        if (!empty($type)) {
            $obj = $obj->where('type', '=', $type);
        }
        if (!empty($changjing)) {
            $obj = $obj->where('changjing', '=', $changjing);
        }
        if (!empty($rule_type)) {
            if ($rule_type == 1) {
                $obj = $obj->where('rule_type', '=', "普通规则");
            } else if ($rule_type == 2) {
                $obj = $obj->where('rule_type', '=', "首页规则");
            } else if ($rule_type == 3) {
                $obj = $obj->where('rule_type', '=', "门诊规则");
            }
        }
        $count = $obj->count();

        $pageStart = ($page - 1) * $pageSize;
        $data = $obj->offset($pageStart)->LIMIT($pageSize)->get()->toArray();

        $department = Department::query()->get(['dep_id', 'dep_name'])->toArray();
        $department = array_column($department, 'dep_name', 'dep_id');
        $dict = TableDict::query()->where('status', '=', 1)->whereIn('type', [1, 2])->get(['id', 'parent_field', 'field', 'field_name'])->toArray();
        $dict = array_column($dict, null, 'id');

        foreach ($data as $k => $v) {
            $departmentStr = [];
            $departmentArr = json_decode($v['department'], true);
            if ($rule_type == 1) {
                foreach ($departmentArr as $v1) {
                    $departmentStr[] = $department[$v1] ?? '';
                }
                $data[$k]['department'] = implode(',', $departmentStr);
            } else {
                $data[$k]['department'] = $departmentArr ? $departmentArr[0] : '';
            }
            $data[$k]['error_level'] = intval($data[$k]['error_level']);
            //                $data[$k]['object'] = $dict[$v['object']]['field_name'] ?? '';
        }

        $code = 0;
        return ToolsService::returnAdmin($code, ['count' => $count, 'list' => $data], $msg ?? '');
    }

    /**
     * @param Request $request
     * @return array
     * 获取病例规则和自定义规则合并列表
     */
    public function getAllRuleList(Request $request)
    {
        list($page, $pageSize) = $this->getPageParams($request);

        try {
            $sourceFilter = $this->getQualitySourceFilter($request);
            if (!$sourceFilter['case_rule'] && !$sourceFilter['custom_rule']) {
                $count = 0;
                $data = [];
            } else {
                $countQuery = $this->buildAllRuleListUnionQuery($request, $sourceFilter);
                $count = DB::query()->fromSub($countQuery, 'rule_list')->count();

                $pageStart = ($page - 1) * $pageSize;
                $listQuery = DB::query()->fromSub(
                    $this->buildAllRuleListUnionQuery($request, $sourceFilter),
                    'rule_list'
                );
                $listQuery = $this->applyAllRuleListOrder($listQuery, $this->getAllRuleSortConfig($request));
                $rows = $listQuery->offset($pageStart)->limit($pageSize)->get()->toArray();
                $data = $this->formatAllRuleListRows($rows, $request);
            }

            $code = 0;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }

        return ToolsService::returnAdmin($code, ['count' => $count ?? 0, 'list' => $data ?? []], $msg ?? '');
    }

    /**
     * @param Request $request
     * @return array
     * 获取已删除的病例规则和普通自定义规则合并列表
     */
    public function getDeletedRuleList(Request $request)
    {
        list($page, $pageSize) = $this->getPageParams($request);

        try {
            $sourceFilter = $this->getQualitySourceFilter($request);
            if (!$sourceFilter['case_rule'] && !$sourceFilter['custom_rule']) {
                $count = 0;
                $data = [];
            } else {
                $countQuery = $this->buildDeletedRuleListUnionQuery($request, $sourceFilter);
                $count = DB::query()->fromSub($countQuery, 'rule_list')->count();

                $pageStart = ($page - 1) * $pageSize;
                $listQuery = DB::query()->fromSub(
                    $this->buildDeletedRuleListUnionQuery($request, $sourceFilter),
                    'rule_list'
                );
                $listQuery = $this->applyAllRuleListOrder($listQuery, $this->getAllRuleSortConfig($request));
                $rows = $listQuery->offset($pageStart)->limit($pageSize)->get()->toArray();
                $data = $this->formatAllRuleListRows($rows);
            }

            $code = 0;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }

        return ToolsService::returnAdmin($code, ['count' => $count ?? 0, 'list' => $data ?? []], $msg ?? '');
    }

    /**
     * @param Request $request
     * @return array
     * 恢复已删除规则
     */
    public function restoreRule(Request $request)
    {
        $id = intval($request->post('id', $request->get('id', $request->get('rule_id', 0))));
        $isCustom = $request->post('is_custom', $request->get('is_custom', $request->get('isCustom', '')));
        if (empty($id) || $isCustom === '') {
            return ToolsService::returnAdmin(4001, [], '参数不能为空');
        }

        try {
            $updateData = [
                'status' => 1,
                'updated_by' => $this->getCurrentAdminNickname($request),
            ];

            if (intval($isCustom) === 1) {
                $res = RuleSetting::query()
                    ->where('id', '=', $id)
                    ->where('status', '=', 3)
                    ->where('rule_type', '=', '普通规则')
                    ->update($updateData);
            } else {
                $res = CaseRule::query()
                    ->where('id', '=', $id)
                    ->where('status', '=', 3)
                    ->update($updateData);
            }

            if (!$res) {
                return ToolsService::returnAdmin(4001, [], '规则不存在或无需恢复');
            }

            $code = 0;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }

        return ToolsService::returnAdmin($code, true, $msg ?? '');
    }

    /**
     * @param Request $request
     * @return mixed
     * 导出病例规则和自定义规则合并列表
     */
    public function exportAllRuleList(Request $request)
    {
        try {
            $sourceFilter = $this->getQualitySourceFilter($request);
            $rows = [];
            if ($sourceFilter['case_rule'] || $sourceFilter['custom_rule']) {
                $listQuery = DB::query()->fromSub(
                    $this->buildAllRuleListUnionQuery($request, $sourceFilter),
                    'rule_list'
                );
                $listQuery = $this->applyAllRuleListOrder($listQuery, $this->getAllRuleSortConfig($request));
                $rows = $listQuery->get()->toArray();
            }

            $data = $this->formatAllRuleListRows($rows);
            $exportData = $this->buildAllRuleExportData($data);
            $title = [[
                'case_type' => '文书范围',
                'rule_name' => '规则名称',
                'level_name' => '问题级别',
                'type' => '质控类型',
                'disease' => '病种',
                'warning_time' => '预警时间',
                'one_no' => '单否项',
                'rule_type_name' => '规则类型',
                'node' => '运行节点',
                'score' => '分值',
                'status_name' => '状态',
                'rule_id' => '规则ID',
                'source_name' => '质控来源',
            ]];

            $fileName = '规则列表_' . date('YmdHis') . '.xlsx';
            return Excel::download(new DataExport($title, $exportData), $fileName);
        } catch (\Exception $e) {
            return ToolsService::returnAdmin(4001, [], $e->getMessage());
        }
    }

    /**
     * @param array $data
     * @return array
     * 组装规则列表导出数据
     */
    private function buildAllRuleExportData(array $data)
    {
        $exportData = [];
        foreach ($data as $rule) {
            $exportData[] = [
                'case_type' => $rule['case_type'] ?? $rule['category'] ?? '',
                'rule_name' => $rule['description'] ?? $rule['notice'] ?? '',
                'level_name' => $this->formatRuleLevel($rule['level'] ?? $rule['error_level'] ?? ''),
                'type' => $rule['type'] ?? '',
                'disease' => $rule['disease'] ?? '',
                'warning_time' => $rule['warningTime'] ?? '',
                'one_no' => $this->formatRuleOneNo($rule['one_no'] ?? ''),
                'rule_type_name' => $this->formatRuleTypeName($rule),
                'node' => $rule['node'] ?? $rule['changjing'] ?? '',
                'score' => $rule['score'] ?? '',
                'status_name' => $this->formatRuleStatus($rule['status'] ?? ''),
                'rule_id' => $rule['id'] ?? '',
                'source_name' => $this->formatRuleSource($rule),
            ];
        }

        return $exportData;
    }

    /**
     * @param mixed $level
     * @return mixed|string
     * 格式化问题级别
     */
    private function formatRuleLevel($level)
    {
        $map = [
            1 => '必改',
            2 => '建议',
            '1' => '必改',
            '2' => '建议',
        ];

        return array_key_exists($level, $map) ? $map[$level] : $level;
    }

    /**
     * @param mixed $status
     * @return mixed|string
     * 格式化规则状态
     */
    private function formatRuleStatus($status)
    {
        $map = [
            0 => '关闭',
            1 => '开启',
            2 => '关闭',
            '0' => '关闭',
            '1' => '开启',
            '2' => '关闭',
        ];

        return array_key_exists($status, $map) ? $map[$status] : $status;
    }

    /**
     * @param mixed $oneNo
     * @return mixed|string
     * 格式化单否项
     */
    private function formatRuleOneNo($oneNo)
    {
        $map = [
            0 => '否',
            1 => '是',
            '0' => '否',
            '1' => '是',
        ];

        return array_key_exists($oneNo, $map) ? $map[$oneNo] : $oneNo;
    }

    /**
     * @param array $rule
     * @return string
     * 格式化规则类型
     */
    private function formatRuleTypeName(array $rule)
    {
        if (!empty($rule['rule_type'])) {
            return $rule['rule_type'];
        }

        return intval($rule['is_custom'] ?? 0) === 1 ? '自定义规则' : '病例规则';
    }

    /**
     * @param array $rule
     * @return mixed|string
     * 格式化质控来源
     */
    private function formatRuleSource(array $rule)
    {
        if (intval($rule['is_custom'] ?? 0) === 1) {
            return '可维护';
        }

        $map = [
            1 => '系统',
            2 => '人工',
            3 => '模型',
            '1' => '系统',
            '2' => '人工',
            '3' => '模型',
        ];
        $isAi = $rule['is_ai'] ?? '';

        return array_key_exists($isAi, $map) ? $map[$isAi] : $isAi;
    }

    /**
     * @param Request $request
     * @return string
     * 获取当前登录管理员昵称
     */
    private function getCurrentAdminNickname(Request $request)
    {
        $token = $request->header('token');
        if (empty($token)) {
            return '';
        }

        $adminData = Admin::findWhereToken($token);
        if (empty($adminData)) {
            return '';
        }

        return !empty($adminData['realname']) ? $adminData['realname'] : ($adminData['name'] ?? '');
    }

    /**
     * @param Request $request
     * @return array
     * 规范分页参数，避免异常页码和过大分页拖慢接口
     */
    private function getPageParams(Request $request)
    {
        $page = intval($request->get('page', 1));
        $pageSize = intval($request->get('page_size', 10));

        if ($page < 1) {
            $page = 1;
        }
        if ($pageSize < 1) {
            $pageSize = 10;
        }
        if ($pageSize > 200) {
            $pageSize = 200;
        }

        return [$page, $pageSize];
    }

    /**
     * @param Request $request
     * @return array
     * 获取合并列表排序配置
     */
    private function getAllRuleSortConfig(Request $request)
    {
        $sortField = $this->normalizeSingleFilter($this->getRequestFilter(
            $request,
            ['sort_field', 'order_field', 'order_by', 'sort'],
            'sort'
        ));
        $sortDirection = strtolower($this->normalizeSingleFilter($this->getRequestFilter(
            $request,
            ['sort_order', 'order', 'direction'],
            'asc'
        )));

        $fieldMap = [
            'id' => 'id',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            'score' => 'score',
            'sort' => 'sort',
            'status' => 'status',
            'level' => 'level',
            'error_level' => 'level',
            'type' => 'type',
            'case_type' => 'case_type',
            'category' => 'category',
            'is_custom' => 'is_custom',
        ];

        if (!isset($fieldMap[$sortField])) {
            $sortField = 'sort';
        }
        if (!in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'asc';
        }

        return [
            'field' => $fieldMap[$sortField],
            'direction' => $sortDirection,
        ];
    }

    /**
     * @param mixed $query
     * @param array $sortConfig
     * @return mixed
     * 为合并列表添加稳定排序
     */
    private function applyAllRuleListOrder($query, array $sortConfig)
    {
        $query = $this->applyAllRuleSortOrder($query);
        if ($sortConfig['field'] !== 'sort') {
            $query = $query->orderBy($sortConfig['field'], $sortConfig['direction']);
        }

        $fallbackOrders = [
            'updated_at' => 'desc',
            'created_at' => 'desc',
            'is_custom' => 'asc',
            'id' => 'desc',
        ];

        foreach ($fallbackOrders as $field => $direction) {
            if ($field === $sortConfig['field']) {
                continue;
            }
            $query = $query->orderBy($field, $direction);
        }

        return $query;
    }

    /**
     * @param mixed $query
     * @return mixed
     * 按排序值正序排列，未配置排序值的数据排在后面
     */
    private function applyAllRuleSortOrder($query)
    {
        return $query
            ->orderByRaw("CASE WHEN `sort` IS NULL OR `sort` = '' THEN 1 ELSE 0 END ASC")
            ->orderByRaw("CAST(`sort` AS UNSIGNED) ASC")
            ->orderBy('sort', 'asc');
    }

    /**
     * @param Request $request
     * @param array $sourceFilter
     * @return mixed
     * 构建病例规则和自定义规则的数据库合并查询
     */
    private function buildAllRuleListUnionQuery(Request $request, array $sourceFilter)
    {
        $queries = [];
        if ($sourceFilter['case_rule']) {
            $queries[] = $this->getCaseRuleUnionSelect($request, $sourceFilter['case_rule_is_ai']);
        }
        if ($sourceFilter['custom_rule']) {
            $queries[] = $this->getCustomRuleUnionSelect($request);
        }

        $unionQuery = array_shift($queries);
        foreach ($queries as $query) {
            $unionQuery = $unionQuery->unionAll($query);
        }

        return $unionQuery;
    }

    /**
     * @param Request $request
     * @param array $sourceFilter
     * @return mixed
     * 构建已删除规则的数据库合并查询
     */
    private function buildDeletedRuleListUnionQuery(Request $request, array $sourceFilter)
    {
        $queries = [];
        if ($sourceFilter['case_rule']) {
            $queries[] = $this->getCaseRuleUnionSelect($request, $sourceFilter['case_rule_is_ai'], 3);
        }
        if ($sourceFilter['custom_rule']) {
            $queries[] = $this->getCustomRuleUnionSelect($request, 3, '1');
        }

        $unionQuery = array_shift($queries);
        foreach ($queries as $query) {
            $unionQuery = $unionQuery->unionAll($query);
        }

        return $unionQuery;
    }

    /**
     * @param Request $request
     * @param array $isAiList
     * @return mixed
     * 病例规则合并查询字段
     */
    private function getCaseRuleUnionSelect(Request $request, array $isAiList = [], $fixedStatus = null)
    {
        return $this->buildCaseRuleListQuery($request, $isAiList, $fixedStatus)
            ->selectRaw("id, category, options, title, notice, rule, number1, number2, score, sort, status,
                created_at, type, level, is_ai, updated_at, updated_by, is_shizhong, department, disease,
                trigger_condition, judgment_caliber, quality_basis, basis_source, data_source, node,
                one_no, warningTime, category as case_type, node as changjing, title as object,
                '' as rule_type, 0 as is_not, notice as description, level as error_level,
                '' as is_zh, 0 as is_custom")
            ->toBase();
    }

    /**
     * @param Request $request
     * @return mixed
     * 自定义规则合并查询字段
     */
    private function getCustomRuleUnionSelect(Request $request, $fixedStatus = null, $fixedRuleType = '')
    {
        return $this->buildCustomRuleListQuery($request, $fixedStatus, $fixedRuleType)
            ->selectRaw("id, case_type as category, '' as options, object as title, description as notice,
                '' as rule, 0 as number1, 0 as number2, score, sort, status, created_at, type,
                error_level as level, 0 as is_ai, updated_at, updated_by, 0 as is_shizhong, department, disease,
                trigger_condition, judgment_caliber, quality_basis, basis_source, data_source,
                changjing as node, 0 as one_no, 0 as warningTime, case_type, changjing, object,
                rule_type, is_not, description, error_level, is_zh, 1 as is_custom")
            ->toBase();
    }

    /**
     * @param array $rows
     * @return array
     * 格式化合并列表分页数据
     */
    private function formatAllRuleListRows(array $rows, Request $request = null)
    {
        $customDepartmentRows = CustomTemplateDepartment::query()->get(['dep_id', 'name'])->toArray();
        $qualityRecordMap = $this->getRuleQualityRecordStatisticMap($rows, $request);
        $data = [];

        foreach ($rows as $row) {
            $rule = (array) $row;
            $rule['is_custom'] = intval($rule['is_custom']);
            $rule['level'] = intval($rule['level']);
            $rule['error_level'] = intval($rule['error_level']);

            $qualityRecordId = $this->getRuleQualityRecordId($rule);
            $qualityRecordStatistic = $qualityRecordMap[$qualityRecordId] ?? [
                'quality_count' => 0,
                'quality_accuracy_rate' => 0,
            ];
            $rule['quality_count'] = $qualityRecordStatistic['quality_count'];
            $rule['quality_accuracy_rate'] = $qualityRecordStatistic['quality_accuracy_rate'];
            //人工纠正
            $rule['artificial_correction_count'] = '';
            //申诉纠正
            $rule['appeal_correction_count'] = '';

            if ($rule['is_custom'] === 1) {
                $rule['department'] = $this->formatRuleSettingDepartment($rule, $customDepartmentRows);
            }

            $data[] = $rule;
        }

        return $data;
    }

    /**
     * @param array $rule
     * @param array $customDepartmentRows
     * @return string
     * 根据自定义规则科室映射科室名称
     */
    private function formatRuleSettingDepartment(array $rule, array $customDepartmentRows)
    {
        $departmentArr = $this->normalizeJsonOrCommaSeparatedValue($rule['department'] ?? '');
        if (empty($departmentArr)) {
            return '';
        }

        $departmentStr = [];
        foreach ($departmentArr as $departmentId) {
            $departmentName = $this->getCustomDepartmentNameByDepId($customDepartmentRows, $departmentId);
            $departmentStr[] = $departmentName !== '' ? $departmentName : $departmentId;
        }

        $departmentStr = array_values(array_unique(array_filter($departmentStr, function ($departmentName) {
            return $departmentName !== '';
        })));

        return implode(',', $departmentStr);
    }

    /**
     * @param mixed $value
     * @return array
     * 规范 JSON 数组或逗号分隔的多值字段
     */
    private function normalizeJsonOrCommaSeparatedValue($value)
    {
        if (is_array($value)) {
            $values = $value;
        } else {
            $value = trim((string)$value);
            if ($value === '') {
                return [];
            }

            $values = json_decode($value, true);
            if (!is_array($values)) {
                $values = explode(',', $value);
            }
        }

        return array_values(array_filter(array_map(function ($item) {
            return trim((string)$item);
        }, $values), function ($item) {
            return $item !== '';
        }));
    }

    /**
     * @param array $customDepartmentRows
     * @param mixed $depId
     * @return string
     * 根据自定义科室 dep_id 包含关系获取名称
     */
    private function getCustomDepartmentNameByDepId(array $customDepartmentRows, $depId)
    {
        if ($depId === null || $depId === '') {
            return '';
        }

        foreach ($customDepartmentRows as $customDepartment) {
            if (strpos((string)($customDepartment['dep_id'] ?? ''), (string)$depId) !== false) {
                return (string)($customDepartment['name'] ?? '');
            }
        }

        return '';
    }

    /**
     * @param array $rows
     * @param Request|null $request
     * @return array
     * 获取规则列表对应的质控次数和正确率
     */
    private function getRuleQualityRecordStatisticMap(array $rows, Request $request = null)
    {
        $ruleIds = [];
        foreach ($rows as $row) {
            $ruleIds[] = $this->getRuleQualityRecordId((array)$row);
        }

        $ruleIds = array_values(array_unique(array_filter($ruleIds)));
        if (empty($ruleIds)) {
            return [];
        }

        if ($request instanceof Request) {
            [$startTime, $endTime] = $this->getQualityRecordStatisticsTimeRange($request);
        } else {
            $startTime = date('Y-m-d 00:00:00', strtotime('-30 days'));
            $endTime = date('Y-m-d 23:59:59');
        }

        $appealSql = $this->getLatestQualityAppealSql();
        $rows = DB::table('case_quality_shizhong_records as record')
            ->leftJoin(DB::raw("({$appealSql}) as appeal_info"), function ($join) {
                $join->on('appeal_info.ZYH', '=', 'record.jzhm')
                    ->on('appeal_info.error_id', '=', 'record.rule_id');
            })
            ->whereIn('record.rule_id', $ruleIds)
            ->where('record.last_quality_time', '>=', $startTime)
            ->where('record.last_quality_time', '<=', $endTime)
            ->select('record.rule_id')
            ->selectRaw('COALESCE(SUM(record.lock_count), 0) as quality_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN appeal_info.status = 1 THEN record.lock_count ELSE 0 END), 0) as error_quality_count')
            ->groupBy('record.rule_id')
            ->get()
            ->toArray();

        $result = [];
        foreach ($rows as $row) {
            $qualityCount = (int)($row->quality_count ?? 0);
            $errorQualityCount = (int)($row->error_quality_count ?? 0);
            $result[(int)$row->rule_id] = [
                'quality_count' => $qualityCount,
                'quality_accuracy_rate' => $qualityCount > 0
                    ? round((($qualityCount - $errorQualityCount) / $qualityCount) * 100, 2)
                    : 0,
            ];
        }

        return $result;
    }

    /**
     * @param array $rule
     * @return int
     * 获取规则在事中质控记录中的规则ID
     */
    private function getRuleQualityRecordId(array $rule)
    {
        $ruleId = (int)($rule['id'] ?? 0);
        if ($ruleId <= 0) {
            return 0;
        }

        return intval($rule['is_custom'] ?? 0) === 1 ? $ruleId + 1000000 : $ruleId;
    }

    /**
     * @return array
     * 获取规则分组统计
     */
    public function getRuleStatistics(Request $request)
    {
        try {
            $caseRuleCount = CaseRule::query()->where('status', '!=', 3)->count();
            $customRuleCount = $this->getCommonRuleSettingQuery()->count();

            $data = [
                'count_statistics' => [
                    // 规则总数：病案规则 + 普通自定义规则
                    'total_rules' => $caseRuleCount + $customRuleCount,
                    // 启用规则：两个规则来源中 status=1 的规则数量
                    'enabled_rules' => CaseRule::query()->where('status', 1)->count()
                        + $this->getCommonRuleSettingQuery()->where('status', 1)->count(),
                    // 停用规则：case_rule 使用 status=0，rule_setting 使用 status=2
                    'disabled_rules' => CaseRule::query()->where('status', 0)->count()
                        + $this->getCommonRuleSettingQuery()->where('status', 2)->count(),
                    // 模型规则：仅统计 case_rule 中 is_ai=3 的规则
                    'model_rules' => CaseRule::query()->where('status', '!=', 3)->where('is_ai', 3)->count(),
                    // 可维护规则：普通自定义规则来源于 rule_setting
                    'maintainable_rules' => $customRuleCount,
                ],
                // 按规则类型统计，并额外追加书写预警库、人工质控库两个固定分组
                'type_statistics' => array_merge(
                    $this->mergeGroupStatistics([
                        $this->getGroupStatistics('case_rule', 'type', [], ['status' => 3]),
                        $this->getGroupStatistics('rule_setting', 'type', ['rule_type' => '普通规则'], ['status' => 3]),
                    ]),
                    $this->getExtraRuleLibraryStatistics()
                ),
                // 按文书适用范围统计，case_rule 使用 category，rule_setting 使用 case_type
                'document_scope_statistics' => $this->mergeGroupStatistics([
                    $this->getGroupStatistics('case_rule', 'category', [], ['status' => 3]),
                    $this->getGroupStatistics('rule_setting', 'case_type', ['rule_type' => '普通规则'], ['status' => 3]),
                ]),
                // 事中质控记录统计，默认统计近30天，可通过start_time/end_time筛选
                'quality_record_statistics' => $this->getQualityRecordStatistics($request),
            ];

            $code = 0;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }

        return ToolsService::returnAdmin($code, $data ?? [], $msg ?? '');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     * 获取普通自定义规则基础查询
     */
    private function getCommonRuleSettingQuery()
    {
        return RuleSetting::query()
            ->where('status', '!=', 3)
            ->where('rule_type', '=', '普通规则');
    }

    /**
     * @param Request $request
     * @return array
     * 获取事中质控记录统计
     */
    private function getQualityRecordStatistics(Request $request)
    {
        [$startTime, $endTime] = $this->getQualityRecordStatisticsTimeRange($request);

        $appealSql = $this->getLatestQualityAppealSql();
        $statistics = DB::table('case_quality_shizhong_records as record')
            ->leftJoin(DB::raw("({$appealSql}) as appeal_info"), function ($join) {
                $join->on('appeal_info.ZYH', '=', 'record.jzhm')
                    ->on('appeal_info.error_id', '=', 'record.rule_id');
            })
            ->where('record.last_quality_time', '>=', $startTime)
            ->where('record.last_quality_time', '<=', $endTime)
            ->selectRaw('COALESCE(SUM(record.lock_count), 0) as trigger_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN appeal_info.status = 1 THEN record.lock_count ELSE 0 END), 0) as error_trigger_count')
            ->first();

        $triggerCount = (int)($statistics->trigger_count ?? 0);
        $errorTriggerCount = (int)($statistics->error_trigger_count ?? 0);
        $qualityAccuracyRate = $triggerCount > 0
            ? round((($triggerCount - $errorTriggerCount) / $triggerCount) * 100, 2)
            : 0;

        return [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'trigger_count' => $triggerCount,
            'quality_accuracy_rate' => $qualityAccuracyRate,
            'monthly_quality_counts' => $this->getMonthlyQualityRecordCounts(),
        ];
    }

    /**
     * @param Request $request
     * @return array
     * 获取质控记录统计时间范围，默认近30天
     */
    private function getQualityRecordStatisticsTimeRange(Request $request)
    {
        $startTime = $this->getRequestFilter($request, ['start_time', 'quality_start_time'], '');
        $endTime = $this->getRequestFilter($request, ['end_time', 'quality_end_time'], '');

        $startTime = $this->formatQualityRecordStatisticsTime($startTime, 'start', date('Y-m-d 00:00:00', strtotime('-30 days')));
        $endTime = $this->formatQualityRecordStatisticsTime($endTime, 'end', date('Y-m-d 23:59:59'));

        return [$startTime, $endTime];
    }

    /**
     * @param mixed $time
     * @param string $suffix
     * @param string $default
     * @return string
     * 格式化质控记录统计时间
     */
    private function formatQualityRecordStatisticsTime($time, $suffix, $default)
    {
        if (!$this->hasFilterValue($time)) {
            return $default;
        }

        $timestamp = strtotime((string)$time);
        if ($timestamp === false) {
            return $default;
        }

        $date = date('Y-m-d', $timestamp);

        return $suffix === 'end' ? $date . ' 23:59:59' : $date . ' 00:00:00';
    }

    /**
     * @return array
     * 获取近12个月质控次数列表
     */
    private function getMonthlyQualityRecordCounts()
    {
        $monthStart = date('Y-m-01 00:00:00', strtotime('-11 months'));
        $monthEnd = date('Y-m-t 23:59:59');
        $monthExpr = "DATE_FORMAT(last_quality_time, '%Y-%m')";

        $rows = DB::table('case_quality_shizhong_records')
            ->selectRaw($monthExpr . ' as month')
            ->selectRaw('COALESCE(SUM(lock_count), 0) as quality_count')
            ->where('last_quality_time', '>=', $monthStart)
            ->where('last_quality_time', '<=', $monthEnd)
            ->groupBy(DB::raw($monthExpr))
            ->get()
            ->toArray();

        $countMap = [];
        foreach ($rows as $row) {
            $countMap[$row->month] = (int)($row->quality_count ?? 0);
        }

        $list = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime('-' . $i . ' months'));
            $list[] = [
                'month' => $month,
                'quality_count' => $countMap[$month] ?? 0,
            ];
        }

        return $list;
    }

    /**
     * @return string
     * 获取最新事中质控申诉SQL
     */
    private function getLatestQualityAppealSql()
    {
        return "
            SELECT appeal.*
            FROM appeal
            INNER JOIN (
                SELECT MAX(id) AS id
                FROM appeal
                WHERE quality_type = 2 AND type = 2
                GROUP BY ZYH, error_id
            ) latest_appeal ON latest_appeal.id = appeal.id
        ";
    }

    /**
     * @return array
     * 获取特殊规则库统计
     */
    private function getExtraRuleLibraryStatistics()
    {
        // 书写预警库：配置了有效 warningTime 的未删除病案规则
        $warningRuleQuery = CaseRule::query()
            ->where('status', '!=', 3)
            ->whereNotNull('warningTime')
            ->where('warningTime', '!=', '')
            ->where('warningTime', '!=', 0);

        // 人工质控库：is_ai=2 的未删除病案规则
        $manualRuleQuery = CaseRule::query()
            ->where('status', '!=', 3)
            ->where('is_ai', 2);

        return [
            $this->buildCaseRuleStatisticItem('书写预警库', $warningRuleQuery),
            $this->buildCaseRuleStatisticItem('人工质控库', $manualRuleQuery),
        ];
    }

    /**
     * @param string $name
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return array
     * 构建病案规则统计项
     */
    private function buildCaseRuleStatisticItem($name, $query)
    {
        return [
            'name' => $name,
            // 当前分组规则总数
            'count' => (clone $query)->count(),
            // 当前分组启用规则数量
            'enabled_rules' => (clone $query)->where('status', 1)->count(),
            // 当前分组停用规则数量
            'disabled_rules' => (clone $query)->where('status', 0)->count(),
            // 当前分组模型规则数量
            'model_rules' => (clone $query)->where('is_ai', 3)->count(),
            // 特殊规则库来源于 case_rule，不属于 rule_setting 可维护规则
            'maintainable_rules' => 0,
        ];
    }

    /**
     * @param string $table
     * @param string $field
     * @param array $where
     * @param array $whereNot
     * @return array
     * 获取单表分组统计
     */
    private function getGroupStatistics($table, $field, array $where = [], array $whereNot = [])
    {
        $query = DB::table($table);
        foreach ($where as $key => $value) {
            $query = $query->where($key, '=', $value);
        }
        foreach ($whereNot as $key => $value) {
            $query = $query->where($key, '!=', $value);
        }

        // 模型规则仅存在于 case_rule，rule_setting 固定为 0
        $modelRuleSelect = $table === 'case_rule'
            ? 'SUM(CASE WHEN is_ai = 3 THEN 1 ELSE 0 END) as model_rules'
            : '0 as model_rules';

        // 可维护规则仅统计 rule_setting 中的普通自定义规则
        $maintainableRuleSelect = $table === 'rule_setting'
            ? 'COUNT(*) as maintainable_rules'
            : '0 as maintainable_rules';

        // 停用状态按来源表区分：case_rule 为 0，rule_setting 为 2
        $disabledStatus = $table === 'rule_setting' ? 2 : 0;

        return $query
            ->select(
                $field . ' as name',
                DB::raw('COUNT(*) as count'),
                // 当前分组启用规则数量
                DB::raw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as enabled_rules'),
                // 当前分组停用规则数量
                DB::raw('SUM(CASE WHEN status = ' . $disabledStatus . ' THEN 1 ELSE 0 END) as disabled_rules'),
                DB::raw($modelRuleSelect),
                DB::raw($maintainableRuleSelect)
            )
            ->groupBy($field)
            ->get()
            ->toArray();
    }

    /**
     * @param array $statisticsList
     * @return array
     * 合并多表分组统计
     */
    private function mergeGroupStatistics(array $statisticsList)
    {
        $mergeData = [];

        // 多来源同名分组需要累计这些统计字段
        $statisticFields = [
            'count',
            'enabled_rules',
            'disabled_rules',
            'model_rules',
            'maintainable_rules',
        ];

        foreach ($statisticsList as $statistics) {
            foreach ($statistics as $item) {
                $name = $item->name ?? '';
                if ($name === '' || $name === null) {
                    $name = '未设置';
                }
                if (!isset($mergeData[$name])) {
                    // name 展示时统一增加“质控库”后缀，原始 name 仍作为合并 key
                    $mergeData[$name] = [
                        'name' => $name . '质控库',
                        'count' => 0,
                        'enabled_rules' => 0,
                        'disabled_rules' => 0,
                        'model_rules' => 0,
                        'maintainable_rules' => 0,
                    ];
                }
                foreach ($statisticFields as $statisticField) {
                    $mergeData[$name][$statisticField] += (int) ($item->{$statisticField} ?? 0);
                }
            }
        }

        return array_values($mergeData);
    }

    /**
     * @param Request $request
     * @param array $keys
     * @param mixed $default
     * @return mixed
     * 按兼容字段顺序获取筛选值
     */
    private function getRequestFilter(Request $request, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            $value = $request->get($key, null);
            if ($this->hasFilterValue($value)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * @param mixed $value
     * @return bool
     * 判断筛选值是否有效，保留0作为有效值
     */
    private function hasFilterValue($value)
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->hasFilterValue($item)) {
                    return true;
                }
            }

            return false;
        }

        return $value !== null && $value !== '';
    }

    /**
     * @param mixed $value
     * @return array
     * 多选参数统一转数组，兼容逗号分隔字符串
     */
    private function normalizeFilterArray($value)
    {
        if (!$this->hasFilterValue($value)) {
            return [];
        }
        if (!is_array($value)) {
            $value = explode(',', (string) $value);
        }

        $result = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $result = array_merge($result, $this->normalizeFilterArray($item));
                continue;
            }
            $item = trim((string) $item);
            if ($item !== '') {
                $result[] = $item;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @param mixed $value
     * @return mixed|string
     * 单选参数统一取第一个有效值
     */
    private function normalizeSingleFilter($value)
    {
        $values = $this->normalizeFilterArray($value);

        return $values[0] ?? '';
    }

    /**
     * @param mixed $value
     * @return mixed|string
     * 问题级别中文值转数据库值
     */
    private function normalizeLevelFilter($value)
    {
        $value = $this->normalizeSingleFilter($value);
        $map = [
            '必改' => 1,
            '建议' => 2,
        ];

        return $map[$value] ?? $value;
    }

    /**
     * @param mixed $value
     * @return mixed|string
     * 状态中文值转数据库值
     */
    private function normalizeStatusFilter($value, $disabledStatus = 0)
    {
        $value = $this->normalizeSingleFilter($value);
        $map = [
            '开启' => 1,
            '启用' => 1,
            '关闭' => $disabledStatus,
            '停用' => $disabledStatus,
            '禁用' => $disabledStatus,
        ];

        if ((string) $value === '0' && (int) $disabledStatus !== 0) {
            return $disabledStatus;
        }

        return array_key_exists($value, $map) ? $map[$value] : $value;
    }

    /**
     * @param Request $request
     * @return array
     * 解析质控来源，决定查询病例规则、自定义规则及is_ai条件
     */
    private function getQualitySourceFilter(Request $request)
    {
        $source = $this->getRequestFilter($request, ['source', 'quality_source', 'is_ai'], '');
        $sourceList = $this->normalizeFilterArray($source);
        if (count($sourceList) === 0) {
            return [
                'case_rule' => true,
                'custom_rule' => true,
                'case_rule_is_ai' => [],
            ];
        }

        $sourceMap = [
            '系统' => 1,
            '人工' => 2,
            '模型' => 3,
            '大模型' => 3,
        ];
        $customSource = ['自定义', '可维护'];
        $caseRuleIsAi = [];
        $customRule = false;

        foreach ($sourceList as $sourceName) {
            if (isset($sourceMap[$sourceName])) {
                $caseRuleIsAi[] = $sourceMap[$sourceName];
            } elseif (in_array($sourceName, $customSource, true)) {
                $customRule = true;
            } elseif (is_numeric($sourceName)) {
                $caseRuleIsAi[] = intval($sourceName);
            }
        }

        return [
            'case_rule' => count($caseRuleIsAi) > 0,
            'custom_rule' => $customRule,
            'case_rule_is_ai' => array_values(array_unique($caseRuleIsAi)),
        ];
    }

    /**
     * @param mixed $query
     * @param string $field
     * @param array $values
     * @return mixed
     * 多值精确匹配筛选
     */
    private function applyInFilter($query, $field, array $values)
    {
        if (count($values) === 0) {
            return $query;
        }
        if (count($values) === 1) {
            return $query->where($field, '=', $values[0]);
        }

        return $query->whereIn($field, $values);
    }

    /**
     * @param mixed $query
     * @param string $field
     * @param array $values
     * @return mixed
     * 多值模糊匹配筛选
     */
    private function applyLikeAnyFilter($query, $field, array $values)
    {
        if (count($values) === 0) {
            return $query;
        }

        return $query->where(function ($subQuery) use ($field, $values) {
            foreach ($values as $value) {
                $subQuery->orWhere($field, 'like', '%' . $value . '%');
            }
        });
    }

    /**
     * @param mixed $query
     * @param string $field
     * @param array $values
     * @return mixed
     * JSON数组字段多值包含筛选
     */
    private function applyJsonContainsAnyFilter($query, $field, array $values)
    {
        if (count($values) === 0) {
            return $query;
        }

        return $query->where(function ($subQuery) use ($field, $values) {
            foreach ($values as $value) {
                $jsonValues = [json_encode((string) $value, JSON_UNESCAPED_UNICODE)];
                if (is_numeric($value)) {
                    $jsonValues[] = json_encode(intval($value));
                }
                foreach (array_unique($jsonValues) as $jsonValue) {
                    $subQuery->orWhereRaw('JSON_CONTAINS(' . $field . ', ?)', [$jsonValue]);
                }
            }
        });
    }

    /**
     * @param mixed $query
     * @param array $departments
     * @return mixed
     * 根据自定义科室名称筛选规则科室
     */
    private function applyRuleSettingDepartmentFilter($query, array $departments)
    {
        if (count($departments) === 0) {
            return $query;
        }

        $departmentValues = $this->getRuleSettingDepartmentFilterValues($departments);

        return $this->applyJsonContainsAnyFilter($query, 'department', $departmentValues);
    }

    /**
     * @param array $departments
     * @return array
     * 将自定义科室名称转换为 rule_setting.department 可匹配的科室ID
     */
    private function getRuleSettingDepartmentFilterValues(array $departments)
    {
        $departmentValues = $departments;
        $customDepartmentIds = CustomTemplateDepartment::query()
            ->whereIn('name', $departments)
            ->pluck('dep_id')
            ->toArray();

        foreach ($customDepartmentIds as $customDepartmentId) {
            $customDepartmentId = trim((string)$customDepartmentId);
            if ($customDepartmentId === '') {
                continue;
            }

            $departmentValues[] = $customDepartmentId;
            foreach (explode(',', $customDepartmentId) as $departmentId) {
                $departmentId = trim((string)$departmentId);
                if ($departmentId !== '') {
                    $departmentValues[] = $departmentId;
                }
            }
        }

        return array_values(array_unique(array_filter($departmentValues, function ($departmentValue) {
            return $departmentValue !== null && $departmentValue !== '';
        })));
    }

    /**
     * @param Request $request
     * @param array $isAiList
     * @return \Illuminate\Database\Eloquent\Builder
     * 构建病例规则列表查询
     */
    private function buildCaseRuleListQuery(Request $request, array $isAiList = [], $fixedStatus = null)
    {
        $ruleName = $this->getRequestFilter($request, ['rule_name', 'notice', 'description'], '');
        $departments = $this->normalizeFilterArray($this->getRequestFilter($request, ['department'], []));
        $diseases = $this->normalizeFilterArray($this->getRequestFilter($request, ['disease'], []));
        $caseTypes = $this->normalizeFilterArray($this->getRequestFilter($request, ['case_type', 'category'], []));
        $types = $this->normalizeFilterArray($this->getRequestFilter($request, ['type'], []));
        $level = $this->normalizeLevelFilter($this->getRequestFilter($request, ['level', 'error_level'], ''));
        $node = $this->normalizeSingleFilter($this->getRequestFilter($request, ['node', 'changjing'], ''));
        $status = $this->normalizeStatusFilter($this->getRequestFilter($request, ['status'], ''), 2);
        $score = $this->normalizeSingleFilter($this->getRequestFilter($request, ['score'], ''));

        $obj = CaseRule::query();
        if ($fixedStatus === null) {
            $obj = $obj->where('status', '!=', 3);
        } else {
            $obj = $obj->where('status', '=', $fixedStatus);
        }
        if ($this->hasFilterValue($ruleName)) {
            $obj = $obj->where('notice', 'like', '%' . $ruleName . '%');
        }
        $obj = $this->applyLikeAnyFilter($obj, 'department', $departments);
        $obj = $this->applyInFilter($obj, 'disease', $diseases);
        $obj = $this->applyInFilter($obj, 'category', $caseTypes);
        $obj = $this->applyInFilter($obj, 'type', $types);
        $obj = $this->applyInFilter($obj, 'is_ai', $isAiList);
        if ($this->hasFilterValue($level)) {
            $obj = $obj->where('level', '=', $level);
        }
        if ($this->hasFilterValue($node)) {
            $obj = $obj->where('node', '=', $node);
        }
        if ($fixedStatus === null && $this->hasFilterValue($status)) {
            $obj = $obj->where('status', '=', $status);
        }
        if ($this->hasFilterValue($score)) {
            $obj = $obj->where('score', '=', $score);
        }

        return $obj;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Builder
     * 构建自定义规则列表查询
     */
    private function buildCustomRuleListQuery(Request $request, $fixedStatus = null, $fixedRuleType = '')
    {
        $caseTypes = $this->normalizeFilterArray($this->getRequestFilter($request, ['case_type', 'category'], []));
        $object = $this->getRequestFilter($request, ['object', 'title'], '');
        $description = $this->getRequestFilter($request, ['rule_name', 'description', 'notice'], '');
        $department = $this->normalizeFilterArray($this->getRequestFilter($request, ['department'], []));
        $diseases = $this->normalizeFilterArray($this->getRequestFilter($request, ['disease'], []));
        $isNot = $this->normalizeSingleFilter($this->getRequestFilter($request, ['is_not'], ''));
        $status = $this->normalizeStatusFilter($this->getRequestFilter($request, ['status'], ''));
        $type = $this->normalizeFilterArray($this->getRequestFilter($request, ['type'], []));
        $ruleType = $fixedRuleType !== '' ? $fixedRuleType : $request->get('rule_type', '1');
        $changjing = $this->normalizeSingleFilter($this->getRequestFilter($request, ['node', 'changjing'], ''));
        $errorLevel = $this->normalizeLevelFilter($this->getRequestFilter($request, ['level', 'error_level'], ''));
        $score = $this->normalizeSingleFilter($this->getRequestFilter($request, ['score'], ''));

        $obj = RuleSetting::query();
        if ($fixedStatus === null) {
            $obj = $obj->where('status', '!=', 3);
        } else {
            $obj = $obj->where('status', '=', $fixedStatus);
        }
        if ($this->hasFilterValue($description)) {
            $obj = $obj->where('description', 'like', '%' . $description . '%');
        }
        if ($this->hasFilterValue($score)) {
            $obj = $obj->where('score', '=', $score);
        }
        if ($this->hasFilterValue($errorLevel)) {
            $obj = $obj->where('error_level', '=', $errorLevel);
        }
        $obj = $this->applyInFilter($obj, 'case_type', $caseTypes);
        $obj = $this->applyInFilter($obj, 'disease', $diseases);
        if ($this->hasFilterValue($object)) {
            $object = is_array($object) ? implode(',', $object) : $object;
            $obj = $obj->where('object', '=', $object);
        }
        if (count($department) > 0) {
            $obj = $this->applyRuleSettingDepartmentFilter($obj, $department);
        }
        if ($this->hasFilterValue($isNot)) {
            $obj = $obj->where('is_not', '=', $isNot);
        }
        if ($fixedStatus === null && $this->hasFilterValue($status)) {
            $obj = $obj->where('status', '=', $status);
        }
        $obj = $this->applyInFilter($obj, 'type', $type);
        if ($this->hasFilterValue($changjing)) {
            $obj = $obj->where('changjing', '=', $changjing);
        }
        if (!empty($ruleType)) {
            if ($ruleType == 1) {
                $obj = $obj->where('rule_type', '=', '普通规则');
            } else if ($ruleType == 2) {
                $obj = $obj->where('rule_type', '=', '首页规则');
            } else if ($ruleType == 3) {
                $obj = $obj->where('rule_type', '=', '门诊规则');
            }
        }

        return $obj;
    }

    /**
     * @param Request $request
     * @param array $isAiList
     * @return array
     * 获取病例规则列表数据
     */
    private function getCaseRuleListData(Request $request, array $isAiList = [])
    {
        $caseRules = $this->buildCaseRuleListQuery($request, $isAiList)->get()->toArray();
        foreach ($caseRules as &$caseRule) {
            $caseRule['case_type'] = $caseRule['category'] ?? '';
            $caseRule['object'] = $caseRule['title'] ?? '';
            $caseRule['description'] = $caseRule['notice'] ?? '';
            $caseRule['is_custom'] = 0;
        }

        return $caseRules;
    }

    /**
     * @param Request $request
     * @return array
     * 获取自定义规则列表数据
     */
    private function getCustomRuleListData(Request $request)
    {
        $ruleType = $request->get('rule_type', '1');
        $data = $this->buildCustomRuleListQuery($request)->get()->toArray();
        $departmentMap = Department::query()->get(['dep_id', 'dep_name'])->toArray();
        $departmentMap = array_column($departmentMap, 'dep_name', 'dep_id');

        foreach ($data as $key => $rule) {
            $departmentArr = json_decode($rule['department'], true);
            if (!is_array($departmentArr)) {
                $departmentArr = [];
            }

            if ($ruleType == 1) {
                $departmentStr = [];
                foreach ($departmentArr as $departmentId) {
                    $departmentStr[] = $departmentMap[$departmentId] ?? '';
                }
                $data[$key]['department'] = implode(',', $departmentStr);
            } else {
                $data[$key]['department'] = $departmentArr ? $departmentArr[0] : '';
            }

            $data[$key]['level'] = intval($data[$key]['error_level']);
            $data[$key]['category'] = $rule['case_type'] ?? '';
            $data[$key]['title'] = $rule['object'] ?? '';
            $data[$key]['notice'] = $rule['description'] ?? '';
            $data[$key]['is_custom'] = 1;
            $data[$key]['node'] = $rule['changjing'] ?? '';
        }

        return $data;
    }

    /**
     * @param Request $request
     * @return array
     * 获取规则详情
     */
    public function getRuleDetail(Request $request)
    {
        $id = $request->get('id');

        $ruleSetting = RuleSetting::query()->where('id', '=', $id)->get()->toArray();
        $ruleSetting = $ruleSetting[0];
        $ruleSettingDetail = RuleSettingDetail::query()->where('rule_id', '=', $id)->get()->toArray();
        $ruleSetting['department'] = json_decode($ruleSetting['department'], 256);
        $ruleSetting['error_level'] = intval($ruleSetting['error_level']);
        foreach ($ruleSettingDetail as &$d) {
            $d['condition_content'] = json_decode($d['condition_content'], true);
            if (!empty($d['custom_basis'])) {
                $d['custom_basis'] = json_decode($d['custom_basis'], true);
            }
            if (!empty($d['custom_msg'])) {
                $d['custom_msg'] = json_decode($d['custom_msg'], true);
            }
        }

        return ToolsService::returnAdmin(0, ['rule' => $ruleSetting, 'rule_list' => $ruleSettingDetail], $msg ?? '');
    }

    /**
     * @param Request $request
     * @return array
     * 添加规则信息
     */
    public function addWordMap(Request $request)
    {
        $name = $request->post('name');
        $keywords = $request->post('keywords');
        if (!$name || !$keywords) {
            return ToolsService::returnData(4001, [], '参数不能为空');
        }

        // 获取当前登录用户信息（直接从 header 获取 token，因为路由跳过了中间件）
        $token = $request->header('token');
        $gxr = '';
        if (!empty($token)) {
            $adminData = Admin::findWhereToken($token);
            if ($adminData) {
                // 优先使用 realname，如果没有则使用 name
                $gxr = !empty($adminData['realname']) ? $adminData['realname'] : ($adminData['name'] ?? '');
            }
        }

        // 处理 keywords，统一转换为逗号分隔的字符串
        if (is_array($keywords)) {
            // 如果是数组，转换为逗号分隔的字符串
            $keywordsStr = implode(',', $keywords);
        } else if (is_string($keywords)) {
            // 如果是字符串，尝试解析 JSON
            $decoded = json_decode($keywords, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // 如果是 JSON 数组，转换为逗号分隔的字符串
                $keywordsStr = implode(',', $decoded);
            } else {
                // 如果不是 JSON，保持原样（已经是逗号分隔的字符串）
                $keywordsStr = $keywords;
            }
        } else {
            // 其他情况，转换为字符串
            $keywordsStr = (string)$keywords;
        }

        $msg = '';
        try {
            RuleWordMap::query()->insert([
                'name' => $name,
                'keyword' => $keywordsStr,
                'GXR' => $gxr,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $code = 0;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnAdmin($code, true, $msg);
    }

    /**
     * @param Request $request
     * @return array
     * 添加规则信息
     */
    public function editRuleStatus(Request $request)
    {
        $id = $request->post('id');
        $status = $request->post('status');
        if (!$id || !$status) {
            return ToolsService::returnData(4001, [], '参数不能为空');
        }

        $msg = '';
        try {
            RuleSetting::query()->where('id', '=', $id)->update([
                'status' => $status,
                'updated_by' => $this->getCurrentAdminNickname($request),
            ]);
            $code = 0;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnAdmin($code, true, $msg);
    }

    /**
     * @param Request $request
     * @return array
     * 修改字段
     */
    public function editWordMap(Request $request)
    {
        $id = intval($request->post('id'));
        $name = $request->post('name');
        $keywords = $request->post('keywords');
        $BZMC = $request->post('BZMC') ?? null;

        if (!$name || !$keywords) {
            return ToolsService::returnData(4001, [], '参数不能为空');
        }

        // 获取当前登录用户信息（直接从 header 获取 token，因为路由跳过了中间件）
        $token = $request->header('token');
        $gxr = '';
        if (!empty($token)) {
            $adminData = Admin::findWhereToken($token);
            if ($adminData) {
                // 优先使用 realname，如果没有则使用 name
                $gxr = !empty($adminData['realname']) ? $adminData['realname'] : ($adminData['name'] ?? '');
            }
        }

        // 处理 keywords，统一转换为逗号分隔的字符串
        if (is_array($keywords)) {
            // 如果是数组，转换为逗号分隔的字符串
            $keywordsStr = implode(',', $keywords);
        } else if (is_string($keywords)) {
            // 如果是字符串，尝试解析 JSON
            $decoded = json_decode($keywords, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // 如果是 JSON 数组，转换为逗号分隔的字符串
                $keywordsStr = implode(',', $decoded);
            } else {
                // 如果不是 JSON，保持原样（已经是逗号分隔的字符串）
                $keywordsStr = $keywords;
            }
        } else {
            // 其他情况，转换为字符串
            $keywordsStr = (string)$keywords;
        }

        $msg = '';
        try {
            RuleWordMap::query()->where(['id' => $id])->update([
                'name' => $name,
                'keyword' => $keywordsStr,
                'BZMC' => $BZMC,
                'GXR' => $gxr,
            ]);
            $code = 0;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnAdmin($code, true, $msg);
    }


    /**
     * @param Request $request
     * @param CaseDict $caseDict
     * @return array
     * 获取规则列表
     */
    public function getWordMap(Request $request)
    {
        $name = $request->post('name', '');
        $keyword = $request->post('keyword', '');
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $id = $request->post('id', '');

        try {
            $obj = RuleWordMap::query();
            if (!empty($id)) {
                $obj = $obj->where('id', '=', $id);
            }
            if (!empty($name)) {
                $obj = $obj->where('name', 'like', '%' . $name . '%');
            }
            if (!empty($keyword)) {
                $obj = $obj->where('keyword', 'like', '%' . $keyword . '%');
            }
            $count = $obj->count();

            $pageStart = ($page - 1) * $pageSize;
            $data = $obj->offset($pageStart)->LIMIT($pageSize)->get()->toArray();


            foreach ($data as &$v) {
                $v['keywords'] = explode(',', $v['keyword']);
            }

            $code = 0;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }

        return ToolsService::returnAdmin($code, ['count' => $count, 'list' => $data], $msg ?? '');
    }

    /**
     * @param Request $request
     * @param CaseDict $caseDict
     * @return array
     * 获取规则列表
     */
    public function getAllWordMap(Request $request)
    {
        $name = $request->post('name', '');
        try {
            $obj = RuleWordMap::query();
            if (!empty($name)) {
                $obj = $obj->where('name', 'like', '%' . $name . '%');
            }
            $data = $obj->get()->toArray();

            foreach ($data as &$v) {
                $v['keywords'] = explode(',', $v['keyword']);
            }

            $code = 0;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }

        return ToolsService::returnAdmin($code, $data, $msg ?? '');
    }

    /**
     * @param Request $request
     * @return array
     * 删除字段
     */
    public function delWordMap(Request $request)
    {
        $id = intval($request->post('id'));
        if (!$id) {
            return ToolsService::returnData(4001, [], '参数不能为空');
        }

        $msg = '';
        try {
            RuleWordMap::query()->where(['id' => $id])->delete();
            $code = 0;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnAdmin($code, true, $msg);
    }

    /**
     * @param Request $request
     * @return array
     * 删除规则
     */
    public function delRule(Request $request)
    {
        $id = intval($request->post('id'));
        if (!$id) {
            return ToolsService::returnData(4001, [], '参数不能为空');
        }

        $msg = '';
        try {
            //修改逻辑,删除规则时,将规则状态改为3,表示已删除
            RuleSetting::query()->where(['id' => $id])->update([
                'status' => 3,
                'updated_by' => $this->getCurrentAdminNickname($request),
            ]);
            //RuleSettingDetail::query()->where(['rule_id' => $id])->delete();
            $code = 0;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnAdmin($code, true, $msg);
    }

    /**
     * @param Request $request
     * @return array
     * 获取版本记录列表
     */
    public function getVersionRecord(Request $request)
    {
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 20);
        $name = $request->post('name', '');
        $keyword = $request->post('keyword', '');

        try {
            $obj = RuleWordMap::query()->where('is_bb', '=', '1');

            if (!empty($name)) {
                $obj = $obj->where('name', 'like', '%' . $name . '%');
            }
            if (!empty($keyword)) {
                $obj = $obj->where('keyword', 'like', '%' . $keyword . '%');
            }

            $count = $obj->count();
            $pageStart = ($page - 1) * $pageSize;
            $data = $obj->orderBy('updated_at', 'desc')
                ->offset($pageStart)
                ->limit($pageSize)
                ->get()
                ->toArray();

            foreach ($data as &$v) {
                if (!empty($v['keyword'])) {
                    $v['keywords'] = explode(',', $v['keyword']);
                } else {
                    $v['keywords'] = [];
                }
            }

            $code = 0;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }

        return ToolsService::returnAdmin($code, ['count' => $count, 'list' => $data], $msg ?? '');
    }

    /**
     * 获取规则公式列表
     * @return array
     */
    public function getSelectFormula(Request $request)
    {
        try {
            //添加条件,获取传入参数lx,根据lx返回对应的公式
            $lx = $request->get('lx', '');
            $formulas = RuleFormula::query()->where('lx', '=', $lx)->get(['id', 'formula'])->toArray();
            $code = 0;
        } catch (\Exception $e) {
            $formulas = [];
            $code = 4001;
            $msg = $e->getMessage();
        }

        return ToolsService::returnAdmin($code, $formulas, $msg ?? '');
    }

    //新建方法,从表rule_setting_other中获取病历类型,质控类型,质控场景的下拉数据
    public function getRuleSettingOther(Request $request)
    {
        //type:1病历类型,2质控类型,3质控场景
        $type = $request->get('type', '');
        $issz = $request->get('issz', 0);
        $data = RuleSettingOther::query()->where('type', '=', $type)->where('is_mz', 0)->get(['id', 'name'])->toArray();

        if ($type == 1 && $issz == 1) {
            $data = TableDictSY::query()->where('parent_field', '!=', 0)->get(['id', 'field_name as name'])->toArray();
        } elseif ($type == 1 && $issz == 3) {
            $data = RuleSettingOther::query()->where('type', '=', $type)->where('is_mz', 1)->get(['id', 'name'])->toArray();
        }
        return ToolsService::returnAdmin(0, $data, $msg ?? '');
    }
}
