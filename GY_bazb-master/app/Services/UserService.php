<?php


namespace App\Services;


use App\Model\Department;
use App\Model\RuleWordMap;
use App\Model\User;
use Illuminate\Http\Request;

class UserService
{
    /**
     * @param Request $request
     *
     * 获取当前登录用户可查看数据的部门数据
     */
    public static function getCurrentUserDep(Request $request)
    {
        $token = $request->header('token');
        $user = User::query()->where("token", "=", $token)->get()->toArray();
        $depId = json_decode($user[0]['dep_id'], true);

        $white = RuleWordMap::getFirstById(4001);

        // 超管可以查看所有部门
        $dep = [];
        if (array_intersect($depId, $white) || $user[0]['group_id'] == '1') {
            // 返回具有超级管理员权限的标识
            return 'admin';
        } elseif (!empty($depId)) {
            // 非管理员只能看关联的部门
            $dep = Department::query()->whereIn("dep_id", $depId)->get(['dep_id', 'dep_name'])->toArray();
        }

        // 如果当前登录人的所属部门不在指定的查看所有预警信息的科室信息中，则只能查看指定部门的信息
        $depIds = [];
        if (!empty($dep)) {
            $depIds = array_column($dep, "dep_id");
        }
        return $depIds;
    }
}
