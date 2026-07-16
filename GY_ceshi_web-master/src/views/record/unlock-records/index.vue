<template>
  <div class="page-container">
    <SearchBoxVue
      qualityType="2"
      class="filter-list-form"
      ref="SearchBoxRef"
      @search="handleSearch"
      @reset="handleReset"
    />
    <div class="filter-list-action">
      <el-row type="flex" justify="space-between" align="middle">
        <mPagination
          :data="paginationData"
          @pageChangeEvent="pageHasChanged"
        ></mPagination>
        <el-button type="primary" @click="exportData()" icon="el-icon-download">导出数据</el-button>
      </el-row>
      <TableBoxVue :loading="loading" :data="tableData" ref="tableRef" />
    </div>
  </div>
</template>

<script>
import mPagination from '@/components/m-pagination';
import SearchBoxVue from './SearchBox.vue';
import TableBoxVue from './TableBox.vue';
import { shizhong_quality_unlock_records } from '@/api/excel';
import { exportFile } from '@/utils/export-file';
export default {
  components: {
    mPagination,
    SearchBoxVue,
    TableBoxVue,
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
      this.$axios2
        .post('/case-quality/shizhong_quality_unlock_records', {
          ...this.$refs.SearchBoxRef.formData,
          page: this.paginationData.currentPage,
          page_size: this.paginationData.pageSize,
        })
        .then(res => {
          if (res.code == 200) {
            this.loading = false;
            this.paginationData.total = res.data.count;
            this.tableData = res.data.list;
            this.$refs.tableRef.selectedArray = [];
          }
        }).finally(() => {
            this.loading = false;
        })
        ;
    },
    exportData() {
      shizhong_quality_unlock_records({
        ...this.$refs.SearchBoxRef.formData,
        is_export: 1,
        page: this.paginationData.currentPage,
        page_size: this.paginationData.pageSize,
      }).then(res => {
        exportFile(res.data, 'csv', '解锁记录.csv');
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
