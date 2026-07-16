<template>
  <div class="part-box" style="margin-bottom: 20px;">
    <el-form :inline="true" :model="data" class="demo-form-inline" label-width="100px">
      <el-row :gutter="24">
        <el-col :span="8">
          <el-form-item label="质控时间">
            <!-- <el-date-picker
              v-model="data.zk_start_time"
              type="date"
              format="yyyy年MM月dd日"
              value-format="yyyyMMdd"
              placeholder="质控开始时间"
              :picker-options="pickerOptions"
              style="margin-right: 8px; width: 190px;"
            />
            <el-date-picker
              v-model="data.zk_end_time"
              type="date"
              format="yyyy年MM月dd日"
              value-format="yyyyMMdd"
              placeholder="质控结束时间"
              :picker-options="pickerOptions"
              style="width: 190px;"
            /> -->
            <el-date-picker
                v-model="data.zk_start_time"
                type="date"
                :picker-options="zkPickerOptions"
                placeholder="质控开始时间"
                value-format="yyyyMMdd"
                format="yyyy年MM月dd日"
              />
              <el-date-picker
                v-model="data.zk_end_time"
                type="date"
                :picker-options="[]"
                placeholder="质控结束时间"
                value-format="yyyyMMdd"
                format="yyyy年MM月dd日"
              />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="出院时间">
            <!-- <el-date-picker
              v-model="data.start_time"
              type="month"
              format="yyyy年MM月"
              value-format="yyyyMM"
              placeholder="出院开始时间"
              style="margin-right: 8px; width: 190px;"
            />
            <el-date-picker
              v-model="data.end_time"
              type="month"
              format="yyyy年MM月"
              value-format="yyyyMM"
              placeholder="出院结束时间"
              style="width: 190px;"
            /> -->
            <el-date-picker
                v-model="data.start_time"
                type="date"
                :picker-options="pickerOptions"
                placeholder="出院开始时间"
                value-format="yyyyMMdd"
                format="yyyy年MM月dd日"
              />
              <el-date-picker
                v-model="data.end_time"
                type="date"
                :picker-options="[]"
                placeholder="出院结束时间"
                value-format="yyyyMMdd"
                format="yyyy年MM月dd日"
              />
          </el-form-item>
        </el-col>
        <el-col :span="8" v-if="type_name === 'lc'">
          <el-form-item label="入院时间">
            <!-- <el-date-picker
              v-model="data.ry_start_time"
              type="date"
              format="yyyy年MM月dd日"
              value-format="yyyyMMdd"
              placeholder="入院开始时间"
              :picker-options="pickerOptions"
              style="margin-right: 8px; width: 190px;"
            />
            <el-date-picker
              v-model="data.ly_end_time"
              type="date"
              format="yyyy年MM月dd日"
              value-format="yyyyMMdd"
              placeholder="入院结束时间"
              :picker-options="pickerOptions"
              style="width: 190px;"
            /> -->
             <el-date-picker
                v-model="data.ry_start_time"
                type="date"
                :picker-options="ryPickerOptions"
                placeholder="入院开始时间"
                value-format="yyyyMMdd"
                format="yyyy年MM月dd日"
              />
              <el-date-picker
                v-model="data.ly_end_time"
                type="date"
                :picker-options="[]"
                placeholder="入院结束时间"
                value-format="yyyyMMdd"
                format="yyyy年MM月dd日"
              />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <!-- 临床科室 -->
          <el-form-item v-if="type_name == 'lc'" label="出院科室">
            <el-select v-model="data.AAC11N" filterable clearable placeholder="请选择" style="width: 390px;">
              <el-option v-for="(item, index) in departmentList" :label="item" :value="item" :key="'a' + index"></el-option>
            </el-select>
          </el-form-item>
          <!-- 非临床科室 -->
          <el-form-item v-else label="出院科室">
            <el-select v-model="data.dep_id" filterable clearable virtual-scroll placeholder="请选择" style="width: 390px;">
              <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.id" :key="'b' + index"></el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="住院号码">
            <el-input v-model="data.AAA28" clearable placeholder="请输入" style="width: 390px;"></el-input>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="缺陷描述">
            <el-input v-model="data.desc" clearable placeholder="请输入" style="width: 390px;"></el-input>
          </el-form-item>
        </el-col>
        <!-- <el-col :span="8">
          <el-form-item label="缺陷归类">
            <el-select v-model="data.type" filterable clearable placeholder="请选择" style="width: 390px;">
              <el-option v-for="item of selectData.type" :key="item" :label="item" :value="item" />
            </el-select>
          </el-form-item>
        </el-col> -->
        <!-- <el-col :span="8">
          <el-form-item label="住院医师">
            <el-select v-model="data.AEE04" filterable clearable virtual-scroll placeholder="请选择" style="width: 390px;">
              <el-option v-for="item of selectData.doctor" :key="item.id" :label="item.name" :value="item.name" />
            </el-select>
          </el-form-item>
        </el-col> -->
        <!-- <el-col :span="8">
          <el-form-item label="编码员">
            <el-select v-model="data.AEE08" filterable clearable virtual-scroll placeholder="请选择" style="width: 390px;">
              <el-option v-for="item of selectData.doctor" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
          </el-form-item>
        </el-col> -->
        <!-- <el-col :span="8">
          <el-form-item label="主要诊断名称">
            <el-input v-model="data.ICD10_NAME" clearable placeholder="请输入" style="width: 390px;"></el-input>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="主要诊断编码">
            <el-input v-model="data.ICD10_ID1" clearable placeholder="请输入" style="width: 390px;"></el-input>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="主要手术名称">
            <el-input v-model="data.ICD9_NAME" clearable placeholder="请输入" style="width: 390px;"></el-input>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="主要手术编码">
            <el-input v-model="data.ICD9_ID1" clearable placeholder="请输入" style="width: 390px;"></el-input>
          </el-form-item>
        </el-col> -->
        <el-col :span="8" style="width: 512px; text-align: right; float: right; margin-right: 16px;">
          <el-form-item>
            <el-button type="primary" @click="onSubmit">查询</el-button>
            <el-button @click="onReset">重置</el-button>
          </el-form-item>
        </el-col>
      </el-row>
    </el-form>
  </div>
