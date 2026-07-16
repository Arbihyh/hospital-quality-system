import request from '@/utils/request';

export default {
	//...其他的请求
  // 获取权限菜单列表
  getRoleMenu(){
    return request({
      url: `/user/menus`,
      method: 'post',
    })
  },
   // ...其他的请求
  //
}