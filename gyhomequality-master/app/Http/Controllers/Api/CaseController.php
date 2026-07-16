<?php

namespace App\Http\Controllers\Api;

use App\Model\User;
use App\Model\Staff;
use App\Model\ZY_BRRY;
use App\Model\CaseRule;
use App\Model\Department;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use App\Model\PatientInfo;
use App\Model\RuleWordMap;
use Illuminate\Http\Request;
use App\Services\CaseService;
use App\Services\UserService;
use App\Services\ToolsService;
use App\Model\CollectZjzkSearch;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

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
     */
    public function getCaseQuality(Request $request)
    {
        $id = $request->get('id');
        if (!$id) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        $res = [];
        try {
            $caseService = new CaseService();
            $res = $caseService->getCaseQuality($id);
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
     * {id: zyh,bllb: bllb,isHight:1,keyWord:betweenPart}
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
        $res = [];
        try {
            $res = $caseService->getCasePlatform($id, $bllb, $isHight, $keyWord);
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

    /**
     * 获取病程格式化数据
     * @param Request $request
     * @return array
     */
    public function getBlBlsy(Request $request)
    {
        $blbh = $request->get('blbh');
        $zyh = $request->get('zyh');
        $blsy = [];
        if ($blbh) {
            $blsy = EMR_BL_BLSY::query()->where("BLBH", $blbh)->distinct()->get(["SYYS"])->toArray();
            if (empty($blsy)) {
//                $blsy = EMR_BL_BL01::query()->where("BLBH", $blbh)->get(["SXYS as SYYS"])->toArray();
                return ToolsService::returnData(200, [], '获取成功');
            }
            $SYYS = array_unique(array_column($blsy, "SYYS"));
            $staff = Staff::query()->whereIn("code", $SYYS)->get()->toArray();
            $staff = array_column($staff, "ksdm", "code");
            foreach ($blsy as &$item) {
                $item["dep_id"] = intval($staff[$item["SYYS"]] ?? 0);
            }
        } else {
            $brry = ZY_BRRY::query()->where("ZYH", $zyh)->get(["GCYSDM", "ZLZZDM", "ZZYSDM", "MZYS", "ZRYS"])->toArray();
            if ($brry) {
                $res = array_unique(array_filter(array_values($brry[0])));
                $blsy = Staff::query()->whereIn("code", $res)->get(["code as SYYS", "ksdm as dep_id"])->toArray();
                foreach ($blsy as &$v) {
                    $v["dep_id"] = intval($v["dep_id"]);
                }
            }
        }

        return ToolsService::returnData(200, $blsy, $msg ?? '');
    }

    /**
     * 获取患者brry中的基本信息
     * @param Request $request
     * @return array
     */
    public function getBrry(Request $request)
    {
        $zyh = $request->get('zyh');
        $brry = ZY_BRRY::query()->where("ZYH", $zyh)->get()->toArray();
        $resData = $brry[0] ?? [];
        $info = PatientInfo::query()->where("MED_REC_ID", $zyh)->get(["ADA01", "AAC04"])->toArray();
        $resData["ADA01"] = $info[0]["ADA01"] ?? "";
        $resData["AAC04"] = $info[0]["AAC04"] ?? "";

        return ToolsService::returnData(200, $resData, $msg ?? '');
    }

    public function getAdminDepartment(Request $request)
    {
        $token = $request->header('token');
        $user = User::query()->where("token", "=", $token)->get()->toArray();
        $depId = [];
        if ($user) {
            $depId = json_decode($user[0]['dep_id'], true);
        }

        $wordMap = RuleWordMap::query()->where("id", 4001)->first()->toArray();
        $white = !empty($wordMap) ? $wordMap['keyword'] : '';
        $white = str_replace('，', ',', $white);
        $white = explode(',', $white);

        $depIds = UserService::getCurrentUserDep($request);

        // 超管可以查看所有部门
        if ($depIds == 'admin') {
            $dep = Department::query()->get(['dep_id', 'dep_name'])->toArray();
            return ToolsService::returnData(200, $dep, $msg ?? '');
        }
        if (empty($depIds)) {
            return ToolsService::returnData(200, [], $msg ?? '');
        }

        // 非管理员只能看关联的部门
        $dep = Department::query()->whereIn("dep_id", $depIds)->get(['dep_id', 'dep_name'])->toArray();
        return ToolsService::returnData(200, $dep, $msg ?? '');

    }

    /**
     * 获取专家质控列表
     * @param Request $request
     * @return array
     */
    public function geZjZkList(Request $request)
    {
        $AAA28 = $request->post('AAA28', '');    // 住院号
        $AAB01_START = $request->post('AAB01_START', '');    // 入院开始时间
        $AAB01_END = $request->post('AAB01_END', '');        // 入院结束时间
        $AAC01_START = $request->post('AAC01_START', '');    // 出院开始时间
        $AAC01_END = $request->post('AAC01_END', '');        // 出院结束时间
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $orderKey = $request->post('order_key', "");
        $orderKey = $orderKey ?: "AAC01";
        $orderVlue = $request->post('order_value', "");
        $orderVlue = $orderVlue ?: "desc";
        $yzmc = $request->post('yzmc', '');  // 医嘱名称

        $query = ZY_BRRY::query()
            ->select([
                "ZY_BRRY.AAA28",
                "ZY_BRRY.BRXM",
                "ZY_BRRY.AAB01",
                "ZY_BRRY.AAC01",
                "ZY_BRRY.ZYH",
            ])
            ->leftJoin("patient_info", "patient_info.MED_REC_ID", "=", "ZY_BRRY.ZYH")
            ->leftJoin("patient_doctor_info", "patient_doctor_info.AAA28", "=", "ZY_BRRY.ZYH");


        if (!empty($AAA28)) {
            $query = $query->where("ZY_BRRY.AAA28", $AAA28);
        }

        if (!empty($yzmc)) {
            $query = $query->where("yzb.YZMC", "like", "%{$yzmc}%")->leftJoin("yzb", "yzb.ZYH", "=", "ZY_BRRY.ZYH");
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

        $BRKS = $request->post('BRKS', '');  // 出院科室
        if ($BRKS) {
            $query = $query->whereIn("ZY_BRRY.BRKS", $BRKS);
        }

        $BRBQ = $request->post('BRBQ', '');  // 病人病区
        if ($BRBQ) {
            $query = $query->whereIn("ZY_BRRY.BRBQ", $BRBQ);
        }

        $inHospital = $request->post('in_hospital', '');  // 离院方式
        if ($inHospital == "在院") {
            $query = $query->where("ZY_BRRY.AAC01", "0000-00-00 00:00:00");
        } elseif ($inHospital == "出院") {
            $query = $query->whereNotNull("ZY_BRRY.AAC01");
        }

        $AEM01C = $request->post('AEM01C', '');  // 离院方式
        if ($AEM01C) {
            $query = $query->where("patient_info.AEM01C", $AEM01C);
        }

        $blmb = $request->post('blmb', []);  // 病历模版
        if (!empty($blmb) && is_array($blmb)) {
            $bl01 = EMR_BL_BL01::query()->whereIn("ID_TEP", $blmb)->get(["JZHM"])->toArray();
            $zyh = array_column($bl01, "JZHM");
            $query = $query->whereIn("ZY_BRRY.ZYH", $zyh);
        }

        $minDay = $request->post('min_day', 0);  // 住院天数查询
        $maxDay = $request->post('max_day', 0);  // 住院天数查询
        //住院天数(起始天数)
        if (is_numeric($minDay)) $query->where('patient_info.AAC04', '>=', $minDay);
        //住院天数(结束天数)
        if (is_numeric($maxDay)) $query->where('patient_info.AAC04', '<=', $maxDay);

        $minCost = $request->post('min_cost', 0);  // 总费用查询
        $maxCost = $request->post('max_cost', 0);  // 总费用查询
        if (is_numeric($minCost)) $query->where('patient_info.ADA01', '>=', $minCost);
        if (is_numeric($maxCost)) $query->where('patient_info.ADA01', '<=', $maxCost);


//        $ssap = $request->get('ssap', 0);  // 手术安排
//        $fjhss = $request->get('fjhss', 0);  // 非计划手术

        $data = $query->orderBy($orderKey, $orderVlue)->paginate($pageSize, ['*'], 'page', $page)->toArray();

        return ToolsService::returnData(200, ['list' => $data['data'], 'count' => $data['total']]);
    }

    public function collectZjzkSearch(Request $request)
    {
        $id = $request->post('id', '');
        $title = $request->post('title', '');
        $isPublic = $request->post('is_public', '');
        $isDefault = $request->post('is_default', '');
        $data = [
            "user_id" => $request->user()->id,
            "title" => $title,
            "is_public" => $isPublic,
            "is_default" => $isDefault,
        ];
        $filter["AAA28"] = $request->post('AAA28', '');    // 住院号
        $filter["AAB01_START"] = $request->post('AAB01_START', '');    // 入院开始时间
        $filter["AAB01_END"] = $request->post('AAB01_END', '');        // 入院结束时间
        $filter["AAC01_START"] = $request->post('AAC01_START', '');    // 出院开始时间
        $filter["AAC01_END"] = $request->post('AAC01_END', '');        // 出院结束时间
        $filter["BRKS"] = $request->post('BRKS', '');  // 出院科室
        $filter["BRBQ"] = $request->post('BRBQ', '');  // 病人病区
        $filter["min_day"] = $request->post('min_day', 0);  // 住院天数查询
        $filter["max_day"] = $request->post('max_day', 0);  // 住院天数查询
        $filter["min_cost"] = $request->post('min_cost', 0);  // 总费用查询
        $filter["max_cost"] = $request->post('max_cost', 0);  // 住院天数查询
        $filter["ssap"] = $request->post('ssap', 0);  // 手术安排
        $filter["fjhss"] = $request->post('fjhss', 0);  // 非计划手术
        $filter["AEM01C"] = $request->post('AEM01C', 0);  // 离院方式
        $filter["in_hospital"] = $request->post('in_hospital', "");
        $filter["yzmc"] = $request->post('yzmc', "");
        $filter["fymc"] = $request->post('fymc', "");
        $filter["blmb"] = $request->post('blmb', "");
        $filter["order_value"] = $request->post('order_value', "");
        $filter["order_key"] = $request->post('order_key', "");

        $czs = CollectZjzkSearch::query()->where(["user_id" => $request->user()->id, "is_default" => 1])->count();
        if ($czs && $isDefault == 1) {
            return ToolsService::returnData(1, [], "默认收藏条件已设置");
        }
        if ($id) {
            CollectZjzkSearch::query()->where("id", $id)->update($data);
        } else {
            $data["filter_content"] = json_encode($filter, 256);
            CollectZjzkSearch::query()->insert($data);
        }

        return ToolsService::returnData(200, [], "设置成功");
    }

    public function getCollectZjzkSearch(Request $request)
    {
        $collectZjzkSearch = CollectZjzkSearch::query();
        $title = $request->get("title");
        if ($title) {
            $collectZjzkSearch->where("title", "like", "'%{$title}%'");
        }
        $is_public = $request->get("is_public");
        if ($is_public) {
            $collectZjzkSearch->where("is_public", $is_public);
        }
        $collectZjzkSearch = $collectZjzkSearch->get()->toArray();

        return ToolsService::returnData(200, $collectZjzkSearch, "获取成功");
    }

    /**
     * @param Request $request
     * @return array
     * 删除
     */
    public function deleteCollectZjzkSearch(Request $request)
    {
        $id = $request->post('id', '');
        if (!$id) return ToolsService::returnData(4001, [], "参数错误");
        CollectZjzkSearch::query()->where("id", $id)->delete();

        return ToolsService::returnData(200, [], "删除成功");
    }

    /**
     * @param Request $request
     * @return array
     * 获取默认的
     */
    public function getDefaultCollectZjzkSearch(Request $request)
    {
        $czs = CollectZjzkSearch::query()->where(["user_id" => $request->user()['id'], "is_default" => 1])->get()->toArray();
        return ToolsService::returnData(200, $czs ? $czs[0] : [], "获取成功");
    }
}
