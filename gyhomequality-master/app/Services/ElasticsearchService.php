<?php

namespace App\Services;

use Elasticsearch\ClientBuilder;

class ElasticsearchService
{
    /**
     * 搜索结构体
     * @var array
     */
    protected $params = [
        'type' => '_doc',
        'body' => [
            'query' => [
                'bool' => [
                    'filter' => [],
                    'should' => [],
                    'must' => []
                ]
            ]
        ]
    ];

    /**
     * 通过构造函数进行索引初始化
     * ElasticsearchService constructor.
     * @param $index
     */
    public function __construct($index)
    {
        //要搜索的索引名称
        // 如果是测试环境，所有的索引名称自动增加test前缀
        if(env('APP_ENV') == 'test'){
            $index = 'test_'.$index;
        }
        $this->params['index'] = $index;
        return $this;
    }

    /**
     * 根据字段-条件搜索
     * @param $type .搜索类型：term精准搜索，match分词器模糊查询，prefix字段前缀
     * @param $key .搜索的字段名称
     * @param $value .搜索字段值
     * @return $this
     */
    public function queryByFilter($type, $key, $value)
    {
        $this->params['body']['query']['bool']['filter'][] = [$type => [$key => $value]];

        return $this;
    }

    /**
     * 多条件must搜索
     * @param $keyWords
     * @return $this
     */
    public function queryByMustBatch($keyWords)
    {
        //如果不是数据则转换为数组
        $keyWords = is_array($keyWords) ? $keyWords : [$keyWords];

        foreach ($keyWords as $key => $value) {
            $this->queryByMust($value);
        }
        return $this;
    }

    /**
     * 根据权重进行多字段搜索
     * @return $this
     */
    public function clearMust()
    {
        $this->params['body']['query']['bool']['must'] = [];
        $this->params['body']['query']['bool']['should'] = [];
        $this->params['body']['query']['bool']['must_not'] = [];
        $this->params['body']['query']['bool']['minimum_should_match'] = 0;
        unset($this->params['body']['aggs']);
        return $this;
    }

