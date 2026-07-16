<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class MainDiagnosis extends Model
{
    const RYQK = [1=>'有',2=>'临床未确定',3=>'情况不明',4=>'无'];
    const CYQK = [1=>'治愈',2=>'好转',3=>'未愈',4=>'死亡',9=>'其他'];

    protected $table = 'main_diagnosis';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
