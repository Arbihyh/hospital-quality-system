<?php

namespace App\Console\Commands;

use App\Model\HomeQuality;
use App\Services\ElasticsearchService;

use Illuminate\Console\Command;

class ZzTest extends Command
{
    protected $signature = 'laravel:ZzTest';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $homeArray = HomeQuality::query()->where('error_rule',1452)
            ->where('is_del',0)
            ->whereBetween('AAC01',['2025-01-01 00:00:00','2025-03-31 23:59:59'])
            ->get(['id','ZYH'])->toArray();

        $homeQualityEs = new ElasticsearchService('home_quality');
        $qxIds = [];
        foreach ($homeArray as $k=>$v){
            $must = [];
            $must[] = ['term'=>['error_rule'=>1452]];
            $must[] = ['term'=>['ZYH'=>$v['ZYH']]];
            $must[] = ['range'=>['AAC01'=>['gte' => '2025-01-01 00:00:00', 'lte' => '2025-03-31 23:59:59']]];

            $params = $homeQualityEs->clearMust()
                ->queryByMustBatch($must)
                ->getParams();
            $restful = app('es')->search($params);
            $data =  $homeQualityEs->getDataByEs($restful);
            if ($data[1] > 0){
                print_r($v['ZYH']);
                exit();
            }
            $num = $data[1] ?? 0;
            if ($num == 0) $qxIds[] = $v['ZYH'];
        }
        print_r($qxIds);
        exit();
    }
}
