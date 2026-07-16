<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RadioService;
use Illuminate\Support\Facades\Log;

class Radio extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:zb {type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 指标数据';

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
        $type = $this->argument('type');
        $type = $type ?: 'cacheData';

        echo 'start '.date("Y-m-d H:i:s")."\n";
        $radioService = new RadioService();
        try{
            $radioService->$type();
        }catch (\Throwable $e){
            var_dump("command:radio 错误:code:".$e->getCode().'；line:'.$e->getLine().'；错误信息：'.$e->getMessage());
        }
        echo 'end '.date("Y-m-d H:i:s");
    }
}
