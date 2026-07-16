<template>
  <div class="app-container">
    <SearchBoxVue
      :data="searchData"
      @search="handleSearch"
      @reset="handleReset"
    />
    <TableBoxVue
      :loading="loading"
      :data="tableData"
      style="margin-top: -40px"
      @refresh="handleRefresh"
    />
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
import { get_dict_list } from '@/api/dict'

export default {
  components: {
    SearchBoxVue,
    TableBoxVue
  },
  data() {
    return {
      loading: false,
      searchData: {
        table: '',
        field: '',
        field_name: '',
        dict: ''
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
        table: '',
        field: '',
        field_name: '',
        dict: ''
      }
      this.paginationData.page = 1
      this.getList()
    },
    handleRefresh() {
      this.getList()
    },
    async getList() {
      const { table, field, field_name, dict } = this.searchData
      const { page, limit } = this.paginationData
      const params = {
        table,
        field,
        field_name,
        dict,
        page,
        page_size: limit
      }
      this.loading = true
      get_dict_list(params)
        .then((res) => {
          const { p } = res
          this.paginationData.total = p.count
          this.tableData = p.list
        })
        .catch((error) => {
          console.log(error)
        })
        .finally(() => {
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
