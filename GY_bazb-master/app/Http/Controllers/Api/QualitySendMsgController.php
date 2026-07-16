<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\QualitySendMsgLog;
use App\Services\ToolsService;
class QualitySendMsgController extends Controller
{
    //获取消息列表
    public function getMsgList(Request $request)
    {
        $doctor_id = $request->user()->id;//获取当前登录用户id
        $isRead = $request->post('isRead', "");//为空代表全部数据
        $msgId = $request->post('msgId');
        $num = 3;
          if ($isRead == 0 || $isRead == 1) {
            if ($msgId) {
                $result = QualitySendMsgLog::query()
                            ->where([
                                ['doctor_id', '=', $doctor_id],
                                ['is_read', '=', $isRead],
                                ['id', '<', $msgId],
                                ])
                                ->orderBy('id', 'desc')
                                ->take($num)
                                ->get();
            }else{
                $result = QualitySendMsgLog::query()->where(['doctor_id'=>$doctor_id, 'is_read'=>$isRead])->orderBy('created_at', 'desc')->paginate($num);
            }
        } else {
            if ($msgId) {
                $result = QualitySendMsgLog::query()
                            ->where([
                                ['doctor_id', '=', $doctor_id],
                                ['id', '<', $msgId],
                                ])
                                ->orderBy('id', 'desc')
                                ->take($num)
                                ->get();
            }else{
                $result = QualitySendMsgLog::query()->where(['doctor_id'=>$doctor_id])->orderBy('created_at', 'desc')->paginate($num);
            }
        }
        //获取未读消息数量
        $unReadCount = QualitySendMsgLog::query()->where(['doctor_id'=>$doctor_id, 'is_read'=>0])->count();
        $data = $result->toArray();
        if ($data) {
            if (isset($data['data'])) {
                $arr['list']['data'] = $data['data'];
            }else{
                $arr['list']['data'] = $data;
            }
            $arr['list']['unReadCount'] = $unReadCount;
            $arr['list']['count'] = 0;
            $code = 200;
            $msg = '成功！';
        }else{
            $code = 200;
            $arr = ['list' => [], 'count' => 0];
            $msg = '没有数据！';
        }
        return ToolsService::returnData($code, $arr, $msg);
    }
    //清除消息
    public function clearMsg(Request $request)
    {
        $doctor_id = $request->user()->id;//获取当前登录用户id
        $msgId = $request->post('msgId', "");
        $result = QualitySendMsgLog::query()->where('doctor_id',$doctor_id)->update(['is_read' => 1]);
        if($msgId){
            $result = QualitySendMsgLog::query()->where('id',$msgId)->update(['is_read' => 1]);
            //获取未读消息数量
            $count = QualitySendMsgLog::query()->where(['doctor_id'=>$doctor_id, 'is_read'=>0])->count();
        }else{
            $result = QualitySendMsgLog::query()->where('doctor_id',$doctor_id)->update(['is_read' => 1]);
            $count = 0;
        }
        //发送消息
        $post_data = array(
            "type" => "publish",
            "title"=> '清除消息',
            "quality_type"=> 1,
            'content'=>"清除消息",
            "count"=>$count,
            "msg_type" => 2,//1发送消息，2清除消息
            "created_at" => time(),
        );
        if ($result) {
             webSendMsg($post_data, $doctor_id);
             return ToolsService::returnData(200, [], $msg ?? '');
        }else{
             return ToolsService::returnData(4001, '清除失败', $msg ?? '');
        }
    }
}