import { constantRoutes } from '@/router'
import { menuList } from '@/api/menu'
import Layout from '@/layout/index'

/**
 * Use meta.role to determine if the current user has permission
 * @param roles
 * @param route
 */
function hasPermission(roles, route) {
  if (route.meta && route.meta.roles) {
    return roles.some(role => route.meta.roles.includes(role))
  } else {
    return true
  }
}

/**
 * Filter asynchronous routing tables by recursion
 * @param routes asyncRoutes
 * @param roles
 */
export function filterAsyncRoutes(routes, roles) {
  const res = []

  routes.forEach(route => {
    const tmp = { ...route }
    if (hasPermission(roles, tmp)) {
      if (tmp.children) {
        tmp.children = filterAsyncRoutes(tmp.children, roles)
      }
      res.push(tmp)
    }
  })

  return res
}

const state = {
  routes: [],
  addRoutes: []
}

const mutations = {
  SET_ROUTES: (state, routes) => {
    state.addRoutes = routes
    state.routes = constantRoutes.concat(routes)
  }
}

function generaMenu(data) {
  const res = []
  data.forEach(item => {
    let menu = {
      path: '/' + item.component,
      component: item.parent_id === 0 ? Layout : (resolve) => require([`@/views/${item.component}`], resolve),
      hidden: !item.visible,
      children: [],
      name: item.component,
      meta: {
        title: item.name,
        icon: item.icon,
        access: item.access
      }
    }
    if (item.parent_id === 0) {
      menu.redirect = 'noRedirect'
    }
    if (item.children) {
      menu.children = generaMenu(item.children)
    } else {
      if (item.parent_id === 0) {
        const s = item.component.split('/')
        const single = {
          path: '/' + s[0],
          component: Layout,
          hidden: false,
          children: [],
          name: s[0],
          meta: {
            title: item.name,
            icon: item.icon,
            access: item.access
          }
        }
        single.children = [
          {
            path: '/' + item.component,
            component: (resolve) => require([`@/views/${item.component}`], resolve),
            name: item.component,
            meta: {
              title: item.name,
              icon: item.icon,
              access: item.access
            }
          }
        ]
        menu = single
      }
    }
    res.push(menu)
  })

  return res
}
const actions = {
  generateRoutes({ commit }, roles) {
    return new Promise(resolve => {
      menuList().then(response => {
        let accessedRoutes = []
        const { p } = response
        if (p.length > 0) {
          accessedRoutes = generaMenu(response.p)
        }
        accessedRoutes.push({ path: '*', redirect: '/404', hidden: true })
        commit('SET_ROUTES', accessedRoutes)
        resolve(accessedRoutes)
      })
    }).catch(error => {
      console.log(error)
    })
  }
}

/* const actions = {
  async generateRoutes({ commit }, roles) {
    const data = await menuList().then(res => {
      const len = res.p.length
      const routeArr = []
      if (len >= 1) {
        res.p.forEach((v, k) => {
          const child = []
          v.child.forEach(vv => {
            let file
            const path = vv.url
            try {
              file = require(/!* webpackChunkName: "group-foo" *!/ `@/views/${path}`).default
            } catch (e) {
              file = require(/!* webpackChunkName: "group-foo" *!/ `@/views/dashboard/index`).default
            }
            child.push({
              path: `/${vv.url.toString()}`,
              component: file,
              hidden: parseInt(vv.is_show) !== 1,
              name: vv.name.toString(),
              meta: { title: vv.name.toString() }
            })
          })
          routeArr.push({
            path: `${child[0].path}?time=${+new Date()}`,
            component: Layout,
            name: v.name.toString(),
            alwaysShow: true,
            meta: {
              title: v.name.toString(),
              icon: v.icon.toString()
            },
            children: child
          })
        })
      }
      return routeArr.concat(asyncRoutes)
    })
    return new Promise(resolve => {
      // let accessedRoutes
      // if (roles.includes('admin')) {
      //   accessedRoutes = asyncRoutes || []
      // } else {
      //   accessedRoutes = filterAsyncRoutes(asyncRoutes, roles)
      // }
      // commit('SET_ROUTES', accessedRoutes)
      // resolve(accessedRoutes)
      const asyncRouter = filterAsyncRoutes(data, roles)
      commit('SET_ROUTES', asyncRouter)
      setMenu(asyncRouter)
      resolve(asyncRouter)
    })
  }
}*/

export default {
  namespaced: true,
  state,
  mutations,
  actions
}
