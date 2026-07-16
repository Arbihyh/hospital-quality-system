<?php

namespace App\Console\Commands;

use App\Model\PatientInfo;
use Illuminate\Console\Command;

class PatientDischargeProcedure extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:pdp';

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
        $limit = 1;
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
            if(empty($keys)) {
                die;
            }
            $keys = implode(',',$keys);
            echo $keys."\n";
            $list = self::selectPdp($keys);
            self::insertPdp($list);
            $page++;
        }
        return 0;
    }
    //医嘱本数据查询
    public static function selectPdp($list){
        $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS',"UTF8");
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql = "SELECT ZYH,BAH,CYSJ,SSCZMC,SSCZBM,SSCZRI,SSSX,SFZYSS,SSJB,SSLX,SZXM,SZBM,YZXM,YZYSBM,EZXM,EZYSBM,QKDJ,YHDJ,MZFS,MZYSXM,MZYSBM,SSSKSSJ,SSJSSJ,SFWRJSS,SSPB,to_char(CYSJ,'yyyy-mm-dd hh24:mi:ss') as CYSJ1,to_char(SSCZRI,'yyyy-mm-dd hh24:mi:ss') as SSCZRI1,to_char(SSSKSSJ,'yyyy-mm-dd hh24:mi:ss') as SSSKSSJ1,to_char(SSJSSJ,'yyyy-mm-dd hh24:mi:ss') as SSJSSJ1 FROM PORTAL_HIS.V_JMGS_BASY_SS_GK WHERE ZYH IN (" . $list . ")";
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);
        return $result;
    }
    //医嘱本数据
    public static function insertPdp($data){
        foreach ($data as $item) {
            $list = [
                'ZYH' => $item['ZYH'] ?? '',
                'BAH' => $item['BAH'] ?? '',
                'CYSJ' => $item['CYSJ1'] ?? '',
                'SSCZMC' => $item['SSCZMC'] ?? '',
                'SSCZBM' => $item['SSCZBM'] ?? '',
                'SSCZRI' => $item['SSCZRI1'] ?? '',
                'SSSX' => $item['SSSX'] ?? '',
                'SFZYSS' => $item['SFZYSS'] ?? '',
                'SSJB' => $item['SSJB'] ?? '',
                'SSLX' => $item['SSLX'] ?? '',
                'SZXM' =>  $item['SZXM'] ?? '',
                'SZBM' => $item['SZBM'] ?? '',
                'YZXM' => $item['YZXM'] ?? '',
                'YZYSBM' => $item['YZYSBM'] ?? '',
                'EZXM' => $item['EZXM'] ?? '',
                'EZYSBM' => $item['EZYSBM'] ?? '',
                'QKDJ' => $item['QKDJ'] ?? '',
                'YHDJ' => $item['YHDJ'] ?? '',
                'MZFS' => $item['MZFS'] ?? '',
                'MZYSXM' => $item['MZYSXM'] ?? '',
                'MZYSBM' => $item['MZYSBM'] ?? '',
                'SSSKSSJ' => $item['SSSKSSJ1'] ?? '',
                'SSJSSJ' => $item['SSJSSJ1'] ?? '',
                'SFWRJSS' => $item['SFWRJSS'] ?? '',
                'SSPB' => $item['SSPB'] ?? '',
            ];
            \App\Model\PatientDischargeProcedure::query()->insert($list);
        }
    }
}
