<template>
  <div class="box" :class="{'nocopy': $route.meta.nocopy}">
    <div class="box_wrapper">
      <el-tabs v-model="activeName" type="card" @tab-click="handleClick">
        <el-tab-pane label="指标分析" name="0">
          <CaseIndexAnalysis />
        </el-tab-pane>
        <el-tab-pane label="评审指标" name="1">
          <CaseIndex />
        </el-tab-pane>
      </el-tabs>
      <el-button class="layout-btn" @click="logout">
        退出
      </el-button>
    </div>
  </div>
</template>

<script>
import { setToken } from '@/utils/auth';
import CaseIndexAnalysis from '@/views/yyps/analysis/index.vue'
import CaseIndex from '@/views/yyps/index/index.vue'

export default {
  components: {
    CaseIndexAnalysis,
    CaseIndex
  },
  data() {
    return {
      activeName: '0'
    };
  },
  methods: {
    handleClick(tab, event) {
      this.activeName = tab.name
    },
    async logout() {
      await this.$store.dispatch('user/logout')
      const preUrl = sessionStorage.getItem("preUrl")
      this.$router.push({ path: '/login', query: { preUrl }})
    },
  },
};
</script>

<style lang="scss" scoped>
::v-deep .el-tabs__nav-scroll{
	width: 320px;
	margin:0 auto
}
.box {
  padding: 16px;
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
}
</style>