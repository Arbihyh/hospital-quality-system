<?php

namespace App\Http\Controllers\Api;

use App\Model\Yzb;
use App\Model\PACS;
use App\Model\User;
use App\Model\Error;
use App\Model\Staff;
use App\Model\Appeal;
use App\Model\ErrorV2;
use App\Model\Setting;
use App\Model\ZY_BRRY;
use App\Model\ZY_HCMX;
use App\Model\CaseRule;
use App\Model\ErrorRule;
use App\Model\TableDict;
use App\Model\Department;
use App\Model\CaseQuality;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use App\Model\HomeQuality;
use App\Model\PatientInfo;
use App\Model\RuleWordMap;
use App\Model\ErrorHomeBmy;
use App\Model\PatientScore;
use App\Services\CsvService;
use Illuminate\Http\Request;
use App\Services\CaseService;
use App\Services\UserService;
use App\Model\CaseQualityPlan;
use App\Model\EMR_BL_BL01_NEW;
use App\Services\ToolsService;
use App\Services\TargetService;
use Illuminate\Validation\Rule;
use App\Model\PatientDoctorInfo;
use App\Model\PatientInfoReview;
use App\Model\PatientInfoTarget;
use App\Services\QualityService;
use App\Model\CaseQualityPlanList;
use App\Model\PatientHospitalInfo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Model\CaseQualityZm;
use App\Model\PatientInfoV2;
use App\Services\ElasticsearchService;
use App\Services\MedicalRecordService;

/**
 * 人工质控控制器
 */
class ArtificialControlController extends Controller
{

    /**
     * 获取患者的审核状态
     *
     * @param  Request $request
     * @return void
     */
    public function getZyhReviewStatus(Request $request)
    {
        $json = file_get_contents('php://input');

        // 检测并移除UTF-8 BOM标记 (EF BB BF)
        if (strpos($json, "\xEF\xBB\xBF") === 0) {
            $json = substr($json, 3);
        }
        Log::info("qualityHandleV2 请求参数：" . $json);

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
        $data = ZY_BRRY::query()->where('ZYH', $zyh)->first();
        if (!$data) {
            return ToolsService::returnData(200, ['review_status' => 2]);
        }
        $data = $data->toArray();
        // `review_status` 审核状态：0、未审核，1、审核中，2审核通过，3、审核不通过',
        $data['review_status'] = $data['review_status'] ?? 2;
        $reviewStatus = 1;
        if ($data['review_status'] != 2) {
            $reviewStatus = 2;
        }

        return ToolsService::returnData(200, ['review_status' => $reviewStatus]);
    }
    /**
     * 获取病历质控列表
     *
     * @param  Request $request
     * @return array
     */
    public function getBlZkList(Request $request)
    {
        $is_export = $request->get('is_export', '');
        $AAA28 = $request->get('AAA28', '');    // 住院号
        $review_status = $request->get('review_status', '');  // 审核状态
        $AAC02C = $request->get('AAC02C', '');  // 出院科室
        $AAB01_START = $request->get('AAB01_START', '');    // 入院开始时间
        $AAB01_END = $request->get('AAB01_END', '');        // 入院结束时间
        $AAC01_START = $request->get('AAC01_START', '');    // 出院开始时间
        $AAC01_END = $request->get('AAC01_END', '');        // 出院结束时间
        $page = $request->get('page', 1);
        $pageSize = $request->get('page_size', 10);


        // 查看当前时间在case_quality_plan中是否有设置质控计划，如果有则获取当前登录人的科室信息对应的住院号
        $zyhList = [];
        $now = time();
        $plan = CaseQualityPlan::query()
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->first();

        if ($plan) {
            $is_random = $plan->is_random;
            if ($is_random == 1) {
                // 获取当前登录人科室信息
                $user = $request->user();
                if ($plan->unit == 1) {
                    $depIds = [];
                    if (!empty($user['dep_id'])) {
                        if (is_array($user['dep_id'])) {
                            $depIds = $user['dep_id'];
                        } else {
                            $depIds = json_decode($user['dep_id'], true);
                        }
                    }
                    if (!empty($depIds)) {
                        // 查询当前科室的住院号
                        $zyhList = CaseQualityPlanList::query()
                            ->whereIn('KSID', $depIds)
                            ->pluck('ZYH')
                            ->toArray();

                        if (empty($zyhList)) {
                            return ToolsService::returnData(200, ['list' => [], 'count' => 0]);
                        }
                    } else {
                        return ToolsService::returnData(200, ['list' => [], 'count' => 0]);
                    }
                } elseif ($plan->unit == 2) {

                    if ($user['w_id']) {
                        $wIds = json_decode($user['w_id'], true);
                    }

                    if (!empty($wIds)) {
                        // 查询当前科室的住院号
                        $zyhList = CaseQualityPlanList::query()
                            ->whereIn('BQID', $wIds)
                            ->pluck('ZYH')
                            ->toArray();

                        if (empty($zyhList)) {
                            return ToolsService::returnData(200, ['list' => [], 'count' => 0]);
                        }
                    } else {
                        return ToolsService::returnData(200, ['list' => [], 'count' => 0]);
                    }
                } elseif ($plan->unit == 3) {

                    // 诊疗组todo
                }
            }
        }

        $query = ZY_BRRY::query()
            ->select(
                [
                    "patient_doctor_info.AEE03",
                    "patient_doctor_info.AEE03_CODE",
                    "patient_doctor_info.AEE01",
                    "patient_doctor_info.AEE01_CODE",
                    "ZY_BRRY.*",
                    "ZY_BRRY.ZYCS as AAA29",
                    "ZY_BRRY.ZLZZDM as YLZZ_BH",
                    "ZY_BRRY.ZLZZMC as YLZZ",
                    "ZY_BRRY.CH as CWH",
                    "ZY_BRRY.BRKS as AAC02C",
                    "ZY_BRRY.CY_KSMC as AAC02C_name",
                    "ZY_BRRY.ZZYSMC as AEE03_name",
                    "ZY_BRRY.ZRYS_MC as AEE01_name",
                ]
            )
            ->leftJoin("patient_doctor_info", "patient_doctor_info.AAA28", "=", "ZY_BRRY.ZYH")
            ->where("ZY_BRRY.AAC01", "<>", "");

        if (!empty($zyhList)) {
            $query = $query->whereIn("ZY_BRRY.ZYH", $zyhList);
        }

        $score_lv = $request->get('score_lv', '');
        if ($score_lv) {
            $query = $query->where("ZY_BRRY.score_lv", $score_lv);
        }
        $score_start = $request->get('score_start', 0);
        $score_end = $request->get('score_end', 100);
        if ($score_start && $score_end) {
            $query = $query->where("ZY_BRRY.score", ">=", $score_start);
            $query = $query->where("ZY_BRRY.score", "<=", $score_end);
        }
        $home_ysz_score_lv = $request->get('home_ysz_score_lv', '');
        if ($home_ysz_score_lv) {
            $query = $query->where("ZY_BRRY.home_ysz_score_lv", $home_ysz_score_lv);
        }
        $home_ysz_score_start = $request->get('home_ysz_score_start', 0);
        $home_ysz_score_end = $request->get('home_ysz_score_end', 100);
        if ($home_ysz_score_start && $home_ysz_score_end) {
            $query = $query->where("ZY_BRRY.home_ysz_score", ">=", $home_ysz_score_start);
            $query = $query->where("ZY_BRRY.home_ysz_score", "<=", $home_ysz_score_end);
        }

        $depIds = UserService::getCurrentUserDep($request);
        if (empty($depIds)) {
            return ToolsService::jsonSuccess(['count' => 0, 'list' => []]);
        }
        if (is_array($depIds)) {
            $query = $query->whereIn("ZY_BRRY.BRKS", $depIds);
        }

        if (!empty($AAA28)) {
            $query = $query->where("ZY_BRRY.AAA28", $AAA28);
        }

        if ($AAB01_START && $AAB01_END) {
            $AAB01_START = date("Y-m-d 00:00:00", strtotime($AAB01_START));
            $AAB01_END = date("Y-m-d 23:59:59", strtotime($AAB01_END));
            $query = $query->where("ZY_BRRY.AAB01", ">=", $AAB01_START);
            $query = $query->where("ZY_BRRY.AAB01", "<=", $AAB01_END);
        }
        if ($AAC01_START && $AAC01_END) {
            $AAC01_START = date("Y-m-d 00:00:00", strtotime($AAC01_START));
            $AAC01_END = date("Y-m-d 23:59:59", strtotime($AAC01_END));
            $query = $query->where("ZY_BRRY.AAC01", ">=", $AAC01_START);
            $query = $query->where("ZY_BRRY.AAC01", "<=", $AAC01_END);
        }

        if ($AAC02C) {
            $query = $query->where("ZY_BRRY.BRKS", $AAC02C);
        }

        if (is_numeric($review_status)) {
            $query = $query->where("ZY_BRRY.review_status", $review_status);
        }
        $query = $query->orderByRaw(DB::raw("ZY_BRRY.review_status asc,ZY_BRRY.AAC01 desc"));
        if (empty($is_export)) {
            $data = $query->paginate($pageSize, ['*'], 'page', $page)->toArray();
        } else {
            $data['data'] = $query->get()->toArray();
        }
        $zyh = array_column($data['data'], "ZYH");

        $correction = [];

        if ($zyh) {
            $placeholders = implode(',', array_fill(0, count($zyh), '?'));

            $correction = DB::select(
                "select ZYH,count(*) as nums,sum(is_correction) as correction_nums from (
        SELECT JZHM as ZYH,is_correction FROM `case_quality` WHERE is_artificial=1 and JZHM IN ({$placeholders})
        UNION ALL
        SELECT ZYH,is_correction FROM `error_v2` WHERE is_artificial=1 and ZYH IN ({$placeholders})
        UNION ALL
        SELECT ZYH,is_correction FROM `home_quality` WHERE is_artificial=1 and ZYH IN ({$placeholders})
        ) tmp group by ZYH",
                array_merge($zyh, $zyh, $zyh)
            );

            $correction = array_column($correction, null, "ZYH");
        }

