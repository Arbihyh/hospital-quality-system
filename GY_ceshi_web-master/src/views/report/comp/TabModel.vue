<template>
  <div class="tab-model">
    <el-tabs 
      v-model="activeTab" 
      tab-position="top" 
      style="background: #fff; padding: 10px; border-radius: 4px; margin-bottom: 20px;"
    >
      <el-tab-pane label="质控指标" name="indicators"></el-tab-pane>
      <el-tab-pane label="缺陷分布" name="defects"></el-tab-pane>
      <el-tab-pane label="科室统计" name="department"></el-tab-pane>
      <el-tab-pane label="医师统计" name="doctor"></el-tab-pane>
      <el-tab-pane label="指标对比" name="comparison"></el-tab-pane>
    </el-tabs>

    <Indicators v-if="activeTab === 'indicators'" />
    <Defects v-if="activeTab === 'defects'" />
    <Department v-if="activeTab === 'department'" />
    <Doctor v-if="activeTab === 'doctor'" />
    <Comparison v-if="activeTab === 'comparison'" />
  </div>
</template>

<script>
import Indicators from './Indicators.vue'
import Defects from './DefectDistribution.vue'
import Department from './DeptStatistics.vue'
import Doctor from './DoctorStatistics.vue'
import Comparison from './IndexCompare.vue'

export default {
  name: 'tabModel',
  components: {
    Indicators,
    Defects,
    Department,
    Doctor,
    Comparison
  },
  data() {
    return {
      activeTab: 'indicators'
    }
  }
}
</script>

<style scoped>
.tab-model {
  /* max-width: 1600px; */
  margin: 0 auto;
}
</style>