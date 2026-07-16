<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class BASY_SS extends Model
{
    protected $table = 'BASY_SS';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function ssFieldYs()
    {
        $fieldYs = [
            'C14x01C' => 'ICD9_ID1',
            'C15x01N' => 'ICD9_NAME',
            'C16x01' => 'SSRQ',
            'C17x01' => 'SSJB',
            'F13' => 'SSCXSJ',
            'C18x01' => 'SSCZSZ',
            'C19x01' => 'SSCZYZ',
            'C20x01' => 'SSCZEZ',
            'C21x01C' => 'QKYHDJ',
            'C22x01C' => 'MZFS',
            'F15' => 'MZFJ',
            'C23x01' => 'MZYS'
        ];
        $arr = [
            '01','02','03','04','05','06','07','08','09','10','11','12','13','14','15','16','17','18','19','20','21',
            '22','23','24','25','26','27','28','29','30','31','32','33','34','35','36','37','38','39','40'
        ];
        foreach ($arr as $val) {
            $fieldYs['C35x'.$val.'C'] = 'ICD9_ID1';
            $fieldYs['C36x'.$val.'N'] = 'ICD9_NAME';
            $fieldYs['C37x'.$val] = 'SSRQ';
            $fieldYs['C38x'.$val] = 'SSJB';
            $fieldYs['F14x'.$val] = 'SSCXSJ';
            $fieldYs['C39x'.$val] = 'SSCZSZ';
            $fieldYs['C40x'.$val] = 'SSCZYZ';
            $fieldYs['C41x'.$val] = 'SSCZEZ';
            $fieldYs['C42x'.$val.'C'] = 'QKYHDJ';
            $fieldYs['C43x'.$val.'C'] = 'MZFS';
            $fieldYs['F16x'.$val] = 'MZFJ';
            $fieldYs['C44x'.$val] = 'MZYS';
        }

        return $fieldYs;
    }

}
