<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class OMR_BL01 extends Model
{
    const FIELD_LIST = ['zs'=>'主诉','xbs'=>'现病史','jws'=>'既往史','tgjc'=>'体格检查','fzjc'=>'辅助检查','cbzd'=>'初步诊断','zlyj'=>'诊疗意见','mzh'=>'门诊号','xm'=>'姓名','xb'=>'性别','ks'=>'科室','nl1'=>'年龄','xy'=>'西药'];

    protected $table = 'OMR_BL01';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

}
