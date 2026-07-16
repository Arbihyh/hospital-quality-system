<template>
  <div class="bg-box">
    <div class="bg-card">
      <SearchBoxVue :data="searchData" @search="handleSearch" @reset="handleReset" />
      <div style="margin-top: -22px;">
        <el-divider></el-divider>
        <TableBoxVue
          :data="tableData"
          :paginationData="paginationData"
          @sort="handleSort"
          @export="handleExport"
        />
        <Pagination
          :page="paginationData.page"
          :limit="paginationData.page_size"
          :total="paginationData.total"
          @pagination="handlePagination"
        />
      </div>
    </div>
  </div>
</template>

<script>
import SearchBoxVue from './components/doctorBl/SearchBox.vue';
import TableBoxVue from './components/doctorBl/TableBox.vue';
import Pagination from '@/components/Pagination';
import { bmyDoctorRankingBlExport } from '@/api/excel';

export default {
  components: {
    SearchBoxVue,
    TableBoxVue,
    Pagination,
  },
  data() {
    return {
      searchData: {}, //搜索
      tableData: [], //表单数据
      paginationData: { page: 1, page_size: 10, total: 0 }, //分页
    };
  },
  created() {
    let query = this.$route.query;

    let params = {};
    params['zy_status'] = query['zy_status'];
    params['bm_status'] = query['bm_status'];

    params['AAA28'] = query['AAA28'];

    params['code'] = query['doctor_code'];
    params['sf_type'] = query['sf_type'];
    params['start_time'] = query['start_time'];
    params['end_time'] = query['end_time'];
    this.searchData = params;
    this.getList();
  },
  methods: {
    //获取table数据
    getList() {
      let params = Object.assign({}, this.searchData);
      params.page = this.paginationData.page;
      params.page_size = this.paginationData.page_size;
      // this.$axios2.post('/case-quality/doctor_ranking_list', params).then(res => {
      //   this.tableData = res.data.data;
      //   this.paginationData.total = res.data.total;
      // });
      params.is_export = 0;
      this.$axios.post('/bmy/doctorRankingDrillList', params).then(res => {
        this.tableData = res.data.list;
        this.paginationData.total = res.data.count;
      });
    },
    handleSearch() {
      this.paginationData.page = 1;
      this.getList();
    },
    handleReset() {
      this.searchData = {
        start_time: '',
        end_time: '',
        level: '',
        code: '',
        AAC11N: '',
        sort: [],
      };
    },
    handlePagination(val) {
      const { page, limit } = val;
      this.paginationData.page = page;
      this.paginationData.page_size = limit;
      this.getList();
    },
    handleSort(val) {
      this.searchData.sort = val;
      this.getList();
    },
    //导出
    handleExport() {
      let params = this.searchData;
      params['is_export'] = 1;
      bmyDoctorRankingBlExport(params).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `首页质控(编码员)-医生病历总数.csv`;
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
  },
};
</script>

<style>
</style>
