<?php

namespace App\Http\Controllers\Api;

use App\Model\TkMenu;

use App\Model\ZY_BRRY;
use App\Model\Department;
use App\Model\CaseQuality;
use Illuminate\Http\Request;
use App\Services\ToolsService;
use App\Model\QualitySendMsgLog;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Model\RuleWordMap;
use Illuminate\Support\Facades\Session;


class Tk extends Controller
{


    public function getMenu(Request $request)
    {
        $re = TkMenu::query()->where('is_show', 1)->get()->toArray();
        return ToolsService::returnData(200, $re, $msg ?? '');
    }

    public function getDepCaseQuality(Request $request)
    {

        $token = $request->header('token');
        $departmentId = Session::get($token . '_department_id');
        
        if (empty($departmentId)) {
            $zyh = $token = $request->get("zyh");
            if (empty($zyh)) {
                return ToolsService::returnData(200, [
                    'total' => 0,
                    'list' => []
                ], '');
            }
            $departmentId = ZY_BRRY::query()->where('ZYH', $zyh)->value('brks');
            if (empty($departmentId)) {
                return ToolsService::returnData(200, [
                    'total' => 0,
                    'list' => []
                ], '');
            }
            Session::put($token . '_department_id', $departmentId);
        }

        $case_quality = CaseQuality::query()
            ->join('ZY_BRRY', 'case_quality.JZHM', '=', 'ZY_BRRY.ZYH')
            ->where('ZY_BRRY.brks', $departmentId)
            ->where(function ($query) {
                $query->where('ZY_BRRY.AAC01', '=', "")->orWhere('ZY_BRRY.AAC01', '=', "0000-00-00 00:00:00")->orWhere('ZY_BRRY.AAC01', '>', date("Y-m-d 00:00:00"));
            })
            ->select('case_quality.JZHM', DB::raw('count(*) as quality_issue_count'))
            ->groupBy('case_quality.JZHM')
            ->orderBy('ZY_BRRY.score', 'desc')
            ->get()->toArray(); 

        $zy_brry = ZY_BRRY::query()
            ->whereIn('ZYH', array_column($case_quality, 'JZHM'))
            ->get(['ZYH', 'AAA28', 'CH', 'BRXM'])->toArray();
        $zy_brry = array_column($zy_brry, null, 'ZYH');

        foreach ($case_quality as $key => &$value) {
            $value['ZYH'] = $zy_brry[$value['JZHM']]['ZYH'] ?? '';
            $value['AAA28'] = $zy_brry[$value['JZHM']]['AAA28'] ?? '';
            $value['CH'] = $zy_brry[$value['JZHM']]['CH'] ?? '';
            $value['BRXM'] = $zy_brry[$value['JZHM']]['BRXM'] ?? '';
        }

        // 返回患者信息和对应的质控问题数量
        return ToolsService::returnData(200, [
            'total' => count($case_quality),
            'list' => $case_quality
        ], '');


    }

    public function getMsgCount(Request $request)
    {
        $token = $request->header('token');
        $departmentId = Session::get($token . '_department_id');
        
        if (empty($departmentId)) {
            $zyh = $token = $request->get("zyh");
            if (empty($zyh)) {
                return ToolsService::returnData(200, [
                    'read_count' => 0,
                    'unread_count' => 0
                ], '');
            }
            $departmentId = ZY_BRRY::query()->where('ZYH', $zyh)->value('brks');
            if (empty($departmentId)) {
                return ToolsService::returnData(200, [
                    'read_count' => 0,
                    'unread_count' => 0
                ], '');
            }
            Session::put($token . '_department_id', $departmentId);
        }

        if (empty($departmentId)) {
            return ToolsService::returnData(200, [
                'read_count' => 0,
                'unread_count' => 0
            ], '');
        }

        // 获取科室下所有消息
        $query = QualitySendMsgLog::query()
            ->join('ZY_BRRY', 'quality_send_msg_log.ZYH', '=', 'ZY_BRRY.ZYH')
            ->where('ZY_BRRY.brks', $departmentId);

        $readCount = (clone $query)->where('quality_send_msg_log.is_read', 1)->count();
        $unreadCount = (clone $query)->where('quality_send_msg_log.is_read', 0)->count();

        return ToolsService::returnData(200, [
            'read_count' => $readCount,
            'unread_count' => $unreadCount
        ], '');
    }

