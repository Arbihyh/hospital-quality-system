<template>
  <div class="box">
    <div class="box_wrapper">
      <SearchBoxVue :data="searchData" :codes="tableShowCode" :type_name="type_name" @search="handleSearch" @reset="handleReset" />
      <TableBoxVue
        :loading="loading"
        :data="tableData"
        :type_name="type_name"
        :hospital_name="searchData.hospital_name"
        :page="paginationData"
        :codes="tableShowCode"
        @codesChange="handleCodesChange"
        @sort="handleSort"
        @reset="handleReset"
        @export="handelExport"
        @limit_change="handleLimitChange"
      />
      <div style="overflow: hidden; text-align: center; background: #fff; padding-bottom: 16px">
        <el-pagination
          :total="paginationData.total"
          background
          class="table-pagination"
          :page-size="paginationData.limit"
          :current-page.sync="paginationData.page"
          layout="prev, pager, next, jumper"
          @size-change="SizeChangeEvent"
          @current-change="pageHasChanged"
        />
      </div>
    </div>
  </div>
</template>

<script>
import SearchBoxVue from './components/SearchBox2.vue';
import TableBoxVue from './components/TableBox2.vue';
import { errorDetailsListExport, errorDetailsLCListExport } from '@/api/excel';
import { dateFormat, getDaysInMonth } from '@/utils';

export default {
  components: {
    SearchBoxVue,
    TableBoxVue,
  },
  data() {
    return {
      loading: false,
      searchData: {
        hospital_name: '', // 医院名称
        AAA28: '',
        dep_id: '',
        start_time: '',
        end_time: '',
        type: '',
        level: '',
        desc: '',
        AEE04: '',
        AEE08: '',
        ICD10_NAME: '',
        ICD10_ID1: '',
        ICD9_NAME: '',
        ICD9_ID1: '',
        AAC11N: '',
        zk_start_time: '',
        zk_end_time: '',
        ry_start_time: '',
        ly_end_time: '',
      },
      tableData: [],
      paginationData: {
        total: 0,
        page: 1,
        limit: 10,
      },
      error_rule: '',
      tableShowCode: ['field', 'desc', 'AAA28', 'AAA01', 'time', 'AAC11N', 'AEE08', 'AEE04', 'type', 'level', 'ICD10_NAME', 'ICD10_ID1', 'ICD9_NAME', 'ICD9_ID1'],
      sort: [],
      type_name: '',
    };
  },
  created() {
    this.error_rule = this.$route.query.error_rule;
    this.searchData.hospital_name = this.$route.query.hospital_name ? this.$route.query.hospital_name : '';
    this.type_name = this.$route.query.type_name ? this.$route.query.type_name : '';
    this.searchData.start_time = this.$route.query.start_time ? this.$route.query.start_time : '';
    this.searchData.end_time = this.$route.query.end_time ? this.$route.query.end_time : '';
    this.searchData.zk_start_time = this.$route.query.zk_start_time ? this.$route.query.zk_start_time : '';
    this.searchData.zk_end_time = this.$route.query.zk_end_time ? this.$route.query.zk_end_time : '';
    this.searchData.ry_start_time = this.$route.query.ry_start_time ? this.$route.query.ry_start_time : '';
    this.searchData.ly_end_time = this.$route.query.ly_end_time ? this.$route.query.ly_end_time : '';
    this.searchData.AAA28 = this.$route.query.zyhm ? this.$route.query.zyhm : '';
    this.searchData.AAC11N = this.$route.query.cykb ? this.$route.query.cykb : '';
    this.getList();
  },
  methods: {
    handleReset() {
      this.searchData = {
        hospital_name: '',
        AAA28: '',
        dep_id: '',
        start_time: '',
        end_time: '',
        type: '',
        level: '',
        desc: '',
        AEE04: '',
        AEE08: '',
        ICD10_NAME: '',
        ICD10_ID1: '',
        ICD9_NAME: '',
        ICD9_ID1: '',
        AAC11N: '',
        zk_start_time: '',
        zk_end_time: '',
        ry_start_time: '',
        ly_end_time: '',
      };
    },
    // 展示字段变化
    handleCodesChange(val) {
      localStorage.setItem('surgery_talbe_codes', val);
      this.$set(this, 'tableShowCode', val);
    },
    getList() {
      const { page, limit } = this.paginationData;
      const params = {
        ...this.searchData,
        error_rule: this.error_rule,
        page,
        page_size: limit,
      };
      // const { start_time, end_time } = this.searchData;
      // const days = getDaysInMonth(end_time.slice(0, 4), end_time.slice(4, 6));
      // const dayStr = days < 10 ? `0${days}` : days;
      // if (start_time) {
      //   params.start_time = start_time.length === 8 ? start_time : `${start_time}01`;
      // }
      // if (end_time) {
      //   params.end_time = `${end_time.slice(0, 4)}${end_time.slice(4, 6)}${dayStr}`;
      // }

      if (this.sort.length) {
        params.sort = this.sort;
      }
      let url = '';
      if (this.type_name == 'lc') {
        url = '/home_sz_quality/errorDetailsList';
      } else {
        url = '/home_quality/errorDetailsList';
      }
      this.$axios.post(url, params).then(res => {
        this.tableData = res.data.list;
        this.paginationData.total = res.data.count;
      });
    },
    handleSort(val) {
      this.sort = val;
      this.getList();
    },
    SizeChangeEvent(val) {
      this.paginationData.limit = val;
      this.getList();
    },
    pageHasChanged(val) {
      this.paginationData.page = val;
      this.getList();
    },
    handleSearch() {
      this.paginationData.page = 1;
      this.getList();
    },
    handleLimitChange(val) {
      this.paginationData.page = 1;
      this.paginationData.limit = val;
      this.getList();
    },
    handelExport() {
      if (this.type_name == 'lc') {
        this.lcExport();
      } else {
        this.flcExport();
      }
    },
    // 临床导出
    lcExport() {
      errorDetailsLCListExport({ ...this.searchData, error_rule: this.error_rule, is_export: 1 }).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `缺陷问题详情.csv`;
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
    // 非临床导出
    flcExport() {
      errorDetailsListExport({ ...this.searchData, error_rule: this.error_rule }).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `缺陷问题详情.csv`;
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
    handleReset() {
      this.paginationData.page = 1;
      this.paginationData.limit = 10;
      this.searchData = {
        hospital_name: '', // 医院名称
        AAA28: '',
        dep_id: '',
        start_time: '',
        end_time: '',
        type: '',
        level: '',
        desc: '',
        AEE04: '',
        AEE08: '',
        ICD10_NAME: '',
        ICD10_ID1: '',
        ICD9_NAME: '',
        ICD9_ID1: '',
      };
      this.tableData = [];
      this.getList();
    },
  },
};
</script>

<style lang="scss" scoped>
.part-box {
  padding: 16px;
  background: #fff;
  border-radius: 4px;
}

.box {
  padding: 0 16px 16px 16px;
  .box_wrapper {
    border-radius: 5px;
    position: relative;
  }
}
</style>