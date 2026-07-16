<?php

namespace App\Elastic;


use App\Model\PatientInfo;
use App\Services\ElasticsearchService;
use App\Model\ZY_HCMX;

class Zyhcmx
{

    public $zyHcmxesService;
    public function __construct()
    {

    }

    public function getHCRQ($zyh = '')
    {
        ////
//        $zyHcmx = ZY_HCMX::query()
//            ->where('ZYH', '=', $zyh)
//            ->where('HCLX', '=', 0)
//            ->orderBy('HCRQ', 'asc')
//            ->get()->toArray();
        $patientInfo = PatientInfo::query()->select(['AAB01 AS HCRQ'])->where(['MED_REC_ID'=>$zyh])->get()->toArray();

        return [$patientInfo];
    }

}

