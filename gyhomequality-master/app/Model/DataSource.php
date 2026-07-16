<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class DataSource extends Model
{
    protected $table = 'data_source';

    protected $fillable = [
        'qingmiao_table_name',
        'qingmiao_field_name',
        'qingmiao_field',
        'hospital_name',
        'hospital_field',
        'hospital_one',
        'hospital_two',
        'hospital_three'
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