</template>

<script>
import moment from 'moment/moment';
export default {
  props: {
    data: {
      type: Object,
      default() {
        return {
          hospital_name:'',
          AAA28: '',
          AAA01: '',
          AAC11N: '',
          dep_id: '',
          start_time: '',
          end_time: '',
          type: '',
          level: '',
          desc: '',
          AEE04: '',
          AEE08: '',
          ICD10_NAME: '',
          ICD10_ID1: '',
          ICD9_NAME: '',
          ICD9_ID1: '',
          zk_start_time: '',
          zk_end_time: '',
          ry_start_time: '',
          ly_end_time: ''
        }
      },
    },
    type_name:{  // 'lc' 临床
      type: String,
      default() {
        return ''
      }
    }
  },
  data() {
    const that = this;
    return {
      pickerOptions: {
        disabledDate: time => {
          if (that.data.end_time != '') {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [
          {
            text: '今天',
            onClick(picker) {
              picker.$emit('pick', moment().format('YYYYMMDD'));
              that.data.end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近7天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
              that.data.end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近30天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
              that.data.end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '一季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.data.end_time = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '二季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(3, 'M').format('YYYYMMDD'));
              that.data.end_time = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '三季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(6, 'M').format('YYYYMMDD'));
              that.data.end_time = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '四季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(9, 'M').format('YYYYMMDD'));
              that.data.end_time = moment().startOf('year').add(12, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-2, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-2, 'Y').startOf('year').format('YYYYMMDD'));
              that.data.end_time = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-1, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-1, 'Y').startOf('year').format('YYYYMMDD'));
              that.data.end_time = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.data.end_time = moment().endOf('year').format('YYYYMMDD');
            },
          },
        ],
      },
      ryPickerOptions: {
        disabledDate: time => {
          if (that.data.ly_end_time != '') {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [
          {
            text: '今天',
            onClick(picker) {
              picker.$emit('pick', moment().format('YYYYMMDD'));
              that.data.ly_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近7天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
              that.data.ly_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近30天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
              that.data.ly_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '一季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.data.ly_end_time = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '二季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(3, 'M').format('YYYYMMDD'));
              that.data.ly_end_time = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '三季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(6, 'M').format('YYYYMMDD'));
              that.data.ly_end_time = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '四季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(9, 'M').format('YYYYMMDD'));
              that.data.ly_end_time = moment().startOf('year').add(12, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-2, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-2, 'Y').startOf('year').format('YYYYMMDD'));
              that.data.ly_end_time = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-1, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-1, 'Y').startOf('year').format('YYYYMMDD'));
              that.data.ly_end_time = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.data.ly_end_time = moment().endOf('year').format('YYYYMMDD');
            },
          },
        ],
      },
      zkPickerOptions: {
        disabledDate: time => {
          if (that.data.zk_end_time != '') {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [
          {
            text: '今天',
            onClick(picker) {
              picker.$emit('pick', moment().format('YYYYMMDD'));
              that.data.zk_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近7天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
              that.data.zk_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近30天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
              that.data.zk_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '一季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.data.zk_end_time = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '二季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(3, 'M').format('YYYYMMDD'));
              that.data.zk_end_time = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '三季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(6, 'M').format('YYYYMMDD'));
              that.data.zk_end_time = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '四季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(9, 'M').format('YYYYMMDD'));
              that.data.zk_end_time = moment().startOf('year').add(12, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-2, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-2, 'Y').startOf('year').format('YYYYMMDD'));
              that.data.zk_end_time = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-1, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-1, 'Y').startOf('year').format('YYYYMMDD'));
              that.data.zk_end_time = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.data.zk_end_time = moment().endOf('year').format('YYYYMMDD');
            },
          },
        ],
      },
      selectData:  {},
      departmentList: []
    }
  },
  created() {
    this.getSelectData()
    this.getDepList()
  },
  methods: {
    onReset() {
      this.$emit('reset')
    },
    getDepList() {
      let url = '';
      if(this.type_name == 'lc'){
        url = '/home_sz_quality/getKsList'
      }else{
        url = '/omr_zk/department_list'
      }
      this.$axios.post(url).then(res => {
        this.departmentList = res.data;
      });
    },
    getSelectData() {
      this.$axios.post('/home_quality/getErrorSerachWhere').then(res => {
        this.selectData = res.data
      });
    },
    onSubmit() {
      this.$emit('search')
    }
  }
}
</script>

<style lang="scss" scoped>
::v-deep .el-col-8 {
  // width: auto;
}
.demo-form-inline {
  position: relative;
  margin: 0 auto;
  text-align: center;
}
.search-btn {
  width: 240px;
}
.btn-group {
  text-align: right;
  margin-bottom: 20px;
  position: relative;
}
</style>
<style lang="scss">
.table_code_popper {
  .el-checkbox {
    display: block;
    line-height: 26px;
  }
}
</style>
