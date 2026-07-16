<template>
  <div class="navbar">
    <hamburger :is-active="sidebar.opened" class="hamburger-container" @toggleClick="toggleSideBar" />
    <!-- <div class="hospital">滨州医学院烟台附属医院</div> -->
    <div class="hospital">{{ systemSetting.web_name }}</div>
    <div class="right-menu">
      <el-dropdown class="avatar-container" trigger="click">
        <div class="avatar-wrapper">
          <img :src="require('../../assets/images/avatar07.png')" class="user-avatar" />
          <span class="username">{{ name }}</span>
          <i class="el-icon-caret-bottom" />
        </div>
        <el-dropdown-menu slot="dropdown" class="user-dropdown">
          <router-link to="/">
            <el-dropdown-item>首页</el-dropdown-item>
          </router-link>
          <el-dropdown-item divided @click.native="logout">
            <span style="display: block">退出登陆</span>
          </el-dropdown-item>
        </el-dropdown-menu>
      </el-dropdown>
    </div>
  </div>
</template>

<script>
import { getToken, setToken, removeToken } from '@/utils/auth';
import { mapGetters, mapState } from 'vuex';

import Breadcrumb from '@/components/Breadcrumb';
import Hamburger from '@/components/Hamburger';

export default {
  components: {
    Breadcrumb,
    Hamburger,
  },
  data() {
    return {}
  },
  computed: {
    ...mapState({
      systemSetting: (state) => state.app.systemSetting
    }),
    ...mapGetters(['sidebar', 'avatar']),
    name() {
      return localStorage.getItem('realname')
    }
  },
  created() {},
  methods: {
    toggleSideBar() {
      this.$store.dispatch('app/toggleSideBar');
    },
    async logout() {
      await this.$store.dispatch('user/logout')
      const preUrl = sessionStorage.getItem("preUrl")
      if (preUrl) {
        this.$router.push({ path: '/login', query: { preUrl }})
      } else {
        this.$router.push({ path: '/login' })
      }
    },
  },
};
</script>

<style lang="scss" scoped>
.hospital {
  line-height: 50px;
  color: #000;
  font-size: 14px;
  float: left;
}
.navbar {
  // width: 1200px;
  height: 50px;
  overflow: hidden;
  position: relative;
  background: #fff;
  box-shadow: 0 1px 4px rgba(0, 21, 41, 0.08);

  .hamburger-container {
    line-height: 46px;
    height: 100%;
    float: left;
    cursor: pointer;
    transition: background 0.3s;
    -webkit-tap-highlight-color: transparent;

    &:hover {
      background: rgba(0, 0, 0, 0.025);
    }
  }

  .breadcrumb-container {
    float: left;
  }

  .right-menu {
    float: right;
    height: 100%;
    line-height: 50px;

    &:focus {
      outline: none;
    }

    .right-menu-item {
      display: inline-block;
      padding: 0 8px;
      height: 100%;
      font-size: 18px;
      color: #5a5e66;
      vertical-align: text-bottom;

      &.hover-effect {
        cursor: pointer;
        transition: background 0.3s;

        &:hover {
          background: rgba(0, 0, 0, 0.025);
        }
      }
    }

    .avatar-container {
      margin-right: 30px;

      .avatar-wrapper {
        position: relative;
          cursor: pointer;

        .user-avatar {
          cursor: pointer;
          width: 40px;
          height: 40px;
          border-radius: 20px;
          vertical-align: middle;
          margin-right: 10px;
        }

        .username {
          vertical-align: middle;
          cursor: pointer;
        }

        .el-icon-caret-bottom {
          cursor: pointer;
          position: absolute;
          right: -20px;
          top: 50%;
          transform: translateY(-50%);
          font-size: 12px;
        }
      }
    }
  }
}
</style>
