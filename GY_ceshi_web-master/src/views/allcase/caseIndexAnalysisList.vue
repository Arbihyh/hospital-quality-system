<template>
  <div class="box" :class="{'nocopy': $route.meta.nocopy}">
    <div class="box_wrapper">
      <!-- 搜索 -->
      <CaseIndexAnalysisListSearch :data="searchData" @search="onSearch" />
      <!-- 列表 -->
      <CaseIndexAnalysisListTable :list="tableData" @export="handleExport" />
      <!-- 分页 -->
      <el-pagination
          v-if="tableData && tableData.length !== 0"
          @size-change="SizeChangeEvent"
          @current-change="pageHasChanged"
          :total="paginationData.total"
          background
          class="table-pagination"
          style="margin: 30px 0px; float: right;"
          :page-size="paginationData.pageSize"
          :current-page.sync="paginationData.currentPage"
          layout="total, sizes, prev, pager, next, jumper"
        ></el-pagination>
    </div>
  </div>
</template>

<script>
  import CaseIndexAnalysisListSearch from './components/CaseIndexAnalysisListSearch.vue'
  import CaseIndexAnalysisListTable from './components/CaseIndexAnalysisListTable.vue'
  import { depBlExport } from '@/api/excel'
  import { dateFormat } from '@/utils'

  export default {
    components : {
      CaseIndexAnalysisListSearch,
      CaseIndexAnalysisListTable
    },
    data() {
      return {
        searchData: {
          dep_name: '',
          status: '',
          start_time: '',
          end_time: ''
        },
        tableData: [],
        paginationData: {
          total: 0,
          currentPage: 1,
          pageSize: 10,
        },
      }
    },
    methods: {
      // 分页
      SizeChangeEvent(val) {
        this.paginationData.pageSize = val;
        this.getList();
      },
      pageHasChanged() {
        this.getList();
      },
      // 搜索
      onSearch() {
        this.paginationData.currentPage = 1;
        this.getList();
      },
      getList() {
        const { ruleId } = this.$route.query
        const { dep_name, status, start_time, end_time } = this.searchData
        const { currentPage, pageSize } = this.paginationData
        const params = {
          type: ruleId,
          page: currentPage,
          page_size: pageSize,
          dep_name,
          status: parseInt(status),
          start_time: start_time ? dateFormat(start_time, 'YYYYMMDD') : '',
          end_time: end_time ? dateFormat(end_time, 'YYYYMMDD') : ''
        }
        this.$axios2.post('/dep_bl_list', params).then(res => {
          if (Array.isArray(res.data.data)) {
            this.tableData = res.data.data;
            this.paginationData.total = res.data.count
          } else {
            this.tableData = [];
          }
        });
      },
      // 导出
      handleExport() {
        const { dep_name, time, status } = this.searchData
        const { ruleId } = this.$route.query
        const params = {
          dep_name,
          type: ruleId,
          status
        }
        
        if (time && time.length) {
          params.start_time = time[0]
          params.end_time = time[1]
        }
        depBlExport(params).then(res => {
          const content = res.data // 后台返回二进制数据
          const blob = new Blob([content])
          const fileName = `科室病案.csv`
          if ('download' in document.createElement('a')) { // 非IE下载
            const elink = document.createElement('a')
            elink.download = fileName
            elink.style.display = 'none'
            elink.href = URL.createObjectURL(blob)
            document.body.appendChild(elink)
            elink.click()
            URL.revokeObjectURL(elink.href) // 释放URL 对象
            document.body.removeChild(elink)
          } else { // IE10+下载
            navigator.msSaveBlob(blob, fileName)
          }
        })
      }
    }
  }
</script>

<style lang="scss" scoped>
.box {
  padding: 0 16px 16px 16px;
  .box_wrapper {
    padding: 16px;
    background: #fff;
    border-radius: 5px;
    overflow: hidden;
  }
}
</style>