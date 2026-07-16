<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CaseQualityShizhongRecord extends Model
{
    protected $table = 'case_quality_shizhong_records';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * @param string $jzhm
     * @param array $select
     * @return array
     */
    public static function getByJZHM($jzhm = '', $select = ['*'])
    {
        return self::query()->where('jzhm', '=', $jzhm)->get($select)->toArray();
    }

    /**
     * 添加或更新事中质控记录
     *
     * @param array $data
     * @return bool
     */
    public static function addData(array $data = [])
    {
        if (empty($data['jzhm']) || empty($data['rule_id'])) {
            return false;
        }

        $now = empty($data['last_quality_time']) ? date('Y-m-d H:i:s') : $data['last_quality_time'];
        $query = self::query()
            ->where('jzhm', '=', $data['jzhm'])
            ->where('rule_id', '=', $data['rule_id']);

        if (!$query->exists()) {
            return self::query()->insert([
                'jzhm' => $data['jzhm'],
                'rule_id' => $data['rule_id'],
                'department' => empty($data['department']) ? '' : $data['department'],
                'lock_count' => empty($data['lock_count']) ? 0 : $data['lock_count'],
                'resident_doctor' => empty($data['resident_doctor']) ? '' : $data['resident_doctor'],
                'medical_record_no' => empty($data['medical_record_no']) ? '' : $data['medical_record_no'],
                'patient_name' => empty($data['patient_name']) ? '' : $data['patient_name'],
                'bed_no' => empty($data['bed_no']) ? '' : $data['bed_no'],
                'last_quality_time' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return self::query()
            ->where('jzhm', '=', $data['jzhm'])
            ->where('rule_id', '=', $data['rule_id'])
            ->update([
                'lock_count' => DB::raw('lock_count + 1'),
                'last_quality_time' => $now,
                'updated_at' => $now,
            ]) > 0;
    }
}
