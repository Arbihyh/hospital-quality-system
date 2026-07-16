<template>
  <div class="app-container">
    <SearchBoxVue :objects="objects" :departments="departments" @search="handleSearch" @reset="handleReset" />
    <TableBoxVue :loading="loading" :data="tableData" :objects="objects" :departments="departments" @refresh="handleRefresh" />
    <pagination
      :auto-scroll="false"
      :total="paginationData.total"
      :page="paginationData.page"
      :limit="paginationData.limit"
      @pagination="handlePagination"
    />
  </div>
</template>

<script>
import SearchBoxVue from './components/SearchBox.vue'
import TableBoxVue from './components/TableBox.vue'
import { get_select_object, get_select_department, get_rule_list } from '@/api/rule/config'

export default {
  components: {
    SearchBoxVue,
    TableBoxVue
  },
  data() {
    return {
      loading: false,
      ruleType: 1, // 默认为病历规则
      searchData: {
        changjing: [],
        department: [],
        object: '',
        type: '',
        is_not: '',
        description: '',
        score: undefined,
        error_level: '',
        status: ''
      },
      tableData: [],
      paginationData: {
        total: 0,
        page: 1,
        limit: 10
      },
      objects: [],
      departments: []
    }
  },
  created() {
    this.getObjectData()
    this.getDepartmentData()
    this.getList()
  },
  methods: {
    /**
     * 获取质控项目数据
     * @param {number|string} type - 规则类型，可选参数
     */
    getObjectData(type) {
      // 构建请求参数对象
      const params = {}

      // 如果传入了type参数，添加到请求中
      if (type !== undefined) {
        params.rule_type = type
      }

      // 发起API请求获取对象数据
      get_select_object(params).then(res => {
        const { p } = res
        this.objects = Array.isArray(p) ? p : []
        console.log('获取到质控项目数据:', this.objects)
      }).catch(error => {
        console.error('获取质控项目数据失败:', error)
      })
    },
    // 质控科室
    getDepartmentData() {
      get_select_department().then(res => {
        const { p } = res
        this.departments = Array.isArray(p) ? p : []
      })
    },
    handleRefresh(data) {
      if (data && data.rule_type !== undefined) {
        // 如果规则类型变化，更新ruleType并重新获取对象数据
        const oldRuleType = this.ruleType
        this.ruleType = data.rule_type

        // 如果规则类型发生变化，重新获取对象数据
        if (oldRuleType !== this.ruleType) {
          this.getObjectData(this.ruleType)
        }
      }

      // 获取列表数据
      this.getList()
    },
    async getList() {
      const { page, limit } = this.paginationData
      const params = {
        ...this.searchData,
        page,
        page_size: limit,
        rule_type: this.ruleType
      }
      this.loading = true
      get_rule_list(params).then(res => {
        const { p } = res
        this.paginationData.total = p.count
        this.tableData = p.list
      }).catch(error => {
        console.log(error)
      }).finally(() => {
        this.loading = false
      })
    },
    handlePagination(param) {
      this.paginationData.page = param.page
      this.paginationData.limit = param.limit
      this.getList()
    },
    handleSearch(params) {
      this.searchData = { ...params }
      this.paginationData.page = 1
      this.getList()
    },
    handleReset(params) {
      this.searchData = { ...params }
      this.paginationData.page = 1
      this.getList()
    },
    // 手动切换规则类型的方法(如果需要)
    changeRuleType(newType) {
      if (this.ruleType !== newType) {
        this.ruleType = newType
        // 切换规则类型后重新获取对象数据和列表
        this.getObjectData(this.ruleType)
        this.getList()
      }
    }
  }
}
</script>

<style lang="scss" scoped>

</style>
