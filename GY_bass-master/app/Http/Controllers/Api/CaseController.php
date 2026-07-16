<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CaseService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use App\Model\CaseRule;

class CaseController extends Controller
{
    /**
     * @param Request $request
     * @param CaseService $caseService
     * @return array
     */
    public function getList(Request $request, CaseService $caseService)
    {
        $id = $request->post('id');
        if (!$id) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        $res = [];
        try {
            $res = $caseService->getCaseDetail($id);
            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnData($code, $res, $msg ?? '');
    }

    /**
     * @param Request $request
     * @param CaseService $caseService
     * @return array
     * 获取格式化病例内容
     */
    public function getCasePlatform(Request $request, CaseService $caseService)
    {
        $id = $request->post('id');
        $bllb = $request->post('bllb', 1);
        if (!$id) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        $res = [];
        try {
            $res = $caseService->getCasePlatform($id, $bllb);
            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnData($code, $res, $msg ?? '');
    }

    /**
     * @param Request $request
     * @param CaseRule $caseRule
     * @return array
     * 获取规则列表
     */
    public function getCaseRule(Request $request, CaseRule $caseRule)
    {


        $page = $request->post('page', 1);
        $page_size = $request->post('page_size', 20);
        $rule = [];
        try {
            $rule = $caseRule::getList($page, $page_size);
            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }

        return ToolsService::returnData($code, $rule ?? [], $msg ?? '');
    }

    /**
     * @param Request $request
     * @param CaseRule $caseRule
     * @return array
     * 添加规则
     */
    public function addCaseRule(Request $request, CaseRule $caseRule)
    {

        $id = $request->post('id', '');
        $title = $request->post('title', '');
        $notice = $request->post('notice', '');
        $rule = $request->post('rule', '');

        $msg = '';
        if(empty($title)){
            $msg = '质控项不能为空';
        }elseif(empty($rule)){
            $msg = '规则内容不能为空';
        }elseif(empty($notice)){
            $msg = '提示内容不能为空';
        }
        if(!empty($msg)){
            return ToolsService::returnData(4001, $rule, $msg ?? '');
        }

        $addData = ['title'=>$title,'rule'=>$rule, 'notice'=>$notice];

        try {
            $res = CaseRule::query()->insert($addData);
            $code = 200;
            if(!$res){
                $code = 4001;
            }
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnData($code, [], $msg ?? '');
    }

    /**
     * 获取手术格式化数据
     * @param Request $request
     * @return array
     */
    public function getSurgeryData(Request $request)
    {
        $blbh = $request->post('blbh');
        if (!$blbh) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        $res = [];
        try {
            $caseService = new CaseService();
            $res = $caseService->getSurgeryData($blbh, 303);
            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnData($code, $res, $msg ?? '');
    }

    /**
     * 获取病程格式化数据
     * @param Request $request
     * @return array
     */
    public function getBcData(Request $request)
    {
        $blbh = $request->post('blbh');
        if (!$blbh) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        $res = [];
        try {
            $caseService = new CaseService();
            $res = $caseService->getBcData($blbh, 294);
            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnData($code, $res, $msg ?? '');
    }

}
