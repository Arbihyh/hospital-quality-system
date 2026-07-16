<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Model\IndexCatalog;
use App\Model\IndexCatalogRg;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use App\Services\RadioService;


class IndexCatalogController extends Controller
{
    /**
     * @param Request $request
     * @param RadioService $radioService
     * @return array
     * 绩效考核列表
     */
    public function add(Request $request)
    {

        $params = $request->post();
        if (empty($params)) {
            return ToolsService::returnData(4001, '参数不能为空', $msg ?? '');
        }

        $params['created_at'] = date('Y-m-d H:i:s');
        //$re = (new IndexCatalog())->insert($params);

        try {
            $info = IndexCatalog::query()->where('pid', $params['pid'])
                ->where('name', $params['name'])
                ->first();
            if (!empty($info)) {
                $res = IndexCatalog::query()->where('id', $info->id)->update($params);
            } else {
                $res = IndexCatalog::query()->insert($params);
            }

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

    public function info(Request $request)
    {
        $id = $request->get('id');
        $isParent = $request->get('is_parent');
        $isChildren = $request->get('is_children');

        if (!$id) {
            return ToolsService::returnData(4001, '参数不能为空', $msg ?? '');
        }

        $info = IndexCatalog::find($id);
        if (empty($info)) {
            return ToolsService::returnData(4001, '该信息未找到', $msg ?? '');
        }


        $info = $info->toArray();

        if ($isChildren) {
            $info['children'] = [];
            $list = IndexCatalog::query()->where('pid', $id)->get();
            foreach ($list as $v) {
                $info['children'][] = ['id' => $v->id, 'name' => $v->name];
            }
        }


        if ($isParent) {
            $pid = $info['pid'];

            $info['parent'] = [];
            while (1) {

                if ($pid == 0) {
                    break;
                }

                $re = IndexCatalog::find($pid);
                if (empty($re)) {
                    break;
                }
                $pid = $re->pid;
                $info['parent'][] = ['id' => $re->id, 'name' => $re->name];
            }
        }

        return ToolsService::returnData(200, $info, $msg ?? '');
    }


    public function lists(Request $request)
    {

        $name = $request->get('name');
        $fenzi = $request->get('fenzi');
        $fenmu = $request->get('fenmu');

        $where = [];

        if (!empty($name)) {
            $where[] = ['name', 'like', "%$name%"];
        }

        if (!empty($fenzi)) {
            $where[] = ['fenzi', 'like', "%$fenzi%"];
        }
        if (!empty($fenmu)) {
            $where[] = ['fenmu', 'like', "%$fenmu%"];
        }


        $list = IndexCatalog::query()
            ->where($where)
            ->orderBy('sort_num', 'asc')
            ->get();

        $rglist = IndexCatalogRg::query()
            ->where($where)
            ->where('pid', '>', 0)
            ->orderBy('sort_num', 'asc')
            ->get();

        //将rglist合并到list中
        foreach ($rglist as $v) {
            $list[] = $v;
        }

        // 合并后按sort_num重新排序
        $listArray = $list->toArray();
        usort($listArray, function ($a, $b) {
            return ($a['sort_num'] ?? 0) <=> ($b['sort_num'] ?? 0);
        });

        $data = [];
        if (!empty($listArray)) {
            $data = $this->list_to_tree($listArray);
        }


        return ToolsService::returnData(200, $data, $msg ?? '');
    }

    /**
     * 更新人工指标自定义数据
     * 
     * 请求参数示例:
     * {
     *     "index_name": "rgzb_example",
     *     "custom_data": [
     *         {
     *             "month": 1,
     *             "year": 2024,
     *             "fenzi": 100,
     *             "fenmu": 120
     *         },
     *         {
     *             "month": 2,
     *             "year": 2024,
     *             "fenzi": 95,
     *             "fenmu": 110
     *         }
     *     ]
     * }
     * 
     * @param Request $request
     * @return array
     */
    public function updateCustomData(Request $request)
    {
        $indexName = $request->post('index_name');
        $customData = $request->post('custom_data');

        if (empty($indexName)) {
            return ToolsService::returnData(4001, '指标名称不能为空', '');
        }

        if (empty($customData) || !is_array($customData)) {
            return ToolsService::returnData(4001, '自定义数据不能为空且必须是数组', '');
        }

        try {
            // 获取现有的自定义数据
            $indexCatalog = IndexCatalogRg::query()
                ->where('index_name', $indexName)
                ->first();

            if (!$indexCatalog) {
                return ToolsService::returnData(4001, '未找到对应的指标', '');
            }

            // 获取现有的 custom_data
            $existingData = $indexCatalog->custom_data;
            if (is_string($existingData)) {
                $existingData = json_decode($existingData, true) ?: [];
            } elseif (!is_array($existingData)) {
                $existingData = [];
            }

            // 将现有数据转换为以 year-month 为键的关联数组
            $dataMap = [];
            foreach ($existingData as $item) {
                if (isset($item['year']) && isset($item['month'])) {
                    $key = $item['year'] . '-' . $item['month'];
                    $dataMap[$key] = $item;
                }
            }

            // 更新或添加新数据
            foreach ($customData as $newItem) {
                if (isset($newItem['year']) && isset($newItem['month'])) {
                    $key = $newItem['year'] . '-' . $newItem['month'];
                    $dataMap[$key] = $newItem;
                }
            }

            // 将关联数组转换回索引数组
            $mergedData = array_values($dataMap);

            // 更新数据库
            $result = IndexCatalogRg::query()
                ->where('index_name', $indexName)
                ->update(['custom_data' => $mergedData]);

            if ($result) {
                return ToolsService::returnData(200, [], '更新成功');
            } else {
                return ToolsService::returnData(4001, '更新失败', '');
            }
        } catch (\Exception $e) {
            return ToolsService::returnData(4001, '更新失败', $e->getMessage());
        }
    }


    /**
     * 获取人工指标自定义数据
     * 
     * custom_data 数据格式示例:
     * [
     *     {
     *         "month": 1,
     *         "year": 2024,
     *         "fenzi": 100,
     *         "fenmu": 120
     *     },
     *     {
     *         "month": 2,
     *         "year": 2024,
     *         "fenzi": 95,
     *         "fenmu": 110
     *     }
     * ]
     * 
     * @param Request $request
     * @return array
     */
    public function getCustomData(Request $request)
    {
        $indexName = $request->get('index_name');
        $year = $request->get('year');
        $AAC11N = $request->get('AAC11N');
        $AEE03 = $request->get('AEE03');
        $cysj_start = $request->get('cysj_start');
        $cysj_end = $request->get('cysj_end');
        $isExport = $request->get('is_export');

        if (empty($indexName)) {
            return ToolsService::returnData(4001, [], '指标名称不能为空');
        }

        try {
            // 获取人工指标配置
            $indexCatalog = IndexCatalogRg::query()
                ->where('index_name', $indexName)
                ->first();

            if (!$indexCatalog) {
                return ToolsService::returnData(4001, [], '未找到指标配置');
            }

            // 解析custom_data
            $customData = $indexCatalog->custom_data;
            if (is_string($customData)) {
                $customData = json_decode($customData, true);
            }

            /* if (empty($customData)) {
                return ToolsService::returnData(200, [], '暂无数据');
            } */

            // 获取当前年份和当前月份
            $currentYear = date('Y');
            $currentMonth = (int)date('m');

            // 初始化数据结构：从当前年1月到当前月
            $data = [];
            for ($month = 1; $month <= $currentMonth; $month++) {
                $data[$month] = [
                    'fenzi' => 0,
                    'fenmu' => 0,
                    'radio' => 0,
                    'month' => $month,
                    'year' => $currentYear,
                    'status' => $indexCatalog->status == 1 ? '质控中' : '成功',
                    'update_time' => $indexCatalog->quality_time ? date("Y-m-d", $indexCatalog->quality_time) : '--'
                ];
            }

            // 用custom_data填充有数据的月份
            if (!empty($customData)) {
                foreach ($customData as $item) {
                    $itemYear = intval($item['year'] ?? 0);
                    $itemMonth = intval($item['month'] ?? 0);

                    // 只填充当前年份且在1月到当前月范围内的数据
                    if ($itemYear == $currentYear && $itemMonth >= 1 && $itemMonth <= $currentMonth) {
                        $fenzi = floatval($item['fenzi'] ?? 0);
                        $fenmu = floatval($item['fenmu'] ?? 0);
                        $data[$itemMonth] = [
                            'fenzi' => $fenzi,
                            'fenmu' => $fenmu,
                            'radio' => $fenmu > 0 ? number_format(($fenzi / $fenmu) * 100, 2) : 0,
                            'month' => $itemMonth,
                            'year' => $itemYear,
                            'status' => $indexCatalog->status == 1 ? '质控中' : '成功',
                            'update_time' => $indexCatalog->quality_time ? date("Y-m-d", $indexCatalog->quality_time) : '--'
                        ];
                    }
                }
            }

            $resData = array_values($data);

            // 计算平均值
            $fenzi = array_sum(array_column($resData, 'fenzi'));
            $fenmu = array_sum(array_column($resData, 'fenmu'));
            $r = $fenmu > 0 ? round($fenzi / $fenmu, 4) * 100 : 0;
            $r = number_format($r, 2);

            // 添加平均值记录
            $count = [
                'fenzi' => $fenzi,
                'fenmu' => $fenmu,
                'radio' => $r,
                'month' => '',
                'year' => '平均值',
                'status' => $indexCatalog->status == 1 ? '质控中' : '成功',
                'update_time' => $indexCatalog->quality_time ? date("Y-m-d", $indexCatalog->quality_time) : '--'
            ];
            $resData[] = $count;

            // 导出功能
            if ($isExport) {
                $exportData[] = ['时间', $indexCatalog->name . '分子', $indexCatalog->name . '分母', '指标合格率'];
                foreach ($resData as $key => $val) {
                    if ($val['month']) {
                        $yearStr = sprintf("\t%04d/%02d", $val['year'], $val['month']);
                    } else {
                        $yearStr = '平均值';
                    }
                    $exportData[$key + 1] = [
                        $yearStr,
                        $val["fenzi"],
                        $val["fenmu"],
                        $val["radio"] . "%",
                    ];
                }

                $csv = new \App\Services\CsvService();
                $csv->filename = $csv->charset($indexCatalog->name . '--指标', 'UTF-8');
                return $csv->export($exportData);
            }

            return ToolsService::returnData(200, $resData, '成功');
        } catch (\Exception $e) {
            return ToolsService::returnData(4001, [], '获取失败：' . $e->getMessage());
        }
    }

    public function edit(Request $request)
    {
        $params = $request->post();
        if (empty($params)) {
            return ToolsService::returnData(4001, '参数不能为空', $msg ?? '');
        }

        $re = IndexCatalog::query()->where('id', $params['id'])->update($params);

        return ToolsService::returnData(200, [], $msg ?? '');
    }


    public function del(Request $request)
    {
        $id = $request->post('id');
        if (empty($id)) {
            return ToolsService::returnData(4001, '参数不能为空', $msg ?? '');
        }

        $re = IndexCatalog::query()->where('id', $id)->first();
        if (empty($re)) {
            return ToolsService::returnData(4001, '该信息未找到', $msg ?? '');
        }

        $cnt = IndexCatalog::query()->where('pid', $re->id)->count();
        if ($cnt) {
            return ToolsService::returnData(4001, '请先删除该分类下子目录', $msg ?? '');
        }

        $re->delete();

        return ToolsService::returnData(200, [], $msg ?? '');
    }


    /**
     * 列表转树形
     *
     * @param array $list 列表数组
     * @param string $pk 主键名称
     * @param string $pid 父键名称
     * @param int $root 根节点id
     * @param string $child 子节点名称
     *
     * @return array
     */
    public function list_to_tree($list = [], $pk = 'id', $pid = 'pid', $root = 0, $child = 'children')
    {
        $tree = [];
        $refer = [];
        foreach ($list as $k => $v) {
            $refer[$v[$pk]] = &$list[$k];
        }
        foreach ($list as $key => $val) {
            $parent_id = 0;
            if (isset($val[$pid])) {
                $parent_id = $val[$pid];
            }
            if ($root == $parent_id) {
                $tree[] = &$list[$key];
            } else {
                if (isset($refer[$parent_id])) {
                    $parent = &$refer[$parent_id];
                    $parent[$child][] = &$list[$key];
                }
            }
        }
        return $tree;
    }


    public function getOptions()
    {
        $list = IndexCatalog::all();

        $data = [];
        foreach ($list as $item) {

            if ($item->pid == 0) {
                $data['name'][] = $item->name;
            }

            if (!empty($item->fenzi_name)) {

                $data['fenzi'][] = $item->fenzi_name;
            }

            if (!empty($item->fenmu_name)) {

                $data['fenmu'][] = $item->fenmu_name;
            }
        }

        return ToolsService::returnData(200, $data, $msg ?? '');
    }
}
