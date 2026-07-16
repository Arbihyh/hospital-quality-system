<?php

namespace App\Console\Commands;

use App\Services\QualityWechatWorkNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WechatWorkWarningPush extends Command
{
    /**
     * 命令名称。
     *
     * @var string
     */
    protected $signature = 'wechat-work:push-warning {--force : 忽略推送时间策略立即执行} {--limit=200 : 单次最多处理数量}';

    /**
     * 命令描述。
     *
     * @var string
     */
    protected $description = '按企业微信推送策略发送未处理质控预警';

    /**
     * 执行命令。
     *
     * @return int
     */
    public function handle()
    {
        try {
            $service = new QualityWechatWorkNotificationService();
            $result = $service->pushPendingWarnings([
                'force' => (bool) $this->option('force'),
                'limit' => intval($this->option('limit')),
            ]);

            if ($result['success']) {
                $this->info($result['message']);
                $this->line(json_encode($result['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                return 0;
            }

            $this->warn($result['message']);
            return 1;
        } catch (\Exception $e) {
            Log::error('[企业微信] 定时推送质控预警失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('企业微信定时推送失败：' . $e->getMessage());
            return 1;
        }
    }
}
