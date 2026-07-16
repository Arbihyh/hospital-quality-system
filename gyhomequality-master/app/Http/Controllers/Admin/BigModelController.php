<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\BigModelTaskName;
use App\Model\CaseRule;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use App\Model\BigModelTemplate;
use App\Model\SelectMblb;
use Illuminate\Support\Facades\DB;

class BigModelController extends Controller
{

    public function getTaskName(Request $request)
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

        return ToolsService::returnAdmin(0, $category_tree, $msg ?? '');

    }

    public function getInputSelect(Request $request)
    {
        $data = [
            [
                'key' => 'RYJL',
                'name' => '手术类',
                'child' => [
                    [
                        'key' => 'YZQX',
                        'name' => '手术记录',
                        'child' => [
                            ['key' => "SZ", 'name' => '术者'],
                            ['key' => "SSMC", 'name' => '手术名称'],
                        ]
                    ]
                ]
            ],
            [
                'key' => 'RYJL',
                'name' => '入院记录',
            ],
            [
                'key' => 'CYJL',
                'name' => '出院记录',
            ],
        ];

        return ToolsService::returnAdmin(0, $data);
    }

    public function getCaseRule(Request $request)
    {
        $rule = CaseRule::query()->where('is_ai',3)->get()->toArray();
        return ToolsService::returnAdmin(0, $rule);
    }

    public function getTemplList(Request $request)
    {
        $title = $request->post('title', []);
        $type = $request->post('type', []);
        $status = $request->post('status', '');
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 20);
        $obj = BigModelTemplate::query();
        if (!empty($title)) {
            $obj = $obj->where('title', 'like', "%".$title."%");
        }
        if (is_array($type) && !empty($type)) {
            $obj = $obj->whereIn('type', $type);
        }
        if ($status !== '' && $status !== null) {
            $obj = $obj->where('status', $status);
        }
        $count = $obj->count();
        $pageStart = ($page - 1) * $pageSize;
        $data = $obj->orderBy("id", "desc")->offset($pageStart)->LIMIT($pageSize)->get()->toArray();


        $type = [1 => "病历质控", 2 => "病例生成", 3 => "评审指标"];
        foreach ($data as &$v) {
            $v['app_name'] = $v["title"] ?? '';
            $v["task_type"] = $type[$v["type"]] ?? "";
            $v['data_type'] = json_decode($v['data_type'], 256);
        }

        return ToolsService::returnAdmin(0, ['count' => $count, 'list' => $data], $msg ?? '');

    }

    /**
     * @param Request $request
     * @return array
     * 设置模板
     */
    public function setTemplate(Request $request)
    {
        $id = $request->post("id"); // 关联规则
        $ruleId = $request->post("rule_id", 0); // 关联规则
        $dateType = $request->post("data_type", []); // 模型入参，参数输入
        $status = $request->post("status", 1); // 状态
        $content = $request->post("content", ''); // 提示内容
        $type = $request->post("type", ''); // 任务类型，1病例质控、2病例生成、3评审指标
        $mblb = $request->post("mblb", ''); // 模型类型
        if (empty($ruleId) || empty($content) || empty($dateType)) {
            return ToolsService::returnAdmin(1, [], $msg ?? "");
        }

        DB::beginTransaction();
        try {
            $oldTemplate = null;
            $oldRuleId = 0;
            if (!empty($id)) {
                $oldTemplate = BigModelTemplate::query()->where('id', '=', $id)->first();
                if (!empty($oldTemplate) && (int)$oldTemplate->type === 1) {
                    $oldRuleId = $this->parseTemplateRuleId($oldTemplate->rule_id);
                }
            }

            $title = "";
            $templateRuleId = $ruleId;

            // 获取任务名称，不同的任务类型，任务名称获取来源不一样
            if ((int)$type === 1) {
                $ruleNotice = trim((string)$ruleId);
                if ($ruleNotice === '') {
                    DB::rollBack();
                    return ToolsService::returnAdmin(1, [], '规则内容不能为空');
                }

                if ($oldRuleId > 0) {
                    CaseRule::query()->where('id', '=', $oldRuleId)->update([
                        'category' => '病历文书',
                        'title' => '病历文书',
                        'notice' => $ruleNotice,
                        'score' => 0,
                        'status' => 1,
                        'type' => '内涵性',
                        'level' => 2,
                        'is_ai' => 3,
                        'is_shizhong' => 1,
                        'node' => '运行、终末',
                        'one_no' => 0,
                        'updated_at' => date("Y-m-d H:i:s"),
                    ]);
                    $newRuleId = $oldRuleId;
                } else {
                    $newRuleId = ((int)CaseRule::query()->lockForUpdate()->max('id')) + 1;
                    CaseRule::query()->insert([
                        'id' => $newRuleId,
                        'category' => '病历文书',
                        'title' => '病历文书',
                        'notice' => $ruleNotice,
                        'score' => 0,
                        'status' => 1,
                        'type' => '内涵性',
                        'level' => 2,
                        'is_ai' => 3,
                        'is_shizhong' => 1,
                        'node' => '运行、终末',
                        'one_no' => 0,
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                    ]);
                }
                $title = $ruleNotice;
                $templateRuleId = $newRuleId;
            } elseif ((int)$type === 2) {
                $taskName = BigModelTaskName::query()->whereIn("id", [$ruleId[0] ?? 0, $ruleId[1] ?? 0])->get()->toArray();
                $title = implode('-', array_column($taskName, "name"));
                $templateRuleId = json_encode($ruleId, JSON_UNESCAPED_UNICODE);
            } else {
                $templateRuleId = json_encode($ruleId, JSON_UNESCAPED_UNICODE);
            }

            $data = [
                'title' => $title,
                'type' => $type,
                'rule_id' => $templateRuleId,
                'data_type' => json_encode($dateType, 256),
                'mblb' => $mblb,
                'status' => $status,
                'content' => $content ?? '',
                "created_at" => date("Y-m-d H:i:s"),
            ];
            if (empty($id)) {
                $data = BigModelTemplate::query()->insert($data);
            } else {
                $data["updated_at"] = date("Y-m-d H:i:s");
                $data = BigModelTemplate::query()->where('id', '=', $id)->update($data);
            }

            DB::commit();
            return ToolsService::returnAdmin(0, $data);
        } catch (\Exception $e) {
            DB::rollBack();
            return ToolsService::returnAdmin(1, [], $e->getMessage());
        }
    }


    /**
     * @param Request $request
     * @return array
     * 删除设置模板
     */
    public function delTemplate(Request $request)
    {
        $id = $request->post("id"); // 关联规则
        DB::beginTransaction();
        try {
            $template = BigModelTemplate::query()->where('id', '=', $id)->first();
            if (!empty($template) && (int)$template->type === 1) {
                $ruleId = $this->parseTemplateRuleId($template->rule_id);
                if ($ruleId > 0) {
                    CaseRule::query()->where('id', '=', $ruleId)->delete();
                }
            }
            DB::table('big_model_template')->where(['id' => $id])->delete();
            DB::commit();
            return ToolsService::returnAdmin(0, []);
        } catch (\Exception $e) {
            DB::rollBack();
            return ToolsService::returnAdmin(1, [], $e->getMessage());
        }
    }

    public function selectMblb(Request $request)
    {
        $data = SelectMblb::query()->get()->toArray();
        return ToolsService::returnAdmin(0, $data);
    }

    public function setStatus(Request $request)
    {
        $id = $request->post("id"); // 关联规则
        $status = $request->post("status"); // 状态
        $data = BigModelTemplate::query()->where(['id' => $id])->update(['status' => $status]);
        return ToolsService::returnAdmin(0, $data);
    }

    private function parseTemplateRuleId($ruleId)
    {
        if (is_numeric($ruleId)) {
            return (int)$ruleId;
        }

        $decodedRuleId = json_decode($ruleId, true);
        if (is_numeric($decodedRuleId)) {
            return (int)$decodedRuleId;
        }

        return 0;
    }


}
