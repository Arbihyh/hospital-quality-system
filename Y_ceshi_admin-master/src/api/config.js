import request from '@/utils/request'

// 理由列表
export function configList(data) {
  return request({
    url: 'rule/errorList',
    method: 'post',
    data: data
  })
}
// 删除理由
export function delConfig(data) {
  return request({
    url: 'rule/delErrorRule',
    method: 'post',
    data: data
  })
}
// 修改
export function saveConfig(data) {
  return request({
    url: 'rule/editErrorRule',
    method: 'post',
    data: data
  })
}
// 修改
export function updateStatus(data) {
  return request({
    url: 'rule/updateStatus',
    method: 'post',
    data: data
  })
}
// 添加
export function addConfig(data) {
  return request({
    url: 'rule/addErrorRule',
    method: 'post',
    data: data
  })
}

// 修改
export function updateBmyLevel(data) {
  return request({
    url: 'rule/updateBmyLevel',
    method: 'post',
    data: data
  })
}

