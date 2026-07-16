<?php


namespace App\Model;

use DateTimeInterface;
use App\Model\BaseModel;
use Illuminate\Support\Facades\DB;

class OtherDiagnosis extends BaseModel
{
    protected $table = 'other_diagnosis';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

}
