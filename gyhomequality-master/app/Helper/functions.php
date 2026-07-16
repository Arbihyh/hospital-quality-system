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

if (!function_exists('blobToStr')) {
    function blobToStr($blob = null)
    {
        $str = '';
        if (!empty($blob)) {
            $text = $blob->load();
            $blob->free();
            $mde = mb_detect_encoding($text, array("ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5'));
            if ($mde) {
                $str = mb_convert_encoding($text, 'utf-8', $mde);
            }
        }

        return $str;
    }
}

if (!function_exists('platformTime')) {
    function platformTime($time = '', $fm = "Y-m-d H:i:s")
    {
        if (!$time || strtotime($time) === false || strtotime($time) < 0) {
            return date($fm, 0);
        }

        return date($fm, strtotime($time));
    }
}


if (!function_exists('saveImg')) {
    /**
     * 保存最新图片上传插件上传的图片
     * @param string $img_content [description]
     * @return [type]              [description]
     */
    function saveImg($imgContent = '', $type = '')
    {
        if (empty($imgContent)) {
            return '';
        }
        if (!strpos($imgContent, "base64")) {
            return $imgContent;
        }

        $imgPathRoot = str_replace("\\", "/", dirname(dirname(dirname(__FILE__))));
        preg_match('/^(data:\s*image\/(\w+);base64,)/', $imgContent, $result);
        $type = $result[2] ?? null;

        $dir = '/data/img/' . date('Ym');
        if (!is_dir($imgPathRoot . $dir)) {
            @mkdir($imgPathRoot . $dir, 0777, true);
            @chmod($imgPathRoot . $dir, 0777);
        }
        $imgName = time() . rand(1, 999999) . ('.' . ($type ?: 'png'));
        $path = $dir . '/' . $imgName;
        $imgContent = str_replace('data:image/' . $type . ';base64,', '', $imgContent);
        $imgContent = str_replace('[removed]', '', $imgContent);
        $res = file_put_contents($imgPathRoot . $path, base64_decode($imgContent));
        if (!$res) {
            return false;
        }
        return env("APP_URL").'/img/'.date('Ym') . '/' . $imgName;
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


if (!function_exists('dataDesensitize')) {
    function dataDesensitize($data = [])
    {
        if (empty(request()->get('is_tm')) && empty(request()->post('is_tm'))) {
            return $data;
        }
        /**
         * 姓名：patient_info 【AAA01】           杨**
         * 出生地：patient_address_info 【AAA10】【AAA11】【AAA12】山东***
         * 籍贯：patient_address_info 【AAA44】山东***
         * 证件号：patient_info 【AAA07】3706***
         * 电话：patient_address_info 【AAA51】138***
         * 现住址：patient_address_info 【AAA15】【AAA49】【AAA50】【AAA16c】山东***
         * 户口地址：patient_address_info 【AAA46】【AAA47】【AAA12】【AAA13c】【AAA33c】【AAA14c】山东***
         * 工作单位及地址：patient_work_info 【AAA19】烟台***
         * 单位电话：patient_work_info 【AAA20】138***
         * 联系人姓名：patient_contacts_info 【AAA22】杨**
         * 联系人地址：patient_contacts_info 【AAA24】***
         * 联系人电话：patient_contacts_info 【AAA25】138***
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

if (!function_exists('remainderTime')) {
    function remainderTime($time = '')
    {

        $hour = floor($time / 3600);
        $minute = floor(($time - $hour * 3600) / 60);
        return $hour . '小时' . sprintf('%02s', $minute) . '分钟';
    }
}

if (!function_exists('validatePasswordIsEasy')){
    function validatePasswordIsEasy($password)
    {
        // 密码长度至少 8 个字符
        if (strlen($password) < 8) {
            return false;
        }
        // 必须包含大写字母、小写字母、数字
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        return true;
    }
}

if (!function_exists('requestPost')) {
    function requestPost($url = '', $data = '')
    {
        $data = json_encode($data);
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
        curl_setopt($curl, CURLOPT_TIMEOUT, 0); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
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

if(!function_exists('webSendMsg')){
    function webSendMsg($data, $to_uid)
    {
        // 指明给谁推送，为空表示向所有在线用户推送
        // 推送的url地址，使用自己的服务器地址
        $push_api_url = env("PUSH_API_URL", "182.44.10.206:2121");

        $post_data = array(
            "type" => "publish",
            "content" => json_encode($data,JSON_UNESCAPED_UNICODE),
            "to" => $to_uid,
        );
        try{

            $ch = curl_init ();
            curl_setopt ( $ch, CURLOPT_URL, $push_api_url );
            curl_setopt ( $ch, CURLOPT_POST, 1 );
            curl_setopt ( $ch, CURLOPT_HEADER, 0 );
            curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
            curl_setopt ( $ch, CURLOPT_POSTFIELDS, $post_data );
            curl_setopt ($ch, CURLOPT_HTTPHEADER, array("Expect:"));
            $return = curl_exec ( $ch );
            curl_close ( $ch );
        } catch (\Exception $e) {

        }
    }
}












