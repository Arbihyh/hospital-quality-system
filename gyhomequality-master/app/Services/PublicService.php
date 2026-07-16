<?php

namespace App\Services;

class PublicService
{
    /**
     * 二维数组排序
     * @param $arr
     * @param $key
     * @param $sort
     * @return mixed
     */
    public static function sortByKey($arr, $key, $sort=1)
    {
        if ($sort == 1) {
            array_multisort(array_column($arr, $key), SORT_DESC, $arr);
        } else {
            array_multisort(array_column($arr, $key), SORT_ASC, $arr);
        }

        return $arr;
    }

    /**
     * 正则
     * @param $str
     * @param $type
     * @return bool
     */
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

    /**
     * 获取一维数组中重复数据
     * @param $data
     * @return array
     */
    public static function getRepeatData($data)
    {
        $data = array_count_values($data);
        $arr = [];
        foreach ($data as $value => $count) {
            if ($count > 1) {
                $arr[] = $value;
            }
        }
        return $arr;
    }



}

