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
        prop="qingmiao_table_name"
        label="菁苗表名"
        align="center"
      />
      <el-table-column
        prop="qingmiao_field_name"
        label="菁苗字段名称"
        align="center"
      />
      <el-table-column
        prop="qingmiao_field"
        label="菁苗字段"
        align="center"
      />
      <el-table-column
        prop="hospital_name"
        label="医院名称"
        align="center"
      />
      <el-table-column
        prop="hospital_field"
        label="医院字段"
        align="center"
      />
      <el-table-column
        prop="hospital_one"
        label="医院数据源一级目录"
        align="center"
      />
      <el-table-column
        prop="hospital_two"
        label="医院数据源二级目录"
        align="center"
      />
      <el-table-column
        prop="hospital_three"
        label="医院数据源三级目录"
        align="center"
      />
      <el-table-column
        prop=""
        label="操作"
        align="center"
        fixed="right"
      >
        <template slot-scope="scope">
          <el-button type="text" @click="onEdit(scope.row)">修改</el-button>
          <el-button type="text" style="color: #F56C6C;" @click="onDel(scope.row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
    <!-- 新增、编辑 -->
    <CreateDialog v-if="createData.bSwitch" :data="createData" :options="options" @refresh="handleRefresh" />
  </div>
</template>

<script>
import CreateDialog from './CreateDialog.vue'
import { data_source_del } from '@/api/rule/data'

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
    },
    options: {
      type: Object,
      default() {
        return {
          table_name: [],
          EMR_YZB: {
            field_name: [],
            field: []
          },
          hospital_name: [],
          hospital_field: []
        }
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
    handleRefresh() {
      this.$emit('refresh')
    },
    // 删除
    onDel(row) {
      this.$confirm('是否确认删除该数据?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        data_source_del({ id: row.id }).then((res) => {
          this.$message.success(res.m || '操作成功')
          this.$emit('refresh')
        })
      })
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
