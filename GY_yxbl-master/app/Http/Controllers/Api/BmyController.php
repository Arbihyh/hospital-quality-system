<?php

namespace App\Http\Controllers\Api;


use App\Console\Commands\BmyQuality;
use App\Http\Controllers\Controller;
use App\Services\ToolsService;
use Illuminate\Http\Request;

class BmyController extends Controller
{

    //编码员重新质控
    public function reCheck(Request $request)
    {

        //获取病案号
        $ZYH = $request->post('ZYH','');
        $result = (new BmyQuality())->reCheck($ZYH);
        return ToolsService::returnData(200,[]);
    }
}