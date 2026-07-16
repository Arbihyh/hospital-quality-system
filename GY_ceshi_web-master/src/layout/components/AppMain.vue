<template>
  <section class="app-main">
    <div v-if="!$route.meta.nocrumb" style="height: 42.5px;">
      <breadcrumb :keep-alive-component-instance="keepAliveComponentInstance" :t_name="t_name" class="breadcrumb-container" />
    </div>
    <div ref="keepAliveContainer" class="keepAliveContainer" :style="{
      height: !$route.meta.nocrumb ? `calc(100% - 42.5px)` : '100%'
      }">
      <keep-alive>
        <router-view v-if="$route.meta.keepAlive && isRouterAlive" :key="$route.fullPath"/>
      </keep-alive>
      <router-view v-if="!$route.meta.keepAlive && isRouterAlive" :key="$route.fullPath"></router-view>
    </div>

  </section>
</template>

<script>
import { setToken } from '@/utils/auth';
import Breadcrumb from './Breadcrumb.vue';
export default {
  name: 'AppMain',
  name: 'Layout',
  components: {
    Breadcrumb
  },
  
  provide () {
    // 父组件中通过provide来提供变量，在子组件中通过inject来注入。
    return {
      reloads: this.reloads,
      t_title: this.t_title
    };
  },
  data() {
    return {
      keepAliveComponentInstance: null,
      isRouterAlive: true,
      t_name:''
    };
  },
  methods:{
    reloads () {
      this.isRouterAlive = false;
      this.$nextTick(function () {
        this.isRouterAlive = true;
      });
    },
    t_title(e){
      this.t_name = e;
      this.$nextTick(function () {
        this.t_name = '';
      });
      console.log(this.t_name)
    }

  },
  mounted() {
    setTimeout(() => {
      window.addEventListener('message',function(e){
        if (e.data == 12312312) {
          setToken('');
          this.$router.push(`/login`);
        }
      },false)
    }, 2000)
    this.keepAliveComponentInstance = this.$refs.keepAliveContainer.childNodes[0].__vue__;

  },
  watch: {
    $route(to, from) {
      const { tagsView } = this.$store.state
      const whiteList = ['/hospital-search', '/hospital-caseViews', '/hospital-details', '/hospital-chargeDetails']
      if (tagsView && tagsView.visitedViews.length) {
        let bSwitch = 1
        tagsView.visitedViews.map(item => {
          if (whiteList.includes(item.path)) {
            bSwitch = 0
          }
        })
        if (bSwitch) {
          sessionStorage.removeItem("jingmiao_token")
          this.$router.push(`/login`);
        }
      }
    }
  }
};
</script>

<style scoped>
.app-main {
  /*50 = navbar  */
  /* min-height: calc(100vh - 60px); */
  flex: 1;
  display: flex;
  flex-direction: column;
  width: 100%;
  /* width: 100%; */
  position: relative;
  /* overflow: hidden; */
  background: #f4f4f4;
  height: 100%;
}
.fixed-header + .app-main {
  margin-top: 50px;
  height: calc(100% - 50px);
}
.keepAliveContainer {
    flex: 1;
    display: flex;
    width: 100%;
    height: 100%;
}

.keepAliveContainer > div {
  /* display: flex; */
  /* flex: 1; */
  /* flex-direction: column; */
  /* overflow: auto; */
  width: calc(100% - 32px);
  height: calc(100% - 32px);
  padding: 0px !important;
  overflow: auto;
  margin: 16px !important
}
</style>

<style lang="scss">
// fix css style bug in open el-dialog
.el-popup-parent--hidden {
  .fixed-header {
    padding-right: 15px;
  }
}
</style>
