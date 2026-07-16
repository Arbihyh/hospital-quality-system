<?php

if (!function_exists('checkPageStart')) {
    /**
     * 检查分页起始位置
     * @param int $page
     * @param int $pageSize
     * @return int
     */
    function checkPageStart($page=1, $pageSize=10)
    {
        $pageStart = ($page - 1) * $pageSize;
        if ($pageStart >= 10000) {
            if ($pageStart >= 10000) {
                $page = floor(10000 / $pageSize);
            }
            $page = ($page - 1);
        }

        return $page;
    }
}

if (!function_exists('getReflectionParamValue')) {
    function getReflectionParamValue($reflect, $avgs = [])
    {
        if ($reflect->getNumberOfParameters() > 0) {
            foreach ($reflect->getParameters() as $param) {
                $param_type = $param->getClass(); //获取当前注入对象的类型提示
                $param_value = $param->getName(); //获取参数名称
                if ($param_type) {
                    //表示是对象类型的参数
                    $avgs[] = new $param_type->name;
                } else {
                    $avgs[] = $param_value;
                }
            }
        }
        return $avgs;
    }
}
if (!function_exists('mbDateToTime')) {
    function mbDateToTime($time = '')
    {

        $dateArr = date_parse_from_format('Y年m月d日', $time);
        $res = mktime(0, 0, 0, $dateArr['month'], $dateArr['day'], $dateArr['year']);

        if (!$res) {
            $res  = strtotime($time);
        }

        return $res;
    }
}
if (!function_exists('phoneFormatCheck')) {
    function phoneFormatCheck($phone)
    {
        return preg_match('/^1[3456789][0-9]{9}$/', $phone);
    }
}

/**
 * 数据脱敏
 */
if (!function_exists('desensitize')) {
    function desensitize($string, $start = 0, $length = 0, $re = '*')
    {
        if (empty($string) || empty($re)) return $string;
        $strlen = mb_strlen($string);
        $end = $length ? $start + $length : $strlen;

        $str_arr = array();
        for ($i = 0; $i < $strlen; $i++) {
            if ($i >= $start && $i < $end) {
                $str_arr[] = $re;
            } else {
                $str_arr[] = mb_substr($string, $i, 1);
            }

        }
        return implode('', $str_arr);
    }
}


if (!function_exists('dataDesensitize')) {
    function dataDesensitize($data = [])
    {
        if(empty(request()->get('is_tm')) && empty(request()->post('is_tm'))){
            return $data;
        }
        /**
         * 姓名：patient_info 【AAA01】           杨**
        出生地：patient_address_info 【AAA10】【AAA11】【AAA12】山东***
        籍贯：patient_address_info 【AAA44】山东***
        证件号：patient_info 【AAA07】3706***
        电话：patient_address_info 【AAA51】138***
        现住址：patient_address_info 【AAA15】【AAA49】【AAA50】【AAA16c】山东***
        户口地址：patient_address_info 【AAA46】【AAA47】【AAA12】【AAA13c】【AAA33c】【AAA14c】山东***
        工作单位及地址：patient_work_info 【AAA19】烟台***
        单位电话：patient_work_info 【AAA20】138***
        联系人姓名：patient_contacts_info 【AAA22】杨**
        联系人地址：patient_contacts_info 【AAA24】***
        联系人电话：patient_contacts_info 【AAA25】138***
         */
        $desensitizeField = [
            'AAA01' => ['start' => 1, 'length' => 0],
            'AAA11' => ['start' => 0, 'length' => 0],
            'AAA12' => ['start' => 0, 'length' => 0],
            'AAA44' => ['start' => 2, 'length' => 0],
            'AAA07' => ['start' => 4, 'length' => 0],
            'AAA51' => ['start' => 3, 'length' => 0],
            'AAA15' => ['start' => 0, 'length' => 0],
            'AAA49' => ['start' => 0, 'length' => 0],
            'AAA50' => ['start' => 0, 'length' => 0],
            'AAA16c' => ['start' => 0, 'length' => 0],
            'AAA47' => ['start' => 0, 'length' => 0],
            'AAA33c' => ['start' => 0, 'length' => 0],
            'AAA13c' => ['start' => 0, 'length' => 0],
            'AAA14c' => ['start' => 0, 'length' => 0],
            'AAA19' => ['start' => 2, 'length' => 0],
            'AAA20' => ['start' => 3, 'length' => 0],
            'AAA22' => ['start' => 1, 'length' => 0],
            'AAA24' => ['start' => 0, 'length' => 0],
            'AAA25' => ['start' => 3, 'length' => 0],
        ];

        foreach ($data as $key => &$d) {
            $item = $desensitizeField[$key] ?? 0;
            if ($item) {
                $d = desensitize($d, $item['start'], $item['length'], '*');
            }
        }

        return $data;
    }
}













