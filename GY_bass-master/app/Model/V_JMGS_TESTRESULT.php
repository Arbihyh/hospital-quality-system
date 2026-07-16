<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class V_JMGS_TESTRESULT extends Model
{
    protected $table = 'V_JMGS_TESTRESULT';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
