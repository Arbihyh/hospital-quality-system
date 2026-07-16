<?php


namespace App\Services;


use App\Model\AdminGroup;
use App\Model\Admin;

class AdminGroupService
{
    public static function getAdminGroupList($page, $len)
    {
        $data = AdminGroup::getAdminGroupPage($page, $len);
        if (!$data) {
            return [];
        }
//        $adminIds = array_column($data, "admin_id");
//        $adminData = Admin::getInAll($adminIds);
//        $adminIdData = array_column($adminData,null,'id');
        foreach ($data as $k => &$v){
            $v['status'] = $v['id'] == 1?0:1; // 0 不可删除 1 可以删除
//            $v["admin_name"] = $adminIdData[$v["admin_id"]]["name"];
            if($v["role"] != "all"){
                $v["role"] = json_decode($v['role'],true);
            }
        }
        return $data;
    }

    // 添加管理员组
    public static function add($groupName,$desc,$role,$admin_id)
    {
        return AdminGroup::add($groupName,$desc,json_encode($role),$admin_id);
    }

    // 添加管理员组
    public static function edit($id,$groupName,$desc,$role)
    {
        $state = AdminGroup::edit($id,$groupName,$desc,json_encode($role));
        if($state){
            return true;
        }else{
            return false;
        }
    }
}
