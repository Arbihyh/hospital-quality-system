import request from '@/utils/request'

// 2.0 转 3.0
export function getSsczysList(data) {
  return request({
    url: '/ssczys/ssczysList',
    method: 'post',
    data: data
  })
}

// 2.0 转 3.0 新增
export function ssczysAdd(data) {
  return request({
    url: '/ssczys/ssczysAdd',
    method: 'post',
    data: data
  })
}

// 2.0 转 3.0 编辑
export function ssczysSave(data) {
  return request({
    url: '/ssczys/ssczysSave',
    method: 'post',
    data: data
  })
}

// 手术分类
export function getSsczList(data) {
  return request({
    url: '/sscz/ssczList',
    method: 'post',
    data: data
  })
}

// 手术分类 新增
export function ssczAdd(data) {
  return request({
    url: '/sscz/ssczAdd',
    method: 'post',
    data: data
  })
}

// 手术分类 编辑
export function ssczSave(data) {
  return request({
    url: '/sscz/ssczSave',
    method: 'post',
    data: data
  })
}

// 病历目录-数据字典
export function get_field_detail(data) {
  return request({
    url: '/quality_rule/get_field_detail',
    method: 'get',
    params: data
  })
}

// 病历目录-列表
export function get_dict_list(data) {
  return request({
    url: '/quality_rule/get_dict',
    method: 'post',
    data: data
  })
}

// 病历目录-列表-修改状态
export function edit_dict_status(data) {
  return request({
    url: '/quality_rule/edit_dict_status',
    method: 'post',
    data: data
  })
}

// 病历目录-列表-删除
export function del_dict(data) {
  return request({
    url: '/quality_rule/del_dict',
    method: 'post',
    data: data
  })
}

// 病历目录-列表-新增
export function add_dict(data) {
  return request({
    url: '/quality_rule/add_dict',
    method: 'post',
    data: data
  })
}

// 病历目录-列表-修改
export function edit_field(data) {
  return request({
    url: '/quality_rule/edit_field',
    method: 'post',
    data: data
  })
}

// 病历目录-字典-修改
export function edit_field_dict(data) {
  return request({
    url: '/quality_rule/edit_field_dict',
    method: 'post',
    data: data
  })
}

// 病历目录-质控字典-列表
export function get_word_map(data) {
  return request({
    url: '/quality_rule/get_word_map',
    method: 'post',
    data: data
  })
}

// 病历目录-质控字典-删除
export function del_word_map(data) {
  return request({
    url: '/quality_rule/del_word_map',
    method: 'post',
    data: data
  })
}

// 病历目录-质控字典-新增
export function add_word_map(data) {
  return request({
    url: '/quality_rule/add_word_map',
    method: 'post',
    data: data
  })
}

// 病历目录-质控字典-修改
export function edit_word_map(data) {
  return request({
    url: '/quality_rule/edit_word_map',
    method: 'post',
    data: data
  })
}

// 病历目录-质控字典-下拉集合
export function get_all_word_map(data) {
  return request({
    url: '/quality_rule/get_all_word_map',
    method: 'post',
    data: data
  })
}
