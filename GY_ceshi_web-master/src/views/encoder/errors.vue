<template>
  <div class="bg-box">
    <div class="bg-card">
      <SearchBoxVue :data="formInline" @search="handleSearch" @reset="handleReset" />
      <div style="margin-top: -22px;">
        <el-divider></el-divider>
        <TableBoxVue :data="tableData" :paginationData="paginationData"  @export="handleExport" />
        <Pagination :page="paginationData.page" :limit="paginationData.page_size" :total="paginationData.total" @pagination="handlePagination" />
      </div>
    </div>
  </div>
</template>

<script>
import SearchBoxVue from './components/errors/SearchBox.vue'
import TableBoxVue from './components/errors/TableBox.vue'
import Pagination from '@/components/Pagination'
import { encoderErrorExport } from '@/api/excel'

export default {
  components: {
    SearchBoxVue,
    TableBoxVue,
    Pagination
  },
  data() {
    return {
      formInline: {
        AAA28: '',
        AAC11C: '',
        AAC01: [],
        AEE04_CODE: '',
        AEE08_CODE: '',
        ICD9_ID1: '',
        ICD9_NAME: '',
        ICD10_ID1: '',
        ICD10_NAME: ''
      },
      tableData: [],
      paginationData: {
        page: 1,
        page_size: 10,
        total: 0
      }
    }
  },
  created() {
    const { start_time, end_time, dep_id,YQ_CODES,KS_IDS,BQ_IDS ,zy_status,bm_status,AAA28} = this.$route.query
    this.$set(this.formInline, 'AAC01', [start_time, end_time])
    //this.$set(this.formInline, 'AAC11C', dep_id)
    this.$set(this.formInline, 'YQ_CODES', YQ_CODES)
    this.$set(this.formInline, 'KS_IDS', KS_IDS)
    this.$set(this.formInline, 'BQ_IDS', BQ_IDS)
    this.$set(this.formInline, 'zy_status', zy_status)
    this.$set(this.formInline, 'bm_status', bm_status)
    this.$set(this.formInline, 'AAA28', AAA28)
    console.log(this.formInline);
    this.getList()
  },
  methods: {
    getList() {
      const { AAC01 } = this.formInline
      const { page, page_size } = this.paginationData
      const params = {
        hospital_name: '',
        bl_type: 2,
        is_export: 0,
        rule_id: this.$route.query.rule_id,
        is_qx: this.$route.query.is_qx,
        page,
        page_size,
        ...this.formInline
      }
      if (AAC01 && AAC01.length) {
        params.start_time = AAC01[0]
        params.end_time = AAC01[1]
      }
      params.YQ_CODE = this.formInline.YQ_CODE;
      params.KS_IDS = this.formInline.KS_IDS;
      params.BQ_IDS = this.formInline.BQ_IDS;
      params.zy_status = this.formInline.zy_status;
      params.bm_status = this.formInline.bm_status;
      this.$axios.post('/bmy/bmyQualityList', params).then(res => {
        this.tableData = res.data.data
        this.paginationData.total = res.data.count
      });
    },
    handleSearch() {
      this.paginationData.page = 1
      this.getList()
    },
    handleReset() {
      this.formInline = {
        AAA28: '',
        AAC11C: '',
        AAC01: [],
        AEE04_CODE: '',
        AEE08_CODE: '',
        ICD9_ID1: '',
        ICD9_NAME: '',
        ICD10_ID1: '',
        ICD10_NAME: ''
      }
    },
    handlePagination(val) {
      const { page, limit } = val
      this.paginationData.page = page
      this.paginationData.page_size = limit
      this.getList()
    },
    handleExport() {
      const {
        AAC01
      } = this.formInline
      const params = {
        hospital_name: '',
        bl_type: 2,
        is_export: 1,
        rule_id: this.$route.query.rule_id,
        is_qx: this.$route.query.is_qx,
        ...this.formInline
      }
      if (AAC01 && AAC01.length) {
        params.start_time = AAC01[0]
        params.end_time = AAC01[1]
      }
      encoderErrorExport(params).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `首页质控(编码员)-缺陷列表.csv`;
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
