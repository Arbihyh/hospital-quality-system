<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class PatientInfoV2 extends Model
{
    protected $table = 'patient_info_v2';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    public static function getPatientInfoByZyh($zyh){
        $data = self::query()->where('ZYH',$zyh)->first();
        return !empty($data) ? $data->toArray() : [];
    }

    public function brry(){
        return $this->hasMany(ZY_BRRY::class,'ZYH','MED_REC_ID');
    }
}
