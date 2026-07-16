<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::namespace('Admin')->group(function () {
    Route::any('getCaseAppeal', 'CaseController@getCaseAppeal')->withoutMiddleware('AdminRbac');
    Route::any('examineAppeal', 'CaseController@examineAppeal')->withoutMiddleware('AdminRbac');
    Route::post("login", "LoginController@login")->withoutMiddleware('AdminRbac');
    Route::prefix('admin')->group(function (){
        Route::post('adminInfo', 'AdminController@adminInfo');
        Route::post('adminList', 'AdminController@adminList');
        Route::post('addAdmin', 'AdminController@addAdmin');
        Route::post('editAdmin', 'AdminController@editAdmin');
        Route::post('delAdmin', 'AdminController@delAdmin');
        Route::post("adminGroupList", "AdminController@groupList");
        Route::post("editAdminGroup", 'AdminController@editAdminGroup');
        Route::post("addAdminGroup", 'AdminController@addAdminGroup');
        Route::post('delAdminGroup', 'AdminController@delAdminGroup');
        Route::post('adminGroup', 'AdminController@adminGroup');
        Route::post('groupList', 'AdminController@groupList');
        Route::post("menuList", "MenuController@groupMenuTree");
        Route::post("menu", "MenuController@groupMenus");
        Route::post("rbacList", "MenuController@menuList");
        Route::post("adminLog", "AdminController@adminLogList");

        Route::any('getSurgeryList', 'SurgeryController@getList');
        Route::any('addSurgery', 'SurgeryController@addSurgery');
        Route::any('editSurgery', 'SurgeryController@editSurgery');
        Route::any('delSurgery', 'SurgeryController@delSurgery');

        Route::any('getSurgeryMappingList', 'SurgeryMappingController@getList');
        Route::any('addSurgeryMapping', 'SurgeryMappingController@add');
        Route::any('editSurgeryMapping', 'SurgeryMappingController@edit');
        Route::any('delSurgeryMapping', 'SurgeryMappingController@del');
    });
    Route::prefix('user')->group(function (){
        Route::post('userList', 'UserController@userList');
        Route::post('addUser', 'UserController@addUser');
        Route::post('editUser', 'UserController@editUser');
        Route::post('delUser', 'UserController@delUser');
        Route::post('userGroup', 'UserController@userGroup');
        Route::post("userGroupList", "UserController@groupList");
        Route::post("addUserGroup", 'UserController@addUserGroup');
        Route::post("editUserGroup", 'UserController@editUserGroup');
        Route::post('delUserGroup', 'UserController@delUserGroup');
        Route::post("rbacList", "UserMenuController@menuList");
        Route::post("userLog", "UserController@userLogList");
        Route::post("userSearchLog", "UserController@userSearchLogList");
        Route::post("userSearchLogExport", "UserController@userSearchLogExport");
        Route::post("getDeportmentList", "UserController@getDeportmentList");

        Route::post('feedbackList','UserController@feedbackList')->withoutMiddleware('AdminRbac');// 反馈列表
        Route::post('editPassword', 'UserController@editPassword')->withoutMiddleware('AdminRbac'); // 修改密码
    });
    Route::prefix('rule')->group(function (){
        Route::any('errorList', 'ErrorRuleController@getList');
        Route::any('addErrorRule', 'ErrorRuleController@addErrorRule');
        Route::any('delErrorRule', 'ErrorRuleController@delErrorRule');
        Route::any('editErrorRule', 'ErrorRuleController@editErrorRule');
        Route::any('updateStatus', 'ErrorRuleController@updateStatus');
        Route::any('updateBmyLevel', 'ErrorRuleController@updateBmyLevel');
    });

    Route::prefix('operation')->group(function (){
        Route::any('relationsList', 'OperationController@relationsList');
        Route::any('addOperationRelation', 'OperationController@addOperationRelation');
        Route::any('editOperationRelation', 'OperationController@editOperationRelation');
        Route::any('delOperationRelation', 'OperationController@delOperationRelation');
        Route::any('updateStatus', 'OperationController@updateStatus');
        Route::any('getCaseRule','CaseController@getCaseRule')->withoutMiddleware('AdminRbac');// 病例规则列表
        Route::any('getCategory','CaseController@getCategory')->withoutMiddleware('AdminRbac');// 病例规则列表下拉选项
        Route::any('getType','CaseController@getType')->withoutMiddleware('AdminRbac');// 病例规则列表下拉选项
        Route::any('getDepartmentList','CaseController@getDepartmentList')->withoutMiddleware('AdminRbac');// 科室列表
        Route::any('addCaseRule','CaseController@addCaseRule')->withoutMiddleware('AdminRbac');// 添加病例规则
        Route::any('updateCaseRuleStatus','CaseController@updateCaseRuleStatus'); // 修改质控规则的状态
        Route::any('setCaseRuleshizhong','CaseController@setCaseRuleshizhong'); // 修改质控规则的是否同步到事中质控
    });

    // 疾病管理
    Route::prefix('disease')->group(function (){
        Route::post("templateExport", "DiseaseController@templateExport");// 模板下载
        Route::post("diseaseImport", "DiseaseController@diseaseImport");// 导入
        Route::post("diseaseExport", "DiseaseController@diseaseExport");// 导出
        Route::post("diseaseList", "DiseaseController@diseaseList");// 列表
        Route::post("diseaseInfo", "DiseaseController@diseaseInfo");// 列表
        Route::post("diseaseAdd", "DiseaseController@diseaseAdd");// 新增
        Route::post("diseaseSave", "DiseaseController@diseaseSave");// 编辑
        Route::post("diseaseDelete", "DiseaseController@diseaseDelete");// 删除
        Route::post("syncSurgery", "DiseaseController@syncSurgery");// 同步手术信息
    });

    // 手术管理
    Route::prefix('surgery')->group(function (){
        Route::post("templateExport", "SurgeryManageController@templateExport");// 模板下载
        Route::post("surgeryImport", "SurgeryManageController@surgeryImport");// 导入
        Route::post("surgeryExport", "SurgeryManageController@surgeryExport");// 导出
        Route::post("surgeryList", "SurgeryManageController@surgeryList");// 列表
        Route::post("surgeryInfo", "SurgeryManageController@surgeryInfo");// 列表
        Route::post("surgeryAdd", "SurgeryManageController@surgeryAdd");// 新增
        Route::post("surgerySave", "SurgeryManageController@surgerySave");// 编辑
        Route::post("surgeryDelete", "SurgeryManageController@surgeryDelete");// 删除
        Route::post("syncDisease", "SurgeryManageController@syncDisease");// 同步疾病信息
    });

    // 门诊质控规则管理
    Route::prefix('omr_rule')->group(function (){
        Route::post("getCategory", "OmrRuleController@getCategory");     // 质控分类
        Route::post("ruleList", "OmrRuleController@ruleList");   // 列表
        Route::post("ruleInfo", "OmrRuleController@ruleInfo");   // 详情
        Route::post("ruleAdd", "OmrRuleController@ruleAdd");     // 添加
        Route::post("ruleSave", "OmrRuleController@ruleSave");   // 编辑
        Route::post("ruleSaveStatus", "OmrRuleController@ruleSaveStatus");   // 修改状态
    });

    // 病案室
    Route::prefix('bl_zk')->group(function (){
        Route::post("getBlZkList", "CaseQualityControlController@getBlZkList"); // 质控病历列表
        Route::post("getCaseQualityList", "CaseQualityControlController@getCaseQualityList");   // 获取质控结果列表
        Route::post("getBlMenuList", "CaseQualityControlController@getBlMenuList");     // 获取病历下质控结果列表
        Route::post("getHomeData", "CaseQualityControlController@getHomeData"); // 病案首页
        Route::post("getCasePlatform", "CaseQualityControlController@getCasePlatform"); // 获取格式化病例内容
        Route::post('getSurgeryData','CaseQualityControlController@getSurgeryData');    // 获取手术格式化数据
        Route::post('getBcData','CaseQualityControlController@getBcData');      // 获取病程格式化数据
        Route::post('getPacsData','CaseQualityControlController@getPacsData');  // 获取报告单格式化数据
        Route::post('getAllCase','CaseQualityControlController@getAllCase');    // 获取病历数据
        Route::post('long','CaseQualityControlController@long');                // 长期医嘱
        Route::post('temporary','CaseQualityControlController@temporary');      // 临时医嘱
        Route::post('getRule','CaseQualityControlController@getRule');          // 获取住院质控规则
        Route::post('addCaseQuality','CaseQualityControlController@addCaseQuality');    // 添加质控结果
        Route::post('getCaseQuality','CaseQualityControlController@getCaseQuality');    // 病例质控
        Route::post('getBlInfo','CaseQualityControlController@getBlInfo');      // 获取住院质控规则（人工）
    });

    // 手术操作
    Route::prefix('sscz')->group(function (){
        Route::post("ssczList", "SsczController@ssczList");
        Route::post("ssczExport", "SsczController@ssczExport");
        Route::post("ssczAdd", "SsczController@ssczAdd");
        Route::post("ssczSave", "SsczController@ssczSave");
        Route::post("ssczDelete", "SsczController@ssczDelete");
    });

    // 手术操作映射
    Route::prefix('ssczys')->group(function (){
        Route::post("ssczysList", "SsczysController@ssczysList");
        Route::post("ssczysExport", "SsczysController@ssczysExport");
        Route::post("ssczysAdd", "SsczysController@ssczysAdd");
        Route::post("ssczysSave", "SsczysController@ssczysSave");
        Route::post("ssczysDelete", "SsczysController@ssczysDelete");
    });

    // 病案首页编码员质控
    Route::prefix('quality_rule')->group(function (){
        // 字典相关配置
        Route::post("add_dict", "QualityRule@addDict")->withoutMiddleware('AdminRbac'); // 添加规则字典
        Route::post("edit_field", "QualityRule@editField")->withoutMiddleware('AdminRbac'); // 添加规则字典
        Route::post("edit_field_dict", "QualityRule@editFieldDict")->withoutMiddleware('AdminRbac'); // 添加规则字典
        Route::post("get_dict", "QualityRule@getDict")->withoutMiddleware('AdminRbac'); // 获取规则字典列表
        Route::get("get_dict_detail", "QualityRule@getDictDetail")->withoutMiddleware('AdminRbac'); // 获取规则字典详情
        Route::post("del_dict", "QualityRule@delDict")->withoutMiddleware('AdminRbac'); // 删除规则字典
        Route::post("edit_dict_status", "QualityRule@editDictStatus")->withoutMiddleware('AdminRbac'); // 修改规则字典状态
        Route::get("get_field_detail", "QualityRule@getDataByField")->withoutMiddleware('AdminRbac'); // 获取字段对应的字典
        Route::post("get_dict_by_type", "QualityRule@getDictByType")->withoutMiddleware('AdminRbac'); // 获取字段对应的字典

        // 规则相关配置
        Route::any("add_rule", "QualityRule@addRule")->withoutMiddleware('AdminRbac'); // 添加规则模板
        Route::any("edit_rule_status", "QualityRule@editRuleStatus")->withoutMiddleware('AdminRbac'); // 添加规则模板
        Route::any("get_rule_list", "QualityRule@getRuleList")->withoutMiddleware('AdminRbac'); // 获取规则模板列表
        Route::any("get_all_rule_list", "QualityRule@getAllRuleList")->withoutMiddleware('AdminRbac'); // 获取合并规则列表
        Route::any("get_deleted_rule_list", "QualityRule@getDeletedRuleList")->withoutMiddleware('AdminRbac'); // 获取已删除规则列表
        Route::any("restore_rule", "QualityRule@restoreRule")->withoutMiddleware('AdminRbac'); // 恢复已删除规则
        Route::any("export_all_rule_list", "QualityRule@exportAllRuleList")->withoutMiddleware('AdminRbac'); // 导出合并规则列表
        Route::any("get_rule_statistics", "QualityRule@getRuleStatistics")->withoutMiddleware('AdminRbac'); // 获取规则统计
        Route::any("get_rule_detail", "QualityRule@getRuleDetail")->withoutMiddleware('AdminRbac'); // 获取规则模板列表
        Route::any("del_rule", "QualityRule@delRule")->withoutMiddleware('AdminRbac'); // 获取规则模板列表
        Route::get("get_select_object", "QualityRule@getSelectObjectValue")->withoutMiddleware('AdminRbac'); // 质控项目的下拉
        Route::get("get_select_department", "QualityRule@getSelectDepartmentValue")->withoutMiddleware('AdminRbac'); // 质控科室的下拉
        Route::get("get_select_department2", "QualityRule@getSelectDepartmentValue2")->withoutMiddleware('AdminRbac'); // 自定义质控科室的下拉
        Route::get("get_select_formula", "QualityRule@getSelectFormula")->withoutMiddleware('AdminRbac'); // 获取规则公式列表
        Route::get("get_rule_setting_other", "QualityRule@getRuleSettingOther")->withoutMiddleware('AdminRbac'); // 获取病历类型,质控类型,质控场景的下拉数据


        // 质控字典
        Route::any("add_word_map", "QualityRule@addWordMap")->withoutMiddleware('AdminRbac'); // 添加
        Route::any("edit_word_map", "QualityRule@editWordMap")->withoutMiddleware('AdminRbac'); // 修改
        Route::any("get_word_map", "QualityRule@getWordMap")->withoutMiddleware('AdminRbac'); // 获取
        Route::any("get_all_word_map", "QualityRule@getAllWordMap")->withoutMiddleware('AdminRbac'); // 获取
        Route::any("del_word_map", "QualityRule@delWordMap")->withoutMiddleware('AdminRbac'); // 删除
        Route::any("get_version_record", "QualityRule@getVersionRecord")->withoutMiddleware('AdminRbac'); // 获取版本记录
    });

    // 数据源
    Route::prefix('data_source')->group(function (){

        Route::post("lists", "DataSourceController@lists")->withoutMiddleware('AdminRbac'); // 添加规则字典
        Route::post("add", "DataSourceController@add")->withoutMiddleware('AdminRbac'); // 添加规则字典
        Route::get("info", "DataSourceController@info")->withoutMiddleware('AdminRbac'); // 添加规则字典
        Route::post("edit", "DataSourceController@edit")->withoutMiddleware('AdminRbac'); // 添加规则字典
        Route::post("del", "DataSourceController@del")->withoutMiddleware('AdminRbac'); // 添加规则字典

        Route::post("hospitalAdd", "DataSourceController@hospitalAdd")->withoutMiddleware('AdminRbac'); // 添加规则字典
        Route::get("hospitalList", "DataSourceController@hospitalList")->withoutMiddleware('AdminRbac'); // 添加规则字典
        Route::get("options", "DataSourceController@getOptions")->withoutMiddleware('AdminRbac'); // 添加规则字典

    });

    // 数据源
    Route::prefix('setting')->group(function (){
        Route::post("global_set", "Setting@globalSet")->withoutMiddleware('AdminRbac');
        Route::any('get_setting','SettingController@getSetting')->withoutMiddleware('AdminRbac');

    });

    // 大模型模板配置
    Route::prefix('big_model')->group(function (){
        Route::get("get_task_name", "BigModelController@getTaskName")->withoutMiddleware('AdminRbac');
        Route::get("get_input_select", "BigModelController@getInputSelect")->withoutMiddleware('AdminRbac');
        Route::get("get_case_rule", "BigModelController@getCaseRule")->withoutMiddleware('AdminRbac');
        Route::post("get_template", "BigModelController@getTemplList")->withoutMiddleware('AdminRbac');
        Route::post("set_template", "BigModelController@setTemplate")->withoutMiddleware('AdminRbac');
        Route::post("del_template", "BigModelController@delTemplate")->withoutMiddleware('AdminRbac');
        Route::post("get_custom_template", "CustomTemplateController@getList")->withoutMiddleware('AdminRbac');
        Route::post("set_custom_template", "CustomTemplateController@setTemplate")->withoutMiddleware('AdminRbac');
        Route::post("del_custom_template", "CustomTemplateController@delTemplate")->withoutMiddleware('AdminRbac');
        Route::get("get_custom_template_departments", "CustomTemplateController@getDepartmentList")->withoutMiddleware('AdminRbac');
        Route::get("get_custom_template_diseases", "CustomTemplateController@getDiseaseList")->withoutMiddleware('AdminRbac');
        Route::get("selectmblb", "BigModelController@selectMblb")->withoutMiddleware('AdminRbac');
        Route::post("set_status", "BigModelController@setStatus")->withoutMiddleware('AdminRbac');
    });

});
