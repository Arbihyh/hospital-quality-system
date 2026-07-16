<template>
  <div class="box">
    <div class="box_wrapper">
      <SearchBoxVue :data="searchData" :type_name="type_name" @search="handleSearch" @reset="handleReset" />
      <TableBoxVue
        :loading="loading"
        :data="tableData"
        :type_name="type_name"
        :search="searchData"
        :hospital_name="searchData.hospital_name"
        :page="paginationData"
        @export="handelExport"
        @limit_change="handleLimitChange"
      />
      <div style="overflow: hidden; text-align: center; background: #fff; padding-bottom: 16px;">
        <el-pagination
          :total="paginationData.total"
          background
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
import SearchBoxVue from './components/SearchBox.vue';
import TableBoxVue from './components/TableBox.vue';
import { errorDataLCExport } from '@/api/excel';
import { dateFormat } from  '@/utils'
export default {
  components: {
    SearchBoxVue,
    TableBoxVue,
  },
  data() {
    return {
      type_name: 'lc',
      loading: false,
      searchData: {
        hospital_name: '', // 医院名称
        start_time: '',
        end_time: '',
        level: '',
        type: '',
        desc: '',
        field: '',
        zk_start_time: '',
        zk_end_time: '',
        ry_start_time: '',
        ly_end_time: '',
        cykb: '',
        zyhm: ''
      },
      HospitalList: [], //医院名称列表
      tableData: [],
      paginationData: {
        total: 0,
        page: 1,
        limit: 10,
      },
    };
  },
  created() {
    this.searchData.hospital_name = this.$route.query.hospital_name ? this.$route.query.hospital_name : '';

    // 设置默认时间
    const currentYear = new Date().getFullYear();
    const currentMonth = new Date().getMonth()+1 < 10 ? `0${new Date().getMonth()+1}` : new Date().getMonth()+1;
    this.searchData.zk_start_time = `${currentYear}${currentMonth}01`;
    this.searchData.zk_end_time = dateFormat(new Date(), 'YYYYMMDD');
    this.getList();
  },
  methods: {
    getList() {
      const { page, limit } = this.paginationData;
      const { hospital_name, start_time, end_time, level, type, desc, field, zk_start_time, zk_end_time, ry_start_time, ly_end_time, cykb, zyhm } = this.searchData;
      const params = {
        hospital_name,
        level,
        type,
        desc,
        field,
        zk_start_time,
        zk_end_time,
        ry_start_time,
        ly_end_time,
        start_time,
        end_time,
        page,
        page_size: limit,
        cykb,
        zyhm
      };

      this.$axios.post('/home_sz_quality/errorData', params).then(res => {
        this.tableData = res.data.list;
        this.paginationData.total = res.data.count;
      });
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
    handleReset() {
      this.searchData = {
        hospital_name:"",// 医院名称
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
        zyhm: '',
        cykb: ''
      }
    },
    handelExport() {
      errorDataLCExport({ ...this.searchData, is_export: 1 }).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `缺陷问题.csv`;
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
    handleLimitChange(val) {
      this.paginationData.page = 1;
      this.paginationData.limit = val;
      this.getList();
    }
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