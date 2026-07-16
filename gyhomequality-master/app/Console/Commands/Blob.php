<?php

namespace App\Console\Commands;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use Illuminate\Console\Command;

class Blob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:blob';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        ini_set('default_socket_timeout', 0);
        $page = 1;
        $limit = 10;
        while (true){
            echo $page."\n";
            $offset = ($page - 1) * $limit;
            $list = EMR_BL_BL01::query()
                ->offset($offset)
                ->limit($limit)
                ->pluck('BLBH');
            if ($list){
                $list = $list->toArray();
            }else{
                die;
            }
            if (empty($list)){
                die;
            }
            $list = implode(',',$list);
            $con = oci_connect('root', 'root', '124.70.62.102:1521/xe', 'UTF8');
            if (!$con) {
                $e = oci_error();
                print_r(htmlentities($e['message'])) . "\n";
                die;
            }
            echo $list."\n";
                $sql = "SELECT ROWNUM r,JLXH,BLBH,XGGH,to_char(XGSJ,'yyyy-mm-dd hh24:mi:ss') as XGSJ,HJNR FROM ROOT.EMR_BL_BLXG WHERE XGSJ BETWEEN to_char('2021-02-01','yyyy-mm-dd hh24:mi:ss') and to_char('2021-02-10','yyyy-mm-dd hh24:mi:ss') and BLBH IN ($list)";
            $result = oci_parse($con, $sql);
            oci_execute($result,OCI_DEFAULT);
            $data = [];
            while ($row = oci_fetch_assoc($result)) {
                $data[] = $row;
            }
            if (empty($data)){
                continue;
            }
            foreach ($data as $item) {
                $text = $item['HJNR']->load();
                $item['HJNR']->free();
                $mde = mb_detect_encoding($text, 'auto');
                $a = EMR_BL_BLXG::query()
                    ->where('JLXH',$item['JLXH'])
                    ->first();
                if ($a){
                    $a = $a->toArray();
                }
                if (!empty($a)){
                    continue;
                }
                $temp = [
                    'JLXH' => $item['JLXH'],
                    'BLBH' => $item['BLBH'],
                    'XGGH' => $item['XGGH'],
                    'XGSJ' => $item['XGSJ'],
//                    'HJNR' => $mde == 'CP936' ? iconv('utf-8','latin1//IGNORE',$text) : iconv($mde,"UTF-8",$text),
                    'HJNR' => mb_convert_encoding($text,'utf-8',$mde),
                ];
                EMR_BL_BLXG::query()->insert($temp);
            }
            $page++;
        }
    }
}
