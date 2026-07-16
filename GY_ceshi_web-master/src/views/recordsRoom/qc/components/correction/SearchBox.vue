<template>
  <el-form style="width: 100%" ref="filterListFormRef" :model="formData" class="demo-form-inline" label-suffix=":"
    label-width="74px">
    <el-row :gutter="24">
      <el-col :span="7">
        <el-form-item label="出院日期">
          <div style="width: 100%;display: flex;gap: 5px;">
            <el-form-item prop="AAC01_START">
              <el-date-picker style="width: 100%" v-model="formData.AAC01_START" type="date" placeholder="开始日期"
                :picker-options="AAC01PickerOptions" value-format="yyyyMMdd" format="yyyy年MM月dd日">
              </el-date-picker>
            </el-form-item>
            <el-form-item prop="AAC01_END">
              <el-date-picker style="width: 100%" v-model="formData.AAC01_END" type="date" placeholder="结束日期"
                value-format="yyyyMMdd" format="yyyy年MM月dd日">
              </el-date-picker>
            </el-form-item>
          </div>
        </el-form-item>
      </el-col>
      <el-col :span="5">
        <el-form-item label="病案号" prop="AAA28">
          <el-input v-model="formData.AAA28" placeholder="请输入"></el-input>
        </el-form-item>
      </el-col>
      <el-col :span="6">
        <el-form-item label="病人科室" prop="AAC02C">
          <el-select style="width:100%" v-model="formData.AAC02C" filterable clearable placeholder="请选择">
            <el-option v-for="item of departments" :key="item.dep_id" :label="item.name" :value="item.dep_id" />
          </el-select>
        </el-form-item>
      </el-col>
      <el-col :span="6">
        <el-form-item label="质控类型" prop="type">
          <el-select style="width:100%" v-model="formData.type" filterable clearable placeholder="请选择">
            <el-option label="运行首页" value="1" />
            <el-option label="运行病历" value="2" />         
            <el-option label="编目首页" value="3" />
          </el-select>
        </el-form-item>
      </el-col>
    </el-row>
    <el-row :gutter="24">
      <el-col :span="7">
        <el-form-item label="入院日期">
          <div style="width: 100%;display: flex;gap: 5px;">
            <el-form-item prop="AAB01_START">
              <el-date-picker style="width: 100%" v-model="formData.AAB01_START" type="date" placeholder="开始日期"
                :picker-options="AAB01PickerOptions" value-format="yyyyMMdd" format="yyyy年MM月dd日">
              </el-date-picker>
            </el-form-item>
            <el-form-item prop="AAB01_END">
              <el-date-picker style="width: 100%" v-model="formData.AAB01_END" type="date" placeholder="结束日期"
                value-format="yyyyMMdd" format="yyyy年MM月dd日">
              </el-date-picker>
            </el-form-item>
          </div>
        </el-form-item>
      </el-col>
      <el-col :span="5">
        <el-form-item label="整改状态" prop="is_correction">
          <el-select style="width: 100%;" v-model="formData.is_correction" filterable clearable placeholder="请选择">
            <el-option label="已整改" :value="1" />
            <el-option label="未整改" :value="0" />
          </el-select>
        </el-form-item>
      </el-col>
      <el-col :span="6" :offset="6">
        <el-form-item label="" prop="" style="float: right;">
          <el-button type="primary" @click="onSubmit">查询</el-button>
          <el-button @click="onReset">重置</el-button>
          <el-button type="primary" icon="el-icon-download" class="export-btn" @click="() =>{this.$emit('onExport',2)}">导出数据</el-button>
        </el-form-item>
      </el-col>
    </el-row>
  </el-form>
</template>
<script>
import moment from 'moment/moment';

export default {
  emits: ['search', 'reset'],
  data() {
    const that = this
    return {
      formData: {
        AAC01_START: '',
        AAC01_END: '',
        AAB01_START: '',
        AAB01_END: '',
        AAA28: '',
        AAC02C: '',
        is_correction: '',
        type: ''
      },
      departments: [],
      AAC01PickerOptions: {
        disabledDate: (time) => {
          if (that.formData.AAC01_END != "") {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [{
          text: '今天',
          onClick(picker) {
            picker.$emit('pick', moment().format('YYYYMMDD'));
            that.formData.AAC01_END = moment().format('YYYYMMDD')
          }
        }, {
          text: '近7天',
          onClick(picker) {
            picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().format('YYYYMMDD')
          }
        }, {
          text: '近30天',
          onClick(picker) {
            picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().format('YYYYMMDD')
          }
        }, {
          text: '一季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().startOf('year').add(3,'M').subtract(1, 'days').format('YYYYMMDD')
          }
        },  {
          text: '二季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(3,'M').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().startOf('year').add(6,'M').subtract(1, 'days').format('YYYYMMDD')
          }
        },  {
          text: '三季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(6,'M').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().startOf('year').add(9,'M').subtract(1, 'days').format('YYYYMMDD')
          }
        },  {
          text: '四季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(9,'M').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().startOf('year').add(12,'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: moment().add(-2,'Y').format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().add(-2,'Y').startOf('year').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().add(-2,'Y').endOf('year').format('YYYYMMDD')
          }
        }, {
          text: moment().add(-1,'Y').format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().add(-1,'Y').startOf('year').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().add(-1,'Y').endOf('year').format('YYYYMMDD')
          }
        }, {
          text: moment().format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().endOf('year').format('YYYYMMDD')
          }
        }]
      },
      AAB01PickerOptions: {
        disabledDate: (time) => {
          if (that.formData.AAB01_END != "") {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [{
          text: '今天',
          onClick(picker) {
            picker.$emit('pick', moment().format('YYYYMMDD'));
            that.formData.AAB01_END = moment().format('YYYYMMDD')
          }
        }, {
          text: '近7天',
          onClick(picker) {
            picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().format('YYYYMMDD')
          }
        }, {
          text: '近30天',
          onClick(picker) {
            picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().format('YYYYMMDD')
          }
        }, {
          text: '一季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().startOf('year').add(3,'M').subtract(1, 'days').format('YYYYMMDD')
          }
        },  {
          text: '二季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(3,'M').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().startOf('year').add(6,'M').subtract(1, 'days').format('YYYYMMDD')
          }
        },  {
          text: '三季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(6,'M').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().startOf('year').add(9,'M').subtract(1, 'days').format('YYYYMMDD')
          }
        },  {
          text: '四季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(9,'M').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().startOf('year').add(12,'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: moment().add(-2,'Y').format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().add(-2,'Y').startOf('year').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().add(-2,'Y').endOf('year').format('YYYYMMDD')
          }
        }, {
          text: moment().add(-1,'Y').format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().add(-1,'Y').startOf('year').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().add(-1,'Y').endOf('year').format('YYYYMMDD')
          }
        }, {
          text: moment().format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().endOf('year').format('YYYYMMDD')
          }
        }]
      },
    }
  },
  created() {
    this.getDeportmentList()
  },
  methods: {
    onSubmit() {
      this.$emit('search')
    },
    onReset() {
      this.$refs.filterListFormRef.resetFields();
      this.$emit('reset')
    },
    getDeportmentList() {
      this.$axios.get('/user/depDropDown').then(res => {
        const { data } = res
        this.departments = data;
      }).catch(error => {
        console.log(error)
      })
    }
  }
}
</script>

<style lang="scss" scoped></style>
