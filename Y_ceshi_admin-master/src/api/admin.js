import request from '@/utils/request'

// 管理员组列表
export function adminGroupList(data) {
  return request({
    url: '/admin/adminGroupList',
    method: 'post',
    data: data
  })
}

export function getDepartmentList() {
  return request({
    url: '/operation/getDepartmentList',
    method: 'get'
  })
}

// 权限列表
export function rbacList() {
  return request({
    url: '/admin/rbacList',
    method: 'post'
  })
}

// 添加管理员组
export function addAdminGroup(data) {
  return request({
    url: '/admin/addAdminGroup',
    method: 'post',
    data: data
  })
}

// 修改管理员组
export function editAdminGroup(data) {
  return request({
    url: '/admin/editAdminGroup',
    method: 'post',
    data: data
  })
}

// 管理员列表
export function adminList(data) {
  return request({
    url: '/admin/adminList',
    method: 'post',
    data: data
  })
}

// 修改管理员
export function editAdmin(data) {
  return request({
    url: '/admin/editAdmin',
    method: 'post',
    data: data
  })
}

// 添加管理员
export function addAdmin(data) {
  return request({
    url: '/admin/addAdmin',
    method: 'post',
    data: data
  })
}

// 查看用户组  新增/修改 配置账号组的时候
export function adminGroup(data) {
  return request({
    url: '/admin/adminGroup',
    method: 'post',
    data: data
  })
}

// 删除管理员组
export function delAdminGroup(data) {
  return request({
    url: '/admin/delAdminGroup',
    method: 'post',
    data: data
  })
}

// 删除管理员
export function delAdmin(data) {
  return request({
    url: '/admin/delAdmin',
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
export function adminText(data) {
  return request({
    url: '/admin/text',
    method: 'post',
    data: data
  })
}

export function adminInfo(data) {
  return request({
    url: '/admin/adminInfo',
    method: 'post',
    data: data
  })
}

export function adminLogList(data) {
  return request({
    url: '/admin/adminLog',
    method: 'post',
    data: data
  })
}

export function userLogList(data) {
  return request({
    url: '/user/userLog',
    method: 'post',
    data: data
  })
}

// 获取科室集合
export function getDeportmentList(data) {
  return request({
    url: '/user/getDeportmentList',
    method: 'post',
    data: data
  })
}

// 获取搜索日志数据
export function userSearchLog(data) {
  return request({
    url: '/user/userSearchLog',
    method: 'post',
    data: data
  })
}

// 获取反馈列表
export function feedbackList(data) {
  return request({
    url: '/user/feedbackList',
    method: 'post',
    data: data
  })
}

// 病历规则列表
export function caseRuleList(data) {
  return request({
    url: '/operation/getCaseRule',
    method: 'get',
    params: data
  })
}

// 病历规则列表
export function createCaseRuleList(data) {
  return request({
    url: '/operation/addCaseRule',
    method: 'post',
    data
  })
}

// 病案质控分类
export function getCategory(data) {
  return request({
    url: '/operation/getCategory',
    method: 'get',
    params: data
  })
}

// 病案质控类型
export function getType(data) {
  return request({
    url: '/operation/getType',
    method: 'get',
    params: data
  })
}

// 修改质控规则是否同步到事中质控
export function setCaseRuleshizhong(data) {
  return request({
    url: '/operation/setCaseRuleshizhong',
    method: 'post',
    data
  })
}

// 获取申述列表
export function getCaseAppealList(data) {
  return request({
    url: '/getCaseAppeal',
    method: 'get',
    data
  })
}
//  申诉审核
export function getCaseExamineAppeal(data) {
  return request({
    url: '/examineAppeal',
    method: 'post',
    data
  })
}
