<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use Illuminate\Console\Command;

class GY_YGDM extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:gy_ygdm {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步GY_YGDM';

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

        $star = $this->argument('start');
        $end = $this->argument('end');

        $con = oci_connect('zdyh', 'zdyh', '172.16.9.8:1521/his', "UTF8");
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        while (true) {
            echo $star . PHP_EOL;
            if ($star > $end) {
                break;
            }
            $syncRecordData = [
                'name' => 'laravel:gy_ygdm',
                'start' => $star,
                'end' => $end,
                'count' => 0,
                'created_at' => now(),
            ];

            $sql = "SELECT YGXM name,YGDM code,ZYBH base_code,SFZH sfz,KSDM ksdm,YGJB  ygjb FROM PORTAL_HIS.GY_YGDM ";
            $result = oci_parse($con, $sql);
            oci_execute($result, OCI_DEFAULT);
            $data = [];
            while ($row = oci_fetch_assoc($result)) {
                $data[] = $row;
            }
            if (empty($data)) {
                exit;
            }
            foreach ($data as $item) {
                $insertData = [
                    'name' => $item['name'],
                    'code' => $item['code'],
                    'base_code' => $item['base_code'],
                    'sfz' => $item['sfz'],
                    'ksdm' => $item['ksdm'],
//                    'status' => $item['status'],
                    'ygjb' => $item['ygjb'],
//                    'ygjb_text' => $item['ygjb_text'],
                ];

                \App\Model\ZY_HCMX::query()->updateOrInsert(['ZYH' => $insertData['ZYH']], $insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d', strtotime($star) + 86400);
        }
        return 0;
    }
}
