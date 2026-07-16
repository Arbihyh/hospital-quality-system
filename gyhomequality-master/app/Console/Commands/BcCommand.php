<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BcService;
use Illuminate\Support\Facades\Log;

class BcCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:bc';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 病程详细信息解析';

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
        echo 'start command:bc '.date("Y-m-d H:i:s");
        try{
            BcService::checkCaseList();
        }catch (\Throwable $e){
            var_dump("command laravel:bc 错误:code:".$e->getCode().'；line:'.$e->getLine().'；错误信息：'.$e->getMessage());
        }

        echo 'end command:bc '.date("Y-m-d H:i:s");
    }
}
