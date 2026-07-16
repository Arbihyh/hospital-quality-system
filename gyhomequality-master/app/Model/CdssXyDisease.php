<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * 西医知识库疾病症状模型
 */
class CdssXyDisease extends Model
{
    protected $table = 'jm_cdss_xy_disease';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}

