<?php


namespace App\Services;


use App\Model\Menu;
use App\Model\User;
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

    public static function groupMenuTree($params)
    {
        $adminGroupId = $params['group_id'];
        $data = UserGroup::findRole($adminGroupId);
        if (!$data) {
            return [];
        }
        if ($data['role'] == 'all') {
            $menuIds = [];
        } else {
            // $menuIds = explode(',', $data['role']);
            $menuIds = json_decode($data["role"], true);
        }

        $list = UserMenu::getList($menuIds);
        if (empty($list)) {
            return [];
        }

        return self::menuTreeFrontData($list, 0, true);
    }

    static function menuTreeFrontData($menus, $pid = 0, $notMenuHidden = false, $deep = 3, $level = 0)
    {
        $tree = [];
        $array = array_values($menus);
        foreach ($array as $key => $val) {
            if ($deep == $level) {
                break;
            }
            if ($val['parent_id'] == $pid) {
                if ($notMenuHidden && $val['type'] != 1) {
                    continue;
                }
                if ($level == 0) {
                    $newVal = [
                        'path' => $val['path'],
                        'component' => 'Layout',
                        'redirect' => $val['redirect'],
                        'name' => $val['name'],
                        'alwaysShow' => $val['always_show'] == 1 ?? true,
                        'meta' => ['title' => $val['title'], 'icon' => $val['icon']]
                    ];
                } else {
                    $newVal = [
                        'path' => $val['path'],
                        'name' => $val['name'],
                        'component' => $val['component'],
                        'hidden' => $val['hidden'] == 1 ?? true,
                        'meta' => [
                            'title' => $val['title'],
                            'icon' => $val['icon'],
                            'keepAlive' => $val['keep_alive'] ?? 1
                        ]
                    ];
                }
                if ($val['parent_id'] == 0) {
                    if (empty($newVal['name']) || $val['path'] == '/' || $val['path'] == $val['redirect']) {
                        unset($newVal['name']);
                    }
                    $newVal['children'] = [
                        [
                            'path' => $val['path'] != $val['redirect'] ? $val['redirect'] : $val['path'],
                            'name' => $val['name'],
                            'component' => $val['component'],
                            'hidden' => $val['hidden'] == 1 ?? true,
                            'meta' => [
                                'title' => $val['title'],
                                'icon' => $val['icon'],
                                'keepAlive' => $val['keep_alive'] ?? 1
                            ]
                        ]
                    ];
                }

                $children = self::menuTreeFrontData($array, $val['id'], $notMenuHidden, $deep, $level + 1);
                if (!empty($children)) {
                    $newVal['children'] = $children;
                }
                $tree[] = $newVal;
            }
        }
        return $tree;
    }

    static function menuTreeData($menus, $pid = 0, $notMenuHidden = false, $deep = 3, $level = 0)
    {
        $tree = [];
        $array = array_values($menus);
        foreach ($array as $key => $val) {
            if ($deep == $level) {
                break;
            }
            if ($val['parent_id'] == $pid) {
                if ($notMenuHidden && $val['type'] != 1) {
                    continue;
                }
                $children = self::menuTreeData($array, $val['id'], $notMenuHidden, $deep, $level + 1);
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
//            ['hidden', '=', 0]
        ];
        $list = UserMenu::getListByWhere($where, ['id', 'parent_id', 'title', 'type']);
        if (empty($list)) {
            return [];
        }

        return self::menuTreeData($list, 0, false);
    }

    /**
     * 获取指定的菜单树形数据
     * @param $ids
     * @return array
     */
    public static function menuListByIds($ids)
    {
        $list = UserMenu::query()->where([['visible', '=', 1], ['status', '=', 1], ['hidden', '=', 0]])
            ->whereIn('id', $ids)->get(['id', 'parent_id', 'title', 'type'])->toArray();
        if (empty($list)) {
            return [];
        }

        return self::menuTreeData($list, 0, false);
    }


    /**
     * 根据用户所有用户所具备的菜单权限数据-----添加角色的访问权限下拉数据
     * @param $user_id
     * @return array
     */
    public static function menuListByUserId($user_id, $group_id)
    {
        //超级管理员返回所有的
        if ($group_id == 1) {
            return self::menuList();
        }
        //非超级管理员获取自己所具备的菜单权限，没有返回空
        $userGroupInfo = UserGroup::query()->where('id', $group_id)->first();
        if (empty($userGroupInfo['role'])) {
            return [];
        }
        $userGroupInfo = $userGroupInfo->toArray();
        // $menuIds = explode(',', $userGroupInfo['role']);
        $menuIds = json_decode($userGroupInfo['role'], true);
        $userMenuList = UserMenu::query()->whereIn('id', $menuIds)
            ->where([['visible', '=', 1], ['status', '=', 1]])->get(['id', 'parent_id', 'title', 'type']);
        if ($userMenuList) {
            $userMenuList = $userMenuList->toArray();
        } else {
            $userMenuList = [];
        }
        return self::menuTreeData($userMenuList, 0, false);
    }


    /**
     * 无条件获取所有的菜单ID
     * @return mixed
     */
    public static function getAllMenuIds()
    {
        $ids = UserMenu::query()->where([['visible', '=', 1], ['status', '=', 1], ['hidden', '=', 0]])->get(['id']);
        if ($ids) {
            $ids = $ids->toArray();
        }
        $ids = array_column($ids, 'id');
        return $ids ?: [];

    }


}
