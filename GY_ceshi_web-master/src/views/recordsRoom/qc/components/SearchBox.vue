<template>
  <el-form style="width: 100%" ref="filterListFormRef" :model="formData" class="demo-form-inline" label-suffix=":"
    label-width="74px">
    <el-row :gutter="24">
      <el-col :span="8">
        <el-form-item label="出院日期">
          <div style="width: 100%;display: flex;gap: 5px;">
            <el-form-item prop="AAC01_START" style="flex-grow: 1">
              <el-date-picker style="width: 100%" v-model="formData.AAC01_START" type="date" placeholder="开始日期"
                :picker-options="AAC01PickerOptions" value-format="yyyyMMdd" format="yyyy年MM月dd日">
              </el-date-picker>
            </el-form-item>
            <el-form-item prop="AAC01_END" style="flex-grow: 1">
              <el-date-picker style="width: 100%" v-model="formData.AAC01_END" type="date" placeholder="结束日期"
                value-format="yyyyMMdd" format="yyyy年MM月dd日">
              </el-date-picker>
            </el-form-item>
          </div>
        </el-form-item>
      </el-col>
      <el-col :span="8">
        <el-form-item label="入院日期">
          <div style="width: 100%;display: flex;gap: 5px;">
            <el-form-item prop="AAB01_START" style="flex-grow: 1">
              <el-date-picker style="width: 100%" v-model="formData.AAB01_START" type="date" placeholder="开始日期"
                :picker-options="AAB01PickerOptions" value-format="yyyyMMdd" format="yyyy年MM月dd日">
              </el-date-picker>
            </el-form-item>
            <el-form-item prop="AAB01_END" style="flex-grow: 1">
              <el-date-picker style="width: 100%" v-model="formData.AAB01_END" type="date" placeholder="结束日期"
                value-format="yyyyMMdd" format="yyyy年MM月dd日">
              </el-date-picker>
            </el-form-item>
          </div>
        </el-form-item>
      </el-col>
      <el-col :span="6">
        <el-form-item label="病案号" prop="AAA28">
          <el-input v-model="formData.AAA28" placeholder="请输入"></el-input>
        </el-form-item>
      </el-col>

    </el-row>
    <el-row :gutter="24">

      <el-col :span="8">
        <el-form-item label="病历得分" >
          <div style="width: 100%;display: flex;gap: 5px;">
            <el-form-item prop="score_start" style="flex-grow: 1">
              <el-input placeholder="开始得分" style="width: 100%" v-model="formData.score_start">
              </el-input>
            </el-form-item>
            <el-form-item prop="score_end" style="flex-grow: 1">
              <el-input placeholder="结束得分" style="width: 100%" v-model="formData.score_end">
              </el-input>
            </el-form-item>
          </div>

        </el-form-item>
      </el-col>
      <el-col :span="6">
        <el-form-item label="出院科室" prop="AAC02C">
          <el-select style="width:100%" v-model="formData.AAC02C" filterable clearable placeholder="请选择">
            <el-option v-for="item of departments" :key="item.dep_id" :label="item.name" :value="item.dep_id" />
          </el-select>
        </el-form-item>
      </el-col>

      <el-col :span="6">
        <el-form-item label="病历等级" prop="score_lv ">
          <el-select style="width: 100%;" multiple collapse-tags  v-model="formData.score_lv" filterable clearable placeholder="请选择">
            <el-option label="甲" value="甲" />
            <el-option label="乙" value="乙" />
            <el-option label="丙" value="丙" />
          </el-select>
        </el-form-item>
      </el-col>
      
    </el-row>
    <el-row :gutter="24"> 
      <el-col :span="8">
        <el-form-item label="首页得分">
         <div style="width: 100%;display: flex;gap: 5px;">
            <el-form-item prop="home_ysz_score_start" style="flex-grow: 1">
              <el-input placeholder="开始得分" v-model="formData.home_ysz_score_start">
              </el-input>
            </el-form-item>
            <el-form-item prop="home_ysz_score_end" style="flex-grow: 1">
              <el-input placeholder="结束得分" v-model="formData.home_ysz_score_end">
              </el-input>
            </el-form-item>
          </div>

        </el-form-item>
      </el-col>
      <el-col :span="6">
        <el-form-item label="首页等级" prop="home_ysz_score_lv  ">
          <el-select style="width: 100%;" v-model="formData.home_ysz_score_lv " multiple collapse-tags  filterable clearable placeholder="请选择">
            <el-option label="优" value="优" />
            <el-option label="良" value="良" />
            <el-option label="中" value="中" />
            <el-option label="差" value="差" />
          </el-select>
        </el-form-item>
      </el-col>
      <el-col :span="6">
        <el-form-item label="审核状态" prop="review_status">
          <el-select style="width: 100%;" v-model="formData.review_status" filterable clearable placeholder="请选择">
            <el-option label="未审核" :value="0" />
            <el-option label="审核中" :value="1" />
            <el-option label="已通过" :value="2" />
            <el-option label="未通过" :value="3" />
          </el-select>
        </el-form-item>
      </el-col>
      <el-col :span="24">
        <el-form-item label="" prop="" style="float: right;">
          <el-button type="primary" @click="onSubmit">查询</el-button>
          <el-button @click="onReset">重置</el-button>
          <el-button type="primary" icon="el-icon-download" class="export-btn" @click="() =>{this.$emit('onExport',1)}">导出数据</el-button>
        </el-form-item>
      </el-col>
    </el-row>

  </el-form>
</template>
<script>
import moment from 'moment/moment';

export default {
  data() {
    const that = this
    return {
      formData: {
        AAC01_START: moment().subtract(30, 'days').format('YYYYMMDD'),
        AAC01_END: moment().format('YYYYMMDD'),
        AAB01_START: '',
        AAB01_END: '',
        AAA28: '',
        AAC02C: '',
        review_status: '',
        score_lv: [],
        home_ysz_score_lv: [],
        score_start: '',
        score_end: '',
        home_ysz_score_start: '',
        home_ysz_score_end: ''
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
            that.formData.AAC01_END = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: '二季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(3, 'M').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: '三季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(6, 'M').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: '四季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(9, 'M').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().startOf('year').add(12, 'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: moment().add(-2, 'Y').format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().add(-2, 'Y').startOf('year').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD')
          }
        }, {
          text: moment().add(-1, 'Y').format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().add(-1, 'Y').startOf('year').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD')
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
            that.formData.AAB01_END = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: '二季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(3, 'M').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: '三季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(6, 'M').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: '四季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(9, 'M').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().startOf('year').add(12, 'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: moment().add(-2, 'Y').format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().add(-2, 'Y').startOf('year').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD')
          }
        }, {
          text: moment().add(-1, 'Y').format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().add(-1, 'Y').startOf('year').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD')
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
