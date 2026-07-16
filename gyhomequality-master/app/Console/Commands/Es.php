<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RadioService;
use Illuminate\Support\Facades\Log;

class Es extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'es {type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 执行es数据导入';

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
        echo 'start command:es '.date("Y-m-d H:i:s");
        try{
            eval('python /usr/local/datax/bin/datax.py /quality/homeQuality/app/Console/Commands/mysqltoes/'.$type.'.json');
        }catch (\Throwable $e){
            var_dump("command laravel:YzbClean 错误:code:".$e->getCode().'；line:'.$e->getLine().'；错误信息：'.$e->getMessage());
        }

        echo 'end command:es '.date("Y-m-d H:i:s");
    }
}
