<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * 西医知识库药品模型
 */
class CdssMedicine extends Model
{
    protected $table = 'jm_cdss_medicine';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}

