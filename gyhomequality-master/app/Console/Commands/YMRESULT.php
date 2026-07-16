<?php

namespace App\Console\Commands;

use App\Model\PatientInfo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class YMRESULT extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:ymResult';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
            if (date('H') == '06'){
                // break;
            }
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
            if (empty($list)){
                continue;
            }
            self::Yzb($list);
            $page++;
        }
        return 0;
    }
    //医嘱本数据查询
    public static function selectYzb($list){
        $con = oci_connect('jmba', 'jmba', '172.16.2.29:1521/bslis',"UTF8");
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql = "SELECT a.*,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJ,to_char(JSSJ,'yyyy-mm-dd hh24:mi:ss') as JJ,to_char(BGSJ,'yyyy-mm-dd hh24:mi:ss') as BJ FROM JMBA.V_JMGS_YMresult a WHERE ZYH IN (" . $list . ")";
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
        foreach ($data as &$item) {
            $item['CJSJ'] = $item['CJ'];
            $item['JSSJ'] = $item['JJ'];
            $item['BGSJ'] = $item['BJ'];
            unset($item['CJ']);
            unset($item['JJ']);
            unset($item['BJ']);
        }
        foreach ($data as $val) {
            foreach ($val as &$v) {
                if (empty($v)) {
                    $v = '';
                }
            }
        }
        if (!empty($data)){
            $zyh = array_column($data, 'ZYH');
            DB::table('V_JMGS_YMresult')->whereIn('ZYH', $zyh)->delete();
            DB::table('V_JMGS_YMresult')->insert($data);
        }
    }
}
