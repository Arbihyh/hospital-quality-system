<template>
  <div>
    <SearchBoxVue
      :data="searchData"
      :codes="tableShowCode"
      @search="handleSearch"
      @reset="handleReset"
      @codesChange="handleCodesChange"
      @create="handleCreate"
    />
    <TableBoxVue
      :loading="loading"
      :data="tableData"
      :codes="tableShowCode"
      style="margin-top: -40px"
      @export="handleExport"
      @edit="handleEdit"
      @refresh="handleRefresh"
    />
    <pagination
      :auto-scroll="false"
      :total="paginationData.total"
      :page="paginationData.page"
      :limit="paginationData.page_size"
      @pagination="handlePagination"
    />
    <!-- 新建、编辑 -->
    <CreateDaialogVue
      v-if="createData.bSwitch"
      :data="createData"
      @refresh="handleRefresh"
    />
  </div>
</template>

<script>
import SearchBoxVue from './components/SearchBox.vue'
import TableBoxVue from './components/TableBox.vue'
import { illnessExport } from '@/api/excel'
import { illnessList } from '@/api/knowledge'
import CreateDaialogVue from './components/CreateDaialog.vue'
import { dateFormat } from '@/filters/index'

export default {
  components: {
    SearchBoxVue,
    TableBoxVue,
    CreateDaialogVue
  },
  data() {
    return {
      loading: false,
      searchData: {
        FLAG: '',
        KSMC: '',
        JBMC: '',
        BM: '',
        JBBM: '',
        JBZD: '',
        ZZ: '',
        TZ: '',
        YP: '',
        ZL: '',
        JC: '',
        JJ: '',
        BFZ: '',
        CKWX: '',
        createStartTime: '',
        createEndTime: '',
        updateStartTime: '',
        updateEndTime: ''
      },
      tableData: [],
      tableShowCode: [
        'FLAG',
        'KSMC',
        'JBMC',
        'BM',
        'JBBM',
        'JBZD',
        'ZZ',
        'TZ',
        'YP',
        'ZL',
        'JC',
        'JJ',
        'BFZ',
        'CKWX',
        'created_at',
        'updated_at'
      ],
      paginationData: {
        total: 0,
        page: 1,
        page_size: 10
      },
      createData: {
        bSwitch: false,
        id: ''
      }
    }
  },
  created() {
    const codes = localStorage.getItem('illness_talbe_codes') ? localStorage.getItem('illness_talbe_codes').split(',') : []
    if (codes.length) {
      this.$set(this, 'tableShowCode', codes)
    }
    this.getList()
  },
  methods: {
    // 展示字段变化
    handleCodesChange(val) {
      localStorage.setItem('illness_talbe_codes', val)
      this.$set(this, 'tableShowCode', val)
    },
    // 获取列表数据
    getList() {
      const {
        FLAG,
        KSMC,
        JBMC,
        BM,
        JBBM,
        JBZD,
        ZZ,
        TZ,
        YP,
        ZL,
        JC,
        JJ,
        BFZ,
        CKWX,
        createStartTime,
        createEndTime,
        updateStartTime,
        updateEndTime
      } = this.searchData
      const { page, page_size } = this.paginationData
      const params = {
        FLAG,
        KSMC,
        JBMC,
        BM,
        JBBM,
        JBZD,
        ZZ,
        TZ,
        YP,
        ZL,
        JC,
        JJ,
        BFZ,
        CKWX,
        page,
        page_size
      }
      params.createStartTime = createStartTime
        ? dateFormat(createStartTime, 'YYYYMMDD')
        : ''
      params.createEndTime = createEndTime
        ? dateFormat(createEndTime, 'YYYYMMDD')
        : ''
      params.updateStartTime = updateStartTime
        ? dateFormat(updateStartTime, 'YYYYMMDD')
        : ''
      params.updateEndTime = updateEndTime
        ? dateFormat(updateEndTime, 'YYYYMMDD')
        : ''
      this.loading = true
      illnessList(params)
        .then((res) => {
          const { p } = res
          this.paginationData.total = p.count
          this.tableData = p.list
        })
        .catch((error) => {
          console.log(error)
        })
        .finally(() => {
          this.loading = false
        })
    },
    // 分页
    handlePagination(param) {
      this.paginationData.page = param.page
      this.paginationData.limit = param.limit
      this.getList()
    },
    // 搜索
    handleSearch() {
      this.paginationData.page = 1
      this.getList()
    },
    // 重置
    handleReset() {
      this.searchData = {
        FLAG: '',
        KSMC: '',
        JBMC: '',
        BM: '',
        JBBM: '',
        JBZD: '',
        ZZ: '',
        TZ: '',
        YP: '',
        ZL: '',
        JC: '',
        JJ: '',
        BFZ: '',
        CKWX: '',
        createStartTime: '',
        createEndTime: '',
        updateStartTime: '',
        updateEndTime: ''
      }
    },
    // 导出
    handleExport() {
      const {
        FLAG,
        KSMC,
        JBMC,
        BM,
        JBBM,
        JBZD,
        ZZ,
        TZ,
        YP,
        ZL,
        JC,
        JJ,
        BFZ,
        CKWX,
        createStartTime,
        createEndTime,
        updateStartTime,
        updateEndTime
      } = this.searchData
      const params = {
        FLAG,
        KSMC,
        JBMC,
        BM,
        JBBM,
        JBZD,
        ZZ,
        TZ,
        YP,
        ZL,
        JC,
        JJ,
        BFZ,
        CKWX
      }
      params.createStartTime = createStartTime
        ? dateFormat(createStartTime, 'YYYYMMDD')
        : ''
      params.createEndTime = createEndTime
        ? dateFormat(createEndTime, 'YYYYMMDD')
        : ''
      params.updateStartTime = updateStartTime
        ? dateFormat(updateStartTime, 'YYYYMMDD')
        : ''
      params.updateEndTime = updateEndTime
        ? dateFormat(updateEndTime, 'YYYYMMDD')
        : ''
      illnessExport(params).then((res) => {
        const content = res.data // 后台返回二进制数据
        const blob = new Blob([content])
        const fileName = `疾病库.csv`
        if ('download' in document.createElement('a')) {
          // 非IE下载
          const elink = document.createElement('a')
          elink.download = fileName
          elink.style.display = 'none'
          elink.href = URL.createObjectURL(blob)
          document.body.appendChild(elink)
          elink.click()
          URL.revokeObjectURL(elink.href) // 释放URL 对象
          document.body.removeChild(elink)
        } else {
          // IE10+下载
          navigator.msSaveBlob(blob, fileName)
        }
      })
    },
    // 新增
    handleCreate() {
      this.createData.id = ''
      this.createData.bSwitch = true
    },
    handleEdit(row) {
      this.createData.id = row.id
      this.createData.bSwitch = true
    },
    // 刷新
    handleRefresh() {
      this.getList()
    }
  }
}
</script>

<style lang="scss" scoped>
</style>