    /**
     * 根据权重进行多字段搜索
     * @return $this
     */
    public function queryByMust($value = [])
    {
        if ($value) {
            $this->params['body']['query']['bool']['must'][] = $value;
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function queryByMustNot($value = [])
    {
        if ($value) {
            $this->params['body']['query']['bool']['must_not'][] = $value;
        }
        return $this;
    }

    /**
     * 多条件must搜索
     * @param $keyWords
     * @return $this
     */
    public function queryByShouldBatch($should = [])
    {
        foreach ($should as $key => $value) {
            $this->queryByShould($value);
        }
        return $this;
    }

    /**
     * 根据权重进行多字段搜索
     * @return $this
     */
    public function queryByShould($value = [])
    {
        if ($value) {
            $this->params['body']['query']['bool']['should'][] = $value;
        }
        return $this;
    }

    /**
     * 根据权重进行多字段搜索
     * @return $this
     */
    public function aggs($value = [])
    {
        if ($value) {
            $this->params['body']['aggs'] = $value;
        }
        return $this;
    }

    /**
     * 获取指定字段
     * @param $keyWords  一维数组
     * @return $this
     */
    public function source($keyWords)
    {
        $keyWords = is_array($keyWords) ? $keyWords : [$keyWords];
        $this->params['body']['_source'] = $keyWords;

        return $this;
    }

    /**
     * 设置分页
     * @param $page
     * @param $pageSize
     * @return $this
     */
    public function paginate($page, $pageSize)
    {
        $pageStart = ($page - 1) * $pageSize;
        $this->params['body']['from'] = $pageStart;
        $this->params['body']['size'] = $pageSize;

        return $this;
    }

    /**
     * 启用 Scroll API（用于导出大量数据，避免 from + size 超过 10000 的限制）
     * @param int $scrollSize 每次滚动获取的文档数量
     * @param string $scrollTime Scroll 上下文保持时间，默认 1m（1分钟）
     * @return $this
     */
    public function scroll($scrollSize = 5000, $scrollTime = '1m')
    {
        $this->params['scroll'] = $scrollTime;
        $this->params['body']['size'] = $scrollSize;
        // Scroll API 不需要设置 from，总是从 0 开始
        unset($this->params['body']['from']);

        return $this;
    }

    /**
     * 排序
     * @param $filed .排序字段
     * @param $direction .排序值
     * @return $this
     */
    public function orderBy($filed, $direction)
    {
        if (!isset($this->params['body']['sort'])) {
            $this->params['body']['sort'] = [];
        }

        $this->params['body']['sort'][] = [$filed => $direction];

        return $this;
    }

    /**
     * 聚合查询 商品属性筛选的条件
     * @param $name     属性名称
     * @param $value    属性值
     * @return $this
     */
    public function attributeFilter($name, $value)
    {
        //attributes 为 索引中的  attributes  字段
        $this->params['body']['query']['bool']['filter'] = [
            'nested' => [
                'path' => 'attributes',
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'term' => [
                                    'attributes.name' => $name
                                ]
                            ], [
                                'term' => [
                                    'attributes.value' => $value
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return $this;
    }

    /**
     * 返回结构体
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * 多维数组转换一维数组
     * @param $input
     * @param $flatten
     * @return array
     */
    public function getDataByEs($input)
    {
        $total = $input['hits']['total']['value'];
        $hits = $input['hits']['hits'];
        $aggregations = !empty($input['aggregations']) ? $input['aggregations'] : [];
        $data = array_column($hits, '_source');
        return [$data, $total, $aggregations];
    }

    public function queryByFilterBatch($keyWords)
    {
        foreach ($keyWords as $value) {
            $this->queryByFilter($value['type'], $value['key'], $value['value']);
        }

        return $this;
    }

    public function queryByMustNotBatch($keyWords)
    {
        //如果不是数据则转换为数组
        $keyWords = is_array($keyWords) ? $keyWords : [$keyWords];

        foreach ($keyWords as $key => $value) {
            $this->queryByMustNot($value);
        }
        return $this;
    }

    /**
     * 用到 queryByShould（should）查询时，会查询出无关数据，调用该方法，传参数：1 ，去除无用数据
     * @param $value
     * @return $this
     */
    public function minimumShouldMatch($value=1)
    {
        $this->params['body']['query']['bool']['minimum_should_match'] = $value;

        return $this;
    }

    /**
     * 返回高亮数据
     * @param $fields 一维数组,例如：['字段A','字段B']
     * @return $this
     */
    public function highlight($fields = [])
    {
        if ($fields) {
            $fieldsArr = [];
            foreach ($fields as $field) {
                $fieldsArr[$field] = (object)[];
            }
            $this->params['body']['highlight'] = [
                'fields' => $fieldsArr,
                "pre_tags" => ["<font color='red'>"],
                "post_tags" => ["</font>"],
                "fragment_size" => 10000,
                "number_of_fragments" => 0,
            ];
        } else {
            $this->params['body']['highlight'] = [
                'fields' => [
                    '*' => [
                        "pre_tags" => "<font color='red'>",
                        "post_tags" => "</font>",
                    ],
                ],
                "fragment_size" => 10000,
                "number_of_fragments" => 0,
            ];
        }

        return $this;
    }

    /**
     * 使用 高亮方法【highlight()】 用此方法解析数据
     * @param $input
     * @return array
     */
    public function getDataByEsToArray($input)
    {
        $total = $input['hits']['total']['value'];
        $hits = $input['hits']['hits'];
        $aggregations = !empty($input['aggregations']) ? $input['aggregations'] : [];

        foreach ($hits as $key => $value) {
            if (!empty($value['highlight'])) {
                foreach ($value['highlight'] as $k => $v) {
                    $hits[$key]['_source'][$k] = $v[0];
                }
            }
        }

        $data = array_column($hits, '_source');
        return [$data, $total, $aggregations];
    }

    /**
     * 在查询时候把 track_total_hits 设置为 true。设置为true就会返回真实的命中条数
     * @return $this
     */
    public function trackTotalHits()
    {
        $this->params['track_total_hits'] = true;

        return $this;
    }

    /**
     * 单条写入
     * @param $indexName
     * @param $id
     * @param $insertData
     * @param $type
     * @return array|callable
     */
    public function insert($indexName, $id, $insertData, $type='_doc')
    {
        $client = ClientBuilder::create()->setHosts([env('ES_HOST')])->build();

        $params = [
            'index' => $indexName,
            'type' => $type,
            'id'    => $id,
            'body'  => $insertData
        ];

        return $client->index($params);
    }

    /**
     * 批量写入
     * @param $data
     * @return array|callable
     */
    public function insertBatch($indexName,$data,$type='_doc')
    {
        $client = ClientBuilder::create()->setHosts([env('ES_HOST')])->build();

        $params = [
            'index' => $indexName,
            'type' => $type,
            'body' => $data,
        ];

        return $client->bulk($params);
    }

}
