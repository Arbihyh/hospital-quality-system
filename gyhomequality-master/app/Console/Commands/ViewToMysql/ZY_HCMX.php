<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\PatientInfo;
use App\Model\SyncRecord;
use Illuminate\Console\Command;

class ZY_HCMX extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:zy_hcmx {start?} {end?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步护士分床时间';

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

        if(empty($star)){
            $star = date("Ymd", time()-9*24*3600);
        }
        if(empty($end)){
            $end = date("Ymd", time());
        }

        $username = env('ORACLE_USERNAME', 'zdyh');
        $password = env('ORACLE_PASSWORD', 'zdyh');
        $connection = env('ORACLE_HOST', '172.16.9.8');
        $port = env('ORACLE_PORT', '1521');
        $tns = env('ORACLE_TNS', 'his');
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        echo $star . '-' . $end . PHP_EOL;

        $syncRecordData = [
            'name' => 'laravel:zy_hcmx',
            'start' => $star,
            'end' => $end,
            'count' => 0,
            'created_at' => now(),
        ];

        $sql = "SELECT ZYH,to_char(HCRQ,'yyyy-mm-dd hh24:mi:ss') as HCRQ,to_char(ZZRQ,'yyyy-mm-dd hh24:mi:ss') as ZZRQ,HCLX,HQCH,HHCH,HQKS,HHKS,HQBQ,HHBQ,JSCS,CZGH,JGID FROM PORTAL_HIS.ZY_HCMX WHERE DSG_LAST_MODIFY_TIME BETWEEN TO_DATE('".$star."000000', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('".$end."235959', 'yyyy-MM-dd HH24:mi:ss')";
        $result = oci_parse($con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            SyncRecord::query()->insert($syncRecordData);
            var_dump("未同步到数据");
            exit;
        }
        foreach ($data as $item) {
            $insertData = [
                'ZYH' => $item['ZYH'],
                'HCRQ' => $item['HCRQ'],
                'ZZRQ' => $item['ZZRQ'],
                'HCLX' => $item['HCLX'],
                'HQCH' => $item['HQCH'],
                'HHCH' => $item['HHCH'],
                'HQKS' => $item['HQKS'],
                'HHKS' => $item['HHKS'],
                'HQBQ' => $item['HQBQ'],
                'HHBQ' => $item['HHBQ'],
                'JSCS' => $item['JSCS'],
                'CZGH' => $item['CZGH'],
                'JGID' => $item['JGID']
            ];

            \App\Model\ZY_HCMX::query()->updateOrInsert(['ZYH' => $insertData['ZYH'], 'HCRQ' => $insertData['HCRQ']], $insertData);
            PatientInfo::query()->where('MED_REC_ID', $item['ZYH'])->update(['updated_at'=>date("Y-m-d H:i:s")]);
        }

        //记录日志
        $syncRecordData['count'] = count($data);
        SyncRecord::query()->insert($syncRecordData);
        var_dump("同步完成");

        return 0;
    }
}
