<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\WechatWorkGroup;
use App\Model\WechatWorkPushConfig;
use App\Model\WechatWorkPushLog;
use App\Services\WechatWorkCallbackCryptoService;
use App\Services\WechatWorkClientService;
use App\Services\QualityWechatWorkNotificationService;
use App\Services\ToolsService;
use Illuminate\Http\Request;

class WechatWorkNotificationController extends Controller
{
    /**
     * 获取企业微信推送配置。
     *
     * @return array
     */
    public function config()
    {
        $config = WechatWorkPushConfig::getDefaultConfig();
        $groups = WechatWorkGroup::query()->orderBy('id', 'desc')->get()->toArray();
        $configData = $config ? $this->formatConfig($config->toArray()) : new \ArrayObject();

        return ToolsService::returnData(200, [
            'config' => $configData,
            'groups' => $groups,
        ], '');
    }

    /**
     * 保存企业微信推送策略。
     *
     * @param Request $request
     * @return array
     */
    public function saveConfig(Request $request)
    {
        $user = $request->user() ?: [];
        $userId = intval($user['id'] ?? 0);
        $config = WechatWorkPushConfig::getDefaultConfig();
        $data = [
            'name' => $request->post('name', '质控预警默认策略'),
            'enabled' => intval($request->post('enabled', 1)),
            'corp_id' => $this->configValue($request, $config, 'corp_id'),
            'secret' => $this->configValue($request, $config, 'secret'),
            'agent_id' => $this->configValue($request, $config, 'agent_id'),
            'access_token' => $this->configValue($request, $config, 'access_token'),
            'api_base_url' => $this->configValue($request, $config, 'api_base_url', WechatWorkClientService::DEFAULT_API_BASE),
            'detail_url_template' => $this->configValue($request, $config, 'detail_url_template'),
            'timeout' => intval($this->configValue($request, $config, 'timeout', 5)),
            'message_type' => $this->configValue($request, $config, 'message_type', 'taskcard'),
            'callback_url' => $this->configValue($request, $config, 'callback_url'),
            'callback_token' => $this->configValue($request, $config, 'callback_token'),
            'callback_aes_key' => $this->configValue($request, $config, 'callback_aes_key'),
            'repeat_enabled' => intval($request->post('repeat_enabled', 0)),
            'repeat_type' => $request->post('repeat_type', 'fixed_times'),
            'repeat_interval_minutes' => intval($request->post('repeat_interval_minutes', 0)),
            'repeat_times' => $this->implodeValue($request->post('repeat_times', '08:00,14:00,16:00,18:00')),
            'default_userids' => $this->implodeValue($request->post('default_userids', '')),
            'default_group_ids' => $this->implodeValue($request->post('default_group_ids', '')),
            'unchanged_skip_enabled' => intval($request->post('unchanged_skip_enabled', 1)),
            'remind_unread_only' => intval($request->post('remind_unread_only', 0)),
            'updated_by' => $userId,
        ];

        if ($config) {
            $config->update($data);
        } else {
            $data['created_by'] = $userId;
            $config = WechatWorkPushConfig::query()->create($data);
        }

        return ToolsService::returnData(200, $this->formatConfig($config->toArray()), '保存成功');
    }

    /**
     * 获取企业微信群配置列表。
     *
     * @return array
     */
    public function groupList()
    {
        $list = WechatWorkGroup::query()->orderBy('id', 'desc')->get()->toArray();

        return ToolsService::returnData(200, $list, '');
    }

    /**
     * 保存企业微信群配置。
     *
     * @param Request $request
     * @return array
     */
    public function saveGroup(Request $request)
    {
        $id = intval($request->post('id', 0));
        $name = trim((string) $request->post('name', ''));
        if ($name === '') {
            return ToolsService::returnData(1, [], '群名称不能为空');
        }

        $sendType = $request->post('send_type', 'appchat');
        if (!in_array($sendType, ['appchat', 'webhook'], true)) {
            return ToolsService::returnData(1, [], '群发送方式不正确');
        }

        $user = $request->user() ?: [];
        $userId = intval($user['id'] ?? 0);
        $data = [
            'name' => $name,
            'send_type' => $sendType,
            'chat_id' => $request->post('chat_id', ''),
            'agent_id' => $request->post('agent_id', ''),
            'webhook_key' => $request->post('webhook_key', ''),
            'webhook_url' => $request->post('webhook_url', ''),
            'enabled' => intval($request->post('enabled', 1)),
            'remark' => $request->post('remark', ''),
            'updated_by' => $userId,
        ];

        if ($id > 0) {
            $group = WechatWorkGroup::query()->where('id', $id)->first();
            if (!$group) {
                return ToolsService::returnData(1, [], '群配置不存在');
            }
            $group->update($data);
        } else {
            $data['created_by'] = $userId;
            $group = WechatWorkGroup::query()->create($data);
        }

        return ToolsService::returnData(200, $group->toArray(), '保存成功');
    }

