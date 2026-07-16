<template>
  <div class="app-container">
    <SearchBoxVue class="filter-list-form" ref="SearchBoxRef" @search="handleSearch" @reset="handleReset" />
    <div class="filter-list-action">
      <el-row type="flex" justify="space-between" align="middle">
        <mPagination  :paginationData="paginationData" @pageChangeEvent="pageHasChanged"
         @btnEvent="btnEvent">
          <template #table>
            <!-- 这里放置你的表格组件 -->
            <TableBoxVue :loading="loading" :data="tableData" ref="tableRef" @onUpdate="getList" />
            <!-- 表格列定义 -->
          </template>
        </mPagination>
        <!-- <el-row type="flex" justify="end" style="flex: 1">
          <el-button @click="openAddPlanModal" type="primary">新建计划</el-button>
        </el-row> -->
      </el-row>
      <!-- <TableBoxVue :loading="loading" :data="tableData" ref="tableRef" @onUpdate="getList" /> -->
    </div>
    <AddPlanModalBoxVue2 ref="AddPlanModalBoxVueRef" @onUpdate="getList" />
  </div>
</template>
  
  <script>
import mPagination from '@/components/m-pagination/new-index.vue';
import SearchBoxVue from '@/views/recordsRoom/qc/components/qualityControlPlan/SearchBox.vue';
import TableBoxVue from '@/views/recordsRoom/qc/components/qualityControlPlan/TableBox.vue';
import AddPlanModalBoxVue2 from '@/views/recordsRoom/qc/components/qualityControlPlan/AddPlanModal2.vue';
import pagination from '@/components/Pagination/index2.vue';
import { getCaseAppealList } from '@/api/admin';
import { examineAppeal, planSaveAndEdit, planList, planDel } from '@/api/qc';

export default {
  components: {
    mPagination,
    SearchBoxVue,
    TableBoxVue,
    pagination,
    AddPlanModalBoxVue2,
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
  created() {},
  mounted() {
    this.getList();
  },
  methods: {
    getList() {
      this.loading = true;
      planList({
        ...this.$refs.SearchBoxRef.formData,
        page: this.paginationData.currentPage,
        page_size: this.paginationData.pageSize,
      })
        .then(res => {
          this.paginationData.total = res.data.total;
          this.tableData = res.data.list;
          this.$refs.tableRef.selectedArray = [];
        })
        .catch(error => {
          console.log(error);
        })
        .finally(() => {
          this.loading = false;
        });

      // this.$axios
      //   .post('/case_quality_plan/list', {
      //     ...this.$refs.SearchBoxRef.formData,
      //     page: this.paginationData.currentPage,
      //     page_size: this.paginationData.pageSize,
      //   })
      //   .then(res => {
      //     this.paginationData.total = res.data.total;
      //     this.tableData = res.data.list;
      //     this.$refs.tableRef.selectedArray = [];
      //   })
      //   .catch(error => {
      //     console.log(error);
      //   })
      //   .finally(() => {
      //     this.loading = false;
      //   });
    },
    btnEvent(){
        this.$refs.AddPlanModalBoxVueRef.openModal();
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
    openAddPlanModal() {
      this.$refs.AddPlanModalBoxVueRef.openModal();
    },
  },
};
</script>
  
  <style lang="scss" scoped>

  .filter-list-action{
    padding: 0px;
  }
</style>
  