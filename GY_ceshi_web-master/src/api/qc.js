import request from '@/utils/request';
import request2 from '@/utils/request_bazb';
import request3 from '@/utils/request_api';
import axios from '@/axios/index';

// 病案室-质控列表
export function getBlZkList(data) {
  return request({
    url: '/getBlZkList',
    method: 'get',
    params: data,
  });
}

// 病案室-质控列表-明细
export function getCorrectionList(data) {
  return request({
    url: '/correction_list',
    method: 'get',
    params: data,
  });
}

export function getQualityControlStatus(data) {
  return request({
    url: '/getQualityControlStatus',
    method: 'post',
    params: data,
  });
}

export function updateQualityControl(data) {
  return request({
    url: '/updateQualityControl',
    method: 'post',
    params: data,
  });
}

export function applyForReview(data) {
  return request({
    url: '/applyForReview',
    method: 'post',
    params: data,
  });
}

export function getRevokeList(data) {
  return request({
    url: '/getRevokeList',
    method: 'post',
    params: data,
  });
}

export function revokeUpdate(data) {
  return request({
    url: '/revokeUpdate',
    method: 'post',
    params: data,
  });
}

export function getDataExamine(data) {
  return request({
    url: '/getDataExamine',
    method: 'post',
    data,
  });
}

// 病案室-病案详情-左侧菜单
export function getBlMenuList(data) {
  return request({
    url: '/bl_zk/getBlMenuList',
    method: 'post',
    params: data,
  });
}
// 病案室-病案详情-左侧菜单
// 导出一个函数，用于获取树形列表
export function getTreeList(data) {
  // 发送请求，获取树形列表
  return request({
    url: '/getTree',
    method: 'post',
    data,
  });
}

// 病案室-病案详情-右侧质控栏
export function getCaseQuality(data) {
  return request2({
    // url: '/bl_zk/getCaseQuality',
    url: '/get_case_quality_v2',
    method: 'post',
    data: data,
  });
}

// 病案室-病案详情-右侧质控栏-申诉驳回接口
export function examineAppeal(data) {
  return request2({
    url: '/appeal',
    method: 'post',
    data: data,
  });
}

export function planSaveAndEdit(data) {
  return request({
    url: '/case_quality_plan/save',
    method: 'post',
    params: data,
  });
}

export function planDel(data) {
  return request({
    url: '/case_quality_plan/delete',
    method: 'post',
    params: data
  });
}

export function planList(data) {
  return request({
    url: '/case_quality_plan/list',
    method: 'get',
    params: data,
  });
}


// 病案室-病案详情-右侧质控栏-审核接口
export function examineReview(data) {
  return request.post('/examineCaseAppeal', data);
}

// 驳回信息接口
export function getAppealData(data) {
  return request({
    url: '/getAppeal',
    method: 'get',
    params: data,
  });
}

// 住院病历接口
export function getCaseQualityBazb(data) {
  return request2({
    url: '/get_case_quality_v2',
    method: 'post',
    data: data,
  });
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
    data: data,
  });
}
export function getAllCase(data) {
  return request3({
    url: '/getAllCase',
    method: 'post',
    data: data,
  });
}

export function getLong(data) {
  return request3({
    url: '/long',
    method: 'post',
    data: data,
  });
}

export function getTemporary(data) {
  return request({
    url: '/bl_zk/temporary',
    method: 'post',
    params: data,
  });
}

export function getCaseResultData(data) {
  return request({
    url: '/getCaseResult',
    method: 'get',
    params: data,
  });
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
    data: data,
  });
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
    data: data,
  });
}
export function getHomeData(data) {
  return request({
    url: '/bl_zk/getHomeData',
    method: 'post',
    params: data,
  });
}

export function getSurgeryData(data) {
  return request({
    url: '/bl_zk/getSurgeryData',
    method: 'post',
    params: data,
  });
}

export function getRuleData(data) {
  return request({
    url: '/bl_zk/getRule',
    method: 'post',
    params: data,
  });
}

export function getBlInfo(data) {
  return request({
    url: '/bl_zk/getBlInfo',
    method: 'post',
    params: data,
  });
}

export function addCaseQuality(data) {
  return request({
    url: '/bl_zk/addCaseQuality',
    method: 'post',
    params: data,
  });
}

export function getStaffListData(data) {
  return request({
    url: '/getStaffList',
    method: 'get',
    params: data,
  });
}

// 获取院区
export function getCampusArea(data) {
  return request({
    url: '/getCampusAreaList',
    method: 'get',
    params: data,
  });
}

// 获取接收端
export function getCaseCate(data) {
  return request({
    url: '/bl_zk/getCaseCate',
    method: 'post',
    params: data,
  });
}

// 获取管床
export function getPipeBedding(data) {
  return request({
    url: '/getPipeBeddingList',
    method: 'get',
    params: data,
  });
}

//获取消息
export function getNumberInfo(data) {
  return request({
    url: '/getCaseNumberInfo',
    method: 'get',
    params: data,
  });
}

//获取消息
export function getAppealNumberInfo(data) {
  return request({
    url: '/appeal_tab_nums',
    method: 'get',
    params: data,
  });
}

export function catalogList(data) {
  return request({
    url: '/catalog_lists',
    method: 'get',
    params: data,
  });
}



// 获取病案首页接口
export function getQualityResult(data) {
  return request({
    url: '/home_quality/getQualityResult',
    method: 'post',
    data: data,
  });
}

// 质控目录接口
export function getSelectValue(data) {
  return request({
    url: '/getSelectObjectValue',
    method: 'get',
    params: data,
  });
}

export function getBrry(params) {
  return axios.get('/get_brry', {
    params: params
  })
}

export function setCorrection(data) {
  return axios.post('/set_correction', data)
}

export function getZJZKList(data) {
  return axios.post('/case-quality/expert/ge_zjZk_list', data)
}

export function collectSearchSave(data) {
  return axios.post('/case-quality/expert/collect_zjzk_search', data)
}

export function getCollectSearchList(params) {
  return axios.get('/case-quality/expert/get_collect_zjzk_search', {
    params: params
  })
}

export function deleteCollectSearch(data) {
  return axios.post('/case-quality/expert/delete_collect_zjzk_search', data)
}

export function getDefaultCollectSearch() {
  return axios.get('/case-quality/expert/get_default_collect_zjzk_search')
}