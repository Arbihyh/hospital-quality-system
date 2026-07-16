<?php

namespace App\Console\Commands\OracleToMysql;

use App\Console\Commands\ViewToMysql\SSSQ;
use App\Model\SM_SSAP;
use App\Model\Staff;
use App\Model\ZY_BRRY;
use Illuminate\Console\Command;

class ShoumaORC extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oracle_shouma {start_time?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步oracle中的手麻数据';

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
        $this->info('Oracle数据库中的OMR_BL01表同步到Mysql - 开始同步');


        $host = env("SM_ORACLE_HOST");
        $username = env("SM_ORACLE_USERNAME");
        $password = env("SM_ORACLE_PASSWORD");
        $dataBase = env("SM_ORACLE_DATABASE");

        $con = oci_connect($username, $password, $host . ':1521/' . $dataBase, 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }

        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, "code", "YGBH");

        // 使用直接查询获取数据
        $startTime = $startTime ? date("Y-m-d 00:00:00", strtotime($startTime)) : date("Y-m-d 00:00:00", time() - 24 * 7 * 3600);
        $sql = "SELECT * FROM MEDCOMM.ZY_SSAP WHERE GXSJ>TO_DATE('{$startTime}', 'YYYY-MM-DD HH24:MI:SS')";
        $result = oci_parse($con, $sql);
        oci_execute($result, OCI_DEFAULT);
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }

        // SM_SSAP::query()->where('flag', '麦迪斯顿')->delete();
        foreach ($data as $item) {
            $this->info($item['SQDH']);
            //$brry = ZY_BRRY::query()->where(["AAA28" => $item["BAH"], "ZYCS" => $item["ZYCS"]])->first(["ZYH"]);
            $item["ZYH"] = $item["ZYCS"] ?? "";
            $item["ZYCS"] = "";
            /* if ($brry) {
                $item["ZYH"] = $brry->ZYH;
            } else {
                $brry = ZY_BRRY::query()
                    ->where('AAA28', $item["BAH"])
                    ->where('AAB01', '<=', $item['SSRQ'])
                    ->where(function ($query) use ($item) {
                        $query->where('AAC01', '>=', $item['SSRQ'])->orWhereNull('AAC01')->orWhere('AAC01', '');
                    })
                    ->first();

                if ($brry) {
                    $item["ZYH"] = $brry->ZYH;
                }
            } */
            $mc = $item["ICD9_SSCZMC"] ?? "";
            $item["ICD9_SSCZMC"] = $item["ICD9_SSCZBM"] ?? "";
            $item["ICD9_SSCZBM"] = $mc;
            $item["flag"] = "麦迪斯顿";
            $item["SZDM"] = $staff[$item["SZDM"]] ?? $item["SZDM"];
            $item["SQYS"] = $staff[$item["SQYS"]] ?? $item["SQYS"];
            SM_SSAP::addData($item);
        }
        $this->info('Oracle数据库中的OMR_BL01表同步到Mysql - 同步完毕');
    }
}
