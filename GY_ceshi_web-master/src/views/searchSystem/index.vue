<template>
  <div class="box">
    <div class="box_wrapper">
      <el-tabs v-model="activeName" type="card" @tab-click="handleClick">
        <el-tab-pane label="住院病历查询" name="0">
          <BLSearchVue v-if="activeName ==0" :is-whitelist="isWhiteList" />
        </el-tab-pane>
        <el-tab-pane label="病案首页查询" name="1">
          <CaseHomeSearchVue v-if="activeName ==1" :is-whitelist="isWhiteList" />
        </el-tab-pane>
        <el-tab-pane label="住院医嘱查询" name="2">
          <DoctorOrderSearchVue v-if="activeName ==2" :is-whitelist="isWhiteList" />
        </el-tab-pane>
        <el-tab-pane label="门诊病历查询" name="3">
          <OutpatientSearchVue v-if="activeName ==3" :is-whitelist="isWhiteList" />
        </el-tab-pane>
      </el-tabs>
      <el-button v-if="!isWhiteList" class="layout-btn" @click="logout">
        退出
      </el-button>
    </div>
  </div>
</template>

<script>
import BLSearchVue from '@/views/search';
import CaseHomeSearchVue from '@/views/data/query';
import DoctorOrderSearchVue from '@/views/data/query/adviceSearch.vue';
import OutpatientSearchVue from '@/views/outpatient/case/index.vue'
import { setToken } from '@/utils/auth';
export default {
  components: {
    BLSearchVue,
    CaseHomeSearchVue,
    DoctorOrderSearchVue,
    OutpatientSearchVue
  },
  data() {
    return {
      activeName: '0',
    };
  },
  computed: {
    isWhiteList() {
      // 判断是否不登录访问
      console.log("this.$route.path",this.$route.path)
      return this.$route.path.includes('whitelist')
    }
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
  },
};
</script>

<style lang="scss" scoped>
::v-deep .el-tabs__nav-scroll{
  width: 502px;
	margin:0 auto;
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
  }
}
</style>