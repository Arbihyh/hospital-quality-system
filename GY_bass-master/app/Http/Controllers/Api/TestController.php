<?php


namespace App\Http\Controllers\Api;

use App\Model\ErrorRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TestController
{
    public function test(Request $request)
    {
        $list = ErrorRule::query()
            ->pluck('id');
        if ($list){
            $list = $list->toArray();
        }else{
            $list = [];
        }
        foreach ($list as $item){
            $key = '2022_05_'.$item;
            Cache::forget($key);
        }
    }
}
