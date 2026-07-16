<template>
  <div class="sidebar-logo-container" :class="{ collapse: collapse }">
    <transition name="sidebarLogoFade">
      <router-link v-if="collapse" key="collapse" class="sidebar-logo-link" to="/">
        <img v-if="systemSetting.menu_logo" :src="systemSetting.menu_logo" class="sidebar-logo" />
        <h1 v-else class="sidebar-title">{{ title }}</h1>
      </router-link>
      <router-link v-else key="expand" class="sidebar-logo-link" to="/">
        <img v-if="systemSetting.menu_logo" :src="systemSetting.menu_logo" class="sidebar-logo5" />
        <h1 class="sidebar-title">{{ title }}</h1>
      </router-link>
    </transition>
  </div>
</template>

<script>
import { mapState } from 'vuex';

export default {
  name: 'SidebarLogo',
  props: {
    collapse: {
      type: Boolean,
      required: true,
    },
  },
  computed: {
    ...mapState({
      systemSetting: (state) => state.app.systemSetting
    }),
  },
  data() {
    return {
      title: '',
    };
  },
  created() {},
};
</script>

<style lang="scss" scoped>
.sidebarLogoFade-enter-active {
  transition: opacity 1.5s;
}

.sidebarLogoFade-enter,
.sidebarLogoFade-leave-to {
  opacity: 0;
}

.sidebar-logo-container {
  position: relative;
  width: 100%;
  height: 88px;
  line-height: 88px;
  text-align: center;
  overflow: hidden;
  border-bottom: 1px solid #b7b7b7;

  & .sidebar-logo-link {
    height: 100%;
    width: 100%;

    & .sidebar-logo {
      width: 32px;
      height: 32px;
      vertical-align: middle;
      border-radius: 50%;
    }

    & .sidebar-logo2 {
      width: 118px;
      height: 32px;
      vertical-align: middle;
      margin-right: 12px;
    }
    & .sidebar-logo5 {
      width: 68px;
      height: 68px;
      border-radius: 50%;
      vertical-align: middle;
    }
    & .sidebar-title {
      display: inline-block;
      margin: 0;
      color: #fff;
      font-weight: 600;
      line-height: 50px;
      font-size: 14px;
      font-family: Avenir, Helvetica Neue, Arial, Helvetica, sans-serif;
      vertical-align: middle;
    }
  }

  &.collapse {
    .sidebar-logo {
      margin-right: 0px;
      border-radius: 50%;
    }
  }
}
</style>
