<?php

namespace App\Http\Controllers\Api;

use App\Console\Commands\ModelList;
use App\Http\Controllers\Controller;
use App\Model\BigModelList;
use App\Model\BigModelTemplate;
use App\Model\BigModelTaskName;
use App\Model\Department;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\ModelConfig;
use App\Model\QualitySendMsgLog;
use App\Model\RuleWordMap;
use App\Model\User;
use App\Model\Staff;
use App\Model\Yzb;
use App\Services\BlDataFormatService;
use App\Services\CaseService;
use App\Services\CsvService;
use App\Services\EsSaveService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use App\Model\CaseRule;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Model\CaseQualityCount;

class CaseController extends Controller
{
    /**
     * @param Request $request
     * @return array
     * 检查是否有新的质控结果
     */
    public function getQualityHasNewResult(Request $request)
    {
        $json = file_get_contents('php://input');

        // 检测并移除UTF-8 BOM标记 (EF BB BF)
        if (strpos($json, "\xEF\xBB\xBF") === 0) {
            $json = substr($json, 3);
        }
        Log::info("getQualityHasNewResult：" . $json);

        // 尝试解析JSON
        $content = json_decode(str_replace("\\", '', $json), true);

        // 检查解析是否成功
        if ($content === null && json_last_error() !== JSON_ERROR_NONE) {
            // 解析失败，尝试直接解析（不移除反斜杠）
            $content = json_decode($request->post(), true);

            // 如果仍然失败，记录错误并返回错误响应
            if ($content === null) {
                return ToolsService::returnData(4001, [], '请求的参数格式有误: ' . json_last_error_msg());
            }
        }
        $zyh = $content['ZYH'] ?? "";
        $count = CaseQualityCount::query()->where('ZYH', $zyh)->where('is_viewed', 0)->count();
        return ToolsService::returnData(200, ['zkjg' => $count > 0 ? 1 : 0], 'success');
    }

    /**
     * @param Request $request
     * @return array
     * 检查指定科室指定日期是否有需要弹框的结果
     */
    public function getQualityHasResult(Request $request)
    {
        $json = file_get_contents('php://input');

        // 检测并移除UTF-8 BOM标记 (EF BB BF)
        if (strpos($json, "\xEF\xBB\xBF") === 0) {
            $json = substr($json, 3);
        }
        Log::info("getQualityHasResult：" . $json);

        // 尝试解析JSON
        $content = json_decode(str_replace("\\", '', $json), true);

        // 检查解析是否成功
        if ($content === null && json_last_error() !== JSON_ERROR_NONE) {
            // 解析失败，尝试直接解析（不移除反斜杠）
            $content = json_decode($request->post(), true);

            // 如果仍然失败，记录错误并返回错误响应
            if ($content === null) {
                return ToolsService::returnData(4001, [], '请求的参数格式有误: ' . json_last_error_msg());
            }
        }
        $dep_id = $content['KSDM'] ?? "";
        // 查找is_viewed 为0的数据，并且BRKS为$dep_id，quality_date为昨日年月日格式
        $quality_date = date('Y-m-d', strtotime("-1 day"));
        
        $count = CaseQualityCount::query()->where('is_viewed', 0)
        ->join('patient_info', 'patient_info.MED_REC_ID', '=', 'case_quality_count.ZYH')
        ->where('case_quality_count.BRKS', $dep_id)
        ->where(function ($query) {
            $query->where('patient_info.AAC01', '=', "")->orWhere('patient_info.AAC01', '>', date("Y-m-d 00:00:00"));
        })
        ->where('case_quality_count.quality_date', $quality_date)->count();
        // 如果res不为空，则返回1，否则返回0
        if ($count > 0) {
            return ToolsService::returnData(200, ['sftc' => 1], '有需要弹框的结果');
        }
        return ToolsService::returnData(200, ['sftc' => 0], '没有需要弹框的结果');
    }

