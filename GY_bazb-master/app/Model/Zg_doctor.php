<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class Zg_doctor extends Model
{
    protected $table = 'zg_doctor';
    public $connection = 'ding_mysql';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
