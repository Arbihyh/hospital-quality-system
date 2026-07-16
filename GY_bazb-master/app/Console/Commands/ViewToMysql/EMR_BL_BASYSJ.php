<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use Illuminate\Console\Command;

class EMR_BL_BASYSJ extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:emr_bl_basysj {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步 临床书写首页的手术和诊断';

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
                'name' => 'laravel:emr_bl_basysj',
                'start' => $star,
                'end' => $end,
                'count' => 0,
                'created_at' => now(),
            ];
            $emr_bl_basysj = \App\Model\EMR_BL_BASYSJ::query()->orderByDesc('JLXH')->first();
            $sql = "SELECT JLXH,JZHM,BLBH,XMXH,XMMC,XMQZ,DYYS,DLLJ,GLZD,KSMRZ,SYBTX,XMNM FROM PORTAL55_EMR.EMR_BL_BASYSJ WHERE (XMXH=638 OR XMXH=498) AND JLXH > {$emr_bl_basysj->JLXH} ORDER BY JLXH";
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
                    'JLXH' => $item['JLXH'],
                    'JZHM' => $item['JZHM'],
                    'BLBH' => $item['BLBH'],
                    'XMXH' => $item['XMXH'],
                    'XMMC' => $item['XMMC'],
                    'XMQZ' => $item['XMQZ'],
                    'DYYS' => $item['DYYS'],
                    'DLLJ' => $item['DLLJ'],
                    'GLZD' => $item['GLZD'],
                    'KSMRZ' => $item['KSMRZ'],
                    'SYBTX' => $item['SYBTX'],
                    'XMNM' => $item['XMNM'],
                ];

                \App\Model\EMR_BL_BASYSJ::query()->updateOrInsert(['JLXH' => $insertData['JLXH']], $insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d', strtotime($star) + 86400);
        }
        return 0;
    }
}
