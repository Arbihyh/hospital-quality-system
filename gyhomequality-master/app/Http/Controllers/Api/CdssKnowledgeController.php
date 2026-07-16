<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\CdssInspection;
use App\Model\CdssMedicine;
use App\Model\CdssOperation;
use App\Model\CdssXyDisease;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 知识库接口控制器
 */
class CdssKnowledgeController extends Controller
{
    /**
     * 类型映射配置
     */
    private const TYPE_MAP = [
        '医疗疾病' => [
            'model' => CdssXyDisease::class,
            'name_field' => 'name',
            'update_time_field' => 'updated_at',
            'has_update_field' => true,
        ],
        '手术操作' => [
            'model' => CdssOperation::class,
            'name_field' => 'operation_name',
            'update_time_field' => 'updated_at',
            'has_update_field' => true,
        ],
        '药品' => [
            'model' => CdssMedicine::class,
            'name_field' => 'name',
            'update_time_field' => 'updated_at',
            'has_update_field' => true,
        ],
        '检验检查' => [
            'model' => CdssInspection::class,
            'name_field' => 'name',
            'update_time_field' => 'updated_at',
            'has_update_field' => true,
        ],
    ];

    /**
     * 查询知识库列表
     * @param Request $request
     * @return array
     */
    public function getKnowledgeList(Request $request)
    {
        // 前端参数嵌套在params对象中，需要从params中获取
        $params = $request->input('params', []);
        
        $type = trim((string)($params['type'] ?? $request->input('type', '')));
        $name = trim((string)($params['name'] ?? $request->input('name', '')));
        $page = intval($params['page'] ?? $request->input('page', 1));
        $pageSize = intval($params['page_size'] ?? $request->input('page_size', 10));

        if ($page < 1) {
            $page = 1;
        }
        if ($pageSize < 1) {
            $pageSize = 10;
        }

        try {
            // 确定要查询的类型列表
            $typesToQuery = [];
            if (empty($type) || strtolower($type) === 'all') {
                // 查询所有类型
                $typesToQuery = array_keys(self::TYPE_MAP);
            } else {
                // 查询指定类型
                if (isset(self::TYPE_MAP[$type])) {
                    $typesToQuery = [$type];
                } else {
                    return ToolsService::returnData(4001, [], '无效的类型参数');
                }
            }

            // 如果只查询一种类型，直接在数据库层面分页和排序，性能更好
            if (count($typesToQuery) === 1) {
                $typeName = $typesToQuery[0];
                $config = self::TYPE_MAP[$typeName];
                $model = $config['model'];
                $nameField = $config['name_field'];
                $updateTimeField = $config['update_time_field'];

                $query = $model::query();

                // 名称模糊查询
                if (!empty($name)) {
                    $query->where($nameField, 'like', '%' . $name . '%');
                }

                // 获取总数
                $totalCount = $query->count();

                // 在数据库层面分页和排序
                $pageStart = ($page - 1) * $pageSize;
                $selectFields = [
                    'id',
                    $nameField . ' as name',
                    $updateTimeField . ' as updated_at'
                ];

                $data = $query->select($selectFields)
                    ->orderBy($updateTimeField, 'desc')
                    ->offset($pageStart)
                    ->limit($pageSize)
                    ->get()
                    ->toArray();

                // 添加类型字段和序号
                foreach ($data as $index => &$item) {
                    $item['type'] = $typeName;
                    $item['serial_number'] = $pageStart + $index + 1;
                    // 格式化更新时间
                    if (!empty($item['updated_at'])) {
                        $item['updated_at'] = date('Y-m-d H:i:s', strtotime($item['updated_at']));
                    } else {
                        $item['updated_at'] = date('Y-m-d H:i:s');
                    }
                }

                return ToolsService::returnData(200, [
                    'count' => $totalCount,
                    'list' => $data,
                ], '');
            }

            // 查询多种类型时，使用 UNION ALL 在数据库层面统一排序和分页。
            // 这样不会因为每种类型预取条数不足，导致深分页返回空数据。
            $totalCount = 0;
            $pageStart = ($page - 1) * $pageSize;
            $unionQuery = null;

            foreach ($typesToQuery as $typeName) {
                $config = self::TYPE_MAP[$typeName];
                $model = $config['model'];
                $nameField = $config['name_field'];
                $updateTimeField = $config['update_time_field'];
                $table = (new $model)->getTable();

                $countQuery = DB::table($table);

                // 名称模糊查询
                if (!empty($name)) {
                    $countQuery->where($nameField, 'like', '%' . $name . '%');
                }

                // 获取总数
                $totalCount += $countQuery->count();

                $typeValue = str_replace("'", "''", $typeName);
                $selectQuery = DB::table($table)->select([
                    'id',
                    DB::raw('CONVERT(`' . $nameField . '` USING utf8mb4) COLLATE utf8mb4_unicode_ci as name'),
                    DB::raw('CAST(`' . $updateTimeField . '` AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as updated_at'),
                    DB::raw("CONVERT('" . $typeValue . "' USING utf8mb4) COLLATE utf8mb4_unicode_ci as type"),
                ]);

                if (!empty($name)) {
                    $selectQuery->where($nameField, 'like', '%' . $name . '%');
                }

                if ($unionQuery === null) {
                    $unionQuery = $selectQuery;
                } else {
                    $unionQuery->unionAll($selectQuery);
                }
            }

            if ($unionQuery === null || $totalCount <= 0) {
                return ToolsService::returnData(200, [
                    'count' => $totalCount,
                    'list' => [],
                ], '');
            }

            $pagedResults = DB::query()
                ->fromSub($unionQuery, 'knowledge_list')
                ->orderBy('updated_at', 'desc')
                ->orderBy('type', 'asc')
                ->orderBy('id', 'desc')
                ->offset($pageStart)
                ->limit($pageSize)
                ->get()
                ->map(function ($item) {
                    return (array)$item;
                })
                ->toArray();

            // 添加序号并格式化更新时间
            foreach ($pagedResults as $index => &$item) {
                $item['serial_number'] = $pageStart + $index + 1;
                if (!empty($item['updated_at'])) {
                    $item['updated_at'] = date('Y-m-d H:i:s', strtotime($item['updated_at']));
                } else {
                    $item['updated_at'] = date('Y-m-d H:i:s');
                }
            }
            unset($item);

            return ToolsService::returnData(200, [
                'count' => $totalCount,
                'list' => $pagedResults,
            ], '');
        } catch (\Exception $e) {
            return ToolsService::returnData(4001, [], $e->getMessage());
        }
    }

