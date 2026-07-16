<?php

namespace App\Console\Commands;

use App\Model\PatientCostInfo;
use App\Model\PatientInfo;
use Illuminate\Console\Command;
use JsonStreamingParser\Parser;
use Mockery\Exception;

class Fee extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:fee';

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
        ini_set('default_socket_timeout', 0);
        $files = [
            'INIT_MED_REC_FEE_202209242209.json',
            'INIT_MED_REC_FEE_202209242211.json',
            'INIT_MED_REC_FEE_202209242213.json',
            'INIT_MED_REC_FEE_202209242214.json'
        ];
        foreach ($files as $item){
            echo $item;
            $file = fopen("/data/api/public/file/fee/".$item, 'r');
            $listener = new \JsonStreamingParser\Listener\InMemoryListener();
            try {
                $reader = new Parser($file, $listener);
                $reader->parse();
                fclose($file);
            } catch (Exception $e) {
                fclose($file);
                throw $e;
            }
            $data = $listener->getJson();
            $query = PatientCostInfo::query();
            foreach ($data as $value){
                if ($query == null){
                    $query = PatientCostInfo::query();
                }
                $filed = config('dictionaries.CASE_FIELD')[$value['CASE_CHARGE_KIND_ID']];
                if ($query->where('AAA28',$value['MED_REC_ID'])->first()){
                    $query->where('AAA28',$value['MED_REC_ID'])
                        ->update([$filed=>$value['AMOUNT']]);
                }else{
                    $PatientInfo = PatientInfo::query()->where('MED_REC_ID',$value['MED_REC_ID'])
                        ->first(['ADA0101']);
                    if ($PatientInfo){
                        $PatientInfo = $PatientInfo->toArray();
                        $ADA0101 = $PatientInfo['ADA0101'] ?? 0;
                    }else{
                        $ADA0101 = 0;
                    }
                    $insert = [
                        'AAA28' => $value['MED_REC_ID'],
                        'ADA0101' => $ADA0101,
                        'AAE040' => '0',
                        'D11' => '0',
                        'D12' => '0',
                        'D13' => '0',
                        'D14' => '0',
                        'D15' => '0',
                        'D16' => '0',
                        'D17' => '0',
                        'D18' => '0',
                        'D19' => '0',
                        'D19X01' => '0',
                        'D20' => '0',
                        'D20X01' => '0',
                        'D20X02' => '0',
                        'D21' => '0',
                        'D22' => '0',
                        'D23' => '0',
                        'D23X01' => '0',
                        'D24' => '0',
                        'D25' => '0',
                        'D26' => '0',
                        'D27' => '0',
                        'D28' => '0',
                        'D29' => '0',
                        'D30' => '0',
                        'D31' => '0',
                        'D32' => '0',
                        'D33' => '0',
                        'D34' => '0'
                    ];
                    $insert[$filed] = $value['AMOUNT'];
                    $query->insert($insert);
                    $query = null;
                }
            }
            unset($data);
        }

        return 0;
    }
}
