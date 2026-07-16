<template>
  <div>
    <div class="btn-box">
      <el-button
        type="primary"
        icon="el-icon-plus"
        @click="onCreate"
      >新增</el-button>
    </div>
    <el-table v-loading="loading" :data="data" border style="width: 100%">
      <el-table-column type="index" label="序号" width="100" align="center" />
      <el-table-column
        prop="type"
        label="任务类型"
        width="240"
        show-overflow-tooltip
      >
        <template slot-scope="scope">
          <span>{{
            scope.row.type == 1
              ? "病历质控"
              : scope.row.type == 2
                ? "病历生成"
                : scope.row.type == 3
                  ? "评审指标"
                  : ""
          }}</span>
        </template>
      </el-table-column>
      <el-table-column
        prop="title"
        label="任务名称"
        width="240"
        show-overflow-tooltip
      />
      <!-- <el-table-column
        prop="app_name"
        label="病历类目"
        width="240"
        show-overflow-tooltip
      /> -->
      <el-table-column prop="content" label="任务描述" show-overflow-tooltip />
      <el-table-column prop="" label="操作" width="180">
        <template slot-scope="scope">
          <el-link
            :underline="false"
            type="primary"
            @click="onEdit(scope.row)"
          >编辑</el-link>
          <el-divider direction="vertical" />
          <el-link
            :underline="false"
            type="danger"
            @click="onDel(scope.row)"
          >删除</el-link>
        </template>
      </el-table-column>
      <el-table-column prop="status" label="是否开启" width="180">
        <template slot-scope="scope">
          <el-switch
            :value="scope.row.status === '1'"
            active-color="#13ce66"
            @change="handleStatusChange(scope.row,$event)"
          />
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script>
import { deleteHelper, setStatus } from '@/api/helper'
export default {
  emits: ['onAdd', 'onEdit'],
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
      status: '1'
    }
  },
  methods: {
    onCreate() {
      this.$emit('onAdd')
      // localStorage.removeItem('helper')
      // this.$router.push({ path: '/helper/config' })
    },
    onEdit(row) {
      this.$emit('onEdit', row)
      // localStorage.setItem('helper', JSON.stringify(row))
      // this.$router.push({ path: '/helper/config' })
    },
    onDel(row) {
      this.$confirm('是否确认删除该数据?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        deleteHelper({ id: row.id }).then((res) => {
          this.$message.success(res.m || '操作成功')
          this.$emit('refresh')
        })
      })
    },

    handleStatusChange(row, newValue) {
      const params = {
        id: row.id,
        status: newValue ? '1' : '0'
      }
      setStatus(params).then((res) => {
        this.$message.success(res.m || '操作成功')
        this.handleRefresh()
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
