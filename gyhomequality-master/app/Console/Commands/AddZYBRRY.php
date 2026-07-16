<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Model\ZY_BRRY;

class AddZYBRRY extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:ZYBRRY';

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

        $sql = "SELECT A.ZYH, A.BRID FROM PORTAL_HIS.ZY_BRRY A";
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $list = [];
        while ($row = oci_fetch_assoc($data)) {
            $list[] = $row;
        }

        if (!empty($list)) {
            $hospitalName = config('confAdmin.hospital_name');
            foreach ($list as $val) {
                echo $val['ZYH'].PHP_EOL;

                $insertData = [
                    'ZYH' => $val['ZYH'],
                    'BRID' => $val['BRID'] ?? ''
                ];

                $where = ['ZYH'=>$val['ZYH']];
                ZY_BRRY::query()->updateOrInsert($where,$insertData);
            }
        }

        $this->info('数据同步完毕');
    }
}
