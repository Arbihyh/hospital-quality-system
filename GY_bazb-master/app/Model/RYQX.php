<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class RYQX extends Model
{
    protected $table = 'RYQX';

    public function getSssjDatetimeAttribute()
    {
        if ($this->sssj == '0000-00-00 00:00:00'){
            return null;
        }
        return  $this->sssj;
    }

    public function getFbsjDatetimeAttribute()
    {
        if ($this->fbsj == '0000-00-00 00:00:00'){
            return null;
        }
        return  $this->fbsj;
    }
}
