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

    public function errors()
    {
        return $this->hasMany('App\Model\ErrorV2','AAA28','AAA28');
    }
}
