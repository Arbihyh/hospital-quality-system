<?php


namespace App\Services;


class ToolsService
{
    /**接口返回调用
     * @param $code
     * @param $data
     * @param string $msg
     * @return array
     */
    public static function returnData($code,$data=[],$msg=''){
        return [
            'code' => $code,
            'msg' => $msg,
            'data' => !empty($data) ? $data : new \ArrayObject(),
            'time' => time()
        ];
    }

    public static function jsonSuccess($data=[],$msg='成功!')
    {
        return self::returnData(200, $data, $msg);
    }

    /**
     * @param $code
     * @param array $data
     * @param string $msg
     * @return array
     */
    public static function returnAdmin($code,$data=[],$msg=''){
        return [
            'c' => $code,
            'd' => false,
            'm' => $msg,
            'p' => !empty($data) ? $data : new \ArrayObject(),
            't' => time()
        ];
    }

    /**代码转换(二维)
     * @param array $code
     * @param array $data
     * @return array
     */
    public static function codeTransformationList(array $code,array $data){
        foreach ($code as &$v){
            foreach ($data as $kk=>$vv)
            {
                if (!isset($vv[$v]))continue;
                $data[$kk][$v] = config('dictionaries.'.$v.'.'.(string)$vv[$v]);
            }
        }
        return $data;
        foreach ($data as &$value){
            foreach ($code as $item){
                $value[$item] = config('dictionaries.'.$item.'.'.(string)$value[$item]);
            }
        }
        return $data;
    }
    /**代码转换(一维)
     * @param array $code
     * @param array $data
     * @return array
     */
    public static function codeTransformationInfo(array $code,array $data){
        $config = config('dictionaries');
        foreach ($code as $item){
            if (!empty($data[$item])){
                $data[$item] = $config[$item][$data[$item]] ?? '';
            }
        }
        return $data;
    }

    public static function arrayColumns(array $input,string $column_keys,string $index_keys = ''){
        $result = array();
        $keys = isset($column_keys) ? explode(',',$column_keys) : array();
        if ($input){
            foreach ($input as $item){
                if ($keys){
                    $temp = array();
                    foreach ($keys as $key){
                        $temp[$key] = $item[$key];
                    }
                }else{
                    $temp = $item;
                }
                if (!empty($index_keys)){
                    $result[$item[$index_keys]] = $temp;
                }else{
                    $result[] = $temp;
                }
            }
        }
        return $result;
    }
}
