
<!-- 床号、住院号、姓名、在院状态、质控日期、是否查看
001  0021456、张三、在院（/出院）、2025.01.01 01:00:00、已查看/未查看
接口增加字段：
viewed_status：是否查看
ZYZT：在院状态 -->
<template>
  <div ref="box" class="box">
        <el-table :data="tableData" empty-text="暂无数据" style="width: 100%" height="100%" border>
          <el-table-column type="index" label="序号" width="50px"></el-table-column>
          <el-table-column prop="CH" label="床号"  width="50px"></el-table-column>
          <el-table-column prop="ZYH" label="住院号" width="100px"></el-table-column>
          <el-table-column prop="BRXM" label="姓名" width="120px" show-overflow-tooltip></el-table-column>
          <el-table-column prop="ZYZT" label="在院状态" width="150px"></el-table-column>
          <el-table-column prop="quality_date" label="质控日期" width="150px"></el-table-column>
          <el-table-column prop="viewed_status" label="是否查看" width="80px"></el-table-column>
          <!-- <el-table-column prop="viewed_status" label="是否查看" width="80px">
            <template slot-scope="scope">
              <span v-if="scope.row.viewed_status === 1" style="color:green">已查看</span>
              <span v-else style="color:red">未查看</span>
            </template>
          </el-table-column> -->
          
        </el-table>
  </div>
</template>
<script>
export default {
  data() {
    return {
      tableData: [],
    };
  },

  mounted() {
    this.qualityUnreadResults();
  },

  methods: {
    qualityUnreadResults() {
      this.$axios2.get(`/get_case_quality_count?KSDM=${this.$route.query.id}`, {}).then(res => {
        this.tableData = Array.isArray(res.data) ? res.data : [];
      });
    }

    // qualityUnreadResults() {
    //   this.$axios2
    //     .post('/quality_has_result', {
    //       YGGH: 'D123', // 医师工号
    //       YGXM: '张三', // 医师姓名
    //       KSDM: '1021', // 医师所属科室代码
    //       KSMC: '儿科', // 医师所属科室名称
    //     })
    //     .then(res => {
    //       this.tableData = Array.isArray(res.data) ? res.data : [];
    //     });
    // },
  },
};
</script>
<style lang="scss" scoped>
  .app-main{
    flex: 1;
    display: flex;
    flex-direction: column;
    width: 100%;
    // height: 100% !important;
  }
</style>