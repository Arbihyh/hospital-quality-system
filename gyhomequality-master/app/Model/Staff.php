<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;


class Staff extends Model
{
    protected $table = 'staff';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function getStaffData()
    {
        return Staff::query()->pluck('name','code')->toArray();
    }

    /**
     * @param $type_id
     * @return array
     */
    public static function getDoctorStaffType($type_id)
    {
        $data = Cache::get("staffDoctorType{$type_id}");

        if (empty($data['data']))//未读取到缓存
        {
            $query = Staff::query()->leftJoin('staff_identity as b','b.staff_code','=','staff.base_code')->where('b.type_id', $type_id);
            $total_num = $query->count();
            $table = $query->pluck('staff.name','staff.base_code')->toArray();
            $data = ['total'=>$total_num,'data'=>$table];
            Cache::put("staffDoctorType{$type_id}",$data,3600);
        }
        return $data;
    }

}
