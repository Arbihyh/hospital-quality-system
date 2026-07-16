import request from '@/utils/request'

export async function menuList() {
  return request({
    url: 'admin/menuList',
    method: 'post'
  })
}
