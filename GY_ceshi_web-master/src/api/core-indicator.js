// 核心指标接口
import request from '@/utils/request'
import request2 from '@/utils/request_bazb';


// 1. 指标数据分析接口 - 根据年月和type类型，查询不同时间范围的指标数据，返回指标率（键值对格式）
export function getQualityIndexAnalysis(data) {
  return request2({
    url: '/quality_index_analysis',
    method: 'get',
    params: data,
  });
}

// 2. 指标科室排名接口 - 根据条件查询各科室的指标率排名
export function getQualityIndexDeptRanking(data) {
  return request2({
    url: '/quality_index_department_ranking',
    method: 'get',
    params: data,
  });
}