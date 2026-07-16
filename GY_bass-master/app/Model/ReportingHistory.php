<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class ReportingHistory extends Model
{
    protected $table = 'reporting_history';

    const PLATFORM = [1=>'国考',2=>'卫统',3=>'医保'];


    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
