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
        if ($data === null) {
            return '';
        }

        if (is_array($data) || is_object($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        $data = (string) $data;

        if ($data === '') {
            return '';
        }

        // 导出 GBK CSV 时，部分病历内容可能包含 GBK 无法表示的字符，直接 iconv 会触发 500。
        $result = @iconv('UTF-8', 'GBK//IGNORE', $data);
        if ($result !== false) {
            return $result;
        }

        if (function_exists('mb_convert_encoding')) {
            $result = @mb_convert_encoding($data, 'GBK', 'UTF-8');
            if ($result !== false) {
                return $result;
            }
        }

        return $data;
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
