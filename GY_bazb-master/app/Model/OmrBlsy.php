<?php


namespace App\Model;

use DateTimeInterface;
use App\Model\BaseModel;
use Illuminate\Support\Facades\DB;

class OmrBlsy extends BaseModel
{
    protected $table = 'OMR_BLSY';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

}
