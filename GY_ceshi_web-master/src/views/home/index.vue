<template>
  <div class="page-container">
    <!-- 顶部导航栏 -->
    <div class="page-header">
      <el-image class="header-logo" :src="logoImg" fit="contain" />
      <div class="header-action-group">
        <el-image class="header-icon" :src="fullscreenImg" @click="handleToggleMax" fit="contain" />
        <el-badge :value="badgeValue" class="item">
          <el-image class="header-icon" :src="remindImg" @click="remindClick" fit="contain" />
        </el-badge>
        <div class="split-line"></div>
        <div class="right-menu">
          <el-dropdown class="avatar-container" trigger="click">
            <div class="avatar-wrapper">
              <span class="username">{{ loginName }}</span>
              <i class="el-icon-caret-bottom" />
            </div>
            <el-dropdown-menu slot="dropdown" style="margin-left: 40px">
              <el-dropdown-item>
                <span @click="personalCenter">个人中心</span>
              </el-dropdown-item>
              <el-dropdown-item>
                <span @click="logout">退出登陆</span>
              </el-dropdown-item>
            </el-dropdown-menu>
          </el-dropdown>
        </div>
      </div>
    </div>
    <FullscreenContainer :showBtn="false" :showEscTip="true" ref="customMaxContainerRef">
      <div class="content">
        <div class="page-title">
          <div class="title-top-box">
            <el-image class="title-top-img" :src="titleRectImg" fit="contain" />
            <div class="title-text">数据治理与服务平台</div>

            <div class="border-top"></div>
            <div class="border-left"></div>
            <div class="border-right"></div>
            <div class="border-bottom-left"></div>
            <div class="border-bottom-right"></div>
          </div>
          <el-image class="title-bottom-img" :src="titleBottomImg" fit="contain" />
        </div>

        <div class="page-card-list">
          <el-row :gutter="70">
            <el-col :span="6"><NavCustomCard :imgSrc="navItem1Bbg" cardText="院内搜索引擎" @cardClick="cardClick(1)" /></el-col>
            <el-col :span="6"><NavCustomCard :imgSrc="navItem2Bbg" cardText="病案首页质控" @cardClick="cardClick(2)" /></el-col>
            <el-col :span="6"><NavCustomCard :imgSrc="navItem3Bbg" cardText="住院病历质控" @cardClick="cardClick(3)" /></el-col>
            <el-col :span="6"><NavCustomCard :imgSrc="navItem4Bbg" cardText="门诊病历质控" @cardClick="cardClick(4)" /></el-col>
          </el-row>

          <el-row :gutter="70" style="margin-bottom: 60px">
            <el-col :span="6"><NavCustomCard :imgSrc="navItem5Bbg" cardText="评审评价指标" @cardClick="cardClick(5)" /></el-col>
            <el-col :span="6"><NavCustomCard :imgSrc="navItem6Bbg" cardText="权限配置管理" @cardClick="cardClick(6)" /></el-col>
            <el-col :span="6"><NavCustomCard :imgSrc="navItem7Bbg" cardText="人工精准管控" @cardClick="cardClick(7)" /></el-col>
            <el-col :span="6"><NavCustomCard :imgSrc="navItem8Bbg" cardText="接口协同服务" @cardClick="cardClick(8)" /></el-col>
          </el-row>
        </div>
      </div>
    </FullscreenContainer>
  </div>
</template>

<script>
import logoImg from '@/assets/images/nav-logo.png';
import fullscreenImg from '@/assets/images/nav-fullscreen.png';
import remindImg from '@/assets/images/nav-remind.png';
import titleRectImg from '@/assets/images/nav-title-rect.png';
import titleBottomImg from '@/assets/images/nav-title-bottom.png';

import navItem1Bbg from '@/assets/images/nav-item1-bg.png';
import navItem2Bbg from '@/assets/images/nav-item2-bg.png';
import navItem3Bbg from '@/assets/images/nav-item3-bg.png';
import navItem4Bbg from '@/assets/images/nav-item4-bg.png';
import navItem5Bbg from '@/assets/images/nav-item5-bg.png';
import navItem6Bbg from '@/assets/images/nav-item6-bg.png';
import navItem7Bbg from '@/assets/images/nav-item7-bg.png';
import navItem8Bbg from '@/assets/images/nav-item8-bg.png';

import NavCustomCard from './comp/NavCustomCard.vue';
import FullscreenContainer from '@/components/fullscreen-container';

