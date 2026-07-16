<?php

namespace App\Console\Commands\DataAnalysis;

use App\Model\EMR_BL_BL01;
use App\Model\Setting;
use Illuminate\Console\Command;

class Surgery extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:surgery {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '手术数据解析';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('手术相关数据格式化 - 开始');

        $setName = 'gsh_bl01_ss';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $page = (int)$this->argument('page') ?: 1;
        while (true) {
            $dataList = EMR_BL_BL01::query()
                ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                ->where('BLLB','=',303)
                ->where('BLZT','!=',9)
                ->where('EMR_BL_BL01.BLBH','>',$lastId)
                ->orderBy('EMR_BL_BL01.BLBH')
                ->paginate(100, ['EMR_BL_BL01.BLBH','JZHM','BLMC','BRBH','BRXM','HJNR','MBLB'],'page', $page)
                ->toArray();

            if ($page == 1) {
                echo '数据总条数：'.$dataList['total'].PHP_EOL.'总页数：'.$dataList['last_page'].PHP_EOL;
            } elseif ($page > $dataList['last_page']) {
                // 如果当前页码大于最大页码则结束
                break;
            }
            echo $page.PHP_EOL;
            $page++;

            $bl01Data = $dataList['data'];
            foreach ($bl01Data as $bl01) {
                $lastId = $bl01['BLBH'];
                echo $lastId.PHP_EOL;

//                $surgeryType = $this->getSurgeryType($bl01['HJNR']);
                switch ($bl01['MBLB']) {
                    case 76: // 手术风险评估表
                        $this->ssfxpg($bl01);
                        break;
                    case 75: // 手术安全核查表
                        $this->ssaqhcb($bl01);
                        break;
                    case 8:  // 手术同意书
                        $this->sstys($bl01);
                        break;
                    case 306 : // 手术记录
                        $this->ssjl($bl01);
                        break;
                    case 74 : // 手术记录2
                        $this->ssjl($bl01);
                        break;
                    default:
                        break;
                }
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $lastId]);
        }

        $this->info('手术相关数据解析 - 结束');
    }

    /**
     * 手术记录
     * @param $data
     * @return true
     */
    protected function ssjl($bl01)
    {
        $hjnr = str_replace(':','：',$bl01['HJNR']);

        $insertData['type'] = 4;

        // 提取医院名称
        $arr = explode('手术记录',$hjnr);
        $insertData['hospital'] = '';
        if (!empty($arr[0])) {
            $arr = explode("\n",trim($arr[0]));
            $insertData['hospital'] = !empty($arr[count($arr)-1]) ? trim($arr[count($arr)-1]) : '';
        }

        $insertData['brxm'] = $bl01['BRXM'];    // 患者姓名
        $insertData['brbh'] = $bl01['BRBH'];    // 住院号
        $keyArr = [
            'keshi' => '科室：',
            'ch' => '床号：',
            'sex' => '性别',
            'age' => '年龄',
            'ssrq' => '手术日期：',
            'sssj' => '手术时间：',
            'sqzd' => '术前诊断：',
            'szzd' => '术中诊断：',
            'ssmc' => '手术名称：',
            'mzfs' => '麻醉方法：',
            'sszdz' => '手术指导者：',
            'ssz' => '手术者：',
            'zs' => '助手：',
            'describe' => '手术经过、术中出现的情况及处理：',
            'sszqm' => '手术者签名',
            'jlsj' => '记录时间',
        ];

        foreach ($keyArr as $key => $val) {
            $insertData[$key] = $this->getVal($hjnr,$val);
        }
        $insertData['describe'] = !empty($insertData['describe']) ? $insertData['describe'] : $hjnr;
        EMR_BL_BL01::query()
            ->where('BLBH','=',$bl01['BLBH'])
            ->update(['surgery_content'=>json_encode($insertData,JSON_UNESCAPED_UNICODE)]);

        // 要同步到Es的数据
        $es_params['body'][] = ['update' => ['_index' => 'bl01_2023', '_id' => $bl01['BLBH']]];
        $es_params['body'][] = ['doc' => [
            "surgery_content" => json_encode($insertData, JSON_UNESCAPED_UNICODE)
        ], 'doc_as_upsert' => true];
//        app('es')->bulk($es_params);

        return true;
    }

    /**
     * 手术风险评估表
     * @param $bl01
     * @return true
     */
    protected function ssfxpg($bl01)
    {
        $hjnr = $bl01['HJNR'];

        $insertData['type'] = 1;

        // 提取医院名称
        $arr = explode('手术风险评估表',$hjnr);
        $insertData['hospital'] = '';
        if (!empty($arr[0])) {
            $arr = explode("\n",trim($arr[0]));
            $insertData['hospital'] = !empty($arr[count($arr)-1]) ? trim($arr[count($arr)-1]) : '';
        }

        $insertData['brxm'] = $bl01['BRXM'];    // 患者姓名
        $insertData['brbh'] = $bl01['BRBH'];    // 住院号

        $keyArr = [
            'keshi' => '科室',
            'sex' => '性别',
            'age' => '年龄',
            'sz' => '术者',
            'ssrq' => '手术日期',
            'mzfs' => '麻醉方式',
            'ssfs' => '手术方式',
        ];
        foreach ($keyArr as $key => $val) {
            $insertData[$key] = $this->getVal($hjnr,$val);
        }

        // 手术切口清洁程度
        $ssqkType = ['I类手术切口','II类手术切口','III类手术切口','IV类手术切口'];
        foreach ($ssqkType as $key => $value) {
            $arr = explode($value,$hjnr);
            $arr = !empty($arr[1]) ? explode("\n",$arr[1]) : ['','','','',''];
            $ilssqk[$key]['name'] = $value.trim($arr[1]);
            $ilssqk[$key]['desc'] = trim($arr[2]);
            if (strlen($arr[3]) > 4) {
                $ilssqk[$key]['desc'] .= trim($arr[3]);
            }
            if (strlen($arr[3]) > 4 || !$arr[3]) {
                $ilssqk[$key]['jk'] = $arr[4];
            } else {
                $ilssqk[$key]['jk'] = $arr[3];
            }
        }
        $insertData['ssqk'] = $ilssqk;

        // 麻醉分级（ASA分级）麻醉医师或局麻时手术医师填写
        $mzfjType = ['ASA I'=>'P1','ASA II'=>'P2','ASA III'=>'P3','ASA IV'=>'P4','ASA V'=>'P5','ASA VI'=>'P6'];
        foreach ($mzfjType as $key => $value) {
            $arr = explode($value,$hjnr);
            $arr = !empty($arr[1]) ? explode("\n",$arr[1]) : [];
            $mzfjData[] = ['name'=>$key,'desc'=>$arr[1] ?? '','jk'=>$arr[2] ?? ''];
        }
        $insertData['mzfj'] = $mzfjData;

        // 手术持续时间
        $sscxsjType = ['手术预计在3小时内完成','手术预计超过3小时完成'];
        foreach ($sscxsjType as $value) {
            $arr = explode($value,$hjnr);
            $arr = !empty($arr[1]) ? explode("\n",$arr[1]) : [];
            if (!empty($arr[1])) {
                $sscxsj[] = ['name'=>$value,'jk'=>$arr[1]];
            } else {
                $sscxsj[] = ['name'=>$value,'jk'=>''];
            }
        }
        $insertData['sscxsj'] = $sscxsj;

        // 合计分值
        $arr = explode('合计分值（以上均为单选）',$hjnr);
        $arr = !empty($arr[1]) ? explode("\n",$arr[1]) : [];
        $insertData['hjfz'] = $arr[1] ?? 0;


        // :todo 预防使用抗菌药物、规范备皮 暂无数据


        // 切口愈合
        $arr = explode('切口愈合',$hjnr);
        $arr = !empty($arr[1]) ? explode("\n",$arr[1]) : [];
        $insertData['qkyh'] = ['name'=>$arr[1] ?? '','jk'=>$arr[2] ?? ''];
        // 感染
        $insertData['gr'] = ['name'=>$arr[3] ?? '','jk'=>$arr[4] ?? ''];

        // 手术类别
        $sslbList = ['浅层组织手术','深部组织手术','器官手术','腔隙手术'];
        foreach ($sslbList as $value) {
            $arr = explode($value,$hjnr);
            $arr = !empty($arr[1]) ? explode("\n",$arr[1]) : [];
            $sslbData[] = ['name'=>$value,'desc'=>$arr[1] ?? '','jk'=>$arr[2] ?? ''];
        }
        $insertData['sslb'] = $sslbData;

        // 手术者签名
        $hjnr = str_replace('手术者签名 ：','手术者签名：',$hjnr);
        $hjnr = str_replace('麻醉医师签名：','麻醉医师签名',$hjnr);
        $hjnr = str_replace('麻醉医师签名','麻醉医师签名：',$hjnr);
        $arr = explode('手术者签名：',$hjnr);
        $insertData['sszqm'] = '';
        $insertData['mzysqm'] = '';
        if (!empty($arr[1])) {
            $arr = explode("麻醉医师签名：",$arr[1]);
            $insertData['sszqm'] = !empty($arr[0]) ? trim($arr[0]) : '';
            $insertData['mzysqm'] = '';
            if (!empty($arr[1])) {
                $arr = explode("\n",$arr[1]);
                // 麻醉医师签名
                $insertData['mzysqm'] = !empty($arr[0]) ? trim($arr[0]) : '';
            }
        }

        EMR_BL_BL01::query()
            ->where('BLBH','=',$bl01['BLBH'])
            ->update(['surgery_content'=>json_encode($insertData,JSON_UNESCAPED_UNICODE)]);

        // 要同步到Es的数据
        $es_params['body'][] = ['update' => ['_index' => 'bl01_2023', '_id' => $bl01['BLBH']]];
        $es_params['body'][] = ['doc' => [
            "surgery_content" => json_encode($insertData, JSON_UNESCAPED_UNICODE)
        ], 'doc_as_upsert' => true];
//        app('es')->bulk($es_params);

        return true;
    }

    /**
     * 手术安全核查表
     * @param $data
     * @return true
     */
    protected function ssaqhcb($bl01)
    {
        $hjnr = str_replace(':','：',$bl01['HJNR']);

        $insertData['type'] = 2;

        // 提取医院名称
        $arr = explode('手术安全核查表',$hjnr);
        $insertData['hospital'] = '';
        if (!empty($arr[0])) {
            $arr = explode("\n",trim($arr[0]));
            $insertData['hospital'] = !empty($arr[count($arr)-1]) ? trim($arr[count($arr)-1]) : '';
        }

        $insertData['brxm'] = $bl01['BRXM'];    // 患者姓名
        $insertData['brbh'] = $bl01['BRBH'];    // 住院号

        $keyArr = [
            'keshi' => '科室',
            'sex' => '性别',
            'age' => '年龄',
            'sz' => '术者',
            'ssrq' => '手术日期',
            'mzfs' => '麻醉方式',
            'ssfs' => '手术方式',
        ];
        foreach ($keyArr as $key => $val) {
            $insertData[$key] = $this->getVal($hjnr,$val);
        }

        //************ 麻醉实施前 ************
        $keyArr = [
            'xm_sex_age' => '患者姓名、性别、年龄正确：',
            'ssfsqr' => '手术方式确认：',
            'ssbwybsqr' => '手术部位与标识正确：',
            'sszqty' => '手术知情同意：',
            'mzzqty' => '麻醉知情同意：',
            'mzfsqr' => '麻醉方式确认：',
            'mzsbaqjc' => '麻醉设备安全检查完成：',
            'pfsfwz' => '皮肤是否完整：',
            'sypfzbzq' => '术野皮肤准备正确：',
            'jmtdjlwc' => '静脉通道建立完成：',
            'hzsfygms' => '患者是否有过敏史：',
            'kjywpsjg' => '抗菌药物皮试结果：',
            'sqbx' => '术前备血：',
            'jt' => '假体',
            'tnzrw' => '体内植入物',
            'yxxzl' => '影像学资料',
            'qt' => '其他：',
        ];
        foreach ($keyArr as $key => $val) {
            $mzssq[$key] = $this->getVal($hjnr,$val);
        }
        $insertData['mzssq'] = $mzssq;

        //************ 手术开始前 ************
        $keyArr = [
            'xm_sex_age' => '患者姓名、性别、年龄正确：',
            'ssfsqr' => '手术方式确认：',
            'ssbwybsqr' => '手术方式确认：',
        ];
        foreach ($keyArr as $key => $val) {
            $ssksq[$key] = $this->getVal1($hjnr,$val);
        }

        // 处理 手术医师陈述下的 预计手术时间、预计失血量、手术关注点、其它
        $arr = explode('手术医师陈述：',$hjnr);
        $arr = explode('麻醉医师陈述',$arr[1]);
        $arr = explode("\n",trim($arr[0]));
        $yjsssj = explode('预计手术时间',trim($arr[0]));
        $yjsxy = explode('预计失血量',trim($arr[1]));
        $ssgzd = explode('手术关注点',trim($arr[2]));
        $ssgzd = explode('其它',trim($ssgzd[1]));
        $qt = explode('其它',trim($arr[2]));
        $ssksq['ssyscs'] = [
            'yjsssj' => trim($yjsssj[1]),
            'yjsxy' => trim($yjsxy[1]),
            'ssgzd' => trim($ssgzd[0]),
            'qt' => !empty($qt[1]) ? trim($qt[1]) : '',
        ];

        // 处理 麻醉医师陈述下的 麻醉关注点、其它
        $arr = explode('麻醉医师陈述：',$hjnr);
        $arr = explode('手术护士陈述',$arr[1]);
        $arr = explode("\n",trim($arr[0]));
        $mzgzd = explode('麻醉关注点',$arr[0]);
        $mzgzd = explode('其它',$mzgzd[1]);
        $ssksq['mzyscs'] = [
            'mzgzd' => trim($mzgzd[0]),
            'qt' => !empty($mzgzd[1]) ? trim($mzgzd[1]) : '',
        ];

        // 处理 手术护士陈述下的 物品灭菌合格、仪器设备、术前术中特殊用药情况、其它
        $arr = explode('手术护士陈述：',$hjnr);
        $arr = explode('是否需要相关影像资料',$arr[1] ?? '');
        $arr = explode("\n",trim($arr[0]));
        $wpmjhg = explode('物品灭菌合格',$arr[0]);
        $yqsb = explode('仪器设备',$arr[1] ?? '');
        $sqsztsyyqk = explode('术前术中特殊用药情况',$arr[2] ?? '');
        $qt = explode('其它',$arr[3] ?? '');
        $ssksq['sshscs'] = [
            'wpmjhg' => !empty($wpmjhg[1]) ? trim($wpmjhg[1]) : '',
            'yqsb' => !empty($yqsb[1]) ? trim($yqsb[1]) : '',
            'sqsztsyyqk' => !empty($sqsztsyyqk[1]) ? trim($sqsztsyyqk[1]) : '',
            'qt' => !empty($qt[1]) ? trim($qt[1]) : '',
        ];

        // 是否需要相关影像资料
        $arr = explode('是否需要相关影像资料：',$hjnr);
        $ssksq['xgyxzl'] = '';
        if (!empty($arr[1])) {
            $arr = explode("\n",trim($arr[1]));
            $ssksq['xgyxzl'] = $arr[0];
        }

        // 其它
        $qt = ['',''];
        if (!empty($arr[1])) {
            $qt = explode('其他：',$arr[1]);
        }
        $ssksq['qt'] = '';
        if (!empty($qt[1])) {
            $ssksq['qt'] = trim($qt[1]);
        }

        $insertData['ssksq'] = $ssksq;

        //************ 患者离开手术前 ************
        $keyArr = [
            'xm_sex_age' => '患者姓名、性别、年龄正确：',
            'sjssfsqr' => '实际手术方式确认：',
            'ssyy_sx' => '手术用药、输血的核查：',
            'ssywqd' => '手术用物清点正确：',
            'ssbb' => '手术标本确认：',
            'pfsfwz' => '皮肤是否完整：',
        ];
        foreach ($keyArr as $key => $val) {
            $hzlkssq[$key] = $this->getVal($hjnr,$val);
        }

        // 处理 各种管路下的 中心静脉通路、动脉通路、气管插管、伤口引流□  胃管、尿管、其他
        $arr = explode('各种管路：',$hjnr);
        if (!empty($arr[1])) {
            $arr = explode('患者去向',$arr[1]);
            $arr = explode("\n",trim($arr[0]));
            $zxjmtl = explode('中心静脉通路',$arr[0]);
            if (!empty($arr[1])) {
                $dmtl = explode('动脉通路',$arr[1]);
            }
        }

        if (!empty($dmtl[1])) {
            $dmtl = explode('气管插管',$dmtl[1]);
        }
        if (!empty($arr[2])) {
            $skyl = explode('伤口引流',$arr[2]);
            if (!empty($skyl[1])) {
                $skyl = explode('胃管',$skyl[1]);
            }
        }

        $ng = [];
        if (!empty($arr[3])) {
            $ng = explode('尿管',$arr[3]);
            if (!empty($ng[1])) {
                $ng = explode('其他',$ng[1]);
            }
        }

        $hzlkssq['gzgl'] = [
            'zxjmtl' => !empty($zxjmtl[1]) ? trim($zxjmtl[1]) : '',
            'dmtl' => !empty($dmtl[0]) ? trim($dmtl[0]) : '',
            'qgcg' => !empty($dmtl[1]) ? trim($dmtl[1]) : '',
            'skyl' => !empty($skyl[0]) ? trim($skyl[0]) : '',
            'wg' => !empty($skyl[1]) ? trim($skyl[1]) : '',
            'ng' => !empty($ng[0]) ? trim($ng[0]) : '',
            'qt' => !empty($ng[1]) ? trim($ng[1]) : '',
        ];


        // 处理 患者去向下的 恢复室、病房、ICU 病房、急诊、离院
        $arr = explode('患者去向：',$hjnr);
        $arr = explode('其他：',$arr[1]);
        $hzlkssq['qt'] = '';
        if (!empty($arr[1])) {
            $qt = explode("\n", $arr[1]);
            $hzlkssq['qt'] = trim($qt[0]);
        }
        $arr = explode("\n",trim($arr[0]));
        $hfs = explode('恢复室',$arr[0]);
        if (!empty($hfs[1])) {
            $hfs = explode('病房',$hfs[1]);
        } else {
            $hfs = ['',''];
        }

        $icubf = [];
        if (!empty($arr[1])) {
            $icubf = explode('ICU 病房',$arr[1]);
        }
        if (!empty($icubf[1])) {
            $icubf = explode('急诊',$icubf[1]);
            $ly = explode('离院',$arr[2] ?? '');
        } else {
            $icubf = ['',''];
            $ly = ['',''];
        }

        $hzlkssq['hzqx'] = [
            'hfs' => trim($hfs[0]),
            'bf' => !empty($hfs[1]) ? trim($hfs[1]) : '',
            'icubf' => trim($icubf[0]),
            'jz' => !empty($icubf[1]) ? trim($icubf[1]) : '',
            'ly' => !empty($ly[1]) ? trim($ly[1]) : '',
        ];

        // 手术医师签名
        $arr = explode('手术医师签名：',$hjnr);
        $insertData['ssysqm'] = '';
        if (!empty($arr[1])) {
            $arr = explode("麻醉医师签名",$arr[1]);
            $insertData['ssysqm'] = trim($arr[0]);
        }

        // 麻醉医师签名
        $arr = explode('麻醉医师签名：',$hjnr);
        $insertData['mzysqm'] = '';
        if (!empty($arr[1])) {
            $arr = explode("手术室护士签名",$arr[1]);
            $insertData['mzysqm'] = trim($arr[0]);
        }

        // 麻醉医师签名
        $arr = explode('手术室护士签名：',$hjnr);
        $insertData['ssshsqm'] = '';
        if (!empty($arr[1])) {
            $arr = explode("\n",$arr[1]);
            $insertData['ssshsqm'] = trim($arr[0]);
        }

        $insertData['hzlkssq'] = $hzlkssq;

        EMR_BL_BL01::query()
            ->where('BLBH','=',$bl01['BLBH'])
            ->update(['surgery_content'=>json_encode($insertData,JSON_UNESCAPED_UNICODE)]);

        // 要同步到Es的数据
        $es_params['body'][] = ['update' => ['_index' => 'bl01_2023', '_id' => $bl01['BLBH']]];
        $es_params['body'][] = ['doc' => [
            "surgery_content" => json_encode($insertData, JSON_UNESCAPED_UNICODE)
        ], 'doc_as_upsert' => true];
//        app('es')->bulk($es_params);

        return true;
    }

    /**
     * 手术同意书
     * @param $bl01
     * @return bool
     */
    protected function sstys($bl01)
    {
        $hjnr = str_replace(':','：',$bl01['HJNR']);

        $insertData['type'] = 3;

        // 提取医院名称
        $arr = explode('手术同意书',$hjnr);
        $insertData['hospital'] = '';
        if (!empty($arr[0])) {
            $arr = explode("\n",trim($arr[0]));
            $insertData['hospital'] = !empty($arr[count($arr)-1]) ? trim($arr[count($arr)-1]) : '';
        }

        $insertData['brxm'] = $bl01['BRXM'];    // 患者姓名
        $insertData['brbh'] = $bl01['BRBH'];    // 住院号

        $keyArr = [
            'bdhm' => '表单编号：',
            'keshi' => '科室：',
            'ch' => '床号：',
            'qssj' => '签署时间：',
        ];
        foreach ($keyArr as $key => $val) {
            $insertData[$key] = $this->getVal($hjnr,$val);
        }

        // 病情介绍及治疗建议
        $arr = explode('病情介绍及治疗建议：',$hjnr);
        if (!empty($arr[1])) {
            $arr = explode('手术目的：',$arr[1]);
            $insertData['bqjsjzljy'] = trim($arr[0]);
        } else {
            return false;
        }

        // 手术目的
        $arr = explode('手术目的：',$hjnr);
        if (!empty($arr[1])) {
            $arr = explode('预期效果：',$arr[1]);
            $insertData['ssmd'] = trim($arr[0]);
        }

        // 预期效果
        $arr = explode('预期效果：',$hjnr);
        if (!empty($arr[1])) {
            $arr = explode('手术潜在风险和对策：',$arr[1]);
//            $arr = explode("\n",$arr[0]);
            $insertData['yqxg'] = trim($arr[0]);
        }

        // 手术潜在风险和对策
        $arr = explode('手术潜在风险和对策：',$hjnr);
        if (!empty($arr[1])) {
            $arr = explode('医师陈述：',$arr[1]);
            $insertData['ssqzfxhdc'] = trim($arr[0]);
        }

        // 医师陈述
        $arr = explode('医师陈述：',$hjnr);
        $insertData['yscs']['yscs'] = '';
        if (!empty($arr[1])) {
            $arr = explode('手术医师签名：',$arr[1]);
            $insertData['yscs']['yscs'] = trim($arr[0]);
        }
        $insertData['yscs']['ssysqm'] = '';
        $arr = explode('手术医师签名：',$hjnr);
        if (!empty($arr[1])) {
            $arr = explode('签字时间：',$arr[1]);
            $insertData['yscs']['ssysqm'] = trim($arr[0]);
        }
        $arr = explode('签字时间：',$hjnr);
        $insertData['yscs']['qzsj'] = '';
        if (!empty($arr[1])) {
            $arr = explode('患者知情选择：',$arr[1]);
            $insertData['yscs']['qzsj'] = trim($arr[0]);
        }

        // 患者知情选择
        $arr = explode('患者知情选择：',$hjnr);
        $insertData['hzzqxz']['hzzq'] = '';
        if (!empty($arr[1])) {
            $arr = explode('患者签名：',$arr[1]);
            $insertData['hzzqxz']['hzzq'] = trim($arr[0]);
        }
        $arr = explode('患者签名：',$hjnr);
        $insertData['hzzqxz']['hzqm'] = '';
        if (!empty($arr[1])) {
            $arr = explode('如果患者无法签署',$arr[1]);
            $insertData['hzzqxz']['hzqm'] = trim($arr[0]);
        }
        $arr = explode('法定监护人签名：',$hjnr);
        $insertData['hzzqxz']['jhr'] = '';
        if (!empty($arr[1])) {
            $arr = explode('与患者关系：',$arr[1]);
            $insertData['hzzqxz']['jhr'] = trim($arr[0]);
        }
        $arr = explode('与患者关系：',$hjnr);
        $insertData['hzzqxz']['yhzgx'] = '';

        if (!empty($arr[1])) {
            $arr = explode('签字时间：',$arr[1]);
            $insertData['hzzqxz']['yhzgx'] = trim($arr[0]);
        }
        $insertData['hzzqxz']['qzsj'] = '';
        if (!empty($arr[1])) {
            $qzsj = explode("\n",$arr[1]);
            if (!empty($qzsj[0])) {
                $insertData['hzzqxz']['qzsj'] = $qzsj[0];
            }
        }

        EMR_BL_BL01::query()
            ->where('BLBH','=',$bl01['BLBH'])
            ->update(['surgery_content'=>json_encode($insertData,JSON_UNESCAPED_UNICODE)]);

        // 要同步到Es的数据
        $es_params['body'][] = ['update' => ['_index' => 'bl01_2023', '_id' => $bl01['BLBH']]];
        $es_params['body'][] = ['doc' => [
            "surgery_content" => json_encode($insertData, JSON_UNESCAPED_UNICODE)
        ], 'doc_as_upsert' => true];
//        app('es')->bulk($es_params);

        return true;
    }

    /**
     * 数据提取
     * @param $hjnr
     * @param $key
     * @return string
     */
    protected function getVal($hjnr,$key)
    {
        $returnData = '';
        $arr = explode($key,$hjnr);
        if (!empty($arr[1])) {
            if ($key == '手术经过、术中出现的情况及处理：') {
                $ssjg = explode('手术者签名',$arr[1]);
                $returnData = trim($ssjg[0]);
            } elseif($key == '体内植入物') {
                $newArr = explode("\n",$arr[1]);
                $newArr = explode('影像学资料',$newArr[0]);
                return $newArr[0];
            } else {
                $newArr = explode("\n",$arr[1]);
                if ($key == '其他：') {
                    $returnData = trim($newArr[0]);
                } elseif (!empty(trim($newArr[0]))) {
                    $returnData = trim($newArr[0]);
                } elseif (!empty($newArr[1])) {
                    $returnData = trim($newArr[1]);
                }
            }
        }

        return $returnData;
    }

    protected function getVal1($hjnr,$key)
    {
        $returnData = '';
        $arr = explode($key,$hjnr);
        if (!empty($arr[2])) {
            $newArr = explode("\n",$arr[2]);
            if (!empty(trim($newArr[0]))) {
                $returnData = trim($newArr[0]);
            } elseif (!empty($newArr[1])) {
                $returnData = trim($newArr[1]);
            }
        }

        return $returnData;
    }

    /**
     * 匹配出手术类型
     * @param $hjnr
     * @return string
     */
    protected function getSurgeryType($hjnr)
    {
        $surgeryType = '';

        if (stripos($hjnr,'手术风险评估表')) {
            $surgeryType = '手术风险评估表';
        } elseif (stripos($hjnr,'手术安全核查表')) {
            $surgeryType = '手术安全核查表';
        } elseif (stripos($hjnr,'手术同意书')) {
            $surgeryType = '手术同意书';
        } elseif (stripos($hjnr,'手术记录')) {
            $surgeryType = '手术记录';
        }

        return $surgeryType;
    }


}
