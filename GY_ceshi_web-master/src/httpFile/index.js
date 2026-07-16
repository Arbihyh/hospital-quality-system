// 下载文件流, 传入参数为 接口 请求参数
import axios from 'axios'
import { Message, Loading } from 'element-ui';
import { getToken } from '@/utils/auth';
import store from '@/store';
// 下载文件流, 传入参数为 接口 请求参数
export function downloadFile (url, data = {}, type = 'xls', name) {
  let loadingInstance = Loading.service({
    lock: false,
    customClass: 'z-index999',
    text: '加载中，请稍后...',
    spinner: 'ui-icon-loading',
    background: 'rgba(0, 0, 0, 0.7)'
  })
  axios.defaults.baseURL = process.env.VUE_APP_API
  return new Promise((resolve, reject) => {
    const formData = new FormData()
    if(data.field){
      data.field=JSON.stringify(data.field)
     }
    Object.keys(data).forEach(key => {
      if (data.hasOwnProperty(key)) {
        formData.append(key, data[key])
      }
    })
    let config = {
      headers: {
        'Content-Type': 'multipart/form-data'
      },
      responseType: 'blob'
    }
    if (store.getters.token) {
      config.headers['token'] = getToken();
    }
    axios.post(url, formData, config).then(res => {
      loadingInstance.close()
      if (res) {
        const content = res.data
        const blob = new Blob([content])
        // 如果返回格式为json, 即为报错, 抛出报错信息
        if (content.type === 'application/json') {

          var reader = new FileReader()
          reader.readAsText(blob)
          console.error("1",res);
          reader.onload = e => {

            const { jsonError } = JSON.parse(e.target.result)
            var errormsg=JSON.parse(e.target.result);

            // const error = new Error(`${jsonError[0]._exceptionMessage}`)
            console.error("2",errormsg);

            Message({
              message: errormsg.msg,
              type: 'error',
              duration: 10 * 1000
            })
            throw error
          }
        } else {
          // 如果不为json, 进入下载流程
          // const fileName = `${res.name}.${type}`
          const fileName = `${name}.${type}`
          if ('download' in document.createElement('a')) {
            // 非IE下载
            const elink = document.createElement('a')
            elink.download = fileName
            elink.style.display = 'none'
            elink.href = URL.createObjectURL(blob)
            elink.click()
            URL.revokeObjectURL(elink.href)
          } else {
            // IE下载
            navigator.msSaveBlob(blob, fileName)
          }
          resolve(res)
        }
      }
    }).catch(err => {
      loadingInstance.close()
      reject(err)
    })
  })
}