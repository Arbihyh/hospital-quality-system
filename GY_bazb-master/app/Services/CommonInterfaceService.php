<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 外部api请求
 */
class CommonInterfaceService
{

    protected $params; //参数

    protected $uri; //接口地址

    protected $interfaceList; //接口地址数组

    protected $host;

    protected $verify = false;

    protected $allow_redirects = false;

    protected $is_object = false;


    public function postCurl(){
        if(!$this->uri || !$this->host) return false;

        // 设置cURL选项
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->host . $this->uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $this->params,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml;charset=UTF-8',
                'Content-Length: ' . strlen($this->params),
                'SOAPAction: ""'  // 如果需要特定的SOAPAction，请替换
            ],
            CURLOPT_SSL_VERIFYPEER => false,  // 如果是https请求，可能需要这个选项
            CURLOPT_SSL_VERIFYHOST => false   // 如果是https请求，可能需要这个选项
        ]);
        try {
            // 发送请求并获取响应
            $response = curl_exec($ch);

            if ($response === false) {
                Log::error('IIH接口信息错误::' . curl_error($ch));
                return false;
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if($httpCode !== 200){
                Log::error('IIH接口HTTP_CODE错误::',[$httpCode]);
                return false;
            }

            $xml_reader = new \XMLReader();
            $xml_reader->xml($response,'UTF-8');

            while ($xml_reader->read()){
                if($xml_reader->name == '#text') $xml_value = $xml_reader->value;
            }

            $xml_json = json_encode(simplexml_load_string($xml_value,'SimpleXMLElement',LIBXML_NOCDATA));

            $xml_data = json_decode($xml_json,true);

        } catch (\Exception $e) {
            Log::error('IIH接口信息::',[$e->getMessage()]);
            return false;
        } finally {
            curl_close($ch);
        }
        return $xml_data ?: false;
    }

    public function postJson(){
        if(!$this->uri || !$this->host) return false;

        $response = (new Client([
            'base_uri' => $this->host,
            'verify' => $this->verify,
            'allow_redirects' => $this->allow_redirects
        ]))->request('POST', $this->uri, [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'json' => $this->params
        ]);

        $json_string = $response->getBody()->getContents();

        return $this->is_object ? @json_decode($json_string) : json_decode($json_string, true);
    }

}
