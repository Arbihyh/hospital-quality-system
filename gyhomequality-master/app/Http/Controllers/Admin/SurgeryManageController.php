<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ExportData;
use App\Http\Controllers\Controller;
use App\Http\Service\PinYin2AbbreviationService;
use App\Imports\SurgeryImport;
use App\Model\Disease;
use App\Model\Surgery;
use App\Services\CsvService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class SurgeryManageController extends Controller
{
    /**
     * 模本导出
     * @return string|null
     */
    public function templateExport()
    {
        // 查询要导出的数据
        $title = ['科室','手术名称（必填）','别名（必填 多个别名用英文逗号“,”隔开）','手术编码','并发症','检查','检验','参考文献','疾病名称','疾病编码'];
        $exportData = [];

        return Excel::download(new ExportData($title,$exportData), '手术导入模板.xlsx');
//        $csv = new CsvService();
//        $csv->filename = $csv->charset('手术导入模板', 'UTF-8');
//        return $csv->export($exportData, false);
    }

    /**
     * 导入
     * @param Request $request
     * @return array
     */
    public function surgeryImport(Request $request)
    {
        $file = $request->file('file','');
//        $file = 'surgery.xlsx';
        if (empty($file)) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }

        Excel::import(new SurgeryImport(), $file, 'local');

        $data = [];
        return ToolsService::returnAdmin(0, $data);
    }

    /**
     * 导出
     * @param Request $request
     * @return string|null
     */
    public function surgeryExport(Request $request)
    {
        $FLAG = $request->post('FLAG','');  //序号
        $KSMC = $request->post('KSMC','');  //科室
        $SSMC = $request->post('SSMC','');  //手术名称
        $BM = $request->post('BM','');      //别名
        $SSBM = $request->post('SSBM','');  //手术编码
        $BFZ = $request->post('BFZ','');    //并发症
        $JC = $request->post('JC','');      //检查
        $JJ = $request->post('JJ','');      //检验
        $CKWX = $request->post('CKWX','');  //参考文献
        $JBMC = $request->post('JBMC','');  //疾病名称
        $JBBM = $request->post('JBBM','');  //疾病编码
        $createStartTime = $request->post('createStartTime','');  //创建开始时间
        $createEndTime = $request->post('createEndTime','');  //创建结束时间
        $updateStartTime = $request->post('updateStartTime','');  //更新开始时间
        $updateEndTime = $request->post('updateEndTime','');  //更新结束时间

        $where = [];
        if (!empty($FLAG)) {
            $where[] = ['FLAG','like',"%".$FLAG."%"];
        }
        if (!empty($KSMC)) {
            $where[] = ['KSMC','like',"%".$KSMC."%"];
        }
        if (!empty($SSMC)) {
            $where[] = ['JBMC','like',"%".$SSMC."%"];
        }
        if (!empty($BM)) {
            $where[] = ['BM','like',"%".$BM."%"];
        }
        if (!empty($SSBM)) {
            $where[] = ['JBBM','like',"%".$SSBM."%"];
        }
        if (!empty($BFZ)) {
            $where[] = ['BFZ','like',"%".$BFZ."%"];
        }
        if (!empty($JC)) {
            $where[] = ['JC','like',"%".$JC."%"];
        }
        if (!empty($JJ)) {
            $where[] = ['JJ','like',"%".$JJ."%"];
        }
        if (!empty($CKWX)) {
            $where[] = ['CKWX','like',"%".$CKWX."%"];
        }
        if (!empty($createStartTime) && !empty($createEndTime)) {
            $startTime = date('Y-m-d H:i:s', strtotime($createStartTime)).' 00:00:00';
            $endTime = date('Y-m-d H:i:s', strtotime($createEndTime)).' 23:59:59';
            $where[] = ['created_at','>=',$startTime];
            $where[] = ['created_at','<=',$endTime];
        } elseif (!empty($createStartTime)) {
            $startTime = date('Y-m-d H:i:s', strtotime($createStartTime)).' 00:00:00';
            $where[] = ['created_at','>=',$startTime];
        } elseif (!empty($createEndTime)) {
            $endTime = date('Y-m-d H:i:s', strtotime($createEndTime)).' 23:59:59';
            $where[] = ['created_at','<=',$endTime];
        }
        if (!empty($updateStartTime) && !empty($updateEndTime)) {
            $startTime = date('Y-m-d H:i:s', strtotime($updateStartTime)).' 00:00:00';
            $endTime = date('Y-m-d H:i:s', strtotime($updateEndTime)).' 23:59:59';
            $where[] = ['updated_at','>=',$startTime];
            $where[] = ['updated_at','<=',$endTime];
        } elseif (!empty($updateStartTime)) {
            $startTime = date('Y-m-d H:i:s', strtotime($updateStartTime)).' 00:00:00';
            $where[] = ['updated_at','>=',$startTime];
        } elseif (!empty($updateEndTime)) {
            $endTime = date('Y-m-d H:i:s', strtotime($updateEndTime)).' 23:59:59';
            $where[] = ['updated_at','<=',$endTime];
        }
        if (!empty($JBMC)) {
            $where[] = ['JBMC','like',"%".$JBMC."%"];
        }
        if (!empty($JBBM)) {
            $where[] = ['JBBM','like',"%".$JBBM."%"];
        }

        // 查询要导出的数据
        $exportData = ['序号','科室','手术名称','别名','手术编码','并发症','检查','检验','参考文献','创建时间','最后修改时间'];
        $field = ['FLAG','KSMC','SSMC','BM','SSBM','BFZ','JC','JJ','CKWX','created_at','updated_at'];
        $data = Surgery::query()->where($where)->get($field)->toArray();
        array_unshift($data,$exportData);

        $csv = new CsvService();
        $csv->filename = $csv->charset('手术', 'UTF-8');

        return $csv->export($data, false);
    }

    /**
     * 列表
     * @param Request $request
     * @return array
     */
    public function surgeryList(Request $request)
    {
        $FLAG = $request->post('FLAG','');  //序号
        $KSMC = $request->post('KSMC','');  //科室
        $SSMC = $request->post('SSMC','');  //手术名称
        $BM = $request->post('BM','');      //别名
        $SSBM = $request->post('SSBM','');  //手术编码
        $BFZ = $request->post('BFZ','');    //并发症
        $JC = $request->post('JC','');      //检查
        $JJ = $request->post('JJ','');      //检验
        $CKWX = $request->post('CKWX','');  //参考文献
        $JBMC = $request->post('JBMC','');  //疾病名称
        $JBBM = $request->post('JBBM','');  //疾病编码
        $createStartTime = $request->post('createStartTime','');  //创建开始时间
        $createEndTime = $request->post('createEndTime','');  //创建结束时间
        $updateStartTime = $request->post('updateStartTime','');  //更新开始时间
        $updateEndTime = $request->post('updateEndTime','');  //更新结束时间
        $page = $request->post('page',1);
        $pageSize = $request->post('page_size',10);

        $where = [];
        if (!empty($FLAG)) {
            $where[] = ['FLAG','like',"%".$FLAG."%"];
        }
        if (!empty($KSMC)) {
            $where[] = ['KSMC','like',"%".$KSMC."%"];
        }
        if (!empty($SSMC)) {
            $where[] = ['JBMC','like',"%".$SSMC."%"];
        }
        if (!empty($BM)) {
            $where[] = ['BM','like',"%".$BM."%"];
        }
        if (!empty($SSBM)) {
            $where[] = ['JBBM','like',"%".$SSBM."%"];
        }
        if (!empty($BFZ)) {
            $where[] = ['BFZ','like',"%".$BFZ."%"];
        }
        if (!empty($JC)) {
            $where[] = ['JC','like',"%".$JC."%"];
        }
        if (!empty($JJ)) {
            $where[] = ['JJ','like',"%".$JJ."%"];
        }
        if (!empty($CKWX)) {
            $where[] = ['CKWX','like',"%".$CKWX."%"];
        }
        if (!empty($createStartTime) && !empty($createEndTime)) {
            $startTime = date('Y-m-d H:i:s', strtotime($createStartTime)).' 00:00:00';
            $endTime = date('Y-m-d H:i:s', strtotime($createEndTime)).' 23:59:59';
            $where[] = ['created_at','>=',$startTime];
            $where[] = ['created_at','<=',$endTime];
        } elseif (!empty($createStartTime)) {
            $startTime = date('Y-m-d H:i:s', strtotime($createStartTime)).' 00:00:00';
            $where[] = ['created_at','>=',$startTime];
        } elseif (!empty($createEndTime)) {
            $endTime = date('Y-m-d H:i:s', strtotime($createEndTime)).' 23:59:59';
            $where[] = ['created_at','<=',$endTime];
        }
        if (!empty($updateStartTime) && !empty($updateEndTime)) {
            $startTime = date('Y-m-d H:i:s', strtotime($updateStartTime)).' 00:00:00';
            $endTime = date('Y-m-d H:i:s', strtotime($updateEndTime)).' 23:59:59';
            $where[] = ['updated_at','>=',$startTime];
            $where[] = ['updated_at','<=',$endTime];
        } elseif (!empty($updateStartTime)) {
            $startTime = date('Y-m-d H:i:s', strtotime($updateStartTime)).' 00:00:00';
            $where[] = ['updated_at','>=',$startTime];
        } elseif (!empty($updateEndTime)) {
            $endTime = date('Y-m-d H:i:s', strtotime($updateEndTime)).' 23:59:59';
            $where[] = ['updated_at','<=',$endTime];
        }
        if (!empty($JBMC)) {
            $where[] = ['JBMC','like',"%".$JBMC."%"];
        }
        if (!empty($JBBM)) {
            $where[] = ['JBBM','like',"%".$JBBM."%"];
        }

        $data = Surgery::query()->where($where)->paginate($pageSize,['*'],'page',$page)->toArray();

        $returnData = [
            'list' => $data['data'] ?? [],
            'count' => $data['total'] ?? 0
        ];

        return ToolsService::returnAdmin(0, $returnData);
    }

    /**
     * 详情
     * @param Request $request
     * @return array
     */
    public function surgeryInfo(Request $request)
    {
        $id = $request->post('id','');
        if (empty($id)) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }

        $data = Surgery::query()->find($id);
        if ($data) {
            $data = $data->toArray();
        } else {
            $data = [];
        }

        return ToolsService::returnAdmin(0, ['data'=>$data]);
    }

    /**
     * 添加
     * @param Request $request
     * @return array
     */
    public function surgeryAdd(Request $request)
    {
        $KSMC = $request->post('KSMC','');  //科室
        $SSMC = $request->post('SSMC','');  //手术名称
        $BM = $request->post('BM','');      //别名
        $SSBM = $request->post('SSBM','');  //手术编码
        $BFZ = $request->post('BFZ','');    //并发症
        $JC = $request->post('JC','');      //检查
        $JJ = $request->post('JJ','');      //检验
        $CKWX = $request->post('CKWX','');  //参考文献
        $JBMC = $request->post('JBMC','');  //疾病名称
        $JBBM = $request->post('JBBM','');  //疾病编码

        if (empty($SSMC) || empty($BM)) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }

        // 查询数据是否存在
        $where = [];
        $where[] = ['KSMC', '=', $KSMC];
        $where[] = ['SSMC', '=', $SSMC];
        $info = Surgery::query()->where($where)->first();
        if (!empty($info)) {
            return ToolsService::returnAdmin(1, '', '该科室下已有此手术名称');
        }

        // 序号（标识）
        $FLAG = '';
