<?php
namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class ErrorV2 extends Model
{
    protected $table = 'error_v2';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    const YLZC = [
        '优'   => [97,100],
        '良' => [90,96.9],
        '中' => [75,89.9],
        '差'   => [0,75.9],
    ];

    public function patientInfoV2()
    {
        return $this->belongsTo('App\Model\PatientInfoV2','AAA28','AAA28');
    }
}
