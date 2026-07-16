<template>
  <div class="app-container">
    <SearchBoxVue :data="searchData" :types="types" :categorys="categorys" @search="handleSearch" />
    <TableBoxVue :loading="loading" :types="types" :categorys="categorys" :data="tableData" style="margin-top: -40px;" @refresh="handleRefresh" />
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
import { caseRuleList, getCategory, getType } from '@/api/admin'

export default {
  components: {
    SearchBoxVue,
    TableBoxVue
  },
  data() {
    return {
      loading: false,
      searchData: {
        category: '',
        title: '',
        notice: '',
        status: '',
        score: '',
        is_ai: '',
        type: '',
        department: ''
      },
      tableData: [],
      paginationData: {
        total: 0,
        page: 1,
        limit: 10
      },
      types: [],
      categorys: []
    }
  },
  created() {
    this.getType()
    this.getCategory()
    this.getList()
  },
  methods: {
    handleRefresh() {
      this.getList()
    },
    getType() {
      getType().then(res => {
        const { p } = res
        this.types = p
      }).catch(error => {
        console.log(error)
      })
    },
    getCategory() {
      getCategory().then(res => {
        const { p } = res
        this.categorys = p
      }).catch(error => {
        console.log(error)
      })
    },
    async getList() {
      const { category, title, notice, status, score, type, department, is_ai } = this.searchData
      const { page, limit } = this.paginationData
      const params = {
        category,
        status,
        score,
        type,
        title,
        notice,
        is_ai,
        department,
        page,
        page_size: limit
      }
      this.loading = true
      caseRuleList(params).then(res => {
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
