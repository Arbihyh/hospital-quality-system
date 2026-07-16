<?php


namespace App\Model;

use DateTimeInterface;
use App\Model\BaseModel;
use Illuminate\Support\Facades\DB;

class MainOperation extends BaseModel
{
    const SSPB = ['手术'=>1,'诊断操作'=>2,'治疗操作'=>3,'介入治疗'=>4,'空'=>5];

    protected $table = 'main_operation';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * @param array $where
     * @param array $column
     * @return int
     * 手术判别是手术和介入治疗相加总人数
     */
    public static function getLeaveHospitalData(array $where = [])
    {
        $obj = self::getWhere($where);
        return $obj->count();
    }


    /**
     * @param array $map
     * @return \Illuminate\Database\Eloquent\Builder
     * 数据获取条件组合
     */
    public static function getWhere(array $map = [])
    {
        $obj = self::query();
        if ($map) {
            if (!empty($map['start_time']) && !empty($map['end_time'])) {
                $obj = $obj->whereBetween(DB::raw('UNIX_TIMESTAMP(OPE_DATE)'), [$map['start_time'], $map['end_time']]);
            }
            unset($map['start_time']);
            unset($map['end_time']);
            foreach ($map as $key => $val) {
                if (is_array($val)) {
                    $obj = $obj->whereIn($key, $val);
                } else {
                    $obj = $obj->where($key, '=', $val);
                }
            }
        }
        return $obj;
    }
}
