<?php

use Illuminate\Support\Facades\Log;

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
if (!function_exists('removeHtmlAndHiddenElements')) {
    /**
     * 移除HTML中的隐藏元素、style、script标签，并返回纯文本
     * @param string $str
     * @return string | DOMDocument
     */
    function removeHtmlAndHiddenElements($str='', $returnDom = false)
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);

        // 加载HTML
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $str);
        if ($returnDom == true) {
            return $dom;
        }
        
        $xpath = new DOMXPath($dom);

        // 查找所有包含display:none的元素
        $hiddenElements = $xpath->query('//*[contains(@style, "display:none") or contains(@style, "display: none")]');

        // 移除隐藏元素
        foreach ($hiddenElements as $element) {
            if ($element->parentNode) {
                $element->parentNode->removeChild($element);
            }
        }
        // 移除header标签
        $headers = $dom->getElementsByTagName('header');
        while ($headers->length > 0) {
            $headers->item(0)->parentNode->removeChild($headers->item(0));
        }
        // 移除style标签
        $styles = $dom->getElementsByTagName('style');
        while ($styles->length > 0) {
            $styles->item(0)->parentNode->removeChild($styles->item(0));
        }
        // 移除script标签
        $scripts = $dom->getElementsByTagName('script');
        while ($scripts->length > 0) {
            $scripts->item(0)->parentNode->removeChild($scripts->item(0));
        }


        // 获取纯文本

        $text = $dom->textContent;

        // 获取纯文本，把所有br标签替换为换行符（待替换方案）
        /* $text = $dom->saveHTML();
        $text = str_replace(['<br>', '<br/>', '<br />'], "\n", $text);
        $text = strip_tags($text); */

        // 使用正则去掉“第 1 页”、“第 2 页”等类似的页码文字
        $text = preg_replace('/第\s*\d+\s*页/u', '', $text);

        // 去除多余空白
        $text = trim(preg_replace('/\s+/', ' ', $text));

        return $text;
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
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        $tmpInfo = curl_exec($curl); // 执行操作
        if (curl_errno($curl)) {
            echo 'Errno' . curl_error($curl); //捕抓异常

        }
        curl_close($curl); // 关闭CURL会话

        $result = json_decode($tmpInfo);
        return $result;
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


if (!function_exists('platformTime')) {
    function platformTime($time = '', $fm = "Y-m-d H:i:s")
    {
        if (!$time || strtotime($time) === false || strtotime($time) < 0) {
            return date($fm, 0);
        }

        return date($fm, strtotime($time));
    }
}

if (!function_exists('sqlLog')) {
    function sqlLog($query)
    {
        Log::info("sql: " . $query->sql, $query->bindings);
    }
}



