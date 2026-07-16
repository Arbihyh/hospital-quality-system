import axios from 'axios'
import { Message, Loading } from 'element-ui'
import store from '@/store'
import { getToken } from '@/utils/auth'


// create an axios instance
const service = axios.create()
let loadingInstance
// request interceptor
service.interceptors.request.use(
  config => {
    if (store.getters.token) {
      config.headers['token'] = `${getToken()}`
    }
    loadingInstance = Loading.service({
      lock: false,
      customClass: 'z-index999',
      text: '加载中，请稍后...',
      spinner: 'ui-icon-loading',
      background: 'rgba(0, 0, 0, 0.7)'
    })
    return config
  },
  error => {
    // do something with request error
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
    loadingInstance.close()
    return response
  },
  error => {
    loadingInstance.close()
    console.log('err' + error) // for debug
    Message({
      message: error.message,
      type: 'error',
      duration: 5 * 1000,
      offset: 112
    })
    return Promise.reject(error)
  }
)

export default service
