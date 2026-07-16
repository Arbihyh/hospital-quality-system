<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'department';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
