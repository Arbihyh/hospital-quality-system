<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class DataSourceHospital extends Model
{
    protected $table = 'data_source_hospital';

    protected $fillable = [
        'name'
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
