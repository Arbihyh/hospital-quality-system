<template>
  <div class="box">
    <div class="box_wrapper">
      <div class="box_header">
        <Title :title="$route.query.name" style="margin-top: 8px" />
      </div>
      <!-- 搜索栏 -->
      <div class="box_header">
        <el-form :inline="true" :model="searchData" class="demo-form-inline" style="float: left">
          <el-form-item label="出院时间" style="margin-bottom: 0">
            <el-date-picker
              v-model="searchData.start_time"
              type="date"
              format="yyyy年MM月dd日"
              value-format="yyyyMMdd"
              placeholder="出院开始时间"
              style="margin-right: 10px"
            />
            <el-date-picker v-model="searchData.end_time" type="date" format="yyyy年MM月dd日" value-format="yyyyMMdd" placeholder="出院结束时间" />
          </el-form-item>
          <el-form-item label="科室" style="margin-bottom: 0">
            <el-select v-model="searchData.AAC11N" clearable filterable placeholder="请选择">
              <el-option v-for="(item, index) in departmentList" :key="index" :label="item.name" :value="item.name"></el-option>
            </el-select>
          </el-form-item>
          <el-form-item label="住院号码" style="margin-bottom: 0">
            <el-input v-model="searchData.AAA28" clearable placeholder="请输入"></el-input>
          </el-form-item>
          <el-form-item style="margin-bottom: 0">
            <el-button type="primary" @click="onSearch">查询</el-button>
          </el-form-item>
        </el-form>
      </div>
      <!-- 列表 -->
      <div>
        <el-button @click="onExport" type="primary" style="float: right; margin-bottom: 16px" class="export-btn">导出数据</el-button>
        <el-table :data="tableData" style="width: 100%">
          <el-table-column prop="" label="住院号码" width="120" fixed="left">
            <template slot-scope="scope">
              <span class="link" @click="toPage(scope.row)">{{ scope.row.AAA28 }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="AAC01" label="出院时间" width="160"></el-table-column>
          <el-table-column prop="AAC11N" label="出院科室" width="200" show-overflow-tooltip></el-table-column>
          <el-table-column prop="ZYZD_NAME" label="主要诊断名称" width="200" show-overflow-tooltip></el-table-column>
          <el-table-column prop="ZYZD_ID" label="主要诊断编码" width="200" show-overflow-tooltip></el-table-column>
          <el-table-column prop="ZYZD_RYQK" label="主要诊断入院病情" width="200" show-overflow-tooltip></el-table-column>
          <el-table-column prop="QTZD_NAME1" label="其他诊断名称1" width="200" show-overflow-tooltip></el-table-column>
          <el-table-column prop="QTZD_ID1" label="其他诊断编码1" width="200" show-overflow-tooltip></el-table-column>
          <el-table-column prop="QTZDRYQK1" label="其他诊断入院病情1" width="200" show-overflow-tooltip></el-table-column>
          <el-table-column prop="QTZD_NAME2" label="其他诊断名称2" width="200" show-overflow-tooltip></el-table-column>
          <el-table-column prop="QTZD_ID2" label="其他诊断编码2" width="200" show-overflow-tooltip></el-table-column>
          <el-table-column prop="QTZDRYQK2" label="其他诊断入院病情2" width="200" show-overflow-tooltip></el-table-column>
          <el-table-column prop="QTZD_NAME3" label="其他诊断名称3" width="200" show-overflow-tooltip></el-table-column>
          <el-table-column prop="QTZD_ID3" label="其他诊断编码3" width="200" show-overflow-tooltip></el-table-column>
          <el-table-column prop="QTZDRYQK3" label="其他诊断入院病情3" width="200" show-overflow-tooltip></el-table-column>
        </el-table>
        <!-- 分页 -->
        <el-pagination
          v-if="tableData && tableData.length !== 0"
          @size-change="SizeChangeEvent"
          @current-change="pageHasChanged"
          :total="paginationData.total"
          background
          class="table-pagination"
          style="margin: 30px 0px; float: right"
          :page-size="paginationData.pageSize"
          :current-page.sync="paginationData.currentPage"
          layout="total, sizes, prev, pager, next, jumper"
        ></el-pagination>
      </div>
    </div>
  </div>
</template>

<script>
import Title from '@/components/Title';
import { getDaysInMonth } from '@/utils';
import { otherStatisticsDataExport } from '@/api/excel'

export default {
  components: {
    Title,
  },
  data() {
    return {
      searchData: {
        start_time: '',
        end_time: '',
        AAA28: '',
        AAC11N: '',
      },
      tableData: [],
      paginationData: {
        total: 0,
        currentPage: 1,
        pageSize: 10,
      },
      departmentList: [],
    };
  },
  watch: {},
  created() {
    const { time, year } = this.$route.query
    if (time === '全年') {
      this.searchData.start_time = `${year}0101`;
      this.searchData.end_time = `${year}1231`;
    } else {
      const aTimes = time.split('-');
      const days = getDaysInMonth(aTimes[0], aTimes[1]);
      this.searchData.start_time = `${aTimes[0]}${aTimes[1]}01`;
      this.searchData.end_time = `${aTimes[0]}${aTimes[1]}${days}`;
    }
    this.getList();
    this.getDepartmentList();
  },
  methods: {
    getDepartmentList() {
      this.$axios.post('/get_department_list').then(res => {
        // 不要全部选项
        this.departmentList = res.data;
      });
    },
    // 获取指标数据
    getList() {
      const { ruleId } = this.$route.query;
      const { currentPage, pageSize } = this.paginationData;
      const { start_time, end_time, AAA28, AAC11N } = this.searchData;
      const params = {
        start_time,
        end_time,
        field: ruleId,
        page: currentPage,
        page_size: pageSize,
        AAA28,
        AAC11N,
        is_export: 0,
      };
      this.$axios.post(`/ssbfz/getBfzList`, params).then(res => {
        this.tableData = res.data.list;
        this.paginationData.total = res.data.count;
      });
    },
    // 病案指标详情
    toPage(row) {
      this.storageSet('getData', row.ZYH);
      const path = '/caseViews'
      this.goto(path);
    },
    // 分页
    SizeChangeEvent(val) {
      this.paginationData.pageSize = val;
      this.getList();
    },
    pageHasChanged() {
      this.getList();
    },
    // 搜索
    onSearch() {
      this.paginationData.currentPage = 1;
      this.getList();
    },
    // 导出
    onExport() {
      const { ruleId } = this.$route.query;
      const { start_time, end_time, AAA28, AAC11N } = this.searchData;
      const params = {
        start_time,
        end_time,
        field: ruleId,
        AAA28,
        AAC11N,
        is_export: 1,
      };
      otherStatisticsDataExport(params).then(res => {
        const content = res.data // 后台返回二进制数据
        const blob = new Blob([content])
        const fileName = `${this.$route.query.name}.csv`
        if ('download' in document.createElement('a')) { // 非IE下载
          const elink = document.createElement('a')
          elink.download = fileName
          elink.style.display = 'none'
          elink.href = URL.createObjectURL(blob)
          document.body.appendChild(elink)
          elink.click()
          URL.revokeObjectURL(elink.href) // 释放URL 对象
          document.body.removeChild(elink)
        } else { // IE10+下载
          navigator.msSaveBlob(blob, fileName)
        }
      })
    },
  },
};
</script>

<style lang="scss" scoped>
.box {
  padding: 0 16px 16px 16px;
  .box_wrapper {
    padding: 16px;
    background: #fff;
    border-radius: 5px;
    height: calc(100vh - 82px);
    .box_header {
      overflow: hidden;
      margin-bottom: 16px;
      .btn-box {
        float: right;
      }
    }
  }
}
.link {
  font-weight: 600;
  color: #409eff;
  cursor: pointer;
}
::v-deep.el-table .el-table__header tr th {
  background: #f1f6ff;
  color: #13171e;
  border-bottom: 0px;
}
::v-deep.el-table .el-table__row td {
  color: #7e8bab;
  border-bottom: 1px solid #f4f4f4;
}
::v-deep.el-table .el-table__header tr th:first-child {
  border-radius: 5px 0px 0px 5px;
}
::v-deep.el-table .el-table__header tr th:nth-child(3) {
  border-radius: 0px 5px 5px 0px;
}
</style>