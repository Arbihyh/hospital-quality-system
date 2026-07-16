<template>
  <div>
    <SearchBoxVue :data="searchData" @search="handleSearch" />
    <TableBoxVue :loading="loading" :data="tableData" style="margin-top: -40px;" @download="handleDownLoad" />
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
import SearchBoxVue from './components/SearchBox.vue'
import TableBoxVue from './components/TableBox.vue'
import { userSearchLogExport } from '@/api/excel'
import { userSearchLog } from '@/api/admin'
import { dateFormat } from '@/filters/index'

export default {
  components: {
    SearchBoxVue,
    TableBoxVue
  },
  data() {
    return {
      loading: false,
      searchData: {
        name: '',
        dep_id: '',
        keyword: '',
        start_time: '',
        end_time: ''
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
    getList() {
      const { name, dep_id, keyword, start_time, end_time } = this.searchData
      const { page, limit } = this.paginationData
      const params = {
        name,
        dep_id,
        page,
        limit,
        keyword
      }
      params.start_time = start_time ? dateFormat(start_time, 'YYYYMMDD') : ''
      params.end_time = end_time ? dateFormat(end_time, 'YYYYMMDD') : ''
      this.loading = true
      userSearchLog(params).then(res => {
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
    handleDownLoad() {
      const { name, dep_id, time, keyword } = this.searchData
      const params = {
        name,
        dep_id,
        keyword
      }
      if (time && time.length) {
        params.start_time = time[0]
        params.end_time = time[1]
      }
      userSearchLogExport(params).then(res => {
        const content = res.data // 后台返回二进制数据
        const blob = new Blob([content])
        const fileName = `科研探索日志.csv`
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

</style>
