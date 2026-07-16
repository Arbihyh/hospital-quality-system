<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class HospitalDaySurgery extends Model
{
    protected $table = 'hospital_day_surgery';

    public function getSssjDatetimeAttribute()
    {
        if ($this->sssj == '0000-00-00 00:00:00'){
            return null;
        }
        return  $this->sssj;
    }

    public function getFbsjDatetimeAttribute()
    {
        if ($this->fbsj == '0000-00-00 00:00:00'){
            return null;
        }
        return  $this->fbsj;
    }
}
