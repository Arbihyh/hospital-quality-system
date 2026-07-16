<?php

namespace App\Console\Commands\OracleToMysql;

use Illuminate\Console\Command;

class OMR_BL01 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oracleToMysql:OMR_BL01 {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Oracle数据库中的omr_bl01表同步到Mysql';

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
        $this->info('Oracle数据库中的OMR_BL01表同步到Mysql - 开始同步');

        ini_set('default_socket_timeout', 0);

        $page = (int)$this->argument('page') ?: 0;
        $pageSize = 100;
        while (true){
            echo $page.PHP_EOL;

            $pageStart = $page*$pageSize+1;
            $pageEnd = $page*$pageSize+$pageSize;

            $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS', 'UTF8');
            if (!$con) {
                $e = oci_error();
                print_r(htmlentities($e['message'])) . "\n";
                die;
            }

            $sql = "SELECT BLBH,JZXH,BRID,BLLX,BLLB,BLMC,DLLB,DLJ,MBLB,MBBH,to_char(JLSJ,'yyyy-mm-dd hh24:mi:ss') as JLSJ,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJSJ,to_char(WCSJ,'yyyy-mm-dd hh24:mi:ss') as WCSJ,SXYS,SXKS,BRKS,BLZT,SYBZ,BZMBBH,BLBBZ,BLPF,JGID,ZZDY,PTID,BLNR_TXT,SBBZ FROM (SELECT A.*, ROWNUM RN FROM (SELECT * FROM PORTAL_HIS.OMR_BL01)A WHERE ROWNUM <= ".$pageEnd." order by CJSJ desc) WHERE RN >= ".$pageStart." order by CJSJ desc";
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
                $where = ['BLBH' => $item['BLBH']];
                $insertData = [
                    'JZXH' => $item['JZXH'] ?? '',
                    'BRID' => $item['BRID'] ?? '',
                    'BLLX' => $item['BLLX'] ?? '',
                    'BLLB' => $item['BLLB'] ?? '',
                    'BLMC' => $item['BLMC'] ?? '',
                    'DLLB' => $item['DLLB'] ?? '',
                    'DLJ' => $item['DLJ'] ?? '',
                    'MBLB' => $item['MBLB'] ?? '',
                    'MBBH' => $item['MBBH'] ?? '',
                    'JLSJ' => $item['JLSJ'] ?? '',
                    'CJSJ' => $item['CJSJ'] ?? '',
                    'WCSJ' => $item['WCSJ'] ?? '',
                    'SXYS' => $item['SXYS'] ?? '',
                    'SXKS' => $item['SXKS'] ?? '',
                    'BRKS' => $item['BRKS'] ?? '',
                    'BLZT' => $item['BLZT'] ?? '',
                    'SYBZ' => $item['SYBZ'] ?? '',
                    'BZMBBH' => $item['BZMBBH'] ?? '',
                    'BLBBZ' => $item['BLBBZ'] ?? '',
                    'BLPF' => $item['BLPF'] ?? '',
                    'JGID' => $item['JGID'] ?? '',
                    'ZZDY' => $item['ZZDY'] ?? '',
                    'PTID' => $item['PTID'] ?? '',
                    'BLNR_TXT' => $item['BLNR_TXT'] ?? '',
                    'SBBZ' => $item['SBBZ'] ?? '',
                ];
                \App\Model\OMR_BL01::query()->updateOrInsert($where,$insertData);
            }
        }

        $this->info('Oracle数据库中的OMR_BL01表同步到Mysql - 同步完毕');
    }
}
