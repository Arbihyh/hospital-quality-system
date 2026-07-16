<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * Model for the case_quality_count table.
 */
class CaseQualityCount extends Model
{
    // 表名
    protected $table = 'case_quality_count';

    // 自动维护时间戳
    public $timestamps = true;

    /**
     * 添加数据
     * @param array $data
     * @return bool
     */
    public static function addData(array $data = []){
        if (!$data) {
            return false;
        }
        return self::query()->updateOrInsert(
            ['ZYH' => $data['ZYH'], 'quality_date' => $data['quality_date']],
            [
                'BRXM' => (empty($data['BRXM']) ? '' : $data['BRXM']),
                'BRKS' => (empty($data['BRKS']) ? '' : $data['BRKS']),
                'CH' => (empty($data['CH']) ? '' : $data['CH']),
                'is_viewed' => (empty($data['is_viewed']) ? '' : $data['is_viewed']),
            ]
        );
    }
} 