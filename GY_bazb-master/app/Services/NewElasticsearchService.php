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
class NewElasticsearchService
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

    static private $instance;

    /**
     * @return NewElasticsearchService
     */
    static public function getInstance() :self
    {
        if(!self::$instance instanceof NewElasticsearchService){
            return (self::$instance = new NewElasticsearchService());
        }

        return self::$instance;
    }

    /**
     * @param $index
     * @return $this
     */
    public function setIndex($index) :self
    {

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
    public function queryByFilter($type, $key, $value) :self
    {
        $this->params['body']['query']['bool']['filter'][] = [$type => [$key => $value]];

        return $this;
    }

    /**
     * 多条件must搜索
     * @param $keyWords
     * @return $this
     */
    public function queryByMustBatch($keyWords) :self
    {
        //如果不是数据则转换为数组
        $keyWords = is_array($keyWords) ? $keyWords : [$keyWords];

        foreach ($keyWords as $key => $value) {
            if(!$value){
                continue;
            }
            $this->queryByMust($value);
        }
        return $this;
    }

    /**
     * 根据权重进行多字段搜索
     * @return $this
     */
    public function clearMust() :self
    {
        $this->params['body']['query']['bool'] = [
            'must' => [],
            'filter' => [],
            'should' => [],
            'must_not' => [],
            'minimum_should_match' => 0,
        ];
        $this->params['body']['sort'] = [];
        return $this;
    }

    /**
     * 根据权重进行多字段搜索
     * @return $this
     */
    public function queryByMust($value = []) :self
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
    public function queryByMustNot($value = []) :self
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
    public function queryByShouldBatch($should = []) :self
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
    public function queryByShould($value = []) :self
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
    public function aggs($value = []) :self
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
    public function source($keyWords) :self
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
    public function paginate($page, $pageSize) :self
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
    public function orderBy($filed, $direction) :self
    {
        if (!isset($this->params['body']['sort'])) {
            $this->params['body']['sort'] = [];
        }

        $this->params['body']['sort'][] = [$filed => $direction];

        return $this;
    }

    /**
     * 聚合查询 商品属性筛选的条件
     * @param $name
     * @param $value
     * @return $this
     */
    public function attributeFilter($name, $value) :self
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
    public function getParams() :array
    {
        return $this->params;
    }

    /**
     * @return array
     */
    public function search(): array
    {
        $res = app('es')->search($this->params);
        $total = data_get($res,'hits.total.value',0);
        $data = data_get($res,'hits.hits.*._source',[]);
        $aggregations = data_get($res,'aggregations',[]);
        return [$data, $total, $aggregations];
    }

    /**
     * @param $keyWords
     * @return $this
     */
    public function queryByFilterBatch($keyWords) :self
    {
        foreach ($keyWords as $value) {
            $this->queryByFilter($value['type'], $value['key'], $value['value']);
        }
        return $this;
    }

    /**
     * @param $keyWords
     * @return $this
     */
    public function queryByMustNotBatch($keyWords) :self
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
     * @param int $value
     * @return $this
     */
    public function minimumShouldMatch(int $value = 1) :self
    {
        $this->params['body']['query']['bool']['minimum_should_match'] = $value;

        return $this;
    }

}

