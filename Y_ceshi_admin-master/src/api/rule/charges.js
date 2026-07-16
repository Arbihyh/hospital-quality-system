import request from '@/utils/request'

// 列表
export function fetchList(data) {
  return request({
    url: 'operation/relationsList',
    method: 'post',
    data: data
  })
}
// 修改
export function update(data) {
  return request({
    url: 'operation/editOperationRelation',
    method: 'post',
    data: data
  })
}
// 修改
export function updateStatus(data) {
  return request({
    url: 'operation/updateStatus',
    method: 'post',
    data: data
  })
}
// 添加
export function add(data) {
  return request({
    url: 'operation/addOperationRelation',
    method: 'post',
    data: data
  })
}
// 删除
export function del(data) {
  return request({
    url: 'operation/delOperationRelation',
    method: 'post',
    data: data
  })
}

