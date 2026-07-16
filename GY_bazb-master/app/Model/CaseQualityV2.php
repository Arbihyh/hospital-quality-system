<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class CaseQualityV2 extends Model
{
    protected $table = 'case_quality_v2';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function brry(){
        return $this->hasMany(ZY_BRRY::class,'ZYH','JZHM');
    }

    public static function getById(int $id = 0)
    {
        return self::query()->where('BLBH', '=', $id)->get()->toArray();
    }

    public static function getByJZHM(int $id = 0)
    {
        return self::query()->where('status', '=', 1)->where('JZHM', '=', $id)->get()->toArray();
    }

    public static function getList($page = 1, $pageSize = 20, $column = ['*'])
    {
        $pageStart = ($page - 1) * $pageSize;
        return self::query()->select($column)->offset($pageStart)->LIMIT($pageSize)->get()->toArray();
    }

    /**
     * @param $data
     * @return bool
     * 添加数据
     */
    public static function addData(array $data = [])
    {
        if (!$data) {
            return false;
        }
        return self::query()->updateOrInsert(
            ['rule_id' => $data['rule_id'], 'JZHM' => $data['JZHM']],
            [
                'BLBH' => (empty($data['BLBH']) ? '' : $data['BLBH']),
                'code' => (empty($data['code']) ? '' : $data['code']),
                'basis' => (empty($data['basis']) ? '' : $data['basis']),
                'error_field' => (empty($data['error_field']) ? '' : $data['error_field']),
            ]
        );
    }
}
