<template>
  <div class="app-container">
    <div class="filter-list-form">
      <el-radio-group v-model="currentTab" @input="handleReset" style="margin-bottom:15px">
        <el-radio-button label="审核列表"></el-radio-button>
        <el-radio-button label="审核明细"></el-radio-button>
      </el-radio-group>
      <SearchBoxVue
        v-if="currentTab === '审核列表'"
        ref="SearchBoxRef"
        @search="handleSearch"
        @reset="handleReset"
        @onExport="onExport(1)"
      />
      <CorrectionSearchBoxVue
        v-else
        ref="SearchBoxRef"
        @search="handleSearch"
        @reset="handleReset"
        @onExport="onExport(2)"
      />
    </div>
    <TableBoxVue v-if="currentTab === '审核列表'" :loading="loading" :data="tableData" />
    <CorrectionTableBoxVue v-else :loading="loading" @search="handleTableSearch" :data="tableData" />
    <div class="filter-list-table-pagination">
      <mPagination
        v-if="tableData && tableData.length !== 0"
        :data="paginationData"
        @pageChangeEvent="pageHasChanged"
        @sizeChange="sizeChange"
      ></mPagination>
    </div>
  </div>
</template>

<script>
import mPagination from '@/components/m-pagination';
import CorrectionSearchBoxVue from '@/views/recordsRoom/qc/components/correction/SearchBox.vue';
import CorrectionTableBoxVue from '@/views/recordsRoom/qc/components/correction/TableBox.vue';
import SearchBoxVue from '@/views/recordsRoom/qc/components/SearchBox.vue';
import TableBoxVue from '@/views/recordsRoom/qc/components/TableBox.vue';
import pagination from '@/components/Pagination/index2.vue';
import { getBlZkList, getCorrectionList } from '@/api/qc';
import { blZkListExport, correctionListExport } from '@/api/excel';

let current = '';

export default {
  components: {
    mPagination,
    SearchBoxVue,
    TableBoxVue,
    pagination,
    CorrectionSearchBoxVue,
    CorrectionTableBoxVue,
  },
  data() {
    return {
      currentTab: '审核列表',
      loading: false,
      tableData: [],
      paginationData: {
        total: 0,
        currentPage: 1,
        pageSize: 10,
      },
      order_type: '',
      order_key: '',
      current: '',
    };
  },
  created() {},
  activated() {
    this.getList();
  },
  beforeRouteEnter(to, from, next) {
    current = from.query.ZYH || '';
    // console.log('>>>>>>>>>111', a, b)
    next();
  },
  methods: {
    getList() {
      this.loading = true;
      let params = {
        ...this.$refs.SearchBoxRef.formData,
        page: this.paginationData.currentPage,
        page_size: this.paginationData.pageSize,
      };
      if (this.currentTab === '审核明细') {
        params.order_key = this.order_key;
        params.order_type = this.order_type;
        params.quality_level = 2;
      }
      (this.currentTab === '审核列表' ? getBlZkList : getCorrectionList)(params)
        .then(res => {
          this.paginationData.total = res.data.count;
          this.tableData = res.data.list;
          // 切换选中状态
          Array.isArray(this.tableData) &&
            this.tableData.map(item => {
              item.selected = item.ZYH == current ? true : false;
            });
        })
        .catch(error => {
          console.log(error);
        })
        .finally(() => {
          this.loading = false;
        });
    },

    pageHasChanged(params) {
      this.getList();
    },
    sizeChange(size) {
      this.paginationData.pageSize = size;
      this.getList();
    },
    onExport(type) {
      const params = {
        ...this.$refs.SearchBoxRef.formData,
        page: this.paginationData.currentPage,
        page_size: this.paginationData.pageSize,
        is_export: 1,
      };
      if (this.currentTab === '审核明细') {
        params.order_key = this.order_key;
        params.order_type = this.order_type;
        params.quality_level = 2;
      }
      const exportApi = this.currentTab === '审核列表' ? blZkListExport : correctionListExport;
      const fileName = this.currentTab === '审核列表' ? '审核历史.csv' : '审核明细.csv';
      exportApi(params).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const name = type === 1 ? '审核历史' : '审核明细';
        // const fileName = `${name}.csv`;
        if ('download' in document.createElement('a')) {
          // 非IE下载
          const elink = document.createElement('a');
          elink.download = fileName;
          elink.style.display = 'none';
          elink.href = URL.createObjectURL(blob);
          document.body.appendChild(elink);
          elink.click();
          URL.revokeObjectURL(elink.href); // 释放URL 对象
          document.body.removeChild(elink);
        } else {
          // IE10+下载
          navigator.msSaveBlob(blob, fileName);
        }
      });
    },
    handleSearch() {
      this.$nextTick(() => {
        this.paginationData.currentPage = 1;
        this.getList();
      });
    },
    handleTableSearch(order_type, order_key) {
      this.order_key = order_key;
      this.order_type = order_type;
      this.getList();
    },
    handleReset() {
      this.order_key = '';
      this.order_type = '';
      this.handleSearch();
    },
  },
};
</script>

<style lang="scss" scoped>
.pagination-container {
  background-color: rgba(244, 244, 244, 1);
  padding: 0 20px;
  display: flex;
  justify-content: flex-end;

  .cus-total {
    float: none;
    line-height: 28px;
  }

  .el-pagination {
    float: none;
  }
}

.app-container {
  &::-webkit-scrollbar {
    width: 16px !important;
    height: 16px !important;
  }
}
</style>
