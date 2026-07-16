<template>
  <div class="bg-box">
    <div class="bg-card">
      <SearchBoxVue :data="formInline" @search="handleSearch" @reset="handleReset" />
      <div style="margin-top: -22px;">
        <el-divider></el-divider>
        <TableBoxVue :data="tableData" :paginationData="paginationData" />
        <Pagination :page="paginationData.page" :limit="paginationData.page_size" :total="paginationData.total" @pagination="handlePagination" />
      </div>
    </div>
  </div>
</template>

<script>
import SearchBoxVue from './components/cost/SearchBox.vue'
import TableBoxVue from './components/cost/TableBox.vue'
import Pagination from '@/components/Pagination'

export default {
  components: {
    SearchBoxVue,
    TableBoxVue,
    Pagination
  },
  data() {
    return {
      formInline: {
        ZYH: '',
        FYMC: '',
        time: [],
        FYKS: ''
      },
      tableData: [
        // {
        //   "FYXH": 56893,
        //   "ZJE": "4.000",
        //   "FYDJ": "4.000",
        //   "FYMC": "尿液分析（11项加收）/次",
        //   "JFRQ": "2022-12-30 07:51:30",
        //   "FYKS": "2021",
        //   "pre_FYMC": "尿液分析（11项加收）",
        //   "FYSL": 1,
        //   "SYFYGB": 0,
        //   "ZFJE": "4.000",
        //   "FYGB": 0,
        //   "MED_REC_ID": "767016"
        // },
        // {
        //   "FYXH": 40819,
        //   "ZJE": "85.000",
        //   "FYDJ": "85.000",
        //   "FYMC": "丙型肝炎抗体测定（Anti-HCV）发光法",
        //   "JFRQ": "2022-12-30 07:53:49",
        //   "FYKS": "2021",
        //   "pre_FYMC": "丙型肝炎抗体测定（Anti-HCV）发光法",
        //   "FYSL": 1,
        //   "SYFYGB": 0,
        //   "ZFJE": "85.000",
        //   "FYGB": 0,
        //   "MED_REC_ID": "767016"
        // }
      ],
      paginationData: {
        page: 1,
        page_size: 10,
        total: 0
      }
    }
  },
  created() {
    const { zyh } = this.$route.query
    this.$set(this.formInline, 'ZYH', zyh)
    this.getList()
  },
  methods: {
    getList() {
      if (!this.formInline.ZYH) {
        this.$message.error('请输入住院号码')
        return
      }
      const {
        time
      } = this.formInline
      const {
        page,
        page_size
      } = this.paginationData
      const params = {
        page,
        page_size,
        ...this.formInline
      }
      if (time && time.length) {
        params.JFRQ_START = time[0]
        params.JFRQ_END = time[1]
      }
      this.$axios.post('/bmy/getFeeDetailed', params).then(res => {
        this.tableData = res.data.list
        this.paginationData.total = res.data.count
      });
    },
    handleSearch() {
      this.paginationData.page = 1
      this.getList()
    },
    handleReset() {
      this.formInline = {
        ZYH: '',
        FYMC: '',
        time: [],
        FYKS: ''
      }
    },
    handlePagination(val) {
      const { page, limit } = val
      this.paginationData.page = page
      this.paginationData.page_size = limit
      this.getList()
    }
  }
}
</script>

<style>

</style>