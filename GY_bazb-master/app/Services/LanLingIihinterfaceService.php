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
class LanLingIihinterfaceService extends CommonInterfaceService
{
    //http://192.168.60.111:7800/iih.ei.std.i.IIHService?wsdl
    protected $interfaceList = [
        'get_mr_details' => 'iih.ei.std.i.IIHService?wsdl'
    ];

    protected $host = 'http://192.168.60.111:7800/';

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
        $this->user_code = '00000';
        $this->dep_code = '501201';
        $this->access_token = 'ef7b86f9-aef7-4c03-a3e4-b1333d9f4916';
        $this->caller_code = 'C00037';
        $this->secret_key = '5F384D4AB3CD18C984BCFB1F45D23684';
        $this->external_code = 'zk';
    }


    /**
     * @return IihinterfaceService
     */
    public static function getModel()
    {
        if(!self::$model instanceof CommonInterfaceService){
            self::$model = new self();
        }
        return self::$model;
    }

    /**
     * @param array $params
     */
    public function setXmlParams(array $params)
    {
        $data_xml = '';

        foreach($params as $key => $value){
            $data_xml .= "<{$key}>$value</{$key}>";
        }

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

        $xml = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:i="http://i.std.ei.iih/">
    <soapenv:Header />
    <soapenv:Body>
        <i:process>
            <code>$this->arg0</code>
            <xml>
                <![CDATA[<iihparam>
                    <Code_user>$this->user_code</Code_user>
                    <Code_dep>$this->dep_code</Code_dep>
                    <Code_external>$this->external_code</Code_external>
                    <Data>$data_xml</Data>
                </iihparam>]]>
            </xml>
        </i:process>
    </soapenv:Body>
</soapenv:Envelope>
XML;

        return $xml;
    }
}
