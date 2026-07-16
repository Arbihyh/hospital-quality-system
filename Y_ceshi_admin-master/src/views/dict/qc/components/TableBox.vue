<template>
  <div>
    <div class="btn-box">
      <el-button type="primary" icon="el-icon-plus" @click="onCreate">新增</el-button>
    </div>
    <el-table
      v-loading="loading"
      :data="data"
      border
      style="width: 100%"
    >
      <el-table-column
        type="index"
        label="序号"
        width="80"
        fixed="left"
        align="center"
      />
      <el-table-column
        prop="name"
        label="字典名称"
        align="center"
        width="200"
      />
      <el-table-column
        prop="keyword"
        label="映射名称"
        align="center"
      />
      <el-table-column
        prop=""
        label="操作"
        align="center"
        fixed="right"
        width="200"
      >
        <template slot-scope="scope">
          <el-button type="text" @click="onEdit(scope.row)">修改</el-button>
          <el-button type="text" style="color: #F56C6C;" @click="onDel(scope.row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
    <!-- 新增、编辑 -->
    <CreateDialog v-if="createData.bSwitch" :data="createData" @refresh="handleRefresh" />
  </div>
</template>

<script>
import CreateDialog from './CreateDialog.vue'
import { del_word_map } from '@/api/dict'

export default {
  components: {
    CreateDialog
  },
  props: {
    data: {
      type: Array,
      default() {
        return []
      }
    },
    loading: {
      type: Boolean,
      default() {
        return false
      }
    }
  },
  data() {
    return {
      createData: {
        bSwitch: false,
        row: {}
      }
    }
  },
  methods: {
    onCreate() {
      this.createData.row = {}
      this.createData.bSwitch = true
    },
    onEdit(row) {
      this.createData.row = row
      this.createData.bSwitch = true
    },
    onDel(row) {
      this.$confirm('是否确认删除该数据?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        del_word_map({ id: row.id }).then((res) => {
          this.$message.success(res.m || '操作成功')
          this.$emit('refresh')
        })
      })
    },
    handleRefresh() {
      this.$emit('refresh')
    }
  }
}
</script>

<style lang="scss" scoped>
.btn-box {
  text-align: right;
  margin-bottom: 15px;
}
</style>
