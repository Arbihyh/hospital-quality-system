<?php
namespace App\Services;

class ProduceService
{
    public function produce()
    {
        $topic = env('topic_test','test-1'); //配置在env中
        $url = env('kafka_url_test','127.0.0.1:9092'); //配置在env中
        $value =
            [
                'code' => 'test',
                'data_type' => 'personal',
                'action' => 'update',
                'data' =>
                    [
                        'id' => 1,
                        'name' => 'tom',
                        'gender' => 2
                    ],
                'redirect_url' => '',
                'operator' => 'system',
            ];
        $value = json_encode ($value, JSON_FORCE_OBJECT );
        $kafka = new KafkaService();
        try {
            $kafka->Producer($topic, $value , $url);
        }catch (\Exception $exception){
            print_r($exception->getMessage());
        }
    }

}
