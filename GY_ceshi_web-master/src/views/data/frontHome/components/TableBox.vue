<template>
  <div class="app-container">
    <div class="btn-box">
      <el-button type="primary" icon="el-icon-download" @click="onExport" class="export-btn">导出数据</el-button>
    </div>
    <el-table
      v-loading="loading"
      border
      :data="data"
      style="width: 100%">
      <el-table-column
        type="index"
        label="序号"
        align="center"
        width="120">
      </el-table-column>
      <el-table-column
        prop="field"
        label="缺陷字段"
        width="200">
      </el-table-column>
      <el-table-column
        prop="desc"
        label="缺陷描述">
      </el-table-column>
      <el-table-column
        prop=""
        label="缺陷数量"
        width="200">
        <template slot-scope="scope">
          <span class="link" @click="toPage(scope.row)">{{ scope.row.count }}</span>
        </template>
      </el-table-column>
      <el-table-column
        prop="level"
        label="缺陷分级"
        width="200">
      </el-table-column>
      <el-table-column
        prop="type"
        label="缺陷归类"
        width="200">
      </el-table-column>
    </el-table>
  </div>
</template>

<script>

export default {
  props: {
    data: {
      type: Array,
      default() {
        return []
      }
    },
    type_name:{  // 'lc' 临床
      type: String,
      default() {
        return ''
      }
    },
    loading: {
      type: Boolean,
      default() {
        return false
      }
    },
    hospital_name:{
      type: String,
      default() {
        return ''
      }
    },
    search: {
      type: Object,
      default() {
        return {}
      }
    }
  },
  methods: {
    // 模板导出
    onExport() {
      this.$emit('export')
    },
    toPage(row) {
      const { error_rule } = row
      this.$router.push({ 
        path: '/defectRuleProblem', 
        query: {
          type_name: this.type_name,
          error_rule,
          hospital_name: this.hospital_name,
          start_time: this.search.start_time,
          end_time: this.search.end_time
        }
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
.link {
  cursor: pointer;
  color: #409EFF;
}
</style>
