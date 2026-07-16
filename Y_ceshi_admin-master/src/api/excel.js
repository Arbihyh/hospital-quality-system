import request_blob from '@/utils/request_blob'

// 搜索日志数据下载
export function userSearchLogExport(data) {
  return request_blob({
    url: `/user/userSearchLogExport`,
    method: 'post',
    responseType: 'blob',
    data: data
  })
}

export function export_all_rule_list(data) {
  return request_blob({
    url: `/quality_rule/export_all_rule_list`,
    method: 'post',
    responseType: 'blob',
    data: data
  })
}

// 疾病库模板导出
export function illnessTemplateExport(data) {
  return request_blob({
    url: `/disease/templateExport`,
    method: 'post',
    responseType: 'blob',
    data: data
  })
}

// 疾病库导出
export function illnessExport(data) {
  return request_blob({
    url: `/disease/diseaseExport`,
    method: 'post',
    responseType: 'blob',
    data: data
  })
}

// 疾病库导入
export function illnessImport(data) {
  return request_blob({
    url: `/disease/diseaseImport`,
    method: 'post',
    data: data
  })
}

// 手术库模板导出
export function surgeryTemplateExport(data) {
  return request_blob({
    url: `/surgery/templateExport`,
    method: 'post',
    responseType: 'blob',
    data: data
  })
}

// 手术库导出
export function surgeryExport(data) {
  return request_blob({
    url: `/surgery/surgeryExport`,
    method: 'post',
    responseType: 'blob',
    data: data
  })
}

// 手术库导入
export function surgeryImport(data) {
  return request_blob({
    url: `/surgery/surgeryImport`,
    method: 'post',
    data: data
  })
}

// 2.0转3.0导出
export function ssczysExport(data) {
  return request_blob({
    url: `/ssczys/ssczysExport`,
    method: 'post',
    data: data
  })
}

// 手术类别导出
export function ssczExport(data) {
  return request_blob({
    url: `/sscz/ssczExport`,
    method: 'post',
    data: data
  })
}
