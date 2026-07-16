<?php

namespace App\Console\Commands\DataFormat;

use App\Model\BLLB1;
use App\Model\EMR_BL_BL01;
use App\Model\Setting;
use Illuminate\Console\Command;

class Cyjl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'format:cyjl {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '解析出院记录';

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
     * @return true
     */
    public function handle()
    {
        $this->info('解析出院记录 - bllb1 开始');

        $page = (int)$this->argument('page') ?: 1;
        $this->cyjl($page);

        $this->info('解析出院记录 - bllb1 - 完毕');
    }

    public function cyjl($page)
    {
        $setName = 'gsh_bllb1';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $field = ['EMR_BL_BL01.BLBH','patient_info.AAA28','JZHM','MBLB','HJNR'];
            $data = EMR_BL_BL01::query()
                ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                ->leftJoin('patient_info', 'EMR_BL_BL01.JZHM', '=', 'patient_info.MED_REC_ID')
                ->where('BLLB', '=', 1)
                ->where('BLZT', '!=', 9)
//                ->where('patient_info.AAC01','>=','2021-01-01 00:00:00')
                ->where('EMR_BL_BL01.BLBH','>',$lastId)
//                ->where('EMR_BL_BL01.BLBH','=',5751133)
                ->orderBy('EMR_BL_BL01.BLBH')
                ->paginate(500, $field, 'page', $page)
                ->toArray();
            if (empty($data['data'])) {
                break;
            }
            echo $page . PHP_EOL;
            $page++;

            foreach ($data['data'] as $value) {
                echo $value['BLBH'].PHP_EOL;

                $HJNR = $value['HJNR'];
                $HJNR = str_replace("：",":",$HJNR);

                // 姓名
                $cyjlData['XM'] = $this->getXM($HJNR);

                // 入院日期
                $cyjlData['RYRQ'] = $this->getRYRQ($HJNR);
                $cyjlData['AAB01'] = $this->getRYRQ($HJNR,1);

                // 性别
                $cyjlData['XB'] = $this->getXB($HJNR);

                // 出院日期
                $cyjlData['CYRQ'] = $this->getCYRQ($HJNR);
                $cyjlData['AAC01'] = $this->getCYRQ($HJNR,1);

                // 年龄
                $nl_arr = $this->getNL($HJNR);
                $cyjlData['NL_STR'] = $nl_arr['NL_STR'];
                $cyjlData['NL'] = $nl_arr['NL'];
                $cyjlData['MONTH'] = $nl_arr['MONTH'];
                $cyjlData['DAY'] = $nl_arr['DAY'];
                $cyjlData['HOUR'] = $nl_arr['HOUR'];
                $cyjlData['MINUTE'] = $nl_arr['MINUTE'];

                // 住院天数
                $cyjlData['ZYTS'] = $this->getZYTS($HJNR);

                // 入院情况
                $cyjlData['RYQK'] = $this->getRYQK($HJNR);

                // 初步诊断
                $cyjlData['CBZD'] = $this->getCBZD($HJNR);
                $cyjlData['CBZD_FIRST'] = '';
                if ($cyjlData['CBZD']) {
                    $CHZD = json_decode($cyjlData['CBZD'], true);
                    $cyjlData['CBZD_FIRST'] = !empty($CHZD[0]) ? $CHZD[0] : '';
                }

                // 诊疗经过
                $cyjlData['ZLJG'] = $this->getZLJG($HJNR);

                // 出院情况
                $cyjlData['CYQK'] = $this->getCYQK($HJNR);

                // 出院诊断
                $cyjlData['CYZD'] = $this->getCyzd($HJNR);
                $cyjlData['CYZD_FIRST'] = '';
                if ($cyjlData['CYZD']) {
                    $CYZD = json_decode($cyjlData['CYZD'], true);
                    $cyjlData['CYZD_FIRST'] = !empty($CYZD[0]) ? $CYZD[0] : '';
                }

                // 出院医嘱
                $cyjlData['CYYZ'] = $this->getCYYZ($HJNR);

                // 科室
                $cyjlData['KS'] = $this->getKS($HJNR);

                // 床号
                $cyjlData['CH'] = $this->getCH($HJNR);

                // 住院号
                $cyjlData['WB_ZYH'] = $this->getWBZYH($HJNR);

//                $cyjlData['BLBH'] = $value['BLBH'];
                $cyjlData['AAA28'] = $value['AAA28'];
                $cyjlData['ZYH'] = $value['JZHM'];
                $cyjlData['MBLB'] = $value['MBLB'];

                BLLB1::query()->updateOrInsert(['BLBH'=>$value['BLBH']], $cyjlData);
//                $insertData[] = $cyjlData;

                $lastBLBH = $value['BLBH'];
            }
        }

        if (!empty($lastBLBH)) {
            Setting::query()->where('name', '=', $setName)->update(['content' => $lastBLBH]);
        }

        return true;
    }

    /**
     * 解析姓名
     * @param $HJNR
     * @return string
     */
    public function getXM($HJNR)
    {
        $HJNR = str_replace(" ","",$HJNR);

        $arr = explode("姓名:",$HJNR);
        $XM = '';
        if (!empty($arr[1])) {
            $arr = explode("\n",$arr[1]);
            $XM = !empty(trim($arr[0])) ? desensitize(trim($arr[0]), 1, 1, '*') : '';
        }

        return $XM;
    }

    /**
     * 入院日期
     * @param $HJNR
     * @param $type
     * @return string
     */
    public function getRYRQ($HJNR, $type=0)
    {
        $HJNR = str_replace("   "," ",$HJNR);
        $HJNR = str_replace("  "," ",$HJNR);

        $arr = explode("入院日期:",$HJNR);
        $RYRQ = '';
        if (!empty($arr[1])) {
            $arr = explode("\n",$arr[1]);
            if (!empty(trim($arr[0]))) {
                if ($type) {
                    $RYRQ = trim($arr[0]);
                } else {
                    $RYRQ = date('Y-m-d H:i:s', strtotime(trim($arr[0])));
                }
            }
        }

        return $RYRQ;
    }

    /**
     * 解析性别
     * @param $HJNR
     * @return string
     */
    public function getXB($HJNR)
    {
        $HJNR = str_replace(" ","",$HJNR);

        $arr = explode("性别:",$HJNR);
        $XB = '';
        if (!empty($arr[1])) {
            $arr = explode("\n",$arr[1]);
            $XB = trim($arr[0]);
        }

        return $XB;
    }

    /**
     * 出院日期
     * @param $HJNR
     * @param $type
     * @return string
     */
    public function getCYRQ($HJNR,$type=0)
    {
        $HJNR = str_replace("   "," ",$HJNR);
        $HJNR = str_replace("  "," ",$HJNR);

        $arr = explode("出院日期:",$HJNR);
        $CYRQ = '';
        if (!empty($arr[1])) {
            $arr = explode("\n",$arr[1]);
            if (!empty(trim($arr[0]))) {
                if ($type) {
                    $CYRQ = trim($arr[0]);
                } else {
                    $CYRQ = date('Y-m-d H:i:s', strtotime(trim($arr[0])));
                }
            }
        }

        return $CYRQ;
    }

    /**
     * 解析年龄
     * @param $HJNR
     * @return array
     */
    public function getNL($HJNR)
    {
        $HJNR = str_replace(" ","",$HJNR);

        $arr = explode("年龄:",$HJNR);
        $NL = 0;
        $str = '';
        if (!empty($arr[1])) {
            $arr = explode("\n",$arr[1]);
            $str = $arr[0];
            if (stripos($arr[0],'岁') !== false) {
                $arr = explode("岁", trim($arr[0]));
                $NL = $arr[0];
            }
        }

        $returnData = ['NL_STR'=>$str,'NL'=>$NL,'MONTH'=>'','DAY'=>'','HOUR'=>'','MINUTE'=>''];
        if (!empty($str)) {
            // 月
            preg_match_all("/(\d+)月/", $str, $month);
            $returnData['MONTH'] = $month[1][0] ?? '';

            // 天
            preg_match_all("/(\d+)天/", $str, $day);
            $returnData['DAY'] = $day[1][0] ?? '';

            // 小时
            preg_match_all("/(\d+)小时/", $str, $hour);
            $returnData['HOUR'] = $hour[1][0] ?? '';

            // 分钟
            preg_match_all("/(\d+)分钟/", $str, $minute);
            $returnData['MINUTE'] = $minute[1][0] ?? '';
        }

        return $returnData;
    }

    /**
     * 住院天数
     * @param $HJNR
     * @return array|string|string[]
     */
    public function getZYTS($HJNR)
    {
        $HJNR = str_replace(" ","",$HJNR);

        $arr = explode("住院天数:",$HJNR);
        $ZYTS = 0;
        if (!empty($arr[1])) {
            $arr = explode("\n",$arr[1]);
            $ZYTS = str_replace("天", "", trim($arr[0]));
        }

        return $ZYTS;
    }

    /**
     * 入院情况
     * @param $HJNR
     * @return string
     */
    public function getRYQK($HJNR)
    {
        $arr = explode("入院情况:",$HJNR);
        $RYQK = '';
        if (!empty($arr[1])) {
            if (stripos($arr[1],'初步诊断:') !== false) {
                $arr = explode("初步诊断",$arr[1]);
            } elseif (stripos($arr[1],'入院诊断:') !== false) {
                $arr = explode("入院诊断",$arr[1]);
            }
            $RYQK = trim($arr[0]);
        }

        return $RYQK;
    }

    /**
     * 初步诊断
     * @param $HJNR
     * @return string
     */
    public function getCBZD($HJNR)
    {
        $HJNR = str_replace(";","；",$HJNR);
        $HJNR = str_replace("诊疗过程","诊疗经过",$HJNR);
        $HJNR = str_replace("入院诊断","初步诊断",$HJNR);
        $arr = explode("初步诊断:", $HJNR);
        $cbzd = '';
        if (!empty($arr[1])) {
            if (stripos($arr[1], '诊疗经过') !== false) {
                $arr = explode("诊疗经过", $arr[1]);
                $cbzd = trim($arr[0]);
            } elseif (stripos($arr[1], '出院情况') !== false) {
                $arr = explode("出院情况", $arr[1]);
                $cbzd = trim($arr[0]);
            }
        }

        $cyzdNameList = [];
        $cbzd = str_replace(",","，",$cbzd);
        if ($cbzd) {
            $list = [];
            if (stripos($cbzd, '1.') !== false) {
                for ($i=1; $i<=100; $i++) {
                    $key = $i.'.';
                    $key1 = ($i+1).'.';
                    if (stripos($cbzd, $key) !== false) {
                        $cyzdArr = explode($key, $cbzd);
                        $cyzdArr = explode($key1, $cyzdArr[1]);
                        $nameStr = trim($cyzdArr[0]);
                        $list[] = $nameStr;
                    }
                }
            } elseif (stripos($cbzd, '，') !== false) {
                $list = explode("，",$cbzd);
            } elseif (stripos($cbzd, '；') !== false) {
                $list = explode("；",$cbzd);
            } elseif (stripos($cbzd, '、') !== false) {
                $list = explode("、",$cbzd);
            } elseif (stripos($cbzd, ' ') !== false) {
                $list = explode(" ",$cbzd);
            } else {
                $list = [$cbzd];
            }

            foreach ($list as $val) {
                $val = trim($val);
                $val = str_replace(";","",$val);
                $val = str_replace("；","",$val);
                $val = str_replace("。","",$val);
                $val = str_replace("，","",$val);
                $val = str_replace(",","",$val);
                if ($val) {
                    $cyzdNameList[] = $val;

                }
            }
        }

        if ($cyzdNameList) {
            return json_encode($cyzdNameList, JSON_UNESCAPED_UNICODE);
        }

        return '';
    }

    /**
     * 诊疗过程
     * @param $HJNR
     * @return string
     */
    public function getZLJG($HJNR)
    {
        $HJNR = str_replace("诊疗过程","诊疗经过",$HJNR);
        $arr = explode("诊疗经过:",$HJNR);
        $str = '';
        if (!empty($arr[1])) {
            if (stripos($arr[1],'出院情况:') !== false) {
                $arr = explode("出院情况:",$arr[1]);
                if (stripos($arr[0],'出院诊断:') !== false) {
                    $arr = explode("出院诊断:",$arr[0]);
                }
            } elseif (stripos($arr[1],'出院诊断:') !== false) {
                $arr = explode("出院诊断:",$arr[1]);
                if (stripos($arr[0],'出院情况:') !== false) {
                    $arr = explode("出院情况:",$arr[0]);
                }
            }
            $str = trim($arr[0]);
        }

        return $str;
    }

    /**
     * 出院情况
     * @param $HJNR
     * @return string
     */
    public function getCYQK($HJNR)
    {
        $arr = explode("出院情况:",$HJNR);
        $str = '';
        if (!empty($arr[1])) {
            if (stripos($arr[1],'出院诊断') !== false) {
                $arr = explode("出院诊断",$arr[1]);
            } elseif (stripos($arr[1],'出院医嘱') !== false) {
                $arr = explode("出院医嘱",$arr[1]);
            }
            $str = trim($arr[0]);
        }

        return $str;
    }

    /**
     * 出院诊断
     * @param $HJNR
     * @return string
     */
    public function getCyzd($HJNR)
    {
        $HJNR = str_replace(";","；",$HJNR);
        $arr = explode("出院诊断:", $HJNR);
        $cyzd = '';
        if (!empty($arr[1])) {
            if (stripos($arr[1], '出院情况') !== false) {
                $arr = explode("出院情况", $arr[1]);
                $cyzd = trim($arr[0]);
            } elseif (stripos($arr[1], '出院医嘱') !== false) {
                $arr = explode("出院医嘱", $arr[1]);
                $cyzd = trim($arr[0]);
            }
        }

        $cyzdNameList = [];
        $cyzd = str_replace(",","，",$cyzd);
        if ($cyzd) {
            $list = [];
            if (stripos($cyzd, '1.') !== false) {
                for ($i=1; $i<=100; $i++) {
                    $key = $i.'.';
                    $key1 = ($i+1).'.';
                    if (stripos($cyzd, $key) !== false) {
                        $cyzdArr = explode($key, $cyzd);
                        $cyzdArr = explode($key1, $cyzdArr[1]);
                        $nameStr = trim($cyzdArr[0]);
                        $list[] = $nameStr;
                    }
                }
            } elseif (stripos($cyzd, '，') !== false) {
                $list = explode("，",$cyzd);
            } elseif (stripos($cyzd, '；') !== false) {
                $list = explode("；",$cyzd);
            } elseif (stripos($cyzd, '、') !== false) {
                $list = explode("、",$cyzd);
            } elseif (stripos($cyzd, ' ') !== false) {
                $list = explode(" ",$cyzd);
            } else {
                $list = [$cyzd];
            }

            foreach ($list as $val) {
                $val = trim($val);
                $val = str_replace(";","",$val);
                $val = str_replace("；","",$val);
                $val = str_replace("。","",$val);
                $val = str_replace("，","",$val);
                $val = str_replace(",","",$val);
                if ($val) {
                    $cyzdNameList[] = $val;

                }
            }
        }

        if ($cyzdNameList) {
            return json_encode($cyzdNameList, JSON_UNESCAPED_UNICODE);
        }

        return '';
    }

    /**
     * 出院医嘱
     * @param $HJNR
     * @return false|string
     */
    public function getCYYZ($HJNR)
    {
        $HJNR = str_replace(" ","",$HJNR);

        $arr = explode("出院医嘱:",$HJNR);
        $str = '';
        if (!empty($arr[1])) {
            // 医师签名
            if (stripos($arr[1],'医师签名') !== false) {
                $arr = explode("医师签名",$arr[1]);
            } elseif (stripos($arr[1],'第1页') !== false) {
                $arr = explode("医师签名",$arr[1]);
            } elseif (stripos($arr[1],'第2页') !== false) {
                $arr = explode("医师签名",$arr[1]);
            } elseif (stripos($arr[1],'第3页') !== false) {
                $arr = explode("医师签名",$arr[1]);
            } elseif (stripos($arr[1],'第4页') !== false) {
                $arr = explode("医师签名",$arr[1]);
            }
            $str = trim($arr[0]);
        }

        if ($str) {
            $cyyzArr = explode("\n",$str);
            return json_encode($cyyzArr, JSON_UNESCAPED_UNICODE);
        }

        return $str;
    }

    /**
     * 科室
     * @param $HJNR
     * @return string
     */
    public function getKS($HJNR)
    {
        $HJNR = str_replace(" ","",$HJNR);

        $arr = explode("科室:",$HJNR);
        $str = '';
        if (!empty($arr[1])) {
            $arr = explode("\n",$arr[1]);
            $str = trim($arr[0]);
        }

        return $str;
    }

    /**
     * 床号
     * @param $HJNR
     * @return string
     */
    public function getCH($HJNR)
    {
        $HJNR = str_replace(" ","",$HJNR);

        $arr = explode("床号:",$HJNR);
        $str = '';
        if (!empty($arr[1])) {
            $arr = explode("\n",$arr[1]);
            $str = trim($arr[0]);
        }

        return $str;
    }

    /**
     * 住院号
     * @param $HJNR
     * @return string
     */
    public function getWBZYH($HJNR)
    {
        $HJNR = str_replace(" ","",$HJNR);

        $arr = explode("住院号:",$HJNR);
        $str = '';
        if (!empty($arr[1])) {
            $arr = explode("\n",$arr[1]);
            $str = trim($arr[0]);
        }

        return $str;
    }

}
