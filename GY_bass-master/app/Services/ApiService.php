<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 外部api请求
 */
class ApiService
{

    const BLBASEURL = "http://121.36.94.218:10090";
    const JMJKBASEURL = 'https://jm.jiankangche.cn';

    public static function reqGet(string $url = "")
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $output = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Errno' . curl_error($ch);//捕抓异常
            Log::error('api-request-error', ['msg' => curl_error($ch)]);
        }
        //释放curl句柄
        curl_close($ch);
        return json_decode($output, true);
    }


    /**
     * @param string $url
     * @param array $data
     * @param array $header
     * @return mixed
     * 模拟post请求
     */
    public static function reqPost(string $url = '', $data, array $header = [])
    {

        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT'] ?? ''); // 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        if ($header) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        }

        $tmpInfo = curl_exec($curl); // 执行操作
        if (curl_errno($curl)) {
            echo 'Errno' . curl_error($curl);//捕抓异常
            Log::error('api-request-error', ['msg' => curl_error($curl)]);
        }
        curl_close($curl); // 关闭CURL会话

        $result = json_decode($tmpInfo);
        return $result;
    }

    /**
     * @param string $content
     * @return bool
     * 根据内容获取分析内容
     */
    public static function getDiseaseRes($content = '', $debug = 0)
    {
        $data = [
            'sentence' => $content
        ];
        $header = [
            'Content-Type:application/json;charset=UTF-8'
        ];
        $response = self::reqPost(self::BLBASEURL . '/disease/ner/predict', json_encode($data, 256), $header);
        if($debug){
            var_dump($content);
            var_dump($response);exit;
        }
        if (!$response || $response->code != 200) {
            return false;
        } else {
            if (empty($response->data)) {
                return false;
            }
            return $response->data;
        }
    }

    /**
     * @param string $symptoms
     * @return bool
     * 根据cdss接口用户诊断内容提交
     */
    public static function cdssAdvisorySubmit($symptoms = '')
    {

        $data = [
            'symptom' => $symptoms,
        ];
        $header = [
            'Content-Type:application/x-www-form-urlencoded'
        ];
        $response = self::reqPost(self::JMJKBASEURL . '/jmjk/match-clinicalFeature', http_build_query($data), $header);

        if (!$response || $response->code != 0) {
            return false;
        } else {
            if (empty($response->data)) {
                return false;
            }
            return json_decode(json_encode($response->data), true);
        }
    }
}
