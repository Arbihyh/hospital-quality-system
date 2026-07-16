<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\Department;
use App\Model\SyncRecord;
use Illuminate\Console\Command;

class MS_GHKS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:ms_ghks {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步 门诊挂号费';

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
            if ($star > $end) {
                break;
            }
            echo $star . PHP_EOL;
            $syncRecordData = [
                'name' => 'laravel:ms_ghks',
                'start' => $star,
                'end' => $end,
                'count' => 0,
                'created_at' => now(),
            ];

            $sql = "SELECT KSDM,KSMC,GHLB,PYDM,WBDM,JXDM,QTDM,GHF,ZLF,ZJMZ,GHXE,YGRS,YYRS,to_char(GHRQ, 'yyyy-mm-dd hh24:mi:ss') AS GHRQ,MZKS,TJPB,TJF,MZLB,JZXH,JJRGHF,FJJJ_ZXKS,YZKS,EKBZ,FKBZ,JGID,DB,DDXX,YQSB,KSJJ,ZYSX,ZQZB,to_char(CJSJ, 'yyyy-mm-dd hh24:mi:ss') AS CJSJ,to_char(GXSJ, 'yyyy-mm-dd hh24:mi:ss') AS GXSJ,NLXZ_KS,NLXZ_JS,NLXZ_LX,XBXZ,ZFPB,SPTKS,SPTMC,SFHLW FROM PORTAL_HIS.MS_GHKS WHERE GXSJ BETWEEN TO_DATE('{$star}', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('{$end}', 'yyyy-MM-dd HH24:mi:ss')";
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
                    'hospital_name' => '滨州医学院烟台附属医院',
                    'dep_id' => $item['KSDM'],
                    'dep_name' => $item['KSMC'],
                ];

                Department::query()->updateOrInsert(['KSDM' => $insertData['KSDM']], $insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d', strtotime($star) + 86400);
        }
        return 0;
    }
}
