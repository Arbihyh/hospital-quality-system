<template>
  <div>
    <SearchBoxVue :data="searchData" @search="handleSearch" />
    <TableBoxVue :loading="loading" :data="tableData" style="margin-top: -40px;" />
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
import { feedbackList } from '@/api/admin'
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
        type_id: '',
        dep_id: '',
        user_name: '',
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
      const { type_id, dep_id, user_name, start_time, end_time } = this.searchData
      const { page, limit } = this.paginationData
      const params = {
        type_id,
        user_name,
        dep_id,
        page,
        page_size: limit
      }
      params.start_time = start_time ? dateFormat(start_time, 'YYYYMMDD') : ''
      params.end_time = end_time ? dateFormat(end_time, 'YYYYMMDD') : ''
      this.loading = true
      feedbackList(params).then(res => {
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
    }
  }
}
</script>

<style lang="scss" scoped>

</style>