    /**
     * @param Request $request
     * @return array
     * 获取质控结果列表
     */
    public function getCaseQualityCount(Request $request)
    {
        $dep_id = $request->get('KSDM', '');
        $quality_date = date('Y-m-d', strtotime("-1 day"));
        
        $res = CaseQualityCount::query()
        ->join('patient_info', 'patient_info.MED_REC_ID', '=', 'case_quality_count.ZYH')
        ->where('case_quality_count.BRKS', $dep_id)
        ->where(function ($query) {
            $query->where('patient_info.AAC01', '=', "")->orwhere('patient_info.AAC01', '=', "0000-00-00 00:00:00")->orWhere('patient_info.AAC01', '>', date("Y-m-d 00:00:00"));
        })
        ->where('case_quality_count.quality_date', $quality_date)
        ->get(['case_quality_count.is_viewed', 'case_quality_count.CH', 'case_quality_count.BRXM', 'case_quality_count.updated_at as quality_date', 'case_quality_count.BRKS', 'patient_info.AAA28 as ZYH', 'patient_info.AAC01'])
        ->toArray();

        foreach($res as $key => $item){
            $res[$key]['viewed_status'] = $item['is_viewed'] == 1 ? '已查看' : '未查看';
            $res[$key]['ZYZT'] = '已出院';
            if($item['AAC01'] == "0000-00-00 00:00:00" or empty($item['AAC01'])){
                $res[$key]['ZYZT'] = '未出院';
            }
        }
        return ToolsService::returnData(200, $res, $msg ?? '');
    }
    /**
     * @param Request $request
     * @return array
     *
     * 获取AI大模型需要的配置信息
     */
    public function getAiInfo(Request $request)
    {
        $modelConfig = ModelConfig::query()->where('id', 2)->get()->toArray();
        return ToolsService::returnData(200, !empty($modelConfig[0]) ? $modelConfig[0] : [], 'success');

    }
    /**
     * @param Request $request
     * @return array
     *
     * 修改痕迹内容
     */
    public function editHJNR(Request $request)
    {
        $blbh = $request->post('blbh', "");
        $hjnr = $request->post('hjnr', "");
        if (empty($blbh) || empty($hjnr)) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }

        // 判断病历编号是否存在
        $bl01 = EMR_BL_BL01::query()->where("BLBH", $blbh)->first();
        if (empty($bl01)) {
            return ToolsService::returnData(4001, [], '病历编号不存在');
        }
        $bl01 = $bl01->toArray();

        // 修改病程内容
        EMR_BL_BLXG::query()->where("BLBH", $blbh)->update(["HJNR" => $hjnr]);

        // 格式化
        $bldf = new BlDataFormatService();
        $bl01["HJNR"] = $hjnr;
        $bldf->insertData([], $bl01["MBLB"], $bl01);

        // 同步ES
        EsSaveService::bl01($bl01['JZHM']);

