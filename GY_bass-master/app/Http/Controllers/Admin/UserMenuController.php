<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MenuService;
use App\Services\ToolsService;
use App\Services\UserMenuService;
use Illuminate\Http\Request;

class UserMenuController extends Controller
{
    public function list(Request $request)
    {
        $id = $request->user()['group_id'];
        $data = MenuService::list($id);
        return ToolsService::returnAdmin(0, $data);
    }

    public function groupMenuTree(Request $request)
    {
        $id = $request->user()['group_id'];
        $data = MenuService::groupMenuTree($id);
        return ToolsService::returnAdmin(0, $data);
    }

    public function rbacList()
    {
        $data = MenuService::rbacList();
        return ToolsService::returnAdmin(0, $data);
    }

    public function menuList()
    {
        $data = UserMenuService::menuList();
        return ToolsService::returnAdmin(0, $data);
    }

    public function userMenuRbacList()
    {
        $data = UserMenuService::rbacList();
        return ToolsService::returnAdmin(0, $data);
    }
}