    /**
     * 删除企业微信群配置。
     *
     * @param Request $request
     * @return array
     */
    public function deleteGroup(Request $request)
    {
        $id = intval($request->post('id', 0));
        if ($id <= 0) {
            return ToolsService::returnData(1, [], '缺少群配置ID');
        }

        WechatWorkGroup::query()->where('id', $id)->delete();

        return ToolsService::returnData(200, [], '删除成功');
    }

    /**
     * 手动推送指定质控预警。
     *
     * @param Request $request
     * @return array
     */
    public function sendWarning(Request $request)
    {
        $logId = intval($request->post('log_id', $request->post('source_log_id', 0)));
        if ($logId <= 0) {
            return ToolsService::returnData(1, [], '缺少质控提醒日志ID');
        }

        $service = new QualityWechatWorkNotificationService();
        $result = $service->pushQualityLog($logId, [
            'force' => intval($request->post('force', 0)) === 1,
            'receiver_userids' => $this->parseList($request->post('userids', $request->post('receiver_userids', []))),
            'group_ids' => $this->parseList($request->post('group_ids', [])),
            'log_skipped' => true,
        ]);

        return ToolsService::returnData($result['success'] ? 200 : 1, $result['data'] ?? [], $result['message']);
    }

    /**
     * 发送企业微信测试消息。
     *
     * @param Request $request
     * @return array
     */
    public function sendTest(Request $request)
    {
        $title = $request->post('title', '质控预警企业微信测试');
        $content = $request->post('content', '这是一条企业微信推送测试消息');
        $qualityContent = $request->post('quality_content', $request->post('problem', $content));
        $basis = $request->post('basis', $request->post('msg_yj', ''));
        $service = new QualityWechatWorkNotificationService();
        $result = $service->pushCustomMessage(
            $title,
            $content,
            $this->parseList($request->post('userids', [])),
            $this->parseList($request->post('group_ids', [])),
            $qualityContent,
            $basis
        );

        return ToolsService::returnData($result['success'] ? 200 : 1, $result['data'] ?? [], $result['message']);
    }

    /**
     * 查询企业微信推送日志。
     *
     * @param Request $request
     * @return array
     */
    public function logList(Request $request)
    {
        $page = max(1, intval($request->post('page', 1)));
        $pageSize = max(1, intval($request->post('page_size', $request->post('len', 20))));
        $query = WechatWorkPushLog::query();

        $this->applyLogFilters($query, $request);

        $count = $query->count();
        $list = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get()
            ->toArray();

        return ToolsService::returnData(200, [
            'count' => $count,
            'list' => $list,
        ], '');
    }

    /**
     * 标记企业微信预警为已查看。
     *
     * @param Request $request
     * @return array
     */
    public function markRead(Request $request)
    {
        $user = $request->user() ?: [];
        $service = new QualityWechatWorkNotificationService();
        $result = $service->markRead([
            'log_id' => $request->post('log_id', 0),
            'source_log_id' => $request->post('source_log_id', 0),
            'warning_key' => $request->post('warning_key', ''),
            'user_id' => $request->post('user_id', $user['id'] ?? 0),
            'wechat_userid' => $request->post('wechat_userid', ''),
        ]);

        return ToolsService::returnData($result['success'] ? 200 : 1, $result['data'] ?? [], $result['message']);
    }

