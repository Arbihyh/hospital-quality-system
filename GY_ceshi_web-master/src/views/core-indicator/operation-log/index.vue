<!-- 运营日志 -->
<template>
  <div class="page-container">
    <div class="search-box">
      <el-form :model="searchForm" label-width="80px" ref="searchFormRef">
        <el-row>
          <el-col :span="7">
            <el-form-item label="账号" prop="name">
              <el-input style="width: 100%" placeholder="请输入账号" v-model="searchForm.name" clearable></el-input>
            </el-form-item>
          </el-col>
          <el-col :span="7">
            <el-form-item label="姓名" prop="realname">
              <el-input style="width: 100%" placeholder="请输入姓名" v-model="searchForm.realname" clearable></el-input>
            </el-form-item>
          </el-col>
          <el-col :span="7">
            <el-form-item label="登录IP" prop="loginip">
              <el-input style="width: 100%" placeholder="请输入登录IP" v-model="searchForm.loginip" clearable></el-input>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row>
          <el-col :span="8">
            <DateRangePicker labelText="查询时间" v-model="searchForm" />
          </el-col>
          <el-col :span="7">
            <el-form-item label="操作内容" prop="content">
              <el-input style="width: 100%" placeholder="请输入操作内容" v-model="searchForm.content" clearable></el-input>
            </el-form-item>
          </el-col>
          <el-col :span="5" offset="3">
            <el-form-item>
              <div style="width: 100%; display: flex; justify-content: flex-end; gap: 8px">
                <el-button type="primary" @click="funQuery" icon="el-icon-search">查询</el-button>
                <el-button @click="reset" icon="el-icon-refresh-left">重置</el-button>
              </div>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
    </div>
    <div class="table-box">
      <div class="table-header">
        <CardTitle title="查询结果" />
      </div>
      <!-- 表格区域 -->
      <el-table v-loading="listLoading" :data="tableData" stripe border fit highlight-current-row empty-text="暂无相关日志数据" size="small" :row-class-name="tableRowClassName">
        <el-table-column type="index" label="序号" align="center" header-align="center" width="60" />
        <el-table-column prop="name" label="账号" align="center" header-align="center" width="200">
          <template slot-scope="scope">
            {{ scope.row.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="realname" label="姓名" align="center" header-align="center" width="200">
          <template slot-scope="scope">
            {{ scope.row.realname || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="loginip" label="登录IP" align="center" header-align="center" width="200">
          <template slot-scope="scope">
            {{ scope.row.loginip || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="content" label="操作内容" align="center" header-align="center">
          <template slot-scope="scope">
            {{ scope.row.content || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="查询时间" align="center" header-align="center" width="200">
          <template slot-scope="scope">
            {{ scope.row.created_at || '-' }}
          </template>
        </el-table-column>
      </el-table>
      <el-pagination
        @size-change="handleSizeChange"
        style="float: right; margin-top: 20px"
        @current-change="handleCurrentChange"
        :current-page="pageParams.page"
        :page-sizes="[10, 20, 30, 40]"
        :page-size="pageParams.page_size"
        layout="prev, pager, next, sizes, total, jumper"
        :total="pageParams.total"
        background
      ></el-pagination>
    </div>
  </div>
</template>

<script>
import DateRangePicker from '@/components/DateRangePicker';
import CardTitle from '@/components/CardTitle';

export default {
  name: 'KnowKu',
  components: { DateRangePicker, CardTitle },
  data() {
    return {
      searchForm: {
        name: '',
        realname: '',
        loginip: '',
        content: '',
        startTime: '',
        endTime: '',
      },
      listLoading: false,
      tableData: [],
      pageParams: {
        page: 1,
        page_size: 10,
        total: 0,
      },
    };
  },
  created() {
    this.funQuery();
  },
  methods: {
    async funQuery() {
      try {
        this.listLoading = true;
        const params = {
          name: this.searchForm.name,
          realname: this.searchForm.realname,
          loginip: this.searchForm.loginip,
          content: this.searchForm.content,
          start_time: this.searchForm.startTime,
          end_time: this.searchForm.endTime,
          page: this.pageParams.page,
          page_size: this.pageParams.page_size,
        };
        const res = await this.$axios.post('/man_logs/list', { params });
        if (res?.code === 200) {
          this.tableData = res.data?.list || [];
          this.pageParams.total = res.data?.count || 0;
          if (this.pageParams.total === 0) this.tableData = [];
        } else {
          // this.$message.warning(res?.msg || '查询日志列表失败');
          this.tableData = [];
          this.pageParams.total = 0;
        }
      } catch (error) {
        // this.$message.error('网络异常，查询日志列表失败！');
        console.error('【日志查询报错】：', error);
        this.tableData = [];
      } finally {
        this.listLoading = false;
      }
    },

    reset() {
      if (this.$refs.searchFormRef) {
        this.$refs.searchFormRef.resetFields();
      }
      this.searchForm = {
        name: '',
        name: '',
        loginip: '',
        content: '',
        startTime: '',
        endTime: '',
      };
      this.pageParams.page = 1;
      this.pageParams.page_size = 10;
      this.funQuery();
    },

    handleSizeChange(val) {
      this.pageParams.page_size = val;
      this.pageParams.page = 1;
      this.funQuery();
    },

    handleCurrentChange(val) {
      this.pageParams.page = val;
      this.funQuery();
    },

    tableRowClassName({ row }) {
      if (row.name === '暂无数据') return 'empty-data-row';
    },
  },
};
</script>

<style lang="scss" scoped>
@import '~@/styles/common.scss';

.page-container {
  padding: 0 18px;
  background: #f4f4f4;
  min-height: calc(100vh - 120px);

  .search-box {
    padding: 20px;
    background: #fff;
    border-radius: 6px;
    overflow-x: hidden;
    margin-bottom: 12px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
  }

  .table-box {
    padding: 20px;
    background: #fff;
    border-radius: 6px;
    overflow-x: auto;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);

    .table-header {
      margin-bottom: 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
  }
}
</style>