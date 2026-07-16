<template>
  <div class="app-container">
    <SearchBoxVue :data="searchData" :options="options" @search="handleSearch" @reset="handleReset" />
    <TableBoxVue :loading="loading" :data="tableData" :options="options" style="margin-top: -40px;" @refresh="handleRefresh" @export="handleExport" />
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
import { data_source_list, options_list } from '@/api/rule/data'

export default {
  components: {
    SearchBoxVue,
    TableBoxVue
  },
  data() {
    return {
      loading: false,
      searchData: {
        table_name: '',
        qingmiao_field_name: '',
        qingmiao_field: '',
        hospital_name: '',
        hospital_field: ''
      },
      tableData: [],
      paginationData: {
        total: 0,
        page: 1,
        limit: 10
      },
      options: {
        table_name: [],
        EMR_YZB: {
          field_name: [],
          field: []
        },
        hospital_name: [],
        hospital_field: []
      }
    }
  },
  created() {
    this.getOptionsData()
    this.getList()
  },
  methods: {
    // 质控项目
    getOptionsData() {
      options_list().then(res => {
        const { p } = res
        const { table_name, EMR_YZB, hospital_name, hospital_field } = p
        this.options.table_name = Array.isArray(table_name) ? table_name : []
        this.options.EMR_YZB = EMR_YZB
        this.options.hospital_name = Array.isArray(hospital_name) ? hospital_name : []
        this.options.hospital_field = Array.isArray(hospital_field) ? hospital_field : []
      })
    },
    handleRefresh() {
      this.getOptionsData()
      this.getList()
    },
    handleReset() {
      this.searchData = {
        table_name: '',
        qingmiao_field_name: '',
        qingmiao_field: '',
        hospital_name: '',
        hospital_field: ''
      }
    },
    async getList() {
      const {
        table_name,
        qingmiao_field_name,
        qingmiao_field,
        hospital_name,
        hospital_field
      } = this.searchData
      const { page, limit } = this.paginationData
      const params = {
        table_name,
        qingmiao_field_name,
        qingmiao_field,
        hospital_name,
        hospital_field,
        page,
        page_size: limit
      }
      this.loading = true
      data_source_list(params).then(res => {
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
