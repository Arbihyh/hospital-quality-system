<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class IndexCatalog extends Model
{
    protected $table = 'index_catalog';

    protected $fillable = [
        'pid',
        'name',
        'fenzi',
        'fenmu',
        'url',
        'created_at',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function editStatus($name = "", $status = 1)
    {
        self::query()->where("index_name", "=", $name)->update(["status" => $status, "quality_time" => time()]);
    }
}
