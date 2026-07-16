<?php


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
            $res = strtotime($time);
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


if (!function_exists('postQuery')) {
    function postQuery($url = '', $data = '')
    {
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 1); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        $tmpInfo = curl_exec($curl); // 执行操作
        if (curl_errno($curl)) {
            echo 'Errno' . curl_error($curl);//捕抓异常

        }
        curl_close($curl); // 关闭CURL会话

        $result = json_decode($tmpInfo);
        return $result;
    }
}












