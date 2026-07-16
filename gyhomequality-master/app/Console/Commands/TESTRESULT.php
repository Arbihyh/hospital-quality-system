<?php

namespace App\Console\Commands;

use App\Model\PatientInfo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TESTRESULT extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:testResult';

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
        $star = '2021-01-01';
        while (true){
            if (date('H') == '06'){
                break;
            }

            $list = self::selectYzb($star);
            if (empty($list)){
                continue;
            }
            self::Yzb($list);
            $star = date('Y-m-d',strtotime($star)+86400);
        }
        return 0;
    }
    //医嘱本数据查询
    public static function selectYzb($star){
        $con = oci_connect('jmba', 'jmba', '172.16.2.29:1521/bslis',"UTF8");
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql = "SELECT a.*,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJ,to_char(JSSJ,'yyyy-mm-dd hh24:mi:ss') as JJ,to_char(BGSJ,'yyyy-mm-dd hh24:mi:ss') as BJ FROM JMBA.V_JMGS_TESTRESULT a WHERE BGSJ BETWEEN TO_DATE('".$star." 00:00:00', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('".$star." 23:59:59', 'yyyy-MM-dd HH24:mi:ss')";
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
    public static function Yzb($data){
        foreach ($data as $item) {
            $list = [];
            $list['CJSJ'] = $item['CJ'];
            $list['JSSJ'] = $item['JJ'];
            $list['BGSJ'] = $item['BJ'];
            $list['ZYH'] = $item['ZYH'] ?? '';
            $list['DW'] = $item['DW'] ?? '';
            $list['TXM'] = $item['TXM'] ?? '';
            $list['NO'] = $item['NO'] ?? '';
            $list['XM'] = $item['XM'] ?? '';
            $list['XB'] = $item['XB'] ?? '';
            $list['NL'] = $item['NL'] ?? '';
            $list['CH'] = $item['CH'] ?? '';
            $list['YBLX'] = $item['YBLX'] ?? '';
            $list['YBZT'] = $item['YBZT'] ?? '';
            $list['AAA28'] = $item['AAA28'] ?? '';
            $list['BQ'] = $item['BQ'] ?? '';
            $list['LCZD'] = $item['LCZD'] ?? '';
            $list['YW'] = $item['YW'] ?? '';
            $list['JYXM'] = $item['JYXM'] ?? '';
            $list['JG'] = $item['JG'] ?? '';
            $list['TS'] = $item['TS'] ?? '';
            $list['CKFW'] = $item['CKFW'] ?? '';
            $list['SJYS'] = $item['SJYS'] ?? '';
            $list['JYY'] = $item['JYY'] ?? '';
            $list['SHY'] = $item['SHY'] ?? '';
            \App\Model\TESTRESULT::query()->insert($list);
        }
    }
}