        $department = Department::query()->get()->toArray();
        $department = array_column($department, 'dep_name', 'dep_id');
        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, 'name', 'code');
        $user = User::query()->get(["name", "realname", "id"])->toArray();
        $user = array_column($user, null, 'id');

        foreach ($data['data'] as &$v) {
            $v["in_hospital"] = $v["CYPB"] == 1 ? 1 : 2;
            $v["BRKS_MC"] = data_get($department, $v["BRKS"], "");
            $v["AAC01"] = $v["AAC01"] == "0000-00-00 00:00:00" ? "" : $v["AAC01"];

            $v["ZKR"] = [["ZKR_CODE" => $user[$v["review_user"]]["name"] ?? "", "ZKR" => $user[$v["review_user"]]["realname"] ?? ""]];
            $v["correction_total"] = data_get($correction, $v["ZYH"] . ".nums", 0);
            $v["correction_success_nums"] = data_get($correction, $v["ZYH"] . ".correction_nums", 0);
        }
        if ($is_export == 1) {
            // 0、未审核，1、审核中，2审核通过，3、审核不通过
            $review_status_map = [
                0 => '未审核',
                1 => '审核中',
                2 => '审核通过',
                3 => '审核不通过',
            ];
            $exportData[] = [
                '序号',
                '病案号',
                '患者姓名',
                '床号',
                '整改状态',
                '审核状态',
                '病历得分',
                '首页得分',
                '审核医师',
                '审核时间',
                '入院时间',
                '出院时间',
                '病人科室',
                '管床医师',
                '主治医师',
                '诊疗组长',
                '科主任'
            ];
            foreach ($data['data'] as $k => $v) {
                $exportData[] = [
                    $k + 1,
                    $v["AAA28"] . "\t",
                    $v["BRXM"],
                    $v["CWH"],
                    $v["correction_success_nums"] . '/' . $v["correction_total"],
                    $review_status_map[$v["review_status"]],
                    $v["score"] . '/' . $v["score_lv"],
                    $v["home_ysz_score"] . '/' . $v["home_ysz_score_lv"],
                    $user[$v["review_user"]]["realname"] ?? "",
                    $v["review_time"] ?? "",
                    $v["AAB01"] ?? "",
                    $v["AAC01"] ?? "",
                    $v["BRKS_MC"] ?? "",
                    $v["GCYSMC"] ?? "",
                    $v["ZZYSMC"] ?? "",
                    $v["ZLZZMC"] ?? "",
                    $v["ZRYS_MC"] ?? "",
                ];
            }
            $csv = new CsvService();
            $csv->filename = $csv->charset('审核列表', 'UTF-8');

            return $csv->export($exportData);
        }

        return ToolsService::returnData(200, ['list' => $data['data'], 'count' => $data['total']]);
    }

    /**
     * 整改列表
     *
     * @param  Request $request
     * @return array
     */
    public function correctionList(Request $request)
    {
        $is_export = $request->get('is_export', '');
        $AAA28 = $request->get('AAA28', '');    // 住院号
        $isCorrection = $request->get('is_correction', '');  // 审核状态
        $AAC02C = $request->get('AAC02C', '');  // 出院科室
        $type = $request->get('type', '');  // 质控类型
        $AAB01_START = $request->get('AAB01_START', '');    // 入院开始时间
        $AAB01_END = $request->get('AAB01_END', '');        // 入院结束时间
        $AAC01_START = $request->get('AAC01_START', '');    // 出院开始时间
        $AAC01_END = $request->get('AAC01_END', '');        // 出院结束时间
        $qualityLevel = $this->normalizeCorrectionQualityLevel($request->get('quality_level', $request->get('qualityLevel', 1))); // 质控级别
        $page = $request->get('page', 1);
        $pageSize = $request->get('page_size', 10);

        $field = "tmp.*,quality_user.group_id as quality_group_id,ZY_BRRY.AAA28,ZY_BRRY.CYPB, ZY_BRRY.AAB01,ZY_BRRY.home_ysz_score, ZY_BRRY.home_ysz_score_lv, ZY_BRRY.score, ZY_BRRY.score_lv, ZY_BRRY.ZYCS as AAA29, ZY_BRRY.ZLZZDM as YLZZ_BH, ZY_BRRY.ZLZZMC as YLZZ, ZY_BRRY.CH as CWH, ZY_BRRY.CY_KSDM as AAC02C, ZY_BRRY.ZZYSDM, ZY_BRRY.ZZYSMC, ZY_BRRY.ZRYS, ZY_BRRY.ZRYS_MC, ZY_BRRY.GCYSDM, ZY_BRRY.GCYSMC, ZY_BRRY.BRKS, ZY_BRRY.BRXM, ZY_BRRY.AAC01, ZY_BRRY.CH as CWH, ZY_BRRY.ZZYSMC as AEE03_name, ZY_BRRY.ZRYS_MC as AEE01_name";
        $sql = " from (
        SELECT cq.JZHM as ZYH,CONVERT(cr.notice USING utf8mb4) as basis,cq.is_correction,cq.JSR,cq.created_at,cq.ZKR,cq.ZKR_CODE,2 AS type FROM `case_quality` cq left join case_rule cr on cq.rule_id=cr.id where cq.is_artificial=1
        UNION ALL
        SELECT ev.ZYH,CONVERT(er.desc USING utf8mb4) as basis,ev.is_correction,ev.JSR,ev.created_at,ev.ZKR,ev.ZKR_CODE,1 AS type FROM `error_v2` ev left join error_rule er on ev.error_rule=er.id where ev.is_artificial=1
        UNION ALL
        SELECT hq.ZYH,CONVERT(er.desc USING utf8mb4) as basis,hq.is_correction,hq.JSR,hq.created_at,hq.ZKR,hq.ZKR_CODE,3 AS type FROM `home_quality` hq left join error_rule er on hq.error_rule=er.id where hq.is_artificial=1
        ) tmp left join ZY_BRRY on tmp.ZYH=ZY_BRRY.ZYH left join department dep on dep.dep_id=ZY_BRRY.BRKS left join `user` quality_user on quality_user.name=tmp.ZKR_CODE and quality_user.deleted_at is null where 1=1";

        if (!empty($type)) {
            $sql .= " and tmp.type={$type}";
        }

        if ($AAB01_START && $AAB01_END) {
            $AAB01_START = date("Y-m-d 00:00:00", strtotime($AAB01_START));
            $AAB01_END = date("Y-m-d 23:59:59", strtotime($AAB01_END));
            $sql .= " and ZY_BRRY.AAB01>='{$AAB01_START}'";
            $sql .= " and ZY_BRRY.AAB01<='{$AAB01_END}'";
        }
        if ($AAC01_START && $AAC01_END) {
            $AAC01_START = date("Y-m-d 00:00:00", strtotime($AAC01_START));
            $AAC01_END = date("Y-m-d 23:59:59", strtotime($AAC01_END));
            $sql .= " and ZY_BRRY.AAC01>='{$AAC01_START}'";
            $sql .= " and ZY_BRRY.AAC01<='{$AAC01_END}'";
        }

        if (!empty($AAA28)) {
            $sql .= " and ZY_BRRY.AAA28='{$AAA28}'";
        }

        if ($AAC02C) {
            $sql .= " and ZY_BRRY.BRKS='{$AAC02C}'";
        }

        if (is_numeric($isCorrection)) {
            $sql .= " and tmp.is_correction={$isCorrection}";
        }
        if ($qualityLevel !== 1) {
            $qualityGroupIds = $this->getCorrectionQualityLevelGroupIds($qualityLevel);
            if (empty($qualityGroupIds)) {
                return ToolsService::returnData(200, ['list' => [], 'count' => 0]);
            }
            $sql .= " and quality_user.group_id in (" . implode(',', $qualityGroupIds) . ")";
        }
        $count = DB::select("select count(*) as nums" . $sql);
        if (empty($count[0]->nums)) {
            return ToolsService::returnData(200, ['list' => [], 'count' => 0]);
        }
        $sql .= $this->buildCorrectionListOrderSql($request);
        if (empty($is_export)) {
            $pageStart = ($page - 1) * $pageSize;
            $sql .= " limit {$pageStart},{$pageSize}";
        }

        $data = DB::select("select {$field}" . $sql);
        $data = json_decode(json_encode($data, 256), true);

        $staff = Staff::query()->get(['name', 'code'])->toArray();
        $staff = array_column($staff, 'name', 'code');

        //科室表
        $department = Department::query()->get()->toArray();
        $department = array_column($department, 'dep_name', 'dep_id');
        foreach ($data as &$v) {
            $zkr = explode(',', $v["JSR"]);
            $v["JSR"] = [];
            if ($zkr) {
                foreach ($zkr as $z) {
                    $v["JSR"][] = $staff[$z] ?? "";
                }
            }
            $v["JSR"] = implode(',', $v["JSR"]);
            $v["BRKS_MC"] = data_get($department, $v["BRKS"], "");
            $v["AAC01"] = $v["AAC01"] == "0000-00-00 00:00:00" ? "" : $v["AAC01"];
        }
        if ($is_export == 1) {
            $type_map = [
                1 => '运行首页',
                2 => '运行病例',
                3 => '编目首页',
            ];
            $exportData[] = [
                '序号',
                '病案号',
                '患者姓名',
                '入院时间',
                '出院时间',
                '病人科室',
                '质控类型',
                '整改状态',
                '缺陷问题',
                '接收医师',
                '接收时间',
                '质控医师',
                '质控时间'
            ];
            foreach ($data as $k => $v) {
                $exportData[] = [
                    $k + 1,
                    $v["AAA28"] . "\t",
                    $v["BRXM"],
                    $v["AAB01"],
                    $v["AAC01"],
                    $v["BRKS_MC"],
                    $type_map[$v["type"]],
                    $v["is_correction"] == 1 ? '已整改' : '未整改',
                    $v["basis"],
                    $v["JSR"],
                    $v["created_at"] ?? "",
                    $v["ZKR"],
                    $v["created_at"] ?? "",
                ];
            }
            $csv = new CsvService();
            $csv->filename = $csv->charset('审核明细', 'UTF-8');

            return $csv->export($exportData);
        }

        return ToolsService::returnData(200, ['list' => $data, 'count' => $count[0]->nums]);
    }

    /**
     * 组装整改列表排序 SQL
     *
     * @param Request $request
     * @return string
     */
    private function buildCorrectionListOrderSql(Request $request)
    {
        $orderKey = $this->getCorrectionListOrderValue($request, ['order_key', 'sort_field', 'order_field', 'order_by', 'sort']);
        $orderDirection = $this->getCorrectionListOrderValue($request, ['order_type', 'sort_order', 'order_direction', 'direction', 'order'], 'asc');
        $orderDirection = strtoupper($this->normalizeCorrectionListOrderDirection($orderDirection));
        $orderMap = [
            'department' => "COALESCE(NULLIF(dep.dep_name, ''), ZY_BRRY.BRKS)",
            'BRKS_MC' => "COALESCE(NULLIF(dep.dep_name, ''), ZY_BRRY.BRKS)",
            'BRKS' => "COALESCE(NULLIF(dep.dep_name, ''), ZY_BRRY.BRKS)",
            '病人科室' => "COALESCE(NULLIF(dep.dep_name, ''), ZY_BRRY.BRKS)",
            'admission_time' => 'ZY_BRRY.AAB01',
            'AAB01' => 'ZY_BRRY.AAB01',
            '入院时间' => 'ZY_BRRY.AAB01',
            'discharge_time' => 'ZY_BRRY.AAC01',
            'AAC01' => 'ZY_BRRY.AAC01',
            '出院时间' => 'ZY_BRRY.AAC01',
            'basis' => 'tmp.basis',
            'defect_problem' => 'tmp.basis',
            '缺陷问题' => 'tmp.basis',
            'receive_time' => 'tmp.created_at',
            '接收时间' => 'tmp.created_at',
            'quality_doctor' => 'tmp.ZKR',
            'ZKR' => 'tmp.ZKR',
            '质控医师' => 'tmp.ZKR',
            'quality_time' => 'tmp.created_at',
            'created_at' => 'tmp.created_at',
            '质控时间' => 'tmp.created_at',
        ];

        $orderSql = ' order by tmp.is_correction asc';
        if (isset($orderMap[$orderKey])) {
            $orderSql .= ',' . $orderMap[$orderKey] . ' ' . $orderDirection;
        }

        return $orderSql . ',tmp.created_at desc,ZY_BRRY.AAC01 desc';
    }

    /**
     * 获取整改列表排序参数
     *
     * @param Request $request
     * @param array $keys
     * @param string $default
     * @return string
     */
    private function getCorrectionListOrderValue(Request $request, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            $value = $request->get($key, null);
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $value = reset($value);
            }

            return trim((string)$value);
        }

        return $default;
    }

    /**
     * 规范化整改列表排序方向
     *
     * @param string $direction
     * @return string
     */
    private function normalizeCorrectionListOrderDirection($direction)
    {
        $direction = strtolower(trim((string)$direction));
        $descValues = ['desc', 'descending', 'descend', '倒序', '降序', '-1'];

        return in_array($direction, $descValues, true) ? 'desc' : 'asc';
    }

    /**
     * 规范化整改列表质控级别
     *
     * @param mixed $qualityLevel
     * @return int
     */
    private function normalizeCorrectionQualityLevel($qualityLevel)
    {
        $qualityLevel = (int)$qualityLevel;

        return in_array($qualityLevel, [2, 3], true) ? $qualityLevel : 1;
    }

    /**
     * 获取整改列表质控级别对应用户组 ID
     *
     * @param int $qualityLevel
     * @return array
     */
    private function getCorrectionQualityLevelGroupIds($qualityLevel)
    {
        $configName = $qualityLevel === 2 ? '二级质控' : '三级质控';
        $keyword = RuleWordMap::query()->where('name', $configName)->value('keyword');
        if (empty($keyword)) {
            return [];
        }

        $groupIds = preg_split('/[,，]/', $keyword);
        $groupIds = array_map('intval', $groupIds);
        $groupIds = array_filter($groupIds, function ($groupId) {
            return $groupId > 0;
        });

        return array_values(array_unique($groupIds));
    }

    /**
     * 申请审核
     */
    public function applyForReview(Request $request)
    {
        $ZYH = $request->query('ZYH', []);
        // 确保 $ZYH 是数组格式
        if (!is_array($ZYH)) {
            $ZYH = [$ZYH];
        }
        $status = $request->query('status', 2);
        $userid = $request->user()['id'];

        if (empty($ZYH)) {
            return ToolsService::returnData(1, [], '参数错误');
        }

        if ($status == 2) {
            /**
             * 查询该住院号中审核状态为0、3的住院号id
             */
            $query = ZY_BRRY::query()->whereIn('review_status', [0, 3]);
        } else {

            $departmentReview = $request->user()['department_review'] ?? 0;
            $reason = $request->query('reason', "");

            foreach ($ZYH as $value) {
                /**
                 * 增加撤销审核原因
                 */
                $insertData = [
                    'ZYH' => $value,
                    'apply_for_id' => $userid,
                    'apply_for_time' => date('Y-m-d H:i:s'),
                    'reason' => $reason,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
                if ($departmentReview == 2) {
                    $insertData['to_examine_id'] = $userid;
                    $insertData['to_examine_time'] = date('Y-m-d H:i:s');
                }

                /**
                 * 查询当前住院号是否存在撤销审核申请
                 */
                $patientInfoReviewInfo = PatientInfoReview::query()
                    ->where('ZYH', '=', $value)
                    ->whereNull('to_examine_id')
                    ->first();

                if (empty($patientInfoReviewInfo)) {
                    /**
                     * 添加撤销审核记录
                     */
                    PatientInfoReview::query()->insert($insertData);
                }
            }

            if ($departmentReview != 2) {
                ZY_BRRY::query()
                    ->where('review_status', '=', 2)
                    ->whereIn('ZYH', $ZYH)
                    ->update(['review_status' => 1]);
                return ToolsService::returnData(200, [], '申请撤销审核成功。');
            }

            $query = ZY_BRRY::query()->where('review_status', '=', 2);
        }

        $ids = $query->whereIn('ZYH', $ZYH)
            ->get(['id', 'ZYH'])
            ->toArray();

        //查询rulewordmap 4002
        $levelstatus = RuleWordMap::query()->where('id', '=', 4002)->value('keyword') ?? '2';

        // 用于统计通过和失败的数量
        $passedCount = 0;
        $failedCount = 0;
        $failedZYHList = []; // 存储存在强制缺陷的住院号
        $validIds = []; // 存储可以通过审核的id

        //查询case_quality中的缺陷等级是否有强制的
        if ($status == 2 && $levelstatus == '1') {

            // 逐个检查每条记录是否存在强制缺陷
            foreach ($ids as $item) {
                $zyh = $item['ZYH'];
                $hasForceDefect = false;

                // 先检查是否存在首页数据（patient_info_v2），如果缺少首页数据也不允许通过审核
                $patientInfoExists = PatientInfoV2::query()
                    ->where('ZYH', $zyh)
                    ->exists();

                if (!$patientInfoExists) {
                    // 缺少首页数据，视为有问题记录
                    $failedCount++;
                    $failedZYHList[] = $zyh;
                    continue;
                }

                // 检查case_quality中的强制缺陷
                $caseQualityCount = CaseQuality::query()
                    ->where('JZHM', $zyh)
                    ->leftJoin('case_rule', 'case_quality.rule_id', '=', 'case_rule.id')
                    ->where('case_rule.level', '=', '1')
                    ->count();

                if ($caseQualityCount > 0) {
                    $hasForceDefect = true;
                }

                // 检查自定义规则
                if (!$hasForceDefect) {
                    $rulesettingCount = CaseQuality::query()
                        ->where('JZHM', $zyh)
                        ->leftJoin('rule_setting', function ($join) {
                            $join->on(DB::raw('case_quality.rule_id'), '=', DB::raw('rule_setting.id + 1000000'));
                        })
                        ->where('rule_setting.error_level', '=', '1')
                        ->count();

                    if ($rulesettingCount > 0) {
                        $hasForceDefect = true;
                    }
                }

                // 检查errorv2中的强制缺陷
                if (!$hasForceDefect) {
                    $errorV2Count = ErrorV2::query()
                        ->where('ZYH', $zyh)
                        ->leftJoin('error_rule', 'error_v2.error_rule', '=', 'error_rule.id')
                        ->where('error_rule.level', '=', '0')
                        ->where('error_v2.status', '=', '0')
                        ->count();

                    if ($errorV2Count > 0) {
                        $hasForceDefect = true;
                    }
                }

                // 根据检查结果统计
                if ($hasForceDefect) {
                    $failedCount++;
                    $failedZYHList[] = $zyh;
                } else {
                    $passedCount++;
                    $validIds[] = $item['id'];
                }
            }
        } else {
            // 如果不需要检查强制缺陷，所有记录都通过
            $validIds = array_column($ids, 'id');
            $passedCount = count($validIds);
        }


        if (empty($validIds)) {
            $message = $status == 2 ? '当前病历已通过审核，无需再次审核' : "当前病历审核撤销中，无法再次审核";
            if ($failedCount > 0) {
                $message = "所有病历都存在强制缺陷或缺少首页数据，无法通过审核。共{$failedCount}条存在问题。";
            }
            return ToolsService::returnData(1, [], $message);
        }

        $ids = $validIds;

        $updateData = [
            'review_status' => $status,
            'review_user' => $userid,
            'is_artificial' => 1,
        ];

        if ($status == 2) {
            $updateData['review_time'] = date('Y-m-d H:i:s');
        } else {
            $updateData['review_time'] = null;
            $updateData['review_user'] = 0;
        }

        /**
         * 调整住院号信息
         */
        $result = ZY_BRRY::query()
            ->whereIn('id', $ids)
            ->update($updateData);

        $msg = '申请';
        if ($status == 0) {
            $msg = '撤销';
        }

        if (!$result) {
            return ToolsService::returnData(1, [], $msg . '失败');
        }

        // 构建返回消息，包含统计信息
        $successMessage = $msg . '成功';
        if ($status == 2 && $levelstatus == '1') {
            $successMessage = "{$msg}成功。通过审核：{$passedCount}条";
            if ($failedCount > 0) {
                $successMessage .= "，存在强制缺陷或缺少首页数据无法通过：{$failedCount}条";
            }
        }

        return ToolsService::returnData(200, ['passed' => $passedCount, 'failed' => $failedCount], $successMessage);
    }

    /**
     * 获取撤销审核列表
     */
    public function getRevokeList(Request $request)
    {
        $department = $request->user()['dep_id'] ?? [];
        if (!empty($department)) {
            $department = json_decode($department, true);
        }
        $ward = $request->user()['w_id'];
        if (!empty($ward)) {
            $ward = json_decode($ward, true);
        }
        $doctor = $request->user()['s_id'];
        $groupId = $request->user()['group_id'];

        /**
         * 查询撤销审核记录
         */
        $patientInfoReview = PatientInfoReview::query()
            ->where('apply_for_id', '<>', null)
            ->orderBy('apply_for_time', 'desc')
            ->get(['ZYH', 'apply_for_id', 'apply_for_time', 'reason', 'to_examine_id', 'to_examine_time'])
            ->toArray();

        /**
         * 查询该住院号的出院时间
         */
        $zyh = array_unique(array_column($patientInfoReview, 'ZYH'));

        if ($groupId == 1) {
            $AAC01 = ZY_BRRY::query()
                ->whereIn('ZYH', $zyh)
                ->get(['ZYH', 'AAC01'])
                ->toArray();
        } else {
            /**
             * 查询该员工所属的住院号
             */
            $ZYH = PatientHospitalInfo::query()
                ->whereIn('AAA28', $zyh)
                ->whereIn('AAC02C', $department)
                ->get(['AAA28', 'AAC02C'])
                ->toArray();
            $ZYH = array_column($ZYH, 'AAA28');
            $AAC01 = ZY_BRRY::query()
                ->whereIn('ZYH', $ZYH)
                ->get(['ZYH', 'AAC01'])
                ->toArray();
        }

        $AAC01 = array_column($AAC01, 'AAC01', 'ZYH');

        $applyForIds = array_column($patientInfoReview, 'apply_for_id');
        $applyForInfo = User::query()->whereIn('id', $applyForIds)->get(['id', 'realname'])->toArray();
        $applyForInfo = array_column($applyForInfo, 'realname', 'id');
        $toExamineIds = array_column($patientInfoReview, 'to_examine_id');
        $toExamineInfo = User::query()->whereIn('id', $toExamineIds)->get(['id', 'realname'])->toArray();
        $toExamineInfo = array_column($toExamineInfo, 'realname', 'id');

        foreach ($patientInfoReview as $key => $value) {
            if (empty($AAC01[$value['ZYH']])) {
                unset($patientInfoReview[$key]);
                continue;
            }
            $patientInfoReview[$key]['to_examine_id'] = $value['to_examine_id'] ?? 0;
            $patientInfoReview[$key]['apply_for_name'] = $applyForInfo[$value['apply_for_id']] ?? '';
            $patientInfoReview[$key]['to_examine_name'] = $toExamineInfo[$value['to_examine_id']] ?? '';
            $patientInfoReview[$key]['AAC01'] = $AAC01[$value['ZYH']];
        }

        $patientInfoReview = array_values($patientInfoReview);

        return ToolsService::returnData(200, $patientInfoReview, '获取成功');
    }

    /**
     * 撤销审核通过
     */
    public function revokeUpdate(Request $request)
    {
        $ZYH = $request->query('ZYH', []);

        if (empty($ZYH)) {
            return ToolsService::returnData(1, [], '参数错误');
        }

        $userid = $request->user()['id'];
        $departmentReview = $request->user()['department_review'] ?? 0;
        $time = date('Y-m-d H:i:s');

        if ($departmentReview != 2) {
            return ToolsService::returnData(1, [], '您暂无权限操作');
        }

        foreach ($ZYH as $value) {
            PatientInfoReview::query()
                ->where('ZYH', '=', $value)
                ->update(['to_examine_id' => $userid, 'to_examine_time' => $time]);
        }

        ZY_BRRY::query()
            ->whereIn('ZYH', $ZYH)
            ->update(['review_user' => 0, 'review_time' => null, 'review_status' => 0]);

        return ToolsService::returnData(200, [], '撤销成功');
    }

    /**
     * 获取自动质控状态
     */
    public function getQualityControlStatus(Request $request)
    {
        /**
         * 查询质控状态
         */
        $status = RuleWordMap::query()->where('id', '=', 4003)->value('keyword');

        return ToolsService::returnData(200, ['status' => $status], '获取成功');
    }

    /**
     * 质控开关状态更新
     */
    public function updateQualityControl(Request $request)
    {
        $status = $request->query('status', 0);

        $result = RuleWordMap::query()
            ->where('id', '=', 4003)
            ->update(['keyword' => $status, 'updated_at' => date('Y-m-d H:i:s')]);

        if (!$result) {
            return ToolsService::returnData(1, [], '更新失败');
        }

        return ToolsService::returnData(200, [], '更新成功');
    }

    /**
     * 获取病历下质控结果列表
     *
     * @param  Request $request
     * @return array
     */
    public function getCaseQualityList(Request $request)
    {
        $ZYH = $request->query('ZYH', '');
        $page = $request->query('page', 1);
        $pageSize = $request->query('page_size', 10);

        if (empty($ZYH)) {
            return ToolsService::returnData(1, '', '参数错误');
        }

        $caseQualityService = new ElasticsearchService('case_quality_2023');
        $must = [
            ['term' => ['JZHM' => $ZYH]]
        ];
        $params = $caseQualityService->clearMust()
            ->queryByMustBatch($must)
            ->paginate($page, $pageSize)
            ->trackTotalHits()
            ->getParams();
        $restful = app('es')->search($params);
        $data = $caseQualityService->getDataByEs($restful);
        $list = !empty($data[0]) ? $data[0] : [];
        $count = !empty($data[1]) ? $data[1] : 0;

        return ToolsService::returnData(200, ['list' => $list, 'count' => $count]);
    }

    /**
     * 获取病历菜单
     *
     * @param  Request $request
     * @return array
     */
    public function getBlMenuList(Request $request)
    {
        $ZYH = $request->query('id', '');
        if (empty($ZYH)) {
            return ToolsService::returnData(1, [], '参数错误');
        }

        $esService = new ElasticsearchService('quality2');
        $must = [
            "term" => [
                'MED_REC_ID' => $ZYH
            ]
        ];
        $params = $esService->queryByMust($must)->getParams();
        $res = app('es')->search($params);
        $res = $esService->getDataByEs($res);
        if (empty($res[1])) {
            return ToolsService::returnData(1, [], '参数错误');
        }
        $info = !empty($res[0]) ? $res[0][0] : [];
        $bllb = array_keys($info['EMR_BL_BL01']);

        // 79儿童营养风险评估
        // 104 外周血管活性药物
        $config = [
            ['name' => '出院记录', 'bllb' => 1],
            ['name' => '入院记录', 'bllb' => 292],
            ['name' => '病程记录', 'bllb' => 294],
            ['name' => '手术', 'bllb' => 303],
            ['name' => '报告单', 'bllb' => 2000002], // no
            ['name' => '病历讨论记录', 'bllb' => 43],
            ['name' => '授权同意类', 'bllb' => 329],
            ['name' => '报告结果类', 'bllb' => 67], // no
            ['name' => '影像报告', 'bllb' => 78], // no
            ['name' => '住院病历类', 'bllb' => 14], // no
            ['name' => '准分子中心门急诊病历', 'bllb' => 2000177], // no
            ['name' => '评估评分表类', 'bllb' => 79],
            ['name' => '护理记录', 'bllb' => 2000049], // no
            ['name' => '护理病历', 'bllb' => 2000048], // no
            ['name' => '护理病程', 'bllb' => 2000146], // no
            ['name' => '门急诊病历', 'bllb' => 2000], // no
            ['name' => '门急诊病程', 'bllb' => 2005], // no
            ['name' => '门诊知情同意书', 'bllb' => 2007], // no
            ['name' => '重危报告类', 'bllb' => 2000185], // no
            ['name' => '死亡记录类', 'bllb' => 288],
            ['name' => '急诊留观病历', 'bllb' => 2004], // no
            ['name' => '化疗记录类', 'bllb' => 83], // no
            ['name' => '检查申请类', 'bllb' => 66], // no
            ['name' => '24小时内记录类', 'bllb' => 18],
            ['name' => '医患沟通类', 'bllb' => 34],
            ['name' => '医疗常用表格', 'bllb' => 87],
        ];


        $esService = new ElasticsearchService('bl01_202303');
        $mustBl[] = [
            "term" => [
                'JZHM' => $ZYH
            ]
        ];
        $mustBl[] = [
            "terms" => [
                'BLLB' => [294, 303]
            ]
        ];
        $params = $esService->queryByMustBatch($mustBl)->paginate(1, 10000)->orderBy('ZXSJ', 'asc')->getParams();
        $res = app('es')->search($params);
        $res = $esService->getDataByEs($res);
        $bl01Data = empty($res[0]) ? [] : $res[0];

        $targetService = new TargetService();
        foreach ($config as $k => $v) {
            if (!in_array($v['bllb'], $bllb)) {
                unset($config[$k]);
            }
            if (in_array($v['bllb'], [294, 303])) {
                // 获取病程记录、手术二级菜单
                $bllbList = $targetService->getSurgeryMenu($bl01Data, $v['bllb']);
                if (!empty($bllbList)) {
                    $config[$k]['count'] = count($bllbList);
                    $config[$k]['list'] = $bllbList;
                }
            } elseif ($v['bllb'] == 2000002) {
                // 获取报告单二级菜单
                $pacsMenuList = $targetService->getPacsMenu($info);
                if (!empty($pacsMenuList)) {
                    $config[$k] = ['name' => '报告单', 'bllb' => 2000002, 'list' => $pacsMenuList];
                    $config[$k]['count'] = count($pacsMenuList);
                }
            } elseif (in_array($v['bllb'], [329, 34, 288, 87])) {
                $bllbList = $targetService->getTwoMenu($ZYH, $v['bllb']);
                if (!empty($bllbList)) {
                    $config[$k]['count'] = count($bllbList);
                    $config[$k]['list'] = $bllbList;
                }
            } elseif (!empty($config[$k]['name'])) {
                $config[$k]['count'] = 1;

                if (in_array($v['bllb'], [1, 18, 292])) {
                    $config[$k]['blbh'] = EMR_BL_BL01::query()
                        ->where('JZHM', '=', $ZYH)
                        ->where('BLLB', '=', $v['bllb'])
                        ->where('BLZT', '!=', 9)
                        ->value('BLBH');
                }
            }
        }

        if ($info['YZB']) {
            $config[] = ['name' => '医嘱', 'bllb' => 49, 'count' => 2];
        }

        return ToolsService::returnData(200, $config);
    }

    /**
     * 获取病历详情
     *
     * @param  Request $request
     * @return array
     */
    public function getBl01Info(Request $request)
    {
        $BLBH = $request->query('blbh', '');

        if (empty($BLBH)) {
            return ToolsService::returnData(1, [], '参数错误');
        }

        $data = EMR_BL_BL01::query()
            ->join('EMR_BL_BLXG', 'EMR_BL_BLXG.BLBH', '=', 'EMR_BL_BL01.BLBH')
            ->where('EMR_BL_BL01.BLBH', '=', $BLBH)
            ->groupBy('EMR_BL_BLXG.BLBH')
            ->orderBy('EMR_BL_BLXG.XGSJ')
            ->first(['EMR_BL_BL01.BLBH', 'EMR_BL_BL01.JZHM', 'EMR_BL_BL01.BLMC', 'BLLB', 'MBLB', 'CJSJ', 'ZXSJ', 'WCSJ', 'SXYS', 'HJNR'])->toArray();
        if ($data) {
            $patientInfo = PatientInfo::query()
                ->where('MED_REC_ID', '=', $data['JZHM'])
                ->first();
            $brxm = !empty($patientInfo) ? $patientInfo->AAA01 : '';

            $data['title'] = $data['BLMC'];
            if ($data['WCSJ'] == '0000-00-00 00:00:00') {
                $data['WCSJ'] = '';
            }
            // 数据脱敏
            $desensitizeData = desensitize($brxm, 1);
            $data['HJNR'] = str_replace($brxm, $desensitizeData, $data['HJNR']);

            // 查询签名医生
            $SYYS = EMR_BL_BLSY::query()->where('BLBH', '=', $data['BLBH'])->pluck('SYYS')->toArray();
            $doctorList = '';
            if ($SYYS) {
                $staff = Staff::query()->whereIn('code', $SYYS)->get(['name', 'ygjb_text'])->toArray();
                if ($staff) {
                    foreach ($staff as $val) {
                        $doctorName[] = $val['name'] . '（' . $val['ygjb_text'] . '）';
                    }
                    $doctorList = implode('，', $doctorName);
                }
            }
            $data['doctor_name'] = $doctorList;
        }

        return ToolsService::returnData(200, $data);
    }

    /**
     * 病案首页
     *
     * @param  Request $request
     * @return array
     */
    public function getHomeData(Request $request)
    {
        $id = $request->query('id');

        if (empty($id)) {
            return ToolsService::returnData(1, [], '病案号不可以为空！');
        }
        $data = MedicalRecordService::getData($id);
        if (!$data) {
            return ToolsService::returnData(1, [], '没有数据！');
        }

        $data = ToolsService::codeTransformationInfo(['AAA23C', 'ABF02C', 'AEB02C', 'AEI01C', 'AAA26C', 'AAA02C', 'AAA06C', 'AAA08C', 'AAA18C', 'AAB06C', 'AEM01C', 'AEM03C', 'AEG01C', 'AEG02C', 'AAA05C', 'AAB02C', 'AAC02C'], $data);
        if (count($data['operation'])) {
            foreach ($data['operation'] as $k => $v) {
                if (null != config('dictionaries.INCISION_GRADE_ID.' . $v['INCISION_GRADE_ID'])) {
                    $data['operation'][$k]['INCISION_GRADE_ID'] = config('dictionaries.INCISION_GRADE_ID.' . $v['INCISION_GRADE_ID']);
                }
                if (null != config('dictionaries.HOCUS_WAY_ID.' . $v['HOCUS_WAY_ID'])) {
                    $data['operation'][$k]['HOCUS_WAY_ID'] = config('dictionaries.HOCUS_WAY_ID.' . $v['HOCUS_WAY_ID']);
                }
            }
        }
        if (count($data['diagnosis'])) {
            foreach ($data['diagnosis'] as $k => $v) {
                if (null != config('dictionaries.IN_STATUS.' . $v['RYQK'])) {
                    $data['diagnosis'][$k]['RYQK'] = config('dictionaries.IN_STATUS.' . $v['RYQK']);
                }
            }
        }

        if (isset($data['AAC03']) && null != config('dictionaries.ABAS02.' . $data['AAC03'])) {
            $data['AAC03'] = config('dictionaries.ABAS02.' . $data['AAC03']);
        }
        $data['RJSS'] = '';
        if (isset($data['operation'][0]['RJSS'])) {
            $data['RJSS'] = $data['operation'][0]['RJSS'];
        }
        $data['AAA03'] = substr($data['AAA03'], 0, 10);
        if (isset($data['AAB01']) && strpos($data['AAB01'], 'T')) {
            $data['AAB01'] = str_replace('T', ' ', $data['AAB01']);
        }
        if (strpos($data['AAC01'], 'T')) {
            $data['AAC01'] = str_replace('T', ' ', $data['AAC01']);
        }
        $data['AEB02C'] = ($data['AEB02C'] ?? '无') . ',' . ($data['AEB01'] ?? '');
        if (!empty($data['AEE01_CODE'])) {
            $data['AEE01_CODE'] = QualityService::getStaffInfo($data['AEE01_CODE'], 'base_code');
        }
        if (!empty($data['AEE02_CODE'])) {
            $data['AEE02_CODE'] = QualityService::getStaffInfo($data['AEE02_CODE'], 'base_code');
        }
        if (!empty($data['AEE03_CODE'])) {
            $data['AEE03_CODE'] = QualityService::getStaffInfo($data['AEE03_CODE'], 'base_code');
            $data['AEE03_CODE_dep'] = QualityService::getStaffInfo($data['AEE03_CODE'], 'ks_code');
        }
        if (!empty($data['AEE04_CODE'])) {
            $data['AEE04_CODE'] = QualityService::getStaffInfo($data['AEE04_CODE'], 'base_code');
        }
        if (!empty($data['ZRHSBM'])) {
            $data['ZRHSBM'] = QualityService::getStaffInfo($data['ZRHSBM'], 'base_code');
        }

        // 查询医生签名、创建时间、修改时间、完成时间
        $data['CJSJ'] = '';
        $data['ZXSJ'] = '';
        $data['WCSJ'] = '';
        $data['doctor_name'] = '';
        $data['CWH'] = ZY_BRRY::query()->where("ZYH", $id)->value("CH");
        $bl01NewInfo = EMR_BL_BL01::query()
            ->where('JZHM', '=', $id)
            ->where('BLLB', '=', 2000001)
            ->where('BLZT', '!=', 9)
            ->first();
        if ($bl01NewInfo) {
            $data['CJSJ'] = $bl01NewInfo->CJSJ;
            $data['ZXSJ'] = $bl01NewInfo->first_blsy_time;
            $data['WCSJ'] = $bl01NewInfo->WCSJ;

            $SYYS = EMR_BL_BLSY::query()->where('BLBH', '=', $bl01NewInfo->BLBH)->pluck('SYYS')->toArray();
            if ($SYYS) {
                $staffList = Staff::query()->whereIn('code', $SYYS)->get(['name', 'ygjb_text'])->toArray();
                $nameList = [];
                foreach ($staffList as $staffInfo) {
                    $nameList[] = $staffInfo['name'] . "（" . $staffInfo['ygjb_text'] . "）";
                }
                $data['doctor_name'] = implode('、', $nameList);
            }
        }

        $data = dataDesensitize($data); // 数据脱敏
        return ToolsService::returnData(200, $data);
    }

    /**
     * 获取格式化病例内容
     *
     * @param  Request $request
     * @return array
     */
    public function getCasePlatform(Request $request, CaseService $caseService)
    {
        $id = $request->query('id');
        $bllb = $request->query('bllb', 1);

        if (!$id) {
            return ToolsService::returnData(1, [], '请求的参数有误');
        }

        $res = [];
        try {
            $res = $caseService->getCasePlatform($id, $bllb);
            $code = 200;
        } catch (\Exception $e) {
            $code = 1;
            $msg = $e->getMessage();
        }

        return ToolsService::returnData($code, $res, $msg ?? '');
    }

    /**
     * 获取手术格式化数据
     *
     * @param  Request $request
     * @return array
     */
    public function getSurgeryData(Request $request)
    {
        $blbh = $request->query('blbh');
        $bllb = 303;
        if (!$blbh) {
            return ToolsService::returnData(1, [], '请求的参数有误');
        }
        $res = [];
        try {
            $caseService = new CaseService();
            $res = $caseService->getSurgeryData($blbh, $bllb);
            $code = 200;
        } catch (\Exception $e) {
            $code = 1;
            $msg = $e->getMessage();
        }
        return ToolsService::returnData($code, $res, $msg ?? '');
    }

    /**
     * 获取病程格式化数据
     *
     * @param  Request $request
     * @return array
     */
    public function getBcData(Request $request)
    {
        $blbh = $request->query('blbh');
        if (!$blbh) {
            return ToolsService::returnData(1, [], '请求的参数有误');
        }
        $res = [];
        try {
            $caseService = new CaseService();
            $res = $caseService->getBcData($blbh, 294);
            $code = 200;
        } catch (\Exception $e) {
            $code = 1;
            $msg = $e->getMessage();
        }
        return ToolsService::returnData($code, $res, $msg ?? '');
    }

    /**
     * 获取报告单相关数据
     *
     * @param  Request $request
     * @return array
     */
    public function getPacsData(Request $request)
    {
        $type = $request->query('type');     // 检查类型
        $ZYH = $request->query('zyh');       // 病案号

        if (!$ZYH || !$type) {
            return ToolsService::returnData(1, [], '请求的参数有误');
        }

        $pacsContent = PatientInfoTarget::query()
            ->where('ZYH', '=', $ZYH)
            ->value('pacs_content');
        $pacsData = json_decode($pacsContent, true);
        $data = [];
        if (isset($pacsData[$type])) {
            $data = $pacsData[$type];

            // 报告类型处理
            foreach ($data as $key => &$val) {
                $ExamType = '';
                if (!empty($val['ExamType'])) {
                    $ExamType = !empty(Pacs::EXAM_TYPE[$val['ExamType']]) ? Pacs::EXAM_TYPE[$val['ExamType']] : '';
                }
                $val['ExamType'] = $ExamType;
            }
        }

        return ToolsService::returnData(200, $data);
    }

    /**
     * 获取病历数据
     *
     * @param  Request $request
     * @return array
     */
    public function getAllCase(Request $request)
    {
        $bllb = $request->query('bllb', 1);
        $MED_REC_ID = $request->query('MED_REC_ID');

        $data = EMR_BL_BL01::query()
            ->join('EMR_BL_BLXG', 'EMR_BL_BLXG.BLBH', '=', 'EMR_BL_BL01.BLBH')
            ->where('JZHM', $MED_REC_ID)
            ->where('BLLB', $bllb)
            ->groupBy('EMR_BL_BLXG.BLBH')
            ->orderBy('EMR_BL_BLXG.XGSJ')
            ->get(['EMR_BL_BL01.BLBH', 'MBLB', 'CJSJ', 'ZXSJ', 'WCSJ', 'SXYS', 'HJNR', 'HTML_PRINT'])->toArray();

        $patientInfo = PatientInfo::query()
            ->where('MED_REC_ID', '=', $MED_REC_ID)
            ->get()->toArray();
        $brxm = !empty($patientInfo) ? $patientInfo[0]['AAA01'] : '';

        if ($data) {
            $bllbArray = [329 => '授权同意记录', 18 => '24小时记录记录', 34 => '医患沟通记录', 87 => '医疗常用表格'];
            $mblbArray = [21 => '24小时入院死亡记录', 20 => '24小时内入院记录', 288 => '死亡记录', 290 => '死亡记录', 291 => '死亡讨论记录'];
            foreach ($data as &$value) {
                if (array_key_exists($value['MBLB'], $mblbArray)) {
                    $value['title'] = $mblbArray[$value['MBLB']];
                } elseif (array_key_exists($bllb, $bllbArray)) {
                    $value['title'] = $bllbArray[$bllb];
                }
                if ($value['WCSJ'] == '0000-00-00 00:00:00') {
                    $value['WCSJ'] = '';
                }
                // 数据脱敏
                if (!empty(request()->post('is_tm')) && $brxm) {
                    $desensitizeData = desensitize($brxm, 1, 0, $re = '*');
                    $value['HJNR'] = str_replace($brxm, $desensitizeData, $value['HJNR']);
                }

                // 查询签名医生
                $SYYS = EMR_BL_BLSY::query()->where('BLBH', '=', $value['BLBH'])->pluck('SYYS')->toArray();
                $doctorList = '';
                if ($SYYS) {
                    $doctorName = [];
                    $staff = Staff::query()->whereIn('code', $SYYS)->get(['code', 'name', 'ygjb_text'])->toArray();
                    if ($staff) {
                        foreach ($staff as $val) {
                            $doctorName[$val['code']] = $val['name'] . '（' . $val['ygjb_text'] . '）';
                        }
                        $doctorList = implode('，', $doctorName);
                    }
                }
                $value['doctor_name'] = $doctorList;
            }
        }

        return ToolsService::returnData(200, $data);
    }

    /**
     * 长期医嘱
     *
     * @param  Request $request
     * @return array
     */
    public static function long(Request $request)
    {
        $AAA28 = $request->query('AAA28');
        $page = $request->query('page', 1);
        $info = self::getInfo($AAA28);
        if (empty($info)) {
            return ToolsService::returnData(1, []);
        }
        $list = Yzb::query()
            ->where('ZYH', $AAA28)
            ->where('YZQX', 1)
            ->orderBy('KZSJ')
            ->get(['YZMC', 'KZSJ', 'TZSJ', 'KZYS', 'TZYS', 'XZJDGH', 'SYPC', 'YCJL'])->toArray();
        if (!empty($list)) {
            foreach ($list as &$item) {
                $item['KZYS'] = QualityService::getStaffInfo($item['KZYS'], 'name');
                $item['TZYS'] = QualityService::getStaffInfo($item['TZYS'], 'name');
                $item['XZJDGH'] = QualityService::getStaffInfo($item['XZJDGH'], 'name');
                $item['KSDATE'] = date('Y-m-d', strtotime($item['KZSJ']));
                $item['KSTIME'] = date('H:i:s', strtotime($item['KZSJ']));
                $item['TZDATE'] = date('Y-m-d', strtotime($item['TZSJ']));
                $item['TZTIME'] = date('H:i:s', strtotime($item['TZSJ']));
            }
        }

        if (!empty(request()->post('is_tm'))) {
            // 数据脱敏
            $info['AAA01'] = desensitize($info['AAA01'], 1, 0, '*');
        }

        $data = ['list' => $list, 'info' => $info];
        return ToolsService::returnData(200, $data);
    }

    /**
     * 临时医嘱
     *
     * @param  Request $request
     * @return array
     */
    public static function temporary(Request $request)
    {
        $AAA28 = $request->query('AAA28');
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 20);
        $offset = ($page - 1) * $limit;
        $info = self::getInfo($AAA28);
        if (empty($info)) {
            return ToolsService::returnData(1, []);
        }
        $list = Yzb::query()
            ->where('ZYH', $AAA28)
            ->where('YZQX', 2)
            ->orderBy('XZJDSJ')
            ->get(['YZMC', 'KZSJ', 'TZSJ', 'KZYS', 'XZJDSJ', 'XZJDGH', 'SYPC', 'YCJL'])->toArray();
        if (!empty($list)) {
            foreach ($list as &$item) {
                $item['KZYS'] = QualityService::getStaffInfo($item['KZYS'], 'name');
                $item['XZJDGH'] = QualityService::getStaffInfo($item['XZJDGH'], 'name');
                $item['DATE'] = date('Y-m-d', strtotime($item['KZSJ']));
                $item['TIME'] = date('H:i:s', strtotime($item['KZSJ']));
            }
        }

        if (!empty(request()->post('is_tm'))) {
            // 数据脱敏
            $info['AAA01'] = desensitize($info['AAA01'], 1, 0, '*');
        }

        // 获取护士分床时间、死亡时间、出院时间
        $info['HCRQ'] = ZY_HCMX::query()->where('ZYH', '=', $AAA28)->where('HCLX', '=', 0)->value('HCRQ');
        // 查询出院时间
        $info['AAC01'] = Yzb::query()->where('ZYH', '=', $AAA28)->where('YDYZLB', '=', 303)->value('XZJDSJ');
        // 查询死亡时间
        $info['SWSJ'] = Yzb::query()->where('ZYH', '=', $AAA28)->where('YDYZLB', '=', 305)->value('XZJDSJ');

        $data = ['list' => $list, 'info' => $info];
        return ToolsService::returnData(200, $data);
    }

    public static function getInfo($AAA28)
    {
        $info = PatientInfo::query()
            ->join('patient_hospital_info', 'patient_info.MED_REC_ID', '=', 'patient_hospital_info.AAA28')
            ->where('MED_REC_ID', $AAA28)
            ->leftjoin('ZY_BRRY', 'patient_info.MED_REC_ID', '=', 'ZY_BRRY.ZYH')
            ->first(['patient_info.AAA28', 'patient_info.AAA01', 'patient_info.AAA02C', 'patient_info.AAA04', 'patient_hospital_info.AAB02C', 'patient_info.AAC01', 'ZY_BRRY.BRKS']);
        $info = !empty($info) ? $info->toArray() : [];
        if (isset($info['AAA02C']) && $info['AAA02C'] == 1) {
            $info['AAA02C'] = '男';
        } else {
            $info['AAA02C'] = '女';
        }
        $conf = config('dictionaries.AAB02C');
        //$info['AAB02C'] = $conf[$info['BRKS']] ?? '';
        $info['AAB02C'] = Department::query()->where('dep_id', $info['BRKS'])->value('dep_name') ?? '';
        return $info;
    }

    /**
     * 获取住院质控规则（人工）
     *
     * @param  Request $request
     * @return array
     */
    public function getRule(Request $request)
    {
        // 查询人工质控规则
        $ruleData = CaseRule::query()
            ->where('status', '=', 1)
            ->where('is_ai', '=', 0)
            ->get(['id', 'category', 'title', 'notice', 'score', 'type', 'level'])->toArray();
        $returnData['rule'] = $ruleData;

        // 查询质控分类
        $category = array_unique(array_column($ruleData, 'category'));
        $category = array_filter($category);
        sort($category);
        $returnData['category'] = $category;

        // 查询质控项目
        $title = array_unique(array_column($ruleData, 'title'));
        $title = array_filter($title);
        sort($title);
        $returnData['title'] = $title;

        // 质控类型
        $type = array_unique(array_column($ruleData, 'type'));
        $type = array_filter($type);
        sort($type);
        $returnData['type'] = $type;

        // 整改级别
        $returnData['level'] = [['id' => 1, 'name' => '强制'], ['id' => 2, 'name' => '建议']];

        // 整改级别
        $returnData['zgjb'] = ['终末质控', '事中质控'];

        return ToolsService::returnData(200, $returnData);
    }

    /**
     * 获取病历信息
     *
     * @param  Request $request
     * @return array
     */
    public function getBlInfo(Request $request)
    {
        $blbh = $request->query('blbh', '');
        if (empty($blbh)) {
            return ToolsService::returnData(1, [], '参数错误');
        }

        $bl01Info = EMR_BL_BL01::query()
            ->join('EMR_BL_BLXG', 'EMR_BL_BLXG.BLBH', '=', 'EMR_BL_BL01.BLBH')
            ->where('EMR_BL_BL01.BLBH', '=', $blbh)
            ->groupBy('EMR_BL_BLXG.BLBH')
            ->orderBy('EMR_BL_BLXG.XGSJ')
            ->first(['EMR_BL_BL01.BLBH', 'EMR_BL_BL01.JZHM', 'EMR_BL_BL01.BLMC', 'BLLB', 'MBLB', 'CJSJ', 'ZXSJ', 'WCSJ', 'SXYS', 'BRKS', 'HJNR']);
        if (!$bl01Info) {
            return ToolsService::returnData(200);
        }

        // 接收人
        $jsr = Staff::query()->where('code', '=', $bl01Info->SXYS)->value('name');
        // 接收科室
        $jsks = Department::query()->where('dep_id', '=', $bl01Info->BRKS)->value('dep_name');
        // AAA28
        $patientInfo = PatientInfo::query()->where('MED_REC_ID', '=', $bl01Info->JZHM)->first();
        $AAA28 = !empty($patientInfo) ? $patientInfo->AAA28 : '';
        $brxm = !empty($patientInfo) ? $patientInfo->AAA01 : '';
        $doctorList = '';
        $HJNR = '';
        if ($bl01Info) {
            $WCSJ = $bl01Info->WCSJ == '0000-00-00 00:00:00' ? '' : $bl01Info->WCSJ;

            // 数据脱敏
            $desensitizeData = desensitize($brxm, 1);
            $HJNR = str_replace($brxm, $desensitizeData, $bl01Info->HJNR);

            // 查询签名医生
            $SYYS = EMR_BL_BLSY::query()->where('BLBH', '=', $blbh)->pluck('SYYS')->toArray();

            if ($SYYS) {
                $staff = Staff::query()->whereIn('code', $SYYS)->get(['name', 'ygjb_text'])->toArray();
                if ($staff) {
                    foreach ($staff as $val) {
                        $doctorName[] = $val['name'] . '（' . $val['ygjb_text'] . '）';
                    }
                    $doctorList = implode('，', $doctorName);
                }
            }
        }

        $returnData = [
            'BLBH' => $blbh,
            'AAA28' => $AAA28,
            'ZYH' => $bl01Info->JZHM,
            'JSR' => $jsr,
            'JSKS' => $jsks,
            'title' => $bl01Info->BLMC ?? '',
            'CJSJ' => $bl01Info->CJSJ ?? '',
            'ZXSJ' => $bl01Info->ZXSJ ?? '',
            'WCSJ' => $WCSJ,
            'doctor_name' => $doctorList,
            'BLMC' => $bl01Info->BLMC ?? '',
            'HJNR' => $HJNR,
        ];

        return ToolsService::returnData(200, $returnData);
    }

    /**
     * 添加质控结果
     *
     * @param  Request $request
     * @return array
     */
    public function addCaseQualityOld(Request $request)
    {
        $ZYH = $request->query('ZYH', '');            // 住院号
        $AAA28 = $request->query('AAA28', '');        // 病案号

        $ZKR = $request->query('ZKR', '');            // 质控人
        $ZKKS = $request->query('ZKKS', '');          // 质控科室
        $JSR = $request->query('JSR', '');            // 接收人
        $JSKS = $request->query('JSKS', '');          // 接收科室
        $ZGJB = $request->query('ZGJB', '');          // 整改级别
        $ZGQX = $request->query('ZGQX', '');          // 整改期限
        $ruleId = $request->query('rule_id', '');     // 规则ID
        $category = $request->query('category', '');  // 质控分类
        $title = $request->query('title', '');        // 质控项目
        $type = $request->query('type', '');          // 质控类型
        $notice = $request->query('notice', '');      // 错误描述
        $basis = $request->query('basis', '');        // 质控内容
        $score = $request->query('score', '');        // 扣分
        $level = $request->query('level', 2);         // 1-强制 2-建议
        $cate = $request->query('cate', 1);           // 质控类别：1、病历质控，2、首页质控，3、医生站，4、首页质控编码员
        $score = !empty($score) ? $score : 0;

        if (!$ZYH || !$ZKR || !$ZKKS || !$JSR || !$JSKS || !$ZGJB || !$category || !$title || !$notice || !$basis) {
            return ToolsService::returnData(1, [], '参数错误');
        }

        // 人工质控规则
        $type = !empty($type) ? $type : '';
        $saveCaseRuleData = [
            'category' => $category,
            'title' => $title,
            'type' => $type,
            'notice' => $notice,
            'score' => $score,
            'is_ai' => 0,
            'level' => $level,
        ];
        if ($ruleId) {
            // 更新
            CaseRule::query()->where('id', '=', $ruleId)->update($saveCaseRuleData);
        } else {
            // 添加
            $ruleId = CaseRule::query()->insertGetId($saveCaseRuleData);
        }

        switch ($cate) {
            case 2:
            //                $errorInfo = Error::query()
            //                    ->where('ZYH','=',$ZYH)
            //                    ->where('AAA28','=',$AAA28)
            //                    ->first();
            //                if ($errorInfo) {
            //                    return ToolsService::returnData(1,[],'已质控过该病历，请勿重复质控');
            //                }

                /**
                 * 查询该住院号信息
                 */
                $ZYHInfo = PatientInfo::query()->where('MED_REC_ID', '=', $ZYH)->first();
                $ZYHHPInfo = PatientHospitalInfo::query()->where('AAA28', '=', $ZYH)->first();

                $insertData = [
                    'year' => date('Y'),
                    'month' => intval(date('M')),
                    'AAA28' => $AAA28,
                    'desc' => $notice,
                    'error_field' => $title,
                    'error_name' => 'rule_' . $ruleId,
                    'down' => $score,
                    'ZYH' => $ZYH,
                    'AAC11C' => $JSR,
                    'AAA01' => $ZYHInfo['AAA01'] ?? '',
                    'AAC01' => $ZYHInfo['AAC01'] ?? '',
                    'AAC03' => $ZYHHPInfo['AAC03'] ?? '',
                    'AAC11N' => $ZYHInfo['AAC11N'] ?? '',
                    'is_edit' => $level,
                    'category' => $category,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];

                $res = Error::query()->insert($insertData);
                break;
            case 3:
                $errorV2Info = ErrorV2::query()
                    ->where('ZYH', '=', $ZYH)
                    ->first();
                if ($errorV2Info) {
                    return ToolsService::returnData(1, [], '已质控过该病历，请勿重复质控');
                }

                /**
                 * 查询该住院号信息
                 */
                $ZYHInfo = PatientInfo::query()->where('MED_REC_ID', '=', $ZYH)->first();
                $ZYHHPInfo = PatientHospitalInfo::query()->where('AAA28', '=', $ZYH)->first();
                $ZYHDTInfo = PatientDoctorInfo::query()->where('AAA28', '=', $ZYH)->first();

                $insertData = [
                    'AAA28' => $AAA28,
                    'ZYH' => $ZYH,
                    'AAA01' => $ZYHInfo['AAA01'] ?? '',
                    'AAB01' => $ZYHInfo['AAB01'] ?? '',
                    'AAC01' => $ZYHInfo['AAC01'] ?? '',
                    'error_rule' => $ruleId,
                    'desc' => $notice,
                    'coder_id' => $ZYHDTInfo['BMY_BH'] ?? '',
                    'coder_name' => $ZYHDTInfo['AEE08'] ?? '',
                    'CYKSBM' => $ZYHHPInfo['AAC02C'] ?? '',
                    'CYKB' => $ZYHHPInfo['AAC03'] ?? '',
                    'ZZYS_BH' => $ZYHDTInfo['AEE03_CODE'] ?? '',
                    'ZZYS' => $ZYHDTInfo['AEE03'] ?? '',
                    'ZYYS_BH' => $ZYHDTInfo['AEE04_CODE'] ?? '',
                    'ZYYS' => $ZYHDTInfo['AEE04'] ?? '',
                    'ICD10_ID1' => '',
                    'ICD10_NAME' => $ZYHInfo['ICD10_NAME'] ?? '',
                    'ICD9_ID1' => '',
                    'ICD9_NAME' => $ZYHInfo['ICD9_NAME'] ?? '',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];

                $res = ErrorV2::query()->insert($insertData);
                break;
            case 4:
                $errorBmyInfo = ErrorHomeBmy::query()
                    ->where('ZYH', '=', $ZYH)
                    ->first();
                if ($errorBmyInfo) {
                    return ToolsService::returnData(1, [], '已质控过该病历，请勿重复质控');
                }

                /**
                 * 查询该住院号信息
                 */
                $ZYHInfo = PatientInfo::query()->where('MED_REC_ID', '=', $ZYH)->first();
                $ZYHHPInfo = PatientHospitalInfo::query()->where('AAA28', '=', $ZYH)->first();
                $ZYHDTInfo = PatientDoctorInfo::query()->where('AAA28', '=', $ZYH)->first();

                $insertData = [
                    'AAA28' => $AAA28,
                    'ZYH' => $ZYH,
                    'AAB01' => $ZYHInfo['AAB01'] ?? '',
                    'AAC01' => $ZYHInfo['AAC01'] ?? '',
                    'error_rule' => $ruleId,
                    'desc' => $notice,
                    'coder_id' => $ZYHDTInfo['BMY_BH'] ?? '',
                    'coder_name' => $ZYHDTInfo['AEE08'] ?? '',
                    'CYKSBM' => $ZYHHPInfo['AAC02C'] ?? '',
                    'CYKB' => $ZYHHPInfo['AAC03'] ?? '',
                    'ZZYS_BH' => $ZYHDTInfo['AEE03_CODE'] ?? '',
                    'ZZYS' => $ZYHDTInfo['AEE03'] ?? '',
                    'ZYYS_BH' => $ZYHDTInfo['AEE04_CODE'] ?? '',
                    'ZYYS' => $ZYHDTInfo['AEE04'] ?? '',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];

                $res = ErrorHomeBmy::query()->insert($insertData);
                break;
            default:
                $caseQualityInfo = CaseQuality::query()
                    ->where('rule_id', '=', $ruleId)
                    ->where('JZHM', '=', $ZYH)
                    ->first();
                if ($caseQualityInfo) {
                    return ToolsService::returnData(1, [], '已质控过该病历，请勿重复质控');
                }

                // 添加质控结果
                $insertData = [
                    'rule_id' => $ruleId,
                    'code' => 'rule_' . $ruleId,
                    'error_field' => $title,
                    'JZHM' => $ZYH,
                    'basis' => json_encode([[$basis]], 256),
                    'is_ai' => 0,
                    'ZKR' => $ZKR,
                    'ZKKS' => $ZKKS,
                    'JSR' => $JSR,
                    'JSKS' => $JSKS,
                    'ZGJB' => $ZGJB,
                ];
                if (!empty($ZGQX)) {
                    $insertData['ZGQX'] = date('Y-m-d', $ZGQX);
                }

                $res = CaseQuality::query()->insert($insertData);
                break;
        }

        if (!$res) {
            return ToolsService::returnData(1);
        }
        return ToolsService::returnData(200, [], '质控失败');
    }

    /**
     * 获取质控类别
     */
    public function getCaseCate()
    {
        return ToolsService::returnData(
            200,
            [
                [
                    'id' => 1,
                    'name' => '医生端（病历）'
                ],
                [
                    'id' => 2,
                    'name' => '医生端（首页）'
                ],
                [
                    'id' => 3,
                    'name' => '编码员端'
                ],
            ]
        );
    }

    /**
     * 获取质控结果
     *
     * @param  Request $request
     * @return array
     */
    public function getCaseQuality(Request $request)
    {
        $ZYH = $request->get('ZYH');
        if (!$ZYH) {
            return ToolsService::returnData(1, [], '请求的参数有误');
        }
        $caseQualityData = [];
        try {
            $caseQualityService = new CaseService();
            $caseQualityData = $caseQualityService->getCaseQuality($ZYH);
            $code = 200;
        } catch (\Exception $e) {
            $code = 1;
            $msg = $e->getMessage();
        }
        return ToolsService::returnData($code, $caseQualityData, $msg ?? '');
    }

    /**
     * 质控结果列表（二级列表）
     *
     * @param  Request $request
     * @return array|void
     */
    public function caseQualityExamine(Request $request)
    {
        $ZYH = $request->query('ZYH', '');

        if (empty($ZYH)) {
            return ToolsService::returnData(1, [], '请求的参数有误');
        }

        $error = Error::query()
            ->where(['ZYH' => $ZYH])
            ->where('status', 0)
            ->select('down', 'desc', 'error_field', 'error_name', 'category', 'error_rule')
            ->get()->toArray();

        if (!empty($error)) {
            $errorRuleData = ErrorRule::query()->where('status', '=', 0)->get()->toArray();
            $errorRuleData = array_column($errorRuleData, null, 'id');
            foreach ($error as &$errorInfo) {
                $errorInfo['level'] = $errorRuleData[$errorInfo['error_rule']]['level'];
                if ($errorInfo['error_rule'] != 1458) {
                    $errorInfo['desc'] = !empty($errorRuleData[$errorInfo['error_rule']]) ? $errorRuleData[$errorInfo['error_rule']]['desc'] : $errorInfo['desc'];
                }
            }
        }

        return ToolsService::returnData(200, $error);
    }

    /**
     * 添加质控结果（新）
     */
    public function addCaseQuality(Request $request)
    {
        $AAA28 = $request->post('ZYH', '');
        //if (!empty($AAA28)) return ToolsService::returnData()
        $rule_id = $request->post('rule_id', 0);

        try {
            /**
             * 参数接收
             */
            $AAA28 = $request->query('ZYH', '');          // 病案号
            $cate = $request->query('cate', 1);          // 接收端（质控类别：1、病历质控，2、首页质控（医生站），3、首页质控（编码员））
            $JSR = $request->query('JSR', []);                 // 接收人(医师工号)
            $JSKS = $request->query('JSKS', []);                // 接收科室
            $ZKR = $request->query('ZKR', []);                 // 质控人
            $score = $request->query('score', '');        // 扣分
            $ZGQX = $request->query('ZGQX', '');         // 整改期限   （天数）
            $level = $request->query('level', 2);         // 状态：1-强制 2-建议
            $title = $request->query('title', []);               // 质控目录
            $basis = $request->query('basis', '');        // 质控内容
            $notice = $request->query('notice', '');       // 质控依据
            $ruleId = $request->query('rule_id', '');      // 规则ID

            if (!$AAA28 || !$JSR || !$JSKS || !$ZKR || !$score || !$level || !$title || !$basis || !$notice) {
                return ToolsService::returnData(1, [], '参数错误');
            }

            $saveCaseRuleData = [
                'category' => $category ?? 0,
                'title' => $title,
                'type' => $cate,
                'notice' => $notice,
                'score' => $score,
                'is_ai' => 2,
                'level' => $level,
            ];
            if ($ruleId) {
                // 更新
                CaseRule::query()->where('id', '=', $ruleId)->update($saveCaseRuleData);
            } else {
                // 添加
                $ruleId = CaseRule::query()->insertGetId($saveCaseRuleData);
            }

            switch ($cate) {
                case 2:
                    $ZYHInfo = PatientInfo::query()
                        ->where('MED_REC_ID', '=', $AAA28)
                        ->first();
                    $errorV2Info = ErrorV2::query()
                        ->where('error_rule', '=', $ruleId)
                        ->where('ZYH', '=', $ZYHInfo['MED_REC_ID'])
                        ->where('is_artificial', '=', 1)
                        ->first();
                    if ($errorV2Info) {
                        return ToolsService::returnData(1, [], '已质控过该病历，请勿重复质控');
                    }

                    // 添加质控结果
                    $insertData = [
                        'hospital_name' => $ZYHInfo['hospital_name'],
                        'AAA28' => $ZYHInfo['AAA28'],
                        'ZYH' => $ZYHInfo['MED_REC_ID'],
                        'AAA01' => $ZYHInfo['AAA01'],
                        'AAB01' => $ZYHInfo['AAB01'],
                        'AAC01' => $ZYHInfo['AAC01'],
                        'error_rule' => $ruleId,
                        'desc' => $basis,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'is_artificial' => 1,
                    ];

                    $res = ErrorV2::query()->insert($insertData);
                    break;
                case 3:
                    $ZYHInfo = PatientInfo::query()
                        ->where('MED_REC_ID', '=', $AAA28)
                        ->first();
                    $homeQuality = HomeQuality::query()
                        ->where('error_rule', '=', $ruleId)
                        ->where('ZYH', '=', $ZYHInfo['MED_REC_ID'])
                        ->where('is_artificial', '=', 1)
                        ->first();
                    if ($homeQuality) {
                        return ToolsService::returnData(1, [], '已质控过该病历，请勿重复质控');
                    }

                    // 添加质控结果
                    $insertData = [
                        'hospital_name' => $ZYHInfo['hospital_name'],
                        'AAA28' => $ZYHInfo['AAA28'],
                        'ZYH' => $ZYHInfo['MED_REC_ID'],
                        'AAC01' => $ZYHInfo['AAC01'],
                        'error_rule' => $ruleId,
                        'basis' => json_encode([['desc' => $basis, 'location' => ['user' => ['UNT_ID' => ''], 'zd' => [], 'ss' => []]]], JSON_UNESCAPED_UNICODE),
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'is_artificial' => 1,
                    ];

                    $res = HomeQuality::query()->insert($insertData);
                    break;
                default:
                    $caseQualityInfo = CaseQuality::query()
                        ->where('rule_id', '=', $ruleId)
                        ->where('JZHM', '=', $AAA28)
                        ->first();
                    if ($caseQualityInfo) {
                        return ToolsService::returnData(1, [], '已质控过该病历，请勿重复质控');
                    }

                    // 添加质控结果
                    $insertData = [
                        'rule_id' => $ruleId,
                        'code' => 'rule_' . $ruleId,
                        'error_field' => $title,
                        'JZHM' => $AAA28,
                        'basis' => json_encode([[$basis]], 256),
                        'is_ai' => 0,
                        'ZKR' => $ZKR,
                        'JSR' => $JSR,
                        'JSKS' => $JSKS,
                        'is_artificial' => 1,
                    ];
                    if (!empty($ZGQX)) {
                        $insertData['ZGQX'] = date('Y-m-d H:i:s', (time() + $ZGQX * 86400));
                    }

                    $res = CaseQuality::query()->insert($insertData);
                    break;
            }

            if (!$res) {
                return ToolsService::returnData(1, [], '质控失败');
            }
            return ToolsService::returnData(200, [], '质控成功');
        } catch (\Exception $e) {
            return ToolsService::returnData(1, [], $e->getMessage());
        }
    }

    /**
     * @param  Request $request
     * @param  $id
     * @param  $status
     * @return array
     * 申诉审核接口
     */
    public function examineAppeal(Request $request, $id = 0, $status = 0)
    {
        $id = $request->query('id', 0);
        $ZYH = $request->query('ZYH', 0);
        $type = $request->query('type', 2);
        $cate = $request->query('cate', 2);
        $status = $request->query('status', 0);
        $describe = $request->query('describe', "");
        $caseDocument = $request->query('case_document', 0);
        $caseDocter = $request->query('case_docter', 0);
        $caseDocterMobile = $request->query('case_docter_mobile', 0);
        if (!$id) {
            return ToolsService::returnData(1, [], '参数错误');
        }
        $admin = $request->user();
        $data['examine_time'] = time();
        $data['quality_type'] = $type;
        $data['error_id'] = $id;
        $data['ZYH'] = $ZYH;
        $data['type'] = $cate;
        $data['case_docter'] = $admin['name'] ?? '';
        $data['status'] = $status;
        $data['reject_content'] = $describe;
        $data['case_document'] = $caseDocument;
        $data['case_docter'] = $caseDocter;
        $data['case_docter_mobile'] = $caseDocterMobile;
        $data['updated_at'] = date('Y-m-d H:i:s');

        $result = Appeal::query()
            ->where('error_id', '=', $id)
            ->where("quality_type", '=', $type)
            ->where("ZYH", '=', $ZYH)
            ->first();

        if ($cate == 1) {
            $data['type'] = 0;
            $data['ignore_num'] = empty($result) ? 1 : $result->toArray()['ignore_num'] + 1;
        }

        if (empty($result)) {
            $patientInfo = PatientInfo::query()->where('MED_REC_ID', '=', $ZYH)->first();
            $data['AAA28'] = $patientInfo['AAA28'];
            $data['AAB01'] = $patientInfo['AAB01'];
            $data['appeal_time'] = time();

            $res = Appeal::query()->insert($data);
        } else {
            $data['type'] = ($cate == 1) ? 0 : 2;
            $data['status'] = 0;
            $res = Appeal::query()
                ->where('error_id', '=', $id)
                ->where("quality_type", '=', $type)
                ->where("ZYH", '=', $ZYH)
                ->update($data);
        }

        if ($res) {
            return ToolsService::returnData(200, [], '操作成功');
        }

        return ToolsService::returnData(0, [], '操作失败');
    }

    /**
     * 获取申诉信息
     */
    public function getAppeal(Request $request, $id = 0)
    {
        $id = $request->query('id', 0);
        $cate = $request->query('cate', 2);
        $ZYH = $request->query('ZYH', 0);

        if (!$id || !$cate) {
            return ToolsService::returnData(1, [], '参数错误');
        }

        // 这里可以根据 error_id, quality_type, ZYH 查询 Appeal 表，获取最新一条记录
        $data = Appeal::query()
            ->where('error_id', $id)
            ->where("quality_type", $cate)
            ->where("ZYH", $ZYH)
            ->orderBy('id', 'desc')
            ->first();

        return ToolsService::returnData(200, $data, '获取成功');
    }

    /**
     * 获取员工信息
     */
    public function getStaffList(Request $request)
    {
        $dep = Department::query()->get()->toArray();
        $dep = array_column($dep, 'dep_name', 'dep_id');
        $keyword = Setting::getInfo("allow_ygjb");
        $data = Staff::query()->get(['name', 'code', 'base_code', 'ksdm as dep_id'])->toArray();
        foreach ($data as &$v) {
            $v["dep_id"] = intval($v["dep_id"]);
            $v["dep_name"] = $dep[$v["dep_id"]] ?? "";
        }

        return ToolsService::returnData(200, $data, '获取成功');
    }

    /**
     * 获取院区信息
     */
    public function getCampusAreaList(Request $request)
    {
        $data = ZY_BRRY::query()->groupBy('YQ')->get(['YQ'])->toArray();

        $data = array_column($data, 'YQ');

        return ToolsService::returnData(200, $data, '获取成功');
    }

    /**
     * 获取管床信息
     */
    public function getPipeBeddingList(Request $request)
    {
        $data = ZY_BRRY::query()->groupBy('GCYS')->get(['GCYS'])->toArray();

        $data = array_column($data, 'GCYS');

        return ToolsService::returnData(200, $data, '获取成功');
    }

    /**
     * 获取质控强制信息
     */
    public function getCaseNumberInfo(Request $request)
    {
        $ZYH = $request->query('ZYH', "");   // 住院号
        $appeal = Appeal::query()->where("ZYH", $ZYH)->get(["error_id", "quality_type"])->toArray();

        $caseRule = $errorRule = [];
        foreach ($appeal as $v) {
            if ($v["quality_type"] == 2) {
                $caseRule[] = $v["error_id"];
            } else {
                $errorRule[] = $v["error_id"];
            }
        }
        $medicalRecord = CaseQuality::query()
            ->join('case_rule', 'case_rule.id', '=', 'case_quality.rule_id')
            ->where('case_quality.JZHM', '=', $ZYH)
            ->when(
                !empty($caseRule),
                function ($query) use ($caseRule) {
                    return $query->whereNotIn('case_quality.rule_id', $caseRule);
                }
            )
            ->where('case_rule.level', '=', 1)
            ->where('case_rule.status', '=', 1)
            ->where('case_quality.is_correction', '=', 0)
            ->where('case_quality.is_ignore', '=', 0)
            ->count();

        //自定义规则
        $rulesetting = CaseQuality::query()
            ->where('JZHM', '=', $ZYH)
            ->leftJoin('rule_setting', function ($join) {
                $join->on(DB::raw('case_quality.rule_id'), '=', DB::raw('rule_setting.id + 1000000'));
            })
            ->where('rule_setting.error_level', '=', '1')
            ->where('case_quality.is_correction', '=', 0)
            ->where('case_quality.is_ignore', '=', 0)
            ->count();

        $medicalRecord += $rulesetting;


        $errorV2 = ErrorV2::query()
            ->join('error_rule', 'error_rule.id', '=', 'error_v2.error_rule')
            ->where('error_v2.ZYH', '=', $ZYH)
            ->where('error_v2.status', '=', 0)
            ->when(
                !empty($errorRule),
                function ($query) use ($errorRule) {
                    return $query->whereNotIn('error_v2.error_rule', $errorRule);
                }
            )
            ->where('error_rule.status', '=', 0)
            ->where('error_v2.is_correction', '=', 0)
            ->where('error_v2.is_ignore', '=', 0)
            ->where('error_rule.level', '=', 0)
            ->count();
        $homeQuality = HomeQuality::query()
            ->join('error_rule', 'error_rule.id', '=', 'home_quality.error_rule')
            ->where('home_quality.ZYH', '=', $ZYH)
            ->when(
                !empty($errorRule),
                function ($query) use ($errorRule) {
                    return $query->whereNotIn('home_quality.error_rule', $errorRule);
                }
            )
            ->where('error_rule.status', '=', 0)
            ->where('home_quality.is_correction', '=', 0)
            ->where('home_quality.is_ignore', '=', 0)
            ->where('error_rule.level', '=', 0)
            ->count();

        return ToolsService::returnData(
            200,
            [
                'medicalRecord' => $medicalRecord,
                'errorV2' => $errorV2,
                'homeQuality' => $homeQuality,
            ],
            '获取成功'
        );
    }

    /**
     * 病历智审结果接口
     */
    public function getCaseResult(Request $request)
    {
        $YQ = $request->query('YQ', "");   // 院区
        $KS = $request->query('KS', "");   // 科室
        $BQ = $request->query('BQ', "");   // 病区
        $GC = $request->query('GC', "");   // 管床
        $CH = $request->query('CH', "");   // 床号
        $page = $request->query('page', 1);
        $pageSize = $request->query('page_size', 10);

        $query = PatientInfo::query();

        if (!empty($KS)) {
            $query = $query->where('AAC11N', '=', $KS);
        }

        if (!empty($BQ)) {
            $query = $query->where('AAC11N', '=', $BQ);
        }

        if (!empty($CH)) {
            $query = $query->where('CWH', '=', $CH);
        }

        $list = $query
            ->select(['MED_REC_ID', 'CWH', 'AAA29', 'in_hospital'])
            ->paginate($page, $pageSize)
            ->toArray();
        $list = $list['data'] ?? [];

        $count = $query->count();

        $ZYH = array_column($list, 'MED_REC_ID');

        $medicalRecord = CaseQuality::query()
            ->join('case_rule', 'case_rule.id', '=', 'case_quality.rule_id')
            ->whereIn('case_quality.JZHM', $ZYH)
            ->where('case_rule.type', '=', 1)
            ->get(['JZHM', DB::raw('count(*) as count')])
            ->toArray();
        $medicalRecord = array_column($medicalRecord, 'count', 'JZHM');
        $medicalRecords = CaseQuality::query()
            ->join('case_rule', 'case_rule.id', '=', 'case_quality.rule_id')
            ->whereIn('case_quality.JZHM', $ZYH)
            ->where('case_quality.is_artificial', '=', 1)
            ->where('case_rule.type', '=', 1)
            ->get(['JZHM', DB::raw('count(*) as count')])
            ->toArray();
        $medicalRecords = array_column($medicalRecords, 'count', 'JZHM');
        $errorV2 = ErrorV2::query()
            ->join('case_rule', 'case_rule.id', '=', 'error_v2.error_rule')
            ->whereIn('error_v2.ZYH', $ZYH)
            ->where('case_rule.type', '=', 2)
            ->get(['ZYH', DB::raw('count(*) as count')])
            ->toArray();
        $errorV2 = array_column($errorV2, 'count', 'ZYH');
        $errorV2s = ErrorV2::query()
            ->join('case_rule', 'case_rule.id', '=', 'error_v2.error_rule')
            ->whereIn('error_v2.ZYH', $ZYH)
            ->where('error_v2.is_artificial', '=', 1)
            ->where('case_rule.type', '=', 2)
            ->get(['ZYH', DB::raw('count(*) as count')])
            ->toArray();
        $errorV2s = array_column($errorV2s, 'count', 'ZYH');
        $homeQuality = HomeQuality::query()
            ->join('case_rule', 'case_rule.id', '=', 'home_quality.error_rule')
            ->whereIn('home_quality.ZYH', $ZYH)
            ->where('case_rule.type', '=', 3)
            ->get(['ZYH', DB::raw('count(*) as count')])
            ->toArray();
        $homeQuality = array_column($homeQuality, 'count', 'ZYH');
        $homeQualitys = HomeQuality::query()
            ->join('case_rule', 'case_rule.id', '=', 'home_quality.error_rule')
            ->whereIn('home_quality.ZYH', $ZYH)
            ->where('home_quality.is_artificial', '=', 1)
            ->where('case_rule.type', '=', 3)
            ->get(['ZYH', DB::raw('count(*) as count')])
            ->toArray();
        $homeQualitys = array_column($homeQualitys, 'count', 'ZYH');

        foreach ($list as &$v) {
            $v['medical'] = $medicalRecord[$v['MED_REC_ID']] ?? 0;
            $v['error'] = $errorV2[$v['MED_REC_ID']] ?? 0;
            $v['home'] = $homeQuality[$v['MED_REC_ID']] ?? 0;
            $v['count'] = $v['medical'] + $v['error'] + $v['home'];
            $v['medicals'] = $medicalRecords[$v['MED_REC_ID']] ?? 0;
            $v['errors'] = $errorV2s[$v['MED_REC_ID']] ?? 0;
            $v['homes'] = $homeQualitys[$v['MED_REC_ID']] ?? 0;
            $v['counts'] = $v['medicals'] + $v['errors'] + $v['homes'];

            if ($v['in_hospital'] == 1) {
                $v['in_hospital'] = "在院";
            } elseif ($v['in_hospital'] == 2) {
                $v['in_hospital'] = "出院";
            } else {
                $v['in_hospital'] = "其他";
            }
        }

        unset($v);

        return ToolsService::returnData(200, ['list' => $list, 'count' => $count], '获取成功');
    }

    /**
     * @return array
     * 质控项目的���拉
     */
    public function getSelectObjectValue()
    {
        // 获取所有type为1,2,3的数据
        $res = TableDict::query()
            ->where('status', '=', 1)
            ->whereIn('type', [1, 2, 3, 4])
            ->get(['id', 'parent_field', 'field', 'field_name', 'type'])
            ->toArray();
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

        return ToolsService::returnData(200, $category_tree, $msg ?? '获取成功');
    }

    /**
     * @param  Request $request
     * @return array
     * 设置质控结果已整改
     */
    public function setCorrection(Request $request)
    {
        $id = $request->post('id', "");
        $qualityType = $request->post('quality_type', "");

        if ($qualityType == 2) {
            //$cq = CaseQuality::query()->where('id', '=', $id)->first()->toArray();
            CaseQuality::query()->where('id', '=', $id)->update(["is_correction" => 1]);
            //CaseQualityZm::query()->where('JZHM', '=', $cq['JZHM'])->where('rule_id', '=', $cq['rule_id'])->delete();
        } // 首页质控申诉
        elseif ($qualityType == 1) {
            ErrorV2::query()->where('id', '=', $id)->update(["is_correction" => 1]);
        } // 首页质控申诉
        elseif ($qualityType == 3) {
            HomeQuality::query()->where('id', '=', $id)->update(["is_correction" => 1]);
        }

        return ToolsService::returnData(200, [], '设置成功');
    }
}
