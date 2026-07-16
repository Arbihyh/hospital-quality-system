<?php


namespace App\Services;

use Carbon\Carbon;

class PublicService
{
    /**
     * 获取两个字符串重复率
     * @param $str1
     * @param $str2
     * @return float|int
     */
    public function getSimilar($str1, $str2)
    {
        if ($str1 == '' || $str2 == '') {
            return 0;
        }
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        $len = strlen($this->getLCS($str1, $str2, $len1, $len2));
        return $len * 2 / ($len1 + $len2);
    }
    public function getLCS($str1, $str2, $len1 = 0, $len2 = 0)
    {
        $this->str1 = $str1;
        $this->str2 = $str2;
        if ($len1 == 0) $len1 = strlen($str1);
        if ($len2 == 0) $len2 = strlen($str2);
        $this->initC($len1, $len2);
        return $this->printLCS($this->c, $len1 - 1, $len2 - 1);
    }
    public function initC($len1, $len2)
    {
        for ($i = 0; $i < $len1; $i++) $this->c[$i][0] = 0;
        for ($j = 0; $j < $len2; $j++) $this->c[0][$j] = 0;
        for ($i = 1; $i < $len1; $i++) {
            for ($j = 1; $j < $len2; $j++) {
                if ($this->str1[$i] == $this->str2[$j]) {
                    $this->c[$i][$j] = $this->c[$i - 1][$j - 1] + 1;
                } else if ($this->c[$i - 1][$j] >= $this->c[$i][$j - 1]) {
                    $this->c[$i][$j] = $this->c[$i - 1][$j];
                } else {
                    $this->c[$i][$j] = $this->c[$i][$j - 1];
                }
            }
        }
    }
    public function printLCS($c, $i, $j)
    {
        if ($i == 0 || $j == 0) {
            if ($this->str1[$i] == $this->str2[$j]) return $this->str2[$j];
            else return "";
        }
        if ($this->str1[$i] == $this->str2[$j]) {
            return $this->printLCS($this->c, $i - 1, $j - 1).$this->str2[$j];
        } else if ($this->c[$i - 1][$j] >= $this->c[$i][$j - 1]) {
            return $this->printLCS($this->c, $i - 1, $j);
        } else {
            return $this->printLCS($this->c, $i, $j - 1);
        }
    }

    public static function pregMatchTszf($str, $type='all')
    {
        $res = false;
        switch ($type) {
            case 'all' :
                $res = preg_match('/[[:alpha:][:digit:][:alnum:][:space:][:upper:][:lower:][:punct:][:xdigit:]]/', $str);
                break;
            case 'alpha' :  // 任何字母
                $res = preg_match('/[[:alpha:]]/', $str);
                break;
            case 'digit' :  // 任何数字
                $res = preg_match('/[[:digit:]]/', $str);
                break;
            case 'alnum' :  // 任何字母和数字
                $res = preg_match('/[[:alnum:]]/', $str);
                break;
            case 'space' :  // 任何白字符
                $res = preg_match('/[[:space:]]/', $str);
                break;
            case 'upper' :  // 任何大写字母
                $res = preg_match('/[[:upper:]]/', $str);
                break;
            case 'lower' :  // 任何小写字母
                $res = preg_match('/[[:lower:]]/', $str);
                break;
            case 'punct' :  // 任何标点符号
                $res = preg_match('/[[:punct:]]/', $str);
                break;
            case 'xdigit' :  // 任何16进制的数字，相当于[0-9a-fA-F]
                $res = preg_match('/[[:xdigit:]]/', $str);
                break;
        }

        return !empty($res) ? true : false;
    }
}
