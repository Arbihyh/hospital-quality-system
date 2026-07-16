<?php

namespace App\Console\Commands;

use App\Model\MainDiagnosis;
use App\Model\PatientInfo;
use Illuminate\Console\Command;

class OtherDiagnosis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:od';

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
        $offset = 1;
        $limit = 10;
        while (true) {
            $page = ($offset - 1) * $limit;
            $list = PatientInfo::query()
                ->offset($page)
                ->limit($limit)
                ->orderBy('MED_REC_ID', 'desc')
                ->pluck('MED_REC_ID');
            if ($list) {
                $list = $list->toArray();
            } else {
                break;
            }
            if (empty($list)) {
                break;
            }
            $list = implode(',',$list);
            $data = self::selectDiagnosis($list);
            self::diagnosis($data);
            $offset++;
        }
        return 0;
    }
    //诊断数据查询
    public static function selectDiagnosis($list){
        $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS',"UTF8");
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql1 = "SELECT * FROM PORTAL_HIS.V_JMGS_BASY_ZD WHERE ZYH IN (".$list.")";
        $data = oci_parse($con, $sql1);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);
        return $result;
    }
    //诊断数据写入
    public static function diagnosis($data){
        $main = [];
        $diagnosis = [];
        foreach ($data as $item){
            if ($item['ZZPB'] == 1){
                $main[] = [
                    'AAA28' => $item['ZYH'],
                    'ICD10_ID1' => $item['ZDBM'],
                    'ICD10_NAME' => $item['ZDMC'],
                    'DIA_ORDER' => $item['ZDXH'],
                    'LBMC' =>  $item['LBMC'],
                    'RYQK' => $item['RYQK']
                ];
            }else{
                $diagnosis[] = [
                    'AAA28' => $item['ZYH'],
                    'ICD10_ID1' => $item['ZDBM'],
                    'ICD10_NAME' => $item['ZDMC'],
                    'DIA_ORDER' => $item['ZDXH'],
                    'LBMC' =>  $item['LBMC'],
                    'RYQK' => $item['RYQK']
                ];
            }
        }
        if (!empty($diagnosis)){
            \App\Model\OtherDiagnosis::query()->insert($diagnosis);
        }
        if (!empty($main)){
            MainDiagnosis::query()->insert($main);
        }
    }
}