    public function getMsg(Request $request)
    {
        $token = $request->header('token');
        $departmentId = Session::get($token . '_department_id');
        
        if (empty($departmentId)) {
            $zyh = $token = $request->get("zyh");
            if (empty($zyh)) {
                return ToolsService::returnData(4001, '住院号不能为空', $msg ?? '');
            }
            $departmentId = ZY_BRRY::query()->where('ZYH', $zyh)->value('brks');
            if (empty($departmentId)) {
                return ToolsService::returnData(200, [
                    'total' => 0,
                    'list' => []
                ], '');
            }
            Session::put($token . '_department_id', $departmentId);
        }

        $params = $request->post();
        $page = $params['page'] ?? 1;
        $pageSize = $params['page_size'] ?? 10;
        $isRead = $params['is_read'] ?? null;

        $field = ['quality_send_msg_log.*','ZY_BRRY.BRXM','ZY_BRRY.CH','ZY_BRRY.BRKS'];
        $map9029 = RuleWordMap::query()->where('id', 9029)->value('keyword');
        $map9029Arr = explode(',', $map9029);
        foreach ($map9029Arr as $key => $value) {
            $field[] = "ZY_BRRY.{$value}";
        }
        
        $list = QualitySendMsgLog::query()
            ->join('ZY_BRRY', 'quality_send_msg_log.ZYH', '=', 'ZY_BRRY.ZYH')
            ->where('ZY_BRRY.brks', $departmentId)
            ->when(is_numeric($isRead) && $isRead == 1, function ($query) {
                return $query->where('quality_send_msg_log.is_read', 1);
            })
            ->when(is_numeric($isRead) && $isRead == 0, function ($query) {
                return $query->where('quality_send_msg_log.is_read', 0);
            })
            ->select($field)
            ->orderBy('quality_send_msg_log.created_at', 'desc')
            ->paginate($pageSize, ['*'], 'page', $page)
            ->toArray();

        $data = [];

        $department = Department::query()->pluck('dep_name', 'dep_id')->toArray();


        foreach ($list['data'] as $key => $value) {
            $sedName = '';
            if (in_array($value['BRKS'], $map9029Arr)) {
                $sedName = $value['BRKS'];
            } else {
                $sedName = $department[$value['BRKS']];
            }
            $msg = "尊敬的{$sedName}：
            您好！您负责的病历{$value['AAA28']}(" . $value['BRXM'] . " · " . $value['CH'] . ")，{$value['content']}，谢谢！
            祝您工作顺利！";
            $data[] = [
                'id' => $value['id'],
                'msg' => $msg,
                'create_time' => $value['push_time'],
                'ZYH' => $value['zyh'],
                'is_read' => $value['is_read'],
            ];
        }

        return ToolsService::returnData(200, [
            'total' => $list['total'],
            'list' => $data
        ], '');
    }

    public function readMsg(Request $request)
    {
        $params = $request->post();
        $id = $params['id'] ?? '';

        QualitySendMsgLog::query()->where('id', $id)->update(['is_read' => 1]);
        return ToolsService::returnData(200, [], '');
    }

    public function readAllMsg(Request $request)
    {
        $token = $request->header('token');
        $departmentId = Session::get($token . '_department_id');
        
        
        if (empty($departmentId)) {
            $zyh = $token = $request->get("zyh");
            if (empty($zyh)) {
                return ToolsService::returnData(200, [], '');
            }
            $departmentId = ZY_BRRY::query()->where('ZYH', $zyh)->value('brks');
            if (empty($departmentId)) {
                return ToolsService::returnData(200, [], '');
            }
            Session::put($token . '_department_id', $departmentId);
        }
        QualitySendMsgLog::query()
            ->join('ZY_BRRY', 'quality_send_msg_log.ZYH', '=', 'ZY_BRRY.ZYH')
            ->where('ZY_BRRY.brks', $departmentId)
            ->update(['is_read' => 1]);
        return ToolsService::returnData(200, [], '');
    }

    public function isKnow(Request $request)
    {
        $params = $request->post(); 
        $zyh = $params['zyh'] ?? '';
        $ruleId = $params['rule_id'] ?? '';

        CaseQuality::query()->where('JZHM', $zyh)->where('rule_id', $ruleId)->update(['is_know' => 1]);
        return ToolsService::returnData(200,'');
    }
}
