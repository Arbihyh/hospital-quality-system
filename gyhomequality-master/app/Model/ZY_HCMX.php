<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class ZY_HCMX extends Model
{
    protected $table = 'ZY_HCMX';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
