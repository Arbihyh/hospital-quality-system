<?php

namespace App\Services;

use App\Model\DiseaseDiagnosisCode;
use App\Model\CaseQuality;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Model\Jmgs;
use App\Model\YmJmgs;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;

/**
 * 病例分析
 */
class JmgsService
{
    const ID = 'ZZ18953';

    public static function getJmgsDetail($id)
    {


        $data1 = Jmgs::query()->whereRaw("ZYH='$id'")->get()->toArray();
		$data2 = YmJmgs::query()->whereRaw("ZYH='$id'")->get()->toArray();
        
        return array('data1'=>$data1, 'data2'=>$data2);
    }
}

