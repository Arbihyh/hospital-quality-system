<template>
  <div class="app-container">
    <el-row :gutter="10" class="mb8">
      <el-col :span="13" style="margin-bottom: -15px;">
        <el-col :span="1.5">
          <el-tooltip class="item" effect="dark" content="刷新" placement="top">
            <el-button size="small" icon="el-icon-refresh" @click="handleRefresh" />
          </el-tooltip>
        </el-col>
      </el-col>
      <el-col :span="11" style="margin-bottom: -15px;">
        <el-form
          ref="queryForm"
          :model="queryParams"
          size="small"
          :inline="true"
          label-width="68px"
          style="text-align:right;"
        >
          <el-form-item label="账号名称" prop="operName">
            <el-input
              v-model="queryParams.name"
              size="small"
              placeholder="请输入账号名称"
              clearable
              style="width: 240px;"
              @keyup.enter.native="handleQuery"
            />
          </el-form-item>
          <el-form-item style="margin-right:0">
            <!--            <el-tooltip class="item" effect="dark" :content="showSearch ? '隐藏搜索' : '更多搜索'" placement="top">-->
            <!--              <el-button size="small" icon="el-icon-search" @click="toggleSearch()" />-->
            <!--            </el-tooltip>-->
            <el-button size="small" type="primary" icon="el-icon-search" @click="handleQuery">搜索</el-button>
            <el-button size="small" icon="el-icon-refresh" @click="handleResetQuery">重置</el-button>
          </el-form-item>
        </el-form>
      </el-col>
    </el-row>
    <el-table v-loading="listLoading" :data="pageList">
      <el-table-column prop="id" label="ID" />
      <el-table-column prop="admin_name" label="账号名" />
      <el-table-column prop="desc" label="描述" />
      <el-table-column prop="path" label="url" />
      <el-table-column prop="method" label="请求方式" />
      <el-table-column prop="ip" label="IP" />
      <el-table-column prop="user_agent" label="Browser" />
      <el-table-column prop="created_at" label="创建时间" />
    </el-table>
    <pagination
      :total="listCount"
      :page.sync="queryParams.pageNum"
      :limit.sync="queryParams.pageSize"
      @pagination="getList"
    />
    <!--    <el-pagination-->
    <!--      background-->
    <!--      :current-page.sync="form.page"-->
    <!--      :page-size="form.length"-->
    <!--      layout="total, prev, pager, next"-->
    <!--      :total="count"-->
    <!--      @current-change="list"-->
    <!--    />-->
  </div>
</template>

<script>
import { adminLogList } from '@/api/admin'

export default {
  data() {
    return {
      listCount: 0,
      pageList: [],
      listLoading: true,
      // 显示搜索条件
      showSearch: false,
      search: true,
      // 查询参数
      queryParams: {
        pageNum: 1,
        pageSize: 10,
        name: undefined
      }
    }
  },
  created() {
    this.getList()
  },
  methods: {
    // 搜索
    toggleSearch() {
      this.showSearch = !this.showSearch
    },
    handleRefresh() {
      this.handleQuery()
    },
    handleResetQuery() {
      this.queryParams = {
        pageNum: 1,
        pageSize: 10,
        name: undefined
      }
      this.getList()
    },
    /** 搜索按钮操作 */
    handleQuery() {
      this.queryParams.pageNum = 1
      this.getList()
    },
    getList() {
      this.listLoading = true
      adminLogList(this.queryParams).then(res => {
        this.pageList = res.p.list
        this.listCount = res.p.count
        this.listLoading = false
      }).catch(error => {
        console.log(error)
      })
    }
  }
}
</script>

<style scoped>

</style>
