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
    });
    Route::prefix('rule')->group(function (){
        Route::any('errorList', 'ErrorRuleController@getList');
        Route::any('addErrorRule', 'ErrorRuleController@addErrorRule');
        Route::any('delErrorRule', 'ErrorRuleController@delErrorRule');
        Route::any('editErrorRule', 'ErrorRuleController@editErrorRule');
        Route::any('updateStatus', 'ErrorRuleController@updateStatus');
    });

    Route::prefix('operation')->group(function (){
        Route::any('relationsList', 'OperationController@relationsList');
        Route::any('addOperationRelation', 'OperationController@addOperationRelation');
        Route::any('editOperationRelation', 'OperationController@editOperationRelation');
        Route::any('delOperationRelation', 'OperationController@delOperationRelation');
        Route::any('updateStatus', 'OperationController@updateStatus');
        Route::any('getCaseRule','CaseController@getCaseRule');// 病例规则列表
        Route::any('addCaseRule','CaseController@addCaseRule');// 添加病例规则
    });
});
