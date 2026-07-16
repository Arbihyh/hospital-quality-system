<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\Admin;
use App\Model\Appeal;
use App\Model\CaseQuality;
use App\Model\CustomTemplateDepartment;
use App\Model\Department;
use App\Model\User;
use App\Services\MenuService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use App\Model\CaseRule;
use Illuminate\Support\Facades\DB;

class CaseController extends Controller
{

    public function getCategory()
    {

        $config = config("confAdmin");
        $qualityCategory = !empty($config['qualityCategory']) ? $config['qualityCategory'] : [];
        return ToolsService::returnAdmin(0, $qualityCategory, $msg ?? '');
    }


    public function getType()
    {
        $qualityCategory = CaseQuality::ruleTypeArray();
        return ToolsService::returnAdmin(0, array_column($qualityCategory, "name"), $msg ?? '');
    }

    public function getDepartmentList()
    {
        $departmentList = Department::getDepartmentList();
        return ToolsService::returnAdmin(0, $departmentList, $msg ?? '');
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
        $where['is_ai'] = $request->get('is_ai', '');
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
    public function examineAppeal(Request $request, $id = 0, $status = 0)
    {
        $id = $request->post('id', 0);
        $type = $request->post('type', 2);
        $status = $request->post('status', 0);
        $describe = $request->post('describe', "");
        $caseDocument = $request->post('case_document', "");
        $caseDocter = $request->post('case_docter', 0);
        $caseDocterMobile = $request->post('case_docter_mobile', "");
        if(!$id || !$status){
            return ToolsService::returnAdmin(401, $data ?? [], '参数错误');
        }
        $admin = $request->user();
        $data['examine_time'] = time();
        $data['case_docter'] = $admin['name'] ?? '';
        $data['status'] = $status;
        $data['reject_content'] = $describe;
        $data['case_document'] = $caseDocument;
        $data['case_docter'] = $caseDocter;
        $data['case_docter_mobile'] = $caseDocterMobile;

        Appeal::query()->where('error_id', $id)->where("quality_type", $type)->update($data);

        return ToolsService::returnAdmin(0, [], '操作成功');
    }

    /**
     * @param Request $request
     * @param CaseRule $caseRule
     * @return array
     * 获取规则列表
     */
    public function getCaseAppeal(Request $request, Appeal $appeal)
    {
        $page = $request->get('page', 1);
        $pageSize = $request->get('page_size', 20);
        $where['appeal_start_time'] = $request->get('appeal_start_time', '');
        $where['appeal_end_time'] = $request->get('appeal_end_time', '');
        $where['level'] = $request->get('level', '');
        $where['AAA28'] = $request->get('AAA28', '');
        $where['AAB01_start_time'] = $request->get('AAB01_start_time', '');
        $where['AAB01_end_time'] = $request->get('AAB01_end_time', '');
        $where['appeal_document'] = $request->get('appeal_document', '');
        $where['appeal_docter'] = $request->get('appeal_docter', '');
        $where['case_docter'] = $request->get('case_docter', '');
        $where['examine_start_time'] = $request->get('examine_start_time', '');
        $where['examine_end_time'] = $request->get('examine_end_time', '');
        $where['defect_content'] = $request->get('defect_content', '');

        try {
            $rule = [];
            $count = $appeal::getCount($where);
            if ($count) {
                $rule = $appeal::getList($page, $pageSize, ['*'], $where);
                foreach ($rule as &$r) {
                    $r['appeal_time'] = date("Y-m-d H:i:s", $r['appeal_time']);
                    $r['examine_time'] = date("Y-m-d H:i:s", $r['examine_time']);
                }
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
        $level = $request->post('level', 2);
        $isAi = $request->post('is_ai', 1);
        $department = $request->post('department', '');
        $department = $this->formatCaseRuleDepartment($department);
        $node = $request->post('node', '');
        $isShizhong = strpos($node, '运行') !== false ? 1 : 0;
        $one_no = $request->post('one_no', '');
        $disease = $request->post('disease', '');
        $triggerCondition = $request->post('trigger_condition', '');
        $judgmentCaliber = $request->post('judgment_caliber', '');
        $qualityBasis = $request->post('quality_basis', '');
        $basisSource = $request->post('basis_source', '');
        $dataSource = $request->post('data_source', '');
        $status = $request->post('status', 1);
        $updatedBy = $this->getCurrentAdminNickname($request);

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
            'level' => $level,
            'is_ai' => $isAi,
            'department' => $department,
            'node' => $node,
            'is_shizhong' => $isShizhong,
            'one_no' => $one_no,
            'disease' => $disease,
            'trigger_condition' => $triggerCondition,
            'judgment_caliber' => $judgmentCaliber,
            'quality_basis' => $qualityBasis,
            'basis_source' => $basisSource,
            'data_source' => $dataSource,
            'status' => $status,
            'updated_by' => $updatedBy,
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
     * 根据科室名称转换为自定义质控科室名称
     *
     * @param mixed $department
     * @return string
     */
    private function formatCaseRuleDepartment($department)
    {
        $departmentNames = $this->normalizeCaseRuleDepartmentNames($department);
        if (empty($departmentNames)) {
            return '';
        }

        $departmentName = $departmentNames[0] ?? '';

        $departmentId = Department::query()
            ->where('dep_name', $departmentName)
            ->value('dep_id');

        if (empty($departmentId)) {
            return $departmentName;
        }

        $customDepartmentName = CustomTemplateDepartment::query()
            ->where('dep_id', 'like', '%' . $departmentId . '%')
            ->value('name');

        return !empty($customDepartmentName) ? $customDepartmentName : $departmentName;
    }

    /**
     * 规范病例规则科室名称参数
     *
     * @param mixed $department
     * @return array
     */
    private function normalizeCaseRuleDepartmentNames($department)
    {
        if (is_array($department)) {
            $departmentNames = $department;
        } else {
            $department = trim((string) $department);
            if ($department === '') {
                return [];
            }

            $departmentNames = json_decode($department, true);
            if (!is_array($departmentNames)) {
                $departmentNames = explode(',', $department);
            }
        }

        return array_values(array_unique(array_filter(array_map(function ($departmentName) {
            return trim((string) $departmentName);
        }, $departmentNames), function ($departmentName) {
            return $departmentName !== '';
        })));
    }

    /**
     * @param Request $request
     * @return string
     * 获取当前登录管理员昵称
     */
    private function getCurrentAdminNickname(Request $request)
    {
        $token = $request->header('token');
        if (empty($token)) {
            return '';
        }

        $adminData = Admin::findWhereToken($token);
        if (empty($adminData)) {
            return '';
        }

        return !empty($adminData['realname']) ? $adminData['realname'] : ($adminData['name'] ?? '');
    }

    /**
     * 修改质控规则的状态
     */
    public function updateCaseRuleStatus(Request $request, CaseRule $caseRule)
    {

        $id = $request->post('id', '');
        $status = $request->post('status', '0');
        DB::table('case_rule')->where('id', $id)->update([
            'status' => $status,
            'updated_by' => $this->getCurrentAdminNickname($request),
        ]);
        return ToolsService::returnAdmin(0, [], '');

    }

    /**
     * 修改质控规则的是否同步到事中质控
     */
    public function setCaseRuleshizhong(Request $request, CaseRule $caseRule)
    {

        $id = $request->post('id', '');
        $status = $request->post('status', '0');
        DB::table('case_rule')->where('id', $id)->update([
            'is_shizhong' => $status,
            'updated_by' => $this->getCurrentAdminNickname($request),
        ]);
        return ToolsService::returnAdmin(0, [], '');

    }

}
