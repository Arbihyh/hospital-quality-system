<?php


namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class QualitySendMsgLog extends Model
{
    protected $table = 'quality_send_msg_log';


    public static function getCount($where = [])
    {
        $obj = self::getWhere($where);
        return $obj->count();
    }

    public static function getList($page = 1, $pageSize = 20, $column = ['*'], $where = [])
    {
        $obj = self::getWhere($where);
        $pageStart = ($page - 1) * $pageSize;
        if($where['export'] == 1){
            return $obj->select($column)->get()->toArray();
        } else {
            return $obj->select($column)->offset($pageStart)->LIMIT($pageSize)->orderBy('quality_send_msg_log.created_at', 'desc')->get()->toArray();
        }
    }

    /**
     * @param array $map
     * @return \Illuminate\Database\Eloquent\Builder
     * 数据获取条件组合
     */
    public static function getWhere(array $map = [])
    {
        $obj = self::query()->leftJoin('patient_info', 'quality_send_msg_log.zyh', '=', 'patient_info.MED_REC_ID');
        if (!empty($map['quality_start_time'])) {
            $obj = $obj->where('quality_send_msg_log.created_at', '>', strtotime($map['quality_start_time']));
        }
        if (!empty($map['quality_start_time'])) {
            $obj = $obj->where('quality_send_msg_log.created_at', '<', strtotime($map['quality_end_time'].'235959'));
        }
        if (!empty($map['aac01_start_time'])) {
            $obj = $obj->where('patient_info.AAC01', '>', date("Y-m-d H:i:s", strtotime($map['aac01_start_time'].'000000')));
        }
        if (!empty($map['aac01_end_time'])) {
            $obj = $obj->where('patient_info.AAC01', '<', date("Y-m-d H:i:s", strtotime($map['aac01_end_time'].'235959')));
        }
        if (!empty($map['aab01_start_time'])) {
            $obj = $obj->where('patient_info.AAB01', '>', date("Y-m-d H:i:s", strtotime($map['aab01_start_time'].'000000')));
        }
        if (!empty($map['aab01_end_time'])) {
            $obj = $obj->where('patient_info.AAB01', '<', date("Y-m-d H:i:s", strtotime($map['aab01_end_time'].'235959')));
        }
        if($map['department']){
            $obj = $obj->where('quality_send_msg_log.AAC11N', 'LIKE', '%'.$map['department'].'%');
        }
        if($map['content']){
            $obj = $obj->where('quality_send_msg_log.quality_content', 'LIKE', '%'.$map['content'].'%');
        }
        if($map['AAA28']){
            $obj = $obj->where('patient_info.AAA28', '=', $map['AAA28']);
        }
        return $obj;
    }

    /**
     * @param string $zyh
     * @param int $ruleId
     * @param int $dataId
     * @return bool
     * 如果病例信息进行过质控提醒，数据删除后将数据改为已删除
     */
    public static function setStatus($zyh='', $ruleId=0, $dataId=0)
    {
        $where = ['zyh'=>$zyh, 'rule_id'=> $ruleId];
        if($dataId){
            $where['data_id'] = $dataId;
        }
        $isHas = QualitySendMsgLog::query()->where($where)->get()->toArray();
        if($isHas){
            QualitySendMsgLog::query()->where($where)->update(['is_delete'=>1]);
        }

        return true;
    }
}
