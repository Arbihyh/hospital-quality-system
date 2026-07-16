<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\FeeDetailed;
use App\Model\SyncRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class YS_ZY_HZSQ extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:ys_zy_hzsq {start?} {end?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'sssq';

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
            'name' => 'laravel:sssq',
            'start' => $star,
            'end' => $end,
            'count' => 0,
            'created_at' => now(),
        ];

        $sql = "SELECT SQXH, JZHM, SQKS, SQYS, to_char(SQSJ,'yyyy-mm-dd hh24:mi:ss') as SQSJ, HZMD, HZMD2, HZSJ, YQDX, JJBZ, TJBZ, TJYS, TJSJ, ZFBZ, JSBZ, JSSJ, TXRY, BQZL, HZLX, BLBH, SQZD, JGID, JSYS, JZBZ, HZLB FROM PORTAL_HIS.YS_ZY_HZSQ  WHERE SQSJ BETWEEN TO_DATE('" . $star . "', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('" . $end . "', 'yyyy-MM-dd HH24:mi:ss')";
        $inResult = oci_parse($con, $sql);
        oci_execute($inResult, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($inResult)) {
            $data[] = $row;
        }
        if (empty($data)) {
            var_dump("未同步到数据");
            return false;
        }

        $inData = [];
        foreach ($data as $item) {
            $inData = [
                'SQXH'=>$item['SQXH'],
                'JZHM'=>$item['JZHM'],
                'SQKS'=>$item['SQKS'],
                'SQYS'=>$item['SQYS'],
                'SQSJ'=>$item['SQSJ'],
                'HZMD'=>$item['HZMD'],
                'HZMD2'=>$item['HZMD2'],
                'HZSJ'=>$item['HZSJ'],
                'YQDX'=>$item['YQDX'],
                'JJBZ'=>$item['JJBZ'],
                'TJBZ'=>$item['TJBZ'],
                'TJYS'=>$item['TJYS'],
                'TJSJ'=>$item['TJSJ'],
                'ZFBZ'=>$item['ZFBZ'],
                'JSBZ'=>$item['JSBZ'],
                'JSSJ'=>$item['JSSJ'],
                'TXRY'=>$item['TXRY'],
                'BQZL'=>$item['BQZL'],
                'HZLX'=>$item['HZLX'],
                'BLBH'=>$item['BLBH'],
                'SQZD'=>$item['SQZD'],
                'JGID'=>$item['JGID'],
                'JSYS'=>$item['JSYS'],
                'JZBZ'=>$item['JZBZ'],
                'HZLB'=>$item['HZLB'],
            ];
            \App\Model\YS_ZY_HZSQ::query()->updateOrInsert(['SQXH' => $item['SQXH'],], $inData);
        }
        //记录日志
        $syncRecordData['count'] = count($data);
        SyncRecord::query()->insert($syncRecordData);
        var_dump("同步完成");

        return 0;
    }
}