    /**
     * 修改知识库数据
     * @param Request $request
     * @return array
     */
    public function updateKnowledge(Request $request)
    {
        $id = intval($request->post('id'));
        $type = $request->post('type', '');
        $name = $request->post('name', '');

        if (!$id) {
            return ToolsService::returnData(4001, [], 'ID参数不能为空');
        }

        if (empty($type)) {
            return ToolsService::returnData(4001, [], '类型参数不能为空');
        }

        if (empty($name)) {
            return ToolsService::returnData(4001, [], '名称参数不能为空');
        }

        if (!isset(self::TYPE_MAP[$type])) {
            return ToolsService::returnData(4001, [], '无效的类型参数');
        }

        try {
            $config = self::TYPE_MAP[$type];
            $model = $config['model'];
            $nameField = $config['name_field'];
            $updateTimeField = $config['update_time_field'];

            // 检查记录是否存在
            $record = $model::query()->where('id', $id)->first();
            if (!$record) {
                return ToolsService::returnData(4001, [], '记录不存在');
            }

            // 更新数据
            $updateData = [
                $nameField => $name,
                $updateTimeField => date('Y-m-d H:i:s'),
            ];

            $model::query()->where('id', $id)->update($updateData);

            return ToolsService::returnData(200, true, '修改成功');
        } catch (\Exception $e) {
            return ToolsService::returnData(4001, [], $e->getMessage());
        }
    }

    /**
     * 删除知识库数据
     * @param Request $request
     * @return array
     */
    public function deleteKnowledge(Request $request)
    {
        $id = intval($request->post('id'));
        $type = $request->post('type', '');

        if (!$id) {
            return ToolsService::returnData(4001, [], 'ID参数不能为空');
        }

        if (empty($type)) {
            return ToolsService::returnData(4001, [], '类型参数不能为空');
        }

        if (!isset(self::TYPE_MAP[$type])) {
            return ToolsService::returnData(4001, [], '无效的类型参数');
        }

        try {
            $config = self::TYPE_MAP[$type];
            $model = $config['model'];

            // 检查记录是否存在
            $record = $model::query()->where('id', $id)->first();
            if (!$record) {
                return ToolsService::returnData(4001, [], '记录不存在');
            }

            // 删除记录
            $model::query()->where('id', $id)->delete();

            return ToolsService::returnData(200, true, '删除成功');
        } catch (\Exception $e) {
            return ToolsService::returnData(4001, [], $e->getMessage());
        }
    }

    /**
     * 查看知识库详情
     * @param Request $request
     * @return array
     */
    public function getKnowledgeDetail(Request $request)
    {
        $id = intval($request->post('id'));
        $type = $request->post('type', '');

        if (!$id) {
            return ToolsService::returnData(4001, [], 'ID参数不能为空');
        }

        if (empty($type)) {
            return ToolsService::returnData(4001, [], '类型参数不能为空');
        }

        if (!isset(self::TYPE_MAP[$type])) {
            return ToolsService::returnData(4001, [], '无效的类型参数');
        }

        try {
            $config = self::TYPE_MAP[$type];
            $model = $config['model'];

            // 查询记录详情
            $record = $model::query()->where('id', $id)->first();

            if (!$record) {
                return ToolsService::returnData(4001, [], '记录不存在');
            }

            // 转换为数组并格式化时间字段
            $data = $record->toArray();

            // 格式化时间字段
            if (isset($data['created_at']) && !empty($data['created_at'])) {
                $data['created_at'] = date('Y-m-d H:i:s', strtotime($data['created_at']));
            }
            if (isset($data['updated_at']) && !empty($data['updated_at'])) {
                $data['updated_at'] = date('Y-m-d H:i:s', strtotime($data['updated_at']));
            }

            // 添加类型字段
            $data['type'] = $type;

            return ToolsService::returnData(200, $data, '');
        } catch (\Exception $e) {
            return ToolsService::returnData(4001, [], $e->getMessage());
        }
    }
}
