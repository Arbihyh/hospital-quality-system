<template>
  <div>
    <SearchBoxVue :data="searchData" @search="handleSearch" @reset="handleReset" />
    <div class="app-container">
      <el-table
        v-loading="loading"
        :data="tableData"
        style="width: 100%"
      >
        <el-table-column type="index" label="序号" width="80" />
        <el-table-column
          prop=""
          label="审核状态"
          width="80"
          show-overflow-tooltip>
          <template slot-scope="scope">
            <span v-if="scope.row.status == 0">待审核</span>
            <span v-if="scope.row.status == 1">通过</span>
            <span v-if="scope.row.status == 2">不通过</span>
          </template>
        </el-table-column>
        <el-table-column
          prop=""
          label="质控类型"
          width="80"
          show-overflow-tooltip>
          <template slot-scope="scope">
            <span v-if="scope.row.quality_type == 1">住院病历</span>
            <span v-if="scope.row.quality_type == 2">病案首页</span>
            <span v-if="scope.row.quality_type == 3">编码员</span>
          </template>
        </el-table-column>
        <el-table-column
          prop="defect_content"
          label="缺槬陷问题描述"
          show-overflow-tooltip
        />
        <el-table-column
          prop=""
          label="整改级别"
          width="80"
          show-overflow-tooltip
        >
        <template slot-scope="scope">
          <span>
            <el-tag style="max-width: 80px" :type="scope.row.levels === 1 ? 'danger' : ''">
              {{ scope.row.levels == 1?'必改':'建议' }}
            </el-tag>
          </span>
        </template>
      </el-table-column>
        <el-table-column
          prop=""
          label="住院号码"
          width="100"
          show-overflow-tooltip
        >
        <template slot-scope="scope">
          <el-button type="text" @click="toPage(scope.row)">{{ scope.row.AAA28 }}</el-button>
        </template>
      </el-table-column>

        <el-table-column
          prop="AAB01"
          label="入院时间"
          show-overflow-tooltip
        />
       
        <el-table-column
          prop="appeal_document"
          label="申诉科室"
          show-overflow-tooltip
        />
        <el-table-column
          prop="appeal_docter"
          label="申诉医师"
          show-overflow-tooltip
        />
        <el-table-column
          prop="appeal_time"
          label="申诉时间"
          show-overflow-tooltip
        />
        
        <el-table-column
          prop="case_docter"
          label="质控医师"
          show-overflow-tooltip
        />
        <el-table-column
          prop="examine_time"
          label="审核时间"
          show-overflow-tooltip
        />
      </el-table>
    </div>
    <pagination
      :auto-scroll="false"
      :total="paginationData.total"
      :page="paginationData.page"
      :limit="paginationData.limit"
      @pagination="handlePagination"
    />


  </div>
</template>

<script>
import SearchBoxVue from './components/SearchBox2.vue'
import { getCaseAppealList } from '@/api/admin'

export default {
  components: {
    SearchBoxVue,
  },
  data() {
    return {
      loading: false,
      searchData: {
        BLZT: '',
        level: '',
        ZKYS: '',
        BLDJ: '',
        AAA28: '',
        AAA01: '',
        AAC11N: '',
        AAB01_START: '',
        AAB01_END: '',
        AAC01_START: '',
        AAC01_END: ''
      },
      tableData: [],
      paginationData: {
        total: 0,
        page: 1,
        limit: 10
      }
    }
  },
  created() {
    this.getList()
  },
  methods: {
    toPage(row) {
      const { ZYH } = row
      localStorage.setItem('getData', ZYH)
      this.$router.push({ path: '/recordsRoom/qc/caseViews' })
    },
    getList() {
      const {
        AAB01_START,
        AAB01_END,
        levels,
        AAB01_start_time,
        AAB01_end_time,
        appeal_document,
        appeal_docter,
        examine_start_time,
        examine_end_time,
        case_docter,
        defect_content,
      } = this.searchData
      const { page, limit } = this.paginationData
      const params = {
        AAB01_START,
        AAB01_END,
        levels,
        AAB01_start_time,
        AAB01_end_time,
        appeal_document,
        appeal_docter,
        examine_start_time,
        examine_end_time,
        case_docter,
        defect_content,
        page,
        page_size: limit
      }
      params.AAB01_START = AAB01_START ? AAB01_START / 1000 : ''
      params.AAB01_END = AAB01_END ? AAB01_END / 1000 : ''
      params.AAB01_start_time = AAB01_start_time ? AAB01_start_time / 1000 : ''
      params.AAB01_end_time = AAB01_end_time ? AAB01_end_time / 1000 : ''
      params.examine_start_time = examine_start_time ? examine_start_time / 1000 : ''
      params.examine_end_time = examine_end_time ? examine_end_time / 1000 : ''
      this.loading = true
      getCaseAppealList(params).then(res => {
        const { p } = res
        this.paginationData.total = p.count
        this.tableData = p.list
      }).catch(error => {
        console.log(error)
      }).finally(() => {
        this.loading = false
      })
    },
    handlePagination(param) {
      this.paginationData.page = param.page
      this.paginationData.limit = param.limit
      this.getList()
    },
    handleSearch() {
      this.paginationData.page = 1
      this.getList()
    },
    handleReset() {
      this.searchData = {
        AAB01_START: '',
        AAB01_END: '',
        levels: '',
        AAB01_start_time: '',
        AAB01_end_time: '',
        appeal_document: '',
        appeal_docter: '',
        examine_start_time: '',
        examine_end_time: '',
        case_docter: '',
        defect_content: ''
      }
      this.handleSearch()
    }
  }
}
</script>

<style lang="scss" scoped>
// ==================

</style>
