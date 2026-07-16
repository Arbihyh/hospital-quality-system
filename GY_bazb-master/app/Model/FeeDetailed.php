<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class FeeDetailed extends Model
{
    protected $table = 'fee_detailed';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function patient_info(){
        return $this->hasOne(PatientInfo::class,'ZYH_ID','ZYH_ID');
    }
}
