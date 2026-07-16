<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\UltrasonicService;
use Illuminate\Support\Facades\Log;

class CleanPacs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:cleanPacs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 清洗医嘱本字段';

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
        echo 'start command:cleanPacs '.date("Y-m-d H:i:s");
        try{
            $service = new UltrasonicService();
            $service->cleanPacs();
        }catch (\Throwable $e){
            var_dump("command laravel:YzbClean 错误:code:".$e->getCode().'；line:'.$e->getLine().'；错误信息：'.$e->getMessage());
        }

        echo 'end command:cleanPacs '.date("Y-m-d H:i:s");
    }
}
