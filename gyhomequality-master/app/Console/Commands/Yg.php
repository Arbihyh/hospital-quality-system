<?php

namespace App\Console\Commands;

use App\Model\Staff;
use Illuminate\Console\Command;

class Yg extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:yg';

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
        $limit = 99;
        while (true) {
            $end_page = $offset + $limit;
            $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS',"UTF8");
            if (!$con) {
                $e = oci_error();
                print_r(htmlentities($e['message'])) . "\n";
                die;
            }
            $sql = "SELECT * FROM (SELECT ROWNUM r,a.* FROM PORTAL_HIS.GY_YGDM WHERE YGDM != 'BSSA' ORDER BY YGDM ASC) WHERE r between $offset and $end_page";
            $data = oci_parse($con, $sql);
            oci_execute($data, OCI_DEFAULT);
            $result = [];
            while ($row = oci_fetch_assoc($data)) {
                $result[] = $row;
            }
            if (empty($result)) {
                break;
            }
            $yg = [];
            foreach ($result as $item) {
                $yg[] = [
                    'code' => $item['YGDM'],
                    'name' => $item['YGXM'],
                    'ksdm' => $item['KSDM'],
                    'base_code' => $item['ZYBH'],
                    'sfz' => $item['SFZH'],
                ];
            }
            if (!empty($yg)){
                Staff::query()->insert($yg);
            }
            sleep(3);
            $offset += 100;
        }

        return 0;
    }
}
