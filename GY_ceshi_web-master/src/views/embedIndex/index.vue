<template>
  <div class="box" :class="{'nocopy': $route.meta.nocopy}">
    <div class="box_wrapper">
      <el-button class="feedback-btn" type="primary" plain @click="onFeedback">
        问题反馈
      </el-button>
      <el-tabs v-model="activeName" type="card" @tab-click="handleClick">
        <el-tab-pane label="指标分析" name="0">
          <CaseIndexAnalysis />
        </el-tab-pane>
        <el-tab-pane label="病案指标" name="1">
          <CaseIndex />
        </el-tab-pane>
      </el-tabs>
      <el-button class="layout-btn" @click="logout">
        退出
      </el-button>
    </div>
    <FeedbackDialogVue v-if="feedbackData.bSwitch" :data="feedbackData" />
  </div>
</template>

<script>
import { setToken } from '@/utils/auth';
import CaseIndexAnalysis from '@/views/allcase/caseIndexAnalysis.vue'
import CaseIndex from '@/views/allcase/caseIndex.vue'
import FeedbackDialogVue from './components/FeedbackDialog.vue';

export default {
  components: {
    CaseIndexAnalysis,
    CaseIndex,
    FeedbackDialogVue
  },
  data() {
    return {
      activeName: '0',
      feedbackData: {
        bSwitch: false
      }
    };
  },
  methods: {
    handleClick(tab, event) {
      console.log(tab.name);
      this.activeName = tab.name
    },
    async logout() {
      await this.$store.dispatch('user/logout')
      const preUrl = sessionStorage.getItem("preUrl")
      this.$router.push({ path: '/login', query: { preUrl }})
    },
    onFeedback() {
      this.feedbackData.bSwitch = true
    }
  },
};
</script>

<style lang="scss" scoped>
::v-deep .el-tabs__nav-scroll{
	width: 320px;
	margin:0 auto
}
.box {
  padding: 0 16px 16px 16px;
  .box_wrapper {
    padding: 16px;
    padding: 16px;
    background: #fff;
    border-radius: 5px;
    position: relative;
  }
  .layout-btn {
    position: absolute;
    --size: 16px;
    top: var(--size);
    right: var(--size);
    z-index: 99;
  }
  .feedback-btn {
    position: absolute;
    --size: 16px;
    top: var(--size);
    left: var(--size);
    z-index: 99;
  }
}
</style>