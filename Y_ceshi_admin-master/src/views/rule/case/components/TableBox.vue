<template>
  <div>
    <div class="btn-box">
      <el-button type="primary" plain icon="el-icon-plus" style="float: right; margin-bottom: 15px;" @click="onCreate">新增</el-button>
    </div>
    <el-table
      v-loading="loading"
      :data="data"
      border
      style="width: 100%"
    >
      <el-table-column type="index" label="序号" width="80" fixed="left" align="center" />
      <el-table-column
        prop="department"
        label="科室"
        align="center"
        width="160"
      />
      <el-table-column
        prop="category"
        label="质控分类"
        align="center"
        width="100"
      />
      <el-table-column
        prop="title"
        label="质控项目"
        align="center"
      />
      <el-table-column
        prop="notice"
        label="错误描述"
        align="center"
        width="300"
        show-overflow-tooltip
      />
      <el-table-column
        prop="rule"
        label="质控规则"
        align="center"
        width="300"
        show-overflow-tooltip
      />
      <el-table-column
        prop="score"
        label="扣分"
        align="center"
        width="80"
      />
      <el-table-column
        prop="type"
        label="质控类型"
        align="center"
        width="120"
      />
      <el-table-column
        prop=""
        label="单项否决"
        align="center"
        width="80"
      >
        <template slot-scope="scope">
          <span type="one_no">{{ scope.row.one_no?'是':'否' }}</span>
        </template>
      </el-table-column>
      <el-table-column
        prop="node"
        label="质控节点"
        align="center"
        width="120"
        show-overflow-tooltip
      />
      <el-table-column
        prop="status"
        label="状态"
        align="center"
        width="80"
      >
        <template slot-scope="scope">

          <span v-if="scope.row.status === 3" class="red">已删除</span>
          <el-switch
            v-else
            v-model="scope.row.status"
            active-color="#13ce66"
            :active-value="1"
            :inactive-value="0"
            @change="handleStatusChange(scope.row)"
          />
        </template>
      </el-table-column>
      <el-table-column
        prop="is_shizhong"
        label="是否同步到事中质控规则"
        width="200"
        align="center"
      >
        <template slot-scope="scope">
          <el-switch
            v-model="scope.row.is_shizhong"
            active-color="#13ce66"
            :active-value="1"
            :inactive-value="0"
            @change="handleIsShizhongChange(scope.row)"
          />
        </template>
      </el-table-column>
      <el-table-column
        prop=""
        label="操作"
        align="center"
        width="120"
        fixed="right"
      >
        <template slot-scope="scope">
          <el-button type="text" @click="onEdit(scope.row)">修改</el-button>
          <el-button type="text" style="color: #F56C6C;" @click="onDel(scope.row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
    <!-- 新增、编辑 -->
    <CreateDialog v-if="createData.bSwitch" :data="createData" :types="types" :categorys="categorys" @refresh="handleRefresh" />
  </div>
</template>

<script>
import CreateDialog from './CreateDialog.vue'
import { createCaseRuleList, setCaseRuleshizhong } from '@/api/admin'

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
    categorys: {
      type: Array,
      default() {
        return []
      }
    },
    types: {
      type: Array,
      default() {
        return []
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
        row.status = 3
        createCaseRuleList(row).then(res => {
          this.data.bSwitch = false
          this.$emit('refresh')
          this.$message.success(res.m || '操作成功')
        }).catch(function() {
          // row.status = row.status === 0 ? 1 : 0
        })
      }).catch(function() {
        // row.status = row.status === 0 ? 1 : 0
      })
    },
    handleStatusChange(row) {
      const index = row.status ? 1 : 0
      this.$confirm('确认要更改为 <strong>' + ['停用', '正常'][index] + '</strong> 状态吗？', '提示', {
        dangerouslyUseHTMLString: true,
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        createCaseRuleList(row).then(res => {
          this.data.bSwitch = false
          this.$emit('refresh')
          this.$message.success(res.m || '操作成功')
        }).catch(function() {
          row.status = row.status === 0 ? 1 : 0
        })
      }).catch(function() {
        row.status = row.status === 0 ? 1 : 0
      })
    },
    handleIsShizhongChange(row) {
      const index = row.is_shizhong ? 1 : 0
      this.$confirm('确认要更改为 <strong>' + ['不同步', '同步'][index] + '</strong> 状态吗？', '提示', {
        dangerouslyUseHTMLString: true,
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        const params = {
          id: row.id,
          status: row.is_shizhong
        }
        setCaseRuleshizhong(params).then(res => {
          this.data.bSwitch = false
          this.$emit('refresh')
          this.$message.success(res.m || '操作成功')
        }).catch(function() {
          row.is_shizhong = row.is_shizhong ? 0 : 1
        })
      }).catch(function() {
        row.is_shizhong = row.is_shizhong ? 0 : 1
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
  overflow: hidden;
}
</style>
