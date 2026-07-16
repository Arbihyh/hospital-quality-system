<template>
  <div class="app-container">
    <div class="filter-list-form">
      <CorrectionSearchBoxVue
        ref="SearchBoxRef"
        @search="handleSearch"
        @reset="handleReset"
        @onExport="onExport(2)"
      />
    </div>
    <CorrectionTableBoxVue :loading="loading" :data="tableData" @search="handleTableSearch"/>
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
import { getCorrectionList } from '@/api/qc';
import { correctionListExport } from '@/api/excel';

let current = '';

export default {
  components: {
    mPagination,
    CorrectionSearchBoxVue,
    CorrectionTableBoxVue,
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
      order_type: '',
      order_key: '',
      current: '',
    };
  },
  created() {},
  activated() {
    this.getList();
  },
  mounted() {
    this.getList();
  },
  beforeRouteEnter(to, from, next) {
    current = from.query.ZYH || '';
    next();
  },
  methods: {
    getList() {
      this.loading = true;
      getCorrectionList({
        ...this.$refs.SearchBoxRef.formData,
        quality_level: 3, //三级质控
        order_key: this.order_key,
        order_type: this.order_type,
        page: this.paginationData.currentPage,
        page_size: this.paginationData.pageSize,
      })
        .then(res => {
          this.paginationData.total = res.data.count;
          this.tableData = res.data.list;
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
        order_key: this.order_key,
        order_type: this.order_type,
        quality_level: 3, //三级质控
        is_export: 1,
      };
      const fileName = '审核明细.csv';
      correctionListExport(params).then(res => {
        const content = res.data;
        const blob = new Blob([content]);
        const name = type === 1 ? '审核历史' : '审核明细';
        if ('download' in document.createElement('a')) {
          const elink = document.createElement('a');
          elink.download = fileName;
          elink.style.display = 'none';
          elink.href = URL.createObjectURL(blob);
          document.body.appendChild(elink);
          elink.click();
          URL.revokeObjectURL(elink.href);
          document.body.removeChild(elink);
        } else {
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
    handleTableSearch(order_type, order_key){
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
