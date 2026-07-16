<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RadioService;
use Illuminate\Support\Facades\Log;
use App\Model\PatientInfo;

class CleanYzb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:YzbClean {zyh?} {start?} {end?}';

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
        $zyh = $this->argument('zyh');
        $start = $this->argument('start');
        $end = $this->argument('end');
        if(empty($end)){
            $end = date('Y-m-d 23:59:59', time());
        }else{
            $end = $end.' 23:59:59';
        }
        echo 'start command:case '.date("Y-m-d H:i:s");
        try{
            $painetinfo = PatientInfo::query();
            if($zyh){
                $painetinfo->where('MED_REC_ID', $zyh);
            }
            if($start){
                $start = $start.' 00:00:00';
                $painetinfo->where('AAC01', '>=', $start);
            }
            if($end){
                $painetinfo->where('AAC01', '<=', $end);
            }
            $painetinfo = $painetinfo->orderBy('AAC01', 'desc')->get()->toArray();
            foreach ($painetinfo as $item) {
                echo $item['MED_REC_ID'] . PHP_EOL;
                RadioService::filterField($item['MED_REC_ID']);
            }
        }catch (\Throwable $e){
            var_dump("command laravel:YzbClean 错误:code:".$e->getCode().'；line:'.$e->getLine().'；错误信息：'.$e->getMessage());
        }

        echo 'end command:case '.date("Y-m-d H:i:s");
    }
}
