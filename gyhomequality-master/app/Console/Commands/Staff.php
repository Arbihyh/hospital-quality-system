<?php

namespace App\Console\Commands;

use App\Model\Staff as st;
use Illuminate\Console\Command;

class Staff extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:staff';

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
        $sql = "SELECT * FROM PORTAL_HIS.GY_YGDM";
        $result = oci_parse($con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            exit;
        }
        $ygjb = [
            1 => '主任医师',
            2 => '副主任医师',
            3 => '实习医生',
            4 => '护士长',
            5 => '护士',
            6 => '主治医师',
            7 => '医师',
            8 => '主管护师',
            9 => '护师',
            10 => '副主任护师',
            11 => '检验师',
            12 => '主管检验师',
            13 => '副主任检验师',
            14 => '主任检验师',
            15 => '技师',
            16 => '主管技师',
            17 => '副主任技师',
            18 => '主管药师',
            19 => '药师',
            20 => '主任护士'
        ];

        foreach ($data as $item) {
            $where = ['code' => $item['YGDM']];

            $ygjb_text = !empty($ygjb[$item['YGJB']]) ? $ygjb[$item['YGJB']] : '';
            $insertData = [
                'name' => $item['YGXM'] ?? '',
                'base_code' => $item['ZYBH'] ?? '',
                'sfz' => $item['SFZH'] ?? '',
                'ksdm' => $item['KSDM'] ?? '',
                'ygjb' => $item['YGJB'] ?? '',
                'YGBH' => $item['YGBH'] ?? '',
                'ygjb_text' => $ygjb_text,
            ];

            st::query()->updateOrInsert($where,$insertData);
        }
    }
}