        return ToolsService::returnData(200, [], '修改成功');

    }
    /**
     * 申诉
     */
    public function appeal(Request $request, CaseService $caseService)
    {
        $id = $request->post('id', 0);
        $zyh = $request->post('zyh', 0);
        $type = $request->post('type', 0);
        $qualityType = $request->post('quality_type', 0);
        $defectContent = $request->post('defect_content', "");
        $appealDocter = $request->post('appeal_docter', "");
        if (!$id || !$zyh || !$type || !in_array($qualityType, [1, 2, 3])) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        return $caseService->appeal($id, $zyh, $type, $qualityType, $defectContent, $appealDocter);

    }

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
     */
    public function getCaseQuality(Request $request)
    {
        $id = $request->get('id');
        $ruleId = $request->get('ruleId');
        $showCorrection = $request->post('show_correction', 0) or 0; // 是否返回整改数据
        if (!$id) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        $caseService = new CaseService();
        $res = $caseService->getCaseQualityZm($id, $showCorrection);
        if (!empty($res['data'])) {
            $rulekey = 0;
            foreach ($res['data'] as $key => $item) {
                if ($item['rule_id'] == $ruleId) {
                    $rulekey = $key;
                }
            }
            if ($rulekey != 0 && isset($res['data'][$rulekey])) {
                $info = $res['data'][$rulekey];
                $res['data'][$rulekey] = $res['data'][0];
                $res['data'][0] = $info;
            }
        }

        $code = 200;
        //log res
        //Log::info('res', $res);
        $result = json_decode(json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), true);
        //Log::info('result after json_decode/encode', $result ? ['result' => 'not empty'] : ['result' => 'empty']);
        return ToolsService::returnData($code, $result, $msg ?? '');
    }

    /**
     * @param Request $request
     * @param CaseService $caseService
     * @return array
     */
    public function getCaseQualityV2(Request $request)
    {
        $id = $request->post('id');
        $ruleId = $request->post('ruleId');
        $source = $request->post('source', '');
        $showCorrection = $request->post('show_correction', 0) or 0; // 是否返回整改数据
        if (!$id) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        $res = [];
        $req = 0;
        try {
            $caseService = new CaseService();
            $res = $caseService->getCaseQuality($id, $showCorrection, $source);
            
            if (!empty($res['data'])) {
                $now = date('Y-m-d H:i:s');
                $ruleIds = array_values(array_unique(array_filter(array_map(function ($item) {
                    return isset($item['rule_id']) ? (int)$item['rule_id'] : 0;
                }, $res['data']))));
                $hasTransferOrder = $this->hasShizhongTransferOrder($id);
                $latestUnlockRecords = $this->getShizhongLatestUnlockRecords($id, $ruleIds);
                $unlockCountMap = $this->getShizhongUnlockCountMap($id, $ruleIds);
                $rulekey = 0;
                foreach ($res['data'] as $key => $item) {
                    $itemRuleId = isset($item['rule_id']) ? (int)$item['rule_id'] : 0;
                    $isRequiredRule = isset($item['level']) && (int)$item['level'] === 1;
                    $latestUnlockRecord = $latestUnlockRecords[$itemRuleId] ?? null;
                    $isUnlocked = !empty($latestUnlockRecord) && $latestUnlockRecord->expire_time > $now;
                    $unlockCount = $unlockCountMap[$itemRuleId] ?? 0;
                    $canUnlock = $isUnlocked || ($isRequiredRule && $hasTransferOrder && $unlockCount < 3);

                    $res['data'][$key]['can_unlock'] = $canUnlock ? 1 : 0;
                    if ($isUnlocked) {
                        $res['data'][$key]['unlock_status'] = '已解锁';
                        $res['data'][$key]['apply_time'] = $latestUnlockRecord->apply_time;
                        $res['data'][$key]['expire_time'] = $latestUnlockRecord->expire_time;
                        $res['data'][$key]['unlock_applicant'] = $latestUnlockRecord->unlock_applicant;
                        $res['data'][$key]['unlock_reason'] = $latestUnlockRecord->unlock_reason;
                    } else if ($canUnlock) {
                        $res['data'][$key]['unlock_status'] = '未解锁';
                    }

                    if ($isRequiredRule && !$isUnlocked) {
                        $req++;
                    }
                    if ($item['rule_id'] == $ruleId) {
                        $rulekey = $key;
                    }
                }
                if ($rulekey != 0) {
                    $info = $res['data'][$rulekey];
                    $res['data'][$rulekey] = $res['data'][0];
                    $res['data'][0] = $info;
                }
            }
            $code = 200;
            $res['req'] = $req;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage() . $e->getFile() . $e->getLine();
        }
        $result = json_decode(json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), true);
        return ToolsService::returnData($code, $result, $msg ?? '');
    }

    /**
     * 判断当前住院号是否存在转科/转出医嘱。
     *
     * @param string $zyh
     * @return bool
     */
    private function hasShizhongTransferOrder($zyh)
    {
        $ruleMap8035 = RuleWordMap::query()->where('id', '=', 20077)->value('keyword');
        $ruleMap8035 = !empty($ruleMap8035) ? $ruleMap8035 : '转出,转科';
        $keywords = strpos($ruleMap8035, ',') !== false ? explode(',', $ruleMap8035) : [$ruleMap8035];
        $keywords = array_filter(array_map('trim', $keywords));
        if (empty($keywords)) {
            return false;
        }

        try {
            return Yzb::query()
                ->where('ZYH', '=', $zyh)
                ->where(function ($query) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $query->orWhere('YZMC', 'like', '%' . $keyword . '%');
                    }
                })
                ->where('YZMC', 'not like', '%好转%')
                ->orderBy('KZSJ', 'desc')
                ->exists();
        } catch (\Exception $e) {
            Log::error('getCaseQualityV2-transfer-order-error: ' . $e->getMessage(), ['zyh' => $zyh]);
            return false;
        }
    }

    /**
     * 获取当前住院号下每个规则最新解锁记录。
     *
     * @param string $zyh
     * @param array $ruleIds
     * @return array
     */
    private function getShizhongLatestUnlockRecords($zyh, array $ruleIds)
    {
        $ruleIds = array_values(array_unique(array_filter(array_map('intval', $ruleIds))));
        if (empty($ruleIds)) {
            return [];
        }

        $records = DB::table('case_quality_shizhong_unlock_records')
            ->where('zyh', '=', $zyh)
            ->whereIn('rule_id', $ruleIds)
            ->orderBy('apply_time', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $map = [];
        foreach ($records as $record) {
            $ruleId = (int)$record->rule_id;
            if (!isset($map[$ruleId])) {
                $map[$ruleId] = $record;
            }
        }

        return $map;
    }

    /**
     * 获取当前住院号下每个规则累计解锁次数。
     *
     * @param string $zyh
     * @param array $ruleIds
     * @return array
     */
    private function getShizhongUnlockCountMap($zyh, array $ruleIds)
    {
        $ruleIds = array_values(array_unique(array_filter(array_map('intval', $ruleIds))));
        if (empty($ruleIds)) {
            return [];
        }

        $rows = DB::table('case_quality_shizhong_unlock_records')
            ->select('rule_id')
            ->selectRaw('COUNT(1) AS unlock_count')
            ->where('zyh', '=', $zyh)
            ->whereIn('rule_id', $ruleIds)
            ->groupBy('rule_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row->rule_id] = (int)$row->unlock_count;
        }

        return $map;
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
        $isHight = $request->post('isHight', 0);
        $keyWord = $request->post('keyWord', '');
        if (!$id) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        $res = $caseService->getCasePlatform($id, $bllb, $isHight, $keyWord);
        $code = 200;
        //Malformed UTF-8 characters, possibly incorrectly encoded
        $result = json_decode(json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), true);
        return ToolsService::returnData($code, $result, $msg ?? '');
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

        try {
            $res = CaseRule::query()->insert($addData);
            $code = 200;
            if (!$res) {
                $code = 4001;
            }
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnData($code, [], $msg ?? '');
    }


    public function warningMsg(Request $request)
    {
        $map['quality_start_time'] = $request->get('quality_start_time', '');
        $map['quality_end_time'] = $request->get('quality_end_time', '');
        $map['aac01_start_time'] = $request->get('aac01_start_time', '');
        $map['aac01_end_time'] = $request->get('aac01_end_time', '');
        $map['department'] = $request->get('department', '');
        $map['aab01_start_time'] = $request->get('aab01_start_time', '');
        $map['aab01_end_time'] = $request->get('aab01_end_time', '');
        $map['content'] = $request->get('content', '');
        $map['AAA28'] = $request->get('AAA28', '');
        $map['status'] = $request->get('status', 0);
        $map['is_delete'] = $request->get('is_delete', 0);
        $map['export'] = $request->get('export', 0);
        $map["is_warning_msg"] = 1; // 只获取预警消息
        $page = $request->get('page', 1);
        $pageSize = $request->get('page_size', 10);

        $token = $request->header('token');
        $user = User::query()->where("token", "=", $token)->get()->toArray();
        $depId = json_decode($user[0]['dep_id'], true);

        $white = RuleWordMap::getFirstById(4001);

        // 超管可以查看所有部门
        $dep = [];
        if (array_intersect($depId, $white)) {
            $dep = Department::query()->get(['dep_id', 'dep_name'])->toArray();
        } elseif (!empty($depId)) {
            // 非管理员只能看关联的部门
            $dep = Department::query()->whereIn("dep_id", $depId)->get(['dep_id', 'dep_name'])->toArray();
        }
        if (empty($dep)) {
            return ToolsService::returnData(200, ['count' => 0, 'list' => []]);
        }


        // 如果当前登录人的所属部门不在指定的查看所有预警信息的科室信息中，则只能查看指定部门的信息
        if (empty($map['department']) and $user[0]['group_id'] != 1) {
            $map['department'] = array_column($dep, "dep_name");
        }

        $count = QualitySendMsgLog::getCount($map);
        if (!$count) {
            return ToolsService::returnData(200, ['count' => 0, 'list' => []]);
        }

        $list = QualitySendMsgLog::getList($page, $pageSize, [
            'quality_send_msg_log.zyh',
            'quality_send_msg_log.quality_content',
            'quality_send_msg_log.msg_yj',
            'quality_send_msg_log.status',
            'ZY_BRRY.AAA28',
            'ZY_BRRY.BRXM AS AAA01',
            'quality_send_msg_log.AAC11N',
            'quality_send_msg_log.AEE03',
            'quality_send_msg_log.AEE01',
            'quality_send_msg_log.push_time',
            'quality_send_msg_log.is_delete',
            'quality_send_msg_log.data_type',
            'quality_send_msg_log.data_id',
            'quality_send_msg_log.is_youxiao',
            'ZY_BRRY.AAB01',
            'ZY_BRRY.AAC01',
            'ZY_BRRY.ZY_KSMC as AAC11N',
            'ZY_BRRY.ZZYSMC as AEE03',
            'ZY_BRRY.ZRYS_MC AS AEE01',
        ], $map);
        

        if ($map['export'] == 1) {
            $this->exportNormalSearch($list);
            exit;
        }
        foreach ($list as &$v) {
            $v['msg_yj'] = json_decode($v['msg_yj'], true);
            // 数据未删除，情况删除数据的类型
            if ($v['is_delete']) {
                $v['data_type'] = '';
            }
        }

        return ToolsService::returnData(200, ['count' => $count, 'list' => $list]);
    }

    public function exportNormalSearch($list = [])
    {
        $excel_data[] = ['序号', '质控时间', '预警问题', '是否整改', '质控依据', '住院号码', '患者姓名', '科室姓名', '主治医师', '主任医师', '入院时间', '出院时间'];
        $no = 1;

        foreach ($list as $item) {
            $excel_data[] = [
                $no,
                $item['push_time'] . "\t",
                $item['quality_content'] . "\t",
                $item['status'] ? '是' : '否' . "\t",
                $item['msg_yj'] . "\t",
                $item['AAA28'] . "\t",
                $item['AAA01'] . "\t",
                $item['AAC11N'] . "\t",
                $item['AEE03'] . "\t",
                $item['AEE01'] . "\t",
                $item['AAB01'] . "\t",
                $item['AAC01'] . "\t",
            ];
            $no++;
        }

        $csv = new CsvService();
        $csv->filename = $csv->charset('预警信息', 'UTF-8');
        return $csv->export($excel_data, false);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function getSurgeryData(Request $request)
    {
        $blbh = $request->post('blbh');
        $bllb = 303;
        if (!$blbh) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        $res = [];
        try {
            $caseService = new CaseService();
            $res = $caseService->getSurgeryData($blbh, $bllb);
            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnData($code, $res, $msg ?? '');
    }

    public function analysis()
    {
        echo '<pre>';
        var_dump(Env::get('PATH_TRANSLATED'));
    }

    public function getTaskNameBackup(Request $request)
    {
        // 获取所有type为1,2,3的数据
        $res = BigModelTaskName::query()->where("status", 1)->where('type', 2)->get()->toArray();
        $return = array_column($res, null, 'id');

        $category_tree = array();
        foreach ($return as $key => $v) {
            if ($v['parent_id'] == 0) {
                // 一级目录
                $category_tree[] = &$return[$key];
            } else {
                if ($v['type'] == 2) {
                    // 二级目录
                    $return[$v['parent_id']]['child'][] = &$return[$key];
                } else if ($v['type'] == 3) {
                    // 三级目录,直接添加到parent_id对应的二级目录下
                    if (!isset($return[$v['parent_id']]['child'])) {
                        $return[$v['parent_id']]['child'] = [];
                    }
                    $return[$v['parent_id']]['child'][] = &$return[$key];
                } else if ($v['type'] == 4) {
                    // 三级目录,直接添加到parent_id对应的二级目录下
                    if (!isset($return[$v['parent_id']]['child'])) {
                        $return[$v['parent_id']]['child'] = [];
                    }
                    $return[$v['parent_id']]['child'][] = &$return[$key];
                }
            }
        }

        return ToolsService::returnData(200, $category_tree, $msg ?? '');

    }

    public function getTaskName(Request $request)
    {
        $res = BigModelTemplate::query()
            ->where('status', 1)
            ->where('type', 2)
            ->selectRaw('big_model_template.*, title as name')
            ->get()
            ->toArray();

        return ToolsService::returnData(200, $res, $msg ?? '');
    }
}
