import request from '@/utils/request'
import request2 from '@/utils/request_bazb'
import request3 from '@/utils/request_api'

// 病案室-质控列表
export function getBlZkList(data) {
  return request({
    url: '/bl_zk/getBlZkList',
    method: 'post',
    data: data
  })
}

// 病案室-病案详情-左侧菜单
export function getBlMenuList(data) {
  return request({
    url: '/bl_zk/getBlMenuList',
    method: 'post',
    data: data
  })
}
// 病案室-病案详情-左侧菜单
export function getTreeList(data) {
  return request3({
    url: '/getTree',
    method: 'post',
    data: data
  })
}

// 病案室-病案详情-右侧质控栏
export function getCaseQuality(data) {
  return request2({
    // url: '/bl_zk/getCaseQuality',
    url: '/get_case_quality_v2',
    method: 'post',
    data: data
  })
}

// 病案室-病案详情-病历内容
// export function getCasePlatform(data) {
//   return request({
//     url: '/bl_zk/getCasePlatform',
//     method: 'post',
//     data: data
//   })
// }
export function getCasePlatform(data) {
  return request2({
    url: '/get_case_platform',
    method: 'post',
    data: data
  })
}
export function getAllCase(data) {
  return request3({
    url: '/getAllCase',
    method: 'post',
    data: data
  })
}

export function getLong(data) {
  return request3({
    url: '/long',
    method: 'post',
    data: data
  })
}

export function getTemporary(data) {
  return request({
    url: '/bl_zk/temporary',
    method: 'post',
    data: data
  })
}

// export function getPacsData(data) {
//   return request({
//     url: '/bl_zk/getPacsData',
//     method: 'post',
//     data: data
//   })
// }
export function getPacsData(data) {
  return request3({
    url: '/get_pacs_data',
    method: 'post',
    data: data
  })
}
// export function getBcData(data) {
//   return request({
//     url: '/bl_zk/getBcData',
//     method: 'post',
//     data: data
//   })
// }
export function getBcData(data) {
  return request3({
    url: '/get_bc_data',
    method: 'post',
    data: data
  })
}
export function getHomeData(data) {
  return request({
    url: '/bl_zk/getHomeData',
    method: 'post',
    data: data
  })
}

export function getSurgeryData(data) {
  return request({
    url: '/bl_zk/getSurgeryData',
    method: 'post',
    data: data
  })
}

export function getRuleData(data) {
  return request({
    url: '/bl_zk/getRule',
    method: 'post',
    data: data
  })
}

export function getBlInfo(data) {
  return request({
    url: '/bl_zk/getBlInfo',
    method: 'post',
    data: data
  })
}

export function addCaseQuality(data) {
  return request({
    url: '/bl_zk/addCaseQuality',
    method: 'post',
    data: data
  })
}
