<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\TableDict;
use App\Services\CaseService;
use App\Services\ToolsService;
use Illuminate\Http\Request;

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
        if (!$field || !$fieldName) {
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
            }else{
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
            if (empty($dict[0])) {
                return ToolsService::returnData(4001, [], '数据已存在，不能重复添加');
            }
        }

        $msg = '';
        try {
            TableDict::query()->updateOrInsert(['id' => $id], [
                'parent_field' => $parentField,
                'field' => $field,
                'field_name' => $fieldName,
                'type' => $type,
                'remark' => $remark,
            ]);
            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnData($code, true, $msg);
    }

    /**
     * @param Request $request
     * @param CaseDict $caseDict
     * @return array
     * 获取规则列表
     */
    public function getDict()
//    public function getDict(Request $request)
    {
        $request = new Request();
        $table = $request->post('table', '');
        $field = $request->post('field', '');
        $fieldMap = !empty($field) ? [$field] : [];
        $field_name = $request->post('field_name', '');
        $dict = $request->post('dict', '');
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 20);
        try {
            $query = TableDict::query()->where('parent_field', '=', 0)->where('status','=', 1);
            if(!empty($table)){
                $query = $query->where('field', '=', $table);
            }
            $parentField = $query->get()->toArray();
            $tableList = array_column($parentField, null, 'field');

            // 根据字典查询
            if(!empty($dict)){
                $dictRes = TableDict::query()->where('type', '=', 3)->where('field_name', '=', $dict)->get()->toArray();
                $fieldMap = array_merge($fieldMap, array_column($dictRes, 'field'));
            }

            $obj = TableDict::query()->whereIn('parent_field', array_keys($tableList));
            $fieldMap = array_filter($fieldMap);
            if (!empty($fieldMap)) {
                $obj = $obj->whereIn('field', $fieldMap);
            }
            if(!empty($field_name)){
                $obj = $obj->where('field_name', '=', $field_name);
            }
            $count = $obj->count();

            $pageStart = ($page - 1) * $pageSize;
            $data = $obj->offset($pageStart)->LIMIT($pageSize)->get()->toArray();


            foreach ($data as &$v) {
                $v['parent_fildname'] = $tableList[$v['parent_field']]['field_name'] ?? '';
            }

            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }

        return ToolsService::returnData($code, ['count' => $count, 'data' => $data], $msg ?? '');
    }

    public function getDataByField(Request $request)
    {
        $field = $request->get('field');
        $field_name = $request->get('field_name');

        $query = TableDict::query()->where('parent_field', '=', $field);
        if(!empty($field_name)){
            $query = $query->where('field_name', '=', $field_name);
        }
        $res = $query->get()->toArray();
        return ToolsService::returnData(200, $res, $msg ?? '');
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
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        $res = TableDict::query()->where('id', '=', $id)->get()->toArray();
        if (!$res) {
            return ToolsService::returnData(4001, [], '数据不存在');
        }

        return ToolsService::returnData(200, $res[0], $msg ?? '');
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
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        $res = TableDict::query()->where('id', '=', $id)->get()->toArray();
        if (!$res) {
            return ToolsService::returnData(4001, [], '数据不存在');
        }

        try {
            TableDict::query()->where('id', '=', $id)->delete();
            $msg = '删除成功';
            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }

        return ToolsService::returnData($code, $res[0], $msg ?? '');
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
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        $res = TableDict::query()->where('id', '=', $id)->get()->toArray();
        if (!$res) {
            return ToolsService::returnData(4001, [], '数据不存在');
        }

        try {
            TableDict::query()->where('id', '=', $id)->update(['status' => $status]);
            $code = 200;
            $msg = '修改成功';
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }

        return ToolsService::returnData($code, $res[0], $msg ?? '');
    }


}
