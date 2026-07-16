import request_blob from '@/utils/request_blob'


export function exportCaseAppeal(params) {
  return request_blob({
    url: `/api/getCaseAppeal`,
    method: 'get',
    responseType: 'blob',
    params: params
  })
}

export function shizhong_quality_unlock_records(params) {
  return request_blob({
    url: `/bazb/case-quality/shizhong_quality_unlock_records`,
    method: 'get',
    responseType: 'blob',
    params: params
  })
}

export function totalCasesDrillDown(url,data) {
  return request_blob({
    url: '/api'+url,
    method: 'post',
    responseType: 'blob',
    data
  })
}


export function shizhong_quality_records(data) {
  return request_blob({
    url: `/bazb/case-quality/shizhong_quality_records`,
    method: 'post',
    responseType: 'blob',
    data: data
  })
}

export function shizhong_quality_rule_statistics(data) {
  return request_blob({
    url: `/bazb/case-quality/shizhong_quality_rule_statistics`,
    method: 'post',
    responseType: 'blob',
    data: data
  })
}

export function shizhong_quality_department_statistics(data) {
  return request_blob({
    url: `/bazb/case-quality/shizhong_quality_department_statistics`,
    method: 'post',
    responseType: 'blob',
    data: data
  })
}

export function quality_index_core_reportExport(params) {
  return request_blob({
    url: `/bazb/quality_index_core_report`,
    method: 'post',
    responseType: 'blob',
    params: params
  })
}

// 
export function quality_index_core_top_departmentsExport(params) {
  return request_blob({
    url: `/bazb/quality_index_core_top_departments`,
    method: 'post',
    responseType: 'blob',
    params: params
  })
}

export function quality_index_core_bottom_departmentsExport(params) {
  return request_blob({
    url: `/bazb/quality_index_core_bottom_departments`,
    method: 'post',
    responseType: 'blob',
    params: params
  })
}

// 
export function exportQuality_index_department_ranking(params) {
  return request_blob({
    url: `/bazb/quality_index_department_ranking`,
    method: 'get',
    responseType: 'blob',
    params: params
  })
}

