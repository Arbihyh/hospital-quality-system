<?php
namespace App\Services;
use Kafka;
use Kafka\ConsumerConfig;
use Kafka\Producer;
use Kafka\ProducerConfig;

class KafkaService
{
    public function __construct()
    {
        date_default_timezone_set('PRC');
    }

    /*
     * Produce
     */
    public function Producer($topic, $value , $url)
    {
        $config = ProducerConfig::getInstance();
        $config->setMetadataRefreshIntervalMs(10000);
        $config->setMetadataBrokerList($url);
        $config->setBrokerVersion('1.0.0');
        $config->setRequiredAck(1);
        $config->setIsAsyn(false);
        $config->setProduceInterval(500);
        $producer = new Producer(function () use($value,$topic){
            return [
                [
                    'topic' => $topic,
                    'value' => $value,
                    'key' => '',
                ],
            ];
        });
        $producer->success(function ($result){
            return "success";
        });
        $producer->error(function ($errorCode){
            var_dump($errorCode);
        });
        $producer->send(true);
    }

    /*
     * Consumer
     */
    public function consumer($group,$topics , $url){
        $config = ConsumerConfig::getInstance();
        $config->setMetadataRefreshIntervalMs(500);
        $config->setMetadataBrokerList($url);
        $config->setGroupId($group);
        $config->setBrokerVersion('1.0.0');
        $config->setTopics([$topics]);
        $config->setOffsetReset('earliest');
        $consumer = new \Kafka\Consumer();
        $consumer->start(function($topic, $part, $message) {
            echo "receive a message...\n";
            app('consumerKafka')->consumerData($message['message']['value']);  //你的接收处理逻辑
            var_dump($message['message']['value']);
        });
    }
}
