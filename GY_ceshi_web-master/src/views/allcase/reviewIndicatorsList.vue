<template>
  <!-- 脑梗心梗列表 -->
  <div class="box">
    <div class="box_wrapper">
      <div class="box_header">
        <Title :title="'指标列表'" style="margin-top: 8px" />
      </div>
      <!-- 搜索栏 -->
      <div class="box_header">
        <el-form :model="searchData" class="demo-form-inline">
          <el-row>
            <el-col span="6">
              <el-form-item label="状态" label-width="80px">
                <el-select v-model="searchData.is_error" clearable placeholder="请选择" style="width: 100%">
                  <el-option label="正确" :value="1"></el-option>
                  <el-option label="错误" :value="0"></el-option>
                </el-select>
              </el-form-item>
            </el-col>

            <el-col span="6">
              <el-form-item label="住院号码" label-width="80px">
                <el-input v-model="searchData.AAA28" clearable placeholder="请输入" style="width: 100%"></el-input>
              </el-form-item>
            </el-col>

            <el-col span="6">
              <el-form-item label="出院科室" label-width="80px">
                <el-select v-model="searchData.AAC11N" clearable filterable placeholder="请选择" style="width: 100%">
                  <el-option v-for="(item, index) in departmentList" :key="index" :label="item.name" :value="item.name"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row>
            <el-col span="8">
              <el-form-item label-width="80px" label="时间" prop="startTime">
                <div style="width: 94%; display: flex; gap: 5px">
                  <el-form-item prop="startTime">
                    <el-date-picker
                      v-model="timeData.startTime"
                      type="date"
                      style="width: 100%"
                      format="yyyy 年 MM 月 dd 日"
                      value-format="yyyy-MM-dd"
                      placeholder="开始日期"
                    ></el-date-picker>
                  </el-form-item>
                  <el-form-item prop="endTime">
                    <el-date-picker
                      v-model="timeData.endTime"
                      type="date"
                      style="width: 100%"
                      format="yyyy 年 MM 月 dd 日"
                      value-format="yyyy-MM-dd"
                      placeholder="结束日期"
                    ></el-date-picker>
                  </el-form-item>
                </div>
              </el-form-item>
            </el-col>

            <el-col :span="10">
              <el-form-item>
                <div style="margin-left: 350px; width: 94%; display: flex; justify-content: space-between">
                  <div>
                    <el-button class="btn1" type="primary" @click="onSearch">查询</el-button>
                    <el-button @click="reset">重置</el-button>
                    <el-button type="primary" v-if="$route.query.ruleId == 33" @click="toChildrenCase">子指标</el-button>
                    <el-button type="primary" icon="el-icon-download" class="export-btn" @click="onExport">导出数据</el-button>
                  </div>
                  <el-button @click="toBack" style="float: right; margin-right: 20px">返回</el-button>
                </div>
              </el-form-item>
            </el-col>
          </el-row>

          <!-- <el-form-item style="margin-bottom: 0">
            <el-button type="primary" @click="onSearch">查询</el-button>
          </el-form-item> -->
        </el-form>
        <!-- <div class="btn-box">
          <el-button v-if="$route.query.ruleId == 33" @click="toChildrenCase">子指标</el-button>
          <el-button type="primary" @click="onExport" icon="el-icon-download" class="export-btn">导出数据</el-button>
          <el-button @click="toBack">返回</el-button>
        </div> -->
      </div>
      <!-- 列表 -->
      <!-- 指标三，发病4.5小时内脑梗死患者静脉溶栓率 -->
      <el-table v-if="$route.query.ruleId == 62" :data="tableData" @sort-change="handleSortChange" style="width: 100%">
        <el-table-column type="index" label="序号" align="center" width="80">
          <template slot-scope="scope">
            <span>{{ scope.$index + 1 + (paginationData.currentPage - 1) * paginationData.pageSize }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="" label="住院号码" width="160">
          <template slot-scope="scope">
            <span class="link" @click="toPage(scope.row)">{{ scope.row.AAA28 }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="AAA01" label="患者姓名" width="160"></el-table-column>
        <el-table-column prop="zyzdmc" label="主要诊断名称" width="160"></el-table-column>
        <el-table-column prop="zyzbbh" label="主要诊断编号" width="160"></el-table-column>
        <el-table-column prop="" label="溶栓时间" width="160">
          <template slot-scope="scope">
            <span v-if="scope.row.rssj">{{ scope.row.rssj }}小时</span>
            <span v-else>--</span>
          </template>
        </el-table-column>
        <el-table-column prop="yzmc" label="医嘱名称" width="240"></el-table-column>
        <el-table-column prop="XZJDSJ" label="首次护士执行时间" width="160"></el-table-column>
        <el-table-column prop="fbsj" label="发病时间" width="160"></el-table-column>
        <el-table-column prop="AAB01" label="入院日期" width="160"></el-table-column>
        <el-table-column prop="AAB01" label="出院日期" width="160"></el-table-column>
        <el-table-column prop="AAC11N" label="出院科室" width="160"></el-table-column>
        <el-table-column prop="" label="状态" width="160">
          <template slot-scope="scope">
            <el-tag v-if="scope.row.numerator" type="success">正确</el-tag>
            <el-tag v-else type="danger">错误</el-tag>
          </template>
        </el-table-column>
      </el-table>
      <!-- 其他指标 -->
      <el-table v-else :data="tableData" @sort-change="handleSortChange" style="width: 100%">
        <el-table-column type="index" label="序号" align="center" width="80">
          <template slot-scope="scope">
            <span>{{ scope.$index + 1 + (paginationData.currentPage - 1) * paginationData.pageSize }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="" label="住院号码" width="90">
          <template slot-scope="scope">
            <span class="link" @click="toPage(scope.row)">{{ scope.row.AAA28 }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="AAA01" label="患者姓名" width="120"></el-table-column>
        <el-table-column prop="" label="发病时间" width="120">
          <template slot-scope="scope">
            <i v-if="scope.row.fbsj == '' || scope.row.fbsj == null" style="font-size: 20px" @click="goEdit(scope.row, 'fbsj')" class="el-icon-edit"></i>
            <span v-else>{{ scope.row.fbsj }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="AAB01" label="入院时间" sortable width="160"></el-table-column>
        <el-table-column prop="zhusu" label="主诉内容" width="160"></el-table-column>
        <el-table-column prop="zyzdmc" label="主要诊断名称" width="160"></el-table-column>
        <el-table-column prop="zyzbbh" label="主要诊断编号" width="160"></el-table-column>
        <el-table-column prop="" label="手术时间" width="160">
          <template slot-scope="scope">
            <i v-if="scope.row.sssj == null && scope.row.ssmc != ''" style="font-size: 20px" @click="goEdit(scope.row, 'sssj')" class="el-icon-edit"></i>
            <span v-else>{{ scope.row.sssj }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="ssmc" label="手术名称" width="160"></el-table-column>
        <el-table-column prop="ssbh" label="手术编号" width="160"></el-table-column>

        <el-table-column prop="AAC01" label="出院时间" sortable width="160"></el-table-column>

        <el-table-column prop="AAC11N" label="出院科室"></el-table-column>

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
        <el-table-column prop="fbsj_s" label="发病时间(时)" width="120"></el-table-column>
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
    <!-- 手工录入 -->
    <el-dialog title="手工录入" :visible.sync="visible" width="400px">
      <span style="line-height: 40px">请输入发病时间</span>
      <el-date-picker v-model="editTime" class="mgl5" type="datetime" value-format="yyyy-MM-dd HH:mm:ss" placeholder="选择日期时间"></el-date-picker>
      <span slot="footer" class="dialog-footer">
        <el-button @click="visible = false">取 消</el-button>
        <el-button type="primary" @click="saveEidt">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import Title from '@/components/Title';
import { downloadFile } from '@/httpFile';
import domMessage from '@/utils/messageOnce';
import { depBlExport } from '@/api/excel';
const messageOnce = new domMessage();
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
        order_sort: '',
      },
      timeData: {
        startTime: '',
        endTime: '',
      },
      tableData: [],
      paginationData: {
        total: 0,
        currentPage: 1,
        pageSize: 10,
      },
      departmentList: [],
      visible: false,
      editData: {},
      editTime: '',
      modifyType: '',
    };
  },
  watch: {
    $route(to, from) {
      if (from.path === '/caseIndex' && to.path === '/caseIndexList') {
        this.searchData.is_error = '';
        this.searchData.AAA28 = '';
        this.searchData.AAC11N = '';
        this.searchData.order = '';
        this.searchData.order_sort = '';
        this.tableData = [];
        this.paginationData.currentPage = 1;
        this.paginationData.pageSize = 10;
        this.getList();
      }
    },
  },
  created() {
    // 获取默认时间
    let routeYear = this.$route.query.year;
    // this.timeData.startTime = `${year}-01-01`;
    // this.timeData.endTime = `${year}-12-31`;
    const time = this.$route.query.time;
    if (time) {
      this.timeData.startTime = `${time}-01`;
      const [year, month] = time.split('-').map(Number);
      const lastDay = new Date(year, month, 0);
      const formattedLastDay = `${lastDay.getFullYear()}-${String(lastDay.getMonth() + 1).padStart(2, '0')}-${String(lastDay.getDate()).padStart(2, '0')}`;
      this.timeData.endTime = formattedLastDay;
      if (time === '全年') {
        this.timeData.startTime = `${routeYear}-01-01`;
        this.timeData.endTime = `${routeYear}-12-31`;
      }
    } else {
      this.timeData.startTime = '';
      this.timeData.endTime = '';
    }

    this.getList();
    this.getDepartmentList();
  },
  methods: {
    handleSortChange(column) {
      const { prop, order } = column;
      let str = '';
      if (order === 'descending') {
        str = 'desc';
      } else if (order === 'ascending') {
        str = 'asc';
      } else {
        str = null;
      }

      this.searchData.order = prop;
      this.searchData.order_sort = str;
      this.tableData = [];
      this.getList();
    },
    toChildrenCase() {
      this.$router.push({ path: '/caseIndex', query: { type: 'children' } });
    },
    getDepartmentList() {
      this.$axios.post('/get_department_list').then(res => {
        // 不要全部选项
        this.departmentList = res.data;
      });
    },
    // 获取指标数据
    getList() {
      const { time, type, ruleId, year } = this.$route.query;
      const { currentPage, pageSize } = this.paginationData;
      const { is_error, AAA28, AAC11N, order, order_sort } = this.searchData;
      const params = {
        time,
        id: ruleId,
        data_type: 1,
        page: currentPage,
        page_size: pageSize,
        AAA28,
        AAC11N,
        year,
      };
      if (order) {
        params.order = order;
        params.order_sort = order_sort;
      }
      params.is_error = is_error;

      params.start_time = this.timeData.startTime;
      params.end_time = this.timeData.endTime;

      this.$axios2.post('/get_zhibiao_list', params).then(res => {
        this.tableData = res.data.data;
        this.paginationData.total = res.data.count;
      });
    },
    //返回上一页
    toBack() {
      this.$router.back();
    },
    reset() {
      this.searchData.is_error = '';
      this.searchData.AAA28 = '';
      this.searchData.AAC11N = '';
      // this.searchData.order = '';
      // this.searchData.order_sort = '';

      this.timeData.startTime = '';
      this.timeData.endTime = '';
    },
    // 病案指标详情
    toPage(row) {
      this.storageSet('getData', row.MED_REC_ID);
      let path;
      if (this.$route.path === '/embedIndex-caseIndexList') {
        path = '/embedIndex-caseViews';
      } else {
        path = '/caseViews';
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
    },
    // 导出
    onExport() {
      const { time, type, ruleId, year } = this.$route.query;
      const { is_error, AAA28, AAC11N, order, order_sort } = this.searchData;

      const params = {
        dep_name: AAC11N,
        type: ruleId,
        start_time: this.timeData.startTime,
        end_time: this.timeData.endTime,
        status: is_error,
        year,
      };
      console.log(params);
      depBlExport(params).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `评审指标.csv`;
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
    },
    funExeclPost(fileName, pramse, httpUrl, format) {
      //导出
      let httpUrls = httpUrl;
      downloadFile(httpUrls, pramse, format, fileName).then(res => {
        console.log(res);
      });
    },
    // 点击编辑时间
    goEdit(obj, type) {
      this.visible = true;
      this.editData = obj;
      this.editTime = '';
      this.modifyType = type;
      console.log(this.editData);
    },
    saveEidt(modifyType) {
      const { ruleId } = this.$route.query;
      const params = {
        type: ruleId,
        blbh: ruleId == 60 ? this.editData.NG_BLBH : this.editData.XG_BLBH,
        time: this.editTime,
        field: this.modifyType,
      };
      console.log(params);
      this.$axios2.post('/up_zb_fbsj', params).then(res => {
        this.visible = false;
        this.getList();
      });
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

.mgl5 {
  margin-left: 5px;
}
</style>
