<template>
  <div class="page-container">
    <SearchBoxVue qualityType="2" class="filter-list-form" ref="SearchBoxRef" @search="handleSearch" @reset="handleReset" />
    <div class="filter-list-action">
      <el-row type="flex" justify="space-between" align="middle">
        <mPagination v-if="tableData && tableData.length !== 0" :data="paginationData" @pageChangeEvent="pageHasChanged"></mPagination>
        <el-button type="primary" @click="exportData()" icon="el-icon-download">导出数据</el-button>

        <!-- <el-row type="flex" justify="end" style="flex:1">
          <el-button @click="actionYes">批量通过</el-button>
          <el-button>批量驳回</el-button>
          <el-button>筛选</el-button>
          <el-button icon="el-icon-download">下载</el-button>
        </el-row> -->
      </el-row>
      <TableBoxVue :loading="loading" :data="tableData" ref="tableRef" />
    </div>
  </div>
</template>

<script>
import mPagination from '@/components/m-pagination';
import SearchBoxVue from '@/views/recordsRoom/qc/components/review/SearchBox.vue';
import TableBoxVue from '@/views/recordsRoom/qc/components/review//TableBox.vue';
import pagination from '@/components/Pagination/index2.vue';
import { getCaseAppealList } from '@/api/admin';
import { exportCaseAppeal } from '@/api/excel';
import { exportFile } from '@/utils/export-file';
// import { search } from 'core-js/fn/symbol';
export default {
  components: {
    mPagination,
    SearchBoxVue,
    TableBoxVue,
    pagination,
  },
  data() {
    return {
      loading: false,
      tableData: [],
      paginationData: {
        total: 0,
        currentPage: 1,
        pageSize: 10,
      },
    };
  },
  activated() {
    this.getList();
  },
  methods: {
    getList() {
      this.loading = true;
      getCaseAppealList({
        ...this.$refs.SearchBoxRef.formData,
        page: this.paginationData.currentPage,
        page_size: this.paginationData.pageSize,
      })
        .then(res => {
          this.paginationData.total = res.data.count;
          this.tableData = res.data.list;
          this.$refs.tableRef.selectedArray = [];
        })
        .catch(error => {
          console.log(error);
        })
        .finally(() => {
          this.loading = false;
        });
    },
    exportData() {
      exportCaseAppeal({
        ...this.$refs.SearchBoxRef.formData,
        is_export: 1,
        page: this.paginationData.currentPage,
        page_size: this.paginationData.pageSize,
      }).then(res => {
        exportFile(res.data, 'csv', '运行病历申诉.csv');
      });
    },

    pageHasChanged() {
      this.getList();
    },
    handleSearch() {
      this.paginationData.currentPage = 1;
      this.getList();
    },
    handleReset() {
      this.handleSearch();
    },
    actionYes() {
      if (this.$refs.tableRef.selectedArray.length == 0) {
        this.$message.warning('请至少选择一条数据！');
        return;
      } else {
        this.$message.success('操作成功！');
      }
    },
  },
};
</script>

<style lang="scss" scoped>
.page-container {
  &::-webkit-scrollbar {
    width: 16px !important;
    height: 16px !important;
  }
}
</style>
