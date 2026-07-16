<?php
//部门
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Dep extends Model
{
    protected $table = 'dep';

    public function getThreeSub()
    {
        $table = self::query()
		->where('SD_DEPTTP','=','01')
        ->where("SD_DEPTLEVEL",'=','0001')
		->get(['id','dep_id as value','dep_name as label','ID_DEP'])
            ->toArray();
        foreach ($table as $k=>$v){
            $sub = self::query()->where('parent_id','=',$v['ID_DEP'])
			->get(['id','dep_id as value','dep_name as label','ID_DEP'])
            ->toArray();
            foreach ($sub as $kk=>$vv){
                $sub2 = self::query()->where('parent_id','=',$vv['ID_DEP'])->get(['id','dep_id as value','dep_name as label','ID_DEP'])
                    ->toArray();
				foreach ($sub2 as $kkk=>$vvv){
                    $sub3 =  self::query()->where('parent_id','=',$vvv['ID_DEP'])
                        ->get(['id','dep_id as value','dep_name as label','ID_DEP']);
                    if ($sub3->isEmpty())continue;
                    $sub2[$kkk]['children'] = $sub3->toArray();
                }
                $sub[$kk]['children'] = $sub2;
            }
            $v['children'] = $sub;
            $table[$k] = $v;
        }
        return $table;
    }

    /**
     * 获取病区分类
     * @return void
     */
    public function getBqCategory()
    {
        $table = self::query()->where('dep_id','=','H01')->get(['id','dep_id as value','dep_name as label','ID_DEP'])
            ->toArray();
        foreach ($table as $k=>$v){
            $sub = self::query()->where('parent_id','=',$v['ID_DEP'])->get(['id','dep_id as value','dep_name as label','ID_DEP'])
                ->toArray();
            foreach ($sub as $kk=>$vv){
                $sub2 = self::query()->where('parent_id','=',$vv['ID_DEP'])->get(['id','dep_id as value','dep_name as label','ID_DEP'])
                    ->toArray();
                $sub[$kk]['children'] = $sub2;
            }
            $v['children'] = $sub;
            $table[$k] = $v;
        }
        return $table;
    }
}
