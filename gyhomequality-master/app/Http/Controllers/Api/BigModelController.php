<?php
/**
 * 空构造，预留
 */
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\BigModelTemplate;
use App\Model\TableDict;
use App\Services\ElasticsearchService;
use App\Services\ToolsService;
use Illuminate\Http\Request;

class BigModelController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getTask(Request $request)
    {
        $id = $request->post("rule_id");
        $MED_REC_ID = $request->post("MED_REC_ID");
        if (!$MED_REC_ID || !$id) {
            return ToolsService::returnData(400, [], '参数错误');
        }
        $id = json_encode($id, 256);
        $bigModel = BigModelTemplate::query()->where("rule_id", $id)->first();

        if (empty($bigModel)) {
            return ToolsService::returnData(400, [], '任务不存在');
        }

        $tableDict = TableDict::query()->get()->toArray();
        $tableDict = array_column($tableDict, null, 'field');

        $task = $bigModel->toArray();
        $dataType = $task["data_type"] ?: "";
        // 根据后台设置的入参获取所有的数据
        $content = "";
        if ($dataType) {
            $dataType = json_decode($dataType, true);
            if ($dataType) {
                foreach ($dataType as $v) {
                    $esName = $v["table"]; // es的索引名称
                    if (empty($tableDict[$esName])) {
                        continue;
                    }
                    $esService = new ElasticsearchService($esName);
                    $params = $esService->clearMust()
                        ->queryByMust(['term' => [$tableDict[$esName]["zyh_field"] => $MED_REC_ID]])
                        ->getParams();
                    $res = app('es')->search($params);
                    $filterData = $esService->getDataByEs($res);
                    $fdata = $filterData[0][0] ?? [];
                    if(empty($fdata)){
                        continue;
                    }
                    foreach ($v["field"] as $f) {
                        $content .= $tableDict[$f]["field_name"].":".$fdata[$f];
                    }
                }
            }
        }

        // 移除最后的逗号
        $content = rtrim($content, ',');
        // 替换模板中的占位符
        $content = str_replace('{params}', $content, $task['content']);
        return ToolsService::returnData(200, $content, '获取成功');
    }
}
