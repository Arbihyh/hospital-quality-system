<template>
    <div>
      <SearchBoxVue ref="SearchBoxRef" @search="handleSearch" @reset="handleReset" @add="handleAdd" :action="action" :staffListData="staffListData"/>
      <div>
        <TableBoxVue :loading="loading" :data="tableData" ref="tableRef" @onUpdate="getList" :action="action" :staffListData="staffListData"/>
        <el-row type="flex" justify="end" align="middle">
          <mPagination v-if="tableData && tableData.length !== 0" :data="paginationData" @pageChangeEvent="pageHasChanged"></mPagination>
        </el-row>
      </div>
    </div>
  </template>
  
  <script>
  import mPagination from '@/components/m-pagination';
  import SearchBoxVue from '@/views/recordsRoom/qc/components/AssignDoctor/SearchBox.vue'
  import TableBoxVue from '@/views/recordsRoom/qc/components/AssignDoctor/TableBox.vue'
  import pagination from '@/components/Pagination/index2.vue'
  import { getCaseAppealList } from '@/api/admin'
  import { getStaffListData } from '@/api/qc';

  export default {
    components: {
      mPagination,
      SearchBoxVue,
      TableBoxVue,
      pagination,
    },
    emits: ['onListDataChange'],
    props: ['action'],
    data() {
      return {
        loading: false,
        tableData: [],
        staffListData: [],
        paginationData: {
          total: 0,
          currentPage: 1,
          pageSize: 10
        },
      }
    },
    created() {
    },
    mounted() {
      this.getList()
      this.getStaffList()
    },
    methods: {
      // 人员
      getStaffList() {
        getStaffListData().then(res => {
          const { data = [] } = res;
          this.staffListData = data
        });
      },
      getList() {
        this.loading = true
        getCaseAppealList({
          ...this.$refs.SearchBoxRef.formData,
          page: this.paginationData.currentPage,
          page_size: this.paginationData.pageSize
        }).then(res => {
          this.paginationData.total = res.data.count
          this.tableData = res.data.list
          this.$refs.tableRef.selectedArray = []
          this.$emit('onListDataChange', this.tableData)
        }).catch(error => {
          console.log(error)
        }).finally(() => {
          this.loading = false
        })
      },
  
      pageHasChanged() {
        this.getList()
      },
      handleSearch() {
        this.paginationData.currentPage = 1
        this.getList()
      },
      handleReset() {
        this.handleSearch()
      },
      handleAdd() {
        this.tableData.unshift({
            isEditing: true,
            editData: {}
        })
      }
    }
  }
  </script>
  
  <style lang="scss" scoped>
  </style>
  