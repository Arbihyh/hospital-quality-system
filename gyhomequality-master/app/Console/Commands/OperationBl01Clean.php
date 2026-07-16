<?php

namespace App\Console\Commands;

use App\Services\OperationService;
use Illuminate\Console\Command;

class OperationBl01Clean extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:OperationBl01Clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 清洗手术记录中的手术日期';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        echo 'start command:operationClean '.date("Y-m-d H:i:s");
        try{
            OperationService::checkList();
        }catch (\Throwable $e){
            var_dump("command:operationClean 错误:code:".$e->getCode().'；line:'.$e->getLine().'；错误信息：'.$e->getMessage());
        }
        echo 'end command:operationClean '.date("Y-m-d H:i:s");
    }
}
