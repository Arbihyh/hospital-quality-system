import request from '@/utils/request';
import axios from '@/axios/index'
import store from '@/store';
import { getToken } from '@/utils/auth';
export function login(data) {
   let login = {
     name :data.username,
     password:data.password
   }
   store.state.login = login
  //  axios.post('/login',login).then(res=>{
  //     // console.log(res)
  //     store.getters.token = res.data.token
  //     // config.headers['X-Token'] = getToken();
  //     commit('SET_TOKEN',res.data.token);
  //     setToken(res.data.token);
  //     return res;
  //  })
  return request({
    url: '/login',
    method: 'post',
    params:login,
  });
}

export function hisLogin(data) {
  let login = {
    name:data.name
  }
  store.state.login = login
  return request({
    url: '/thirdLogin',
    method: 'post',
    data: data
  });
}

export function getInfo(token) {
  return request({
    url: '/vue-admin-template/user/info',
    method: 'get',
    params: { token },
  });
}

export function logout() {
  return request({
    url: '/logout',
    method: 'post',
  });
}
