import request from '@/utils/request'

export function login(query) {
  return request({
    url: 'login',
    data: query,
    method: 'post'
  })
}
