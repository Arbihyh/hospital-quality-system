<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'department';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function departmentList()
    {
        $departmentList = Department::query()
            ->where('type_id','=',2)
            ->orderBy('dep_id')
            ->pluck('dep_name as name','dep_id as id')
            ->toArray();

        return $departmentList;
    }

}
