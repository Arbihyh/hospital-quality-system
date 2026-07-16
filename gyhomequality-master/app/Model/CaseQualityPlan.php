<?php


namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class CaseQualityPlan extends Model
{
    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'case_quality_plan';

    /**
     * 指示是否自动维护时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * 获取质控计划列表
     *
     * @param int $page 页码
     * @param int $pageSize 每页条数
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param string $addUser 添加人
     * @param string $title 标题
     * @return array
     */
    public static function getList($page=1, $pageSize=10, $startTime='', $endTime='', $addUser='', $title='', $progress=0)
    {
        $query = self::query();
        if ($startTime) {
            $startTime = date('Y-m-d 00:00:00', strtotime($startTime));
            $endTime = date('Y-m-d 23:59:59', strtotime($endTime));
            $query->whereBetween('created_at', [$startTime, $endTime]);
        }
        if ($addUser) {
            $query->where('add_user', $addUser);
        }
        if ($title) {
            $query->where('title', 'like', '%'.$title.'%');
        }
        $total = $query->count();
        // ->offset(($page - 1) * $pageSize)->limit($pageSize)
        $list = $query->orderBy('id', 'desc')->get();
        // 统计每个质控计划包含的住院号总数和review_status=2的数量
        $planIds = $list->pluck('id')->toArray();
        $zyhCounts = [];
        $reviewedCounts = [];
        if (!empty($planIds)) {
            // 查询每个计划下的zyh总数
            $zyhCounts = \App\Model\CaseQualityPlanList::query()
                ->selectRaw('plan_id, COUNT(zyh) as total')
                ->whereIn('plan_id', $planIds)
                ->groupBy('plan_id')
                ->pluck('total', 'plan_id')
                ->toArray();

            // 查询每个计划下review_status=2的zyh数量
            $planZyh = \App\Model\CaseQualityPlanList::query()
                ->select('plan_id', 'zyh')
                ->whereIn('plan_id', $planIds)
                ->get()
                ->groupBy('plan_id');

            foreach ($planZyh as $planId => $zyhList) {
                $zyhArr = $zyhList->pluck('zyh')->toArray();
                if (!empty($zyhArr)) {
                    $count = \App\Model\ZY_BRRY::query()
                        ->whereIn('ZYH', $zyhArr)
                        ->where('review_status', 2)
                        ->count();
                    $reviewedCounts[$planId] = $count;
                } else {
                    $reviewedCounts[$planId] = 0;
                }
            }
        }

        // 将统计结果合并到$list
        $list = $list->toArray();
        $addUser = \App\Model\User::query()->whereIn('id', array_column($list, 'add_user'))->pluck('realname', 'id')->toArray();
        foreach ($list as &$item) {
            $planId = $item['id'];
            $item['add_user'] = $addUser[$item['add_user']] ?? '';
            $item['created_at'] = strtotime($item['created_at']);
            $item['zyh_total'] = $zyhCounts[$planId] ?? 0;
            $item['reviewed_total'] = $reviewedCounts[$planId] ?? 0;
            $item['progress'] = $item['zyh_total'] > 0 ? round($item['reviewed_total'] / $item['zyh_total'] * 100, 2) : 0;
            $item['progress'] = $item['progress'] > 100 ? 100 : $item['progress'];
        }

        if ($progress == 1) {
            $list = array_filter($list, function ($item) {
                return $item['progress'] >= 100;
            });
        }
        if ($progress == 2) {
            $list = array_filter($list, function ($item) {
                return $item['progress'] < 100;
            });
        }
        return [
            'list' => $list,
            'total' => $total,
        ];
    }
}
