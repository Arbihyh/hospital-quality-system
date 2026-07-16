<?php

namespace App\Console\Commands;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\ErrorRule;
use App\Model\MainOperation;
use App\Model\PatientHospitalInfo;
use App\Model\PatientInfo;
use App\Services\BasyQualityService;
use App\Services\HomeSzService;
use App\Services\ShizhongDataService;
use Illuminate\Console\Command;

class DowData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sh:data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '编码员批量质控';

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
        $this->info('跑数据 - 开始');
        $startTime = '2023-01-01';
        $endTime = '2024-01-31';

        $blxg = EMR_BL_BLXG::query()->where('HJNR', 'like', '%术前多学科讨论意见%')
            //->where('XGSJ')
            ->whereBetween('XGSJ', [$startTime . ' 00:00:00', $endTime . ' 23:59:59'])
            ->get();


//        $patientData = PatientInfo::query()->select(['AAA28', 'AAC01', 'AAC11C','MED_REC_ID'])
//            ->whereBetween('AAC01', [$startTime . ' 00:00:00', $endTime . ' 23:59:59'])
//            ->orderBy('AAC01')
//            ->get()
//            ->toArray();

//        $patientHospitalList = PatientHospitalInfo::query()->whereIn('AAA28', $zyhArr)
//            //->where('AAB02C', 201)
//            ->get();



        $data = [];

        $num = 0;
        $data[] = ['住院号',
            '出院日期',
            '出院科室',
            '手术操作名称',
            '术者姓名',
            '手术级别',
            '手术判别'
        ];

        foreach ($blxg as $k => $blxgItem) {


            $bl01 = EMR_BL_BL01::query()->where('BLBH', $blxgItem['BLBH'])->first();


            $patientData = PatientInfo::query()->select(['AAA28', 'AAC01', 'AAC11C','AAC11N', 'MED_REC_ID'])
                ->where('MED_REC_ID', $bl01['JZHM'])
                //->whereBetween('AAC01', [$startTime . ' 00:00:00', $endTime . ' 23:59:59'])
                ->orderBy('AAC01')
                ->first();

            if(empty($patientData)){
                continue;
            }

            var_dump($patientData->MED_REC_ID . '-----' . date('Y-m-d H:i:s'));

            $man = MainOperation::query()->where('AAA28', $bl01['JZHM'])->first();


//            PatientHospitalInfo::query()->where('AAA28', $bl01['JZHM'])->first();
            $num++;

            $data[$num]['AAA28'] = $patientData->AAA28;
            $data[$num]['AAC01'] = $patientData->AAC01;
            $data[$num]['AAC11N'] = $patientData->AAC11N;

            if (empty($man)) {
                $data[$num]['ICD9_NAME'] = '';
                $data[$num]['OPE_MAN_NAME'] = '';
                $data[$num]['OPE_LEVEL'] = '';
                $data[$num]['SSPB'] = '';
            } else {
                $data[$num]['ICD9_NAME'] = $man->ICD9_NAME;
                $data[$num]['OPE_MAN_NAME'] = $man->OPE_MAN_NAME;
                $data[$num]['OPE_LEVEL'] = $man->OPE_LEVEL;
                $data[$num]['SSPB'] = $man->SSPB;
            }
        }



        $csv = new CsvService();
        $csv->filename = $csv->charset(  'blxg-data', 'UTF-8');
        return $csv->export($data, true);


        $this->info('编码员质控 - 完毕');
    }


    public function export($data, $is_download = false)
    {
        if (!is_array($data)) return;
        $string = '';
        $new = array();
        foreach ($data as $k => $v) {
            foreach ($v as $xk => $xv) {
                $xv = $this->utfToGbk($xv);
                $v[$xk] = str_replace(',', '', $xv);
            }
            $new[] = implode(',', $v);
        }
        if (!empty($new)) {
            $string = implode("\n", $new);
        }
        if ($is_download) {
            return chr(0xEF) . chr(0xBB) . chr(0xBF) . $string;
        } else {
//            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
//            header("Content-Disposition: attachment;filename={$this->filename}.csv");
//            //添加boom,防止excel打开乱码
////            echo chr(0xEF).chr(0xBB).chr(0xBF);
//            echo $string;exit;

            $fileHandle = fopen(storage_path().'/bl_data.csv', 'w');
            if (!$fileHandle) {
                die("无法打开文件");
            }

// 写入CSV文件头部信息
            foreach ($data as $row => $columns) {
                if (is_array($columns)) {
                    fputcsv($fileHandle, $columns);
                } else {
                    break; // 只需写入第一行为头部信息
                }
            }

// 关闭文件
            fclose($fileHandle);
        }
    }

    function utfToGbk($data)
    {
        return iconv('utf-8', 'GBK', $data);
    }

    public
    function blobToStr($blob = null)
    {
        $str = '';
        if (!is_object($blob)) {
            return $blob;
        }
        if (!empty($blob)) {
            $text = $blob->load();
            $blob->free();
            $mde = mb_detect_encoding($text, array("ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5'));
            if ($mde) {
                $str = mb_convert_encoding($text, 'utf-8', $mde);
            }
        }

        return $str;
    }

    /**
     * 导出csv
     * @param array $data 导出的数据
     * @param array $headList 头部文字描述
     * @param string $fileName 文件名
     */
    function csvExport($data, $headList, $fileName)
    {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $fileName . '.csv"');
        header('Cache-Control: max-age=0');

        //$fp = fopen('php://output', 'a');

        $filename = 'data_2024.csv';
        $fp = fopen($filename, 'w');

        foreach ($headList as $key => $value) {
            $headList[$key] = iconv('utf-8', 'gbk', $value);
        }

        fputcsv($fp, $headList);
        $num = 0;
        $limit = 100000;

        $count = count($data);
        for ($i = 0; $i < $count; $i++) {
            $num++;
            if ($limit == $num) {
                ob_flush();
                flush();
                $num = 0;
            }
            $row = $data[$i];
            foreach ($row as $key => $value) {
                $row[$key] = iconv('utf-8', 'gbk', $value);
            }
            fputcsv($fp, $row);
        }
    }


}



