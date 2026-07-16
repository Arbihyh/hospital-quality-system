<?php

namespace App\Console\Commands;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use Illuminate\Console\Command;

class Yj01 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:Yj01';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 同步Yj01表';

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
        $star = '2022-01-01';
//        $between = ['2022-04-05 00:00:00','2022-04-05 23:59:59'];
//        $del = EMR_BL_BL01::query()->whereBetween('ZXSJ',$between)->pluck('BLBH');
//        EMR_BL_BL01::query()->whereIn('BLBH',$del)->delete();
//        EMR_BL_BLXG::query()->whereIn('BLBH',$del)->delete();
        while (true){
            echo $star."\n";
            $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS', 'UTF8');
            if (!$con) {
                $e = oci_error();
                print_r(htmlentities($e['message'])) . "\n";
                die;
            }
            //$sql = "SELECT * FROM (SELECT ROWNUM r,a.*,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZJ,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJ,to_char(WCSJ,'yyyy-mm-dd hh24:mi:ss') as WJ FROM PORTAL55_EMR.V_JMGS_BASY_QBL a) WHERE ZXSJ BETWEEN TO_DATE('2022-12-01 00:00:00', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('2022-12-01 00:00:00', 'yyyy-MM-dd HH24:mi:ss') AND r BETWEEN $start AND $end";
            $sql = "SELECT ZYH,SQDH FROM PORTAL55_EMR.V_JMGS_BASY_QBL a WHERE ZXSJ";
            $result = oci_parse($con, $sql);
            oci_execute($result,OCI_DEFAULT);
            $data = [];
            while ($row = oci_fetch_assoc($result)) {
                $data[] = $row;
            }
            if (empty($data)){
                break;
            }
            foreach ($data as $item){
                $temp = [
                    'ZYH' => $item['ZYH'] ?? '',
                    'SQDH' => $item['SQDH'] ?? '',
                ];
                \App\Model\Yj01::query()->insert($temp);
            }
            $star = date('Y-m-d',strtotime($star)+86400);
        }
        return 0;
    }
}
