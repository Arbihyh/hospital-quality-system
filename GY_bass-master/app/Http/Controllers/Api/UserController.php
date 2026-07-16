<?php
/*
 * Created by PhpStorm
 * User: moquan
 * Date: 2023/2/9
 * Time: 17:54
 */

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Services\MenuService;
use App\Services\ToolsService;
use App\Services\UserMenuService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function groupMenuTree(Request $request)
    {
        $id = $request->user()['group_id'];
        $data = UserMenuService::groupMenuTree($id);
        return ToolsService::returnData(200, $data);
    }

    public function userInfo(Request $request){
        $user = $request->user();
        // 这先去掉,不用返回可访问接口列表了
//        $menus = UserMenuService::groupMenus($user['group_id']);
        $data = [
            'roles' => $user['group_id'],
            'introduction' => '',
            'avatar' => 'https://wpimg.wallstcn.com/f778738c-e4f8-4870-b634-56703b4acafe.gif',
            'name' => $user['name'],
            'nickname' => $user['name'],
            'accessApi' => []
        ];
        return ToolsService::returnData(200, $data);
    }
}
