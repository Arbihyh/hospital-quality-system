<template>
  <div class="app-container">
    <SearchBoxVue :data="searchData" @search="handleSearch" />
    <TableBoxVue :loading="loading" :data="tableData" style="margin-top: -40px;" @refresh="handleRefresh" @export="handleExport" />
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
import { getSsczysList } from '@/api/dict'
import { ssczysExport } from '@/api/excel'

export default {
  components: {
    SearchBoxVue,
    TableBoxVue
  },
  data() {
    return {
      loading: false,
      searchData: {
        ssbm: '',
        ssmc: '',
        ssysbm: '',
        ssysmc: '',
        sslb: '',
        ssnm: ''
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
    handleRefresh() {
      this.getList()
    },
    async getList() {
      const { ssbm, ssmc, ssysbm, ssysmc, sslb, ssnm } = this.searchData
      const { page, limit } = this.paginationData
      const params = {
        ssbm,
        ssmc,
        ssysbm,
        ssysmc,
        sslb,
        ssnm,
        page,
        page_size: limit
      }
      this.loading = true
      getSsczysList(params).then(res => {
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
    handleExport() {
      ssczysExport(this.searchData).then((res) => {
        const content = res.data // 后台返回二进制数据
        const blob = new Blob([content])
        const fileName = `2.0转3.0.csv`
        if ('download' in document.createElement('a')) {
          // 非IE下载
          const elink = document.createElement('a')
          elink.download = fileName
          elink.style.display = 'none'
          elink.href = URL.createObjectURL(blob)
          document.body.appendChild(elink)
          elink.click()
          URL.revokeObjectURL(elink.href) // 释放URL 对象
          document.body.removeChild(elink)
        } else {
          // IE10+下载
          navigator.msSaveBlob(blob, fileName)
        }
      })
    }
  }
}
</script>

<style lang="scss" scoped>

</style>
