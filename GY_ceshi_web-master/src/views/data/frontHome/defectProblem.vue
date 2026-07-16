<template>
  <div class="box">
    <div class="box_wrapper">
      <SearchBoxVue :data="searchData" :type_name="type_name" @search="handleSearch" />
      <TableBoxVue :loading="loading" :data="tableData"  :type_name="type_name" :search="searchData" :hospital_name="searchData.hospital_name" @export="handelExport" style="margin-top: -40px;" />
      <div style="overflow: hidden;">
        <el-pagination
          v-if="tableData && tableData.length !== 0"
          :total="paginationData.total"
          background
          class="table-pagination"
          :page-size="paginationData.limit"
          :current-page.sync="paginationData.page"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="SizeChangeEvent"
          @current-change="pageHasChanged"
        />
      </div>
    </div>
  </div>
</template>

<script>
import SearchBoxVue from './components/SearchBox.vue'
import TableBoxVue from './components/TableBox.vue'
import { errorDataExport } from '@/api/excel'
import { dateFormat, getDaysInMonth } from '@/utils'

export default {
  components: {
    SearchBoxVue,
    TableBoxVue
  },
  data() {
    return {
      type_name:'',
      loading: false,
      searchData: {
        hospital_name:"",// 医院名称
        start_time: '',
        end_time: '',
        level: '',
        type: '',
        desc: '',
        field: '',
      },
      HospitalList: [], //医院名称列表
      tableData: [],
      paginationData: {
        total: 0,
        page: 1,
        limit: 10
      }
    }
  },
  created() {
    this.searchData.hospital_name = this.$route.query.hospital_name?this.$route.query.hospital_name:'';
    this.type_name = this.$route.query.type_name?this.$route.query.type_name:''; // 'lc' 临床

    // 设置默认时间
    const currentYear = new Date().getFullYear()
    this.searchData.start_time = `${currentYear}0101`
    this.searchData.end_time = dateFormat(new Date(), 'YYYYMMDD')
    this.getList();
  },
  methods: {
    getList() {
      const {
        page,
        limit
      } = this.paginationData
      const {
        hospital_name,
        start_time,
        end_time,
        level,
        type,
        desc,
        field
      } = this.searchData
      const days = getDaysInMonth(end_time.slice(0, 4), end_time.slice(4, 6))
      const dayStr = days < 10 ? `0${days}` : days
      const params = {
        hospital_name,
        level,
        type,
        desc,
        field,
        start_time: start_time.length === 8 ? start_time : `${start_time}01`,
        end_time: `${end_time.slice(0, 4)}${end_time.slice(4, 6)}${dayStr}`,
        page,
        page_size: limit
      }
      let url = '';
      if(this.type_name == 'lc'){
        url = '/home_sz_quality/errorData'
      }else{
        url = '/home_quality/errorData'
      }
      this.$axios.post(url, params).then(res => {
        this.tableData = res.data.list
        this.paginationData.total = res.data.count
      });
    },
    SizeChangeEvent(val) {
      this.paginationData.limit = val
      this.getList()
    },
    pageHasChanged(val) {
      this.paginationData.page = val
      this.getList()
      
    },
    handleSearch() {
      this.paginationData.page = 1
      this.getList()
    },
    handelExport() {
      errorDataExport(this.searchData).then(res => {
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

  }
}
</script>

<style lang="scss" scoped>
.box {
  padding: 0 16px 16px 16px;
  .box_wrapper {
    padding: 16px;
    padding: 16px;
    background: #fff;
    border-radius: 5px;
    position: relative;
  }
  .table-pagination {
    float: right;
  }
}
</style>