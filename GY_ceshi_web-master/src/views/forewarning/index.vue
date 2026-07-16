<template>
  <div class="bg-box">
    <!-- 搜索栏 -->
    <SearchBoxVue :data="searchData" @search="handleSearch" @reset="handleReset" />
    <!-- 列表 -->
    <TableBoxVue :data="tableData" :page="paginationData" @export="handelExport" @limit_change="handleLimitChange" />
    <div style="overflow: hidden; text-align: center; background: #fff; padding-bottom: 16px">
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
</template>

<script>
import SearchBoxVue from './components/SearchBox.vue';
import TableBoxVue from './components/TableBox.vue';
import { foreWarningExport } from '@/api/excel';
import { dateFormat } from '@/utils'
export default {
  components: {
    SearchBoxVue,
    TableBoxVue,
  },
  data() {
    return {
      searchData: {
        quality_start_time: '',
        quality_end_time: '',
        aac01_start_time: '',
        aac01_end_time: '',
        aab01_start_time: '',
        aab01_end_time: '',
        department: '',
        content: '',
        AAA28: '',
        is_delete: 1,
        status: ''
      },
      tableData: [],
      paginationData: {
        total: 0,
        page: 1,
        limit: 10,
      },
    };
  },
  created() {
    this.searchData.quality_start_time = dateFormat(new Date(), 'YYYYMMDD')
    this.searchData.quality_end_time = dateFormat(new Date(), 'YYYYMMDD')
    this.getList()
  },
  methods: {
    getList() {
      const { page, limit } = this.paginationData;
      const { 
        quality_start_time,
        quality_end_time,
        aac01_start_time,
        aac01_end_time,
        aab01_start_time,
        aab01_end_time,
        department,
        content,
        AAA28,
        is_delete,
        status} = this.searchData
      let url = `/warning_msg?page=${page}&page_size=${limit}&export=0&content=${content}&department=${department}&is_delete=${is_delete}&status=${status}`
      // if (quality_start_time) {
      //   url += `&quality_start_time=${quality_start_time}`
      // }
      // if (quality_end_time) {
      //   url += `&quality_end_time=${quality_end_time}`
      // }
      // if (aac01_start_time) {
      //   url += `&aac01_start_time=${aac01_start_time}`
      // }
      // if (aac01_end_time) {
      //   url += `&aac01_end_time=${aac01_end_time}`
      // }
      // if (aab01_start_time) {
      //   url += `&aab01_start_time=${aab01_start_time}`
      // }
      // if (aab01_end_time) {
      //   url += `&aab01_end_time=${aab01_end_time}`
      // }
      // if (AAA28) {
      //   url += `&AAA28=${AAA28}`
      // }
      url += `&quality_start_time=${quality_start_time}`
      url += `&quality_end_time=${quality_end_time}`
      url += `&aac01_start_time=${aac01_start_time}`
      url += `&aac01_end_time=${aac01_end_time}`
      url += `&aab01_start_time=${aab01_start_time}`
      url += `&aab01_end_time=${aab01_end_time}`
      url += `&AAA28=${AAA28}`
      this.$axios2.get(url).then(res => {
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
        quality_start_time: '',
        quality_end_time: '',
        aac01_start_time: '',
        aac01_end_time: '',
        aab01_start_time: '',
        aab01_end_time: '',
        department: '',
        content: '',
        AAA28: '',
        is_delete: 1
      };
    },
    handelExport() {

      const { page, limit } = this.paginationData;
      const params = {
        ...this.searchData,
        page,
        page_size: limit,
        export: 1
      }
    
      foreWarningExport(params).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `预警信息.csv`;
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
    },
  },
};
</script>

<style lang="scss" scoped>
</style>