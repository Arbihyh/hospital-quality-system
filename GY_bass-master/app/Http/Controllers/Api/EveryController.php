<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Pheanstalk\Pheanstalk;

class EveryController extends Controller
{
    /**
     * @param Request $request
     * @return array
     */
    public function everyDay(Request $request){
        $start = date('Ymd',strtotime('-1 day'));
        $beanstalkd = Pheanstalk::create(env('BEANSTALKD') ?? 'localhost')->useTube('every');
        $beanstalkd->put(json_encode(['start' => $start]));
        return ToolsService::returnData(200,['start'=>$start], $msg ?? '');
    }

}
