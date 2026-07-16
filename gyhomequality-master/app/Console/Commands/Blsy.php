<?php

namespace App\Console\Commands;

use App\Model\Staff as st;
use Illuminate\Console\Command;
use App\Model\EMR_BL_BLSY;

class Blsy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:blsy';

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
        ini_set('default_socket_timeout', 0);
        $con = oci_connect('PORTAL55_EMR', 'emr#2023', '10.10.11.21:1521/ODS', 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $start = 0;
        $page = 1;
        while (true) {

            $end = $start + 1000;
            $sql = "SELECT  JLXH,BLBH,SYYS,to_char(SYSJ,'yyyy-mm-dd hh24:mi:ss') as SYSJTIME,to_char(JLSJ,'yyyy-mm-dd hh24:mi:ss') as JLSJTIME FROM EMR_BL_BLSY WHERE JLXH>" . $start . " AND JLXH<=" . $end . " ORDER BY JLXH ASC";
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
                $start = $item['JLXH'];
                echo $start."\r\n";
                $insertData = [
                    'JLXH' => $item['JLXH'],
                    'BLBH' => $item['BLBH'],
                    'SYYS' => $item['SYYS'],
                    'SYSJ' => $item['SYSJTIME'],
                    'JLSJ' => $item['JLSJTIME']
                ];
                EMR_BL_BLSY::query()->updateOrInsert(['JLXH' => $insertData['JLXH']], $insertData);
            }
            echo $page.PHP_EOL;
            $page++;
        }
    }
}
