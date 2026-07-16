<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ExportData;
use App\Http\Controllers\Controller;
use App\Model\SSCZYS;
use App\Services\CsvService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class SsczysController extends Controller
{
    /**
     * 列表
     * @param Request $request
     * @return array
     */
    public function ssczysList(Request $request)
    {
        $ssbm = $request->post('ssbm','');
        $ssmc = $request->post('ssmc','');
        $sslb = $request->post('sslb','');
        $ssysmc = $request->post('ssysmc','');
        $ssysbm = $request->post('ssysbm','');
        $ssnm = $request->post('ssnm','');
        $page = $request->post('page',1);
        $pageSize = $request->post('page_size',10);

        $where = [];
        if (!empty($ssbm)) {
            $where[] = ['SSBM','like',"%".$ssbm."%"];
        }
        if (!empty($ssmc)) {
            $where[] = ['SSMC','like',"%".$ssmc."%"];
        }
        if (!empty($sslb)) {
            $where[] = ['SSLB','like',"%".$sslb."%"];
        }
        if (!empty($ssysbm)) {
            $where[] = ['SSYSBM','like',"%".$ssysbm."%"];
        }
        if (!empty($ssysmc)) {
            $where[] = ['SSYSMC','like',"%".$ssysmc."%"];
        }
        if (!empty($ssnm)) {
            $where[] = ['SSNM','=',$ssnm];
        }

        $data = SSCZYS::query()
            ->where($where)
            ->paginate($pageSize,['id','SSBM','SSMC','SSYSBM','SSYSMC','SSLB','SSNM'],'page',$page)
            ->toArray();

        $returnData = [
            'list' => $data['data'] ?? [],
            'count' => $data['total'] ?? 0
        ];

        return ToolsService::returnAdmin(0, $returnData);
    }

    /**
     * 导出
     * @return string|null
     */
    public function ssczysExport(Request $request)
    {
        $ssbm = $request->post('ssbm','');
        $ssmc = $request->post('ssmc','');
        $ssysbm = $request->post('ssysbm','');
        $ssysmc = $request->post('ssysmc','');
        $sslb = $request->post('sslb','');
        $ssnm = $request->post('ssnm','');
        $status = $request->post('status','');

        $where = [];
        if (!empty($ssbm)) {
            $where[] = ['SSBM','like',"%".$ssbm."%"];
        }
        if (!empty($ssmc)) {
            $where[] = ['SSMC','like',"%".$ssmc."%"];
        }
        if (!empty($sslb)) {
            $where[] = ['SSLB','like',"%".$sslb."%"];
        }
        if (!empty($ssysbm)) {
            $where[] = ['SSYSBM','like',"%".$ssysbm."%"];
        }
        if (!empty($ssysmc)) {
            $where[] = ['SSYSMC','like',"%".$ssysmc."%"];
        }
        if (!empty($ssnm)) {
            $where[] = ['SSNM','=',$ssnm];
        }
        $exportData = SSCZYS::query()
            ->where($where)
            ->get(['SSBM','SSMC','SSYSBM','SSYSMC','SSLB','SSNM'])
            ->toArray();

        foreach ($exportData as &$value) {
            $value['SSBM'] = (string)$value['SSBM']."\t";
            $value['SSYSBM'] = (string)$value['SSYSBM']."\t";
            $value['SSNM'] = (string)$value['SSNM']."\t";
        }

        $title = ['手术编码','手术名称','手术映射编码','手术映射名称','手术类别','手术内码'];
        return Excel::download(new ExportData($title, $exportData), '手术操作.csv');

//        $title = ['手术编码','手术名称','手术映射编码','手术映射名称','手术类别','手术内码'];
//        array_unshift($exportData,$title);
//
//        $csv = new CsvService();
//        $csv->filename = $csv->charset('手术操作', 'UTF-8');
//
//        return $csv->export($exportData, false);
    }

    /**
     * 添加
     * @param Request $request
     * @return array
     */
    public function ssczysAdd(Request $request)
    {
        $ssbm = $request->post('ssbm','');
        $ssmc = $request->post('ssmc','');
        $sslb = $request->post('sslb','');
        $ssysbm = $request->post('ssysbm','');
        $ssysmc = $request->post('ssysmc','');
        $ssnm = $request->post('ssnm','');
        $status = $request->post('status',0);

        if (empty($ssbm) || empty($ssmc) || empty($sslb) || empty($ssysbm) || empty($ssysmc) || empty($ssnm)) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }

        $ssczInfo = SSCZYS::query()->where('SSBM','=',$ssbm)->first();
        if (!empty($ssczInfo)) {
            return ToolsService::returnAdmin(1, '', '手术编码已存在');
        }
        $ssczInfo = SSCZYS::query()->where('SSMC','=',$ssmc)->first();
        if (!empty($ssczInfo)) {
            return ToolsService::returnAdmin(1, '', '手术名称已存在');
        }

        $insertData = [
            'SSBM' => $ssbm,
            'SSMC' => $ssmc,
            'SSLB' => $sslb,
            'SSYSBM' => $ssysbm,
            'SSYSMC' => $ssysmc,
            'SSNM' => $ssnm,
            'status' => $status
        ];
        $res = SSCZYS::query()->insert($insertData);
        if ($res) {
            return ToolsService::returnAdmin(0, [], '操作成功');
        }

        return ToolsService::returnAdmin(1, '', '操作失败');
    }

    /**
     * 修改
     * @param Request $request
     * @return array
     */
    public function ssczysSave(Request $request)
    {
        $id = $request->post('id','');
        $ssbm = $request->post('ssbm','');
        $ssmc = $request->post('ssmc','');
        $ssysbm = $request->post('ssysbm','');
        $ssysmc = $request->post('ssysmc','');
        $sslb = $request->post('sslb','');
        $ssnm = $request->post('ssnm','');
        $status = $request->post('status',0);

        if (empty($id) || empty($ssbm) || empty($ssmc) || empty($sslb) || empty($ssysbm) || empty($ssysmc) || empty($ssnm)) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }

        $insertData = [
            'SSBM' => $ssbm,
            'SSMC' => $ssmc,
            'SSLB' => $sslb,
            'SSYSBM' => $ssysbm,
            'SSYSMC' => $ssysmc,
            'SSNM' => $ssnm,
            'status' => $status
        ];
        $res = SSCZYS::query()->where('id','=',$id)->update($insertData);
        if ($res) {
            return ToolsService::returnAdmin(0, [], '操作成功');
        }

        return ToolsService::returnAdmin(1, '', '操作失败');
    }

    /**
     * 删除
     * @param Request $request
     * @return array
     */
    public function ssczysDelete(Request $request)
    {
        $id = $request->post('id','');
        if (empty($id)) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }

//        $res = SSCZYS::query()->where('id','=',$id)->delete();
//        if ($res) {
//            return ToolsService::returnAdmin(0, [], '操作成功');
//        }

        return ToolsService::returnAdmin(1, [], '操作失败');
    }


}
