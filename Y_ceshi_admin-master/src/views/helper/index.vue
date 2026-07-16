<template>
  <div class="app-container">
    <SearchBoxVue
      ref="SearchBoxRef"
      @onSearch="handleSearch"
      @onReset="handleReset"
    />
    <TableBoxVue
      :loading="loading"
      :data="tableData"
      style="margin-top: -40px"
      @refresh="handleRefresh"
      @onAdd="handleAdd"
      @onEdit="handleEdit"
    />
    <pagination
      :auto-scroll="false"
      :total="paginationData.total"
      :page="paginationData.page"
      :limit="paginationData.limit"
      @pagination="handlePagination"
    />
    <AddModalVue ref="AddModalVueRef" @onSuccess="handleRefresh" />
  </div>
</template>

<script>
import { SearchBoxVue, TableBoxVue, AddModalVue } from './components/index'
import { helperPage } from '@/api/helper'
export default {
  components: {
    SearchBoxVue,
    TableBoxVue,
    AddModalVue
  },
  data() {
    return {
      loading: false,
      tableData: [],
      paginationData: {
        total: 0,
        page: 1,
        limit: 10
      }
    }
  },
  created() {},
  mounted() {
    this.getList()
  },
  activated() {
    this.getList()
  },
  methods: {
    async getList() {
      const { page, limit } = this.paginationData
      const params = {
        ...this.$refs.SearchBoxRef.formData,
        page,
        page_size: limit
      }
      this.loading = true
      helperPage(params)
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
    handleReset() {
      this.handleSearch()
    },
    handleSearch() {
      this.paginationData.page = 1
      this.getList()
    },
    handleRefresh() {
      this.paginationData.page = 1
      this.getList()
    },
    handleAdd() {
      this.$refs.AddModalVueRef.openModal({ type: '2' }, 'ADD')
    },
    handleEdit(row) {
      this.$refs.AddModalVueRef.openModal(row, 'EDIT')
    }
  }
}
</script>

<style lang="scss" scoped>
.box-container {
  padding: 20px;
}
</style>
