<?php

namespace App\Services;

use App\Model\WechatWorkPushConfig;
use App\Model\WechatWorkGroup;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WechatWorkClientService
{
    const DEFAULT_API_BASE = 'https://qyapi.weixin.qq.com';

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var WechatWorkPushConfig|null
     */
    protected $config;

    public function __construct(WechatWorkPushConfig $config = null)
    {
        $this->config = $config ?: WechatWorkPushConfig::getDefaultConfig();
        $timeout = $this->config ? (float) $this->config->timeout : 5;
        $this->client = new Client([
            'timeout' => $timeout > 0 ? $timeout : 5,
        ]);
    }

    /**
     * 发送应用文本消息给企业微信用户。
     *
     * @param array $userIds
     * @param string $content
     * @return array
     */
    public function sendTextToUsers(array $userIds, $content)
    {
        $userIds = $this->filterList($userIds);
        if (empty($userIds)) {
            return $this->failResult('缺少企业微信接收人UserID');
        }

        $agentId = $this->config ? (string) $this->config->agent_id : '';
        if ($agentId === '') {
            return $this->failResult('缺少企业微信应用AgentId配置');
        }

        $body = [
            'touser' => implode('|', $userIds),
            'msgtype' => 'text',
            'agentid' => is_numeric($agentId) ? intval($agentId) : $agentId,
            'text' => [
                'content' => $content,
            ],
            'safe' => 0,
        ];

        return $this->postApi('/cgi-bin/message/send', $body);
    }

    /**
     * 发送任务卡片消息给企业微信用户。
     *
     * @param array $userIds
     * @param string $title
     * @param string $description
     * @param string $taskId
     * @param array $buttons
     * @param string $url
     * @return array
     */
    public function sendTaskCardToUsers(array $userIds, $title, $description, $taskId, array $buttons, $url = '')
    {
        $userIds = $this->filterList($userIds);
        if (empty($userIds)) {
            return $this->failResult('缺少企业微信接收人UserID');
        }

        $agentId = $this->config ? (string) $this->config->agent_id : '';
        if ($agentId === '') {
            return $this->failResult('缺少企业微信应用AgentId配置');
        }

        if ($taskId === '') {
            return $this->failResult('缺少企业微信任务卡片task_id');
        }

        $body = [
            'touser' => implode('|', $userIds),
            'msgtype' => 'taskcard',
            'agentid' => is_numeric($agentId) ? intval($agentId) : $agentId,
            'taskcard' => [
                'title' => $title,
                'description' => $description,
                'url' => $url,
                'task_id' => $taskId,
                'btn' => $buttons,
            ],
        ];

        return $this->postApi('/cgi-bin/message/send', $body);
    }

    /**
     * 更新任务卡片按钮状态。
     *
     * @param array $userIds
     * @param string $taskId
     * @param string $clickedKey
     * @return array
     */
    public function updateTaskCard(array $userIds, $taskId, $clickedKey)
    {
        $userIds = $this->filterList($userIds);
        if (empty($userIds)) {
            return $this->failResult('缺少企业微信接收人UserID');
        }

        $agentId = $this->config ? (string) $this->config->agent_id : '';
        if ($agentId === '') {
            return $this->failResult('缺少企业微信应用AgentId配置');
        }

        $body = [
            'userids' => $userIds,
            'agentid' => is_numeric($agentId) ? intval($agentId) : $agentId,
            'task_id' => $taskId,
            'clicked_key' => $clickedKey,
        ];

        return $this->postApi('/cgi-bin/message/update_taskcard', $body);
    }

    /**
     * 发送企业微信群文本消息。
     *
     * @param WechatWorkGroup $group
     * @param string $content
     * @return array
     */
    public function sendTextToGroup(WechatWorkGroup $group, $content)
    {
        if ((int) $group->enabled !== 1) {
            return $this->failResult('企业微信群配置已停用');
        }

        if ($group->send_type === 'webhook') {
            return $this->sendWebhookText($group, $content);
        }

        return $this->sendAppChatText($group, $content);
    }

    /**
     * 使用应用群聊chatid发送群消息。
     *
     * @param WechatWorkGroup $group
     * @param string $content
     * @return array
     */
    protected function sendAppChatText(WechatWorkGroup $group, $content)
    {
        if (empty($group->chat_id)) {
            return $this->failResult('缺少企业微信应用群聊chatid');
        }

        $body = [
            'chatid' => $group->chat_id,
            'msgtype' => 'text',
            'text' => [
                'content' => $content,
            ],
            'safe' => 0,
        ];

        return $this->postApi('/cgi-bin/appchat/send', $body);
    }

    /**
     * 使用群机器人webhook发送群消息。
     *
     * @param WechatWorkGroup $group
     * @param string $content
     * @return array
     */
    protected function sendWebhookText(WechatWorkGroup $group, $content)
    {
        $webhookUrl = $this->buildWebhookUrl($group);
        if (empty($webhookUrl)) {
            return $this->failResult('缺少企业微信群机器人webhook配置');
        }

        $body = [
            'msgtype' => 'text',
            'text' => [
                'content' => $content,
            ],
        ];

        return $this->request('POST', $webhookUrl, ['json' => $body]);
    }

    /**
     * 调用需要access_token的企业微信接口。
     *
     * @param string $path
     * @param array $body
     * @return array
     */
    protected function postApi($path, array $body)
    {
        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            return $this->failResult('缺少企业微信access_token');
        }

        return $this->request('POST', $this->getApiBaseUrl() . $path, [
            'query' => ['access_token' => $accessToken],
            'json' => $body,
        ]);
    }

    /**
     * 获取企业微信API基础地址。
     *
     * @return string
     */
    protected function getApiBaseUrl()
    {
        $apiBaseUrl = $this->config ? trim((string) $this->config->api_base_url) : '';

        return rtrim($apiBaseUrl !== '' ? $apiBaseUrl : self::DEFAULT_API_BASE, '/');
    }

    /**
     * 获取企业微信access_token，优先通过CorpID和Secret动态换取。
     *
     * @return string
     */
    protected function getAccessToken()
    {
        $corpId = $this->config ? (string) $this->config->corp_id : '';
        $secret = $this->config ? (string) $this->config->secret : '';
        $configuredToken = $this->config ? (string) $this->config->access_token : '';

        if (!empty($corpId) && !empty($secret)) {
            $cacheKey = 'wechat_work:access_token:' . md5($this->getApiBaseUrl() . '|' . $corpId . '|' . $secret);
            try {
                return Cache::remember($cacheKey, 7000, function () use ($corpId, $secret) {
                    return $this->fetchAccessToken($corpId, $secret);
                });
            } catch (\Exception $e) {
                Log::warning('[企业微信] access_token缓存读取失败', [
                    'error' => $e->getMessage(),
                ]);

                return $this->fetchAccessToken($corpId, $secret);
            }
        }

        return (string) $configuredToken;
    }

    /**
     * 向企业微信换取access_token。
     *
     * @param string $corpId
     * @param string $secret
     * @return string
     */
    protected function fetchAccessToken($corpId, $secret)
    {
        $response = $this->request('GET', $this->getApiBaseUrl() . '/cgi-bin/gettoken', [
            'query' => [
                'corpid' => $corpId,
                'corpsecret' => $secret,
            ],
        ]);

        if (!$response['success']) {
            throw new \RuntimeException($response['message']);
        }

        $body = $response['response_body'];
        if (empty($body['access_token'])) {
            throw new \RuntimeException('企业微信access_token响应为空');
        }

        return $body['access_token'];
    }

    /**
     * 发起HTTP请求并统一解析企业微信响应。
     *
     * @param string $method
     * @param string $url
     * @param array $options
     * @return array
     */
    protected function request($method, $url, array $options)
    {
        try {
            $response = $this->client->request($method, $url, $options);
            $bodyText = (string) $response->getBody();
            $body = json_decode($bodyText, true);
            $body = is_array($body) ? $body : ['raw' => $bodyText];
            $errCode = isset($body['errcode']) ? intval($body['errcode']) : 0;
            $success = $errCode === 0;

            return [
                'success' => $success,
                'message' => $success ? '发送成功' : ($body['errmsg'] ?? '企业微信接口返回失败'),
                'request_body' => $options['json'] ?? [],
                'response_body' => $body,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'request_body' => $options['json'] ?? [],
                'response_body' => [],
            ];
        }
    }

    /**
     * 组装群机器人webhook地址。
     *
     * @param WechatWorkGroup $group
     * @return string
     */
    protected function buildWebhookUrl(WechatWorkGroup $group)
    {
        if (!empty($group->webhook_url)) {
            return $group->webhook_url;
        }

        if (!empty($group->webhook_key)) {
            return $this->getApiBaseUrl() . '/cgi-bin/webhook/send?key=' . $group->webhook_key;
        }

        return '';
    }

    /**
     * 过滤空接收人。
     *
     * @param array $list
     * @return array
     */
    protected function filterList(array $list)
    {
        $result = [];
        foreach ($list as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $result[] = $item;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * 返回失败结构。
     *
     * @param string $message
     * @return array
     */
    protected function failResult($message)
    {
        return [
            'success' => false,
            'message' => $message,
            'request_body' => [],
            'response_body' => [],
        ];
    }
}
