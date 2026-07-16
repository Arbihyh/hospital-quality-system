<?php

namespace App\Console\Commands\OracleToMysql;

use App\Model\EMR_BL_BL01_NEW;
use App\Model\PatientInfo;
use Illuminate\Console\Command;

class EMR_BL_BASYSJ_NEW extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oracleToMysql:EMR_BL_BASYSJ_NEW {page?}';

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
        $this->info('Oracle数据库中的EMR_BL_BASYSJ_NEW表同步到Mysql - 开始同步');

        ini_set('default_socket_timeout', 0);

        $page = 1;
        while (true){
            $data = PatientInfo::query()
                ->paginate(10, ['MED_REC_ID'],'page', $page)
                ->toArray();

            if ($page == 1) {
                echo '数据总条数：'.$data['total'].PHP_EOL.'总页数：'.$data['last_page'].PHP_EOL;
            } elseif ($page > $data['last_page']) {
                // 如果当前页码大于最大页码则结束
                break;
            }
            echo $page.PHP_EOL;
            $page++;

            $zyhList = array_column($data['data'],'MED_REC_ID');
            $blbhList = EMR_BL_BL01_NEW::query()->whereIn('JZHM',$zyhList)->pluck('BLBH')->toArray();
            if (empty($blbhList)) {
                continue;
            }
            $blbhList = implode(',',$blbhList);

            $con = oci_connect('PORTAL55_EMR', 'emr#2023', '10.10.11.21:1521/ODS', 'UTF8');
            if (!$con) {
                $e = oci_error();
                print_r(htmlentities($e['message'])) . "\n";
                die;
            }

            $sql = "SELECT * FROM PORTAL55_EMR.EMR_BL_BASYSJ WHERE BLBH in(".$blbhList.")";
            $result = oci_parse($con, $sql);
            oci_execute($result,OCI_DEFAULT);
            while ($row = oci_fetch_assoc($result)) {
                $data[] = $row;
            }

            if (empty($data)) {
                continue;
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
                \App\Model\EMR_BL_BASYSJ_NEW::query()->updateOrInsert($where,$insertData);
            }
        }

        $this->info('Oracle数据库中的EMR_BL_BASYSJ_NEW表同步到Mysql - 同步完毕');
    }
}
