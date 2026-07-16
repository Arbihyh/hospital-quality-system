<?php

namespace App\Console\Commands;

use App\Model\RuleWordMap;
use App\Services\EsSaveService;
use Illuminate\Console\Command;

class AAC01 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:AAC01 {start_time?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步AAC01数据';

    public static $con;

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

        $startTime = $this->argument("start_time");
        $this->AAC01($startTime);
    }

    /**
     * @param string $zyh 根据指定住院号同步brry数据
     * @param int $sync_data 同步brry表数据，不质控
     * @param int $last_id 质控从什么时候开始同步brry的数据，这个是一个6位住院号
     * @return bool
     * 三院 SELECT CYPB,ZYH,ZYHM as AAA28,BRKS,BRXM,ZZYS as ZZYSDM,ZSYS,to_char(RYRQ,'yyyy-mm-dd HH24:mi:ss') as AAB01,to_char(CYRQ,'yyyy-mm-dd HH24:mi:ss') as AAC01,BRBQ,BRCH as CH,ZYYS as GCYSDM,ZSYS as ZRYS,ZLXZ as ZLZZDM,MZYS,CYFS,RYCS as ZYCS FROM PORTAL_HIS.ZY_BRRY
     * 预警消息，brry数据同步
     */
    public function AAC01($startTime = "")
    {
        $data = $this->getAAC01Data($startTime);
        if (empty($data)) {
            return false;
        }

        $data = array_column($data, null, 'ZYH');
        $data = array_values($data);
        foreach ($data as $item) {
            $AAC01 = !empty($item['AAC01']) ? $item['AAC01'] : "";

            echo $item['ZYH'] . ' - ' . $AAC01 . PHP_EOL;
            \App\Model\ZY_BRRY::query()->where('ZYH', $item['ZYH'])->update(['AAC01' => $AAC01]);
            EsSaveService::zy_brry($item['ZYH']);
            \App\Model\PatientInfo::query()->where('MED_REC_ID', $item['ZYH'])->update(['AAC01' => $AAC01]);
        }
    }

    /**
     * @return array
     * 获取HIS中BRRY的数据
     */
    public function getAAC01Data($startTime = "")
    {
        $username = env('HISDB_USERNAME', '');
        $password = env('HISDB_PASSWORD', '');
        $connection = env('HISDB_HOST', '');
        $port = env('HISDB_PORT', '');
        $tns = env('HISDB_TNS', '');
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        // 同步所有数据
        if (!$startTime) {
            $startTime = date("Ymd", time()-86400);
        }

        $sql = RuleWordMap::query()->where("id", 9007)->value("keyword");
        $map9013 = RuleWordMap::query()->where("id", 9013)->value("keyword");
        $sql .= " WHERE {$map9013} > TO_DATE('" . $startTime . "000000', 'yyyy-MM-dd HH24:mi:ss')";
        $result = oci_parse($con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }

        return $data;
    }
}
