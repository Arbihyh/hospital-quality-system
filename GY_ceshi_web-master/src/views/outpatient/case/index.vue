<template>
  <div class="box" :class="{ 'nocopy': $route.meta.nocopy }">
    <div class="box_wrapper">
      <div class="title">门诊病历搜索</div>
      <!-- 搜索部分 -->
      <el-form ref="form" :model="form" class="search_wrapper" label-width="100px">
        <el-form-item label="" v-for="(item, index) of form.field" :key="index">
          <!-- 关系 -->
          <el-select v-model="item.select_type" filterable placeholder="" :disabled="item.lock"
            style="width: 90px; position: absolute; left: -100px;">
            <el-option label="且" :value="0" />
            <el-option label="或" :value="1" />
            <el-option label="不包含" :value="2" />
          </el-select>
          <!-- 检索字段 -->
          <el-select v-model="item.key" filterable placeholder="请选择" :disabled="item.lock" @change="handleKeyChange(item)"
            style="margin-right: 10px; width: 237px;">
            <el-option v-for="(fitem, findex) in fieldList" :key="findex" :label="fitem.value" :value="fitem.key"
              :disabled="item.lock" />
          </el-select>
          <!-- 检索内容 -->
          <el-select v-if="item.key === 'ks'" v-model="item.value" filterable clearable placeholder="请选择"
            style="width: 237px;"  >
            <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.id"
              :key="index"></el-option>
          </el-select>
          <el-input v-else v-model="item.value" :disabled="item.lock" placeholder="请输入" style="width: 237px;" :key="`input-${index}-${item.key}`"/>
          <span class="btn-group"
            :class="{ 'btn-group1': form.field.length !== 1 && index == form.field.length - 1, 'btn-group2': index != form.field.length - 1 && searchNum, 'btn-group3': index == form.field.length - 1 && searchNum }">
            <el-button :disabled="form.field.length == 1 || item.lock" type="primary" icon="el-icon-minus"
              @click="funDel(index)" />
            <el-button type="primary" icon="el-icon-plus" @click="funAdd" />
            <el-button v-if="index === form.field.length - 1 && searchNum" @click="onLockResult">结果中检索</el-button>
          </span>
        </el-form-item>
        <el-form-item label="患者年龄">
          <el-input-number v-model="form.start_nl" :min="1" :step="1" :controls="false" placeholder="起始年龄"
            style="width: 220px"></el-input-number>
          <span class="pind5" />
          —
          <span class="pind5" />
          <el-input-number v-model="form.end_nl" :min="1" :step="1" :controls="false" placeholder="终止年龄"
            style="width: 220px">
          </el-input-number>
        </el-form-item>
        <el-form-item label="就诊时间">
          <el-date-picker v-model="form.start_time" type="date" :picker-options="pickerOptions1" placeholder="开始日期" />
          <span class="pind5" />
          —
          <span class="pind5" />
          <el-date-picker v-model="form.end_time" type="date" :picker-options="pickerOptions2" placeholder="结束日期" />
        </el-form-item>
      </el-form>
      <div style="text-align: center; position: relative; margin-bottom: 30px;">
        <el-button type="primary" class="long-btn" @click="onSearch(0)">检索</el-button>
        <el-button @click="onReset" style="position: absolute; right: 0px;">重置条件</el-button>
      </div>
      <!-- 列表部分 -->
      <div class="table_wrapper">
        <div class="table_header">
          <Title :title="'检索结果'" style="float: left; margin-right: 15px;" />
          <span>例数：<span class="total"> {{ paginationData.total }} </span>例</span>
          <el-button-group style="float: right; margin-top: -15px;">
            <el-button :type="active ? 'primary' : ''" @click="onTab(1)">列表</el-button>
            <el-button :type="!active ? 'primary' : ''" @click="onTab(0)">详情</el-button>
          </el-button-group>
        </div>
        <div class="table_box">
          <el-table v-if="active" :data="tableData" style="width: 100%">
            <el-table-column type="index" label="序号" width="80" align="center">
            </el-table-column>
            <el-table-column prop="" label="门诊号">
              <template slot-scope="scope">
                <span class="blue" @click="funGoto(scope.row.BLBH, scope.row.xm)">
                  {{ scope.row.mzh }}
                </span>
              </template>
            </el-table-column>
            <el-table-column prop="xm" label="姓名">
            </el-table-column>
            <el-table-column prop="nl" label="年龄">
            </el-table-column>
            <el-table-column prop="xb" label="性别">
            </el-table-column>
            <el-table-column prop="ks" label="科室">
            </el-table-column>
            <el-table-column prop="SFZH" label="身份证号">
            </el-table-column>
            <el-table-column prop="CJSJ" label="就诊时间">
            </el-table-column>
          </el-table>
          <div v-if="!active">
            <InfoCard v-for="(item, index) of infoData" :key="index" :item="item" :index="index" />
            <el-empty v-if="!infoData.length" description="暂无数据"></el-empty>
          </div>
          <!-- 分页 -->
          <el-pagination v-if="tableData && tableData.length !== 0" @size-change="SizeChangeEvent"
            @current-change="pageHasChanged" :total="paginationData.total" background class="table-pagination"
            style="margin: 30px 0 ; float: right;" :page-size="paginationData.pageSize"
            :current-page.sync="paginationData.currentPage" layout="total, sizes, prev, pager, next, jumper">
          </el-pagination>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import Title from '@/components/Title';
import InfoCard from './components/InfoCard'
import { dateFormat } from '@/utils/index'

