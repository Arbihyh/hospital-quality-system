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
                preg_match_all("/加重.*?(\d{1,3}\.??\d??)[余]??(分钟|小时|天|周)/u", $zhusu, $res);
            } else {
                if (is_array($value)) {
                    preg_match_all("/$value[0].*?(\d{1,3}\.??\d??)[余]??(分钟|小时|天|周)/u", $zhusu, $res);
                } else {
                    preg_match_all("/$value.*?(\d{1,3}\.??\d??)[余]??(分钟|小时|天|周)/u", $zhusu, $res);
                }
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
                    case '天':
                        $sickSTime = ($res[1][0] * 24) . '小时';
                        $sickTime = date('Y-m-d H:i', strtotime($hospitalizeTime) - $res[1][0] * 24 * 60 * 60);
                        break;
                    case '周':
                        $sickSTime = ($res[1][0] * 24 * 7) . '小时';
                        $sickTime = date('Y-m-d H:i', strtotime($hospitalizeTime) - $res[1][0] * 7 * 24 * 60 * 60);
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
