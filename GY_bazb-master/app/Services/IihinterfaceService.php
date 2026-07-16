<?php


namespace App\Services;

use App\Model\BA_RECEIVE;
use App\Model\PatientInfoTargetTemporary;
use App\Model\Yzb;
use Carbon\Carbon;

/**
 * 北大一信接口
 * HIS系统
 */
class IihinterfaceService extends CommonInterfaceService
{
    //http://{ip}:{prot}/iih.custom.std.ws.i.CtmWebService?wsdl&access_token={access_token}
    protected $interfaceList = [
        'get_mr_details' => 'iih.custom.std.ws.i.CtmWebService?wsdl'
    ];

    protected $host = 'http://10.32.92.92:80/';

    private static $model;

    private $arg0 = 'SI0195';

    private $user_code; //用户编码

    private $dep_code; //科室编码

    private $access_token; //token

    private $caller_code; //调用者编码

    private $secret_key;  //秘钥

    private $external_code; //外部编码


    /**
     * 初始化必要参数
     */
    public function __construct(){
        $this->user_code = 'NHZK';
        $this->dep_code = 'Z01';
        $this->access_token = 'ef7b86f9-aef7-4c03-a3e4-b1333d9f4916';
        $this->caller_code = 'C00037';
        $this->secret_key = '5F384D4AB3CD18C984BCFB1F45D23684';
        $this->external_code = '内涵质控';
    }


    /**
     * @return IihinterfaceService
     */
    public static function getModel()
    {
        if(!self::$model instanceof IihinterfaceService){
            self::$model = new IihinterfaceService();
        }
        return self::$model;
    }

    /**
     * @param array $params
     */
    public function setXmlParams(array $params)
    {
        $data_xml = '<Data>';

        foreach($params as $key => $value){
            $data_xml .= "<{$key}>$value</{$key}>";
        }

        $data_xml .= '</Data>';

        $binaryKey = hex2bin($this->secret_key);

        $data_xml = openssl_encrypt($data_xml,"sm4-ecb",$binaryKey, 0);

        $this->params = $this->getXmlData($data_xml);

        return $this;
    }

    /**
     * @param $uri
     * @param false $is_flag
     * @return $this
     */
    public function setUri($uri,$is_flag = false){
        $this->uri = data_get($this->interfaceList,$uri,'');
        $this->uri .= $is_flag ? '&access_token=' . $this->access_token : '';
        return $this;
    }


    /**
     * 获取Data整体内容
     * @param $data_xml
     * @return string
     */
    private function getXmlData($data_xml){
        $request_no = rand(11111,99999) . time() . rand(111,999);
        $request_time = Carbon::now()->format("Y-m-d H:i:s");

        $xml = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:i="http://i.ws.std.custom.iih/">
    <soapenv:Header />
    <soapenv:Body>
        <i:process>
            <i:arg0>$this->arg0</i:arg0>
            <i:arg1><![CDATA[<iihparam>
                <Code_user>$this->user_code</Code_user>
                <Code_dep>$this->dep_code</Code_dep>
                <Code_external>$this->external_code</Code_external>
                <Id_request>$request_no</Id_request>
                <Type_sign>SM4</Type_sign>
                <Time_bs>$request_time</Time_bs>
                <Info_ca></Info_ca>
                <Code_version>V1.0</Code_version>
                <Code_caller>$this->caller_code</Code_caller>
                <Data>$data_xml</Data>
                </iihparam>]]></i:arg1>
        </i:process>
    </soapenv:Body>
</soapenv:Envelope>
XML;


        return $xml;
    }
}
