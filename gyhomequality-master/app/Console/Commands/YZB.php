<?php

namespace App\Console\Commands;

use App\Model\PatientInfo;
use Illuminate\Console\Command;

class YZB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:yzb';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '取医嘱本数据';

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
        $page = 1;
        $limit = 1;
        while (true){
            $offset = ($page - 1) * $limit;
            $keys = PatientInfo::query()
                ->offset($offset)
                ->limit($limit)
                ->orderBy('MED_REC_ID')
                ->pluck('MED_REC_ID');
            if ($keys){
                $keys = $keys->toArray();
            }else{
                break;
            }
            if(empty($keys)) {
                die;
            }
            $keys = implode(',',$keys);
            echo $keys."\n";
            $list = self::selectYzb($keys);
            self::Yzb($list);
            $page++;
        }
        return 0;
    }
    //医嘱本数据查询
    public static function selectYzb($list){
        $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS',"UTF8");
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql = "SELECT *,to_char(TZSJ,'yyyy-mm-dd hh24:mi:ss') as TJ,to_char(XZJDSJ,'yyyy-mm-dd hh24:mi:ss') as XJ,to_char(TZQRSJ,'yyyy-mm-dd hh24:mi:ss') as TZJ,to_char(APSJ,'yyyy-mm-dd hh24:mi:ss') as AJ,to_char(KZSJ,'yyyy-mm-dd hh24:mi:ss') as KJ,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZJ FROM PORTAL_HIS.EMR_YZB WHERE ZYH IN (" . $list . ")";
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);
        return $result;
    }
    //医嘱本数据
    public static function Yzb($data){
        foreach ($data as $item) {
            $list = [
                'ZYH' => $item['ZYH'],
                'YZBXH' => $item['YZBXH'] ?? '',
                'RID' => $item['RID'] ?? '',
                'YEPB' => $item['YEPB'] ?? '',
                'BRKS' =>  $item['BRKS'] ?? '',
                'BRBQ' => $item['BRBQ'] ?? '',
                'BRCH' => $item['BRCH'] ?? '',
                'YDYZLB' => $item['YDYZLB'] ?? '',
                'XMLB' => $item['XMLB'] ?? '',
                'XMID' => $item['XMID'] ?? '',
                'XMDJ' => $item['XMDJ'] ?? '',
                'YZZH' => $item['YZZH'] ?? '',
                'YZQX' => $item['YZQX'] ?? '',
                'YYSX' => $item['YYSX'] ?? '',
                'KZKS' => $item['KZKS'] ?? '',
                'KZYS' => $item['KZYS'] ?? '',
                'KZSJ' => $item['KJ'] ?? '',
                'YZMC' => $item['YZMC'] ?? '',
                'YPCD' => $item['YPCD'] ?? '',
                'FYSX' => $item['FYSX'] ?? '',
                'SYPC' => $item['SYPC'] ?? '',
                'GYTJ' => $item['GYTJ'] ?? '',
                'YCJL' => $item['YCJL'] ?? '',
                'JLDW' => $item['JLDW'] ?? '',
                'ZL' => $item['ZL'] ?? '',
                'ZLDW' => $item['ZLDW'] ?? '',
                'JJYZ' => $item['JJYZ'] ?? '',
                'BLYZ' => $item['BLYZ'] ?? '',
                'TZSJ' => $item['TJ'] ?? '',
                'TZYS' => $item['TZYS'] ?? '',
                'YZZT' => $item['YZZT'] ?? '',
                'ZXZT' => $item['ZXZT'] ?? '',
                'KZDY' => $item['KZDY'] ?? '',
                'ZTBZ' => $item['ZTBZ'] ?? '',
                'XZJDGH' => $item['XZJDGH'] ?? '',
                'XZJDSJ' => $item['XJ'] ?? '',
                'TZQRGH' => $item['TZQRGH'] ?? '',
                'TZQRSJ' => $item['TZJ'] ?? '',
                'APSJ' => $item['AJ'] ?? '',
                'YYTS' => $item['YYTS'] ?? '',
                'YSZT' => $item['YSZT'] ?? '',
                'SRCS' => $item['SRCS'] ?? '',
                'SRSD' => $item['SRSD'] ?? '',
                'ZXSD' => $item['ZXSD'] ?? '',
                'DS' => $item['DS'] ?? '',
                'DSDW' => $item['DSDW'] ?? '',
                'PSBZ' => $item['PSBZ'] ?? '',
                'PSJG' => $item['PSJG'] ?? '',
                'ZFPB' => $item['ZFPB'] ?? '',
                'YBLX' => $item['YBLX'] ?? '',
                'SPBH' => $item['SPBH'] ?? '',
                'CYJF' => $item['CYJF'] ?? '',
                'PLSX' => $item['PLSX'] ?? '',
                'CZBZ' => $item['CZBZ'] ?? '',
                'BZXX' => $item['BZXX'] ?? '',
                'SQDH' => $item['SQDH'] ?? '',
                'ZXKS' => $item['ZXKS'] ?? '',
                'YFGG' => $item['YFGG'] ?? '',
                'YFDW' => $item['YFDW'] ?? '',
                'YFBZ' => $item['YFBZ'] ?? '',
                'SFSJ' => $item['SFSJ'] ?? '',
                'YFYY' => $item['YFYY'] ?? '',
                'YFYYYY' => $item['YFYYYY'] ?? '',
                'QXKZ' => $item['QXKZ'] ?? '',
                'YYPS' => $item['YYPS'] ?? '',
                'FZLJ' => $item['FZLJ'] ?? '',
                'PASSINDEX' => $item['PASSINDEX'] ?? '',
                'QXMC' => $item['QXMC'] ?? '',
                'YZPLZH' => $item['YZPLZH'] ?? '',
                'LCTS' => $item['LCTS'] ?? '',
                'ZLFY' => $item['ZLFY'] ?? '',
                'YZLX' => $item['YZLX'] ?? '',
                'SSYZ' => $item['SSYZ'] ?? '',
                'CDA_PC' => $item['CDA_PC'] ?? '',
                'ZXSJ' => $item['ZJ'] ?? '',
                'NWARN' => $item['NWARN'] ?? ''
            ];
            \App\Model\Yzb::query()->insert($list);
        }
    }
}
