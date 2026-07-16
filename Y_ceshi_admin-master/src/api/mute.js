import request from '@/utils/request'

// 话题列表
export function addMute(data) {
  return request({
    url: 'user/addMute',
    method: 'post',
    data: data
  })
}
// 上线下线
export function muteList(data) {
  return request({
    url: 'user/muteList',
    method: 'post',
    data: data
  })
}
// 添加
export function muteAdd(data) {
  return request({
    url: 'user/addMuteConfig',
    method: 'post',
    data: data
  })
}
// 修改
export function muteDel(data) {
  return request({
    url: 'user/muteDel',
    method: 'post',
    data: data
  })
}
//
export function updateMute(data) {
  return request({
    url: 'user/updateMute',
    method: 'post',
    data: data
  })
}
