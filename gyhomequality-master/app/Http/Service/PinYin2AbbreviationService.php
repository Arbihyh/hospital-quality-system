<?php

namespace App\Http\Service;

use App\Model\AttendingGroupData;
use App\Model\CoderData;
use App\Model\DepartmentData;
use App\Model\Error;
use App\Model\HospitalData;
use App\Model\IndicationsData;
use App\Model\ReportingHistory;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ExportData;

/**
 * 导出服务层
 */
class PinYin2AbbreviationService
{
    public function getFirstStr($str)
    {
        if (empty(trim($str))) {
            return [];
        }

        // 字符串拆分成数组
        $arr = [];
        for ($i=0; $i<mb_strlen($str); $i++) {
            if (!empty(trim(mb_substr($str,$i,1)))) {
                $arr[] = mb_substr($str,$i,1);
            }
        }
        $szm = [];
        foreach ($arr as $value) {
            // 汉字取首字母
            $szm[] = $this->getFirstChar($value);
        }

        return $szm;
    }

    //获取中文的首字母
    public function getFirstChar($s)
    {
        $s0 = mb_substr($s, 0, 1, 'utf-8');//获取名字的姓
        $s = iconv('UTF-8', 'GBK', $s0);//将UTF-8转换成GB2312编码
        if (ord($s0) > 128) {//汉字开头，汉字没有以U、V开头的
            $asc = ord($s{0}) * 256 + ord($s{1}) - 65536;
            if ($asc >= -20319 and $asc <= -20284) return "A";
            if ($asc >= -20283 and $asc <= -19776) return "B";
            if ($asc >= -19775 and $asc <= -19219) return "C";
            if ($asc >= -19218 and $asc <= -18711) return "D";
            if ($asc >= -18710 and $asc <= -18527) return "E";
            if ($asc >= -18526 and $asc <= -18240) return "F";
            if ($asc >= -18239 and $asc <= -17760) return "G";
            if ($asc >= -17759 and $asc <= -17248) return "H";
            if ($asc >= -17247 and $asc <= -17418) return "I";
            if ($asc >= -17417 and $asc <= -16475) return "J";
            if ($asc >= -16474 and $asc <= -16213) return "K";
            if ($asc >= -16212 and $asc <= -15641) return "L";
            if ($asc >= -15640 and $asc <= -15166) return "M";
            if ($asc >= -15165 and $asc <= -14923) return "N";
            if ($asc >= -14922 and $asc <= -14915) return "O";
            if ($asc >= -14914 and $asc <= -14631) return "P";
            if ($asc >= -14630 and $asc <= -14150) return "Q";
            if ($asc >= -14149 and $asc <= -14091) return "R";
            if ($asc >= -14090 and $asc <= -13319) return "S";
            if ($asc >= -13318 and $asc <= -12839) return "T";
            if ($asc >= -12838 and $asc <= -12557) return "W";
            if ($asc >= -12556 and $asc <= -11848) return "X";
            if ($asc >= -11847 and $asc <= -11056) return "Y";
            if ($asc >= -11055 and $asc <= -10247) return "Z";
            return "ZBD";
        } elseif (ord($s) >= 48 and ord($s) <= 57) {//数字开头
            switch (iconv_substr($s, 0, 1, 'utf-8')) {
                case 1:
                    return "Y";
                case 2:
                    return "E";
                case 3:
                    return "S";
                case 4:
                    return "S";
                case 5:
                    return "W";
                case 6:
                    return "L";
                case 7:
                    return "Q";
                case 8:
                    return "B";
                case 9:
                    return "J";
                case 0:
                    return "L";
            }
        } else if (ord($s) >= 65 and ord($s) <= 90) {//大写英文开头
            return substr($s, 0, 1);
        } else if (ord($s) >= 97 and ord($s) <= 122) {//小写英文开头
            return strtoupper(substr($s, 0, 1));
        } else {
            return iconv_substr($s0, 0, 1, 'utf-8');//中英混合的词语提取首个字符即可
        }
    }
}