import { mapGetters, mapState } from 'vuex';
export default {
  name: 'PageLayout',
  components: { NavCustomCard, FullscreenContainer },
  data() {
    return {
      logoImg,
      fullscreenImg,
      remindImg,
      titleRectImg,
      titleBottomImg,
      navItem1Bbg,
      navItem2Bbg,
      navItem3Bbg,
      navItem4Bbg,
      navItem5Bbg,
      navItem6Bbg,
      navItem7Bbg,
      navItem8Bbg,

      isMaximized: false,
      badgeValue: 0,
      badgeTimer: null,
    };
  },
  computed: {
    ...mapState({
      systemSetting: state => state.app.systemSetting,
    }),
    ...mapGetters(['sidebar', 'avatar']),
    loginName() {
      return localStorage.getItem('realname');
    },
  },
  beforeDestroy() {
    if (this.badgeTimer) {
      clearInterval(this.badgeTimer);
    }
  },
  mounted() {
    this.fetchRemindCount();
  },
  methods: {
    cardClick(type) {
      console.log(type);
      switch (type) {
        case 1:
          this.$router.push('/data/query');
          break;
        case 2:
          this.$router.push('/data/after');
          break;
        case 3:
          this.$router.push('/forewarning');
          break;
        case 4:
          this.$router.push('/outpatientControl');
          break;
        case 5:
          this.$router.push('/otherStatisticsData');
          break;
        case 6:
          this.$router.push('/user/user');
          break;
        case 7:
          this.$router.push('/recordsRoom/qc/expertQualityControl');
          break;
        case 8:
          this.$router.push('/recordsImport');
          break;
      }
    },

    animateBadgeValue(targetValue) {
      this.badgeValue = 0;
      if (this.badgeTimer) clearInterval(this.badgeTimer);
      const step = Math.ceil(targetValue / 10);
      this.badgeTimer = setInterval(() => {
        if (this.badgeValue >= targetValue) {
          this.badgeValue = targetValue;
          clearInterval(this.badgeTimer);
          this.badgeTimer = null;
        } else {
          this.badgeValue += step;
          if (this.badgeValue > targetValue) {
            this.badgeValue = targetValue;
          }
        }
      }, 100); 
    },

    async fetchRemindCount() {
      try {
        // const res = await this.$axios.get('/api/remind/count');
        // const targetCount = res.data.count;
        const targetCount = 99;
        this.animateBadgeValue(targetCount);
      } catch (error) {
        console.error('获取消息数量失败：', error);
        this.badgeValue = 0;
      }
    },
    remindClick() {
      console.log('点击消息');
      // this.$router.push({ path: '/bbjl' });
    },
    personalCenter() {
      console.log('个人中心');
      this.$router.push({ path: '/user/user' });
    },

    async logout() {
      await this.$store.dispatch('user/logout');
      const preUrl = sessionStorage.getItem('preUrl');
      if (preUrl) {
        this.$router.push({ path: '/login', query: { preUrl } });
      } else {
        this.$router.push({ path: '/login' });
      }
    },
    handleToggleMax() {
      this.isMaximized = true;
      this.$refs.customMaxContainerRef.openMax();
    },
  },
};
</script>

<style lang="scss" scoped>
$header-height: 64px;
$header-bg: #ffffff;
$container-bg: #f0f2f5;
$border-color: #efefef;
$title-text-color: #000;
$border-main-color: rgba(187, 187, 187, 1);
$split-line-color: #aaaaaa;

.page-container {
  width: 100vw;
  height: 100vh;
  background-color: $container-bg;
  box-sizing: border-box;
  overflow: hidden;
}

.page-header {
  width: 100%;
  height: $header-height;
  background-color: $header-bg;
  border: 1px solid $border-color;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 20px;
  box-sizing: border-box;

  .header-logo {
    height: 70%;
    width: auto;
  }

  .header-action-group {
    display: flex;
    gap: 24px;
    align-items: center;

    .split-line {
      width: 1px;
      height: 25px;
      background-color: $split-line-color;
      flex-shrink: 0;
      border: 1px solid rgba(170, 170, 170, 1);
    }

    .right-menu {
      height: 100%;
      display: flex;
      align-items: center;

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
          display: flex;
          align-items: center;
          .username {
            cursor: pointer;
            margin-right: 6px;
          }
          .el-icon-caret-bottom {
            cursor: pointer;
            font-size: 12px;
            color: #999;
          }
        }
      }
    }
  }

  .header-icon {
    width: 24px;
    height: 24px;
    cursor: pointer;
  }
}
.content {
  background-color: $container-bg;
}

.page-title {
  margin-top: 37px;
  min-height: 94px;
  color: #1d2129;
  font-size: 36px;
  font-family: 'PingFang SC', 'Microsoft YaHei', bold;
  text-align: center;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  // background-color: #F0F2F5;

  .title-top-box {
    position: relative;
    display: inline-block;
  }

  .title-top-img,
  .title-bottom-img {
    width: auto;
    height: auto;
    object-fit: contain;
    img {
      width: 100%;
      height: 100%;
      object-fit: contain !important;
    }
  }
  .title-bottom-img {
    margin-top: -5px;
  }

  .title-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 22px;
    font-weight: bold;
    font-family: 'PingFang SC', 'Microsoft YaHei', sans-serif;
    color: $title-text-color;
    white-space: nowrap;
    letter-spacing: 2px;
    z-index: 2;
  }

  .border-top {
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 400px;
    height: 1px;
    background-color: rgba(255, 255, 255, 1);
    border: 3px solid $border-main-color;
    z-index: 1;
  }
  .border-left {
    position: absolute;
    top: 0;
    left: 0;
    width: 1px;
    height: 95px;
    background-color: rgba(255, 255, 255, 1);
    border: 3px solid $border-main-color;
    z-index: 1;
  }
  .border-right {
    position: absolute;
    top: 0;
    right: 0;
    width: 1px;
    height: 95px;
    background-color: rgba(255, 255, 255, 1);
    border: 3px solid $border-main-color;
    z-index: 1;
  }
  .border-bottom-left {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 79px;
    height: 1px;
    background-color: rgba(255, 255, 255, 1);
    border: 3px solid $border-main-color;
    z-index: 1;
  }
  .border-bottom-right {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 79px;
    height: 1px;
    background-color: rgba(255, 255, 255, 1);
    border: 3px solid $border-main-color;
    z-index: 1;
  }
}

.page-card-list {
  width: 100%;
  margin-top: 50px;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  overflow-y: scroll;
  flex-wrap: wrap;
  padding: 0 10px;

  .el-row {
    margin-bottom: 80px;
    &:last-child {
      margin-bottom: 0;
    }
  }
}
</style>