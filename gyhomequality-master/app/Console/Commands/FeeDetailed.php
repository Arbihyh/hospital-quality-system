<?php

namespace App\Console\Commands;

use App\Model\PatientInfo;
use App\Services\HomeDataService;
use Illuminate\Console\Command;

class FeeDetailed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:fd {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $config = config('dictionaries');
        $page = 1;
        $limit = 1;
        while (true) {
            $offset = ($page - 1) * $limit;
            $keys = PatientInfo::query()
                ->offset($offset)
                ->limit($limit)
                ->orderBy('MED_REC_ID')
                ->pluck('MED_REC_ID');
            if ($keys){
                $keys = $keys->toArray();
            }else{
                break;
            }
            if(empty($keys)) {
                die;
            }
            foreach ($keys as $zyh) {
                $homeDataService = new HomeDataService();
                $homeDataService->getFyData($zyh);
            }
            $page++;
        }
        return 0;
    }
}
