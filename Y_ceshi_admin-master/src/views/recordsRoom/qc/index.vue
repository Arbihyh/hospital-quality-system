<template>
  <div>
    <SearchBoxVue :data="searchData" @search="handleSearch" @reset="handleReset" />
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
import { getBlZkList } from '@/api/qc'

export default {
  components: {
    SearchBoxVue,
    TableBoxVue
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
    getList() {
      const {
        BLZT,
        level,
        ZKYS,
        BLDJ,
        AAA28,
        AAA01,
        AAC11N,
        AAB01_START,
        AAB01_END,
        AAC01_START,
        AAC01_END
      } = this.searchData
      const { page, limit } = this.paginationData
      const params = {
        BLZT,
        level,
        ZKYS,
        BLDJ,
        AAA28,
        AAA01,
        AAC11N,
        AAB01_START,
        AAB01_END,
        AAC01_START,
        AAC01_END,
        page,
        page_size: limit
      }
      params.AAB01_START = AAB01_START ? AAB01_START / 1000 : ''
      params.AAB01_END = AAB01_END ? AAB01_END / 1000 : ''
      params.AAC01_START = AAC01_START ? AAC01_START / 1000 : ''
      params.AAC01_END = AAC01_END ? AAC01_END / 1000 : ''
      this.loading = true
      getBlZkList(params).then(res => {
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
      }
      this.handleSearch()
    }
  }
}
</script>

<style lang="scss" scoped>

</style>
