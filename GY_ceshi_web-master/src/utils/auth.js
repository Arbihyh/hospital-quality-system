import Cookies from 'js-cookie';

const TokenKey = 'jingmiao_token';

// 建坤 每次打开都要重新登录，token 存在 sessionStorage
export function getToken() {
  return sessionStorage.getItem(TokenKey)
  // return Cookies.get(TokenKey);
}

export function setToken(token) {
  return sessionStorage.setItem(TokenKey, token)
  // return Cookies.set(TokenKey, token);
}

export function removeToken() {
  return sessionStorage.removeItem(TokenKey)
  // return Cookies.remove(TokenKey);
}