class CsvService
{
    public $filename;

    public function export($data, $is_download = false)
    {
        if (!is_array($data)) return;
        $string = '';
        $new = array();
        foreach ($data as $k => $v) {
            foreach ($v as $xk => $xv) {
                $xv = $this->utfToGbk($xv);
                $v[$xk] = str_replace(',', '', $xv);
            }
            $new[] = implode(',', $v);
        }
        if (!empty($new)) {
            $string = implode("\n", $new);
        }
        if ($is_download) {
            return chr(0xEF) . chr(0xBB) . chr(0xBF) . $string;
        } else {
            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment;filename={$this->filename}.csv");
            //添加boom,防止excel打开乱码
//            echo chr(0xEF).chr(0xBB).chr(0xBF);
            echo $string;exit;
        }
    }

    function utfToGbk($data)
    {
        return iconv('utf-8', 'GBK', $data);
    }

    /**
     * 转码函数
     *
     * @param mixed $content
     * @param string $from
     * @param string $to
     * @return mixed
     */
    public function charset($content, $from = 'gbk', $to = 'utf-8')
    {
        if (in_array(strtoupper($from), ['UTF8', 'UTF-8'])) {
            $from = 'utf-8';
        }
        if (in_array(strtoupper($to), ['UTF8', 'UTF-8'])) {
            $to = 'utf-8';
        }
        if (strtoupper($from) === strtoupper($to) || empty($content)) {
            //如果编码相同则不转换
            return $content;
        }
        if (function_exists('mb_convert_encoding')) {
            if (is_array($content)) {
                $content = var_export($content, true);
                if ($from != 'utf-8') {
                    $content = mb_convert_encoding($content, $to, $from);
                }
                eval("\$content = $content;");
                return $content;
            } else {
                return mb_convert_encoding($content, $to, $from);
            }
        } elseif (function_exists('iconv')) {
            if (is_array($content)) {
                $content = var_export($content, true);
                if ($from != 'utf-8') {
                    $content = iconv($content, $to, $from);
                }
                eval("\$content = $content;");
                return $content;
            } else {
                if ($from != 'utf-8') {
                    return iconv($content, $to, $from);
                }
                return $content;
            }
        } else {
            return $content;
        }
    }
}
