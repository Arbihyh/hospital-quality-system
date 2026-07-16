<?php
/*
 * Created by PhpStorm
 * User: moquan
 * Date: 2023/2/9
 * Time: 17:54
 */

namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use App\Model\OperationRelations;
use App\Services\ToolsService;
use App\Services\OperationRelationsService;
use Illuminate\Http\Request;

class OperationController extends Controller
{
    public function relationsList(Request $request)
    {
        $page = $request->post("page");
        if (!is_numeric($page)) {
            $page = 1;
        }
        $length = $request->post("length");
        if (!is_numeric($length)) {
            $length = 16;
        }

        $name = $request->post("name");
        $operationName = $request->post("operation_name");
        $code = $request->post("code");
        $data = OperationRelationsService::relationsList($operationName, $name, $code, $page, $length);
        return ToolsService::returnAdmin(0, $data);
    }

    public function addOperationRelation(Request $request)
    {
        $feeName = $request->post("fee_name");
        if (empty($feeName)) {
            return ToolsService::returnAdmin(401, [], '项目名称不能为空');
        }
        $operationName = $request->post("operation_name");
        if (empty($operationName)) {
            return ToolsService::returnAdmin(401, [], '手术名称不能为空');
        }
        $code = $request->post("code");
        if (empty($code)) {
            return ToolsService::returnAdmin(401, [], '手术代码不能为空');
        }
        $feeUnit = $request->post("fee_unit");
        if (!$feeUnit) {
            return ToolsService::returnAdmin(401, [], '计费单位不能为空');
        }

        $price = $request->post("price");
        if (empty($price)) {
            return ToolsService::returnAdmin(401, [], '价格不能为空');
        }
        $state = OperationRelationsService::addOperationRelations($feeName, $operationName, $code, $price, $feeUnit);
        if ($state) {
            return ToolsService::returnAdmin(0);
        } else {
            return ToolsService::returnAdmin(500);
        }
    }

    public function editOperationRelation(Request $request)
    {
        $id = $request->post("id");
        if (empty($id) || !is_numeric($id)) {
            return ToolsService::returnAdmin(401, [], 'ID不能为空');
        }
        $feeName = $request->post("fee_name");
        if (empty($feeName)) {
            return ToolsService::returnAdmin(401, [], '项目名称不能为空');
        }
        $operationName = $request->post("operation_name");
        if (empty($operationName)) {
            return ToolsService::returnAdmin(401, [], '手术名称不能为空');
        }
        $code = $request->post("code");
        if (empty($code)) {
            return ToolsService::returnAdmin(401, [], '手术代码不能为空');
        }
        $feeUnit = $request->post("fee_unit");
        if (!$feeUnit) {
            return ToolsService::returnAdmin(401, [], '计费单位不能为空');
        }
        $price = $request->post("price");
        if (empty($price)) {
            return ToolsService::returnAdmin(401, [], '价格不能为空');
        }
        $state = OperationRelationsService::editOperationRelationsById($id, $feeName, $operationName, $code, $price, $feeUnit);
        if ($state) {
            return ToolsService::returnAdmin(0);
        } else {
            return ToolsService::returnAdmin(500);
        }
    }

    public function delOperationRelation(Request $request)
    {
        $id = $request->post("id");
        if(!is_numeric($id)){
            return ToolsService::returnAdmin(401);
        }
        $state = OperationRelationsService::delOperationRelationsById($id);
        if ($state) {
            return ToolsService::returnAdmin(0);
        } else {
            return ToolsService::returnAdmin(500);
        }
    }

    //更新状态
    public function updateStatus(Request $request)
    {
        $id = $request->post("id");
        if(!is_numeric($id)){
            return ToolsService::returnAdmin(401);
        }
        $status = $request->post("status");
        if(!in_array($status, [0,1])){
            return ToolsService::returnAdmin(401, [], 'status is failed');
        }
        $state = OperationRelationsService::updateStatusById($id, $status);
        if ($state) {
            return ToolsService::returnAdmin(0, [], 'success');
        } else {
            return ToolsService::returnAdmin(500, [], 'updated failed');
        }
    }
}
