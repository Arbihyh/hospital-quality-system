import request from '@/utils/request'

// 管理员组列表
export function userGroupList(data) {
  return request({
    url: '/user/userGroupList',
    method: 'post',
    data: data
  })
}

// 权限列表
export function rbacList() {
  return request({
    url: '/user/rbacList',
    method: 'post'
  })
}

// 添加管理员组
export function addUserGroup(data) {
  return request({
    url: '/user/addUserGroup',
    method: 'post',
    data: data
  })
}

// 修改管理员组
export function editUserGroup(data) {
  return request({
    url: '/user/editUserGroup',
    method: 'post',
    data: data
  })
}

// 管理员列表
export function userList(data) {
  return request({
    url: '/user/userList',
    method: 'post',
    data: data
  })
}

// 修改管理员
export function editUser(data) {
  return request({
    url: '/user/editUser',
    method: 'post',
    data: data
  })
}

// 添加管理员
export function addUser(data) {
  return request({
    url: '/user/addUser',
    method: 'post',
    data: data
  })
}

// 查看用户组  新增/修改 配置账号组的时候
export function userGroup(data) {
  return request({
    url: '/user/userGroup',
    method: 'post',
    data: data
  })
}

// 删除管理员组
export function delUserGroup(data) {
  return request({
    url: '/user/delUserGroup',
    method: 'post',
    data: data
  })
}

// 删除管理员
export function delUser(data) {
  return request({
    url: '/user/delUser',
    method: 'post',
    data: data
  })
}

// 上传
export function upload(data) {
  return request({
    url: 'upload',
    method: 'post',
    data: data
  })
}
// 测试
export function userText(data) {
  return request({
    url: '/user/text',
    method: 'post',
    data: data
  })
}

// 删除管理员
export function editPassword(data) {
  return request({
    url: '/user/editPassword ',
    method: 'post',
    data: data
  })
}
