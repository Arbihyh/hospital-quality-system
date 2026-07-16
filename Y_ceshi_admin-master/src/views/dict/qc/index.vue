<template>
  <div class="app-container">
    <SearchBoxVue :data="searchData" @search="handleSearch" @reset="handleReset" />
    <TableBoxVue :loading="loading" :data="tableData" style="margin-top: -40px;" @refresh="handleRefresh" />
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
import { get_word_map } from '@/api/dict'

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
        keyword: ''
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
    handleReset() {
      this.searchData = {
        name: '',
        keyword: ''
      }
    },
    handleRefresh() {
      this.getList()
    },
    async getList() {
      const {
        name,
        keyword
      } = this.searchData
      const { page, limit } = this.paginationData
      const params = {
        keyword,
        name,
        page,
        page_size: limit
      }
      this.loading = true
      get_word_map(params).then(res => {
        const { p } = res
        this.paginationData.total = p.count
        this.tableData = Array.isArray(p.list) ? p.list : []
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
    }
  }
}
</script>

<style lang="scss" scoped>

</style>
