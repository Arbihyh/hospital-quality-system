<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class ZbBagl extends Model
{
    protected $table = 'zb_bagl';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function getFirstById($id,$flag = false){
        $res = self::query()->where(compact('id'))->first();
        return $res ? ($flag ? $res : $res->getAttributes()) : false;
    }
}
