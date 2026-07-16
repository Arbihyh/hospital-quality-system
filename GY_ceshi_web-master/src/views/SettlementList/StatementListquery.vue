<template>
  <div class="dashboard-container">
    <div class="block">
      <div class="barBtn">
        <el-radio-group v-model="choice" class="bnts" size="medium">
          <el-radio-button label="0">普通检索</el-radio-button>
          <el-radio-button label="1">高级检索</el-radio-button>
        </el-radio-group>
      </div>
      <div class="bnh">
        <el-input v-if="choice == 0" style="width: 303px" placeholder="全站搜索病案号" suffix-icon="el-icon-search" v-model="inputOn"></el-input>
      </div>
      <div class="inputs" v-if="choice == 0">
        <el-row :gutter="20" class="rowsa">
          <el-col :span="4">
            <div class="grid-content bg-purple">
              <el-input class="inpus" v-model="formData0.recordNum" placeholder="病案号"></el-input>
            </div>
          </el-col>
          <el-col :span="5">
            <div class="grid-content bg-purple">
              <el-select v-model="formData0.Department" class="selects" placeholder="出院科室">
                <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.id" :key="index"></el-option>
              </el-select>
            </div>
          </el-col>
          <el-col :span="5">
            <div class="grid-content bg-purple">
              <el-select v-model="formData0.problem" class="selects" placeholder="问题属性">
                <el-option v-for="(item, index) in levelList" :label="item.name" :value="item.id" :key="index"></el-option>
              </el-select>
            </div>
          </el-col>
          <el-col :span="5">
            <div class="grid-content bg-purple">
              <el-select v-model="formData0.payment" class="selects" placeholder="医疗付款方式">
                <el-option v-for="(item, index) in payList" :label="item.name" :value="item.id" :key="index"></el-option>
              </el-select>
            </div>
          </el-col>
          <el-col :span="5">
            <div class="grid-content bg-purple">
              <el-select v-model="formData0.medicalRecord" class="selects" placeholder="全部病案">
                <el-option label="已质控" value="1"></el-option>
                <el-option label="未质控" value="0"></el-option>
              </el-select>
            </div>
          </el-col>
        </el-row>
        <el-row :gutter="20" class="rowsa">
          <el-col :span="4">
            <div class="grid-content bg-purple">
              <el-select v-model="formData0.state" class="selects" placeholder="编辑状态">
                <!-- statusList -->
                <el-option v-for="(item, index) in statusList" :label="item.name" :value="item.id" :key="index"></el-option>
              </el-select>
            </div>
          </el-col>
          <el-col :span="10">
            <div class="grid-content bg-purple">
              <el-date-picker
                style="width: 100%"
                v-model="formData0.rangeDate"
                size="large"
                type="daterange"
                range-separator="-"
                start-placeholder="开始日期"
                end-placeholder="结束日期"
                format="yyyy 年 MM 月 dd 日"
                value-format="yyyyMMdd"
              >
              </el-date-picker>
              <!-- <el-date-picker v-model="formData0.startTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="开始日期"></el-date-picker>
              <el-date-picker
                v-model="formData0.endTime"
                type="date"
                style="margin-left: 10px"
                format="yyyy 年 MM 月 dd 日"
                value-format="yyyyMMdd"
                placeholder="结束日期"
              ></el-date-picker> -->
            </div>
          </el-col>
          <el-col :span="5">
            <div class="grid-content bg-purple">
              <el-select v-model="formData0.Coder" class="selects" placeholder="编码员">
                <el-option v-for="(item, index) in coderList" :label="item.name" :value="item.id" :key="index"></el-option>
              </el-select>
            </div>
          </el-col>
          <el-col :span="5">
            <div class="grid-content bg-purple">
            </div>
          </el-col>
        </el-row>
      </div>
      <div class="barBtn" v-else>
        <el-form ref="form" :model="formData1" label-width="100px">
          <el-form-item v-for="(item, index) in formData1.seniorList" :key="index">
            <el-select class="width150" v-model="item.key" placeholder="请选择入院病情">
              <!-- fieldList -->
              <el-option v-for="(item, index) in fieldList" :label="item.name" :value="item.id" :key="index"></el-option>
            </el-select>
            <span class="pind10"></span>

            <el-input class="width150" v-model="item.value" placeholder=""></el-input>
            <span class="pind10"></span>

            <el-select class="width90" v-model="item.type" placeholder="">
              <el-option label="精确" value="1"></el-option>
              <el-option label="模糊" value="0"></el-option>
            </el-select>
            <span class="pind10"></span>
            <span v-if="index == 0">
              <el-button :disabled="formData1.seniorList.length == 1" type="primary" icon="el-icon-minus" @click="funDel"></el-button>
              <el-button type="primary" icon="el-icon-plus" @click="funAdd"></el-button>
            </span>
          </el-form-item>
          <el-form-item label="患者年龄">
            <el-input class="width300" v-model="formData1.ageday" :min="28" :max="365" type="number" placeholder="<28天" @blur="funBlur">
              <template slot="append">天</template>
            </el-input>
            <span class="pind">或</span>
            <el-input class="width300" v-model="formData1.ageyear" :min="1" :max="150" type="number" placeholder="1-150岁" @blur="funBlur">
              <template slot="append">岁</template>
            </el-input>
          </el-form-item>
          <el-form-item label="时间范围">
            <el-date-picker
              class="width500"
              v-model="formData1.rangeDate"
              size="large"
              type="daterange"
              range-separator="-"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              format="yyyy 年 MM 月 dd 日"
              value-format="yyyyMMdd"
            ></el-date-picker>
          </el-form-item>
        </el-form>
      </div>
      <div class="fBtn">
        <el-button @click="reset">重置条件</el-button>
        <el-button type="primary" @click="funQuery">检索</el-button>
      </div>
    </div>
    <div class="tableBox">
      <Title :title="'病案列表'" />
      <el-table :data="tableData" style="width: 100%">
        <el-table-column type="index" label="序号"></el-table-column>
        <el-table-column prop="AAA28" label="病案号">
          <template slot-scope="scope">
            <span class="blue" @click="funGoto(scope.row.MED_REC_ID)">{{ scope.row.AAA28 }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="AAA01" label="患者姓名"></el-table-column>
        <el-table-column prop="AAA02C" label="性别"></el-table-column>
        <el-table-column prop="AAA04" label="年龄"></el-table-column>
        <el-table-column prop="AAA29" label="住院次数"></el-table-column>
        <el-table-column prop="AAC11N" label="出院科室"></el-table-column>
        <el-table-column prop="AAC01" label="出院日期"></el-table-column>
        <el-table-column prop="ADA01" label="总费用"></el-table-column>
        <el-table-column prop="F_D" label="药品费用"></el-table-column>
        <el-table-column prop="J" label="材料费用"></el-table-column>
        <el-table-column prop="ABC01N" label="主诊断"></el-table-column>
        <el-table-column prop="ICD9_NAME" label="主手术"></el-table-column>
        <!-- <el-table-column prop="address" label="业务操作人"> </el-table-column> -->
        <el-table-column prop="AAC04" label="实际住院(天)"></el-table-column>
        <el-table-column prop="ATTEND_GRP_NAME" label="主诊组"></el-table-column>
        <el-table-column prop="AEM01C" label="离院方式"></el-table-column>
        <el-table-column prop="AAB06C" label="入院途径"></el-table-column>
      </el-table>
      <!-- 分页控制 -->
      <mPagination v-if="tableData && tableData.length !== 0" :data="paginationData" @pageChangeEvent="pageHasChanged"></mPagination>
    </div>
  </div>
</template>

<script>
import Title from '@/components/Title';
import { mapGetters } from 'vuex';
import mPagination from '@/components/m-pagination';

export default {
  name: 'Dashboard',
  components: {
    Title,
    mPagination,
  },
  computed: {
    ...mapGetters(['name']),
  },
  data() {
    return {
      choice: '0',
      formData0: {
        recordNum: '', //病案号
        Department: '', //出院科室
        problem: 'all', //问题属性
        payment: '', //医疗付款方式
        state: '', //编辑状态
        rangeDate: [], //时间
        Coder: '', //住院医师
        medicalRecord: '', //全部病案
      },
      formData1: {
        ageday: '',
        ageyear: '',
        rangeDate: [],
        seniorList: [
          {
            key: '',
            value: '',
            type: '0',
          },
          {
            key: '',
            value: '',
            type: '0',
          },
        ],
      },
      inputOn: '', //全站搜索病案号
      value: '',
      value1: '',
      tableData: [],
      payList: [], //支付方式
      departmentList: [], //出院科室
      levelList: [], //问题属性
      coderList: [], //编码元
      statusList: [], //编辑状态
      fieldList: [], //主要诊断名字
      // 分页数据
      paginationData: {
        total: 10,
        currentPage: 1,
        pageSize: 10,
      },
    };
  },
  mounted() {
    this.selectInfo();
    this.funQuery();
  },
  methods: {
    funGoto(val) {
      this.storageSet('getData', val);
      this.goto('SetDetails');
    },
    funBlur() {
      if (this.formData1.ageday > 356) {
        this.formData1.ageday = 356;
      }
      if (this.formData1.ageyear > 150) {
        this.formData1.ageyear = 150;
      }
    },
    funDel() {
      this.formData1.seniorList.pop();
    },
    funAdd() {
      this.formData1.seniorList.push({
        key: '',
        value: '',
        type: '0',
      });
    },
    pageHasChanged() {
      this.funQuery();
    },
    selectInfo() {
      // let pramse = {};
      this.$axios.post('/selectInfo').then(res => {
        this.payList = res.data.pay;
        console.log(this.payList);
        //支付方式 pay
        this.departmentList = res.data.department;
        //出院科室 department
        this.levelList = res.data.level;
        //问题属性 level
        this.coderList = res.data.coder;
        //编码元  coder
        this.statusList = res.data.status;
        this.fieldList = res.data.field;
      });
    },
    funQuery() {
      //查询
      if (this.choice == 0) {
        let pramse = {
           source:1,
          level: this.formData0.problem || null, //问题属性
          AAA28: this.formData0.recordNum || null, //病案号
          AAC11N: this.formData0.Department || null, //出院科室
          AAA26C: this.formData0.payment || null, //付款方式
          status: this.formData0.state || null, //编辑状态
          AAC01: this.formData0.rangeDate, //出院时间
          coder_id: this.formData0.Coder || null, //编码员ID
          ORG_STATE: this.formData0.medicalRecord || null, //全部病案
          page: this.paginationData.currentPage, //页码
          limit: this.paginationData.pageSize, //条数
        };
        this.$axios.post('/qualityList', pramse).then(res => {
          console.log(res);
          this.paginationData.total = res.data.count;
          this.tableData = res.data.list;
        });
      } else {
        let pramse = {
          source:1,
          AAA04: this.formData1.ageyear || null, //年龄
          AAA40: this.formData1.ageday || null, //不足一周岁年龄
          AAC01: this.formData1.rangeDate, //出院时间
          field: this.formData1.seniorList || null, //字段条件
          page: this.paginationData.currentPage, //页码
          limit: this.paginationData.pageSize, //条数
        };
        this.$axios.post('/qualityList', pramse).then(res => {
          console.log(res);
          this.paginationData.total = res.data.count;
          this.tableData = res.data.list;
        });
      }
    },
    reset() {
      // 重置数据
      if (this.choice == 0) {
        Object.assign(this.$data.formData0, this.$options.data().formData0);
      } else {
        Object.assign(this.$data.formData1, this.$options.data().formData1);
      }
    },
  },
};
</script>
<style scoped>
::v-deep.el-pagination.is-background .btn-next,
::v-deep.el-pagination.is-background .btn-prev,
::v-deep.el-pagination.is-background .el-pager li {
  margin: 0 5px;
  background-color: #fff;
  color: #606266;
  min-width: 30px;
  border-radius: 2px;
  border: 1px solid #dfe3f3;
  line-height: 27px;
}
::v-deep.el-pagination.is-background .el-pager li:not(.disabled).active {
  background: #7e8bab;
}
::v-deep.el-table .el-table__row td {
  color: #7e8bab;
  border-bottom: 1px solid #f4f4f4;
}
::v-deep.el-table .el-table__header tr th:first-child {
  border-radius: 10px 0px 0px 10px;
}
::v-deep.el-table .el-table__header tr th:last-child {
  border-radius: 0px 10px 10px 0px;
}
::v-deep.el-table .el-table__header tr th {
  background: #f1f6ff;
  color: #13171e;
  border-bottom: 0px;
}
</style>
<style lang="scss" scoped>
.tableBox {
  background: #fff;
  padding: 19px;
  border-radius: 5px;
  font-size: 12px;
}
.block {
  background: #fff;
  border-radius: 5px;
  padding: 20px 30px;
  margin-bottom: 20px;
  .fBtn {
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .bnh {
    margin-bottom: 20px;
  }
  .barBtn {
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .selects {
    width: 100%;
  }
  .rowsa {
    margin-bottom: 20px;
  }
}
.tableBox {
  background: #fff;
  padding: 19px;
  border-radius: 5px;
}
.dashboard {
  &-container {
    margin: 30px;
  }
  &-text {
    font-size: 30px;
    line-height: 46px;
  }
}
.pind {
  padding: 0 20px;
}
.pind10 {
  padding: 0 5px;
}
.width150 {
  width: 200px;
}
.width300 {
  width: 295px;
}
.width500 {
  width: 645px;
}
.width90 {
  width: 90px;
}
.blue {
  color: #185da6;
}
</style>
