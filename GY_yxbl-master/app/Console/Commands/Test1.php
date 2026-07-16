<?php

namespace App\Console\Commands;

use App\Services\CaseService;
use Illuminate\Console\Command;
use App\Services\ElasticsearchService;

class Test1 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'test1';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 定时生成执行相关诊断信息的统计信息';

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
     *
     */
    public function handle()
    {
        $ZA03 = "莱州市人民医院";
        $restful = preg_match('/^[\x7f-\xff]+$/', $ZA03);
        var_dump($restful);
        exit;

    }

}
