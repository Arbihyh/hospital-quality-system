<?php

namespace App\Console\Commands\UpdateEs;

use App\Model\PatientInfo;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateEs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:updateEs
     */
    protected $signature = 'command:updateEs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 增量同步es数据';

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
        echo 'start command:updateEs '.date("Y-m-d H:i:s");
        $yesterday = now()->yesterday();
        $patientInfo = PatientInfo::query()->where('updated_at','>=',$yesterday)->get();
        echo 'end command:updateEs '.date("Y-m-d H:i:s");
        $this->homeBl($patientInfo);
    }

    public function homeBl($patientInfo)
    {
        $index = 'test1';
        $esParams = [];

        foreach ($patientInfo as $val){
            $esParams['body'][] = ['update' => ['_index' => $index, '_id' => $val->MED_REC_ID]];
            $esParams['body'][] = ['doc' => ["id"=>$val->MED_REC_ID,"AAA01" => $val->AAA01, "AAA28" => $val->AAA28, 'AAA03' => $val->AAA03, "AAA04" => $val->AAA04, "MED_REC_ID" => $val->MED_REC_ID],'doc_as_upsert'=>true];
        }
        if (empty($esParams)){
            return false;
        }
        app('es')->bulk($esParams);
        return  true;
    }
}
