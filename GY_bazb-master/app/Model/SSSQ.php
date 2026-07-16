<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class SSSQ extends Model
{
	const AAC01 = null;
    // 手术申请
    protected $table = 'SSSQ';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
