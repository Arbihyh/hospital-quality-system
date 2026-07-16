<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use Illuminate\Console\Command;

class BA_BRSY extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:ba_brsy {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步ba_brsy';

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
                'name' => 'laravel:ba_brsy',
                'start' => $star,
'end' => $end,
                'count' => 0,
            ];

            $sql = "SELECT ZYH AS AAA28,CYBQ FROM PORTAL_HIS.ba_brsy";
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
                    'AAA28' => $item['AAA28'],
                    'CYBQ' => $item['CYBQ'],
                ];
                \App\Model\BaBrsy::query()->updateOrInsert(['AAA28' => $insertData['AAA28']], $insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d', strtotime($star) + 86400);
        }
        return 0;
    }
}
