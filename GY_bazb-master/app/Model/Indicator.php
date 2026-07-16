<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class Indicator extends Model
{
    protected $table = 'indicator';


    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($indicator) {
            $indicator->onCreatedOrUpdated();
        });

        static::updated(function ($indicator) {
            $indicator->onCreatedOrUpdated();
        });
    }

    public function onCreatedOrUpdated()
    {
    }

    
}