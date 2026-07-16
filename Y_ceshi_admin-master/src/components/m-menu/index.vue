<template>
  <div id="Mydiv">
    <el-row class="tac">
      <el-col :span="60">
        <el-menu class="el-menu-vertical-demo" :default-active="activeIndex" @select="handleOpen">
          <el-menu-item index="/homePage">
            <span slot="title">住院病案</span>
          </el-menu-item>
          <el-menu-item index="/dischargerecord">
            <span slot="title">出院记录</span>
          </el-menu-item>
          <el-menu-item index="/hospitalized">
            <span slot="title">入院记录</span>
          </el-menu-item>
          <el-menu-item index="/courseOfDisease">
            <span slot="title">病程记录</span>
          </el-menu-item>
          <el-menu-item index="/operation">
            <span slot="title">手术记录</span>
          </el-menu-item>
          <el-menu-item index="/groupConsultation">
            <span slot="title">会诊记录</span>
          </el-menu-item>
          <el-submenu index="3">
            <template slot="title">
              <span>报告单</span>
            </template>
            <el-menu-item index="/imgsText">病历图文报告单</el-menu-item>
            <el-menu-item index="/ultrasonic">超声诊断报告</el-menu-item>
            <el-menu-item index="/image">影像诊断报告单</el-menu-item>
            <el-menu-item index="/electrocardiogram">心电图诊断报告</el-menu-item>
            <el-menu-item index="/inspectionReport">检验报告单</el-menu-item>
          </el-submenu>
          <el-submenu index="2">
            <template slot="title">
              <span>医嘱单</span>
            </template>
            <el-menu-item index="/medicalAdvice">长期医嘱</el-menu-item>
            <el-menu-item index="/medicalTemporary">临时医嘱</el-menu-item>
          </el-submenu>
        </el-menu>
      </el-col>
    </el-row>
  </div>
</template>
<script>
// import store from '@/store';
export default {
  data() {
    return {
      activeIndex: ''
    }
  },
  mounted() {
    // this.valData = this.storageGet('getData');
    this.activeIndex = this.storageGet('activeIndex') || '/homePage'
    // 生命周期钩子：模板编译、挂载之后(此时不保证已在 document 中)
  },
  methods: {
    handleOpen(key) {
      console.log(key)
      this.storageSet('activeIndex', key)
      this.goreplace(key)
    }
  }
}
</script>

<style scoped>
    #Mydiv{
      padding: 0 0 0 2px;
    }
.el-menu-vertical-demo {
  width: 200px;
}
.el-submenu__title {
  /* padding-left: 20px !important; */
  padding: 0 0 0 20px;
}
</style>
