import request from "../../api/request.js"
import menu from "../../menu/menu.js"
import { login, logout, getInfo, hisLogin } from '@/api/user';
import { getToken, setToken, removeToken } from '@/utils/auth';
import router, { constantRoutes, resetRouter } from '@/router';

const getDefaultState = () => {
  return {
    token: getToken(),
    name: '',
    avatar: '',
    menu: [],//添加一个数组用来装菜单列表
    ifchange: false
  };
};

const state = getDefaultState();

const mutations = {
  RESET_STATE: state => {
    Object.assign(state, getDefaultState());
  },
  SET_TOKEN: (state, token) => {
    state.token = token;
  },
  SET_NAME: (state, name) => {
    state.name = name;
  },
  SET_AVATAR: (state, avatar) => {
    state.avatar = avatar;
  },
  SET_MENU: (state,menu) =>{
    state.menu = menu;
    state.ifchange = true
  }
};

const actions = {
  // user login
  login({ commit }, userInfo) {
    const { username, password } = userInfo;
    return new Promise((resolve, reject) => {
      login({ username: username.trim(), password: password })
        .then(response => {
          const { data } = response;
          localStorage.setItem('realname', data.realname)
          localStorage.setItem('loginName', data.name)
          commit('SET_NAME', data.realname);
          commit('SET_TOKEN', data.token);
          setToken(data.token);
          // menu.getMenu();  //获取权限菜单
          resolve();
        })
        .catch(error => {
          reject(error);
        });
    });
  },
  // HIS登录
  hisLogin({ commit }, data) {
    return new Promise((resolve, reject) => {
      console.log('=====data', data)
      hisLogin(data)
        .then(response => {
          const { token, realname } = response.data;
          localStorage.setItem('realname', realname)
          commit('SET_TOKEN', token);
          commit('SET_NAME', realname);
          setToken(token);
          resolve();
        })
        .catch(error => {
          reject(error);
        });
    });
  },
   modifyMenu({ commit },menu){
    return new Promise(resolve => {
      commit('SET_MENU',menu)
      resolve()
    })
  },
  // get user info
  getInfo({ commit, state }) {
    return new Promise((resolve, reject) => {
      getInfo(state.token)
        .then(response => {
          const { data } = response;

          if (!data) {
            return reject('Verification failed, please Login again.');
          }

          const { name, avatar } = data;

          commit('SET_NAME', name);
          commit('SET_AVATAR', avatar);
          resolve(data);
        })
        .catch(error => {
          reject(error);
        });
    });
  },

  // user logout
  logout({ commit, state }) {
    return new Promise((resolve, reject) => {
      logout(state.token)
        .then(() => {
          removeToken()
          resolve()
        })
        .catch(error => {
          reject(error);
        });
    });
  },

  // remove token
  resetToken({ commit }) {
    return new Promise(resolve => {
      // 清除缓存的权限菜单
      removeToken(); // must remove  token  first
      commit('RESET_STATE');
      resolve();
    });
  },
};

export default {
  namespaced: true,
  state,
  mutations,
  actions,
};
