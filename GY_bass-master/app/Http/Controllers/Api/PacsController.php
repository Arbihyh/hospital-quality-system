<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PacsService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use App\Model\Pacs;

class PacsController extends Controller
{
    /**
     * get_pacs_detail
     * 得到报告明细
     * @group quality
     * @bodyParam AAC01 string 出院时间
     * @response {
     *  "code":200,
     *  "msg":"",
     *  "data":{
     *      "list": [{
     * 			  "ExamType": "01",
     * 			  "BRXM": "姓名",
     * 			  "BRXB": "性别",
     * 			  "PatientID": "影像号",
     * 			  "SQKSMC": "科室",
     * 			  "JZLSH": "住院号",
     * 			  "StudyUid": "检查号",
     * 			  "JCMC": "检查项目",
     * 			  "YXBX": "影像学诊断",
     * 			  "JCYS": "报告医生",
     * 			  "SHRXM": "审核医生"
     * 			},
	 * 			{
     * 			  "ExamType": "02",
     * 			  "BRXM": "姓名",
     * 			  "BRXB": "性别",
     * 			  "PatientID": "影像号",
     * 			  "SQKSMC": "科室",
     * 			  "JZLSH": "住院号",
     * 			  "StudyUid": "检查号",
     * 			  "JCMC": "检查项目",
     * 			  "YXBX": "影像学诊断",
     * 			  "JCYS": "报告医生",
     * 			  "SHRXM": "审核医生"
     * 			},
	 * 			{
     * 			  "ExamType": "03",
     * 			  "BRXM": "姓名",
     * 			  "BRXB": "性别",
     * 			  "PatientID": "影像号",
     * 			  "SQKSMC": "科室",
     * 			  "JZLSH": "住院号",
     * 			  "StudyUid": "检查号",
     * 			  "JCMC": "检查项目",
     * 			  "YXBX": "影像学诊断",
     * 			  "JCYS": "报告医生",
     * 			  "SHRXM": "审核医生"
     * 			},
	 * 			{
     * 			  "ExamType": "04",
     * 			  "BRXM": "姓名",
     * 			  "BRXB": "性别",
     * 			  "PatientID": "影像号",
     * 			  "SQKSMC": "科室",
     * 			  "JZLSH": "住院号",
     * 			  "StudyUid": "检查号",
     * 			  "JCMC": "检查项目",
     * 			  "YXBX": "影像学诊断",
     * 			  "JCYS": "报告医生",
     * 			  "SHRXM": "审核医生"
     * 			},
	 * 			{
     * 			  "ExamType": "05",
     * 			  "BRXM": "姓名",
     * 			  "BRXB": "性别",
     * 			  "PatientID": "影像号",
     * 			  "SQKSMC": "科室",
     * 			  "JZLSH": "住院号",
     * 			  "StudyUid": "检查号",
     * 			  "JCMC": "检查项目",
     * 			  "YXBX": "影像学诊断",
     * 			  "JCYS": "报告医生",
     * 			  "SHRXM": "审核医生"
     * 			},
	 * 			{
     * 			  "ExamType": "06",
     * 			  "StudyUid": "编号",
     * 		 	  "JZLSH": "病人号",
     * 			  "BRXM": "姓名：",
     * 			  "BRXB": "性别：BRXB",
     * 			  "SQKSMC": "科室",
     * 			  "YXZD": "超声所见：YXBX",
     * 			  "YXBX": "超声所见：YXBX",
     *            "JCYS": "检查医生：JCYS",
     * 			  "SHRXM": "审核医生：SHRXM",
     * 			  "BGRQ": "检查时间："
     * 			},
	 * 			{
     * 			  "ExamType": "07",
     * 			  "StudyUid": "病理",
	 * 			  "BRXM": "姓名：",
     * 			  "BRXB": "性别：BRXB",
     * 		 	  "JZLSH": "住院号",
     * 			  "SQKSMC": "科别",
     * 			  "JYSJ": "送检日期",
     * 			  "JCBW": "大体描述",
     * 			  "YXBX": "镜下所见：YXBX",
     * 			  "YXZD": "病理诊断：YXZD",
     *            "JCYS": "诊断医生：JCYS",
     * 			  "SHRXM": "复诊医生",
     * 			  "BGRQ": "检查时间："
     * 			},
     * 			{
     * 			  "ExamType": "08",
     * 			  "StudyUid": "病理",
	 * 			  "BRXM": "姓名：",
     * 			  "BRXB": "性别：BRXB",
     * 		 	  "JZLSH": "住院号",
     * 			  "SQKSMC": "科别",
     * 			  "JYSJ": "送检日期",
     * 			  "JCBW": "大体描述",
     * 			  "YXBX": "镜下所见：YXBX",
     * 			  "YXZD": "病理诊断：YXZD",
     *            "JCYS": "诊断医生：JCYS",
     * 			  "SHRXM": "复诊医生",
     * 			  "BGRQ": "检查时间："
     * 			},
     * 			{
     * 			  "ExamType": "10",
	 * 			  "BRXM": "姓名：",
     * 			  "BRXB": "性别：BRXB",
     * 		 	  "JZLSH": "住院号",
     * 			  "SQKSMC": "科室",
     * 			  "YXZD": "病理诊断：YXZD",
     *            "JCYS": "诊断医生：JCYS"
     * 			}],
     *      "count":100
     *  },
     *  "time":123787842
     * }
     *
     */
    public function getList(Request $request, PacsService $pacsService)
    {
		$ExamType = $request->post('ExamType');
		$JZLSH    = $request->post('AAA28'); //AAA28 病案号
		$AAB01    = $request->post('AAB01'); //AAB01入院时间
		$AAC01    = $request->post('AAC01'); //AAC01出院时间
		$AAC0107  = date("Y-m-d H:i:s",strtotime("+7 day",strtotime($AAC01)));

        if (!$JZLSH) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
		if (!$ExamType) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        $res = [];
        try {
            $res = $pacsService->getPacsDetail($JZLSH, $ExamType, $AAB01, $AAC0107);

			$arr = array();
			foreach($res as $key=>$val) {
				if($val['ExamType'] == 1 || $val['ExamType'] == 2  || $val['ExamType'] == 3  || $val['ExamType'] == 4  || $val['ExamType'] == 5 ) {
					/*
					检查日期：
					报告日期
					姓名： BRXM
					性别：BRXB
					年龄：
					影像号：PatientID
					科室：SQKSMC
					住院号：JZLSH
					床号：
					检查号：StudyUid
					检查项目：JCMC
					影像学表现：YXBX
					影像学诊断：YXZD
					报告医生：JCYS
					审核医生：SHRXM
					*/
					$arr[] = array( 'ExamType'  => $val['ExamType'], //类型
									'BRXM'      => $val['BRXM'], //姓名： BRXM
									'BRXB'      => $val['BRXB'], //性别：BRXB
									'PatientID' => $val['PatientID'], //影像号：PatientID
									'SQKSMC'    => $val['SQKSMC'], //住院号：JZLSH
									'JZLSH'     => $val['JZLSH'], //检查号：StudyUid
									'StudyUid'  => $val['StudyUid'], //检查号：StudyUid
									'JCMC'      => $val['JCMC'], //检查项目：JCMC
									'YXBX'      => $val['YXBX'], //影像学表现：YXBX
									'YXZD'      => $val['YXZD'], //影像学诊断：YXZD
									'JCYS'      => $val['JCYS'], //报告医生：JCYS
									'SHRXM'     => $val['SHRXM'] //审核医生：SHRXM
									);
				} elseif($val['ExamType'] == 6) {
					/*
					编号：StudyUid
					病人号：JZLSH
					姓名： BRXM
					性别：BRXB
					年龄：
					科别：SQKSMC

					超声所见：YXBX

					超声提示：YXZD


					检查医生：JCYS

					审核医生：SHRXM
					录入员：
					会诊医师：
					检查时间： BGRQ   + BGSJ 
					*/
					$arr[] = array( 'ExamType' => $val['ExamType'], //类型
									'StudyUid' => $val['StudyUid'], //编号：StudyUid
									'JZLSH'    => $val['JZLSH'], //病人号：JZLSH
									'BRXM'     => $val['BRXM'], //姓名： BRXM
									'BRXB'     => $val['BRXB'], //性别：BRXB
									'SQKSMC'   => $val['SQKSMC'], //科别：SQKSMC
									'YXBX'     => $val['YXBX'], //超声所见：YXBX
									'YXZD'     => $val['YXZD'], //超声提示：YXZD
									'JCYS'     => $val['JCYS'], //检查医生：JCYS
									'SHRXM'    => $val['SHRXM'], //审核医生：SHRXM
									'BGRQ'     => $val['BGRQ'] . $val['BGSJ'], //检查时间： BGRQ   + BGSJ 
									);
					
				} elseif($val['ExamType'] == 7 || $val['ExamType'] == 8) {
					/*
					病理：StudyUid
					姓名： BRXM
					性别：BRXB
					年龄：
					住院号：JZLSH

					送检医院：

					科别：SQKSMC

					送检日期：JYSJ

					临床诊断：

					送检医生：

					大体描述：JCBW    +   JCMC

					镜下所见：YXBX

					病理诊断：YXZD

					诊断医生：JCYS

					复诊医生：SHRXM

					报告时间： BGRQ   + BGSJ 
					*/


					$arr[] = array( 'ExamType' => $val['ExamType'], //类型
									'StudyUid' => $val['StudyUid'], //病理：StudyUid
									'BRXM'     => $val['BRXM'], //姓名： BRXM
									'BRXB'     => $val['BRXB'], //性别：BRXB
									'JZLSH'    => $val['JZLSH'], //住院号：JZLSH
									'SQKSMC'   => $val['SQKSMC'], //科别：SQKSMC
									'JYSJ'     => $val['JYSJ'], //送检日期：JYSJ
									'JCBW'     => $val['JCBW'] . $val['JCMC'], //大体描述：JCBW    +   JCMC
									'YXBX'     => $val['YXBX'], //镜下所见：YXBX
									'YXZD'     => $val['YXZD'], //病理诊断：YXZD
									'JCYS'     => $val['JCYS'], //诊断医生：JCYS
									'SHRXM'    => $val['SHRXM'], //复诊医生：SHRXM
									'BGRQ'     => $val['BGRQ'] . $val['BGSJ'] //检查时间： BGRQ   + BGSJ 
									);					
				} elseif($val['ExamType'] == 10) {
					/*
					姓名：   BRXM                  
					 性别：  BRXB            
					年龄：                          
					科室：    SQKSMC                            
					住院号：   JZLSH                            
					床号：
					心电提示：YXZD
					检查医生：JCYS
					 */
					$arr[] = array( 'ExamType'  => $val['ExamType'], //类型
									'BRXM'      => $val['BRXM'], //姓名： BRXM
									'BRXB'      => $val['BRXB'], //性别：BRXB
									'SQKSMC'    => $val['SQKSMC'], //科室：    SQKSMC 
									'JZLSH'     => $val['JZLSH'], //住院号：   JZLSH 
									'YXZD'      => $val['YXZD'], //心电提示：YXZD
									'JCYS'      => $val['JCYS'], //检查医生：JCYS
									);
					
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
