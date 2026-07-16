<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\FeeDetailed;
use App\Model\PatientInfo;
use App\Model\SyncRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class V_ZY_FYMX extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:v_zy_fymx {start?} {end?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'v_zy_fymx';

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
            $star = date("Y-m-d H:i:s", time()-9*24*3600);
        }
        if(empty($end)){
            $end = date("Y-m-d H:i:s", time());
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
        echo $star.'-'.$end.PHP_EOL;

        $syncRecordData = [
            'name' => 'laravel:v_zy_fymx',
            'start' => $star,
            'end' => $end,
            'count' => 0,
            'created_at' => now(),
        ];

        $sql = "SELECT ZYH,FYXH,FYMC,to_char(ZFJE,'fm9999990.000') as ZFJE,to_char(JFRQ,'yyyy-mm-dd hh24:mi:ss') as JFRQ,FYSL,to_char(FYDJ,'fm9999990.000') as FYDJ,FYKS FROM PORTAL_HIS.V_ZY_FYMX  WHERE JFRQ BETWEEN TO_DATE('" . $star . "', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('" . $end . "', 'yyyy-MM-dd HH24:mi:ss')";
        $result = oci_parse($con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        $config = config('dictionaries');
        foreach ($data as $item) {
            $fee = [
                'AAA28' => $item['ZYH'],//病案号
                'FYXH' => $item['FYXH'] ?? '',//费用序号
                'FYMC' => $item['FYMC'] ?? '',//费用名称
                'ZFJE' => $item['ZFJE'] ?? '',//自付金额
                'JFRQ' => $item['JFRQ'] ?? '',//计费日期
                'FYSL' => $item['FYSL'] ?? '',//费用数量
                'FYDJ' => $item['FYDJ'] ?? '',//费用单价
                'ZJE' => $item['ZJE'] ?? '',//总金额
                'FYKS' => $item['FYKS'] ?? '',//费用科室
            ];
            FeeDetailed::query()->updateOrInsert(['AAA28' => $item['ZYH'], 'FYXH' => $item['FYXH']], $fee);
            PatientInfo::query()->where('MED_REC_ID', $item['ZYH'])->update(['updated_at'=>date("Y-m-d H:i:s")]);
        }

        //记录日志
        $syncRecordData['count'] = count($data);
        SyncRecord::query()->insert($syncRecordData);

        return 0;
    }
}