//        if (!empty($KSMC)) {
//            $pinyinService = new PinYin2AbbreviationService();
//            $ksSzmArr = $pinyinService->getFirstStr($KSMC);
//            if (!empty($ksSzmArr)) {
//                $FLAG .= implode("",$ksSzmArr).'_';
//            }
//        }
//
//        // 获取首字母
//        $pinyinService = new PinYin2AbbreviationService();
//        $szmArr = $pinyinService->getFirstStr($SSMC);
//        $FLAG .= implode("", $szmArr);

        $insertData = [
            'FLAG' => $FLAG,
            'KSMC' => $KSMC,
            'SSMC' => $SSMC,
            'BM' => $BM,
            'SSBM' => $SSBM,
            'JC' => $JC,
            'JJ' => $JJ,
            'BFZ' => $BFZ,
            'CKWX' => $CKWX,
            'JBMC' => $JBMC,
            'JBBM' => $JBBM
        ];
        $res = Surgery::query()->insert($insertData);
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
    public function surgerySave(Request $request)
    {
        $id = $request->post('id','');      //数据id
        $KSMC = $request->post('KSMC','');  //科室
        $SSMC = $request->post('SSMC','');  //手术名称
        $BM = $request->post('BM','');      //别名
        $SSBM = $request->post('SSBM','');  //手术编码
        $BFZ = $request->post('BFZ','');    //并发症
        $JC = $request->post('JC','');      //检查
        $JJ = $request->post('JJ','');      //检验
        $CKWX = $request->post('CKWX','');  //参考文献
        $JBMC = $request->post('JBMC','');  //疾病名称
        $JBBM = $request->post('JBBM','');  //疾病编码

        if (empty($id) || empty($SSMC) || empty($BM)) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }

        // 查询数据是否存在
        $where = [];
        $where[] = ['id', '!=', $id];
        $where[] = ['KSMC', '=', $KSMC];
        $where[] = ['SSMC', '=', $SSMC];
        $info = Surgery::query()->where($where)->first();
        if (!empty($info)) {
            return ToolsService::returnAdmin(1, '', '该科室下已有此手术名称');
        }

        // 序号（标识）
        $FLAG = '';
