<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class OmrDepartment extends Model
{
    protected $table = 'omr_department';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function getOmrDepartmentData()
    {
        return OmrDepartment::query()->pluck('dep_name','dep_id')->toArray();
    }
}
