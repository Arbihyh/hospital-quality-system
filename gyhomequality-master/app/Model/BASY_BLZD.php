<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class BASY_BLZD extends Model
{
    protected $table = 'BASY_BLZD';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }


}
