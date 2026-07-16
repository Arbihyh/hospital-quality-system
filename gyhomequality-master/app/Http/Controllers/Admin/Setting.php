<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\DataSource;
use App\Model\DataSourceHospital;
use App\Model\TableDict;
use App\Services\ToolsService;
use Illuminate\Http\Request;


class Setting extends Controller
{

    /**
     * @param Request $request
     * @return array
     * 系统全局设置
     */
    public function globalSet(Request $request)
    {
        $backgroundImg = $request->post('background_img', '');
        $logo = $request->post('logo', '');
        $menuLogo = $request->post('menu_logo', '');
        $name = $request->post('web_name', '');


        $backgroundImg = saveImg($backgroundImg);
        $logo = saveImg($logo);
        $menuLogo = saveImg($menuLogo);
        $msg = '';
        try {
            \App\Model\Setting::query()->updateOrInsert(['name'=>'background_img'],['content'=>$backgroundImg]);
            \App\Model\Setting::query()->updateOrInsert(['name'=>'logo'],['content'=>$logo]);
            \App\Model\Setting::query()->updateOrInsert(['name'=>'menu_logo'],['content'=>$menuLogo]);
            \App\Model\Setting::query()->updateOrInsert(['name'=>'web_name'],['content'=>$name]);
            $code = 0;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnAdmin($code, true, $msg);
    }
}
