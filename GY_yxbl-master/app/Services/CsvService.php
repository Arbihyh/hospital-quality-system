<?php

namespace App\Services;

final class CsvService
{
    public $filename;

    public function export($data, $is_download = false)
    {
        if (!is_array($data)) return;
        $string = '';
        $new = array();
        $titleArr = array_keys($data[0]);
        foreach ($data as $k => $v) {
            $arr = [];
            foreach ($titleArr as $value){
                $arr[$value] = $v[$value];
            }
            foreach ($arr as $xk => $xv) {
                $xv = $this->utfToGbk($xv);
                $arr[$xk] = str_replace(',', '', $xv);
            }
            $new[] = implode(',', $arr);
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
