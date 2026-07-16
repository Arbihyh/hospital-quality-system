import Vue from 'vue';

import 'normalize.css/normalize.css'; // A modern alternative to CSS resets

import ElementUI from 'element-ui';
import 'element-ui/lib/theme-chalk/index.css';
import locale from 'element-ui/lib/locale/lang/zh-CN';
import '@/styles/index.scss'; // global css
import '@/styles/custom.scss'; // global css
import '@fortawesome/fontawesome-free/css/all.css'

import App from './App';
import store from './store';
import router from './router';
import '@/icons'; // icon
import '@/permission'; // permission control
import './assets/icons/iconfont.css'
// baseUrl = '/api
import axios from '@/axios/index';
// baseUrl = '/bazb
import axios2 from '@/axios/index2';
// baseUrl = '/bass
import axios3 from '@/axios/index3';
// baseUrl = '/yxbl
import axios4 from '@/axios/index4';
// baseUrl = '/
import axios_new from '@/axios/index_new';
import '@/mixins';

import * as echarts from 'echarts'
Vue.prototype.$echarts = echarts
// 引入弹窗拖拽一方法
import VueDragResize from 'vue-drag-resize'
Vue.component('vue-drag-resize', VueDragResize)
Vue.prototype.$getViewportSize = function() {
  return {
    width: window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth,
    height: window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight,
  }
}
// 注册全局指令
import elDragDialog from './directive/el-drag-dialog'
Vue.use(elDragDialog, { directiveName: 'el-drag-dialog' })

// 全局组件
import CardTitle from '@/components/CardTitle'
import CardTitleCollapse from '@/components/CardTitle/card-title-collapse.vue'
Vue.component('CardTitle', CardTitle)
Vue.component('CardTitleCollapse', CardTitleCollapse)
/**
 * If you don't want to use mock-server
 * you want to use MockJs for mock api
 * you can execute: mockXHR()
 *
 * Currently MockJs will be used in the production environment,
 * please remove it before going online ! ! !
 */
if (process.env.NODE_ENV === 'production') {
  const { mockXHR } = require('../mock');
  mockXHR();
}
Vue.prototype.$axios = axios;
Vue.prototype.$axios2 = axios2;
Vue.prototype.$axios3 = axios3;
Vue.prototype.$axios4 = axios4;
Vue.prototype.$axios_new = axios_new;
// set ElementUI lang to EN
Vue.use(ElementUI, { locale });
// 如果想要中文版 element-ui，按如下方式声明
// Vue.use(ElementUI)

Vue.config.productionTip = false;

new Vue({
  el: '#app',
  router,
  store,
  render: h => h(App),
});
