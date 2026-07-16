<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\Appeal;
use App\Model\CaseQuality;
use App\Model\Department;
use App\Model\ErrorRule;
use App\Model\ErrorV2;
use App\Model\HomeQuality;
use App\Model\RuleWordMap;
use App\Model\Staff;
use App\Model\User;
use App\Model\ZY_BRRY;
use App\Services\MenuService;
use App\Services\ToolsService;
use App\Services\UserService;
use Illuminate\Http\Request;
use App\Model\CaseRule;
use App\Model\RuleSetting;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ExportData;

class AppealController extends Controller
{
    /**
     * @return array
     * tab切换申诉数量
     */
    public function appealTabNums(Request $request)
    {
        $zyh = $request->get("zyh");
        $where = ["appeal.ZYH" => $zyh, "appeal.type" => 2, "appeal.status" => 0];

        // 获取各类型申诉数据
        $errorv2 = $this->getAppealsByQualityType($where, 1, 'error_v2');
        $caseQuality = $this->getAppealsByQualityType($where, 2, 'case_quality');
        $homeQuality = $this->getAppealsByQualityType($where, 3, 'home_quality');

        // 统计有效申诉数量
        $errorv2Nums = $this->countValidAppeals($errorv2, 'error_v2');
        $caseQualityNums = $this->countValidAppeals($caseQuality, 'case_quality');
        $homeQualityNums = $this->countValidAppeals($homeQuality, 'home_quality');

        return ToolsService::returnData(200, [
            "error_v2" => $errorv2Nums,
            "case_quality" => $caseQualityNums,
            "home_quality" => $homeQualityNums,
        ], "操作成功");
    }

    /**
     * 根据质控类型获取申诉数据
     * 
     * @param array $where 查询条件
     * @param int $qualityType 质控类型
     * @param string $table 关联表名
     * @return array
     */
    private function getAppealsByQualityType($where, $qualityType, $table)
    {
        $query = Appeal::query()
            ->join($table, 'appeal.id', '=', "{$table}.appeal_id")
            ->where("appeal.quality_type", $qualityType)
            ->where($where);

        // 仅对 error_v2 表添加额外的状态过滤
        if ($table === 'error_v2') {
            $query->where("{$table}.status", 0);
        }

        return $query->get()->toArray();
    }

