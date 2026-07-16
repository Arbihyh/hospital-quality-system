<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CaseService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use App\Model\CaseRule;

class SettingController extends Controller
{
    /**
     * @param Request $request
     * @param CaseService $caseService
     * @return array
     */
    public function getSetting(Request $request)
    {
        $res = \App\Model\Setting::query()->get()->toArray();
        $res = array_column($res, null, 'name');
        return ToolsService::returnData(200, $res, $msg ?? '');
    }

}
