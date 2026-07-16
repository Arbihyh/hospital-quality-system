import request from '@/utils/request'

export function addHelper(data) {
  return request({
    url: '/big_model/set_template',
    method: 'post',
    data
  })
}

export function getModelParams(params) {
  return request({
    url: '/quality_rule/get_select_object',
    method: 'get',
    params
  })
}

export function getTaskNameData() {
  return request({
    url: '/big_model/get_task_name',
    method: 'get'
  })
}

export function getRule() {
  return request({
    url: '/big_model/get_case_rule',
    method: 'get'
  })
}

export function getInputSelect() {
  return request({
    url: '/big_model/get_input_select',
    method: 'get'
  })
}

export function helperPage(params) {
  return request({
    url: '/big_model/get_template',
    method: 'post',
    data: params
  })
}

export function deleteHelper(data) {
  return request({
    url: '/big_model/del_template',
    method: 'post',
    data
  })
}

export function selectMblb() {
  return request({
    url: '/big_model/selectmblb',
    method: 'get'
  })
}

export function setStatus(data) {
  return request({
    url: '/big_model/set_status',
    method: 'post',
    data
  })
}