export function appealCasesDrillDown(data) {
  return request_blob({
    url: `/api/quality_report/quality_report_drill/defectCasesDrillDown`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

export function exportDefectIssues(data) {
  return request_blob({
    url: `/api/case-quality/defect_issues`,
    method: 'post',
    responseType: 'blob',
    data
  })
}
export function exportReportStatistics(params) {
  return request_blob({
    url: `/api/quality_report/getReportStatistics`,
    method: 'post',
    responseType: 'blob',
    params: params
  })
}
export function defectDataExport(data) {
  return request_blob({
    url: `/api/omr_zk/defect_issues`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

export function deptDataExport(data) {
  return request_blob({
    url: `/api/omr_zk/ranking_department`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

export function doctorDataExport(data) {
  return request_blob({
    url: `/api/omr_zk/ranking_doctor`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

// 医院普通检索下载
export function zz(data) {
  return request_blob({
    url: `/api/exportData`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

//数据质控错误数据导出
export function qxBlNumberTableList(data) {
  return request_blob({
    url: `/api/CaseHistory/Terminal/qxBlNumberTableList`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

export function blNumberTableList(data) {
  return request_blob({
    url: `/api/CaseHistory/Terminal/blNumberTableList`,
    method: 'post',
    responseType: 'blob',
    data
  })
}



// 医院普通检索下载
export function bassNormalSearchDownload(data) {
  return request_blob({
    url: `/bass/normalSearchExport`,
    method: 'get',
    responseType: 'blob',
    params: data
  })
}

// 医院高级检索下载
export function bassHighSearchDownload(data) {
  return request_blob({
    url: `/bass/search_result_export`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

// 科室病历导出
export function depBlExport(data) {
  return request_blob({
    url: `/bazb/dep_bl_export`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

// 科室病历导出
export function depBlNewExport(data) {
  return request_blob({
    url: `/bazb/dep_bl_export_new`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

// 门诊病历导出
export function outHospitalExport(data) {
  return request_blob({
    url: `/api/omr_zk/defect_issues_all`,
    method: 'post',
    responseType: 'blob',
    data
  })
}
//门诊病历导出 -缺陷数据
export function outHospitalErrorListExport(data) {
  return request_blob({
    url: `/api/omr_zk/error_list`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

export function mzFeedbackListExport(data) {
  return request_blob({
    url: `/bazb/mz_feedback_list`,
    method: 'post',
    responseType: 'blob',
    data
  })
}
// 门诊病历导出
export function outHospitalShouldExport(data) {
  return request_blob({
    url: `/api/omr_zk/get_should_be_bl_list`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

// 病按首页导出
export function medicalRecordExport(data) {
  return request_blob({
    url: `/api/qualityList`,
    method: 'post',
    responseType: 'blob',
    data
  })
}
// 病按首页导出
export function doctorAdviceExport(data) {
  return request_blob({
    url: `/bass/yz/serachExport`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

// 住院病历查询-导出
export function professionSearchExport(data) {
  return request_blob({
    url: `/bass/bl/serachExport`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

// 缺陷问题导出
export function errorDataExport(data) {
  return request_blob({
    url: `/api/home_quality/errorDataExport`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

// 预警信息导出
export function foreWarningExport(data) {
  return request_blob({
    url: `/bazb/warning_msg`,
    method: 'get',
    responseType: 'blob',
    params: data
  })
}

export function errorDataLCExport(data) {
  return request_blob({
    url: `/api/home_sz_quality/errorData`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

// 缺陷问题详情导出
export function errorDetailsListExport(data) {
  return request_blob({
    url: `/api/home_quality/errorDetailsListExport`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

export function errorDetailsLCListExport(data) {
  return request_blob({
    url: `/api/home_sz_quality/errorDetailsList`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

// 其他统计数据详情导出
export function otherStatisticsDataExport(data) {
  return request_blob({
    url: `/api/ssbfz/getBfzList`,
    method: 'post',
    responseType: 'blob',
    data
  })
}


// 其他统计数据详情导出
export function otherStatisticsMenuExport(data) {
  return request_blob({
    url: `/api/ssbfz/exportAll`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

// 编码员缺陷导出
export function encoderErrorExport(data) {
  return request_blob({
    url: `/api/bmy/bmyQualityList`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

// 医师排名导出
export function medicalRecordDoctorExport(data) {
  return request_blob({
    url: `/bazb/case-quality/medical_record_doctor`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

// 首页质控（编码员）--医师排名导出
export function bmyDoctorRanking(data) {
  return request_blob({
    url: `/api/bmy/doctorRanking`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

// 首页质控（编码员）--医师排病历总数
export function bmyDoctorRankingBlExport(data) {
  return request_blob({
    url: `/api/bmy/doctorRankingDrillList`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

export function errorDataListExport(data) {
  return request_blob({
    url: `/bazb/errorDataList`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

// 首页质控（编码员）--医师排病历扣分
export function bmyDoctorRankingBlKfExport(data) {
  return request_blob({
    url: `/api/bmy/doctorErrorRanking`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

export function blZkListExport(data) {
  return request_blob({
    url: `/api/getBlZkList`,
    method: 'post',
    responseType: 'blob',
    data
  })
}
export function correctionListExport(data) {
  return request_blob({
    url: `/api/correction_list`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

// 编码员缺陷问题导出
export function encoderProblemExport(data) {
  return request_blob({
    url: `/api/bmy/qualityExport`,
    method: 'post',
    responseType: 'blob',
    data
  })
}

// 重点指标导出
export function majorIndexExport(data) {
  // const { year, category } = data
  // return request_blob({
  //   url: `/bazb/quality_index_list?type=1&year=${year}&is_export=1&category=${category}`,
  //   method: 'get',
  //   responseType: 'blob'
  // })
  const { apiType, cysj_start, cysj_end, category, AAC11N, AEE03, is_export = 1 } = data;
  if (apiType === '0') {
    return request_blob({
      url: `/bazb/quality_index_list`,
      method: 'get',
      responseType: 'blob',
      params: {
        type: 1,
        cysj_start,
        cysj_end,
        category,
        AAC11N,
        AEE03,
        is_export
      }
    });
  } else {
    return request_blob({
      url: `/bazb/catalog_get_custom_data`,
      method: 'get',
      responseType: 'blob',
      params: {
        type: 1,
        cysj_start,
        cysj_end,
        category,
        AAC11N,
        AEE03,
        is_export,
        index_name: data.nodeName
      }
    });
  }
}

// 重点指标详情导出
export function majorIndexDetailExport(data) {
  // const { year, category, month, zhuangtai, chuyuankesi, zyh } = data
  const {
    is_export = 1,
    category,
    AAC11N,
    AEE03,
    status,
    cysj_start,
    cysj_end,
    zyh,
    page,
    pagesize
  } = data;
  return request_blob({
    url: `/bazb/quality_index_detail_list`,
    method: 'post',
    responseType: 'blob',
    data: {
      type: 0,
      is_export,
      category,
      AAC11N,
      AEE03,
      status,
      cysj_start,
      cysj_end,
      zyh,
    }
  })
}

//
export function getDepartmentRankExport(data) {
  return request_blob({
    url: `api/CaseHistory/Terminal/getDepartmentRankExport`,
    method: 'post',
    responseType: 'blob',
    data
  })
}
