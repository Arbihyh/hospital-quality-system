<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $table = 'menu';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    protected static $field = ["id","name","sort","desc","icon"];
    //
    public static function getMenu($id)
    {
        $data = self::query()->whereIn("id",$id)->orderBy("sort", "asc")->get(self::$field);
        if($data){
            return $data->toArray();
        }else{
            return false;
        }
    }
}