    /**
     * 点击企业微信消息中的“已收到”链接后标记为已查看。
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function ack(Request $request)
    {
        $service = new QualityWechatWorkNotificationService();
        $result = $service->markRead([
            'log_id' => $request->get('log_id', 0),
            'source_log_id' => $request->get('source_log_id', 0),
            'warning_key' => $request->get('warning_key', ''),
            'user_id' => $request->get('user_id', 0),
            'wechat_userid' => $request->get('wechat_userid', ''),
        ]);

        $message = $result['success'] ? '已标记为已收到' : $result['message'];
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>企业微信预警确认</title>'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"></head>'
            . '<body style="font-family:Arial, sans-serif;text-align:center;padding:48px 16px;">'
            . '<h3>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</h3>'
            . '<p style="color:#666;">' . date('Y-m-d H:i:s') . '</p>'
            . '</body></html>';

        return response($html, $result['success'] ? 200 : 404);
    }

    /**
     * 企业微信应用回调，处理任务卡片按钮点击事件。
     *
     * @param Request $request
     * @return \Illuminate\Http\Response|string
     */
    public function callback(Request $request)
    {
        $config = WechatWorkPushConfig::getDefaultConfig();
        $crypto = new WechatWorkCallbackCryptoService($config);
        $signature = $request->get('msg_signature', '');
        $timestamp = $request->get('timestamp', '');
        $nonce = $request->get('nonce', '');

        if ($request->isMethod('get')) {
            $verify = $crypto->verifyUrl($signature, $timestamp, $nonce, $request->get('echostr', ''));
            if (!$verify['success']) {
                return response($verify['message'], 403);
            }

            return response($verify['data']['xml'] ?? '', 200);
        }

        $decrypt = $crypto->decryptMessage($signature, $timestamp, $nonce, $request->getContent());
        if (!$decrypt['success']) {
            return response('fail', 200);
        }

        $event = $crypto->xmlToArray($decrypt['data']['xml'] ?? '');
        if (($event['MsgType'] ?? '') === 'event' && ($event['Event'] ?? '') === 'taskcard_click') {
            $service = new QualityWechatWorkNotificationService();
            $service->handleTaskCardClick(
                (string) ($event['TaskId'] ?? ''),
                (string) ($event['EventKey'] ?? ''),
                (string) ($event['FromUserName'] ?? '')
            );
        }

        return response('success', 200);
    }

    /**
     * 应用日志查询条件。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     * @return void
     */
    protected function applyLogFilters($query, Request $request)
    {
        $filters = [
            'source_log_id',
            'warning_key',
            'patient_zyh',
            'patient_no',
            'rule_id',
            'receiver_type',
            'receiver_id',
            'send_status',
            'read_status',
        ];

        foreach ($filters as $field) {
            $value = $request->post($field, '');
            if ($value !== '') {
                $query->where($field, $value);
            }
        }

        $keyword = trim((string) $request->post('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->where('title', 'like', '%' . $keyword . '%')
                    ->orWhere('content', 'like', '%' . $keyword . '%')
                    ->orWhere('quality_content', 'like', '%' . $keyword . '%');
            });
        }

        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        if ($startTime !== '') {
            $query->where('created_at', '>=', $startTime);
        }
        if ($endTime !== '') {
            $query->where('created_at', '<=', $endTime);
        }
    }

    /**
     * 将数组或字符串统一为逗号分隔字符串。
     *
     * @param mixed $value
     * @return string
     */
    protected function implodeValue($value)
    {
        if (is_array($value)) {
            return implode(',', $this->parseList($value));
        }

        return trim((string) $value);
    }

    /**
     * 获取配置字段值；请求未传时保留数据库原值。
     *
     * @param Request $request
     * @param WechatWorkPushConfig|null $config
     * @param string $field
     * @param mixed $default
     * @return mixed
     */
    protected function configValue(Request $request, $config, $field, $default = '')
    {
        if ($request->has($field)) {
            return $request->post($field, $default);
        }

        return $config ? $config->{$field} : $default;
    }

    /**
     * 格式化配置返回，避免接口直接暴露完整密钥。
     *
     * @param array $config
     * @return array
     */
    protected function formatConfig(array $config)
    {
        if (!empty($config['secret'])) {
            $config['secret_mask'] = $this->maskSecret($config['secret']);
            unset($config['secret']);
        }
        if (!empty($config['access_token'])) {
            $config['access_token_mask'] = $this->maskSecret($config['access_token']);
            unset($config['access_token']);
        }
        if (!empty($config['callback_aes_key'])) {
            $config['callback_aes_key_mask'] = $this->maskSecret($config['callback_aes_key']);
            unset($config['callback_aes_key']);
        }

        return $config;
    }

    /**
     * 密钥脱敏显示。
     *
     * @param string $value
     * @return string
     */
    protected function maskSecret($value)
    {
        $value = (string) $value;
        $length = strlen($value);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 4) . str_repeat('*', max(0, $length - 8)) . substr($value, -4);
    }

    /**
     * 解析列表参数。
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
}
