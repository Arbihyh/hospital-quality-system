<template>
  <div class="bg-box">
    <div class="bg-card">
      <SearchBoxVue :data="searchData" @search="handleSearch" @reset="handleReset" />
      <div style="margin-top: -22px;">
        <el-divider></el-divider>
        <TableBoxVue :data="tableData" :paginationData="paginationData" @sort="handleSort" @export="handleExport" />
        <Pagination :page="paginationData.page" :limit="paginationData.page_size" :total="paginationData.total" @pagination="handlePagination" />
      </div>
    </div>
  </div>
</template>

<script>
import SearchBoxVue from './components/doctorBlKf/SearchBox.vue'
import TableBoxVue from './components/doctorBlKf/TableBox.vue'
import Pagination from '@/components/Pagination'
import { bmyDoctorRankingBlKfExport } from '@/api/excel'

export default {
  components: {
    SearchBoxVue,
    TableBoxVue,
    Pagination
  },
  data() {
    return {
      searchData: {},
      tableData: [],
      paginationData: { page: 1, page_size: 10, total: 0 }
    }
  },
  created() {
    let query = this.$route.query;
    //时间范围
    let timeArray = [];
    timeArray[0] = query['start_time'];
    timeArray[1] = query['end_time'];
    //医师code
    let doctor_code = [];
    doctor_code[0] = query['doctor_code'];
    let params = {};
    params['zy_status'] = query['zy_status'];
    params['bm_status'] = query['bm_status'];
    params['time'] = timeArray;
    params['doctor_code'] = doctor_code;
    params['sf_type'] = query['sf_type']
    params['AAA28'] = query['AAA28']
    this.searchData = params;
    this.getList()
  },
  methods: {
    getList() {
      let params =  Object.assign({},this.searchData, this.paginationData);
      this.$axios.post('/bmy/doctorErrorRanking', params).then(res => {
        this.tableData = res.data.data;
        this.paginationData.total = res.data.total;
      });
    },
    handleSearch() {
      this.paginationData.page = 1
      this.getList()
    },
    handleReset() {
      this.searchData = {
        time: [],
        doctor_name: [],
        AAC11N: '',
        sort: []
      }
    },
    handlePagination(val) {
      const { page, limit } = val
      this.paginationData.page = page
      this.paginationData.page_size = limit
      this.getList()
    },
    handleSort(val) {
      this.searchData.sort = val
      this.getList()
    },
    handleExport() {
      const { time } = this.searchData
      const params = {
        is_export: 1,
        ...this.searchData
      }
      if (time && time.length) {
        params.start_time = time[0]
        params.end_time = time[1]
      }
      bmyDoctorRankingBlKfExport(params).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `首页质控(编码员)-医生病历扣分.csv`;
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
    }
  }
}
</script>

<style>

</style>
