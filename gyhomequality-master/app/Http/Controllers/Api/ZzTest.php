<?php

namespace App\Http\Controllers\Api;



use App\Http\Controllers\Controller;
use App\Model\HomeQuality;
use App\Services\HomeQualityService;

class ZzTest extends Controller
{
    public function zzTest()
    {
        $homeArray = HomeQuality::query()->where('error_rule',1452)
            ->where('is_del',0)
            ->whereBetween('AAC01',['2025-01-01 00:00:00','2025-03-31 23:59:59'])
            ->pluck('ZYH','id')->toArray();
        $homeQualityService = new HomeQualityService();
        $qxIds = [];
        foreach ($homeArray as $k=>$v){
            $must = [];
            $must[] = ['error_rule'=>1452];
            $must[] = ['range'=>['AAC01'=>['gte' => '2025-01-01 00:00:00', 'lte' => '2025-03-31 23:59:59']]];
            $data = $homeQualityService->getBlData($must);
            $num = $data[1] ?? 0;
            if ($num == 0) $qxIds[] = $k;
        }
        print_r($qxIds);
        exit();
    }
}
