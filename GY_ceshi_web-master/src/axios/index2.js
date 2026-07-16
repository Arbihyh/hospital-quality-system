import store from '@/store';
import axios from 'axios';
import { Message, Loading } from 'element-ui';
import { getToken, setToken, removeToken } from '@/utils/auth';
import router from '@/router'
import domMessage from '@/utils/messageOnce'

const messageOnce = new domMessage()
// 公共数据
let loadingInstance = null; // 声明loading
let loadingCount = 0; // 声明接口并发计数器

// 记录和显示错误
function errorLog(error) {
  // 添加到日志
  store.dispatch('vxadmin/log/push', {
    message: '数据请求异常',
    type: 'danger',
    meta: {
      error,
    },
  });
  // 打印到控制台

  if (process.env.NODE_ENV === 'development') {
    console.log(error);
  }
  // 显示提示
  messageOnce.error({
    message: error.message,
    type: 'error',
    duration: 5 * 1000,
  });
}
// 创建一个 axios 实例
const service2 = axios.create({
  baseURL: process.env.VUE_APP_BASE_API2,
  timeout: 80000
});

// 请求拦截器
service2.interceptors.request.use(
  config => {
    const { isNoLoading = false } = config.headers || {};
    const { NODE_ENV } = process.env
    if (!isNoLoading && loadingCount == 0) {
      loadingInstance = Loading.service({
        lock: false,
        customClass: 'z-index999',
        text: '加载中，请稍后...',
        spinner: 'ui-icon-loading',
        background: 'rgba(0, 0, 0, 0.7)',
      });
    }
    if (store.getters.token) {
      config.headers['token'] = getToken();
    }
    loadingCount++;
    // 在请求发送之前做一些处理
    let jsonStr = config.data;
    let data = jsonStr;
    config.data = data;
    return config;
  },
  error => {
    // 发送失败
    Promise.reject(error);
  },
);

// 响应拦截器
service2.interceptors.response.use(
  response => {
    loadingCount--;
    if (loadingInstance && loadingCount == 0) {
      loadingInstance.close();
    }
    const dataAxios = response.data;
    const { code } = dataAxios;
    // 根据 code 进行判断}
    if (code === 200) {
      // 如果没有 code 代表这不是项目后端开发的接口 比如可能是 VXAdmin 请求最新版本
      return dataAxios;
    } else {
      if (code == -1) {
        messageOnce.error({
          message: `${dataAxios.msg}`,
          type: 'error',
          duration: 5 * 1000,
        });
        setToken('');
        // 清除缓存的权限菜单
        //跳转登录页面
        router.push({path:`/login`})
        return
      }
      messageOnce.error({
        message: `${dataAxios.msg}`,
        type: 'error',
        duration: 5 * 1000,
      });
      return Promise.reject(dataAxios.data);
      //   //  发送的接口为response.config.url，进行报错处理
    }
  },
  error => {
    loadingCount--;
    if (loadingInstance && loadingCount == 0) {
      loadingInstance.close();
    }
    if (error && error.response) {
      switch (error.response.status) {
        case 400:
          error.message = '请求错误';
          break;
        case 401:
          error.message = '未授权，请登录';
          break;
        case 403:
          error.message = '拒绝访问';
          break;
        case 404:
          error.message = `请求地址出错: ${error.response.config.url}`;
          break;
        case 408:
          error.message = '请求超时';
          break;
        case 500:
          error.message = '服务器内部错误';
          break;
        case 501:
          error.message = '服务未实现';
          break;
        case 502:
          error.message = '网关错误';
          break;
        case 503:
          error.message = '服务不可用';
          break;
        case 504:
          error.message = '网关超时';
          break;
        case 505:
          error.message = 'HTTP版本不受支持';
          break;
        default:
          break;
      }
    }
    errorLog(error);
    return Promise.reject(error);
  },
);

export default service2;
