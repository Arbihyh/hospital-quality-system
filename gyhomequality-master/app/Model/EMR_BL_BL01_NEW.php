<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class EMR_BL_BL01_NEW extends Model
{
    protected $table = 'EMR_BL_BL01_NEW';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
