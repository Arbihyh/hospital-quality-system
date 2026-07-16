<?php

namespace App\Services;


/**
 * Class ElasticsearchService
 * @package App\Services
 * $esService = new ElasticsearchService('products');
 *
 * //封装es类，增加商品状态为上架条件的分页查询结构
 * $builder = $esService->queryByFilter('term','shop_id',$shop_id)->paginate($page,$pageSize);
 *
 * //分类搜索
 * if ($category_id){
 * //获取分类信息
 * $category = PublicService::category($category_id);
 *
 * //根据字段-条件搜索
 * if($category && $category->is_directory){
 * $builder->queryByFilter('prefix','category_path',$category->path.$category->id.'-');
 * }else{
 * $builder->queryByFilter('term','category_id',$category->id);
 * }
 * }
 *
 * //关键词按照权重进行搜索
 * if($search){
 * $keywords = array_filter(explode(' ',$search));
 *
 * $builder->keyWords($keywords);
 * }
 *
 * //排序
 * if($order){
 * if (preg_match('/^(.+)_(asc|desc)$/',$order,$m)){
 * //只有当前三个字段才可以进行排序搜索
 * if (in_array($m[1],['price','sold_count','review_count'])){
 * $builder->orderBy($m[1],$m[2]);
 * }
 * }
 * }
 *
 * $attributeFilter = [];
 *
 * //根据商品类型搜索
 * if($attributes){
 * $attrArray = explode("|",$attributes);
 *
 * foreach ($attrArray as $attr){
 * list($name,$value) = explode(":",$attr);
 * $attributeFilter[$name] = $value;
 *
 * $builder->attributeFilter($name,$value);
 * }
 * }
 *
 * //获取的字段
 * $builder->source(['id','name','long_name','shop_id','skus','attributes','create_time']);
 *
 * //执行es搜索
 * $restful = app('es')->search($builder->getParams());
 *
 * //多维数组转换一维数组
 * list($data,$total) = $builder->getDataByEs($restful);
 */
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
     * @param array $value 参数值
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
        $this->params['body']['from'] = ($page - 1) * $pageSize;
        $this->params['body']['size'] = $pageSize;

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
     * 聚合查询(方式2) 商品属性筛选的条件
     * @param $name
     * @param $value
     * @return $this
     */
    /*public function attributeFilter($name,$value){
    $this->params['body']['query']['bool']['filter'][] = [
    'nested' => [
    'path'  => 'attributes',
    'query' => [
    ['term' => ['attributes.name' => $name]],
    ['term' => ['attributes.value' => $value]]
    ],
    ],
    ];
    return $this;
    }*/

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
        if(empty($input['hits'])){
            return [];
        }
        $total = $input['hits']['total']['value'];
        $input = $input['hits']['hits'];
        $data = array_column($input, '_source');
        return [$data, $total];
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

}

