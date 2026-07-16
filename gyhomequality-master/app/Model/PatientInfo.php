<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class PatientInfo extends Model
{
    protected $table = 'patient_info';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * 病案质量
     */
    public static function getBazlArray()
    {
        return [
            ['id' => 1, 'name' => '甲'],
            ['id' => 2, 'name' => '乙'],
            ['id' => 3, 'name' => '丙']
        ];
    }

    /**
     * 离院方式
     */
    public static function getLyTypeArray()
    {
        return [
            ['id' => 1, 'name' => '医嘱离院'],
            ['id' => 2, 'name' => '医嘱转院'],
            ['id' => 3, 'name' => '医嘱转社区服务机构'],
            ['id' => 4, 'name' => '非医嘱离院'],
            ['id' => 5, 'name' => '死亡'],
            ['id' => 9, 'name' => '其他'],
        ];
    }

    public static function getBlZl($score, $iswy = 0)
    {
        $result = "";
        if ($iswy == 0) {
            switch ($score) {
                case $score > 90:
                    $result = "<span style='color: green'>{$score}/甲</span>";
                    break;
                case $score >= 75 &&  $score <= 90:
                    $result = "<span style='color: orange'>{$score}/乙</span>";
                    break;
                case $score < 75:
                    $result = "<span style='color: red'>{$score}/丙</span>";
                    break;
                default:
            }
        }else{
            switch ($score) {
                case $score > 90:
                    $result = "/甲";
                    break;
                case $score >= 75 &&  $score <= 90:
                    $result = "/乙";
                    break;
                case $score < 75:
                    $result = "{$score}/丙";
                    break;
                default:
            }
        }

        return $result;
    }

    public function brry()
    {
        return $this->hasMany(ZY_BRRY::class, 'ZYH', 'MED_REC_ID');
    }
}
