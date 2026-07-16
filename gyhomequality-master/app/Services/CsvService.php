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
            echo $string;exit;
        }
    }

    /**
     * UTF-8 转 GBK 编码
     * 处理无法转换的字符，避免 iconv 错误
     *
     * @param mixed $data 待转换的数据
     * @return string 转换后的 GBK 编码字符串
     */
    function utfToGbk($data)
    {
        // 处理非字符串类型
        if (!is_string($data)) {
            $data = (string)$data;
        }
        
        // 空字符串直接返回
        if (empty($data)) {
            return $data;
        }
        
        // 优先使用 mb_convert_encoding，它更健壮
        if (function_exists('mb_convert_encoding')) {
            $result = @mb_convert_encoding($data, 'GBK', 'UTF-8');
            if ($result !== false) {
                return $result;
            }
        }
        
        // 使用 iconv 并忽略无法转换的字符
        if (function_exists('iconv')) {
            $result = @iconv('UTF-8', 'GBK//IGNORE', $data);
            if ($result !== false) {
                return $result;
            }
        }
        
        // 如果都失败了，返回原始数据（可能会有乱码，但不会报错）
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
