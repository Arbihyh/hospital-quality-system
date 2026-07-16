import request from '@/utils/request'

// 疾病库列表
export function illnessList(data) {
  return request({
    url: '/disease/diseaseList',
    method: 'post',
    data: data
  })
}

// 疾病库-新增
export function illnessAdd(data) {
  return request({
    url: '/disease/diseaseAdd',
    method: 'post',
    data: data
  })
}

// 疾病库-详情
export function illnessInfo(data) {
  return request({
    url: '/disease/diseaseInfo',
    method: 'post',
    data: data
  })
}

// 疾病库-编辑
export function illnessUpdate(data) {
  return request({
    url: '/disease/diseaseSave',
    method: 'post',
    data: data
  })
}

// 疾病库-删除
export function illnessDelete(data) {
  return request({
    url: '/disease/diseaseDelete',
    method: 'post',
    data: data
  })
}

// 手术库列表
export function surgeryList(data) {
  return request({
    url: '/surgery/surgeryList',
    method: 'post',
    data: data
  })
}

// 手术库-新增
export function surgeryAdd(data) {
  return request({
    url: '/surgery/surgeryAdd',
    method: 'post',
    data: data
  })
}

// 手术库-详情
export function surgeryInfo(data) {
  return request({
    url: '/surgery/surgeryInfo',
    method: 'post',
    data: data
  })
}

// 手术库-编辑
export function surgeryUpdate(data) {
  return request({
    url: '/surgery/surgerySave',
    method: 'post',
    data: data
  })
}

// 手术库-删除
export function surgeryDelete(data) {
  return request({
    url: '/surgery/surgeryDelete',
    method: 'post',
    data: data
  })
}