    /**
     * 统计有效的申诉数量
     * 
     * @param array $appeals 申诉数据
     * @param string $type 申诉类型
     * @return int
     */
    private function countValidAppeals($appeals, $type)
    {
        $count = 0;

        foreach ($appeals as $item) {
            if ($this->isValidAppeal($item['error_id'], $type)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * 判断申诉是否有效
     * 
     * @param int $errorId 错误ID
     * @param string $type 申诉类型
     * @return bool
     */
    private function isValidAppeal($errorId, $type)
    {
        // 自定义规则(ID > 1000000)
        if ($errorId > 1000000) {
            $ruleSetting = RuleSetting::query()->where('id', $errorId - 1000000)->first();
            return $ruleSetting && $ruleSetting['status'] == 1;
        }

        // 系统规则
        switch ($type) {
            case 'error_v2':
            case 'home_quality':
                $rule = ErrorRule::query()->where('id', $errorId)->first();
                return $rule && $rule['status'] == 0;

            case 'case_quality':
                $rule = CaseRule::query()->where('id', $errorId)->first();
                return $rule && $rule['status'] == 1;

            default:
                return false;
        }
    }

    public function getCategory()
    {

        $config = config("confAdmin");
        $qualityCategory = !empty($config['qualityCategory']) ? $config['qualityCategory'] : [];
        return ToolsService::returnAdmin(0, $qualityCategory, $msg ?? '');
    }


    public function getType()
    {

        $config = config("confAdmin");
        $qualityCategory = !empty($config['qualityType']) ? $config['qualityType'] : [];
        return ToolsService::returnAdmin(0, $qualityCategory, $msg ?? '');
    }

    /**
     * @param Request $request
     * @param CaseRule $caseRule
     * @return array
     * 获取规则列表
     */
    public function getCaseRule(Request $request, CaseRule $caseRule)
    {
        $page = $request->get('page', 1);
        $pageSize = $request->get('page_size', 20);
        $where['category'] = $request->get('category', '');
        $where['title'] = $request->get('title', '');
        $where['status'] = $request->get('status', '');
        $where['score'] = $request->get('score', '');
        $where['type'] = $request->get('type', '');
        $where['notice'] = $request->get('notice', '');
        $where['department'] = $request->get('department', '');

        try {
            $rule = [];
            $count = $caseRule::getCount($where);
            if ($count) {
                $rule = $caseRule::getList($page, $pageSize, ['*'], $where);
            }
            $data = ['count' => $count, 'list' => $rule];
            $code = 0;
        } catch (\Exception $e) {
            $code = 401;
            $msg = $e->getMessage();
        }
        return ToolsService::returnAdmin($code, $data ?? [], $msg ?? '');
    }

    /**
     * @param Request $request
     * @param int $id
     * @param int $status
     * @return array
     * 申诉审核
     */
    public function examineAppeal(Request $request)
    {
        $id = $request->post('id', 0);
        $type = $request->post('type', 2);
        $status = $request->post('status', 0);
        $caseDocter = $request->post('case_docter', "");
        $reject_content = $request->post('reject_content', "");
        if (!$id || !$status || !in_array($status, [1, 2])) {
            return ToolsService::returnData(1, [], "参数错误");
        }
        $admin = $request->user();
        $data['examine_time'] = time();
        $data['case_docter'] = $admin['name'] ?? '';
        $data['status'] = $status;
        $data['case_docter'] = $caseDocter;
        $data['reject_content'] = $reject_content;
        Appeal::query()->where('id', $id)->where("quality_type", $type)->where("status", 0)->update($data);
        if ($status == 2) {
            $update = ["is_appeal" => 0];
        } else {
            $update = ["is_appeal" => 1];
        }
        if ($type == 2) {
            CaseQuality::query()->where('appeal_id', '=', $id)->update($update);
        } // 首页质控申诉
        elseif ($type == 1) {
            ErrorV2::query()->where('appeal_id', '=', $id)->update($update);
        } // 首页质控申诉
        elseif ($type == 3) {
            HomeQuality::query()->where('appeal_id', '=', $id)->update($update);
        }

        return ToolsService::returnData(200, [], "操作成功");
    }

    /**
     * @param Request $request
     * @param CaseRule $caseRule
     * @return array
     * 获取规则列表
     */
    public function getCaseAppeal(Request $request, Appeal $appeal)
    {
        // 更新申诉信息和质控结果没有对应关系的数据
        // 先同步已审核和待审核，再同步未审核
        DB::update("update `appeal` as a left join case_quality as b on a.ZYH=b.JZHM and a.error_id=b.rule_id set b.appeal_id=a.id,b.is_appeal=1 where a.status in (0,1) and a.quality_type=2 and a.type=2 and b.is_appeal=0");
        DB::update("update `appeal` as a left join case_quality as b on a.ZYH=b.JZHM and a.error_id=b.rule_id set b.appeal_id=a.id,b.is_appeal=1 where a.quality_type=2 and a.type=2 and b.is_appeal=0");
        DB::update("update `appeal` as a left join error_v2 as b on a.ZYH=b.ZYH and a.error_id=b.error_rule set b.appeal_id=a.id,b.is_appeal=1 where a.status in (0,1) and a.quality_type=1 and a.type=2 and b.is_appeal=0");
        DB::update("update `appeal` as a left join error_v2 as b on a.ZYH=b.ZYH and a.error_id=b.error_rule set b.appeal_id=a.id,b.is_appeal=1 where a.quality_type=1 and a.type=2 and b.is_appeal=0");
        DB::update("update `appeal` as a left join home_quality as b on a.ZYH=b.ZYH and a.error_id=b.error_rule set b.appeal_id=a.id,b.is_appeal=1 where a.status in (0,1) and a.quality_type=3 and a.type=2 and b.is_appeal=0");
        DB::update("update `appeal` as a left join home_quality as b on a.ZYH=b.ZYH and a.error_id=b.error_rule set b.appeal_id=a.id,b.is_appeal=1 where a.quality_type=3 and a.type=2 and b.is_appeal=0");

        $depIds = UserService::getCurrentUserDep($request);
        if (empty($depIds)) {
            return ToolsService::jsonSuccess(['count' => 0, 'list' => []]);
        }
        $AAA28 = $request->get('AAA28', '');    // 住院号
        $quality_type = $request->get('quality_type', '');  // 质控类型
        $status = $request->get('status', '');  // 审核状态
        $AAC02C = $request->get('AAC02C', '');  // 出院科室
        $AAB01_START = $request->get('AAB01_START', '');    // 入院开始时间
        $AAB01_END = $request->get('AAB01_END', '');        // 入院结束时间
        $AAC01_START = $request->get('AAC01_START', '');    // 出院开始时间
        $AAC01_END = $request->get('AAC01_END', '');        // 出院结束时间
        $is_export = $request->get('is_export', 0);         // 是否导出
        $page = $request->get('page', 1);
        $pageSize = $request->get('page_size', 10);
        $query = Appeal::query()
            ->select([
                "appeal.*",
                "ZY_BRRY.CYPB",
                "ZY_BRRY.home_ysz_score",
                "ZY_BRRY.home_ysz_score_lv",
                "ZY_BRRY.score",
                "ZY_BRRY.score_lv",
                "ZY_BRRY.ZYCS as AAA29",
                "ZY_BRRY.ZLZZDM as YLZZ_BH",
                "ZY_BRRY.ZLZZMC as YLZZ",
                "ZY_BRRY.CH as CWH",
                "ZY_BRRY.CY_KSDM as AAC02C",
                "ZY_BRRY.ZZYSDM",
                "ZY_BRRY.ZZYSMC",
                "ZY_BRRY.ZRYS",
                "ZY_BRRY.ZRYS_MC",
                "ZY_BRRY.GCYSDM",
                "ZY_BRRY.GCYSMC",
                "ZY_BRRY.BRKS",
                "ZY_BRRY.BRXM",
                "ZY_BRRY.AAC01",
                "ZY_BRRY.CH as CWH",
                "ZY_BRRY.CY_KSMC as AAC02C_name",
                "ZY_BRRY.ZZYSMC as AEE03_name",
                "ZY_BRRY.ZRYS_MC as AEE01_name",
                "patient_info.AAC11N",
            ])
            ->leftJoin("ZY_BRRY", "appeal.ZYH", "=", "ZY_BRRY.ZYH")
            ->leftJoin("patient_info", "patient_info.MED_REC_ID", "=", "appeal.ZYH")
            ->where("appeal.type", 2)
            ->when(is_array($depIds), function ($query) use ($depIds) {
                return $query->whereIn('ZY_BRRY.BRKS', $depIds);
            });

        if (!empty($AAA28)) {
            $query = $query->where("appeal.AAA28", $AAA28);
        }

        if ($AAB01_START && $AAB01_END) {
            $AAB01_START = date("Y-m-d 00:00:00", strtotime($AAB01_START));
            $AAB01_END = date("Y-m-d 23:59:59", strtotime($AAB01_END));
            $query = $query->where("patient_info.AAB01", ">=", $AAB01_START);
            $query = $query->where("patient_info.AAB01", "<=", $AAB01_END);
        }
        if ($AAC01_START && $AAC01_END) {
            $AAC01_START = date("Y-m-d 00:00:00", strtotime($AAC01_START));
            $AAC01_END = date("Y-m-d 23:59:59", strtotime($AAC01_END));
            $query = $query->where("patient_info.AAC01", ">=", $AAC01_START);
            $query = $query->where("patient_info.AAC01", "<=", $AAC01_END);
        }

        if ($AAC02C) {
            $query = $query->where("patient_info.AAC02C", $AAC02C);
        }

        if (is_numeric($status)) {
            $query = $query->where("appeal.status", $status);
        }

        if ($quality_type) {
            $query = $query->where("appeal.quality_type", $quality_type);
        }

        // 如果是导出，获取所有数据；否则分页查询
        if ($is_export) {
            $data = $query->orderByRaw(DB::raw("appeal.status asc,appeal.id desc"))->get()->toArray();
            $data = ['data' => $data, 'total' => count($data)];
        } else {
            $data = $query->orderByRaw(DB::raw("appeal.status asc,appeal.id desc"))->paginate($pageSize, ['*'], 'page', $page)->toArray();
        }

        $department = Department::query()->get()->toArray();
        $department = array_column($department, 'dep_name', 'dep_id');

        $errorRule = ErrorRule::query()->get()->toArray();
        $errorRule = array_column($errorRule, 'desc', 'id');

        $caseRule = CaseRule::query()->get()->toArray();
        $caseRule = array_column($caseRule, 'notice', 'id');

        $ruleSetting = RuleSetting::query()->get()->toArray();
        $ruleSetting = array_column($ruleSetting, 'description', 'id');

        $staffData = Staff::query()->pluck('name', 'code')->toArray();
        foreach ($data['data'] as &$v) {
            $v["in_hospital"] = $v["CYPB"] == 1 ? 1 : 2;
            $v["BRKS_MC"] = data_get($department, $v["BRKS"], "");
            switch ($v['quality_type']) {
                case 1: // 首页质控
                    if ($v['error_id'] > 1000000) {
                        $v['rule_title'] = $ruleSetting[$v['error_id'] - 1000000] ?? '';
                    } else {
                        $v['rule_title'] = $errorRule[$v['error_id']] ?? '';
                    }
                    break;
                case 2: // 病例质控
                    if ($v['error_id'] > 1000000) {
                        $v['rule_title'] = $ruleSetting[$v['error_id'] - 1000000] ?? '';
                    } else {
                        $v['rule_title'] = $caseRule[$v['error_id']] ?? '';
                    }
                    break;
                case 3: // 编码员质控
                    if ($v['error_id'] > 1000000) {
                        $v['rule_title'] = $ruleSetting[$v['error_id'] - 1000000] ?? '';
                    } else {
                        $v['rule_title'] = $errorRule[$v['error_id']] ?? '';
                    }
                    break;
                default:
                    $v['rule_title'] = '';
                    break;
            }

            // 质控医生展示为「姓名（工号）」格式
            if (!empty($v['case_docter'])) {
                $originCaseDoctor = $v['case_docter'];

                // 情况一：原始数据为「工号 姓名」，例如「1754 周让」
                if (preg_match('/^\s*(\d+)\s+(.+)\s*$/u', $originCaseDoctor, $matches)) {
                    $code = $matches[1];
                    $name = $matches[2];
                    $v['case_docter'] = $name . '（' . $code . '）';
                } elseif (preg_match('/^\s*\d+\s*$/', $originCaseDoctor)) {
                    // 情况二：原始数据为工号，尝试通过工号查询姓名
                    $code = trim($originCaseDoctor);
                    $name = $staffData[$code] ?? '';
                    if ($name !== '') {
                        $v['case_docter'] = $name . '（' . $code . '）';
                    } else {
                        // 查不到姓名则保持原值
                        $v['case_docter'] = $originCaseDoctor;
                    }
                } else {
                    // 情况三：原始数据可能是姓名，尝试通过姓名反查工号
                    $code = array_search($originCaseDoctor, $staffData, true);
                    if ($code !== false) {
                        $v['case_docter'] = $originCaseDoctor . '（' . $code . '）';
                    } else {
                        // 查不到工号则保持原值
                        $v['case_docter'] = $originCaseDoctor;
                    }
                }
            }
        }

        // 如果导出，返回 Excel 文件
        if ($is_export) {
            $exportData = [];
            foreach ($data['data'] as $index => $item) {
                $qualityTypeName = '';
                switch ($item['quality_type']) {
                    case 1:
                        $qualityTypeName = '首页质控';
                        break;
                    case 2:
                        $qualityTypeName = '病例质控';
                        break;
                    case 3:
                        $qualityTypeName = '编码员质控';
                        break;
                }

                $statusName = '';
                switch ($item['status']) {
                    case 0:
                        $statusName = '待审核';
                        break;
                    case 1:
                        $statusName = '通过';
                        break;
                    case 2:
                        $statusName = '驳回';
                        break;
                    case 3:
                        $statusName = '已整改';
                        break;
                }

                $exportData[] = [
                    $index + 1,
                    $item['BRKS_MC'] ?? '',
                    $item['BRXM'] ?? '',
                    $item['AAA28'] ?? '',
                    $item['CWH'] ?? '',
                    $statusName,
                    $item['appeal_docter'] ?? '',
                    $item['defect_content'] ?? '',
                    $item['appeal_time'] ? date('Y-m-d H:i:s', $item['appeal_time']) : '',
                    $item['rule_title'] ?? '',
                    // 质控审核医师：直接使用已格式化好的「姓名（工号）」字段
                    $item['case_docter'] ?? '',
                    $item['examine_time'] ? date('Y-m-d H:i:s', $item['examine_time']) : '',
                    $item['reject_content'] ?? '',
                ];
            }

            $title = [
                '序号',
                '病人科室',
                '患者姓名',
                '病案号',
                '床号',
                '审核状态',
                '申诉医师',
                '申诉问题',
                '申诉时间',
                '质控规则',
                '质控审核医师',
                '审核时间',
                '驳回原因',
            ];

            $fileName = '申诉列表_' . date('YmdHis') . '.xlsx';
            return Excel::download(new ExportData($title, $exportData), $fileName);
        }

        return ToolsService::returnData(200, ['list' => $data['data'], 'count' => $data['total']]);
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
        $category = $request->post('category', '');
        $score = $request->post('score', '');
        $type = $request->post('type', '');
        $department = $request->post('department', '');
        $node = $request->post('node', '');
        $one_no = $request->post('one_no', '');
        $status = $request->post('status', '');

        $msg = '';
        if (empty($title)) {
            $msg = '质控项不能为空';
        } elseif (empty($notice)) {
            $msg = '提示内容不能为空';
        }
        if (!empty($msg)) {
            return ToolsService::returnAdmin(1, [], $msg ?? "");
        }
        $addData = [
            'title' => $title,
            'notice' => $notice,
            'category' => $category,
            'score' => $score,
            'type' => $type,
            'department' => $department,
            'node' => $node,
            'one_no' => $one_no,
            'status' => $status,
        ];
        if (!$id) {
            $res = DB::table('case_rule')->insert($addData);
        } else {
            $res = DB::table('case_rule')->where('id', $id)->update($addData);
        }
        $code = 0;
        return ToolsService::returnAdmin($code, [], '');
    }

    /**
     * 修改质控规则的状态
     */
    public function updateCaseRuleStatus(Request $request, CaseRule $caseRule)
    {

        $id = $request->post('id', '');
        $status = $request->post('status', '0');
        DB::table('case_rule')->where('id', $id)->update(['status' => $status]);
        return ToolsService::returnAdmin(0, [], '');
    }

    /**
     * 修改质控规则的是否同步到事中质控
     */
    public function setCaseRuleshizhong(Request $request, CaseRule $caseRule)
    {

        $id = $request->post('id', '');
        $status = $request->post('status', '0');
        DB::table('case_rule')->where('id', $id)->update(['is_shizhong' => $status]);
        return ToolsService::returnAdmin(0, [], '');
    }
}
