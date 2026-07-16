<?php
/*
 * Created by PhpStorm
 * User: moquan
 * Date: 2023/2/8
 * Time: 18:47
 */

namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use App\Model\ErrorRule;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TestController extends Controller
{

    public function testErrorRules(Request $request)
    {
        $page = $request->post('page', 1);
        $reqUrl = 'http://qualityweb.jiankangche.cn:8080/admin/rule/errorList';
        $headers = ['token'=> 'fe763fca4b9dd56cfed32c3dfd287a32'];
        $reqData = ['page'=> $page, 'limit'=> 20];
        $response = Http::withHeaders($headers)->post($reqUrl, $reqData);

        if ($response->ok()) {
            $data = $response->json();
            $res = Db::table('error_rule')->insert($data['p']['list']);
            return ToolsService::returnAdmin(1, $res);
        } else {
            return ToolsService::returnAdmin(0);
        }
    }
}
