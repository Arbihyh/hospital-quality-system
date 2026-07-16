<?php

namespace App\Services;

use App\Model\Bllb303;
use App\Model\EMR_BL_BL01;
use App\Model\MainOperation;
use App\Model\SecondaryOperation;
use Illuminate\Support\Arr;

/**
 * 评审指标
 */
class PszbService
{

    /**
     * 处理体征 发病时间
     * @param $zhusu
     * @param $symptomArr
     * @param $hospitalizeTime
     * @return array
     */
    public function handleData($zhusu, $symptomArr, $hospitalizeTime)
    {
        $sickTime = '';
        $sickSTime = ''; //小时格式的发病时间
        $symptom = '';
        foreach ($symptomArr as $value) {
            //处理加重
            if (strpos($zhusu, '加重') !== false) {
                preg_match_all("/加重.*?(\d{1,3}\.??\d??)[余]??(分钟|小时|日|天|周|月|年)/u", $zhusu, $res);
                preg_match_all("/加重.*?(半|一|二|三|四|五|六|七|八|九)(小时|日|天|周|月|年)/", $zhusu, $result);
            } else {
                if (is_array($value)) {
                    preg_match_all("/$value[0].*?(\d{1,3}\.??\d??)[余]??(分钟|小时|日|天|周|月|年)/u", $zhusu, $res);
                    preg_match_all("/$value[0].*?(半|一|二|三|四|五|六|七|八|九)(小时|天|周|月|年)/", $zhusu, $result);
                } else {
                    preg_match_all("/$value.*?(\d{1,3}\.??\d??)[余]??(分钟|小时|日|天|周|月|年)/u", $zhusu, $res);
                    preg_match_all("/$value.*?(半|一|二|三|四|五|六|七|八|九)(小时|日|天|周|月|年)/", $zhusu, $result);
                }
            }

            //判断是否存在
            if(!empty(array_filter($result))){ //如果数字正则匹配没有 汉字匹配存在
                    //根据映射重新赋值
                    $result[1][0] = data_get(['半'=>0.5,'一'=>1,'二' =>2,'三'=>3,'四'=>4,'五'=>5,'六'=>6,'七'=>7,'八'=>8,'九'=>9],$result[1][0],0);
                    //将汉字结果 赋值res
                    $res = $result;
            }

            if (array_filter($res)) {
                switch ($res[2][0]) {
                    case '分钟':
                        $sickSTime = round(($res[1][0] / 60), 2) . '小时';
                        $sickTime = date('Y-m-d H:i', strtotime($hospitalizeTime) - $res[1][0] * 60);
                        break;
                    case '小时':
                        $sickSTime = ($res[1][0]) . '小时';
                        $sickTime = date('Y-m-d H:i', strtotime($hospitalizeTime) - $res[1][0] * 60 * 60);
                        break;
                    case '日':
                    case '天':
                        $sickSTime = ($res[1][0] * 24) . '小时';
                        $sickTime = date('Y-m-d H:i', strtotime($hospitalizeTime) - $res[1][0] * 24 * 60 * 60);
                        break;
                    case '周':
                        $sickSTime = ($res[1][0] * 24 * 7) . '小时';
                        $sickTime = date('Y-m-d H:i', strtotime($hospitalizeTime) - $res[1][0] * 7 * 24 * 60 * 60);
                        break;
                    case '月':
                        $sickSTime = ($res[1][0] * 24 * 30) . '小时';
                        $sickTime = date('Y-m-d H:i', strtotime($hospitalizeTime) - $res[1][0] * 30 * 24 * 60 * 60);
                        break;
                    case '年':
                        $sickSTime = ($res[1][0] * 24 * 30 * 12) . '小时';
                        $sickTime = date('Y-m-d H:i', strtotime($hospitalizeTime) - $res[1][0] * 12 * 30 * 24 * 60 * 60);
                        break;
                }

                if (is_array($value)) {
                    $str = implode('+', $value);
                    $symptom .= $str . $res[1][0] . (array_key_exists(1, $res[1]) ? '.' . $res[1][1] : '') . $res[2][0] . ' ';
                } else {
                    $symptom .= $value . $res[1][0] . (array_key_exists(1, $res[1]) ? '.' . $res[1][1] : '') . $res[2][0] . ' ';
                }
            }
        }

        return [$sickTime, $symptom, $sickSTime];
    }

    /**
     * 匹配症状
     * @param $arr
     * @param $str
     * @return array
     */
    public function matchZz($arr,$str): array
    {
        $res = [];
        foreach ($arr as $val) {
            if (is_array($val)) {
                //处理 手+麻木的状态
                $temporaryStatus = [];
                foreach ($val as $v) {
                    if (strpos($str, $v) !== false) {
                        $temporaryStatus[] = $v;
                    }
                }
                if ($temporaryStatus == $val) {
                    $res[] = $val;
                }
            } else {
                if (strpos($str, $val) !== false) {
                    $res[] = $val;
                }
            }
        }
        return $res;
    }

}
