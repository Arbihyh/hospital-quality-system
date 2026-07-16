<template>
  <div class="table-box">
    <div class="btn-box">
      <el-button type="primary" icon="el-icon-upload" class="export-btn" @click="onExport">下载</el-button>
    </div>
    <el-table
      :data="data"
       @sort-change="handleSortChange"
      style="width: 100%">
      <el-table-column
        type="index"
        label="序号"
        width="50"
        align="center">
        <template slot-scope="scope">
          <span>{{ scope.$index + 1 + (paginationData.page - 1) * paginationData.page_size }}</span>
        </template>
      </el-table-column>
      <el-table-column
        prop=""
        label="病案号"
        show-overflow-tooltip
        align="center">
        <template slot-scope="scope">
          <span class="link2" @click="toPage(scope.row)">{{ scope.row.AAA28 }}</span>
        </template>
      </el-table-column>
      <el-table-column
        prop="AAC01"
        label="出院时间"
        sortable
        align="center">
      </el-table-column>
      <el-table-column
        prop="AAC11N"
        label="出院科室"
        show-overflow-tooltip
        align="center">
      </el-table-column>
      <el-table-column
        prop="AEE01"
        label="科主任"
        align="center">
      </el-table-column>
      <el-table-column
        prop="AEE02"
        label="主任(副主任)医师"
        align="center">
      </el-table-column>
      <el-table-column
        prop="AEE03"
        label="主治医师"
        align="center">
      </el-table-column>
      <el-table-column
        prop="AEE04"
        label="住院医师"
        align="center">
      </el-table-column>
      <!-- <el-table-column
        prop="ZZYISXM"
        label="医疗组长"
        align="center">
      </el-table-column> -->
      <el-table-column
        prop="home_bmy_score"
        label="病历评分"
        sortable
        align="center">
      </el-table-column>
      <el-table-column
        prop="level"
        label="病历等级"
        show-overflow-tooltip
        align="center">
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
      paginationData: {
        type: Object,
        default: {
          page: 1,
          page_size: 10
        }
      }
    },
    data() {
      return {
      }
    },
    methods: {
      toPage(row) {
        this.$router.push({ name: 'MedicalRecordNew', query: { zyh: row.ZYH }})
      },
      handleSortChange(column) {
        const { prop, order } = column
        let str = ''
        if (order === 'descending') {
          str = 'desc'
        } else if (order === 'ascending') {
          str = 'asc'
        } else {
          str = null
        }
        const val = str ? [prop, str] : []
        this.$emit('sort', val)
      },
      onExport() {
        this.$emit('export')
      }
    }
  }
</script>

<style lang="scss" scoped>
.table-box {
  margin-bottom: 20px;
  .btn-box {
    text-align: right;
    margin-bottom: 20px;
  }
}
</style>
<style lang="scss">
.table_code_popper {
  .el-checkbox {
    display: block;
    line-height: 26px;
  }
}
</style>
