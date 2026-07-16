<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class HomeRequestContent extends Model
{
    protected $table = 'home_request_content';

    // 定义可填充字段
    protected $fillable = [
        'hospital_name', 'ZYH', 'content', 'fy_content', 'yz_content'
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

}
