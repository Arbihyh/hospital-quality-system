<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class EMR_BL_BLXG extends Model
{
    protected $table = 'EMR_BL_BLXG';

    protected $fillable = [
        'BLBH',
        'HJNR',
        'XGSJ'
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function getById($id){
        return self::query()->orderBy("JLXH", "desc")->where('BLBH', '=', $id)->orderBy("JLXH", 'desc')->LIMIT(1)->get()->toArray();
    }

    public static function analysisSsDateTime($hjnr)
    {
        // 取出手术日期
        $ssInfo = explode('手术日期',$hjnr);
        if (empty($ssInfo['1'])) {
            return '';
        }
        $ssDate = trim(mb_substr($ssInfo['1'],1,11));
        $ssDate = str_replace('年','-',str_replace('月','-',$ssDate));
        // 取出手术时间
        $ssTime = explode('手术时间',$hjnr);
        $time = '00:00:00';
        if (!empty($ssTime['1'])) {
            $time = trim(substr($ssTime['1'],1,6)).':00';
        }

        return $ssDate.' '.$time;
    }
}
