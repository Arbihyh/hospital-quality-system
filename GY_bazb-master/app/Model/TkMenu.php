<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class TkMenu extends Model
{
    protected $table = 'tk_menu';

    protected $fillable = ['title', 'is_show', 'url', 'pic_url', 'right_class_name'];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
