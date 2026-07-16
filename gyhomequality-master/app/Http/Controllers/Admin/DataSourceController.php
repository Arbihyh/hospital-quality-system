<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\DataSource;
use App\Model\DataSourceHospital;
use App\Model\TableDict;
use App\Services\ToolsService;
use Illuminate\Http\Request;


class DataSourceController extends Controller
{
    public function lists(Request $request)
    {
        $tableName = $request->post('qingmiao_table_name', '');
        $fieldName = $request->post('qingmiao_field_name', '');
        $field = $request->post('qingmiao_field', '');
        $hospitalName = $request->post('hospital_name', '');
        $hospitalField = $request->post('hospital_field', '');

        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);

        $where = [];
        if (!empty($tableName)) {
            $where[] = ['qingmiao_table_name', '=', $tableName];
        }
        if (!empty($fieldName)) {
            $where[] = ['qingmiao_field_name', '=', $fieldName];
        }
        if (!empty($field)) {
            $where[] = ['qingmiao_field', '=', $field];
        }
        if (!empty($hospitalName)) {
            $where[] = ['hospital_name', '=', $hospitalName];
        }

        if (!empty($hospitalField)) {
            $where[] = ['hospital_field', '=', $hospitalField];
        }


        $data = DataSource::query()
            ->where($where)
            ->paginate($pageSize, ['*'], 'page', $page)
            ->toArray();

        $returnData = [
            'list' => $data['data'] ?? [],
            'count' => $data['total'] ?? 0
        ];

        return ToolsService::returnAdmin(0, $returnData);
    }

    public function add(Request $request)
    {
        $tableName = $request->post('qingmiao_table_name', '');
        $fieldName = $request->post('qingmiao_field_name', '');
        $field = $request->post('qingmiao_field', '');
        $hospitalName = $request->post('hospital_name', '');
        $hospitalField = $request->post('hospital_field', '');

        $hospitalOne = $request->post('hospital_one', '');
        $hospitalTwo = $request->post('hospital_two', '');
        $hospitalThree = $request->post('hospital_three', '');

        $cnt = DataSource::query()->where('qingmiao_table_name', $tableName)
            ->where('qingmiao_field', $field)
            ->where('hospital_name', $hospitalName)
            ->where('hospital_field', $hospitalField)
            ->count();

        if ($cnt) {
            return ToolsService::returnAdmin(1, '', '数据已存在');
        }

        $data = [
            'qingmiao_table_name' => $tableName,
            'qingmiao_field_name' => $fieldName,
            'qingmiao_field' => $field,

            'hospital_name' => $hospitalName,
            'hospital_field' => $hospitalField,

            'hospital_one' => $hospitalOne,
            'hospital_two' => $hospitalTwo,
            'hospital_three' => $hospitalThree
        ];

        $state = DataSource::create($data);
        if ($state) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }

    public function info(Request $request)
    {
        $id = $request->get('id', 0);
        $info = DataSource::find($id);

        return ToolsService::returnAdmin(0, $info);

    }

    public function edit(Request $request)
    {

        $id = $request->post('id', '');
        $tableName = $request->post('qingmiao_table_name', '');
        $fieldName = $request->post('qingmiao_field_name', '');
        $field = $request->post('qingmiao_field', '');
        $hospitalName = $request->post('hospital_name', '');
        $hospitalField = $request->post('hospital_field', '');

        $hospitalOne = $request->post('hospital_one', '');
        $hospitalTwo = $request->post('hospital_two', '');
        $hospitalThree = $request->post('hospital_three', '');

        $cnt = DataSource::query()
            ->where('id', '<>', $id)
            ->where('qingmiao_table_name', $tableName)
            ->where('qingmiao_field', $field)
            ->where('hospital_name', $hospitalName)
            ->where('hospital_field', $hospitalField)
            ->count();

        if ($cnt) {
            return ToolsService::returnAdmin(1, '', '该数据已存在');
        }

        $data = [
            'qingmiao_table_name' => $tableName,
            'qingmiao_field_name' => $fieldName,
            'qingmiao_field' => $field,

            'hospital_name' => $hospitalName,
            'hospital_field' => $hospitalField,

            'hospital_one' => $hospitalOne,
            'hospital_two' => $hospitalTwo,
            'hospital_three' => $hospitalThree
        ];

        $state = DataSource::query()->where('id', $id)->update($data);
        if ($state) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }


    public function del(Request $request)
    {
        $adminIds = [1];
        $id = $request->post("id");
        if (!is_numeric($id)) {
            return ToolsService::returnAdmin(1, '管理员信息错误');
        }
        if (in_array($id, $adminIds)) {
            return ToolsService::returnAdmin(1, '', "此管理员不能删除");
        }

        $state = DataSource::query()->where(["id" => $id])->delete();
        if ($state) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }

    public function hospitalAdd(Request $request)
    {
        $name = $request->post('name');
        $cnt = DataSourceHospital::query()->where('name', $name)->count();

        if ($cnt) {
            return ToolsService::returnAdmin(1, '', '数据已存在');
        }

        DataSourceHospital::create(['name' => $name]);

        return ToolsService::returnAdmin(0, '', '操作成功');
    }


    public function hospitalList(Request $request)
    {

        $data = DataSourceHospital::query()->get();

        return ToolsService::returnAdmin(0, $data, '操作成功');
    }


    public function getOptions()
    {
        $list = DataSource::query()->get();

        $hospitals = DataSourceHospital::query()->get();
        $hospitalArr = [];
        foreach ($hospitals as $Item) {
            $hospitalArr[$Item->name] = $Item;
        }

        $data = [];
        foreach ($list as $k => $l) {
            //$data['table_name'][$k] = $l->qingmiao_table_name;
            if (empty($l->hospital_name)) {
                continue;
            }

            if (isset($hospitalArr[$l->hospital_name])) {
                //$data['hospital_name'][$hospitalArr[$l->hospital_name]['id']] = $l->hospital_name;

                $hospitalNames[$hospitalArr[$l->hospital_name]['id']] = $l->hospital_name;
            }
            // $data['hospital_field'][$l->hospital_field] = $l->hospital_field;
        }

        foreach ($hospitalNames as $hKey => $hVal) {
            $data['hospital_name'][] = ['id' => $hKey, 'name' => $hVal];
        }

        foreach ($list as $k => $l) {

            if (empty($l->hospital_name) || empty($l->hospital_field)) {
                continue;
            }

            if (isset($hospitalArr[$l->hospital_name])) {
                $data['hospital_field'][$hospitalArr[$l->hospital_name]['id']][] = $l->hospital_field;
            }

        }


        $dict = TableDict::all();

        $fieldArr = [];
        foreach ($dict as $di) {
            if ($di->type == 1) {
                $tableNames[$di->id] = $di->field;
            }

            if ($di->type == 2 && !empty($di->field_name)) {
                $fieldArr[$di->parent_field][] = ['field' => $di->field, 'field_name' => $di->field_name];
            }
        }


        foreach ($tableNames as $tk => $tb) {
            $data['table_name'][] = $tb;
            $i = 0;
            if (isset($fieldArr[$tk])) {
                foreach ($fieldArr[$tk] as $field) {
                    $data['table_detail'][$tb]['field_name'][$i] = $field['field_name'];
                    $data['table_detail'][$tb]['field'][$i] = $field['field'];
                    $i++;
                }

            }
        }


        return ToolsService::returnAdmin(0, $data, '操作成功');
    }

}
