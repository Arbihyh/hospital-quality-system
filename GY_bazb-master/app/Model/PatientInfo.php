<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class PatientInfo extends Model
{
    protected $table = 'patient_info';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    public static function getPatientInfoByZyh($zyh){
        $data = self::query()->where('MED_REC_ID',$zyh)->first();
        return !empty($data) ? $data->toArray() : [];
    }

    public function brry(){
        return $this->hasMany(ZY_BRRY::class,'ZYH','MED_REC_ID');
    }
}
