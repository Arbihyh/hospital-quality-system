<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class BASY_ZD extends Model
{
    protected $table = 'BASY_ZD';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function ssFieldYs()
    {
        $field = [
            'C03C' => 'ICD10_ID1',
            'C04N' => 'ICD10_NAME',
            'C05C' => 'RYBQ',
            'F05' => 'CYQK',
        ];

        $arr = [
            '01','02','03','04','05','06','07','08','09','10','11','12','13','14','15','16','17','18','19','20','21',
            '22','23','24','25','26','27','28','29','30','31','32','33','34','35','36','37','38','39','40'
        ];
        foreach ($arr as $val) {
            $field['C06x'.$val.'C'] = 'ICD10_ID1';
            $field['C07x'.$val.'N'] = 'ICD10_NAME';
            $field['C08x'.$val.'C'] = 'RYBQ';
            $field['F06x'.$val] = 'CYQK';
        }

        return $field;
    }

}
