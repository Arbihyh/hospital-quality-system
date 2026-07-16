<?php


namespace App\Services;


use App\Model\Menu;
use App\Model\UserMenu;
use App\Model\UserGroup;
use App\Model\UserUserMenu;
use Illuminate\Support\Facades\Log;

class UserMenuService
{
    public static function list($adminGroupId)
    {
        $data = UserGroup::findRole($adminGroupId);
        if (!$data) {
            return [];
        }
        if ($data["role"] == "all") {
            $list = UserMenu::getMenuAll(true);
        } else {
            $list = UserMenu::getUserMenu(json_decode($data["role"], true));
        }
        return self::listData($list);
    }

    public static function groupMenus($adminGroupId)
    {
        $data = UserGroup::findRole($adminGroupId);
        if (!$data) {
            return [];
        }
        if ($data['role'] == 'all') {
            $menuIds = [];
        } else {
            $menuIds = json_decode($data["role"], true);
        }
        return UserMenu::getList($menuIds);
    }

    public static function groupMenuTree($adminGroupId)
    {
        $data = UserGroup::findRole($adminGroupId);
        if (!$data) {
            return [];
        }
        if ($data['role'] == 'all') {
            $menuIds = [];
        } else {
            $menuIds = json_decode($data["role"], true);
        }
        $list = UserMenu::getList($menuIds);
        if(empty($list)) {
            return [];
        }

        return self::menuTreeFrontData($list, 0, true);
    }

    static function menuTreeFrontData( $menus, $pid = 0, $notMenuHidden = false, $deep = 3, $level = 0){
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
                if($level == 0){
                    $newVal = [
                        'path'=> $val['path'],
                        'component'=> 'Layout',
                        'redirect'=> $val['redirect'],
                        'name'=> $val['name'],
                        'alwaysShow'=> $val['always_show']==1 ?? true,
                        'meta'=> ['title'=> $val['title'], 'icon'=> $val['icon']]
                    ];
                } else {
                    $newVal = [
                        'path'=> $val['path'],
                        'name'=> $val['name'],
                        'component'=> $val['component'],
                        'hidden'=> $val['hidden']==1 ?? true,
                        'meta'=> [
                            'title'=> $val['title'],
                            'icon'=> $val['icon'],
                            'keepAlive'=> $val['keep_alive'] ?? 1
                        ]
                    ];
                }
                if($val['parent_id'] == 0) {
                    if(empty($newVal['name']) || $val['path'] == '/' || $val['path'] == $val['redirect']){
                        unset($newVal['name']);
                    }
                    $newVal['children'] = [
                        [
                            'path'=> $val['path'] != $val['redirect'] ? $val['redirect'] : $val['path'],
                            'name'=> $val['name'],
                            'component'=> $val['component'],
                            'hidden'=> $val['hidden']==1 ?? true,
                            'meta'=> [
                                'title'=> $val['title'],
                                'icon'=> $val['icon'],
                                'keepAlive'=> $val['keep_alive'] ?? 1
                            ]
                        ]
                    ];
                }

                $children = self::menuTreeFrontData($array, $val['id'], $notMenuHidden, $deep, $level+1);
                if (!empty($children)) {
                    $newVal['children'] = $children;
                }
                $tree[] = $newVal;
            }
        }
        return $tree;
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
        $list = UserUserMenu::getMenuAll(false);
        return self::listData($list);
    }

    // 全部权限返回
    public static function menuList()
    {
        // 这个条件，暂时先这样，后面确定一下
        $where = [
            ['hidden', '=', 0]
        ];
        $list = UserMenu::getListByWhere($where, ['id', 'parent_id', 'title', 'type']);
        if(empty($list)) {
            return [];
        }

        return self::menuTreeData($list, 0, false);
    }

}
