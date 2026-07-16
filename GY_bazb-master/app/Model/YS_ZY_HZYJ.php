<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class YS_ZY_HZYJ extends Model
{
    protected $table = 'YS_ZY_HZYJ';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
