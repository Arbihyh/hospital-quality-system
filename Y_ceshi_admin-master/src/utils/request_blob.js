import axios from 'axios'
import { Message } from 'element-ui'
import { getToken } from '@/utils/auth'

// create an axios instance
const service = axios.create({
  baseURL: process.env.VUE_APP_BASE_API,
  transformRequest: [function(data) {
    const form = new FormData()
    if (!data) {
      return form
    }
    Object.keys(data).forEach(k => {
      const v = (data[k] !== 0 && !data[k] || data[k] === null ? '' : data[k])
      if (Array.isArray(v) && v !== null) {
        Object.keys(v).forEach(key => {
          const vv = (!v[key] || v[key] === null ? '' : v[key])
          form.append(`${k}[${key}]`, vv)
        })
      } else {
        form.append(k, v)
      }
    })
    return form
  }],
  headers: {
    'Content-Type': 'application/x-www-form-urlencoded'
  }
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
    return config
  },
  error => {
    // do something with request error
    console.log(error) // for debug
    return Promise.reject(error)
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
    return response
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
