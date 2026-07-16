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
          <el-form-item label="">
            <el-date-picker
              v-model="queryParams.start_time"
              type="date"
              :picker-options="pickerOptions1"
              placeholder="开始日期"
              value-format="yyyyMMdd"
            />
          </el-form-item>
          <el-form-item label="">
            <el-date-picker
              v-model="queryParams.end_time"
              type="date"
              :picker-options="pickerOptions2"
              placeholder="结束日期"
              value-format="yyyyMMdd"
            />
          </el-form-item>
          <el-form-item label="" prop="name">
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
            <el-button size="small" type="primary" icon="el-icon-search" @click="handleQuery">搜索</el-button>
            <el-button size="small" icon="el-icon-refresh" @click="handleResetQuery">重置</el-button>
          </el-form-item>
        </el-form>
      </el-col>
    </el-row>
    <el-table v-loading="listLoading" :data="pageList">
      <el-table-column type="index" label="序号" width="80" />
      <el-table-column prop="admin_name" label="账号名" width="120" />
      <el-table-column prop="title" label="标题" width="200" />
      <el-table-column prop="path" label="url" width="200" />
      <el-table-column prop="content" label="请求参数" width="210">
        <template slot-scope="scope">
          <el-popover trigger="click" placement="top" width="400">
            <div style="max-height: 400px;overflow-y: auto;">{{ scope.row.content }}</div>
            <div slot="reference" class="text-more-box">
              {{ scope.row.content }}
            </div>
          </el-popover>
        </template>
      </el-table-column>
      <el-table-column prop="method" label="请求方式" width="90" />
      <el-table-column prop="ip" label="IP" width="150" />
      <el-table-column prop="user_agent" label="Browser">
        <template slot-scope="scope">
          <el-popover trigger="click" placement="top" width="400">
            <div style="max-height: 400px;overflow-y: auto;">{{ scope.row.user_agent }}</div>
            <div slot="reference" class="text-more-box">
              {{ scope.row.user_agent }}
            </div>
          </el-popover>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" label="记录时间" width="150" />
    </el-table>
    <pagination
      :auto-scroll="false"
      :total="listCount"
      :page="queryParams.page"
      :limit="queryParams.limit"
      @pagination="handlePagination"
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
        page: 1,
        limit: 10,
        name: undefined,
        start_time: '',
        end_time: ''
      },
      pickerOptions1: {
        disabledDate: (time) => {
          if (this.queryParams.end_time) {
            return time.getTime() > new Date(this.queryParams.end_time).getTime()
          } else {
            return time.getTime() > Date.now()
          }
        }
      },
      pickerOptions2: {
        disabledDate: (time) => {
          if (this.queryParams.start_time) {
            return time.getTime() < new Date(this.queryParams.start_time).getTime()
          } else {
            return time.getTime() > Date.now()
          }
        }
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
      this.getList()
    },
    handleResetQuery() {
      this.queryParams = {
        page: 1,
        limit: 10,
        name: undefined
      }
      this.getList()
    },
    /** 搜索按钮操作 */
    handleQuery() {
      this.queryParams.page = 1
      this.getList()
    },
    handlePagination(param) {
      this.queryParams.page = param.page
      this.queryParams.limit = param.limit
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
