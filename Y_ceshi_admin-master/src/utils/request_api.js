import axios from 'axios'
import { Message } from 'element-ui'
import { getToken, removeToken } from '@/utils/auth'
import router, { resetRouter } from '@/router'

// create an axios instance
const service = axios.create({
  baseURL: process.env.VUE_APP_BASE_API3, // url = base url + request url
  timeout: 60000 // request timeout
  // transformRequest: [function(data) { // 转换数据
  //   const form = new FormData()
  //   if (!data) {
  //     return form
  //   }
  //   Object.keys(data).forEach(k => {
  //     const v = (data[k] !== 0 && !data[k] || data[k] === null ? '' : data[k])
  //     if (Array.isArray(v) && v !== null) {
  //       Object.keys(v).forEach(key => {
  //         const vv = (!v[key] || v[key] === null ? '' : v[key])
  //         form.append(`${k}[${key}]`, vv)
  //       })
  //     } else {
  //       form.append(k, v)
  //     }
  //   })
  //   return form
  // }],
  // headers: {
  //   'Content-Type': 'application/x-www-form-urlencoded'
  // }
})

// request interceptor
service.interceptors.request.use(
  config => {
    // do something before request is sent

    if (getToken()) {
      // console.log(config, getToken())
      // let each request carry token
      // ['X-Token'] is a custom headers key
      // please modify it according to the actual situation
      config.headers['token'] = getToken()
    }
    // 在请求发送之前做一些处理
    const jsonStr = config.data
    const data = jsonStr
    config.data = data
    return config
  },
  error => {
    // 发送失败
    Promise.reject(error)
  }
)

// response interceptor
service.interceptors.response.use(
  /**
   * If you want to get http information such as headers or status
   * Please return  response => response
  */

  /**
   * Determine the request status by custom code
   * Here is just an example
   * You can also judge the status by HTTP Status Code
   */
  response => {
    const dataAxios = response.data
    const { code } = dataAxios
    // 根据 code 进行判断}
    if (code === 200) {
      // 如果没有 code 代表这不是项目后端开发的接口 比如可能是 VXAdmin 请求最新版本
      return dataAxios
    } else {
      if (code === -1) {
        Message({
          message: `${dataAxios.msg}`,
          type: 'error',
          duration: 3 * 1000
        })
        removeToken()
        resetRouter()
        router.push(`/login`)
        return
      }
      return Promise.reject(dataAxios.data)
      //   //  发送的接口为response.config.url，进行报错处理
    }
  },
  error => {
    console.log('err' + error) // for debug
    Message({
      message: error.message,
      type: 'error',
      duration: 5 * 1000
    })
    return Promise.reject(error)
  }
)

export default service
