<template>
  <div>
    <div class="btn-box">
      <el-button type="primary" icon="el-icon-plus" @click="onCreate">新增</el-button>
      <el-button type="primary" icon="el-icon-download" @click="onExport">导出</el-button>
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
        prop=""
        label="手术2.0"
        align="center"
      >
        <el-table-column
          prop="SSBM"
          label="手术2.0代码"
          align="center"
        />
        <el-table-column
          prop="SSMC"
          label="手术2.0名称"
          align="center"
        />
      </el-table-column>
      <el-table-column
        prop=""
        label="手术3.0"
        align="center"
      >
        <el-table-column
          prop="SSYSBM"
          label="手术3.0代码"
          align="center"
        />
        <el-table-column
          prop="SSYSMC"
          label="手术3.0名称"
          align="center"
        />
      </el-table-column>
      <el-table-column
        prop="SSNM"
        label="手术内码"
        align="center"
      />
      <el-table-column
        prop="SSLB"
        label="操作类型"
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
        </template>
      </el-table-column>
    </el-table>
    <!-- 新增、编辑 -->
    <CreateDialog v-if="createData.bSwitch" :data="createData" @refresh="handleRefresh" />
  </div>
</template>

<script>
import CreateDialog from './CreateDialog.vue'

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
    handleRefresh() {
      this.$emit('refresh')
    },
    onExport() {
      this.$emit('export')
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
