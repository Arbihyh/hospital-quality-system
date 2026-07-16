<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class BA_RECEIVEl extends Model
{
    protected $table = 'BA_RECEIVEl';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
