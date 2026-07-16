<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use Illuminate\Console\Command;

class YK_TYPK_sanyuan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:yk_typk_sanyuan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步三院药品库';

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

        //$star = $this->argument('start');
        //$end = $this->argument('end');

        $username = env("ORACLE_USERNAME", "zdyh");
        $password = env("ORACLE_PASSWORD", "zdyh");
        $connection = env("ORACLE_HOST", "172.16.9.8");
        $port = env("ORACLE_PORT", "1521");
        $tns = env("ORACLE_TNS", "his");
        $con = oci_connect($username, $password, $connection . ":" . $port . "/" . $tns, "UTF8");

        //$con = oci_connect('zdyh', 'zdyh', '172.16.9.8:1521/his', "UTF8");
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        //while (true) {
        //echo $star . PHP_EOL;
        //if ($star > $end) {
        //    break;
        //}
        $syncRecordData = [
            'name' => 'laravel:yk_typk',
            //'start' => $star,
            //'end' => $end,
            'count' => 0,
        ];

        $sql = "SELECT ZXDW,ZXCD,ZXBZ,ZSSF,ZJPB,ZFPB,ZDJL,ZBLB,YYBZ,YWMC,YPZC,YPXZ,YPXQ,YPXH,YPSX,YPMC,YPJL,YPGG,YPDW,YPDM,YPDC,YPBH,YLXZ,YKZF,YJTXFS,YFGG,YFDW,YFBZ,YDYSY,YCYL,YCJL,YBFL,to_char(XZSJ,'yyyy-mm-dd hh24:mi:ss') as XZSJ,XTSB,TYPE,TYMC,TSYY,TSJD,TPN,QZCL,QTDM,PYDM,PSPB,PCBM,MRXL,KWBM,KSSFL,KSDJ,KSBZ,KJYFYY,KJSPBZ,KJJB,JZYY,JLDW,JBYWBZ,HZXZ,GYFF,GWYP,GGQC,GCSL,FYFS,DWQC,DDDZ,DCSL,CYYW,CFYP,CFLX,BXLC,BFGG,BFDW,BFBZ,ATCM,ABC,TSYP,MESS,WBDM,PYDM AS JXDM FROM BTF_YK_TYPK";
        $result = oci_parse($con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }

        foreach ($data as $item) {
            $insertData = [
                'ZXDW' => $item['ZXDW'],
                'ZXCD' => $item['ZXCD'],
                'ZXBZ' => $item['ZXBZ'],
                'ZSSF' => $item['ZSSF'],
                'ZJPB' => $item['ZJPB'],
                'ZFPB' => $item['ZFPB'],
                'ZDJL' => $item['ZDJL'],
                'ZBLB' => $item['ZBLB'],
                'YYBZ' => $item['YYBZ'],
                'YWMC' => $item['YWMC'],
                'YPZC' => $item['YPZC'],
                'YPXZ' => $item['YPXZ'],
                'YPXQ' => $item['YPXQ'],
                'YPXH' => $item['YPXH'],
                'YPSX' => $item['YPSX'],
                'YPMC' => $item['YPMC'],
                'YPJL' => $item['YPJL'],
                'YPGG' => $item['YPGG'],
                'YPDW' => $item['YPDW'],
                'YPDM' => $item['YPDM'],
                'YPDC' => $item['YPDC'],
                'YPBH' => $item['YPBH'],
                'YLXZ' => $item['YLXZ'],
                'YKZF' => $item['YKZF'],
                'YJTXFS' => $item['YJTXFS'],
                'YFGG' => $item['YFGG'],
                'YFDW' => $item['YFDW'],
                'YFBZ' => $item['YFBZ'],
                'YDYSY' => $item['YDYSY'],
                'YCYL' => $item['YCYL'],
                'YCJL' => $item['YCJL'],
                'YBFL' => $item['YBFL'],
                'XZSJ' => $item['XZSJ'],
                'XTSB' => $item['XTSB'],
                'WBDM' => $item['WBDM'],
                'TYPE' => $item['TYPE'],
                'TYMC' => $item['TYMC'],
                'TSYY' => $item['TSYY'],
                'TSJD' => $item['TSJD'],
                'TPN' => $item['TPN'],
                'TSYP' => $item['TSYP'],
                'QZCL' => $item['QZCL'],
                'QTDM' => $item['QTDM'],
                'PYDM' => $item['PYDM'],
                'PSPB' => $item['PSPB'],
                'PCBM' => $item['PCBM'],
                'MRXL' => $item['MRXL'],
                'MESS' => $item['MESS'],
                'KWBM' => $item['KWBM'],
                'KSSFL' => $item['KSSFL'],
                'KSDJ' => $item['KSDJ'],
                'KSBZ' => $item['KSBZ'],
                'KJYFYY' => $item['KJYFYY'],
                'KJSPBZ' => $item['KJSPBZ'],
                'KJJB' => $item['KJJB'],
                'JZYY' => $item['JZYY'],
                'JXDM' => $item['JXDM'],
                'JLDW' => $item['JLDW'],
                'JBYWBZ' => $item['JBYWBZ'],
                'HZXZ' => $item['HZXZ'],
                'GYFF' => $item['GYFF'],
                'GWYP' => $item['GWYP'],
                'GGQC' => $item['GGQC'],
                'GCSL' => $item['GCSL'],
                'FYFS' => $item['FYFS'],
                'DWQC' => $item['DWQC'],
                'DDDZ' => $item['DDDZ'],
                'DCSL' => $item['DCSL'],
                'CYYW' => $item['CYYW'],
                'CFYP' => $item['CFYP'],
                'CFLX' => $item['CFLX'],
                'BXLC' => $item['BXLC'],
                'BFGG' => $item['BFGG'],
                'BFDW' => $item['BFDW'],
                'BFBZ' => $item['BFBZ'],
                'ATCM' => $item['ATCM'],
                'ABC' => $item['ABC'],
            ];
            //打印药品序号
            echo "YPXH:" . $insertData['YPXH'] . " - " . date('Y-m-d H:i:s') . PHP_EOL;

            \App\Model\YK_TYPK::query()->updateOrInsert(['YPXH' => $insertData['YPXH']], $insertData);
        }

        //记录日志
        $syncRecordData['count'] = count($data);
        SyncRecord::query()->insert($syncRecordData);
        //$star = date('Y-m-d', strtotime($star) + 86400);
        //}
        return 0;
    }
}
