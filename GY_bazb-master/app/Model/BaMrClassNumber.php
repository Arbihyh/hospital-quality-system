<?php


namespace App\Model;

use DateTimeInterface;
use App\Model\BaseModel;
use Illuminate\Support\Facades\DB;

class BaMrClassNumber extends BaseModel
{
    protected $table = 'BA_MR_CLASS_NUMBER';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

}
