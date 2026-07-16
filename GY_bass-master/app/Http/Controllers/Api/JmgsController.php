<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PacsService;
use App\Services\ToolsService;
use App\Services\JmgsService;
use App\Services\YmJmgsService;
use Illuminate\Http\Request;
use App\Model\Jmgs;

class JmgsController extends Controller
{
    /**
     * get_jmgs_detail
     * 得到报告明细, 从1-10没有9
     * @group quality
     * @bodyParam AAC01 object 出院时间
     *
     * @response {
     * 		  "code": 200,
     * 		  "msg": "",
     * 		  "data": [
     * 			{
     * 			  "type": "1",
     * 			  "TXM": "条形码",
     * 			  "NO": "姓名",
     * 			  "XB": "性别",
     * 			  "NL": "年龄",
     * 			  "CH": "床号",
     * 			  "YBLX": "样本类型",
     * 			  "YBZT": "样本状态",
     * 			  "AAA28": "住院号",
     * 			  "LCZD": "临床诊断",
     * 			  "YW": " 英文（检验项目）",
     * 			  "JYXM": "检验项目",
	 * 			  "JG": "结果",
	 * 			  "TS": "提示",
	 * 			  "DW": "单位",
	 * 			  "SJYS": "送检医生",
	 * 			  "JYY": "检验员",
	 * 			  "SHY": "审核员",
	 * 			  "CJSJ": "采集时间",
	 * 			  "JSSJ": "接收时间",
	 * 			  "BGSJ": "报告时间"
     * 			},
     * 			{
     * 			  "type": "2",
     * 			  "TXM": "条形码",
     * 			  "NO": "姓名",
     * 			  "XB": "性别",
     * 			  "NL": "年龄",
     * 			  "CH": "床号",
     * 			  "YBLX": "样本类型",
     * 			  "YBZT": "样本状态",
     * 			  "AAA28": "住院号",
     * 			  "LCZD": "临床诊断",
     * 			  "PYJG": " 细菌培养结果",
     * 			  "XJMC": "细菌名称",
	 * 			  "XJJL": "细菌数量",
	 * 			  "YMMC": "药敏名称",
	 * 			  "YMJG": "药敏结果",
	 * 			  "YMBW": "部位（样本类型）",
	 * 			  "SJYS": "送检医生",
	 * 			  "JYY": "检验员",
	 * 			  "SHY": "审核员",
	 * 			  "CJSJ": "采集时间",
	 * 			  "JSSJ": "接收时间",
	 * 			  "BGSJ": "报告时间"
     * 			}
     * 		  ],
     * 		  "time": 1679920161
     * }
     */
    public function getList(Request $request, JmgsService $jmgsService)
    {
		$id    = $request->get('id'); //id 病案号

        if (!$id) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        $res = [];
        try {
            $res = $jmgsService->getJmgsDetail($id);
			$arr = array();
			foreach($res as $key=>$val) {
				if($key == 'data2') {
					$type = 2;
					if(is_array($val)) {
						foreach($val as $key1=>$val1) {
							/*
								type    2
								TXM     条形码
								NO      NO
								XM     姓名
								XB  性别
								NL   年龄
								CH   床号
								YBLX    样本类型
								YBZT   样本状态
								AAA28  住院号
								BQ   病区
								LCZD  临床诊断

								PYJG  细菌培养结果***
								XJMC  细菌名称***
								XJJL  细菌数量**
								YMMC 药敏名称**
								YMJG  药敏结果**
								YMBW部位（样本类型）**


								SJYS  送检医生
								JYY  检验员
								SHY  审核员
								CJSJ  采集时间
								JSSJ  接收时间
								BGSJ   报告时间
							 */
							$arr[] = array(
								'type'  => $type,
								'TXM'   => $val1['TXM'], //条形码
								'NO'    => $val1['NO'], //NO
								'XM'    => $val1['XM'], //姓名
								'XB'    => $val1['XB'], //性别
								'NL'    => $val1['NL'], //年龄
								'CH'    => $val1['CH'], //床号
								'YBLX'  => $val1['YBLX'], //样本类型
								'YBZT'  => $val1['YBZT'], //样本状态
								'AAA28' => $val1['AAA28'], //住院号
								'BQ'    => $val1['BQ'], //病区
								'LCZD'  => $val1['LCZD'], //临床诊断**
								'PYJG'    => $val1['PYJG'], //细菌培养结果**
								'XJMC'  => $val1['XJMC'], //细菌名称**
								'XJJL'    => $val1['XJJL'], //细菌数量****
								'YMMC'    => $val1['YMMC'], //药敏名称
								'YMJG'  => $val1['YMJG'], //药敏结果**
								'YMBW'    => $val1['YMBW'], //YMBW部位（样本类型）**
								'SJYS'  => $val1['SJYS'], //送检医生
								'JYY'   => $val1['JYY'], //检验员
								'SHY'   => $val1['SHY'], // 审核员
								'CJSJ'  => $val1['CJSJ'], // 采集时间
								'JSSJ'  => $val1['JSSJ'], //接收时间*
								'BGSJ'  => $val1['BGSJ'] //报告时间
							);
						}
					}
				}
				if($key == 'data1') {
					$type = 1;
					if(is_array($val)) {
						foreach($val as $key1=>$val1) {

							/*
								type 1
								TXM     条形码
								NO      NO
								XM     姓名
								XB  性别
								NL   年龄
								CH   床号
								YBLX    样本类型
								YBZT   样本状态
								AAA28  住院号
								BQ   病区
								LCZD  临床诊断
								YW   英文（检验项目）
								JYXM   检验项目
								JG   结果
								TS  提示
								CKFW 参考范围
								DW  单位
								SJYS  送检医生
								JYY  检验员
								SHY  审核员
								CJSJ  采集时间
								JSSJ  接收时间
								BGSJ   报告时间
							 */

							$arr[] = array(
								'type'  => $type,
								'TXM'   => $val1['TXM'], //条形码
								'NO'    => $val1['NO'], //NO
								'XM'    => $val1['XM'], //姓名
								'XB'    => $val1['XB'], //性别
								'NL'    => $val1['NL'], //年龄
								'CH'    => $val1['CH'], //床号
								'YBLX'  => $val1['YBLX'], //样本类型
								'YBZT'  => $val1['YBZT'], //样本状态
								'AAA28' => $val1['AAA28'], //住院号
								'BQ'    => $val1['BQ'], //病区
								'LCZD'  => $val1['LCZD'], //临床诊断**
								'YW'    => $val1['YW'], //英文（检验项目）
								'JYXM'  => $val1['JYXM'], //检验项目
								'JG'    => $val1['JG'], //结果
								'TS'    => $val1['TS'], //提示
								'CKFW'  => $val1['CKFW'], //参考范围
								'DW'    => $val1['DW'], //单位
								'SJYS'  => $val1['SJYS'], //送检医生
								'JYY'   => $val1['JYY'], //检验员
								'SHY'   => $val1['SHY'], // 审核员
								'CJSJ'  => $val1['CJSJ'], // 采集时间
								'JSSJ'  => $val1['JSSJ'], //接收时间*
								'BGSJ'  => $val1['BGSJ'], //报告时间
							);
						}
					}
				}
				
			}
            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnData($code, $arr, $msg ?? '');
    }

    /**
     * @param Request $request
     * @param CaseService getCasePlatform
     * @return array
     */
    public function getPacsPlatform(Request $request, PacsService $pacsService)
    {
        $id = $request->post('id');

        if (!$id) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        $res = [];
        try {
            $res = $pacsService->getPacsPlatform($id);
            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnData($code, $res, $msg ?? '');
    }

    

}
