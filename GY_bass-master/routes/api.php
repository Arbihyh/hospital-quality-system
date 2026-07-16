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

Route::namespace('Api')->group(function () {
    Route::any('login', 'LoginController@login')->withoutMiddleware('CheckLogin');
    Route::any('test', 'TestController@test')->withoutMiddleware('CheckLogin');

    Route::prefix('user')->group(function () {
        Route::any('info', 'UserController@userInfo');
        Route::any("menus", "UserController@groupMenuTree");
    });

    Route::post('selectInfo', 'QualityController@selectInfo');
    Route::post('qualityList', 'QualityController@qualityList');
    Route::post('feeDetail', 'QualityController@feeDetail');
    Route::post('getHomeQualityList', 'QualityController@getHomeQualityList'); // 病案数量
    Route::post('ruleList', 'QualityController@ruleList');
    Route::post('errorList', 'QualityController@errorList');
    Route::post('errorData', 'QualityController@errorData');
    Route::post('errorDataList', 'QualityController@errorDataList');
    Route::post('homeErrorDataList', 'QualityController@homeErrorDataList');
    Route::post('errorCount', 'QualityController@errorCount');
    Route::post('workrecord', 'WorkController@record');//工作记录
    Route::post('workrepoet', 'WorkController@repoet');//工作报告
    Route::post('searchSelect', 'QualityController@searchSelect')->withoutMiddleware('CheckLogin');//全病例-病例搜索引擎
    Route::post('search', 'QualityController@searchData')->withoutMiddleware('CheckLogin');//全病例-病例搜索引擎
    Route::post('normalSearch', 'QualityController@normalSearch')->withoutMiddleware('CheckLogin');//全病例-病例普通搜索
    Route::get('normalSearchExport', 'QualityController@exportNormalSearch')->withoutMiddleware('CheckLogin');//全病例-病例普通搜索-导出
    Route::any('search_result_export', 'QualityController@exportSearchResult')->withoutMiddleware('CheckLogin');//全病例-病例高级搜索-导出
    Route::any('excel_error', 'ExcelController@excel_error');//缺陷问题-导出excel


    Route::post('homeCensus', 'RankingController@homeCensus');//统计
    Route::post('reportingHistory', 'ReportController@history');//上报历史

    Route::post('ranking_hospital', 'RankingController@hospital');//住院医师排名
    Route::post('ranking_department', 'RankingController@department');//科室排名
    Route::post('ranking_attending_group', 'RankingController@attendingGroup');//主诊组排名
    Route::post('ranking_indications', 'RankingController@indications');//主治医师排名
    Route::post('ranking_coder', 'RankingController@coder');//编码员排名

    Route::any('excel', 'ExcelController@export');//测试excel下载
    Route::post('medical_record', 'MedicalRecordController@home')->withoutMiddleware('CheckLogin');//住院病案首页
    Route::any('medicalRecordEdit', 'MedicalRecordController@editHome');//住院病案首页
    Route::any('wtExport', 'QualityController@wtExport');//卫统导出
    Route::any('gkExport', 'QualityController@gkExport');//卫统导出
    Route::any('getTree', 'QualityController@getTree')->withoutMiddleware('CheckLogin');
    Route::any('getAllCase', 'QualityController@getAllCase')->withoutMiddleware('CheckLogin');
    Route::any('get_assessment_indicators', 'RadioController@getList');// 绩效考核指标
    Route::any('get_zhibiao_list', 'RadioController@getZbList');// 查看指标对应的列表数据
    Route::any('get_illness_type', 'IllnessTypeController@getList');// 单病种质量
    Route::any('get_case', 'CaseController@getList')->withoutMiddleware('CheckLogin');// 病例质控
    Route::any('get_case_platform', 'CaseController@getCasePlatform')->withoutMiddleware('CheckLogin');// 病例数据格式化
    Route::any('get_pacs_dir', 'PacsController@getPacsPlatform')->withoutMiddleware('CheckLogin');// 目录
    Route::any('get_pacs_detail', 'PacsController@getList')->withoutMiddleware('CheckLogin');// 详细
    Route::any('get_jmgs_detail', 'JmgsController@getList')->withoutMiddleware('CheckLogin');// 详细
    Route::any('get_bc', 'QualityController@getBc')->withoutMiddleware('CheckLogin');// 病程明细
    Route::any('every', 'EveryController@everyDay')->withoutMiddleware('CheckLogin');// 自动取数据
    Route::any('long', 'DoctorAdviceController@long')->withoutMiddleware('CheckLogin');// 长期医嘱
    Route::any('temporary', 'DoctorAdviceController@temporary');// 临时医嘱
    Route::any('getDoctorAdvice', 'DoctorAdviceController@getDoctorAdvice')->withoutMiddleware('CheckLogin');// 医嘱查询
    Route::any('doctorAdviceSelect', 'DoctorAdviceController@doctorAdviceSelect')->withoutMiddleware('CheckLogin');// 医嘱查询配置
    Route::any('doctorAdviceExport', 'DoctorAdviceController@doctorAdviceExport')->withoutMiddleware('CheckLogin');// 医嘱查询配置
    Route::any('get_surgery_data', 'CaseController@getSurgeryData')->withoutMiddleware('CheckLogin');// 获取手术格式化数据
    Route::any('get_bc_data', 'CaseController@getBcData')->withoutMiddleware('CheckLogin');// 获取病程格式化数据
    Route::any('get_pacs_data', 'PacsController@getPacsData')->withoutMiddleware('CheckLogin');// 获取报告单格式化数据

    // 全病历质控 相关路由
    Route::prefix('case-quality')->group(function () {
        Route::post('analysis', 'CaseQualityController@analysis'); // 质量分析
        Route::post("ranking_department", "CaseQualityController@rankingDepartment"); // 科室排名
        Route::post("defect_issues", "CaseQualityController@defectIssues"); // 缺陷问题
    });

    // 调整医嘱+首页联合查询
    Route::prefix('yz')->group(function () {
        Route::post('serach_where', 'YzController@getYzSerachWhere')->withoutMiddleware('CheckLogin');    // 医嘱搜索条件
        Route::post('serach', 'YzController@getYzSerach')->withoutMiddleware('CheckLogin');               // 医嘱搜索
        Route::post('serachExport', 'YzController@yzSerachExport')->withoutMiddleware('CheckLogin');      // 医嘱导出
    });

    // 病历搜索
    Route::prefix('bl')->group(function () {
        Route::post('serach_where', 'BlSerachController@getSerachWhere')->withoutMiddleware('CheckLogin');// 病历搜索条件
        Route::post('serach', 'BlSerachController@blSerach')->withoutMiddleware('CheckLogin');            // 病历搜索
        Route::post('serachExport', 'BlSerachController@blSerachExport')->withoutMiddleware('CheckLogin');// 病历搜索导出
        Route::post('tiwenWhere', 'BlSerachController@getTiWenWhere')->withoutMiddleware('CheckLogin');
    });

});

