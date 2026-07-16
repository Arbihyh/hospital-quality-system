<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ExportData;
use App\Http\Controllers\Controller;
use App\Http\Service\PinYin2AbbreviationService;
use App\Imports\DiseaseImport;
use App\Model\Disease;
use App\Model\Surgery;
use App\Services\CsvService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class DiseaseController extends Controller
{

    /**
     * 模本导出
     * @return string|null
     */
    public function templateExport()
    {
        // 查询要导出的数据
        $title = ['科室','疾病名称（必填）','别名（必填 多个别名用英文逗号“,”隔开）','疾病编码','鉴别诊断','症状','体征','药品','治疗','检查','检验','并发症','参考文献','手术名称','手术编码'];
        $exportData = [];

        return Excel::download(new ExportData($title,$exportData), '疾病导入模板.xlsx');
//        $csv = new CsvService();
//        $csv->filename = $csv->charset('疾病导入模板', 'UTF-8');
//        return $csv->export($exportData, false);
    }

    /**
     * 导入
     * @param Request $request
     * @return array
     */
    public function diseaseImport(Request $request)
    {
        $file = $request->file('file','');
//        $file = 'disease.xlsx';
        if (empty($file)) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }
        if ($file->getSize() > (4*1024*1024)){
            return ToolsService::returnData(0,'','文件大小超过限制');
        }

        Excel::import(new DiseaseImport(), $file, 'local');

        $data = [];
        return ToolsService::returnAdmin(0, $data);
    }

    /**
     * 导出
     * @param Request $request
     * @return string|null
     */
    public function diseaseExport(Request $request)
    {
        $FLAG = $request->post('FLAG','');  //序号
        $KSMC = $request->post('KSMC','');  //科室
        $JBMC = $request->post('JBMC','');  //疾病名称
        $BM = $request->post('BM','');      //别名
        $JBBM = $request->post('JBBM','');  //疾病编码
        $JBZD = $request->post('JBZD','');  //鉴别诊断
        $ZZ = $request->post('ZZ','');      //症状
        $TZ = $request->post('TZ','');      //体征
        $YP = $request->post('YP','');      //药品
        $ZL = $request->post('ZL','');      //治疗
        $JC = $request->post('JC','');      //检查
        $JJ = $request->post('JJ','');      //检验
        $BFZ = $request->post('BFZ','');    //并发症
        $CKWX = $request->post('CKWX','');  //参考文献
        $SSMC = $request->post('SSMC','');  //手术名称
        $SSBM = $request->post('SSBM','');  //手术编码
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
        if (!empty($JBMC)) {
            $where[] = ['JBMC','like',"%".$JBMC."%"];
        }
        if (!empty($BM)) {
            $where[] = ['BM','like',"%".$BM."%"];
        }
        if (!empty($JBBM)) {
            $where[] = ['JBBM','like',"%".$JBBM."%"];
        }
        if (!empty($JBZD)) {
            $where[] = ['JBZD','like',"%".$JBZD."%"];
        }
        if (!empty($ZZ)) {
            $where[] = ['ZZ','like',"%".$ZZ."%"];
        }
        if (!empty($TZ)) {
            $where[] = ['TZ','like',"%".$TZ."%"];
        }
        if (!empty($YP)) {
            $where[] = ['YP','like',"%".$YP."%"];
        }
        if (!empty($ZL)) {
            $where[] = ['ZL','like',"%".$ZL."%"];
        }
        if (!empty($JC)) {
            $where[] = ['JC','like',"%".$JC."%"];
        }
        if (!empty($JJ)) {
            $where[] = ['JJ','like',"%".$JJ."%"];
        }
        if (!empty($BFZ)) {
            $where[] = ['BFZ','like',"%".$BFZ."%"];
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
        if (!empty($SSMC)) {
            $where[] = ['SSMC','like',"%".$SSMC."%"];
        }
        if (!empty($SSBM)) {
            $where[] = ['SSBM','like',"%".$SSBM."%"];
        }

        // 查询要导出的数据
        $exportData = ['序号','科室','疾病名称','别名','疾病编码','鉴别诊断','症状','体征','药品','治疗','检查','检验','并发症','参考文献','手术名称','手术编码','创建时间','最后修改时间'];
        $field = ['FLAG','KSMC','JBMC','BM','JBBM','JBZD','ZZ','TZ','YP','ZL','JC','JJ','BFZ','CKWX','SSMC','SSBM','created_at','updated_at'];
        $data = Disease::query()->where($where)->get($field)->toArray();
        array_unshift($data,$exportData);

        $csv = new CsvService();
        $csv->filename = $csv->charset('疾病', 'UTF-8');

        return $csv->export($data, false);
    }

    /**
     * 列表
     * @param Request $request
     * @return array
     */
    public function diseaseList(Request $request)
    {
        $FLAG = $request->post('FLAG','');  //序号
        $KSMC = $request->post('KSMC','');  //科室
        $JBMC = $request->post('JBMC','');  //疾病名称
        $BM = $request->post('BM','');      //别名
        $JBBM = $request->post('JBBM','');  //疾病编码
        $JBZD = $request->post('JBZD','');  //鉴别诊断
        $ZZ = $request->post('ZZ','');      //症状
        $TZ = $request->post('TZ','');      //体征
        $YP = $request->post('YP','');      //药品
        $ZL = $request->post('ZL','');      //治疗
        $JC = $request->post('JC','');      //检查
        $JJ = $request->post('JJ','');      //检验
        $BFZ = $request->post('BFZ','');    //并发症
        $CKWX = $request->post('CKWX','');  //参考文献
        $SSMC = $request->post('SSMC','');  //手术名称
        $SSBM = $request->post('SSBM','');  //手术编码
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
        if (!empty($JBMC)) {
            $where[] = ['JBMC','like',"%".$JBMC."%"];
        }
        if (!empty($BM)) {
            $where[] = ['BM','like',"%".$BM."%"];
        }
        if (!empty($JBBM)) {
            $where[] = ['JBBM','like',"%".$JBBM."%"];
        }
        if (!empty($JBZD)) {
            $where[] = ['JBZD','like',"%".$JBZD."%"];
        }
        if (!empty($ZZ)) {
            $where[] = ['ZZ','like',"%".$ZZ."%"];
        }
        if (!empty($TZ)) {
            $where[] = ['TZ','like',"%".$TZ."%"];
        }
        if (!empty($YP)) {
            $where[] = ['YP','like',"%".$YP."%"];
        }
        if (!empty($ZL)) {
            $where[] = ['ZL','like',"%".$ZL."%"];
        }
        if (!empty($JC)) {
            $where[] = ['JC','like',"%".$JC."%"];
        }
        if (!empty($JJ)) {
            $where[] = ['JJ','like',"%".$JJ."%"];
        }
        if (!empty($BFZ)) {
            $where[] = ['BFZ','like',"%".$BFZ."%"];
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
        if (!empty($SSMC)) {
            $where[] = ['SSMC','like',"%".$SSMC."%"];
        }
        if (!empty($SSBM)) {
            $where[] = ['SSBM','like',"%".$SSBM."%"];
        }

        $data = Disease::query()->where($where)->paginate($pageSize,['*'],'page',$page)->toArray();

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
    public function diseaseInfo(Request $request)
    {
        $id = $request->post('id','');
        if (empty($id)) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }

        $data = Disease::query()->find($id);
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
    public function diseaseAdd(Request $request)
    {
        $KSMC = $request->post('KSMC','');  //科室
        $JBMC = $request->post('JBMC','');  //疾病名称
        $BM = $request->post('BM','');      //别名
        $JBBM = $request->post('JBBM','');  //疾病编码
        $JBZD = $request->post('JBZD','');  //鉴别诊断
        $ZZ = $request->post('ZZ','');      //症状
        $TZ = $request->post('TZ','');      //体征
        $YP = $request->post('YP','');      //药品
        $ZL = $request->post('ZL','');      //治疗
        $JC = $request->post('JC','');      //检查
        $JJ = $request->post('JJ','');      //检验
        $BFZ = $request->post('BFZ','');    //并发症
        $CKWX = $request->post('CKWX','');  //参考文献
        $SSMC = $request->post('SSMC','');  //手术名称
        $SSBM = $request->post('SSBM','');  //手术编码

        if (empty($JBMC) || empty($BM)) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }

        // 查询数据是否存在
        $where = [];
        $where[] = ['KSMC', '=', $KSMC];
        $where[] = ['JBMC', '=', $JBMC];
        $info = Disease::query()->where($where)->first();
        if (!empty($info)) {
            return ToolsService::returnAdmin(1, '', '该科室下已有此疾病名称');
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
//        $szmArr = $pinyinService->getFirstStr($JBMC);
//        $FLAG .= implode("", $szmArr);

        $insertData = [
            'FLAG' => $FLAG,
            'KSMC' => $KSMC,
            'JBMC' => $JBMC,
            'BM' => $BM,
            'JBBM' => $JBBM,
            'JBZD' => $JBZD,
            'ZZ' => $ZZ,
            'TZ' => $TZ,
            'YP' => $YP,
            'ZL' => $ZL,
            'JC' => $JC,
            'JJ' => $JJ,
            'BFZ' => $BFZ,
            'CKWX' => $CKWX,
            'SSMC' => $SSMC,
            'SSBM' => $SSBM,
        ];
        $res = Disease::query()->insert($insertData);
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
    public function diseaseSave(Request $request)
    {
        $id = $request->post('id','');      //数据id
        $KSMC = $request->post('KSMC','');  //科室
        $JBMC = $request->post('JBMC','');  //疾病名称
        $BM = $request->post('BM','');      //别名
        $JBBM = $request->post('JBBM','');  //疾病编码
        $JBZD = $request->post('JBZD','');  //鉴别诊断
        $ZZ = $request->post('ZZ','');      //症状
        $TZ = $request->post('TZ','');      //体征
        $YP = $request->post('YP','');      //药品
        $ZL = $request->post('ZL','');      //治疗
        $JC = $request->post('JC','');      //检查
        $JJ = $request->post('JJ','');      //检验
        $BFZ = $request->post('BFZ','');    //并发症
        $CKWX = $request->post('CKWX','');  //参考文献
        $SSMC = $request->post('SSMC','');  //手术名称
        $SSBM = $request->post('SSBM','');  //手术编码

        if (empty($id) || empty($JBMC) || empty($BM)) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }

        // 查询数据是否存在
        $where = [];
        $where[] = ['id', '!=', $id];
        $where[] = ['KSMC', '=', $KSMC];
        $where[] = ['JBMC', '=', $JBMC];
        $info = Disease::query()->where($where)->first();
        if (!empty($info)) {
            return ToolsService::returnAdmin(1, '', '该科室下已有此疾病名称');
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
//        $szmArr = $pinyinService->getFirstStr($JBMC);
//        $FLAG .= implode("", $szmArr);

        $insertData = [
            'FLAG' => $FLAG,
            'KSMC' => $KSMC,
            'JBMC' => $JBMC,
            'BM' => $BM,
            'JBBM' => $JBBM,
            'JBZD' => $JBZD,
            'ZZ' => $ZZ,
            'TZ' => $TZ,
            'YP' => $YP,
            'ZL' => $ZL,
            'JC' => $JC,
            'JJ' => $JJ,
            'BFZ' => $BFZ,
            'CKWX' => $CKWX,
            'SSMC' => $SSMC,
            'SSBM' => $SSBM,
        ];
        $res = Disease::query()->where('id','=',$id)->update($insertData);
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
    public function diseaseDelete(Request $request)
    {
        $id = $request->post('id','');      //数据id
        if (empty($id)) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }

        Disease::query()->where('id','=',$id)->delete();
        return ToolsService::returnAdmin(0, [], '操作成功');
    }

    /**
     * 疾病管理中同步手术名称、手术编码
     * @return array
     */
    public function syncSurgery()
    {
        $diseaseData = Disease::query()
            ->where(function($query){
                $query->whereNull('SSMC')->orWhere('SSMC','=','');
            })
            ->get()->toArray();

        if (!empty($diseaseData)) {
            foreach ($diseaseData as $value) {
                $jbmcList = explode(',',$value['BM']);
                array_unshift($jbmcList,$value['JBMC']);

                if ($jbmcList) {
                    $surgeryInfo = Surgery::query()->whereIn('JBMC',$jbmcList)->first();
                    if ($surgeryInfo) {
                        $saveData = ['SSMC'=>$surgeryInfo->SSMC,'SSBM'=>$surgeryInfo->SSBM];
                        Disease::query()->where('id','=',$value['id'])->update($saveData);
                    }
                }
            }
        }

        return ToolsService::returnAdmin(0, [], '操作成功');
    }


}
