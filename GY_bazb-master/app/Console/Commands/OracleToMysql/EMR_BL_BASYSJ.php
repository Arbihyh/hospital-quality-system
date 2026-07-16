<?php

namespace App\Console\Commands\OracleToMysql;

use Illuminate\Console\Command;

class EMR_BL_BASYSJ extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oracleToMysql:EMR_BL_BASYSJ {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Oracle数据库中的EMR_BL_BASYSJ表同步到Mysql';

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
        $this->info('Oracle数据库中的EMR_BL_BASYSJ表同步到Mysql - 开始同步');

        ini_set('default_socket_timeout', 0);

        $page = (int)$this->argument('page') ?: 0;
        $pageSize = 100;
        while (true){
            echo $page.PHP_EOL;

            $pageStart = $page*$pageSize+1;
            $pageEnd = $page*$pageSize+$pageSize;

            $con = oci_connect('PORTAL55_EMR', 'emr#2023', '10.10.11.21:1521/ODS', 'UTF8');
            if (!$con) {
                $e = oci_error();
                print_r(htmlentities($e['message'])) . "\n";
                die;
            }

            $sql = "SELECT * FROM (SELECT A.*, ROWNUM RN FROM (SELECT * FROM PORTAL55_EMR.EMR_BL_BASYSJ)A WHERE ROWNUM <= ".$pageEnd." and XMXH in(498,638)) WHERE RN >= ".$pageStart." and XMXH in(498,638)";
            $result = oci_parse($con, $sql);
            oci_execute($result,OCI_DEFAULT);
            while ($row = oci_fetch_assoc($result)) {
                $data[] = $row;
            }

            $page++;
            if (empty($data)) {
                break;
            }
            foreach ($data as $item){
                $where = ['JLXH' => $item['JLXH']];
                $insertData = [
                    'BLBH' => $item['BLBH'] ?? '',
                    'XMXH' => $item['XMXH'] ?? '',
                    'XMMC' => $item['XMMC'] ?? '',
                    'XMQZ' => $item['XMQZ'] ?? '',
                    'DYYS' => $item['DYYS'] ?? '',
                    'DLLJ' => $item['DLLJ'] ?? '',
                    'GLZD' => $item['GLZD'] ?? '',
                    'KSMRZ' => $item['KSMRZ'] ?? '',
                    'SYBTX' => $item['SYBTX'] ?? '',
                    'XMNM' => $item['XMNM'] ?? ''
                ];
                \App\Model\EMR_BL_BASYSJ::query()->updateOrInsert($where,$insertData);
            }
        }

        $this->info('Oracle数据库中的EMR_BL_BASYSJ表同步到Mysql - 同步完毕');
    }
}
