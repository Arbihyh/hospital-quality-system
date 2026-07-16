<?php

namespace App\Console\Commands\BigModel;

use App\Model\BigModelQualityResult;
use App\Model\BigModelTemplate;
use App\Model\CaseQuality;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\PatientInfo;
use Illuminate\Console\Command;

class Bl01_292 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'big_model:Bl01_292 {start?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '大模型-入院记录质控';

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
        $star = $this->argument('start');

        // 读取大模型设置的质控模板
        $temp = BigModelTemplate::query()->get()->toArray();

        // 根据模板设置的数据源和规则进行质控
        foreach ($temp as $t) {

        }

        $bl01 = EMR_BL_BL01::query()->selectRaw("min(BLBH) as BLBH")->where('BLLB', '=', 292)->where("ZXSJ", ">", $star)->limit(1)->get()->toArray();
        $id = $bl01[0]['BLBH'] ?? 0;
        $url = 'http://172.16.9.43:7997/api/predict/';
        while (true) {
            $bl01 = EMR_BL_BL01::query()->select(['BLBH', 'JZHM', 'BRBH'])->where('BLLB', '=', 292)->where("BLBH", ">", $id)->limit(1000)->get()->toArray();
            if (empty($bl01)) {
                break;
            }
            $blbh = array_column($bl01, 'BLBH');
            $jzhm = array_column($bl01, 'JZHM');
            $id = max($blbh);
            $blxg = EMR_BL_BLXG::query()->whereIn('BLBH', $blbh)->get()->toArray();
            $blxg = array_column($blxg, null, 'BLBH');

            foreach ($bl01 as $item) {
                $startTime = time();
                $content = $blxg[$item['BLBH']]['HJNR'] ?? '';
                if (empty($content)) {
                    continue;
                }
                $content = str_replace(' ', '', $content) . "，判断这份病历初步诊断是否合理，说明原因，和应填写的初步诊断的疾病名称，结果以json返回，不要其他无关内容";
                $content = str_replace("\r\n", "", $content);
                $content = str_replace("\n", "", $content);
                $res = requestPost($url, ['query' => $content]);


                $response = $res->result->response ?? "";
                if(strpos($response, '不合理') === false){
                    var_dump('结果合理，无需保存：'.date("Y-m-d H:i:s")."\r\n");
                    continue;
                }
                $errorNotice = [
                    'basis' => json_encode([$response], 256),
                    'JZHM' => $item['JZHM'],
                    'rule_id' => 267,
                    'code' => '',
                    'error_field' => ''
                ];
                CaseQuality::addData($errorNotice);
                PatientInfo::query()->where('MED_REC_ID', '=', $item['JZHM'])->update(['is_defect'=>1]);
                echo '用时：' . (time() - $startTime) . "秒\r\n";
                var_dump($res);
            }
        }


        $this->info('科室同步完毕');
    }


}
