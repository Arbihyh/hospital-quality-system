<template>
  <div class="box" :class="{'nocopy': $route.meta.nocopy}">
    <div class="box_wrapper">
      <div class="box_header">
        <Title :title="$route.query.name" style="margin-top: 8px;" />
      </div>
      <!-- 搜索栏 -->
      <div class="box_header">
        <el-form :inline="true" :model="searchData" class="demo-form-inline" style="float: left; ">
          <el-form-item label="状态" style="margin-bottom: 0">
            <el-select v-model="searchData.is_error" clearable placeholder="请选择">
              <el-option label="正确" :value="1"></el-option>
              <el-option label="错误" :value="0"></el-option>
            </el-select>
          </el-form-item>
          <el-form-item label="住院号码" style="margin-bottom: 0">
            <el-input v-model="searchData.AAA28" clearable placeholder="请输入"></el-input>
          </el-form-item>
          <el-form-item label="科室" style="margin-bottom: 0">
            <el-select v-model="searchData.AAC11N" clearable filterable placeholder="请选择">
              <el-option
                v-for="(item, index) in departmentList"
                :key="index"
                :label="item.name"
                :value="item.name">
              </el-option>
            </el-select>
          </el-form-item>
          <el-form-item style="margin-bottom: 0">
            <el-button type="primary" @click="onSearch">查询</el-button>
          </el-form-item>
        </el-form>
        <div class="btn-box">
          <el-button v-if="$route.query.ruleId == 33" @click="toChildrenCase">子指标</el-button>
          <el-button type="primary" icon="el-icon-download" class="export-btn">导出数据</el-button>
          <el-button @click="toBack">返回</el-button>
        </div>
      </div>
      <!-- 列表 -->
      <el-table :data="tableData" @sort-change="handleSortChange" style="width: 100%">
        <el-table-column type="index" label="序号" align="center" width="80">
          <template slot-scope="scope">
            <span>{{ scope.$index + 1 +  (paginationData.currentPage - 1) * paginationData.pageSize }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="" label="住院号码" width="90">
          <template slot-scope="scope">
            <span class="link" @click="toPage(scope.row)">{{ scope.row.AAA28 }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="AAA01" label="患者姓名" width="120"></el-table-column>
        <el-table-column prop="AAC11N" label="出院科室"></el-table-column>
        <el-table-column prop="AAC01" label="出院时间" sortable width="160"></el-table-column>
        <el-table-column prop="AAB01" label="入院时间" sortable width="160"></el-table-column>
        <el-table-column prop="" label="状态" width="70">
          <template slot-scope="scope">
            <el-tag v-if="scope.row.numerator" type="success">正确</el-tag>
            <el-tag v-else type="danger">错误</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="" label="描述" width="800">
          <template slot-scope="scope">
            <span>{{ scope.row.description }}</span>
          </template>
        </el-table-column>
      </el-table>
      <!-- 分页 -->
      <el-pagination
          v-if="tableData && tableData.length !== 0"
          @size-change="SizeChangeEvent"
          @current-change="pageHasChanged"
          :total="paginationData.total"
          background
          class="table-pagination"
          style="margin: 30px 0px; float: right;"
          :page-size="paginationData.pageSize"
          :current-page.sync="paginationData.currentPage"
          layout="total, sizes, prev, pager, next, jumper"
        ></el-pagination>
    </div>
  </div>
</template>

<script>
import Title from '@/components/Title';
export default {
  components: {
    Title,
  },
  data() {
    return {
      searchData: {
        is_error: '',
        AAA28: '',
        AAC11N: '',
        order: '',
        order_sort: ''
      },
      tableData: [],
      paginationData: {
        total: 0,
        currentPage: 1,
        pageSize: 10,
      },
      departmentList: []
    };
  },
  watch: {
    $route(to, from) {
      if (['/caseIndex', '/yypsIndex'].includes(from.path) && to.path === '/caseIndexList') {
        this.searchData.is_error = ''
        this.searchData.AAA28 = ''
        this.searchData.AAC11N = ''
        this.searchData.order = ''
        this.searchData.order_sort = ''
        this.tableData = []
        this.paginationData.currentPage = 1
        this.paginationData.pageSize = 10
        this.getList()
      }
    }
  },
  created() {
    this.getList()
    this.getDepartmentList()
  },
  methods: {
    handleSortChange(column) {
      const { prop, order } = column
      let str = ''
      if (order === 'descending') {
        str = 'desc'
      } else if (order === 'ascending') {
        str = 'asc'
      } else {
        str = null
      }
      
      this.searchData.order = prop
      this.searchData.order_sort = str
      this.tableData = []
      this.getList()
    },
    toChildrenCase() {
      this.$router.push({ path: '/caseIndex', query: { type: 'children' }})
    },
    getDepartmentList() {
      this.$axios.post("/get_department_list").then((res) => {
        // 不要全部选项
        this.departmentList = res.data
      });
    },
    // 获取指标数据
    getList() {
      const { time, type, ruleId, year } = this.$route.query
      const { currentPage, pageSize } = this.paginationData
      const { is_error, AAA28, AAC11N, order, order_sort } = this.searchData
      const params = {
        time,
        id: ruleId,
        data_type: type === 'numerator' ? 0 : 1,
        page: currentPage,
        page_size: pageSize,
        AAA28,
        AAC11N,
        year
      }
      if (order) {
        params.order = order
        params.order_sort = order_sort
      }
      if ([0, 1].includes(is_error)) {
        params.is_error = is_error
      }
      let url = '/get_zhibiao_list'
      
      if (this.$route.query.from === 'yypsIndex') {
        url = '/get_zhibiao_list_v2'
      }
      this.$axios2.post(url, params).then(res => {
        this.tableData = res.data.data
        this.paginationData.total = res.data.count
      })
    },
    //返回上一页
    toBack() {
      this.$router.back();
    },
    // 病案指标详情
    toPage(row) {
      this.storageSet('getData', row.MED_REC_ID);
      let path
      if (this.$route.path === '/embedIndex-caseIndexList') {
        path = '/embedIndex-caseViews'
      } else if (this.$route.path === '/reviewIndex-caseIndexList') {
        path = '/reviewIndex-caseViews?type_v=v2'
      } else {
        path = '/caseViews?type_v=v2'
      }
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
    }
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
.link{
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