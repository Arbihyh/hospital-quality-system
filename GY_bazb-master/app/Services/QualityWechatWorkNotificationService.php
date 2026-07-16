<?php

namespace App\Services;

use App\Model\QualitySendMsgLog;
use App\Model\Staff;
use App\Model\WechatWorkGroup;
use App\Model\WechatWorkPushConfig;
use App\Model\WechatWorkPushLog;
use App\Model\WechatWorkWarningRead;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class QualityWechatWorkNotificationService
{
    /**
     * @var WechatWorkClientService
     */
    protected $client;

    public function __construct(WechatWorkClientService $client = null)
    {
        $config = $this->getConfig();
        $this->client = $client ?: new WechatWorkClientService($config);
    }

    /**
     * 推送单条质控预警日志。
     *
     * @param int|array|QualitySendMsgLog $sourceLog
     * @param array $options
     * @return array
     */
    public function pushQualityLog($sourceLog, array $options = [])
    {
        $config = $this->getConfig();
        if (!$this->isEnabled($config)) {
            return $this->result(false, '企业微信推送未启用');
        }

        $source = $this->resolveSourceLog($sourceLog);
        if (empty($source)) {
            return $this->result(false, '质控预警日志不存在');
        }

        return $this->pushSource($source, $config, $options);
    }

    /**
     * 定时推送未处理质控预警。
     *
     * @param array $options
     * @return array
     */
    public function pushPendingWarnings(array $options = [])
    {
        $config = $this->getConfig();
        if (!$this->isEnabled($config)) {
            return $this->result(false, '企业微信推送未启用');
        }

        $force = !empty($options['force']);
        if (!$force && !$this->shouldRunNow($config)) {
            return $this->result(true, '当前时间未命中重复提醒策略');
        }

        $limit = !empty($options['limit']) ? intval($options['limit']) : 200;
        $repeatBatch = $this->makeRepeatBatch($config);
        $rows = QualitySendMsgLog::query()
            ->where('status', 2)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        $summary = [
            'total' => $rows->count(),
            'success' => 0,
            'fail' => 0,
            'skip' => 0,
        ];

        foreach ($rows as $row) {
            $result = $this->pushSource($row->toArray(), $config, [
                'force' => $force,
                'repeat_batch' => $repeatBatch,
                'log_skipped' => false,
            ]);

            $summary['success'] += $result['data']['success'] ?? 0;
            $summary['fail'] += $result['data']['fail'] ?? 0;
            $summary['skip'] += $result['data']['skip'] ?? 0;
        }

        return $this->result(true, '定时推送执行完成', $summary);
    }

    /**
     * 推送自定义企业微信消息，并记录日志。
     *
     * @param string $title
     * @param string $content
     * @param array $userIds
     * @param array $groupIds
     * @param string $qualityContent
     * @param string $basis
     * @return array
     */
    public function pushCustomMessage($title, $content, array $userIds = [], array $groupIds = [], $qualityContent = '', $basis = '')
    {
        $config = $this->getConfig();
        if (!$this->isEnabled($config)) {
            return $this->result(false, '企业微信推送未启用');
        }

        $qualityContent = $qualityContent !== '' ? $qualityContent : $content;

        $source = [
            'id' => 0,
            'title' => $title ?: '企业微信测试消息',
            'content' => $content,
            'quality_content' => $qualityContent,
            'msg_yj' => $basis,
            'zyh' => '',
            'AAA28' => '',
            'rule_id' => 0,
            'case_quality_id' => 0,
            'data_id' => '',
            'quality_type' => 0,
        ];

        return $this->pushSource($source, $config, [
            'force' => true,
            'receiver_userids' => $userIds,
            'group_ids' => $groupIds,
            'repeat_batch' => 'custom_' . date('YmdHis'),
        ]);
    }

    /**
     * 标记预警为已查看。
     *
     * @param array $params
     * @return array
     */
    public function markRead(array $params)
    {
        $logId = !empty($params['log_id']) ? intval($params['log_id']) : 0;
        $sourceLogId = !empty($params['source_log_id']) ? intval($params['source_log_id']) : 0;
        $warningKey = $params['warning_key'] ?? '';
        $userId = !empty($params['user_id']) ? intval($params['user_id']) : 0;
        $wechatUserId = $params['wechat_userid'] ?? '';

        if (!$logId && !$sourceLogId && empty($warningKey)) {
            return $this->result(false, '缺少已查看标记参数');
        }

        $query = WechatWorkPushLog::query();
        if ($logId) {
            $query->where('id', $logId);
        }
        if ($sourceLogId) {
            $query->where('source_log_id', $sourceLogId);
        }
        if ($warningKey !== '') {
            $query->where('warning_key', $warningKey);
        }

        $logs = $query->get(['id', 'source_log_id', 'warning_key', 'receiver_type', 'receiver_id']);
        if ($logs->isEmpty()) {
            return $this->result(false, '未找到企业微信推送日志');
        }

        $now = date('Y-m-d H:i:s');
        $ids = $logs->pluck('id')->toArray();
        WechatWorkPushLog::query()->whereIn('id', $ids)->update([
            'read_status' => 1,
            'read_at' => $now,
            'read_user_id' => $userId,
        ]);

        $sourceIds = array_filter(array_unique($logs->pluck('source_log_id')->toArray()));
        if (!empty($sourceIds)) {
            QualitySendMsgLog::query()->whereIn('id', $sourceIds)->update(['is_read' => 1]);
        }

        foreach ($logs as $log) {
            $readWechatUserId = $wechatUserId;
            if ($readWechatUserId === '' && $log->receiver_type === 'user') {
                $readWechatUserId = $log->receiver_id;
            }

            WechatWorkWarningRead::query()->updateOrCreate(
                [
                    'warning_key' => $log->warning_key,
                    'user_id' => $userId,
                ],
                [
                    'source_log_id' => $log->source_log_id,
                    'wechat_userid' => $readWechatUserId,
                    'read_at' => $now,
                ]
            );
        }

        return $this->result(true, '已标记为已查看', ['count' => count($ids)]);
    }

    /**
     * 处理企业微信任务卡片按钮点击事件。
     *
     * @param string $taskId
     * @param string $eventKey
     * @param string $wechatUserId
     * @return array
     */
    public function handleTaskCardClick($taskId, $eventKey, $wechatUserId = '')
    {
        if ($taskId === '') {
            return $this->result(false, '缺少企业微信任务卡片task_id');
        }

        if ($eventKey !== 'ack') {
            return $this->result(false, '暂不支持的任务卡片按钮事件');
        }

        $pushLog = WechatWorkPushLog::query()->where('task_id', $taskId)->first();
        if (!$pushLog && preg_match('/wechat_work_push_log_(\d+)/', $taskId, $matches)) {
            $pushLog = WechatWorkPushLog::query()->where('id', intval($matches[1]))->first();
        }

        if (!$pushLog) {
            return $this->result(false, '未找到企业微信任务卡片推送日志');
        }

        $readResult = $this->markRead([
            'log_id' => $pushLog->id,
            'wechat_userid' => $wechatUserId,
        ]);

        if ($readResult['success'] && $pushLog->receiver_type === 'user' && !empty($pushLog->receiver_id)) {
            $this->client->updateTaskCard([$pushLog->receiver_id], $taskId, $eventKey);
        }

        return $readResult;
    }

    /**
     * 推送源数据给接收人和群。
     *
     * @param array $source
     * @param WechatWorkPushConfig|null $config
     * @param array $options
     * @return array
     */
    protected function pushSource(array $source, $config, array $options = [])
    {
        $userIds = $this->resolveUserIds($source, $config, $options);
        $groups = $this->resolveGroups($config, $options);
        if (empty($userIds) && $groups->isEmpty()) {
            return $this->result(false, '缺少企业微信接收人或接收群');
        }

        $summary = [
            'success' => 0,
            'fail' => 0,
            'skip' => 0,
            'logs' => [],
        ];

        foreach ($userIds as $userId) {
            $result = $this->sendToReceiver($source, $config, [
                'type' => 'user',
                'id' => $userId,
                'name' => $userId,
            ], $options);
            $this->mergeSendSummary($summary, $result);
        }

        foreach ($groups as $group) {
            $result = $this->sendToReceiver($source, $config, [
                'type' => 'group',
                'id' => $group->id,
                'name' => $group->name,
                'group' => $group,
            ], $options);
            $this->mergeSendSummary($summary, $result);
        }

        return $this->result(true, '企业微信推送处理完成', $summary);
    }

    /**
     * 发送给单个接收端。
     *
     * @param array $source
     * @param WechatWorkPushConfig|null $config
     * @param array $receiver
     * @param array $options
     * @return array
     */
    protected function sendToReceiver(array $source, $config, array $receiver, array $options)
    {
        $warningKey = $this->makeWarningKey($source);
        $repeatBatch = $options['repeat_batch'] ?? '';
        $skipReason = $this->getSkipReason($warningKey, $receiver, $config, $repeatBatch, $options);

        if ($skipReason !== '') {
            if (!empty($options['log_skipped'])) {
                $this->createPushLog($source, $receiver, $warningKey, 3, $skipReason, [], [], $repeatBatch);
            }

            return ['status' => 'skip', 'message' => $skipReason];
        }

        $pushLog = $this->createPushLog($source, $receiver, $warningKey, 0, '', [], [], $repeatBatch);
        if ($this->shouldSendTaskCard($config, $receiver)) {
            $taskId = $this->buildTaskId($pushLog);
            $eventKey = 'ack';
            $pushLog->update([
                'message_type' => 'taskcard',
                'task_id' => $taskId,
                'event_key' => $eventKey,
            ]);
            $sendResult = $this->client->sendTaskCardToUsers(
                [$receiver['id']],
                $source['title'] ?? '质控预警提醒',
                $this->buildTaskCardDescription($source),
                $taskId,
                $this->buildTaskCardButtons(),
                $this->buildTaskCardUrl($source, $pushLog, $options)
            );
        } elseif ($receiver['type'] === 'group') {
            $message = $this->buildMessage($source, $pushLog, $options);
            $sendResult = $this->client->sendTextToGroup($receiver['group'], $message);
        } else {
            $message = $this->buildMessage($source, $pushLog, $options);
            $sendResult = $this->client->sendTextToUsers([$receiver['id']], $message);
        }

        $sendStatus = $sendResult['success'] ? 1 : 2;
        $pushLog->update([
            'send_status' => $sendStatus,
            'send_time' => date('Y-m-d H:i:s'),
            'fail_reason' => $sendResult['success'] ? '' : $sendResult['message'],
            'request_body' => $this->jsonEncode($sendResult['request_body'] ?? []),
            'response_body' => $this->jsonEncode($sendResult['response_body'] ?? []),
        ]);

        return [
            'status' => $sendResult['success'] ? 'success' : 'fail',
            'message' => $sendResult['message'],
            'log_id' => $pushLog->id,
        ];
    }

    /**
     * 创建推送日志。
     *
     * @param array $source
     * @param array $receiver
     * @param string $warningKey
     * @param int $status
     * @param string $failReason
     * @param array $requestBody
     * @param array $responseBody
     * @param string $repeatBatch
     * @return WechatWorkPushLog
     */
    protected function createPushLog(array $source, array $receiver, $warningKey, $status, $failReason, array $requestBody, array $responseBody, $repeatBatch)
    {
        return WechatWorkPushLog::query()->create([
            'source_log_id' => intval($source['id'] ?? 0),
            'source_type' => 'quality_warning',
            'warning_key' => $warningKey,
            'patient_zyh' => $source['zyh'] ?? '',
            'patient_no' => $source['AAA28'] ?? '',
            'rule_id' => intval($source['rule_id'] ?? 0),
            'case_quality_id' => intval($source['case_quality_id'] ?? 0),
            'data_id' => (string) ($source['data_id'] ?? ''),
            'quality_type' => intval($source['quality_type'] ?? 0),
            'title' => $source['title'] ?? '质控预警提醒',
            'content' => $this->plainText($source['content'] ?? ''),
            'quality_content' => $this->plainText($source['quality_content'] ?? ''),
            'message_type' => 'text',
            'task_id' => '',
            'event_key' => '',
            'receiver_type' => $receiver['type'],
            'receiver_id' => (string) $receiver['id'],
            'receiver_name' => (string) $receiver['name'],
            'send_status' => $status,
            'send_time' => $status > 0 ? date('Y-m-d H:i:s') : null,
            'fail_reason' => $failReason,
            'request_body' => $this->jsonEncode($requestBody),
            'response_body' => $this->jsonEncode($responseBody),
            'read_status' => 0,
            'repeat_batch' => $repeatBatch,
        ]);
    }

    /**
     * 获取重复提醒跳过原因。
     *
     * @param string $warningKey
     * @param array $receiver
     * @param WechatWorkPushConfig|null $config
     * @param string $repeatBatch
     * @param array $options
     * @return string
     */
    protected function getSkipReason($warningKey, array $receiver, $config, $repeatBatch, array $options)
    {
        if (!empty($options['force'])) {
            return '';
        }

        $query = WechatWorkPushLog::query()
            ->where('warning_key', $warningKey)
            ->where('receiver_type', $receiver['type'])
            ->where('receiver_id', (string) $receiver['id'])
            ->where('send_status', 1);

        $repeatEnabled = $config && (int) $config->repeat_enabled === 1;
        if (!$repeatEnabled) {
            $skipUnchanged = !$config || (int) $config->unchanged_skip_enabled === 1;
            return $skipUnchanged && $query->exists() ? '重复提醒关闭，同一问题未变化已跳过' : '';
        }

        if ($config && (int) $config->remind_unread_only === 1) {
            $latestLog = (clone $query)->orderBy('id', 'desc')->first(['read_status']);
            if ($latestLog && (int) $latestLog->read_status === 1) {
                return '重复提醒仅提醒未读消息，该消息已查看';
            }
        }

        $repeatType = $config ? $config->repeat_type : 'fixed_times';
        if ($repeatType === 'interval') {
            $minutes = $config ? intval($config->repeat_interval_minutes) : 0;
            if ($minutes > 0) {
                $latestLog = (clone $query)->orderBy('send_time', 'desc')->first(['send_time']);
                if ($latestLog && strtotime($latestLog->send_time) > time() - $minutes * 60) {
                    return '未达到固定间隔重复提醒时间';
                }
            }

            return '';
        }

        if ($repeatBatch !== '' && (clone $query)->where('repeat_batch', $repeatBatch)->exists()) {
            return '当前定时批次已推送';
        }

        return '';
    }

    /**
     * 获取业务配置。
     *
     * @return WechatWorkPushConfig|null
     */
    protected function getConfig()
    {
        try {
            return WechatWorkPushConfig::getDefaultConfig();
        } catch (\Exception $e) {
            Log::warning('[企业微信] 推送配置读取失败', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 判断企业微信推送是否启用。
     *
     * @param WechatWorkPushConfig|null $config
     * @return bool
     */
    protected function isEnabled($config)
    {
        return $config && (int) $config->enabled === 1;
    }

    /**
     * 判断当前时间是否允许定时推送。
     *
     * @param WechatWorkPushConfig|null $config
     * @return bool
     */
    protected function shouldRunNow($config)
    {
        if (!$config || (int) $config->repeat_enabled !== 1) {
            return true;
        }

        if ($config->repeat_type === 'interval') {
            return true;
        }

        $times = $this->parseTimes($config->repeat_times);
        if (empty($times)) {
            return false;
        }

        return in_array(date('H:i'), $times, true);
    }

    /**
     * 生成重复提醒批次号。
     *
     * @param WechatWorkPushConfig|null $config
     * @return string
     */
    protected function makeRepeatBatch($config)
    {
        if (!$config || (int) $config->repeat_enabled !== 1) {
            return '';
        }

        return date('YmdHi');
    }

    /**
     * 解析源日志。
     *
     * @param int|array|QualitySendMsgLog $sourceLog
     * @return array
     */
    protected function resolveSourceLog($sourceLog)
    {
        if ($sourceLog instanceof Model) {
            return $sourceLog->toArray();
        }

        if (is_array($sourceLog)) {
            return $sourceLog;
        }

        $row = QualitySendMsgLog::query()->where('id', intval($sourceLog))->first();

        return $row ? $row->toArray() : [];
    }

    /**
     * 解析接收人。
     *
     * @param array $source
     * @param WechatWorkPushConfig|null $config
     * @param array $options
     * @return array
     */
    protected function resolveUserIds(array $source, $config, array $options)
    {
        if (array_key_exists('receiver_userids', $options)) {
            $optionUserIds = $this->parseList($options['receiver_userids']);
            if (!empty($optionUserIds)) {
                return $optionUserIds;
            }
        }
        if (array_key_exists('userids', $options)) {
            $optionUserIds = $this->parseList($options['userids']);
            if (!empty($optionUserIds)) {
                return $optionUserIds;
            }
        }

        $doctorIds = $this->parseList($source['doctor_id'] ?? '');
        $userIds = $this->resolveStaffWechatUserIds($doctorIds);
        $defaultUserIds = $config ? $this->parseList($config->default_userids) : [];

        return array_values(array_unique(array_merge($userIds, $defaultUserIds)));
    }

    /**
     * 将系统医师编码转换为企业微信UserID；无法映射时保留原值。
     *
     * @param array $doctorIds
     * @return array
     */
    protected function resolveStaffWechatUserIds(array $doctorIds)
    {
        if (empty($doctorIds)) {
            return [];
        }

        $wechatUserIds = [];
        try {
            $staffs = Staff::query()
                ->where(function ($query) use ($doctorIds) {
                    $query->whereIn('code', $doctorIds)
                        ->orWhereIn('base_code', $doctorIds)
                        ->orWhereIn('YGBH', $doctorIds)
                        ->orWhereIn('YHID', $doctorIds);
                })
                ->get(['code', 'base_code', 'YGBH', 'YHID'])
                ->toArray();

            foreach ($staffs as $staff) {
                if (!empty($staff['YHID'])) {
                    $wechatUserIds[] = $staff['YHID'];
                }
            }
        } catch (\Exception $e) {
            Log::warning('[企业微信] 医师企业微信账号映射失败', ['error' => $e->getMessage()]);
        }

        return !empty($wechatUserIds) ? array_values(array_unique($wechatUserIds)) : $doctorIds;
    }

    /**
     * 解析接收群。
     *
     * @param WechatWorkPushConfig|null $config
     * @param array $options
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function resolveGroups($config, array $options)
    {
        $groupIds = [];
        if (array_key_exists('group_ids', $options)) {
            $groupIds = $this->parseList($options['group_ids']);
        } elseif ($config) {
            $groupIds = $this->parseList($config->default_group_ids);
        }

        if (empty($groupIds)) {
            return WechatWorkGroup::query()->whereRaw('1 = 0')->get();
        }

        return WechatWorkGroup::query()
            ->where('enabled', 1)
            ->whereIn('id', $groupIds)
            ->get();
    }

    /**
     * 判断是否发送任务卡片消息。
     *
     * @param WechatWorkPushConfig|null $config
     * @param array $receiver
     * @return bool
     */
    protected function shouldSendTaskCard($config, array $receiver)
    {
        return $receiver['type'] === 'user'
            && $config
            && (string) $config->message_type === 'taskcard';
    }

    /**
     * 构建任务卡片ID。
     *
     * @param WechatWorkPushLog $pushLog
     * @return string
     */
    protected function buildTaskId(WechatWorkPushLog $pushLog)
    {
        return 'wechat_work_push_log_' . $pushLog->id;
    }

    /**
     * 构建任务卡片描述。
     *
     * @param array $source
     * @return string
     */
    protected function buildTaskCardDescription(array $source)
    {
        $lines = [];
        if (!empty($source['AAA28'])) {
            $lines[] = '病案号：' . $source['AAA28'];
        }
        if (!empty($source['zyh'])) {
            $lines[] = '住院号：' . $source['zyh'];
        }
        if (!empty($source['AAC11N'])) {
            $lines[] = '科室：' . $source['AAC11N'];
        }

        $qualityContent = $this->plainText($source['quality_content'] ?? '');
        if ($qualityContent !== '') {
            $lines[] = '' . $qualityContent;
        }

        $basis = $this->plainText($source['msg_yj'] ?? '');
        if ($basis === '') {
            $content = $this->plainText($source['content'] ?? '');
            if ($content !== '' && $content !== $qualityContent) {
                $basis = $content;
            }
        }
        if ($basis !== '') {
            $lines[] = '依据：' . $basis;
        }

        return $this->limitText(implode("\n", $lines), 512);
    }

    /**
     * 构建任务卡片按钮。
     *
     * @return array
     */
    protected function buildTaskCardButtons()
    {
        return [
            [
                'key' => 'ack',
                'name' => '待确认',
                'replace_name' => '已确认',
                'color' => 'green',
                'is_bold' => true,
            ],
        ];
    }

    /**
     * 构建任务卡片主体跳转地址。
     *
     * @param array $source
     * @param WechatWorkPushLog $pushLog
     * @param array $options
     * @return string
     */
    protected function buildTaskCardUrl(array $source, WechatWorkPushLog $pushLog, array $options = [])
    {
        $detailUrl = $this->buildDetailUrl($source);
        if ($detailUrl !== '') {
            return $detailUrl;
        }

        return $this->buildAckUrl($pushLog, $options);
    }

    /**
     * 构建企业微信消息内容。
     *
     * @param array $source
     * @param WechatWorkPushLog|null $pushLog
     * @param array $options
     * @return string
     */
    protected function buildMessage(array $source, WechatWorkPushLog $pushLog = null, array $options = [])
    {
        $title = $source['title'] ?? '质控预警提醒';
        $lines = [
            '【' . $title . '】',
        ];

        if (!empty($source['AAA28'])) {
            $lines[] = '病案号：' . $source['AAA28'];
        }
        if (!empty($source['zyh'])) {
            $lines[] = '住院号：' . $source['zyh'];
        }
        if (!empty($source['AAC11N'])) {
            $lines[] = '科室：' . $source['AAC11N'];
        }
        if (!empty($source['rule_id'])) {
            $lines[] = '规则ID：' . $source['rule_id'];
        }

        $qualityContent = $this->plainText($source['quality_content'] ?? '');
        if ($qualityContent !== '') {
            $lines[] = '' . $qualityContent;
        }

        $basis = $this->plainText($source['msg_yj'] ?? '');
        if ($basis === '') {
            $content = $this->plainText($source['content'] ?? '');
            if ($content !== '' && $content !== $qualityContent) {
                $basis = $content;
            }
        }
        if ($basis !== '') {
            $lines[] = '依据：' . $basis;
        }

        $detailUrl = $this->buildDetailUrl($source);
        if ($detailUrl !== '') {
            $lines[] = '详情：' . $detailUrl;
        }

        $ackUrl = $this->buildAckUrl($pushLog, $options);
        if ($ackUrl !== '') {
            $lines[] = '已收到：' . $ackUrl;
        }

        $message = implode("\n", $lines);

        return $this->limitText($message, 1800);
    }

    /**
     * 生成预警去重标识。
     *
     * @param array $source
     * @return string
     */
    protected function makeWarningKey(array $source)
    {
        if (!empty($source['warning_key'])) {
            return $source['warning_key'];
        }

        $problem = $this->plainText($source['quality_content'] ?? ($source['content'] ?? ($source['msg_yj'] ?? '')));
        if ($problem === '') {
            $problem = (string) ($source['data_id'] ?? ($source['case_quality_id'] ?? ''));
        }

        return sha1(implode('|', [
            $source['zyh'] ?? '',
            $source['AAA28'] ?? '',
            $source['rule_id'] ?? '',
            $source['quality_type'] ?? '',
            md5($problem),
        ]));
    }

    /**
     * 根据配置模板生成详情地址。
     *
     * @param array $source
     * @return string
     */
    protected function buildDetailUrl(array $source)
    {
        $config = $this->getConfig();
        $template = $config ? (string) $config->detail_url_template : '';
        if ($template === '') {
            return '';
        }

        return strtr($template, [
            '{source_log_id}' => (string) ($source['id'] ?? ''),
            '{warning_key}' => $this->makeWarningKey($source),
            '{zyh}' => (string) ($source['zyh'] ?? ''),
            '{aaa28}' => (string) ($source['AAA28'] ?? ''),
            '{case_quality_id}' => (string) ($source['case_quality_id'] ?? ''),
        ]);
    }

    /**
     * 生成点击确认已收到链接。
     *
     * @param WechatWorkPushLog|null $pushLog
     * @param array $options
     * @return string
     */
    protected function buildAckUrl($pushLog, array $options = [])
    {
        if (!$pushLog || empty($pushLog->id)) {
            return '';
        }

        $baseUrl = $this->resolveAckBaseUrl($options);
        if ($baseUrl === '') {
            return '';
        }

        return rtrim($baseUrl, '/') . '/api/wechat_work_notification/ack?log_id=' . $pushLog->id;
    }

    /**
     * 获取确认链接基础地址。
     *
     * @param array $options
     * @return string
     */
    protected function resolveAckBaseUrl(array $options = [])
    {
        if (!empty($options['ack_base_url'])) {
            return (string) $options['ack_base_url'];
        }

        $config = $this->getConfig();
        $detailUrlTemplate = $config ? (string) $config->detail_url_template : '';
        if ($detailUrlTemplate !== '') {
            $scheme = parse_url($detailUrlTemplate, PHP_URL_SCHEME);
            $host = parse_url($detailUrlTemplate, PHP_URL_HOST);
            $port = parse_url($detailUrlTemplate, PHP_URL_PORT);
            if (!empty($scheme) && !empty($host)) {
                return $scheme . '://' . $host . (!empty($port) ? ':' . $port : '');
            }
        }

        return (string) config('app.url', '');
    }

    /**
     * 解析逗号分隔配置。
     *
     * @param mixed $value
     * @return array
     */
    protected function parseList($value)
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $value = str_replace(['，', '|', ';', ' '], ',', (string) $value);
            $items = explode(',', $value);
        }

        $result = [];
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $result[] = $item;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * 解析定时推送时间。
     *
     * @param string $value
     * @return array
     */
    protected function parseTimes($value)
    {
        $items = $this->parseList($value);
        $times = [];
        foreach ($items as $item) {
            if (preg_match('/^\d{1,2}$/', $item)) {
                $item .= ':00';
            }
            if (preg_match('/^(\d{1,2}):(\d{1,2})$/', $item, $matches)) {
                $hour = min(23, max(0, intval($matches[1])));
                $minute = min(59, max(0, intval($matches[2])));
                $times[] = sprintf('%02d:%02d', $hour, $minute);
            }
        }

        return array_values(array_unique($times));
    }

    /**
     * 合并发送统计。
     *
     * @param array $summary
     * @param array $result
     * @return void
     */
    protected function mergeSendSummary(array &$summary, array $result)
    {
        if ($result['status'] === 'success') {
            $summary['success']++;
        } elseif ($result['status'] === 'skip') {
            $summary['skip']++;
        } else {
            $summary['fail']++;
        }

        $summary['logs'][] = $result;
    }

    /**
     * 清理文本并保留换行。
     *
     * @param mixed $value
     * @return string
     */
    protected function plainText($value)
    {
        if (is_array($value)) {
            $value = $this->jsonEncode($value);
        }

        $value = (string) $value;
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $value = $this->jsonEncode($decoded);
        }

        $value = strip_tags($value);
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $lines = explode("\n", $value);
        foreach ($lines as &$line) {
            $line = preg_replace('/[\t \x{00A0}]+/u', ' ', $line);
            $line = trim($line);
        }
        unset($line);

        return trim(implode("\n", $lines));
    }

    /**
     * 限制消息长度。
     *
     * @param string $text
     * @param int $limit
     * @return string
     */
    protected function limitText($text, $limit)
    {
        if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > $limit) {
            return mb_substr($text, 0, $limit, 'UTF-8') . '...';
        }

        if (!function_exists('mb_strlen') && strlen($text) > $limit) {
            return substr($text, 0, $limit) . '...';
        }

        return $text;
    }

    /**
     * JSON编码，避免中文转义。
     *
     * @param mixed $value
     * @return string
     */
    protected function jsonEncode($value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 返回统一结构。
     *
     * @param bool $success
     * @param string $message
     * @param array $data
     * @return array
     */
    protected function result($success, $message, array $data = [])
    {
        return [
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ];
    }
}
