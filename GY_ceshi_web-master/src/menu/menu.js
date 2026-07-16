import Layout from '@/layout'//引入admin的layout布局
import router from '../router'//引入router
import request from '../api/request.js'//自定义封装的请求
import store from '../store'//Vuex

export default {
  // 获取路由菜单
  getMenu() {
    return new Promise((resolve, reject) => {
      return request.getRoleMenu().then(res => {
        //声明一个空数组，用来装处理好的菜单信息
        const result = []
        // 获取到路由菜单，进行数据处理
        this.parseRoute(res.data,result)
        // 添加菜单
        this.addMenu(result)
        //缓存用户菜单，我这里使用的是sessionStorage，用localStorage也可以
        // 输出成功
        resolve()
      }).catch(err =>{
        reject()
      })
    })
  },
  // 对路由菜单数据处理
  parseRoute(fullList, resultList) {
    return new Promise((resolve, reject) => {
      let result = []
      fullList.forEach( (ele,index) => {
        const childrenList =  this.processChildren(ele.children, ele['path']);
        const routerObject = {
          path: ele['path'],
          component: Layout,
          redirect: ele['redirect']?ele['redirect']:'noRedirect',
          alwaysShow: ele['alwaysShow'],  // 是否始终为根元素
          meta: {
            title: (ele.meta && ele.meta['title']) || '',
            icon: (ele.meta && ele.meta['icon']) || '',
          },
          children: childrenList,
        }
        //插入组装好的数据
        resultList.push(routerObject);
        //插入最终完整的数据列表
        result.push(routerObject);
      });
      //输出已经组装好并且能用的数据
      resolve(result)
    })
  },
  // 递归处理子菜单
  processChildren(children = [], parentPath = '') {
    return children.map((childItem, index) => {
      const childRouter = {
        path: (childItem['path'] || '').trim(),
        name: childItem['name'] || '',
        hidden: childItem['hidden'] || false,
        meta: {
          title: (childItem.meta && childItem.meta['title']) || '',
          icon: (childItem.meta && childItem.meta['icon']) || '',
          keepAlive: childItem.meta && childItem.meta['keepAlive'] || 0,
        },
      };

      const componentPath = childItem.component;
      
      // 处理组件路径（只有叶子节点才需要组件）
      // if (componentPath && typeof componentPath === 'string') {
      //   childRouter.component = resolve => require([`@/views/${componentPath}`], resolve);
      // }

      // // 递归处理更深层的子菜单
      // if (childItem.children && childItem.children.length > 0) {
      //   childRouter.children = this.processChildren(childItem.children, parentPath + '/' + childItem.path);
      // }


      // 处理如包含核心制度指标 start
      if (childItem.meta && childItem.meta.title === '核心制度指标' && !componentPath) {
        childRouter.component = {
          render: h => h('router-view')
        };
        childRouter.alwaysShow = true;
      }

      else if (childItem.meta && ['指标分析', '指标概括', '指标上报'].includes(childItem.meta.title)) {
        const fullCompPath = componentPath.endsWith('.vue') ? componentPath : `${componentPath}.vue`;
        childRouter.component = resolve => require([`@/views/${fullCompPath}`], resolve);
        childRouter.path = childItem.path;
      }
      else if (componentPath && typeof componentPath === 'string') {
        childRouter.component = resolve => require([`@/views/${componentPath}`], resolve);
      }

      if (childItem.children && childItem.children.length > 0) {
        const newParentPath = (childItem.meta && childItem.meta.title === '核心制度指标')
          ? ''
          : (parentPath + '/' + childItem.path);
        childRouter.children = this.processChildren(childItem.children, newParentPath);
      }
      // 处理如包含核心制度指标 end

      return childRouter;
    }).filter(item => item !== null && !item.hidden); // 过滤掉无效项
  },
  // 添加菜单
  addMenu(data) {
    return new Promise((resolve, reject) => {
      // 在处理完的菜单列表数据后面插入404页面，404必须存在菜单列表的最后一项，否则会对所有页面进行拦截，并跳转404页面
      data.push({
        path: '*',
        redirect: '/404',
        hidden: true
      })
      // 打印菜单列表
      // console.log(data)
      // 将可用的路由权限列表存入Vuex
      store.dispatch('user/modifyMenu', data)
      // 添加菜单
      router.addRoutes(data)
      // 将路由元注入路由对象，必须添加
      router.options.routes.push(data)
      console.log('router.options.routes', router.options.routes)
      //输出成功
      resolve()
    })
  },
  //
}
