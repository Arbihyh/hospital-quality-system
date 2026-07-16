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
    
    Route::prefix('tk')->group(function (){
        Route::get("get_menu", "Tk@getMenu")->withoutMiddleware('CheckLogin'); // 弹框菜单
        Route::get("get_dep_case_quality", "Tk@getDepCaseQuality")->withoutMiddleware('CheckLogin'); // 科室质控记录列表
        Route::get("get_msg_count", "Tk@getMsgCount")->withoutMiddleware('CheckLogin'); // 消息数量
        Route::get("get_msg", "Tk@getMsg")->withoutMiddleware('CheckLogin'); // 消息列表
        Route::post("read_msg", "Tk@readMsg")->withoutMiddleware('CheckLogin'); // 已读消息
        Route::post("read_all_msg", "Tk@readAllMsg")->withoutMiddleware('CheckLogin'); // 所有已读
        Route::post("is_Know", "Tk@isKnow")->withoutMiddleware('CheckLogin'); // 是否已知
    });



    Route::prefix('big_model')->group(function (){
        Route::get("get_task_name", "CaseController@getTaskName")->withoutMiddleware('CheckLogin');
        Route::any("staff_login", "BigModelMedicalRecordController@staffLogin")->withoutMiddleware('CheckLogin');
        Route::any("create_custom_template", "BigModelMedicalRecordController@createCustomTemplate")->withoutMiddleware('CheckLogin');
        Route::any("custom_template_list", "BigModelMedicalRecordController@getCustomTemplateList")->withoutMiddleware('CheckLogin');
        Route::any("edit_custom_template", "BigModelMedicalRecordController@editCustomTemplate")->withoutMiddleware('CheckLogin');
        Route::any("get_custom_template_departments", "BigModelMedicalRecordController@getCustomTemplateDepartments")->withoutMiddleware('CheckLogin');
        Route::any("get_custom_template_diseases", "BigModelMedicalRecordController@getCustomTemplateDiseases")->withoutMiddleware('CheckLogin');
        Route::any("medical_record_list", "BigModelMedicalRecordController@getMedicalRecordList")->withoutMiddleware('CheckLogin');
        Route::any("save_medical_record", "BigModelMedicalRecordController@saveMedicalRecord")->withoutMiddleware('CheckLogin');
        Route::any("build_medical_record_data", "BigModelMedicalRecordController@buildTemplateData")->withoutMiddleware('CheckLogin');
        Route::any("generate_medical_record", "BigModelMedicalRecordController@generateMedicalRecord")->withoutMiddleware('CheckLogin');
        Route::any("audio_transcription", "BigModelMedicalRecordController@audioTranscription")->withoutMiddleware('CheckLogin');
    });
    Route::any('quality_has_result', 'CaseController@getQualityHasResult')->withoutMiddleware('CheckLogin'); // 检查指定科室指定日期是否有需要弹框的结果
    Route::any('get_case_quality_count', 'CaseController@getCaseQualityCount')->withoutMiddleware('CheckLogin'); // 获取质控结果列表
    Route::any('quality_has_new_result', 'CaseController@getQualityHasNewResult')->withoutMiddleware('CheckLogin'); // 检查是否有新的质控结果
    Route::any('get_ai_info', 'CaseController@getAiInfo')->withoutMiddleware('CheckLogin');
    Route::any('appeal', 'CaseController@appeal')->withoutMiddleware('CheckLogin');

    Route::any('edit_hjnr', 'CaseController@editHJNR');// 修改病例内容
    Route::any('get_assessment_indicators', 'RadioController@getList');// 绩效考核指标
    Route::any('get_assessment_indicators_v2', 'RadioController@getListV2');// 绩效考核指标
    Route::any('get_zhibiao_list', 'RadioController@getZbList');// 查看指标对应的列表数据
    Route::any('get_zhibiao_list_v2', 'RadioController@getZbListV2');// 查看指标对应的列表数据d
    Route::any('get_illness_type', 'IllnessTypeController@getList');// 单病种质量
    Route::any('get_case', 'CaseController@getList');// 病例质控
    Route::any('get_case_platform', 'CaseController@getCasePlatform');// 病例数据格式化
    Route::any('get_case_quality', 'CaseController@getCaseQuality')->withoutMiddleware('CheckLogin');// 终末质控
    Route::any('get_case_quality_v2', 'CaseController@getCaseQualityV2')->withoutMiddleware('CheckLogin');// 事中质控

    Route::any('add_zb', 'RadioController@addZb');// 手动录入指标
    Route::any('dep_statistics', 'RadioController@depStatistics'); // 科室排名统计
    Route::any('dep_statistics_export', 'RadioController@depStatisticsExport'); // 科室排名统计导出
    Route::any('dep_bl_list', 'RadioController@depBlList'); // 科室排名统计详细列表
    Route::any('dep_bl_export', 'RadioController@depBlExport'); // 科室排名统计详细列表导出

    Route::any('dep_statistics_new', 'RadioController@depStatisticsNew'); // 科室排名统计
    Route::any('dep_statistics_export_new', 'RadioController@depStatisticsExportNew'); // 科室排名统计导出
    Route::any('dep_bl_list_new', 'RadioController@depBlListNew'); // 科室排名统计详细列表
    Route::any('dep_bl_export_new', 'RadioController@depBlExportNew'); // 科室排名统计详细列表导出

    Route::any('up_zb_fbsj', 'RadioController@updateZlFbsj'); // 修改脑梗心梗发病时间
    Route::any('errorDataList', 'QualityController@errorDataList')->withoutMiddleware('CheckLogin');
    Route::any('quality_tab_menu', 'QualityController@getQualityTabMenu')->withoutMiddleware('CheckLogin'); // 获取质控栏tab菜单
    Route::any('quality_handle', 'QualityController@qualityHandle')->withoutMiddleware('CheckLogin'); // 单病例质控
    Route::any('quality_handle_v2', 'QualityController@qualityHandleV2')->withoutMiddleware('CheckLogin'); // 单病例质控
    Route::any('quality_handle_mz', 'QualityHandleMzController@qualityHandleMz')->withoutMiddleware('CheckLogin'); // 门诊病历质控
    Route::any('mz_feedback', 'QualityHandleMzController@mzFeedback')->withoutMiddleware('CheckLogin'); // 门诊反馈
    Route::any('mz_feedback_list', 'QualityHandleMzController@mzFeedbackList')->withoutMiddleware('CheckLogin'); // 门诊反馈列表
    Route::any('warning_msg', 'CaseController@warningMsg')->withoutMiddleware('CheckLogin');

    // 全病历质控 相关路由
    Route::prefix('case-quality')->group(function () {
        Route::any('analysis', 'CaseQualityController@analysis')->withoutMiddleware('CheckLogin'); // 质量分析
        Route::any("ranking_department", "CaseQualityController@rankingDepartment")->withoutMiddleware('CheckLogin'); // 科室排名
        Route::any("defect_issues", "CaseQualityController@defectIssues")->withoutMiddleware('CheckLogin'); // 缺陷问题
        Route::any("medical_record_level", "CaseQualityController@medicalRecordLevel")->withoutMiddleware('CheckLogin'); // 病例等级
        Route::any("medical_record_doctor", "CaseQualityController@doctorRanking")->withoutMiddleware('CheckLogin'); // 医师排名统计
        Route::any("doctor_ranking_list", "CaseQualityController@doctorRankingList")->withoutMiddleware('CheckLogin'); // 医师排名统计
        Route::any("doctor_list", "CaseQualityController@dockerList")->withoutMiddleware('CheckLogin'); // 医师列表
        Route::any("document_type_list", "CaseQualityController@getDocumentTypeList")->withoutMiddleware('CheckLogin'); // 文书类型列表
        Route::any("rule_nature_list", "CaseQualityController@getRuleNatureList")->withoutMiddleware('CheckLogin'); // 规则性质列表
        Route::any("correction_status_list", "CaseQualityController@getCorrectionStatusList")->withoutMiddleware('CheckLogin'); // 整改状态列表
        Route::any("shizhong_quality_records", "CaseQualityController@shizhongQualityRecords")->withoutMiddleware('CheckLogin'); // 事中质控记录列表
        Route::any("shizhong_quality_rule_statistics", "CaseQualityController@shizhongQualityRuleStatistics")->withoutMiddleware('CheckLogin'); // 事中质控按规则统计
        Route::any("shizhong_quality_department_statistics", "CaseQualityController@shizhongQualityDepartmentStatistics")->withoutMiddleware('CheckLogin'); // 事中质控按科室汇总
        Route::any("shizhong_quality_rule_unlock", "CaseQualityController@shizhongQualityRuleUnlock")->withoutMiddleware('CheckLogin'); // 事中质控规则解锁申请
        Route::any("shizhong_quality_unlock_records", "CaseQualityController@shizhongQualityUnlockRecords")->withoutMiddleware('CheckLogin'); // 事中质控解锁记录列表
        Route::any("shizhong_quality_unlock_audit", "CaseQualityController@shizhongQualityUnlockAudit")->withoutMiddleware('CheckLogin'); // 事中质控解锁审核
    });


    Route::post('catalog_add', 'IndexCatalogController@add');
    Route::get('catalog_lists', 'IndexCatalogController@lists');
    Route::get('catalog_info', 'IndexCatalogController@info');
    Route::post('catalog_edit', 'IndexCatalogController@edit');
    Route::post('catalog_del', 'IndexCatalogController@del');
    Route::get('catalog_options', 'IndexCatalogController@getOptions');
    Route::post('catalog_update_custom_data', 'IndexCatalogController@updateCustomData');
    Route::get('catalog_get_custom_data', 'IndexCatalogController@getCustomData');

    Route::post('quality_index_info', 'QualityIndexController@getInfo');
    Route::get('get_kesi', 'QualityIndexController@getKesi');
    Route::get('get_staff', 'QualityIndexController@getStaff');
    Route::get('get_allindex_list', 'QualityIndexController@getAllIndex');
    Route::get('quality_index_list', 'QualityIndexController@getIndex'); //指标列表接口
    Route::get('quality_index_analysis', 'QualityIndexController@getIndexAnalysis'); //指标分析接口
    Route::any('quality_index_core_report', 'QualityIndexController@getCoreInstitutionReport'); // 核心制度指标报表接口
    Route::any('quality_index_core_top_departments', 'QualityIndexController@getCoreInstitutionTopDepartments'); // 核心制度指标前五科室
    Route::any('quality_index_core_bottom_departments', 'QualityIndexController@getCoreInstitutionBottomDepartments'); // 核心制度指标后五科室
    Route::get('quality_index_department_ranking', 'QualityIndexController@getIndexDepartmentRanking'); //指标科室排名接口
    Route::post('quality_index_detail_list', 'QualityIndexController@getDetailIndex'); //指标列表分子，分母下钻列表接口
    Route::post('quality_index_recalculate', 'QualityIndexController@reCalculateIndex'); //指标重新计算接口


    // 医疗质量安全核心制度和省平台对接的接口
    Route::get("sptUploadData", 'IndicatorController@sptUploadData')->withoutMiddleware('CheckLogin');
    Route::get("sptUploadHistoryList", 'IndicatorController@sptUploadHistoryList')->withoutMiddleware('CheckLogin');

    //获取推送消息列表
    Route::any("getMsgList", 'QualitySendMsgController@getMsgList')->withoutMiddleware('CheckLogin');
    //清除消息
    Route::any("clearMsg", 'QualitySendMsgController@clearMsg')->withoutMiddleware('CheckLogin');

});
