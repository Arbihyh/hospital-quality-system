<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use Illuminate\Console\Command;

class BA_RECEIVEl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:ba_receivel {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步 无纸化目录';

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
        $con = oci_connect('zzj', 'zzj', '172.16.2.177:1433/CEMS', "UTF8");
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
                'name' => 'laravel:ba_mr_class_number',
                'start' => $star,
                'end' => $end,
                'count' => 0,
            ];

            $sql = "SELECT patient_id,visit_id,MrClass,Quantity,serial_no FROM BA_RECEIVEl";
            $result = oci_parse($con, $sql);
            oci_execute($result, OCI_DEFAULT);
            $data = [];
            while ($row = oci_fetch_assoc($result)) {
                $data[] = $row;
            }
            if (empty($data)) {
                SyncRecord::query()->insert($syncRecordData);
                exit;
            }
            foreach ($data as $item) {
                $insertData = [
                    'patient_id' => $item['patient_id'],
                    'visit_id' => $item['visit_id'],
                    'MrClass' => $item['MrClass'],
                    'Quantity' => $item['Quantity'],
                    'serial_no' => $item['serial_no'],
                ];

                \App\Model\BA_RECEIVEl::query()->updateOrInsert(['ZYH' => $insertData['ZYH']], $insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d', strtotime($star) + 86400);
        }
        return 0;
    }
}
