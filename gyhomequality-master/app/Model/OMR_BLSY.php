<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class OMR_BLSY extends Model
{
    protected $table = 'OMR_BLSY';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