if (!function_exists('webSendMsg')) {
    function webSendMsg($data, $to_uid)
    {
        return false;
        // 指明给谁推送，为空表示向所有在线用户推送
        // 推送的url地址，使用自己的服务器地址
        $push_api_url = env("PUSH_API_URL", "182.44.10.206:2121");

        $post_data = array(
            "type" => "publish",
            "content" => json_encode($data, JSON_UNESCAPED_UNICODE),
            "to" => $to_uid,
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $push_api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Expect:"));
        $return = curl_exec($ch);
        curl_close($ch);
        //var_export($return);
    }
}


if (!function_exists('splitMedicalRecordNumber')) {
    function formatYJJHYS($input = "")
    {
        if (empty($input)) {
            return [];
        }
        // 步骤1: 提取开头的数字和连字符
        $input1 = substr($input, 0, 9);
        preg_match('/^[\d-]+/', $input1, $matches);
        $extracted = $matches[0] ?? '';

        preg_match('/\d{4}-\d{2}-\d{2}/', $input, $matches);
        $date = !empty($matches) ? $matches[0] : "";
        preg_match('/日期(\d{2})/', $input, $matches);
        $day = !empty($matches) ? $matches[1] : "";

        // 统计连字符数量
        $dashCount = substr_count($extracted, '-');

        // 根据连字符数量执行不同的分割逻辑
        if ($dashCount === 0) {
            // 规则2: 无前2个数字，第三个数字，剩余数字
            return [
                substr($extracted, 0, 2),
                substr($extracted, 2, 1),
                substr($extracted, 3),
                $date,
                $day
            ];
        } elseif ($dashCount === 1) {
            preg_match('/^(\d{2})(\d{1})-(\d{2})/', $input, $matches);
            return [
                $matches[1],
                $matches[2],
                $matches[3],
                $date,
                $day
            ];
        } elseif ($dashCount === 2) {
            preg_match('/^(\d{2})(\d{1}-\d{1})(\d{2}-\d{2})/', $input, $matches);
            return [
                $matches[1],
                $matches[2],
                $matches[3],
                $date,
                $day
            ];
        }
        return [];
    }
}

// 正则匹配体格检查中的指标
if (!function_exists('regexTgjc')) {
    /**
     * 格式化体格检查中的指标
     *
     * @param string $text 体格检查中的指标
     * @param int $type 1: 体温, 2: 脉搏, 3: 呼吸, 4: 血压
     * T36.4℃P82次/分R19次/分BP145/85mmHg发育正常
     * /T(\d+\.\d+)℃.*?P(\d+)\/.*?R(\d+)\/.*?BP(\d+\/\d+)mmHg/
     * @return array
     */
    function regexTgjc($text, $type = 1)
    {
        if (empty($text)) {
            return [];
        }
        //如果包含“左”或“右”，则返回空
        if (strpos($text, '左') !== false || strpos($text, '右') !== false) {
            return [];
        }
        if (strpos($text, '3.体格检查') !== false && strpos($text, '4.辅助检查') !== false) {
            //取3.体格检查到4.辅助检查之间的文本
            if (preg_match('/3\.体格检查(.*?)4\.辅助检查/s', $text, $matches)) {
                $text = $matches[1];
            }
        }

        $text = preg_replace('/[\x{4e00}-\x{9fa5}]/u', '', $text);
        $text = str_replace([' ', '、', '。', '（', '）', ':', '：'], '', $text);
        if ($type == 1) {
            // 匹配体温：先尝试英文格式，再尝试中文格式
            $pattern = '/T[　\s]*(\d+\.\d+)℃.*?P/';
            if (!preg_match($pattern, $text)) {
                $pattern = '/体温[　\s]*(\d+\.\d+)脉搏/';
            }
        } else if ($type == 2) {
            // 匹配脉搏：先尝试英文格式，再尝试中文格式
            //删除BP，避免提取错误
            $text = str_replace('BP', '', $text);
            $pattern = '/P[　\s]*(\d+)\/.*?R/';
            if (!preg_match($pattern, $text)) {
                $pattern = '/脉搏[　\s]*(\d+)\/.*?呼吸/';
            }
        } else if ($type == 3) {
            // 匹配呼吸：先尝试英文格式，再尝试中文格式
            $pattern = '/R[　\s]*(\d+)[\/／次]*.*?BP/';
            if (!preg_match($pattern, $text)) {
                $pattern = '/呼吸[　\s]*(\d+)[\/／次]*.*?血压/';
            }
        } else if ($type == 4) {
            // 匹配血压：先尝试英文格式，再尝试中文格式
            $pattern = '/BP[　\s]*(\d+\/\d+)mmHg/';
            if (!preg_match($pattern, $text)) {
                $pattern = '/血压[　\s]*(\d+\/\d+)身高/';
            }
        }
        preg_match($pattern, $text, $matches);
        return $matches;
    }
}

if (!function_exists('formatTime')) {
    /**
     * 格式化时间
     *
     * @param string $time 时间字符串
     * @return string 格式化后的时间字符串
     */
    function formatTime($nsssj)
    {
        if (empty($nsssj)) {
            return '';
        }

        // 尝试多种时间格式解析
        $nsssj_time = null;

        if (!empty($nsssj)) {
            // 处理中文格式：2026年02月04日22时
            if (preg_match('/(\d{4})年(\d{1,2})月(\d{1,2})日(\d{1,2})时/', $nsssj, $matches)) {
                $nsssj_time = sprintf('%04d-%02d-%02d %02d:00:00', $matches[1], $matches[2], $matches[3], $matches[4]);
            }
            // 处理标准格式：2026-10-22 11:23:11 或 2026/10/22 11:23:11
            elseif (preg_match('/(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})\s+(\d{1,2}):(\d{1,2}):(\d{1,2})/', $nsssj, $matches)) {
                $nsssj_time = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $matches[1], $matches[2], $matches[3], $matches[4], $matches[5], $matches[6]);
            }
            // 处理无秒格式：2026-10-22 11:23 或 2026/10/22 11:23
            elseif (preg_match('/(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})\s+(\d{1,2}):(\d{1,2})/', $nsssj, $matches)) {
                $nsssj_time = sprintf('%04d-%02d-%02d %02d:%02d:00', $matches[1], $matches[2], $matches[3], $matches[4], $matches[5]);
            }
            // 处理纯日期格式：2026-10-22 或 2026/10/22
            elseif (preg_match('/(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/', $nsssj, $matches)) {
                $nsssj_time = sprintf('%04d-%02d-%02d 00:00:00', $matches[1], $matches[2], $matches[3]);
            }
            // 尝试使用 strtotime 解析其他格式
            else {
                $timestamp = strtotime($nsssj);
                if ($timestamp !== false) {
                    $nsssj_time = date('Y-m-d H:i:s', $timestamp);
                }
            }
        }
        return $nsssj_time;
    }
}


if (!function_exists('getPregTime')) {
    /**
     * 获取时间正则表达式
     */
    function getPregTime()
    {
        $pregTime = '(\d{4}\/\d{2}\/\d{2}\s+\d{2}:\d{2}(?::\d{2})?|\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(?::\d{2})?|\d{4}-\d{2}-\d{2}\s+\d{2}时\d{2}分(?:\d{2}秒)?|\d{4}年\d{2}月\d{2}日\s+\d{2}时\d{2}分(?:\d{2}秒)?|\d{4}年\d{2}月\d{2}日\s+\d{2}:\d{2}|\d{4}年\d{2}月\d{2}日\s+\d{2}:\d{2}:\d{2})';
        return $pregTime;
    }
}

