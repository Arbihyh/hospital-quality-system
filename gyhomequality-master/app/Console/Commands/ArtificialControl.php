<?php

namespace App\Console\Commands;

use App\Model\CaseRule;
use App\Model\PatientInfo;
use App\Model\CaseQuality;
use App\Model\PatientScore;
use App\Model\RuleWordMap;
use App\Model\User;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class ArtificialControl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'ArtificialControl';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动处理质控数据审核状态';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle() {
        /**
         * 查询质控分数
         */
        $fraction = RuleWordMap::query()->where('id', '=', 4000)->value('keyword');

        /**
         * 查询是否强制错误
         */
        $level = RuleWordMap::query()->where('id', '=', 4002)->value('keyword');

        if (empty($fraction) && empty($level)) {
            echo "未配置质控分数与质控级别";
        }

        $must = [];

        if ($level) {
            $cqMust = [];
            if (!empty($level)) {
                $ruleIdList = CaseRule::query()->where('level','=',$level)->pluck('id')->toArray();
                if (!empty($ruleIdList)) {
                    $cqMust['should'] = [];
                    foreach ($ruleIdList as $value) {
                        $cqMust['should'][] = ['term' => ['rule_id' => $value]];
                    }
                    $cqMust['minimum_should_match'] = $level;
                }
            }

            if (!empty($cqMust)) {
                $must[] = [
                    'nested' => [
                        'path' => 'case_quality',
                        'query' => [
                            'bool' => $cqMust
                        ]
                    ]
                ];
            }
        }

        $page = 1;
        $pageSize = 1000;
        while (true) {
            echo $page . " - " . date('Y-m-d H:i:s') . PHP_EOL;
            $blZkService = new ElasticsearchService('bl_zk_2023');
            $params = $blZkService->clearMust()
                ->queryByMustBatch($must)
                ->paginate($page,$pageSize)
                ->trackTotalHits()
                ->getParams();
            $restful = app('es')->search($params);
            $data = $blZkService->getDataByEs($restful);

            if (!empty($data[0])){
                foreach ($data[0] as $val) {
                    // 事中分数
                    $szfs = CaseQuality::query()
                        ->leftJoin('case_rule','case_quality.rule_id','=','case_rule.id')
                        ->where('case_quality.JZHM','=',$val['ZYH'])
                        ->sum('case_rule.score');
                    $szfs = !empty($szfs) ? 100-$szfs : 100;
                    /**
                     * 查询科室审核人
                     */
                    $reviewUser = User::query()
                        ->where('dep_id', 'LIKE', $val['AAC11N'])
                        ->value('id');

                    // 病案首页分数
                    $homeMinusPoints = PatientScore::query()->where('ZYH','=',$val['ZYH'])->value('score');
                    $homeMinusPoints = !empty($homeMinusPoints) ? $homeMinusPoints : 100;
                    if (!empty($fraction) && $szfs >= $fraction && $homeMinusPoints >= $fraction) {
                        /**
                         * 修改质控审核状态
                         */
                        PatientInfo::query()
                            ->where('MED_REC_ID', '=', $val['ZYH'])
                            ->update([
                                'review_status' => 2,
                                'updated_at' => date('Y-m-d H:i:s'),
                                'review_user' => $reviewUser ?? 0,
                                'review_time' => date('Y-m-d H:i:s'),
                                'is_artificial' => 2
                            ]);
                    }
                }
            } else {
                break;
            }
            $page++;
        }
    }
}
