<?php

namespace App\Console\Commands;

use App\Model\Department;
use Illuminate\Console\Command;

class DepartmentSubIdsQx extends Command
{
    protected $signature = 'laravel:DepartmentSubIdsQx';
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        echo "清洗开始->".date('Y-m-d H:i:s')."\n";
        Department::updateSubIds();//清洗子集
        Department::updateDepartmentLevel();//清洗级别
        echo "清洗结束->".date('Y-m-d H:i:s')."\n";
    }

}
