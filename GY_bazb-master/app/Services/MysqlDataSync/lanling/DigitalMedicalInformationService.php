<?php

namespace App\Services\MysqlDataSync\lanling;

use App\Model\ZY_SS;
use App\Services\CommonInterfaceService;
use App\Services\EsSaveService;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DigitalMedicalInformationService extends CommonInterfaceService
{
    /**
     * 兰陵数字医信同步服务
     */
    //http://ip:port/sdk/getMsg/{sysId}/{serviceId}

    protected $host = 'http://192.168.60.180:8700/sdk/getMsg/';

    private $sys_id = 'S051';

    static private $instance;

    public static function getInstance()
    {
        if(!self::$instance instanceof self) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 输血记录  360
     */
    public function getTransfuseRecords(){
        $this->uri = sprintf('%s/BS360',$this->sys_id);
        $res = $this->postJson();
        if(!$res) return false;
        collect($res)->map(function($item){
            ZY_SS::query()->updateOrInsert([
                'SXXH' => data_get($item,'body.bloodRequestLid')
            ],[
                'ZYH' => data_get($item,'body.visitOrdNo'),
                'XX' => 'bloodRecords.0.bloodType',
                'SZL' => 'bloodRecords.0.transfusionQuantity',
                'CFX' => 'examResults.0.itemName',
                'KSSJ' => '',
                'JSSJ' => '',
                'SFZF' => '',
            ]);
        });
        return true;
    }
}
