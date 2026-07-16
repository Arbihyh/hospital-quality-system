<?php

namespace App\Console\Commands\SyncMysql;

use App\Model\Department;
use Illuminate\Console\Command;

class DepartmentData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:department';

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
        $username = env('ORACLE_USERNAME', 'zdyh');
        $password = env('ORACLE_PASSWORD', 'zdyh');
        $connection = env('ORACLE_HOST', '172.16.9.8');
        $port = env('ORACLE_PORT', '1521');
        $tns = env('ORACLE_TNS', 'his');
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }

        $sql = "SELECT A.*,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJSJ,to_char(GXSJ,'yyyy-mm-dd hh24:mi:ss') as GXSJ FROM PORTAL_HIS.GY_KSDM A";
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $list = [];
        while ($row = oci_fetch_assoc($data)) {
            $list[] = $row;
        }

        if (!empty($list)) {
            $hospitalName = config('confAdmin.hospital_name');
            foreach ($list as $val) {
                echo $val['KSMC'].PHP_EOL;

                $insertData = [
                    'dep_name' => $val['KSMC'],
                    'PYDM' => $val['PYDM'] ?? '',
                    'JGBM' => $val['JGBM'] ?? '',
                    'SJKS' => $val['SJKS'] ?? '',
                    'HSKS' =>  $val['HSKS'] ?? '',
                    'MZSY' => $val['MZSY'] ?? '',
                    'YJSY' => $val['YJSY'] ?? '',
                    'ZYSY' => $val['ZYSY'] ?? '',
                    'BQSY' => $val['BQSY'] ?? '',
                    'EDCW' => $val['EDCW'] ?? '',
                    'PLSX' => $val['PLSX'] ?? '',
                    'JGID' => $val['JGID'] ?? '',
                    'JHBL' => $val['JHBL'] ?? '',
                    'JHSR' => $val['JHSR'] ?? '',
                    'YPXE' => $val['YPXE'] ?? '',
                    'YZPX' => $val['YZPX'] ?? '',
                    'TJKS' => $val['TJKS'] ?? '',
                    'SFHSKS' => $val['SFHSKS'] ?? '',
                    'YBKS' => $val['YBKS'] ?? '',
                    'YYBM' => $val['YYBM'] ?? '',
                    'WSTJ_ZLKM' => $val['WSTJ_ZLKM'] ?? '',
                    'JMKSMC' => $val['JMKSMC'] ?? '',
                    'JMKSLC' => $val['JMKSLC'] ?? '',
                    'ZXYBZ' => $val['ZXYBZ'] ?? '',
                    'BQLX' => $val['BQLX'] ?? '',
                    'CWQX' => $val['CWQX'] ?? '',
                    'LXDH' => $val['LXDH'] ?? '',
                    'BZXX' => $val['BZXX'] ?? '',
                    'KSMM2' => $val['KSMM2'] ?? '',
                    'KSMM1' => $val['KSMM1'] ?? '',
                    'KSZFPB' => $val['KSZFPB'] ?? '',
                    'YYSJKS' => $val['YYSJKS'] ?? '',
                    'YYSJMC' => $val['YYSJMC'] ?? '',
                    'CJSJ' => $val['CJSJ'] ?? '',
                    'GXSJ' => $val['GXSJ'] ?? '',
                    'PTBZ' => $val['PTBZ'] ?? '',
                    'PTSC' => $val['PTSC'] ?? '',
                    'YQSB' => $val['YQSB'] ?? '',
                    'YYWZ' => $val['YYWZ'] ?? '',
                    'YYSJPY' => $val['YYSJPY'] ?? '',
                    'PTZDMC' => $val['PTZDMC'] ?? '',
                    'PTZD' => $val['PTZD'] ?? '',
                    'JXDY' => $val['JXDY'] ?? '',
                    'SX' => $val['SX'] ?? '',
                    'YJYY' => $val['YJYY'] ?? '',
                ];

                $where = ['hospital_name'=>$hospitalName,'dep_id'=>$val['KSDM']];
                Department::query()->updateOrInsert($where,$insertData);
            }
        }

        $this->info('科室同步完毕');
    }


}
