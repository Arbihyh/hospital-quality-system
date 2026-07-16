<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class PatientScore extends Model
{
    protected $table = 'patient_score';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
