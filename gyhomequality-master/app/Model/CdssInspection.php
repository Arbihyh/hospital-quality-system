<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * 西医知识库检查检验模型
 */
class CdssInspection extends Model
{
    protected $table = 'jm_cdss_inspection';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}

