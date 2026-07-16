<template>
  <div :class="{ 'has-logo': showLogo }">
    <!-- logo 非生产环境展示 -->
    <logo v-if="showLogo" :collapse="isCollapse" />
    <el-scrollbar wrap-class="scrollbar-wrapper">
      <el-menu
        :default-active="activeMenu"
        :collapse="isCollapse"
        background-color="#185da6"
        :text-color="variables.menuText"
        :unique-opened="false"
        active-text-color="#fff"
        :collapse-transition="false"
        mode="vertical"
        router
      >
        <sidebar-item v-for="route in routes" :key="route.path" :item="route" :base-path="route.path" />
      </el-menu>
    </el-scrollbar>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import Logo from './Logo';
import SidebarItem from './SidebarItem';
import variables from '@/styles/variables.scss';

export default {
  components: { SidebarItem, Logo },
  computed: {
    ...mapGetters(['sidebar']),
    isProd() {
      return process.env.NODE_ENV !== 'production'
    },
    routes() {
      //修改的代码
      let menuRouter = this.$store.state.user.menu,
      // consoleMenu = menuRouter.filter(item => item.name === 'console')
      routeMenu = [...this.$router.options.routes,...menuRouter]
      console.log('====...this.$router.options.routes======', JSON.parse(JSON.stringify(routeMenu.filter(item => !item.hidden && item.meta))))
      return routeMenu.filter(item => !item.hidden && item.meta)
      //源码中的代码
      // return this.$router.options.routes;
    },
    activeMenu() {
      const route = this.$route;
      const { meta, path } = route;
      // if set path, the sidebar will highlight the path you set
      if (meta.activeMenu) {
        return meta.activeMenu;
      }
      return path;
    },
    showLogo() {
      return this.$store.state.settings.sidebarLogo;
    },
    variables() {
      return variables;
    },
    isCollapse() {
      return !this.sidebar.opened;
    },
  },
};
</script>
<style lang="scss">
@import "~element-ui/packages/theme-chalk/src/common/var";
  .el-menu-item.is-active {
    font-weight: bold;
    background-color: $--color-primary !important;
  }
 
  .el-menu-item:hover, .el-menu-item:focus {
    //background-color: $--color-primary !important;
    color: #fff !important;
    font-weight: bold;
  }
  .el-submenu__title:hover, .el-submenu__title:focus {
    // background-color: $--color-primary !important;
    color: #fff !important;
    font-weight: bold;
  }
</style>
