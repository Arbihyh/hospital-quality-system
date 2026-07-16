<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class IndicatorUploadHistory extends Model
{
    protected $table = 'indicator_upload_history';

    protected $fillable = [
        'period',
        'upload_time',
        'data',
    ];

    public $timestamps = false;

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
