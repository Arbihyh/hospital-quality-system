<template>
  <div style="padding: 20px;">
    <el-button type="primary" @click="toExamine">批量审核</el-button>
    <el-table v-loading="loading" :data="data" style="width: 100%" @selection-change="handleSelectionChange">
      <el-table-column type="selection" width="55"></el-table-column>
      <el-table-column type="index" label="序号" width="80" />
      <el-table-column prop="" label="审核状态" width="100" show-overflow-tooltip>
        <template slot-scope="scope">
          <el-button type="text" style="color: red" v-if="scope.row.to_examine_id == 0" @click="review(scope.row.ZYH)">未审核</el-button>
          <el-button type="text" v-else>已通过</el-button>
        </template>
      </el-table-column>
      <el-table-column prop="ZYH" label="住院号" />
      <el-table-column prop="AAC01" label="出院时间" />
      <el-table-column prop="apply_for_name" label="申请人" />
      <el-table-column prop="apply_for_time" label="申请时间" />
      <el-table-column prop="reason" label="申请原因" />
      <el-table-column prop="to_examine_name" label="审核人" />
      <el-table-column prop="to_examine_time" label="审核时间" />
    </el-table>
  </div>
</template>

<script>
import { getRevokeList, revokeUpdate } from '@/api/qc';

export default {
  props: {

  },
  data() {
    return {
      dialogVisible: false,
      loading: false,
      data: [],
    }
  },
  created() {
    this.getRevokeList();
  },
  methods: {
    getRevokeList() {
      this.loading = true;
      getRevokeList().then(res => {
        this.loading = false;
        this.data = res.data;
      }).catch(error => {
        console.log(error);
      })
    },

    /**
     * 选中
     * @param val
     */
    handleSelectionChange(val) {
      this.multipleSelection = val;
    },

    /**
     * 批量审核
     */
    toExamine() {
      this.$confirm('确认审核通过, 是否继续?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        var ZYH = this.multipleSelection.map(row => row['ZYH']);
        revokeUpdate({ ZYH: ZYH }).then(res => {
          this.getRevokeList();
        }).catch(error => {
          console.log(error);
        })
      }).catch(() => {
        this.$message({
          type: 'info',
          message: '已取消删除'
        });
      });
    },

    /**
     * 审核通过
     */
    review(val) {
      this.$confirm('确认审核通过, 是否继续?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        revokeUpdate({ ZYH: [val] }).then(res => {
          this.getRevokeList();
        }).catch(error => {
          console.log(error);
        })
      }).catch(() => {
        this.$message({
          type: 'info',
          message: '已取消删除'
        });
      });

    }
  },
};
</script>

<style scoped lang="scss"></style>
