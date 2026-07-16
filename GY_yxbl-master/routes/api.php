<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::namespace('Api')->group(function (){

    Route::any('analysis','CaseController@analysis');

    // 病案首页质控（事中 对外）
    Route::any("homeQuality", "ErrorV2Controller@homeQuality");
    // 病案首页编码员质控
    Route::any("homeBmyQuality", "ErrorV2Controller@homeBmyQuality");

    // 测试
    Route::any("bmyQualityTest", "ErrorV2Controller@bmyQualityTest");

    //zz的测试
    Route::post("zzTest", "BmyController@reCheck");

});

