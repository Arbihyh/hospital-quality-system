<?php


namespace App\Services;


use App\Model\AdminGroup;
use App\Model\AdminMenu;
use App\Model\Menu;
use App\Model\MenuList;

class MenuService
{
    public static function list($adminGroupId)
    {
        $data = AdminGroup::findRole($adminGroupId);
        if (!$data) {
            return [];
        }
        if ($data["role"] == "all") {
            $list = MenuList::getMenuAll(true);
        } else {
            $list = MenuList::getMenuList(json_decode($data["role"]));
        }
        return self::listData($list);
    }

    public static function groupMenus($adminGroupId)
    {
        $data = AdminGroup::findRole($adminGroupId);
        if (!$data) {
            return [];
        }
        if ($data['role'] == 'all') {
            $menuIds = [];
        } else {
            $menuIds = json_decode($data["role"]);
        }
        return AdminMenu::getList($menuIds);
    }

    public static function groupMenuTree($adminGroupId)
    {
        $data = AdminGroup::findRole($adminGroupId);
        if (!$data) {
            return [];
        }
        if ($data['role'] == 'all') {
            $menuIds = [];
        } else {
            $menuIds = json_decode($data["role"]);
        }
        $list = AdminMenu::getList($menuIds);
        if(empty($list)) {
            return [];
        }

        return self::menuTreeData($list, 0, true);
    }

    static function menuTreeData( $menus, $pid = 0, $notMenuHidden = false, $deep = 3, $level = 0){
        $tree = [];
        $array = array_values($menus);
        foreach ($array as $key => $val) {
            if($deep == $level) {
                break;
            }
            if( $val['parent_id'] == $pid ) {
                if($notMenuHidden && $val['type'] != 1){
                    continue;
                }
                $children = self::menuTreeData($array, $val['id'], $notMenuHidden, $deep, $level+1);
                $val['children'] = $children;
                $tree[] = $val;
            }
        }
        return $tree;
    }
    // 权限列表
    public static function listData($list)
    {
        $menu = Menu::getMenu(array_unique(array_column($list, "menu_id")));
        $menuData = ToolsService::arrayColumns($menu, "icon,name", 'id');
        $list = ToolsService::arrayColumns($list, "id,name,url,is_show,menu_id");
        foreach ($list as $k => $v) {
            $menuId = $v["menu_id"];
            unset($v["menu_id"]);
            $menuData[$menuId]["child"][] = $v;
        }
        return array_values($menuData);
    }

    // 全部权限返回
    public static function rbacList()
    {
        $list = MenuList::getMenuAll(false);
        return self::listData($list);
    }

    // 全部权限返回
    public static function menuList()
    {
        $list = AdminMenu::getList([], ['id', 'parent_id', 'name', 'type']);
        if(empty($list)) {
            return [];
        }

        return self::menuTreeData($list, 0, false);
    }

}