export default {
  components: {
    Title,
    InfoCard
  },
  data() {
    return {
      pickerOptions1: {
        disabledDate: (time) => {
          if (this.form.end_time) {
            return time.getTime() > new Date(this.form.end_time).getTime()
          } else {
            return time.getTime() > Date.now()
          }
        }
      },
      pickerOptions2: {
        disabledDate: (time) => {
          if (this.form.start_time) {
            return time.getTime() < new Date(this.form.start_time).getTime()
          } else {
            return time.getTime() > Date.now()
          }
        }
      },
      fieldList: [],
      oldKeys: {},
      form: {
        start_nl: undefined,
        end_nl: undefined,
        start_time: '',
        end_time: '',
        field: [
          {
            select_type: 0,
            key: 'BLNR_TXT',
            value: '',
            lock: false
          }
        ]
      },
      // 1:列表 0：详情
      active: 1,
      tableData: [],
      infoData: [],
      paginationData: {
        total: 0,
        currentPage: 1,
        pageSize: 10,
      },
      resultSearch: 0,
      searchNum: 0,
      departmentList: [],
      is_tm_path: ['/hospital-caseViews', '/embedIndex-caseViews', '/reviewIndex-caseViews', '/whitelist-caseViews', '/whitelist-search']
    }
  },


  created() {
    this.selectInfo()
    this.getList(0)
  },
  methods: {

    handleKeyChange(item) {
      // 只要字段key发生变化，就清空值
      this.$nextTick(() => {
        this.$set(item, 'value', '');
      });
    },

    funGoto(blbh, xm) {
      const { path } = this.$route;
      let toPath;
      if (path === '/whitelist-search') {
        toPath = '/whitelist-outpatientMedicalRecordDetail';
      } else {
        toPath = '/outpatientMedicalRecordDetail';
      }
      this.$router.push({ path: toPath, query: { blbh, xm, from: 'search' } })
    },
    selectInfo() {
      this.$axios.post('/get_omr_department_list').then(res => {
        this.departmentList = res.data;
      });
      this.$axios.post('/omr_zk/serach_type_list').then(res => {
        this.fieldList = res.data;
      });
    },
    // 获取列表
    getList(index) {
      this.searchNum = index
      const { currentPage, pageSize } = this.paginationData
      const { start_nl, end_nl, start_time, end_time, field } = this.form
      const params = {
        start_nl,
        end_nl,
        start_time: start_time ? dateFormat(start_time, 'YYYYMMDD') : '',
        end_time: end_time ? dateFormat(end_time, 'YYYYMMDD') : '',
        page: currentPage,
        page_size: pageSize,
      }
      if (this.$route.query.code) {
        params.code = this.$route.query.code
      }
      if (field[0].key) {
        params.field = field
      }
      if (this.is_tm_path.includes(this.$route.path)) {
        params.is_tm = 1;
      }
      this.$axios.post('/get_omr_bl01_list', params).then(res => {
        this.tableData = res.data.list
        this.infoData = res.data.detail
        this.paginationData.total = res.data.total
      })
    },
    // 列表 详情
    onTab(index) {
      this.active = index
    },
    // 分页
    SizeChangeEvent(val) {
      this.paginationData.pageSize = val;
      this.getList(this.resultSearch);
    },
    pageHasChanged() {
      this.getList(this.resultSearch);
    },
    // 搜索
    onSearch() {
      this.paginationData.currentPage = 1;
      this.getList(1);
    },
    // 重置
    onReset() {
      this.$set(this, 'form', {
        start_nl: undefined,
        end_nl: undefined,
        start_time: '',
        end_time: '',
        field: [
          {
            select_type: 0,
            key: 'BLNR_TXT',
            value: '',
            lock: false
          }
        ]
      }
      )
      this.resultSearch = 0,
        this.searchNum = 0
      this.paginationData.currentPage = 1;
      this.getList(0);
    },
    funDel(i) {
      const index = i;
      const list = this.form.field;
      list.splice(index, 1);
      this.form.field = list;
    },
    funAdd() {
      this.form.field.push({
        key: '',
        select_type: 0,
        value: '',
        lock: false
      });
    },
    onLockResult() {
      this.resultSearch = 1
      this.form.field.map(item => {
        if (!!item.key && !!item.value) {
          item.lock = true
        } else {
          item.lock = false
        }
      })
      this.funAdd()
    },
  }
}
</script>

<style lang="scss" scoped>
.blue {
  color: #185da6;
  cursor: pointer;
}

.pind5 {
  padding: 0 5px;
}

.btn-group {
  position: absolute;
  right: -144px;

  &.btn-group1 {
    right: -144px;
  }

  &.btn-group2 {
    right: -78px;
  }

  &.btn-group3 {
    right: -264px;
  }
}

.box {
  padding: 0 16px 16px 16px;

  .box_wrapper {
    padding: 16px;
    background: #fff;
    border-radius: 5px;
    overflow: hidden;

    .title {
      font-size: 24px;
      text-align: center;
      font-weight: 600;
      padding-top: 20px;
      padding-bottom: 20px;
      margin-bottom: 20px;
    }
  }

  .search_wrapper {
    width: 584px;
    margin: 0 auto 30px;
  }

  .table_wrapper {
    .table_header {
      padding-top: 15px;
      overflow: hidden;

      .total {
        color: #F56C6C;
      }

      span {
        font-weight: 600;
        padding-top: 2px;
      }
    }
  }
}

::v-deep .el-input-number .el-input__inner {
  text-align: left;
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