<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class BA_MR_CLASS_NUMBER extends Model
{
    protected $table = 'BA_MR_CLASS_NUMBER';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
