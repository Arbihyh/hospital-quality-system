<template>
  <el-card class="box-card">
    <el-descriptions title="" :column="1">
      <el-descriptions-item label="序号">{{ index+1 }}</el-descriptions-item>
      <!-- <el-descriptions-item label="医院名称">滨州医学院烟台附属医院</el-descriptions-item> -->
      <el-descriptions-item label="主诉"><span v-html="item.zs"></span></el-descriptions-item>
      <el-descriptions-item label="现病史"><span v-html="item.xbs"></span></el-descriptions-item>
      <el-descriptions-item label="既往史"><span v-html="item.jws"></span></el-descriptions-item>
      <el-descriptions-item label="体格检查"><span v-html="item.tgjc"></span></el-descriptions-item>
      <el-descriptions-item label="初步诊断"><span v-html="item.cbzd"></span></el-descriptions-item>
      <el-descriptions-item label="处理意见"><span v-html="item.zlyj"></span></el-descriptions-item>
      <el-descriptions-item label="西药"><span v-html="item.xy"></span></el-descriptions-item>
      <el-descriptions-item label="提醒"><span v-html="item.tx"></span></el-descriptions-item>
      <!-- <el-descriptions-item label="滨州医学院烟台附属医院"></el-descriptions-item> -->
      <el-descriptions-item label="门诊病历"></el-descriptions-item>
      <el-descriptions-item label="就诊时间"><span v-html="item.jzsj"></span></el-descriptions-item>
      <el-descriptions-item label="姓名"><span v-html="item.xm"></span></el-descriptions-item>
      <el-descriptions-item label="科室"><span v-html="item.ks"></span></el-descriptions-item>
      <el-descriptions-item label="性别"><span v-html="item.xb"></span></el-descriptions-item>
      <el-descriptions-item label="年龄"><span v-html="item.nl"></span></el-descriptions-item>
      <el-descriptions-item label="身份证号"><span v-html="item.SFZH"></span></el-descriptions-item>
  </el-descriptions>
  </el-card>
</template>

<script>
export default {
  props: {
    item: {
      type: Object,
      default() {
        return {}
      }
    },
    index: {
      type: Number,
      default() {
        return 1
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.box-card {
  margin-bottom: 20px;
}
</style>