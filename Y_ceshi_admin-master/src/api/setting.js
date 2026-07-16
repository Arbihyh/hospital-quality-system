import request from '@/utils/request'

export function setSetting(data) {
  return request({
    url: '/setting/global_set',
    method: 'post',
    data
  })
}

export function getSetting() {
  return request({
    url: '/setting/get_setting',
    method: 'get'
  })
}
