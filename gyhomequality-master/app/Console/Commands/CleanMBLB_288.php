<?php

namespace App\Console\Commands;

use App\Model\BLLB288;
use App\Model\EMR_BL_BL01;
use Illuminate\Console\Command;

class CleanMBLB_288 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bl:cleanmblb_288';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清洗bl01表中病程记录(BLLB=288)关联BLXG中的HJNR字段，将字段里面的死亡时间清洗到SWSJ字段';

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
     * Summary of handle
     * @return void
     */
    public function handle()
    {
        echo "bl:cleanmblb_288 start" . date('Y-m-d H:i:s') . PHP_EOL;
        /**
         * 查询数据
         */
        $blData = EMR_BL_BL01::query()
            ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.MBLB', '=', 288)
            ->get(["EMR_BL_BL01.JZHM", "EMR_BL_BL01.BLBH", "EMR_BL_BLXG.HJNR"])
            ->toArray();

        foreach ($blData as $bl) {
            $data = [];
            $data = [
                'ZLJG' => $this->getContent(["诊疗经过"], $bl['HJNR'], '诊疗经过'),
                'SWSJ' => $this->getContent(["死亡时间", ""], $bl['HJNR'], '死亡时间')
            ];

            echo $data['ZLJG'].$data['SWSJ'].PHP_EOL;

            BLLB288::query()->updateOrInsert([
                'ZYH' => $bl['JZHM'],
                'BLBH' => $bl['BLBH'],
            ],$data);
        }


        echo "bl:cleanmblb_288 end" . date('Y-m-d H:i:s') . PHP_EOL;
        exit();
    }

    function getContent($ruleMapArr, $str, $field)
    {
        // 去除所有空格
        $str = preg_replace("/\s+/", "", $str);
        $field = preg_replace("/\s+/", "", $field);

        $str = str_replace(["{", "}"], "", $str);
        $str = str_replace("：", ":", $str);
        // 1. 先找到指定字段的位置
        $start = mb_strpos($str, "{$field}");
        if ($start === false) {
            return "";
        }

        // 2. 起始位置（跳过字段名和冒号）
        if(!in_array($field,['体格检查','检查结果:'])){
            $start += mb_strlen($field) + 1;
        }else{
            $start += mb_strlen($field);
        }

        // 3. 查找下一个分隔点
        $end = PHP_INT_MAX;


        foreach ($ruleMapArr as $keyword) {
            $keyword = preg_replace("/\s+/", "", $keyword);

            //如果字符串长度小于等于 $start 直接复制字符串长度 避免报错
            $pos = mb_strlen($str) <= $start ? mb_strlen($str) : mb_strpos($str, $keyword, $start);

            if ($pos !== false && $pos > $start && $pos < $end) {
                $end = $pos;
            }elseif($pos !== false && $end == PHP_INT_MAX){
                $end = $pos;
            }
        }

        // 4. 提取内容
        if ($end === PHP_INT_MAX) {
            $content = trim(mb_substr($str, $start));
        } else {
            $content = trim(mb_substr($str, $start, $end - $start));
        }
        return $content;
    }
}
