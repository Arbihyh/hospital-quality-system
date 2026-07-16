<?php

namespace App\Console\Commands;

use App\Model\PatientCostInfo;
use App\Model\PatientInfo;
use Illuminate\Console\Command;
use Pheanstalk\Pheanstalk;
use function GuzzleHttp\json_encode;

class SaveZF extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:saveZF';

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
        $page = 1;
        $limit = 100;
        while (true){
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
            echo $page."\n";
            if(empty($keys)) {
                die;
            }
            $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS', 'UTF8');
            if (!$con) {
                $e = oci_error();
                print_r(htmlentities($e['message'])) . "\n";
                die;
            }
            $in = implode(',',$keys);
            $sql = "SELECT ADA0101,MED_REC_ID FROM PORTAL_HIS.INIT_MED_REC_MAIN WHERE MED_REC_ID IN (".$in.")";
            $result = oci_parse($con, $sql);
            oci_execute($result,OCI_DEFAULT);
            $data = [];
            while ($row = oci_fetch_assoc($result)) {
                $data[] = $row;
            }
            if (empty($data)){
                die;
            }
            $data = array_column($data,null,'MED_REC_ID');
            $patient = PatientInfo::query();
            $patientCost = PatientCostInfo::query();
            foreach ($keys as $item){
                $patient->where('MED_REC_ID',$item)->update(['ADA0101'=>$data[$item]['ADA0101']]);
                $patientCost->where('AAA28',$item)->update(['ADA0101'=>$data[$item]['ADA0101']]);
            }
            $page++;
        }
        return 0;
    }
}
