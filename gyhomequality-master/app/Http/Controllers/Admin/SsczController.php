<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ExportData;
use App\Http\Controllers\Controller;
use App\Model\SSCZ;
use App\Services\CsvService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class SsczController extends Controller
{
    /**
     * 列表
     * @param Request $request
     * @return array
     */
    public function ssczList(Request $request)
    {
        $ssbm = $request->post('ssbm','');
        $ssmc = $request->post('ssmc','');
        $sslb = $request->post('sslb','');
        $lrxx = $request->post('lrxx','');
        $status = $request->post('status','');
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
        if (!empty($lrxx)) {
            $where[] = ['LRXX','like',"%".$lrxx."%"];
        }
        if ($status !== '' && $status !== null) {
            $where[] = ['status','=',$status];
        }

        $data = SSCZ::query()
            ->where($where)
            ->paginate($pageSize,['id','SSBM','SSMC','SSLB','LRXX','status'],'page',$page)
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
    public function ssczExport(Request $request)
    {
        $ssbm = $request->post('ssbm','');
        $ssmc = $request->post('ssmc','');
        $sslb = $request->post('sslb','');
        $lrxx = $request->post('lrxx','');
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
        if (!empty($lrxx)) {
            $where[] = ['LRXX','like',"%".$lrxx."%"];
        }
        if ($status !== '' && $status !== null) {
            $where[] = ['status','=',$status];
        }

        $exportData = SSCZ::query()
            ->where($where)
            ->get(['SSBM','SSMC','SSLB','LRXX'])
            ->toArray();

        foreach ($exportData as &$value) {
            $value['SSBM'] = (string)$value['SSBM']."\t";
        }

        $title = ['手术编码','手术名称','手术类别','录入选项'];
        return Excel::download(new ExportData($title, $exportData), '手术操作映射.csv');

//        $title = ['手术编码','手术名称','手术类别','录入选项'];
//        array_unshift($exportData,$title);
//
//        $csv = new CsvService();
//        $csv->filename = $csv->charset('手术操作映射', 'UTF-8');
//
//        return $csv->export($exportData, false);
    }

    /**
     * 添加
     * @param Request $request
     * @return array
     */
    public function ssczAdd(Request $request)
    {
        $ssbm = $request->post('ssbm','');
        $ssmc = $request->post('ssmc','');
        $sslb = $request->post('sslb','');
        $lrxx = $request->post('lrxx','');
        $status = $request->post('status',0);

        if (empty($ssbm) || empty($ssmc) || empty($sslb)) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }

        $ssczInfo = SSCZ::query()->where('SSBM','=',$ssbm)->first();
        if (!empty($ssczInfo)) {
            return ToolsService::returnAdmin(1, '', '手术编码已存在');
        }
        $ssczInfo = SSCZ::query()->where('SSMC','=',$ssmc)->first();
        if (!empty($ssczInfo)) {
            return ToolsService::returnAdmin(1, '', '手术名称已存在');
        }

        $insertData = [
            'SSBM' => $ssbm,
            'SSMC' => $ssmc,
            'SSLB' => $sslb,
            'LRXX' => $lrxx,
            'status' => $status
        ];
        $res = SSCZ::query()->insert($insertData);
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
    public function ssczSave(Request $request)
    {
        $id = $request->post('id','');
        $ssbm = $request->post('ssbm','');
        $ssmc = $request->post('ssmc','');
        $sslb = $request->post('sslb','');
        $lrxx = $request->post('lrxx','');
        $status = $request->post('status',0);

        if (empty($id) || empty($ssbm) || empty($ssmc) || empty($sslb)) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }

        $insertData = [
            'SSBM' => $ssbm,
            'SSMC' => $ssmc,
            'SSLB' => $sslb,
            'LRXX' => $lrxx,
            'status' => $status
        ];
        $res = SSCZ::query()->where('id','=',$id)->update($insertData);
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
    public function ssczDelete(Request $request)
    {
        $id = $request->post('id','');
        if (empty($id)) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }

//        $res = SSCZ::query()->where('id','=',$id)->delete();
//        if ($res) {
//            return ToolsService::returnAdmin(0, [], '操作成功');
//        }

        return ToolsService::returnAdmin(1, [], '操作失败');
    }


}
