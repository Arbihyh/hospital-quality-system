<!-- 上报历史 -->
 <template>
  <div class="page-container">
    <div class="search-box">
      <el-form :model="searchForm" label-width="80px" ref="searchFormRef">
        <el-row>
          <el-col :span="7">
            <el-form-item label="指标日期" prop="indexTime">
              <el-date-picker style="width: 100%" v-model="searchForm.indexTime" type="date" placeholder="选择日期"></el-date-picker>
            </el-form-item>
          </el-col>
          <el-col :span="7">
            <el-form-item label="上报人" prop="reporter">
              <el-select style="width: 100%" v-model="searchForm.reporter" clearable placeholder="请选择上报人">
                <el-option v-for="item in reporterOptions" :key="item.id" :label="item.name" :value="item.id"></el-option>
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="7">
            <el-form-item label="状态" prop="status">
              <el-select style="width: 100%" v-model="searchForm.status" clearable placeholder="请选择状态">
                <el-option v-for="item in statusOptions" :key="item.value" :label="item.label" :value="item.value"></el-option>
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row>
          <el-col :span="7">
            <DateRangePicker labelText="上报时间" v-model="searchForm" />
          </el-col>
          <el-col :span="5" offset="12">
            <el-form-item>
              <div style="width: 100%; display: flex; justify-content: flex-end">
                <el-button type="primary" @click="funQuery">查询</el-button>
                <el-button @click="reset">重置</el-button>
              </div>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
    </div>
    <div class="table-box">
      <div class="table-header">
        <CardTitle title="上报记录" />
        <el-button type="primary" plain icon="el-icon-upload2" size="small" @click="handleExport">导出</el-button>
      </div>
      <el-table v-loading="listLoading" :data="tableData" stripe border fit highlight-current-row empty-text="暂无数据" size="small">
        <el-table-column type="index" label="序号" align="center" header-align="center" width="60" />
        <el-table-column prop="name" label="机构名称" align="center" header-align="center" show-overflow-tooltip />
        <el-table-column prop="indexTime" label="指标日期" align="center" header-align="center" width="220" show-overflow-tooltip />
        <el-table-column prop="reportDesc" label="上报情况" align="center" header-align="center" width="180">
          <template>
            <span style="display: inline-flex; align-items: center; gap: 20px">
              <span>45/59</span>

              <span style="display: flex; align-items: center; justify-content: center">
                <svg t="1767366142174" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="6837" width="22" height="22">
                  <path
                    d="M774.56 64.22h-446.1c-80.47 0-145.7 65.23-145.7 145.7v65.01h-39.51c-21.82 0-39.51 17.69-39.51 39.51 0 21.82 17.69 39.51 39.51 39.51h39.51v316.08h-39.51c-21.82 0-39.51 17.69-39.51 39.51 0 21.82 17.69 39.51 39.51 39.51h39.51v65.01c0 80.47 65.23 145.71 145.7 145.71h446.1c80.47 0 145.7-65.23 145.7-145.71V209.93c0.01-80.47-65.23-145.71-145.7-145.71z m66.69 749.85c0 36.77-29.92 66.69-66.69 66.69h-446.1c-36.77 0-66.69-29.92-66.69-66.69v-65.01h39.51c21.82 0 39.51-17.69 39.51-39.51 0-21.82-17.69-39.51-39.51-39.51h-39.51V353.96h39.51c21.82 0 39.51-17.69 39.51-39.51 0-21.82-17.69-39.51-39.51-39.51h-39.51v-65.01c0-36.77 29.92-66.69 66.69-66.69h446.1c36.77 0 66.69 29.92 66.69 66.69v604.14z"
                    fill="#1656FD"
                    p-id="6838"
                  ></path>
                  <path
                    d="M713.1 273.42l-2.03-2.03c-7.43-7.44-17.18-11.15-26.92-11.15s-19.49 3.72-26.92 11.15l-66.2 66.2-66.2-66.2c-7.43-7.44-17.18-11.15-26.92-11.15s-19.49 3.72-26.92 11.15l-2.03 2.03c-14.87 14.87-14.87 38.97 0 53.84l66.2 66.2-66.2 66.2c-14.87 14.87-14.87 38.97 0 53.84l2.03 2.03c7.43 7.43 17.18 11.15 26.92 11.15s19.49-3.72 26.92-11.15l66.2-66.2 66.2 66.2c7.43 7.43 17.18 11.15 26.92 11.15s19.49-3.72 26.92-11.15l2.03-2.03c14.87-14.87 14.87-38.97 0-53.84l-66.2-66.2 66.2-66.2c14.87-14.86 14.87-38.97 0-53.84zM683.21 683.21H498.83c-21.82 0-39.51 17.69-39.51 39.51 0 21.82 17.69 39.51 39.51 39.51h184.38c21.82 0 39.51-17.69 39.51-39.51 0-21.82-17.69-39.51-39.51-39.51z"
                    fill="#1656FD"
                    p-id="6839"
                  ></path>
                </svg>
              </span>
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" align="center" header-align="center" width="180" show-overflow-tooltip>
          <template slot-scope="scope">
            <div class="status-box" :class="`${scope.row.status == '作废' ? 'status-icon-3' : scope.row.status == '正常' ? 'status-icon-2' : 'status-icon-1'}`">
              <span class="status-icon"></span>
              <span>{{ scope.row.status }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="reporter" label="上报人" align="center" header-align="center" width="180" show-overflow-tooltip />
        <el-table-column prop="updated_at" label="更新时间" align="center" header-align="center" width="220"></el-table-column>
      </el-table>
      <el-pagination
        @size-change="handleSizeChange"
        style="float: right; margin-top: 20px"
        @current-change="handleCurrentChange"
        :current-page="pageParams.page"
        :page-sizes="[10, 20, 30, 40]"
        :page-size="10"
        layout="prev, pager, next, sizes, total, jumper"
        :total="pageParams.total"
      ></el-pagination>
    </div>
  </div>
</template>

<script>
import DateRangePicker from '@/components/DateRangePicker';
import CardTitle from '@/components/CardTitle';

export default {
  name: 'History',
  components: { DateRangePicker, CardTitle },
  data() {
    return {
      searchForm: {
        indexTime: '',
        reporter: '',
        status: '',
        startTime: '',
        endTime: '',
      },
      statusOptions: [
        { label: '全部', value: '0' },
        { label: '正常', value: '1' },
        { label: '作废', value: '2' },
      ],
      reporterOptions: [],
      listLoading: false,
      tableData: [],
      pageParams: {
        page: 1,
        pageSize: 10,
        total: 0,
      },
    };
  },
  created() {
    this.getReporterList();
    this.funQuery();
  },
  methods: {
    async funQuery() {
      try {
        this.pageParams.page = 1;
        this.listLoading = true;
        const params = { ...this.searchForm, ...this.pageParams };
        const res = await this.$axios.post('/xxxx', { params });
        if (res.code === 200) {
          this.tableData = res.data.list || [];
          this.pageParams.total = res.data.total || 0;
        } else {
          this.tableData = [];
          this.pageParams.total = 0;
        }
      } catch (error) {
        console.error('上报历史查询报错：', error);
        this.tableData = [];
        this.pageParams.total = 0;
      } finally {
        this.listLoading = false;
      }
    },

    reset() {
      this.$refs.searchFormRef.resetFields();
      this.searchForm = {
        indexTime: '',
        reporter: '',
        status: '',
        startTime: '',
        endTime: '',
      };
      this.funQuery();
    },

    handleSizeChange(val) {
      this.pageParams.pageSize = val;
      this.funQuery();
    },
    handleCurrentChange(val) {
      this.pageParams.page = val;
      this.funQuery();
    },

    async getReporterList() {
      try {
        const res = await this.$axios.post('/omr_zk/docker_list');
        if (res.code === 200) {
          this.reporterOptions = res.data || [];
        }
      } catch (error) {
        // console.error('获取上报人列表失败：', error);
        this.reporterOptions = [];
      }
    },

    async handleExport() {
      try {
        this.$message.info('正在导出数据，请稍候');
        const params = { ...this.searchForm };
        const res = await this.$axios.get('/xxxx', {
          params,
          responseType: 'blob',
        });
        const blob = new Blob([res.data], { type: 'application/vnd.ms-excel' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `上报历史_${new Date().getTime()}.xlsx`;
        a.click();
        window.URL.revokeObjectURL(url);
        this.$message.success('导出成功');
      } catch (error) {
        console.error('上报历史导出报错：', error);
      }
    },
  },
};
</script>
<style lang="scss" scoped>
@import '~@/styles/common.scss';

.page-container {
  background: #f4f4f4;

  .table-box,
  .search-box {
    padding: 20px;
    background: #fff;
    border-radius: 5px;
    overflow-x: hidden;
  }

  .table-box {
    margin-top: 10px;

    .table-header {
      margin-bottom: 13px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
  }
}

.el-tag + .el-tag {
  margin-left: 10px;
}
.button-new-tag {
  margin-left: 10px;
  height: 32px;
  line-height: 30px;
  padding-top: 0;
  padding-bottom: 0;
}
.input-new-tag {
  width: 90px;
  margin-left: 10px;
  vertical-align: bottom;
}
</style>