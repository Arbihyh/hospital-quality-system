<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use App\Model\CaseRule;
use Illuminate\Support\Facades\DB;

class CaseController extends Controller
{
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
        $title = $request->post('title');
        $notice = $request->post('notice');
        $where['title'] = $title;
        $where['notice'] = $notice;


        try {
            $rule = [];
            $count = $caseRule::getCount($where);
            if ($count) {
                $rule = $caseRule::getList($page, $page_size, ['*'], $where);
            }
            $data = ['count'=>$count, 'list'=>$rule];
            $code = 0;
        } catch (\Exception $e) {
            $code = 401;
            $msg = $e->getMessage();
        }
        return ToolsService::returnAdmin($code, $data ?? [], $msg ?? '');
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
        if (empty($title)) {
            $msg = '质控项不能为空';
        } elseif (empty($rule)) {
            $msg = '规则内容不能为空';
        } elseif (empty($notice)) {
            $msg = '提示内容不能为空';
        }
        if (!empty($msg)) {
            return ToolsService::returnData(4001, $rule, $msg ?? '');
        }
        $addData = ['title' => $title, 'rule' => $rule, 'notice' => $notice];
        if (!$id) {
            $res = DB::table('case_rule')->insert($addData);
        } else {
            $res = DB::table('case_rule')->where('id', $id)->update($addData);
        }
        $code = 200;
        if (!$res) {
            $code = 4001;
        }
        return ToolsService::returnAdmin($code, [], $msg ?? '');
    }

}
