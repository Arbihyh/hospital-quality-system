<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class BigModelMedicalRecord extends Model
{
    protected $table = 'big_model_medical_record';

    public $timestamps = false;

    protected $fillable = [
        'zyh',
        'title',
        'content',
        'code',
        'created_at',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
