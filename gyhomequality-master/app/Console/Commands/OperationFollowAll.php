<?php

namespace App\Console\Commands;

use App\Model\PatientInfo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class OperationFollowAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:operation-follow-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '根据收费明细质控遗漏的手术';

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
        ini_set('default_socket_timeout', 24 * 60 * 60);
        //\App\Services\OperationFollow::follow(637139);
        //exit;
        Log::info('command:operation-follow', []);
        //\App\Services\OperationFollow::follow(646899);exit;
        $offset = $count = 0;
        $limit = 10;
        while (true) {
            $lists = PatientInfo::query()
                ->offset($offset)
                ->limit($limit)
                ->orderBy('id', 'asc')
                ->pluck('MED_REC_ID');
            $lists = $lists->toArray();
            print_r($lists);
            foreach ($lists as $list){
                \App\Services\OperationFollow::follow($list);
            }
            if(empty($lists)){
                break;
            }
            echo $count . PHP_EOL;
            $count++;
            $offset = $count * $limit;
            sleep(5);
        }
        exit;
    }
}
