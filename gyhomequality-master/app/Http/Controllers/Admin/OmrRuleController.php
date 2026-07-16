<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\OmrRule;
use App\Services\ToolsService;
use Illuminate\Http\Request;

class OmrRuleController extends Controller
{
    public function getCategory()
    {
        $categoryList = OmrRule::query()->groupBy('category')->pluck('category')->toArray();

        return ToolsService::returnAdmin(0, $categoryList);
    }

    /**
     * 列表
     * @param Request $request
     * @return array
     */
    public function ruleList(Request $request)
    {
        $category = $request->post('category','');
        $title = $request->post('title','');
        $notice = $request->post('notice','');
        $status = $request->post('status','');
        $score = $request->post('score','');
        $page = $request->post('page',1);
        $pageSize = $request->post('page_size',10);

        $where = [];
        if ($category) {
            $where[] = ['category','=',$category];
        }
        if ($title) {
            $where[] = ['title',"like","%".$title."%"];
        }
        if ($notice) {
            $where[] = ['category',"like","%".$notice."%"];
        }
        if ($status !== '' && $status !== null) {
            $where[] = ['status','=',$status];
        }
        if ($score) {
            $where[] = ['score','=',$score];
        }

        $data = OmrRule::query()->where($where)->paginate($pageSize,['*'],'page',$page)->toArray();

        $returnData = [
            'list' => $data['data'] ?? [],
            'count' => $data['total'] ?? 0
        ];

        return ToolsService::returnAdmin(0, $returnData);
    }

    public function ruleInfo(Request $request)
    {
        $id = $request->post('id',1);
        if (!$id) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }

        $omrRuleInfo = OmrRule::query()->find($id);
        if ($omrRuleInfo) {
            return ToolsService::returnAdmin(0, $omrRuleInfo->toArray());
        }

        return ToolsService::returnAdmin(0, []);
    }

    /**
     * 添加
     * @param Request $request
     * @return array
     */
    public function ruleAdd(Request $request)
    {
        $category = $request->post('category','');
        $title = $request->post('title','');
        $notice = $request->post('notice','');
        $score = $request->post('score','');

        if (!$category || !$title || !$score || !$notice) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }

        $ruleInfo = OmrRule::query()->where(['category'=>$category,'title'=>$title])->first();
        if ($ruleInfo) {
            return ToolsService::returnAdmin(1, '', '质控规则已存在');
        }

        $insertData = [
            'category' => $category,
            'title' => $title,
            'notice' => $notice,
            'score' => $score,
        ];
        $res = OmrRule::query()->insert($insertData);
        if ($res) {
            return ToolsService::returnAdmin(0, [], '操作成功');
        }

        return ToolsService::returnAdmin(1, '', '操作失败');
    }

    /**
     * 编辑
     * @param Request $request
     * @return array
     */
    public function ruleSave(Request $request)
    {
        $id = $request->post('id','');
        $category = $request->post('category','');
        $title = $request->post('title','');
        $notice = $request->post('notice','');
        $score = $request->post('score','');

        if (!$id || !$category || !$title || !$score || !$notice) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }

        $where = [
            ['id','!=',$id],
            ['category','=',$category],
            ['title','=',$title]
        ];
        $ruleInfo = OmrRule::query()->where($where)->first();
        if ($ruleInfo) {
            return ToolsService::returnAdmin(1, '', '质控规则已存在');
        }

        $saveData = [
            'category' => $category,
            'title' => $title,
            'notice' => $notice,
            'score' => $score,
        ];
        $res = OmrRule::query()->where('id','=',$id)->update($saveData);
        if ($res) {
            return ToolsService::returnAdmin(0, [], '操作成功');
        }

        return ToolsService::returnAdmin(1, '', '操作失败');
    }

    /**
     * 修改状态
     * @param Request $request
     * @return array
     */
    public function ruleSaveStatus(Request $request)
    {
        $id = $request->post('id','');
        $status = $request->post('status','');

        if (!$id || $status==='') {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }

        $res = OmrRule::query()->where('id','=',$id)->update(['status'=>$status]);
        if ($res) {
            return ToolsService::returnAdmin(0, [], '操作成功');
        }

        return ToolsService::returnAdmin(1, '', '操作失败');
    }


}
