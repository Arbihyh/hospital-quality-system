<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class MS_CF02 extends Model
{
    protected $table = 'MS_CF02';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
