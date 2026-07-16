<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CaseService;

class Analysis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:analysisCase';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 定时生成执行解析入院记录的信息，并保存到 EMR_BL_BL01 的 analysis_case 字段';

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
        echo 'command start::';
        CaseService::analysisCase();
        echo 'AnalysisCase end';
    }

}