//        if (!empty($KSMC)) {
//            $pinyinService = new PinYin2AbbreviationService();
//            $ksSzmArr = $pinyinService->getFirstStr($KSMC);
//            if (!empty($ksSzmArr)) {
//                $FLAG .= implode("",$ksSzmArr).'_';
//            }
//        }
//
//        // 获取首字母
//        $pinyinService = new PinYin2AbbreviationService();
//        $szmArr = $pinyinService->getFirstStr($SSMC);
//        $FLAG .= implode("", $szmArr);

        $insertData = [
            'FLAG' => $FLAG,
            'KSMC' => $KSMC,
            'SSMC' => $SSMC,
            'BM' => $BM,
            'SSBM' => $SSBM,
            'JC' => $JC,
            'JJ' => $JJ,
            'BFZ' => $BFZ,
            'CKWX' => $CKWX,
            'JBMC' => $JBMC,
            'JBBM' => $JBBM
        ];
        $res = Surgery::query()->where('id','=',$id)->update($insertData);
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
    public function surgeryDelete(Request $request)
    {
        $id = $request->post('id','');      //数据id
        if (empty($id)) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }

        Surgery::query()->where('id','=',$id)->delete();
        return ToolsService::returnAdmin(0, [], '操作成功');
    }

    /**
     * 手术管理中同步疾病名称、疾病编码
     * @return array
     */
    public function syncDisease()
    {
        $surgerData = Surgery::query()
            ->where(function($query){
                $query->whereNull('JBMC')->orWhere('JBMC','=','');
            })
            ->get()->toArray();

        if (!empty($surgerData)) {
            foreach ($surgerData as $value) {
                $ssmcList = explode(',',$value['BM']);
                array_unshift($ssmcList,$value['SSMC']);
                if ($ssmcList) {
                    $diseaseInfo = Disease::query()->whereIn('SSMC',$ssmcList)->first();
                    if ($diseaseInfo) {
                        $saveData = ['JBMC'=>$diseaseInfo->JBMC,'JBBM'=>$diseaseInfo->JBBM];
                        Surgery::query()->where('id','=',$value['id'])->update($saveData);
                    }
                }
            }
        }

        return ToolsService::returnAdmin(0, [], '操作成功');
    }

}
