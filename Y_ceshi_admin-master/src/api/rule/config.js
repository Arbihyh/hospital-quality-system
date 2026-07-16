import request from '@/utils/request'

// 质控项目的下拉数据
export function get_select_object(data) {
  return request({
    url: '/quality_rule/get_select_object',
    method: 'get',
    params: data
  })
}

// 质控科室的下拉
export function get_select_department(data) {
  return request({
    url: '/quality_rule/get_select_department',
    method: 'get',
    params: data
  })
}

// 规则模板列表
export function get_rule_list(data) {
  return request({
    url: '/quality_rule/get_rule_list',
    method: 'get',
    params: data
  })
}

// 规则模板-新增、编辑
export function add_rule(data) {
  return request({
    url: '/quality_rule/add_rule',
    method: 'post',
    data: data
  })
}

// 规则模板-删除
export function del_rule(data) {
  return request({
    url: '/quality_rule/del_rule',
    method: 'post',
    data: data
  })
}

// 规则模板-修改状态
export function edit_rule_status(data) {
  return request({
    url: '/quality_rule/edit_rule_status',
    method: 'post',
    data: data
  })
}

// 规则详情
export function get_rule_detail(data) {
  return request({
    url: '/quality_rule/get_rule_detail',
    method: 'get',
    params: data
  })
}
// 规则详情
export function getSelectFormula(data) {
  return request({
    url: '/quality_rule/get_select_formula',
    method: 'get',
    params: data
  })
}

// 字典编辑
export function edit_word_map(data) {
  return request({
    url: '/quality_rule/edit_word_map',
    method: 'post',
    data: data
  })
}

// 获取type:1病历类型,2质控类型,3质控场景数据
export function get_rule_setting_other(data) {
  return request({
    url: '/quality_rule/get_rule_setting_other',
    method: 'get',
    params: data
  })
}

// 根据类型获取三级数据
export function get_dict_by_type(data) {
  return request({
    url: '/quality_rule/get_dict_by_type',
    method: 'post',
    data: data
  })
}

