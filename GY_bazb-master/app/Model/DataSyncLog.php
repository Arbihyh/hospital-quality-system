<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class DataSyncLog extends Model
{
    protected $table = 'data_sync_log';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function addData($data = []) {
        self::query()->insert($data);
    }
}
