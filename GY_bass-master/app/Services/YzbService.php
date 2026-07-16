<?php

namespace App\Services;
use App\Model\Department;
use App\Model\Staff;

class YzbService
{
    public function getSerachAllKeyValue()
    {
        $serachAllKey = [
            'MD_RYQK' => ['有','临床未确定','情况不明','无'],
            'OD_RYQK' => ['有','临床未确定','情况不明','无'],
            'MO_OPE_LEVEL' => [1,2,3,4],
            'MO_SSPB' => [1,2,3,4,5],
            'MO_OPE_TYPE' => [1,2,3],
            'SO_OPE_LEVEL' => [1,2,3,4],
            'SO_SSPB' => [1,2,3,4,5],
            'SO_OPE_TYPE' => [1,2,3],
            'MO_SO_OPE_LEVEL' => [1,2,3,4],
            'MO_SO_SSPB' => [1,2,3],
            'MO_SO_OPE_TYPE' => [1,2,3],
            'MO_SO_RJSS' => ['是','否'],
            'AEM01C' => [1,2,3,4,5,9],
            'AAB06C' => [1,2,3,9]
        ];

        return $serachAllKey;
    }

    /**
     * 获取医生搜索 搜索条件数据
     * @return array
     */
    public function getYzSerachSelectData()
    {
        // 获取科室
        $departmentList = Department::departmentList();

        // 员工
        $staffData = Staff::query()->pluck('name','name as id')->toArray();

        $selectList = [
            ['key'=>'YZMC','name'=>'医嘱名称','type'=>'input','value'=>[]],
            ['key'=>'KZKS','name'=>'开嘱科室','type'=>'select','value'=>$departmentList],
            ['key'=>'YZQX','name'=>'医嘱期效','type'=>'select','value'=>[1=>'长期医嘱',2=>'临时医嘱']],
            ['key'=>'YYSX','name'=>'用药属性','type'=>'select','value'=>[1=>'长期用药',2=>'临时用药',3=>'急诊用药',4=>'出院带药']],
            ['key'=>'XMLB','name'=>'项目类别','type'=>'select','value'=>[1=>'药品',2=>'诊疗',3=>'费用',4=>'材料',5=>'草药方',6=>'组套',7=>'特殊',9=>'嘱托']],
            ['key'=>'BRKS','name'=>'病人科室','type'=>'select','value'=>$departmentList],
            ['key'=>'AAA28','name'=>'病案号','type'=>'input','value'=>[]],
            // ['key'=>'AAA01','name'=>'姓名','type'=>'input','value'=>[]],

            ['key'=>'MD_ICD10_NAME','name'=>'主要诊断名称','type'=>'input','value'=>[]],
            ['key'=>'MD_ICD10_ID1','name'=>'主要诊断编码','type'=>'input','value'=>[]],
            ['key'=>'MD_RYQK','name'=>'主要诊断入院情况','type'=>'select','value'=>['all'=>'全部','有'=>'有','临床未确定'=>'临床未确定','情况不明'=>'情况不明','无'=>'无']],
            // ['key'=>'OD_ICD10_NAME','name'=>'其他诊断名称','type'=>'input','value'=>[]],
            // ['key'=>'OD_ICD10_ID1','name'=>'其他诊断编码','type'=>'input','value'=>[]],
            // ['key'=>'OD_RYQK','name'=>'其他诊断入院情况','type'=>'select','value'=>['all'=>'全部','有'=>'有','临床未确定'=>'临床未确定','情况不明'=>'情况不明','无'=>'无']],

            // ['key'=>'PMI_ABF01N','name'=>'病理诊断名称','type'=>'input','value'=>[]],
            // ['key'=>'PMI_ABF01C','name'=>'病理诊断编码','type'=>'input','value'=>[]],

            ['key'=>'MO_ICD9_NAME','name'=>'主要手术名称','type'=>'input','value'=>[]],
            ['key'=>'MO_ICD9_ID1','name'=>'主要手术编码','type'=>'input','value'=>[]],
            ['key'=>'MO_OPE_LEVEL','name'=>'主要手术级别','type'=>'select','value'=>['all'=>'全部',1=>'一级手术',2=>'二级手术',3=>'三级手术',4=>'四级手术']],
            // ['key'=>'MO_SSPB','name'=>'主要手术判别','type'=>'select','value'=>['all'=>'全部',1=>'手术',2=>'诊断操作',3=>'治疗操作',4=>'介入治疗',5=>'空']],
            // ['key'=>'MO_OPE_TYPE','name'=>'主要手术类型','type'=>'select','value'=>['all'=>'全部',1=>'择期手术',2=>'急诊手术',3=>'限期手术']],

            // ['key'=>'SO_ICD9_NAME','name'=>'其他手术名称','type'=>'input','value'=>[]],
            // ['key'=>'SO_ICD9_ID1','name'=>'其他手术编码','type'=>'input','value'=>[]],
            // ['key'=>'SO_OPE_LEVEL','name'=>'其他手术手术级别','type'=>'select','value'=>['all'=>'全部',1=>'一级手术',2=>'二级手术',3=>'三级手术',4=>'四级手术']],
            // ['key'=>'SO_SSPB','name'=>'其他手术判别','type'=>'select','value'=>['all'=>'全部',1=>'手术',2=>'诊断操作',3=>'治疗操作',4=>'介入治疗',5=>'空']],
            // ['key'=>'SO_OPE_TYPE','name'=>'其他手术类型','type'=>'select','value'=>['all'=>'全部',1=>'择期手术',2=>'急诊手术',3=>'限期手术']],

            ['key'=>'MO_SO_ICD9_NAME','name'=>'手术名称','type'=>'input','value'=>[]],
            ['key'=>'MO_SO_ICD9_ID1','name'=>'手术编码','type'=>'input','value'=>[]],
            ['key'=>'MO_SO_OPE_LEVEL','name'=>'手术级别','type'=>'select','value'=>['all'=>'全部',1=>'一级手术',2=>'二级手术',3=>'三级手术',4=>'四级手术']],
            // ['key'=>'MO_SO_SSPB','name'=>'手术判别','type'=>'select','value'=>['all'=>'全部',1=>'手术',2=>'诊断操作',3=>'治疗操作',4=>'介入治疗',5=>'空']],
            // ['key'=>'MO_SO_OPE_TYPE','name'=>'手术类型','type'=>'select','value'=>['all'=>'全部',1=>'择期手术',2=>'急诊手术',3=>'限期手术']],
            // ['key'=>'MO_SO_RJSS','name'=>'是否为日间手术','type'=>'select','value'=>['all'=>'全部','是'=>'是','否'=>'否']],

            ['key'=>'AEM01C','name'=>'离院方式','type'=>'select','value'=>['all'=>'全部',1=>'医嘱离院',2=>'医嘱转院',3=>'医嘱转社区卫生服务机构/乡镇卫生院',4=>'非医嘱离院',5=>'死亡',9=>'其他']],
            ['key'=>'AAB06C','name'=>'入院途径','type'=>'select','value'=>['all'=>'全部',1=>'急诊',2=>'门诊',3=>'其他医疗机构转入',9=>'其他']],
            ['key'=>'PMI_ABA01N','name'=>'门（急）诊诊断名称','type'=>'input','value'=>[]],
            ['key'=>'PMI_ABA01C','name'=>'门（急）诊诊断编码','type'=>'input','value'=>[]],
            ['key'=>'AAD01C','name'=>'转科科室','type'=>'select','value'=>$departmentList],
            ['key'=>'AAB02C','name'=>'入院科室','type'=>'select','value'=>$departmentList],
            ['key'=>'AAC11N','name'=>'出院科室','type'=>'select','value'=>$departmentList],
            // ['key'=>'AEE10','name'=>'责任护士','type'=>'select','value'=>$staffData],
        ];

        return $selectList;
    }

    /**
     * 医嘱搜索
     * @param $must
     * @param $should
     * @param $notMust
     * @return array
     */
    public function yzSerach($page, $pageSize, $must=[], $should=[], $notMust=[])
    {
        $yzSerachService = new ElasticsearchService('yz_serach_2023');

        if (!$should) {
            $params = $yzSerachService->clearMust()
                ->queryByMustBatch($must)
                ->queryByMustNotBatch($notMust)
                ->paginate($page, $pageSize)
                ->trackTotalHits()
                ->getParams();
        } else {
            $params = $yzSerachService->clearMust()
                ->queryByMustBatch($must)
                ->queryByShouldBatch($should)
                ->queryByMustNotBatch($notMust)
                ->paginate($page, $pageSize)
                ->minimumShouldMatch(1)
                ->trackTotalHits()
                ->getParams();
        }
        $params['body']['sort'] = [['AAC01' => 'desc']];
        $restful = app('es')->search($params);
        $data = $yzSerachService->getDataByEs($restful);

        return $data;
    }

}

