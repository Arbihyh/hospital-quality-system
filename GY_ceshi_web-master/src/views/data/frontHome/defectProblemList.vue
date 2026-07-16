<template>
  <div class="bg-box">
    <div class="bg-card">
      <SearchBoxVue :data="searchData" @search="handleSearch" @reset="handleReset" />
      <div style="margin-top: -22px;">
        <el-divider></el-divider>
        <TableBoxVue :data="tableData" :search="searchData" :paginationData="paginationData" @basChange="handleSearch" @export="handleExport" />
        <Pagination :page="paginationData.page" :limit="paginationData.page_size" :total="paginationData.total" @pagination="handlePagination" />
      </div>
    </div>
  </div>
</template>

<script>
import SearchBoxVue from './components/defectRuleProblemList/SearchBox.vue'
import TableBoxVue from './components/defectRuleProblemList/TableBox.vue'
import Pagination from '@/components/Pagination'
import { errorDetailsListExport } from '@/api/excel';

export default {
  components: {
    SearchBoxVue,
    TableBoxVue,
    Pagination
  },
  data() {
    return {
      searchData: {
        desc: '',
        AAA28: '',
        outTime: [],
        dep_id: '',
        AEE08: '',
        type: '',
        error_field: '',
        level: '',
        ICD9_ID1: '',
        ICD9_NAME: '',
        ICD10_ID1: '',
        ICD10_NAME: '',
        controlTime: [],
        is_bas: 1,
        is_edit: ''
      },
      tableData: [],
      paginationData: {
        page: 1,
        page_size: 10,
        total: 0
      }
    }
  },
  created() {
    const { start, end } = this.$route.query
    this.$set(this.searchData, 'outTime', [start, end])
    this.getList()
  },
  methods: {
    getList() {
      const {
        desc,
        AAA28,
        outTime,
        dep_id,
        AEE08,
        type,
        error_field,
        level,
        ICD9_ID1,
        ICD9_NAME,
        ICD10_ID1,
        ICD10_NAME,
        controlTime,
        is_bas,
        is_edit
      } = this.searchData
      const {
        page,
        page_size
      } = this.paginationData
      const params = {
        desc,
        AAA28,
        dep_id,
        AEE08,
        type,
        error_field,
        level,
        ICD9_ID1,
        ICD9_NAME,
        ICD10_ID1,
        ICD10_NAME,
        page,
        page_size,
        is_bas,
        is_edit
      }
      if (outTime && outTime.length) {
        params.start_time = outTime[0]
        params.end_time = outTime[1]
      }
      if (controlTime && controlTime.length) {
        params.zk_start_time = controlTime[0]
        params.zk_end_time = controlTime[1]
      }
      this.$axios.post('/home_quality/errorDetailsList', params).then(res => {
        this.tableData = res.data.list
        this.paginationData.total = res.data.count
      });
    },
    handleSearch() {
      this.paginationData.page = 1
      this.getList()
    },
    handleReset() {
      this.searchData = {
        desc: '',
        AAA28: '',
        outTime: [],
        dep_id: '',
        AEE08: '',
        type: '',
        error_field: '',
        level: '',
        ICD9_ID1: '',
        ICD9_NAME: '',
        ICD10_ID1: '',
        ICD10_NAME: '',
        controlTime: [],
        is_bas: 1,
        is_edit: ''
      }
      this.paginationData.page = 1
      this.getList()
    },
    handlePagination(val) {
      const { page, limit } = val
      this.paginationData.page = page
      this.paginationData.page_size = limit
      this.getList()
    },
    handleExport() {
      const {
        desc,
        AAA28,
        outTime,
        dep_id,
        AEE08,
        type,
        error_field,
        level,
        ICD9_ID1,
        ICD9_NAME,
        ICD10_ID1,
        ICD10_NAME,
        controlTime,
        is_bas,
        is_edit
      } = this.searchData
      const params = {
        desc,
        AAA28,
        dep_id,
        AEE08,
        type,
        error_field,
        level,
        ICD9_ID1,
        ICD9_NAME,
        ICD10_ID1,
        ICD10_NAME,
        is_bas,
        is_edit
      }
      if (outTime && outTime.length) {
        params.start_time = outTime[0]
        params.end_time = outTime[1]
      }
      if (controlTime && controlTime.length) {
        params.zk_start_time = controlTime[0]
        params.zk_end_time = controlTime[1]
      }
      errorDetailsListExport(params).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `缺陷问题列表.csv`;
        if ('download' in document.createElement('a')) {
          // 非IE下载
          const elink = document.createElement('a');
          elink.download = fileName;
          elink.style.display = 'none';
          elink.href = URL.createObjectURL(blob);
          document.body.appendChild(elink);
          elink.click();
          URL.revokeObjectURL(elink.href); // 释放URL 对象
          document.body.removeChild(elink);
        } else {
          // IE10+下载
          navigator.msSaveBlob(blob, fileName);
        }
      });
    }
  }
}
</script>

<style>

</style>