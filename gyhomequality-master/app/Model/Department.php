<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Department extends Model
{
    protected $table = 'department';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function getDepartmentData($hospitalName='')
    {
        $hospital_name = !empty($hospitalName) ? $hospitalName : config('confAdmin.hospital_name');
        return Department::query()
            // ->where('hospital_name','=',$hospital_name)
//            ->where('flag','=',1)
            ->pluck('dep_name','dep_id')->toArray();
    }

    public static function getDepartmentList($hospitalName='')
    {
        $hospital_name = !empty($hospitalName) ? $hospitalName : config('confAdmin.hospital_name');
        return Department::query()
            // ->where('hospital_name','=',$hospital_name)
            ->where('type_id','=',2)
            ->get(['dep_id','dep_name'])->toArray();
    }

    public static function getOmrDepartmentData($hospitalName='')
    {
        $hospital_name = !empty($hospitalName) ? $hospitalName : config('confAdmin.hospital_name');
        return Department::query()
            ->where('hospital_name','=',$hospital_name)
            ->where('MZSY','=','Y')
            ->pluck('dep_name','dep_id')->toArray();
    }

    /**
     * 获取科室名称
     */
    public static function getDepartmentName($depId)
    {
        $table = Cache::get('departmentName');
        if (empty($table))
        {
            $table = self::query()->get(['dep_id','dep_name'])->keyBy('dep_id');
            Cache::put('departmentName',$table,60*60*24);
        }
        return  $table[$depId]['dep_name'] ?? '暂无数据';
    }

    /**
     * 获取院区/科室/病区
     */
    public static function getDepartmentOptions(int $type=1, int $level=1,array $parent_id=[])
    {
        $query = Department::query()->where('type_id',$type)->where('level_id',$level);
        if (!empty($parent_id) ) $query->whereIn('parent_id',$parent_id);
        $table =  $query->get(['dep_id as YQ_CODE','dep_name as YQ_NAME','dep_id as value','dep_name as label','dep_id','dep_name','id','parent_id','level_id','type_id'])->toArray();

        if (!empty($table)){
            foreach ($table as $k=>$v){
                $child = self::getDepartmentOptions($v['type_id'],$v['level_id']+1,[$v['dep_id']]);
                if(!empty($child)){
                    $table[$k]['children'] = $child;
                }
            }
        }
        return $table;
    }

    /**
     * 更新部门的所有子集
     * @return void
     */
   public static function updateSubIds()
   {
       $table = self::query()->get(['id', 'parent_id','dep_id'])->toArray();
       foreach ($table as $v) {
           self::updateSubIdsRecursively($v['dep_id']);
       }
   }

    /**
     * 无限级递归方法,用于更新部门所有子集
     * 根据父 dep_id 递归收集其下所有层级的 dep_id 并写入 sub_ids。
     * $ancestors 仅记录当前递归链上的祖先节点，用于防止脏数据自引用/成环导致死循环，
     * 不会跳过正常的子树（同级或深层子节点都会被完整清洗）。
     * @param $currentDepId
     * @param array $ancestors 当前递归链上的祖先 dep_id
     * @return array
     */
   public static function updateSubIdsRecursively($currentDepId, $ancestors = [])
   {
       // 获取直接子集
       $children = self::query()->where('parent_id', $currentDepId)->get(['id', 'parent_id', 'dep_id']);

       // 当前递归链新增自己，用于向下传递做成环检测
       $ancestorsWithSelf = array_merge($ancestors, [$currentDepId]);

       // 含自己
       $subIds = [$currentDepId];
       foreach ($children as $v) {
           // 仅当子节点已在当前递归链上（真正成环）时才跳过，避免死循环；
           // 正常的二级、三级等子节点不会被跳过。
           if (in_array($v['dep_id'], $ancestorsWithSelf)) {
               continue;
           }
           // 递归获取子节点的所有后代 ID
           $subIds = array_merge($subIds, self::updateSubIdsRecursively($v['dep_id'], $ancestorsWithSelf));
       }

       //去重
       $subIds = array_unique($subIds);

       //更新
       self::query()->where('dep_id', $currentDepId)->update(['sub_ids' => implode(',', $subIds)]);

       return $subIds;
   }

    /**
     * 更新部门级别
     * @return void
     */
   public static function updateDepartmentLevel()
   {
       $table = self::query()->get(['id', 'parent_id','dep_id','type_id','level_id'])->toArray();
       foreach ($table as $v){
           $row = self::query()->where('dep_id',$v['parent_id'])->first(['type_id','level_id']);
           $update = [];
           if (empty($row)){
               $update['level_id'] = 1;
           }else{
               $update['level_id'] = $v['type_id'] == $row['type_id'] ? $row['level_id']+1 : 1;
           }
           self::query()->where('dep_id',$v['dep_id'])->update($update);
       }
       return true;
   }

}
