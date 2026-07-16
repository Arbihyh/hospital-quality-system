<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\SyncRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class V_JMGS_BASY_QBL extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:v_jmgs_basy_qbl {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '视图增量同步到mysql BL01 BLXG';

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

        while (true){
            echo $star.PHP_EOL;
            if ($star > $end) {
                break;
            }
            $syncRecordData = [
                'name' => 'laravel:v_jmgs_basy_qbl',
                'start' => $star,
                'end' => $end,
                'count' => 0,
                'created_at' => now(),
            ];

            $con = oci_connect('zdyh', 'zdyh', '172.16.9.8:1521/his', 'UTF8');
            if (!$con) {
                $e = oci_error();
                print_r(htmlentities($e['message'])) . "\n";
                die;
            }

            $sql = "SELECT a.*,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZJ,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJ,to_char(WCSJ,'yyyy-mm-dd hh24:mi:ss') as WJ,to_char(XGSJ,'yyyy-mm-dd hh24:mi:ss') as XJ FROM PORTAL55_EMR.V_JMGS_BASY_QBL a WHERE CJSJ BETWEEN TO_DATE('".$star."', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('".$end."', 'yyyy-MM-dd HH24:mi:ss')";
            $result = oci_parse($con, $sql);
            oci_execute($result,OCI_DEFAULT);
            $data = [];
            while ($row = oci_fetch_assoc($result)) {
                $data[] = $row;
            }
            if (empty($data)){
                SyncRecord::query()->insert($syncRecordData);
                break;
            }
            foreach ($data as $item){
                $result = [
                    'BLBH' => $item['BLBH'] ?? '',
                    'JZHM' => $item['ZYH'] ?? '',
                    'BRBH' => $item['BRBH'] ?? '',
                    'BLLX' => $item['BLLX'] ?? '',
                    'BLLB' => $item['BLLB'] ?? '',
                    'BLMC' => $item['BLMC'] ?? '',
                    'BLZM' => $item['BLZM'] ?? '',
                    'DLLB' => $item['DLLB'] ?? '',
                    'DLJ' => $item['DLJ'] ?? '',
                    'MBLB' => $item['MBLB'] ?? '',
                    'MBBH' => $item['MBBH'] ?? '',
                    'ZXSJ' => $item['ZJ'] ?? '',
                    'CJSJ' => $item['CJ'] ?? '',
                    'WCSJ' => $item['WJ'] ?? '',
                    'SXYS' => $item['SXYS'] ?? '',
                    'BRKS' => $item['BRKS'] ?? '',
                    'CJKS' => $item['CJKS'] ?? '',
                    'BLZT' => $item['BLZT'] ?? '',
                    'BRXM' => $item['BRXM'] ?? '',
                    'BRZD' => $item['BRZD'] ?? '',
                    'SSYS' => $item['SSYS'] ?? '',
                    'SYBZ' => $item['SYBZ'] ?? '',
                    'BZMBBH' => $item['BZMBBH'] ?? '',
                    'BLYM' => $item['BLYM'] ?? '',
                    'YMJL' => $item['YMJL'] ?? '',
                    'RYZDSJ' => $item['RYZDSJ'] ?? '',
                    'PTID' => $item['PTID'] ?? '',
                    'BLZSTJ' => $item['BLZSTJ'] ?? '',
                    'JGID' => $item['JGID'] ?? '',
                    'SQDH' => $item['SQDH'] ?? '',
                    'ZDMC' => $item['ZDMC'] ?? '',
                    'ZDLX' => $item['ZDLX'] ?? '',
                    'CXPX' => $item['CXPX'] ?? '',
                    'SBBZ' => $item['SBBZ'] ?? '',
                    'WZZT' => $item['WZZT'] ?? ''
                ];
                $str = '';
                if (!empty($item['BLNR'])) {
                    $text = $item['BLNR']->load();
                    $item['BLNR']->free();
                    $mde = mb_detect_encoding($text, array("ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5'));
                    if ($mde) {
                        $str = mb_convert_encoding($text, 'utf-8', $mde);
                    }
                }else{
                    Log::debug($item['BLBH'] . "---痕迹内容为空");
                }
                $temp = [
                    'JLXH' => $item['JLXH'] ?? '',
                    'BLBH' => $item['BLBH'] ?? '',
                    'XGGH' => $item['XGGH'] ?? '',
                    'XGSJ' => $item['XJ'] ?? '',
                    'HJNR' => $str,
                ];
                EMR_BL_BLXG::query()->updateOrInsert(['BLBH'=>$temp['BLBH']],$temp);
                EMR_BL_BL01::query()->updateOrInsert(['JZHM'=>$result['JZHM'],'BLBH'=>$result['BLBH']],$result);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d',strtotime($star)+600);
        }
        return 0;
    }
}
