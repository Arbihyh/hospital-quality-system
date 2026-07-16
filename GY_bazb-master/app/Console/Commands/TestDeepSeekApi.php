<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestDeepSeekApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:deepseek-api {--message=测试消息} {--model=DeepSeek-R1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试神思 DeepSeek-R1 接口';

    /**
     * API配置
     */
    private $apiUrl = 'http://11.31.67.10:8888/ssp/openApi/E83FzQvW/KEaFFBXT/6bLTMHHE/v1/chat/completions';
    private $apiKey = '07477bee76e24b1eb1ad3a3785f2d8d2';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $message = $this->option('message');
        $model = $this->option('model');

        $this->info("开始测试神思 DeepSeek-R1 接口...");
        $this->info("测试消息: {$message}");
        $this->info("使用模型: {$model}");

        try {
            // 测试基本对话
            $this->testBasicChat($message, $model);
            
            // 测试流式响应
            $this->testStreamChat($message, $model);
            
            $this->info("✅ 所有测试完成");
            
        } catch (\Exception $e) {
            $this->error("❌ 测试失败: " . $e->getMessage());
            Log::error('DeepSeek API测试失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * 测试基本对话功能
     */
    private function testBasicChat($message, $model)
    {
        $this->info("\n=== 测试基本对话功能 ===");
        
        $requestData = [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $message
                ]
            ],
            'model' => $model,
            'stream' => false
        ];

        $this->info("发送请求数据: " . json_encode($requestData, JSON_UNESCAPED_UNICODE));

        $response = Http::withHeaders([
            'Authorization' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->timeout(60)->post($this->apiUrl, $requestData);

        if ($response->successful()) {
            $responseData = $response->json();
            $this->info("✅ 基本对话测试成功");
            $this->info("响应状态码: " . $response->status());
            $this->info("响应数据: " . json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            // 解析响应内容
            if (isset($responseData['choices'][0]['delta']['content'])) {
                $content = $responseData['choices'][0]['delta']['content'];
                $this->info("AI回复内容: " . $content);
            }
            
        } else {
            $this->error("❌ 基本对话测试失败");
            $this->error("状态码: " . $response->status());
            $this->error("错误信息: " . $response->body());
            throw new \Exception("API请求失败: " . $response->body());
        }
    }

    /**
     * 测试流式响应功能
     */
    private function testStreamChat($message, $model)
    {
        $this->info("\n=== 测试流式响应功能 ===");
        
        $requestData = [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $message . "（请简短回复）"
                ]
            ],
            'model' => $model,
            'stream' => true
        ];

        $this->info("发送流式请求数据: " . json_encode($requestData, JSON_UNESCAPED_UNICODE));

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->timeout(60)->post($this->apiUrl, $requestData);

            if ($response->successful()) {
                $this->info("✅ 流式响应测试成功");
                $this->info("响应状态码: " . $response->status());
                
                // 处理流式响应
                $responseBody = $response->body();
                $this->info("流式响应内容: " . $responseBody);
                
            } else {
                $this->error("❌ 流式响应测试失败");
                $this->error("状态码: " . $response->status());
                $this->error("错误信息: " . $response->body());
            }
            
        } catch (\Exception $e) {
            $this->error("❌ 流式响应测试异常: " . $e->getMessage());
        }
    }

    /**
     * 测试多轮对话
     */
    private function testMultiTurnChat()
    {
        $this->info("\n=== 测试多轮对话功能 ===");
        
        $messages = [
            [
                'role' => 'user',
                'content' => '你好，请介绍一下你自己'
            ],
            [
                'role' => 'assistant',
                'content' => '你好！我是DeepSeek-R1，一个AI助手。'
            ],
            [
                'role' => 'user',
                'content' => '你能帮我做什么？'
            ]
        ];

        $requestData = [
            'messages' => $messages,
            'model' => $this->option('model'),
            'stream' => false
        ];

        $response = Http::withHeaders([
            'Authorization' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->timeout(60)->post($this->apiUrl, $requestData);

        if ($response->successful()) {
            $this->info("✅ 多轮对话测试成功");
            $responseData = $response->json();
            $this->info("多轮对话响应: " . json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->error("❌ 多轮对话测试失败: " . $response->body());
        }
    }

    /**
     * 验证API连接性
     */
    private function validateApiConnection()
    {
        $this->info("验证API连接性...");
        
        try {
            $response = Http::timeout(10)->get($this->apiUrl);
            $this->info("API连接状态: " . $response->status());
        } catch (\Exception $e) {
            $this->error("API连接失败: " . $e->getMessage());
        }
    }
}
