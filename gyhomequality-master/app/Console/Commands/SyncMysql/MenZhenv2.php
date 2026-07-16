<?php

namespace App\Console\Commands\SyncMysql;

use App\Model\Department;
use App\Model\MS_BRDA;
use App\Model\OMR_BL01;
use Illuminate\Console\Command;

class MenZhenv2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:menzhenv2 {startTime?} {endTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '门诊数据同步';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public static $con;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->getConnect();

        $startTime = $this->argument('startTime');
        $endTime = $this->argument('endTime');

        $startTime = empty($startTime) ? date('Y-m-d 00:00:00', strtotime("-1 day", time())) : date('Y-m-d 00:00:00', strtotime($startTime));
        $endTime = empty($endTime) ? date('Y-m-d 23:59:59', strtotime("-1 day", time())) : date('Y-m-d 23:59:59', strtotime($endTime));
        $this->OMR_BL01($startTime, $endTime);//同步门诊病历数据 -- 病历详情: 同步至OMR_BL01
        exit();
    }

    /**
     * 建立数据库连接
     * @return void
     */
    public function getConnect()
    {
        $username = env('ORACLE_USERNAME', 'zdyh');
        $password = env('ORACLE_PASSWORD', 'zdyh');
        $connection = '192.168.52.98';
        $port = env('ORACLE_PORT', '1521');
        $tns = 'storcl';
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');


        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        self::$con = $con;
    }

    /**
     * 同步门诊病历数据 -- 病历详情
     * @param $startTime
     * @param $endTime
     * @return void
     */
    public function OMR_BL01($startTime, $endTime)
    {
        echo "同步门诊数据OMR_BL01-start:" . date('Y-m-d H:i:s') . "\n";
        $s = time();
        $total = 0;
        $bllbMap = config("omrBl01Map.BLLB");
        while (true) {
            $startTimeEnd = date('Y-m-d 23:59:59', strtotime($startTime));
            if ($startTime > $endTime) {
                break;
            }
            $sql = "SELECT A.ID_MEDRECDOC,A.ID_HOSPITAL,A.ID_DS,NA_MECA,A.NA_CREATE,A.NA_DEPT,A.FG_ACTIVE,A.FG_CHECK,
            A.HTML_TEXT,A.ID_ARCHIVES,A.ID_PATIENT,
            to_char(A.CREATE_TIME,'yyyy-mm-dd hh24:mi:ss') AS JLSJ,
            to_char(A.UPDATE_TIME,'yyyy-mm-dd hh24:mi:ss') AS WCSJ
            FROM WHIS_EMR.HI_VIEW_REC_MZ_HTML A
            WHERE A.CREATE_TIME BETWEEN TO_DATE('$startTime','YYYY-MM-DD HH24:MI:SS') AND TO_DATE('$startTimeEnd','YYYY-MM-DD HH24:MI:SS')";
            $data = oci_parse(self::$con, $sql);
            oci_execute($data, OCI_DEFAULT);
            $result = [];
            while ($row = oci_fetch_assoc($data)) {
                $result[] = $row;
            }
            if (!empty($result)) {
                $total += count($result);
                echo $startTime . " - total:" . count($result) . " - " . date('Y-m-d H:i:s') . "\n";
                foreach ($result as $item) {
                    $BLNR_TXT = trim(blobToStr($item['HTML_TEXT']));
                    $BLLB = isset($bllbMap[$item['ID_DS']]) ? $bllbMap[$item['ID_DS']] : 0;
                    $BRID = $item['ID_PATIENT'];

                    //身份证号
                    $msBrdaData = MS_BRDA::query()->where("BRID", '=', $BRID)->get(['SFZH'])->toArray();
                    $SFZH = !empty($msBrdaData[0]) ? $msBrdaData[0]['SFZH'] : '';
                    if (mb_strlen($SFZH) > 14) {
                        $SFZH = desensitize($SFZH, 0, 6, '*') ;
                        $SFZH = desensitize($SFZH,14,2,'*');
                        $SFZH = desensitize($SFZH,17,1,'*');
                    }

                    $upsertData = [
                        'ID_MEDRECDOC' => $item['ID_MEDRECDOC'],        //唯一标识
                        'JZXH'         => $item['ID_HOSPITAL'],         //就诊序号：标识一次就诊的流水号，对应YS MZ JZLS
                        'BLLB'         => $BLLB,                        //病历类别： 一级病历分类，即FREAMWORKCODE,对应
                        'BLMC'         => $item['NA_MECA'],             //病历名称
                        'JLSJ'         => $item['JLSJ'],                //记录时间：医学意义上的记录时间
                        'CJSJ'         => $item['JLSJ'],                //创建时间：系统时间
                        'WCSJ'         => $item['WCSJ'],                //完成时间：系统时间
                        'SXYS'         => $item['NA_CREATE'],           //书写医生：书写病历的医生
                        'SXKS'         => $item['NA_DEPT'],             //书写科室：书写病历的科室
                        'BRKS'         => $item['NA_DEPT'],             //病人科室：病人所在科室
                        'BLZT'         => $item['FG_ACTIVE'],           //病历状态：0书写 1完成 9注销 DICT150
                        'SYBZ'         => $item['FG_CHECK'],            //审阅标志：0草稿 1签名中 2已完成
                        'BLNR_TXT'     => str_replace(' ', '', $BLNR_TXT),                    //病历内容
                        'mzh'          => $item['ID_ARCHIVES'],         // 门诊号，病历号
                        'BRID'         => $BRID,                        //病人ID'
                        'SFZH'         => $SFZH,                        //身份证号
                    ];
                    $formatRes = $this->dataFormtBl01($BLNR_TXT);
                    $upsertData = array_merge($upsertData, $formatRes);
                    OMR_BL01::query()->updateOrInsert(['ID_MEDRECDOC' => $item['ID_MEDRECDOC']], $upsertData);
                }
            }
            $startTime = date('Y-m-d 00:00:00', strtotime($startTime) + 86400);
        }
        $e = time();
        $timeDifference = $e - $s;
        $totalHours = floor($timeDifference / 3600); // 3600秒 = 1小时
        $totalMinutes = floor(($timeDifference % 3600) / 60); // 剩余分钟
        echo "一共同步" . $total . "条数据；用时" . $totalHours . "小时" . $totalMinutes . "分钟" . "\n";
        echo "同步门诊数据OMR_BL01-end:" . date('Y-m-d H:i:s') . "\n";
    }

    public function dataFormtBl01($BLNR)
    {
        $saveData = [];
        if (empty($BLNR)) {
            return [];
        }
        $BLNR = str_replace("：", ":", $BLNR);
        $BLNR = str_replace('  ', '', $BLNR);
        //解析--就诊时间
        preg_match("/就诊时间:(\d{4}-\d{2}-\d{2}\d{2}:\d{2})主诉/", $BLNR, $match);
        if (!empty($match)) {
            $match[1] = str_replace(["{","}"],"",$match[1]);
            $saveData['jzsj'] = date('Y-m-d H:i:00',strtotime($match[1]));
        }

        if (stripos($BLNR,'&lt;西药&gt;') !== false){
            $arr = explode('&lt;西药&gt;',$BLNR);

            // 解析--西药 xy xy_json
            $pattern = '/(\S+)\s+(\d+\.?\d*[\w%]+)\((\d+)\)\s+(qd|tid|q12h|bid|q6h|prn)/u';
            preg_match_all($pattern, $arr[1], $matches, PREG_SET_ORDER);
            if (!empty($matches)) {
                $xy_json = [];
                $xy = [];
                foreach ($matches as $match) {
                    $xy[] = $match[0];
                    $xy_json[] = [
                        'ym' => $match[1],
                        'yl' => $match[2].'('.$match[3].')',
                        'yf' =>'',
                        'pc' => $match[4],
                    ];
                }
                $saveData['xy'] = implode('',$xy);
                $saveData['xy_json'] = json_encode($xy_json,JSON_UNESCAPED_UNICODE);
            }
        }



        // 解析--主诉
        preg_match("/主诉:(.*)现病史:/", $BLNR, $match);
        if (!empty($match)) {
            $saveData['zs'] = str_replace(["{", "}"], "", $match[1]);
        }
        // 解析--现病史
        preg_match("/现病史:(.*)既往史:/", $BLNR, $match);
        if (!empty($match)) {
            $saveData['xbs'] = str_replace(["{", "}"], "", $match[1]);
        }
        // 解析-既往史
        preg_match("/既往史:(.*)个人史/", $BLNR, $match);
        if (!empty($match)) {
            $saveData['jws'] = str_replace(["{", "}"], "", $match[1]);
        }
        // 解析-流行病学史 lxbxs

        // 解析--体格检查
        preg_match("/体格检查:(.*)专科检查/", $BLNR, $match);
        if (!empty($match)) {
            $saveData['tgjc'] = str_replace(["{", "}"], "", $match[1]);
        }

        //解析--辅助检查
        preg_match("/辅助检查:(.*)诊断:/", $BLNR, $match);
        if (!empty($match)) {
            $saveData['fzjc'] = str_replace(["{", "}"], "", $match[1]);
        }

        //解析--初步诊断
        preg_match("/诊断:(.*?)诊疗意见/", $BLNR, $match);
        if (!empty($match)) {
            $match[1] = str_replace(["{","}"],"",$match[1]);
            $saveData['cbzd'] = $match[1];
        }

        //解析--诊疗意见
        if (stripos($BLNR,'诊疗意见:') !== false){
            preg_match("/诊疗意见:(.*)医师签名/", $BLNR, $match);
            if (!empty($match)){
                $saveData['zlyj'] = str_replace(["{","}"],"",$match[1]);
            }
        }
        //解析--提醒

        //解析--门诊号--这个有单独的字段

        //解析--姓名
        preg_match("/姓名:(.*?)性别/", $BLNR, $match);
        if (!empty($match)) {
            $match[1] = str_replace(["{","}"],"",$match[1]);
            $saveData['xm'] = $match[1] ? desensitize($match[1], 1, 1, '*') : '';
        }
        //解析--性别
        preg_match("/性别:(.*?)年龄/", $BLNR, $match);
        if (!empty($match)) {
            $match[1] = str_replace(["{","}"],"",$match[1]);
            $saveData['xb'] = $match[1];
        }
        //解析--科室
        preg_match("/科室:(.*?)病历号/", $BLNR, $match);
        if (!empty($match)) {
            $match[1] = str_replace(["{","}"],"",$match[1]);
            $saveData['ks'] = $match[1];
            $hospital_name =  config('confAdmin.hospital_name');
            $dep_id = Department::query() ->where('hospital_name','=',$hospital_name)
                ->where('MZSY','=','Y')
                ->where('dep_name','=',$saveData['ks'])
                ->value('dep_id');
            if (!empty($dep_id)){
                $saveData['BRKS'] = $dep_id;
            }
        }
        //解析--年龄
        preg_match("/年龄:(.*?)科室/", $BLNR, $match);
        if (!empty($match)) {
            $match[1] = str_replace(["{","}"],"",$match[1]);
            //岁
            preg_match("/(\d+)岁/", $match[1], $year);
            $saveData['nl'] = $year[1] ?? 0;
            // 月
            preg_match("/(\d+)月/", $match[1], $month);
            $saveData['nl1'] = $month[1] ?? 0;

            // 天
            preg_match("/(\d+)天/", $match[1], $day);
            $saveData['nl_day'] = $day[1] ?? 0;

            // 小时
            preg_match("/(\d+)小时/", $match[1], $hour);
            $saveData['nl_hour'] = $hour[1] ?? 0;

            // 分钟
            preg_match("/(\d+)分钟/", $match[1], $minute);
            $saveData['nl_minute'] = $minute[1] ?? 0;
        }

        return $saveData;
    }
}
