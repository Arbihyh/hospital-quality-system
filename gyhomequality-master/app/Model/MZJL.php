<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class MZJL extends Model
{
    protected $table = 'mzjl';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
