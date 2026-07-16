import request from '@/utils/request'

// 专科类型
export function get_custom_template_departments(data) {
  return request({
    url: '/big_model/get_custom_template_departments',
    method: 'get',
    data: data
  })
}

export function get_select_department2(data) {
  return request({
    url: '/quality_rule/get_select_department2',
    method: 'get',
    data: data
  })
}

// 专病病种
export function get_custom_template_diseases(data) {
  return request({
    url: '/big_model/get_custom_template_diseases',
    method: 'get',
    data: data
  })
}

export function get_rule_setting_other(type = 1, issz = 0) {
  return request({
    url: `/quality_rule/get_rule_setting_other?type=${type}&issz=${issz}`,
    method: 'get'
  })
}

export function get_deleted_rule_list(data) {
  return request({
    url: '/quality_rule/get_deleted_rule_list',
    method: 'post',
    data: data
  })
}

export function restore_rule(data) {
  return request({
    url: '/quality_rule/restore_rule',
    method: 'post',
    data: data
  })
}

export function get_rule_statistics(data) {
  return request({
    url: '/quality_rule/get_rule_statistics',
    method: 'post',
    data: data
  })
}

export function get_all_rule_list(data) {
  return request({
    url: '/quality_rule/get_all_rule_list',
    method: 'post',
    data: data
  })
}

// 数据源-列表
export function data_source_list(data) {
  return request({
    url: '/data_source/lists',
    method: 'post',
    data: data
  })
}

// 数据源-删除
export function data_source_del(data) {
  return request({
    url: '/data_source/del',
    method: 'post',
    data: data
  })
}

// 数据源-选项
export function options_list(data) {
  return request({
    url: '/data_source/options',
    method: 'get',
    params: data
  })
}

// 数据源-医院列表
export function hospitalList(data) {
  return request({
    url: '/data_source/hospitalList',
    method: 'get',
    params: data
  })
}

// 数据源-医院添加
export function hospitalAdd(data) {
  return request({
    url: '/data_source/hospitalAdd',
    method: 'post',
    data: data
  })
}

// 数据源-新增
export function data_source_add(data) {
  return request({
    url: '/data_source/add',
    method: 'post',
    data: data
  })
}

// 数据源-编辑
export function data_source_edit(data) {
  return request({
    url: '/data_source/edit',
    method: 'post',
    data: data
  })
}
