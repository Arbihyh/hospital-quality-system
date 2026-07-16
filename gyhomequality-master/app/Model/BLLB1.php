<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class BLLB1 extends Model
{
    const FLAG_CYZD = '出院诊断';

    protected $table = 'bllb1';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }


}
