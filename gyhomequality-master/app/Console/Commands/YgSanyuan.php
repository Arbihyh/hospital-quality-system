<?php

namespace App\Console\Commands;

use App\Model\Staff;
use Illuminate\Console\Command;

class YgSanyuan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:yg_sanyuan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步员工代码(oracle:(table:PORTAL_HIS.BTF_GY_GY_YGDM))到mysql(table:staff)';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }
    public static $con;
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $offset = 1;
        $limit = 99;
        while (true) {
            $end_page = $offset + $limit;
            $username = env("ORACLE_USERNAME", "zdyh");
            $password = env("ORACLE_PASSWORD", "zdyh");
            $connection = env("ORACLE_HOST", "172.16.9.8");
            $port = env("ORACLE_PORT", "1521");
            $tns = env("ORACLE_TNS", "his");
            $con = oci_connect($username, $password, $connection . ":" . $port . "/" . $tns, "UTF8");
            if (!$con) {
                $e = oci_error();
                print_r(htmlentities($e["message"])) . "\n";
                die();
            }
            self::$con = $con;
            $sql = "SELECT * FROM (SELECT ROWNUM r,a.* FROM PORTAL_HIS.BTF_GY_YGDM a WHERE a.YGDM != 'BSSA' ORDER BY a.YGDM ASC) WHERE r between $offset and $end_page";
            $data = oci_parse(self::$con, $sql);
            oci_execute($data, OCI_DEFAULT);
            $result = [];
            while ($row = oci_fetch_assoc($data)) {
                $result[] = $row;
            }
            if (empty($result)) {
                break;
            }
            foreach ($result as $item) {
                echo "YGDM:".$item['YGDM']." - ".date('Y-m-d H:i:s') . PHP_EOL;
                $yg = [
                    'code' => $item['YGDM'],
                    'YGBH' => $item['YGBH'],
                    'name' => $item['YGXM'],
                    'ksdm' => $item['KSDM'],
                    //                    'base_code' => $item['ZYBH'],
                    'sfz' => $item['SFZH'],
                    'ygjb' => $item['YGJB'],        //员工级别代码
                    'ygjb_text' => $item['JBMC']    //员工级别名称
                ];
                Staff::query()->updateOrInsert(['code'=>$item['YGDM']],$yg);

            }
            sleep(1);
            $offset += 100;
        }
        var_dump("同步完成");
        return 0;
    }
}
