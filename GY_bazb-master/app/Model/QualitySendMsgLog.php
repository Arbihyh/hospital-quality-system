<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class QualitySendMsgLog extends Model
{
    protected $table = 'quality_send_msg_log';


    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function getCount($where = [])
    {
        $obj = self::getWhere($where);
        return $obj->count();
    }

    public static function getList($page = 1, $pageSize = 20, $column = ['*'], $where = [])
    {
        $obj = self::getWhere($where);
        $pageStart = ($page - 1) * $pageSize;
        if ($where['export'] == 1) {
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
        $obj = self::query()->leftJoin('ZY_BRRY', 'quality_send_msg_log.zyh', '=', 'ZY_BRRY.ZYH');
        if (in_array($map['status'], [1, 2])) {
            $obj = $obj->where('quality_send_msg_log.status', '=', $map['status']);
        } elseif ($map['status'] == 3) {
            $obj = $obj->where('quality_send_msg_log.status', '=', $map['status']);
            $obj = $obj->where('quality_send_msg_log.is_youxiao', '=', 1); // 按时整改
        }
        if ($map['is_delete'] != -1) {
            $obj = $obj->where('quality_send_msg_log.is_delete', '=', $map['is_delete']);
        }
        if (!empty($map['quality_start_time'])) {
            $obj = $obj->where('quality_send_msg_log.created_at', '>', strtotime($map['quality_start_time']));
        }
        if (!empty($map['quality_start_time'])) {
            $obj = $obj->where('quality_send_msg_log.created_at', '<', strtotime($map['quality_end_time'] . '235959'));
        }
        if (!empty($map['aac01_start_time'])) {
            $obj = $obj->where('ZY_BRRY.AAC01', '>', date("Y-m-d H:i:s", strtotime($map['aac01_start_time'] . '000000')));
        }
        if (!empty($map['aac01_end_time'])) {
            $obj = $obj->where('ZY_BRRY.AAC01', '<', date("Y-m-d H:i:s", strtotime($map['aac01_end_time'] . '235959')));
        }
        if (!empty($map['aab01_start_time'])) {
            $obj = $obj->where('ZY_BRRY.AAB01', '>', date("Y-m-d H:i:s", strtotime($map['aab01_start_time'] . '000000')));
        }
        if (!empty($map['aab01_end_time'])) {
            $obj = $obj->where('ZY_BRRY.AAB01', '<', date("Y-m-d H:i:s", strtotime($map['aab01_end_time'] . '235959')));
        }
        if (is_array($map['department'])) {
            $dep = Department::query()->whereIn("dep_name", $map['department'])->pluck("dep_id")->toArray();
            $obj = $obj->whereIn('ZY_BRRY.BRKS', $dep);
        } elseif ($map['department']) {
            $dep = Department::query()->whereIn("dep_name", [$map['department']])->pluck("dep_id")->toArray();
            $obj = $obj->whereIn('ZY_BRRY.BRKS', $dep);
        }
        if (!empty($map['wardName'])) {
            $obj = $obj->whereIn('quality_send_msg_log.AAC11N', $map['wardName']);
        }
        if (!empty($map['doctorName'])) {
            $obj = $obj->whereIn('quality_send_msg_log.AAC11N', $map['doctorName']);
        }
        if (!empty($map['content'])) {
            $obj = $obj->where('quality_send_msg_log.quality_content', 'LIKE', '%' . $map['content'] . '%');
        }
        if (!empty($map['is_warning_msg'])) {
            $obj = $obj->where('quality_send_msg_log.is_warning_msg', '=', $map['is_warning_msg']);
        }
        if (!empty($map['AAA28'])) {
            $obj = $obj->where('ZY_BRRY.AAA28', '=', $map['AAA28']);
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
    public static function setStatus($zyh = '', $ruleId = 0, $dataId = 0)
    {
        $where = ['zyh' => $zyh, 'rule_id' => $ruleId];
        if ($dataId) {
            $where['data_id'] = $dataId;
        }
        $isHas = QualitySendMsgLog::query()->where($where)->get()->toArray();
        if ($isHas) {
            QualitySendMsgLog::query()->where($where)->update(['is_delete' => 0]);
        }

        return true;
    }
}
