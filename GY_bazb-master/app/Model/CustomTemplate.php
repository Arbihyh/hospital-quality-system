<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class CustomTemplate extends Model
{
    protected $table = 'custom_templates';

    protected $fillable = [
        'name',
        'department',
        'department_id',
        'disease_id',
        'document_type',
        'content',
        'big_model_template_id',
        'template_scope',
        'source_template_id',
        'staff_code',
        'staff_name',
        'status',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function bigModelTemplate()
    {
        return $this->belongsTo(BigModelTemplate::class, 'big_model_template_id');
    }
}
