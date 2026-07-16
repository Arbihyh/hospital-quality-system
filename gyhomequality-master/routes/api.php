<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Artificial\Department\DetailController;


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

    Route::get("get_all_department", "DepartmentController@getAllDepartment")->withoutMiddleware('CheckLogin');
    Route::any('get_zyh_review_status', 'ArtificialControlController@getZyhReviewStatus')->withoutMiddleware('CheckLogin'); // 获取患者的审核状态
    Route::any('examineCaseAppeal', 'AppealController@examineAppeal')->withoutMiddleware('CheckLogin'); // 申诉审核
    Route::any('examineAppeal', 'AppealController@examineAppeal')->withoutMiddleware('CheckLogin'); // 申诉审核
    Route::any('get_brry', 'CaseController@getBrry')->withoutMiddleware('CheckLogin');
    Route::any('get_bl_blsy', 'CaseController@getBlBlsy')->withoutMiddleware('CheckLogin'); // 获取病例关联的书写医师
    Route::any('predict', 'ApiController@predict'); // 大模型质控接口
    Route::any('single_patient_quality', 'BigModelQualityController@singlePatientQuality'); // 单个患者大模型质控接口
    Route::any('custom_model_request', 'BigModelQualityController@customModelRequest'); // 自定义内容大模型请求接口
    Route::any('appeal_tab_nums', 'AppealController@appealTabNums')->withoutMiddleware('CheckLogin'); // tab申诉数量
    Route::any('getCaseAppeal', 'AppealController@getCaseAppeal')->withoutMiddleware('CheckLogin'); // 申诉列表
    Route::any('get_big_model_task', 'BigModelController@getTask')->withoutMiddleware('CheckLogin');
    Route::any('save_label', 'LabelController@saveLabel');
    //zz测试
    Route::any('zzTest', 'ZzTest@zzTest');
    Route::any('get_label', 'LabelController@getLabel');

    Route::any('get_setting', 'SettingController@getSetting')->withoutMiddleware('CheckLogin');
    Route::any('login', 'LoginController@login')->withoutMiddleware('CheckLogin');
    Route::any('thirdLogin', 'LoginController@thirdLogin')->withoutMiddleware('CheckLogin');
    Route::any('get_setting', 'SettingController@getSetting')->withoutMiddleware('CheckLogin');
    Route::any('logout', 'LoginController@logout')->withoutMiddleware('CheckLogin');
    Route::any('verifyForReset', 'LoginController@verifyForReset')->withoutMiddleware('CheckLogin'); // 忘记密码 - 验证身份
    Route::any('resetPassword', 'LoginController@resetPassword')->withoutMiddleware('CheckLogin'); // 忘记密码 - 重置密码
    Route::any('test', 'TestController@test')->withoutMiddleware('CheckLogin');

    Route::prefix('user')->group(function () {
        Route::any('info', 'UserController@userInfo');
        Route::any("menus", "UserController@groupMenuTree");
        Route::any("getDoctorList", "UserController@getDoctorList");

        //用户组(角色)操作
        Route::any("menuDropDown", "UserGroupController@menuDropDownList"); //添加用户组时权限下拉数据
        Route::post("addUserGroup", "UserGroupController@addUserGroup"); //添加用户组
        Route::post("editUserGroup", "UserGroupController@editUserGroup"); //编辑用户组
        Route::post("delUserGroup", "UserGroupController@delUserGroup"); //删除用户组
        Route::post("userGroupList", "UserGroupController@userGroupList"); //用户组列表


        //用户操作
        Route::any("depDropDown", "UserController@depDropDownList"); //添加编辑用户时使用的科室下拉数据
        Route::any("groupDropDown", "UserController@groupDropDownList"); //添加编辑用户时使用的用户组(角色)下拉数据
        Route::post("userList", "UserController@userList"); //用户列表
        Route::post("addUser", "UserController@addUser"); //添加用户
        Route::post("editUser", "UserController@editUser"); //编辑用户
        Route::post("delUser", "UserController@delUser"); //删除用户
        Route::post("lockAndUnlockUser", "UserController@lockAndUnlockUser"); //锁定或解锁用户
        Route::post("editPassword", "UserController@editPassword"); //修改密码
        Route::post("allDepartmentList", "UserController@allDepartmentList"); //获取全部科室列表
    });

    Route::post('selectStaff', 'QualityController@selectStaff')->withoutMiddleware('CheckLogin');
    Route::get('selectBmyStaff', 'QualityController@selectBmyStaff')->withoutMiddleware('CheckLogin');
    Route::post('selectInfo', 'QualityController@selectInfo')->withoutMiddleware('CheckLogin');
    Route::post('icd10DiagnosisList', 'QualityController@icd10DiagnosisList')->withoutMiddleware('CheckLogin'); // ICD10 诊断名称（模糊匹配）
    Route::post('icd10DiagnosisCodeList', 'QualityController@icd10DiagnosisCodeList')->withoutMiddleware('CheckLogin'); // ICD10 诊断编码（模糊匹配）
    Route::post('icd09OperationNameList', 'QualityController@icd09OperationNameList')->withoutMiddleware('CheckLogin'); // ICD09 手术名称（模糊匹配）
    Route::post('icd09OperationCodeList', 'QualityController@icd09OperationCodeList')->withoutMiddleware('CheckLogin'); // ICD09 手术编码（模糊匹配）
    Route::post('qualityList', 'QualityController@qualityList')->withoutMiddleware('CheckLogin');
    Route::post('exportData', 'QualityController@exportData'); //获取导出数据
    Route::post('getExportField', 'QualityController@getExportField')->withoutMiddleware('CheckLogin'); //获取导出字段
    Route::post('getCollect', 'QualityController@getCollect'); //获取搜索收藏详情
    Route::post('getSearchCollect', 'QualityController@getSearchCollect'); //获取账号搜索收藏
    Route::post('deleteSearchCollect', 'QualityController@deleteSearchCollect'); //搜索收藏删除
    Route::post('searchCollectSave', 'QualityController@searchCollectSave'); //搜索收藏保存
    Route::post('getDeportmentList', 'QualityController@getDeportmentList'); //获取科室
    Route::post('feeDetail', 'QualityController@feeDetail')->withoutMiddleware('CheckLogin');
    Route::post('getHomeQualityList', 'QualityController@getHomeQualityList'); // 病案数量
    Route::post('ruleList', 'QualityController@ruleList');
    Route::post('errorList', 'QualityController@errorList');
    Route::post('errorData', 'QualityController@errorData');
    Route::post('errorDataList', 'QualityController@errorDataList');
    Route::post('homeErrorDataList', 'QualityController@homeErrorDataList');
    Route::post('errorCount', 'QualityController@errorCount');
    Route::post('workrecord', 'WorkController@record'); //工作记录
    Route::post('workrepoet', 'WorkController@repoet'); //工作报告
    Route::post('search', 'QualityController@searchData'); //全病例-病例搜索引擎
    Route::post('normalSearch', 'QualityController@normalSearch'); //全病例-病例普通搜索
    Route::any('excel_error', 'ExcelController@excel_error'); //缺陷问题-导出excel

    Route::post('homeCensus', 'RankingController@homeCensus'); //统计
    Route::post('reportingHistory', 'ReportController@history'); //上报历史

    Route::post('ranking_hospital', 'RankingController@hospital'); //住院医师排名
    Route::post('ranking_department', 'RankingController@department'); //科室排名
    Route::post('ranking_attending_group', 'RankingController@attendingGroup'); //主诊组排名
    Route::post('ranking_indications', 'RankingController@indications'); //主治医师排名
    Route::post('ranking_coder', 'RankingController@coder'); //编码员排名

    Route::any('excel', 'ExcelController@export'); //测试excel下载
    Route::post('medical_record', 'MedicalRecordController@home')->withoutMiddleware('CheckLogin'); //住院病案首页
    Route::any('medicalRecordEdit', 'MedicalRecordController@editHome'); //住院病案首页
    Route::any('wtExport', 'QualityController@wtExport'); //卫统导出
    Route::any('gkExport', 'QualityController@gkExport'); //卫统导出
    Route::any('getTree', 'QualityController@getTree')->withoutMiddleware('CheckLogin');
    Route::any('getAllCase', 'QualityController@getAllCase')->withoutMiddleware('CheckLogin');
    Route::any('getHzxx', 'QualityController@getHzxx')->withoutMiddleware('CheckLogin');
    Route::any('get_assessment_indicators', 'RadioController@getList'); // 绩效考核指标
    Route::any('get_zhibiao_list', 'RadioController@getZbList'); // 查看指标对应的列表数据
    Route::any('get_illness_type', 'IllnessTypeController@getList'); // 单病种质量
    Route::any('get_case', 'CaseController@getList')->withoutMiddleware('CheckLogin'); // 病例质控
    Route::any('get_case_quality', 'CaseController@getCaseQuality')->withoutMiddleware('CheckLogin'); // 病例质控
    Route::any('get_case_platform', 'CaseController@getCasePlatform')->withoutMiddleware('CheckLogin'); // 病例数据格式化
    Route::any('get_pacs_dir', 'PacsController@getPacsPlatform'); // 目录
    Route::any('get_pacs_detail', 'PacsController@getList'); // 详细
    Route::any('get_jmgs_detail', 'JmgsController@getList'); // 详细
    Route::any('get_bc', 'QualityController@getBc'); // 病程明细
    Route::any('every', 'EveryController@everyDay'); // 自动取数据
    Route::any('long', 'DoctorAdviceController@long')->withoutMiddleware('CheckLogin'); // 长期医嘱
    Route::any('temporary', 'DoctorAdviceController@temporary')->withoutMiddleware('CheckLogin'); // 临时医嘱
    Route::any('getDoctorAdvice', 'DoctorAdviceController@getDoctorAdvice')->withoutMiddleware('CheckLogin'); // 医嘱查询
    Route::any('doctorAdviceSelect', 'DoctorAdviceController@doctorAdviceSelect')->withoutMiddleware('CheckLogin'); // 医嘱查询配置
    Route::any('doctorAdviceExport', 'DoctorAdviceController@doctorAdviceExport'); // 医嘱查询配置
    Route::any('get_surgery_data', 'CaseController@getSurgeryData')->withoutMiddleware('CheckLogin'); // 获取手术格式化数据
    Route::any('get_bc_data', 'CaseController@getBcData')->withoutMiddleware('CheckLogin'); // 获取病程格式化数据
    Route::any('get_pacs_data', 'PacsController@getPacsData')->withoutMiddleware('CheckLogin'); // 获取报告单格式化数据

    Route::post('add_feedback', 'ProblemFeedbackController@addFeedback'); // 问题反馈
    Route::any('get_department_list', 'ProblemFeedbackController@getDepartmentList')->withoutMiddleware('CheckLogin'); // 获取科室列表
    Route::any('get_omr_bl01_list', 'OmrBl01Controller@getOmrBl01List')->withoutMiddleware('CheckLogin'); // 门诊病例
    Route::post("get_omr_department_list", "OmrBl01Controller@getOmrDepartmentList")->withoutMiddleware('CheckLogin');  // 获取门诊科室列表

    // 全病历质控 相关路由
    Route::prefix('case-quality')->group(function () {
        Route::post('analysis', 'CaseQualityController@analysis'); // 质量分析
        Route::post("ranking_department", "CaseQualityController@rankingDepartment"); // 科室排名
        Route::post("defect_issues", "CaseQualityController@defectIssues")->withoutMiddleware('CheckLogin'); // 缺陷问题

        // 预警消息
        Route::prefix('warning')->group(function () {
            Route::any('get_admin_department', 'CaseController@getAdminDepartment')->withoutMiddleware('CheckLogin');
        });

        // 专家质控
        Route::prefix('expert')->group(function () {

            Route::any('ge_zjZk_list', 'CaseController@geZjZkList')->withoutMiddleware('CheckLogin'); // 质控列表
            Route::any('collect_zjzk_search', 'CaseController@collectZjzkSearch'); // 搜索条件收藏
            Route::any('get_collect_zjzk_search', 'CaseController@getCollectZjzkSearch'); // 搜索条件收藏
            Route::any('delete_collect_zjzk_search', 'CaseController@deleteCollectZjzkSearch');
            Route::any('get_default_collect_zjzk_search', 'CaseController@getDefaultCollectZjzkSearch');
        });
    });


    // 质控计划
    Route::prefix('case_quality_plan')->group(function () {
        Route::any('save', 'CaseQualityPlanController@save'); // 保存质控计划
        Route::any('list', 'CaseQualityPlanController@getList'); // 获取质控计划列表
        Route::any('delete', 'CaseQualityPlanController@delete'); // 删除质控计划
    });

    // 企业微信质控预警通知
    Route::prefix('wechat_work_notification')->group(function () {
        Route::any('config', 'WechatWorkNotificationController@config'); // 获取推送策略和群配置
        Route::post('save_config', 'WechatWorkNotificationController@saveConfig'); // 保存推送策略
        Route::any('group_list', 'WechatWorkNotificationController@groupList'); // 企业微信群列表
        Route::post('save_group', 'WechatWorkNotificationController@saveGroup'); // 保存企业微信群
        Route::post('delete_group', 'WechatWorkNotificationController@deleteGroup'); // 删除企业微信群
        Route::post('send_warning', 'WechatWorkNotificationController@sendWarning'); // 手动推送预警
        Route::post('send_test', 'WechatWorkNotificationController@sendTest'); // 发送测试消息
        Route::any('log_list', 'WechatWorkNotificationController@logList'); // 推送日志
        Route::post('mark_read', 'WechatWorkNotificationController@markRead')->withoutMiddleware('CheckLogin'); // 标记已查看
        Route::get('ack', 'WechatWorkNotificationController@ack')->withoutMiddleware('CheckLogin'); // 点击确认已收到
        Route::any('callback', 'WechatWorkNotificationController@callback')->withoutMiddleware('CheckLogin'); // 企业微信按钮回调
    });

    // 门诊质控 相关路由
    Route::prefix('omr_zk')->group(function () {
        Route::post('serach_type_list', 'OmrBl01Controller@serachTypeList')->withoutMiddleware('CheckLogin');
        Route::post('analysis', 'OmrBl01Controller@analysis'); // 质量分析
        Route::post("ranking_department", "OmrBl01Controller@rankingDepartment"); // 科室排名
        Route::post("ranking_doctor", "OmrBl01Controller@rankingDoctor"); //医师排名
        Route::post("defect_issues", "OmrBl01Controller@defectIssuesTrend"); // 缺陷问题（已统一使用聚合方式）
        Route::post("error_list", "OmrBl01Controller@errorList");       // 缺陷列表
        Route::post("error_list_export", "OmrBl01Controller@errorListExport");  // 缺陷列表导出
        Route::post("omr_info", "OmrBl01Controller@omrInfo")->withoutMiddleware('CheckLogin');
        Route::post("get_omr_quality", "OmrBl01Controller@getOmrQuality")->withoutMiddleware('CheckLogin');      // 质控结果
        Route::post("get_should_be_bl_list", "OmrBl01Controller@getShouldBeBlList");    // 实际门诊病历数量列表
        Route::post("get_should_be_bl_export", "OmrBl01Controller@getShouldBeBlExport"); // 实际门诊病历数据导出

        Route::post("docker_list", "OmrBl01Controller@getDoctorList");          // 医生列表
        Route::post("department_list", "OmrBl01Controller@getDepartmentList");  // 科室列表
        Route::post("mzh_list", "OmrBl01Controller@getMzh");        // 获取门诊号列表

        // 质控问题的月趋势图
        Route::post("quality_month_trend", "OmrBl01Controller@qualityMonthTrend");
        // 缺陷问题趋势图
        Route::post("defect_issues_trend", "OmrBl01Controller@defectIssuesTrend");
        //全部缺陷
        Route::post("defect_issues_all", "OmrBl01Controller@defectIssuesAll");
    });

    // 病案首页质控
    Route::prefix('home_quality')->group(function () {
        Route::post("getHospitalList", "HomeQualityController@getHospitalList");

        Route::post('errorData', 'HomeQualityController@errorData');
        Route::post('errorDataExport', 'HomeQualityController@errorDataExport');
        Route::post('errorDetailsList', 'HomeQualityController@errorDetailsList');
        Route::post('errorDetailsListExport', 'HomeQualityController@errorDetailsListExport');
        Route::post('getErrorSerachWhere', 'HomeQualityController@getErrorSerachWhere');

        Route::post("getQualityResult", "HomeQualityController@getQualityResult")->withoutMiddleware('CheckLogin'); // 获取病案首页质控结果
        Route::any("getHomeList", "HomeQualityController@getHomeList")->withoutMiddleware('CheckLogin'); //病案首页科室例数查询列表
        Route::post("doctorRankingList", "HomeQualityController@doctorRanking")->withoutMiddleware('CheckLogin'); // 病案首页医生站医师排名
        Route::post("department", "HomeQualityController@department")->withoutMiddleware('CheckLogin'); //首页质控医生站科室排名
    });

    // 病案首页事中质控（运行）
    Route::prefix('home_sz_quality')->group(function () {
        Route::post("qualityStatistics", "HomeSzQualityController@qualityStatistics");  // 统计分析
        Route::post("getHospitalList", "HomeSzQualityController@getHospitalList");  // 获取医院列表
        Route::post("getKsList", "HomeSzQualityController@getKsList");              // 获取质控结果中的出院科室
        Route::post("errorData", "HomeSzQualityController@errorData");              // 缺陷问题列表
        Route::post("errorDetailsList", "HomeSzQualityController@errorDetailsList")->withoutMiddleware('CheckLogin'); // 缺陷问题详情列表
        Route::post("blInfo", "HomeSzQualityController@blInfo")->withoutMiddleware('CheckLogin');                    // 病案首页（事中）详情页
        Route::post("qualityResult", "HomeSzQualityController@qualityResult")->withoutMiddleware('CheckLogin')->withoutMiddleware('CheckLogin');      // 质控结果

        Route::post("feeDetailedV2", "HomeSzQualityController@feeDetailedV2");      // 费用明细

        Route::post("upQuestion", "HomeSzQualityController@upQuestion")->withoutMiddleware('CheckLogin'); //首页质控医生站(修改问题)

        Route::post("generateQualityReport", "HomeSzQualityController@generateQualityReport");  // 生成病历质控分析报告（Word文档）
    });

    // Word报告统计接口
    Route::prefix('quality_report')->group(function () {
        Route::any("getReportStatistics", "QualityReportController@getReportStatistics")->withoutMiddleware('CheckLogin');;  // 获取Word报告统计数据

        // 质控病历数下钻接口
        Route::prefix('quality_report_drill')->group(function () {
            Route::post('totalCasesDrillDown', 'QualityReportDrillController@totalCasesDrillDown');
            Route::post('defectCasesDrillDown', 'QualityReportDrillController@defectCasesDrillDown');
            Route::post('defectCountDrillDown', 'QualityReportDrillController@defectCountDrillDown');
            Route::post('appealCasesDrillDown', 'QualityReportDrillController@appealCasesDrillDown');
        });
    });

    // 病历导入
    Route::prefix('bl_import')->group(function () {
        Route::post("importData", "BasyController@importData"); // 导入

        Route::any("getBlAll", "BasyController@getBlAll")->withoutMiddleware('CheckLogin');
    });

    // 手术并发症统计
    Route::prefix('ssbfz')->group(function () {
        Route::post("getBfzData", "SsbfzController@getBfzData");
        Route::post("getBfzList", "SsbfzController@getBfzList");
        Route::post("exportAll", "SsbfzController@exportAll");
    });

    // 病案首页编码员质控
    Route::prefix('home_bmy_quality')->group(function () {
        Route::post("bmyErrorData", "HomeSzQualityController@bmyErrorData");
        Route::post("bmyErrorDetailsList", "HomeSzQualityController@bmyErrorDetailsList");
        //        Route::post("bmyQualityResult", "HomeSzQualityController@bmyQualityResult")->withoutMiddleware('CheckLogin');    // 质控结果

        // 获取质控结果
        Route::post("bmyQualityResult", "HomeSzQualityController@getBmyQualityResult")->withoutMiddleware('CheckLogin');
    });

    // 病案首页编码员质控（新）
    Route::prefix('bmy')->group(function () {
        //获取搜索options
        Route::post("getSearchOptions", "HomeSzQualityController@bmyGetSearchOptions")->withoutMiddleware('CheckLogin');
        // 统计分析
        Route::post("qualityStatistics", "HomeSzQualityController@bmyQualityStatistics")->withoutMiddleware('CheckLogin');
        // 获取缺陷字段
        Route::post("errorFieldList", "HomeSzQualityController@errorFieldList")->withoutMiddleware('CheckLogin');
        // 缺陷问题
        Route::post("qualityData", "HomeSzQualityController@bmyQualityData")->withoutMiddleware('CheckLogin');
        Route::post("qualityExport", "HomeSzQualityController@bmyQualityExport")->withoutMiddleware('CheckLogin');

        // 科室优秀率
        Route::post("ksYxl", "HomeSzQualityController@bmyKsYxl")->withoutMiddleware('CheckLogin');
        // 科室平均分
        Route::post("ksPjf", "HomeSzQualityController@bmyKsPjf")->withoutMiddleware('CheckLogin');
        // 科室质控结果
        Route::post("ksQualityResult", "HomeSzQualityController@bmyKsQualityResult")->withoutMiddleware('CheckLogin');
        Route::post("ksQualityResultExport", "HomeSzQualityController@bmyKsQualityResultExport")->withoutMiddleware('CheckLogin');

        // 主治医师合格率
        Route::post("zzysYxl", "HomeSzQualityController@bmyZzysYxl")->withoutMiddleware('CheckLogin');
        // 主治医师平均分
        Route::post("zzysPjf", "HomeSzQualityController@bmyZzysPjf")->withoutMiddleware('CheckLogin');
        // 主治医师质控结果
        Route::post("zzysQualityResult", "HomeSzQualityController@bmyZzysQualityResult")->withoutMiddleware('CheckLogin');
        Route::post("zzysQualityResultExport", "HomeSzQualityController@bmyZzysQualityResultExport")->withoutMiddleware('CheckLogin');

        // 住院医师合格率
        Route::post("zyysYxl", "HomeSzQualityController@bmyZyysYxl")->withoutMiddleware('CheckLogin');
        // 住院医师平均分
        Route::post("zyysPjf", "HomeSzQualityController@bmyZyysPjf")->withoutMiddleware('CheckLogin');
        // 住院医师质控结果
        Route::post("zyysQualityResult", "HomeSzQualityController@bmyZyysQualityResult")->withoutMiddleware('CheckLogin');
        Route::post("zyysQualityResultExport", "HomeSzQualityController@bmyZyysQualityResultExport")->withoutMiddleware('CheckLogin');

        // 编码员
        Route::post("bmyQualityResultList", "HomeSzQualityController@bmyQualityResultList")->withoutMiddleware('CheckLogin');
        Route::post("bmyQualityResultExport", "HomeSzQualityController@bmyQualityResultListExport")->withoutMiddleware('CheckLogin');

        // 获取首页质控(编码员)医师排名医师options
        Route::post("getBmyIndexDoctorOptions", "HomeSzQualityController@getBmyIndexDoctorOptions");

        // 质控详情列表
        Route::post("bmyQualityList", "HomeSzQualityController@bmyQualityList")->withoutMiddleware('CheckLogin');

        // 编码员质控病历列表
        Route::post("qualityBlData", "HomeSzQualityController@bmyQualityBlData")->withoutMiddleware('CheckLogin');

        // 编码员质控医院列表
        Route::post("qualityHospitalList", "HomeSzQualityController@bmyQualityHospitalList")->withoutMiddleware('CheckLogin');

        // 获取质控结果
        Route::post("qualityResult", "HomeSzQualityController@getBmyQualityResult")->withoutMiddleware('CheckLogin');

        // 获取病案首页详细数据
        Route::post("getBlDetails", "HomeSzQualityController@getBlDetails")->withoutMiddleware('CheckLogin');

        // 获取费用明细
        Route::post("getFeeDetailed", "HomeSzQualityController@getFeeDetailed")->withoutMiddleware('CheckLogin');

        // 获取科室列表
        Route::post("getAllDepartment", "HomeSzQualityController@getAllDepartment")->withoutMiddleware('CheckLogin');

        // 医师排名
        Route::post("doctorRanking", "HomeSzQualityController@doctorRanking")->withoutMiddleware('CheckLogin');

        // 医师排名-病历列表
        Route::post("doctorRankingBlList", "HomeSzQualityController@doctorRankingBlList")->withoutMiddleware('CheckLogin');

        // 医师排名-根据code下钻病历明细列表
        Route::post("doctorRankingDrillList", "HomeSzQualityController@doctorRankingDrillList")->withoutMiddleware('CheckLogin');

        // 医师排名-缺陷字段
        Route::post("doctorErrorRanking", "HomeSzQualityController@doctorErrorRanking")->withoutMiddleware('CheckLogin');
    });

    // 病案首页编码员质控
    Route::prefix('quality_rule')->group(function () {
        Route::post("add_dict", "QualityRule@addDict")->withoutMiddleware('CheckLogin'); // 添加规则字典
        Route::post("get_dict", "QualityRule@getDict")->withoutMiddleware('CheckLogin'); // 获取规则字典列表
        Route::get("get_dict_detail", "QualityRule@getDictDetail")->withoutMiddleware('CheckLogin'); // 获取规则字典详情
        Route::post("del_dict", "QualityRule@delRule")->withoutMiddleware('CheckLogin'); // 删除规则字典
        Route::post("edit_dict_status", "QualityRule@editDictStatus")->withoutMiddleware('CheckLogin'); // 修改规则字典状态
        Route::get("get_field_detail", "QualityRule@getDataByField")->withoutMiddleware('CheckLogin'); // 获取字段对应的字典
    });

    // 质控字典关键词映射
    Route::prefix('rule_word_map')->group(function () {
        Route::any("add_word_map", "RuleWordMapController@addWordMap"); // 添加
        Route::any("edit_word_map", "RuleWordMapController@editWordMap"); // 修改
        Route::any("get_word_map", "RuleWordMapController@getWordMap"); // 获取
        Route::any("get_all_word_map", "RuleWordMapController@getAllWordMap"); // 获取所有
        Route::any("del_word_map", "RuleWordMapController@delWordMap"); // 删除
        Route::any("get_version_record", "RuleWordMapController@getVersionRecord"); // 获取版本记录
    });

    // 知识库接口
    Route::prefix('cdss_knowledge')->group(function () {
        Route::any("get_knowledge_list", "CdssKnowledgeController@getKnowledgeList")->withoutMiddleware('CheckLogin'); // 查询知识库列表
        Route::any("update_knowledge", "CdssKnowledgeController@updateKnowledge"); // 修改知识库数据
        Route::any("delete_knowledge", "CdssKnowledgeController@deleteKnowledge"); // 删除知识库数据
        Route::any("get_knowledge_detail", "CdssKnowledgeController@getKnowledgeDetail"); // 获取知识库详情
    });

    // 运营日志
    Route::prefix('man_logs')->group(function () {
        Route::any('list', 'ManLogController@list');  // 列表查询
        Route::any('add', 'ManLogController@add');    // 新增日志
    });


    Route::any('getBlZkList', 'ArtificialControlController@getBlZkList'); // 质控列表
    //    Route::any('examineAppeal','ArtificialControlController@examineAppeal')->withoutMiddleware('CheckLogin');// 申诉审核
    Route::any('getAppeal', 'ArtificialControlController@getAppeal')->withoutMiddleware('CheckLogin'); // 获取申诉信息
    Route::any('set_correction', 'ArtificialControlController@setCorrection')->withoutMiddleware('CheckLogin'); // 质控信息设置为已整改
    Route::any('correction_list', 'ArtificialControlController@correctionList')->withoutMiddleware('CheckLogin'); // 质控信息整改列表
    Route::any('getStaffList', 'ArtificialControlController@getStaffList')->withoutMiddleware('CheckLogin'); // 获取员工信息
    Route::any('getCampusAreaList', 'ArtificialControlController@getCampusAreaList'); // 获取院区信息
    Route::any('getPipeBeddingList', 'ArtificialControlController@getPipeBeddingList'); // 获取管床信息
    Route::any('getCaseNumberInfo', 'ArtificialControlController@getCaseNumberInfo')->withoutMiddleware('CheckLogin'); // 获取质控强制信息
    Route::any('getCaseResult', 'ArtificialControlController@getCaseResult'); // 病历智审结果接口
    Route::any('getQualityControlStatus', 'ArtificialControlController@getQualityControlStatus'); // 自动质控开关
    Route::any('updateQualityControl', 'ArtificialControlController@updateQualityControl'); // 质控开关更新
    Route::any('applyForReview', 'ArtificialControlController@applyForReview'); // 批量审核
    Route::any('getRevokeList', 'ArtificialControlController@getRevokeList'); // 撤销审核列表
    Route::any('revokeUpdate', 'ArtificialControlController@revokeUpdate'); // 撤销审核通过
    Route::any('getDataExamine', 'ApiControlController@getDataExamine')->withoutMiddleware('CheckLogin'); //获取质控状态
    Route::any('getSelectObjectValue', 'ArtificialControlController@getSelectObjectValue')->withoutMiddleware('CheckLogin'); //获取质控目录

    // 病案室
    Route::prefix('bl_zk')->group(function () {
        Route::post("getCaseQualityList", "ArtificialControlController@getCaseQualityList");   // 获取质控结果列表
        Route::post("getBlMenuList", "ArtificialControlController@getBlMenuList");     // 获取病历下质控结果列表
        Route::post("getHomeData", "ArtificialControlController@getHomeData")->withoutMiddleware('CheckLogin'); // 病案首页
        Route::post("getCasePlatform", "ArtificialControlController@getCasePlatform"); // 获取格式化病例内容
        Route::post('getSurgeryData', 'ArtificialControlController@getSurgeryData');    // 获取手术格式化数据
        Route::post('getBcData', 'ArtificialControlController@getBcData');      // 获取病程格式化数据
        Route::post('getPacsData', 'ArtificialControlController@getPacsData');  // 获取报告单格式化数据
        Route::post('getAllCase', 'ArtificialControlController@getAllCase');    // 获取病历数据
        Route::post('long', 'ArtificialControlController@long');                // 长期医嘱
        Route::post('temporary', 'ArtificialControlController@temporary');      // 临时医嘱
        Route::post('getRule', 'ArtificialControlController@getRule');          // 获取住院质控规则
        Route::post('addCaseQuality', 'ArtificialControlController@addCaseQuality');    // 添加质控结果
        Route::post('getCaseQuality', 'ArtificialControlController@getCaseQuality');    // 病例质控
        Route::post('getBlInfo', 'ArtificialControlController@getBlInfo');      // 获取住院质控规则（人工）
        Route::post('getCaseCate', 'ArtificialControlController@getCaseCate');      // 获取住院质控规则（人工）
    });

    //region 人工质控===
    Route::prefix('artificial')->group(function () {
        //region 科室质控===
        Route::prefix('department')->group(function () {
            Route::post('addQualityControlInfo', 'Artificial\Department\DetailController@addQualityControlInfo');
            Route::post('addRule', 'Artificial\Department\DetailController@addRule');
            Route::post('getZkInfo', 'Artificial\Department\DetailController@getZkInfo');
        });
        //endregion
    });
    //endregion

    //region 住院病历质控===
    Route::prefix('CaseHistory')->group(function () {
        //region 事中病历质控===
        Route::prefix('Sz')->group(function () {
            //getTotalList 事中统计汇总列表
            Route::post('getTotalList', 'CaseHistory\Sz\IndexController@getTotalList');
            // 事中缺陷问题列表
            Route::post('defectIssues', 'CaseHistory\Sz\IndexController@defectIssues');
            // 事中医师排名
            Route::post('doctorRanking', 'CaseHistory\Sz\IndexController@doctorRanking');
            // 事中科室排名
            Route::post('getDepartmentTableList', 'CaseHistory\Sz\IndexController@getDepartmentTableList');
        });
        //endregion

        //region 终末病历质控===
        Route::prefix('Terminal')->group(function () {
            //getTotalList 统计汇总列表
            Route::post('getTotalList', 'CaseHistory\Terminal\IndexController@getTotalList');
            Route::post('getDepartmentTableList', 'CaseHistory\Terminal\IndexController@getDepartmentTableList')->withoutMiddleware('CheckLogin');
            Route::post('getSearchOptions', 'CaseHistory\Terminal\IndexController@getSearchOptions');
            //获取头部搜索options
            Route::post('getKsOptions', 'CaseHistory\Terminal\IndexController@getKsOptions');
            //所属院区change事件
            Route::post('getBqOptions', 'CaseHistory\Terminal\IndexController@getBqOptions');
            //所属科室change事件
            Route::post('getDepartmentRankExport', 'CaseHistory\Terminal\IndexController@getDepartmentRankExport');
            //科室排名下载


            //region 下砖列表页面===
            //病例数量顶部搜索
            Route::post('getBlSearchOptions', 'CaseHistory\Terminal\ListController@getBlSearchOptions');

            //获取科室options
            Route::post('getKsOptions', 'CaseHistory\Terminal\ListController@getKsOptions');

            //获取病区options
            Route::post('getBqOptions', 'CaseHistory\Terminal\ListController@getBqOptions');

            //病例数量列表页面
            Route::post('blNumberTableList', 'CaseHistory\Terminal\ListController@blNumberTableList');

            //缺陷病例数量顶部搜索options
            Route::post('getQxBlSearchOptions', 'CaseHistory\Terminal\ListController@getQxBlSearchOptions');

            //获取缺陷病例数量列表
            Route::post('qxBlNumberTableList', 'CaseHistory\Terminal\ListController@qxBlNumberTableList')->withoutMiddleware('CheckLogin');
            // 公用的下拉选项
            Route::post('publicSelect', 'CaseHistory\Terminal\ListController@publicSelect');

            //endregion
        });
        //endregion
    });
    //endregion
});
