import Vue from 'vue'
import axios from 'axios'

import Cookies from 'js-cookie'

import 'normalize.css/normalize.css' // a modern alternative to CSS resets

import Element from 'element-ui'
import './styles/element-variables.scss'

import '@/styles/index.scss' // global css
import '@/styles/custom.scss' // global css

import App from './App'
import store from './store'
import router from './router'

import i18n from './lang' // internationalization
import './icons' // icon
import './permission' // permission control
import './utils/error-log' // error log
import Pagination from '@/components/Pagination'

import * as filters from './filters'

import tools from './utils/tools'
import { resetForm, findArrayById } from './utils/jingmiao'
import checkPermission from '@/utils/permission'

Vue.component('Pagination', Pagination)
/**
 * If you don't want to use mock-server
 * you want to use MockJs for mock api
 * you can execute: mockXHR()
 *
 * Currently MockJs will be used in the production environment,
 * please remove it before going online ! ! !
 */
// if (process.env.NODE_ENV === 'production') {
//   const { mockXHR } = require('../mock')
//   mockXHR()
// }
Vue.prototype.axios = axios
// 注册全局指令
import elDragDialog from './directive/el-drag-dialog'
Vue.use(elDragDialog, { directiveName: 'el-drag-dialog' })

Vue.use(Element, {
  size: Cookies.get('size') || 'small', // set element-ui default size
  i18n: (key, value) => i18n.t(key, value)
})

// register global utility filters
Object.keys(filters).forEach(key => {
  Vue.filter(key, filters[key])
})
const domain = 'http://f.ssdogdog.com/'
const defaultPhotoUrl = 'user/a/177ea862-f8c0fcf889a55b2f-0x0.jpg'
Vue.prototype.GLOBAL = {
  domain,
  defaultPhotoUrl
}

Vue.prototype.TOOLS = tools
Vue.prototype.checkPermission = checkPermission
Vue.prototype.resetForm = resetForm

Vue.filter('formatSingleInArray', findArrayById)

new Vue({
  el: '#app',
  router,
  store,
  i18n,
  render: h => h(App)
})
