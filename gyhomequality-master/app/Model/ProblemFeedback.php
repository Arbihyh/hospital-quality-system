<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class ProblemFeedback extends Model
{
    //问题反馈表
    protected $table = 'problem_feedback';

    const TYPE_LIST = [
        1 => '病案首页质控',
        2 => '住院病历质控',
        3 => '病案首页查询',
        4 => '住院病历查询',
        5 => '住院医嘱查询',
        6 => '门诊病历查询',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
