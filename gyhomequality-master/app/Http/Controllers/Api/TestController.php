<?php


namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Pheanstalk\Pheanstalk;

class TestController
{
    public function test(Request $request)
    {
        $MED_REC_ID = $request->post('MED_REC_ID');
        $beanstalkd = Pheanstalk::create('beanstalkd')->useTube('getOne');
        $beanstalkd->put(json_encode(['AAA28' => $MED_REC_ID]));
    }

    public function kafka()
    {
        $server = new \App\Services\ProduceService();
        $server->produce();
    }
}
