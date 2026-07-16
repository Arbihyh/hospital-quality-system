<template>
  <div :class="classObj" class="app-wrapper">
    <div v-if="device === 'mobile' && sidebar.opened" class="drawer-bg" @click="handleClickOutside" />
    <sidebar class="sidebar-container" id="sidebarContainer"/>
    <!-- <sidebar class="sidebar-container" ref="sidebarContainer"/> -->
    <div class="main-container" id="mainContainer">
      <div :class="{ 'fixed-header': fixedHeader }">
        <navbar />
      </div>
      <app-main />
    </div>
  </div>
</template>

<script>
import { Navbar, Sidebar, AppMain } from './components';
import ResizeMixin from './mixin/ResizeHandler';

export default {
  name: 'Layout',
  components: {
    Navbar,
    Sidebar,
    AppMain,
  },
  mixins: [ResizeMixin],
  computed: {
    sidebar() {
      return this.$store.state.app.sidebar;
    },
    device() {
      return this.$store.state.app.device;
    },
    fixedHeader() {
      return this.$store.state.settings.fixedHeader;
    },
    classObj() {
      return {
        hideSidebar: !this.sidebar.opened,
        openSidebar: this.sidebar.opened,
        withoutAnimation: this.sidebar.withoutAnimation,
        mobile: this.device === 'mobile',
      };
    },
  },
  data() {
    return {
      observer: null
    }
  },
  methods: {
    handleClickOutside() {
      this.$store.dispatch('app/closeSideBar', { withoutAnimation: false });
    },
    getSystemSetting() {
      this.$axios.get('/get_setting').then(res => {
        if(res.code == 200) {
          const { web_name, background_img, logo, menu_logo } = res.data
          this.$store.dispatch('app/setSystemSetting', {
            background_img: background_img.content,
            logo: logo.content,
            menu_logo: menu_logo.content,
            web_name: web_name.content
          });
        }
      })     
    }
  },
  mounted() {
    // 目标元素
    const targetElement = document.querySelector('.sidebar-container');

    // 创建观察者
    this.observer = new ResizeObserver((entries) => {
      for (const entry of entries) {
        const { width } = entry.contentRect;
        console.log('新宽度:', width);
         document.getElementById('mainContainer').style.width = `${document.getElementsByClassName('app-wrapper')[0].offsetWidth - document.getElementsByClassName('sidebar-container')[0].offsetWidth}px`
        // 执行你的逻辑
      }
    });

    // 开始观察
    this.observer.observe(targetElement);
    this.getSystemSetting();
    // document.getElementById('mainContainer').style.width = `calc(100% - (${document.getElementById('sidebarContainer')}px))`
    // document.getElementById('mainContainer').style.width = `${document.getElementsByClassName('app-wrapper')[0].offsetWidth - document.getElementsByClassName('sidebar-container')[0].offsetWidth}px`
  },
  beforeDestroy() {
    this.observer.disconnect()
  }
};
</script>

<style lang="scss" scoped>
@import '~@/styles/mixin.scss';
@import '~@/styles/variables.scss';

.app-wrapper {
  @include clearfix;
  position: relative;
  height: 100%;
  width: 100%;
  overflow: scroll;
  display: flex;
  &.mobile.openSidebar {
    position: fixed;
    top: 0;
  }
}
.app-wrapper::-webkit-scrollbar {  display: none; }

// IE 10+

.app-wrapper { -ms-overflow-style: none; }

// Firefox

.app-wrapper { overflow: -moz-scrollbars-none; }
.drawer-bg {
  // background: #000;
  opacity: 0.3;
  width: 100%;
  top: 0;
  height: 100%;
  position: absolute;
  z-index: 999;
}

.fixed-header {
  position: fixed;
  top: 0;
  right: 0;
  z-index: 9;
  width: calc(100% - #{$sideBarWidth});
  transition: width 0.28s;
}

.hideSidebar .fixed-header {
  width: calc(100% - 54px);
}

.mobile .fixed-header {
  width: 100%;
}
</style>
