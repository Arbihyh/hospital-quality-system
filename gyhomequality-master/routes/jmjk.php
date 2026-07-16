<?php

use Illuminate\Http\Request;
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
Route::namespace('Jmjk')->group(function (){
    Route::post('submit','OcrController@submit');
    Route::post('ocr_test','OcrController@ocr_test');
    Route::post('paddle','OcrController@paddle');
});

